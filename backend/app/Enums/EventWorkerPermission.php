<?php

declare(strict_types=1);

namespace App\Enums;

enum EventWorkerPermission: string
{
    // Payment Clerk permissions
    case VIEW_RELEVANT_ORDERS = 'view_relevant_orders';
    case RECORD_CASH_RECEIVED = 'record_cash_received';
    case REVIEW_TRANSFER_REFERENCES = 'review_transfer_references';
    case VERIFY_MANUAL_PAYMENTS = 'verify_manual_payments';
    case VIEW_PAYMENT_SUMMARY = 'view_order_payment_summary';

    // Ticket Checker permissions
    case SCAN_QR_TICKETS = 'scan_qr_tickets';
    case VALIDATE_TICKET_STATUS = 'validate_ticket_status';
    case RECORD_EVENT_ENTRY = 'record_event_entry';

    // Food Station Worker permissions
    case VIEW_ASSIGNED_FOOD_ITEMS = 'view_assigned_food_items';
    case VIEW_COLLECTION_INSTRUCTIONS = 'view_collection_instructions';
    case MARK_QUANTITIES_COLLECTED = 'mark_quantities_collected';
    case SEE_REMAINING_QUANTITIES = 'see_remaining_quantities';

    // Event Supervisor permissions
    case OVERSEE_ASSIGNED_OPERATIONS = 'oversee_assigned_operations';
    case VIEW_RELEVANT_REPORTS = 'view_relevant_reports';
    case MANAGE_WORKER_ASSIGNMENTS = 'manage_worker_assignments';

    /**
     * Human-readable label for dashboard display.
     */
    public function label(): string
    {
        return match ($this) {
            self::VIEW_RELEVANT_ORDERS => 'View Relevant Orders',
            self::RECORD_CASH_RECEIVED => 'Record Cash Received',
            self::REVIEW_TRANSFER_REFERENCES => 'Review Transfer References',
            self::VERIFY_MANUAL_PAYMENTS => 'Verify Manual Payments',
            self::VIEW_PAYMENT_SUMMARY => 'View Limited Payment Summary',
            self::SCAN_QR_TICKETS => 'Scan QR Tickets',
            self::VALIDATE_TICKET_STATUS => 'Validate Ticket Status',
            self::RECORD_EVENT_ENTRY => 'Record Event Entry',
            self::VIEW_ASSIGNED_FOOD_ITEMS => 'View Assigned Food Items',
            self::VIEW_COLLECTION_INSTRUCTIONS => 'View Collection Instructions',
            self::MARK_QUANTITIES_COLLECTED => 'Mark Quantities Collected',
            self::SEE_REMAINING_QUANTITIES => 'See Remaining Quantities',
            self::OVERSEE_ASSIGNED_OPERATIONS => 'Oversee Assigned Operations',
            self::VIEW_RELEVANT_REPORTS => 'View Permitted Reports',
            self::MANAGE_WORKER_ASSIGNMENTS => 'Manage Worker Assignments',
        };
    }

    /**
     * Category grouping for UI selection.
     */
    public function category(): string
    {
        return match ($this) {
            self::VIEW_RELEVANT_ORDERS,
            self::RECORD_CASH_RECEIVED,
            self::REVIEW_TRANSFER_REFERENCES,
            self::VERIFY_MANUAL_PAYMENTS,
            self::VIEW_PAYMENT_SUMMARY => 'Payments',

            self::SCAN_QR_TICKETS,
            self::VALIDATE_TICKET_STATUS,
            self::RECORD_EVENT_ENTRY => 'Ticketing & Gate',

            self::VIEW_ASSIGNED_FOOD_ITEMS,
            self::VIEW_COLLECTION_INSTRUCTIONS,
            self::MARK_QUANTITIES_COLLECTED,
            self::SEE_REMAINING_QUANTITIES => 'Food & Collection',

            self::OVERSEE_ASSIGNED_OPERATIONS,
            self::VIEW_RELEVANT_REPORTS,
            self::MANAGE_WORKER_ASSIGNMENTS => 'Supervision',
        };
    }

    /**
     * Returns all valid permission string values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
