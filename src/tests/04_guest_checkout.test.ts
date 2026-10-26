import { describe, it, expect } from "vitest";
import { TicketOrder } from "../types";

describe("04: Guest Checkout Suite", () => {
  function simulateGuestCheckout(payload: {
    customerName: string;
    customerPhone: string;
    email?: string;
    method: "bank" | "zelle" | "cashapp" | "cash";
    paymentReference?: string;
    senderName?: string;
    items: { menuItemId: string; name: string; quantity: number; finalItemPrice: number }[];
    packagingFee: number;
    currency: "USD" | "JMD";
    organizerId: string;
  }): { success: boolean; order?: TicketOrder; errors?: string[] } {
    const errors: string[] = [];

    if (!payload.customerName || payload.customerName.trim().length < 2) {
      errors.push("Customer name is required");
    }
    const cleanPhone = (payload.customerPhone || "").replace(/\D/g, "");
    if (cleanPhone.length < 7) {
      errors.push("Valid phone number is required for SMS ticket delivery and pickup alerts");
    }
    if (!payload.items || payload.items.length === 0) {
      errors.push("Cart must contain at least one item to checkout");
    }

    if (errors.length > 0) {
      return { success: false, errors };
    }

    const subtotal = payload.items.reduce((sum, item) => sum + item.finalItemPrice * item.quantity, 0);
    const totalAmount = subtotal + payload.packagingFee;
    const randomSuffix = Math.floor(1000 + Math.random() * 9000);
    const orderNumber = `ORD-2026-${randomSuffix}`;
    const ticketCode = `SFF-${randomSuffix}`;

    // Status logic per specification:
    // If a reference or sender name is submitted with transfer method -> payment_submitted
    // Otherwise awaiting_payment
    const hasSubmittedTransfer = Boolean(payload.paymentReference && payload.paymentReference.trim().length > 0);
    const initialStatus = hasSubmittedTransfer ? "payment_submitted" : "awaiting_payment";

    const order: TicketOrder = {
      id: `ord-${Date.now()}-${randomSuffix}`,
      organizerId: payload.organizerId,
      ticketCode,
      orderNumber,
      customerName: payload.customerName.trim(),
      customerPhone: payload.customerPhone.trim(),
      email: payload.email?.trim() || undefined,
      method: payload.method,
      subtotal,
      packagingFee: payload.packagingFee,
      totalAmount,
      amountPaid: 0,
      currency: payload.currency,
      status: initialStatus,
      fulfillmentStatus: "unprepared",
      paymentReference: payload.paymentReference?.trim() || undefined,
      senderName: payload.senderName?.trim() || undefined,
      createdAt: new Date().toISOString(),
      items: payload.items.map((i) => ({
        menuItemId: i.menuItemId,
        name: i.name,
        pricingType: "fixed",
        basePrice: i.finalItemPrice,
        selectedOptionsSummary: [],
        optionsTotalExtra: 0,
        finalItemPrice: i.finalItemPrice,
        currency: payload.currency,
        quantity: i.quantity,
        collectedQuantity: 0,
        handoverStatus: "pending",
      })),
      allHandedOver: false,
      secureTicketToken: `tok-guest-${Date.now()}-${randomSuffix}`,
    };

    return { success: true, order };
  }

  it("should successfully process a guest checkout without user login or account creation", () => {
    const result = simulateGuestCheckout({
      customerName: "Camille Watson",
      customerPhone: "876-555-7821",
      email: "camille.watson@gmail.com",
      method: "cash",
      items: [{ menuItemId: "fish-1", name: "Escovitch Snapper", quantity: 2, finalItemPrice: 2800 }],
      packagingFee: 300,
      currency: "JMD",
      organizerId: "org-island-fish-fry",
    });

    expect(result.success).toBe(true);
    expect(result.order).toBeDefined();
    expect(result.order?.customerName).toBe("Camille Watson");
    expect(result.order?.totalAmount).toBe(5900); // 2800 * 2 + 300
    expect(result.order?.status).toBe("awaiting_payment");
    expect(result.order?.fulfillmentStatus).toBe("unprepared");
    expect(result.order?.allHandedOver).toBe(false);
    expect(result.order?.items[0].collectedQuantity).toBe(0);
    expect(result.order?.items[0].handoverStatus).toBe("pending");
  });

  it("should mark status as 'payment_submitted' when customer enters a bank transfer reference number", () => {
    const result = simulateGuestCheckout({
      customerName: "Devon Campbell",
      customerPhone: "876-555-3344",
      method: "bank",
      paymentReference: "NCB-TRF-9823101",
      senderName: "Devon Campbell",
      items: [{ menuItemId: "fish-1", name: "Escovitch Snapper", quantity: 1, finalItemPrice: 2800 }],
      packagingFee: 300,
      currency: "JMD",
      organizerId: "org-island-fish-fry",
    });

    expect(result.success).toBe(true);
    expect(result.order?.status).toBe("payment_submitted");
    expect(result.order?.paymentReference).toBe("NCB-TRF-9823101");
    expect(result.order?.senderName).toBe("Devon Campbell");
  });

  it("should reject checkout if customer name or phone number is invalid", () => {
    const missingName = simulateGuestCheckout({
      customerName: "",
      customerPhone: "876-555-1234",
      method: "zelle",
      items: [{ menuItemId: "fish-1", name: "Escovitch Snapper", quantity: 1, finalItemPrice: 18 }],
      packagingFee: 2,
      currency: "USD",
      organizerId: "org-island-fish-fry",
    });
    expect(missingName.success).toBe(false);
    expect(missingName.errors).toContain("Customer name is required");

    const invalidPhone = simulateGuestCheckout({
      customerName: "Marcus Sterling",
      customerPhone: "123", // Too short
      method: "zelle",
      items: [{ menuItemId: "fish-1", name: "Escovitch Snapper", quantity: 1, finalItemPrice: 18 }],
      packagingFee: 2,
      currency: "USD",
      organizerId: "org-island-fish-fry",
    });
    expect(invalidPhone.success).toBe(false);
    expect(invalidPhone.errors).toContain("Valid phone number is required for SMS ticket delivery and pickup alerts");
  });

  it("should reject checkout with an empty cart", () => {
    const emptyCart = simulateGuestCheckout({
      customerName: "Tanya Morrison",
      customerPhone: "876-555-9988",
      method: "cashapp",
      items: [],
      packagingFee: 2,
      currency: "USD",
      organizerId: "org-island-fish-fry",
    });
    expect(emptyCart.success).toBe(false);
    expect(emptyCart.errors).toContain("Cart must contain at least one item to checkout");
  });
});
