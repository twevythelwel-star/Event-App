import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { OrganizerDashboard } from "../components/OrganizerDashboard";
import { HostAttendeesView } from "../components/host/HostAttendeesView";
import { HostEventStudioView } from "../components/host/HostEventStudioView";
import { HostFoodOrdersPrepView } from "../components/host/HostFoodOrdersPrepView";
import { HostOrdersView } from "../components/host/HostOrdersView";
import { HostPaymentsLedgerView } from "../components/host/HostPaymentsLedgerView";
import { HostReportsView } from "../components/host/HostReportsView";
import { HostSettingsSection } from "../components/host/HostSettingsSection";
import { HostWorkerAssignmentsView } from "../components/host/HostWorkerAssignmentsView";
import {
  buildHostSummaryMetricCards,
  buildHostUpcomingEventsList,
  computeHostCheckInStats,
  computeHostFinancialBreakdown,
  computeHostRolePermissions,
  computeHostTicketSalesStats,
} from "../components/host/financialCalculations";
import { DEFAULT_EVENT_DETAILS, INITIAL_SAVED_STATE } from "../data/defaultData";
import { CashPaymentRecord, Currency, TenantRole, TicketOrder } from "../types";

const formatMoney = (amount: number, _currency?: Currency) =>
  `$${amount.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

describe("24. Host Dashboard Modular Operations & Financial Integrity", () => {
  const sampleOrders: TicketOrder[] = [
    {
      id: "ord-paid-card",
      organizerId: "org-kingston-youth",
      eventId: "evt-fish-fry-2026",
      ticketCode: "FF26-CARD01",
      orderNumber: "ORD-1001",
      customerName: "Marcia Griffiths",
      customerPhone: "+18765550101",
      email: "marcia@example.com",
      method: "card",
      subtotal: 95,
      packagingFee: 5,
      totalAmount: 100,
      currency: "USD",
      status: "paid",
      paymentReference: "STRIPE-001",
      fulfillmentStatus: "collected",
      createdAt: "2026-07-04T12:00:00Z",
      allHandedOver: true,
      secureTicketToken: "QR-1",
      items: [
        {
          menuItemId: "escovitch-snapper",
          name: "Whole Escovitch Snapper",
          pricingType: "fixed",
          basePrice: 50,
          selectedOptionsSummary: [],
          optionsTotalExtra: 0,
          finalItemPrice: 50,
          currency: "USD",
          quantity: 2,
          handoverStatus: "collected",
          collectedQuantity: 2,
        },
      ],
    },
    {
      id: "ord-paid-cash",
      organizerId: "org-kingston-youth",
      eventId: "evt-fish-fry-2026",
      ticketCode: "FF26-CASH01",
      orderNumber: "ORD-1002",
      customerName: "Beres Hammond",
      customerPhone: "+18765550102",
      email: "beres@example.com",
      method: "cash",
      subtotal: 70,
      packagingFee: 0,
      totalAmount: 70,
      currency: "USD",
      status: "paid",
      paymentReference: "RCPT-001",
      fulfillmentStatus: "ready_for_pickup",
      createdAt: "2026-07-04T12:30:00Z",
      allHandedOver: false,
      secureTicketToken: "QR-2",
      items: [
        {
          menuItemId: "curried-goat",
          name: "Curried Goat Dinner",
          pricingType: "fixed",
          basePrice: 35,
          selectedOptionsSummary: [],
          optionsTotalExtra: 0,
          finalItemPrice: 35,
          currency: "USD",
          quantity: 2,
          handoverStatus: "pending",
          collectedQuantity: 1,
        },
      ],
    },
    {
      id: "ord-pending-transfer",
      organizerId: "org-kingston-youth",
      eventId: "evt-fish-fry-2026",
      ticketCode: "FF26-PEND01",
      orderNumber: "ORD-1003",
      customerName: "Chronixx",
      customerPhone: "+18765550103",
      email: "chronixx@example.com",
      method: "bank",
      subtotal: 120,
      packagingFee: 0,
      totalAmount: 120,
      currency: "USD",
      status: "payment_submitted",
      paymentReference: "ACH-WAIT-99",
      fulfillmentStatus: "preparing",
      createdAt: "2026-07-04T13:00:00Z",
      allHandedOver: false,
      secureTicketToken: "QR-3",
      items: [
        {
          menuItemId: "jerk-pork",
          name: "Boston Jerk Pork",
          pricingType: "fixed",
          basePrice: 40,
          selectedOptionsSummary: [],
          optionsTotalExtra: 0,
          finalItemPrice: 40,
          currency: "USD",
          quantity: 3,
          handoverStatus: "pending",
          collectedQuantity: 0,
        },
      ],
    },
    {
      id: "ord-refunded",
      organizerId: "org-kingston-youth",
      eventId: "evt-fish-fry-2026",
      ticketCode: "FF26-REF01",
      orderNumber: "ORD-1004",
      customerName: "Koffee",
      customerPhone: "+18765550104",
      email: "koffee@example.com",
      method: "card",
      subtotal: 30,
      packagingFee: 0,
      totalAmount: 30,
      currency: "USD",
      status: "refunded",
      paymentReference: "REF-001",
      fulfillmentStatus: "unprepared",
      createdAt: "2026-07-04T13:30:00Z",
      allHandedOver: false,
      secureTicketToken: "QR-4",
      items: [
        {
          menuItemId: "festival",
          name: "Festival Pack",
          pricingType: "fixed",
          basePrice: 15,
          selectedOptionsSummary: [],
          optionsTotalExtra: 0,
          finalItemPrice: 15,
          currency: "USD",
          quantity: 2,
          handoverStatus: "pending",
          collectedQuantity: 0,
        },
      ],
    },
  ];

  const sampleCashLedger: CashPaymentRecord[] = [
    {
      id: "cash-1",
      organizerId: "org-kingston-youth",
      transactionType: "receipt",
      orderId: "ord-paid-cash",
      ticketCode: "FF26-CASH01",
      customerName: "Beres Hammond",
      customerPhone: "+18765550102",
      amount: 70,
      currency: "USD",
      receivedBy: "Aisha Campbell",
      receiptNumber: "RCPT-001",
      receivedAt: "2026-07-04T12:31:00Z",
    },
    {
      id: "cash-rev-1",
      organizerId: "org-kingston-youth",
      transactionType: "reversal",
      orderId: "ord-old-cash",
      ticketCode: "FF26-OLD01",
      customerName: "Reversed Guest",
      customerPhone: "+18765550199",
      amount: -25,
      currency: "USD",
      receivedBy: "Aisha Campbell",
      receiptNumber: "REV-RCPT-000",
      receivedAt: "2026-07-04T11:00:00Z",
      reversalReason: "Duplicate entry",
    },
  ];

  it("strictly separates confirmed payments, pending payments, forecast revenue, refunds, and settled funds", () => {
    const breakdown = computeHostFinancialBreakdown(sampleOrders, sampleCashLedger, "USD");

    // Confirmed payments = $100 (card) + $70 (cash) = $170. Never includes $120 pending or $30 refunded!
    expect(breakdown.confirmedPayments).toBe(170);
    expect(breakdown.confirmedOrdersCount).toBe(2);

    // Pending payments = $120 (payment_submitted)
    expect(breakdown.pendingPayments).toBe(120);
    expect(breakdown.pendingOrdersCount).toBe(1);

    // Forecast revenue = Confirmed ($170) + Pending ($120) = $290
    expect(breakdown.forecastRevenue).toBe(290);
    expect(breakdown.activePipelineOrdersCount).toBe(3);

    // Refunds & Reversals = $30 (refunded order) + $25 (reversed cash receipt) = $55
    expect(breakdown.refundsTotal).toBe(55);
    expect(breakdown.orderRefundsCount).toBe(1);
    expect(breakdown.cashReversalsCount).toBe(1);

    // Settled funds
    expect(breakdown.settledDigitalAndBank).toBe(70); // $100 card - $30 card refund
    expect(breakdown.settledCashInHand).toBe(45); // $70 cash receipt - $25 cash reversal
    expect(breakdown.settledFunds).toBe(140); // $170 confirmed - $30 order refunds
  });

  it("computes accurate ticket sales and check-in progress with reliable percentages", () => {
    const ticketStats = computeHostTicketSalesStats(sampleOrders, "USD");
    expect(ticketStats.confirmedTicketsCount).toBe(2);
    expect(ticketStats.reservedPendingTicketsCount).toBe(1);
    expect(ticketStats.confirmedPortionsCount).toBe(4); // 2 snapper + 2 curried goat
    expect(ticketStats.reservedPendingPortionsCount).toBe(3); // 3 jerk pork
    expect(ticketStats.confirmedClearanceRatePercent).toBe(67); // 2 / 3 = 67%

    const checkInStats = computeHostCheckInStats(sampleOrders, "USD");
    // 2 confirmed orders: ord-paid-card is fully collected, ord-paid-cash has 1 of 2 portions collected
    expect(checkInStats.confirmedEligibleOrdersCount).toBe(2);
    expect(checkInStats.checkedInOrdersCount).toBe(2);
    expect(checkInStats.fullyCollectedOrdersCount).toBe(1);
    expect(checkInStats.partiallyCollectedOrdersCount).toBe(1);
    expect(checkInStats.orderCheckInRatePercent).toBe(100);
    expect(checkInStats.portionRedemptionRatePercent).toBe(75); // 3 of 4 portions collected
  });

  it("builds 4 refined summary metric cards with defined calculations and detail navigation targets", () => {
    const breakdown = computeHostFinancialBreakdown(sampleOrders, sampleCashLedger, "USD");
    const ticketStats = computeHostTicketSalesStats(sampleOrders, "USD");
    const checkInStats = computeHostCheckInStats(sampleOrders, "USD");
    const cards = buildHostSummaryMetricCards(breakdown, ticketStats, checkInStats, formatMoney);

    expect(cards).toHaveLength(4);
    expect(cards.map((c) => c.id)).toEqual([
      "sales",
      "tickets_sold",
      "pending_payments",
      "checkin_progress",
    ]);

    const salesCard = cards.find((c) => c.id === "sales")!;
    expect(salesCard.formattedValue).toBe("$170.00");
    expect(salesCard.detailToolId).toBe("reports");
    expect(salesCard.calculationDefinition).toContain("Pending & unverified transfers are strictly excluded");

    const pendingCard = cards.find((c) => c.id === "pending_payments")!;
    expect(pendingCard.formattedValue).toBe("$120.00");
    expect(pendingCard.detailToolId).toBe("payments");
  });

  it("adapts navigation and operational permissions to the role model", () => {
    const ownerPerms = computeHostRolePermissions("event_owner");
    expect(ownerPerms.canManageEventsAndMenus).toBe(true);
    expect(ownerPerms.canVerifyPaymentsAndCash).toBe(true);
    expect(ownerPerms.authorizedTools.settings).toBe(true);
    expect(ownerPerms.authorizedTools.workers).toBe(true);

    const verifierPerms = computeHostRolePermissions("payment_verifier");
    expect(verifierPerms.canManageEventsAndMenus).toBe(false);
    expect(verifierPerms.canVerifyPaymentsAndCash).toBe(true);
    expect(verifierPerms.authorizedTools.payments).toBe(true);
    expect(verifierPerms.authorizedTools.settings).toBe(false);

    const collectorPerms = computeHostRolePermissions("food_collector");
    expect(collectorPerms.canManageAttendeesAndFulfillment).toBe(true);
    expect(collectorPerms.canVerifyPaymentsAndCash).toBe(false);
    expect(collectorPerms.authorizedTools.attendees).toBe(true);
    expect(collectorPerms.authorizedTools.food_orders).toBe(true);
  });

  it("lists upcoming events belonging to the authenticated host with thumbnails and statuses", () => {
    const upcoming = buildHostUpcomingEventsList({
      activeEvent: DEFAULT_EVENT_DETAILS,
      activeMenu: INITIAL_SAVED_STATE.menu,
      activeOrganizerId: "org-kingston-youth",
      activeRole: "event_owner",
      orders: INITIAL_SAVED_STATE.orders,
      formatMoney,
    });

    expect(upcoming.length).toBeGreaterThanOrEqual(1);
    expect(upcoming[0].title).toBe(DEFAULT_EVENT_DETAILS.title);
    expect(upcoming[0].imageUrl).toMatch(/^\/images\//);
    expect(upcoming[0].status).toBe("published");
  });

  it("renders OrganizerDashboard DOM structure cleanly across all 4 tenant roles", () => {
    const roles: TenantRole[] = ["event_owner", "payment_verifier", "food_collector", "platform_admin"];

    for (const role of roles) {
      const html = renderToStaticMarkup(
        React.createElement(OrganizerDashboard, {
          eventDetails: INITIAL_SAVED_STATE.eventDetails,
          menu: INITIAL_SAVED_STATE.menu,
          settings: INITIAL_SAVED_STATE.settings,
          orders: INITIAL_SAVED_STATE.orders,
          cashLedger: INITIAL_SAVED_STATE.cashLedger,
          notificationLogs: INITIAL_SAVED_STATE.notificationLogs,
          organizers: INITIAL_SAVED_STATE.organizers || [],
          activeOrganizerId: INITIAL_SAVED_STATE.activeOrganizerId || "org-kingston-youth",
          activeRolePerspective: role,
          tenantUsers: INITIAL_SAVED_STATE.tenantUsers || [],
          onSwitchOrganizer: () => {},
          onSwitchRolePerspective: () => {},
          onUpdateEventDetails: () => {},
          onUpdateMenu: () => {},
          onUpdateSettings: () => {},
          onUpdatePaymentStatus: () => {},
          onUpdateFulfillmentStatus: () => {},
          onRecordCashPayment: () => {},
          onReverseCashPayment: () => {},
          onRetryNotification: () => {},
          onSendCustomNotification: () => {},
          onBroadcastEventReminder: () => {},
          onResetDemo: () => {},
          onOpenGatewayModal: () => {},
          formatMoney,
        })
      );

      // Verify Dashboard Header
      expect(html).toContain('id="host-dashboard-header"');
      expect(html).toContain("Island Event");
      expect(html).toContain("Host Dashboard");
      expect(html).toContain('id="host-authorized-identity-card"');
      expect(html).toContain('id="host-header-notifications-btn"');

      // Verify 9 Navigation Tool Buttons
      expect(html).toContain('id="subnav-tab-dashboard"');
      expect(html).toContain('id="subnav-tab-event"');
      expect(html).toContain('id="subnav-tab-orders"');
      expect(html).toContain('id="subnav-tab-payments"');
      expect(html).toContain('id="subnav-tab-attendees"');
      expect(html).toContain('id="subnav-tab-food-orders"');
      expect(html).toContain('id="subnav-tab-reports"');
      expect(html).toContain('id="subnav-tab-workers"');
      expect(html).toContain('id="subnav-tab-settings"');

      // Verify 4 Summary Metric Cards
      expect(html).toContain('id="host-metric-card-sales"');
      expect(html).toContain('id="host-metric-card-tickets_sold"');
      expect(html).toContain('id="host-metric-card-pending_payments"');
      expect(html).toContain('id="host-metric-card-checkin_progress"');

      // Verify 5-Category Financial Distinction Panel
      expect(html).toContain('id="fin-distinction-confirmed"');
      expect(html).toContain('id="fin-distinction-pending"');
      expect(html).toContain('id="fin-distinction-forecast"');
      expect(html).toContain('id="fin-distinction-refunds"');
      expect(html).toContain('id="fin-distinction-settled"');
      expect(html).toContain("Pending ≠ Received Revenue");

      // Verify Upcoming Events Section
      expect(html).toContain('id="host-upcoming-events-section"');
      expect(html).toContain("Manage Event");
    }
  });

  it("renders all 8 dedicated host tool subviews cleanly with real operational data", () => {
    const permissions = computeHostRolePermissions("event_owner");
    const financials = computeHostFinancialBreakdown(
      INITIAL_SAVED_STATE.orders,
      INITIAL_SAVED_STATE.cashLedger,
      "USD"
    );
    const ticketStats = computeHostTicketSalesStats(INITIAL_SAVED_STATE.orders, "USD");
    const checkInStats = computeHostCheckInStats(INITIAL_SAVED_STATE.orders, "USD");
    const upcomingEvents = buildHostUpcomingEventsList({
      activeEvent: INITIAL_SAVED_STATE.eventDetails,
      activeMenu: INITIAL_SAVED_STATE.menu,
      activeOrganizerId: "org-kingston-youth",
      activeRole: "event_owner",
      orders: INITIAL_SAVED_STATE.orders,
      formatMoney,
    });
    const activeOrganizer = (INITIAL_SAVED_STATE.organizers || [])[0];

    const eventsHtml = renderToStaticMarkup(
      React.createElement(HostEventStudioView, {
        eventDetails: INITIAL_SAVED_STATE.eventDetails,
        activeOrganizer,
        permissions,
        upcomingEvents,
        flyerWarningToast: null,
        preSaveConfirmFlyer: null,
        shareCopied: false,
        onManageEvent: () => {},
        onNavigateTool: () => {},
        onUpdateEventDetails: () => {},
        onOpenUnpublishModal: () => {},
        onOpenCancelEventModal: () => {},
        onFlyerUpload: () => {},
        onConfirmPreSavedFlyer: () => {},
        onCancelPreSavedFlyer: () => {},
        onRemoveFlyer: () => {},
        onCopyShareUrl: () => {},
        onSaveToast: () => {},
      })
    );
    expect(eventsHtml).toContain('id="event-studio-view"');
    expect(eventsHtml).toContain('id="event-status-badge"');

    const ordersHtml = renderToStaticMarkup(
      React.createElement(HostOrdersView, {
        orders: INITIAL_SAVED_STATE.orders,
        currency: "USD",
        permissions,
        financials,
        ticketStats,
        onNavigateTool: () => {},
        onUpdatePaymentStatus: () => {},
        onUpdateFulfillmentStatus: () => {},
        onOpenPartialModal: () => {},
        onOpenCashModal: () => {},
        onOpenRejectModal: () => {},
        onOpenCustomMessageModal: () => {},
        formatMoney,
      })
    );
    expect(ordersHtml).toContain('id="host-orders-view"');

    const paymentsHtml = renderToStaticMarkup(
      React.createElement(HostPaymentsLedgerView, {
        eventDetails: INITIAL_SAVED_STATE.eventDetails,
        orders: INITIAL_SAVED_STATE.orders,
        cashLedger: INITIAL_SAVED_STATE.cashLedger,
        permissions,
        financials,
        cashFormName: "",
        setCashFormName: () => {},
        cashFormPhone: "",
        setCashFormPhone: () => {},
        cashFormOrderNum: "",
        setCashFormOrderNum: () => {},
        cashFormAmount: "",
        setCashFormAmount: () => {},
        cashFormCollector: "Treasurer Davis",
        setCashFormCollector: () => {},
        cashFormNotes: "",
        setCashFormNotes: () => {},
        onRecordCashSubmit: () => {},
        reversingReceiptId: null,
        setReversingReceiptId: () => {},
        reversalSupervisor: "Supervisor Davis",
        setReversalSupervisor: () => {},
        reversalReason: "",
        setReversalReason: () => {},
        onReverseCashPayment: () => {},
        onUpdatePaymentStatus: () => {},
        onOpenPartialModal: () => {},
        onOpenCashModal: () => {},
        onOpenRejectModal: () => {},
        onOpenCustomMessageModal: () => {},
        formatMoney,
      })
    );
    expect(paymentsHtml).toContain('id="host-payments-ledger-view"');

    const attendeesHtml = renderToStaticMarkup(
      React.createElement(HostAttendeesView, {
        orders: INITIAL_SAVED_STATE.orders,
        currency: "USD",
        permissions,
        checkInStats,
        handoverAuditLogs: INITIAL_SAVED_STATE.handoverAuditLogs || [],
        onNavigateTool: () => {},
        onUpdateFulfillmentStatus: () => {},
        formatMoney,
      })
    );
    expect(attendeesHtml).toContain('id="host-attendees-view"');

    const foodOrdersHtml = renderToStaticMarkup(
      React.createElement(HostFoodOrdersPrepView, {
        menu: INITIAL_SAVED_STATE.menu,
        menuStats: INITIAL_SAVED_STATE.menu.map((m) => ({
          ...m,
          totalOrdered: 4,
          paidCount: 3,
          pendingCount: 1,
          collectedCount: 2,
          remainingToCook: 2,
        })),
        totalPortionsOrdered: 16,
        currency: "USD",
        permissions,
        onOpenAddDishModal: () => {},
        onOpenEditDishModal: () => {},
        onDeleteDish: () => {},
        formatMoney,
      })
    );
    expect(foodOrdersHtml).toContain('id="host-food-orders-view"');
    expect(foodOrdersHtml).toContain("Kitchen Operations &amp; Menu Studio");

    const reportsHtml = renderToStaticMarkup(
      React.createElement(HostReportsView, {
        orders: INITIAL_SAVED_STATE.orders,
        cashLedger: INITIAL_SAVED_STATE.cashLedger,
        notificationLogs: INITIAL_SAVED_STATE.notificationLogs,
        activeOrganizer,
        permissions,
        financials,
        onRetryNotification: () => {},
        onBroadcastEventReminder: () => {},
        formatMoney,
      })
    );
    expect(reportsHtml).toContain('id="host-reports-view"');

    const workersHtml = renderToStaticMarkup(
      React.createElement(HostWorkerAssignmentsView, {
        eventDetails: INITIAL_SAVED_STATE.eventDetails,
        activeOrganizerId: "org-kingston-youth",
        tenantUsers: INITIAL_SAVED_STATE.tenantUsers || [],
        permissions,
        onSwitchRolePerspective: () => {},
      })
    );
    expect(workersHtml).toContain('id="host-workers-view"');

    const settingsHtml = renderToStaticMarkup(
      React.createElement(HostSettingsSection, {
        eventDetails: INITIAL_SAVED_STATE.eventDetails,
        menu: INITIAL_SAVED_STATE.menu,
        settings: INITIAL_SAVED_STATE.settings,
        orders: INITIAL_SAVED_STATE.orders,
        cashLedger: INITIAL_SAVED_STATE.cashLedger,
        notificationLogs: INITIAL_SAVED_STATE.notificationLogs,
        activeOrganizer,
        tenantUsers: INITIAL_SAVED_STATE.tenantUsers || [],
        permissions,
        copiedMethodKey: null,
        onCopyMethodDetails: () => {},
        onUpdateEventDetails: () => {},
        onUpdateMenu: () => {},
        onUpdateSettings: () => {},
        onOpenGatewayModal: () => {},
        onSaveSettingsToast: () => {},
        formatMoney,
      })
    );
    expect(settingsHtml).toContain('id="host-settings-view"');
    expect(settingsHtml).toContain('id="host-tab-account-settings"');
    expect(settingsHtml).toContain('id="host-tab-payment-methods"');
  });
});

