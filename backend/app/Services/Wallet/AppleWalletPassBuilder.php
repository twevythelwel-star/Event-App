<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\Event;
use App\Models\Order;
use App\Models\WalletPass;
use App\Services\ReceiptService;
use App\Support\TicketLifecycle;
use Illuminate\Support\Carbon;
use PKPass\PKPass;

/**
 * Builds and signs an Apple Wallet event-ticket pass from the authoritative ticket record, every time it is asked
 * for, so a pass is never out of step with the server:
 *
 *   - The barcode is the order's current admission token (Order::deliverTicketQrToken), shown only while the ticket
 *     is valid for entry and still belongs to the pass holder. A reissue changes it; a revoked, refunded, cancelled
 *     or transferred ticket gets a void pass with no barcode at all.
 *   - After the event the pass expires (expirationDate) and carries no barcode: it is never an admission credential.
 *
 * Wallet shows a clean entry pass; the richer collectible ticket stays in the EventBite app.
 */
final class AppleWalletPassBuilder
{
    public function __construct(
        private readonly AppleWalletConfig $config,
        private readonly AppleWalletImages $images,
    ) {}

    /** @return array{status: string, code: string, barcode: bool, voided: bool} */
    public function state(WalletPass $pass, Order $order): array
    {
        return WalletTicketState::of($pass, $order);
    }

    /** The pass.json content. */
    public function passData(WalletPass $pass, Order $order): array
    {
        $event = $order->event;
        $state = $this->state($pass, $order);
        $tz = $event?->timezone ?: 'America/New_York';
        $tickets = $order->items->where('category', 'Tickets');
        $ticketType = $tickets->isNotEmpty()
            ? $tickets->map(fn ($i) => ($i->quantity > 1 ? $i->quantity . ' × ' : '') . $i->name)->join(', ')
            : ($order->items->isNotEmpty() ? 'Food pre-order' : 'Event pass');
        $title = (string) ($event?->title ?: 'EventBite event');

        $when = $this->whenField($event, $tz);
        // The name on the front; the full address goes on the back.
        $venue = (string) ($event?->venue_name ?: ($event ? ReceiptService::venue($event) : ''));
        $address = $event ? collect([$event->street_address, $event->city, $event->state_code, $event->zip_code])->filter()->implode(', ') : '';

        $data = [
            'formatVersion' => 1,
            'passTypeIdentifier' => $this->config->passTypeId(),
            'serialNumber' => $pass->serial_number,
            'teamIdentifier' => $this->config->teamId(),
            'organizationName' => $this->config->organizationName(),
            'description' => 'EventBite ticket: ' . $title,
            'foregroundColor' => 'rgb(248, 250, 252)',
            'backgroundColor' => 'rgb(7, 17, 31)',
            'labelColor' => 'rgb(199, 255, 69)',
            'sharingProhibited' => true,
            'eventTicket' => [
                'headerFields' => [[
                    'key' => 'status', 'label' => 'STATUS', 'value' => $state['status'],
                    'changeMessage' => 'Your ticket is now: %@',
                ]],
                'primaryFields' => [['key' => 'event', 'label' => 'EVENT', 'value' => $title, 'changeMessage' => 'Event renamed to %@']],
                'secondaryFields' => array_values(array_filter([
                    $when,
                    $venue !== '' ? ['key' => 'venue', 'label' => 'VENUE', 'value' => $venue, 'changeMessage' => 'Venue changed to %@'] : null,
                ])),
                'auxiliaryFields' => [
                    ['key' => 'type', 'label' => 'TICKET', 'value' => $ticketType],
                    ['key' => 'number', 'label' => 'TICKET NO.', 'value' => (string) $order->ticket_code],
                ],
                'backFields' => array_values(array_filter([
                    $order->customer_name ? ['key' => 'holder', 'label' => 'Ticket holder', 'value' => (string) $order->customer_name] : null,
                    $address !== '' ? ['key' => 'address', 'label' => 'Address', 'value' => $address] : null,
                    ['key' => 'order', 'label' => 'Order', 'value' => (string) $order->order_number],
                    ['key' => 'entry', 'label' => 'Entry', 'value' => 'Show this pass at the gate. The QR code admits one entry: only the first scan is accepted. If the organizer replaces or cancels your ticket, this pass updates.'],
                    ['key' => 'ticket', 'label' => 'Full ticket', 'value' => route('tickets.show', $order), 'attributedValue' => '<a href="' . e(route('tickets.show', $order)) . '">Open in EventBite</a>'],
                    $event?->organizer_name ? ['key' => 'organizer', 'label' => 'Organizer', 'value' => (string) $event->organizer_name] : null,
                ])),
            ],
        ];

        if ($state['barcode']) {
            $data['barcodes'] = [[
                'format' => 'PKBarcodeFormatQR',
                'message' => $order->deliverTicketQrToken(),
                'messageEncoding' => 'iso-8859-1',
                'altText' => (string) $order->ticket_code,
            ]];
        }
        if ($state['voided']) {
            $data['voided'] = true;
        }
        if ($event?->starts_at) {
            $data['relevantDate'] = $event->starts_at->copy()->setTimezone($tz)->toIso8601String();
        }
        if (($expires = TicketLifecycle::overAt($event)) !== null) {
            $data['expirationDate'] = $expires->toIso8601String();
        }
        if ($event && $event->latitude !== null && $event->longitude !== null && is_numeric($event->latitude) && is_numeric($event->longitude)) {
            $data['locations'] = [['latitude' => (float) $event->latitude, 'longitude' => (float) $event->longitude, 'relevantText' => $title . ' is nearby']];
        }
        if (($url = $this->config->webServiceUrl()) !== null) {
            $data['webServiceURL'] = $url;
            $data['authenticationToken'] = (string) $pass->authentication_token;
        }

        return $data;
    }

    /** The signed .pkpass bytes. */
    public function package(WalletPass $pass, Order $order): string
    {
        $certs = $this->config->certificate();
        $p12 = $this->config->certificateP12();
        if ($certs === null || $p12 === null) {
            throw new \RuntimeException('Apple Wallet is not configured (php artisan wallet:check).');
        }

        $pkpass = new PKPass();
        $pkpass->setCertificateString($p12);
        $pkpass->setCertificatePassword($this->config->certificatePassword());
        $pkpass->setWwdrCertificatePath($this->config->wwdrCertificatePath());
        $pkpass->setTempPath(sys_get_temp_dir());
        $pkpass->setData($this->passData($pass, $order));
        foreach ($this->images->forEvent($order->event) as $name => $bytes) {
            $pkpass->addFileContent($bytes, $name);
        }

        return (string) $pkpass->create(false);
    }

    /** @return array<string, string>|null The date/time field: a real date Wallet can format and localise. */
    private function whenField(?Event $event, string $tz): ?array
    {
        if ($event?->starts_at) {
            return ['key' => 'when', 'label' => 'DATE & TIME', 'value' => $event->starts_at->copy()->setTimezone($tz)->toIso8601String(),
                'dateStyle' => 'PKDateStyleMedium', 'timeStyle' => 'PKDateStyleShort', 'changeMessage' => 'Event time changed to %@'];
        }
        $date = substr((string) ($event?->event_date_iso ?? ''), 0, 10);
        if ($date !== '') {
            return ['key' => 'when', 'label' => 'DATE', 'value' => Carbon::parse($date, $tz)->toIso8601String(),
                'dateStyle' => 'PKDateStyleMedium', 'timeStyle' => 'PKDateStyleNone', 'ignoresTimeZone' => true,
                'changeMessage' => 'Event date changed to %@'];
        }
        $text = $event ? collect([ReceiptService::eventDate($event), ReceiptService::eventTime($event)])->filter()->implode(' · ') : '';

        return $text !== '' ? ['key' => 'when', 'label' => 'DATE & TIME', 'value' => $text, 'changeMessage' => 'Event time changed to %@'] : null;
    }
}
