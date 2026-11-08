{{-- Shown on every legal page: draft status until legal review, and the "not legal advice" statement. --}}
@unless(\App\Support\LegalPages::counselReviewed())
    <x-alert type="warning" title="Draft wording">
        This wording has not yet been reviewed by qualified US legal counsel. It must be reviewed before EventBite is released to the public.
    </x-alert>
@endunless
<aside class="eb-card" role="note" aria-label="Not legal advice" style="padding: 1rem 1.25rem; border-left: 4px solid var(--color-accent);">
    <p style="margin: 0 0 0.5rem; font-weight: 700;">General information, not legal advice</p>
    <p style="margin: 0 0 0.5rem;">{{ \App\Support\LegalPages::NOT_LEGAL_ADVICE }}</p>
    <p style="margin: 0;">{{ \App\Support\LegalPages::NO_GUARANTEE }}</p>
</aside>
