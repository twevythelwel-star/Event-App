<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UploadFlyerRequest;
use App\Models\Event;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventController extends Controller
{
    /**
     * Display a listing of events for the active organizer.
     * Enforces configurable browsing policy (require_login_for_browsing).
     */
    public function index(Request $request): mixed
    {
        if (config('events.require_login_for_browsing', false) && !auth()->check()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Authentication required: Please sign in to browse events.'], 401);
            }
            return redirect()->guest(route('login'))->with('error', 'Please sign in to browse events.');
        }

        $events = Event::latest()->get();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $events,
            ]);
        }

        return view('events.index', compact('events'));
    }

    /**
     * Display the specified event.
     * Enforces configurable browsing policy (require_login_for_browsing).
     */
    public function show(Request $request, string $id): mixed
    {
        if (config('events.require_login_for_browsing', false) && !auth()->check()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Authentication required: Please sign in to view this event.'], 401);
            }
            return redirect()->guest(route('login'))->with('error', 'Please sign in to view this event.');
        }

        $event = Event::with('menuItems')->findOrFail($id);

        // Only published events are public. Draft, unpublished and cancelled events are visible to the people
        // who manage them, and behave as not found for everyone else (including via an event QR short link).
        if ($event->status !== 'published' && !$this->canManage($request, $event)) {
            abort(404);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'event' => $event,
                    'is_ordering_allowed' => $event->isOrderingAllowed(),
                ],
            ]);
        }

        // Real ticket types and the lowest price to show as "From"; ordering itself happens in CheckoutController.
        $currency = $event->currency ?: 'JMD';
        $tiers = \App\Services\Checkout\TicketTierCatalog::sellable($event);
        $prices = array_merge(
            array_column($tiers, 'price'),
            $event->menuItems->where('is_active', true)->map(fn ($item) => (float) ($currency === 'USD' ? $item->price_usd : $item->price_jmd))->filter(fn ($p) => $p > 0)->all(),
        );
        $fromPrice = $prices === [] ? null : min($prices);

        return view('events.show', compact('event', 'tiers', 'fromPrice', 'currency'));
    }

    private function canManage(Request $request, Event $event): bool
    {
        if (!$request->user()) {
            return false;
        }

        try {
            $this->managedEvent($request, (string) $event->id);

            return true;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return false;
        }
    }

    /**
     * The event, if the signed-in user may manage it (their organizer's event, or any event for a System
     * Administrator). Anyone else gets "not found", the same as for an event that does not exist.
     */
    private function managedEvent(Request $request, string $id): Event
    {
        $user = $request->user();
        if (!$user) {
            abort(404);
        }

        return app(\App\Services\EventManagementService::class)->findEvent($user, $id);
    }

    /**
     * The organizer a new event belongs to: the active organizer when the user may manage it, otherwise the
     * user's only manageable organizer. Null when that is not clear (no organizer, or several and none chosen).
     */
    private function organizerForNewEvent(Request $request): ?string
    {
        $manageable = app(\App\Services\EventManagementService::class)->manageableOrganizerIds($request->user());
        $active = TenantContext::id();

        if ($manageable === null) {
            return $active; // System Administrators act for the organizer they have switched into
        }
        if ($active !== null && $manageable->contains($active)) {
            return $active;
        }

        return $manageable->count() === 1 ? (string) $manageable->first() : null;
    }

    /**
     * Show the form for creating a new event.
     * Requires active Event Host privileges.
     */
    public function create(Request $request): mixed
    {
        $user = $request->user();
        if ($user && !$user->hasRole(\App\Enums\AccountType::EVENT_HOST) && !$user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Forbidden: You must be an approved Event Host to create events.'], 403);
            }
            return redirect()->route('host.apply')->with('error', 'You must apply and be approved as an Event Host to create events.');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Event creation form metadata',
            ]);
        }

        return view('events.create');
    }

    /**
     * Show the form for editing an existing event.
     */
    public function edit(Request $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $event,
            ]);
        }

        // Only managers reach this screen (managedEvent). ensureFor() also creates the QR identity for events
        // made before QR codes existed.
        $qrService = app(\App\Services\EventQr\EventQrService::class);
        $qr = $qrService->ensureFor($event);
        $qrDestination = $qrService->destinationUrl($qr);

        return view('events.edit', compact('event', 'qr', 'qrDestination'));
    }

    /**
     * Store a newly created event for the active organizer.
     */
    public function store(StoreEventRequest $request): mixed
    {
        $user = $request->user() ?? (function_exists('auth') ? auth()->user() : null);
        if ($user && !$user->hasRole(\App\Enums\AccountType::EVENT_HOST) && !$user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Forbidden: You must be an approved Event Host to create events.'], 403);
            }
            return redirect()->route('host.apply')->with('error', 'You must apply and be approved as an Event Host to create events.');
        }

        $validated = $request->validated();
        $organizerId = $user ? $this->organizerForNewEvent($request) : null;
        if ($organizerId === null) {
            $message = 'Choose the organizer this event belongs to. Your account is not linked to exactly one organizer you can manage.';
            if ($request->wantsJson()) {
                return response()->json(['error' => $message], 403);
            }
            abort(403, $message);
        }
        $validated['organizer_id'] = $organizerId;

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(6);
        }

        $event = Event::create($validated);

        if ($request->hasFile('flyer')) {
            $file = $request->file('flyer');
            $tenantId = $event->organizer_id;
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = time() . '_' . Str::random(16) . '.' . strtolower($ext);
            $storagePath = "tenants/{$tenantId}/flyers/{$filename}";

            Storage::disk('s3_flyers')->put($storagePath, file_get_contents($file));

            $event->flyer_path = $storagePath;
            $event->flyer_mime_type = $file->getClientMimeType() ?: 'image/jpeg';
            $event->flyer_size_bytes = $file->getSize() ?: 0;
            $event->is_flyer_public = ($event->status === 'published');
            $event->save();
        }

        // Every new event gets its promotional QR identity and artwork. Failure here never fails the event:
        // the identity is kept as "failed" and the host can retry from the event screen.
        app(\App\Services\EventQr\EventQrService::class)->ensureFor($event);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Event created successfully',
                'data' => $event,
            ], 201);
        }

        return redirect()->route('host.dashboard')->with('status', "Event '{$event->title}' created successfully.");
    }

    /**
     * Update the specified event.
     */
    public function update(StoreEventRequest $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);
        $event->update($request->validated());

        if ($request->hasFile('flyer')) {
            $file = $request->file('flyer');
            $tenantId = TenantContext::id() ?? $event->organizer_id;
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = time() . '_' . Str::random(16) . '.' . strtolower($ext);
            $storagePath = "tenants/{$tenantId}/flyers/{$filename}";

            $oldPath = $event->flyer_path;
            Storage::disk('s3_flyers')->put($storagePath, file_get_contents($file));

            if ($oldPath && $oldPath !== $storagePath && Storage::disk('s3_flyers')->exists($oldPath)) {
                Storage::disk('s3_flyers')->delete($oldPath);
            }

            $event->flyer_path = $storagePath;
            $event->flyer_mime_type = $file->getClientMimeType() ?: 'image/jpeg';
            $event->flyer_size_bytes = $file->getSize() ?: 0;
            $event->is_flyer_public = ($event->status === 'published');
            $event->save();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully',
                'data' => $event,
            ]);
        }

        return redirect()->route('host.dashboard')->with('status', "Event '{$event->title}' updated successfully.");
    }

    /**
     * Toggle advance ordering status.
     */
    public function toggleOrdering(Request $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);
        $event->is_ordering_open = !$event->is_ordering_open;
        $event->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $event->is_ordering_open ? 'Ordering opened' : 'Ordering closed',
                'is_ordering_open' => $event->is_ordering_open,
            ]);
        }

        return back()->with('status', $event->is_ordering_open ? 'Advance ordering opened.' : 'Advance ordering closed.');
    }

    /**
     * Upload an event flyer with tenant-scoped isolation.
     */
    public function uploadFlyer(UploadFlyerRequest $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);
        $file = $request->file('flyer');

        $tenantId = TenantContext::id() ?? $event->organizer_id;
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = time() . '_' . Str::random(16) . '.' . strtolower($ext);
        $storagePath = "tenants/{$tenantId}/flyers/{$filename}";

        $oldPath = $event->flyer_path;

        // Store file via Laravel filesystem abstraction (local disk or S3/Cloudflare R2)
        Storage::disk('s3_flyers')->put($storagePath, file_get_contents($file));

        // Safely remove superseded previous flyer file if present
        if ($oldPath && $oldPath !== $storagePath && Storage::disk('s3_flyers')->exists($oldPath)) {
            Storage::disk('s3_flyers')->delete($oldPath);
        }

        $event->flyer_path = $storagePath;
        $event->flyer_mime_type = $file->getClientMimeType() ?: 'image/jpeg';
        $event->flyer_size_bytes = $file->getSize() ?: 0;
        $event->is_flyer_public = $event->status === 'published';
        $event->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Flyer uploaded successfully',
                'flyer_path' => $storagePath,
                'flyer_url' => Storage::disk('s3_flyers')->url($storagePath),
                'event' => $event,
            ]);
        }

        return back()->with('status', 'Flyer uploaded successfully.');
    }

    /**
     * Remove the promotional flyer from the event.
     */
    public function removeFlyer(Request $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);

        if ($event->flyer_path && Storage::disk('s3_flyers')->exists($event->flyer_path)) {
            Storage::disk('s3_flyers')->delete($event->flyer_path);
        }

        $event->flyer_path = null;
        $event->flyer_thumbnail_path = null;
        $event->flyer_mime_type = null;
        $event->flyer_size_bytes = null;
        $event->flyer_width = null;
        $event->flyer_height = null;
        $event->is_flyer_public = false;
        $event->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Flyer removed successfully',
                'event' => $event,
            ]);
        }

        return back()->with('status', 'Flyer removed successfully.');
    }

    /**
     * Publish the event, making it publicly discoverable.
     */
    public function publish(Request $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);
        $event->status = 'published';
        $event->is_ordering_open = true;
        if ($event->flyer_path) {
            $event->is_flyer_public = true;
        }
        $event->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Event published successfully. Now visible to the public.',
                'event' => $event,
                'shareable_url' => $event->shareable_url,
                'social_meta' => $event->social_metadata,
            ]);
        }

        return back()->with('status', "Event '{$event->title}' published successfully.");
    }

    /**
     * Unpublish the event, temporarily withdrawing it from public view.
     */
    public function unpublish(Request $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);
        $event->status = 'unpublished';
        $event->is_ordering_open = false;
        $event->is_flyer_public = false;
        $event->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Event unpublished. Hidden from anonymous visitors.',
                'event' => $event,
            ]);
        }

        return back()->with('status', "Event '{$event->title}' unpublished.");
    }

    /**
     * Cancel the event.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $event = $this->managedEvent($request, $id);
        $event->status = 'cancelled';
        $event->is_ordering_open = false;
        $event->save();

        return response()->json([
            'success' => true,
            'message' => 'Event marked as cancelled.',
            'event' => $event,
        ]);
    }

    /**
     * Public-facing event page view (No authentication required).
     */
    public function publicShow(string $slugOrId): JsonResponse
    {
        $event = Event::withoutGlobalScopes()
            ->where('status', 'published')
            ->where(function ($q) use ($slugOrId) {
                $q->where('slug', $slugOrId)->orWhere('id', $slugOrId);
            })
            ->with(['menuItems' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }])
            ->first();

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found or not currently published.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'event' => $event,
                'is_ordering_allowed' => $event->isOrderingAllowed(),
                'shareable_url' => $event->shareable_url,
                'social_meta' => $event->social_metadata,
            ],
        ]);
    }

    /**
     * Owner Preview Mode (Requires authenticated owner or platform admin).
     */
    public function preview(string $id): JsonResponse
    {
        $event = Event::with('menuItems')->findOrFail($id);

        return response()->json([
            'success' => true,
            'preview_mode' => true,
            'data' => [
                'event' => $event,
                'is_ordering_allowed' => $event->isOrderingAllowed(),
                'shareable_url' => $event->shareable_url,
                'social_meta' => $event->social_metadata,
                'notice' => "Owner Preview Mode: Event is in '{$event->status}' state.",
            ],
        ]);
    }
}
