@extends('layouts.app')

@section('title', 'Menu studio — ' . $event->title)
@section('page-title', 'Menu studio')

@section('content')
@php
    $fmt = fn (int $minor) => \App\Support\Money::formatMinor($minor, $currency);
    $tabs = ['menu' => 'Menu Builder', 'packages' => 'Package Builder', 'preview' => 'Attendee Preview'];
@endphp
<div class="eb-stack" style="gap: var(--space-5);">
    <div class="eb-section-header" style="flex-wrap: wrap; gap: var(--space-3);">
        <div>
            <p class="eb-text-supporting" style="margin: 0;"><a href="{{ route('events.edit', $event->id) }}">← {{ $event->title }}</a></p>
            <h1 class="eb-text-page-title" style="margin: 0;">Menu studio</h1>
            <p class="eb-text-supporting" style="margin: 0;">Build single items, combine them into meal packages, and see the menu as guests will.</p>
        </div>
    </div>

    {{-- Saved / refused messages come from the layout; field errors are listed here since the forms are folded. --}}
    @if($errors->any() && !$errors->has('error'))
        <x-alert type="error" title="Not saved: please check the form" id="studio-errors" tabindex="-1">
            <ul style="margin: 4px 0 0; padding-left: 1.1rem;">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </x-alert>
        <script>document.addEventListener('DOMContentLoaded', () => document.getElementById('studio-errors')?.focus());</script>
    @endif

    <nav class="eb-tabs" role="tablist" aria-label="Menu studio">
        @foreach($tabs as $key => $label)
            <a href="{{ route('events.menu-studio', ['id' => $event->id, 'tab' => $key]) }}" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
               aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="eb-tab {{ $tab === $key ? 'is-active' : '' }}" data-studio-tab="{{ $key }}">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Menu Builder --}}
    <section role="tabpanel" id="panel-menu" aria-labelledby="tab-menu" class="eb-stack" style="gap: var(--space-4);" @if($tab !== 'menu') hidden @endif>
        <details class="eb-panel" style="margin: 0;" @if($items->isEmpty() || old('item_type_form') === 'new-item') open @endif>
            <summary class="eb-text-card-title">＋ Add a menu item</summary>
            @include('events.partials.menu-item-form', ['item' => null, 'formId' => 'new-item'])
        </details>
        @if($items->count() > 1)
            <p class="eb-help-text" id="sort-help" style="margin: 0;">Guests see the menu in this order. Drag a row by its handle, or use the arrows. <span data-sort-status role="status" aria-live="polite"></span></p>
        @endif
        <ol class="eb-sortable" data-sortable data-order-url="{{ route('events.menu-studio.order', $event->id) }}" aria-describedby="sort-help">
        @forelse($items as $item)
            <li class="eb-panel eb-studio-row" style="margin: 0;" data-sort-id="{{ $item->id }}">
                <div class="eb-studio-row-head">
                    @if($items->count() > 1)
                        <span class="eb-sort-handle" draggable="true" data-sort-handle title="Drag to reorder" aria-hidden="true">⠿</span>
                        <div class="eb-sort-buttons">
                            @foreach(['up' => '↑', 'down' => '↓'] as $direction => $arrow)
                                <form method="POST" action="{{ route('events.menu-studio.order', $event->id) }}">
                                    @csrf
                                    <input type="hidden" name="move" value="{{ $item->id }}"><input type="hidden" name="direction" value="{{ $direction }}">
                                    <button type="submit" class="eb-btn eb-btn-ghost eb-btn-sm" data-sort-move="{{ $direction }}" aria-label="Move {{ $item->name }} {{ $direction }}" @disabled(($direction === 'up' && $loop->parent->first) || ($direction === 'down' && $loop->parent->last))>{{ $arrow }}</button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                    @if($item->display_image_url)
                        <img src="{{ $item->display_image_url }}" alt="" class="eb-studio-thumb" style="object-position: {{ $item->image_position ?: '50% 50%' }};">
                    @else
                        <span class="eb-studio-thumb eb-studio-thumb-empty" aria-hidden="true">No photo</span>
                    @endif
                    <div style="flex: 1; min-width: 0;">
                        <h2 class="eb-text-card-title" style="margin: 0;">{{ $item->name }} @unless($item->is_active)<span class="eb-badge eb-badge-warning">Hidden</span>@endunless @if($item->is_featured)<span class="eb-badge eb-badge-info">Featured</span>@endif</h2>
                        <p class="eb-text-supporting" style="margin: 0;">{{ $item->category }} · {{ $fmt($item->priceMinorIn($currency)) }}{{ $item->available_stock !== null ? ' · ' . $item->available_stock . ' left' : '' }}{{ $item->max_per_order ? ' · limit ' . $item->max_per_order . ' per customer' : '' }}</p>
                    </div>
                    <form method="POST" action="{{ route('events.menu-studio.destroy', [$event->id, $item->id]) }}" onsubmit="return confirm('Remove {{ addslashes($item->name) }} from the menu? Past orders keep it.');">
                        @csrf @method('DELETE')
                        <button class="eb-btn eb-btn-ghost eb-btn-sm" type="submit">Remove</button>
                    </form>
                </div>
                <details @if(old('item_type_form') === 'item-' . $item->id) open @endif>
                    <summary class="eb-text-label">Edit {{ $item->name }}</summary>
                    @include('events.partials.menu-item-form', ['item' => $item, 'formId' => 'item-' . $item->id])
                </details>
            </li>
        @empty
            <li class="eb-text-supporting" style="list-style: none;">No items yet. Add the dishes, sides and drinks you'll sell; packages are built from them.</li>
        @endforelse
        </ol>
    </section>

    {{-- Package Builder --}}
    <section role="tabpanel" id="panel-packages" aria-labelledby="tab-packages" class="eb-stack" style="gap: var(--space-4);" @if($tab !== 'packages') hidden @endif>
        @if($items->isEmpty())
            <x-alert type="info">Add some menu items first. A package is made from the event's own items, so nothing is entered twice.</x-alert>
        @else
            <details class="eb-panel" style="margin: 0;" @if($packages->isEmpty() || old('item_type_form') === 'new-package') open @endif>
                <summary class="eb-text-card-title">＋ Create a meal package</summary>
                @include('events.partials.package-form', ['package' => null, 'formId' => 'new-package'])
            </details>
        @endif
        @foreach($packages as $package)
            @php $config = (array) ($package->package_config ?? []); $name = fn ($id) => $items->firstWhere('id', $id)?->name ?? 'Removed item'; @endphp
            <article class="eb-panel eb-studio-row" style="margin: 0;">
                <div class="eb-studio-row-head">
                    @if($package->display_image_url)
                        <img src="{{ $package->display_image_url }}" alt="" class="eb-studio-thumb" style="object-position: {{ $package->image_position ?: '50% 50%' }};">
                    @else
                        <span class="eb-studio-thumb eb-studio-thumb-empty" aria-hidden="true">No photo</span>
                    @endif
                    <div style="flex: 1; min-width: 0;">
                        <h2 class="eb-text-card-title" style="margin: 0;">{{ $package->name }} <span class="eb-badge eb-badge-purple">Package</span> @unless($package->is_active)<span class="eb-badge eb-badge-warning">Hidden</span>@endunless</h2>
                        <p class="eb-text-supporting" style="margin: 0;">{{ $fmt($package->priceMinorIn($currency)) }} ·
                            {{ collect($config['components'] ?? [])->map(fn ($c) => (($c['quantity'] ?? 1) > 1 ? $c['quantity'] . ' × ' : '') . $name($c['menu_item_id']))->implode(', ') }}
                            @foreach($config['choice_groups'] ?? [] as $g) · {{ $g['title'] }} (choose {{ $g['min'] === $g['max'] ? $g['max'] : $g['min'] . '–' . $g['max'] }})@endforeach
                        </p>
                    </div>
                    <form method="POST" action="{{ route('events.menu-studio.destroy', [$event->id, $package->id]) }}" onsubmit="return confirm('Remove this package? Past orders keep it.');">
                        @csrf @method('DELETE')
                        <button class="eb-btn eb-btn-ghost eb-btn-sm" type="submit">Remove</button>
                    </form>
                </div>
                <details @if(old('item_type_form') === 'package-' . $package->id) open @endif>
                    <summary class="eb-text-label">Edit {{ $package->name }}</summary>
                    @include('events.partials.package-form', ['package' => $package, 'formId' => 'package-' . $package->id])
                </details>
            </article>
        @endforeach
    </section>

    {{-- Attendee Preview: the checkout food page itself, with nothing submitted --}}
    <section role="tabpanel" id="panel-preview" aria-labelledby="tab-preview" class="eb-stack" style="gap: var(--space-4);" @if($tab !== 'preview') hidden @endif>
        <x-alert type="info">This is the food page guests see at checkout. Try choices and quantities to see the example order and total; nothing here is ordered.
            @if(!$event->foodOrderingState()['open']) <strong>Right now guests can't order food:</strong> {{ $event->foodOrderingState()['reason'] }} @endif</x-alert>
        @if($preview['menu']->isEmpty() && $preview['packages']->isEmpty())
            <p class="eb-text-supporting">Nothing is on the menu yet, or every item is hidden or outside its availability times.</p>
        @else
            <form onsubmit="return false;" aria-label="Menu preview">
                @include('checkout._food-builder', $preview + ['event' => $event, 'currency' => $currency])
            </form>
        @endif
    </section>
</div>

<script>
    // Menu order: drag by the handle, or the arrow buttons (plain forms without JavaScript). Saved at once.
    document.querySelectorAll('[data-sortable]').forEach(function (list) {
        const status = document.querySelector('[data-sort-status]');
        const token = document.querySelector('input[name="_token"]').value;
        let dragged = null;
        const refresh = function () {
            const rows = [...list.querySelectorAll('[data-sort-id]')];
            rows.forEach(function (row, i) {
                row.querySelector('[data-sort-move="up"]')?.toggleAttribute('disabled', i === 0);
                row.querySelector('[data-sort-move="down"]')?.toggleAttribute('disabled', i === rows.length - 1);
            });
        };
        const save = async function (announce) {
            if (status) status.textContent = 'Saving…';
            try {
                const response = await fetch(list.dataset.orderUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ ids: [...list.querySelectorAll('[data-sort-id]')].map(r => r.dataset.sortId) }) });
                if (!response.ok) throw new Error();
                if (status) status.textContent = announce + ' Order saved.';
            } catch (e) {
                if (status) status.textContent = 'The new order could not be saved. Reload the page and try again.';
            }
            refresh();
        };
        list.addEventListener('click', function (event) {
            const button = event.target.closest('[data-sort-move]');
            if (!button) return;
            event.preventDefault();
            const row = button.closest('[data-sort-id]');
            const up = button.dataset.sortMove === 'up';
            const sibling = up ? row.previousElementSibling : row.nextElementSibling;
            if (!sibling) return;
            up ? sibling.before(row) : sibling.after(row);
            const name = row.querySelector('.eb-text-card-title').childNodes[0].textContent.trim();
            save(name + ' moved ' + (up ? 'up.' : 'down.')).then(function () {
                (row.querySelector('[data-sort-move="' + button.dataset.sortMove + '"]:not([disabled])') || row.querySelector('[data-sort-move]:not([disabled])'))?.focus();
            });
        });
        list.addEventListener('dragstart', function (event) {
            const handle = event.target.closest('[data-sort-handle]');
            if (!handle) return;
            dragged = handle.closest('[data-sort-id]');
            dragged.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', dragged.dataset.sortId);
            event.dataTransfer.setDragImage(dragged, 20, 20);
        });
        list.addEventListener('dragover', function (event) {
            if (!dragged) return;
            event.preventDefault();
            const over = event.target.closest('[data-sort-id]');
            if (!over || over === dragged) return;
            const box = over.getBoundingClientRect();
            event.clientY < box.top + box.height / 2 ? over.before(dragged) : over.after(dragged);
        });
        list.addEventListener('drop', function (event) { if (dragged) event.preventDefault(); });
        list.addEventListener('dragend', function () {
            if (!dragged) return;
            dragged.classList.remove('is-dragging');
            dragged = null;
            save('Moved.');
        });
    });

    // Save confirmation: the pressed button shows it is saving and cannot be pressed twice.
    document.querySelectorAll('form[method="POST"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;
            const button = event.submitter || form.querySelector('[type=submit]');
            if (button) { button.setAttribute('data-saving', ''); button.setAttribute('aria-busy', 'true'); }
        });
    });

    // Tabs switch in place; the links still work without JavaScript.
    document.querySelectorAll('[data-studio-tab]').forEach(function (tab) {
        tab.addEventListener('click', function (event) {
            event.preventDefault();
            document.querySelectorAll('[data-studio-tab]').forEach(function (other) {
                const active = other === tab;
                other.classList.toggle('is-active', active);
                other.setAttribute('aria-selected', active ? 'true' : 'false');
                document.getElementById('panel-' + other.dataset.studioTab).hidden = !active;
            });
            history.replaceState(null, '', tab.href);
        });
    });

    // Photo check before upload (the server checks again): type, size, and a preview with the chosen focus.
    document.querySelectorAll('[data-photo-field]').forEach(function (field) {
        const input = field.querySelector('input[type=file]');
        const preview = field.querySelector('[data-photo-preview]');
        const note = field.querySelector('[data-photo-note]');
        const position = field.closest('form').querySelector('[name="image_position"]');
        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) { note.textContent = 'Choose a JPG, PNG or WebP photo.'; input.value = ''; return; }
            if (file.size > 5 * 1024 * 1024) { note.textContent = 'That photo is larger than 5 MB.'; input.value = ''; return; }
            note.textContent = file.name + ' will be uploaded when you save.';
            preview.src = URL.createObjectURL(file); preview.hidden = false;
        });
        if (position) position.addEventListener('change', function () { preview.style.objectPosition = position.value; });
    });

    // Repeating rows (package parts, choices, selections): copy the template with a new index.
    document.querySelectorAll('[data-repeat]').forEach(function (block) {
        const rows = block.querySelector('[data-repeat-rows]');
        const template = block.querySelector('template');
        let next = rows.children.length;
        block.querySelector('[data-repeat-add]').addEventListener('click', function () {
            rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__N__', String(next++)));
            rows.lastElementChild.querySelector('input, select')?.focus();
        });
        rows.addEventListener('click', function (event) {
            const remove = event.target.closest('[data-repeat-remove]');
            if (remove) { remove.closest('[data-repeat-row]').remove(); block.dispatchEvent(new Event('input', { bubbles: true })); }
        });
    });

    // Package value: what the included items cost bought separately, next to the package price.
    document.querySelectorAll('[data-package-form]').forEach(function (form) {
        const out = form.querySelector('[data-package-value]');
        const price = form.querySelector('[name="price"]');
        const money = (m) => new Intl.NumberFormat('en-US', { style: 'currency', currency: form.dataset.currency }).format(m / 100);
        const update = function () {
            let separate = 0;
            form.querySelectorAll('[data-component-row]').forEach(function (row) {
                const option = row.querySelector('select option:checked');
                const qty = parseInt(row.querySelector('[data-component-qty]').value || '1', 10);
                if (option && option.dataset.priceMinor) separate += parseInt(option.dataset.priceMinor, 10) * Math.max(1, qty);
            });
            const own = Math.round(parseFloat(price.value || '0') * 100);
            out.textContent = separate > 0
                ? 'Included items bought separately: ' + money(separate) + '. Package price: ' + money(own) + (own < separate ? ' (saves ' + money(separate - own) + ').' : '.')
                : 'Choose the included items to compare with buying them separately.';
        };
        form.addEventListener('input', update); form.addEventListener('change', update); update();
    });
</script>
@endsection
