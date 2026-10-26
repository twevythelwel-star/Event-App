import { describe, it, expect } from "vitest";
import { processStockReservation } from "./testUtils";

describe("05: Stock and Quantity Limits Suite", () => {
  const initialStock: Record<string, number> = {
    "snapper-regular": 25,
    "jumbo-lobster": 10,
    "bammy-side": 50,
  };

  it("should successfully reserve stock when requested quantity is available", () => {
    const reservation = processStockReservation(initialStock, [
      { menuItemId: "snapper-regular", quantity: 3 },
      { menuItemId: "bammy-side", quantity: 4 },
    ]);

    expect(reservation.success).toBe(true);
    expect(reservation.updatedInventory["snapper-regular"]).toBe(22);
    expect(reservation.updatedInventory["bammy-side"]).toBe(46);
    expect(reservation.updatedInventory["jumbo-lobster"]).toBe(10); // Untouched
  });

  it("should reject an order if requested quantity exceeds available stock", () => {
    const oversizedReservation = processStockReservation(initialStock, [
      { menuItemId: "jumbo-lobster", quantity: 12 }, // Only 10 available
    ]);

    expect(oversizedReservation.success).toBe(false);
    expect(oversizedReservation.error).toContain("Insufficient stock for item 'jumbo-lobster'");
    // Verify inventory was not modified on failure
    expect(oversizedReservation.updatedInventory["jumbo-lobster"]).toBe(10);
  });

  it("should reject zero, negative, or fractional order quantities", () => {
    const zeroReservation = processStockReservation(initialStock, [
      { menuItemId: "snapper-regular", quantity: 0 },
    ]);
    expect(zeroReservation.success).toBe(false);
    expect(zeroReservation.error).toContain("Invalid requested quantity");

    const negativeReservation = processStockReservation(initialStock, [
      { menuItemId: "snapper-regular", quantity: -2 },
    ]);
    expect(negativeReservation.success).toBe(false);

    const fractionalReservation = processStockReservation(initialStock, [
      { menuItemId: "snapper-regular", quantity: 1.5 },
    ]);
    expect(fractionalReservation.success).toBe(false);
  });

  it("should allow uncapped items without inventory restrictions", () => {
    // Donation or unlimited drink items have no stock entry
    const reservation = processStockReservation(initialStock, [
      { menuItemId: "youth-donation-item", quantity: 1 },
      { menuItemId: "snapper-regular", quantity: 2 },
    ]);

    expect(reservation.success).toBe(true);
    expect(reservation.updatedInventory["snapper-regular"]).toBe(23);
  });
});
