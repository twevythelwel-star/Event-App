{{-- Photo upload for an item or package ($subject, or null when new): preview, replace, remove, focus point. --}}
@php $current = $subject?->display_image_url; @endphp
<fieldset class="eb-fieldset" data-photo-field>
    <legend class="eb-text-label">Photo</legend>
    <div class="eb-grid-2" style="align-items: start;">
        <div class="eb-stack" style="gap: var(--space-2);">
            <label class="eb-file-upload">
                <span class="eb-text-label">{{ $current ? 'Choose a new photo to replace this one' : 'Choose a photo of this food' }}</span>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="eb-input">
            </label>
            <p class="eb-help-text" data-photo-note role="status" aria-live="polite">JPG, PNG or WebP, up to 5 MB, at least 200 × 200 pixels. Use a photo of the food you are selling.</p>
            <x-form-field name="image_position" :id="($formId ?? 'p') . '-position'" type="select" label="Keep in view when cropped" :value="$subject?->image_position ?? '50% 50%'"
                          :options="['50% 50%' => 'Centre', '50% 20%' => 'Top', '50% 80%' => 'Bottom', '20% 50%' => 'Left', '80% 50%' => 'Right']" />
            <x-form-field name="image_alt_text" :id="($formId ?? 'p') . '-alt'" label="Describe the photo (alt text)" :value="$subject?->image_alt_text"
                          placeholder="e.g. Fried snapper with pickled peppers on a paper plate" help="Read aloud to guests who cannot see the photo. Leave empty to use the dish name." />
            <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="image_is_illustrative" value="1" @checked($subject?->image_is_illustrative)>
                <span>This is a sample photo, not the actual dish (guests see "Illustrative photo")</span></label>
            @if($current)
                <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="remove_photo" value="1"> <span>Remove the photo</span></label>
            @endif
        </div>
        <div class="eb-food-card-media" style="border-radius: var(--radius-md);">
            <img data-photo-preview src="{{ $current ?? '' }}" alt="Photo preview" style="object-position: {{ $subject?->image_position ?? '50% 50%' }};" @unless($current) hidden @endunless>
        </div>
    </div>
</fieldset>
