@extends('layouts.admin')

@section('title', $event->title)
@section('breadcrumbs')
    <span aria-hidden="true">›</span><a href="{{ route('admin.console.events.index') }}">Events</a>
    <span aria-hidden="true">›</span><span aria-current="page">{{ $event->title }}</span>
@endsection
@section('heading', $event->title)
@section('subheading')Hosted by {{ $event->organizer?->name ?? $event->organizer_name }}.@endsection
@section('actions')
    @if($canSeePayments)
        <a class="ac-btn" href="{{ route('admin.console.orders.index', ['event' => $event->id]) }}">View orders</a>
    @endif
@endsection

@section('content')
    @php $statusTone = ['published' => 'success', 'draft' => '', 'unpublished' => 'warning', 'cancelled' => 'danger']; @endphp
    <section class="ac-card" aria-labelledby="gates-heading" style="margin-bottom:16px" id="publication-gates">
        <div class="ac-card-head"><h2 id="gates-heading">Compliance publication gates</h2>@can(\App\Support\AdminAuthorization::COMPLIANCE_SUSPEND_EVENT, $event)<a class="ac-link" href="{{ route('admin.console.compliance-centre.declaration', $event->id) }}">Declarations and suspension</a>@endcan
            @php $openGates = collect($gates)->whereNull('override')->count(); @endphp
            <span class="ac-badge {{ $openGates ? 'danger' : 'success' }}">{{ $openGates ? $openGates . ' blocking' : 'None blocking' }}</span></div>
        <div class="ac-card-body">
            @error('gate_code')<div class="ac-alert error" role="alert">{{ $message }}</div>@enderror
            @error('reason')<div class="ac-alert error" role="alert">{{ $message }}</div>@enderror
            @error('review_by')<div class="ac-alert error" role="alert">{{ $message }}</div>@enderror
            @error('confirm')<div class="ac-alert error" role="alert">{{ $message }}</div>@enderror
            @error('revoke_reason')<div class="ac-alert error" role="alert">{{ $message }}</div>@enderror
            @forelse($gates as $gate)
                <div style="padding:10px 0;border-bottom:1px solid var(--ac-line)">
                    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
                        <span>{{ $gate['message'] }}</span>
                        @if($gate['override'])
                            <span class="ac-badge warning">Overridden until {{ $gate['override']->review_by->format('j M Y') }}</span>
                        @else
                            <span class="ac-badge danger">{{ $gate['overridable'] ? 'Blocking' : 'Blocking · cannot be overridden' }}</span>
                        @endif
                    </div>
                    @if($canOverride && $gate['overridable'] && !$gate['override'])
                        <details style="margin-top:8px">
                            <summary class="ac-link">Override this requirement</summary>
                            <form method="POST" action="{{ route('admin.console.compliance.overrides.store', $event->id) }}" style="margin-top:8px">
                                @csrf
                                <input type="hidden" name="gate_code" value="{{ $gate['code'] }}">
                                <div class="ac-form-row">
                                    <div class="ac-field grow"><label for="reason-{{ md5($gate['code']) }}">Documented reason</label>
                                        <textarea id="reason-{{ md5($gate['code']) }}" class="ac-textarea" name="reason" rows="2" required minlength="20" maxlength="2000" placeholder="What you checked and why the event may proceed"></textarea></div>
                                    <div class="ac-field"><label for="review-{{ md5($gate['code']) }}">Expires (review by)</label>
                                        <input id="review-{{ md5($gate['code']) }}" class="ac-input" type="date" name="review_by" required min="{{ now()->addDay()->toDateString() }}" max="{{ now()->addDays(\App\Http\Controllers\Admin\CompliancePublicationOverrideController::MAX_DAYS)->toDateString() }}"></div>
                                </div>
                                <label class="ac-check" style="display:block;margin:6px 0"><input type="checkbox" name="confirm" value="1" required> I have authority to override this requirement, and this is recorded in the audit log.</label>
                                <button class="ac-btn ac-btn-danger ac-btn-sm" type="submit">Record override</button>
                            </form>
                        </details>
                    @endif
                </div>
            @empty
                <p class="ac-empty">Nothing blocks publication.</p>
            @endforelse

            @if($overrides->isNotEmpty())
                <h3 style="font-size:14px;margin:14px 0 6px">Overrides</h3>
                <div class="ac-table-wrap"><table class="ac-table">
                    <thead><tr><th>Requirement</th><th>Reason</th><th>By</th><th>Expires</th><th>Status</th><th></th></tr></thead>
                    <tbody>@foreach($overrides as $override)
                        <tr><td>{{ $override->gate_message }}</td><td>{{ $override->reason }}</td><td>{{ $override->grantor?->name }}<span class="ac-cell-sub">{{ $override->granted_at->format('j M Y') }}</span></td>
                            <td>{{ $override->review_by->format('j M Y') }}</td>
                            <td>@if($override->revoked_at)<span class="ac-badge">Revoked</span><span class="ac-cell-sub">{{ $override->revoke_reason }}</span>@elseif($override->isActive())<span class="ac-badge warning">Active</span>@else<span class="ac-badge">Expired</span>@endif</td>
                            <td>@if($canOverride && $override->isActive())
                                <form method="POST" action="{{ route('admin.console.compliance.overrides.revoke', $override) }}" style="display:flex;gap:6px">@csrf
                                    <label class="ac-label" for="revoke-{{ $override->id }}" style="position:absolute;left:-9999px">Reason for revoking</label>
                                    <input id="revoke-{{ $override->id }}" class="ac-input" name="revoke_reason" required minlength="5" maxlength="2000" placeholder="Reason">
                                    <button class="ac-btn ac-btn-sm" type="submit">Revoke</button></form>@endif</td></tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </div>
    </section>
    <div class="ac-grid ac-grid-2">
        <section class="ac-card" aria-labelledby="details-heading">
            <div class="ac-card-head"><h2 id="details-heading">Event details</h2></div>
            <div class="ac-card-body">
                <dl class="ac-dl">
                    <dt>Publication</dt><dd><span class="ac-badge {{ $statusTone[$event->status] ?? '' }}">{{ ucfirst($event->status) }}</span></dd>
                    <dt>Ordering</dt><dd><span class="ac-badge {{ $event->is_ordering_open ? 'success' : '' }}">{{ $event->is_ordering_open ? 'Open' : 'Closed' }}</span></dd>
                    <dt>Date</dt><dd>{{ $event->date_str }}, {{ $event->time_str }} ({{ $event->timezone }})</dd>
                    <dt>Venue</dt><dd>{{ $event->venue_name }}<span class="ac-cell-sub">{{ $event->venue_address }}</span></dd>
                    <dt>Order deadline</dt><dd>{{ $event->advance_order_deadline?->format('M j, Y g:i A') ?? '—' }}</dd>
                    <dt>Currency</dt><dd>{{ $event->currency }}</dd>
                    <dt>Active staff</dt><dd>{{ $staffCount }}</dd>
                    <dt>Created</dt><dd>{{ $event->created_at?->format('M j, Y') }}</dd>
                </dl>
            </div>
        </section>

        <section class="ac-card" aria-labelledby="orders-heading">
            <div class="ac-card-head"><h2 id="orders-heading">Order activity</h2></div>
            <div class="ac-card-body">
                @if($ordersTotal === 0)
                    <p style="margin:0;color:var(--ac-text-2)">No orders yet.</p>
                @else
                    <div class="ac-metric-value">{{ number_format($ordersTotal) }} <span style="font-size:14px;font-weight:600;color:var(--ac-text-2)">orders, all time</span></div>
                    <div style="margin-top:10px">
                        @foreach($statusCounts as $status => $count)
                            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--ac-line)">
                                @include('admin.partials.status', ['status' => $status])
                                <strong>{{ number_format($count) }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>

    <section class="ac-card ac-section" aria-labelledby="compliance-heading" style="margin-top:16px">
        <div class="ac-card-head"><h2 id="compliance-heading">Food safety, allergen &amp; alcohol compliance</h2></div>
        <div class="ac-card-body">
            <dl class="ac-dl">
                <dt>Food safety &amp; temperatures</dt>
                <dd><span class="ac-badge {{ $event->temperature_and_sanitation_confirmed ? 'success' : 'warning' }}">{{ $event->temperature_and_sanitation_confirmed ? 'Confirmed' : 'Pending' }}</span></dd>
                <dt>Allergen &amp; cross-contamination</dt>
                <dd><span class="ac-badge {{ $event->allergen_disclosure_confirmed ? 'success' : 'warning' }}">{{ $event->allergen_disclosure_confirmed ? 'Confirmed' : 'Pending' }}</span></dd>
                <dt>Food-service permit</dt>
                <dd>{{ \App\Models\Event::FOOD_PERMIT_STATUSES[$event->food_permit_status ?? ''] ?? ($event->food_permit_status ?: 'Not specified') }}{{ $event->food_permit_number ? ' (#' . $event->food_permit_number . ')' : '' }}</dd>
                <dt>Alcohol service</dt>
                <dd><span class="ac-badge {{ $event->serves_alcohol ? 'warning' : '' }}">{{ $event->serves_alcohol ? 'Serves alcohol' : 'No alcohol served' }}</span></dd>
                <dt>Age &amp; ID verification</dt>
                <dd>{{ $event->serves_alcohol ? ($event->alcohol_age_verification_required ? 'Mandatory government ID check confirmed' : 'Missing confirmation') : 'N/A' }}</dd>
                <dt>Alcohol licence / permit</dt>
                <dd>{{ \App\Models\Event::ALCOHOL_PERMIT_STATUSES[$event->alcohol_permit_status ?? ''] ?? ($event->alcohol_permit_status ?: 'Not specified') }}{{ $event->alcohol_permit_number ? ' (#' . $event->alcohol_permit_number . ')' : '' }}</dd>
                <dt>Compliance verified at</dt>
                <dd>{{ $event->compliance_verified_at?->format('M j, Y g:i A') ?? '—' }}</dd>
            </dl>
        </div>
    </section>

    @php
        // Every image the host uploaded for this event, for review. Bundled sample images are not host content.
        $images = collect();
        if (\App\Services\MediaStorageService::isStoredPath($event->flyer_path)) {
            $images->push(['kind' => 'flyer', 'id' => null, 'label' => 'Flyer', 'alt' => $event->flyer_alt_text,
                'url' => route('api.media.flyer', ['eventId' => $event->id, 'variant' => 'small']), 'hidden' => $event->flyerIsHidden()]);
        }
        foreach ($event->mediaItems()->where('media_type', 'gallery')->orderBy('sort_order')->get() as $media) {
            $images->push(['kind' => 'gallery', 'id' => $media->id, 'label' => 'Gallery photo', 'alt' => $media->alt_text,
                'url' => route('api.media.event-media', ['mediaId' => $media->id, 'variant' => 'small']), 'hidden' => $media->isHidden()]);
        }
        foreach (\App\Models\MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereNull('deleted_at')->whereNotNull('image_path')->get() as $item) {
            if (\App\Services\MediaStorageService::isStoredPath($item->image_path)) {
                $images->push(['kind' => 'menu', 'id' => $item->id, 'label' => 'Menu: ' . $item->name, 'alt' => $item->image_alt_text,
                    'url' => route('api.media.menu-photo', ['itemId' => $item->id, 'variant' => 'small']), 'hidden' => $item->imageIsHidden()]);
            }
        }
    @endphp
    <section class="ac-card ac-section" aria-labelledby="images-heading" style="margin-top:16px">
        <div class="ac-card-head"><h2 id="images-heading">Uploaded images ({{ $images->count() }})</h2></div>
        <div class="ac-card-body">
            @if($images->isEmpty())
                <p style="margin:0;color:var(--ac-text-2)">The host has not uploaded any images for this event.</p>
            @else
                <p style="margin:0 0 12px;color:var(--ac-text-2)">Hiding an image takes it off public pages without deleting it; the host still sees it, marked, with your reason. Restoring shows it again.</p>
                <ul class="ac-image-review">
                    @foreach($images as $image)
                        <li>
                            <img src="{{ $image['url'] }}" alt="{{ $image['alt'] ?: $image['label'] }}" loading="lazy" width="160" height="120">
                            <div><strong>{{ $image['label'] }}</strong>
                                @if($image['hidden'])<span class="ac-badge danger" style="margin-left:6px">Hidden</span>@endif
                                <div style="color:var(--ac-text-2);font-size:.85rem">{{ $image['alt'] ?: 'No alt text' }}</div>
                            </div>
                            @if($canModerate)
                                <form method="POST" action="{{ route('admin.console.events.images.moderate', $event->id) }}" class="ac-image-review-form">
                                    @csrf
                                    <input type="hidden" name="kind" value="{{ $image['kind'] }}">
                                    <input type="hidden" name="target_id" value="{{ $image['id'] }}">
                                    <input type="hidden" name="decision" value="{{ $image['hidden'] ? 'restore' : 'hide' }}">
                                    <label class="sr-only" for="img-reason-{{ $loop->index }}">Reason</label>
                                    <input class="ac-input" id="img-reason-{{ $loop->index }}" name="reason" required minlength="5" maxlength="500" placeholder="Reason (audit log)">
                                    <button class="ac-btn {{ $image['hidden'] ? 'ac-btn-dark' : 'ac-btn-danger' }}" type="submit">{{ $image['hidden'] ? 'Restore' : 'Hide' }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <section class="ac-card ac-section" aria-labelledby="moderation-heading" style="margin-top:16px">
        <div class="ac-card-head"><h2 id="moderation-heading">Moderation</h2></div>
        <div class="ac-card-body">
            @if(!$canModerate)
                <p style="margin:0;color:var(--ac-text-2)">You can view this event but not change it. Moderation needs the Event moderation permission for this organizer.</p>
            @elseif($actions === [])
                <p style="margin:0;color:var(--ac-text-2)">No moderation actions apply to an event that is {{ $event->status }}. Drafts and cancellations stay with the host.</p>
            @else
                @php
                    $explain = [
                        'unpublish' => 'Removes the event from public listings and stops new orders. Existing orders and tickets stay valid.',
                        'republish' => 'Puts the event back in public listings. Ordering stays closed until the host or you reopen it.',
                        'close_ordering' => 'Stops new food and ticket orders. The event stays visible.',
                        'open_ordering' => 'Lets attendees place orders again.',
                    ];
                @endphp
                @foreach($actions as $action => $label)
                    <form method="POST" action="{{ route('admin.console.events.moderate', $event->id) }}" class="ac-confirm">
                        @csrf
                        <input type="hidden" name="action" value="{{ $action }}">
                        <h3>{{ $label }}</h3>
                        <p>{{ $explain[$action] }}</p>
                        <div class="ac-field">
                            <label for="reason-{{ $action }}">Reason (shown in the audit log)</label>
                            <textarea class="ac-textarea" id="reason-{{ $action }}" name="reason" required minlength="5" maxlength="500"></textarea>
                        </div>
                        <label class="ac-check" style="margin-top:10px"><input type="checkbox" name="confirm" value="1" required> I confirm this change</label>
                        <div class="ac-form-actions">
                            <button class="ac-btn {{ in_array($action, ['unpublish', 'close_ordering'], true) ? 'ac-btn-danger' : 'ac-btn-dark' }}" type="submit" data-loading-text="Saving…">{{ $label }}</button>
                        </div>
                    </form>
                @endforeach
            @endif
        </div>
    </section>
@endsection
