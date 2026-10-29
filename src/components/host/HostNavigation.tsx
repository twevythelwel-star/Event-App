import React, { useState } from "react";
import {
  BarChart3,
  Calendar,
  ChefHat,
  CreditCard,
  LayoutDashboard,
  Lock,
  PanelLeftClose,
  PanelLeftOpen,
  Receipt,
  Settings,
  Ticket,
  UserCheck,
  Users,
} from "lucide-react";
import { HostNavToolId, HostRolePermissions } from "./types";

type NavItemConfig = {
  id: HostNavToolId;
  domId?: string;
  label: string;
  shortDesc: string;
  icon: React.ReactNode;
  badgeCount?: number;
  badgeTone?: "amber" | "emerald" | "indigo" | "sky";
  isTicketsShortcut?: boolean;
};

type Props = {
  activeTool: HostNavToolId;
  onSelectTool: (tool: HostNavToolId) => void;
  permissions: HostRolePermissions;
  upcomingEventsCount: number;
  totalOrdersCount: number;
  pendingPaymentsCount: number;
  checkedInCount: number;
  confirmedEligibleCount: number;
  totalPortionsOrdered: number;
  workersCount: number;
  isCollapsed?: boolean;
  onToggleCollapse?: () => void;
};

export function HostNavigation({
  activeTool,
  onSelectTool,
  permissions,
  upcomingEventsCount,
  totalOrdersCount,
  pendingPaymentsCount,
  checkedInCount,
  confirmedEligibleCount,
  totalPortionsOrdered,
  workersCount,
  isCollapsed: controlledCollapsed,
  onToggleCollapse,
}: Props) {
  const [internalCollapsed, setInternalCollapsed] = useState(false);
  const isCollapsed = controlledCollapsed ?? internalCollapsed;

  const handleToggleCollapse = () => {
    if (onToggleCollapse) {
      onToggleCollapse();
    } else {
      setInternalCollapsed((prev) => !prev);
    }
  };

  const navItems: NavItemConfig[] = [
    {
      id: "dashboard",
      domId: "subnav-tab-dashboard",
      label: "Dashboard",
      shortDesc: "Overview & metrics",
      icon: <LayoutDashboard size={16} />,
    },
    {
      id: "events",
      domId: "subnav-tab-event",
      label: "Events",
      shortDesc: "Upcoming & flyer studio",
      icon: <Calendar size={16} />,
      badgeCount: upcomingEventsCount,
      badgeTone: "indigo",
    },
    {
      id: "events",
      domId: "subnav-tab-tickets",
      label: "Tickets",
      shortDesc: "Tiers, prices & inventory",
      icon: <Ticket size={16} />,
      badgeCount: confirmedEligibleCount,
      badgeTone: "emerald",
      isTicketsShortcut: true,
    },
    {
      id: "orders",
      domId: "subnav-tab-orders",
      label: "Orders",
      shortDesc: "Ticket & meal orders",
      icon: <Receipt size={16} />,
      badgeCount: totalOrdersCount,
      badgeTone: "sky",
    },
    {
      id: "payments",
      domId: "subnav-tab-payments",
      label: "Payments",
      shortDesc: "Verify & cash ledger",
      icon: <CreditCard size={16} />,
      badgeCount: pendingPaymentsCount > 0 ? pendingPaymentsCount : undefined,
      badgeTone: "amber",
    },
    {
      id: "food_orders",
      domId: "subnav-tab-food-orders",
      label: "Menu Management",
      shortDesc: `Food Orders · ${totalPortionsOrdered} portions`,
      icon: <ChefHat size={16} />,
      badgeCount: totalPortionsOrdered,
      badgeTone: "emerald",
    },
    {
      id: "workers",
      domId: "subnav-tab-workers",
      label: "Staff Management",
      shortDesc: "Worker Assignments & shifts",
      icon: <Users size={16} />,
      badgeCount: workersCount,
      badgeTone: "indigo",
    },
    {
      id: "attendees",
      domId: "subnav-tab-attendees",
      label: "Ticket Check-in",
      shortDesc: "Attendees & QR verification",
      icon: <UserCheck size={16} />,
      badgeCount: confirmedEligibleCount,
      badgeTone: "emerald",
    },
    {
      id: "reports",
      domId: "subnav-tab-reports",
      label: "Reports",
      shortDesc: "Revenue, SMS & audit",
      icon: <BarChart3 size={16} />,
    },
    {
      id: "settings",
      domId: "subnav-tab-settings",
      label: "Settings",
      shortDesc: "Account & methods",
      icon: <Settings size={16} />,
    },
  ];

  const getBadgeColors = (tone?: "amber" | "emerald" | "indigo" | "sky") => {
    switch (tone) {
      case "amber":
        return {
          bg: "rgba(245, 158, 11, 0.22)",
          color: "#FBBF24",
          border: "1px solid rgba(245, 158, 11, 0.45)",
        };
      case "emerald":
        return {
          bg: "rgba(199, 255, 69, 0.18)",
          color: "#C7FF45",
          border: "1px solid rgba(199, 255, 69, 0.42)",
        };
      case "sky":
        return {
          bg: "rgba(56, 189, 248, 0.2)",
          color: "#38BDF8",
          border: "1px solid rgba(56, 189, 248, 0.4)",
        };
      default:
        return {
          bg: "rgba(139, 92, 246, 0.22)",
          color: "#C4B5FD",
          border: "1px solid rgba(139, 92, 246, 0.45)",
        };
    }
  };

  return (
    <nav
      id="host-authorized-navigation"
      aria-label="Host Dashboard Navigation"
      style={{
        background: "#07111F",
        border: "1px solid #26384D",
        borderRadius: "16px",
        padding: isCollapsed ? "10px 8px" : "12px 14px",
        boxShadow: "0 12px 30px rgba(2, 6, 23, 0.42)",
      }}
    >
      {/* EventBite Sidebar Brand + Collapse Toggle */}
      <div
        id="eventbite-host-sidebar-brand"
        style={{
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          gap: "10px",
          marginBottom: "10px",
          paddingBottom: "10px",
          borderBottom: "1px solid rgba(38, 56, 77, 0.85)",
          flexWrap: "wrap",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
          <div
            style={{
              width: "32px",
              height: "32px",
              borderRadius: "9px",
              background: "#C7FF45",
              color: "#14210A",
              display: "grid",
              placeItems: "center",
              fontWeight: 900,
              fontSize: "13px",
              boxShadow: "0 4px 12px rgba(199, 255, 69, 0.25)",
              flexShrink: 0,
            }}
          >
            EB
          </div>
          {!isCollapsed && (
            <div>
              <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                <strong
                  style={{
                    fontSize: "14px",
                    fontWeight: 900,
                    color: "#F8FAFC",
                    letterSpacing: "-0.3px",
                  }}
                >
                  EventBite
                </strong>
                <span
                  style={{
                    fontSize: "9px",
                    fontWeight: 800,
                    padding: "2px 6px",
                    borderRadius: "999px",
                    background: "rgba(199, 255, 69, 0.16)",
                    color: "#C7FF45",
                    border: "1px solid rgba(199, 255, 69, 0.35)",
                    textTransform: "uppercase",
                  }}
                >
                  Host Studio
                </span>
              </div>
              <span
                style={{
                  fontSize: "10px",
                  fontWeight: 700,
                  color: "#A8B6C8",
                  display: "block",
                }}
              >
                Authorized Host Tools ({permissions.roleLabel})
              </span>
            </div>
          )}
        </div>

        <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
          {!isCollapsed && (
            <span style={{ fontSize: "11px", color: "#94A3B8" }}>
              Check-in Progress:{" "}
              <strong style={{ color: "#C7FF45" }}>
                {checkedInCount}/{confirmedEligibleCount}
              </strong>{" "}
              verified orders · Pending Verification:{" "}
              <strong style={{ color: pendingPaymentsCount > 0 ? "#FBBF24" : "#34D399" }}>
                {pendingPaymentsCount}
              </strong>
            </span>
          )}
          <button
            type="button"
            id="btn-toggle-host-sidebar"
            aria-label={isCollapsed ? "Expand host navigation" : "Collapse host navigation"}
            onClick={handleToggleCollapse}
            style={{
              height: "30px",
              padding: "0 8px",
              borderRadius: "8px",
              background: "#10243A",
              border: "1px solid #26384D",
              color: "#A8B6C8",
              fontSize: "11px",
              fontWeight: 700,
              display: "inline-flex",
              alignItems: "center",
              gap: "5px",
            }}
          >
            {isCollapsed ? <PanelLeftOpen size={14} /> : <PanelLeftClose size={14} />}
            <span>{isCollapsed ? "Expand" : "Compact"}</span>
          </button>
        </div>
      </div>

      <div
        style={{
          display: "grid",
          gridTemplateColumns: isCollapsed
            ? "repeat(auto-fit, minmax(56px, 1fr))"
            : "repeat(auto-fit, minmax(156px, 1fr))",
          gap: "8px",
        }}
      >
        {navItems.map((item) => {
          const isAuthorized = permissions.authorizedTools[item.id];
          const isActive =
            activeTool === item.id && !item.isTicketsShortcut;
          const badgeStyle = getBadgeColors(item.badgeTone);

          return (
            <button
              key={item.domId || item.id}
              id={item.domId}
              type="button"
              aria-current={isActive ? "page" : undefined}
              onClick={() => onSelectTool(item.id)}
              title={
                isAuthorized
                  ? `${item.label} — ${item.shortDesc}`
                  : `${item.label} (Restricted for ${permissions.roleLabel})`
              }
              style={{
                position: "relative",
                display: "flex",
                alignItems: "center",
                justifyContent: isCollapsed ? "center" : "space-between",
                gap: "8px",
                padding: isCollapsed ? "10px 6px" : "10px 12px",
                borderRadius: "10px",
                textAlign: "left",
                transition: "all 0.15s ease",
                background: isActive
                  ? "rgba(199, 255, 69, 0.14)"
                  : isAuthorized
                  ? "#0B192B"
                  : "rgba(11, 25, 43, 0.55)",
                border: isActive
                  ? "1px solid #C7FF45"
                  : isAuthorized
                  ? "1px solid #26384D"
                  : "1px dashed rgba(132, 148, 168, 0.25)",
                color: isActive ? "#F8FAFC" : isAuthorized ? "#E2E8F0" : "#64748B",
                opacity: isAuthorized ? 1 : 0.72,
                boxShadow: isActive ? "0 4px 16px rgba(199, 255, 69, 0.14)" : "none",
              }}
            >
              {/* Active Left Indicator Bar */}
              {isActive && (
                <span
                  aria-hidden="true"
                  style={{
                    position: "absolute",
                    left: "0",
                    top: "8px",
                    bottom: "8px",
                    width: "3px",
                    borderRadius: "0 4px 4px 0",
                    background: "#C7FF45",
                  }}
                />
              )}

              <div style={{ display: "flex", alignItems: "center", gap: "9px", minWidth: 0 }}>
                <span
                  style={{
                    width: "30px",
                    height: "30px",
                    borderRadius: "8px",
                    display: "grid",
                    placeItems: "center",
                    flexShrink: 0,
                    background: isActive
                      ? "#C7FF45"
                      : isAuthorized
                      ? "#10243A"
                      : "rgba(15, 23, 42, 0.5)",
                    color: isActive ? "#14210A" : isAuthorized ? "#C7FF45" : "#475569",
                    border: isActive
                      ? "1px solid #D7FF77"
                      : "1px solid #26384D",
                  }}
                >
                  {isAuthorized ? item.icon : <Lock size={14} />}
                </span>
                {!isCollapsed && (
                  <div style={{ minWidth: 0 }}>
                    <div
                      style={{
                        fontSize: "12px",
                        fontWeight: 800,
                        whiteSpace: "nowrap",
                        overflow: "hidden",
                        textOverflow: "ellipsis",
                        color: isActive ? "#C7FF45" : "#F8FAFC",
                      }}
                    >
                      {item.label}
                    </div>
                    <div
                      style={{
                        fontSize: "10px",
                        color: isActive ? "#E2E8F0" : "#A8B6C8",
                        whiteSpace: "nowrap",
                        overflow: "hidden",
                        textOverflow: "ellipsis",
                      }}
                    >
                      {isAuthorized ? item.shortDesc : "Role restricted"}
                    </div>
                  </div>
                )}
              </div>

              {!isCollapsed && isAuthorized && item.badgeCount !== undefined && (
                <span
                  style={{
                    fontSize: "10px",
                    fontWeight: 800,
                    padding: "2px 7px",
                    borderRadius: "999px",
                    background: badgeStyle.bg,
                    color: badgeStyle.color,
                    border: badgeStyle.border,
                    flexShrink: 0,
                  }}
                >
                  {item.badgeCount}
                </span>
              )}
            </button>
          );
        })}
      </div>
    </nav>
  );
}

