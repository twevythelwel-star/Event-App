<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\EventWorkerRole;
use App\Exceptions\SecurityException;
use App\Models\CashPayment;
use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SecurityAuditLog;
use App\Models\User;
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

        // Security check: Assigner must be host, supervisor, or system admin
        $isHost = $assigner->hasRole(AccountType::EVENT_HOST) || $assigner->hasRole(AccountType::SYSTEM_ADMIN);
        $isSupervisor = EventWorkerAssignment::where('user_id', $assigner->id)
            ->where('event_id', $event->id)
            ->where('worker_role', EventWorkerRole::EVENT_SUPERVISOR->value)
            ->where('is_active', true)
            ->exists();

        if (!$isHost && !$isSupervisor) {
            throw new SecurityException('Unauthorized: You do not have permission to manage event workers for this event.');
        }

        // Prohibit privilege escalation: Supervisors cannot create other Supervisors or give platform permissions
        if ($isSupervisor && !$isHost) {
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
        ?string $expectedEventId = null
    ): array {
        // 6. Authorized redemption check
        $scanningEventId = $expectedEventId ?? $order->event_id;
        $assignment = EventWorkerAssignment::where('user_id', $worker->id)
            ->where(fn($q) => $q->where('event_id', $scanningEventId)->orWhereNull('event_id'))
            ->where('is_active', true)
            ->first();

        $canScan = $worker->hasRole(AccountType::EVENT_HOST) ||
            $worker->hasRole(AccountType::SYSTEM_ADMIN) ||
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

        // 1. Authenticity check: Genuine ticket code & order number must exist
        $isAuthentic = !empty($order->ticket_code) && !empty($order->order_number);
        $rejectionReason = null;

        // 2. Event association check
        $isEventMatched = true;
        if ($expectedEventId !== null && (string) $order->event_id !== (string) $expectedEventId) {
            $isEventMatched = false;
            $rejectionReason = "Event association mismatch: Ticket belongs to event {$order->event_id}, not {$expectedEventId}.";
        }

        // 3. Validity period check
        $isPeriodValid = true;
        if ($order->status === 'expired' || ($order->closed_at && $order->all_handed_over)) {
            $isPeriodValid = false;
            $rejectionReason = $rejectionReason ?? "Ticket validity window has expired or event is closed.";
        }

        // 4. Ticket status check (must be paid or confirmed)
        $isStatusValid = in_array($order->status, ['paid', 'confirmed'], true);
        if (!$isStatusValid) {
            $rejectionReason = $rejectionReason ?? "Invalid ticket status: {$order->status}. Must be confirmed or paid.";
        }

        // 5. Duplicate-redemption prevention check
        $isAlreadyAdmitted = !empty($order->checked_in_at);
        if ($isAlreadyAdmitted) {
            $checkInStr = $order->checked_in_at instanceof \DateTimeInterface
                ? $order->checked_in_at->format('Y-m-d H:i:s')
                : (string) $order->checked_in_at;
            $rejectionReason = $rejectionReason ?? "Duplicate redemption prevented: Ticket was already admitted at {$checkInStr}.";
        }

        $isSuccess = $isAuthentic && $isEventMatched && $isPeriodValid && $isStatusValid && !$isAlreadyAdmitted;

        if ($isSuccess) {
            $order->checked_in_at = now();
            $order->checked_in_by = $worker->id;
            $order->save();

            AuditLogService::log(
                action: 'ticket_redemption_verified',
                targetEntity: 'orders',
                targetId: (string) $order->id,
                details: "Ticket {$order->ticket_code} (Order #{$order->order_number}) successfully verified and redeemed by {$worker->email}.",
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
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'total_cents' => $order->total_cents,
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
            'already_admitted' => $isAlreadyAdmitted,
            'valid' => $isSuccess,
            'rejection_reason' => $rejectionReason,
            'ticket' => $sanitizedData,
        ];
    }

    /**
     * Mark food quantities collected while strictly barring prices, financial reports, and unrelated customer PII.
     */
    public static function markFoodCollected(
        User $worker,
        Order $order,
        string $orderItemId,
        int $qtyToCollect
    ): array {
        $assignment = EventWorkerAssignment::where('user_id', $worker->id)
            ->where(fn($q) => $q->where('event_id', $order->event_id)->orWhereNull('event_id'))
            ->where('is_active', true)
            ->first();

        $canCollect = $worker->hasRole(AccountType::EVENT_HOST) ||
            $worker->hasRole(AccountType::SYSTEM_ADMIN) ||
            ($assignment && $assignment->canPerform('mark_quantities_collected'));

        if (!$canCollect) {
            throw new SecurityException("Unauthorized: Worker {$worker->email} is not permitted to mark food collection.");
        }

        $item = OrderItem::where('id', $orderItemId)->where('order_id', $order->id)->first();
        if (!$item) {
            throw new \InvalidArgumentException('Order item not found.');
        }

        // Station enforcement
        if ($assignment && $assignment->station_assignment !== 'all') {
            $itemStation = $item->menuItem?->station ?? 'all';
            if ($itemStation !== 'all' && $itemStation !== $assignment->station_assignment) {
                throw new SecurityException("Station Mismatch: Item belongs to '{$itemStation}', worker is assigned to '{$assignment->station_assignment}'.");
            }
        }

        $remaining = $item->quantity - ($item->collected_quantity ?? 0);
        if ($qtyToCollect > $remaining) {
            throw new \InvalidArgumentException("Cannot collect {$qtyToCollect} portions; only {$remaining} portions remaining.");
        }

        $item->collected_quantity = ($item->collected_quantity ?? 0) + $qtyToCollect;
        $item->last_collected_at = now();
        $item->last_collected_by = $worker->id;
        $item->save();

        return [
            'success' => true,
            'item_id' => $item->id,
            'item_name' => $item->name,
            'collected_now' => $qtyToCollect,
            'total_collected' => $item->collected_quantity,
            'remaining_portions' => $item->quantity - $item->collected_quantity,
        ];
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

        $isHost = $host->hasRole(AccountType::EVENT_HOST) || $host->hasRole(AccountType::SYSTEM_ADMIN);
        $isSupervisor = EventWorkerAssignment::where('user_id', $host->id)
            ->where('event_id', $event->id)
            ->where('worker_role', EventWorkerRole::EVENT_SUPERVISOR->value)
            ->where('is_active', true)
            ->exists();

        if (!$isHost && !$isSupervisor) {
            throw new SecurityException('Unauthorized: You do not have permission to invite workers to this event.');
        }

        if ($isSupervisor && !$isHost && $workerRole === EventWorkerRole::EVENT_SUPERVISOR) {
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
    public static function revokeWorkerAssignment(
        User $actor,
        EventWorkerAssignment $assignment,
        string $reason = 'Administrative revocation'
    ): bool {
        $isHost = $actor->hasRole(AccountType::EVENT_HOST) || $actor->hasRole(AccountType::SYSTEM_ADMIN);
        $isSupervisor = EventWorkerAssignment::where('user_id', $actor->id)
            ->where('event_id', $assignment->event_id)
            ->where('worker_role', EventWorkerRole::EVENT_SUPERVISOR->value)
            ->where('is_active', true)
            ->exists();

        if (!$isHost && !$isSupervisor) {
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
                ->where('collected_by_user_id', $worker->id)
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
        string $timezone = 'America/Jamaica',
        string $stationAssignment = 'all',
        array $customPermissions = []
    ): EventWorkerAssignment {
        $workerRole = $role instanceof EventWorkerRole ? $role : EventWorkerRole::from($role);

        // Security check: Assigner must be host, supervisor, or system admin
        $isHost = $host->hasRole(AccountType::EVENT_HOST) || $host->hasRole(AccountType::SYSTEM_ADMIN);
        $isSupervisor = EventWorkerAssignment::where('user_id', $host->id)
            ->where('event_id', $event->id)
            ->where('worker_role', EventWorkerRole::EVENT_SUPERVISOR->value)
            ->where('is_active', true)
            ->exists();

        if (!$isHost && !$isSupervisor) {
            throw new SecurityException('Unauthorized: You do not have permission to assign staff for this event.');
        }

        if ($isSupervisor && !$isHost && $workerRole === EventWorkerRole::EVENT_SUPERVISOR) {
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
            $tz = new \DateTimeZone('America/Jamaica');
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
     * Independent real-time server-side validation of active assignment and permissions.
     * Enforces start and expiration boundaries on every protected operational call.
     */
    public static function assertActiveAssignmentValid(User $worker, Event $event, string $action): EventWorkerAssignment
    {
        // System Admin and Event Host bypass event-worker boundaries for event ownership
        if ($worker->hasRole(AccountType::SYSTEM_ADMIN) || ($worker->hasRole(AccountType::EVENT_HOST) && $event->organizer_id === $worker->organizer_id)) {
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

        if (!$assignment->canPerform($action)) {
            throw new SecurityException("Access Denied: Your position ({$assignment->worker_role->label()}) is not authorized to perform '{$action}'.");
        }

        return $assignment;
    }
}
