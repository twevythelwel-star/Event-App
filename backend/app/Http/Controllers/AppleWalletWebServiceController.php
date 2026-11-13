<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\WalletDeviceRegistration;
use App\Models\WalletPass;
use App\Services\Wallet\AppleWalletConfig;
use App\Services\Wallet\AppleWalletPassBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Apple's PassKit web service (the webServiceURL in each pass): devices register for updates, ask which passes
 * changed, and fetch the latest version. Calls about one pass carry "Authorization: ApplePass <token>", the pass's
 * own authentication token; a wrong or missing token is 401 and reveals nothing about the pass.
 */
class AppleWalletWebServiceController extends Controller
{
    public function __construct(
        private readonly AppleWalletConfig $config,
        private readonly AppleWalletPassBuilder $builder,
    ) {}

    /** POST /v1/devices/{device}/registrations/{passTypeId}/{serial} */
    public function register(Request $request, string $device, string $passTypeId, string $serial): Response
    {
        $pass = $this->authorizedPass($request, $passTypeId, $serial);
        if ($pass === null) {
            return response('', 401);
        }
        $pushToken = (string) $request->json('pushToken', '');
        if (!preg_match('/^[A-Fa-f0-9]{16,200}$/', $pushToken)) {
            return response('', 400);
        }
        $registration = WalletDeviceRegistration::where('device_library_identifier', $device)->where('wallet_pass_id', $pass->id)->first();
        if ($registration !== null) {
            $registration->forceFill(['push_token' => $pushToken])->save();

            return response('', 200);
        }
        WalletDeviceRegistration::create(['device_library_identifier' => $device, 'push_token' => $pushToken, 'wallet_pass_id' => $pass->id]);

        return response('', 201);
    }

    /** DELETE /v1/devices/{device}/registrations/{passTypeId}/{serial} */
    public function unregister(Request $request, string $device, string $passTypeId, string $serial): Response
    {
        $pass = $this->authorizedPass($request, $passTypeId, $serial);
        if ($pass === null) {
            return response('', 401);
        }
        WalletDeviceRegistration::where('device_library_identifier', $device)->where('wallet_pass_id', $pass->id)->delete();

        return response('', 200);
    }

    /** GET /v1/devices/{device}/registrations/{passTypeId}?passesUpdatedSince=tag */
    public function serials(Request $request, string $device, string $passTypeId): JsonResponse|Response
    {
        if ($passTypeId !== $this->config->passTypeId()) {
            return response('', 404);
        }
        $since = $request->query('passesUpdatedSince');
        $passes = WalletPass::where('pass_type_id', $passTypeId)
            ->whereIn('id', WalletDeviceRegistration::where('device_library_identifier', $device)->select('wallet_pass_id'))
            ->when(is_string($since) && ctype_digit($since), fn ($q) => $q->where('last_modified_at', '>', Carbon::createFromTimestamp((int) $since)))
            ->get(['serial_number', 'last_modified_at']);
        if ($passes->isEmpty()) {
            return response('', 204);
        }

        return response()->json([
            'serialNumbers' => $passes->pluck('serial_number')->values(),
            'lastUpdated' => (string) $passes->max(fn (WalletPass $p) => $p->last_modified_at->getTimestamp()),
        ]);
    }

    /** GET /v1/passes/{passTypeId}/{serial}: the latest version, rebuilt from the ticket record. */
    public function latest(Request $request, string $passTypeId, string $serial): Response
    {
        $pass = $this->authorizedPass($request, $passTypeId, $serial);
        if ($pass === null) {
            return response('', 401);
        }
        $since = $request->header('If-Modified-Since');
        if (is_string($since) && ($sinceTime = strtotime($since)) !== false && $pass->last_modified_at->getTimestamp() <= $sinceTime) {
            return response('', 304);
        }
        $order = $pass->order()->with(['items', 'event' => fn ($q) => $q->withoutGlobalScopes()])->first();
        if ($order === null) {
            return response('', 404);
        }

        return response($this->builder->package($pass, $order), 200, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Last-Modified' => $pass->last_modified_at->toRfc7231String(),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** POST /v1/log: Wallet's error reports about our passes. */
    public function log(Request $request): Response
    {
        foreach (array_slice((array) $request->json('logs', []), 0, 20) as $line) {
            Log::notice('Apple Wallet device log', ['message' => mb_substr((string) $line, 0, 500)]);
        }

        return response('', 200);
    }

    private function authorizedPass(Request $request, string $passTypeId, string $serial): ?WalletPass
    {
        $header = (string) $request->header('Authorization', '');
        if (!str_starts_with($header, 'ApplePass ') || $passTypeId !== $this->config->passTypeId()) {
            return null;
        }
        $pass = WalletPass::where('pass_type_id', $passTypeId)->where('serial_number', $serial)->first();

        return $pass !== null && $pass->matchesToken(substr($header, 10)) ? $pass : null;
    }
}
