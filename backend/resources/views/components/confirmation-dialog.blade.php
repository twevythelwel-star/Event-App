@props(['id', 'title', 'message', 'confirmAction', 'confirmText' => 'Confirm', 'isDestructive' => false])

<div id="{{ $id }}" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 100; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card" style="width: 100%; max-width: 480px; margin: 1rem; position: relative;">
        <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 0.5rem; color: {{ $isDestructive ? 'var(--accent-rose)' : 'var(--text-primary)' }};">
            {{ $title }}
        </h3>
        <p style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 1.5rem;">
            {{ $message }}
        </p>
        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('{{ $id }}').style.display='none'">
                Cancel
            </button>
            <form action="{{ $confirmAction }}" method="POST">
                @csrf
                <button type="submit" class="btn {{ $isDestructive ? 'btn-danger' : 'btn-primary' }}">
                    {{ $confirmText }}
                </button>
            </form>
        </div>
    </div>
</div>
