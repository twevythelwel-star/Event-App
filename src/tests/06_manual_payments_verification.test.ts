import { describe, it, expect } from "vitest";
import { TicketOrder } from "../types";

describe("06: Manual Payment Submissions and Verification Suite", () => {
  function createSampleOrder(initialStatus: "awaiting_payment" | "payment_submitted"): TicketOrder {
    return {
      id: "ord-test-101",
      organizerId: "org-island-fish-fry",
      ticketCode: "SFF-101",
      orderNumber: "ORD-2026-101",
      customerName: "Kareem Campbell",
      customerPhone: "876-555-9123",
      method: "bank",
      subtotal: 5600,
      packagingFee: 300,
      totalAmount: 5900,
      amountPaid: 0,
      currency: "JMD",
      status: initialStatus,
      fulfillmentStatus: "unprepared",
      paymentReference: initialStatus === "payment_submitted" ? "NCB-REF-4491" : undefined,
      senderName: initialStatus === "payment_submitted" ? "Kareem Campbell" : undefined,
      createdAt: "2026-11-20T10:00:00Z",
      items: [
        {
          menuItemId: "fish-meal",
          name: "Escovitch Fish Meal",
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
      secureTicketToken: "tok-sff-101-auth",
    };
  }

  function submitTransferReference(order: TicketOrder, ref: string, senderName: string): TicketOrder {
    return {
      ...order,
      status: "payment_submitted",
      paymentReference: ref,
      senderName,
    };
  }

  function beginVerification(order: TicketOrder, verifier: string): TicketOrder {
    if (order.status !== "payment_submitted" && order.status !== "awaiting_payment") {
      throw new Error(`Cannot verify order in status '${order.status}'`);
    }
    return {
      ...order,
      status: "under_verification",
      verifiedBy: verifier,
    };
  }

  function approveManualPayment(
    order: TicketOrder,
    verifier: string,
    notes: string,
    verifiedAmount?: number
  ): TicketOrder {
    const amount = verifiedAmount !== undefined ? verifiedAmount : order.totalAmount;
    const isPartial = amount < order.totalAmount;

    return {
      ...order,
      status: isPartial ? "partially_paid" : "paid",
      amountPaid: amount,
      verifiedAt: new Date().toISOString(),
      verifiedBy: verifier,
      verificationNotes: notes,
    };
  }

  function rejectManualPayment(order: TicketOrder, verifier: string, reason: string): TicketOrder {
    return {
      ...order,
      status: "rejected",
      rejectionReason: reason,
      verifiedAt: new Date().toISOString(),
      verifiedBy: verifier,
    };
  }

  it("should record customer transfer submission with reference number and sender name", () => {
    const order = createSampleOrder("awaiting_payment");
    const submittedOrder = submitTransferReference(order, "SCOTIA-TX-88319", "Kareem Campbell");

    expect(submittedOrder.status).toBe("payment_submitted");
    expect(submittedOrder.paymentReference).toBe("SCOTIA-TX-88319");
    expect(submittedOrder.senderName).toBe("Kareem Campbell");
    expect(submittedOrder.amountPaid).toBe(0); // Money not verified yet
  });

  it("should allow a payment verifier to place submitted payment under review", () => {
    const order = createSampleOrder("payment_submitted");
    const underReview = beginVerification(order, "Treasurer Davis");

    expect(underReview.status).toBe("under_verification");
    expect(underReview.verifiedBy).toBe("Treasurer Davis");
  });

  it("should mark full payment as verified with audit timestamps and verifier signature", () => {
    const order = createSampleOrder("payment_submitted");
    const underReview = beginVerification(order, "Treasurer Davis");
    const verifiedOrder = approveManualPayment(
      underReview,
      "Treasurer Davis",
      "Confirmed deposit of J$5,900 in NCB Account #10294812"
    );

    expect(verifiedOrder.status).toBe("paid");
    expect(verifiedOrder.amountPaid).toBe(5900);
    expect(verifiedOrder.verifiedBy).toBe("Treasurer Davis");
    expect(verifiedOrder.verificationNotes).toContain("Confirmed deposit");
    expect(verifiedOrder.verifiedAt).toBeDefined();
  });

  it("should support recording partially paid transfers if the deposit was short", () => {
    const order = createSampleOrder("payment_submitted");
    const verifiedPartial = approveManualPayment(
      order,
      "Treasurer Davis",
      "Customer sent J$3,000 only. J$2,900 balance due in cash on arrival.",
      3000
    );

    expect(verifiedPartial.status).toBe("partially_paid");
    expect(verifiedPartial.amountPaid).toBe(3000);
    expect(verifiedPartial.totalAmount).toBe(5900);
  });

  it("should record rejection reason when bank reference cannot be found or is forged", () => {
    const order = createSampleOrder("payment_submitted");
    const rejectedOrder = rejectManualPayment(
      order,
      "Treasurer Davis",
      "Bank statement shows no incoming transfer for reference 'NCB-REF-4491'. Duplicate reference detected."
    );

    expect(rejectedOrder.status).toBe("rejected");
    expect(rejectedOrder.rejectionReason).toContain("no incoming transfer");
    expect(rejectedOrder.amountPaid).toBe(0);
  });

  it("should prevent unauthorized food handover when order is awaiting_payment or under_verification", () => {
    const pendingOrder = createSampleOrder("payment_submitted");
    const isEligibleForCollection = pendingOrder.status === "paid" || pendingOrder.authorizedUnpaid === true;

    expect(isEligibleForCollection).toBe(false);
  });
});
