{{--
    The event's jurisdiction-specific requirements checklist (EventRequirementChecklist).
    $checklist: the built checklist. $event: the saved event or null. $interactive: show evidence forms (the
    Requirements page only; never inside the event form).
--}}
@php
    $tones = ['required' => 'warning', 'not_required' => 'info', 'submitted' => 'info', 'pending_verification' => 'purple',
        'verified' => 'success', 'rejected' => 'error', 'expired' => 'error', 'verification_required' => 'error'];
    $statuses = \App\Services\Compliance\EventRequirementChecklist::STATUSES;
    $interactive = $interactive ?? false;
@endphp
<div class="eb-stack" style="gap: var(--space-4);" data-requirements-checklist>
    <p class="eb-help-text" style="margin:0" role="status">
        {{ $checklist['verified'] }} of {{ $checklist['required'] }} {{ \Illuminate\Support\Str::plural('item', $checklist['required']) }} verified.
        An item is only verified after an EventBite administrator checks what you submit. Confirming the declarations, or submitting a permit number, does not make an event compliant on its own.
    </p>

    @foreach($checklist['sections'] as $section)
        <section class="eb-stack" style="gap: var(--space-2);" aria-label="{{ $section['label'] }}">
            <h3 class="eb-text-label" style="margin:0">{{ $section['label'] }}@if($section['same_as_event']) <span class="eb-help-text">(at the event location)</span>@endif</h3>
            <p class="eb-help-text" style="margin:0">
                @if(count($section['jurisdiction']['chain']) < 2 && empty($section['jurisdiction']['missing']))
                    Give the state and city to identify the jurisdiction.
                @else
                    <strong>{{ $section['jurisdiction']['label'] }}</strong>
                @endif
            </p>
            @if(!empty($section['jurisdiction']['missing']))
                <p class="eb-help-text" style="margin:0">Places marked "not in the database yet" have not been researched by EventBite, so their requirements show as Verification Required.</p>
            @endif

            @forelse($section['items'] as $item)
                <div class="eb-card" style="padding: var(--space-3);" id="item-{{ md5($item['key']) }}">
                    <div style="display:flex;justify-content:space-between;gap:var(--space-2);flex-wrap:wrap;align-items:flex-start">
                        <div>
                            <strong>{{ $item['title'] }}</strong>
                            <div class="eb-help-text">{{ $item['level']['name'] }}@if(!$item['level']['node']) (not in the database yet)@endif</div>
                        </div>
                        <span class="eb-badge eb-badge-{{ $tones[$item['status']] ?? 'info' }}">{{ $statuses[$item['status']] ?? $item['status'] }}</span>
                    </div>
                    <div class="eb-help-text" style="margin-top: var(--space-1);">
                        @if($item['rule'])
                            {{ $item['base'] === 'not_required' ? 'Not required' : 'Required' }} for {{ $item['rule']->conditionSummary() }}.
                            @if($item['rule']->description) {{ $item['rule']->description }}@endif
                            <a href="{{ $item['rule']->source_url }}" target="_blank" rel="noopener noreferrer">Official source</a>
                        @elseif($item['requirement'] && $item['requirement']->isVerified())
                            {{ $item['requirement']->summary }}
                            @if($item['requirement']->source_url)<a href="{{ $item['requirement']->source_url }}" target="_blank" rel="noopener noreferrer">Official source</a>@endif
                        @else
                            EventBite has not verified whether this applies here yet. Check with the local authority; you can submit what you hold for verification.
                        @endif
                    </div>
                    @if($item['evidence'])
                        <div class="eb-help-text" style="margin-top: var(--space-1);">
                            You submitted {{ $item['evidence']->reference_number }} from {{ $item['evidence']->issuing_authority }}
                            on {{ $item['evidence']->submitted_at?->format('j M Y') }}@if($item['evidence']->expires_on), expiring {{ $item['evidence']->expires_on->format('j M Y') }}@endif.
                            @if($item['evidence']->review_comment) Reviewer: “{{ $item['evidence']->review_comment }}”@endif
                            @if($item['evidence']->document_path)<a href="{{ route('events.requirements.document', $item['evidence']) }}">View document</a>@endif
                        </div>
                    @endif

                    @if($interactive && $item['can_submit'] && (!$item['evidence'] || in_array($item['status'], ['submitted', 'rejected', 'expired', 'verified'], true)))
                        <details style="margin-top: var(--space-2);">
                            <summary>{{ $item['evidence'] ? 'Submit updated details' : 'Submit permit or licence details' }}</summary>
                            <form method="POST" action="{{ route('events.requirements.evidence', $event->id) }}" enctype="multipart/form-data" class="eb-stack" style="gap: var(--space-2); margin-top: var(--space-2);">
                                @csrf
                                <input type="hidden" name="item_key" value="{{ $item['key'] }}">
                                @php $fid = 'ev-' . md5($item['key']); @endphp
                                <div class="eb-grid-2">
                                    <x-form-field :name="'reference_number'" :id="$fid . '-ref'" label="Permit or licence number" :required="true" maxlength="120" />
                                    <x-form-field :name="'issuing_authority'" :id="$fid . '-auth'" label="Issued by" :required="true" maxlength="255" placeholder="e.g. county health department" />
                                    <x-form-field :name="'expires_on'" :id="$fid . '-exp'" type="date" label="Expires on (if it does)" />
                                    <div class="eb-form-group">
                                        <label for="{{ $fid }}-doc" class="eb-label">Document (PDF, JPG or PNG, up to 5 MB)</label>
                                        <input type="file" id="{{ $fid }}-doc" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="eb-input">
                                    </div>
                                </div>
                                <x-form-field :name="'note'" :id="$fid . '-note'" type="textarea" label="Note for the reviewer (optional)" :rows="2" />
                                <div><button type="submit" class="eb-btn eb-btn-primary eb-btn-sm">Submit for verification</button></div>
                            </form>
                        </details>
                    @endif
                </div>
            @empty
                <p class="eb-help-text">Nothing to check for this location.</p>
            @endforelse
        </section>
    @endforeach
</div>
