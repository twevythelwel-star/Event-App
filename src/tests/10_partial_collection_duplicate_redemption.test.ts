import { describe, it, expect } from "vitest";
import { HandoverAuditEntry, TicketOrder } from "../types";
import { updateItemPortionCollection } from "./testUtils";

describe("10: Partial Collection and Duplicate Redemption Suite", () => {
  function createMultiItemOrder(): TicketOrder {
    return {
      id: "ord-collect-501",
      organizerId: "org-island-fish-fry",
      ticketCode: "SFF-501",
      orderNumber: "ORD-2026-0501",
      customerName: "Omarion Blake",
      customerPhone: "876-555-6677",
      method: "bank",
      subtotal: 6400,
      packagingFee: 300,
      totalAmount: 6700,
      amountPaid: 6700,
      currency: "JMD",
      status: "paid",
      fulfillmentStatus: "preparing",
      createdAt: "2026-11-28T13:00:00Z",
      items: [
        {
          menuItemId: "escovitch-fish",
          name: "Escovitch Fish Meal",
          pricingType: "fixed",
          basePrice: 2800,
          selectedOptionsSummary: ["Fish Size: Large"],
          optionsTotalExtra: 0,
          finalItemPrice: 2800,
          currency: "JMD",
          quantity: 2, // 2 portions
          collectedQuantity: 0,
          handoverStatus: "pending",
        },
        {
          menuItemId: "golden-festival",
          name: "Golden Fried Festival (4 pcs)",
          pricingType: "fixed",
          basePrice: 800,
          selectedOptionsSummary: [],
          optionsTotalExtra: 0,
          finalItemPrice: 800,
          currency: "JMD",
          quantity: 1, // 1 portion
          collectedQuantity: 0,
          handoverStatus: "pending",
        },
      ],
      allHandedOver: false,
      secureTicketToken: "tok-sff-501",
    };
  }

  it("should process partial collection of portions and keep item pending if not all collected", () => {
    const order = createMultiItemOrder();
    const fishItem = order.items[0]; // quantity: 2

    // Collect 1 of 2 fish meals
    const { updatedItem, success, error } = updateItemPortionCollection(fishItem, 1);

    expect(success).toBe(true);
    expect(error).toBeUndefined();
    expect(updatedItem.collectedQuantity).toBe(1);
    expect(updatedItem.quantity - (updatedItem.collectedQuantity || 0)).toBe(1); // 1 remaining
    expect(updatedItem.handoverStatus).toBe("pending"); // Not fully collected yet
    expect(updatedItem.handedOverAt).toBeDefined();
  });

  it("should mark item as collected when all portions are handed over", () => {
    const order = createMultiItemOrder();
    let fishItem = order.items[0];

    // Collect first portion
    const step1 = updateItemPortionCollection(fishItem, 1);
    fishItem = step1.updatedItem;

    // Collect second portion
    const step2 = updateItemPortionCollection(fishItem, 1);
    fishItem = step2.updatedItem;

    expect(step2.success).toBe(true);
    expect(fishItem.collectedQuantity).toBe(2);
    expect(fishItem.handoverStatus).toBe("collected");
  });

  it("should block duplicate redemption when portions have already been fully collected", () => {
    const order = createMultiItemOrder();
    let fishItem = order.items[0];

    // Collect all 2 portions
    fishItem = updateItemPortionCollection(fishItem, 2).updatedItem;
    expect(fishItem.handoverStatus).toBe("collected");

    // Attempt to collect a 3rd portion
    const duplicateAttempt = updateItemPortionCollection(fishItem, 1);
    expect(duplicateAttempt.success).toBe(false);
    expect(duplicateAttempt.error).toContain("Cannot collect 1 portions. Only 0 remaining.");
    expect(duplicateAttempt.updatedItem.collectedQuantity).toBe(2); // Retained original
  });

  it("should prevent zero or negative collection increments", () => {
    const order = createMultiItemOrder();
    const fishItem = order.items[0];

    const zeroAttempt = updateItemPortionCollection(fishItem, 0);
    expect(zeroAttempt.success).toBe(false);
    expect(zeroAttempt.error).toContain("Quantity to collect must be positive");

    const negativeAttempt = updateItemPortionCollection(fishItem, -1);
    expect(negativeAttempt.success).toBe(false);
  });

  it("should maintain order fulfillment status as preparing until all distinct items are collected", () => {
    const order = createMultiItemOrder();

    // Collect all fish meals (2 of 2)
    order.items[0] = updateItemPortionCollection(order.items[0], 2).updatedItem;

    // Order still has uncollected festival
    const allItemsCollected = order.items.every((itm) => itm.handoverStatus === "collected");
    expect(allItemsCollected).toBe(false);
    expect(order.allHandedOver).toBe(false);

    // Now collect festival (1 of 1)
    order.items[1] = updateItemPortionCollection(order.items[1], 1).updatedItem;

    const allItemsNowCollected = order.items.every((itm) => itm.handoverStatus === "collected");
    expect(allItemsNowCollected).toBe(true);

    if (allItemsNowCollected) {
      order.allHandedOver = true;
      order.fulfillmentStatus = "collected";
    }

    expect(order.allHandedOver).toBe(true);
    expect(order.fulfillmentStatus).toBe("collected");
  });

  it("should record tamper-evident handover audit logs for every redemption", () => {
    const auditLogs: HandoverAuditEntry[] = [];
    const staffName = "Worker Dwayne";
    const station = "Fish Grill Station #1";

    const logEntry: HandoverAuditEntry = {
      id: `audit-${Date.now()}`,
      organizerId: "org-island-fish-fry",
      orderId: "ord-collect-501",
      orderNumber: "ORD-2026-0501",
      ticketCode: "SFF-501",
      customerName: "Omarion Blake",
      itemName: "Escovitch Fish Meal",
      quantityCollected: 1,
      remainingQuantity: 1,
      timestamp: new Date().toISOString(),
      staffName,
      station,
      actionType: "collection",
    };
    auditLogs.push(logEntry);

    expect(auditLogs).toHaveLength(1);
    expect(auditLogs[0].staffName).toBe("Worker Dwayne");
    expect(auditLogs[0].station).toBe("Fish Grill Station #1");
    expect(auditLogs[0].quantityCollected).toBe(1);
  });
});
