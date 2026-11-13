<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\EventWorkerRole;
use App\Enums\TenantRole;
use App\Exceptions\SecurityException;
use App\Models\AttendeeTicket;
use App\Models\CashPayment;
use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Models\OrganizerUser;
use App\Models\OrderItem;
use App\Models\SecurityAuditLog;
use App\Models\TicketAdmission;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventWorkerService
{
    /**
     * Assign a worker to an event with a specific role, station, and permissions.
     */
    public static function assignWorker(
        User $assigner,
        Event $event,
        User $targetUser,
        EventWorkerRole|string $role,
        string $stationAssignment = 'all',
        array $customPermissions = []
    ): EventWorkerAssignment {
        $workerRole = $role instanceof EventWorkerRole ? $role : EventWorkerRole::from($role);

        // Security check: Assigner must be this event's host, a current supervisor on it, or a system admin
        $managerRole = self::staffManagerRole($assigner, $event);
        if ($managerRole === null) {
            throw new SecurityException('Unauthorized: You do not have permission to manage event workers for this event.');
        }

        // Prohibit privilege escalation: Supervisors cannot create other Supervisors or give platform permissions
        if ($managerRole === 'supervisor') {
            if ($workerRole === EventWorkerRole::EVENT_SUPERVISOR) {
                throw new SecurityException('Privilege Escalation Blocked: Event Supervisors cannot appoint additional Event Supervisors.');
            }
        }

        // Filter and validate custom permissions: never allow prohibited actions
        $sanitizedCustomPermissions = [];
        if ($workerRole === EventWorkerRole::CUSTOM_RESTRICTED_WORKER) {
            $allowedPool = [
                'view_relevant_orders',
                'record_cash_received',
                'review_transfer_references',
                'verify_payments',
                'scan_qr_tickets',
                'validate_ticket_status',
                'record_entry',
                'view_assigned_food_items',
                'view_collection_instructions',
                'mark_quantities_collected',
                'see_remaining_quantities',
            ];

            foreach ($customPermissions as $perm) {
                if (in_array($perm, $allowedPool, true) && !$workerRole->isProhibited($perm)) {
                    $sanitizedCustomPermissions[] = $perm;
                }
            }
        }

        $assignment = EventWorkerAssignment::updateOrCreate(
            [
                'organizer_id' => $event->organizer_id,
                'event_id' => $event->id,
                'user_id' => $targetUser->id,
            ],
            [
                'worker_role' => $workerRole,
                'station_assignment' => $stationAssignment,
                'custom_permissions' => $sanitizedCustomPermissions,
                'assigned_by' => $assigner->id,
                'is_active' => true,
                'last_active_at' => now(),
            ]
        );

        // Immutable security audit log
        AuditLogService::log(
            action: 'event_worker_assigned',
            targetEntity: 'event_worker_assignments',
            targetId: (string) $assignment->id,
            details: "User {$targetUser->email} assigned as {$workerRole->label()} (station: {$stationAssignment}) by {$assigner->email}.",
            actor: $assigner
        );

        return $assignment;
    }

    /**
     * Scan ticket and record entry while strictly enforcing:
     * 1. Ticket authenticity
     * 2. Event association
     * 3. Validity period
     * 4. Ticket status
     * 5. Duplicate-redemption prevention
     * 6. Authorized redemption
     * And recording successful redemptions and rejection reasons in the audit trail.
     */
    public static function scanAndValidateTicket(
        User $worker,
        Order $order,
        ?string $expectedEventId = null,
        bool $manual = false,
        ?string $requestId = null,
        ?AttendeeTicket $attendee = null
    ): array {
        // 6. Authorized redemption check
        $scanningEventId = $expectedEventId ?? $order->event_id;
        $assignment = EventWorkerAssignment::where('user_id', $worker->id)
            ->where('organizer_id', (string) $order->organizer_id)
            ->where(fn($q) => $q->where('event_id', $scanningEventId)->orWhereNull('event_id'))
            ->where('is_active', true)
            ->first();

        // Any host account used to pass here, whichever organizer it belonged to; now only this event's organizer.
        $canScan = $worker->hasRole(AccountType::SYSTEM_ADMIN) ||
            self::managesOrganizer($worker, (string) $order->organizer_id) ||
            ($assignment && $assignment->canPerform('scan_qr_tickets'));

        if (!$canScan) {
            AuditLogService::log(
                action: 'ticket_redemption_rejected',
                targetEntity: 'orders',
                targetId: (string) $order->id,
                details: "Unauthorized redemption attempt by worker {$worker->email} on ticket {$order->ticket_code}.",
                actor: $worker
            );
            throw new SecurityException("Unauthorized: Worker {$worker->email} is not permitted to scan tickets.");
        }

        // The check and the admission run on the order row locked for update, so two scanners reading the
        // same ticket at the same moment cannot both admit it: the second waits and then sees the check-in.
        return DB::transaction(function () use ($worker, $order, $expectedEventId, $manual, $assignment, $requestId, $attendee) {
            $locked = Order::query()->withoutGlobalScopes()->where('id', $order->id)->lockForUpdate()->first();
            if ($attendee !== null) {
                // One attendee's own ticket: admitted on its own row, under the same order lock.
                if ((string) $attendee->order_id !== (string) $order->id) {
                    throw new SecurityException('That attendee ticket does not belong to this order.');
                }
                $lockedAttendee = AttendeeTicket::whereKey($attendee->id)->lockForUpdate()->first() ?? $attendee;

                return self::admitAttendee($worker, $locked ?? $order, $lockedAttendee, $expectedEventId, $manual, $assignment, $requestId);
            }
            if ($locked !== null && $locked !== $order) {
                $order->checked_in_at = $locked->checked_in_at;
                $order->checked_in_by = $locked->checked_in_by;
                $order->status = $locked->status;
                $order->check_in_request_id = $locked->check_in_request_id;
                $order->token_revoked_at = $locked->token_revoked_at;
                $order->revocation_reason = $locked->revocation_reason;
            }

            $locked ??= $order;
            if ($requestId !== null && hash_equals((string) $locked->check_in_request_id, $requestId)
                && (int) $locked->checked_in_by === (int) $worker->id && $locked->checked_in_at !== null) {
                return ['success' => true, 'already_admitted' => false, 'valid' => true, 'replayed' => true,
                    'rejection_reason' => null, 'ticket' => ['order_number' => $locked->order_number,
                        'ticket_code' => $locked->ticket_code, 'customer_name' => $locked->customer_name,
                        'status' => $locked->status, 'checked_in_at' => $locked->checked_in_at?->format('Y-m-d H:i:s')]]
                    + self::verdict(true, null, 'Whole party on this order');
            }

            return self::admitLockedTicket($worker, $locked, $order, $expectedEventId, $manual, $assignment, $requestId);
        });
    }

    /**
     * The checks every admission makes on the order, whether its pass or one attendee's ticket was scanned:
     * authenticity, event, admission window, revocation and payment.
     *
     * @return array{bool, bool, bool, bool, ?string, ?string} authentic, event matched, period valid, status valid, reason code, reason
     */
    private static function orderChecks(Order $order, ?string $expectedEventId): array
    {
        // 1. Authenticity check: Genuine ticket code & order number must exist
        $isAuthentic = !empty($order->ticket_code) && !empty($order->order_number);
        $rejectionReason = null;
        $reasonCode = null;

        // 2. Event association check
        $isEventMatched = true;
        if ($expectedEventId !== null && (string) $order->event_id !== (string) $expectedEventId) {
            $isEventMatched = false;
            $reasonCode = 'wrong_event';
            $rejectionReason = "Event association mismatch: Ticket belongs to event {$order->event_id}, not {$expectedEventId}.";
        }

        // 3. Validity period check
        $isPeriodValid = true;
        if ($order->status === 'expired' || ($order->closed_at && $order->all_handed_over)) {
            $isPeriodValid = false;
            $reasonCode = $reasonCode ?? 'ticket_expired';
            $rejectionReason = $rejectionReason ?? "Ticket validity window has expired or event is closed.";
        }

        // 3b. Admission window: the gate opens at doors_open_time (or the event start) and closes at the event end.
        $admissionEvent = $order->event ?? Event::withoutGlobalScopes()->find($order->event_id);

        // 3a. The event itself must be live: a cancelled, unpublished or deleted event admits nobody.
        if (!$admissionEvent || $admissionEvent->status !== 'published') {
            $isPeriodValid = false;
            $reasonCode = $reasonCode ?? 'event_not_active';
            $eventState = $admissionEvent?->status ?: 'missing';
            $rejectionReason = $rejectionReason ?? ($eventState === 'cancelled'
                ? 'This event was cancelled; its tickets do not admit anyone.'
                : "This event is not live (status: {$eventState}); admission is closed.");
        }

        if ($admissionEvent) {
            $tz = (string) ($admissionEvent->timezone ?: 'America/New_York');
            $localDate = $admissionEvent->starts_at?->copy()->setTimezone($tz)->format('Y-m-d')
                ?? ($admissionEvent->event_date_iso ? \Illuminate\Support\Carbon::parse((string) $admissionEvent->event_date_iso)->format('Y-m-d') : null);
            $gateOpensAt = null;
            if ($localDate && $admissionEvent->doors_open_time) {
                $gateOpensAt = \Illuminate\Support\Carbon::createFromFormat('Y-m-d H:i', "{$localDate} {$admissionEvent->doors_open_time}", $tz);
            }
            $gateOpensAt ??= $admissionEvent->starts_at;

            if ($gateOpensAt && now()->lt($gateOpensAt)) {
                $isPeriodValid = false;
                $reasonCode = $reasonCode ?? 'event_not_open';
                $rejectionReason = $rejectionReason ?? 'Admission has not opened yet — doors open at ' . $gateOpensAt->copy()->setTimezone($tz)->format('g:i A T') . '.';
            } elseif ($admissionEvent->ends_at && now()->gt($admissionEvent->ends_at)) {
                $isPeriodValid = false;
                $reasonCode = $reasonCode ?? 'event_not_open';
                $rejectionReason = $rejectionReason ?? 'This event has ended; admission is closed.';
            }
        }

        // 4. Revocation and Ticket status check (must not be revoked, and must be paid or confirmed)
        $isNotRevoked = $order->token_revoked_at === null && !in_array($order->status, ['refunded', 'rejected', 'cancelled', 'expired'], true);
        if (!$isNotRevoked) {
            $revReason = $order->revocation_reason ?: "status: {$order->status}";
            $reasonCode = $reasonCode ?? 'ticket_revoked';
            // A revoked QR says so; a refunded, cancelled or rejected order names its status.
            $rejectionReason = $rejectionReason ?? ($order->token_revoked_at !== null
                ? "Ticket has been revoked ({$revReason})."
                : "Invalid ticket status: {$order->status}. This ticket is no longer valid for entry.");
        }

        $isStatusValid = $isNotRevoked && in_array($order->status, ['paid', 'confirmed'], true);
        if (!$isStatusValid) {
            $reasonCode = $reasonCode ?? 'payment_pending';
            $rejectionReason = $rejectionReason ?? "Invalid ticket status: {$order->status}. Must be confirmed or paid.";
        }

        // 4b. A finished event with no end time set still closes (App\Support\TicketLifecycle): its tickets are
        // keepsakes now and never admit anyone. Checked after the ticket's own status, which is the more specific reason.
        if ($admissionEvent && !$admissionEvent->ends_at && \App\Support\TicketLifecycle::eventIsOver($admissionEvent)) {
            $isPeriodValid = false;
            $reasonCode = $reasonCode ?? 'event_not_open';
            $rejectionReason = $rejectionReason ?? 'This event has ended; admission is closed.';
        }

        return [$isAuthentic, $isEventMatched, $isPeriodValid, $isStatusValid, $reasonCode, $rejectionReason];
    }

    /**
     * Admits one attendee on their own ticket. The order must pass the same checks as its pass; the attendee's
     * ticket must not be used, and the order's pass must not already have admitted the whole party. Once every
     * attendee is in, the order counts as checked in.
     */
    private static function admitAttendee(
        User $worker,
        Order $order,
        AttendeeTicket $attendee,
        ?string $expectedEventId,
        bool $manual,
        ?EventWorkerAssignment $assignment,
        ?string $requestId
    ): array {
        $total = AttendeeTicket::where('order_id', $order->id)->count();
        $seat = ['ticket_number' => $attendee->ticket_number, 'sequence' => $attendee->sequence, 'of' => $total];

        if ($requestId !== null && hash_equals((string) $attendee->check_in_request_id, $requestId)
            && (int) $attendee->checked_in_by === (int) $worker->id && $attendee->checked_in_at !== null) {
            return ['success' => true, 'already_admitted' => false, 'valid' => true, 'replayed' => true, 'reason_code' => null,
                'rejection_reason' => null, 'ticket' => self::scanTicketData($order, $assignment) + ['attendee' => $seat]]
                + self::verdict(true, null, "Guest {$attendee->sequence} of {$total}");
        }

        [$isAuthentic, $isEventMatched, $isPeriodValid, $isStatusValid, $reasonCode, $rejectionReason] = self::orderChecks($order, $expectedEventId);

        $admittedAt = $attendee->checked_in_at ?? $order->checked_in_at;
        $isAlreadyAdmitted = $admittedAt !== null;
        if ($isAlreadyAdmitted) {
            $reasonCode = $reasonCode ?? 'already_admitted';
            $rejectionReason = $rejectionReason ?? 'Duplicate redemption prevented: ' . ($attendee->checked_in_at !== null
                ? "Ticket {$attendee->ticket_number} was already admitted at {$attendee->checked_in_at->format('Y-m-d H:i:s')}."
                : "this order's whole party was already admitted at {$order->checked_in_at->format('Y-m-d H:i:s')}.");
        }

        $isSuccess = $isAuthentic && $isEventMatched && $isPeriodValid && $isStatusValid && !$isAlreadyAdmitted;

        // 9. Atomic admission: the unique admission record is the database's own guard against a second admission.
        if ($isSuccess && !self::recordAdmissions([$attendee], $order, $worker, TicketAdmission::VIA_ATTENDEE_TICKET, $requestId)) {
            $isSuccess = false;
            $isAlreadyAdmitted = true;
            $reasonCode = 'already_admitted';
            $rejectionReason = "Duplicate redemption prevented: Ticket {$attendee->ticket_number} was already admitted.";
        }

        if ($isSuccess) {
            $attendee->forceFill(['checked_in_at' => now(), 'checked_in_by' => $worker->id, 'check_in_request_id' => $requestId])->save();
            $waiting = AttendeeTicket::where('order_id', $order->id)->whereNull('checked_in_at')->exists();
            if (!$waiting) {
                $order->checked_in_at = $attendee->checked_in_at;
                $order->checked_in_by = $worker->id;
                $order->save();
            }
            AuditLogService::log(
                action: 'ticket_redemption_verified',
                targetEntity: 'orders',
                targetId: (string) $order->id,
                details: "Attendee ticket {$attendee->ticket_number} ({$attendee->sequence} of {$total}, Order #{$order->order_number}) verified and admitted by {$worker->email}"
                    . ($manual ? ' using a typed code (manual check).' : ' by QR.'),
                actor: $worker
            );
        } else {
            AuditLogService::log(
                action: 'ticket_redemption_rejected',
                targetEntity: 'orders',
                targetId: (string) $order->id,
                details: "Attendee ticket {$attendee->ticket_number} redemption rejected by {$worker->email}. Reason: {$rejectionReason}",
                actor: $worker
            );
        }

        return [
            'success' => $isSuccess,
            'replayed' => false,
            'already_admitted' => $isAlreadyAdmitted,
            'valid' => $isSuccess,
            'reason_code' => $isSuccess ? null : ($reasonCode ?? 'invalid_ticket'),
            'rejection_reason' => $rejectionReason,
            'ticket' => self::scanTicketData($order, $assignment) + ['attendee' => $seat + [
                'checked_in_at' => $attendee->checked_in_at?->format('Y-m-d H:i:s'),
            ]],
            // Order, payment, ticket, admission and food statuses, reported separately.
            'statuses' => \App\Support\TicketStatuses::for($order, $attendee),
        ] + self::verdict($isSuccess, $rejectionReason, "Guest {$attendee->sequence} of {$total}");
    }

    /**
     * Writes one admission record per ticket under the unique key, inside a savepoint. False when any of them was
     * already admitted (the insert hit the unique key): nothing is then recorded for this scan.
     *
     * @param list<AttendeeTicket> $tickets
     */
    private static function recordAdmissions(array $tickets, Order $order, User $worker, string $via, ?string $requestId): bool
    {
        try {
            DB::transaction(function () use ($tickets, $order, $worker, $via, $requestId) {
                foreach ($tickets as $ticket) {
                    TicketAdmission::create([
                        'attendee_ticket_id' => $ticket->id, 'order_id' => $order->id, 'event_id' => $order->event_id,
                        'admitted_by' => $worker->id, 'admitted_via' => $via, 'request_id' => $requestId, 'admitted_at' => now(),
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /**
     * The plain answer for the person at the gate: admit or not, in a few words.
     *
     * @return array{result: string, headline: string}
     */
    private static function verdict(bool $admit, ?string $reason, string $who): array
    {
        return [
            'result' => $admit ? 'admit' : 'reject',
            'headline' => $admit ? "ADMIT · {$who}" : 'DO NOT ADMIT · ' . ($reason ?? 'Ticket not valid.'),
        ];
    }

    /** What a scanner sees of the order, sanitised for the worker's role. */
    private static function scanTicketData(Order $order, ?EventWorkerAssignment $assignment): array
    {
        $orderData = [
            'order_number' => $order->order_number,
            'ticket_code' => $order->ticket_code,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->email,
            'customer_phone' => $order->customer_phone,
            'status' => $order->status,
            'payment_method' => $order->method,
            'total_cents' => (int) $order->total_minor,
            'checked_in_at' => $order->checked_in_at?->format('Y-m-d H:i:s'),
        ];

        return $assignment ? $assignment->sanitizeOrder($orderData) : $orderData;
    }

    /**
     * @param Order $locked The order row as read under the lock (the same object as $order outside a database)
     * @param Order $order  The caller's copy, kept in step with the admission
     */
    private static function admitLockedTicket(
        User $worker,
        Order $locked,
        Order $order,
        ?string $expectedEventId,
        bool $manual,
        ?EventWorkerAssignment $assignment,
        ?string $requestId
    ): array {
        [$isAuthentic, $isEventMatched, $isPeriodValid, $isStatusValid, $reasonCode, $rejectionReason] = self::orderChecks($order, $expectedEventId);

        // 5. Duplicate-redemption prevention check
        $isAlreadyAdmitted = !empty($order->checked_in_at);
        if ($isAlreadyAdmitted) {
            $checkInStr = $order->checked_in_at instanceof \DateTimeInterface
                ? $order->checked_in_at->format('Y-m-d H:i:s')
                : (string) $order->checked_in_at;
            $reasonCode = $reasonCode ?? 'already_admitted';
            $rejectionReason = $rejectionReason ?? "Duplicate redemption prevented: Ticket was already admitted at {$checkInStr}.";
        } elseif (AttendeeTicket::where('order_id', $order->id)->whereNotNull('checked_in_at')->exists()) {
            // Some guests already came in on their own tickets: the order's pass must not admit the whole party again.
            $isAlreadyAdmitted = true;
            $reasonCode = $reasonCode ?? 'already_admitted';
            $rejectionReason = $rejectionReason ?? 'Duplicate redemption prevented: guests on this order were already admitted with their own tickets. Scan each remaining guest’s ticket.';
        }

        $isSuccess = $isAuthentic && $isEventMatched && $isPeriodValid && $isStatusValid && !$isAlreadyAdmitted;

        // The order's pass admits every guest still outside; each gets its unique admission record first.
        if ($isSuccess) {
            $remaining = AttendeeTicket::where('order_id', $locked->id)->whereNull('checked_in_at')->lockForUpdate()->get()->all();
            if (!self::recordAdmissions($remaining, $locked, $worker, TicketAdmission::VIA_ORDER_PASS, $requestId)) {
                $isSuccess = false;
                $isAlreadyAdmitted = true;
                $reasonCode = 'already_admitted';
                $rejectionReason = 'Duplicate redemption prevented: guests on this order were already admitted.';
            }
        }

        if ($isSuccess) {
            $locked->checked_in_at = now();
            $locked->checked_in_by = $worker->id;
            $locked->check_in_request_id = $requestId;
            $locked->save();
            // The whole party is in: every attendee's own ticket is used up too.
            AttendeeTicket::where('order_id', $locked->id)->whereNull('checked_in_at')
                ->update(['checked_in_at' => $locked->checked_in_at, 'checked_in_by' => $worker->id, 'updated_at' => now()]);
            if ($locked !== $order) {
                $order->checked_in_at = $locked->checked_in_at;
                $order->checked_in_by = $locked->checked_in_by;
                $order->check_in_request_id = $locked->check_in_request_id;
            }

            AuditLogService::log(
                action: 'ticket_redemption_verified',
                targetEntity: 'orders',
                targetId: (string) $order->id,
                details: "Ticket {$order->ticket_code} (Order #{$order->order_number}) successfully verified and redeemed by {$worker->email}"
                    . ($manual ? ' using a typed code (manual check).' : ' by QR.'),
                actor: $worker
            );
        } else {
            AuditLogService::log(
                action: 'ticket_redemption_rejected',
                targetEntity: 'orders',
                targetId: (string) $order->id,
                details: "Ticket {$order->ticket_code} redemption rejected by {$worker->email}. Reason: {$rejectionReason}",
                actor: $worker
            );
        }

        // Prepare raw order array
        $orderData = [
            'order_number' => $order->order_number,
            'ticket_code' => $order->ticket_code,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->email,
            'customer_phone' => $order->customer_phone,
            'status' => $order->status,
            'payment_method' => $order->method,
            'total_cents' => (int) $order->total_minor,
            'checked_in_at' => $order->checked_in_at?->format('Y-m-d H:i:s'),
        ];

        // Sanitize strictly based on worker role!
        // Ticket Checker MUST NOT receive payment details, card/transfer ref, or unnecessary PII
        if ($assignment) {
            $sanitizedData = $assignment->sanitizeOrder($orderData);
        } else {
            $sanitizedData = $orderData;
        }

        return [
            'success' => $isSuccess,
            'replayed' => false,
            'already_admitted' => $isAlreadyAdmitted,
            'valid' => $isSuccess,
            'reason_code' => $isSuccess ? null : ($reasonCode ?? 'invalid_ticket'),
            'rejection_reason' => $rejectionReason,
            'ticket' => $sanitizedData,
        ] + self::verdict($isSuccess, $rejectionReason, 'Whole party on this order');
    }

    /**
     * Create an expiring invitation for a worker to join an event with a specific role.
     *
     * @param array<string> $customPermissions
     * @return array{invitation: EventWorkerAssignment, plain_token: string}
     */
    public static function createWorkerInvitation(
        User $host,
        Event $event,
        string $inviteEmail,
        EventWorkerRole|string $role,
        string $stationAssignment = 'all',
        array $customPermissions = [],
        int $expiryHours = 48
    ): array {
        $workerRole = $role instanceof EventWorkerRole ? $role : EventWorkerRole::from($role);

        $managerRole = self::staffManagerRole($host, $event);
        if ($managerRole === null) {
            throw new SecurityException('Unauthorized: You do not have permission to invite workers to this event.');
        }

        if ($managerRole === 'supervisor' && $workerRole === EventWorkerRole::EVENT_SUPERVISOR) {
            throw new SecurityException('Privilege Escalation Blocked: Event Supervisors cannot appoint additional Event Supervisors.');
        }

        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);

        $sanitizedCustomPermissions = [];
        if ($workerRole === EventWorkerRole::CUSTOM_RESTRICTED_WORKER) {
            $allowedPool = [
                'view_relevant_orders',
                'record_cash_received',
                'review_transfer_references',
                'verify_payments',
                'scan_qr_tickets',
                'validate_ticket_status',
                'record_entry',
                'view_assigned_food_items',
                'view_collection_instructions',
                'mark_quantities_collected',
                'see_remaining_quantities',
            ];
            foreach ($customPermissions as $perm) {
                if (in_array($perm, $allowedPool, true) && !$workerRole->isProhibited($perm)) {
                    $sanitizedCustomPermissions[] = $perm;
                }
            }
        }

        $assignment = EventWorkerAssignment::create([
            'organizer_id' => $event->organizer_id,
            'event_id' => $event->id,
            'user_id' => null, // Pending acceptance
            'worker_role' => $workerRole,
            'station_assignment' => $stationAssignment,
            'custom_permissions' => $sanitizedCustomPermissions,
            'status' => 'pending_invite',
            'invite_email' => strtolower(trim($inviteEmail)),
            'invitation_token_hash' => $hashedToken,
            'invitation_sent_at' => now(),
            'invitation_expires_at' => (new \DateTimeImmutable())->modify("+{$expiryHours} hours"),
            'assigned_by' => $host->id,
            'is_active' => false,
        ]);

        AuditLogService::log(
            action: 'event_worker_invitation_created',
            targetEntity: 'event_worker_assignments',
            targetId: (string) $assignment->id,
            details: "Worker invitation sent to {$inviteEmail} for role {$workerRole->label()} by {$host->email}.",
            actor: $host
        );

        return [
            'invitation' => $assignment,
            'plain_token' => $plainToken,
        ];
    }

    /**
     * Accept a pending worker invitation using the unhashed token.
     */
    public static function acceptWorkerInvitation(
        User $worker,
        string $plainToken
    ): EventWorkerAssignment {
        $hashedToken = hash('sha256', $plainToken);

        $assignment = EventWorkerAssignment::where('invitation_token_hash', $hashedToken)
            ->where('status', 'pending_invite')
            ->first();

        if (!$assignment) {
            throw new SecurityException('Invalid or expired worker invitation token.');
        }

        if ($assignment->isExpired()) {
            throw new SecurityException('This worker invitation has expired.');
        }

        $assignment->user_id = $worker->id;
        $assignment->status = 'active';
        $assignment->is_active = true;
        $assignment->invitation_accepted_at = now();
        $assignment->last_active_at = now();
        $assignment->save();

        AuditLogService::log(
            action: 'event_worker_invitation_accepted',
            targetEntity: 'event_worker_assignments',
            targetId: (string) $assignment->id,
            details: "User {$worker->email} accepted worker invitation for event ID {$assignment->event_id}.",
            actor: $worker
        );

        return $assignment;
    }

    /**
     * Revoke worker assignment immediately and create immutable audit log.
     */
    /**
     * How the actor may manage staff on this event: 'admin' (system administrator), 'host' (an active owner
     * membership of the event's own organizer) or 'supervisor' (a supervisor assignment on this event that is
     * current right now); null when they may not. Holding the host role alone is not enough: it must be this
     * event's organizer.
     */
    public static function staffManagerRole(User $actor, Event $event): ?string
    {
        if ($actor->hasRole(AccountType::SYSTEM_ADMIN)) {
            return 'admin';
        }

        if ($actor->hasRole(AccountType::EVENT_HOST) && OrganizerUser::where('organizer_id', $event->organizer_id)
            ->where('user_id', $actor->id)
            ->where('is_active', true)
            ->where('role', TenantRole::EVENT_OWNER->value)
            ->exists()) {
            return 'host';
        }

        $assignments = EventWorkerAssignment::withoutGlobalScopes()
            ->where('user_id', $actor->id)
            ->where('event_id', $event->id)
            ->where('worker_role', EventWorkerRole::EVENT_SUPERVISOR->value)
            ->get();
        foreach ($assignments as $assignment) {
            if ($assignment->isCurrentlyActive()) {
                return 'supervisor';
            }
        }

        return null;
    }

    public static function revokeWorkerAssignment(
        User $actor,
        EventWorkerAssignment $assignment,
        string $reason = 'Administrative revocation'
    ): bool {
        $event = Event::withoutGlobalScopes()->where('id', $assignment->event_id)->first();
        if (!$event || self::staffManagerRole($actor, $event) === null) {
            throw new SecurityException('Unauthorized: You cannot revoke this worker assignment.');
        }

        $assignment->revoke($actor, $reason);

        AuditLogService::log(
            action: 'event_worker_revoked',
            targetEntity: 'event_worker_assignments',
            targetId: (string) $assignment->id,
            details: "Worker assignment ID {$assignment->id} revoked by {$actor->email}. Reason: {$reason}",
            actor: $actor
        );

        return true;
    }

    /**
     * Enforce strict separation-of-duties boundaries for event workers.
     * Invariants:
     * - A payment collector/clerk who recorded a payment cannot be the sole unverified approver.
     * - Workers must not alter menu/ticket prices or platform settings.
     */
    public static function assertSeparationOfDuties(
        User $worker,
        Order $order,
        string $action
    ): void {
        // Invariant 1: Prohibit altering prices, event ownership, or system settings
        $prohibitedActions = [
            'change_ticket_prices',
            'change_menu_prices',
            'change_event_ownership',
            'access_platform_settings',
            'manage_platform_users',
        ];

        if (in_array($action, $prohibitedActions, true)) {
            throw new SecurityException("Separation of Duties Violation: Event workers are strictly prohibited from performing '{$action}'.");
        }

        // Invariant 2: The worker who collected the cash cannot unilaterally self-approve payment without audit or host review
        if ($action === 'verify_payment') {
            $isCollector = CashPayment::where('order_id', $order->id)
                ->where('received_by_user_id', $worker->id)
                ->exists();

            if ($isCollector && !$worker->hasRole(AccountType::EVENT_HOST) && !$worker->hasRole(AccountType::SYSTEM_ADMIN)) {
                throw new SecurityException("Separation of Duties Violation: Worker {$worker->email} recorded this cash payment and cannot be the sole verification authority.");
            }
        }
    }

    /**
     * Create a staff assignment from a single-use staff claim code.
     * Atomically consumes the code and records a pending assignment.
     */
    public static function createAssignmentFromClaimCode(
        User $host,
        Event $event,
        string $rawCode,
        EventWorkerRole|string $role,
        string|\DateTimeInterface $startsAt,
        string|\DateTimeInterface $expiresAt,
        string $timezone = 'America/New_York',
        string $stationAssignment = 'all',
        array $customPermissions = []
    ): EventWorkerAssignment {
        $workerRole = $role instanceof EventWorkerRole ? $role : EventWorkerRole::from($role);

        // Security check: Assigner must be this event's host, a current supervisor on it, or a system admin
        $managerRole = self::staffManagerRole($host, $event);
        if ($managerRole === null) {
            throw new SecurityException('Unauthorized: You do not have permission to assign staff for this event.');
        }

        if ($managerRole === 'supervisor' && $workerRole === EventWorkerRole::EVENT_SUPERVISOR) {
            throw new SecurityException('Privilege Escalation Blocked: Event Supervisors cannot appoint additional Event Supervisors.');
        }

        // Validate claim code
        $preview = StaffClaimCodeService::validateAndPreview($host, $event, $rawCode);
        if (!$preview['valid'] || empty($preview['claim_code'])) {
            throw new SecurityException($preview['message'] ?? 'Unable to verify this code. Ask the account holder to generate a new one.');
        }

        /** @var \App\Models\StaffClaimCode $claimCode */
        $claimCode = $preview['claim_code'];
        $targetUser = $claimCode->user;
        if (!$targetUser) {
            throw new SecurityException('Unable to locate account holder associated with this claim code.');
        }

        // Date/Time validation & UTC normalization
        try {
            $tz = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            $tz = new \DateTimeZone('America/New_York');
        }

        $startDt = $startsAt instanceof \DateTimeInterface
            ? (new \DateTimeImmutable($startsAt->format('Y-m-d H:i:s'), $tz))->setTimezone(new \DateTimeZone('UTC'))
            : (new \DateTimeImmutable((string) $startsAt, $tz))->setTimezone(new \DateTimeZone('UTC'));

        $expireDt = $expiresAt instanceof \DateTimeInterface
            ? (new \DateTimeImmutable($expiresAt->format('Y-m-d H:i:s'), $tz))->setTimezone(new \DateTimeZone('UTC'))
            : (new \DateTimeImmutable((string) $expiresAt, $tz))->setTimezone(new \DateTimeZone('UTC'));

        if ($startDt >= $expireDt) {
            throw new \InvalidArgumentException('Assignment start date/time must precede the expiration date/time.');
        }

        // Sanitize custom permissions
        $sanitizedCustomPermissions = [];
        if ($workerRole === EventWorkerRole::CUSTOM_RESTRICTED_WORKER) {
            $allowedPool = [
                'view_relevant_orders',
                'record_cash_received',
                'review_transfer_references',
                'verify_payments',
                'scan_qr_tickets',
                'validate_ticket_status',
                'record_entry',
                'view_assigned_food_items',
                'view_collection_instructions',
                'mark_quantities_collected',
                'see_remaining_quantities',
            ];
            foreach ($customPermissions as $perm) {
                if (in_array($perm, $allowedPool, true) && !$workerRole->isProhibited($perm)) {
                    $sanitizedCustomPermissions[] = $perm;
                }
            }
        }

        // Prevent duplicate active assignment for the same worker on this event
        $existing = EventWorkerAssignment::where('event_id', $event->id)
            ->where('user_id', $targetUser->id)
            ->whereIn('status', ['active', 'scheduled', 'pending_acceptance'])
            ->first();

        if ($existing && !$existing->isExpired() && !$existing->isRevoked()) {
            throw new SecurityException('This user already has a pending or active staff assignment for this event.');
        }

        // Execute atomically: redeem claim code and create assignment
        $claimCode->redeem($host, $event);

        $assignment = EventWorkerAssignment::create([
            'organizer_id' => $event->organizer_id,
            'event_id' => $event->id,
            'user_id' => $targetUser->id,
            'worker_role' => $workerRole,
            'station_assignment' => $stationAssignment,
            'custom_permissions' => $sanitizedCustomPermissions,
            'starts_at' => $startDt->format('Y-m-d H:i:s'),
            'expires_at' => $expireDt->format('Y-m-d H:i:s'),
            'timezone' => $timezone,
            'status' => 'pending_acceptance',
            'assigned_by' => $host->id,
            'is_active' => false, // Inactive until accepted by account holder
        ]);

        AuditLogService::log(
            action: 'staff_assignment_created',
            targetEntity: 'event_worker_assignments',
            targetId: (string) $assignment->id,
            details: "Host {$host->email} assigned {$targetUser->email} as {$workerRole->label()} for event ID {$event->id} (Pending Acceptance).",
            actor: $host
        );

        return $assignment;
    }

    /**
     * Accept a pending staff assignment.
     * Enforces that only the authenticated recipient can accept.
     */
    public static function acceptAssignment(User $worker, int|string $assignmentId): EventWorkerAssignment
    {
        $assignment = EventWorkerAssignment::findOrFail($assignmentId);

        if ((int) $assignment->user_id !== (int) $worker->id) {
            throw new SecurityException('Unauthorized: You can only accept staff assignments issued to your account.');
        }

        if ($assignment->status !== 'pending_acceptance') {
            throw new SecurityException("Cannot accept assignment in '{$assignment->status}' state.");
        }

        if ($assignment->isExpired()) {
            $assignment->status = 'expired';
            $assignment->is_active = false;
            $assignment->save();
            throw new SecurityException('This staff assignment offer has expired.');
        }

        $assignment->accept($worker);

        AuditLogService::log(
            action: 'staff_assignment_accepted',
            targetEntity: 'event_worker_assignments',
            targetId: (string) $assignment->id,
            details: "User {$worker->email} accepted staff assignment as {$assignment->worker_role->label()} for event ID {$assignment->event_id}.",
            actor: $worker
        );

        return $assignment;
    }

    /**
     * Decline a pending staff assignment.
     */
    public static function declineAssignment(User $worker, int|string $assignmentId, string $reason = ''): EventWorkerAssignment
    {
        $assignment = EventWorkerAssignment::findOrFail($assignmentId);

        if ((int) $assignment->user_id !== (int) $worker->id) {
            throw new SecurityException('Unauthorized: You can only decline staff assignments issued to your account.');
        }

        $assignment->decline($worker, $reason);

        AuditLogService::log(
            action: 'staff_assignment_declined',
            targetEntity: 'event_worker_assignments',
            targetId: (string) $assignment->id,
            details: "User {$worker->email} declined staff assignment ID {$assignment->id}. Reason: {$reason}",
            actor: $worker
        );

        return $assignment;
    }

    /**
     * True when the user has an active membership of the organizer in a role that runs its events
     * (platform admin, event owner or supervisor).
     */
    public static function managesOrganizer(User $user, string $organizerId): bool
    {
        if ($organizerId === '') {
            return false;
        }

        $membership = OrganizerUser::where('organizer_id', $organizerId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
        if (!$membership) {
            return false;
        }
        $role = $membership->role instanceof TenantRole ? $membership->role : TenantRole::tryFrom((string) $membership->role);

        return $role?->canManageEventWorkers() ?? false;
    }

    /**
     * Independent real-time server-side validation of active assignment and permissions.
     * Enforces start and expiration boundaries on every protected operational call.
     */
    public static function assertActiveAssignmentValid(User $worker, Event $event, string $action): EventWorkerAssignment
    {
        $assignment = self::resolveActiveAssignment($worker, $event);

        // People who run the event act without a staff assignment and are not limited by a staff role.
        if (!self::actsWithoutAssignment($worker, $event) && !$assignment->canPerform($action)) {
            throw new SecurityException("Access Denied: Your position ({$assignment->worker_role->label()}) is not authorized to perform '{$action}'.");
        }

        return $assignment;
    }

    /**
     * System Administrators, and people who run this event's organizer (owner, platform admin or supervisor
     * membership), act at the event without a staff assignment. Membership is read from organizer_users:
     * users have no organizer_id column, so the old comparison never matched and locked hosts out.
     */
    public static function actsWithoutAssignment(User $worker, Event $event): bool
    {
        return $worker->hasRole(AccountType::SYSTEM_ADMIN) || self::managesOrganizer($worker, (string) $event->organizer_id);
    }

    /**
     * The assignment someone works this event under right now, whatever the action. For a system administrator
     * or someone who runs the event's organizer it is an unsaved supervisor assignment; for staff it is their own
     * current, accepted assignment. Throws when there is none.
     */
    public static function resolveActiveAssignment(User $worker, Event $event): EventWorkerAssignment
    {
        if (self::actsWithoutAssignment($worker, $event)) {
            $fake = new EventWorkerAssignment([
                'event_id' => $event->id,
                'user_id' => $worker->id,
                'worker_role' => EventWorkerRole::EVENT_SUPERVISOR,
                'is_active' => true,
                'status' => 'active',
            ]);
            return $fake;
        }

        $assignment = EventWorkerAssignment::where('user_id', $worker->id)
            ->where('event_id', $event->id)
            ->first();

        if (!$assignment) {
            throw new SecurityException("Unauthorized: No staff assignment found for user {$worker->email} on event '{$event->title}'.");
        }

        if ($assignment->isRevoked()) {
            throw new SecurityException('Access Denied: Your staff assignment for this event has been revoked.');
        }

        if ($assignment->status === 'pending_acceptance') {
            throw new SecurityException('Access Denied: This staff assignment is pending acceptance.');
        }

        if ($assignment->status === 'declined') {
            throw new SecurityException('Access Denied: This staff assignment was declined.');
        }

        if (!$assignment->isStarted()) {
            $startsAtFormatted = $assignment->starts_at instanceof \DateTimeInterface
                ? $assignment->starts_at->format('Y-m-d H:i:s')
                : (string) $assignment->starts_at;
            throw new SecurityException("Access Denied: Your scheduled staff shift has not started yet. Starts at: {$startsAtFormatted}.");
        }

        if ($assignment->isExpired()) {
            // Update status if not yet marked
            if ($assignment->status !== 'expired') {
                $assignment->status = 'expired';
                $assignment->is_active = false;
                $assignment->save();
            }
            throw new SecurityException('Access Denied: Your staff assignment for this event has expired.');
        }

        if (!$assignment->is_active || $assignment->status !== 'active') {
            throw new SecurityException("Access Denied: Staff assignment is not currently active (status: {$assignment->status}).");
        }

        return $assignment;
    }
}
