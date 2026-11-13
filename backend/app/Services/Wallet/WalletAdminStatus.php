<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\WalletPass;
use App\Models\WalletPassEvent;
use Illuminate\Support\Collection;

/**
 * What the admin wallet page shows: each provider's configuration and approval state, pass and operation totals,
 * recent provider errors, the last successful sync, the operation history, and the warnings an operator must act on.
 *
 * Built only from settings that are not secret (identifiers, on/off switches, certificate expiry) and from the
 * wallet_passes / wallet_pass_events records, which hold short error codes, never tokens, keys or links.
 */
final class WalletAdminStatus
{
    /** Retryable failures within OUTAGE_MINUTES at or above this count suggest the provider is down. */
    public const OUTAGE_FAILURES = 3;

    public const OUTAGE_MINUTES = 30;

    public const RECENT_DAYS = 7;

    public function __construct(
        private readonly AppleWalletConfig $apple,
        private readonly GoogleWalletConfig $google,
        private readonly SamsungWalletConfig $samsung,
    ) {}

    /** @return array<string, array<string, mixed>> */
    public function providers(): array
    {
        $expires = $this->apple->certificateP12() !== null ? $this->apple->certificateExpiresAt() : null;
        $appleProblems = $this->apple->problems();

        return [
            'apple' => [
                'label' => 'Apple Wallet',
                'enabled' => (bool) config('wallet.apple.enabled'),
                'configured' => $this->apple->enabled(),
                'live' => $this->apple->enabled(),
                'details' => [
                    'Pass Type ID' => $this->apple->passTypeId() ?: 'not set',
                    'Team ID' => $this->apple->teamId() ?: 'not set',
                    'Certificate' => match (true) {
                        $this->apple->certificateP12() === null => 'missing',
                        $expires === null => 'cannot be opened',
                        $expires->getTimestamp() < now()->getTimestamp() => 'expired on ' . $expires->format('Y-m-d'),
                        default => 'valid until ' . $expires->format('Y-m-d') . ' (' . (int) floor(($expires->getTimestamp() - now()->getTimestamp()) / 86400) . ' days)',
                    },
                    'Live updates' => $this->apple->webServiceUrl() !== null ? 'on (HTTPS web service)' : 'off (APP_URL is not HTTPS)',
                ],
                'problems' => $appleProblems,
                'certificate_missing' => $this->apple->certificateP12() === null,
                'certificate_expired' => $expires !== null && $expires->getTimestamp() < now()->getTimestamp(),
                'certificate_expires_soon' => $expires !== null && $expires->getTimestamp() >= now()->getTimestamp() && $expires->getTimestamp() < now()->getTimestamp() + 30 * 86400,
            ],
            'google' => [
                'label' => 'Google Wallet',
                'enabled' => $this->google->enabled(),
                'configured' => $this->google->configured(),
                'live' => $this->google->available(),
                'details' => [
                    'Issuer ID' => $this->google->issuerId() ?: 'not set',
                    'Service account' => $this->google->enabled() && $this->google->problems() === [] ? 'readable' : ($this->google->clientEmail() !== '' ? 'present, not usable' : 'missing'),
                    'Publishing access' => config('wallet.google.publishing_approved') ? 'granted' : 'not granted (demo mode)',
                    'Device test' => config('wallet.google.verified') ? 'passed' : 'not yet',
                ],
                'problems' => $this->google->enabled() ? $this->google->problems() : [],
                'pending' => $this->google->pendingGates(),
            ],
            'samsung' => [
                'label' => 'Samsung Wallet',
                'enabled' => $this->samsung->enabled(),
                'configured' => $this->samsung->configured(),
                'live' => $this->samsung->available(),
                'details' => [
                    'Partner ID' => $this->samsung->partnerId() ?: 'not set',
                    'Card ID' => $this->samsung->cardId() ?: 'not set',
                    'Partner approval' => config('wallet.samsung.partner_approved') ? 'approved, card launched' : 'pending',
                    'Device test' => config('wallet.samsung.verified') ? 'passed' : 'not yet',
                ],
                'problems' => $this->samsung->enabled() ? $this->samsung->problems() : [],
                'pending' => $this->samsung->pendingGates(),
                'approved' => (bool) config('wallet.samsung.partner_approved'),
            ],
        ];
    }

    /** @return array<string, array{passes: int, saved: int, revoked: int, successful: int, failed: int, pending: int, last_sync: ?\Illuminate\Support\Carbon}> */
    public function totals(): array
    {
        $totals = [];
        foreach (WalletPass::PROVIDERS as $provider) {
            $passes = WalletPass::where('provider', $provider);
            $events = WalletPassEvent::where('provider', $provider);
            $lastSync = (clone $passes)->max('last_synced_at');
            $totals[$provider] = [
                'passes' => (clone $passes)->count(),
                'saved' => (clone $passes)->whereNotNull('saved_at')->count(),
                'revoked' => (clone $passes)->where('status', WalletPass::STATUS_REVOKED)->count(),
                'successful' => (clone $events)->where('outcome', WalletPassEvent::SUCCESS)->count(),
                'failed' => (clone $events)->where('outcome', WalletPassEvent::FAILURE)->count(),
                // Waiting on the provider: not yet confirmed as saved, or being retried after an error.
                'pending' => (clone $passes)->where('status', WalletPass::STATUS_ISSUED)
                    ->where(fn ($q) => $q->whereNull('saved_at')->orWhereNotNull('last_error'))->count(),
                'last_sync' => $lastSync ? \Illuminate\Support\Carbon::parse($lastSync) : null,
            ];
        }

        return $totals;
    }

    /** Provider errors in the last RECENT_DAYS, grouped by error code. */
    public function errors(): Collection
    {
        return WalletPassEvent::where('outcome', WalletPassEvent::FAILURE)->where('created_at', '>=', now()->subDays(self::RECENT_DAYS))
            ->selectRaw('provider, action, error_code, count(*) as total, max(created_at) as last_at')
            ->groupBy('provider', 'action', 'error_code')->orderByDesc('last_at')->limit(50)->get();
    }

    public function history(int $limit = 50): Collection
    {
        return WalletPassEvent::orderByDesc('id')->limit($limit)->get();
    }

    /** @return list<array{level: string, provider: string, message: string}> */
    public function warnings(): array
    {
        $warnings = [];
        $providers = $this->providers();
        $apple = $providers['apple'];
        if ($apple['enabled']) {
            if ($apple['certificate_missing']) {
                $warnings[] = $this->warn('error', 'Apple Wallet', 'The pass certificate is missing: Apple Wallet passes cannot be issued. Set APPLE_WALLET_CERTIFICATE_PATH or APPLE_WALLET_CERTIFICATE_BASE64.');
            } elseif ($apple['certificate_expired']) {
                $warnings[] = $this->warn('error', 'Apple Wallet', 'The pass certificate has expired: new passes and updates fail until it is renewed.');
            } elseif ($apple['problems'] !== []) {
                $warnings[] = $this->warn('error', 'Apple Wallet', 'Invalid credentials: ' . implode(' ', $apple['problems']));
            } elseif ($apple['certificate_expires_soon']) {
                $warnings[] = $this->warn('warning', 'Apple Wallet', 'The pass certificate expires within 30 days: renew it.');
            }
        }
        foreach (['google', 'samsung'] as $key) {
            if ($providers[$key]['enabled'] && $providers[$key]['problems'] !== []) {
                $warnings[] = $this->warn('error', $providers[$key]['label'], 'Invalid or missing credentials: ' . implode(' ', $providers[$key]['problems']));
            }
        }
        if (!$providers['samsung']['approved']) {
            $warnings[] = $this->warn('warning', 'Samsung Wallet', 'The Samsung Wallet integration is not approved by Samsung: the button stays hidden from customers.');
        }

        $since = now()->subDays(self::RECENT_DAYS);
        $failures = WalletPassEvent::where('outcome', WalletPassEvent::FAILURE)->where('created_at', '>=', $since)
            ->whereIn('action', ['create', 'update', 'revoke'])->get(['provider', 'action'])
            ->groupBy(fn ($e) => $e->provider . '|' . ($e->action === 'create' ? 'create' : 'update'));
        foreach ($failures as $group => $rows) {
            [$provider, $kind] = explode('|', $group);
            $label = $providers[$provider]['label'] ?? $provider;
            $n = $rows->count();
            $warnings[] = $this->warn('error', $label, $kind === 'create'
                ? "{$n} failed pass creation" . ($n === 1 ? '' : 's') . ' in the last ' . self::RECENT_DAYS . ' days.'
                : "{$n} failed pass update" . ($n === 1 ? '' : 's') . ' in the last ' . self::RECENT_DAYS . ' days.');
        }
        $stuck = WalletPass::where('status', WalletPass::STATUS_FAILED)->selectRaw('provider, count(*) as total')->groupBy('provider')->get();
        foreach ($stuck as $row) {
            $warnings[] = $this->warn('error', $providers[$row->provider]['label'] ?? $row->provider, "{$row->total} pass" . ($row->total == 1 ? '' : 'es') . ' gave up after retries and are out of step with their tickets.');
        }
        $outages = WalletPassEvent::where('outcome', WalletPassEvent::FAILURE)->where('retryable', true)
            ->where('created_at', '>=', now()->subMinutes(self::OUTAGE_MINUTES))
            ->selectRaw('provider, count(*) as total')->groupBy('provider')->having('total', '>=', self::OUTAGE_FAILURES)->get();
        foreach ($outages as $row) {
            $warnings[] = $this->warn('error', $providers[$row->provider]['label'] ?? $row->provider, "Possible provider outage: {$row->total} connection or server errors in the last " . self::OUTAGE_MINUTES . ' minutes. Operations are being retried.');
        }

        return $warnings;
    }

    private function warn(string $level, string $provider, string $message): array
    {
        return ['level' => $level, 'provider' => $provider, 'message' => $message];
    }
}
