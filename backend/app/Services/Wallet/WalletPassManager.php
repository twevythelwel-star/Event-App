<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Jobs\ConfirmWalletPassSaved;
use App\Jobs\SyncWalletPass;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletPass;
use App\Models\WalletPassEvent;
use App\Services\Wallet\Contracts\WalletProvider;
use App\Services\Wallet\Exceptions\WalletProviderException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The single place that decides what happens to a ticket's wallet passes, for Apple, Google and Samsung alike:
 *
 *   decide()        is the ticket eligible, is the provider configured and available, does a pass exist, was it
 *                   saved, does it need updating, was the ticket revoked, did the event change, was the QR reissued
 *   offers()        which "Add to …" buttons this holder may see (only available providers; testers see configured ones)
 *   startSave()     the holder's click: create the pass record and hand over a pass file or the provider's save link
 *   orderChanged()  / eventChanged(): queue a SyncWalletPass for every pass whose provider is out of date
 *   sync()          run by the queued job: update or revoke at the provider and record what it now holds
 *
 * A pass is marked saved only on the provider's own confirmation (Google hasUsers, Samsung ADDED, an Apple device
 * registering) or when the app itself generated, validated and delivered a signed pass file (Apple).
 *
 * Logs carry the provider, the pass id (a random UUID), the action and a short error code: never tokens, keys,
 * credentials or save links.
 */
final class WalletPassManager
{
    /** @var array<string, WalletProvider> */
    private array $providers;

    public function __construct(
        private readonly WalletEligibilityService $eligibility,
        private readonly WalletPassService $applePasses,
        AppleWalletService $apple,
        GoogleWalletService $google,
        SamsungWalletService $samsung,
    ) {
        $this->providers = ['apple' => $apple, 'google' => $google, 'samsung' => $samsung];
    }

    /** @return array<string, WalletProvider> */
    public function providers(): array
    {
        return $this->providers;
    }

    public function provider(string $key): ?WalletProvider
    {
        return $this->providers[$key] ?? null;
    }

    /** The central decision for one ticket, one provider and (optionally) its existing pass and the person asking. */
    public function decide(WalletProvider $provider, Order $order, ?WalletPass $pass = null, ?User $user = null): WalletDecision
    {
        $this->withEvent($order);
        $reason = $user !== null ? $this->eligibility->reason($order, $user) : $this->eligibility->ticketReason($order);
        $configured = $provider->configured();
        $available = $provider->available();
        $state = $pass ? WalletTicketState::of($pass, $order) : null;
        $revoked = $state !== null && $state['voided'];
        $fingerprint = $pass ? WalletTicketContent::fingerprint($pass, $order) : null;
        $alreadySent = $pass !== null && $pass->sync_fingerprint !== null && hash_equals($pass->sync_fingerprint, (string) $fingerprint);
        $eventChanged = $pass !== null && $pass->synced_event_hash !== null
            && !hash_equals($pass->synced_event_hash, WalletTicketContent::eventHash($order->event));
        $reissued = $pass !== null && $pass->synced_token_version !== null && $pass->synced_token_version !== (int) $order->token_version;
        $needsUpdate = $pass !== null && !$alreadySent;

        $action = match (true) {
            !$configured => WalletDecision::UNAVAILABLE,
            $pass !== null && $revoked => $alreadySent && $pass->status === WalletPass::STATUS_REVOKED ? WalletDecision::UP_TO_DATE : WalletDecision::REVOKE,
            $pass !== null => $needsUpdate ? WalletDecision::UPDATE : WalletDecision::UP_TO_DATE,
            $reason !== null => WalletDecision::INELIGIBLE,
            default => WalletDecision::OFFER,
        };

        return new WalletDecision(
            provider: $provider->key(),
            action: $action,
            eligible: $reason === null,
            ineligibleReason: $reason,
            configured: $configured,
            available: $available,
            passExists: $pass !== null,
            saved: $pass?->saved_at !== null,
            needsUpdate: $needsUpdate && !$revoked,
            revoked: $revoked,
            eventChanged: $eventChanged,
            reissued: $reissued,
        );
    }

    /**
     * Why this person can't add the ticket to this provider now (a message), or null when they can. A provider that
     * is configured but not yet approved or tested is offered only to the testers in WALLET_TESTER_EMAILS.
     */
    public function refusal(WalletProvider $provider, Order $order, ?User $user): ?string
    {
        if (!$this->offerable($provider, $user)) {
            return $this->eligibility->message('unavailable', $provider->label());
        }
        $reason = $this->eligibility->reason($order, $user);

        return $reason === null ? null : $this->eligibility->message($reason, $provider->label());
    }

    /**
     * The "Add to …" buttons for this holder: only for providers that are available (or configured, for testers),
     * only while the ticket is eligible. No provider means no button: never a placeholder or a fake link.
     *
     * @return list<array{key: string, label: string, url: string, test: bool}>
     */
    public function offers(Order $order, ?User $user): array
    {
        if ($user === null || !$this->eligibility->eligible($order, $user)) {
            return [];
        }
        $offers = [];
        foreach ($this->providers as $key => $provider) {
            if (!$this->offerable($provider, $user)) {
                continue;
            }
            $offers[] = [
                'key' => $key,
                'label' => $provider->label(),
                'url' => $key === 'apple' ? route('tickets.wallet', $order) : route('tickets.wallet.add', [$order, $key]),
                'test' => !$provider->available(),
            ];
        }

        return $offers;
    }

    /**
     * Where each of this holder's passes for the order stands, for the ticket page: saved (the provider confirmed),
     * pending (handed over, not confirmed yet), failed (the provider refused an update), or revoked.
     *
     * @return array<string, array{label: string, state: string}>
     */
    public function statuses(Order $order, ?User $user): array
    {
        if ($user === null) {
            return [];
        }
        $statuses = [];
        $passes = WalletPass::where('order_id', $order->id)->where('user_id', $user->id)->whereNull('voided_at')->orderBy('created_at')->get();
        foreach ($passes as $pass) {
            $provider = $this->provider((string) $pass->provider);
            if ($provider === null) {
                continue;
            }
            $statuses[$provider->key()] = ['label' => $provider->label(), 'state' => match (true) {
                $pass->status === WalletPass::STATUS_REVOKED => 'revoked',
                $pass->status === WalletPass::STATUS_FAILED || $pass->last_error !== null => 'failed',
                $pass->saved_at !== null => 'saved',
                default => 'pending',
            }];
        }

        return $statuses;
    }

    /** The holder's click: the pass record for this provider, then the pass file or the provider's save link. */
    public function startSave(WalletProvider $provider, Order $order, User $user): WalletSaveAction
    {
        $this->withEvent($order);
        $pass = $this->passFor($provider, $order, $user);
        try {
            $action = $provider->save($pass, $order);
        } catch (WalletProviderException $e) {
            $this->recordFailure($pass, $e, operation: 'create');
            throw $e;
        }
        $this->recordSynced($pass, $order);
        $this->history($pass, 'create');
        if ($action->isFile()) {
            // The app generated, validated and is now delivering a signed pass file.
            $this->markSaved($pass, 'delivered');
        } elseif ($pass->saved_at === null && $provider->key() === 'google') {
            // Google reports hasUsers once the holder saves it: ask a little later.
            ConfirmWalletPassSaved::dispatch($pass->id)->onQueue($this->queue())->delay(now()->addMinutes(2));
        }
        Log::info('wallet.save_started', ['provider' => $provider->key(), 'pass' => $pass->id, 'kind' => $action->kind]);

        return $action;
    }

    /** The holder's current (not voided) pass for the order with this provider, created on first use. */
    public function passFor(WalletProvider $provider, Order $order, User $user): WalletPass
    {
        if ($provider->key() === 'apple') {
            return $this->applePasses->passFor($order, $user);
        }
        $pass = WalletPass::where('order_id', $order->id)->where('user_id', $user->id)->where('provider', $provider->key())
            ->whereNull('voided_at')->first();
        if ($pass !== null) {
            return $pass;
        }
        $pass = new WalletPass([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'provider' => $provider->key(),
            'pass_type_id' => $provider->passTypeId(),
            'serial_number' => (string) Str::uuid(),
            // Not used by Google or Samsung, but the column is shared with Apple: a random value nobody sees.
            'authentication_token' => Str::random(48),
            'last_modified_at' => now()->startOfSecond(),
        ]);
        $pass->external_id = $provider instanceof GoogleWalletService ? $provider->objectId($pass)
            : ($provider instanceof SamsungWalletService ? $provider->refId($pass) : null);
        $pass->save();

        return $pass;
    }

    /** An order changed: Apple passes follow through the PassKit flow; other providers get a queued sync. */
    public function orderChanged(Order $order, array $changed): void
    {
        if (array_intersect($changed, WalletPassService::ORDER_FIELDS) === []) {
            return;
        }
        $this->applePasses->orderChanged($order, $changed);
        WalletPass::where('order_id', $order->id)->where('provider', '!=', 'apple')->get()
            ->each(fn (WalletPass $pass) => $this->queueSyncIfNeeded($pass, $order));
    }

    /** An event changed (time, venue, title, cancellation…): every pass for its tickets. */
    public function eventChanged(Event $event, array $changed): void
    {
        if (array_intersect($changed, WalletPassService::EVENT_FIELDS) === []) {
            return;
        }
        $this->applePasses->eventChanged($event, $changed);
        $orderIds = Order::withoutGlobalScopes()->where('event_id', $event->id)->pluck('id');
        WalletPass::whereIn('order_id', $orderIds)->where('provider', '!=', 'apple')->chunkById(200, function ($passes): void {
            foreach ($passes as $pass) {
                // The decision is made again when the job runs, with the record as it is then.
                SyncWalletPass::dispatch($pass->id)->onQueue($this->queue())->afterCommit();
            }
        });
    }

    /** Run by SyncWalletPass: decide again with the record as it is now, then update or revoke at the provider. */
    public function sync(WalletPass $pass): WalletDecision
    {
        $order = Order::withoutGlobalScopes()->with(['items', 'event' => fn ($q) => $q->withoutGlobalScopes()])->find($pass->order_id);
        $provider = $this->provider((string) $pass->provider);
        if ($order === null || $provider === null) {
            throw new WalletProviderException((string) $pass->provider, 'unknown_pass');
        }
        $decision = $this->decide($provider, $order, $pass);
        if (!$decision->requiresSync()) {
            return $decision;
        }
        if ($decision->action === WalletDecision::REVOKE) {
            $provider->revoke($pass, $order);
            $pass->forceFill([
                'status' => WalletPass::STATUS_REVOKED,
                'revoked_at' => $pass->revoked_at ?? now(),
                'voided_at' => $pass->voided_at ?? (WalletTicketState::holderChanged($pass, $order) ? now() : null),
                'void_reason' => $pass->void_reason ?? WalletTicketState::of($pass, $order)['code'],
            ]);
        } else {
            $pass->touchModified();
            $provider->update($pass, $order);
        }
        $this->recordSynced($pass, $order);
        $this->history($pass, $decision->action === WalletDecision::REVOKE ? 'revoke' : 'update');
        Log::info('wallet.synced', ['provider' => $provider->key(), 'pass' => $pass->id, 'action' => $decision->action,
            'event_changed' => $decision->eventChanged, 'reissued' => $decision->reissued]);

        return $decision;
    }

    /** Ask the provider whether the holder saved the pass. Null: this provider confirms some other way. */
    public function confirmSaved(WalletPass $pass): ?bool
    {
        $provider = $this->provider((string) $pass->provider);
        if ($provider === null || $pass->saved_at !== null) {
            return $pass->saved_at !== null ? true : null;
        }
        $saved = $provider->confirmSaved($pass);
        if ($saved === true) {
            $this->markSaved($pass, 'provider_confirmed');
        }

        return $saved;
    }

    /** Only on the provider's confirmation, or after delivering a validated signed pass file. */
    public function markSaved(WalletPass $pass, string $source, array $meta = []): void
    {
        $pass->forceFill([
            'saved_at' => $pass->saved_at ?? now(),
            'status' => $pass->status === WalletPass::STATUS_REVOKED ? WalletPass::STATUS_REVOKED : WalletPass::STATUS_SAVED,
            'provider_meta' => array_merge((array) $pass->provider_meta, $meta),
        ])->save();
        $this->history($pass, 'saved');
        Log::info('wallet.saved', ['provider' => $pass->provider, 'pass' => $pass->id, 'source' => $source]);
    }

    /** The provider says the holder removed the pass from their wallet. */
    public function markRemoved(WalletPass $pass): void
    {
        $pass->forceFill(['saved_at' => null, 'status' => $pass->status === WalletPass::STATUS_REVOKED ? WalletPass::STATUS_REVOKED : WalletPass::STATUS_ISSUED])->save();
        $this->history($pass, 'removed');
        Log::info('wallet.removed', ['provider' => $pass->provider, 'pass' => $pass->id]);
    }

    /**
     * A provider operation failed: keep a short error code on the pass and in the history (create, update, confirm).
     * A final failure (retries exhausted, or not retryable) marks the pass failed.
     */
    public function recordFailure(WalletPass $pass, \Throwable $e, bool $final = false, string $operation = 'update', bool $history = true): void
    {
        $code = $e instanceof WalletProviderException ? $e->code() : 'unexpected_error';
        $pass->forceFill(['last_error' => mb_substr($code, 0, 120)] + ($final ? ['status' => WalletPass::STATUS_FAILED] : []))->save();
        if ($history) {
            $this->history($pass, $operation, $code, $e instanceof WalletProviderException && $e->retryable);
        }
        Log::warning('wallet.provider_error', ['provider' => $pass->provider, 'pass' => $pass->id, 'operation' => $operation, 'error' => $code, 'final' => $final]);
    }

    /** One line of the operation history shown on the admin wallet page. */
    private function history(WalletPass $pass, string $action, ?string $errorCode = null, bool $retryable = false): void
    {
        WalletPassEvent::create([
            'wallet_pass_id' => $pass->id,
            'provider' => (string) $pass->provider,
            'action' => $action,
            'outcome' => $errorCode === null ? WalletPassEvent::SUCCESS : WalletPassEvent::FAILURE,
            'error_code' => $errorCode !== null ? mb_substr($errorCode, 0, 120) : null,
            'retryable' => $retryable,
        ]);
    }

    public function queue(): string
    {
        return (string) config('wallet.queue', 'default');
    }

    private function queueSyncIfNeeded(WalletPass $pass, Order $order): void
    {
        $provider = $this->provider((string) $pass->provider);
        if ($provider !== null && $this->decide($provider, $order, $pass)->requiresSync()) {
            SyncWalletPass::dispatch($pass->id)->onQueue($this->queue())->afterCommit();
        }
    }

    private function recordSynced(WalletPass $pass, Order $order): void
    {
        $this->withEvent($order);
        $pass->forceFill([
            'sync_fingerprint' => WalletTicketContent::fingerprint($pass, $order),
            'synced_event_hash' => WalletTicketContent::eventHash($order->event),
            'synced_token_version' => (int) $order->token_version,
            'last_synced_at' => now(),
            'last_error' => null,
            'status' => $pass->status === WalletPass::STATUS_FAILED ? ($pass->saved_at ? WalletPass::STATUS_SAVED : WalletPass::STATUS_ISSUED) : $pass->status,
        ])->save();
    }

    /** The pass shows the order's event whatever tenant context is active (queue workers have none). */
    private function withEvent(Order $order): void
    {
        if (!$order->relationLoaded('event') || $order->getRelation('event') === null) {
            $order->setRelation('event', Event::withoutGlobalScopes()->find($order->event_id));
        }
    }

    private function offerable(WalletProvider $provider, ?User $user): bool
    {
        if ($provider->available()) {
            return true;
        }
        $testers = array_filter(array_map(fn ($e) => strtolower(trim($e)), explode(',', (string) config('wallet.tester_emails'))));

        return $provider->configured() && $user !== null && in_array(strtolower((string) $user->email), $testers, true);
    }
}
