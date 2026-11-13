<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\WalletPass;
use App\Services\Wallet\SamsungWalletConfig;
use App\Services\Wallet\SamsungWalletService;
use App\Services\Wallet\WalletPassManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * The partner-server endpoints Samsung Wallet calls (the "Partner Get card data" and "Partner Send card state" URLs
 * registered in the Partners Portal as APP_URL/wallet/samsung). Every call must carry Samsung's Authorization token,
 * verified against Samsung's certificate and bound to this method and path; anything else is 401 and reveals nothing.
 * While Samsung Wallet is not configured these endpoints answer 404.
 */
class SamsungWalletPartnerController extends Controller
{
    public function __construct(
        private readonly SamsungWalletService $samsung,
        private readonly SamsungWalletConfig $config,
        private readonly WalletPassManager $wallets,
    ) {}

    /** GET /wallet/samsung/cards/{cardId}/{refId} - Get Card Data: the latest card, as cdata. */
    public function cardData(Request $request, string $cardId, string $refId): JsonResponse|Response
    {
        if (($pass = $this->authorizedPass($request, $cardId, $refId)) instanceof Response) {
            return $pass;
        }
        $order = Order::withoutGlobalScopes()->with(['items', 'event' => fn ($q) => $q->withoutGlobalScopes()])->find($pass->order_id);
        if ($order === null) {
            return response('', 204);
        }

        try {
            $cdata = $this->samsung->cdata($pass, $order);
        } catch (\App\Services\Wallet\Exceptions\WalletProviderException $e) {
            Log::warning('wallet.provider_error', ['provider' => 'samsung', 'pass' => $pass->id, 'error' => $e->code()]);

            return response('', 503);
        }

        return response()->json(['cdata' => $cdata])->header('Cache-Control', 'no-store');
    }

    /** POST /wallet/samsung/cards/{cardId}/{refId}?cc2=&event= - Send Card State: ADDED, UPDATED, DELETED… */
    public function cardState(Request $request, string $cardId, string $refId): Response
    {
        if (($pass = $this->authorizedPass($request, $cardId, $refId)) instanceof Response) {
            return $pass;
        }
        $cc2 = (string) $request->query('cc2', '');
        $event = strtoupper((string) $request->query('event', ''));
        if (!preg_match('/^[A-Za-z]{2}$/', $cc2) || !preg_match('/^[A-Z_]{1,32}$/', $event)) {
            return response('', 400);
        }
        match ($event) {
            // Samsung's own confirmation that the card is in the holder's wallet: only now is the pass "saved".
            'ADDED' => $this->wallets->markSaved($pass, 'samsung_added', ['cc2' => strtoupper($cc2)]),
            'DELETED' => $this->wallets->markRemoved($pass),
            default => Log::info('wallet.samsung_card_state', ['pass' => $pass->id, 'event' => $event]),
        };

        return response('', 200);
    }

    private function authorizedPass(Request $request, string $cardId, string $refId): WalletPass|Response
    {
        if (!$this->config->configured()) {
            return response('', 404);
        }
        if (!$this->samsung->verifyInboundToken($request->header('Authorization'), $request->method(), '/' . $request->path())) {
            Log::notice('wallet.samsung_unauthorized');

            return response('', 401);
        }
        if (!hash_equals($this->config->cardId(), $cardId)) {
            return response('', 404);
        }
        $pass = WalletPass::where('provider', 'samsung')->where('external_id', $refId)->first();

        return $pass ?? response('', 404);
    }
}
