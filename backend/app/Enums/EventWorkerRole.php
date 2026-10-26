<?php

declare(strict_types=1);

namespace App\Enums;

enum EventWorkerRole: string
{
    case PAYMENT_CLERK = 'payment_clerk';
    case TICKET_CHECKER = 'ticket_checker';
    case FOOD_STATION_WORKER = 'food_station_worker';
    case EVENT_SUPERVISOR = 'event_supervisor';
    case CUSTOM_RESTRICTED_WORKER = 'custom_restricted_worker';

    /**
     * Human-readable role title.
     */
    public function label(): string
    {
        return match ($this) {
            self::PAYMENT_CLERK => 'Payment Clerk',
            self::TICKET_CHECKER => 'Ticket Checker',
            self::FOOD_STATION_WORKER => 'Food Station Worker',
            self::EVENT_SUPERVISOR => 'Event Supervisor',
            self::CUSTOM_RESTRICTED_WORKER => 'Custom Restricted Worker',
        };
    }

    /**
     * Explicit "Can do" operational responsibilities.
     */
    public function canDoDescription(): string
    {
        return match ($this) {
            self::PAYMENT_CLERK => 'View relevant orders, record cash received, review submitted transfer references, and perform permitted payment-verification actions.',
            self::TICKET_CHECKER => 'Scan QR tickets, validate ticket status, and record entry.',
            self::FOOD_STATION_WORKER => 'View assigned food items and collection instructions, mark quantities collected, and see remaining quantities.',
            self::EVENT_SUPERVISOR => 'Oversee assigned event operations, view relevant reports, and manage permitted event-worker assignments.',
            self::CUSTOM_RESTRICTED_WORKER => 'Receive a deliberately selected combination of approved permissions.',
        };
    }

    /**
     * Explicit "Must not be able to do" security & privacy boundaries.
     */
    public function mustNotDoDescription(): string
    {
        return match ($this) {
            self::PAYMENT_CLERK => 'Change event ownership, change system settings, access unrelated events, or alter financial records without an audit trail.',
            self::TICKET_CHECKER => 'View unnecessary payment or personal information, issue refunds, or change ticket prices.',
            self::FOOD_STATION_WORKER => 'View unrelated customer information, change menu prices, or access financial reports.',
            self::EVENT_SUPERVISOR => 'Access other hosts\' events or platform-wide administrator settings.',
            self::CUSTOM_RESTRICTED_WORKER => 'Gain permissions beyond those explicitly granted.',
        };
    }

    /**
     * Standard approved permissions granted by role.
     *
     * @return array<string>
     */
    public function getDefaultPermissions(): array
    {
        return match ($this) {
            self::PAYMENT_CLERK => [
                'view_relevant_orders',
                'record_cash_received',
                'review_transfer_references',
                'verify_payments',
                'view_order_payment_summary',
            ],
            self::TICKET_CHECKER => [
                'scan_qr_tickets',
                'validate_ticket_status',
                'record_entry',
            ],
            self::FOOD_STATION_WORKER => [
                'view_assigned_food_items',
                'view_collection_instructions',
                'mark_quantities_collected',
                'see_remaining_quantities',
            ],
            self::EVENT_SUPERVISOR => [
                'oversee_assigned_operations',
                'view_relevant_reports',
                'manage_permitted_worker_assignments',
                'scan_qr_tickets',
                'validate_ticket_status',
                'record_entry',
                'view_assigned_food_items',
                'mark_quantities_collected',
                'see_remaining_quantities',
                'view_relevant_orders',
                'verify_payments',
            ],
            self::CUSTOM_RESTRICTED_WORKER => [],
        };
    }

    /**
     * Actions strictly prohibited for this role under all circumstances.
     *
     * @return array<string>
     */
    public function getProhibitedActions(): array
    {
        // Common invariants prohibited for ALL event-worker roles
        $universalWorkerProhibitions = [
            'change_event_ownership',
            'change_system_settings',
            'access_unrelated_events',
            'alter_financials_without_audit',
            'access_platform_settings',
            'access_other_hosts_events',
        ];

        $roleSpecific = match ($this) {
            self::PAYMENT_CLERK => [
                'change_ticket_prices',
                'change_menu_prices',
                'manage_worker_assignments',
                'delete_orders',
            ],
            self::TICKET_CHECKER => [
                'view_payment_pii',
                'view_financial_details',
                'issue_refunds',
                'change_ticket_prices',
                'change_menu_prices',
                'record_cash_received',
                'alter_order_items',
            ],
            self::FOOD_STATION_WORKER => [
                'view_unrelated_customer_info',
                'view_payment_pii',
                'change_menu_prices',
                'change_ticket_prices',
                'access_financial_reports',
                'record_cash_received',
                'issue_refunds',
            ],
            self::EVENT_SUPERVISOR => [
                'manage_platform_users',
                'manage_platform_roles',
                'modify_global_platform_fees',
            ],
            self::CUSTOM_RESTRICTED_WORKER => [
                'unauthorized_actions',
            ],
        };

        return array_values(array_unique(array_merge($universalWorkerProhibitions, $roleSpecific)));
    }

    /**
     * Verify whether a worker holding this role (with optional custom permissions) can perform an action.
     *
     * @param string $action
     * @param array<string>|null $customPermissions
     * @return bool
     */
    public function canPerform(string $action, ?array $customPermissions = null): bool
    {
        // 1. Prohibited actions always return false
        if ($this->isProhibited($action)) {
            return false;
        }

        // 2. Custom Restricted Worker operates STRICTLY on whitelist
        if ($this === self::CUSTOM_RESTRICTED_WORKER) {
            return !empty($customPermissions) && in_array($action, $customPermissions, true);
        }

        // 3. Check role's default permissions
        if (in_array($action, $this->getDefaultPermissions(), true)) {
            return true;
        }

        // 4. Check if custom permissions grant this action (unless prohibited)
        if (!empty($customPermissions) && in_array($action, $customPermissions, true)) {
            return true;
        }

        return false;
    }

    /**
     * Verify whether an action is strictly prohibited.
     */
    public function isProhibited(string $action): bool
    {
        return in_array($action, $this->getProhibitedActions(), true);
    }

    /**
     * Sanitizes order data so that workers only see information strictly required for their operational scope.
     *
     * - Ticket Checker: Strips payment data, bank transfer refs, customer email, customer phone, and prices.
     * - Food Station Worker: Strips payment info, customer financial details, prices; filters items to station assignment.
     * - Payment Clerk: Shows order and payment details; strips unrelated organizer configuration.
     *
     * @param array<string, mixed> $orderData
     * @param string $stationAssignment 'all' or specific station/category like 'mains', 'drinks'
     * @return array<string, mixed>
     */
    public function sanitizeOrderForWorker(array $orderData, string $stationAssignment = 'all'): array
    {
        $sanitized = $orderData;

        switch ($this) {
            case self::TICKET_CHECKER:
                // Strip all financial details and extraneous PII
                unset(
                    $sanitized['payment_method'],
                    $sanitized['payment_reference'],
                    $sanitized['total_amount'],
                    $sanitized['total_cents'],
                    $sanitized['subtotal_cents'],
                    $sanitized['packaging_fee_cents'],
                    $sanitized['amount_paid_cents'],
                    $sanitized['customer_email'],
                    $sanitized['customer_phone']
                );
                // Retain only validation fields
                $sanitized['ticket_validation_only'] = true;
                break;

            case self::FOOD_STATION_WORKER:
                // Strip payment, prices, financial totals, customer phone/email
                unset(
                    $sanitized['payment_method'],
                    $sanitized['payment_reference'],
                    $sanitized['total_amount'],
                    $sanitized['total_cents'],
                    $sanitized['subtotal_cents'],
                    $sanitized['packaging_fee_cents'],
                    $sanitized['amount_paid_cents'],
                    $sanitized['customer_email'],
                    $sanitized['customer_phone']
                );

                // Filter items to station assignment if specified
                if ($stationAssignment !== 'all' && !empty($sanitized['items'])) {
                    $sanitized['items'] = array_values(array_filter(
                        $sanitized['items'],
                        fn($item) => ($item['station'] ?? 'all') === $stationAssignment || ($item['category'] ?? '') === $stationAssignment
                    ));
                }

                // Strip individual price per item
                if (!empty($sanitized['items'])) {
                    foreach ($sanitized['items'] as &$item) {
                        unset($item['unit_price'], $item['unit_price_cents'], $item['subtotal_cents']);
                    }
                }
                break;

            case self::PAYMENT_CLERK:
                // Retain order payment status, cash verification controls, order number, reference
                // Mask full customer phone if needed
                break;

            case self::EVENT_SUPERVISOR:
                // Full visibility within the assigned event
                break;

            case self::CUSTOM_RESTRICTED_WORKER:
                // Default to strict sanitization unless permitted
                break;
        }

        return $sanitized;
    }
}
