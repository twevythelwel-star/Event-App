{{-- Add or edit a performer, DJ or speaker profile. $performer is null for a new one. --}}
@php
    $p = $performer;
    $key = $p?->id ?? 'new';
    $links = old('links', $p?->links ?? []);
    $links = array_pad(array_values((array) $links), 3, ['label' => '', 'url' => '']);
@endphp
<form method="POST" action="{{ $p ? route('events.schedule-studio.performers.update', [$event->id, $p->id]) : route('events.schedule-studio.performers.store', $event->id) }}" enctype="multipart/form-data" class="eb-stack" style="gap: var(--space-3);">
    @csrf
    @if($p) @method('PUT') @endif
    <div class="eb-grid-2">
        <x-form-field name="name" :id="'pf-name-' . $key" label="Name" :value="old('name', $p?->name)" :required="true" />
        <x-form-field name="role" :id="'pf-role-' . $key" type="select" label="Role" :value="old('role', $p?->role ?? 'performer')" :options="\App\Models\EventPerformer::ROLES" :required="true" />
    </div>
    <x-form-field name="headline" :id="'pf-headline-' . $key" label="One line about them (optional)" :value="old('headline', $p?->headline)" placeholder="e.g. Roots and culture selector" />
    <x-form-field name="bio" :id="'pf-bio-' . $key" type="textarea" label="Profile (optional)" :value="old('bio', $p?->bio)" help="Write only what they have agreed to share." />
    <fieldset class="eb-fieldset">
        <legend class="eb-text-label">Links (optional, https:// only)</legend>
        @foreach(array_slice($links, 0, 3) as $i => $link)
            <div class="eb-grid-2">
                <x-form-field :name="'links[' . $i . '][label]'" :id="'pf-link-label-' . $key . '-' . $i" label="Label" :value="$link['label'] ?? ''" placeholder="Website" />
                <x-form-field :name="'links[' . $i . '][url]'" :id="'pf-link-url-' . $key . '-' . $i" label="Address" :value="$link['url'] ?? ''" placeholder="https://" />
            </div>
        @endforeach
    </fieldset>
    <div class="eb-grid-2" style="align-items: start;">
        <div class="eb-stack" style="gap: var(--space-2);">
            <label class="eb-label" for="pf-photo-{{ $key }}">{{ $p?->photo_path ? 'Replace their photo' : 'Their photo (optional)' }}</label>
            <input type="file" id="pf-photo-{{ $key }}" name="photo" accept="image/jpeg,image/png,image/webp" class="eb-input">
            <p class="eb-help-text" style="margin: 0;">Use a photo of this person that you have permission to use. Without one, their initials are shown.</p>
            <x-form-field name="photo_alt_text" :id="'pf-alt-' . $key" label="Describe the photo (alt text)" :value="old('photo_alt_text', $p?->photo_alt_text)" />
            @if($p?->photo_path)
                <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="remove_photo" value="1"><span>Remove the photo</span></label>
            @endif
        </div>
        @if($p && ($photo = $p->photo()))
            <x-responsive-image :image="$photo" :alt="$p->photo_alt_text ?: 'Photo of ' . $p->name" sizes="120px" class="eb-lineup-photo" width="120" height="120" style="width: 120px; height: 120px;" />
        @endif
    </div>
    <div><button type="submit" class="eb-btn eb-btn-primary" data-prevent-double-submit="true">{{ $p ? 'Save profile' : 'Add to line-up' }}</button></div>
</form>
