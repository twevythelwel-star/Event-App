import { useState } from "react";
import {
  Banknote,
  BarChart3,
  Bell,
  CheckCircle2,
  Clock,
  RefreshCw,
  RotateCcw,
  Send,
  ShieldCheck,
  TrendingUp,
} from "lucide-react";
import {
  CashPaymentRecord,
  Currency,
  NotificationLogEntry,
  OrganizerProfile,
  TicketOrder,
} from "../../types";
import {
  CROSS_TENANT_DEMO_RECORDS,
  NOTIFICATION_TEMPLATES,
} from "../../data/defaultData";
import { HostFinancialBreakdown, HostRolePermissions } from "./types";

type Props = {
  orders: TicketOrder[];
  cashLedger: CashPaymentRecord[];
  notificationLogs: NotificationLogEntry[];
  activeOrganizer: OrganizerProfile;
  permissions: HostRolePermissions;
  financials: HostFinancialBreakdown;
  onRetryNotification: (logId: string) => void;
  onBroadcastEventReminder: () => void;
  formatMoney: (amount: number, currency: Currency) => string;
};

export function HostReportsView({
  orders,
  cashLedger,
  notificationLogs,
  activeOrganizer,
  permissions,
  financials,
  onRetryNotification,
  onBroadcastEventReminder,
  formatMoney,
}: Props) {
  const [reportTab, setReportTab] = useState<
    "financials" | "notifications" | "tenancy_audit"
  >("financials");
  const [selectedTemplateId, setSelectedTemplateId] = useState<string>(
    NOTIFICATION_TEMPLATES[0]?.id || "tmpl-order-received"
  );
  const [tenancyTestStatus, setTenancyTestStatus] = useState<string | null>(null);

  const refundedOrders = orders.filter((o) => o.status === "refunded");
  const reversedCash = cashLedger.filter((r) => r.transactionType === "reversal");
  const activeTemplate =
    NOTIFICATION_TEMPLATES.find((t) => t.id === selectedTemplateId) ||
    NOTIFICATION_TEMPLATES[0];

  return (
    <div id="host-reports-view" style={{ display: "flex", flexDirection: "column", gap: "18px" }}>
      {/* Top Reports Header & Sub-tab Switcher */}
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
          }}
        >
          <div>
            <span
              style={{
                fontSize: "10px",
                fontWeight: 800,
                letterSpacing: "1.4px",
                color: "#34D399",
                textTransform: "uppercase",
              }}
            >
              Sales Detail Page & Operational Reports
            </span>
            <h2 style={{ margin: "3px 0 4px", fontSize: "19px", fontWeight: 800, color: "#F8FAFC" }}>
              Financial Reconciliation, Notification Delivery & Security Reports
            </h2>
            <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
              Strict operational accounting: Confirmed payments, Pending payments, Forecast revenue, Refunds, and Settled funds are calculated and audited separately.
            </p>
          </div>

          <div style={{ display: "flex", gap: "8px", flexWrap: "wrap" }}>
            <button
              type="button"
              onClick={() => setReportTab("financials")}
              style={{
                padding: "8px 13px",
                borderRadius: "9px",
                background:
                  reportTab === "financials"
                    ? "#4F46E5"
                    : "rgba(30, 41, 59, 0.8)",
                border:
                  reportTab === "financials"
                    ? "1px solid #818CF8"
                    : "1px solid rgba(148, 163, 184, 0.25)",
                color: "#FFFFFF",
                fontSize: "11px",
                fontWeight: 700,
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
              }}
            >
              <BarChart3 size={14} />
              <span>Financial Reconciliation</span>
            </button>

            <button
              type="button"
              onClick={() => setReportTab("notifications")}
              style={{
                padding: "8px 13px",
                borderRadius: "9px",
                background:
                  reportTab === "notifications"
                    ? "#4F46E5"
                    : "rgba(30, 41, 59, 0.8)",
                border:
                  reportTab === "notifications"
                    ? "1px solid #818CF8"
                    : "1px solid rgba(148, 163, 184, 0.25)",
                color: "#FFFFFF",
                fontSize: "11px",
                fontWeight: 700,
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
              }}
            >
              <Bell size={14} />
              <span>SMS & Email Delivery Logs ({notificationLogs.length})</span>
            </button>

            <button
              type="button"
              onClick={() => setReportTab("tenancy_audit")}
              style={{
                padding: "8px 13px",
                borderRadius: "9px",
                background:
                  reportTab === "tenancy_audit"
                    ? "#4F46E5"
                    : "rgba(30, 41, 59, 0.8)",
                border:
                  reportTab === "tenancy_audit"
                    ? "1px solid #818CF8"
                    : "1px solid rgba(148, 163, 184, 0.25)",
                color: "#FFFFFF",
                fontSize: "11px",
                fontWeight: 700,
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
              }}
            >
              <ShieldCheck size={14} />
              <span>Tenant & RBAC Audit</span>
            </button>
          </div>
        </div>
      </section>

      {reportTab === "financials" && (
        <>
          {/* 5-Category Financial Operations Reconciliation */}
          <section
            style={{
              background: "#0B1120",
              border: "1px solid rgba(148, 163, 184, 0.18)",
              borderRadius: "16px",
              padding: "20px 22px",
            }}
          >
            <h3 style={{ margin: "0 0 14px", fontSize: "16px", fontWeight: 800, color: "#F8FAFC" }}>
              5-Tier Financial Separation Statement ({financials.currency})
            </h3>

            <div
              style={{
                display: "grid",
                gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))",
                gap: "12px",
                marginBottom: "20px",
              }}
            >
              {/* 1. Confirmed Payments */}
              <div
                style={{
                  padding: "14px 16px",
                  borderRadius: "12px",
                  background: "rgba(6, 78, 59, 0.22)",
                  border: "1px solid rgba(52, 211, 153, 0.35)",
                }}
              >
                <div style={{ display: "flex", justifyContent: "space-between" }}>
                  <span style={{ fontSize: "10px", fontWeight: 800, color: "#34D399" }}>
                    CONFIRMED PAYMENTS
                  </span>
                  <CheckCircle2 size={14} color="#34D399" />
                </div>
                <div style={{ fontSize: "22px", fontWeight: 800, color: "#FFFFFF", marginTop: "6px" }}>
                  {formatMoney(financials.confirmedPayments, financials.currency)}
                </div>
                <div style={{ fontSize: "10px", color: "#A7F3D0", marginTop: "4px" }}>
                  Paid in full ({financials.fullPaidOrdersCount}):{" "}
                  {formatMoney(financials.confirmedFullPaidAmount, financials.currency)}
                  <br />
                  Partial deposits ({financials.partiallyPaidOrdersCount}):{" "}
                  {formatMoney(financials.confirmedPartialDepositsAmount, financials.currency)}
                </div>
              </div>

              {/* 2. Pending Payments */}
              <div
                style={{
                  padding: "14px 16px",
                  borderRadius: "12px",
                  background: "rgba(120, 53, 15, 0.22)",
                  border: "1px solid rgba(251, 191, 36, 0.35)",
                }}
              >
                <div style={{ display: "flex", justifyContent: "space-between" }}>
                  <span style={{ fontSize: "10px", fontWeight: 800, color: "#FBBF24" }}>
                    PENDING PAYMENTS
                  </span>
                  <Clock size={14} color="#FBBF24" />
                </div>
                <div style={{ fontSize: "22px", fontWeight: 800, color: "#FDE68A", marginTop: "6px" }}>
                  {formatMoney(financials.pendingPayments, financials.currency)}
                </div>
                <div style={{ fontSize: "10px", color: "#FDE68A", marginTop: "4px" }}>
                  Submitted transfers: {formatMoney(financials.pendingSubmittedAmount, financials.currency)}
                  <br />
                  Awaiting / Partial due:{" "}
                  {formatMoney(
                    financials.pendingAwaitingTransferAmount +
                      financials.pendingPartialBalanceRemaining,
                    financials.currency
                  )}
                </div>
              </div>

              {/* 3. Forecast Revenue */}
              <div
                style={{
                  padding: "14px 16px",
                  borderRadius: "12px",
                  background: "#0F172A",
                  border: "1px dashed rgba(129, 140, 248, 0.45)",
                }}
              >
                <div style={{ display: "flex", justifyContent: "space-between" }}>
                  <span style={{ fontSize: "10px", fontWeight: 800, color: "#A5B4FC" }}>
                    FORECAST REVENUE
                  </span>
                  <TrendingUp size={14} color="#818CF8" />
                </div>
                <div style={{ fontSize: "22px", fontWeight: 800, color: "#E0E7FF", marginTop: "6px" }}>
                  {formatMoney(financials.forecastRevenue, financials.currency)}
                </div>
                <div style={{ fontSize: "10px", color: "#94A3B8", marginTop: "4px" }}>
                  Projected gross across {financials.activePipelineOrdersCount} active orders (includes uncollected pending payments).
                </div>
              </div>

              {/* 4. Refunds */}
              <div
                style={{
                  padding: "14px 16px",
                  borderRadius: "12px",
                  background: "rgba(127, 29, 29, 0.18)",
                  border: "1px solid rgba(248, 113, 113, 0.35)",
                }}
              >
                <div style={{ display: "flex", justifyContent: "space-between" }}>
                  <span style={{ fontSize: "10px", fontWeight: 800, color: "#F87171" }}>
                    REFUNDS & REVERSALS
                  </span>
                  <RotateCcw size={14} color="#F87171" />
                </div>
                <div style={{ fontSize: "22px", fontWeight: 800, color: "#FCA5A5", marginTop: "6px" }}>
                  {formatMoney(financials.refundsTotal, financials.currency)}
                </div>
                <div style={{ fontSize: "10px", color: "#FCA5A5", marginTop: "4px" }}>
                  Order refunds ({financials.orderRefundsCount}):{" "}
                  {formatMoney(financials.orderRefundsAmount, financials.currency)}
                  <br />
                  Cash reversals ({financials.cashReversalsCount}):{" "}
                  {formatMoney(financials.cashReversalsAmount, financials.currency)}
                </div>
              </div>

              {/* 5. Settled Funds */}
              <div
                style={{
                  padding: "14px 16px",
                  borderRadius: "12px",
                  background: "rgba(12, 74, 110, 0.24)",
                  border: "1px solid rgba(56, 189, 248, 0.35)",
                }}
              >
                <div style={{ display: "flex", justifyContent: "space-between" }}>
                  <span style={{ fontSize: "10px", fontWeight: 800, color: "#38BDF8" }}>
                    SETTLED FUNDS
                  </span>
                  <Banknote size={14} color="#38BDF8" />
                </div>
                <div style={{ fontSize: "22px", fontWeight: 800, color: "#E0F2FE", marginTop: "6px" }}>
                  {formatMoney(financials.settledFunds, financials.currency)}
                </div>
                <div style={{ fontSize: "10px", color: "#7DD3FC", marginTop: "4px" }}>
                  Bank/Card cleared: {formatMoney(financials.settledDigitalAndBank, financials.currency)}
                  <br />
                  Cash ledger in-hand: {formatMoney(financials.settledCashInHand, financials.currency)}
                </div>
              </div>
            </div>

            {/* Breakdown by Payment Rail */}
            <h4 style={{ margin: "0 0 10px", fontSize: "14px", fontWeight: 800, color: "#CBD5E1" }}>
              Breakdown by Payment Method
            </h4>
            <div style={{ overflowX: "auto" }}>
              <table style={{ width: "100%", borderCollapse: "collapse", textAlign: "left" }}>
                <thead>
                  <tr style={{ borderBottom: "1px solid rgba(148, 163, 184, 0.2)" }}>
                    <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                      Payment Rail
                    </th>
                    <th style={{ padding: "10px 12px", fontSize: "10px", color: "#34D399", textTransform: "uppercase" }}>
                      Confirmed Payments (Received)
                    </th>
                    <th style={{ padding: "10px 12px", fontSize: "10px", color: "#FBBF24", textTransform: "uppercase" }}>
                      Pending Payments (Unverified)
                    </th>
                    <th style={{ padding: "10px 12px", fontSize: "10px", color: "#F87171", textTransform: "uppercase" }}>
                      Refunds
                    </th>
                    <th style={{ padding: "10px 12px", fontSize: "10px", color: "#38BDF8", textTransform: "uppercase" }}>
                      Net Settled
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {financials.byMethod.map((row) => (
                    <tr
                      key={row.method}
                      style={{ borderBottom: "1px solid rgba(148, 163, 184, 0.12)" }}
                    >
                      <td style={{ padding: "12px", fontWeight: 700, color: "#F8FAFC", fontSize: "12px" }}>
                        {row.label}
                      </td>
                      <td style={{ padding: "12px", color: "#34D399", fontWeight: 700, fontSize: "12px" }}>
                        {formatMoney(row.confirmedAmount, financials.currency)}{" "}
                        <span style={{ fontSize: "10px", color: "#94A3B8" }}>
                          ({row.confirmedCount} orders)
                        </span>
                      </td>
                      <td style={{ padding: "12px", color: "#FBBF24", fontWeight: 700, fontSize: "12px" }}>
                        {formatMoney(row.pendingAmount, financials.currency)}{" "}
                        <span style={{ fontSize: "10px", color: "#94A3B8" }}>
                          ({row.pendingCount} orders)
                        </span>
                      </td>
                      <td style={{ padding: "12px", color: "#F87171", fontWeight: 700, fontSize: "12px" }}>
                        {formatMoney(row.refundedAmount, financials.currency)}
                      </td>
                      <td style={{ padding: "12px", color: "#38BDF8", fontWeight: 800, fontSize: "12px" }}>
                        {formatMoney(
                          Math.max(0, row.confirmedAmount - row.refundedAmount),
                          financials.currency
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Refunded & Reversed Audit Trail */}
            <div style={{ marginTop: "20px", paddingTop: "16px", borderTop: "1px solid rgba(148, 163, 184, 0.16)" }}>
              <h4 style={{ margin: "0 0 10px", fontSize: "14px", fontWeight: 800, color: "#FCA5A5" }}>
                Refunds & Cash Reversals Audit Trail ({refundedOrders.length + reversedCash.length})
              </h4>
              <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(280px, 1fr))", gap: "10px" }}>
                {refundedOrders.map((ord) => (
                  <div
                    key={ord.id}
                    style={{
                      padding: "12px",
                      borderRadius: "10px",
                      background: "#0F172A",
                      border: "1px solid rgba(248, 113, 113, 0.3)",
                    }}
                  >
                    <div style={{ display: "flex", justifyContent: "space-between" }}>
                      <strong style={{ fontSize: "12px", color: "#F8FAFC" }}>
                        {ord.customerName} ({ord.orderNumber})
                      </strong>
                      <strong style={{ fontSize: "12px", color: "#F87171" }}>
                        -{formatMoney(ord.refundDetails?.amount ?? ord.totalAmount, ord.currency)}
                      </strong>
                    </div>
                    <div style={{ fontSize: "10px", color: "#94A3B8", marginTop: "4px" }}>
                      Reason: {ord.refundDetails?.reason || "Authorized order refund"} · By:{" "}
                      {ord.refundDetails?.refundedBy || "Supervisor"}
                    </div>
                  </div>
                ))}
                {reversedCash.map((rev) => (
                  <div
                    key={rev.id}
                    style={{
                      padding: "12px",
                      borderRadius: "10px",
                      background: "#0F172A",
                      border: "1px solid rgba(248, 113, 113, 0.3)",
                    }}
                  >
                    <div style={{ display: "flex", justifyContent: "space-between" }}>
                      <strong style={{ fontSize: "12px", color: "#F8FAFC" }}>
                        Cash Reversal ({rev.receiptNumber})
                      </strong>
                      <strong style={{ fontSize: "12px", color: "#F87171" }}>
                        -{formatMoney(Math.abs(rev.amount), rev.currency)}
                      </strong>
                    </div>
                    <div style={{ fontSize: "10px", color: "#94A3B8", marginTop: "4px" }}>
                      Reason: {rev.reversalReason || rev.notes} · Supervisor: {rev.receivedBy}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </section>
        </>
      )}

      {reportTab === "notifications" && (
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
            <div>
              <h3 style={{ margin: 0, fontSize: "16px", fontWeight: 800, color: "#F8FAFC" }}>
                SMS & Email Notification Delivery Audit ({notificationLogs.length} Dispatches)
              </h3>
              <p style={{ margin: "2px 0 0", fontSize: "11px", color: "#94A3B8" }}>
                Idempotent queue dispatch with carrier delivery receipt verification.
              </p>
            </div>

            {permissions.canManageSettingsAndBroadcast && (
              <button
                type="button"
                onClick={onBroadcastEventReminder}
                style={{
                  padding: "8px 14px",
                  borderRadius: "8px",
                  background: "#10B981",
                  color: "#052E16",
                  border: "none",
                  fontSize: "11px",
                  fontWeight: 800,
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "6px",
                }}
              >
                <Send size={13} />
                Broadcast 24h Event Reminder
              </button>
            )}
          </div>

          {/* 8-Stage Template Selector */}
          <div style={{ display: "flex", gap: "6px", flexWrap: "wrap", marginBottom: "14px" }}>
            {NOTIFICATION_TEMPLATES.map((tmpl) => (
              <button
                key={tmpl.id}
                type="button"
                onClick={() => setSelectedTemplateId(tmpl.id)}
                style={{
                  padding: "5px 10px",
                  borderRadius: "7px",
                  fontSize: "10px",
                  fontWeight: 700,
                  background:
                    selectedTemplateId === tmpl.id
                      ? "#4F46E5"
                      : "rgba(30, 41, 59, 0.75)",
                  color: selectedTemplateId === tmpl.id ? "#FFFFFF" : "#CBD5E1",
                  border:
                    selectedTemplateId === tmpl.id
                      ? "1px solid #818CF8"
                      : "1px solid rgba(148, 163, 184, 0.2)",
                }}
              >
                Stage {tmpl.stageNumber}: {tmpl.name}
              </button>
            ))}
          </div>

          {activeTemplate && (
            <div
              style={{
                padding: "12px 14px",
                borderRadius: "10px",
                background: "#0F172A",
                border: "1px solid rgba(129, 140, 248, 0.25)",
                marginBottom: "16px",
                fontSize: "11px",
                color: "#CBD5E1",
              }}
            >
              <strong style={{ color: "#F8FAFC" }}>
                Stage {activeTemplate.stageNumber} — {activeTemplate.name}:
              </strong>{" "}
              {activeTemplate.description}
              <div style={{ marginTop: "6px", fontFamily: "monospace", color: "#34D399" }}>
                SMS Template: {activeTemplate.defaultSms}
              </div>
            </div>
          )}

          {/* Delivery Logs List */}
          <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
            {notificationLogs.map((log) => (
              <div
                key={log.id}
                style={{
                  padding: "12px 14px",
                  borderRadius: "10px",
                  background: "#0F172A",
                  border:
                    log.status === "failed"
                      ? "1px solid rgba(248, 113, 113, 0.4)"
                      : "1px solid rgba(148, 163, 184, 0.16)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  gap: "12px",
                  flexWrap: "wrap",
                }}
              >
                <div>
                  <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                    <strong style={{ fontSize: "12px", color: "#F8FAFC" }}>{log.title}</strong>
                    <span
                      style={{
                        fontSize: "9px",
                        fontWeight: 800,
                        padding: "2px 6px",
                        borderRadius: "4px",
                        textTransform: "uppercase",
                        background:
                          log.status === "delivered"
                            ? "rgba(16, 185, 129, 0.18)"
                            : log.status === "failed"
                            ? "rgba(239, 68, 68, 0.2)"
                            : "rgba(56, 189, 248, 0.18)",
                        color:
                          log.status === "delivered"
                            ? "#34D399"
                            : log.status === "failed"
                            ? "#F87171"
                            : "#38BDF8",
                      }}
                    >
                      {log.status}
                    </span>
                  </div>
                  <div style={{ fontSize: "11px", color: "#94A3B8", marginTop: "2px" }}>
                    To: {log.customerName} ({log.recipient}) · Order #{log.orderNumber} ·{" "}
                    {log.provider}
                  </div>
                </div>

                {log.status === "failed" && (
                  <button
                    type="button"
                    onClick={() => onRetryNotification(log.id)}
                    style={{
                      padding: "6px 11px",
                      borderRadius: "7px",
                      background: "rgba(245, 158, 11, 0.18)",
                      border: "1px solid rgba(251, 191, 36, 0.4)",
                      color: "#FBBF24",
                      fontSize: "11px",
                      fontWeight: 700,
                      display: "inline-flex",
                      alignItems: "center",
                      gap: "5px",
                    }}
                  >
                    <RefreshCw size={12} />
                    Retry Dispatch
                  </button>
                )}
              </div>
            ))}
          </div>
        </section>
      )}

      {reportTab === "tenancy_audit" && (
        <section
          style={{
            background: "#0B1120",
            border: "1px solid rgba(148, 163, 184, 0.18)",
            borderRadius: "16px",
            padding: "20px 22px",
          }}
        >
          <h3 style={{ margin: "0 0 8px", fontSize: "16px", fontWeight: 800, color: "#F8FAFC" }}>
            Multi-Tenant Boundary & Role Authorization Audit
          </h3>
          <p style={{ margin: "0 0 14px", fontSize: "12px", color: "#94A3B8" }}>
            Active Tenant Workspace: <strong>{activeOrganizer.name}</strong> (
            <code>{activeOrganizer.id}</code>) · Active Role:{" "}
            <strong>{permissions.roleLabel}</strong>.
          </p>

          <div
            style={{
              padding: "14px 16px",
              borderRadius: "12px",
              background: "#0F172A",
              border: "1px solid rgba(148, 163, 184, 0.2)",
              marginBottom: "14px",
            }}
          >
            <strong style={{ fontSize: "13px", color: "#F8FAFC", display: "block", marginBottom: "8px" }}>
              Cross-Tenant Isolation Verification Probe
            </strong>
            <div style={{ display: "flex", gap: "8px", flexWrap: "wrap" }}>
              <button
                type="button"
                onClick={() =>
                  setTenancyTestStatus(
                    permissions.canViewCrossTenantPlatform
                      ? `ALLOWED (Platform SuperAdmin): Inspected ${CROSS_TENANT_DEMO_RECORDS.portmoreOrder.orderNumber} in ${CROSS_TENANT_DEMO_RECORDS.portmoreOrder.organizerId}.`
                      : `HTTP 403 FORBIDDEN: Tenant '${activeOrganizer.id}' blocked from reading order '${CROSS_TENANT_DEMO_RECORDS.portmoreOrder.orderNumber}' belonging to '${CROSS_TENANT_DEMO_RECORDS.portmoreOrder.organizerId}'.`
                  )
                }
                style={{
                  padding: "7px 12px",
                  borderRadius: "8px",
                  background: "rgba(239, 68, 68, 0.16)",
                  border: "1px solid rgba(248, 113, 113, 0.35)",
                  color: "#FCA5A5",
                  fontSize: "11px",
                  fontWeight: 700,
                }}
              >
                Simulate Cross-Tenant Order Read ({CROSS_TENANT_DEMO_RECORDS.portmoreOrder.orderNumber})
              </button>
            </div>

            {tenancyTestStatus && (
              <div
                style={{
                  marginTop: "10px",
                  padding: "10px 12px",
                  borderRadius: "8px",
                  background: "rgba(15, 23, 42, 0.9)",
                  border: "1px solid rgba(52, 211, 153, 0.35)",
                  color: "#34D399",
                  fontSize: "11px",
                  fontFamily: "monospace",
                }}
              >
                {tenancyTestStatus}
              </div>
            )}
          </div>
        </section>
      )}
    </div>
  );
}
