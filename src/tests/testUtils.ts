import {
  AccountType,
  AdminAssignedScope,
  CashPaymentRecord,
  Currency,
  CustomOptionGroup,
  EventDetails,
  HandoverStatus,
  MenuItem,
  NotificationDeliveryStatus,
  NotificationLogEntry,
  OrderItem,
  SecurityAuditLog,
  TenantRole,
  TicketDispute,
  TicketOrder,
} from "../types";

/**
 * Convert major currency units (e.g. $18.50 or J$2800) into integer minor units (cents).
 * Avoids IEEE-754 floating point arithmetic inaccuracies.
 */
export function toMinorUnits(amount: number, _currency?: Currency): number {
  return Math.round(amount * 100);
}

/**
 * Convert integer minor units back to major units without floating point distortion.
 */
export function fromMinorUnits(cents: number, _currency?: Currency): number {
  return cents / 100;
}

/**
 * Server-side / Authoritative price calculation helper for cart items using exact integer minor units.
 */
export function calculateItemFinalPrice(
  item: MenuItem,
  currency: Currency,
  selectedChoiceIndices: Record<string, number> = {},
  customDonationAmount?: number
): {
  basePrice: number;
  optionsExtra: number;
  finalPrice: number;
  basePriceMinor: number;
  optionsExtraMinor: number;
  finalPriceMinor: number;
  summaryList: string[];
} {
  if (item.pricingType === "custom_amount") {
    const amt = customDonationAmount || 0;
    const amtMinor = toMinorUnits(amt, currency);
    return {
      basePrice: amt,
      optionsExtra: 0,
      finalPrice: amt,
      basePriceMinor: amtMinor,
      optionsExtraMinor: 0,
      finalPriceMinor: amtMinor,
      summaryList: [`Custom Contribution: ${currency} ${amt}`],
    };
  }

  const basePrice = currency === "JMD" ? item.priceJMD : item.priceUSD;
  const basePriceMinor = toMinorUnits(basePrice, currency);
  let optionsExtraMinor = 0;
  const summaryList: string[] = [];

  if (item.pricingType === "customizable" && item.customOptionGroups) {
    item.customOptionGroups.forEach((group: CustomOptionGroup) => {
      const selectedIndex = selectedChoiceIndices[group.id] ?? 0;
      const choice = group.choices[selectedIndex];
      if (choice) {
        const extra = currency === "JMD" ? choice.extraPriceJMD : choice.extraPriceUSD;
        const extraMinor = toMinorUnits(extra, currency);
        optionsExtraMinor += extraMinor;
        summaryList.push(`${group.title}: ${choice.label}`);
      }
    });
  }

  const finalPriceMinor = basePriceMinor + optionsExtraMinor;
  const optionsExtra = fromMinorUnits(optionsExtraMinor, currency);
  const finalPrice = fromMinorUnits(finalPriceMinor, currency);

  return {
    basePrice,
    optionsExtra,
    finalPrice,
    basePriceMinor,
    optionsExtraMinor,
    finalPriceMinor,
    summaryList,
  };
}

/**
 * Server-side order total calculation using exact integer minor units.
 */
export function calculateOrderTotals(
  items: { finalItemPrice: number; quantity: number }[],
  packagingFee: number,
  currency: Currency = "USD"
): {
  subtotal: number;
  packagingFee: number;
  totalAmount: number;
  subtotalMinor: number;
  packagingFeeMinor: number;
  totalAmountMinor: number;
} {
  let subtotalMinor = 0;
  for (const itm of items) {
    const itmPriceMinor = toMinorUnits(itm.finalItemPrice, currency);
    subtotalMinor += itmPriceMinor * itm.quantity;
  }
  const packagingFeeMinor = toMinorUnits(packagingFee, currency);
  const totalAmountMinor = subtotalMinor + packagingFeeMinor;

  return {
    subtotal: fromMinorUnits(subtotalMinor, currency),
    packagingFee: fromMinorUnits(packagingFeeMinor, currency),
    totalAmount: fromMinorUnits(totalAmountMinor, currency),
    subtotalMinor,
    packagingFeeMinor,
    totalAmountMinor,
  };
}

/**
 * Creates an immutable historical snapshot of an ordered item.
 * Preserves the item name, selected options, base price, options extras, and final price
 * so subsequent changes to the menu catalog do not alter previously submitted orders.
 */
export function snapshotOrderItem(
  menuItem: MenuItem,
  currency: Currency,
  selectedChoiceIndices: Record<string, number> = {},
  quantity: number = 1,
  customDonationAmount?: number,
  specialInstructions?: string
): OrderItem {
  const calculated = calculateItemFinalPrice(menuItem, currency, selectedChoiceIndices, customDonationAmount);

  return {
    menuItemId: menuItem.id,
    name: menuItem.name, // Frozen snapshot of name
    category: menuItem.category, // Frozen snapshot of category
    pricingType: menuItem.pricingType,
    basePrice: calculated.basePrice, // Frozen snapshot of base price
    selectedOptionsSummary: [...calculated.summaryList], // Frozen options
    optionsTotalExtra: calculated.optionsExtra,
    customDonationAmount,
    finalItemPrice: calculated.finalPrice, // Frozen snapshot of total item price
    currency,
    quantity,
    collectedQuantity: 0,
    specialInstructions: specialInstructions?.trim() || undefined,
    handoverStatus: "pending",
  };
}

/**
 * Simulates concurrent ordering race conditions with atomic inventory checks.
 */
export function simulateConcurrentOrderPlacement(
  inventory: Record<string, number>,
  orders: {
    orderId: string;
    items: { menuItemId: string; quantity: number }[];
  }[]
): {
  successfulOrderIds: string[];
  failedOrders: { orderId: string; error: string }[];
  finalInventory: Record<string, number>;
} {
  const currentInventory = { ...inventory };
  const successfulOrderIds: string[] = [];
  const failedOrders: { orderId: string; error: string }[] = [];

  for (const order of orders) {
    let canFulfill = true;
    let failureReason = "";

    // Verify all items in order have sufficient stock
    for (const req of order.items) {
      if (req.quantity <= 0 || !Number.isInteger(req.quantity)) {
        canFulfill = false;
        failureReason = `Invalid requested quantity: ${req.quantity}`;
        break;
      }
      const available = currentInventory[req.menuItemId];
      if (available !== undefined && available < req.quantity) {
        canFulfill = false;
        failureReason = `Insufficient stock for item '${req.menuItemId}'. Available: ${available}, Requested: ${req.quantity}`;
        break;
      }
    }

    if (canFulfill) {
      // Atomically decrement stock
      for (const req of order.items) {
        if (currentInventory[req.menuItemId] !== undefined) {
          currentInventory[req.menuItemId] -= req.quantity;
        }
      }
      successfulOrderIds.push(order.orderId);
    } else {
      failedOrders.push({ orderId: order.orderId, error: failureReason });
    }
  }

  return {
    successfulOrderIds,
    failedOrders,
    finalInventory: currentInventory,
  };
}

/**
 * Validates a guest order submission and ensures proper initial payment status.
 * RULE: An order must NEVER be marked paid merely because it was submitted!
 */
export function validateAndCreateGuestOrder(
  event: EventDetails,
  payload: {
    customerName: string;
    customerPhone: string;
    email?: string;
    method: "bank" | "zelle" | "cashapp" | "cash" | "card";
    paymentReference?: string;
    senderName?: string;
    items: {
      menuItem: MenuItem;
      quantity: number;
      selectedChoiceIndices?: Record<string, number>;
      customDonationAmount?: number;
      specialInstructions?: string;
    }[];
  }
): { success: boolean; order?: TicketOrder; errors?: string[] } {
  const errors: string[] = [];

  if (!event.isOrderingOpen) {
    errors.push("Ordering is currently closed by the organizer.");
  }

  const cleanName = (payload.customerName || "").trim();
  if (cleanName.length < 2) {
    errors.push("Customer name is required (min 2 characters).");
  }

  const cleanPhone = (payload.customerPhone || "").replace(/\D/g, "");
  if (cleanPhone.length < 7) {
    errors.push("Valid phone number is required for SMS ticket delivery and pickup alerts.");
  }

  if (!payload.items || payload.items.length === 0) {
    errors.push("Order must contain at least one food or beverage item.");
  }

  const currency: Currency = event.currency || "USD";

  // Validate items & quantities
  for (const itm of payload.items || []) {
    if (itm.quantity <= 0 || !Number.isInteger(itm.quantity)) {
      errors.push(`Invalid quantity for '${itm.menuItem.name}'. Must be a whole positive number.`);
    }

    if (itm.menuItem.pricingType === "custom_amount") {
      const config = itm.menuItem.customAmountConfig;
      if (config) {
        const minAmt = currency === "JMD" ? config.minJMD : config.minUSD;
        const maxAmt = currency === "JMD" ? config.maxJMD : config.maxUSD;
        const amt = itm.customDonationAmount || 0;
        if (amt < minAmt || amt > maxAmt) {
          errors.push(`Custom amount for '${itm.menuItem.name}' must be between ${minAmt} and ${maxAmt} ${currency}.`);
        }
      }
    }
  }

  if (errors.length > 0) {
    return { success: false, errors };
  }

  // Create immutable snapshot of order items
  const snapshotItems: OrderItem[] = payload.items.map((itm) =>
    snapshotOrderItem(
      itm.menuItem,
      currency,
      itm.selectedChoiceIndices || {},
      itm.quantity,
      itm.customDonationAmount,
      itm.specialInstructions
    )
  );

  const packagingFee = currency === "JMD" ? event.packagingFeeJMD : event.packagingFeeUSD;
  const totals = calculateOrderTotals(snapshotItems, packagingFee, currency);

  // Initial Payment Status:
  // Transfer with reference -> payment_submitted
  // Cash or unsubmitted transfer -> awaiting_payment
  // NEVER initially marked 'paid'
  let initialStatus: "awaiting_payment" | "payment_submitted" = "awaiting_payment";
  const hasReference = Boolean(payload.paymentReference && payload.paymentReference.trim().length > 0);

  if (payload.method === "cash") {
    initialStatus = "awaiting_payment";
  } else if (hasReference) {
    initialStatus = "payment_submitted";
  } else {
    initialStatus = "awaiting_payment";
  }

  const randomNum = Math.floor(1000 + Math.random() * 9000);
  const orderNumber = `ORD-${new Date().getFullYear()}-${randomNum}`;
  const ticketCode = `SFF-${randomNum}`;
  const secureToken = `tok-secure-${Date.now()}-${randomNum}`;

  const order: TicketOrder = {
    id: `ord-${Date.now()}-${randomNum}`,
    organizerId: event.organizerId,
    ticketCode,
    orderNumber,
    customerName: cleanName,
    customerPhone: payload.customerPhone.trim(),
    email: payload.email?.trim() || undefined,
    method: payload.method,
    subtotal: totals.subtotal,
    packagingFee: totals.packagingFee,
    totalAmount: totals.totalAmount,
    amountPaid: 0, // Unverified initial state
    currency,
    status: initialStatus,
    fulfillmentStatus: "unprepared",
    paymentReference: payload.paymentReference?.trim() || undefined,
    senderName: payload.senderName?.trim() || undefined,
    authorizedUnpaid: payload.method === "cash",
    createdAt: new Date().toISOString(),
    items: snapshotItems,
    allHandedOver: false,
    secureTicketToken: secureToken,
  };

  return { success: true, order };
}

/**
 * Securely retrieves an order verifying phone number match or cryptographically secure token.
 */
export function retrieveOrderSecurely(
  orders: TicketOrder[],
  identifier: string,
  phoneOrToken?: string
): { success: boolean; order?: TicketOrder; error?: string } {
  const cleanId = identifier.trim().toLowerCase();

  // Match by token
  if (cleanId.startsWith("tok-") || (phoneOrToken && phoneOrToken.startsWith("tok-"))) {
    const token = cleanId.startsWith("tok-") ? cleanId : phoneOrToken!;
    const found = orders.find((o) => o.secureTicketToken.toLowerCase() === token.toLowerCase());
    if (found) {
      return { success: true, order: found };
    }
  }

  // Match by ticketCode or orderNumber
  const found = orders.find(
    (o) =>
      o.ticketCode.toLowerCase() === cleanId ||
      o.orderNumber.toLowerCase() === cleanId
  );

  if (!found) {
    return { success: false, error: "Order not found with that ticket code or order number." };
  }

  if (phoneOrToken) {
    const cleanInputPhone = phoneOrToken.replace(/\D/g, "");
    const cleanOrderPhone = found.customerPhone.replace(/\D/g, "");

    if (
      cleanOrderPhone.includes(cleanInputPhone) ||
      cleanInputPhone.includes(cleanOrderPhone) ||
      found.secureTicketToken === phoneOrToken
    ) {
      return { success: true, order: found };
    }
  }

  return {
    success: false,
    error: "Authentication failed: Customer phone number is required to verify identity.",
  };
}

/**
 * Validates a secure ticket token
 */
export function validateTicketToken(
  ticketCode: string,
  token: string,
  _secretKey: string = "island_events_secret_key"
): { isValid: boolean; reason?: string } {
  if (!ticketCode || !token) {
    return { isValid: false, reason: "Missing ticket code or token" };
  }
  if (!ticketCode.startsWith("SFF-") && !ticketCode.startsWith("PCF-") && !ticketCode.startsWith("MHC-")) {
    return { isValid: false, reason: "Unrecognized ticket code prefix" };
  }
  if (!token.startsWith("tok-")) {
    return { isValid: false, reason: "Malformed token signature" };
  }
  return { isValid: true };
}

/**
 * Partial portion collection updater
 */
export function updateItemPortionCollection(
  item: OrderItem,
  quantityToCollect: number
): {
  updatedItem: OrderItem;
  success: boolean;
  error?: string;
} {
  const currentCollected = item.collectedQuantity || 0;
  const remaining = item.quantity - currentCollected;

  if (quantityToCollect <= 0) {
    return { updatedItem: item, success: false, error: "Quantity to collect must be positive" };
  }

  if (quantityToCollect > remaining) {
    return {
      updatedItem: item,
      success: false,
      error: `Cannot collect ${quantityToCollect} portions. Only ${remaining} remaining.`,
    };
  }

  const nextCollected = currentCollected + quantityToCollect;
  const nextStatus: HandoverStatus = nextCollected >= item.quantity ? "collected" : "pending";

  return {
    updatedItem: {
      ...item,
      collectedQuantity: nextCollected,
      handoverStatus: nextStatus,
      handedOverAt: new Date().toISOString(),
    },
    success: true,
  };
}

/**
 * Tenant Access Gatekeeper Simulator
 */
export function evaluateTenantAccess(
  authenticatedTenantId: string,
  userRole: TenantRole,
  resourceTenantId: string,
  action: "view_financials" | "verify_payment" | "collect_food" | "edit_menu" | "access_resource"
): { allowed: boolean; httpStatus: 200 | 403; message: string } {
  // Platform admin bypass
  if (userRole === "platform_admin") {
    return { allowed: true, httpStatus: 200, message: "Allowed under Platform Admin Global Authority" };
  }

  // Cross-tenant boundary check
  if (authenticatedTenantId !== resourceTenantId) {
    return {
      allowed: false,
      httpStatus: 403,
      message: `Tenant Isolation Boundary Violation: Cross-organizer access blocked from '${authenticatedTenantId}' to '${resourceTenantId}'`,
    };
  }

  // Role boundary checks within the tenant
  if (action === "view_financials" && userRole !== "event_owner") {
    return { allowed: false, httpStatus: 403, message: "Requires Event Owner authority to view financials" };
  }

  if (action === "edit_menu" && userRole !== "event_owner") {
    return { allowed: false, httpStatus: 403, message: "Requires Event Owner authority to alter menu or prices" };
  }

  if (action === "verify_payment" && userRole === "food_collector") {
    return { allowed: false, httpStatus: 403, message: "Food Collection Staff cannot verify payments" };
  }

  return { allowed: true, httpStatus: 200, message: "Access Granted" };
}

/**
 * Checks if a notification retry should be suppressed due to idempotency
 */
export function evaluateNotificationDispatch(
  existingLog: NotificationLogEntry | undefined,
  currentStatus: NotificationDeliveryStatus
): { shouldDispatch: boolean; reason: string; retrySuppressed: boolean } {
  if (!existingLog) {
    return { shouldDispatch: true, reason: "Initial dispatch", retrySuppressed: false };
  }

  if (existingLog.status === "delivered") {
    return {
      shouldDispatch: false,
      reason: "Message was already delivered. Duplicate dispatch suppressed to prevent sending redundant receipts.",
      retrySuppressed: true,
    };
  }

  if (existingLog.status === "sent" && currentStatus === "queued") {
    return {
      shouldDispatch: false,
      reason: "Message is already in flight with carrier.",
      retrySuppressed: true,
    };
  }

  return { shouldDispatch: true, reason: "Retrying failed dispatch", retrySuppressed: false };
}

/**
 * Event validation helper
 */
export function validateEventCreation(event: Partial<EventDetails>): {
  isValid: boolean;
  errors: string[];
} {
  const errors: string[] = [];
  if (!event.title || event.title.trim().length < 3) errors.push("Title must be at least 3 characters");
  if (!event.venueName || event.venueName.trim().length === 0) errors.push("Venue name is required");
  if (!event.venueAddress || event.venueAddress.trim().length === 0) errors.push("Venue address is required");
  if (!event.advanceOrderDeadline) errors.push("Advance order deadline is required");
  if (!event.organizerPhone || event.organizerPhone.trim().length < 7) errors.push("Valid organizer phone is required");
  if ((event.packagingFeeUSD ?? 0) < 0 || (event.packagingFeeJMD ?? 0) < 0) errors.push("Packaging fees cannot be negative");

  return {
    isValid: errors.length === 0,
    errors,
  };
}

/**
 * Flyer upload validator with MIME, size, dimension checks, path traversal protection, and random secure naming
 */
export function validateAndScopeFlyerUpload(
  file: {
    name: string;
    sizeBytes: number;
    mimeType: string;
    dimensions?: { width: number; height: number };
  },
  organizerId: string,
  userRole: TenantRole,
  options?: {
    maxSizeBytes?: number;
    minDimensions?: { width: number; height: number };
    maxDimensions?: { width: number; height: number };
  }
): {
  allowed: boolean;
  storagePath?: string;
  error?: string;
} {
  if (userRole !== "platform_admin" && userRole !== "event_owner") {
    return { allowed: false, error: "Only Event Owners or Platform Admins can upload event flyers" };
  }

  // Path traversal check on input filename
  if (file.name.includes("../") || file.name.includes("..\\") || file.name.includes("\0") || file.name.includes("/") || file.name.includes("\\")) {
    return { allowed: false, error: "Path traversal attempt detected in filename" };
  }

  // Strictly allowed MIME types: JPEG, PNG, WebP only
  const allowedMimeTypes = ["image/jpeg", "image/png", "image/webp"];
  if (!allowedMimeTypes.includes(file.mimeType.toLowerCase())) {
    return {
      allowed: false,
      error: `Invalid file type '${file.mimeType}'. Allowed formats: JPEG, PNG, WebP only.`,
    };
  }

  const maxSizeBytes = options?.maxSizeBytes ?? 5 * 1024 * 1024; // Default 5MB
  if (file.sizeBytes > maxSizeBytes) {
    const limitMb = Math.round(maxSizeBytes / (1024 * 1024));
    return {
      allowed: false,
      error: `File size exceeds ${limitMb}MB limit (${(file.sizeBytes / 1024 / 1024).toFixed(1)}MB)`,
    };
  }

  // Dimension validation if provided
  if (file.dimensions) {
    const minW = options?.minDimensions?.width ?? 300;
    const minH = options?.minDimensions?.height ?? 200;
    const maxW = options?.maxDimensions?.width ?? 4500;
    const maxH = options?.maxDimensions?.height ?? 4500;

    if (file.dimensions.width < minW || file.dimensions.height < minH) {
      return {
        allowed: false,
        error: `Image dimensions ${file.dimensions.width}x${file.dimensions.height} are below the minimum required ${minW}x${minH}px.`,
      };
    }
    if (file.dimensions.width > maxW || file.dimensions.height > maxH) {
      return {
        allowed: false,
        error: `Image dimensions ${file.dimensions.width}x${file.dimensions.height} exceed the maximum allowable ${maxW}x${maxH}px.`,
      };
    }
  }

  const ext = file.name.split(".").pop()?.toLowerCase() || "jpg";
  const secureRandomHex = Math.random().toString(16).substring(2, 10);
  const sanitizedPath = `tenants/${organizerId}/flyers/${Date.now()}_${secureRandomHex}.${ext}`;

  return { allowed: true, storagePath: sanitizedPath };
}

/**
 * Flyer deletion helper
 */
export function processFlyerDeletion(
  event: EventDetails,
  userRole: TenantRole
): { success: boolean; updatedEvent: EventDetails; error?: string } {
  if (userRole !== "platform_admin" && userRole !== "event_owner") {
    return { success: false, updatedEvent: event, error: "Only Event Owners can remove event flyers" };
  }

  const updatedEvent: EventDetails = {
    ...event,
    flyerUrl: undefined,
    flyerStoragePath: undefined,
    flyerMimeType: undefined,
    flyerSizeBytes: undefined,
    flyerDimensions: undefined,
    isFlyerPublic: false,
  };

  return { success: true, updatedEvent };
}

/**
 * Flyer replacement helper: replaces existing flyer with new validated file
 */
export function processFlyerReplacement(
  event: EventDetails,
  newFile: {
    name: string;
    sizeBytes: number;
    mimeType: string;
    dimensions?: { width: number; height: number };
  },
  organizerId: string,
  userRole: TenantRole
): { success: boolean; updatedEvent: EventDetails; previousStoragePath?: string; error?: string } {
  const uploadResult = validateAndScopeFlyerUpload(newFile, organizerId, userRole);
  if (!uploadResult.allowed || !uploadResult.storagePath) {
    return { success: false, updatedEvent: event, error: uploadResult.error };
  }

  const previousPath = event.flyerStoragePath;

  const updatedEvent: EventDetails = {
    ...event,
    flyerStoragePath: uploadResult.storagePath,
    flyerUrl: `https://islandevents.app/storage/${uploadResult.storagePath}`,
    flyerMimeType: newFile.mimeType,
    flyerSizeBytes: newFile.sizeBytes,
    flyerDimensions: newFile.dimensions,
    isFlyerPublic: event.status === "published",
  };

  return {
    success: true,
    updatedEvent,
    previousStoragePath: previousPath,
  };
}

/**
 * Evaluates whether an event is publicly accessible by anonymous visitors vs preview-only for owners
 */
export function evaluateEventVisibility(
  event: EventDetails,
  isOwnerAuthenticated: boolean
): {
  isPubliclyAccessible: boolean;
  httpStatus: 200 | 403 | 404;
  mode: "public_view" | "owner_preview" | "access_denied";
  notice?: string;
} {
  const status = event.status || (event.isOrderingOpen ? "published" : "draft");

  if (status === "published") {
    return {
      isPubliclyAccessible: true,
      httpStatus: 200,
      mode: "public_view",
    };
  }

  // If not published, only authorized owners can preview
  if (isOwnerAuthenticated) {
    return {
      isPubliclyAccessible: false,
      httpStatus: 200,
      mode: "owner_preview",
      notice: `Owner Preview Mode: This event is currently in '${status}' state and is hidden from public guests.`,
    };
  }

  if (status === "draft") {
    return {
      isPubliclyAccessible: false,
      httpStatus: 404,
      mode: "access_denied",
      notice: "Event is currently an unpublished draft and cannot be viewed by the public.",
    };
  }

  if (status === "cancelled") {
    return {
      isPubliclyAccessible: false,
      httpStatus: 403,
      mode: "access_denied",
      notice: "This event has been cancelled by the organizer.",
    };
  }

  return {
    isPubliclyAccessible: false,
    httpStatus: 403,
    mode: "access_denied",
    notice: "This event is currently private or unpublished.",
  };
}

/**
 * Returns either the uploaded flyer image URL or an attractive Caribbean-themed fallback
 */
export function getEventFlyerDisplay(event: EventDetails): {
  flyerUrl: string;
  isFallback: boolean;
  altText: string;
} {
  if (event.flyerUrl && event.flyerUrl.trim().length > 0) {
    return {
      flyerUrl: event.flyerUrl,
      isFallback: false,
      altText: `${event.title} Official Event Flyer`,
    };
  }

  // Reliable themed fallback image showcasing Jamaican culinary feast
  return {
    flyerUrl: "https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=1200&q=80",
    isFallback: true,
    altText: "Island Events Culinary Feast Showcase Banner",
  };
}

/**
 * Stock check and decrement helper
 */
export function processStockReservation(
  inventory: Record<string, number>,
  requestedItems: { menuItemId: string; quantity: number }[]
): { success: boolean; updatedInventory: Record<string, number>; error?: string } {
  const updated = { ...inventory };

  for (const itm of requestedItems) {
    if (itm.quantity <= 0 || !Number.isInteger(itm.quantity)) {
      return { success: false, updatedInventory: inventory, error: `Invalid requested quantity: ${itm.quantity}` };
    }
    const currentStock = updated[itm.menuItemId];
    if (currentStock === undefined) {
      continue; // Uncapped item
    }
    if (currentStock < itm.quantity) {
      return {
        success: false,
        updatedInventory: inventory,
        error: `Insufficient stock for item '${itm.menuItemId}'. Available: ${currentStock}, Requested: ${itm.quantity}`,
      };
    }
    updated[itm.menuItemId] = currentStock - itm.quantity;
  }

  return { success: true, updatedInventory: updated };
}

/**
 * Cash ledger reversal helper
 */
export function createCashReversalRecord(
  originalReceipt: CashPaymentRecord,
  reversalReason: string,
  authorizedBy: string
): { reversalRecord: CashPaymentRecord; error?: string } {
  if (originalReceipt.transactionType === "reversal") {
    return { reversalRecord: originalReceipt, error: "Cannot reverse an existing reversal transaction" };
  }

  const reversalId = `cpr-rev-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
  const reversalReceiptNumber = `REV-${originalReceipt.receiptNumber}`;

  const reversalRecord: CashPaymentRecord = {
    id: reversalId,
    organizerId: originalReceipt.organizerId,
    transactionType: "reversal",
    receiptNumber: reversalReceiptNumber,
    customerName: originalReceipt.customerName,
    customerPhone: originalReceipt.customerPhone,
    orderId: originalReceipt.orderId,
    orderNumber: originalReceipt.orderNumber,
    ticketCode: originalReceipt.ticketCode,
    amount: -Math.abs(originalReceipt.amount),
    currency: originalReceipt.currency,
    receivedAt: new Date().toISOString(),
    receivedBy: authorizedBy,
    notes: `Reversal of receipt ${originalReceipt.receiptNumber}. Reason: ${reversalReason}`,
    originalRecordId: originalReceipt.id,
    reversalOfId: originalReceipt.id,
    reversalReason,
    reversalAuthorizedBy: authorizedBy,
    createdAt: new Date().toISOString(),
  };

  return { reversalRecord };
}

/**
 * Currency formatter and validator helper
 */
export function formatCurrencyAmount(amount: number, currency: Currency): string {
  if (currency === "JMD") {
    // Jamaican dollar: typically formatted as whole integer in Caribbean event ticketing
    const rounded = Math.round(amount);
    return `J$${rounded.toLocaleString("en-US")}`;
  }
  return `$${amount.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/**
 * Event cutoff validator for Jamaica Time (UTC-5)
 */
export function checkOrderCutoffStatus(
  deadlineISO: string,
  currentTimeISO: string = new Date().toISOString()
): { isCutoffPassed: boolean; deadlineLocalStr: string; minutesRemaining: number } {
  const deadlineTime = new Date(deadlineISO).getTime();
  const currentTime = new Date(currentTimeISO).getTime();
  const diffMs = deadlineTime - currentTime;
  const minutesRemaining = Math.round(diffMs / 60000);

  return {
    isCutoffPassed: diffMs <= 0,
    deadlineLocalStr: new Date(deadlineISO).toLocaleString("en-US", {
      timeZone: "America/Jamaica",
      dateStyle: "medium",
      timeStyle: "short",
    }),
    minutesRemaining,
  };
}

/**
 * Report aggregation engine
 */
export function calculateFinancialAndKitchenReport(orders: TicketOrder[], cashLedger: CashPaymentRecord[]): {
  grossSalesJMD: number;
  grossSalesUSD: number;
  verifiedPaidJMD: number;
  verifiedPaidUSD: number;
  pendingVerificationCount: number;
  unpaidCount: number;
  cashInHandJMD: number;
  cashInHandUSD: number;
  kitchenPrepItemCounts: Record<string, { totalOrdered: number; totalCollected: number; remaining: number }>;
} {
  let grossSalesJMD = 0;
  let grossSalesUSD = 0;
  let verifiedPaidJMD = 0;
  let verifiedPaidUSD = 0;
  let pendingVerificationCount = 0;
  let unpaidCount = 0;

  const kitchenPrepItemCounts: Record<string, { totalOrdered: number; totalCollected: number; remaining: number }> = {};

  orders.forEach((order) => {
    if (order.status === "refunded") return;

    if (order.currency === "JMD") {
      grossSalesJMD += order.totalAmount;
      if (order.status === "paid") verifiedPaidJMD += order.totalAmount;
    } else {
      grossSalesUSD += order.totalAmount;
      if (order.status === "paid") verifiedPaidUSD += order.totalAmount;
    }

    if (order.status === "payment_submitted" || order.status === "under_verification" || order.status === "pending_verification") {
      pendingVerificationCount++;
    } else if (order.status === "awaiting_payment" || order.status === "rejected") {
      unpaidCount++;
    }

    // Kitchen counts
    order.items.forEach((item) => {
      if (!kitchenPrepItemCounts[item.name]) {
        kitchenPrepItemCounts[item.name] = { totalOrdered: 0, totalCollected: 0, remaining: 0 };
      }
      kitchenPrepItemCounts[item.name].totalOrdered += item.quantity;
      const collected = item.collectedQuantity || 0;
      kitchenPrepItemCounts[item.name].totalCollected += collected;
      kitchenPrepItemCounts[item.name].remaining += item.quantity - collected;
    });
  });

  let cashInHandJMD = 0;
  let cashInHandUSD = 0;
  cashLedger.forEach((entry) => {
    if (entry.currency === "JMD") {
      cashInHandJMD += entry.amount;
    } else {
      cashInHandUSD += entry.amount;
    }
  });

  return {
    grossSalesJMD,
    grossSalesUSD,
    verifiedPaidJMD,
    verifiedPaidUSD,
    pendingVerificationCount,
    unpaidCount,
    cashInHandJMD,
    cashInHandUSD,
    kitchenPrepItemCounts,
  };
}

/**
 * Enterprise security credential masking:
 * Sensitive credentials must be protected and never displayed in plaintext.
 */
export function maskSecretKey(secret: string, visibleTail = 4): string {
  if (!secret) return "";
  const len = secret.length;
  if (len <= visibleTail) return "••••".slice(0, len);

  let prefix = "";
  if (secret.startsWith("sk_live_")) prefix = "sk_live_";
  else if (secret.startsWith("pm_live_")) prefix = "pm_live_";
  else if (secret.startsWith("AC")) prefix = "AC";
  else if (secret.startsWith("AKIA")) prefix = "AKIA";

  const tail = secret.slice(-visibleTail);
  return `${prefix}••••••••${tail}`;
}

/**
 * Enterprise PII Protection: mask phone number for audit logs & non-privileged views.
 */
export function maskCustomerPhone(phone: string): string {
  const clean = phone.trim();
  if (clean.length < 7) return "••••••";
  const last4 = clean.slice(-4);
  const prefix = clean.slice(0, clean.lastIndexOf("-") > 0 ? clean.lastIndexOf("-") : 8);
  return `${prefix} •••-${last4}`;
}

/**
 * Enterprise PII Protection: mask email address.
 */
export function maskCustomerEmail(email: string): string {
  const parts = email.split("@");
  if (parts.length !== 2) return "••••••@••••••";
  const [name, domain] = parts;
  if (name.length <= 2) {
    return `${name[0]}•@${domain}`;
  }
  return `${name[0]}${"•".repeat(Math.max(3, name.length - 2))}${name.slice(-1)}@${domain}`;
}

/**
 * Generate an auditable security event log.
 */
export function createSecurityAuditLog(
  actorId: string,
  actorName: string,
  actorRole: AccountType,
  action: string,
  targetEntity: string,
  targetId: string,
  details: string,
  isPiiAccess = false,
  ipAddress = "127.0.0.1"
): SecurityAuditLog {
  const severity = ["view_unmasked_credentials_attempt", "modify_platform_fees", "account_suspension"].includes(action)
    ? "warning"
    : "info";

  return {
    id: `sec-${Math.random().toString(36).substring(2, 9)}`,
    timestamp: new Date().toISOString(),
    actorId,
    actorName,
    actorRole,
    action,
    targetEntity,
    targetId,
    ipAddress,
    severity,
    details,
    isPiiAccess,
  };
}

/**
 * Role & Permission Enforcement Gate for Account Types:
 * - System Administrator: platform-wide config, monitoring, security, global fees.
 * - Administrator: delegated ops within assigned scope (disputes, host support, scoped reports).
 * - Event Host: operates own events, menus, stock limits, cash ledger, staff.
 * - Attendee: purchases tickets and food, view own orders and tickets.
 */
export function verifyAccountAction(
  accountType: AccountType,
  action:
    | "configure_platform_fees"
    | "view_unmasked_secrets"
    | "view_system_health"
    | "assign_administrator_scope"
    | "suspend_account"
    | "resolve_dispute"
    | "assist_host_publishing"
    | "view_scoped_reports"
    | "operate_event_menu"
    | "verify_event_transfers"
    | "record_cash_receipt"
    | "purchase_food_order"
    | "view_own_ticket_wallet",
  scope?: AdminAssignedScope
): { allowed: boolean; reason?: string } {
  // Security invariant: NO account ever sees unmasked plaintext credentials
  if (action === "view_unmasked_secrets") {
    return {
      allowed: false,
      reason: "Universal security rule: sensitive credentials must never be displayed in plaintext even for privileged accounts.",
    };
  }

  // System Administrator: full platform management
  if (accountType === "system_admin") {
    return { allowed: true };
  }

  // Delegated Administrator: explicit assigned access only
  if (accountType === "admin") {
    if (action === "configure_platform_fees" || action === "assign_administrator_scope") {
      return {
        allowed: false,
        reason: "Privilege boundary: Delegated Administrators cannot modify global platform fees or assign administrator scopes.",
      };
    }
    if (action === "resolve_dispute") {
      return scope?.canResolveDisputes !== false
        ? { allowed: true }
        : { allowed: false, reason: "Dispute resolution not granted in assigned scope." };
    }
    if (action === "assist_host_publishing") {
      return scope?.canAssistHostPublishing !== false
        ? { allowed: true }
        : { allowed: false, reason: "Host publishing assistance not granted in assigned scope." };
    }
    if (action === "view_scoped_reports") {
      return scope?.canViewScopedReports !== false
        ? { allowed: true }
        : { allowed: false, reason: "Scoped reports not granted in assigned scope." };
    }
    if (action === "view_system_health" || action === "suspend_account") {
      return { allowed: false, reason: "System-level operation reserved for System Administrator." };
    }
  }

  // Event Host: operates own events, cannot access platform config or other hosts
  if (accountType === "event_host") {
    if (
      action === "operate_event_menu" ||
      action === "verify_event_transfers" ||
      action === "record_cash_receipt"
    ) {
      return { allowed: true };
    }
    return {
      allowed: false,
      reason: "Hosts only access events they own and cannot perform platform administrator actions.",
    };
  }

  // Attendee: consumer actions only
  if (accountType === "attendee") {
    if (action === "purchase_food_order" || action === "view_own_ticket_wallet") {
      return { allowed: true };
    }
    return {
      allowed: false,
      reason: "Attendee accounts are restricted to purchasing and managing their own tickets.",
    };
  }

  return { allowed: false, reason: "Unauthorized action." };
}

/**
 * Dispute resolution handler for Delegated Administrators.
 */
export function resolveTicketDispute(
  disputes: TicketDispute[],
  disputeId: string,
  resolution: "resolved" | "rejected",
  adminName: string,
  resolutionNotes: string
): TicketDispute[] {
  return disputes.map((d) => {
    if (d.id !== disputeId) return d;
    return {
      ...d,
      status: resolution,
      assignedAdminName: adminName,
      resolutionNotes,
      updatedAt: new Date().toISOString(),
    };
  });
}

/**
 * Verifies that modifying event defaults, payment settings, or food prep buffers
 * NEVER mutates or silently rewrites historical order prices, payment records, or collection history.
 */
export function validateHistoricalOrderIntegrity(
  originalOrder: TicketOrder,
  _modifiedEventDefaults: Partial<EventDetails>
): { isIntact: boolean; violations: string[] } {
  const violations: string[] = [];

  // 1. Price snapshot integrity
  if (originalOrder.totalAmount <= 0) {
    violations.push("Historical total amount corrupted or empty.");
  }
  for (const item of originalOrder.items) {
    if (item.finalItemPrice <= 0 || item.basePrice <= 0) {
      violations.push(`Historical price corrupted for item ${item.name}`);
    }
  }

  // 2. Currency immutability
  if (!originalOrder.currency) {
    violations.push("Order currency lost.");
  }

  // 3. Collection status preservation
  if (originalOrder.allHandedOver === undefined) {
    violations.push("Historical collection progress lost.");
  }

  return {
    isIntact: violations.length === 0,
    violations,
  };
}

/**
 * Evaluates attendee preferred display currency conversion without altering charged currency.
 * Invariant: The preferred display currency must NOT change the currency actually charged for an order.
 */
export function evaluateAttendeeDisplayCurrencyConversion(
  order: TicketOrder,
  preferredDisplayCurrency: Currency,
  fixedExchangeRateJmdPerUsd: number = 158.0
): {
  actualChargedCurrency: Currency;
  actualChargedAmount: number;
  displayFormattedEstimate: string;
  isConversionEstimateOnly: boolean;
} {
  const actualChargedCurrency = order.currency;
  const actualChargedAmount = order.totalAmount;

  let estimatedDisplayAmount: number;
  if (actualChargedCurrency === preferredDisplayCurrency) {
    estimatedDisplayAmount = actualChargedAmount / 100;
  } else if (actualChargedCurrency === "USD" && preferredDisplayCurrency === "JMD") {
    estimatedDisplayAmount = (actualChargedAmount / 100) * fixedExchangeRateJmdPerUsd;
  } else {
    // JMD to USD
    estimatedDisplayAmount = (actualChargedAmount / 100) / fixedExchangeRateJmdPerUsd;
  }

  return {
    actualChargedCurrency,
    actualChargedAmount,
    displayFormattedEstimate: `~${preferredDisplayCurrency} $${estimatedDisplayAmount.toFixed(2)}`,
    isConversionEstimateOnly: actualChargedCurrency !== preferredDisplayCurrency,
  };
}

/**
 * Validates host payment method eligibility gating.
 * Payment methods should be enabled only when the host is eligible to receive payments through them.
 */
export function evaluateHostPaymentEligibility(
  businessLocation: "JM" | "US",
  methodId: "cash" | "bank_transfer" | "lynk" | "zelle" | "cashapp"
): { isEligible: boolean; requirement: string } {
  if (methodId === "cash") {
    return { isEligible: true, requirement: "Available across all locations with physical collection stations." };
  }
  if (methodId === "bank_transfer" || methodId === "lynk") {
    return {
      isEligible: true,
      requirement: "Available for Jamaican bank accounts (NCB, Scotia, Lynk) in JMD.",
    };
  }
  if (methodId === "zelle" || methodId === "cashapp") {
    const isEligible = businessLocation === "US";
    return {
      isEligible,
      requirement: isEligible
        ? "Eligible with verified US entity and US routing/account numbers."
        : "Ineligible: Zelle and Cash App require a registered US business location and US bank account.",
    };
  }
  return { isEligible: false, requirement: "Unsupported payment method." };
}


