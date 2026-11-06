{{--
    Sponsors and vendors on the event page. Sponsors appear by level (Gold first, with larger logos); a sponsor without
    an uploaded logo is shown by name, never with an invented logo. Vendors appear only when approved and when they
    agreed to be listed. Props: $event, $sponsors, $vendors, $vendorState.
--}}
@if($sponsors->isNotEmpty())
    <section class="eb-sponsors" aria-labelledby="sponsors-heading">
        <h3 id="sponsors-heading" class="eb-text-section-title" style="margin: 0;">Sponsors</h3>
        @foreach($sponsors->groupBy('level') as $level => $group)
            <p class="eb-sponsor-level">{{ \App\Models\EventSponsor::LEVELS[$level] ?? ucfirst($level) }}</p>
            <ul class="eb-sponsor-grid is-{{ $level }}">
                @foreach($group as $sponsor)
                    <li class="eb-sponsor-card">
                        @php $logo = $sponsor->logo(); $site = $sponsor->safeWebsite(); @endphp
                        @if($site)<a href="{{ $site }}" rel="noopener sponsored" target="_blank" class="eb-sponsor-link">@endif
                        @if($logo)
                            <span class="eb-sponsor-logo-frame"><x-responsive-image :image="$logo" sizes="220px" fit="contain" /></span>
                        @else
                            <span class="eb-sponsor-name">{{ $sponsor->name }}</span>
                        @endif
                        @if($site)<span class="sr-only"> (opens the sponsor's website in a new tab)</span></a>@endif
                        @if($sponsor->description)<p class="eb-help-text" style="margin: 4px 0 0;">{{ \Illuminate\Support\Str::limit($sponsor->description, 140) }}</p>@endif
                    </li>
                @endforeach
            </ul>
        @endforeach
    </section>
@endif
@if($vendors->isNotEmpty() || $vendorState['open'])
    <section class="eb-vendors" aria-labelledby="vendors-heading">
        <h3 id="vendors-heading" class="eb-text-section-title" style="margin: 0;">Vendors & exhibitors</h3>
        @if($vendors->isNotEmpty())
            <ul class="eb-vendor-list">
                @foreach($vendors as $vendor)
                    <li><strong>{{ $vendor->business_name }}</strong> <span class="eb-badge eb-badge-info">{{ $vendor->categoryLabel() }}</span>
                        @if($vendor->booth_label)<span class="eb-help-text"> · Booth {{ $vendor->booth_label }}</span>@endif
                        <br><span class="eb-help-text">{{ \Illuminate\Support\Str::limit($vendor->description, 160) }}</span></li>
                @endforeach
            </ul>
        @endif
        @if($vendorState['open'])
            <p style="margin: 0;"><a class="eb-btn eb-btn-secondary eb-btn-sm" href="{{ route('vendors.apply', $event->id) }}">Apply to be a vendor or sponsor</a></p>
        @endif
    </section>
@endif
