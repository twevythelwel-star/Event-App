<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\DiscountCode;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TicketHold;
use App\Models\WaitlistEntry;
use App\Services\Checkout\ComplimentaryTicketService;
use App\Services\Checkout\TicketingStudioService;
use App\Services\Checkout\TicketPricing;
use App\Services\Checkout\WaitlistService;
use App\Services\EventManagementService;
use App\Http\Requests\UpdateTicketQrDesignRequest;
use App\Services\EventQr\TicketQrDesignSystem;
use App\Services\OrderPlacementException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The host's ticketing studio: smarter pricing per ticket type, discount codes, promoters and their sales,
 * complimentary tickets, the waitlist, and transfer settings. Authorization and every rule live in the services.
 */
class TicketingStudioController extends Controller
{
    private const TABS = ['pricing', 'codes', 'promoters', 'complimentary', 'waitlist', 'qr', 'settings'];

    public function __construct(
        private readonly EventManagementService $events,
        private readonly TicketingStudioService $ticketing,
    ) {}

    public function show(Request $request, string $id): View
    {
        $event = $this->events->findEvent($request->user(), $id);

        return view('events.ticketing-studio', [
            'event' => $event,
            'tab' => in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'pricing',
            'tiers' => collect(is_array($event->ticket_tiers) ? $event->ticket_tiers : [])->filter(fn ($t) => isset($t['id']))->values(),
            'sold' => TicketPricing::soldByTier($event),
            'held' => TicketHold::active()->where('event_id', $event->id)->selectRaw('ticket_tier_id, SUM(quantity) as n')->groupBy('ticket_tier_id')->pluck('n', 'ticket_tier_id'),
            'menu' => MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereNull('deleted_at')->where('item_type', '!=', 'package')->orderBy('name')->get(),
            'codes' => DiscountCode::with('affiliate')->where('event_id', $event->id)->orderBy('code')->get(),
            'affiliates' => Affiliate::where('event_id', $event->id)->orderBy('name')->get(),
            'report' => $this->ticketing->affiliateReport($event),
            'comps' => Order::withoutGlobalScopes()->where('event_id', $event->id)->where('is_complimentary', true)->with('items')->latest()->get(),
            'waitlist' => WaitlistEntry::where('event_id', $event->id)->whereIn('status', ['waiting', 'offered'])->orderBy('created_at')->get(),
            'currency' => $event->currency ?: 'USD',
            'tz' => $event->timezone ?: config('app.timezone'),
            'qr' => $request->query('tab') === 'qr' ? $this->qrStudio($event) : null,
        ]);
    }

    /**
     * Ticket QR design: the host picks one of the approved presets (and, for the Cultural Celebration preset, the
     * event's own colours). A choice that would make the code hard to scan is refused with the reason.
     */
    public function saveQrDesign(UpdateTicketQrDesignRequest $request, string $id, TicketQrDesignSystem $designs): RedirectResponse
    {
        $event = $this->events->findEvent($request->user(), $id);
        $choice = $request->choice();

        if ($problem = $designs->choiceProblem($event, $choice)) {
            return redirect()->route('events.ticketing', ['id' => $id, 'tab' => 'qr'])->withErrors(['ticketing' => $problem])->withInput();
        }

        $event->forceFill(['ticket_qr_design' => $choice])->save();
        \App\Services\AuditLogService::log(action: 'ticket_qr_design_changed', targetEntity: 'events', targetId: (string) $event->id,
            details: 'Ticket QR design set to ' . ($choice['preset'] === 'auto' ? 'automatic' : TicketQrDesignSystem::PRESETS[$choice['preset']]['label']) . '.',
            actor: $request->user(), organizerId: (string) $event->organizer_id, eventId: (string) $event->id);

        return redirect()->route('events.ticketing', ['id' => $id, 'tab' => 'qr'])
            ->with('status', 'Ticket QR design saved. Guests’ QR codes use it the next time their ticket opens.');
    }

    /** @return array<string, mixed> */
    private function qrStudio(\App\Models\Event $event): array
    {
        $designs = app(TicketQrDesignSystem::class);
        $qr = app(\App\Services\EventQr\TicketQrService::class);
        $controls = $designs->controls();
        $previews = [];
        foreach ($designs->availablePresets() as $key => $label) {
            $previews[$key] = $qr->previewSvg($designs->resolve($event, ['preset' => $key]));
        }
        $current = $designs->design($event);

        return [
            'controls' => $controls,
            'presets' => $designs->availablePresets(),
            'previews' => $previews,
            'choice' => is_array($event->ticket_qr_design) ? $event->ticket_qr_design : ['preset' => 'auto'],
            'auto' => TicketQrDesignSystem::presetForTheme($event),
            'current' => $current,
            'currentPreview' => $qr->previewSvg($current),
            'report' => $designs->readability($current),
        ];
    }

    public function savePricing(Request $request, string $id, string $tierId): RedirectResponse
    {
        $data = $request->validate([
            'price_steps' => ['nullable', 'array', 'max:10'], 'price_steps.*.up_to' => ['nullable', 'integer', 'min:1'], 'price_steps.*.price' => ['nullable', 'numeric', 'min:0'],
            'early_bird_price' => ['nullable', 'numeric', 'min:0'], 'early_bird_ends' => ['nullable', 'date'],
            'releases' => ['nullable', 'array', 'max:10'], 'releases.*.quantity' => ['nullable', 'integer', 'min:1'], 'releases.*.opens' => ['nullable', 'date'],
            'group_discounts' => ['nullable', 'array', 'max:5'], 'group_discounts.*.min_quantity' => ['nullable', 'integer'], 'group_discounts.*.percent' => ['nullable', 'integer'],
            'bundle_items' => ['nullable', 'array', 'max:10'], 'bundle_items.*.menu_item_id' => ['nullable', 'string', 'max:64'], 'bundle_items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        return $this->run($id, 'pricing', fn () => $this->ticketing->saveTierPricing($request->user(), $id, $tierId, $data), 'Ticket pricing saved.');
    }

    public function saveCode(Request $request, string $id, ?string $codeId = null): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'], 'description' => ['nullable', 'string', 'max:200'], 'kind' => ['required', 'in:percent,fixed'],
            'amount' => ['required', 'numeric', 'min:0'], 'applies_to' => ['required', 'in:tickets,food,all'],
            'ticket_tier_ids' => ['nullable', 'array'], 'ticket_tier_ids.*' => ['string', 'max:64'],
            'min_tickets' => ['nullable', 'integer', 'min:1'], 'max_uses' => ['nullable', 'integer', 'min:1'], 'max_uses_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date'], 'affiliate_id' => ['nullable', 'string', 'max:64'],
        ]);
        $data['is_active'] = !$request->has('is_active_submitted') || $request->boolean('is_active');

        return $this->run($id, 'codes', fn () => $this->ticketing->saveCode($request->user(), $id, $codeId, $data), $codeId ? 'Code saved.' : 'Code created.');
    }

    public function deleteCode(Request $request, string $id, string $codeId): RedirectResponse
    {
        return $this->run($id, 'codes', fn () => $this->ticketing->deleteCode($request->user(), $id, $codeId), 'Code removed.');
    }

    public function saveAffiliate(Request $request, string $id, ?string $affiliateId = null): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'code' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'], 'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:50']]);
        $data['is_active'] = !$request->has('is_active_submitted') || $request->boolean('is_active');

        return $this->run($id, 'promoters', fn () => $this->ticketing->saveAffiliate($request->user(), $id, $affiliateId, $data), 'Promoter saved.');
    }

    public function issueComplimentary(Request $request, string $id, ComplimentaryTicketService $comps): RedirectResponse
    {
        $data = $request->validate(['tier_id' => ['required', 'string', 'max:64'], 'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'email' => ['required', 'email', 'max:190'], 'reason' => ['required', 'string', 'min:3', 'max:200']]);

        return $this->run($id, 'complimentary', fn () => $comps->issue($request->user(), $id, $data['tier_id'], (int) $data['quantity'], $data['email'], $data['reason']),
            'Complimentary tickets issued. They are in the guest\'s EventBite wallet.');
    }

    public function offerWaitlist(Request $request, string $id, WaitlistService $waitlists): RedirectResponse
    {
        $event = $this->events->findEvent($request->user(), $id);
        $offers = $waitlists->offerFreedTickets($event);

        return redirect()->route('events.ticketing', ['id' => $id, 'tab' => 'waitlist'])
            ->with('status', $offers > 0 ? "{$offers} waitlist " . ($offers === 1 ? 'offer' : 'offers') . ' made.' : 'No tickets are free to offer right now.');
    }

    public function saveSettings(Request $request, string $id): RedirectResponse
    {
        return $this->run($id, 'settings', fn () => $this->ticketing->saveSettings($request->user(), $id, $request->boolean('allow_ticket_transfers'), $request->boolean('waitlist_enabled')), 'Ticketing settings saved.');
    }

    private function run(string $id, string $tab, callable $change, string $message): RedirectResponse
    {
        $back = redirect()->route('events.ticketing', ['id' => $id, 'tab' => $tab]);
        try {
            $change();
        } catch (\InvalidArgumentException|OrderPlacementException $e) {
            return $back->withErrors(['ticketing' => $e->getMessage()])->withInput();
        }

        return $back->with('status', $message);
    }
}
