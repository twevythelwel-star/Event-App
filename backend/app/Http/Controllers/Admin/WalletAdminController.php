<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Wallet\WalletAdminStatus;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin wallet page: provider configuration and approval, certificate expiry, pass and operation totals, provider
 * errors, last sync and history, with warnings. Read-only; credentials stay in the environment and are never shown.
 */
class WalletAdminController extends Controller
{
    public function index(WalletAdminStatus $status): View
    {
        Gate::authorize(Admin::WALLETS_VIEW);

        return view('admin.wallets.index', [
            'providers' => $status->providers(),
            'totals' => $status->totals(),
            'providerErrors' => $status->errors(),
            'history' => $status->history(),
            'warnings' => $status->warnings(),
        ]);
    }
}
