<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Jobs\PushWalletPassUpdate;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletPass;
use Illuminate\Support\Str;

/**
 * Apple Wallet's part of the WalletPassManager (which decides for every provider and calls this for Apple passes).
 * Issues Apple Wallet passes to ticket holders and keeps them in step with the ticket record: when an order or its
 * event changes in a way the pass shows, the pass is marked modified and every registered device is told to fetch it
 * again (PushWalletPassUpdate). The pass content is always rebuilt from the record (AppleWalletPassBuilder), so the
 * admission credential only ever changes through the order's own token lifecycle (reissue, revoke, transfer).
 */
final class WalletPassService
{
    /** Order fields a pass shows or depends on. */
    public const ORDER_FIELDS = ['status', 'secure_ticket_token_hash', 'token_version', 'token_revoked_at', 'user_id',
        'customer_name', 'checked_in_at', 'ticket_code', 'event_id'];

    /** Event fields a pass shows. */
    public const EVENT_FIELDS = ['title', 'starts_at', 'ends_at', 'event_date_iso', 'date_str', 'time_str', 'timezone',
        'venue_name', 'venue_building_name', 'street_address', 'city', 'state_code', 'zip_code', 'latitude', 'longitude',
        'status', 'organizer_name', 'flyer_path', 'flyer_position_x', 'flyer_position_y'];

    public function __construct(
        private readonly AppleWalletConfig $config,
        private readonly WalletEligibilityService $eligibility,
    ) {}

    /** Why this holder can't add the ticket to Apple Wallet now, or null when they can (WalletEligibilityService). */
    public function refusal(Order $order, User $user): ?string
    {
        if (!$this->config->enabled()) {
            return $this->eligibility->message('unavailable', 'Apple Wallet');
        }
        $reason = $this->eligibility->reason($order, $user);

        return $reason === null ? null : $this->eligibility->message($reason, 'Apple Wallet');
    }

    /** The holder's current pass for the order, created on first download. */
    public function passFor(Order $order, User $user): WalletPass
    {
        $pass = WalletPass::where('order_id', $order->id)->where('user_id', $user->id)->whereNull('voided_at')
            ->where('provider', 'apple')->where('pass_type_id', $this->config->passTypeId())->first();

        return $pass ?? WalletPass::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'provider' => 'apple',
            'pass_type_id' => $this->config->passTypeId(),
            'serial_number' => (string) Str::uuid(),
            'authentication_token' => Str::random(48),
            'last_modified_at' => now()->startOfSecond(),
        ]);
    }

    /** An order changed: void passes of a former holder, mark the rest modified, and tell their devices. */
    public function orderChanged(Order $order, array $changed): void
    {
        if (array_intersect($changed, self::ORDER_FIELDS) === []) {
            return;
        }
        $passes = WalletPass::where('order_id', $order->id)->where('provider', 'apple')->get();
        foreach ($passes as $pass) {
            if ($pass->voided_at === null && $order->user_id !== null && (int) $pass->user_id !== (int) $order->user_id) {
                // Transferred: the former holder's pass becomes void, with no barcode, on their next refresh.
                $pass->forceFill(['voided_at' => now(), 'void_reason' => 'transferred']);
            }
            $this->modified($pass);
        }
    }

    /** An event changed (time, venue, title, cancellation…): update every pass for its tickets. */
    public function eventChanged(Event $event, array $changed): void
    {
        if (array_intersect($changed, self::EVENT_FIELDS) === []) {
            return;
        }
        $orderIds = Order::withoutGlobalScopes()->where('event_id', $event->id)->pluck('id');
        WalletPass::whereIn('order_id', $orderIds)->where('provider', 'apple')->chunkById(200, function ($passes): void {
            foreach ($passes as $pass) {
                $this->modified($pass);
            }
        });
    }

    private function modified(WalletPass $pass): void
    {
        $pass->touchModified();
        if ($pass->registrations()->exists()) {
            PushWalletPassUpdate::dispatch($pass->id)->afterCommit();
        }
    }
}
