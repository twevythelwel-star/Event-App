<?php

namespace App\Http\Resources;

use App\Services\CashLedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an order in the shape of the SPA's TicketOrder type.
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $refunds = CashLedgerService::refundEntries($this->resource);
        $lastRefund = $refunds ? $refunds[array_key_last($refunds)] : null;

        return [
            'id' => $this->id,
            'organizerId' => $this->organizer_id,
            'eventId' => $this->event_id,
            'eventTitle' => $this->whenLoaded('event', fn () => $this->event?->title),
            'ticketCode' => $this->ticket_code,
            'orderNumber' => $this->order_number,
            'customerName' => $this->customer_name,
            'customerPhone' => $this->customer_phone,
            'email' => $this->email,
            'method' => $this->method,
            // Major-unit numbers for existing clients, derived exactly from the integer minor-unit columns below.
            'subtotal' => $this->money('subtotal_minor')->toMajorNumber(),
            'packagingFee' => $this->money('fees_minor')->toMajorNumber(),
            'totalAmount' => $this->money('total_minor')->toMajorNumber(),
            'amountPaid' => $this->money('amount_paid_minor')->toMajorNumber(),
            'currency' => $this->currency,
            // Authoritative amounts: integer minor units (cents) in `currency`.
            'amounts' => [
                'subtotalMinor' => (int) $this->subtotal_minor,
                'discountMinor' => (int) $this->discount_minor,
                'feesMinor' => (int) $this->fees_minor,
                'taxMinor' => (int) $this->tax_minor,
                'totalMinor' => (int) $this->total_minor,
                'amountPaidMinor' => (int) $this->amount_paid_minor,
                'refundedMinor' => (int) $this->refunded_minor,
                'balanceMinor' => $this->balanceMinor(),
            ],
            'charges' => $this->whenLoaded('charges', fn () => $this->charges->map(fn ($c) => [
                'kind' => $c->kind,
                'label' => $c->label,
                'amountMinor' => (int) $c->amount_minor,
                'formatted' => $c->amount()->display(),
            ])->values()),
            'status' => $this->status,
            'fulfillmentStatus' => $this->fulfillment_status,
            'paymentReference' => $this->payment_reference,
            'senderName' => $this->sender_name,
            'verifiedAt' => $this->verified_at?->toIso8601String(),
            'verifiedBy' => $this->verified_by,
            'rejectionReason' => $this->rejection_reason,
            // Refund history plus a running summary in the SPA's single refundDetails shape
            'refunds' => $refunds,
            'refundDetails' => $lastRefund ? [
                'amount' => array_sum(array_column($refunds, 'amount')),
                'reason' => $lastRefund['reason'],
                'refundedAt' => $lastRefund['refundedAt'],
                'refundedBy' => $lastRefund['refundedBy'],
            ] : null,
            'authorizedUnpaid' => $this->authorized_unpaid,
            'createdAt' => $this->created_at?->toIso8601String(),
            'allHandedOver' => $this->all_handed_over,
            'closedAt' => $this->closed_at?->toIso8601String(),
            'notes' => $this->notes,
            'secureTicketToken' => $this->secure_ticket_token,
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'menuItemId' => $item->menu_item_id,
                'name' => $item->name,
                'category' => $item->category,
                'pricingType' => $item->pricing_type,
                'basePrice' => (float) $item->base_price,
                'selectedOptionsSummary' => $item->selected_options_summary ?? [],
                'optionsTotalExtra' => (float) $item->options_total_extra,
                'customDonationAmount' => $item->custom_donation_amount !== null ? (float) $item->custom_donation_amount : null,
                'finalItemPrice' => (float) $item->final_item_price,
                'currency' => $item->currency,
                'quantity' => (int) $item->quantity,
                'collectedQuantity' => (int) $item->collected_quantity,
                'specialInstructions' => $item->special_instructions,
                'handoverStatus' => $item->handover_status,
                'handedOverAt' => $item->handed_over_at ? (string) $item->handed_over_at : null,
                'handedOverBy' => $item->handed_over_by,
                'stationHandedOver' => $item->station_handed_over,
            ])->values(),
        ];
    }
}
