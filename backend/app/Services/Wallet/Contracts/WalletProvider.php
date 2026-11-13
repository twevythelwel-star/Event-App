<?php

declare(strict_types=1);

namespace App\Services\Wallet\Contracts;

use App\Models\Order;
use App\Models\WalletPass;
use App\Services\Wallet\Exceptions\WalletProviderException;
use App\Services\Wallet\WalletSaveAction;

/**
 * One wallet provider (Apple, Google, Samsung) behind the WalletPassManager. Adapters only talk to their provider;
 * whether a ticket may have a pass, and what has to happen to it, is decided once, by the manager.
 *
 * Every remote operation throws WalletProviderException (with a short, non-secret code and a retryable flag) and
 * never logs or returns tokens, keys, credentials or signed save links.
 */
interface WalletProvider
{
    /** apple | google | samsung */
    public function key(): string;

    /** "Apple Wallet", "Google Wallet", "Samsung Wallet". */
    public function label(): string;

    /** Turned on and every credential present and well-formed. */
    public function configured(): bool;

    /**
     * Configured, and every gate the provider requires before real users see it has been passed (e.g. Samsung's
     * partner approval, card launch and our own end-to-end test). The "Add to …" button shows only when true.
     */
    public function available(): bool;

    /** @return list<string> What is missing, in plain words; never a secret value. */
    public function problems(): array;

    /** Identifier the pass record is filed under (Apple Pass Type ID, Google class prefix, Samsung card id). */
    public function passTypeId(): string;

    /**
     * The holder pressed "Add to …": create or refresh the pass at the provider and return how to hand it over
     * (a pass file to download, or a short-lived link to the provider's save page).
     *
     * @throws WalletProviderException
     */
    public function save(WalletPass $pass, Order $order): WalletSaveAction;

    /**
     * Push the current ticket state (new time, venue, reissued QR, check-in) to a pass the provider already holds.
     *
     * @throws WalletProviderException
     */
    public function update(WalletPass $pass, Order $order): void;

    /**
     * The ticket is no longer valid for this holder (revoked, refunded, cancelled, transferred): the pass must stop
     * showing the barcode.
     *
     * @throws WalletProviderException
     */
    public function revoke(WalletPass $pass, Order $order): void;

    /**
     * Ask the provider whether the holder actually saved the pass. True only on the provider's own confirmation;
     * null when this provider cannot be asked (it confirms some other way).
     *
     * @throws WalletProviderException
     */
    public function confirmSaved(WalletPass $pass): ?bool;
}
