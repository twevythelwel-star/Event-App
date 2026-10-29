@props(['id', 'title', 'message', 'confirmAction', 'confirmText' => 'Confirm', 'isDestructive' => false])

<div
    id="{{ $id }}"
    class="eb-modal-backdrop modal-backdrop"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $id }}-title"
    style="display: none;"
>
    <div class="eb-modal">
        <h3 id="{{ $id }}-title" class="eb-h3" style="margin-bottom: var(--space-2); color: {{ $isDestructive ? 'var(--color-error-text)' : 'var(--color-text-primary)' }};">
            {{ $title }}
        </h3>
        <p class="eb-text-body" style="margin-bottom: var(--space-6);">
            {{ $message }}
        </p>
        <div style="display: flex; justify-content: flex-end; gap: var(--space-3);">
            <button type="button" class="eb-btn eb-btn-secondary" onclick="document.getElementById('{{ $id }}').style.display='none'">
                Cancel
            </button>
            <form action="{{ $confirmAction }}" method="POST" style="margin: 0;">
                @csrf
                <button type="submit" class="eb-btn {{ $isDestructive ? 'eb-btn-destructive' : 'eb-btn-primary' }}" data-prevent-double-submit="true">
                    {{ $confirmText }}
                </button>
            </form>
        </div>
    </div>
</div>
