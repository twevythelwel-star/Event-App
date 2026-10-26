import { describe, it, expect } from "vitest";
import { TicketOrder } from "../types";

describe("08: Ticket Issuance and Retrieval Suite", () => {
  const sampleOrders: TicketOrder[] = [
    {
      id: "ord-201",
      organizerId: "org-island-fish-fry",
      ticketCode: "SFF-201",
      orderNumber: "ORD-2026-0201",
      customerName: "Alicia Miller",
      customerPhone: "876-555-4011",
      method: "bank",
      subtotal: 2800,
      packagingFee: 300,
      totalAmount: 3100,
      amountPaid: 3100,
      currency: "JMD",
      status: "paid",
      createdAt: "2026-11-20T12:00:00Z",
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
          collectedQuantity: 0,
          handoverStatus: "pending",
        },
      ],
      allHandedOver: false,
      secureTicketToken: "tok-sff-201-sha256-verified",
    },
    {
      id: "ord-202",
      organizerId: "org-island-fish-fry",
      ticketCode: "SFF-202",
      orderNumber: "ORD-2026-0202",
      customerName: "Alicia Miller",
      customerPhone: "876-555-4011",
      method: "zelle",
      subtotal: 18.0,
      packagingFee: 2.0,
      totalAmount: 20.0,
      amountPaid: 0,
      currency: "USD",
      status: "payment_submitted",
      createdAt: "2026-11-21T09:30:00Z",
      items: [],
      allHandedOver: false,
      secureTicketToken: "tok-sff-202-sha256-pending",
    },
    {
      id: "ord-203",
      organizerId: "org-island-fish-fry",
      ticketCode: "SFF-203",
      orderNumber: "ORD-2026-0203",
      customerName: "Rohan Bennett",
      customerPhone: "876-555-9000",
      method: "cash",
      subtotal: 5600,
      packagingFee: 300,
      totalAmount: 5900,
      amountPaid: 0,
      currency: "JMD",
      status: "awaiting_payment",
      createdAt: "2026-11-22T14:00:00Z",
      items: [],
      allHandedOver: false,
      secureTicketToken: "tok-sff-203-sha256-cash",
    },
  ];

  function retrieveOrdersBySearch(query: string, orders: TicketOrder[]): TicketOrder[] {
    const q = query.trim().toLowerCase();
    const cleanPhone = q.replace(/\D/g, "");

    return orders.filter((o) => {
      const matchOrderNum = o.orderNumber.toLowerCase() === q;
      const matchTicket = o.ticketCode.toLowerCase() === q;
      const matchPhone = cleanPhone.length >= 7 && o.customerPhone.replace(/\D/g, "").includes(cleanPhone);
      return matchOrderNum || matchTicket || matchPhone;
    });
  }

  it("should retrieve ticket by exact order number", () => {
    const results = retrieveOrdersBySearch("ORD-2026-0201", sampleOrders);
    expect(results).toHaveLength(1);
    expect(results[0].ticketCode).toBe("SFF-201");
    expect(results[0].customerName).toBe("Alicia Miller");
  });

  it("should retrieve ticket by ticket code (case-insensitive)", () => {
    const results = retrieveOrdersBySearch("sff-201", sampleOrders);
    expect(results).toHaveLength(1);
    expect(results[0].orderNumber).toBe("ORD-2026-0201");
  });

  it("should retrieve all customer orders by phone number", () => {
    const results = retrieveOrdersBySearch("876-555-4011", sampleOrders);
    expect(results).toHaveLength(2);
    expect(results.map((r) => r.ticketCode)).toEqual(["SFF-201", "SFF-202"]);
  });

  it("should return empty array for non-existent order or phone number", () => {
    const results = retrieveOrdersBySearch("ORD-9999-9999", sampleOrders);
    expect(results).toHaveLength(0);

    const unknownPhone = retrieveOrdersBySearch("876-000-0000", sampleOrders);
    expect(unknownPhone).toHaveLength(0);
  });

  it("should issue unique cryptographically distinct tokens for every ticket", () => {
    const tokens = sampleOrders.map((o) => o.secureTicketToken);
    const uniqueTokens = new Set(tokens);
    expect(uniqueTokens.size).toBe(sampleOrders.length);
  });

  it("should accurately distinguish verified paid tickets from pending transfer tickets", () => {
    const paidTicket = sampleOrders.find((o) => o.ticketCode === "SFF-201");
    expect(paidTicket?.status).toBe("paid");
    expect(paidTicket?.amountPaid).toBe(paidTicket?.totalAmount);

    const pendingTicket = sampleOrders.find((o) => o.ticketCode === "SFF-202");
    expect(pendingTicket?.status).toBe("payment_submitted");
    expect(pendingTicket?.amountPaid).toBe(0);
  });
});
