import {
  CashPaymentRecord,
  Currency,
  EventDetails,
  HandoverAuditEntry,
  MenuItem,
  NotificationLogEntry,
  NotificationTrigger,
  OrderFulfillmentStatus,
  OrderStatus,
  OrganizerProfile,
  PaymentMethodKey,
  Settings,
  TenantRole,
  TenantUserMember,
  TicketOrder,
} from "../../types";
import { FishFryEventItem } from "../../data/fishFryEvents";
import { SessionUser } from "../../lib/api";

export type HostNavToolId =
  | "dashboard"
  | "events"
  | "orders"
  | "payments"
  | "attendees"
  | "food_orders"
  | "reports"
  | "workers"
  | "settings";

export type HostRolePermissions = {
  role: TenantRole;
  roleLabel: string;
  roleBadgeColor: string;
  canManageEventsAndMenus: boolean;
  canVerifyPaymentsAndCash: boolean;
  canManageAttendeesAndFulfillment: boolean;
  canManageSettingsAndBroadcast: boolean;
  canManageWorkers: boolean;
  canViewReports: boolean;
  canViewCrossTenantPlatform: boolean;
  authorizedTools: Record<HostNavToolId, boolean>;
};

export type PaymentMethodFinancialRow = {
  method: PaymentMethodKey;
  label: string;
  confirmedAmount: number;
  confirmedCount: number;
  pendingAmount: number;
  pendingCount: number;
  refundedAmount: number;
};

export type HostFinancialBreakdown = {
  currency: Currency;
  // 1. Confirmed Payments (Received & Verified Revenue ONLY)
  confirmedPayments: number;
  confirmedFullPaidAmount: number;
  confirmedPartialDepositsAmount: number;
  confirmedOrdersCount: number;
  fullPaidOrdersCount: number;
  partiallyPaidOrdersCount: number;

  // 2. Pending Payments (Unverified / Uncollected - NEVER presented as received revenue)
  pendingPayments: number;
  pendingSubmittedAmount: number;
  pendingAwaitingTransferAmount: number;
  pendingPartialBalanceRemaining: number;
  pendingOrdersCount: number;
  pendingVerificationQueueCount: number;
  awaitingPaymentOrdersCount: number;

  // 3. Forecast Revenue (Confirmed + Pending on active orders - projected pipeline)
  forecastRevenue: number;
  activePipelineOrdersCount: number;

  // 4. Refunds & Reversals
  refundsTotal: number;
  orderRefundsAmount: number;
  orderRefundsCount: number;
  cashReversalsAmount: number;
  cashReversalsCount: number;
  rejectedOrdersCount: number;
  rejectedOrdersAmount: number;

  // 5. Settled Funds (Cleared bank/card vs Cash ledger in-hand)
  settledFunds: number;
  settledDigitalAndBank: number;
  settledCashInHand: number;
  cashGrossCollected: number;
  cashReceiptsCount: number;

  // Breakdown by payment method
  byMethod: PaymentMethodFinancialRow[];
};

export type HostCheckInStats = {
  confirmedEligibleOrdersCount: number;
  checkedInOrdersCount: number; // Orders with allHandedOver or fulfillmentStatus === 'collected' or >= 1 item collected
  fullyCollectedOrdersCount: number;
  partiallyCollectedOrdersCount: number;
  readyForPickupOrdersCount: number;
  preparingOrdersCount: number;
  totalConfirmedPortions: number;
  collectedPortions: number;
  remainingPortions: number;
  orderCheckInRatePercent: number | null; // null if 0 confirmed orders
  portionRedemptionRatePercent: number | null; // null if 0 confirmed portions
};

export type HostTicketSalesStats = {
  confirmedTicketsCount: number; // Confirmed (paid + partially_paid) orders
  confirmedPortionsCount: number; // Portions inside confirmed orders
  reservedPendingTicketsCount: number; // Pending orders awaiting verification/payment
  reservedPendingPortionsCount: number;
  totalActiveOrdersCount: number;
  totalActivePortionsCount: number;
  confirmedClearanceRatePercent: number | null; // Confirmed orders / total active orders (reliable operational ratio)
};

export type HostSummaryMetricCardSpec = {
  id: "sales" | "tickets_sold" | "pending_payments" | "checkin_progress";
  title: string;
  formattedValue: string;
  rawValue: number;
  subtitle: string;
  secondaryValueLabel: string;
  /**
   * Reliable operational percentage calculated strictly from actual order/check-in records.
   * Null when denominator is 0 or when no reliable comparison ratio exists.
   */
  reliablePercentage: number | null;
  reliablePercentageLabel: string | null;
  calculationDefinition: string;
  detailToolId: HostNavToolId;
  detailActionLabel: string;
  tone: "emerald" | "indigo" | "amber" | "sky";
};

export type HostUpcomingEventCardData = {
  id: string;
  organizerId: string;
  slug?: string;
  title: string;
  tagline: string;
  dateStr: string;
  timeStr: string;
  venueName: string;
  venueAddress: string;
  currency: Currency;
  status: "draft" | "published" | "unpublished" | "cancelled";
  isOrderingOpen: boolean;
  imageUrl: string;
  isCurrentActiveEvent: boolean;
  confirmedSalesFormatted: string;
  confirmedSalesRaw: number;
  ticketsSoldCount: number;
  pendingOrdersCount: number;
  menuItemsCount: number;
  sourceEvent: EventDetails | FishFryEventItem;
};

export type HostSalesAnalyticsPeriod = "today" | "7d" | "30d" | "all" | "custom";

export type HostSalesAnalyticsBucket = {
  bucketKey: string;
  label: string;
  verifiedTicketSales: number;
  verifiedFoodSales: number;
  verifiedTotalRevenue: number;
  pendingSubmissionsAmount: number;
  refundedAmount: number;
  verifiedOrdersCount: number;
  pendingOrdersCount: number;
  refundedOrdersCount: number;
};

export type HostSalesAnalyticsSummary = {
  period: HostSalesAnalyticsPeriod;
  periodLabel: string;
  currency: Currency;
  measureDefinition: string;
  buckets: HostSalesAnalyticsBucket[];
  totals: {
    verifiedTicketSales: number;
    verifiedFoodSales: number;
    verifiedTotalRevenue: number;
    pendingSubmissionsAmount: number;
    refundedAmount: number;
    verifiedOrdersCount: number;
    pendingOrdersCount: number;
    refundedOrdersCount: number;
  };
};

export type HostPopularMenuItemRow = {
  itemId: string;
  name: string;
  category: string;
  imageUrl: string;
  unitPrice: number;
  verifiedUnitsSold: number;
  verifiedRevenue: number;
  pendingUnitsReserved: number;
  pendingRevenueUnverified: number;
  availableStock: number | null;
  isSoldOut: boolean;
};

export type HostWorkerAssignmentItem = {
  id: string;
  userId: string;
  name: string;
  email: string;
  phone?: string;
  eventId?: string;
  eventTitle?: string;
  roleOnEvent:
    | "payment_clerk"
    | "ticket_checker"
    | "food_station_worker"
    | "supervisor"
    | "custom_restricted_worker";
  tenantRoleEquivalent: TenantRole;
  stationAssignment: "all" | "mains" | "sides" | "drinks" | "desserts" | "cash_desk";
  assignedStationsList: string[];
  allowedPermissions?: string[];
  shiftStartsAt?: string;
  shiftExpiresAt?: string;
  status: "active" | "pending_acceptance" | "expired" | "revoked";
  isActive: boolean;
  lastActiveAt?: string;
  verifiedViaClaimCode?: boolean;
};

export type OrganizerDashboardProps = {
  eventDetails: EventDetails;
  menu: MenuItem[];
  settings: Settings;
  orders: TicketOrder[];
  cashLedger: CashPaymentRecord[];
  notificationLogs: NotificationLogEntry[];
  organizers: OrganizerProfile[];
  activeOrganizerId: string;
  activeRolePerspective: TenantRole;
  tenantUsers: TenantUserMember[];
  onSwitchOrganizer: (orgId: string) => void;
  onSwitchRolePerspective: (role: TenantRole) => void;
  onUpdateEventDetails: (details: EventDetails) => void;
  onUpdateMenu: (menu: MenuItem[]) => void;
  onUpdateSettings: (updater: (prev: Settings) => Settings) => void;
  onUpdatePaymentStatus: (
    orderId: string,
    newStatus: OrderStatus,
    details?: {
      amountPaid?: number;
      notes?: string;
      verifier?: string;
      rejectionReason?: string;
      refundDetails?: {
        amount: number;
        reason: string;
        refundedAt: string;
        refundedBy: string;
      };
    }
  ) => void;
  onUpdateFulfillmentStatus: (
    orderId: string,
    fulfillmentStatus: OrderFulfillmentStatus
  ) => void;
  onRecordCashPayment: (
    newCashRecord: CashPaymentRecord,
    linkedOrderId?: string
  ) => void | Promise<CashPaymentRecord>;
  onReverseCashPayment: (
    reversalRecord: CashPaymentRecord,
    linkedOrderId?: string
  ) => void;
  onRetryNotification: (logId: string) => void;
  onSendCustomNotification: (
    orderId: string,
    channel: "sms" | "email" | "whatsapp",
    trigger: NotificationTrigger,
    customMessage: string
  ) => void;
  onBroadcastEventReminder: () => void;
  onResetDemo: () => void;
  onOpenGatewayModal: () => void;
  formatMoney: (amount: number, currency: Currency) => string;
  // Optional real-backend / multi-event props passed from App.tsx
  sessionUser?: SessionUser | null;
  hostEvents?: FishFryEventItem[];
  catalogEvents?: FishFryEventItem[];
  handoverAuditLogs?: HandoverAuditEntry[];
  isApiOnline?: boolean;
  onSelectHostEvent?: (event: FishFryEventItem | EventDetails) => void;
  onSignOut?: () => void;
  onRefundOrder?: (orderId: string, amount: number, reason: string) => void;
};
