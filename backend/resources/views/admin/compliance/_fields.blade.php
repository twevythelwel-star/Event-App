{{-- Edit fields for an authority or requirement record. $record may be null (a new authority). --}}
@php $id = fn (string $name) => "{$prefix}-{$name}"; $date = fn ($value) => $value?->format('Y-m-d'); @endphp
<div class="ac-form-row">
    @if($type === 'authority')
        <div class="ac-field"><label for="{{ $id('name') }}">Official agency name</label><input id="{{ $id('name') }}" class="ac-input" name="name" required maxlength="255" value="{{ $record?->name }}"></div>
        <div class="ac-field"><label for="{{ $id('area') }}">Area</label><select id="{{ $id('area') }}" class="ac-select" name="area">@foreach(\App\Models\Compliance\ComplianceAuthority::AREAS as $value => $label)<option value="{{ $value }}" @selected($record?->area === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="ac-field grow"><label for="{{ $id('role') }}">Role</label><input id="{{ $id('role') }}" class="ac-input" name="role" required maxlength="255" value="{{ $record?->role }}" placeholder="e.g. Issues temporary food event permits"></div>
        <div class="ac-field grow"><label for="{{ $id('website_url') }}">Agency website</label><input id="{{ $id('website_url') }}" class="ac-input" type="url" name="website_url" maxlength="500" value="{{ $record?->website_url }}" placeholder="https://"></div>
        <div class="ac-field grow"><label for="{{ $id('legislation_url') }}">Official legislation or regulation link</label><input id="{{ $id('legislation_url') }}" class="ac-input" type="url" name="legislation_url" maxlength="500" value="{{ $record?->legislation_url }}" placeholder="https://"></div>
        <div class="ac-field grow"><label for="{{ $id('legislation_title') }}">Legislation or regulation title</label><input id="{{ $id('legislation_title') }}" class="ac-input" name="legislation_title" maxlength="255" value="{{ $record?->legislation_title }}"></div>
    @else
        <div class="ac-field grow" style="grid-column:1/-1"><label for="{{ $id('summary') }}">What the requirement is</label><textarea id="{{ $id('summary') }}" class="ac-textarea" name="summary" rows="3" maxlength="5000">{{ $record?->summary }}</textarea>
            <span class="ac-hint">State only what the official source says, for this jurisdiction. Leave it empty while it is unverified.</span></div>
        <div class="ac-field"><label for="{{ $id('value') }}">Short value (e.g. minimum age)</label><input id="{{ $id('value') }}" class="ac-input" name="value" maxlength="120" value="{{ $record?->value }}"></div>
        <div class="ac-field"><label for="{{ $id('authority_id') }}">Authority</label><select id="{{ $id('authority_id') }}" class="ac-select" name="authority_id"><option value="">—</option>@foreach($citableAuthorities as $authority)<option value="{{ $authority->id }}" @selected($record?->authority_id === $authority->id)>{{ $authority->name }}</option>@endforeach</select></div>
        <div class="ac-field grow"><label for="{{ $id('legislation_citation') }}">Legislation or regulation citation</label><input id="{{ $id('legislation_citation') }}" class="ac-input" name="legislation_citation" maxlength="255" value="{{ $record?->legislation_citation }}"></div>
        <div class="ac-field grow"><label for="{{ $id('conditions') }}">Restrictions and conditions</label><textarea id="{{ $id('conditions') }}" class="ac-textarea" name="conditions" rows="2" maxlength="5000">{{ $record?->conditions }}</textarea></div>
        <div class="ac-field grow"><label for="{{ $id('required_documents') }}">Required documentation</label><textarea id="{{ $id('required_documents') }}" class="ac-textarea" name="required_documents" rows="2" maxlength="5000">{{ $record?->required_documents }}</textarea></div>
    @endif
    <div class="ac-field grow"><label for="{{ $id('source_url') }}">Official source link</label><input id="{{ $id('source_url') }}" class="ac-input" type="url" name="source_url" maxlength="500" value="{{ $record?->source_url }}" placeholder="https://"></div>
    <div class="ac-field grow"><label for="{{ $id('source_title') }}">Source title</label><input id="{{ $id('source_title') }}" class="ac-input" name="source_title" maxlength="255" value="{{ $record?->source_title }}"></div>
    <div class="ac-field"><label for="{{ $id('effective_date') }}">Effective date</label><input id="{{ $id('effective_date') }}" class="ac-input" type="date" name="effective_date" value="{{ $date($record?->effective_date) }}"></div>
    <div class="ac-field"><label for="{{ $id('last_verified_date') }}">Source checked on</label><input id="{{ $id('last_verified_date') }}" class="ac-input" type="date" name="last_verified_date" max="{{ now()->toDateString() }}" value="{{ $date($record?->last_verified_date) }}"></div>
    <div class="ac-field"><label for="{{ $id('next_review_date') }}">Next review</label><input id="{{ $id('next_review_date') }}" class="ac-input" type="date" name="next_review_date" min="{{ now()->addDay()->toDateString() }}" value="{{ $date($record?->next_review_date) }}"></div>
    <div class="ac-field grow" style="grid-column:1/-1">
        <input type="hidden" name="source_confirmed_official_submitted" value="1">
        <label class="ac-check"><input type="checkbox" name="source_confirmed_official" value="1" @checked($record?->source_confirmed_official)>
            The links above are the agency's own official government sites (needed only for links that are not on a .gov, .mil or .us https site).</label>
    </div>
    <div class="ac-field grow"><label for="{{ $id('research_notes') }}">Research notes</label><textarea id="{{ $id('research_notes') }}" class="ac-textarea" name="research_notes" rows="2" maxlength="5000">{{ $record?->research_notes }}</textarea></div>
    <div class="ac-field grow"><label for="{{ $id('admin_notes') }}">Compliance administrator notes</label><textarea id="{{ $id('admin_notes') }}" class="ac-textarea" name="admin_notes" rows="2" maxlength="5000">{{ $record?->admin_notes }}</textarea></div>
</div>
