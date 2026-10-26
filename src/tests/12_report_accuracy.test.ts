import { describe, it, expect } from "vitest";
import { CashPaymentRecord, TicketOrder } from "../types";
import { calculateFinancialAndKitchenReport } from "./testUtils";

describe("12: Report Accuracy Suite", () => {
  const testOrders: TicketOrder[] = [
    {
      id: "ord-1",
      organizerId: "org-island-fish-fry",
      ticketCode: "SFF-001",
      orderNumber: "ORD-2026-0001",
      customerName: "Audrey Bailey",
      customerPhone: "876-555-1111",
      method: "bank",
      subtotal: 5600,
      packagingFee: 300,
      totalAmount: 5900,
      amountPaid: 5900,
      currency: "JMD",
      status: "paid",
      createdAt: "2026-11-28T10:00:00Z",
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
          collectedQuantity: 2, // Fully collected
          handoverStatus: "collected",
        },
      ],
      allHandedOver: true,
      secureTicketToken: "tok-1",
    },
    {
      id: "ord-2",
      organizerId: "org-island-fish-fry",
      ticketCode: "SFF-002",
      orderNumber: "ORD-2026-0002",
      customerName: "Clinton Brown",
      customerPhone: "876-555-2222",
      method: "cash",
      subtotal: 2800,
      packagingFee: 300,
      totalAmount: 3100,
      amountPaid: 3100,
      currency: "JMD",
      status: "paid",
      createdAt: "2026-11-28T10:30:00Z",
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
          quantity: 1,
          collectedQuantity: 0, // Uncollected
          handoverStatus: "pending",
        },
        {
          menuItemId: "festival-1",
          name: "Golden Fried Festival (4 pcs)",
          pricingType: "fixed",
          basePrice: 800,
          selectedOptionsSummary: [],
          optionsTotalExtra: 0,
          finalItemPrice: 800,
          currency: "JMD",
          quantity: 2,
          collectedQuantity: 1, // 1 collected, 1 remaining
          handoverStatus: "pending",
        },
      ],
      allHandedOver: false,
      secureTicketToken: "tok-2",
    },
    {
      id: "ord-3",
      organizerId: "org-island-fish-fry",
      ticketCode: "SFF-003",
      orderNumber: "ORD-2026-0003",
      customerName: "Neville Grant",
      customerPhone: "876-555-3333",
      method: "zelle",
      subtotal: 36.0,
      packagingFee: 4.0,
      totalAmount: 40.0,
      amountPaid: 0,
      currency: "USD",
      status: "payment_submitted", // Pending verification
      createdAt: "2026-11-28T11:00:00Z",
      items: [
        {
          menuItemId: "snapper-usd",
          name: "Escovitch Snapper Meal",
          pricingType: "fixed",
          basePrice: 18.0,
          selectedOptionsSummary: [],
          optionsTotalExtra: 0,
          finalItemPrice: 18.0,
          currency: "USD",
          quantity: 2,
          collectedQuantity: 0,
          handoverStatus: "pending",
        },
      ],
      allHandedOver: false,
      secureTicketToken: "tok-3",
    },
  ];

  const testCashLedger: CashPaymentRecord[] = [
    {
      id: "cpr-1",
      organizerId: "org-island-fish-fry",
      transactionType: "receipt",
      receiptNumber: "CR-2026-0001",
      customerName: "Clinton Brown",
      customerPhone: "876-555-2222",
      orderNumber: "ORD-2026-0002",
      amount: 3100,
      currency: "JMD",
      receivedAt: "2026-11-28T10:30:00Z",
      receivedBy: "Pastor Brown",
    },
    {
      id: "cpr-2",
      organizerId: "org-island-fish-fry",
      transactionType: "receipt",
      receiptNumber: "CR-2026-0002",
      customerName: "Walk-in Guest",
      customerPhone: "876-555-4444",
      amount: 1000,
      currency: "JMD",
      receivedAt: "2026-11-28T11:15:00Z",
      receivedBy: "Treasurer Davis",
    },
    {
      id: "cpr-3",
      organizerId: "org-island-fish-fry",
      transactionType: "reversal",
      receiptNumber: "REV-CR-2026-0002",
      customerName: "Walk-in Guest",
      customerPhone: "876-555-4444",
      amount: -1000, // Reversal adjustment
      currency: "JMD",
      receivedAt: "2026-11-28T11:20:00Z",
      receivedBy: "Treasurer Davis",
      reversalOfId: "cpr-2",
    },
  ];

  it("should calculate gross sales and verified paid sales accurately separated by currency", () => {
    const report = calculateFinancialAndKitchenReport(testOrders, testCashLedger);

    // JMD: ord-1 (5900) + ord-2 (3100) = 9000
    expect(report.grossSalesJMD).toBe(9000);
    // Both JMD orders are status "paid"
    expect(report.verifiedPaidJMD).toBe(9000);

    // USD: ord-3 (40)
    expect(report.grossSalesUSD).toBe(40.0);
    // ord-3 is payment_submitted, not paid
    expect(report.verifiedPaidUSD).toBe(0);

    expect(report.pendingVerificationCount).toBe(1);
    expect(report.unpaidCount).toBe(0);
  });

  it("should calculate cash in hand accurately including reversal adjustments", () => {
    const report = calculateFinancialAndKitchenReport(testOrders, testCashLedger);

    // Cash Ledger: 3100 + 1000 - 1000 = 3100
    expect(report.cashInHandJMD).toBe(3100);
    expect(report.cashInHandUSD).toBe(0);
  });

  it("should aggregate kitchen prep metrics accurately: ordered, collected, and remaining", () => {
    const report = calculateFinancialAndKitchenReport(testOrders, testCashLedger);

    // Escovitch Snapper Meal: ord-1 (2 ordered, 2 collected) + ord-2 (1 ordered, 0 collected) + ord-3 (2 ordered, 0 collected)
    const fishReport = report.kitchenPrepItemCounts["Escovitch Snapper Meal"];
    expect(fishReport).toBeDefined();
    expect(fishReport.totalOrdered).toBe(5);
    expect(fishReport.totalCollected).toBe(2);
    expect(fishReport.remaining).toBe(3); // 5 - 2 = 3 still to cook/serve

    // Golden Fried Festival: ord-2 (2 ordered, 1 collected)
    const festivalReport = report.kitchenPrepItemCounts["Golden Fried Festival (4 pcs)"];
    expect(festivalReport).toBeDefined();
    expect(festivalReport.totalOrdered).toBe(2);
    expect(festivalReport.totalCollected).toBe(1);
    expect(festivalReport.remaining).toBe(1);
  });
});
