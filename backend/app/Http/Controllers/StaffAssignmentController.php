<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\EventWorkerRole;
use App\Exceptions\SecurityException;
use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\StaffClaimCode;
use App\Services\EventWorkerService;
use App\Services\StaffClaimCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffAssignmentController extends Controller
{
    /**
     * Generate a new 10-minute staff claim code for the authenticated account holder.
     */
    public function generateClaimCode(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        try {
            $result = StaffClaimCodeService::generateCode(
                user: $user,
                lifetimeMinutes: 10,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'plain_code' => $result['plain_code'],
                    'expires_at' => $result['claim_code']->expires_at->toIso8601String(),
                    'claim_code_id' => $result['claim_code']->id,
                ]);
            }

            return back()->with([
                'claim_code_generated' => true,
                'plain_code' => $result['plain_code'],
                'code_expires_at' => $result['claim_code']->expires_at->format('g:i A'),
            ]);
        } catch (SecurityException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Revoke an active claim code.
     */
    public function revokeClaimCode(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        try {
            StaffClaimCodeService::revokeCode($user, $id);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Staff claim code revoked.']);
            }

            return back()->with('status', 'Staff claim code revoked.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the Event Staff Roster & Assignment Interface for an event.
     */
    public function showEventStaff(Request $request, string $eventId): View|JsonResponse
    {
        $user = $request->user();
        $event = Event::findOrFail($eventId);

        // Verify host ownership or supervisor assignment
        $isHost = $user->hasRole(AccountType::EVENT_HOST) && ($event->organizer_id === $user->organizer_id || empty($user->organizer_id));
        $isAdmin = $user->hasRole(AccountType::SYSTEM_ADMIN);
        $isSupervisor = EventWorkerAssignment::where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->where('worker_role', EventWorkerRole::EVENT_SUPERVISOR->value)
            ->where('is_active', true)
            ->exists();

        if (!$isHost && !$isAdmin && !$isSupervisor) {
            abort(403, 'Unauthorized: You do not have permission to manage staff for this event.');
        }

        $assignments = EventWorkerAssignment::with(['user', 'assignedBy'])
            ->where('event_id', $event->id)
            ->latest()
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'event' => $event,
                'assignments' => $assignments,
            ]);
        }

        return view('events.staff', [
            'event' => $event,
            'assignments' => $assignments,
            'isSupervisor' => $isSupervisor && !$isHost,
        ]);
    }

    /**
     * Validate an entered staff claim code and return masked recipient identification.
     * Prevents user enumeration by returning a generic error on failure.
     */
    public function verifyClaimCode(Request $request, string $eventId): JsonResponse
    {
        $request->validate([
            'claim_code' => ['required', 'string', 'max:32'],
        ]);

        $user = $request->user();
        $event = Event::findOrFail($eventId);

        $preview = StaffClaimCodeService::validateAndPreview(
            host: $user,
            event: $event,
            rawCode: (string) $request->input('claim_code')
        );

        if (!$preview['valid']) {
            return response()->json([
                'success' => false,
                'message' => $preview['message'] ?? 'Unable to verify this code. Ask the account holder to generate a new one.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'masked_name' => $preview['masked_name'],
            'claim_code_id' => $preview['claim_code_id'],
        ]);
    }

    /**
     * Create a staff assignment from a validated claim code.
     */
    public function assignStaff(Request $request, string $eventId): JsonResponse|RedirectResponse
    {
        $request->validate([
            'claim_code' => ['required', 'string', 'max:32'],
            'worker_role' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'expires_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['required', 'string'],
            'station_assignment' => ['nullable', 'string', 'max:64'],
            'custom_permissions' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        $event = Event::findOrFail($eventId);

        try {
            $assignment = EventWorkerService::createAssignmentFromClaimCode(
                host: $user,
                event: $event,
                rawCode: (string) $request->input('claim_code'),
                role: (string) $request->input('worker_role'),
                startsAt: (string) $request->input('starts_at'),
                expiresAt: (string) $request->input('expires_at'),
                timezone: (string) $request->input('timezone', 'America/New_York'),
                stationAssignment: (string) ($request->input('station_assignment') ?? 'all'),
                customPermissions: (array) ($request->input('custom_permissions') ?? [])
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Staff assignment offer created and awaiting recipient acceptance.',
                    'assignment' => $assignment,
                ], 201);
            }

            return back()->with('status', "Staff assignment offer created for {$assignment->worker_role->label()}. Recipient must sign in to accept.");
        } catch (SecurityException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Accept a pending staff assignment offer.
     */
    public function acceptAssignment(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        try {
            $assignment = EventWorkerService::acceptAssignment($user, $id);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Staff assignment accepted successfully.',
                    'assignment' => $assignment,
                ]);
            }

            return back()->with('status', "You have accepted the staff assignment for '{$assignment->event?->title}'.");
        } catch (SecurityException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Decline a pending staff assignment offer.
     */
    public function declineAssignment(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $reason = (string) ($request->input('reason') ?? '');

        try {
            $assignment = EventWorkerService::declineAssignment($user, $id, $reason);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Staff assignment offer declined.',
                ]);
            }

            return back()->with('status', 'Staff assignment offer declined.');
        } catch (SecurityException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Revoke a staff assignment immediately.
     */
    public function revokeAssignment(Request $request, string $eventId, int|string $assignmentId): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $assignment = EventWorkerAssignment::where('event_id', $eventId)->findOrFail($assignmentId);
        $reason = (string) ($request->input('reason') ?? 'Administrative revocation');

        try {
            EventWorkerService::revokeWorkerAssignment($user, $assignment, $reason);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Staff assignment revoked immediately.',
                ]);
            }

            return back()->with('status', 'Staff assignment revoked immediately.');
        } catch (SecurityException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
