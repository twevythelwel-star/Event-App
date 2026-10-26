<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SwitchRoleContextRequest;
use App\Services\ContextSwitchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoleSwitchController extends Controller
{
    /**
     * Switch active dashboard context role.
     */
    public function switchContext(SwitchRoleContextRequest $request): JsonResponse|RedirectResponse
    {
        $targetRole = $request->input('target_role');
        $result = ContextSwitchingService::switchDashboardContext(
            user: $request->user(),
            targetRole: $targetRole,
            ipAddress: $request->ip()
        );

        if ($request->wantsJson()) {
            $status = $result['success'] ? 200 : 403;
            return response()->json($result, $status);
        }

        if ($result['success']) {
            $redirect = $result['redirect_route'] ?? 'attendee.dashboard';
            return redirect()->route($redirect)->with('success', $result['message']);
        }

        return back()->with('error', $result['error'] ?? 'Could not switch context role.');
    }

    /**
     * Backward-compatible alias for switchContext.
     */
    public function switchRole(SwitchRoleContextRequest $request): JsonResponse|RedirectResponse
    {
        return $this->switchContext($request);
    }
}
