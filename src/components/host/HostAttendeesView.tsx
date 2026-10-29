import { useMemo, useState } from "react";
import {
  QrCode,
  Search,
  ShieldAlert,
} from "lucide-react";
import {
  Currency,
  HandoverAuditEntry,
  OrderFulfillmentStatus,
  TicketOrder,
} from "../../types";
import {
  HostCheckInStats,
  HostNavToolId,
  HostRolePermissions,
} from "./types";

type Props = {
  orders: TicketOrder[];
  currency: Currency;
  permissions: HostRolePermissions;
  checkInStats: HostCheckInStats;
  handoverAuditLogs?: HandoverAuditEntry[];
  onNavigateTool: (tool: HostNavToolId) => void;
  onUpdateFulfillmentStatus: (
    orderId: string,
    fulfillmentStatus: OrderFulfillmentStatus
  ) => void;
  formatMoney: (amount: number, currency: Currency) => string;
};

export function HostAttendeesView({
  orders,
  checkInStats,
  handoverAuditLogs = [],
  onNavigateTool,
  onUpdateFulfillmentStatus,
  formatMoney,
}: Props) {
  const [searchQuery, setSearchQuery] = useState("");
  const [checkInFilter, setCheckInFilter] = useState<
    "all_confirmed" | "checked_in" | "partial" | "awaiting_checkin" | "restricted_unpaid"
  >("all_confirmed");

  const getOrderPortionCounts = (order: TicketOrder) => {
    let total = 0;
    let collected = 0;
    for (const item of order.items) {
      const qty = item.quantity;
      const col =
        item.collectedQuantity !== undefined
          ? item.collectedQuantity
          : item.handoverStatus === "collected"
          ? qty
          : 0;
      total += qty;
      collected += Math.min(qty, col);
    }
    const isFullyDone =
      order.allHandedOver ||
      order.fulfillmentStatus === "collected" ||
      (total > 0 && collected >= total);
    return { total, collected, isFullyDone, isPartial: !isFullyDone && collected > 0 };
  };

  const filteredAttendees = useMemo(() => {
    return orders.filter((order) => {
      const isConfirmed = order.status === "paid" || order.status === "partially_paid";
      const counts = getOrderPortionCounts(order);

      if (checkInFilter === "all_confirmed" && !isConfirmed) return false;
      if (checkInFilter === "checked_in" && (!isConfirmed || !counts.isFullyDone)) return false;
      if (checkInFilter === "partial" && (!isConfirmed || !counts.isPartial)) return false;
      if (
        checkInFilter === "awaiting_checkin" &&
        (!isConfirmed || counts.isFullyDone || counts.isPartial)
      ) {
        return false;
      }
      if (checkInFilter === "restricted_unpaid" && isConfirmed) return false;

      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const matchName = order.customerName.toLowerCase().includes(q);
        const matchCode = order.ticketCode.toLowerCase().includes(q);
        const matchPhone = order.customerPhone.toLowerCase().includes(q);
        if (!matchName && !matchCode && !matchPhone) return false;
      }
      return true;
    });
  }, [orders, checkInFilter, searchQuery]);

  return (
    <div id="host-attendees-view" style={{ display: "flex", flexDirection: "column", gap: "18px" }}>
      {/* Check-In Progress Detail Header */}
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
                color: "#38BDF8",
                textTransform: "uppercase",
              }}
            >
              Check-In Progress Detail Page & Guest Manifest
            </span>
            <h2
              style={{
                margin: "3px 0 4px",
                fontSize: "19px",
                fontWeight: 800,
                color: "#F8FAFC",
              }}
            >
              Attendees & Gate Check-In Progress
            </h2>
            <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
              Defined Calculation: <strong>Check-In Progress ({checkInStats.checkedInOrdersCount}/{checkInStats.confirmedEligibleOrdersCount})</strong> tracks verified paid attendees who have checked in or redeemed meal portions. Unverified/unpaid tickets are blocked at the gate.
            </p>
          </div>

          <button
            type="button"
            onClick={() => onNavigateTool("food_orders")}
            style={{
              padding: "8px 13px",
              borderRadius: "9px",
              background: "rgba(16, 185, 129, 0.16)",
              border: "1px solid rgba(52, 211, 153, 0.35)",
              color: "#34D399",
              fontSize: "11px",
              fontWeight: 700,
            }}
          >
            Open Kitchen Portion Sheet →
          </button>
        </div>

        {/* Progress Bars & Breakdown */}
        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
            gap: "12px",
          }}
        >
          <div
            style={{
              padding: "14px 16px",
              borderRadius: "12px",
              background: "#0F172A",
              border: "1px solid rgba(56, 189, 248, 0.3)",
            }}
          >
            <div style={{ display: "flex", justifyContent: "space-between", fontSize: "11px" }}>
              <span style={{ color: "#38BDF8", fontWeight: 800 }}>ORDER CHECK-IN PROGRESS</span>
              <strong style={{ color: "#FFFFFF" }}>
                {checkInStats.orderCheckInRatePercent ?? 0}%
              </strong>
            </div>
            <div style={{ fontSize: "21px", fontWeight: 800, color: "#FFFFFF", margin: "6px 0" }}>
              {checkInStats.checkedInOrdersCount} / {checkInStats.confirmedEligibleOrdersCount} Attendees
            </div>
            <div
              style={{
                height: "6px",
                borderRadius: "999px",
                background: "#1E293B",
                overflow: "hidden",
              }}
            >
              <div
                style={{
                  width: `${checkInStats.orderCheckInRatePercent ?? 0}%`,
                  height: "100%",
                  background: "#38BDF8",
                }}
              />
            </div>
          </div>

          <div
            style={{
              padding: "14px 16px",
              borderRadius: "12px",
              background: "#0F172A",
              border: "1px solid rgba(52, 211, 153, 0.3)",
            }}
          >
            <div style={{ display: "flex", justifyContent: "space-between", fontSize: "11px" }}>
              <span style={{ color: "#34D399", fontWeight: 800 }}>PORTION REDEMPTION RATE</span>
              <strong style={{ color: "#FFFFFF" }}>
                {checkInStats.portionRedemptionRatePercent ?? 0}%
              </strong>
            </div>
            <div style={{ fontSize: "21px", fontWeight: 800, color: "#FFFFFF", margin: "6px 0" }}>
              {checkInStats.collectedPortions} / {checkInStats.totalConfirmedPortions} Portions
            </div>
            <div
              style={{
                height: "6px",
                borderRadius: "999px",
                background: "#1E293B",
                overflow: "hidden",
              }}
            >
              <div
                style={{
                  width: `${checkInStats.portionRedemptionRatePercent ?? 0}%`,
                  height: "100%",
                  background: "#34D399",
                }}
              />
            </div>
          </div>

          <div
            style={{
              padding: "14px 16px",
              borderRadius: "12px",
              background: "#0F172A",
              border: "1px solid rgba(129, 140, 248, 0.3)",
            }}
          >
            <span style={{ fontSize: "10px", color: "#A5B4FC", fontWeight: 800 }}>
              REMAINING TO SERVE
            </span>
            <div style={{ fontSize: "21px", fontWeight: 800, color: "#E0E7FF", marginTop: "6px" }}>
              {checkInStats.remainingPortions} Paid Portions
            </div>
            <span style={{ fontSize: "11px", color: "#94A3B8" }}>
              {checkInStats.readyForPickupOrdersCount} orders packaged & ready for pickup
            </span>
          </div>
        </div>
      </section>

      {/* Attendee Directory & Check-In Controls */}
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
              minWidth: "250px",
              flex: "1 1 260px",
              maxWidth: "400px",
            }}
          >
            <Search size={15} color="#94A3B8" />
            <input
              type="text"
              placeholder="Search attendee name, ticket code or phone..."
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

          <div style={{ display: "flex", gap: "6px", flexWrap: "wrap" }}>
            {(
              [
                {
                  id: "all_confirmed",
                  label: `Confirmed Guests (${checkInStats.confirmedEligibleOrdersCount})`,
                },
                {
                  id: "checked_in",
                  label: `Fully Checked In (${checkInStats.fullyCollectedOrdersCount})`,
                },
                {
                  id: "partial",
                  label: `Partial Redemption (${checkInStats.partiallyCollectedOrdersCount})`,
                },
                {
                  id: "awaiting_checkin",
                  label: "Awaiting Arrival",
                },
                {
                  id: "restricted_unpaid",
                  label: "Unverified / Restricted",
                },
              ] as const
            ).map((tab) => (
              <button
                key={tab.id}
                type="button"
                onClick={() => setCheckInFilter(tab.id)}
                style={{
                  padding: "6px 11px",
                  borderRadius: "8px",
                  fontSize: "11px",
                  fontWeight: 700,
                  background:
                    checkInFilter === tab.id ? "#4F46E5" : "rgba(30, 41, 59, 0.75)",
                  color: checkInFilter === tab.id ? "#FFFFFF" : "#CBD5E1",
                  border:
                    checkInFilter === tab.id
                      ? "1px solid #818CF8"
                      : "1px solid rgba(148, 163, 184, 0.2)",
                }}
              >
                {tab.label}
              </button>
            ))}
          </div>
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(310px, 1fr))", gap: "12px" }}>
          {filteredAttendees.map((order) => {
            const isConfirmed = order.status === "paid" || order.status === "partially_paid";
            const counts = getOrderPortionCounts(order);
            const pct = counts.total > 0 ? Math.round((counts.collected / counts.total) * 100) : 0;

            return (
              <div
                key={order.id}
                style={{
                  padding: "14px 16px",
                  borderRadius: "12px",
                  background: "#0F172A",
                  border: counts.isFullyDone
                    ? "1px solid rgba(52, 211, 153, 0.4)"
                    : isConfirmed
                    ? "1px solid rgba(148, 163, 184, 0.2)"
                    : "1px solid rgba(251, 191, 36, 0.35)",
                  display: "flex",
                  flexDirection: "column",
                  justifyContent: "space-between",
                  gap: "10px",
                }}
              >
                <div>
                  <div
                    style={{
                      display: "flex",
                      alignItems: "flex-start",
                      justifyContent: "space-between",
                      gap: "8px",
                    }}
                  >
                    <div>
                      <strong style={{ fontSize: "14px", color: "#F8FAFC", display: "block" }}>
                        {order.customerName}
                      </strong>
                      <span style={{ fontSize: "11px", color: "#94A3B8" }}>
                        {order.customerPhone}
                      </span>
                    </div>

                    <span
                      style={{
                        display: "inline-flex",
                        alignItems: "center",
                        gap: "4px",
                        padding: "3px 8px",
                        borderRadius: "6px",
                        fontSize: "9px",
                        fontWeight: 800,
                        background: isConfirmed
                          ? "rgba(16, 185, 129, 0.18)"
                          : "rgba(245, 158, 11, 0.2)",
                        color: isConfirmed ? "#34D399" : "#FBBF24",
                      }}
                    >
                      <QrCode size={11} />
                      {isConfirmed ? "QR ACTIVE" : "GATE BLOCKED"}
                    </span>
                  </div>

                  <div
                    style={{
                      marginTop: "8px",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "space-between",
                      fontSize: "11px",
                    }}
                  >
                    <code
                      style={{
                        padding: "2px 7px",
                        borderRadius: "4px",
                        background: "rgba(30, 41, 59, 0.9)",
                        color: "#38BDF8",
                        fontWeight: 700,
                      }}
                    >
                      {order.ticketCode}
                    </code>
                    <span style={{ color: "#CBD5E1", fontWeight: 700 }}>
                      {formatMoney(order.totalAmount, order.currency)}
                    </span>
                  </div>

                  {/* Portion Redemption Bar */}
                  <div style={{ marginTop: "10px" }}>
                    <div
                      style={{
                        display: "flex",
                        justifyContent: "space-between",
                        fontSize: "10px",
                        color: "#94A3B8",
                        marginBottom: "4px",
                      }}
                    >
                      <span>
                        Portions Redeemed: <strong style={{ color: "#F8FAFC" }}>{counts.collected}/{counts.total}</strong>
                      </span>
                      <strong style={{ color: counts.isFullyDone ? "#34D399" : "#38BDF8" }}>
                        {pct}%
                      </strong>
                    </div>
                    <div
                      style={{
                        height: "5px",
                        borderRadius: "999px",
                        background: "#1E293B",
                        overflow: "hidden",
                      }}
                    >
                      <div
                        style={{
                          width: `${pct}%`,
                          height: "100%",
                          background: counts.isFullyDone ? "#34D399" : "#38BDF8",
                        }}
                      />
                    </div>
                  </div>

                  <div style={{ marginTop: "8px", fontSize: "11px", color: "#CBD5E1" }}>
                    {order.items.map((item, i) => {
                      const col =
                        item.collectedQuantity !== undefined
                          ? item.collectedQuantity
                          : item.handoverStatus === "collected"
                          ? item.quantity
                          : 0;
                      return (
                        <div
                          key={i}
                          style={{
                            display: "flex",
                            justifyContent: "space-between",
                            padding: "2px 0",
                          }}
                        >
                          <span>
                            {item.quantity}x {item.name}
                          </span>
                          <span
                            style={{
                              fontSize: "10px",
                              color: col >= item.quantity ? "#34D399" : "#94A3B8",
                            }}
                          >
                            {col}/{item.quantity} collected
                          </span>
                        </div>
                      );
                    })}
                  </div>
                </div>

                {/* Action Footer */}
                <div
                  style={{
                    paddingTop: "8px",
                    borderTop: "1px solid rgba(148, 163, 184, 0.14)",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    gap: "8px",
                  }}
                >
                  {isConfirmed ? (
                    <>
                      <select
                        aria-label={`Check-in status for ${order.customerName}`}
                        value={order.fulfillmentStatus}
                        onChange={(e) =>
                          onUpdateFulfillmentStatus(
                            order.id,
                            e.target.value as OrderFulfillmentStatus
                          )
                        }
                        style={{
                          flex: 1,
                          background: "#1E293B",
                          color: "#F8FAFC",
                          border: "1px solid rgba(148, 163, 184, 0.3)",
                          borderRadius: "7px",
                          padding: "5px 8px",
                          fontSize: "11px",
                          fontWeight: 700,
                        }}
                      >
                        <option value="unprepared">Awaiting Prep</option>
                        <option value="preparing">In Kitchen Prep</option>
                        <option value="ready_for_pickup">Ready at Pickup Counter</option>
                        <option value="collected">Checked In & Collected</option>
                      </select>
                      {order.fulfillmentStatus !== "collected" && (
                        <button
                          type="button"
                          onClick={() => onUpdateFulfillmentStatus(order.id, "collected")}
                          style={{
                            padding: "5px 10px",
                            borderRadius: "7px",
                            background: "#10B981",
                            color: "#052E16",
                            border: "none",
                            fontSize: "10px",
                            fontWeight: 800,
                          }}
                        >
                          Mark Checked In
                        </button>
                      )}
                    </>
                  ) : (
                    <div
                      style={{
                        fontSize: "10px",
                        color: "#FBBF24",
                        display: "flex",
                        alignItems: "center",
                        gap: "5px",
                      }}
                    >
                      <ShieldAlert size={13} />
                      <span>Payment unverified — verify in Payments before check-in.</span>
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>

        {/* Recent Gate Handover Audit Log */}
        {handoverAuditLogs.length > 0 && (
          <div
            style={{
              marginTop: "20px",
              paddingTop: "16px",
              borderTop: "1px solid rgba(148, 163, 184, 0.16)",
            }}
          >
            <h3 style={{ margin: "0 0 10px", fontSize: "13px", fontWeight: 800, color: "#CBD5E1" }}>
              Recent Gate & Station Redemption Audit Trail
            </h3>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(260px, 1fr))", gap: "8px" }}>
              {handoverAuditLogs.slice(0, 6).map((entry) => (
                <div
                  key={entry.id}
                  style={{
                    padding: "9px 12px",
                    borderRadius: "8px",
                    background: "#0F172A",
                    border: "1px solid rgba(148, 163, 184, 0.14)",
                    fontSize: "11px",
                    color: "#CBD5E1",
                  }}
                >
                  <div style={{ display: "flex", justifyContent: "space-between" }}>
                    <strong>{entry.customerName}</strong>
                    <code style={{ color: "#34D399", fontSize: "10px" }}>{entry.ticketCode}</code>
                  </div>
                  <div style={{ fontSize: "10px", color: "#94A3B8", marginTop: "2px" }}>
                    Redeemed {entry.quantityCollected}x {entry.itemName} at {entry.station} ({entry.staffName})
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </section>
    </div>
  );
}
