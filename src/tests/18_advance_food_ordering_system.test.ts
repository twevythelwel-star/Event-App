import { describe, it, expect } from "vitest";
import { EventDetails, MenuItem, TicketOrder } from "../types";
import {
  calculateItemFinalPrice,
  calculateOrderTotals,
  fromMinorUnits,
  retrieveOrderSecurely,
  simulateConcurrentOrderPlacement,
  snapshotOrderItem,
  toMinorUnits,
  validateAndCreateGuestOrder,
} from "./testUtils";

describe("18: Advance Food-Ordering System Suite", () => {
  const sampleEvent: EventDetails = {
    title: "Kingston Youth Fellowship Annual Fish Fry 2026",
    tagline: "Authentic Caribbean Cuisine Fundraiser",
    dateStr: "Saturday, November 14, 2026",
    timeStr: "12:00 PM – 8:00 PM",
    timezone: "America/Jamaica",
    venueName: "Hope Gardens Pavilion",
    venueAddress: "Old Hope Road, Kingston 6",
    coordinates: "18.0179° N, 76.7554° W",
    description: "Enjoy freshly prepared escovitch fish, jerk chicken, and festival sides.",
    currency: "USD",
    organizerName: "Kingston Youth Fellowship",
    organizerPhone: "+1 (876) 555-0192",
    organizerWhatsApp: "+1 (876) 555-0192",
    organizerEmail: "orders@kingstonyouth.org",
    advanceOrderDeadline: "Friday, Nov 13, 2026 at 6:00 PM EST",
    isOrderingOpen: true,
    status: "published",
    pickupInstructions: "Collect food at Pavilion Tent A with your digital ticket QR code.",
    packagingFeeUSD: 2.5,
    packagingFeeJMD: 400,
  };

  const fixedSnapperMeal: MenuItem = {
    id: "item-snapper-fixed",
    name: "Classic Escovitch Snapper Plate",
    category: "Mains",
    description: "Crispy fried whole red snapper with spicy pickled pickle and 2 festivals.",
    pricingType: "fixed",
    priceUSD: 18.0,
    priceJMD: 2800,
    imageUrl: "/images/escovitch_fish.jpg",
    availableStock: 25,
  };

  const customizableMeal: MenuItem = {
    id: "item-fish-custom",
    name: "Custom Escovitch Fish Feast",
    category: "Mains",
    description: "Choose your fish size, signature side pairing, and hot sauce level.",
    pricingType: "customizable",
    priceUSD: 18.0,
    priceJMD: 2800,
    imageUrl: "/images/fish-custom.jpg",
    availableStock: 20,
    customOptionGroups: [
      {
        id: "portion-size",
        title: "Fish Portion Size",
        required: true,
        choices: [
          { label: "Medium Snapper (approx 1 lb)", extraPriceUSD: 0, extraPriceJMD: 0 },
          { label: "Large Snapper (approx 1.5 lb)", extraPriceUSD: 4.5, extraPriceJMD: 700 },
          { label: "Jumbo King Snapper (approx 2 lb)", extraPriceUSD: 9.0, extraPriceJMD: 1400 },
        ],
      },
      {
        id: "side-pairing",
        title: "Side Pairing",
        required: true,
        choices: [
          { label: "Golden Fried Festival (2 pcs)", extraPriceUSD: 0, extraPriceJMD: 0 },
          { label: "Crispy Cassava Bammy (2 wedges)", extraPriceUSD: 1.75, extraPriceJMD: 280 },
          { label: "Combo: Bammy + Festival", extraPriceUSD: 2.75, extraPriceJMD: 440 },
        ],
      },
    ],
  };

  const donationContributionItem: MenuItem = {
    id: "item-youth-donation",
    name: "Youth Culinary Education Fund",
    category: "Community Donations",
    description: "Organizer-authorized donation towards youth culinary apprenticeship supplies.",
    pricingType: "custom_amount",
    priceUSD: 0,
    priceJMD: 0,
    imageUrl: "/images/donate.jpg",
    customAmountConfig: {
      minUSD: 5,
      maxUSD: 1000,
      minJMD: 500,
      maxJMD: 150000,
      suggestedAmountsUSD: [10, 25, 50, 100],
      suggestedAmountsJMD: [1000, 2500, 5000, 15000],
    },
  };

  describe("A. Exact Integer Minor Units Financial Calculation (No Float Drift)", () => {
    it("should convert decimal amounts to integer minor units (cents) accurately", () => {
      expect(toMinorUnits(18.5)).toBe(1850);
      expect(toMinorUnits(0.1)).toBe(10);
      expect(toMinorUnits(0.2)).toBe(20);
      // Floating point 0.1 + 0.2 in JS is 0.30000000000000004; in minor units it is exactly 30 cents
      const centsTotal = toMinorUnits(0.1) + toMinorUnits(0.2);
      expect(centsTotal).toBe(30);
      expect(fromMinorUnits(centsTotal)).toBe(0.3);
    });

    it("should calculate customizable meal pricing with multiple add-ons using integer minor units", () => {
      // Large Snapper (+$4.50) + Combo Bammy+Festival (+$2.75) on $18.00 base
      // Expected = $18.00 + $4.50 + $2.75 = $25.25
      const resultUSD = calculateItemFinalPrice(
        customizableMeal,
        "USD",
        { "portion-size": 1, "side-pairing": 2 }
      );
      expect(resultUSD.basePriceMinor).toBe(1800);
      expect(resultUSD.optionsExtraMinor).toBe(725); // 450 + 275 cents
      expect(resultUSD.finalPriceMinor).toBe(2525);
      expect(resultUSD.finalPrice).toBe(25.25);
      expect(resultUSD.summaryList).toHaveLength(2);
      expect(resultUSD.summaryList[0]).toContain("Large Snapper");
      expect(resultUSD.summaryList[1]).toContain("Combo: Bammy + Festival");

      // In JMD: J$2800 + J$700 + J$440 = J$3940
      const resultJMD = calculateItemFinalPrice(
        customizableMeal,
        "JMD",
        { "portion-size": 1, "side-pairing": 2 }
      );
      expect(resultJMD.basePrice).toBe(2800);
      expect(resultJMD.optionsExtra).toBe(1140);
      expect(resultJMD.finalPrice).toBe(3940);
    });

    it("should calculate order subtotal and packaging fee in minor units accurately", () => {
      const items = [
        { finalItemPrice: 25.25, quantity: 2 }, // 50.50
        { finalItemPrice: 18.0, quantity: 1 },  // 18.00
      ];
      const packagingFee = 2.5; // 2.50
      const orderTotals = calculateOrderTotals(items, packagingFee, "USD");

      expect(orderTotals.subtotalMinor).toBe(6850); // $68.50 in cents
      expect(orderTotals.packagingFeeMinor).toBe(250); // $2.50 in cents
      expect(orderTotals.totalAmountMinor).toBe(7100); // $71.00 in cents
      expect(orderTotals.totalAmount).toBe(71.0);
    });
  });

  describe("B. Organizer-Authorized Custom-Amount Items", () => {
    it("should accept valid custom donation amounts within organizer bounds", () => {
      const orderRes = validateAndCreateGuestOrder(sampleEvent, {
        customerName: "Audrey Campbell",
        customerPhone: "+1 (876) 555-8921",
        method: "cash",
        items: [
          {
            menuItem: donationContributionItem,
            quantity: 1,
            customDonationAmount: 50.0,
          },
        ],
      });

      expect(orderRes.success).toBe(true);
      expect(orderRes.order).toBeDefined();
      expect(orderRes.order?.items[0].customDonationAmount).toBe(50.0);
      expect(orderRes.order?.subtotal).toBe(50.0);
    });

    it("should reject custom donation amounts below or above organizer-configured limits", () => {
      // Config min is $5.00, max is $1000.00
      const underMinRes = validateAndCreateGuestOrder(sampleEvent, {
        customerName: "Audrey Campbell",
        customerPhone: "+1 (876) 555-8921",
        method: "cash",
        items: [
          {
            menuItem: donationContributionItem,
            quantity: 1,
            customDonationAmount: 2.0, // Under $5 limit
          },
        ],
      });
      expect(underMinRes.success).toBe(false);
      expect(underMinRes.errors?.[0]).toContain("must be between 5 and 1000 USD");

      const overMaxRes = validateAndCreateGuestOrder(sampleEvent, {
        customerName: "Audrey Campbell",
        customerPhone: "+1 (876) 555-8921",
        method: "cash",
        items: [
          {
            menuItem: donationContributionItem,
            quantity: 1,
            customDonationAmount: 5000.0, // Over $1000 limit
          },
        ],
      });
      expect(overMaxRes.success).toBe(false);
      expect(overMaxRes.errors?.[0]).toContain("must be between 5 and 1000 USD");
    });
  });

  describe("C. Quantity Validation & Invalid Inputs", () => {
    it("should reject zero, negative, and fractional quantities", () => {
      const zeroQty = validateAndCreateGuestOrder(sampleEvent, {
        customerName: "David Sterling",
        customerPhone: "+1 (876) 555-1122",
        method: "cash",
        items: [{ menuItem: fixedSnapperMeal, quantity: 0 }],
      });
      expect(zeroQty.success).toBe(false);
      expect(zeroQty.errors?.[0]).toContain("Must be a whole positive number");

      const negativeQty = validateAndCreateGuestOrder(sampleEvent, {
        customerName: "David Sterling",
        customerPhone: "+1 (876) 555-1122",
        method: "cash",
        items: [{ menuItem: fixedSnapperMeal, quantity: -3 }],
      });
      expect(negativeQty.success).toBe(false);

      const fractionalQty = validateAndCreateGuestOrder(sampleEvent, {
        customerName: "David Sterling",
        customerPhone: "+1 (876) 555-1122",
        method: "cash",
        items: [{ menuItem: fixedSnapperMeal, quantity: 2.5 }],
      });
      expect(fractionalQty.success).toBe(false);
    });

    it("should require a valid customer phone number for SMS and pickup identification", () => {
      const invalidPhone = validateAndCreateGuestOrder(sampleEvent, {
        customerName: "David Sterling",
        customerPhone: "123", // too short
        method: "cash",
        items: [{ menuItem: fixedSnapperMeal, quantity: 1 }],
      });
      expect(invalidPhone.success).toBe(false);
      expect(invalidPhone.errors?.[0]).toContain("Valid phone number is required");
    });
  });

  describe("D. Historical Price & Options Preservation (Snapshotting)", () => {
    it("should preserve original item names, options, and prices in order when menu item changes in catalog", () => {
      // Step 1: Customer orders the customizable meal at current price of $25.25
      const snapshottedItem = snapshotOrderItem(
        customizableMeal,
        "USD",
        { "portion-size": 1, "side-pairing": 2 },
        2
      );

      expect(snapshottedItem.name).toBe("Custom Escovitch Fish Feast");
      expect(snapshottedItem.finalItemPrice).toBe(25.25);
      expect(snapshottedItem.quantity).toBe(2);

      // Step 2: Next week, organizer increases menu prices in catalog and modifies name
      const modifiedCatalogItem: MenuItem = {
        ...customizableMeal,
        name: "Deluxe Escovitch Fish Feast [PRICE INCREASE]",
        priceUSD: 24.0, // increased from 18.0
      };

      // Step 3: Verify the previously snapshotted order item remains unchanged!
      expect(snapshottedItem.name).toBe("Custom Escovitch Fish Feast");
      expect(snapshottedItem.finalItemPrice).toBe(25.25);
      expect(modifiedCatalogItem.name).not.toBe(snapshottedItem.name);
      expect(modifiedCatalogItem.priceUSD).not.toBe(snapshottedItem.basePrice);
    });
  });

  describe("E. Stock Limits & Safe Concurrent Ordering", () => {
    it("should prevent overselling and race conditions when multiple orders contest inventory", () => {
      const initialInventory: Record<string, number> = {
        "item-snapper-fixed": 3, // Only 3 portions left!
      };

      // Two customers concurrently attempt to order 2 portions each (total requested = 4)
      const concurrentOrders = [
        {
          orderId: "ord-first-customer",
          items: [{ menuItemId: "item-snapper-fixed", quantity: 2 }],
        },
        {
          orderId: "ord-second-customer",
          items: [{ menuItemId: "item-snapper-fixed", quantity: 2 }],
        },
      ];

      const simResult = simulateConcurrentOrderPlacement(initialInventory, concurrentOrders);

      // First order should succeed
      expect(simResult.successfulOrderIds).toContain("ord-first-customer");
      // Second order should be rejected with an insufficient stock error
      expect(simResult.failedOrders).toHaveLength(1);
      expect(simResult.failedOrders[0].orderId).toBe("ord-second-customer");
      expect(simResult.failedOrders[0].error).toContain("Insufficient stock for item 'item-snapper-fixed'");
      // Final remaining inventory should be 1, never negative
      expect(simResult.finalInventory["item-snapper-fixed"]).toBe(1);
    });
  });

  describe("F. Order Status Separation & Initial Unpaid State", () => {
    it("should initially create cash orders with awaiting_payment status, NEVER marked paid upon submission", () => {
      const cashOrderResult = validateAndCreateGuestOrder(sampleEvent, {
        customerName: "Sharon Reid",
        customerPhone: "+1 (876) 555-3419",
        method: "cash",
        items: [{ menuItem: fixedSnapperMeal, quantity: 1 }],
      });

      expect(cashOrderResult.success).toBe(true);
      const order = cashOrderResult.order!;
      expect(order.status).toBe("awaiting_payment");
      expect(order.amountPaid).toBe(0);
      expect(order.authorizedUnpaid).toBe(true);
      expect(order.fulfillmentStatus).toBe("unprepared");
      expect(order.ticketCode).toMatch(/^SFF-\d+$/);
      expect(order.orderNumber).toMatch(/^ORD-\d{4}-\d+$/);
    });

    it("should mark manual transfer order as payment_submitted when customer enters reference, awaiting organizer verification", () => {
      const transferOrderResult = validateAndCreateGuestOrder(sampleEvent, {
        customerName: "Marcus Sterling",
        customerPhone: "+1 (876) 555-9012",
        method: "bank",
        paymentReference: "NCB-WIRE-8891234",
        senderName: "Marcus Sterling",
        items: [{ menuItem: fixedSnapperMeal, quantity: 1 }],
      });

      expect(transferOrderResult.success).toBe(true);
      const order = transferOrderResult.order!;
      expect(order.status).toBe("payment_submitted");
      expect(order.amountPaid).toBe(0); // Still 0 verified amount
      expect(order.paymentReference).toBe("NCB-WIRE-8891234");
    });
  });

  describe("G. Secure Order Retrieval", () => {
    it("should allow customer to retrieve their order via matching phone number and order code", () => {
      const testOrder: TicketOrder = {
        id: "ord-test-retrieval",
        ticketCode: "SFF-8492",
        orderNumber: "ORD-2026-8492",
        customerName: "Beverley Henry",
        customerPhone: "876-555-4019",
        method: "cash",
        subtotal: 18.0,
        packagingFee: 2.5,
        totalAmount: 20.5,
        currency: "USD",
        status: "awaiting_payment",
        fulfillmentStatus: "unprepared",
        createdAt: new Date().toISOString(),
        items: [],
        allHandedOver: false,
        secureTicketToken: "tok-secure-unique-pass-8492",
      };

      // Valid phone match succeeds
      const validRetrieval = retrieveOrderSecurely([testOrder], "SFF-8492", "876-555-4019");
      expect(validRetrieval.success).toBe(true);
      expect(validRetrieval.order?.customerName).toBe("Beverley Henry");

      // Valid token match succeeds
      const tokenRetrieval = retrieveOrderSecurely([testOrder], "tok-secure-unique-pass-8492");
      expect(tokenRetrieval.success).toBe(true);

      // Mismatched phone is rejected
      const mismatchRetrieval = retrieveOrderSecurely([testOrder], "SFF-8492", "876-999-9999");
      expect(mismatchRetrieval.success).toBe(false);
      expect(mismatchRetrieval.error).toContain("Authentication failed");
    });
  });
});
