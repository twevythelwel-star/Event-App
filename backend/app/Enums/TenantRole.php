<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantRole: string
{
    case PLATFORM_ADMIN = 'platform_admin';
    case EVENT_OWNER = 'event_owner';
    case PAYMENT_VERIFIER = 'payment_verifier';
    case FOOD_COLLECTOR = 'food_collector';
    
    // Explicit event-worker roles
    case PAYMENT_CLERK = 'payment_clerk';
    case TICKET_CHECKER = 'ticket_checker';
    case FOOD_STATION_WORKER = 'food_station_worker';
    case EVENT_SUPERVISOR = 'event_supervisor';
    case CUSTOM_RESTRICTED_WORKER = 'custom_restricted_worker';

    /**
     * Human-readable label for dashboard display.
     */
    public function label(): string
    {
        return match ($this) {
            self::PLATFORM_ADMIN => 'Platform Administrator',
            self::EVENT_OWNER => 'Event Owner / Organizer Admin',
            self::PAYMENT_VERIFIER, self::PAYMENT_CLERK => 'Payment Clerk / Verifier',
            self::FOOD_COLLECTOR, self::FOOD_STATION_WORKER => 'Food Station Worker',
            self::TICKET_CHECKER => 'Ticket Checker',
            self::EVENT_SUPERVISOR => 'Event Supervisor',
            self::CUSTOM_RESTRICTED_WORKER => 'Custom Restricted Worker',
        };
    }

    /**
     * Check if this role has permission to access financial reports and bank settings.
     */
    public function canAccessFinancials(): bool
    {
        return in_array($this, [self::PLATFORM_ADMIN, self::EVENT_OWNER]);
    }

    /**
     * Check if this role can verify payments or log cash receipts.
     */
    public function canVerifyPayments(): bool
    {
        return in_array($this, [
            self::PLATFORM_ADMIN,
            self::EVENT_OWNER,
            self::PAYMENT_VERIFIER,
            self::PAYMENT_CLERK,
            self::EVENT_SUPERVISOR,
        ]);
    }

    /**
     * Check if this role can scan tickets and mark items collected.
     */
    public function canCollectFood(): bool
    {
        return in_array($this, [
            self::PLATFORM_ADMIN,
            self::EVENT_OWNER,
            self::PAYMENT_VERIFIER,
            self::PAYMENT_CLERK,
            self::FOOD_COLLECTOR,
            self::FOOD_STATION_WORKER,
            self::EVENT_SUPERVISOR,
        ]);
    }

    /**
     * Check if this role can scan QR tickets and record entry.
     */
    public function canScanTickets(): bool
    {
        return in_array($this, [
            self::PLATFORM_ADMIN,
            self::EVENT_OWNER,
            self::TICKET_CHECKER,
            self::EVENT_SUPERVISOR,
        ]);
    }

    /**
     * Check if this role can alter event parameters, menu prices, and staff rosters.
     */
    public function canManageEventConfiguration(): bool
    {
        return in_array($this, [self::PLATFORM_ADMIN, self::EVENT_OWNER]);
    }

    /**
     * Check if this role can manage event-worker assignments.
     */
    public function canManageEventWorkers(): bool
    {
        return in_array($this, [self::PLATFORM_ADMIN, self::EVENT_OWNER, self::EVENT_SUPERVISOR]);
    }
}
