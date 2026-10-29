import { useState } from "react";
import {
  AlertTriangle,
  ArrowUpRight,
  Calculator,
  CheckCircle2,
  Clock,
  DollarSign,
  Info,
  Receipt,
  Ticket,
  UserCheck,
} from "lucide-react";
import {
  HostNavToolId,
  HostRolePermissions,
  HostSummaryMetricCardSpec,
} from "./types";

type Props = {
  cards: HostSummaryMetricCardSpec[];
  onNavigateTool: (tool: HostNavToolId) => void;
  totalOrdersCount?: number;
  confirmedOrdersCount?: number;
  pendingOrdersCount?: number;
  permissions?: HostRolePermissions;
};

export function HostSummaryMetrics({
  cards,
  onNavigateTool,
  totalOrdersCount,
  confirmedOrdersCount,
  pendingOrdersCount,
  permissions,
}: Props) {
  const [showFormulas, setShowFormulas] = useState(false);

  const getTonePalette = (tone: HostSummaryMetricCardSpec["tone"]) => {
    switch (tone) {
      case "emerald":
        return {
          accent: "#15803D",
          iconBg: "#C7FF45",
          iconColor: "#14210A",
          iconBorder: "#A3E635",
          cardBg: "#FFFFFF",
          borderColor: "#E2E8F0",
          badgeBg: "#ECFCCB",
          badgeColor: "#14210A",
          barColor: "#84CC16",
        };
      case "indigo":
        return {
          accent: "#6D28D9",
          iconBg: "rgba(139, 92, 246, 0.14)",
          iconColor: "#7C3AED",
          iconBorder: "rgba(139, 92, 246, 0.35)",
          cardBg: "#FFFFFF",
          borderColor: "#E2E8F0",
          badgeBg: "#F3E8FF",
          badgeColor: "#6D28D9",
          barColor: "#8B5CF6",
        };
      case "amber":
        return {
          accent: "#B45309",
          iconBg: "#FEF3C7",
          iconColor: "#D97706",
          iconBorder: "#FCD34D",
          cardBg: "#FFFFFF",
          borderColor: "#E2E8F0",
          badgeBg: "#FEF3C7",
          badgeColor: "#92400E",
          barColor: "#F59E0B",
        };
      case "sky":
      default:
        return {
          accent: "#0369A1",
          iconBg: "#E0F2FE",
          iconColor: "#0284C7",
          iconBorder: "#7DD3FC",
          cardBg: "#FFFFFF",
          borderColor: "#E2E8F0",
          badgeBg: "#E0F2FE",
          badgeColor: "#075985",
          barColor: "#0EA5E9",
        };
    }
  };

  const renderCardIcon = (id: HostSummaryMetricCardSpec["id"], color: string) => {
    switch (id) {
      case "sales":
        return <DollarSign size={18} color={color} />;
      case "tickets_sold":
        return <Ticket size={18} color={color} />;
      case "pending_payments":
        return <Clock size={18} color={color} />;
      case "checkin_progress":
        return <UserCheck size={18} color={color} />;
    }
  };

  const derivedTotalOrders =
    totalOrdersCount ??
    (confirmedOrdersCount !== undefined && pendingOrdersCount !== undefined
      ? confirmedOrdersCount + pendingOrdersCount
      : undefined);

  return (
    <section
      id="host-summary-metrics-section"
      style={{
        marginBottom: "22px",
        background: "#F8FAFC",
        border: "1px solid #E2E8F0",
        borderRadius: "16px",
        padding: "18px 20px",
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
          <span
            style={{
              fontSize: "10px",
              fontWeight: 800,
              letterSpacing: "1.4px",
              color: "#14210A",
              background: "#C7FF45",
              padding: "2px 8px",
              borderRadius: "999px",
              textTransform: "uppercase",
              display: "inline-block",
            }}
          >
            Real-Time Operational Telemetry
          </span>
          <h2
            style={{
              margin: "6px 0 0",
              fontSize: "18px",
              fontWeight: 800,
              color: "#0F172A",
              letterSpacing: "-0.3px",
            }}
          >
            Host Summary Metrics
          </h2>
        </div>

        <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
          {derivedTotalOrders !== undefined && (
            <div
              id="host-metric-card-total_orders"
              title="Total Active Orders = Verified Paid Orders + Pending Verification Reservations (excludes refunded and rejected orders)."
              style={{
                display: "inline-flex",
                alignItems: "center",
                gap: "8px",
                padding: "6px 12px",
                borderRadius: "10px",
                background: "#FFFFFF",
                border: "1px solid #CBD5E1",
                color: "#0F172A",
                fontSize: "12px",
                fontWeight: 800,
              }}
            >
              <Receipt size={14} color="#7C3AED" />
              <span>Total Orders:</span>
              <strong
                style={{
                  color: "#6D28D9",
                  fontVariantNumeric: "tabular-nums",
                }}
              >
                {derivedTotalOrders}
              </strong>
            </div>
          )}

          <button
            type="button"
            onClick={() => setShowFormulas((prev) => !prev)}
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "6px",
              padding: "6px 12px",
              borderRadius: "8px",
              background: showFormulas ? "#14210A" : "#FFFFFF",
              border: showFormulas ? "1px solid #14210A" : "1px solid #CBD5E1",
              color: showFormulas ? "#C7FF45" : "#0F172A",
              fontSize: "11px",
              fontWeight: 700,
            }}
          >
            <Calculator size={13} color={showFormulas ? "#C7FF45" : "#7C3AED"} />
            <span>{showFormulas ? "Hide Metric Formulas" : "Inspect Metric Calculations"}</span>
          </button>
        </div>
      </div>

      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(235px, 1fr))",
          gap: "14px",
        }}
      >
        {cards.map((card) => {
          const palette = getTonePalette(card.tone);
          const isPermitted =
            !permissions ||
            card.id === "tickets_sold" ||
            card.id === "checkin_progress" ||
            permissions.canVerifyPaymentsAndCash ||
            permissions.canViewReports;

          return (
            <div
              key={card.id}
              id={`host-metric-card-${card.id}`}
              title={card.calculationDefinition}
              style={{
                background: palette.cardBg,
                border: `1px solid ${palette.borderColor}`,
                borderRadius: "14px",
                padding: "16px 18px",
                display: "flex",
                flexDirection: "column",
                justifyContent: "space-between",
                gap: "12px",
                boxShadow: "0 4px 14px rgba(15, 23, 42, 0.05)",
                opacity: isPermitted ? 1 : 0.85,
              }}
            >
              <div>
                {/* Top header: Title + Icon */}
                <div
                  style={{
                    display: "flex",
                    alignItems: "flex-start",
                    justifyContent: "space-between",
                    gap: "10px",
                  }}
                >
                  <div>
                    <span
                      style={{
                        fontSize: "11px",
                        fontWeight: 800,
                        color: "#475569",
                        letterSpacing: "0.2px",
                        display: "block",
                        textTransform: "uppercase",
                      }}
                    >
                      {card.title}
                    </span>
                    <div
                      style={{
                        marginTop: "6px",
                        fontSize: "24px",
                        fontWeight: 800,
                        color: "#0F172A",
                        letterSpacing: "-0.6px",
                        fontVariantNumeric: "tabular-nums",
                        fontFamily: '"Plus Jakarta Sans", "Inter", sans-serif',
                      }}
                    >
                      {card.formattedValue}
                    </div>
                  </div>

                  <div
                    style={{
                      width: "38px",
                      height: "38px",
                      borderRadius: "10px",
                      background: palette.iconBg,
                      border: `1px solid ${palette.iconBorder}`,
                      display: "grid",
                      placeItems: "center",
                      flexShrink: 0,
                    }}
                  >
                    {renderCardIcon(card.id, palette.iconColor)}
                  </div>
                </div>

                {/* Reliable Operational Ratio / Percentage (only displayed when calculated from real data) */}
                {card.reliablePercentage !== null && card.reliablePercentageLabel && (
                  <div style={{ marginTop: "10px" }}>
                    <div
                      style={{
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                        gap: "6px",
                        marginBottom: "5px",
                      }}
                    >
                      <span
                        style={{
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "5px",
                          fontSize: "10px",
                          fontWeight: 800,
                          padding: "2px 8px",
                          borderRadius: "999px",
                          background: palette.badgeBg,
                          color: palette.badgeColor,
                          border: `1px solid ${palette.iconBorder}`,
                        }}
                      >
                        {card.id === "pending_payments" ? (
                          <AlertTriangle size={11} />
                        ) : (
                          <CheckCircle2 size={11} />
                        )}
                        {card.reliablePercentageLabel}
                      </span>
                      <span
                        style={{
                          fontSize: "11px",
                          fontWeight: 800,
                          color: palette.accent,
                          fontVariantNumeric: "tabular-nums",
                        }}
                      >
                        {card.reliablePercentage}%
                      </span>
                    </div>

                    <div
                      style={{
                        height: "5px",
                        borderRadius: "999px",
                        background: "#F1F5F9",
                        border: "1px solid #E2E8F0",
                        overflow: "hidden",
                      }}
                    >
                      <div
                        style={{
                          width: `${Math.min(100, Math.max(0, card.reliablePercentage))}%`,
                          height: "100%",
                          background: palette.barColor,
                          borderRadius: "999px",
                          transition: "width 0.3s ease",
                        }}
                      />
                    </div>
                  </div>
                )}

                {/* Primary & Secondary subtitles */}
                <p
                  style={{
                    margin: "10px 0 4px",
                    fontSize: "11px",
                    color: "#1E293B",
                    fontWeight: 700,
                    lineHeight: 1.4,
                  }}
                >
                  {card.subtitle}
                </p>
                <p
                  style={{
                    margin: 0,
                    fontSize: "10px",
                    color: "#64748B",
                    lineHeight: 1.4,
                  }}
                >
                  {card.secondaryValueLabel}
                </p>

                {/* Defined Calculation Box */}
                {showFormulas && (
                  <div
                    style={{
                      marginTop: "10px",
                      padding: "8px 10px",
                      borderRadius: "8px",
                      background: "#F8FAFC",
                      border: "1px solid #CBD5E1",
                      fontSize: "10px",
                      color: "#334155",
                      lineHeight: 1.45,
                      display: "flex",
                      alignItems: "flex-start",
                      gap: "6px",
                    }}
                  >
                    <Info size={12} color={palette.accent} style={{ flexShrink: 0, marginTop: "2px" }} />
                    <span>{card.calculationDefinition}</span>
                  </div>
                )}
              </div>

              {/* Relevant Detail Page Link */}
              <div
                style={{
                  paddingTop: "10px",
                  borderTop: "1px solid #F1F5F9",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                }}
              >
                <span style={{ fontSize: "10px", color: "#64748B", fontWeight: 600 }}>
                  Defined ledger calculation
                </span>
                <button
                  type="button"
                  onClick={() => onNavigateTool(card.detailToolId)}
                  style={{
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "4px",
                    padding: "5px 10px",
                    borderRadius: "7px",
                    background: "#0F172A",
                    border: "1px solid #0F172A",
                    color: "#C7FF45",
                    fontSize: "11px",
                    fontWeight: 700,
                  }}
                >
                  <span>{card.detailActionLabel}</span>
                  <ArrowUpRight size={13} />
                </button>
              </div>
            </div>
          );
        })}
      </div>
    </section>
  );
}
