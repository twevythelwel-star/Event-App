<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Models\AdminPermission;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Models\OrganizerUser;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditLogService;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /** Roles the console can grant. Attendee is implicit for every account and never granted or revoked. */
    private const GRANTABLE_ROLES = ['event_host', 'admin', 'system_admin'];

    private const STATUSES = ['active', 'suspended', 'pending_verification'];

    public function index(Request $request): View
    {
        Gate::authorize(Admin::USERS_VIEW);
        $admin = $request->user();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['system_admin', 'admin', 'event_host', 'staff', 'attendee'])],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);

        $query = Admin::applyUserScope(User::query(), $admin)
            ->with(['userRoles' => fn ($q) => $q->where('is_active', true)])
            ->withCount(['workerAssignments as active_assignments_count' => fn ($q) => $q->where('is_active', true)]);

        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
            });
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($role = $filters['role'] ?? null) {
            $this->applyRoleFilter($query, $role);
        }

        return view('admin.users.index', [
            'users' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(Request $request, User $user): View
    {
        Gate::authorize(Admin::USER_VIEW, $user);
        $admin = $request->user();
        $scope = Admin::organizerScope($admin);

        $memberships = OrganizerUser::with('organizer:id,name')
            ->where('user_id', $user->id)
            ->when($scope !== null, fn ($q) => $q->whereIn('organizer_id', $scope))
            ->get();

        $assignments = EventWorkerAssignment::with(['event' => fn ($q) => $q->allTenants()->select('id', 'title', 'date_str')])
            ->where('user_id', $user->id)
            ->when($scope !== null, fn ($q) => $q->whereIn('organizer_id', $scope))
            ->latest()
            ->take(20)
            ->get();

        $orderCount = Admin::applyOrganizerScope(Order::allTenants(), $admin)->where('user_id', $user->id)->count();

        $activity = collect();
        if (Gate::allows(Admin::AUDIT_VIEW)) {
            $activity = Admin::applyAuditScope(SecurityAuditLog::query(), $admin)
                ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere(fn ($t) => $t->where('target_entity', 'users')->where('target_id', (string) $user->id)))
                ->latest('created_at')
                ->take(10)
                ->get();
        }

        $revealContact = $request->boolean('reveal') && Gate::allows(Admin::USER_CONTACT);
        if ($revealContact) {
            AuditLogService::logPiiAccess($admin, 'users', (string) $user->id, 'Revealed contact phone in the administration console.', $request->ip());
        }

        $grantable = collect(self::GRANTABLE_ROLES)
            ->filter(fn ($role) => !$user->hasRole($role) && Gate::allows(Admin::ROLES_MANAGE, $user) && $admin->canAssignRole($user, $role))
            ->values();

        return view('admin.users.show', [
            'subject' => $user,
            'roles' => $user->getAuthorizedRoles(),
            'adminPermissions' => $user->hasRole(AccountType::ADMINISTRATOR) ? $user->adminPermissions : null,
            'memberships' => $memberships,
            'assignments' => $assignments,
            'orderCount' => $orderCount,
            'activity' => $activity,
            'revealContact' => $revealContact,
            'canRevealContact' => Gate::allows(Admin::USER_CONTACT) && filled($user->phone),
            'canManageStatus' => Gate::allows(Admin::USER_STATUS, $user),
            'canManageRoles' => Gate::allows(Admin::ROLES_MANAGE, $user),
            'grantableRoles' => $grantable,
        ]);
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        Gate::authorize(Admin::USER_VIEW, $user);
        Gate::authorize(Admin::USER_STATUS, $user);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'confirm' => ['accepted'],
        ], [
            'reason.required' => 'Give a reason. It is saved in the audit log.',
            'confirm.accepted' => 'Tick the box to confirm this change.',
        ]);

        if ($user->status === $data['status']) {
            return back()->with('error', "This account is already {$data['status']}.");
        }

        $previous = $user->status;
        $user->forceFill(['status' => $data['status']])->save();

        // A suspension must take effect now, not at the next sign-in.
        if ($data['status'] === 'suspended' && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        AuditLogService::log(
            action: 'user_status_changed',
            targetEntity: 'users',
            targetId: (string) $user->id,
            details: "Account status {$previous} -> {$data['status']}. Reason: {$data['reason']}",
            actor: $request->user(),
        );

        return back()->with('success', $data['status'] === 'suspended'
            ? 'Account suspended and signed out everywhere.'
            : 'Account reactivated.');
    }

    public function grantRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize(Admin::USER_VIEW, $user);
        Gate::authorize(Admin::ROLES_MANAGE, $user);

        $data = $request->validate([
            'role' => ['required', Rule::in(self::GRANTABLE_ROLES)],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Tick the box to confirm this change.']);

        $role = $data['role'];
        Gate::authorize('assignRole', [$user, $role]);

        if ($user->hasRole($role)) {
            return back()->with('error', 'This account already has that role.');
        }

        DB::transaction(function () use ($user, $role, $request) {
            UserRole::updateOrCreate(
                ['user_id' => $user->id, 'role' => $role],
                ['is_active' => true, 'assigned_by' => $request->user()->id, 'assigned_at' => now()],
            );

            // New administrators start with no capabilities and no organizers (least privilege).
            if ($role === AccountType::ADMINISTRATOR->value) {
                AdminPermission::firstOrCreate(['user_id' => $user->id], [
                    'scope' => 'assigned_events',
                    'can_manage_events' => false,
                    'can_moderate_content' => false,
                    'can_support_orders' => false,
                    'can_view_payments' => false,
                    'can_view_reports' => false,
                    'can_access_audit_logs' => false,
                    'assigned_organizer_ids' => [],
                ]);
            }
        });

        AuditLogService::log(
            action: 'user_role_granted',
            targetEntity: 'users',
            targetId: (string) $user->id,
            details: "Granted role {$role}.",
            actor: $request->user(),
        );

        return back()->with('success', AccountType::from($role)->label() . ' role granted.'
            . ($role === 'admin' ? ' Set their permissions under Roles & Permissions; they start with none.' : ''));
    }

    public function revokeRole(Request $request, User $user, string $role): RedirectResponse
    {
        Gate::authorize(Admin::USER_VIEW, $user);
        Gate::authorize(Admin::ROLES_MANAGE, $user);

        abort_unless(in_array($role, self::GRANTABLE_ROLES, true), 404);
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Tick the box to confirm this change.']);
        Gate::authorize('assignRole', [$user, $role]);

        if (!$user->hasRole($role)) {
            return back()->with('error', 'This account does not have that role.');
        }

        if ($role === AccountType::SYSTEM_ADMIN->value && $this->activeSystemAdminCount(excluding: $user) === 0) {
            return back()->with('error', 'You cannot remove the last System Administrator.');
        }

        DB::transaction(function () use ($user, $role) {
            UserRole::where('user_id', $user->id)->where('role', $role)->update(['is_active' => false]);
            if ($role === AccountType::SYSTEM_ADMIN->value && $user->is_system_admin) {
                $user->forceFill(['is_system_admin' => false])->save();
            }
            if ($user->current_context_role === $role) {
                $user->forceFill(['current_context_role' => AccountType::ATTENDEE->value])->save();
            }
        });

        AuditLogService::log(
            action: 'user_role_revoked',
            targetEntity: 'users',
            targetId: (string) $user->id,
            details: "Revoked role {$role}.",
            actor: $request->user(),
        );

        return back()->with('success', AccountType::from($role)->label() . ' role removed.');
    }

    private function applyRoleFilter(Builder $query, string $role): void
    {
        $hasRole = fn (string $value) => fn ($q) => $q->select('user_id')->from('user_roles')->where('role', $value)->where('is_active', true);

        match ($role) {
            'system_admin' => $query->where(fn ($q) => $q->where('is_system_admin', true)->orWhereIn('id', $hasRole('system_admin'))),
            'staff' => $query->whereIn('id', fn ($q) => $q->select('user_id')->from('event_worker_assignments')->where('is_active', true)),
            'attendee' => $query->where('is_system_admin', false)
                ->whereNotIn('id', fn ($q) => $q->select('user_id')->from('user_roles')->where('is_active', true)->whereIn('role', ['system_admin', 'admin', 'event_host'])),
            default => $query->whereIn('id', $hasRole($role)),
        };
    }

    private function activeSystemAdminCount(?User $excluding = null): int
    {
        return User::where('status', 'active')
            ->when($excluding, fn ($q) => $q->whereKeyNot($excluding->id))
            ->where(fn ($q) => $q->where('is_system_admin', true)
                ->orWhereIn('id', fn ($s) => $s->select('user_id')->from('user_roles')->where('role', 'system_admin')->where('is_active', true)))
            ->count();
    }
}
