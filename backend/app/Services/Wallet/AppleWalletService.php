<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Jobs\PushWalletPassUpdate;
use App\Models\Order;
use App\Models\WalletPass;
use App\Services\Wallet\Contracts\WalletProvider;
use App\Services\Wallet\Exceptions\AppleWalletException;

/**
 * Apple Wallet behind the common provider contract. The pass itself is built and signed by the existing
 * AppleWalletPassBuilder (pkpass/pkpass), devices fetch updates from the PassKit web service
 * (AppleWalletWebServiceController) and are woken by APNs pushes (AppleWalletPushService, PushWalletPassUpdate).
 *
 * Apple has no "was it saved?" API. A pass counts as saved once the app has generated a signed package, checked it
 * with AppleWalletPackageValidator and delivered it; a device registering for updates confirms it as well.
 */
final class AppleWalletService implements WalletProvider
{
    public function __construct(
        private readonly AppleWalletConfig $config,
        private readonly AppleWalletPassBuilder $builder,
        private readonly AppleWalletPackageValidator $validator,
    ) {}

    public function key(): string
    {
        return 'apple';
    }

    public function label(): string
    {
        return 'Apple Wallet';
    }

    public function configured(): bool
    {
        return $this->config->enabled();
    }

    public function available(): bool
    {
        // Apple needs no partner approval beyond the Apple Developer Program membership that issues the certificate.
        return $this->configured();
    }

    public function problems(): array
    {
        return config('wallet.apple.enabled') ? $this->config->problems() : ['APPLE_WALLET_ENABLED is off.'];
    }

    public function passTypeId(): string
    {
        return $this->config->passTypeId();
    }

    public function save(WalletPass $pass, Order $order): WalletSaveAction
    {
        if (!$this->configured()) {
            throw AppleWalletException::notConfigured('apple');
        }
        try {
            $bytes = $this->builder->package($pass, $order);
        } catch (\Throwable) {
            throw AppleWalletException::because('package_failed');
        }
        $check = $this->validator->validate($bytes, $this->config->wwdrCertificatePath());
        if (!$check['valid']) {
            throw AppleWalletException::because('package_invalid');
        }

        return WalletSaveAction::file($bytes, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Content-Disposition' => 'attachment; filename="eventbite-' . preg_replace('/[^A-Za-z0-9-]/', '', (string) $order->ticket_code) . '.pkpass"',
            'Last-Modified' => $pass->last_modified_at->toRfc7231String(),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Devices fetch the rebuilt pass themselves: mark it modified and wake them. */
    public function update(WalletPass $pass, Order $order): void
    {
        $pass->touchModified();
        if ($pass->registrations()->exists()) {
            PushWalletPassUpdate::dispatch($pass->id)->onQueue((string) config('wallet.queue', 'default'))->afterCommit();
        }
    }

    /** The rebuilt pass is voided with no barcode (WalletTicketState); devices get it on the next fetch. */
    public function revoke(WalletPass $pass, Order $order): void
    {
        $this->update($pass, $order);
    }

    public function confirmSaved(WalletPass $pass): ?bool
    {
        return $pass->registrations()->exists() ? true : null;
    }
}
