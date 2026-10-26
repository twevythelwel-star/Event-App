import { describe, it, expect } from "vitest";
import { TicketOrder } from "../types";

describe("16: Refunds, Cancellations, and Exceptional Cases Suite", () => {
  function createPaidOrder(): TicketOrder {
    return {
      id: "ord-ref-100",
      organizerId: "org-island-fish-fry",
      ticketCode: "SFF-900",
      orderNumber: "ORD-2026-0900",
      customerName: "Patrick Stewart",
      customerPhone: "876-555-9988",
      method: "bank",
      subtotal: 5600,
      packagingFee: 300,
      totalAmount: 5900,
      amountPaid: 5900,
      currency: "JMD",
      status: "paid",
      createdAt: "2026-11-20T10:00:00Z",
      items: [
        {
          menuItemId: "snapper-1",
          name: "Escovitch Snapper Meal",
          pricingType: "fixed",
          basePrice: 2800,
          selectedOptionsSummary: [],
          optionsTotalExtra: 0,
          finalItemPrice: 2800,
          currency: "JMD",
          quantity: 2,
          collectedQuantity: 0,
          handoverStatus: "pending",
        },
      ],
      allHandedOver: false,
      secureTicketToken: "tok-valid-token-900",
    };
  }

  function processRefund(
    order: TicketOrder,
    refundAmount: number,
    reason: string,
    authorizedBy: string
  ): { updatedOrder: TicketOrder; error?: string } {
    if (order.status === "refunded") {
      return { updatedOrder: order, error: "Order is already refunded" };
    }
    if (order.status !== "paid" && order.status !== "partially_paid") {
      return { updatedOrder: order, error: `Cannot refund order with status '${order.status}'` };
    }
    if (refundAmount <= 0 || refundAmount > (order.amountPaid || order.totalAmount)) {
      return { updatedOrder: order, error: `Invalid refund amount: J$${refundAmount}` };
    }

    const updatedOrder: TicketOrder = {
      ...order,
      status: "refunded",
      refundDetails: {
        amount: refundAmount,
        reason,
        refundedAt: new Date().toISOString(),
        refundedBy: authorizedBy,
      },
      // Invalidate ticket token so QR code cannot be redeemed
      secureTicketToken: `tok-void-refunded-${Date.now()}`,
    };

    return { updatedOrder };
  }

  function sanitizeUserInput(input: string): string {
    return input.replace(/<[^>]*>?/gm, "").trim();
  }

  it("should successfully process a full refund and invalidate the ticket token", () => {
    const order = createPaidOrder();
    const { updatedOrder, error } = processRefund(
      order,
      5900,
      "Customer called to cancel due to travel conflict prior to event",
      "Treasurer Davis"
    );

    expect(error).toBeUndefined();
    expect(updatedOrder.status).toBe("refunded");
    expect(updatedOrder.refundDetails).toBeDefined();
    expect(updatedOrder.refundDetails?.amount).toBe(5900);
    expect(updatedOrder.refundDetails?.refundedBy).toBe("Treasurer Davis");
    expect(updatedOrder.secureTicketToken).toContain("tok-void-refunded");
  });

  it("should block collection on a refunded order", () => {
    const order = createPaidOrder();
    const { updatedOrder } = processRefund(order, 5900, "Event weather reschedule", "Organizer");

    // Scanner check
    function verifyCollectionEligibility(o: TicketOrder): { canCollect: boolean; reason?: string } {
      if (o.status === "refunded") {
        return { canCollect: false, reason: "Order has been refunded. Food handover is strictly prohibited." };
      }
      if (o.status !== "paid" && !o.authorizedUnpaid) {
        return { canCollect: false, reason: "Order is not paid" };
      }
      return { canCollect: true };
    }

    const check = verifyCollectionEligibility(updatedOrder);
    expect(check.canCollect).toBe(false);
    expect(check.reason).toContain("Order has been refunded");
  });

  it("should prevent double-refunding an already refunded order", () => {
    const order = createPaidOrder();
    const step1 = processRefund(order, 5900, "First refund", "Treasurer");
    const step2 = processRefund(step1.updatedOrder, 5900, "Second refund attempt", "Treasurer");

    expect(step2.error).toBe("Order is already refunded");
  });

  it("should reject refund amounts exceeding the amount actually paid", () => {
    const order = createPaidOrder();
    const attempt = processRefund(order, 10000, "Excess refund", "Treasurer"); // Exceeds 5900
    expect(attempt.error).toContain("Invalid refund amount");
  });

  it("should sanitize malicious script tags in customer instructions and notes (XSS Defense)", () => {
    const maliciousNote = "<script>alert('xss');</script>Extra spicy sauce on the side please!";
    const sanitized = sanitizeUserInput(maliciousNote);

    expect(sanitized).toBe("alert('xss');Extra spicy sauce on the side please!");
    expect(sanitized).not.toContain("<script>");
    expect(sanitized).not.toContain("</script>");
  });

  it("should handle order cancellation for unpaid pending orders gracefully", () => {
    const unpaidOrder: TicketOrder = {
      ...createPaidOrder(),
      status: "awaiting_payment",
      amountPaid: 0,
    };

    function cancelUnpaidOrder(o: TicketOrder, reason: string): TicketOrder {
      return {
        ...o,
        status: "rejected",
        rejectionReason: `Cancelled by customer/organizer: ${reason}`,
      };
    }

    const cancelled = cancelUnpaidOrder(unpaidOrder, "Customer decided not to attend");
    expect(cancelled.status).toBe("rejected");
    expect(cancelled.rejectionReason).toContain("Cancelled by customer/organizer");
  });
});
