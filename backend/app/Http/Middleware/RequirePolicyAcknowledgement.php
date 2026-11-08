<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Compliance\PolicyCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When an administrator publishes a material policy change, or a new mandatory policy, everyone it applies to must
 * acknowledge it at their next interaction:
 *   - the next page they open takes them to the acknowledgement page, then back where they were going;
 *   - every action that changes something (buying, creating or publishing events, and so on) is refused until they
 *     acknowledge it; JSON clients get 428 Precondition Required with the policies to accept.
 * Reading the policies, signing out and the acknowledgement itself always work.
 */
class RequirePolicyAcknowledgement
{
    /** Routes always allowed, so a person can read, acknowledge or leave. */
    private const ALLOWED = [
        'policies.acknowledge', 'policies.acknowledge.store', 'logout', 'login', 'login.*', 'password.*', 'verification.*',
        'host.compliance', 'host.compliance.policies', 'legal.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || $request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        $outstanding = app(PolicyCatalog::class)->outstanding($user, updatesOnly: true);
        if ($outstanding === []) {
            return $next($request);
        }

        $titles = array_values(array_map(fn ($spec) => $spec['title'], $outstanding));
        $wantsJson = $request->expectsJson() || $request->is('api/*');

        if ($request->isMethodSafe()) {
            if ($wantsJson) {
                return $next($request); // reading is never blocked; the next write is
            }
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('policies.acknowledge');
        }

        if ($wantsJson) {
            return response()->json([
                'message' => 'Accept the updated policies before continuing.',
                'outstanding_policies' => array_values(array_map(fn ($spec) => ['policy' => $spec['policy_name'], 'title' => $spec['title'], 'version' => $spec['version']], $outstanding)),
                'acknowledge_url' => route('policies.acknowledge'),
            ], 428);
        }

        return redirect()->route('policies.acknowledge')
            ->with('error', 'Accept the updated ' . (count($titles) === 1 ? 'policy' : 'policies') . ' before continuing: ' . implode(', ', $titles) . '.');
    }
}
