@extends('layouts.app')

@section('title', 'Vendors & sponsors — ' . $event->title)
@section('page-title', 'Vendors & sponsors')

@section('content')
@php
    $tabs = ['applications' => 'Applications', 'booths' => 'Booth types', 'sponsors' => 'Sponsors', 'settings' => 'Settings'];
    $fmt = fn (?int $minor) => \App\Support\Money::formatMinor((int) $minor, $currency);
    $major = fn (?int $minor) => number_format(((int) $minor) / 100, 2, '.', '');
    $levels = \App\Models\EventSponsor::LEVELS;
    $categories = \App\Models\VendorApplication::CATEGORIES;
    $applyLink = route('vendors.apply', $event->id);
@endphp
<div class="eb-stack" style="gap: var(--space-5);">
    <div>
        <p class="eb-text-supporting" style="margin: 0;"><a href="{{ route('events.edit', $event->id) }}">← {{ $event->title }}</a></p>
        <h1 class="eb-text-page-title" style="margin: 0;">Vendors & sponsors</h1>
        <p class="eb-text-supporting" style="margin: 0;">Invite food, merchandise and craft vendors, exhibitors and sponsors; sell booths; and credit sponsors on the event page, flyer and tickets.</p>
    </div>
    @if($errors->any())
        <x-alert type="error" title="Not saved"><ul style="margin: 4px 0 0; padding-left: 1.1rem;">@foreach($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul></x-alert>
    @endif

    <nav class="eb-tabs" role="tablist" aria-label="Vendors and sponsors">
        @foreach($tabs as $key => $label)
            <a href="{{ route('events.partners', ['id' => $event->id, 'tab' => $key]) }}" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
               aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="eb-tab {{ $tab === $key ? 'is-active' : '' }}">{{ $label }}@if($key === 'applications' && ($counts['pending'] ?? 0) > 0) <span class="eb-badge eb-badge-warning">{{ $counts['pending'] }}</span>@endif</a>
        @endforeach
    </nav>

    {{-- Applications --}}
    <section role="tabpanel" id="panel-applications" aria-labelledby="tab-applications" class="eb-stack" style="gap: var(--space-4);" @if($tab !== 'applications') hidden @endif>
        <div class="eb-panel" style="margin: 0;">
            <p style="margin: 0;"><strong>Invite vendors:</strong> share this link. Applicants sign in with their own account.
                @unless($event->vendor_applications_open)<span class="eb-badge eb-badge-warning">Applications are closed</span> <a href="{{ route('events.partners', ['id' => $event->id, 'tab' => 'settings']) }}">Open them in Settings</a>@endunless</p>
            <p class="eb-cluster" style="margin: var(--space-2) 0 0;"><input class="eb-input" readonly value="{{ $applyLink }}" aria-label="Vendor application link" style="flex: 1 1 280px;" onclick="this.select()">
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" onclick="navigator.clipboard?.writeText(@js($applyLink)); this.textContent='Copied'">Copy link</button></p>
        </div>
        <nav class="eb-cluster" aria-label="Filter applications">
            <a href="{{ route('events.partners', ['id' => $event->id, 'tab' => 'applications']) }}" class="eb-btn eb-btn-sm {{ $status ? 'eb-btn-ghost' : 'eb-btn-secondary' }}">All</a>
            @foreach(\App\Models\VendorApplication::STATUSES as $key => $label)
                <a href="{{ route('events.partners', ['id' => $event->id, 'tab' => 'applications', 'status' => $key]) }}" class="eb-btn eb-btn-sm {{ $status === $key ? 'eb-btn-secondary' : 'eb-btn-ghost' }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
            @endforeach
        </nav>
        @forelse($applications as $app)
            <article class="eb-panel" style="margin: 0;" aria-labelledby="app-{{ $app->id }}">
                <div class="eb-cluster" style="justify-content: space-between;">
                    <h2 id="app-{{ $app->id }}" class="eb-text-card-title" style="margin: 0;">{{ $app->business_name }}</h2>
                    <span class="eb-cluster"><span class="eb-badge eb-badge-info">{{ $app->categoryLabel() }}</span>
                        <span class="eb-badge {{ ['approved' => 'eb-badge-success', 'declined' => 'eb-badge-error', 'pending' => 'eb-badge-warning'][$app->status] ?? '' }}">{{ $app->statusLabel() }}</span></span>
                </div>
                <p class="eb-text-supporting" style="margin: 4px 0;">{{ $app->contact_name }} · <a href="mailto:{{ $app->email }}">{{ $app->email }}</a> · {{ $app->phone }}@if($app->website) · <a href="{{ $app->website }}" rel="noopener nofollow" target="_blank">Website</a>@endif</p>
                <p style="margin: 0 0 4px; white-space: pre-line;">{{ $app->description }}</p>
                @if($app->needs)<p class="eb-help-text" style="margin: 0;">Needs: {{ $app->needs }}</p>@endif
                <p class="eb-help-text" style="margin: 4px 0 0;">Asked for: {{ $app->offer?->name ?? 'no particular booth' }} · {{ $app->show_publicly ? 'Happy to be listed on the event page' : 'Not to be listed publicly' }} · applied {{ $app->created_at->copy()->setTimezone($tz)->format('M j, g:i A') }}</p>
                @if($app->status === 'approved')
                    <p style="margin: var(--space-2) 0 0;"><strong>Booth:</strong> {{ $app->offer?->name ?? '—' }}{{ $app->booth_label ? ' · ' . $app->booth_label : '' }} · <strong>Fee:</strong> {{ $app->fee_minor ? $fmt($app->fee_minor) : 'none' }}
                        <span class="eb-badge {{ $app->fee_status === 'paid' ? 'eb-badge-success' : ($app->fee_status === 'unpaid' ? 'eb-badge-warning' : '') }}">{{ \App\Models\VendorApplication::FEE_STATUSES[$app->fee_status] ?? $app->fee_status }}</span></p>
                    @if((int) $app->fee_minor > 0)
                        <form method="POST" action="{{ route('events.partners.applications.fee', [$event->id, $app->id]) }}" class="eb-cluster" style="margin-top: var(--space-2);">
                            @csrf
                            <label class="sr-only" for="fee-{{ $app->id }}">Booth fee status</label>
                            <select class="eb-select eb-input" name="fee_status" id="fee-{{ $app->id }}" style="max-width: 200px;">
                                @foreach(['unpaid' => 'Fee due', 'paid' => 'Fee paid', 'waived' => 'Fee waived'] as $k => $l)<option value="{{ $k }}" @selected($app->fee_status === $k)>{{ $l }}</option>@endforeach
                            </select>
                            <button class="eb-btn eb-btn-secondary eb-btn-sm" type="submit">Record</button>
                        </form>
                    @endif
                    @if($app->category === 'sponsor' && !\App\Models\EventSponsor::where('application_id', $app->id)->exists())
                        <form method="POST" action="{{ route('events.partners.applications.sponsor', [$event->id, $app->id]) }}" class="eb-cluster" style="margin-top: var(--space-2);">
                            @csrf
                            <label class="eb-text-label" for="lvl-{{ $app->id }}">Add as sponsor at</label>
                            <select class="eb-select eb-input" name="level" id="lvl-{{ $app->id }}" style="max-width: 160px;">@foreach($levels as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                            <button class="eb-btn eb-btn-secondary eb-btn-sm" type="submit">Add sponsor</button>
                        </form>
                    @endif
                @endif
                @if($app->decision_note)<p class="eb-help-text" style="margin: 4px 0 0;">Note to applicant: {{ $app->decision_note }}</p>@endif
                @if(in_array($app->status, ['pending', 'approved'], true))
                    <details style="margin-top: var(--space-3);" @if($app->status === 'pending') open @endif>
                        <summary class="eb-text-label">{{ $app->status === 'pending' ? 'Decide' : 'Change decision' }}</summary>
                        <form method="POST" action="{{ route('events.partners.applications.decide', [$event->id, $app->id]) }}" class="eb-stack" style="gap: var(--space-3); margin-top: var(--space-2);">
                            @csrf
                            <div class="eb-grid-2">
                                <x-form-field name="offer_id" :id="'offer-' . $app->id" type="select" label="Booth type" :value="$app->offer_id"
                                    :options="['' => 'No booth type'] + $offers->mapWithKeys(fn ($o) => [$o->id => $o->name . ' (' . $fmt($o->price_minor) . ($o->remaining() !== null ? ', ' . $o->remaining() . ' left' : '') . ')'])->all()" />
                                <x-form-field name="fee" :id="'feeamt-' . $app->id" type="number" label="Fee ({{ $currency }}, optional)" :value="$app->fee_minor !== null ? $major($app->fee_minor) : ''" help="Leave empty to use the booth type's price." />
                                <x-form-field name="booth_label" :id="'booth-' . $app->id" label="Booth number or name (optional)" :value="$app->booth_label" />
                                @if($booths->isNotEmpty())
                                    <x-form-field name="venue_area_id" :id="'area-' . $app->id" type="select" label="Booth on the venue plan" :value="$app->venue_area_id" :options="['' => 'Not on the plan'] + $booths->pluck('label', 'id')->all()" />
                                @endif
                            </div>
                            <x-form-field name="note" :id="'note-' . $app->id" type="textarea" label="Note to the applicant (optional)" :value="$app->decision_note" />
                            <div class="eb-cluster">
                                <button class="eb-btn eb-btn-primary eb-btn-sm" name="decision" value="approve" type="submit">Approve</button>
                                <button class="eb-btn eb-btn-ghost eb-btn-sm" name="decision" value="decline" type="submit">Decline</button>
                            </div>
                        </form>
                    </details>
                @endif
            </article>
        @empty
            <x-empty-state title="No applications {{ $status ? 'with this status' : 'yet' }}" description="Share the application link with vendors, exhibitors and sponsors." illustration="orders" />
        @endforelse
    </section>

    {{-- Booth types --}}
    <section role="tabpanel" id="panel-booths" aria-labelledby="tab-booths" class="eb-stack" style="gap: var(--space-4);" @if($tab !== 'booths') hidden @endif>
        @foreach($offers->concat([null]) as $offer)
            <form method="POST" action="{{ $offer ? route('events.partners.offers.update', [$event->id, $offer->id]) : route('events.partners.offers.store', $event->id) }}" class="eb-panel eb-stack" style="gap: var(--space-3); margin: 0;">
                @csrf @if($offer) @method('PUT') @endif
                <h2 class="eb-text-card-title" style="margin: 0;">{{ $offer ? $offer->name : '＋ Add a booth type' }}@if($offer) <span class="eb-help-text">{{ $offer->taken() }} given out{{ $offer->quantity ? ' of ' . $offer->quantity : '' }}</span>@endif</h2>
                @php $k = $offer?->id ?? 'new'; @endphp
                <div class="eb-grid-2">
                    <x-form-field name="name" :id="'o-name-' . $k" label="Name" :value="$offer?->name" :required="true" placeholder="e.g. 10×10 food booth with power" />
                    <x-form-field name="category" :id="'o-cat-' . $k" type="select" label="For" :value="$offer?->category" :options="['' => 'Any vendor'] + $categories" />
                    <x-form-field name="price" :id="'o-price-' . $k" type="number" :label="'Price (' . $currency . ')'" :value="$offer ? $major($offer->price_minor) : ''" :required="true" />
                    <x-form-field name="quantity" :id="'o-qty-' . $k" type="number" label="How many (optional)" :value="$offer?->quantity" />
                    @if($booths->isNotEmpty())
                        <x-form-field name="venue_area_id" :id="'o-area-' . $k" type="select" label="Booth area on the plan" :value="$offer?->venue_area_id" :options="['' => 'Not on the plan'] + $booths->pluck('label', 'id')->all()" />
                    @endif
                </div>
                <x-form-field name="description" :id="'o-desc-' . $k" type="textarea" label="What is included (optional)" :value="$offer?->description" />
                @if($offer)
                    <input type="hidden" name="is_active_submitted" value="1">
                    <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="is_active" value="1" @checked($offer->is_active)><span>Offered to applicants</span></label>
                @endif
                <div class="eb-cluster">
                    <button class="eb-btn eb-btn-primary eb-btn-sm" type="submit">{{ $offer ? 'Save' : 'Add booth type' }}</button>
                    @if($offer)<button class="eb-btn eb-btn-ghost eb-btn-sm" type="submit" form="offer-del-{{ $offer->id }}">Remove</button>@endif
                </div>
            </form>
            @if($offer)
                <form id="offer-del-{{ $offer->id }}" method="POST" action="{{ route('events.partners.offers.destroy', [$event->id, $offer->id]) }}" onsubmit="return confirm('Remove this booth type?');" hidden>@csrf @method('DELETE')</form>
            @endif
        @endforeach
    </section>

    {{-- Sponsors --}}
    <section role="tabpanel" id="panel-sponsors" aria-labelledby="tab-sponsors" class="eb-stack" style="gap: var(--space-4);" @if($tab !== 'sponsors') hidden @endif>
        <h2 class="eb-text-section-title" style="margin: 0;">Sponsorship packages</h2>
        @foreach($packages->concat([null]) as $package)
            @php $k = $package?->id ?? 'new'; @endphp
            <form method="POST" action="{{ $package ? route('events.partners.packages.update', [$event->id, $package->id]) : route('events.partners.packages.store', $event->id) }}" class="eb-panel eb-stack" style="gap: var(--space-3); margin: 0;">
                @csrf @if($package) @method('PUT') @endif
                <div class="eb-grid-2">
                    <x-form-field name="level" :id="'p-level-' . $k" type="select" label="Level" :value="$package?->level ?? 'gold'" :options="$levels" />
                    <x-form-field name="name" :id="'p-name-' . $k" label="Package name" :value="$package?->name" :required="true" placeholder="e.g. Gold partner" />
                    <x-form-field name="price" :id="'p-price-' . $k" type="number" :label="'Price (' . $currency . ')'" :value="$package ? $major($package->price_minor) : ''" :required="true" />
                    <x-form-field name="quantity" :id="'p-qty-' . $k" type="number" label="How many (optional)" :value="$package?->quantity" />
                </div>
                <x-form-field name="benefits" :id="'p-ben-' . $k" type="textarea" label="Benefits" :value="$package?->benefits" placeholder="e.g. Logo on tickets and the flyer, stage mention, 4 VIP passes" />
                <div class="eb-cluster">
                    <button class="eb-btn eb-btn-primary eb-btn-sm" type="submit">{{ $package ? 'Save package' : 'Add package' }}</button>
                    @if($package)<button class="eb-btn eb-btn-ghost eb-btn-sm" type="submit" form="pkg-del-{{ $package->id }}">Remove</button>@endif
                </div>
            </form>
            @if($package)<form id="pkg-del-{{ $package->id }}" method="POST" action="{{ route('events.partners.packages.destroy', [$event->id, $package->id]) }}" onsubmit="return confirm('Remove this package? Its sponsors stay.');" hidden>@csrf @method('DELETE')</form>@endif
        @endforeach

        <h2 class="eb-text-section-title" style="margin: 0;">Sponsors</h2>
        @foreach($sponsors->concat([null]) as $sponsor)
            @php $k = $sponsor?->id ?? 'new'; @endphp
            <form method="POST" enctype="multipart/form-data" action="{{ $sponsor ? route('events.partners.sponsors.update', [$event->id, $sponsor->id]) : route('events.partners.sponsors.store', $event->id) }}" class="eb-panel eb-stack" style="gap: var(--space-3); margin: 0;">
                @csrf @if($sponsor) @method('PUT') @endif
                <h3 class="eb-text-card-title" style="margin: 0;">{{ $sponsor ? $sponsor->name . ' · ' . $sponsor->levelLabel() : '＋ Add a sponsor' }}</h3>
                <div class="eb-grid-2">
                    <x-form-field name="name" :id="'s-name-' . $k" label="Name" :value="$sponsor?->name" :required="true" />
                    <x-form-field name="level" :id="'s-level-' . $k" type="select" label="Level" :value="$sponsor?->level ?? 'gold'" :options="$levels" />
                    @if($packages->isNotEmpty())
                        <x-form-field name="package_id" :id="'s-pkg-' . $k" type="select" label="Package (sets the level)" :value="$sponsor?->package_id" :options="['' => 'No package'] + $packages->pluck('name', 'id')->all()" />
                    @endif
                    <x-form-field name="website" :id="'s-web-' . $k" label="Website (https://)" :value="$sponsor?->website" />
                </div>
                <x-form-field name="description" :id="'s-desc-' . $k" type="textarea" label="Description (optional)" :value="$sponsor?->description" />
                <div class="eb-grid-2" style="align-items: start;">
                    <div class="eb-stack" style="gap: var(--space-2);">
                        <label class="eb-label" for="s-logo-{{ $k }}">{{ $sponsor?->logo_path ? 'Replace the logo' : 'Logo (PNG with a transparent background works best)' }}</label>
                        <input type="file" id="s-logo-{{ $k }}" name="logo" accept="image/png,image/jpeg,image/webp" class="eb-input">
                        <x-form-field name="logo_alt_text" :id="'s-alt-' . $k" label="Logo alt text" :value="$sponsor?->logo_alt_text" placeholder="e.g. Harbour Bank logo" />
                        @if($sponsor?->logo_path)<label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="remove_logo" value="1"><span>Remove the logo</span></label>@endif
                    </div>
                    @if($sponsor && ($logo = $sponsor->logo()))
                        <div class="eb-sponsor-logo-frame"><x-responsive-image :image="$logo" sizes="200px" fit="contain" /></div>
                    @endif
                </div>
                <fieldset class="eb-fieldset"><legend class="eb-text-label">Credit them on</legend>
                    <div class="eb-cluster">
                        <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="show_on_page" value="1" @checked($sponsor?->show_on_page ?? true)><span>The event page</span></label>
                        <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="show_on_flyer" value="1" @checked($sponsor?->show_on_flyer)><span>The generated flyer</span></label>
                        <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="show_on_ticket" value="1" @checked($sponsor?->show_on_ticket)><span>Tickets</span></label>
                    </div>
                    <p class="eb-help-text" style="margin: 4px 0 0;">An uploaded flyer is the host's own artwork and is never changed; the generated flyer lists flyer sponsors by name.</p>
                </fieldset>
                <div class="eb-cluster">
                    <button class="eb-btn eb-btn-primary eb-btn-sm" type="submit">{{ $sponsor ? 'Save sponsor' : 'Add sponsor' }}</button>
                    @if($sponsor)<button class="eb-btn eb-btn-ghost eb-btn-sm" type="submit" form="sp-del-{{ $sponsor->id }}">Remove</button>@endif
                </div>
            </form>
            @if($sponsor)<form id="sp-del-{{ $sponsor->id }}" method="POST" action="{{ route('events.partners.sponsors.destroy', [$event->id, $sponsor->id]) }}" onsubmit="return confirm('Remove this sponsor?');" hidden>@csrf @method('DELETE')</form>@endif
        @endforeach
    </section>

    {{-- Settings --}}
    <section role="tabpanel" id="panel-settings" aria-labelledby="tab-settings" @if($tab !== 'settings') hidden @endif>
        <form method="POST" action="{{ route('events.partners.settings', $event->id) }}" class="eb-panel eb-stack" style="gap: var(--space-3); margin: 0;">
            @csrf
            <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="open" value="1" @checked($event->vendor_applications_open)><span><strong>Accept vendor, exhibitor and sponsor applications</strong></span></label>
            <x-form-field name="closes_at" id="vendor-closes" type="datetime-local" label="Applications close (event's local time, optional)" :value="$event->vendor_applications_close_at?->copy()->setTimezone($tz)->format('Y-m-d\TH:i')" />
            <x-form-field name="terms" id="vendor-terms" type="textarea" label="Vendor terms (optional; applicants must agree)" :value="$event->vendor_terms" />
            <div><button class="eb-btn eb-btn-primary" type="submit">Save settings</button></div>
        </form>
    </section>
</div>
@endsection
