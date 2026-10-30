import { useMemo, useState } from "react";
import {
  ArrowRight,
  ArrowUpDown,
  Banknote,
  BarChart3,
  Calendar,
  CheckCircle2,
  ChefHat,
  Clock,
  FileSpreadsheet,
  Lock,
  Receipt,
  RotateCcw,
  Share2,
  ShieldCheck,
  Sparkles,
  TrendingUp,
} from "lucide-react";
import {
  Currency,
  EventDetails,
  MenuItem,
  OrderFulfillmentStatus,
  OrderStatus,
  Settings,
  TicketOrder,
} from "../../types";
import { FishFryEventItem } from "../../data/fishFryEvents";
import {
  HostCheckInStats,
  HostFinancialBreakdown,
  HostNavToolId,
  HostRolePermissions,
  HostSalesAnalyticsPeriod,
  HostSummaryMetricCardSpec,
  HostUpcomingEventCardData,
} from "./types";
import {
  computeHostPopularMenuItems,
  computeHostSalesAnalyticsSeries,
} from "./financialCalculations";
import { HostSummaryMetrics } from "./HostSummaryMetrics";
import { HostUpcomingEventsSection } from "./HostUpcomingEventsSection";

type Props = {
  eventDetails: EventDetails;
  menu: MenuItem[];
  settings: Settings;
  orders: TicketOrder[];
  permissions: HostRolePermissions;
  financials: HostFinancialBreakdown;
  checkInStats: HostCheckInStats;
  metricCards: HostSummaryMetricCardSpec[];
  upcomingEvents: HostUpcomingEventCardData[];
  shareCopied: boolean;
  onCopyShareLink: () => void;
  onNavigateTool: (tool: HostNavToolId) => void;
  onManageEvent: (event: EventDetails | FishFryEventItem) => void;
  onUpdatePaymentStatus: (
    orderId: string,
    status: OrderStatus,
    notes?: string,
    amountPaid?: number
  ) => void;
  onUpdateFulfillmentStatus: (
    orderId: string,
    fulfillmentStatus: OrderFulfillmentStatus
  ) => void;
  onOpenPartialModal: (order: TicketOrder) => void;
  onOpenCashModal: (order: TicketOrder) => void;
  onOpenRejectModal: (order: TicketOrder) => void;
  formatMoney: (amount: number, currency: Currency) => string;
  hostDisplayName?: string;
  onOpenCreateWizard?: () => void;
};

export function HostOperationsOverview({
  eventDetails,
  menu,
  settings,
  orders,
  permissions,
  financials,
  checkInStats,
  metricCards,
  upcomingEvents,
  shareCopied,
  onCopyShareLink,
  onNavigateTool,
  onManageEvent,
  onUpdatePaymentStatus,
  onUpdateFulfillmentStatus,
  onOpenPartialModal,
  onOpenCashModal,
  onOpenRejectModal,
  formatMoney,
  hostDisplayName = "Pastor Dennis Brown",
  onOpenCreateWizard,
}: Props) {
  const [analyticsPeriod, setAnalyticsPeriod] =
    useState<HostSalesAnalyticsPeriod>("all");
  const [customStartDate, setCustomStartDate] = useState<string>("2026-07-01");
  const [customEndDate, setCustomEndDate] = useState<string>("2026-07-31");
  const [recentSortField, setRecentSortField] = useState<
    "date" | "total" | "status"
  >("date");
  const [recentSortDir, setRecentSortDir] = useState<"asc" | "desc">("desc");
  const [recentPage, setRecentPage] = useState<number>(1);
  const RECENT_PAGE_SIZE = 5;

  const salesAnalytics = useMemo(
    () =>
      computeHostSalesAnalyticsSeries(
        orders,
        financials.currency,
        analyticsPeriod,
        customStartDate,
        customEndDate
      ),
    [orders, financials.currency, analyticsPeriod, customStartDate, customEndDate]
  );

  const popularMenuItems = useMemo(
    () => computeHostPopularMenuItems(menu, orders, financials.currency),
    [menu, orders, financials.currency]
  );

  const scopedRecentOrders = useMemo(() => {
    const currencyScoped = orders.filter(
      (o) => o.currency === financials.currency
    );
    const baseList = currencyScoped.length > 0 ? currencyScoped : orders;
    return [...baseList].sort((a, b) => {
      const mult = recentSortDir === "asc" ? 1 : -1;
      if (recentSortField === "total") {
        return (a.totalAmount - b.totalAmount) * mult;
      }
      if (recentSortField === "status") {
        return a.status.localeCompare(b.status) * mult;
      }
      return a.createdAt.localeCompare(b.createdAt) * mult;
    });
  }, [orders, financials.currency, recentSortField, recentSortDir]);

  const totalRecentPages = Math.max(
    1,
    Math.ceil(scopedRecentOrders.length / RECENT_PAGE_SIZE)
  );
  const currentRecentPage = Math.min(recentPage, totalRecentPages);
  const paginatedRecentOrders = scopedRecentOrders.slice(
    (currentRecentPage - 1) * RECENT_PAGE_SIZE,
    currentRecentPage * RECENT_PAGE_SIZE
  );

  const toggleRecentSort = (field: "date" | "total" | "status") => {
    if (recentSortField === field) {
      setRecentSortDir((prev) => (prev === "asc" ? "desc" : "asc"));
    } else {
      setRecentSortField(field);
      setRecentSortDir("desc");
    }
    setRecentPage(1);
  };

  const getOrderStatusBadge = (status: OrderStatus) => {
    switch (status) {
      case "paid":
        return {
          label: "VERIFIED PAID",
          bg: "#DCFCE7",
          color: "#166534",
          border: "#86EFAC",
        };
      case "partially_paid":
        return {
          label: "PARTIAL DEPOSIT",
          bg: "#E0F2FE",
          color: "#075985",
          border: "#7DD3FC",
        };
      case "payment_submitted":
      case "under_verification":
      case "pending_verification":
        return {
          label: "PENDING VERIFICATION",
          bg: "#FEF3C7",
          color: "#92400E",
          border: "#FCD34D",
        };
      case "awaiting_payment":
        return {
          label: "AWAITING PAYMENT",
          bg: "#F1F5F9",
          color: "#334155",
          border: "#CBD5E1",
        };
      case "refunded":
        return {
          label: "REFUNDED",
          bg: "#FEE2E2",
          color: "#991B1B",
          border: "#FCA5A5",
        };
      case "rejected":
      default:
        return {
          label: "REJECTED",
          bg: "#FEE2E2",
          color: "#991B1B",
          border: "#FCA5A5",
        };
    }
  };

  const pendingQueueOrders = orders.filter(
    (o) =>
      o.currency === financials.currency &&
      (o.status === "payment_submitted" ||
        o.status === "under_verification" ||
        o.status === "pending_verification" ||
        o.status === "awaiting_payment" ||
        o.status === "partially_paid")
  );

  const activePaidOrders = orders.filter(
    (o) =>
      o.currency === financials.currency &&
      (o.status === "paid" || o.status === "partially_paid")
  );

  const maxBucketValue = Math.max(
    1,
    ...salesAnalytics.buckets.map((b) =>
      Math.max(b.verifiedTotalRevenue, b.pendingSubmissionsAmount)
    )
  );

  return (
    <div id="host-dashboard-overview" style={{ display: "flex", flexDirection: "column", gap: "4px" }}>
      {/* 0. Welcome Section with Personalized Greeting & Date Filter */}
      <section
        id="host-welcome-banner"
        style={{
          marginBottom: "18px",
          padding: "18px 22px",
          borderRadius: "16px",
          background: "#FFFFFF",
          border: "1px solid #E2E8F0",
          boxShadow: "0 8px 24px rgba(15, 23, 42, 0.08)",
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          gap: "16px",
          flexWrap: "wrap",
        }}
      >
        <div>
          <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
            <span
              style={{
                fontSize: "10px",
                fontWeight: 800,
                padding: "3px 9px",
                borderRadius: "999px",
                background: "#14210A",
                color: "#C7FF45",
                letterSpacing: "1px",
                textTransform: "uppercase",
              }}
            >
              EventBite Host Studio
            </span>
            <span
              style={{
                fontSize: "11px",
                fontWeight: 700,
                padding: "2px 8px",
                borderRadius: "999px",
                background: "#F3E8FF",
                color: "#6D28D9",
                border: "1px solid #DDD6FE",
              }}
            >
              {permissions.roleLabel}
            </span>
          </div>
          <h2
            style={{
              margin: "6px 0 4px",
              fontSize: "20px",
              fontWeight: 800,
              color: "#0F172A",
              letterSpacing: "-0.4px",
            }}
          >
            Welcome back, {hostDisplayName}
          </h2>
          <p style={{ margin: 0, fontSize: "12px", color: "#475569" }}>
            Managing <strong style={{ color: "#0F172A" }}>{eventDetails.title}</strong> ({eventDetails.dateStr} · {eventDetails.venueName}) — real-time box office, menu fulfillment, and verified financial ledger.
          </p>
        </div>

        <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
          <Calendar size={15} color="#6D28D9" />
          <span style={{ fontSize: "11px", fontWeight: 700, color: "#475569" }}>
            Reporting Window:
          </span>
          {(
            [
              { key: "today", label: "Today" },
              { key: "7d", label: "Last 7 Days" },
              { key: "30d", label: "Last 30 Days" },
              { key: "all", label: "All Time" },
              { key: "custom", label: "Custom Range" },
            ] as const
          ).map((opt) => (
            <button
              key={opt.key}
              type="button"
              id={`host-filter-period-${opt.key}`}
              onClick={() => setAnalyticsPeriod(opt.key)}
              style={{
                padding: "6px 11px",
                borderRadius: "8px",
                fontSize: "11px",
                fontWeight: 800,
                background: analyticsPeriod === opt.key ? "#14210A" : "#F1F5F9",
                color: analyticsPeriod === opt.key ? "#C7FF45" : "#334155",
                border:
                  analyticsPeriod === opt.key
                    ? "1px solid #14210A"
                    : "1px solid #CBD5E1",
              }}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </section>

      {/* 1. Summary Metrics (Sales, Tickets Sold, Total Orders, Pending Payments, Check-In Progress) */}
      <HostSummaryMetrics
        cards={metricCards}
        onNavigateTool={onNavigateTool}
        totalOrdersCount={financials.activePipelineOrdersCount}
        confirmedOrdersCount={financials.confirmedOrdersCount}
        pendingOrdersCount={financials.pendingOrdersCount}
        permissions={permissions}
      />

      {/* 1B. Sales Analytics & Popular Menu Items Light Panels */}
      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(360px, 1fr))",
          gap: "18px",
          marginBottom: "22px",
        }}
      >
        {/* Left: Sales Analytics Panel */}
        <section
          id="host-sales-analytics-panel"
          style={{
            background: "#FFFFFF",
            border: "1px solid #E2E8F0",
            borderRadius: "16px",
            padding: "20px 22px",
            color: "#0F172A",
            boxShadow: "0 8px 24px rgba(15, 23, 42, 0.08)",
          }}
        >
          <div
            style={{
              display: "flex",
              alignItems: "flex-start",
              justifyContent: "space-between",
              gap: "12px",
              marginBottom: "12px",
              flexWrap: "wrap",
            }}
          >
            <div>
              <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                <BarChart3 size={15} color="#7C3AED" />
                <span
                  style={{
                    fontSize: "10px",
                    fontWeight: 800,
                    letterSpacing: "1.2px",
                    color: "#6D28D9",
                    textTransform: "uppercase",
                  }}
                >
                  Sales Analytics ({salesAnalytics.periodLabel})
                </span>
              </div>
              <h3
                style={{
                  margin: "4px 0 2px",
                  fontSize: "17px",
                  fontWeight: 800,
                  color: "#0F172A",
                }}
              >
                Verified Ticket & Food Revenue vs. Pending Pipeline
              </h3>
              <p
                style={{
                  margin: 0,
                  fontSize: "11px",
                  color: "#475569",
                  lineHeight: 1.4,
                }}
              >
                {salesAnalytics.measureDefinition}
              </p>
            </div>

            {analyticsPeriod === "custom" && (
              <div style={{ display: "flex", alignItems: "center", gap: "6px", flexWrap: "wrap" }}>
                <input
                  type="date"
                  aria-label="Custom start date"
                  value={customStartDate}
                  onChange={(e) => setCustomStartDate(e.target.value)}
                  style={{
                    height: "30px",
                    padding: "0 8px",
                    borderRadius: "6px",
                    border: "1px solid #CBD5E1",
                    fontSize: "11px",
                    color: "#0F172A",
                    background: "#F8FAFC",
                  }}
                />
                <span style={{ fontSize: "11px", color: "#64748B" }}>to</span>
                <input
                  type="date"
                  aria-label="Custom end date"
                  value={customEndDate}
                  onChange={(e) => setCustomEndDate(e.target.value)}
                  style={{
                    height: "30px",
                    padding: "0 8px",
                    borderRadius: "6px",
                    border: "1px solid #CBD5E1",
                    fontSize: "11px",
                    color: "#0F172A",
                    background: "#F8FAFC",
                  }}
                />
              </div>
            )}
          </div>

          {/* Series Summary Callouts */}
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(130px, 1fr))",
              gap: "10px",
              marginBottom: "14px",
            }}
          >
            <div
              style={{
                padding: "10px 12px",
                borderRadius: "10px",
                background: "#F8FAFC",
                border: "1px solid #E2E8F0",
              }}
            >
              <span style={{ fontSize: "10px", fontWeight: 700, color: "#475569", display: "block" }}>
                Verified Revenue (Paid)
              </span>
              <strong
                style={{
                  fontSize: "16px",
                  fontWeight: 800,
                  color: "#15803D",
                  fontVariantNumeric: "tabular-nums",
                }}
              >
                {formatMoney(salesAnalytics.totals.verifiedTotalRevenue, financials.currency)}
              </strong>
              <span style={{ fontSize: "10px", color: "#64748B", display: "block" }}>
                {salesAnalytics.totals.verifiedOrdersCount} verified orders
              </span>
            </div>

            <div
              style={{
                padding: "10px 12px",
                borderRadius: "10px",
                background: "#F8FAFC",
                border: "1px solid #E2E8F0",
              }}
            >
              <span style={{ fontSize: "10px", fontWeight: 700, color: "#475569", display: "block" }}>
                Food vs. Ticket Mix
              </span>
              <strong
                style={{
                  fontSize: "13px",
                  fontWeight: 800,
                  color: "#0F172A",
                  fontVariantNumeric: "tabular-nums",
                }}
              >
                Food: {formatMoney(salesAnalytics.totals.verifiedFoodSales, financials.currency)}
              </strong>
              <span style={{ fontSize: "10px", color: "#6D28D9", fontWeight: 700, display: "block" }}>
                Tickets: {formatMoney(salesAnalytics.totals.verifiedTicketSales, financials.currency)}
              </span>
            </div>

            <div
              style={{
                padding: "10px 12px",
                borderRadius: "10px",
                background: "#FFFBEB",
                border: "1px solid #FDE68A",
              }}
            >
              <span style={{ fontSize: "10px", fontWeight: 700, color: "#92400E", display: "block" }}>
                Pending (Segregated)
              </span>
              <strong
                style={{
                  fontSize: "16px",
                  fontWeight: 800,
                  color: "#B45309",
                  fontVariantNumeric: "tabular-nums",
                }}
              >
                {formatMoney(salesAnalytics.totals.pendingSubmissionsAmount, financials.currency)}
              </strong>
              <span style={{ fontSize: "10px", color: "#92400E", display: "block" }}>
                Excluded from revenue
              </span>
            </div>
          </div>

          {/* Visual Bar Chart */}
          <div
            role="img"
            aria-label={`Sales analytics chart for ${salesAnalytics.periodLabel}. Total verified revenue ${formatMoney(
              salesAnalytics.totals.verifiedTotalRevenue,
              financials.currency
            )}, segregated pending submissions ${formatMoney(
              salesAnalytics.totals.pendingSubmissionsAmount,
              financials.currency
            )}.`}
            style={{
              padding: "14px",
              borderRadius: "12px",
              background: "#0F172A",
              color: "#F8FAFC",
              marginBottom: "14px",
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
                gap: "8px",
                marginBottom: "10px",
                flexWrap: "wrap",
                fontSize: "10px",
              }}
            >
              <span style={{ fontWeight: 800, color: "#E2E8F0" }}>
                Chart Series Legend:
              </span>
              <div style={{ display: "flex", alignItems: "center", gap: "12px", flexWrap: "wrap" }}>
                <span style={{ display: "inline-flex", alignItems: "center", gap: "5px" }}>
                  <span
                    style={{
                      width: "10px",
                      height: "10px",
                      borderRadius: "3px",
                      background: "#C7FF45",
                    }}
                  />
                  Verified Food Sales
                </span>
                <span style={{ display: "inline-flex", alignItems: "center", gap: "5px" }}>
                  <span
                    style={{
                      width: "10px",
                      height: "10px",
                      borderRadius: "3px",
                      background: "#8B5CF6",
                    }}
                  />
                  Verified Ticket Sales
                </span>
                <span style={{ display: "inline-flex", alignItems: "center", gap: "5px" }}>
                  <span
                    style={{
                      width: "10px",
                      height: "10px",
                      borderRadius: "3px",
                      background: "#F59E0B",
                    }}
                  />
                  Pending Submissions (Unverified)
                </span>
              </div>
            </div>

            {salesAnalytics.buckets.length === 0 ? (
              <div style={{ padding: "18px", textAlign: "center", fontSize: "12px", color: "#94A3B8" }}>
                No orders recorded in this date window.
              </div>
            ) : (
              <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
                {salesAnalytics.buckets.map((b) => {
                  const verifiedPct = Math.min(
                    100,
                    Math.round((b.verifiedTotalRevenue / maxBucketValue) * 100)
                  );
                  const pendingPct = Math.min(
                    100,
                    Math.round((b.pendingSubmissionsAmount / maxBucketValue) * 100)
                  );
                  return (
                    <div key={b.bucketKey} style={{ fontSize: "11px" }}>
                      <div
                        style={{
                          display: "flex",
                          justifyContent: "space-between",
                          marginBottom: "3px",
                          color: "#CBD5E1",
                        }}
                      >
                        <strong style={{ fontFamily: "monospace" }}>{b.label}</strong>
                        <span style={{ fontVariantNumeric: "tabular-nums" }}>
                          Verified:{" "}
                          <strong style={{ color: "#C7FF45" }}>
                            {formatMoney(b.verifiedTotalRevenue, financials.currency)}
                          </strong>{" "}
                          · Pending:{" "}
                          <strong style={{ color: "#FBBF24" }}>
                            {formatMoney(b.pendingSubmissionsAmount, financials.currency)}
                          </strong>
                        </span>
                      </div>
                      <div
                        style={{
                          display: "flex",
                          flexDirection: "column",
                          gap: "3px",
                          background: "#1E293B",
                          padding: "4px",
                          borderRadius: "6px",
                        }}
                      >
                        <div
                          style={{
                            width: `${Math.max(4, verifiedPct)}%`,
                            height: "7px",
                            borderRadius: "4px",
                            background: "linear-gradient(90deg, #C7FF45 0%, #8B5CF6 100%)",
                          }}
                        />
                        {b.pendingSubmissionsAmount > 0 && (
                          <div
                            style={{
                              width: `${Math.max(4, pendingPct)}%`,
                              height: "5px",
                              borderRadius: "4px",
                              background: "#F59E0B",
                              opacity: 0.85,
                            }}
                          />
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          {/* Accessible Summary Table Alternative */}
          <div style={{ overflowX: "auto" }}>
            <table
              id="host-sales-analytics-table"
              aria-label="Sales Analytics Accessible Summary Table"
              style={{
                width: "100%",
                borderCollapse: "collapse",
                fontSize: "11px",
                textAlign: "left",
              }}
            >
              <thead>
                <tr style={{ borderBottom: "1px solid #CBD5E1", background: "#F8FAFC" }}>
                  <th style={{ padding: "8px", color: "#475569", fontWeight: 800 }}>Period</th>
                  <th style={{ padding: "8px", color: "#475569", fontWeight: 800 }}>
                    Verified Tickets
                  </th>
                  <th style={{ padding: "8px", color: "#475569", fontWeight: 800 }}>
                    Verified Food
                  </th>
                  <th style={{ padding: "8px", color: "#15803D", fontWeight: 800 }}>
                    Confirmed Revenue
                  </th>
                  <th style={{ padding: "8px", color: "#B45309", fontWeight: 800 }}>
                    Pending (Unverified)
                  </th>
                  <th style={{ padding: "8px", color: "#B91C1C", fontWeight: 800 }}>Refunds</th>
                </tr>
              </thead>
              <tbody>
                {salesAnalytics.buckets.map((b) => (
                  <tr key={b.bucketKey} style={{ borderBottom: "1px solid #F1F5F9" }}>
                    <td style={{ padding: "8px", fontWeight: 700, color: "#0F172A" }}>
                      {b.label}
                    </td>
                    <td style={{ padding: "8px", fontVariantNumeric: "tabular-nums" }}>
                      {formatMoney(b.verifiedTicketSales, financials.currency)}
                    </td>
                    <td style={{ padding: "8px", fontVariantNumeric: "tabular-nums" }}>
                      {formatMoney(b.verifiedFoodSales, financials.currency)}
                    </td>
                    <td
                      style={{
                        padding: "8px",
                        fontWeight: 800,
                        color: "#15803D",
                        fontVariantNumeric: "tabular-nums",
                      }}
                    >
                      {formatMoney(b.verifiedTotalRevenue, financials.currency)}
                    </td>
                    <td
                      style={{
                        padding: "8px",
                        fontWeight: 700,
                        color: "#B45309",
                        fontVariantNumeric: "tabular-nums",
                      }}
                    >
                      {formatMoney(b.pendingSubmissionsAmount, financials.currency)}
                    </td>
                    <td
                      style={{
                        padding: "8px",
                        color: "#B91C1C",
                        fontVariantNumeric: "tabular-nums",
                      }}
                    >
                      {formatMoney(b.refundedAmount, financials.currency)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        {/* Right: Popular Menu Items Panel */}
        <section
          id="host-popular-menu-items"
          style={{
            background: "#FFFFFF",
            border: "1px solid #E2E8F0",
            borderRadius: "16px",
            padding: "20px 22px",
            color: "#0F172A",
            boxShadow: "0 8px 24px rgba(15, 23, 42, 0.08)",
            display: "flex",
            flexDirection: "column",
            justifyContent: "space-between",
          }}
        >
          <div>
            <div
              style={{
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
                gap: "10px",
                marginBottom: "14px",
                flexWrap: "wrap",
              }}
            >
              <div>
                <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                  <ChefHat size={15} color="#15803D" />
                  <span
                    style={{
                      fontSize: "10px",
                      fontWeight: 800,
                      letterSpacing: "1.2px",
                      color: "#15803D",
                      textTransform: "uppercase",
                    }}
                  >
                    Order-Backed Culinary Telemetry
                  </span>
                </div>
                <h3
                  style={{
                    margin: "4px 0 2px",
                    fontSize: "17px",
                    fontWeight: 800,
                    color: "#0F172A",
                  }}
                >
                  Popular Menu Items
                </h3>
                <p style={{ margin: 0, fontSize: "11px", color: "#475569" }}>
                  Ranked by verified paid revenue from actual order records (refunded & rejected orders excluded).
                </p>
              </div>

              <button
                type="button"
                onClick={() => onNavigateTool("food_orders")}
                style={{
                  padding: "6px 11px",
                  borderRadius: "8px",
                  background: "#0F172A",
                  color: "#C7FF45",
                  border: "1px solid #0F172A",
                  fontSize: "11px",
                  fontWeight: 800,
                }}
              >
                Manage Menu →
              </button>
            </div>

            <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
              {popularMenuItems.slice(0, 5).map((item, idx) => (
                <div
                  key={item.itemId}
                  id={`popular-menu-item-${item.itemId}`}
                  style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    gap: "12px",
                    padding: "10px 12px",
                    borderRadius: "12px",
                    background: "#F8FAFC",
                    border: "1px solid #E2E8F0",
                  }}
                >
                  <div style={{ display: "flex", alignItems: "center", gap: "12px", minWidth: 0 }}>
                    <span
                      style={{
                        width: "22px",
                        height: "22px",
                        borderRadius: "6px",
                        background: idx === 0 ? "#C7FF45" : "#E2E8F0",
                        color: "#14210A",
                        fontSize: "11px",
                        fontWeight: 900,
                        display: "grid",
                        placeItems: "center",
                        flexShrink: 0,
                      }}
                    >
                      #{idx + 1}
                    </span>
                    <img
                      src={item.imageUrl}
                      alt={item.name}
                      onError={(e) => {
                        e.currentTarget.src = "/images/escovitch_fish.jpg";
                      }}
                      style={{
                        width: "46px",
                        height: "46px",
                        borderRadius: "10px",
                        objectFit: "cover",
                        border: "1px solid #CBD5E1",
                        flexShrink: 0,
                      }}
                    />
                    <div style={{ minWidth: 0 }}>
                      <strong
                        style={{
                          fontSize: "13px",
                          color: "#0F172A",
                          display: "block",
                          whiteSpace: "nowrap",
                          overflow: "hidden",
                          textOverflow: "ellipsis",
                        }}
                      >
                        {item.name}
                      </strong>
                      <div style={{ display: "flex", alignItems: "center", gap: "6px", flexWrap: "wrap" }}>
                        <span style={{ fontSize: "10px", color: "#64748B", fontWeight: 700 }}>
                          {item.category}
                        </span>
                        {item.isSoldOut && (
                          <span
                            style={{
                              fontSize: "9px",
                              fontWeight: 800,
                              padding: "1px 6px",
                              borderRadius: "999px",
                              background: "#FEE2E2",
                              color: "#991B1B",
                            }}
                          >
                            SOLD OUT
                          </span>
                        )}
                      </div>
                    </div>
                  </div>

                  <div style={{ textAlign: "right", flexShrink: 0 }}>
                    <strong
                      style={{
                        fontSize: "14px",
                        color: "#15803D",
                        display: "block",
                        fontVariantNumeric: "tabular-nums",
                      }}
                    >
                      {formatMoney(item.verifiedRevenue, financials.currency)}
                    </strong>
                    <span
                      style={{
                        fontSize: "11px",
                        fontWeight: 700,
                        color: "#0F172A",
                        fontVariantNumeric: "tabular-nums",
                      }}
                    >
                      {item.verifiedUnitsSold} units sold
                    </span>
                    {item.pendingUnitsReserved > 0 && (
                      <span
                        style={{
                          fontSize: "10px",
                          color: "#B45309",
                          display: "block",
                        }}
                      >
                        +{item.pendingUnitsReserved} pending
                      </span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>
      </div>

      {/* 1C. Recent Orders Light Panel Table */}
      <section
        id="host-recent-orders-table"
        style={{
          background: "#FFFFFF",
          border: "1px solid #E2E8F0",
          borderRadius: "16px",
          padding: "20px 22px",
          marginBottom: "22px",
          color: "#0F172A",
          boxShadow: "0 8px 24px rgba(15, 23, 42, 0.08)",
        }}
      >
        <div
          style={{
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            gap: "12px",
            marginBottom: "14px",
            flexWrap: "wrap",
          }}
        >
          <div>
            <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
              <Receipt size={15} color="#6D28D9" />
              <span
                style={{
                  fontSize: "10px",
                  fontWeight: 800,
                  letterSpacing: "1.2px",
                  color: "#6D28D9",
                  textTransform: "uppercase",
                }}
              >
                Live Box Office Order Ledger
              </span>
            </div>
            <h3 style={{ margin: "4px 0 0", fontSize: "17px", fontWeight: 800, color: "#0F172A" }}>
              Recent Orders ({scopedRecentOrders.length})
            </h3>
          </div>

          <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
            <button
              type="button"
              onClick={() => toggleRecentSort("date")}
              style={{
                padding: "5px 10px",
                borderRadius: "7px",
                background: recentSortField === "date" ? "#14210A" : "#F1F5F9",
                color: recentSortField === "date" ? "#C7FF45" : "#334155",
                border: "1px solid #CBD5E1",
                fontSize: "11px",
                fontWeight: 700,
                display: "inline-flex",
                alignItems: "center",
                gap: "4px",
              }}
            >
              <ArrowUpDown size={12} />
              Sort Date
            </button>
            <button
              type="button"
              onClick={() => toggleRecentSort("total")}
              style={{
                padding: "5px 10px",
                borderRadius: "7px",
                background: recentSortField === "total" ? "#14210A" : "#F1F5F9",
                color: recentSortField === "total" ? "#C7FF45" : "#334155",
                border: "1px solid #CBD5E1",
                fontSize: "11px",
                fontWeight: 700,
                display: "inline-flex",
                alignItems: "center",
                gap: "4px",
              }}
            >
              <ArrowUpDown size={12} />
              Sort Total
            </button>
            <button
              type="button"
              onClick={() => onNavigateTool("orders")}
              style={{
                padding: "6px 12px",
                borderRadius: "8px",
                background: "#0F172A",
                color: "#C7FF45",
                border: "1px solid #0F172A",
                fontSize: "11px",
                fontWeight: 800,
              }}
            >
              Open Full Orders Table →
            </button>
          </div>
        </div>

        <div style={{ overflowX: "auto" }}>
          <table style={{ width: "100%", borderCollapse: "collapse", textAlign: "left", fontSize: "12px" }}>
            <thead>
              <tr style={{ borderBottom: "2px solid #E2E8F0", background: "#F8FAFC" }}>
                <th style={{ padding: "10px 12px", fontSize: "10px", color: "#475569", textTransform: "uppercase", fontWeight: 800 }}>
                  Order Reference
                </th>
                <th style={{ padding: "10px 12px", fontSize: "10px", color: "#475569", textTransform: "uppercase", fontWeight: 800 }}>
                  Customer
                </th>
                <th style={{ padding: "10px 12px", fontSize: "10px", color: "#475569", textTransform: "uppercase", fontWeight: 800 }}>
                  Item Count
                </th>
                <th style={{ padding: "10px 12px", fontSize: "10px", color: "#475569", textTransform: "uppercase", fontWeight: 800 }}>
                  Total
                </th>
                <th style={{ padding: "10px 12px", fontSize: "10px", color: "#475569", textTransform: "uppercase", fontWeight: 800 }}>
                  Payment Method
                </th>
                <th style={{ padding: "10px 12px", fontSize: "10px", color: "#475569", textTransform: "uppercase", fontWeight: 800 }}>
                  Payment Status
                </th>
                <th style={{ padding: "10px 12px", fontSize: "10px", color: "#475569", textTransform: "uppercase", fontWeight: 800 }}>
                  Order Date
                </th>
                <th style={{ padding: "10px 12px", fontSize: "10px", color: "#475569", textTransform: "uppercase", fontWeight: 800, textAlign: "right" }}>
                  Available Actions
                </th>
              </tr>
            </thead>
            <tbody>
              {paginatedRecentOrders.map((order) => {
                const itemCount = order.items.reduce((sum, i) => sum + i.quantity, 0);
                const statusBadge = getOrderStatusBadge(order.status);
                const canVerifyThisOrder =
                  permissions.canVerifyPaymentsAndCash &&
                  (order.status === "payment_submitted" ||
                    order.status === "under_verification" ||
                    order.status === "pending_verification" ||
                    order.status === "awaiting_payment" ||
                    order.status === "partially_paid");

                return (
                  <tr
                    key={order.id}
                    style={{ borderBottom: "1px solid #E2E8F0" }}
                  >
                    <td style={{ padding: "10px 12px" }}>
                      <strong style={{ color: "#0F172A", fontFamily: "monospace", display: "block" }}>
                        {order.orderNumber}
                      </strong>
                      <span style={{ fontSize: "10px", color: "#64748B", fontFamily: "monospace" }}>
                        Ticket: {order.ticketCode}
                      </span>
                    </td>
                    <td style={{ padding: "10px 12px" }}>
                      <strong style={{ color: "#0F172A", display: "block" }}>
                        {order.customerName}
                      </strong>
                      <span style={{ fontSize: "10px", color: "#64748B" }}>
                        {order.email}
                      </span>
                    </td>
                    <td style={{ padding: "10px 12px" }}>
                      <strong style={{ color: "#0F172A", fontVariantNumeric: "tabular-nums" }}>
                        {itemCount} {itemCount === 1 ? "item" : "items"}
                      </strong>
                      <span style={{ fontSize: "10px", color: "#64748B", display: "block" }}>
                        {order.items.map((i) => `${i.quantity}x ${i.name}`).join(", ")}
                      </span>
                    </td>
                    <td
                      style={{
                        padding: "10px 12px",
                        fontWeight: 800,
                        color: "#0F172A",
                        fontVariantNumeric: "tabular-nums",
                      }}
                    >
                      {formatMoney(order.totalAmount, order.currency)}
                    </td>
                    <td style={{ padding: "10px 12px" }}>
                      <span
                        style={{
                          fontSize: "10px",
                          fontWeight: 800,
                          padding: "3px 8px",
                          borderRadius: "6px",
                          background: "#F1F5F9",
                          color: "#334155",
                          textTransform: "uppercase",
                        }}
                      >
                        {order.method}
                      </span>
                    </td>
                    <td style={{ padding: "10px 12px" }}>
                      <span
                        style={{
                          display: "inline-block",
                          fontSize: "10px",
                          fontWeight: 800,
                          padding: "3px 8px",
                          borderRadius: "999px",
                          background: statusBadge.bg,
                          color: statusBadge.color,
                          border: `1px solid ${statusBadge.border}`,
                        }}
                      >
                        {statusBadge.label}
                      </span>
                    </td>
                    <td style={{ padding: "10px 12px", fontSize: "11px", color: "#475569" }}>
                      {order.createdAt.slice(0, 10)}
                    </td>
                    <td style={{ padding: "10px 12px", textAlign: "right" }}>
                      <div style={{ display: "inline-flex", gap: "6px", flexWrap: "wrap", justifyContent: "flex-end" }}>
                        {canVerifyThisOrder && (
                          <button
                            type="button"
                            onClick={() =>
                              onUpdatePaymentStatus(
                                order.id,
                                "paid",
                                "Verified from Recent Orders table",
                                order.totalAmount
                              )
                            }
                            style={{
                              padding: "4px 9px",
                              borderRadius: "6px",
                              background: "#14210A",
                              color: "#C7FF45",
                              border: "1px solid #14210A",
                              fontSize: "10px",
                              fontWeight: 800,
                            }}
                          >
                            Verify Paid
                          </button>
                        )}
                        <button
                          type="button"
                          onClick={() => onNavigateTool("orders")}
                          style={{
                            padding: "4px 9px",
                            borderRadius: "6px",
                            background: "#F1F5F9",
                            color: "#0F172A",
                            border: "1px solid #CBD5E1",
                            fontSize: "10px",
                            fontWeight: 700,
                          }}
                        >
                          Details
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* Pagination Footer */}
        {totalRecentPages > 1 && (
          <div
            style={{
              marginTop: "12px",
              paddingTop: "10px",
              borderTop: "1px solid #E2E8F0",
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              fontSize: "11px",
              color: "#475569",
            }}
          >
            <span>
              Page <strong>{currentRecentPage}</strong> of <strong>{totalRecentPages}</strong> ({scopedRecentOrders.length} orders)
            </span>
            <div style={{ display: "flex", gap: "6px" }}>
              <button
                type="button"
                disabled={currentRecentPage <= 1}
                onClick={() => setRecentPage((p) => Math.max(1, p - 1))}
                style={{
                  padding: "4px 10px",
                  borderRadius: "6px",
                  background: "#F8FAFC",
                  border: "1px solid #CBD5E1",
                  color: "#0F172A",
                  fontSize: "11px",
                  fontWeight: 700,
                }}
              >
                Previous
              </button>
              <button
                type="button"
                disabled={currentRecentPage >= totalRecentPages}
                onClick={() => setRecentPage((p) => Math.min(totalRecentPages, p + 1))}
                style={{
                  padding: "4px 10px",
                  borderRadius: "6px",
                  background: "#F8FAFC",
                  border: "1px solid #CBD5E1",
                  color: "#0F172A",
                  fontSize: "11px",
                  fontWeight: 700,
                }}
              >
                Next
              </button>
            </div>
          </div>
        )}
      </section>

      {/* 2. Operations Financial Distinction Panel */}
      <section
        id="host-operations-financial-distinction"
        style={{
          background: "#0B1120",
          border: "1px solid rgba(148, 163, 184, 0.18)",
          borderRadius: "16px",
          padding: "20px 22px",
          marginBottom: "22px",
          boxShadow: "0 12px 32px rgba(2, 6, 23, 0.4)",
        }}
      >
        <div
          style={{
            display: "flex",
            alignItems: "flex-start",
            justifyContent: "space-between",
            gap: "14px",
            marginBottom: "16px",
            flexWrap: "wrap",
          }}
        >
          <div>
            <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
              <span
                style={{
                  fontSize: "10px",
                  fontWeight: 800,
                  letterSpacing: "1.4px",
                  color: "#34D399",
                  textTransform: "uppercase",
                }}
              >
                Strict Financial Separation Ledger
              </span>
              <span
                style={{
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "4px",
                  padding: "2px 8px",
                  borderRadius: "999px",
                  background: "rgba(16, 185, 129, 0.14)",
                  border: "1px solid rgba(52, 211, 153, 0.35)",
                  color: "#34D399",
                  fontSize: "10px",
                  fontWeight: 700,
                }}
              >
                <ShieldCheck size={11} />
                Pending ≠ Received Revenue
              </span>
            </div>
            <h2
              style={{
                margin: "4px 0 2px",
                fontSize: "17px",
                fontWeight: 800,
                color: "#F8FAFC",
                letterSpacing: "-0.3px",
              }}
            >
              Revenue, Pending Verification, Forecast, Refunds & Settlement
            </h2>
            <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
              Unverified transfers and estimated balances are strictly segregated from confirmed revenue until cleared on bank statement or cash receipt.
            </p>
          </div>

          <button
            type="button"
            onClick={() => onNavigateTool("reports")}
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "6px",
              padding: "8px 13px",
              borderRadius: "9px",
              background: "rgba(30, 41, 59, 0.85)",
              border: "1px solid rgba(148, 163, 184, 0.25)",
              color: "#E2E8F0",
              fontSize: "11px",
              fontWeight: 700,
            }}
          >
            <FileSpreadsheet size={14} color="#34D399" />
            <span>Full Financial Reconciliation</span>
            <ArrowRight size={13} />
          </button>
        </div>

        {/* 5-Column Financial Distinction Grid */}
        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))",
            gap: "12px",
          }}
        >
          {/* 1. Confirmed Payments */}
          <div
            id="fin-distinction-confirmed"
            style={{
              padding: "14px 16px",
              borderRadius: "12px",
              background: "rgba(6, 78, 59, 0.22)",
              border: "1px solid rgba(52, 211, 153, 0.35)",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
              <span
                style={{
                  fontSize: "10px",
                  fontWeight: 800,
                  color: "#34D399",
                  textTransform: "uppercase",
                  letterSpacing: "0.7px",
                }}
              >
                1. Confirmed Payments
              </span>
              <CheckCircle2 size={15} color="#34D399" />
            </div>
            <div
              style={{
                fontSize: "21px",
                fontWeight: 800,
                color: "#FFFFFF",
                marginTop: "6px",
              }}
            >
              {formatMoney(financials.confirmedPayments, financials.currency)}
            </div>
            <div style={{ fontSize: "11px", color: "#A7F3D0", marginTop: "4px", fontWeight: 600 }}>
              Received & Verified Revenue
            </div>
            <div style={{ fontSize: "10px", color: "#94A3B8", marginTop: "4px", lineHeight: 1.4 }}>
              Full paid ({financials.fullPaidOrdersCount}):{" "}
              {formatMoney(financials.confirmedFullPaidAmount, financials.currency)} · Partial deposits (
              {financials.partiallyPaidOrdersCount}):{" "}
              {formatMoney(financials.confirmedPartialDepositsAmount, financials.currency)}
            </div>
          </div>

          {/* 2. Pending Payments */}
          <div
            id="fin-distinction-pending"
            style={{
              padding: "14px 16px",
              borderRadius: "12px",
              background: "rgba(120, 53, 15, 0.22)",
              border: "1px solid rgba(251, 191, 36, 0.35)",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
              <span
                style={{
                  fontSize: "10px",
                  fontWeight: 800,
                  color: "#FBBF24",
                  textTransform: "uppercase",
                  letterSpacing: "0.7px",
                }}
              >
                2. Pending Payments
              </span>
              <Clock size={15} color="#FBBF24" />
            </div>
            <div
              style={{
                fontSize: "21px",
                fontWeight: 800,
                color: "#FDE68A",
                marginTop: "6px",
              }}
            >
              {formatMoney(financials.pendingPayments, financials.currency)}
            </div>
            <div style={{ fontSize: "11px", color: "#FBBF24", marginTop: "4px", fontWeight: 700 }}>
              Unverified (Not Counted as Revenue)
            </div>
            <div style={{ fontSize: "10px", color: "#94A3B8", marginTop: "4px", lineHeight: 1.4 }}>
              Submitted transfers: {formatMoney(financials.pendingSubmittedAmount, financials.currency)} ·
              Awaiting/Balance:{" "}
              {formatMoney(
                financials.pendingAwaitingTransferAmount + financials.pendingPartialBalanceRemaining,
                financials.currency
              )}
            </div>
          </div>

          {/* 3. Forecast Revenue */}
          <div
            id="fin-distinction-forecast"
            style={{
              padding: "14px 16px",
              borderRadius: "12px",
              background: "rgba(30, 41, 59, 0.65)",
              border: "1px dashed rgba(129, 140, 248, 0.4)",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
              <span
                style={{
                  fontSize: "10px",
                  fontWeight: 800,
                  color: "#A5B4FC",
                  textTransform: "uppercase",
                  letterSpacing: "0.7px",
                }}
              >
                3. Forecast Revenue
              </span>
              <TrendingUp size={15} color="#818CF8" />
            </div>
            <div
              style={{
                fontSize: "21px",
                fontWeight: 800,
                color: "#E0E7FF",
                marginTop: "6px",
              }}
            >
              {formatMoney(financials.forecastRevenue, financials.currency)}
            </div>
            <div style={{ fontSize: "11px", color: "#A5B4FC", marginTop: "4px", fontWeight: 600 }}>
              Projected Pipeline (Uncollected)
            </div>
            <div style={{ fontSize: "10px", color: "#94A3B8", marginTop: "4px", lineHeight: 1.4 }}>
              Confirmed + Pending across {financials.activePipelineOrdersCount} active orders if 100% of pending transfers clear.
            </div>
          </div>

          {/* 4. Refunds */}
          <div
            id="fin-distinction-refunds"
            style={{
              padding: "14px 16px",
              borderRadius: "12px",
              background: "rgba(127, 29, 29, 0.18)",
              border: "1px solid rgba(248, 113, 113, 0.32)",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
              <span
                style={{
                  fontSize: "10px",
                  fontWeight: 800,
                  color: "#F87171",
                  textTransform: "uppercase",
                  letterSpacing: "0.7px",
                }}
              >
                4. Refunds & Reversals
              </span>
              <RotateCcw size={15} color="#F87171" />
            </div>
            <div
              style={{
                fontSize: "21px",
                fontWeight: 800,
                color: "#FCA5A5",
                marginTop: "6px",
              }}
            >
              {formatMoney(financials.refundsTotal, financials.currency)}
            </div>
            <div style={{ fontSize: "11px", color: "#FCA5A5", marginTop: "4px", fontWeight: 600 }}>
              Deducted from Gross Volume
            </div>
            <div style={{ fontSize: "10px", color: "#94A3B8", marginTop: "4px", lineHeight: 1.4 }}>
              Order refunds ({financials.orderRefundsCount}):{" "}
              {formatMoney(financials.orderRefundsAmount, financials.currency)} · Cash reversals (
              {financials.cashReversalsCount}):{" "}
              {formatMoney(financials.cashReversalsAmount, financials.currency)}
            </div>
          </div>

          {/* 5. Settled Funds */}
          <div
            id="fin-distinction-settled"
            style={{
              padding: "14px 16px",
              borderRadius: "12px",
              background: "rgba(12, 74, 110, 0.24)",
              border: "1px solid rgba(56, 189, 248, 0.35)",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
              <span
                style={{
                  fontSize: "10px",
                  fontWeight: 800,
                  color: "#38BDF8",
                  textTransform: "uppercase",
                  letterSpacing: "0.7px",
                }}
              >
                5. Settled Funds
              </span>
              <Banknote size={15} color="#38BDF8" />
            </div>
            <div
              style={{
                fontSize: "21px",
                fontWeight: 800,
                color: "#E0F2FE",
                marginTop: "6px",
              }}
            >
              {formatMoney(financials.settledFunds, financials.currency)}
            </div>
            <div style={{ fontSize: "11px", color: "#7DD3FC", marginTop: "4px", fontWeight: 600 }}>
              Net Cleared Settlement
            </div>
            <div style={{ fontSize: "10px", color: "#94A3B8", marginTop: "4px", lineHeight: 1.4 }}>
              Bank/Card cleared: {formatMoney(financials.settledDigitalAndBank, financials.currency)} ·
              Cash ledger in-hand: {formatMoney(financials.settledCashInHand, financials.currency)}
            </div>
          </div>
        </div>
      </section>

      {/* 3. Upcoming Events Section */}
      <HostUpcomingEventsSection
        upcomingEvents={upcomingEvents}
        onManageEvent={onManageEvent}
        onNavigateTool={onNavigateTool}
        permissions={permissions}
        onOpenCreateWizard={onOpenCreateWizard}
      />

      {/* 4. Host Concierge Setup Roadmap (Preserving #host-concierge-roadmap) */}
      <div
        id="host-concierge-roadmap"
        style={{
          marginBottom: "22px",
          padding: "18px 20px",
          borderRadius: "15px",
          background: "linear-gradient(135deg, #090D18 0%, #111827 55%, #1E1B4B 100%)",
          color: "#F8FAFC",
          border: "1px solid rgba(212, 175, 55, 0.32)",
          boxShadow: "0 10px 26px rgba(2, 6, 23, 0.35)",
        }}
      >
        <div
          style={{
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            flexWrap: "wrap",
            gap: "12px",
            marginBottom: "14px",
          }}
        >
          <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
            <div
              style={{
                width: "32px",
                height: "32px",
                borderRadius: "8px",
                background: "rgba(212, 175, 55, 0.18)",
                border: "1px solid rgba(212, 175, 55, 0.45)",
                display: "grid",
                placeItems: "center",
                color: "#FBBF24",
              }}
            >
              <Sparkles size={16} />
            </div>
            <div>
              <span
                style={{
                  fontSize: "9px",
                  fontWeight: 800,
                  letterSpacing: "1.4px",
                  color: "#FBBF24",
                  textTransform: "uppercase",
                  display: "block",
                }}
              >
                Host Concierge Setup Roadmap
              </span>
              <strong style={{ fontSize: "14px", color: "#FFFFFF" }}>
                4-Step Event Launch & Box Office Readiness
              </strong>
            </div>
          </div>

          <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
            <span
              style={{
                fontSize: "10px",
                fontWeight: 700,
                padding: "4px 10px",
                borderRadius: "999px",
                background:
                  eventDetails.status === "published"
                    ? "rgba(16, 185, 129, 0.2)"
                    : "rgba(245, 158, 11, 0.2)",
                color: eventDetails.status === "published" ? "#34D399" : "#FBBF24",
                border:
                  eventDetails.status === "published"
                    ? "1px solid rgba(16, 185, 129, 0.4)"
                    : "1px solid rgba(245, 158, 11, 0.4)",
              }}
            >
              Status: {(eventDetails.status || "published").toUpperCase()}
            </span>
          </div>
        </div>

        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))",
            gap: "10px",
          }}
        >
          {/* Step 1 */}
          <div
            onClick={() => permissions.canManageEventsAndMenus && onNavigateTool("events")}
            style={{
              padding: "12px 14px",
              borderRadius: "11px",
              background: "rgba(30, 41, 59, 0.6)",
              border: "1px solid rgba(148, 163, 184, 0.2)",
              cursor: permissions.canManageEventsAndMenus ? "pointer" : "not-allowed",
              display: "flex",
              flexDirection: "column",
              justifyContent: "space-between",
              gap: "8px",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
              <span style={{ fontSize: "10px", fontWeight: 800, color: "#FBBF24" }}>STEP 1</span>
              <CheckCircle2 size={14} color="#34D399" />
            </div>
            <div>
              <strong style={{ fontSize: "12px", color: "#FFFFFF", display: "block" }}>
                1. Event & Flyer Details
              </strong>
              <span style={{ fontSize: "11px", color: "#94A3B8" }}>
                Venue, schedule & flyer artwork configured
              </span>
            </div>
            <span
              style={{
                fontSize: "10px",
                fontWeight: 700,
                color: "#A5B4FC",
                display: "inline-flex",
                alignItems: "center",
                gap: "4px",
              }}
            >
              Edit Event Studio <ArrowRight size={11} />
            </span>
          </div>

          {/* Step 2 */}
          <div
            onClick={() => permissions.canManageEventsAndMenus && onNavigateTool("food_orders")}
            style={{
              padding: "12px 14px",
              borderRadius: "11px",
              background: "rgba(30, 41, 59, 0.6)",
              border: "1px solid rgba(148, 163, 184, 0.2)",
              cursor: permissions.canManageEventsAndMenus ? "pointer" : "not-allowed",
              display: "flex",
              flexDirection: "column",
              justifyContent: "space-between",
              gap: "8px",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
              <span style={{ fontSize: "10px", fontWeight: 800, color: "#FBBF24" }}>STEP 2</span>
              <CheckCircle2 size={14} color={menu.length > 0 ? "#34D399" : "#94A3B8"} />
            </div>
            <div>
              <strong style={{ fontSize: "12px", color: "#FFFFFF", display: "block" }}>
                2. Menu & Pricing ({menu.length} Dishes)
              </strong>
              <span style={{ fontSize: "11px", color: "#94A3B8" }}>
                Mains, sides, portions & stock limits
              </span>
            </div>
            <span
              style={{
                fontSize: "10px",
                fontWeight: 700,
                color: "#A5B4FC",
                display: "inline-flex",
                alignItems: "center",
                gap: "4px",
              }}
            >
              Manage Food & Menu <ArrowRight size={11} />
            </span>
          </div>

          {/* Step 3 */}
          <div
            onClick={() => permissions.canManageSettingsAndBroadcast && onNavigateTool("settings")}
            style={{
              padding: "12px 14px",
              borderRadius: "11px",
              background: "rgba(30, 41, 59, 0.6)",
              border: "1px solid rgba(148, 163, 184, 0.2)",
              cursor: permissions.canManageSettingsAndBroadcast ? "pointer" : "not-allowed",
              display: "flex",
              flexDirection: "column",
              justifyContent: "space-between",
              gap: "8px",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
              <span style={{ fontSize: "10px", fontWeight: 800, color: "#FBBF24" }}>STEP 3</span>
              <CheckCircle2 size={14} color="#34D399" />
            </div>
            <div>
              <strong style={{ fontSize: "12px", color: "#FFFFFF", display: "block" }}>
                3. Payment Channels ({settings.currency})
              </strong>
              <span style={{ fontSize: "11px", color: "#94A3B8" }}>
                {Object.values(settings.methods).filter((m) => m.enabled).length} payment rails active
              </span>
            </div>
            <span
              style={{
                fontSize: "10px",
                fontWeight: 700,
                color: "#A5B4FC",
                display: "inline-flex",
                alignItems: "center",
                gap: "4px",
              }}
            >
              Configure Payment Rails <ArrowRight size={11} />
            </span>
          </div>

          {/* Step 4 */}
          <div
            onClick={onCopyShareLink}
            style={{
              padding: "12px 14px",
              borderRadius: "11px",
              background: "rgba(30, 41, 59, 0.6)",
              border: "1px solid rgba(148, 163, 184, 0.2)",
              cursor: "pointer",
              display: "flex",
              flexDirection: "column",
              justifyContent: "space-between",
              gap: "8px",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
              <span style={{ fontSize: "10px", fontWeight: 800, color: "#FBBF24" }}>STEP 4</span>
              <Share2 size={14} color="#34D399" />
            </div>
            <div>
              <strong style={{ fontSize: "12px", color: "#FFFFFF", display: "block" }}>
                4. Share Event Ordering Link
              </strong>
              <span style={{ fontSize: "11px", color: "#94A3B8" }}>
                {shareCopied ? "Copied shareable URL!" : "Send to WhatsApp, IG & guests"}
              </span>
            </div>
            <span
              style={{
                fontSize: "10px",
                fontWeight: 700,
                color: "#34D399",
                display: "inline-flex",
                alignItems: "center",
                gap: "4px",
              }}
            >
              {shareCopied ? "Link Copied!" : "Copy Event Link"} <ArrowRight size={11} />
            </span>
          </div>
        </div>
      </div>

      {/* 5. Live Operational Split: Payment Verification Queue & Check-In / Kitchen Snapshot */}
      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(340px, 1fr))",
          gap: "18px",
        }}
      >
        {/* Left Panel: Immediate Payment Verification Queue */}
        <section
          style={{
            background: "#0B1120",
            border: "1px solid rgba(148, 163, 184, 0.18)",
            borderRadius: "16px",
            padding: "18px 20px",
            boxShadow: "0 10px 28px rgba(2, 6, 23, 0.35)",
          }}
        >
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              gap: "10px",
              marginBottom: "14px",
            }}
          >
            <div>
              <span
                style={{
                  fontSize: "10px",
                  fontWeight: 800,
                  letterSpacing: "1.2px",
                  color: "#FBBF24",
                  textTransform: "uppercase",
                }}
              >
                Action Required
              </span>
              <h3 style={{ margin: "2px 0 0", fontSize: "15px", fontWeight: 800, color: "#F8FAFC" }}>
                Pending Payment Verification Queue ({pendingQueueOrders.length})
              </h3>
            </div>
            <button
              type="button"
              onClick={() => onNavigateTool("payments")}
              style={{
                padding: "6px 11px",
                borderRadius: "8px",
                background: "rgba(245, 158, 11, 0.16)",
                border: "1px solid rgba(251, 191, 36, 0.35)",
                color: "#FBBF24",
                fontSize: "11px",
                fontWeight: 700,
              }}
            >
              Open Payments Desk →
            </button>
          </div>

          {pendingQueueOrders.length === 0 ? (
            <div
              style={{
                padding: "24px",
                borderRadius: "12px",
                background: "rgba(15, 23, 42, 0.7)",
                border: "1px solid rgba(52, 211, 153, 0.25)",
                textAlign: "center",
                color: "#A7F3D0",
                fontSize: "12px",
              }}
            >
              <CheckCircle2 size={22} color="#34D399" style={{ marginBottom: "6px" }} />
              <div>All submitted payments have been verified!</div>
            </div>
          ) : (
            <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
              {pendingQueueOrders.slice(0, 4).map((order) => (
                <div
                  key={order.id}
                  style={{
                    padding: "12px 14px",
                    borderRadius: "11px",
                    background: "#0F172A",
                    border: "1px solid rgba(148, 163, 184, 0.18)",
                  }}
                >
                  <div
                    style={{
                      display: "flex",
                      alignItems: "flex-start",
                      justifyContent: "space-between",
                      gap: "8px",
                    }}
                  >
                    <div>
                      <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                        <strong style={{ fontSize: "13px", color: "#F8FAFC" }}>
                          {order.customerName}
                        </strong>
                        <code
                          style={{
                            fontSize: "10px",
                            padding: "1px 6px",
                            borderRadius: "4px",
                            background: "rgba(99, 102, 241, 0.18)",
                            color: "#A5B4FC",
                          }}
                        >
                          {order.orderNumber}
                        </code>
                      </div>
                      <div style={{ fontSize: "11px", color: "#94A3B8", marginTop: "2px" }}>
                        Method: <strong style={{ color: "#CBD5E1" }}>{order.method.toUpperCase()}</strong>
                        {order.paymentReference && (
                          <>
                            {" "}
                            · Ref: <code style={{ color: "#38BDF8" }}>{order.paymentReference}</code>
                          </>
                        )}
                      </div>
                    </div>

                    <div style={{ textAlign: "right" }}>
                      <strong style={{ fontSize: "14px", color: "#FBBF24", display: "block" }}>
                        {formatMoney(
                          order.status === "partially_paid"
                            ? Math.max(0, order.totalAmount - (order.amountPaid || 0))
                            : order.totalAmount,
                          order.currency
                        )}
                      </strong>
                      <span
                        style={{
                          fontSize: "9px",
                          fontWeight: 800,
                          color: "#FDE68A",
                          textTransform: "uppercase",
                        }}
                      >
                        {order.status.replace(/_/g, " ")}
                      </span>
                    </div>
                  </div>

                  {permissions.canVerifyPaymentsAndCash ? (
                    <div
                      style={{
                        marginTop: "10px",
                        paddingTop: "8px",
                        borderTop: "1px solid rgba(148, 163, 184, 0.12)",
                        display: "flex",
                        alignItems: "center",
                        gap: "6px",
                        flexWrap: "wrap",
                      }}
                    >
                      <button
                        type="button"
                        onClick={() =>
                          onUpdatePaymentStatus(
                            order.id,
                            "paid",
                            "Verified from Host Dashboard queue",
                            order.totalAmount
                          )
                        }
                        style={{
                          padding: "5px 10px",
                          borderRadius: "6px",
                          background: "#10B981",
                          color: "#052E16",
                          border: "none",
                          fontSize: "10px",
                          fontWeight: 800,
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "4px",
                        }}
                      >
                        <CheckCircle2 size={12} />
                        Verify Paid
                      </button>

                      <button
                        type="button"
                        onClick={() => onOpenPartialModal(order)}
                        style={{
                          padding: "5px 9px",
                          borderRadius: "6px",
                          background: "rgba(56, 189, 248, 0.16)",
                          color: "#38BDF8",
                          border: "1px solid rgba(56, 189, 248, 0.35)",
                          fontSize: "10px",
                          fontWeight: 700,
                        }}
                      >
                        Partial Deposit
                      </button>

                      <button
                        type="button"
                        onClick={() => onOpenCashModal(order)}
                        style={{
                          padding: "5px 9px",
                          borderRadius: "6px",
                          background: "rgba(16, 185, 129, 0.15)",
                          color: "#34D399",
                          border: "1px solid rgba(52, 211, 153, 0.35)",
                          fontSize: "10px",
                          fontWeight: 700,
                        }}
                      >
                        Log Cash Receipt
                      </button>

                      <button
                        type="button"
                        onClick={() => onOpenRejectModal(order)}
                        style={{
                          padding: "5px 9px",
                          borderRadius: "6px",
                          background: "rgba(239, 68, 68, 0.15)",
                          color: "#FCA5A5",
                          border: "1px solid rgba(248, 113, 113, 0.35)",
                          fontSize: "10px",
                          fontWeight: 700,
                          marginLeft: "auto",
                        }}
                      >
                        Reject
                      </button>
                    </div>
                  ) : (
                    <div
                      style={{
                        marginTop: "8px",
                        fontSize: "10px",
                        color: "#94A3B8",
                        display: "flex",
                        alignItems: "center",
                        gap: "5px",
                      }}
                    >
                      <Lock size={11} />
                      <span>Payment verification requires Host or Payment Verifier role.</span>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </section>

        {/* Right Panel: Check-In & Kitchen Fulfillment Snapshot */}
        <section
          style={{
            background: "#0B1120",
            border: "1px solid rgba(148, 163, 184, 0.18)",
            borderRadius: "16px",
            padding: "18px 20px",
            boxShadow: "0 10px 28px rgba(2, 6, 23, 0.35)",
          }}
        >
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              gap: "10px",
              marginBottom: "14px",
            }}
          >
            <div>
              <span
                style={{
                  fontSize: "10px",
                  fontWeight: 800,
                  letterSpacing: "1.2px",
                  color: "#38BDF8",
                  textTransform: "uppercase",
                }}
              >
                Gate & Kitchen Telemetry
              </span>
              <h3 style={{ margin: "2px 0 0", fontSize: "15px", fontWeight: 800, color: "#F8FAFC" }}>
                Attendee Check-In & Meal Fulfillment ({activePaidOrders.length} Verified)
              </h3>
            </div>
            <div style={{ display: "flex", gap: "6px" }}>
              <button
                type="button"
                onClick={() => onNavigateTool("attendees")}
                style={{
                  padding: "6px 10px",
                  borderRadius: "8px",
                  background: "rgba(56, 189, 248, 0.16)",
                  border: "1px solid rgba(56, 189, 248, 0.35)",
                  color: "#38BDF8",
                  fontSize: "11px",
                  fontWeight: 700,
                }}
              >
                Attendees →
              </button>
              <button
                type="button"
                onClick={() => onNavigateTool("food_orders")}
                style={{
                  padding: "6px 10px",
                  borderRadius: "8px",
                  background: "rgba(16, 185, 129, 0.16)",
                  border: "1px solid rgba(52, 211, 153, 0.35)",
                  color: "#34D399",
                  fontSize: "11px",
                  fontWeight: 700,
                }}
              >
                Food Orders →
              </button>
            </div>
          </div>

          {/* Check-in status mini-bar */}
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(4, 1fr)",
              gap: "8px",
              marginBottom: "12px",
            }}
          >
            <div
              style={{
                padding: "8px 10px",
                borderRadius: "8px",
                background: "#0F172A",
                border: "1px solid rgba(52, 211, 153, 0.25)",
              }}
            >
              <span style={{ fontSize: "9px", color: "#94A3B8", display: "block" }}>
                Fully Redeemed
              </span>
              <strong style={{ fontSize: "15px", color: "#34D399" }}>
                {checkInStats.fullyCollectedOrdersCount}
              </strong>
            </div>
            <div
              style={{
                padding: "8px 10px",
                borderRadius: "8px",
                background: "#0F172A",
                border: "1px solid rgba(56, 189, 248, 0.25)",
              }}
            >
              <span style={{ fontSize: "9px", color: "#94A3B8", display: "block" }}>
                Partial Check-In
              </span>
              <strong style={{ fontSize: "15px", color: "#38BDF8" }}>
                {checkInStats.partiallyCollectedOrdersCount}
              </strong>
            </div>
            <div
              style={{
                padding: "8px 10px",
                borderRadius: "8px",
                background: "#0F172A",
                border: "1px solid rgba(129, 140, 248, 0.25)",
              }}
            >
              <span style={{ fontSize: "9px", color: "#94A3B8", display: "block" }}>
                Ready for Pickup
              </span>
              <strong style={{ fontSize: "15px", color: "#A5B4FC" }}>
                {checkInStats.readyForPickupOrdersCount}
              </strong>
            </div>
            <div
              style={{
                padding: "8px 10px",
                borderRadius: "8px",
                background: "#0F172A",
                border: "1px solid rgba(251, 191, 36, 0.25)",
              }}
            >
              <span style={{ fontSize: "9px", color: "#94A3B8", display: "block" }}>
                In Kitchen Prep
              </span>
              <strong style={{ fontSize: "15px", color: "#FBBF24" }}>
                {checkInStats.preparingOrdersCount}
              </strong>
            </div>
          </div>

          <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
            {activePaidOrders.slice(0, 4).map((order) => {
              const totalPortions = order.items.reduce((s, i) => s + i.quantity, 0);
              const collectedPortions = order.items.reduce(
                (s, i) =>
                  s +
                  (i.collectedQuantity !== undefined
                    ? i.collectedQuantity
                    : i.handoverStatus === "collected"
                    ? i.quantity
                    : 0),
                0
              );

              return (
                <div
                  key={order.id}
                  style={{
                    padding: "10px 12px",
                    borderRadius: "10px",
                    background: "#0F172A",
                    border: "1px solid rgba(148, 163, 184, 0.16)",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    gap: "10px",
                    flexWrap: "wrap",
                  }}
                >
                  <div>
                    <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                      <strong style={{ fontSize: "12px", color: "#F8FAFC" }}>
                        {order.customerName}
                      </strong>
                      <code style={{ fontSize: "10px", color: "#34D399" }}>
                        {order.ticketCode}
                      </code>
                    </div>
                    <div style={{ fontSize: "10px", color: "#94A3B8", marginTop: "2px" }}>
                      {collectedPortions}/{totalPortions} portions collected ·{" "}
                      {order.items.map((i) => `${i.quantity}x ${i.name}`).join(", ")}
                    </div>
                  </div>

                  <select
                    aria-label={`Fulfillment status for ${order.orderNumber}`}
                    value={order.fulfillmentStatus}
                    onChange={(e) =>
                      onUpdateFulfillmentStatus(
                        order.id,
                        e.target.value as OrderFulfillmentStatus
                      )
                    }
                    style={{
                      background: "#1E293B",
                      color: "#F8FAFC",
                      border: "1px solid rgba(148, 163, 184, 0.3)",
                      borderRadius: "7px",
                      padding: "4px 8px",
                      fontSize: "10px",
                      fontWeight: 700,
                    }}
                  >
                    <option value="unprepared">Unprepared</option>
                    <option value="preparing">Preparing in Kitchen</option>
                    <option value="ready_for_pickup">Ready for Pickup</option>
                    <option value="collected">Collected / Checked In</option>
                  </select>
                </div>
              );
            })}
          </div>
        </section>
      </div>
    </div>
  );
}
