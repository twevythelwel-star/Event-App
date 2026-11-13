<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderPlacementService;
use App\Services\Wallet\Exceptions\WalletProviderException;
use App\Services\Wallet\WalletPassManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * "Add to Apple Wallet / Google Wallet / Samsung Wallet" for the signed-in ticket holder. Whether the button may work
 * is decided by the WalletPassManager; a provider that is not available answers 404, exactly as if it did not exist.
 * Apple gets the signed .pkpass; Google and Samsung get a redirect to their own save page, made at this moment
 * (the link carries a signed, short-lived token and is never stored or logged).
 */
class TicketWalletController extends Controller
{
    public function __construct(
        private readonly OrderPlacementService $orders,
        private readonly WalletPassManager $wallets,
    ) {}

    /** GET /tickets/{order}/wallet.pkpass */
    public function apple(Request $request, Order $order): Response|RedirectResponse
    {
        return $this->add($request, $order, 'apple');
    }

    /** GET /tickets/{order}/wallet/{provider} */
    public function add(Request $request, Order $order, string $provider): Response|RedirectResponse
    {
        $user = $request->user();
        // Someone else's ticket is "not found", as on the ticket page.
        abort_unless($this->orders->isUserAuthorizedForOrder($order, $user), 404);
        $wallet = $this->wallets->provider($provider);
        // Apple keeps its own explanation for "not set up" (its button was the first); the others simply don't exist yet.
        abort_if($wallet === null || ($provider !== 'apple' && !$wallet->configured()), 404);
        $order->load(['items', 'event' => fn ($q) => $q->withoutGlobalScopes()]);

        if (($refusal = $this->wallets->refusal($wallet, $order, $user)) !== null) {
            return redirect()->route('tickets.show', $order)->with('error', $refusal);
        }

        try {
            $action = $this->wallets->startSave($wallet, $order, $user);
        } catch (WalletProviderException $e) {
            Log::warning('wallet.save_failed', ['provider' => $provider, 'order' => $order->id, 'error' => $e->code()]);

            return redirect()->route('tickets.show', $order)
                ->with('error', "We couldn’t reach {$wallet->label()} just now. Please try again in a minute.");
        }

        if ($action->isFile()) {
            return response($action->payload(), 200, $action->headers);
        }
        // Shown when the holder comes back from the provider's save page to their EventBite ticket.
        session()->flash('success', "Your ticket was sent to {$wallet->label()}. Finish saving it there: this ticket page shows it as saved once {$wallet->label()} confirms.");

        return redirect()->away($action->payload())->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
