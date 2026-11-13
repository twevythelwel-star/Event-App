<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderPlacementService;
use App\Services\Wallet\AppleWalletPassBuilder;
use App\Services\Wallet\WalletPassService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** "Add to Apple Wallet": the signed .pkpass for the signed-in ticket holder. */
class AppleWalletController extends Controller
{
    public function __construct(
        private readonly OrderPlacementService $orders,
        private readonly WalletPassService $passes,
        private readonly AppleWalletPassBuilder $builder,
    ) {}

    public function download(Request $request, Order $order): Response|RedirectResponse
    {
        $user = $request->user();
        // Someone else's ticket is "not found", as on the ticket page.
        abort_unless($this->orders->isUserAuthorizedForOrder($order, $user), 404);
        $order->load(['items', 'event' => fn ($q) => $q->withoutGlobalScopes()]);

        if (($refusal = $this->passes->refusal($order, $user)) !== null) {
            return redirect()->route('tickets.show', $order)->with('error', $refusal);
        }

        $pass = $this->passes->passFor($order, $user);
        $bytes = $this->builder->package($pass, $order);

        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Content-Disposition' => 'attachment; filename="eventbite-' . preg_replace('/[^A-Za-z0-9-]/', '', (string) $order->ticket_code) . '.pkpass"',
            'Last-Modified' => $pass->last_modified_at->toRfc7231String(),
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
