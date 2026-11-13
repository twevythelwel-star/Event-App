{{--
    "Save to your phone" on the ticket page. Buttons come only from WalletPassManager::offers(): a provider that is not
    configured and approved, or a ticket that isn't valid for entry, gets no button at all. Each button uses the
    provider's official badge when its file is installed (config wallet.badges), otherwise the provider's exact wording
    as a plain link. Saving to a wallet never changes payment or admission: the EventBite ticket stays the source of truth.
    Props: order (App\Models\Order).
--}}
@props(['order'])
@php
    $walletManager = app(\App\Services\Wallet\WalletPassManager::class);
    $walletOffers = $walletManager->offers($order, auth()->user());
    $walletStatuses = $walletManager->statuses($order, auth()->user());
    $walletStatusText = [
        'saved' => 'Saved in your :label. Changes to this ticket reach it automatically.',
        'pending' => 'Sent to :label. Finish saving it there; this page shows it as saved once :label confirms.',
        'failed' => 'We couldn’t update your :label pass just now and will keep trying. This EventBite ticket always works at the gate.',
        'revoked' => 'Your :label pass no longer admits anyone: this ticket was replaced, cancelled or moved.',
    ];
@endphp
<section class="eb-wallet-actions" aria-labelledby="wallet-actions-title" data-no-print style="margin-top: var(--space-3);">
    <h3 id="wallet-actions-title" class="eb-text-label" style="margin: 0 0 var(--space-2);">Save to your phone</h3>

    @foreach($walletStatuses as $key => $status)
        <p class="eb-help-text" role="status" data-wallet-status="{{ $key }}" data-wallet-state="{{ $status['state'] }}"
           style="margin: 0 0 var(--space-2); color: {{ in_array($status['state'], ['failed', 'revoked'], true) ? 'var(--color-warning-text)' : 'var(--color-success-text)' }};">
            {{ str_replace(':label', $status['label'], $walletStatusText[$status['state']]) }}
        </p>
    @endforeach

    @if($walletOffers !== [])
        <div class="eb-cluster" style="gap: var(--space-2); align-items: center;">
            @foreach($walletOffers as $offer)
                @php
                    $badge = (string) (config('wallet.badges.' . $offer['key']) ?? '');
                    $hasBadge = $badge !== '' && is_file(public_path($badge));
                    $badgeText = 'Add to ' . $offer['label'];
                @endphp
                <a href="{{ $offer['url'] }}" rel="nofollow" data-wallet-provider="{{ $offer['key'] }}" data-confirm-text="Opening {{ $offer['label'] }}"
                   class="{{ $hasBadge ? 'eb-wallet-badge' : 'eb-btn eb-wallet-btn' }}"
                   style="min-height: 44px; display: inline-flex; align-items: center;">
                    @if($hasBadge)
                        <img src="{{ asset($badge) }}" alt="{{ $badgeText }}" height="40" decoding="async" style="height: 40px; width: auto; display: block;">
                    @else
                        <span data-confirm-label>{{ $badgeText }}</span>
                    @endif
                    @if($offer['test'])
                        <span> (test)</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif

    <p class="eb-help-text" style="margin: var(--space-2) 0 0; font-size: 0.78rem;">
        @if($walletOffers === [])
            Wallet passes aren’t available for this ticket yet.
        @endif
        No wallet app? You don’t need one: show this EventBite ticket at the gate, print it, or open it any time from
        <a href="{{ route('tickets.collection') }}" style="color: var(--color-text-primary); text-decoration: underline;">My tickets</a>. Adding a pass to a wallet doesn’t change your payment or admission.
    </p>
</section>
