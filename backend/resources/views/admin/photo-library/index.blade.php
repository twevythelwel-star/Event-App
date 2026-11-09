@extends('layouts.admin')

@section('title', 'Photo library')
@section('heading', 'Event Photo Library')
@section('subheading', 'Photographs of each kind of event, shown labelled "Illustrative photo" on events of that kind that have no flyer of their own.')
@section('breadcrumbs')<span> / Photo library</span>@endsection

@section('content')
    @if($errors->any())<div class="ac-alert error" role="alert"><div>{{ $errors->first() }}</div></div>@endif

    <div class="ac-alert info" role="note"><div>
        <strong>How photos are used.</strong> A host's own flyer and photos always come first. Only when an event has none, and its host said what kind of
        event it is, does an approved photo of that kind appear, always labelled "Illustrative photo". Photos are never chosen from an event's title.
        Nothing here may show, or suggest, a real named venue or event, and nothing is presented as a photo of an EventBite event, vendor or customer.
    </div></div>

    <section class="ac-card">
        <div class="ac-card-head"><h2>Coverage</h2><span class="ac-hint">Aim for several approved photos of each kind, so events of the same kind look different.</span></div>
        <div class="ac-table-wrap"><table class="ac-table">
            <thead><tr><th>Kind of event</th><th>Approved</th><th>Awaiting review</th><th>Events of this kind</th><th>Brief for producing photos</th></tr></thead>
            <tbody>
            @foreach($groups as $groupKey => $groupLabel)
                <tr><th colspan="5" style="text-align:left">{{ $groupLabel }}</th></tr>
                @foreach($subjects as $key => $s)
                    @continue($s['group'] !== $groupKey)
                    @php $c = $coverage->get($key, collect()); $approved = (int) ($c['approved'] ?? 0); @endphp
                    <tr id="subject-{{ $key }}">
                        <td>{{ $s['label'] }}<span class="ac-cell-sub">{{ $key }}</span></td>
                        <td><span class="ac-badge {{ $approved >= 3 ? 'success' : ($approved > 0 ? 'warning' : 'danger') }}">{{ $approved }}</span></td>
                        <td>{{ (int) ($c['pending_review'] ?? 0) }}</td>
                        <td>{{ (int) ($eventsBySubject[$key] ?? 0) }}</td>
                        <td><details><summary>Show brief</summary><p style="white-space:pre-line;max-width:60ch">{{ $s['brief'] }}</p></details></td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="ac-card" style="margin-top:16px">
        <div class="ac-card-head"><h2>Photography standards</h2></div>
        <div class="ac-card-body"><ul style="margin:0;padding-left:18px">@foreach($standards as $text)<li>{{ $text }}</li>@endforeach</ul>
            <p class="ac-hint" style="margin-top:8px">The reviewer confirms each one before approving a photo.</p></div>
    </section>

    @if($pending->isNotEmpty())
        <section class="ac-card" style="margin-top:16px">
            <div class="ac-card-head"><h2>Awaiting review ({{ $pending->count() }})</h2></div>
            @foreach($pending as $photo)
                <article class="ac-card-body" style="border-top:1px solid var(--ac-border,#233);display:grid;grid-template-columns:minmax(0,320px) minmax(0,1fr);gap:16px">
                    <img src="{{ $photo->url(['variant' => 'medium', 'format' => 'webp']) }}" alt="{{ $photo->alt_text }}" style="width:100%;height:auto;border-radius:8px;object-fit:cover;object-position:{{ $photo->focal_x }}% {{ $photo->focal_y }}%">
                    <div>
                        <strong>{{ $photo->title }}</strong> <span class="ac-badge warning">{{ $photo->subjectLabel() }}</span>
                        <dl class="ac-dl" style="margin-top:8px">
                            <dt>Alt text</dt><dd>{{ $photo->alt_text }}</dd>
                            <dt>Source</dt><dd>{{ \App\Models\LibraryPhoto::SOURCES[$photo->source] ?? $photo->source }}: {{ $photo->source_detail }}@if($photo->source_url) · <a class="ac-link" href="{{ $photo->source_url }}" target="_blank" rel="noopener noreferrer">source</a>@endif</dd>
                            <dt>Licence</dt><dd>{{ $photo->licence }}@if($photo->licence_url) · <a class="ac-link" href="{{ $photo->licence_url }}" target="_blank" rel="noopener noreferrer">terms</a>@endif</dd>
                            <dt>People</dt><dd>{{ $photo->identifiable_people ? 'Identifiable people' : 'No identifiable people' }} · {{ \App\Models\LibraryPhoto::MODEL_RELEASES[$photo->model_release] ?? $photo->model_release }}</dd>
                            <dt>Size</dt><dd>{{ $photo->width }}×{{ $photo->height }}px · uploaded by {{ $photo->uploader?->name ?? '—' }}</dd>
                        </dl>
                        @can(\App\Support\AdminAuthorization::PHOTO_LIBRARY_APPROVE, $photo)
                            <form method="POST" action="{{ route('admin.console.photo-library.review', $photo) }}" style="display:grid;gap:6px;margin-top:10px">@csrf
                                @foreach($standards as $key => $text)
                                    <label style="display:flex;gap:6px"><input type="checkbox" name="standards[{{ $key }}]" value="1"> <span>{{ $text }}</span></label>
                                @endforeach
                                <div class="ac-field"><label for="notes-{{ $photo->id }}">Notes (needed to reject)</label><input id="notes-{{ $photo->id }}" class="ac-input" name="notes" maxlength="1000"></div>
                                <div style="display:flex;gap:8px">
                                    <button class="ac-btn ac-btn-primary ac-btn-sm" name="decision" value="approve" type="submit">Approve</button>
                                    <button class="ac-btn ac-btn-danger ac-btn-sm" name="decision" value="reject" type="submit">Reject</button>
                                </div>
                            </form>
                        @else
                            <p class="ac-hint">A system administrator other than the uploader reviews it.</p>
                        @endcan
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    <section class="ac-card" style="margin-top:16px">
        <div class="ac-card-head"><h2>Add a photo</h2></div>
        <form method="POST" action="{{ route('admin.console.photo-library.store') }}" enctype="multipart/form-data" class="ac-card-body" style="display:grid;gap:10px">@csrf
            <div class="ac-field"><label for="lp-photo">Photo (JPEG, PNG or WebP; at least {{ config('photo_library.min_width') }}×{{ config('photo_library.min_height') }}px; up to 5 MB)</label>
                <input id="lp-photo" class="ac-input" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" required></div>
            <div id="lp-preview-wrap" hidden style="position:relative;max-width:480px">
                <img id="lp-preview" alt="Preview of the photo to upload. Click it to set the focal point." style="width:100%;border-radius:8px;cursor:crosshair">
                <span id="lp-focus" aria-hidden="true" style="position:absolute;width:18px;height:18px;margin:-9px 0 0 -9px;border:2px solid #C7FF45;border-radius:50%;left:50%;top:50%"></span>
                <p class="ac-hint">Click the photo where its subject is: cropped versions keep that point in view.</p>
            </div>
            <div class="ac-field"><label for="lp-subject">Kind of event it shows</label>
                <select id="lp-subject" class="ac-select" name="subject" required>
                    <option value="">Choose…</option>
                    @foreach(\App\Services\PhotoLibrary::subjectOptions() as $group => $options)
                        <optgroup label="{{ $group }}">@foreach($options as $value => $label)<option value="{{ $value }}" @selected(old('subject', $subject) === $value)>{{ $label }}</option>@endforeach</optgroup>
                    @endforeach
                </select></div>
            <div class="ac-field"><label for="lp-title">Title</label><input id="lp-title" class="ac-input" name="title" value="{{ old('title') }}" required maxlength="160"></div>
            <div class="ac-field"><label for="lp-alt">Alt text (what the photo shows)</label><input id="lp-alt" class="ac-input" name="alt_text" value="{{ old('alt_text') }}" required minlength="15" maxlength="300"></div>
            <input type="hidden" name="focal_x" id="lp-fx" value="{{ old('focal_x', 50) }}"><input type="hidden" name="focal_y" id="lp-fy" value="{{ old('focal_y', 50) }}">
            <div class="ac-field"><label for="lp-source">Source</label>
                <select id="lp-source" class="ac-select" name="source" required>@foreach(\App\Models\LibraryPhoto::SOURCES as $v => $l)<option value="{{ $v }}" @selected(old('source') === $v)>{{ $l }}</option>@endforeach</select></div>
            <div class="ac-field"><label for="lp-detail">Generator, photographer or stock library</label><input id="lp-detail" class="ac-input" name="source_detail" value="{{ old('source_detail') }}" required maxlength="255"></div>
            <div class="ac-field"><label for="lp-surl">Source link (optional)</label><input id="lp-surl" class="ac-input" type="url" name="source_url" value="{{ old('source_url') }}"></div>
            <div class="ac-field"><label for="lp-licence">Licence</label><input id="lp-licence" class="ac-input" name="licence" value="{{ old('licence') }}" required maxlength="160" placeholder="e.g. Adobe Firefly generated, commercial use permitted"></div>
            <div class="ac-field"><label for="lp-lurl">Licence terms link (optional)</label><input id="lp-lurl" class="ac-input" type="url" name="licence_url" value="{{ old('licence_url') }}"></div>
            <label><input type="checkbox" name="identifiable_people" value="1" @checked(old('identifiable_people', '1'))> Shows identifiable people</label>
            <div class="ac-field"><label for="lp-release">Model release</label>
                <select id="lp-release" class="ac-select" name="model_release">@foreach(\App\Models\LibraryPhoto::MODEL_RELEASES as $v => $l)<option value="{{ $v }}" @selected(old('model_release') === $v)>{{ $l }}</option>@endforeach</select></div>
            <label><input type="checkbox" name="rights_confirmed" value="1" required> EventBite may use this photo commercially under its licence</label>
            <div><button class="ac-btn ac-btn-primary ac-btn-sm" type="submit">Add for review</button></div>
        </form>
    </section>

    <section class="ac-card" style="margin-top:16px">
        <div class="ac-card-head"><h2>All photos</h2></div>
        @if($photos->isEmpty())
            <div class="ac-card-body ac-empty">No photos yet. Until photos are approved, events without a flyer show artwork made from their own details.</div>
        @else
            <div class="ac-card-body" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
                @foreach($photos as $photo)
                    <figure style="margin:0" id="photo-{{ $photo->id }}">
                        <img src="{{ $photo->url(['variant' => 'small', 'format' => 'webp']) }}" alt="{{ $photo->alt_text }}" loading="lazy" style="width:100%;aspect-ratio:3/2;object-fit:cover;object-position:{{ $photo->focal_x }}% {{ $photo->focal_y }}%;border-radius:8px">
                        <figcaption style="font-size:12px;margin-top:4px">
                            <strong>{{ $photo->title }}</strong><br>{{ $photo->subjectLabel() }} ·
                            <span class="ac-badge {{ ['approved' => 'success', 'rejected' => 'danger', 'pending_review' => 'warning'][$photo->status] ?? '' }}">{{ \App\Models\LibraryPhoto::STATUSES[$photo->status] }}</span>
                            @if($photo->status === 'approved')
                                <details style="margin-top:4px"><summary>Edit or retire</summary>
                                    <form method="POST" action="{{ route('admin.console.photo-library.update', $photo) }}" style="display:grid;gap:4px">@csrf @method('PATCH')
                                        <label for="alt-{{ $photo->id }}">Alt text</label><input id="alt-{{ $photo->id }}" class="ac-input" name="alt_text" value="{{ $photo->alt_text }}" required minlength="15">
                                        <label for="fx-{{ $photo->id }}">Focal point across (%)</label><input id="fx-{{ $photo->id }}" class="ac-input" type="number" min="0" max="100" name="focal_x" value="{{ $photo->focal_x }}">
                                        <label for="fy-{{ $photo->id }}">Focal point down (%)</label><input id="fy-{{ $photo->id }}" class="ac-input" type="number" min="0" max="100" name="focal_y" value="{{ $photo->focal_y }}">
                                        <button class="ac-btn ac-btn-sm" type="submit">Save</button></form>
                                    <form method="POST" action="{{ route('admin.console.photo-library.retire', $photo) }}" style="display:grid;gap:4px;margin-top:6px">@csrf
                                        <label for="why-{{ $photo->id }}">Why retire it</label><input id="why-{{ $photo->id }}" class="ac-input" name="reason" required minlength="5">
                                        <button class="ac-btn ac-btn-danger ac-btn-sm" type="submit">Retire</button></form>
                                </details>
                            @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    </section>

    <script>
        // Upload preview and focal point: click the preview to set where the subject is.
        (() => {
            const input = document.getElementById('lp-photo'), wrap = document.getElementById('lp-preview-wrap'), img = document.getElementById('lp-preview');
            const dot = document.getElementById('lp-focus'), fx = document.getElementById('lp-fx'), fy = document.getElementById('lp-fy');
            input?.addEventListener('change', () => {
                const file = input.files && input.files[0];
                if (!file) { wrap.hidden = true; return; }
                img.src = URL.createObjectURL(file);
                wrap.hidden = false;
            });
            img?.addEventListener('click', (e) => {
                const r = img.getBoundingClientRect();
                const x = Math.round(((e.clientX - r.left) / r.width) * 100), y = Math.round(((e.clientY - r.top) / r.height) * 100);
                fx.value = Math.max(0, Math.min(100, x)); fy.value = Math.max(0, Math.min(100, y));
                dot.style.left = fx.value + '%'; dot.style.top = fy.value + '%';
            });
        })();
    </script>
@endsection
