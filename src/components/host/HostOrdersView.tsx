import { useMemo, useState } from "react";
import {
  Filter,
  MessageSquare,
  Search,
} from "lucide-react";
import {
  Currency,
  OrderFulfillmentStatus,
  OrderStatus,
  TicketOrder,
} from "../../types";
import {
  HostFinancialBreakdown,
  HostNavToolId,
  HostRolePermissions,
  HostTicketSalesStats,
} from "./types";

type Props = {
  orders: TicketOrder[];
  currency: Currency;
  permissions: HostRolePermissions;
  financials: HostFinancialBreakdown;
  ticketStats: HostTicketSalesStats;
  onNavigateTool: (tool: HostNavToolId) => void;
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
  onOpenCustomMessageModal: (order: TicketOrder) => void;
  formatMoney: (amount: number, currency: Currency) => string;
};

export function HostOrdersView({
  orders,
  currency,
  permissions,
  financials,
  ticketStats,
  onNavigateTool,
  onUpdatePaymentStatus,
  onUpdateFulfillmentStatus,
  onOpenPartialModal,
  onOpenCashModal,
  onOpenRejectModal,
  onOpenCustomMessageModal,
  formatMoney,
}: Props) {
  const [searchQuery, setSearchQuery] = useState("");
  const [statusFilter, setStatusFilter] = useState<
    "all" | "confirmed" | "pending" | "partially_paid" | "refunded" | "rejected"
  >("all");

  const filteredOrders = useMemo(() => {
    return orders.filter((o) => {
      if (statusFilter === "confirmed" && o.status !== "paid" && o.status !== "partially_paid") {
        return false;
      }
      if (
        statusFilter === "pending" &&
        o.status !== "payment_submitted" &&
        o.status !== "under_verification" &&
        o.status !== "pending_verification" &&
        o.status !== "awaiting_payment"
      ) {
        return false;
      }
      if (statusFilter === "partially_paid" && o.status !== "partially_paid") {
        return false;
      }
      if (statusFilter === "refunded" && o.status !== "refunded") {
        return false;
      }
      if (statusFilter === "rejected" && o.status !== "rejected") {
        return false;
      }

      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const matchName = o.customerName.toLowerCase().includes(q);
        const matchCode = o.ticketCode.toLowerCase().includes(q);
        const matchOrder = o.orderNumber.toLowerCase().includes(q);
        const matchPhone = o.customerPhone.toLowerCase().includes(q);
        const matchRef = (o.paymentReference || "").toLowerCase().includes(q);
        if (!matchName && !matchCode && !matchOrder && !matchPhone && !matchRef) {
          return false;
        }
      }
      return true;
    });
  }, [orders, searchQuery, statusFilter]);

  return (
    <div id="host-orders-view" style={{ display: "flex", flexDirection: "column", gap: "18px" }}>
      {/* Defined Calculation & Summary Header for Tickets Sold & Orders */}
      <section
        style={{
          background: "#0B1120",
          border: "1px solid rgba(148, 163, 184, 0.18)",
          borderRadius: "16px",
          padding: "20px 22px",
          boxShadow: "0 12px 32px rgba(2, 6, 23, 0.4)",
        }}
      >
        <div
          style={{
            display: "flex",
            alignItems: "flex-start",
            justifyContent: "space-between",
            gap: "14px",
            flexWrap: "wrap",
            marginBottom: "16px",
          }}
        >
          <div>
            <span
              style={{
                fontSize: "10px",
                fontWeight: 800,
                letterSpacing: "1.4px",
                color: "#818CF8",
                textTransform: "uppercase",
              }}
            >
              Tickets Sold Detail Page & Order Registry
            </span>
            <h2
              style={{
                margin: "3px 0 4px",
                fontSize: "19px",
                fontWeight: 800,
                color: "#F8FAFC",
              }}
            >
              Event Orders & Ticket Issuance Ledger
            </h2>
            <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
              Defined Calculation: <strong>Tickets Sold ({ticketStats.confirmedTicketsCount})</strong> counts only verified{" "}
              <code>paid</code> and <code>partially_paid</code> orders ({ticketStats.confirmedPortionsCount} portions). Pending reservations ({ticketStats.reservedPendingTicketsCount}) are tracked separately until payment clears.
            </p>
          </div>

          <div style={{ display: "flex", gap: "8px", flexWrap: "wrap" }}>
            <button
              type="button"
              onClick={() => onNavigateTool("payments")}
              style={{
                padding: "8px 13px",
                borderRadius: "9px",
                background: "rgba(245, 158, 11, 0.16)",
                border: "1px solid rgba(251, 191, 36, 0.35)",
                color: "#FBBF24",
                fontSize: "11px",
                fontWeight: 700,
              }}
            >
              Payment Verification Queue ({financials.pendingVerificationQueueCount})
            </button>
            <button
              type="button"
              onClick={() => onNavigateTool("attendees")}
              style={{
                padding: "8px 13px",
                borderRadius: "9px",
                background: "rgba(56, 189, 248, 0.16)",
                border: "1px solid rgba(56, 189, 248, 0.35)",
                color: "#38BDF8",
                fontSize: "11px",
                fontWeight: 700,
              }}
            >
              Attendee Check-In Manifest →
            </button>
          </div>
        </div>

        {/* 4 Metric Strip */}
        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))",
            gap: "12px",
          }}
        >
          <div
            style={{
              padding: "13px 15px",
              borderRadius: "11px",
              background: "#0F172A",
              border: "1px solid rgba(52, 211, 153, 0.3)",
            }}
          >
            <span style={{ fontSize: "10px", color: "#34D399", fontWeight: 800 }}>
              CONFIRMED TICKETS SOLD
            </span>
            <div style={{ fontSize: "22px", fontWeight: 800, color: "#FFFFFF", marginTop: "4px" }}>
              {ticketStats.confirmedTicketsCount} Orders ({ticketStats.confirmedPortionsCount} Portions)
            </div>
            <span style={{ fontSize: "11px", color: "#94A3B8" }}>
              Confirmed revenue: {formatMoney(financials.confirmedPayments, currency)}
            </span>
          </div>

          <div
            style={{
              padding: "13px 15px",
              borderRadius: "11px",
              background: "#0F172A",
              border: "1px solid rgba(251, 191, 36, 0.3)",
            }}
          >
            <span style={{ fontSize: "10px", color: "#FBBF24", fontWeight: 800 }}>
              RESERVED / PENDING ORDERS
            </span>
            <div style={{ fontSize: "22px", fontWeight: 800, color: "#FDE68A", marginTop: "4px" }}>
              {ticketStats.reservedPendingTicketsCount} Orders ({ticketStats.reservedPendingPortionsCount} Portions)
            </div>
            <span style={{ fontSize: "11px", color: "#94A3B8" }}>
              Pending value: {formatMoney(financials.pendingPayments, currency)}
            </span>
          </div>

          <div
            style={{
              padding: "13px 15px",
              borderRadius: "11px",
              background: "#0F172A",
              border: "1px solid rgba(129, 140, 248, 0.3)",
            }}
          >
            <span style={{ fontSize: "10px", color: "#A5B4FC", fontWeight: 800 }}>
              VERIFIED CLEARANCE RATIO
            </span>
            <div style={{ fontSize: "22px", fontWeight: 800, color: "#E0E7FF", marginTop: "4px" }}>
              {ticketStats.confirmedClearanceRatePercent !== null
                ? `${ticketStats.confirmedClearanceRatePercent}%`
                : "N/A"}
            </div>
            <span style={{ fontSize: "11px", color: "#94A3B8" }}>
              {ticketStats.confirmedTicketsCount} of {ticketStats.totalActiveOrdersCount} active orders cleared
            </span>
          </div>

          <div
            style={{
              padding: "13px 15px",
              borderRadius: "11px",
              background: "#0F172A",
              border: "1px solid rgba(248, 113, 113, 0.3)",
            }}
          >
            <span style={{ fontSize: "10px", color: "#F87171", fontWeight: 800 }}>
              REFUNDED & REJECTED
            </span>
            <div style={{ fontSize: "22px", fontWeight: 800, color: "#FCA5A5", marginTop: "4px" }}>
              {financials.orderRefundsCount + financials.rejectedOrdersCount} Orders
            </div>
            <span style={{ fontSize: "11px", color: "#94A3B8" }}>
              Refunded total: {formatMoney(financials.orderRefundsAmount, currency)}
            </span>
          </div>
        </div>
      </section>

      {/* Search & Filter Controls + Orders Table */}
      <section
        style={{
          background: "#0B1120",
          border: "1px solid rgba(148, 163, 184, 0.18)",
          borderRadius: "16px",
          padding: "20px 22px",
        }}
      >
        <div
          style={{
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            gap: "12px",
            marginBottom: "16px",
            flexWrap: "wrap",
          }}
        >
          <div
            style={{
              display: "flex",
              alignItems: "center",
              gap: "8px",
              background: "#0F172A",
              border: "1px solid rgba(148, 163, 184, 0.28)",
              borderRadius: "10px",
              padding: "0 12px",
              height: "38px",
              minWidth: "260px",
              flex: "1 1 280px",
              maxWidth: "420px",
            }}
          >
            <Search size={15} color="#94A3B8" />
            <input
              type="text"
              placeholder="Search customer, order #, ticket code, phone or bank ref..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              style={{
                width: "100%",
                background: "transparent",
                border: "none",
                outline: "none",
                color: "#F8FAFC",
                fontSize: "12px",
              }}
            />
          </div>

          <div style={{ display: "flex", alignItems: "center", gap: "6px", flexWrap: "wrap" }}>
            <Filter size={14} color="#94A3B8" />
            {(
              [
                { id: "all", label: `All (${orders.length})` },
                { id: "confirmed", label: "Confirmed Paid" },
                { id: "pending", label: "Pending Verification" },
                { id: "partially_paid", label: "Partially Paid" },
                { id: "refunded", label: "Refunded" },
                { id: "rejected", label: "Rejected" },
              ] as const
            ).map((f) => (
              <button
                key={f.id}
                type="button"
                onClick={() => setStatusFilter(f.id)}
                style={{
                  padding: "6px 11px",
                  borderRadius: "8px",
                  fontSize: "11px",
                  fontWeight: 700,
                  background:
                    statusFilter === f.id
                      ? "#4F46E5"
                      : "rgba(30, 41, 59, 0.75)",
                  color: statusFilter === f.id ? "#FFFFFF" : "#CBD5E1",
                  border:
                    statusFilter === f.id
                      ? "1px solid #818CF8"
                      : "1px solid rgba(148, 163, 184, 0.2)",
                }}
              >
                {f.label}
              </button>
            ))}
          </div>
        </div>

        <div style={{ overflowX: "auto" }}>
          <table style={{ width: "100%", borderCollapse: "collapse", textAlign: "left" }}>
            <thead>
              <tr style={{ borderBottom: "1px solid rgba(148, 163, 184, 0.2)" }}>
                <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                  Order & Ticket
                </th>
                <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                  Customer
                </th>
                <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                  Items & Portions
                </th>
                <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                  Financial Breakdown
                </th>
                <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                  Payment & Fulfillment Status
                </th>
                <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase", textAlign: "right" }}>
                  Host Actions
                </th>
              </tr>
            </thead>
            <tbody>
              {filteredOrders.map((order) => {
                const isPaid = order.status === "paid";
                const isPartial = order.status === "partially_paid";
                const isPending =
                  order.status === "payment_submitted" ||
                  order.status === "under_verification" ||
                  order.status === "pending_verification" ||
                  order.status === "awaiting_payment";
                const isRefunded = order.status === "refunded";
                const confirmedVal = isPaid
                  ? order.totalAmount
                  : isPartial
                  ? order.amountPaid || 0
                  : 0;
                const pendingVal = isPartial
                  ? Math.max(0, order.totalAmount - (order.amountPaid || 0))
                  : isPending
                  ? order.totalAmount
                  : 0;

                return (
                  <tr
                    key={order.id}
                    style={{
                      borderBottom: "1px solid rgba(148, 163, 184, 0.12)",
                      background: isPending ? "rgba(245, 158, 11, 0.04)" : "transparent",
                    }}
                  >
                    <td style={{ padding: "12px", verticalAlign: "top" }}>
                      <strong style={{ fontSize: "12px", color: "#F8FAFC", display: "block" }}>
                        {order.orderNumber}
                      </strong>
                      <code
                        style={{
                          fontSize: "10px",
                          color: "#34D399",
                          background: "rgba(16, 185, 129, 0.12)",
                          padding: "2px 6px",
                          borderRadius: "4px",
                          display: "inline-block",
                          marginTop: "3px",
                        }}
                      >
                        {order.ticketCode}
                      </code>
                      <div style={{ fontSize: "10px", color: "#64748B", marginTop: "3px" }}>
                        {order.method.toUpperCase()}
                        {order.paymentReference ? ` · ${order.paymentReference}` : ""}
                      </div>
                    </td>

                    <td style={{ padding: "12px", verticalAlign: "top" }}>
                      <strong style={{ fontSize: "12px", color: "#F8FAFC", display: "block" }}>
                        {order.customerName}
                      </strong>
                      <span style={{ fontSize: "11px", color: "#94A3B8", display: "block" }}>
                        {order.customerPhone}
                      </span>
                      {order.email && (
                        <span style={{ fontSize: "10px", color: "#64748B" }}>{order.email}</span>
                      )}
                    </td>

                    <td style={{ padding: "12px", verticalAlign: "top", maxWidth: "260px" }}>
                      <div style={{ display: "flex", flexDirection: "column", gap: "4px" }}>
                        {order.items.map((item, idx) => (
                          <div key={idx} style={{ fontSize: "11px", color: "#E2E8F0" }}>
                            <strong>{item.quantity}x</strong> {item.name}
                            {item.selectedOptionsSummary.length > 0 && (
                              <div style={{ fontSize: "10px", color: "#94A3B8" }}>
                                {item.selectedOptionsSummary.join(" · ")}
                              </div>
                            )}
                          </div>
                        ))}
                      </div>
                    </td>

                    <td style={{ padding: "12px", verticalAlign: "top" }}>
                      <div style={{ fontSize: "12px", fontWeight: 800, color: "#F8FAFC" }}>
                        Total: {formatMoney(order.totalAmount, order.currency)}
                      </div>
                      <div style={{ fontSize: "10px", color: "#34D399", marginTop: "2px" }}>
                        Confirmed: {formatMoney(confirmedVal, order.currency)}
                      </div>
                      {pendingVal > 0 && (
                        <div style={{ fontSize: "10px", color: "#FBBF24", marginTop: "2px" }}>
                          Pending: {formatMoney(pendingVal, order.currency)}
                        </div>
                      )}
                      {isRefunded && (
                        <div style={{ fontSize: "10px", color: "#F87171", marginTop: "2px" }}>
                          Refunded:{" "}
                          {formatMoney(
                            order.refundDetails?.amount ?? order.totalAmount,
                            order.currency
                          )}
                        </div>
                      )}
                    </td>

                    <td style={{ padding: "12px", verticalAlign: "top" }}>
                      <span
                        style={{
                          display: "inline-block",
                          padding: "3px 8px",
                          borderRadius: "6px",
                          fontSize: "10px",
                          fontWeight: 800,
                          textTransform: "uppercase",
                          background: isPaid
                            ? "rgba(16, 185, 129, 0.18)"
                            : isPartial
                            ? "rgba(56, 189, 248, 0.18)"
                            : isPending
                            ? "rgba(245, 158, 11, 0.2)"
                            : "rgba(239, 68, 68, 0.2)",
                          color: isPaid
                            ? "#34D399"
                            : isPartial
                            ? "#38BDF8"
                            : isPending
                            ? "#FBBF24"
                            : "#F87171",
                        }}
                      >
                        {order.status.replace(/_/g, " ")}
                      </span>

                      <div style={{ marginTop: "6px" }}>
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
                            color: "#CBD5E1",
                            border: "1px solid rgba(148, 163, 184, 0.28)",
                            borderRadius: "6px",
                            padding: "3px 7px",
                            fontSize: "10px",
                            fontWeight: 600,
                          }}
                        >
                          <option value="unprepared">Unprepared</option>
                          <option value="preparing">Preparing</option>
                          <option value="ready_for_pickup">Ready for Pickup</option>
                          <option value="collected">Collected / Checked In</option>
                        </select>
                      </div>
                    </td>

                    <td style={{ padding: "12px", verticalAlign: "top", textAlign: "right" }}>
                      <div
                        style={{
                          display: "flex",
                          alignItems: "center",
                          justifyContent: "flex-end",
                          gap: "6px",
                          flexWrap: "wrap",
                        }}
                      >
                        {permissions.canVerifyPaymentsAndCash && !isPaid && !isRefunded && (
                          <>
                            <button
                              type="button"
                              onClick={() =>
                                onUpdatePaymentStatus(
                                  order.id,
                                  "paid",
                                  "Verified in Orders Ledger",
                                  order.totalAmount
                                )
                              }
                              style={{
                                padding: "5px 9px",
                                borderRadius: "6px",
                                background: "#10B981",
                                color: "#052E16",
                                border: "none",
                                fontSize: "10px",
                                fontWeight: 800,
                              }}
                            >
                              Verify Paid
                            </button>
                            <button
                              type="button"
                              onClick={() => onOpenPartialModal(order)}
                              style={{
                                padding: "5px 8px",
                                borderRadius: "6px",
                                background: "rgba(56, 189, 248, 0.16)",
                                color: "#38BDF8",
                                border: "1px solid rgba(56, 189, 248, 0.35)",
                                fontSize: "10px",
                                fontWeight: 700,
                              }}
                            >
                              Partial
                            </button>
                            <button
                              type="button"
                              onClick={() => onOpenCashModal(order)}
                              style={{
                                padding: "5px 8px",
                                borderRadius: "6px",
                                background: "rgba(16, 185, 129, 0.15)",
                                color: "#34D399",
                                border: "1px solid rgba(52, 211, 153, 0.35)",
                                fontSize: "10px",
                                fontWeight: 700,
                              }}
                            >
                              Cash
                            </button>
                            <button
                              type="button"
                              onClick={() => onOpenRejectModal(order)}
                              style={{
                                padding: "5px 8px",
                                borderRadius: "6px",
                                background: "rgba(239, 68, 68, 0.16)",
                                color: "#FCA5A5",
                                border: "1px solid rgba(248, 113, 113, 0.35)",
                                fontSize: "10px",
                                fontWeight: 700,
                              }}
                            >
                              Reject
                            </button>
                          </>
                        )}

                        <button
                          type="button"
                          onClick={() => onOpenCustomMessageModal(order)}
                          style={{
                            padding: "5px 8px",
                            borderRadius: "6px",
                            background: "rgba(99, 102, 241, 0.16)",
                            color: "#A5B4FC",
                            border: "1px solid rgba(129, 140, 248, 0.35)",
                            fontSize: "10px",
                            fontWeight: 700,
                            display: "inline-flex",
                            alignItems: "center",
                            gap: "4px",
                          }}
                        >
                          <MessageSquare size={11} />
                          Message
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}
