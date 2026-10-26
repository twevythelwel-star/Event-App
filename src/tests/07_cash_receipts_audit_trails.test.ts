import { describe, it, expect } from "vitest";
import { CashPaymentRecord, TicketOrder } from "../types";
import { createCashReversalRecord } from "./testUtils";

describe("07: Cash Receipts and Audit Trails Suite", () => {
  const sampleCashReceipt: CashPaymentRecord = {
    id: "cpr-1001",
    organizerId: "org-island-fish-fry",
    transactionType: "receipt",
    receiptNumber: "CR-2026-0045",
    customerName: "Vivienne Rhoden",
    customerPhone: "876-555-8822",
    eventName: "Old Harbour Bay Seafood Festival",
    orderId: "ord-test-45",
    orderNumber: "ORD-2026-0045",
    ticketCode: "SFF-0045",
    amount: 5900,
    currency: "JMD",
    receivedAt: "2026-11-28T14:30:00Z",
    receivedBy: "Pastor Brown",
    notes: "Full payment received in cash at Entrance Gate 1",
    createdAt: "2026-11-28T14:30:00Z",
  };

  it("should record cash payment receipt with complete collector audit trail", () => {
    expect(sampleCashReceipt.receiptNumber).toBe("CR-2026-0045");
    expect(sampleCashReceipt.amount).toBe(5900);
    expect(sampleCashReceipt.currency).toBe("JMD");
    expect(sampleCashReceipt.receivedBy).toBe("Pastor Brown");
    expect(sampleCashReceipt.transactionType).toBe("receipt");
    expect(sampleCashReceipt.orderNumber).toBe("ORD-2026-0045");
  });

  it("should link cash receipt to order, marking it as paid and recording amountPaid", () => {
    const pendingOrder: TicketOrder = {
      id: "ord-test-45",
      organizerId: "org-island-fish-fry",
      ticketCode: "SFF-0045",
      orderNumber: "ORD-2026-0045",
      customerName: "Vivienne Rhoden",
      customerPhone: "876-555-8822",
      method: "cash",
      subtotal: 5600,
      packagingFee: 300,
      totalAmount: 5900,
      amountPaid: 0,
      currency: "JMD",
      status: "awaiting_payment",
      createdAt: "2026-11-28T14:15:00Z",
      items: [],
      allHandedOver: false,
      secureTicketToken: "tok-sff-45",
    };

    // Simulate cashier marking cash receipt
    const updatedOrder: TicketOrder = {
      ...pendingOrder,
      status: "paid",
      amountPaid: sampleCashReceipt.amount,
      verifiedAt: sampleCashReceipt.receivedAt,
      verifiedBy: sampleCashReceipt.receivedBy,
      verificationNotes: `Cash receipt issued: ${sampleCashReceipt.receiptNumber} by ${sampleCashReceipt.receivedBy}`,
    };

    expect(updatedOrder.status).toBe("paid");
    expect(updatedOrder.amountPaid).toBe(5900);
    expect(updatedOrder.verifiedBy).toBe("Pastor Brown");
    expect(updatedOrder.verificationNotes).toContain("CR-2026-0045");
  });

  it("should create an immutable reversing entry without mutating or deleting the original record", () => {
    const { reversalRecord, error } = createCashReversalRecord(
      sampleCashReceipt,
      "Customer mistakenly handed double cash / requested refund before prep",
      "Elder Thompson"
    );

    expect(error).toBeUndefined();
    expect(reversalRecord.transactionType).toBe("reversal");
    expect(reversalRecord.receiptNumber).toBe("REV-CR-2026-0045");
    expect(reversalRecord.amount).toBe(-5900); // Negative adjustment
    expect(reversalRecord.currency).toBe("JMD");
    expect(reversalRecord.originalRecordId).toBe("cpr-1001");
    expect(reversalRecord.reversalOfId).toBe("cpr-1001");
    expect(reversalRecord.reversalAuthorizedBy).toBe("Elder Thompson");
    expect(reversalRecord.notes).toContain("Reversal of receipt CR-2026-0045");

    // Check that original record remains unmodified
    expect(sampleCashReceipt.amount).toBe(5900);
    expect(sampleCashReceipt.transactionType).toBe("receipt");
  });

  it("should prevent double-reversal of an already reversed cash transaction", () => {
    const { reversalRecord } = createCashReversalRecord(
      sampleCashReceipt,
      "First reversal",
      "Elder Thompson"
    );

    const secondReversal = createCashReversalRecord(
      reversalRecord,
      "Accidental second reversal",
      "Elder Thompson"
    );

    expect(secondReversal.error).toContain("Cannot reverse an existing reversal transaction");
  });
});
