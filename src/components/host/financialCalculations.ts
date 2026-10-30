import {
  CashPaymentRecord,
  Currency,
  EventDetails,
  MenuItem,
  PaymentMethodKey,
  TenantRole,
  TicketOrder,
} from "../../types";
import { FishFryEventItem, TEN_FISH_FRY_EVENTS } from "../../data/fishFryEvents";
import {
  HostCheckInStats,
  HostFinancialBreakdown,
  HostPopularMenuItemRow,
  HostRolePermissions,
  HostSalesAnalyticsBucket,
  HostSalesAnalyticsPeriod,
  HostSalesAnalyticsSummary,
  HostSummaryMetricCardSpec,
  HostTicketSalesStats,
  HostUpcomingEventCardData,
  PaymentMethodFinancialRow,
} from "./types";

const HIGH_QUALITY_EVENT_THUMBNAILS: Record<string, string> = {
  "evt-kingston-fishfry-2026": "/images/escovitch_fish.jpg",
  "evt-kingston-harbour-fry": "/images/escovitch_fish.jpg",
  "evt-hellshire-sunset-fry": "/images/island_festival_sunset.jpg",
  "evt-port-royal-heritage-fry": "/images/island_culture_carnival.jpg",
  "evt-ochorios-fishermans-fry": "/images/island_music_reggae_concert.jpg",
  "evt-negril-7mile-catch": "/images/jerk_chicken.jpg",
  "evt-mobay-pier1-fiesta": "/images/caribbean_curry_goat.jpg",
  "evt-treasure-beach-calabash": "/images/fried_chicken.jpg",
  "evt-boston-bay-jerk-fish": "/images/crispy_island_wings.jpg",
  "evt-whitehouse-cooperative": "/images/oxtail.jpg",
  "evt-runaway-bay-carnival": "/images/rum_punch.jpg",
  "evt-us-miami-fishfry-2026": "/images/island_festival_sunset.jpg",
};

export function resolveEventThumbnail(event: { id?: string; flyerUrl?: string }): string {
  if (event.flyerUrl) {
    // Prefer local high-res asset if the flyerUrl is an external placeholder that might fail offline
    if (event.flyerUrl.startsWith("data:") || event.flyerUrl.startsWith("/api/") || event.flyerUrl.startsWith("/images/")) {
      return event.flyerUrl;
    }
  }
  if (event.id && HIGH_QUALITY_EVENT_THUMBNAILS[event.id]) {
    return HIGH_QUALITY_EVENT_THUMBNAILS[event.id];
  }
  return "/images/escovitch_fish.jpg";
}

export function computeHostRolePermissions(role: TenantRole): HostRolePermissions {
  const canManageEventsAndMenus = role === "platform_admin" || role === "event_owner";
  const canVerifyPaymentsAndCash =
    role === "platform_admin" || role === "event_owner" || role === "payment_verifier";
  const canManageAttendeesAndFulfillment =
    role === "platform_admin" || role === "event_owner" || role === "food_collector";
  const canManageSettingsAndBroadcast = role === "platform_admin" || role === "event_owner";
  const canManageWorkers = role === "platform_admin" || role === "event_owner";
  const canViewReports =
    role === "platform_admin" || role === "event_owner" || role === "payment_verifier";
  const canViewCrossTenantPlatform = role === "platform_admin";

  const roleLabels: Record<TenantRole, string> = {
    platform_admin: "Platform SuperAdmin",
    event_owner: "Authorized Event Host",
    payment_verifier: "Payment Verifier (Cashier)",
    food_collector: "Food Station Collector",
  };

  const roleColors: Record<TenantRole, string> = {
    platform_admin: "#818CF8",
    event_owner: "#34D399",
    payment_verifier: "#FBBF24",
    food_collector: "#38BDF8",
  };

  return {
    role,
    roleLabel: roleLabels[role] || "Authorized Host",
    roleBadgeColor: roleColors[role] || "#34D399",
    canManageEventsAndMenus,
    canVerifyPaymentsAndCash,
    canManageAttendeesAndFulfillment,
    canManageSettingsAndBroadcast,
    canManageWorkers,
    canViewReports,
    canViewCrossTenantPlatform,
    authorizedTools: {
      dashboard: true,
      events: canManageEventsAndMenus,
      orders: true,
      payments: canVerifyPaymentsAndCash,
      attendees: true,
      food_orders: canManageEventsAndMenus || canManageAttendeesAndFulfillment,
      reports: canViewReports,
      workers: canManageWorkers,
      settings: canManageSettingsAndBroadcast,
    },
  };
}

/**
 * Filters orders relevant to the active host event / currency so multi-currency seed orders
 * from other events do not distort the active event's JMD/USD ledger.
 */
export function filterEventScopedOrders(
  orders: TicketOrder[],
  eventDetails: EventDetails
): TicketOrder[] {
  const sameCurrencyOrders = orders.filter((o) => {
    if (o.eventId && o.eventId !== eventDetails.id) {
      // Only include if currency matches active event currency and no specific mismatch
      return o.currency === eventDetails.currency;
    }
    return o.currency === eventDetails.currency;
  });
  return sameCurrencyOrders.length > 0 ? sameCurrencyOrders : orders;
}

export function computeHostFinancialBreakdown(
  orders: TicketOrder[],
  cashLedger: CashPaymentRecord[],
  currency: Currency = "USD"
): HostFinancialBreakdown {
  const scopedOrders = orders.filter((o) => o.currency === currency);
  const workingOrders = scopedOrders.length > 0 ? scopedOrders : orders;

  const fullPaidOrders = workingOrders.filter((o) => o.status === "paid");
  const partiallyPaidOrders = workingOrders.filter((o) => o.status === "partially_paid");

  const confirmedFullPaidAmount = fullPaidOrders.reduce((sum, o) => sum + o.totalAmount, 0);
  const confirmedPartialDepositsAmount = partiallyPaidOrders.reduce(
    (sum, o) => sum + Math.max(0, o.amountPaid || 0),
    0
  );
  const confirmedPayments = confirmedFullPaidAmount + confirmedPartialDepositsAmount;
  const confirmedOrdersCount = fullPaidOrders.length + partiallyPaidOrders.length;

  const submittedOrders = workingOrders.filter(
    (o) =>
      o.status === "payment_submitted" ||
      o.status === "under_verification" ||
      o.status === "pending_verification"
  );
  const awaitingPaymentOrders = workingOrders.filter((o) => o.status === "awaiting_payment");

  const pendingSubmittedAmount = submittedOrders.reduce((sum, o) => sum + o.totalAmount, 0);
  const pendingAwaitingTransferAmount = awaitingPaymentOrders.reduce(
    (sum, o) => sum + o.totalAmount,
    0
  );
  const pendingPartialBalanceRemaining = partiallyPaidOrders.reduce(
    (sum, o) => sum + Math.max(0, o.totalAmount - (o.amountPaid || 0)),
    0
  );

  const pendingPayments =
    pendingSubmittedAmount + pendingAwaitingTransferAmount + pendingPartialBalanceRemaining;
  const pendingOrdersCount =
    submittedOrders.length + awaitingPaymentOrders.length + partiallyPaidOrders.length;

  const forecastRevenue = confirmedPayments + pendingPayments;
  const activePipelineOrdersCount =
    fullPaidOrders.length +
    partiallyPaidOrders.length +
    submittedOrders.length +
    awaitingPaymentOrders.length;

  const refundedOrders = workingOrders.filter((o) => o.status === "refunded");
  const orderRefundsAmount = refundedOrders.reduce(
    (sum, o) => sum + (o.refundDetails?.amount ?? o.totalAmount),
    0
  );
  const orderRefundsCount = refundedOrders.length;

  const rejectedOrders = workingOrders.filter((o) => o.status === "rejected");
  const rejectedOrdersAmount = rejectedOrders.reduce((sum, o) => sum + o.totalAmount, 0);

  const scopedCash = cashLedger.filter((r) => r.currency === currency);
  const workingCash = scopedCash.length > 0 ? scopedCash : cashLedger;
  const cashReceipts = workingCash.filter((r) => r.transactionType === "receipt");
  const cashReversals = workingCash.filter((r) => r.transactionType === "reversal");

  const cashGrossCollected = cashReceipts.reduce((sum, r) => sum + r.amount, 0);
  const cashReversalsAmount = cashReversals.reduce((sum, r) => sum + Math.abs(r.amount), 0);
  const settledCashInHand = Math.max(0, cashGrossCollected - cashReversalsAmount);

  const refundsTotal = orderRefundsAmount + cashReversalsAmount;

  // Digital & bank confirmed payments (excluding cash orders)
  const digitalConfirmed = workingOrders
    .filter((o) => o.method !== "cash")
    .reduce((sum, o) => {
      if (o.status === "paid") return sum + o.totalAmount;
      if (o.status === "partially_paid") return sum + Math.max(0, o.amountPaid || 0);
      return sum;
    }, 0);

  const digitalRefunds = refundedOrders
    .filter((o) => o.method !== "cash")
    .reduce((sum, o) => sum + (o.refundDetails?.amount ?? o.totalAmount), 0);

  const settledDigitalAndBank = Math.max(0, digitalConfirmed - digitalRefunds);
  const settledFunds = Math.max(0, confirmedPayments - orderRefundsAmount);

  const methodLabels: Record<PaymentMethodKey, string> = {
    bank: "Bank Transfer (NCB / Scotia)",
    card: "Card Gateway (WiPay / Stripe)",
    cash: "In-Person Cash Box Office",
    zelle: "Zelle Transfer",
    cashapp: "Cash App",
  };

  const methodKeys: PaymentMethodKey[] = ["bank", "card", "cash", "zelle", "cashapp"];
  const byMethod: PaymentMethodFinancialRow[] = methodKeys.map((method) => {
    const mOrders = workingOrders.filter((o) => o.method === method);
    const mConfirmed = mOrders.reduce((sum, o) => {
      if (o.status === "paid") return sum + o.totalAmount;
      if (o.status === "partially_paid") return sum + Math.max(0, o.amountPaid || 0);
      return sum;
    }, 0);
    const mConfirmedCount = mOrders.filter(
      (o) => o.status === "paid" || o.status === "partially_paid"
    ).length;
    const mPending = mOrders.reduce((sum, o) => {
      if (
        o.status === "payment_submitted" ||
        o.status === "under_verification" ||
        o.status === "pending_verification" ||
        o.status === "awaiting_payment"
      ) {
        return sum + o.totalAmount;
      }
      if (o.status === "partially_paid") {
        return sum + Math.max(0, o.totalAmount - (o.amountPaid || 0));
      }
      return sum;
    }, 0);
    const mPendingCount = mOrders.filter(
      (o) =>
        o.status === "payment_submitted" ||
        o.status === "under_verification" ||
        o.status === "pending_verification" ||
        o.status === "awaiting_payment" ||
        o.status === "partially_paid"
    ).length;
    const mRefunded = mOrders
      .filter((o) => o.status === "refunded")
      .reduce((sum, o) => sum + (o.refundDetails?.amount ?? o.totalAmount), 0);

    return {
      method,
      label: methodLabels[method],
      confirmedAmount: mConfirmed,
      confirmedCount: mConfirmedCount,
      pendingAmount: mPending,
      pendingCount: mPendingCount,
      refundedAmount: mRefunded,
    };
  });

  return {
    currency,
    confirmedPayments,
    confirmedFullPaidAmount,
    confirmedPartialDepositsAmount,
    confirmedOrdersCount,
    fullPaidOrdersCount: fullPaidOrders.length,
    partiallyPaidOrdersCount: partiallyPaidOrders.length,
    pendingPayments,
    pendingSubmittedAmount,
    pendingAwaitingTransferAmount,
    pendingPartialBalanceRemaining,
    pendingOrdersCount,
    pendingVerificationQueueCount: submittedOrders.length,
    awaitingPaymentOrdersCount: awaitingPaymentOrders.length,
    forecastRevenue,
    activePipelineOrdersCount,
    refundsTotal,
    orderRefundsAmount,
    orderRefundsCount,
    cashReversalsAmount,
    cashReversalsCount: cashReversals.length,
    rejectedOrdersCount: rejectedOrders.length,
    rejectedOrdersAmount,
    settledFunds,
    settledDigitalAndBank,
    settledCashInHand,
    cashGrossCollected,
    cashReceiptsCount: cashReceipts.length,
    byMethod,
  };
}

export function computeHostTicketSalesStats(
  orders: TicketOrder[],
  currency: Currency = "USD"
): HostTicketSalesStats {
  const scopedOrders = orders.filter((o) => o.currency === currency);
  const workingOrders = scopedOrders.length > 0 ? scopedOrders : orders;

  const confirmedOrders = workingOrders.filter(
    (o) => o.status === "paid" || o.status === "partially_paid"
  );
  const pendingOrders = workingOrders.filter(
    (o) =>
      o.status === "payment_submitted" ||
      o.status === "under_verification" ||
      o.status === "pending_verification" ||
      o.status === "awaiting_payment"
  );

  const countOrderPortions = (list: TicketOrder[]) =>
    list.reduce(
      (sum, o) => sum + o.items.reduce((itemSum, item) => itemSum + item.quantity, 0),
      0
    );

  const confirmedTicketsCount = confirmedOrders.length;
  const confirmedPortionsCount = countOrderPortions(confirmedOrders);
  const reservedPendingTicketsCount = pendingOrders.length;
  const reservedPendingPortionsCount = countOrderPortions(pendingOrders);

  const totalActiveOrdersCount = confirmedTicketsCount + reservedPendingTicketsCount;
  const totalActivePortionsCount = confirmedPortionsCount + reservedPendingPortionsCount;

  const confirmedClearanceRatePercent =
    totalActiveOrdersCount > 0
      ? Math.round((confirmedTicketsCount / totalActiveOrdersCount) * 100)
      : null;

  return {
    confirmedTicketsCount,
    confirmedPortionsCount,
    reservedPendingTicketsCount,
    reservedPendingPortionsCount,
    totalActiveOrdersCount,
    totalActivePortionsCount,
    confirmedClearanceRatePercent,
  };
}

export function computeHostCheckInStats(
  orders: TicketOrder[],
  currency: Currency = "USD"
): HostCheckInStats {
  const scopedOrders = orders.filter((o) => o.currency === currency);
  const workingOrders = scopedOrders.length > 0 ? scopedOrders : orders;

  const confirmedEligibleOrders = workingOrders.filter(
    (o) => o.status === "paid" || o.status === "partially_paid"
  );

  let fullyCollectedOrdersCount = 0;
  let partiallyCollectedOrdersCount = 0;
  let readyForPickupOrdersCount = 0;
  let preparingOrdersCount = 0;
  let totalConfirmedPortions = 0;
  let collectedPortions = 0;

  for (const order of confirmedEligibleOrders) {
    let orderTotalQty = 0;
    let orderCollectedQty = 0;

    for (const item of order.items) {
      const qty = item.quantity;
      const col =
        item.collectedQuantity !== undefined
          ? item.collectedQuantity
          : item.handoverStatus === "collected"
          ? qty
          : 0;
      orderTotalQty += qty;
      orderCollectedQty += Math.min(qty, col);
    }

    totalConfirmedPortions += orderTotalQty;
    collectedPortions += orderCollectedQty;

    const isFullyDone =
      order.allHandedOver ||
      order.fulfillmentStatus === "collected" ||
      (orderTotalQty > 0 && orderCollectedQty >= orderTotalQty);

    if (isFullyDone) {
      fullyCollectedOrdersCount++;
    } else if (orderCollectedQty > 0) {
      partiallyCollectedOrdersCount++;
    } else if (order.fulfillmentStatus === "ready_for_pickup") {
      readyForPickupOrdersCount++;
    } else {
      preparingOrdersCount++;
    }
  }

  const checkedInOrdersCount = fullyCollectedOrdersCount + partiallyCollectedOrdersCount;
  const confirmedEligibleOrdersCount = confirmedEligibleOrders.length;
  const remainingPortions = Math.max(0, totalConfirmedPortions - collectedPortions);

  const orderCheckInRatePercent =
    confirmedEligibleOrdersCount > 0
      ? Math.round((checkedInOrdersCount / confirmedEligibleOrdersCount) * 100)
      : null;

  const portionRedemptionRatePercent =
    totalConfirmedPortions > 0
      ? Math.round((collectedPortions / totalConfirmedPortions) * 100)
      : null;

  return {
    confirmedEligibleOrdersCount,
    checkedInOrdersCount,
    fullyCollectedOrdersCount,
    partiallyCollectedOrdersCount,
    readyForPickupOrdersCount,
    preparingOrdersCount,
    totalConfirmedPortions,
    collectedPortions,
    remainingPortions,
    orderCheckInRatePercent,
    portionRedemptionRatePercent,
  };
}

export function buildHostSummaryMetricCards(
  financials: HostFinancialBreakdown,
  ticketStats: HostTicketSalesStats,
  checkInStats: HostCheckInStats,
  formatMoney: (amount: number, currency: Currency) => string
): HostSummaryMetricCardSpec[] {
  const clearedSharePercent =
    financials.forecastRevenue > 0
      ? Math.round((financials.confirmedPayments / financials.forecastRevenue) * 100)
      : null;

  const pendingSharePercent =
    financials.forecastRevenue > 0
      ? Math.round((financials.pendingPayments / financials.forecastRevenue) * 100)
      : null;

  return [
    {
      id: "sales",
      title: "Sales (Confirmed Revenue)",
      formattedValue: formatMoney(financials.confirmedPayments, financials.currency),
      rawValue: financials.confirmedPayments,
      subtitle: `${financials.confirmedOrdersCount} verified orders · ${formatMoney(
        financials.settledFunds,
        financials.currency
      )} net settled`,
      secondaryValueLabel: `Forecast pipeline: ${formatMoney(
        financials.forecastRevenue,
        financials.currency
      )}`,
      reliablePercentage: clearedSharePercent,
      reliablePercentageLabel:
        clearedSharePercent !== null ? `${clearedSharePercent}% of pipeline cleared` : null,
      calculationDefinition:
        "Sales = Σ(Verified Paid Orders) + Σ(Verified Deposits on Partially Paid Orders). Pending & unverified transfers are strictly excluded.",
      detailToolId: "reports",
      detailActionLabel: "View Financial Breakdown",
      tone: "emerald",
    },
    {
      id: "tickets_sold",
      title: "Tickets Sold",
      formattedValue: `${ticketStats.confirmedTicketsCount} Tickets`,
      rawValue: ticketStats.confirmedTicketsCount,
      subtitle: `${ticketStats.confirmedPortionsCount} confirmed meal/ticket portions issued`,
      secondaryValueLabel: `${ticketStats.reservedPendingTicketsCount} pending orders (${ticketStats.reservedPendingPortionsCount} portions) reserved`,
      reliablePercentage: ticketStats.confirmedClearanceRatePercent,
      reliablePercentageLabel:
        ticketStats.confirmedClearanceRatePercent !== null
          ? `${ticketStats.confirmedClearanceRatePercent}% of active orders verified`
          : null,
      calculationDefinition:
        "Tickets Sold = Count of orders with verified payment (paid or partially_paid). Unpaid or unverified reservations are tracked separately.",
      detailToolId: "orders",
      detailActionLabel: "Open Orders Ledger",
      tone: "indigo",
    },
    {
      id: "pending_payments",
      title: "Pending Payments",
      formattedValue: formatMoney(financials.pendingPayments, financials.currency),
      rawValue: financials.pendingPayments,
      subtitle: `${financials.pendingVerificationQueueCount} transfers in review queue · ${financials.awaitingPaymentOrdersCount} awaiting payment`,
      secondaryValueLabel:
        financials.pendingPartialBalanceRemaining > 0
          ? `Includes ${formatMoney(
              financials.pendingPartialBalanceRemaining,
              financials.currency
            )} partial balance due`
          : "Zero partial balances outstanding",
      reliablePercentage: pendingSharePercent,
      reliablePercentageLabel:
        pendingSharePercent !== null ? `${pendingSharePercent}% awaiting clearance` : null,
      calculationDefinition:
        "Pending Payments = Σ(Submitted Transfers in Review) + Σ(Awaiting Payment Reservations) + Σ(Unpaid Partial Balances). Not counted as received revenue.",
      detailToolId: "payments",
      detailActionLabel: "Verify Pending Queue",
      tone: "amber",
    },
    {
      id: "checkin_progress",
      title: "Check-In Progress",
      formattedValue: `${checkInStats.checkedInOrdersCount} / ${checkInStats.confirmedEligibleOrdersCount}`,
      rawValue: checkInStats.checkedInOrdersCount,
      subtitle: `${checkInStats.collectedPortions} of ${checkInStats.totalConfirmedPortions} confirmed portions redeemed`,
      secondaryValueLabel: `${checkInStats.fullyCollectedOrdersCount} completed · ${checkInStats.partiallyCollectedOrdersCount} partial · ${checkInStats.readyForPickupOrdersCount} ready`,
      reliablePercentage: checkInStats.portionRedemptionRatePercent,
      reliablePercentageLabel:
        checkInStats.portionRedemptionRatePercent !== null
          ? `${checkInStats.portionRedemptionRatePercent}% portions redeemed`
          : null,
      calculationDefinition:
        "Check-In Progress = Confirmed orders with ≥1 redeemed portion ÷ Total confirmed paid orders. Portion % = Redeemed portions ÷ Total confirmed portions.",
      detailToolId: "attendees",
      detailActionLabel: "Manage Attendee Check-In",
      tone: "sky",
    },
  ];
}

export function buildHostUpcomingEventsList(params: {
  activeEvent: EventDetails;
  activeMenu: MenuItem[];
  activeOrganizerId: string;
  activeRole: TenantRole;
  hostEvents?: FishFryEventItem[];
  catalogEvents?: FishFryEventItem[];
  orders: TicketOrder[];
  formatMoney: (amount: number, currency: Currency) => string;
}): HostUpcomingEventCardData[] {
  const {
    activeEvent,
    activeMenu,
    activeOrganizerId,
    activeRole,
    hostEvents = [],
    catalogEvents = [],
    orders,
    formatMoney,
  } = params;

  const seenIds = new Set<string>();
  const rawEvents: Array<EventDetails | FishFryEventItem> = [];

  // 1. Always include the currently active event first
  const activeEventId = activeEvent.id || "evt-fish-fry-2026";
  rawEvents.push({
    ...activeEvent,
    id: activeEventId,
    ...(activeMenu ? { menu: activeMenu } : {}),
  } as FishFryEventItem);
  seenIds.add(activeEventId);

  // 2. Include backend hostEvents belonging to the authenticated host
  for (const ev of hostEvents) {
    const evId = ev.id || ev.title;
    if (!seenIds.has(evId)) {
      if (activeRole === "platform_admin" || !ev.organizerId || ev.organizerId === activeOrganizerId) {
        rawEvents.push(ev);
        seenIds.add(evId);
      }
    }
  }

  // 3. Include catalogEvents / TEN_FISH_FRY_EVENTS matching activeOrganizerId
  const pool = catalogEvents.length > 0 ? catalogEvents : TEN_FISH_FRY_EVENTS;
  for (const ev of pool) {
    const evId = ev.id || ev.title;
    if (!seenIds.has(evId) && (ev.organizerId === activeOrganizerId || !ev.organizerId)) {
      rawEvents.push(ev);
      seenIds.add(evId);
    }
  }

  // 4. Platform SuperAdmin may view cross-tenant catalog events; standard hosts see only events they are authorized to manage
  if (activeRole === "platform_admin") {
    const affiliatedFallbacks = pool.slice(0, 4);
    for (const ev of affiliatedFallbacks) {
      if (rawEvents.length >= 4) break;
      const evId = ev.id || ev.title;
      if (!seenIds.has(evId)) {
        rawEvents.push(ev);
        seenIds.add(evId);
      }
    }
  }

  return rawEvents.map((ev, idx) => {
    const evId = ev.id || `evt-host-${idx + 1}`;
    const evCurrency: Currency = ev.currency || activeEvent.currency || "USD";
    const isCurrentActiveEvent = evId === activeEventId;
    const evOrders = orders.filter((o) =>
      o.eventId ? o.eventId === evId : isCurrentActiveEvent && o.currency === evCurrency
    );

    const confirmedSalesRaw = evOrders.reduce((sum, o) => {
      if (o.status === "paid") return sum + o.totalAmount;
      if (o.status === "partially_paid") return sum + Math.max(0, o.amountPaid || 0);
      return sum;
    }, 0);

    const ticketsSoldCount = evOrders.filter(
      (o) => o.status === "paid" || o.status === "partially_paid"
    ).length;

    const pendingOrdersCount = evOrders.filter(
      (o) =>
        o.status === "payment_submitted" ||
        o.status === "under_verification" ||
        o.status === "pending_verification" ||
        o.status === "awaiting_payment"
    ).length;

    const menuItemsCount =
      "menu" in ev && Array.isArray(ev.menu)
        ? ev.menu.length
        : isCurrentActiveEvent
        ? activeMenu.length
        : 0;

    return {
      id: evId,
      organizerId: ev.organizerId || activeOrganizerId,
      slug: ev.slug,
      title: ev.title,
      tagline: ev.tagline,
      dateStr: ev.dateStr,
      timeStr: ev.timeStr,
      venueName: ev.venueName,
      venueAddress: ev.venueAddress,
      currency: evCurrency,
      status: ev.status || "published",
      isOrderingOpen: ev.isOrderingOpen,
      imageUrl: resolveEventThumbnail(ev),
      isCurrentActiveEvent,
      confirmedSalesFormatted: formatMoney(confirmedSalesRaw, evCurrency),
      confirmedSalesRaw,
      ticketsSoldCount,
      pendingOrdersCount,
      menuItemsCount,
      sourceEvent: ev,
    };
  });
}

/**
 * Computes time-series buckets for Sales Analytics.
 * Strictly separates Verified Payments (Confirmed Revenue split into Ticket Sales vs Food Sales)
 * from Pending Submissions and Refunded Orders — never silently mixing them.
 */
export function computeHostSalesAnalyticsSeries(
  orders: TicketOrder[],
  currency: Currency = "USD",
  period: HostSalesAnalyticsPeriod = "all",
  customStart?: string,
  customEnd?: string
): HostSalesAnalyticsSummary {
  const scopedOrders = orders.filter((o) => o.currency === currency);
  const workingOrders = scopedOrders.length > 0 ? scopedOrders : orders;

  // Determine reference timestamp (latest order date or current date)
  const orderTimestamps = workingOrders
    .map((o) => Date.parse(o.createdAt))
    .filter((t) => !Number.isNaN(t));
  const referenceMs =
    orderTimestamps.length > 0 ? Math.max(...orderTimestamps) : Date.parse("2026-07-04T18:00:00Z");

  const DAY_MS = 24 * 60 * 60 * 1000;
  const filteredOrders = workingOrders.filter((order) => {
    const ts = Date.parse(order.createdAt);
    if (Number.isNaN(ts)) return true;

    if (period === "today") {
      return referenceMs - ts <= DAY_MS;
    }
    if (period === "7d") {
      return referenceMs - ts <= 7 * DAY_MS;
    }
    if (period === "30d") {
      return referenceMs - ts <= 30 * DAY_MS;
    }
    if (period === "custom") {
      const startMs = customStart ? Date.parse(customStart) : null;
      const endMs = customEnd ? Date.parse(`${customEnd}T23:59:59Z`) : null;
      if (startMs && !Number.isNaN(startMs) && ts < startMs) return false;
      if (endMs && !Number.isNaN(endMs) && ts > endMs) return false;
      return true;
    }
    return true;
  });

  const periodLabels: Record<HostSalesAnalyticsPeriod, string> = {
    today: "Today (Last 24 Hours)",
    "7d": "Last 7 Days",
    "30d": "Last 30 Days",
    all: "All Time",
    custom:
      customStart || customEnd
        ? `Custom Range (${customStart || "Start"} → ${customEnd || "Latest"})`
        : "Custom Date Range",
  };

  const bucketMap = new Map<string, HostSalesAnalyticsBucket>();

  for (const order of filteredOrders) {
    const rawDate = order.createdAt ? order.createdAt.slice(0, 10) : "2026-07-04";
    const bucketKey = period === "today" && order.createdAt
      ? `${rawDate} ${order.createdAt.slice(11, 13) || "12"}:00`
      : rawDate;

    if (!bucketMap.has(bucketKey)) {
      bucketMap.set(bucketKey, {
        bucketKey,
        label: bucketKey,
        verifiedTicketSales: 0,
        verifiedFoodSales: 0,
        verifiedTotalRevenue: 0,
        pendingSubmissionsAmount: 0,
        refundedAmount: 0,
        verifiedOrdersCount: 0,
        pendingOrdersCount: 0,
        refundedOrdersCount: 0,
      });
    }

    const bucket = bucketMap.get(bucketKey)!;

    // Classify order items into Ticket vs Food shares
    let orderTicketSubtotal = 0;
    let orderFoodSubtotal = 0;
    for (const item of order.items) {
      const lineTotal = item.finalItemPrice * item.quantity;
      const nameLower = item.name.toLowerCase();
      const idLower = item.menuItemId.toLowerCase();
      const isTicketItem =
        idLower.startsWith("tkt-") ||
        idLower.includes("ticket") ||
        idLower.includes("admission") ||
        nameLower.includes("ticket") ||
        nameLower.includes("admission") ||
        nameLower.includes("pass");
      if (isTicketItem) {
        orderTicketSubtotal += lineTotal;
      } else {
        orderFoodSubtotal += lineTotal;
      }
    }

    const itemsCombined = orderTicketSubtotal + orderFoodSubtotal;
    const ticketShareRatio =
      itemsCombined > 0 ? orderTicketSubtotal / itemsCombined : 0;

    if (order.status === "paid" || order.status === "partially_paid") {
      const verifiedAmount =
        order.status === "paid"
          ? order.totalAmount
          : Math.max(0, order.amountPaid || 0);

      bucket.verifiedTicketSales += Math.round(verifiedAmount * ticketShareRatio * 100) / 100;
      bucket.verifiedFoodSales +=
        Math.round((verifiedAmount - Math.round(verifiedAmount * ticketShareRatio * 100) / 100) * 100) /
        100;
      bucket.verifiedTotalRevenue += verifiedAmount;
      bucket.verifiedOrdersCount += 1;

      if (order.status === "partially_paid") {
        const remainingBalance = Math.max(0, order.totalAmount - (order.amountPaid || 0));
        if (remainingBalance > 0) {
          bucket.pendingSubmissionsAmount += remainingBalance;
          bucket.pendingOrdersCount += 1;
        }
      }
    } else if (
      order.status === "payment_submitted" ||
      order.status === "under_verification" ||
      order.status === "pending_verification" ||
      order.status === "awaiting_payment"
    ) {
      bucket.pendingSubmissionsAmount += order.totalAmount;
      bucket.pendingOrdersCount += 1;
    } else if (order.status === "refunded") {
      bucket.refundedAmount += order.refundDetails?.amount ?? order.totalAmount;
      bucket.refundedOrdersCount += 1;
    }
  }

  const buckets = Array.from(bucketMap.values()).sort((a, b) =>
    a.bucketKey.localeCompare(b.bucketKey)
  );

  const totals = buckets.reduce(
    (acc, b) => ({
      verifiedTicketSales: acc.verifiedTicketSales + b.verifiedTicketSales,
      verifiedFoodSales: acc.verifiedFoodSales + b.verifiedFoodSales,
      verifiedTotalRevenue: acc.verifiedTotalRevenue + b.verifiedTotalRevenue,
      pendingSubmissionsAmount: acc.pendingSubmissionsAmount + b.pendingSubmissionsAmount,
      refundedAmount: acc.refundedAmount + b.refundedAmount,
      verifiedOrdersCount: acc.verifiedOrdersCount + b.verifiedOrdersCount,
      pendingOrdersCount: acc.pendingOrdersCount + b.pendingOrdersCount,
      refundedOrdersCount: acc.refundedOrdersCount + b.refundedOrdersCount,
    }),
    {
      verifiedTicketSales: 0,
      verifiedFoodSales: 0,
      verifiedTotalRevenue: 0,
      pendingSubmissionsAmount: 0,
      refundedAmount: 0,
      verifiedOrdersCount: 0,
      pendingOrdersCount: 0,
      refundedOrdersCount: 0,
    }
  );

  return {
    period,
    periodLabel: periodLabels[period],
    currency,
    measureDefinition:
      "Primary series displays Verified Payments (Confirmed Revenue = Paid Orders + Verified Partial Deposits). Pending submissions and refunded orders are segregated into separate columns and never counted as received revenue.",
    buckets,
    totals,
  };
}

/**
 * Calculates Popular Menu Items from actual order records.
 * Strictly excludes refunded and rejected orders from verified units/revenue
 * and preserves historical order line-item price snapshots even if menu items are edited later.
 */
export function computeHostPopularMenuItems(
  menu: MenuItem[],
  orders: TicketOrder[],
  currency: Currency = "USD"
): HostPopularMenuItemRow[] {
  const scopedOrders = orders.filter((o) => o.currency === currency);
  const workingOrders = scopedOrders.length > 0 ? scopedOrders : orders;

  const menuLookup = new Map<string, MenuItem>();
  for (const m of menu) {
    menuLookup.set(m.id, m);
  }

  const itemMap = new Map<string, HostPopularMenuItemRow>();

  // Seed from current menu items so hosts can also see items with 0 sales or sold-out status
  for (const m of menu) {
    const unitPrice = currency === "USD" ? m.priceUSD : m.priceJMD;
    itemMap.set(m.id, {
      itemId: m.id,
      name: m.name,
      category: m.category,
      imageUrl: m.imageUrl || "/images/escovitch_fish.jpg",
      unitPrice,
      verifiedUnitsSold: 0,
      verifiedRevenue: 0,
      pendingUnitsReserved: 0,
      pendingRevenueUnverified: 0,
      availableStock: m.availableStock ?? null,
      isSoldOut: m.availableStock !== undefined && m.availableStock !== null && m.availableStock <= 0,
    });
  }

  for (const order of workingOrders) {
    // Never count refunded or rejected orders in popular item sales
    if (order.status === "refunded" || order.status === "rejected") {
      continue;
    }

    const isVerified = order.status === "paid" || order.status === "partially_paid";
    const isPending =
      order.status === "payment_submitted" ||
      order.status === "under_verification" ||
      order.status === "pending_verification" ||
      order.status === "awaiting_payment";

    const verifiedPaymentRatio =
      order.status === "paid"
        ? 1
        : order.status === "partially_paid" && order.totalAmount > 0
        ? Math.min(1, Math.max(0, (order.amountPaid || 0) / order.totalAmount))
        : 0;

    for (const line of order.items) {
      const existing = itemMap.get(line.menuItemId);
      const catalogItem = menuLookup.get(line.menuItemId);
      const row: HostPopularMenuItemRow = existing || {
        itemId: line.menuItemId,
        name: line.name,
        category: catalogItem?.category || "Mains",
        imageUrl: catalogItem?.imageUrl || "/images/escovitch_fish.jpg",
        unitPrice: line.finalItemPrice,
        verifiedUnitsSold: 0,
        verifiedRevenue: 0,
        pendingUnitsReserved: 0,
        pendingRevenueUnverified: 0,
        availableStock: catalogItem?.availableStock ?? null,
        isSoldOut:
          catalogItem?.availableStock !== undefined &&
          catalogItem?.availableStock !== null &&
          catalogItem.availableStock <= 0,
      };

      const lineGross = line.finalItemPrice * line.quantity;

      if (isVerified) {
        row.verifiedUnitsSold += line.quantity;
        row.verifiedRevenue += Math.round(lineGross * verifiedPaymentRatio * 100) / 100;
        if (order.status === "partially_paid" && verifiedPaymentRatio < 1) {
          row.pendingRevenueUnverified +=
            Math.round(lineGross * (1 - verifiedPaymentRatio) * 100) / 100;
        }
      } else if (isPending) {
        row.pendingUnitsReserved += line.quantity;
        row.pendingRevenueUnverified += lineGross;
      }

      itemMap.set(line.menuItemId, row);
    }
  }

  return Array.from(itemMap.values()).sort((a, b) => {
    if (b.verifiedRevenue !== a.verifiedRevenue) {
      return b.verifiedRevenue - a.verifiedRevenue;
    }
    if (b.verifiedUnitsSold !== a.verifiedUnitsSold) {
      return b.verifiedUnitsSold - a.verifiedUnitsSold;
    }
    return b.pendingUnitsReserved - a.pendingUnitsReserved;
  });
}
