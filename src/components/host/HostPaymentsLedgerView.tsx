import React from "react";
import {
  Lock,
  Plus,
  ShieldCheck,
} from "lucide-react";
import {
  CashPaymentRecord,
  Currency,
  EventDetails,
  OrderStatus,
  TicketOrder,
} from "../../types";
import { HostFinancialBreakdown, HostRolePermissions } from "./types";

type Props = {
  eventDetails: EventDetails;
  orders: TicketOrder[];
  cashLedger: CashPaymentRecord[];
  permissions: HostRolePermissions;
  financials: HostFinancialBreakdown;
  cashFormName: string;
  setCashFormName: (v: string) => void;
  cashFormPhone: string;
  setCashFormPhone: (v: string) => void;
  cashFormOrderNum: string;
  setCashFormOrderNum: (v: string) => void;
  cashFormAmount: string;
  setCashFormAmount: (v: string) => void;
  cashFormCollector: string;
  setCashFormCollector: (v: string) => void;
  cashFormNotes: string;
  setCashFormNotes: (v: string) => void;
  onRecordCashSubmit: (e: React.FormEvent) => void;
  reversingReceiptId: string | null;
  setReversingReceiptId: (id: string | null) => void;
  reversalSupervisor: string;
  setReversalSupervisor: (v: string) => void;
  reversalReason: string;
  setReversalReason: (v: string) => void;
  onReverseCashPayment: (receiptId: string, reversedBy: string, reason: string) => void;
  onUpdatePaymentStatus: (
    orderId: string,
    status: OrderStatus,
    notes?: string,
    amountPaid?: number
  ) => void;
  onOpenPartialModal: (order: TicketOrder) => void;
  onOpenCashModal: (order: TicketOrder) => void;
  onOpenRejectModal: (order: TicketOrder) => void;
  onOpenCustomMessageModal: (order: TicketOrder) => void;
  formatMoney: (amount: number, currency: Currency) => string;
};

export function HostPaymentsLedgerView({
  eventDetails,
  orders,
  cashLedger,
  permissions,
  financials,
  cashFormName,
  setCashFormName,
  cashFormPhone,
  setCashFormPhone,
  cashFormOrderNum,
  setCashFormOrderNum,
  cashFormAmount,
  setCashFormAmount,
  cashFormCollector,
  setCashFormCollector,
  cashFormNotes,
  setCashFormNotes,
  onRecordCashSubmit,
  reversingReceiptId,
  setReversingReceiptId,
  reversalSupervisor,
  setReversalSupervisor,
  reversalReason,
  setReversalReason,
  onReverseCashPayment,
  onUpdatePaymentStatus,
  onOpenPartialModal,
  onOpenCashModal,
  onOpenRejectModal,
  onOpenCustomMessageModal,
  formatMoney,
}: Props) {
  return (
    <div id="host-payments-ledger-view" style={{ display: "flex", flexDirection: "column", gap: "18px" }}>
      {/* Top Banner: Pending Payments & Confirmed Revenue Separation */}
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
            gap: "12px",
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
                color: "#FBBF24",
                textTransform: "uppercase",
              }}
            >
              Pending Payments Detail Page & Cash Ledger
            </span>
            <h2 style={{ margin: "3px 0 4px", fontSize: "19px", fontWeight: 800, color: "#F8FAFC" }}>
              Payment Verification, Cash Receipts & Settlement Control
            </h2>
            <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
              Defined Calculation: <strong>Pending Payments ({formatMoney(financials.pendingPayments, financials.currency)})</strong> includes{" "}
              {financials.pendingVerificationQueueCount} transfers in review, {financials.awaitingPaymentOrdersCount} awaiting payment, and partial balances due. Pending money is never counted as received revenue until verified.
            </p>
          </div>

          <span
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "6px",
              padding: "6px 12px",
              borderRadius: "999px",
              background: "rgba(16, 185, 129, 0.14)",
              border: "1px solid rgba(52, 211, 153, 0.35)",
              color: "#34D399",
              fontSize: "11px",
              fontWeight: 700,
            }}
          >
            <ShieldCheck size={14} />
            Zero Unverified Revenue Guarantee
          </span>
        </div>

        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(195px, 1fr))",
            gap: "12px",
          }}
        >
          <div
            style={{
              padding: "13px 15px",
              borderRadius: "11px",
              background: "#0F172A",
              border: "1px solid rgba(52, 211, 153, 0.35)",
            }}
          >
            <span style={{ fontSize: "10px", color: "#34D399", fontWeight: 800 }}>
              CONFIRMED PAYMENTS
            </span>
            <div style={{ fontSize: "21px", fontWeight: 800, color: "#FFFFFF", marginTop: "4px" }}>
              {formatMoney(financials.confirmedPayments, financials.currency)}
            </div>
            <span style={{ fontSize: "10px", color: "#94A3B8" }}>
              {financials.confirmedOrdersCount} verified orders (paid + partial deposits)
            </span>
          </div>

          <div
            style={{
              padding: "13px 15px",
              borderRadius: "11px",
              background: "#0F172A",
              border: "1px solid rgba(251, 191, 36, 0.35)",
            }}
          >
            <span style={{ fontSize: "10px", color: "#FBBF24", fontWeight: 800 }}>
              PENDING PAYMENTS
            </span>
            <div style={{ fontSize: "21px", fontWeight: 800, color: "#FDE68A", marginTop: "4px" }}>
              {formatMoney(financials.pendingPayments, financials.currency)}
            </div>
            <span style={{ fontSize: "10px", color: "#94A3B8" }}>
              {financials.pendingVerificationQueueCount} submitted · {financials.awaitingPaymentOrdersCount} awaiting
            </span>
          </div>

          <div
            style={{
              padding: "13px 15px",
              borderRadius: "11px",
              background: "#0F172A",
              border: "1px dashed rgba(129, 140, 248, 0.4)",
            }}
          >
            <span style={{ fontSize: "10px", color: "#A5B4FC", fontWeight: 800 }}>
              FORECAST REVENUE
            </span>
            <div style={{ fontSize: "21px", fontWeight: 800, color: "#E0E7FF", marginTop: "4px" }}>
              {formatMoney(financials.forecastRevenue, financials.currency)}
            </div>
            <span style={{ fontSize: "10px", color: "#94A3B8" }}>
              Projected if all {financials.activePipelineOrdersCount} active orders clear
            </span>
          </div>

          <div
            style={{
              padding: "13px 15px",
              borderRadius: "11px",
              background: "#0F172A",
              border: "1px solid rgba(248, 113, 113, 0.35)",
            }}
          >
            <span style={{ fontSize: "10px", color: "#F87171", fontWeight: 800 }}>
              REFUNDS & REVERSALS
            </span>
            <div style={{ fontSize: "21px", fontWeight: 800, color: "#FCA5A5", marginTop: "4px" }}>
              {formatMoney(financials.refundsTotal, financials.currency)}
            </div>
            <span style={{ fontSize: "10px", color: "#94A3B8" }}>
              {financials.orderRefundsCount} refunded orders · {financials.cashReversalsCount} cash reversals
            </span>
          </div>

          <div
            style={{
              padding: "13px 15px",
              borderRadius: "11px",
              background: "#0F172A",
              border: "1px solid rgba(56, 189, 248, 0.35)",
            }}
          >
            <span style={{ fontSize: "10px", color: "#38BDF8", fontWeight: 800 }}>
              SETTLED FUNDS (NET)
            </span>
            <div style={{ fontSize: "21px", fontWeight: 800, color: "#E0F2FE", marginTop: "4px" }}>
              {formatMoney(financials.settledFunds, financials.currency)}
            </div>
            <span style={{ fontSize: "10px", color: "#94A3B8" }}>
              Cash ledger in-hand: {formatMoney(financials.settledCashInHand, financials.currency)}
            </span>
          </div>
        </div>
      </section>

      {!permissions.canVerifyPaymentsAndCash && (
        <div
          style={{
            padding: "14px 18px",
            borderRadius: "12px",
            background: "rgba(239, 68, 68, 0.14)",
            border: "1px solid rgba(248, 113, 113, 0.4)",
            color: "#FCA5A5",
            fontSize: "12px",
            display: "flex",
            alignItems: "center",
            gap: "10px",
          }}
        >
          <Lock size={16} />
          <span>
            <strong>Separation of Duties Enforced:</strong> Your current role (
            {permissions.roleLabel}) is read-only on financial verification. Switch to{" "}
            <strong>Event Host</strong> or <strong>Payment Verifier</strong> to approve transfers or log cash receipts.
          </span>
        </div>
      )}

      {/* Split Layout: Left = Record Cash Receipt & Cash Ledger, Right = Payment Verification Queue */}
      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(360px, 1fr))",
          gap: "18px",
          alignItems: "start",
        }}
      >
        {/* Left: Cash Receipt Form & Immutable Cash Ledger */}
        <section
          style={{
            background: "#0B1120",
            border: "1px solid rgba(148, 163, 184, 0.18)",
            borderRadius: "16px",
            padding: "20px 22px",
          }}
        >
          <span style={{ fontSize: "10px", fontWeight: 800, color: "#34D399", textTransform: "uppercase" }}>
            In-Person Box Office
          </span>
          <h3 style={{ margin: "2px 0 4px", fontSize: "16px", fontWeight: 800, color: "#F8FAFC" }}>
            Record Official Cash Payment Receipt
          </h3>
          <p style={{ margin: "0 0 14px", fontSize: "11px", color: "#94A3B8" }}>
            Generates an immutable receipt record and automatically updates linked orders to PAID.
          </p>

          <form
            onSubmit={onRecordCashSubmit}
            style={{
              display: "flex",
              flexDirection: "column",
              gap: "10px",
              padding: "14px",
              borderRadius: "12px",
              background: "#0F172A",
              border: "1px solid rgba(148, 163, 184, 0.18)",
            }}
          >
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "10px" }}>
              <div>
                <label style={{ display: "block", fontSize: "10px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                  Customer Name *
                </label>
                <input
                  type="text"
                  required
                  disabled={!permissions.canVerifyPaymentsAndCash}
                  placeholder="e.g. Koffee Jam"
                  value={cashFormName}
                  onChange={(e) => setCashFormName(e.target.value)}
                  style={{
                    width: "100%",
                    height: "36px",
                    padding: "0 10px",
                    borderRadius: "7px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
              </div>
              <div>
                <label style={{ display: "block", fontSize: "10px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                  Phone Number *
                </label>
                <input
                  type="text"
                  required
                  disabled={!permissions.canVerifyPaymentsAndCash}
                  placeholder="+1 (876) 555-0622"
                  value={cashFormPhone}
                  onChange={(e) => setCashFormPhone(e.target.value)}
                  style={{
                    width: "100%",
                    height: "36px",
                    padding: "0 10px",
                    borderRadius: "7px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
              </div>
            </div>

            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: "10px" }}>
              <div>
                <label style={{ display: "block", fontSize: "10px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                  Order # (Optional)
                </label>
                <input
                  type="text"
                  disabled={!permissions.canVerifyPaymentsAndCash}
                  placeholder="ORD-7781"
                  value={cashFormOrderNum}
                  onChange={(e) => setCashFormOrderNum(e.target.value)}
                  style={{
                    width: "100%",
                    height: "36px",
                    padding: "0 10px",
                    borderRadius: "7px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
              </div>
              <div>
                <label style={{ display: "block", fontSize: "10px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                  Amount ({eventDetails.currency}) *
                </label>
                <input
                  type="number"
                  step="0.01"
                  required
                  disabled={!permissions.canVerifyPaymentsAndCash}
                  placeholder="1600"
                  value={cashFormAmount}
                  onChange={(e) => setCashFormAmount(e.target.value)}
                  style={{
                    width: "100%",
                    height: "36px",
                    padding: "0 10px",
                    borderRadius: "7px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
              </div>
              <div>
                <label style={{ display: "block", fontSize: "10px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                  Collector Name *
                </label>
                <input
                  type="text"
                  required
                  disabled={!permissions.canVerifyPaymentsAndCash}
                  value={cashFormCollector}
                  onChange={(e) => setCashFormCollector(e.target.value)}
                  style={{
                    width: "100%",
                    height: "36px",
                    padding: "0 10px",
                    borderRadius: "7px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
              </div>
            </div>

            <div>
              <label style={{ display: "block", fontSize: "10px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                Receipt Audit Notes
              </label>
              <input
                type="text"
                disabled={!permissions.canVerifyPaymentsAndCash}
                placeholder="Cash collected at Box Office Tent B..."
                value={cashFormNotes}
                onChange={(e) => setCashFormNotes(e.target.value)}
                style={{
                  width: "100%",
                  height: "36px",
                  padding: "0 10px",
                  borderRadius: "7px",
                  background: "#1E293B",
                  border: "1px solid rgba(148, 163, 184, 0.3)",
                  color: "#F8FAFC",
                  fontSize: "12px",
                }}
              />
            </div>

            <button
              type="submit"
              disabled={!permissions.canVerifyPaymentsAndCash}
              style={{
                height: "38px",
                borderRadius: "8px",
                background: permissions.canVerifyPaymentsAndCash ? "#10B981" : "#334155",
                color: permissions.canVerifyPaymentsAndCash ? "#052E16" : "#94A3B8",
                border: "none",
                fontSize: "12px",
                fontWeight: 800,
                display: "inline-flex",
                alignItems: "center",
                justifyContent: "center",
                gap: "6px",
              }}
            >
              <Plus size={14} />
              Issue Official Cash Receipt
            </button>
          </form>

          {/* Cash Ledger List */}
          <div style={{ marginTop: "16px" }}>
            <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "8px" }}>
              <strong style={{ fontSize: "13px", color: "#F8FAFC" }}>
                Immutable Cash Ledger ({cashLedger.length} Entries)
              </strong>
              <span style={{ fontSize: "11px", color: "#34D399", fontWeight: 700 }}>
                Net Cash In-Hand: {formatMoney(financials.settledCashInHand, financials.currency)}
              </span>
            </div>

            <div style={{ display: "flex", flexDirection: "column", gap: "8px", maxHeight: "340px", overflowY: "auto" }}>
              {cashLedger.map((entry) => {
                const isReversal = entry.transactionType === "reversal";
                return (
                  <div
                    key={entry.id}
                    style={{
                      padding: "10px 12px",
                      borderRadius: "10px",
                      background: "#0F172A",
                      border: isReversal
                        ? "1px solid rgba(248, 113, 113, 0.35)"
                        : "1px solid rgba(148, 163, 184, 0.16)",
                    }}
                  >
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                      <div>
                        <code style={{ fontSize: "11px", color: isReversal ? "#F87171" : "#34D399", fontWeight: 700 }}>
                          {entry.receiptNumber}
                        </code>
                        <strong style={{ fontSize: "12px", color: "#F8FAFC", marginLeft: "8px" }}>
                          {entry.customerName}
                        </strong>
                      </div>
                      <strong style={{ fontSize: "13px", color: isReversal ? "#F87171" : "#34D399" }}>
                        {formatMoney(entry.amount, entry.currency)}
                      </strong>
                    </div>
                    <div style={{ fontSize: "10px", color: "#94A3B8", marginTop: "3px" }}>
                      Officer: {entry.receivedBy} · {entry.notes}
                    </div>

                    {!isReversal && !entry.isReversed && permissions.canVerifyPaymentsAndCash && (
                      <div style={{ marginTop: "6px" }}>
                        {reversingReceiptId === entry.id ? (
                          <div style={{ display: "flex", gap: "6px", flexWrap: "wrap", marginTop: "6px" }}>
                            <input
                              type="text"
                              placeholder="Supervisor name"
                              value={reversalSupervisor}
                              onChange={(e) => setReversalSupervisor(e.target.value)}
                              style={{
                                height: "28px",
                                padding: "0 8px",
                                borderRadius: "5px",
                                background: "#1E293B",
                                border: "1px solid rgba(148, 163, 184, 0.3)",
                                color: "#F8FAFC",
                                fontSize: "10px",
                              }}
                            />
                            <input
                              type="text"
                              placeholder="Reason for reversal"
                              value={reversalReason}
                              onChange={(e) => setReversalReason(e.target.value)}
                              style={{
                                height: "28px",
                                padding: "0 8px",
                                borderRadius: "5px",
                                background: "#1E293B",
                                border: "1px solid rgba(148, 163, 184, 0.3)",
                                color: "#F8FAFC",
                                fontSize: "10px",
                                flex: 1,
                              }}
                            />
                            <button
                              type="button"
                              onClick={() => {
                                if (reversalReason.trim()) {
                                  onReverseCashPayment(
                                    entry.id,
                                    reversalSupervisor || "Supervisor",
                                    reversalReason
                                  );
                                  setReversingReceiptId(null);
                                  setReversalReason("");
                                }
                              }}
                              style={{
                                padding: "4px 8px",
                                borderRadius: "5px",
                                background: "#EF4444",
                                color: "#FFFFFF",
                                border: "none",
                                fontSize: "10px",
                                fontWeight: 700,
                              }}
                            >
                              Confirm Reversal
                            </button>
                            <button
                              type="button"
                              onClick={() => setReversingReceiptId(null)}
                              style={{
                                padding: "4px 8px",
                                borderRadius: "5px",
                                background: "#334155",
                                color: "#CBD5E1",
                                border: "none",
                                fontSize: "10px",
                              }}
                            >
                              Cancel
                            </button>
                          </div>
                        ) : (
                          <button
                            type="button"
                            onClick={() => setReversingReceiptId(entry.id)}
                            style={{
                              padding: "3px 8px",
                              borderRadius: "5px",
                              background: "rgba(239, 68, 68, 0.14)",
                              color: "#FCA5A5",
                              border: "1px solid rgba(248, 113, 113, 0.3)",
                              fontSize: "10px",
                              fontWeight: 600,
                            }}
                          >
                            Log Supervisor Reversal
                          </button>
                        )}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          </div>
        </section>

        {/* Right: Order Payment Verification & Settlement Status */}
        <section
          style={{
            background: "#0B1120",
            border: "1px solid rgba(148, 163, 184, 0.18)",
            borderRadius: "16px",
            padding: "20px 22px",
          }}
        >
          <span style={{ fontSize: "10px", fontWeight: 800, color: "#FBBF24", textTransform: "uppercase" }}>
            Bank & Digital Transfer Audit
          </span>
          <h3 style={{ margin: "2px 0 4px", fontSize: "16px", fontWeight: 800, color: "#F8FAFC" }}>
            Order Payment Verification Queue ({orders.length} Total Orders)
          </h3>
          <p style={{ margin: "0 0 14px", fontSize: "11px", color: "#94A3B8" }}>
            Cross-reference submitted transaction references against your bank statement before activating QR tickets.
          </p>

          <div style={{ display: "flex", flexDirection: "column", gap: "10px", maxHeight: "620px", overflowY: "auto" }}>
            {orders.map((order) => {
              const isPaid = order.status === "paid";
              const isPartial = order.status === "partially_paid";
              const isPending =
                order.status === "payment_submitted" ||
                order.status === "under_verification" ||
                order.status === "pending_verification" ||
                order.status === "awaiting_payment";

              return (
                <div
                  key={order.id}
                  style={{
                    padding: "12px 14px",
                    borderRadius: "11px",
                    background: "#0F172A",
                    border: isPending
                      ? "1px solid rgba(251, 191, 36, 0.38)"
                      : isPaid
                      ? "1px solid rgba(52, 211, 153, 0.25)"
                      : "1px solid rgba(148, 163, 184, 0.18)",
                  }}
                >
                  <div style={{ display: "flex", justifyContent: "space-between", gap: "8px" }}>
                    <div>
                      <strong style={{ fontSize: "13px", color: "#F8FAFC" }}>
                        {order.customerName}
                      </strong>{" "}
                      <code style={{ fontSize: "10px", color: "#A5B4FC" }}>{order.orderNumber}</code>
                      <div style={{ fontSize: "11px", color: "#94A3B8", marginTop: "2px" }}>
                        Method: <strong>{order.method.toUpperCase()}</strong>
                        {order.paymentReference ? ` · Ref: ${order.paymentReference}` : ""}
                        {order.senderName ? ` · Sender: ${order.senderName}` : ""}
                      </div>
                    </div>

                    <div style={{ textAlign: "right" }}>
                      <strong
                        style={{
                          fontSize: "14px",
                          color: isPaid ? "#34D399" : isPending ? "#FBBF24" : "#38BDF8",
                          display: "block",
                        }}
                      >
                        {formatMoney(order.totalAmount, order.currency)}
                      </strong>
                      <span
                        style={{
                          fontSize: "9px",
                          fontWeight: 800,
                          textTransform: "uppercase",
                          color: isPaid ? "#34D399" : isPending ? "#FDE68A" : "#CBD5E1",
                        }}
                      >
                        {order.status.replace(/_/g, " ")}
                      </span>
                    </div>
                  </div>

                  {isPartial && (
                    <div style={{ marginTop: "6px", fontSize: "10px", color: "#38BDF8" }}>
                      Confirmed deposit: {formatMoney(order.amountPaid || 0, order.currency)} ·
                      Pending balance:{" "}
                      {formatMoney(
                        Math.max(0, order.totalAmount - (order.amountPaid || 0)),
                        order.currency
                      )}
                    </div>
                  )}

                  {permissions.canVerifyPaymentsAndCash && (
                    <div
                      style={{
                        marginTop: "10px",
                        paddingTop: "8px",
                        borderTop: "1px solid rgba(148, 163, 184, 0.12)",
                        display: "flex",
                        gap: "6px",
                        flexWrap: "wrap",
                      }}
                    >
                      {!isPaid && (
                        <button
                          type="button"
                          onClick={() =>
                            onUpdatePaymentStatus(
                              order.id,
                              "paid",
                              "Verified in Payment Studio",
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
                          }}
                        >
                          Verify Paid
                        </button>
                      )}
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
                        Partial Paid
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
                      {!isPaid && (
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
                          }}
                        >
                          Reject
                        </button>
                      )}
                      <button
                        type="button"
                        onClick={() => onOpenCustomMessageModal(order)}
                        style={{
                          padding: "5px 9px",
                          borderRadius: "6px",
                          background: "rgba(99, 102, 241, 0.16)",
                          color: "#A5B4FC",
                          border: "1px solid rgba(129, 140, 248, 0.35)",
                          fontSize: "10px",
                          fontWeight: 700,
                          marginLeft: "auto",
                        }}
                      >
                        Message
                      </button>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </section>
      </div>
    </div>
  );
}
