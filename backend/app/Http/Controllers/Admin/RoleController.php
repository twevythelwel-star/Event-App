<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Enums\EventWorkerRole;
use App\Http\Controllers\Controller;
use App\Models\AdminPermission;
use App\Models\EventWorkerAssignment;
use App\Models\Organizer;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    /**
     * Delegated administrator capabilities, with what each one lets the person do.
     * Keys match admin_permissions columns.
     */
    public const CAPABILITIES = [
        'can_support_orders' => ['label' => 'User support', 'consequence' => 'Can search and view user accounts linked to their organizers.'],
        'can_manage_events' => ['label' => 'Event oversight', 'consequence' => 'Can view events, their hosts and order activity.'],
        'can_moderate_content' => ['label' => 'Event moderation', 'consequence' => 'Can unpublish or republish events and close or reopen ordering.'],
        'can_view_payments' => ['label' => 'Payment oversight', 'consequence' => 'Can view orders, amounts and payment status. Cannot verify payments.'],
        'can_view_reports' => ['label' => 'Reports', 'consequence' => 'Can view report summaries. Financial figures and exports also need Payment oversight.'],
        'can_access_audit_logs' => ['label' => 'Audit logs', 'consequence' => 'Can read audit history for their organizers. Nobody can edit it.'],
    ];

    public function index(Request $request): View
    {
        Gate::authorize(Admin::ROLES_VIEW);

        $roleCounts = [
            AccountType::SYSTEM_ADMIN->value => User::query()
                ->where(fn ($q) => $q->where('is_system_admin', true)
                    ->orWhereIn('id', fn ($s) => $s->select('user_id')->from('user_roles')->where('role', 'system_admin')->where('is_active', true)))
                ->count(),
            AccountType::ADMINISTRATOR->value => $this->holders('admin'),
            AccountType::EVENT_HOST->value => $this->holders('event_host'),
            AccountType::ATTENDEE->value => User::count(),
        ];

        $staffCounts = EventWorkerAssignment::where('is_active', true)
            ->selectRaw('worker_role, COUNT(*) as total')
            ->groupBy('worker_role')
            ->pluck('total', 'worker_role');

        $administrators = collect();
        $organizers = collect();
        if (Gate::allows(Admin::ROLES_MANAGE)) {
            $administrators = User::whereIn('id', fn ($s) => $s->select('user_id')->from('user_roles')->where('role', 'admin')->where('is_active', true))
                ->with('adminPermissions')
                ->orderBy('name')
                ->get()
                ->reject(fn (User $u) => $u->hasRole(AccountType::SYSTEM_ADMIN));
            $organizers = Organizer::orderBy('name')->get(['id', 'name']);
        }

        return view('admin.roles.index', [
            'accountTypes' => AccountType::cases(),
            'roleCounts' => $roleCounts,
            'staffRoles' => EventWorkerRole::cases(),
            'staffCounts' => $staffCounts,
            'administrators' => $administrators,
            'organizers' => $organizers,
            'capabilities' => self::CAPABILITIES,
            'canManage' => Gate::allows(Admin::ROLES_MANAGE),
        ]);
    }

    public function updateAdministrator(Request $request, User $user): RedirectResponse
    {
        Gate::authorize(Admin::ROLES_MANAGE, $user);

        if (!$user->hasRole(AccountType::ADMINISTRATOR) || $user->hasRole(AccountType::SYSTEM_ADMIN)) {
            return back()->with('error', 'Only delegated administrators have editable permissions.');
        }

        $rules = [
            'scope' => ['required', Rule::in(['all', 'assigned_events'])],
            'organizer_ids' => ['nullable', 'array'],
            'organizer_ids.*' => ['string', Rule::exists('organizers', 'id')],
            'confirm' => ['accepted'],
        ];
        foreach (array_keys(self::CAPABILITIES) as $flag) {
            $rules[$flag] = ['nullable', 'boolean'];
        }
        $data = $request->validate($rules, ['confirm.accepted' => 'Tick the box to confirm these permission changes.']);

        $permissions = AdminPermission::firstOrNew(['user_id' => $user->id]);
        $before = $this->describe($permissions);

        $permissions->scope = $data['scope'];
        $permissions->assigned_organizer_ids = $data['scope'] === 'all' ? [] : array_values(array_unique($data['organizer_ids'] ?? []));
        foreach (array_keys(self::CAPABILITIES) as $flag) {
            $permissions->{$flag} = (bool) ($data[$flag] ?? false);
        }
        $permissions->save();

        $after = $this->describe($permissions);
        if ($before === $after) {
            return back()->with('success', 'No changes to save.');
        }

        AuditLogService::log(
            action: 'admin_permissions_changed',
            targetEntity: 'users',
            targetId: (string) $user->id,
            details: "Administrator permissions for {$user->name}: {$before} -> {$after}",
            actor: $request->user(),
        );

        return back()->with('success', "Permissions updated for {$user->name}.");
    }

    private function holders(string $role): int
    {
        return User::whereIn('id', fn ($s) => $s->select('user_id')->from('user_roles')->where('role', $role)->where('is_active', true))->count();
    }

    private function describe(AdminPermission $permissions): string
    {
        $flags = collect(array_keys(self::CAPABILITIES))->filter(fn ($flag) => (bool) $permissions->{$flag})->implode(',');
        $organizers = $permissions->scope === 'all' ? 'all organizers' : count($permissions->assigned_organizer_ids ?? []) . ' organizer(s)';

        return "[{$flags}] on {$organizers}";
    }
}
