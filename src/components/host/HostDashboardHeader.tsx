import { useEffect, useState } from "react";
import {
  Bell,
  Building2,
  CheckCircle2,
  ChevronDown,
  CreditCard,
  Globe,
  LogOut,
  RefreshCw,
  Settings,
  ShieldCheck,
  Sparkles,
  UserCheck,
  Users,
  X,
} from "lucide-react";
import {
  EventDetails,
  NotificationLogEntry,
  OrganizerProfile,
  TenantRole,
  TenantUserMember,
} from "../../types";
import {
  ApiNotificationItem,
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  SessionUser,
} from "../../lib/api";
import {
  HostNavToolId,
  HostRolePermissions,
  HostUpcomingEventCardData,
} from "./types";

type Props = {
  eventDetails: EventDetails;
  organizers: OrganizerProfile[];
  activeOrganizer: OrganizerProfile;
  activeOrganizerId: string;
  activeRolePerspective: TenantRole;
  activeRoleUser?: TenantUserMember;
  permissions: HostRolePermissions;
  sessionUser?: SessionUser | null;
  notificationLogs: NotificationLogEntry[];
  isApiOnline?: boolean;
  activeTool: HostNavToolId;
  onNavigateTool: (tool: HostNavToolId) => void;
  onSwitchOrganizer: (orgId: string) => void;
  onSwitchRolePerspective: (role: TenantRole) => void;
  onOpenGatewayModal: () => void;
  onResetDemo: () => void;
  onSignOut?: () => void;
  upcomingEvents?: HostUpcomingEventCardData[];
  onSelectEvent?: (event: HostUpcomingEventCardData) => void;
};

const TOOL_BREADCRUMB_LABELS: Record<HostNavToolId, string> = {
  dashboard: "Overview & Telemetry",
  events: "Event Management & Studio",
  orders: "Orders & Payment Verification",
  payments: "Payments & Cash Ledger",
  attendees: "Ticket Check-in & Attendees",
  food_orders: "Menu Management & Kitchen Prep",
  reports: "Financial & Operational Reports",
  workers: "Staff Management & Assignments",
  settings: "Host Account & Payment Settings",
};

export function HostDashboardHeader({
  eventDetails,
  organizers,
  activeOrganizer,
  activeOrganizerId,
  activeRolePerspective,
  activeRoleUser,
  permissions,
  sessionUser,
  notificationLogs,
  isApiOnline = false,
  activeTool,
  onNavigateTool,
  onSwitchOrganizer,
  onSwitchRolePerspective,
  onOpenGatewayModal,
  onResetDemo,
  onSignOut,
  upcomingEvents = [],
  onSelectEvent,
}: Props) {
  const [notifDrawerOpen, setNotifDrawerOpen] = useState(false);
  const [accountMenuOpen, setAccountMenuOpen] = useState(false);
  const [apiNotifications, setApiNotifications] = useState<ApiNotificationItem[]>([]);
  const [apiUnreadCount, setApiUnreadCount] = useState<number>(0);
  const [readLocalIds, setReadLocalIds] = useState<Record<string, boolean>>({});

  useEffect(() => {
    let cancelled = false;
    if (sessionUser) {
      fetchNotifications()
        .then((res) => {
          if (!cancelled) {
            setApiNotifications(res.data || []);
            setApiUnreadCount(res.unread_count ?? res.unreadCount ?? 0);
          }
        })
        .catch(() => {
          // Silently fall back to local notificationLogs when offline
        });
    }
    return () => {
      cancelled = true;
    };
  }, [sessionUser]);

  const hostDisplayName =
    sessionUser?.name || activeRoleUser?.name || "Pastor Dennis Brown";
  const hostDisplayEmail =
    sessionUser?.email || activeRoleUser?.email || activeOrganizer.contactEmail;

  const recentLocalLogs = notificationLogs.slice(0, 6);
  const localUnreadCount = recentLocalLogs.filter((n) => !readLocalIds[n.id]).length;
  const totalUnreadCount =
    apiNotifications.length > 0 ? apiUnreadCount : localUnreadCount;

  const handleMarkAllRead = async () => {
    const nextRead: Record<string, boolean> = {};
    recentLocalLogs.forEach((n) => {
      nextRead[n.id] = true;
    });
    setReadLocalIds((prev) => ({ ...prev, ...nextRead }));

    if (sessionUser && apiNotifications.length > 0) {
      try {
        await markAllNotificationsRead();
        setApiUnreadCount(0);
        setApiNotifications((prev) =>
          prev.map((item) => ({ ...item, readAt: item.readAt || new Date().toISOString() }))
        );
      } catch {
        // Fallback handled
      }
    }
  };

  const handleMarkSingleRead = async (id: string, isApi: boolean) => {
    if (isApi) {
      try {
        await markNotificationRead(id);
        setApiNotifications((prev) =>
          prev.map((n) => (n.id === id ? { ...n, readAt: new Date().toISOString() } : n))
        );
        setApiUnreadCount((c) => Math.max(0, c - 1));
      } catch {
        // Ignore
      }
    } else {
      setReadLocalIds((prev) => ({ ...prev, [id]: true }));
    }
  };

  return (
    <header
      id="host-dashboard-header"
      style={{
        background: "linear-gradient(135deg, #070B14 0%, #0F172A 55%, #131C33 100%)",
        border: "1px solid rgba(148, 163, 184, 0.16)",
        borderRadius: "16px",
        padding: "18px 22px",
        color: "#F8FAFC",
        boxShadow: "0 16px 36px rgba(2, 6, 23, 0.45)",
        position: "relative",
        zIndex: 30,
      }}
    >
      {/* Top Row: Brand + Dashboard Title + Identity + Notifications & Account Controls */}
      <div
        style={{
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          gap: "16px",
          flexWrap: "wrap",
        }}
      >
        {/* Left: Island Event Branding & Host Dashboard Title */}
        <div style={{ display: "flex", alignItems: "center", gap: "14px", flexWrap: "wrap" }}>
          <div
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "10px",
              padding: "8px 13px",
              borderRadius: "12px",
              background: "rgba(30, 41, 59, 0.75)",
              border: "1px solid rgba(212, 175, 55, 0.35)",
              boxShadow: "0 4px 14px rgba(0, 0, 0, 0.25)",
            }}
          >
            <div
              style={{
                width: "34px",
                height: "34px",
                borderRadius: "9px",
                background: "linear-gradient(135deg, #4F46E5 0%, #3730A3 60%, #1E1B4B 100%)",
                border: "1px solid rgba(212, 175, 55, 0.45)",
                display: "grid",
                placeItems: "center",
                color: "#FBBF24",
                flexShrink: 0,
              }}
            >
              <Sparkles size={17} />
            </div>
            <div>
              <div
                style={{
                  fontSize: "10px",
                  fontWeight: 800,
                  letterSpacing: "1.4px",
                  color: "#C7FF45",
                  textTransform: "uppercase",
                  lineHeight: 1.1,
                }}
              >
                EventBite · Island Event
              </div>
              <div
                style={{
                  fontSize: "11px",
                  fontWeight: 600,
                  color: "#CBD5E1",
                  marginTop: "2px",
                }}
              >
                Enterprise Box Office
              </div>
            </div>
          </div>

          <div
            style={{
              height: "32px",
              width: "1px",
              background: "rgba(148, 163, 184, 0.2)",
            }}
          />

          <div>
            <div style={{ display: "flex", alignItems: "center", gap: "10px", flexWrap: "wrap" }}>
              <h1
                style={{
                  margin: 0,
                  fontSize: "21px",
                  fontWeight: 800,
                  letterSpacing: "-0.4px",
                  color: "#FFFFFF",
                  fontFamily: '"Plus Jakarta Sans", "Inter", sans-serif',
                }}
              >
                Host Dashboard
              </h1>
              <span
                id="host-header-breadcrumb"
                style={{
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "5px",
                  padding: "3px 9px",
                  borderRadius: "999px",
                  fontSize: "10px",
                  fontWeight: 800,
                  background: "rgba(199, 255, 69, 0.14)",
                  color: "#C7FF45",
                  border: "1px solid rgba(199, 255, 69, 0.35)",
                }}
              >
                / {TOOL_BREADCRUMB_LABELS[activeTool] || "Overview"}
              </span>
              <span
                style={{
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "6px",
                  padding: "3px 10px",
                  borderRadius: "999px",
                  fontSize: "10px",
                  fontWeight: 700,
                  background: isApiOnline
                    ? "rgba(16, 185, 129, 0.14)"
                    : "rgba(56, 189, 248, 0.14)",
                  color: isApiOnline ? "#34D399" : "#38BDF8",
                  border: isApiOnline
                    ? "1px solid rgba(16, 185, 129, 0.35)"
                    : "1px solid rgba(56, 189, 248, 0.35)",
                }}
              >
                <span
                  style={{
                    width: "6px",
                    height: "6px",
                    borderRadius: "50%",
                    background: isApiOnline ? "#10B981" : "#38BDF8",
                  }}
                />
                {isApiOnline ? "Live Backend Connected" : "Verified Local Ledger"}
              </span>
            </div>
            <div
              style={{
                marginTop: "5px",
                display: "flex",
                alignItems: "center",
                gap: "8px",
                flexWrap: "wrap",
                fontSize: "12px",
                color: "#94A3B8",
              }}
            >
              <span>Active Event:</span>
              {upcomingEvents.length > 0 && onSelectEvent ? (
                <select
                  id="host-top-event-selector"
                  aria-label="Select Active Host Event"
                  value={eventDetails.id || upcomingEvents[0].id}
                  onChange={(e) => {
                    const found = upcomingEvents.find((ev) => ev.id === e.target.value);
                    if (found) onSelectEvent(found);
                  }}
                  style={{
                    height: "26px",
                    padding: "0 8px",
                    borderRadius: "6px",
                    background: "#10243A",
                    color: "#F8FAFC",
                    border: "1px solid rgba(199, 255, 69, 0.35)",
                    fontSize: "11px",
                    fontWeight: 700,
                  }}
                >
                  {upcomingEvents.map((ev) => (
                    <option key={ev.id} value={ev.id}>
                      {ev.title} ({ev.currency})
                    </option>
                  ))}
                </select>
              ) : (
                <strong id="host-top-event-selector" style={{ color: "#E2E8F0" }}>
                  {eventDetails.title}
                </strong>
              )}
              <span>· {eventDetails.currency} Settlement</span>
            </div>
          </div>
        </div>

        {/* Right: Current Authorized Host Identity + Notifications + Account Controls */}
        <div
          style={{
            display: "flex",
            alignItems: "center",
            gap: "10px",
            flexWrap: "wrap",
          }}
        >
          {/* Current Authorized Host Identity Pill */}
          <div
            id="host-authorized-identity-card"
            style={{
              display: "flex",
              alignItems: "center",
              gap: "10px",
              padding: "7px 12px",
              borderRadius: "11px",
              background: "rgba(15, 23, 42, 0.85)",
              border: "1px solid rgba(148, 163, 184, 0.22)",
            }}
          >
            <div
              style={{
                width: "32px",
                height: "32px",
                borderRadius: "50%",
                background: "linear-gradient(135deg, #10B981 0%, #059669 100%)",
                color: "#052E16",
                display: "grid",
                placeItems: "center",
                fontWeight: 800,
                fontSize: "12px",
                flexShrink: 0,
              }}
            >
              {hostDisplayName
                .split(" ")
                .map((p) => p[0])
                .join("")
                .slice(0, 2)
                .toUpperCase()}
            </div>
            <div style={{ lineHeight: 1.25 }}>
              <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                <strong style={{ fontSize: "12px", color: "#F8FAFC" }}>
                  {hostDisplayName}
                </strong>
                <span
                  style={{
                    fontSize: "9px",
                    fontWeight: 800,
                    padding: "2px 6px",
                    borderRadius: "4px",
                    background: "rgba(16, 185, 129, 0.16)",
                    color: permissions.roleBadgeColor,
                    border: `1px solid ${permissions.roleBadgeColor}44`,
                    textTransform: "uppercase",
                  }}
                >
                  {permissions.roleLabel}
                </span>
              </div>
              <div style={{ fontSize: "10px", color: "#94A3B8" }}>
                {activeOrganizer.name} · <code style={{ color: "#CBD5E1" }}>{hostDisplayEmail}</code>
              </div>
            </div>
          </div>

          {onSignOut && (
            <button
              type="button"
              id="host-header-signout-btn"
              onClick={onSignOut}
              style={{
                height: "40px",
                padding: "0 11px",
                borderRadius: "10px",
                background: "rgba(239, 68, 68, 0.14)",
                border: "1px solid rgba(248, 113, 113, 0.35)",
                color: "#FCA5A5",
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
                fontSize: "11px",
                fontWeight: 700,
              }}
            >
              <LogOut size={14} />
              <span>Sign Out</span>
            </button>
          )}

          {/* Notifications Control */}
          <div style={{ position: "relative" }}>
            <button
              type="button"
              id="host-header-notifications-btn"
              aria-label="Notifications"
              onClick={() => {
                setNotifDrawerOpen((prev) => !prev);
                setAccountMenuOpen(false);
              }}
              style={{
                height: "40px",
                padding: "0 12px",
                borderRadius: "10px",
                background: notifDrawerOpen
                  ? "rgba(79, 70, 229, 0.28)"
                  : "rgba(30, 41, 59, 0.85)",
                border: notifDrawerOpen
                  ? "1px solid #818CF8"
                  : "1px solid rgba(148, 163, 184, 0.24)",
                color: "#F8FAFC",
                display: "inline-flex",
                alignItems: "center",
                gap: "8px",
                fontSize: "12px",
                fontWeight: 700,
              }}
            >
              <Bell size={15} color={totalUnreadCount > 0 ? "#FBBF24" : "#CBD5E1"} />
              <span>Alerts</span>
              {totalUnreadCount > 0 && (
                <span
                  style={{
                    minWidth: "18px",
                    height: "18px",
                    padding: "0 5px",
                    borderRadius: "999px",
                    background: "#F59E0B",
                    color: "#090D16",
                    fontSize: "10px",
                    fontWeight: 800,
                    display: "grid",
                    placeItems: "center",
                  }}
                >
                  {totalUnreadCount}
                </span>
              )}
            </button>

            {notifDrawerOpen && (
              <div
                id="host-notifications-popover"
                style={{
                  position: "absolute",
                  top: "calc(100% + 8px)",
                  right: 0,
                  width: "min(380px, 90vw)",
                  background: "#0F172A",
                  border: "1px solid rgba(148, 163, 184, 0.28)",
                  borderRadius: "14px",
                  boxShadow: "0 20px 50px rgba(0, 0, 0, 0.65)",
                  padding: "14px",
                  zIndex: 60,
                }}
              >
                <div
                  style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    paddingBottom: "10px",
                    borderBottom: "1px solid rgba(148, 163, 184, 0.16)",
                    marginBottom: "10px",
                  }}
                >
                  <div>
                    <strong style={{ fontSize: "13px", color: "#F8FAFC", display: "block" }}>
                      Host Operational Notifications
                    </strong>
                    <span style={{ fontSize: "11px", color: "#94A3B8" }}>
                      {totalUnreadCount} unread dispatch & verification events
                    </span>
                  </div>
                  <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                    <button
                      type="button"
                      onClick={handleMarkAllRead}
                      style={{
                        background: "rgba(52, 211, 153, 0.12)",
                        border: "1px solid rgba(52, 211, 153, 0.3)",
                        color: "#34D399",
                        borderRadius: "6px",
                        padding: "4px 8px",
                        fontSize: "10px",
                        fontWeight: 700,
                      }}
                    >
                      Mark all read
                    </button>
                    <button
                      type="button"
                      aria-label="Close notifications"
                      onClick={() => setNotifDrawerOpen(false)}
                      style={{
                        background: "transparent",
                        border: "none",
                        color: "#94A3B8",
                        padding: "4px",
                      }}
                    >
                      <X size={14} />
                    </button>
                  </div>
                </div>

                <div
                  style={{
                    display: "flex",
                    flexDirection: "column",
                    gap: "8px",
                    maxHeight: "300px",
                    overflowY: "auto",
                  }}
                >
                  {apiNotifications.length > 0
                    ? apiNotifications.slice(0, 6).map((item) => (
                        <div
                          key={item.id}
                          onClick={() => handleMarkSingleRead(item.id, true)}
                          style={{
                            padding: "10px",
                            borderRadius: "9px",
                            background: item.readAt
                              ? "rgba(30, 41, 59, 0.45)"
                              : "rgba(79, 70, 229, 0.16)",
                            border: item.readAt
                              ? "1px solid rgba(148, 163, 184, 0.12)"
                              : "1px solid rgba(129, 140, 248, 0.35)",
                            cursor: "pointer",
                          }}
                        >
                          <div
                            style={{
                              display: "flex",
                              justifyContent: "space-between",
                              fontSize: "11px",
                              fontWeight: 700,
                              color: "#F8FAFC",
                            }}
                          >
                            <span>{String(item.data?.title || item.type)}</span>
                            {!item.readAt && (
                              <span style={{ color: "#FBBF24", fontSize: "9px" }}>NEW</span>
                            )}
                          </div>
                          <p
                            style={{
                              margin: "4px 0 0",
                              fontSize: "11px",
                              color: "#CBD5E1",
                              lineHeight: 1.4,
                            }}
                          >
                            {String(item.data?.message || "System notification recorded.")}
                          </p>
                        </div>
                      ))
                    : recentLocalLogs.map((log) => {
                        const isRead = !!readLocalIds[log.id];
                        return (
                          <div
                            key={log.id}
                            onClick={() => handleMarkSingleRead(log.id, false)}
                            style={{
                              padding: "10px",
                              borderRadius: "9px",
                              background: isRead
                                ? "rgba(30, 41, 59, 0.45)"
                                : "rgba(30, 41, 59, 0.9)",
                              border:
                                log.status === "failed"
                                  ? "1px solid rgba(248, 113, 113, 0.45)"
                                  : isRead
                                  ? "1px solid rgba(148, 163, 184, 0.12)"
                                  : "1px solid rgba(52, 211, 153, 0.3)",
                              cursor: "pointer",
                            }}
                          >
                            <div
                              style={{
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "space-between",
                                gap: "6px",
                              }}
                            >
                              <strong style={{ fontSize: "11px", color: "#F8FAFC" }}>
                                {log.title}
                              </strong>
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
                            <div
                              style={{
                                fontSize: "10px",
                                color: "#94A3B8",
                                marginTop: "3px",
                              }}
                            >
                              {log.customerName} ({log.orderNumber}) · {log.channel.toUpperCase()}
                            </div>
                          </div>
                        );
                      })}
                </div>

                <div
                  style={{
                    marginTop: "10px",
                    paddingTop: "10px",
                    borderTop: "1px solid rgba(148, 163, 184, 0.16)",
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                  }}
                >
                  <button
                    type="button"
                    onClick={() => {
                      setNotifDrawerOpen(false);
                      onNavigateTool("reports");
                    }}
                    style={{
                      width: "100%",
                      padding: "8px 12px",
                      borderRadius: "8px",
                      background: "rgba(79, 70, 229, 0.22)",
                      border: "1px solid rgba(129, 140, 248, 0.4)",
                      color: "#C7D2FE",
                      fontSize: "11px",
                      fontWeight: 700,
                    }}
                  >
                    Open Full Notification & Delivery Audit →
                  </button>
                </div>
              </div>
            )}
          </div>

          {/* Account Controls Dropdown */}
          <div style={{ position: "relative" }}>
            <button
              type="button"
              id="host-header-account-controls-btn"
              onClick={() => {
                setAccountMenuOpen((prev) => !prev);
                setNotifDrawerOpen(false);
              }}
              style={{
                height: "40px",
                padding: "0 13px",
                borderRadius: "10px",
                background: accountMenuOpen
                  ? "rgba(79, 70, 229, 0.28)"
                  : "rgba(30, 41, 59, 0.85)",
                border: accountMenuOpen
                  ? "1px solid #818CF8"
                  : "1px solid rgba(148, 163, 184, 0.24)",
                color: "#F8FAFC",
                display: "inline-flex",
                alignItems: "center",
                gap: "7px",
                fontSize: "12px",
                fontWeight: 700,
              }}
            >
              <Settings size={15} color="#CBD5E1" />
              <span>Account Controls</span>
              <ChevronDown size={14} color="#94A3B8" />
            </button>

            {accountMenuOpen && (
              <div
                id="host-account-controls-popover"
                style={{
                  position: "absolute",
                  top: "calc(100% + 8px)",
                  right: 0,
                  width: "290px",
                  background: "#0F172A",
                  border: "1px solid rgba(148, 163, 184, 0.28)",
                  borderRadius: "14px",
                  boxShadow: "0 20px 50px rgba(0, 0, 0, 0.65)",
                  padding: "12px",
                  zIndex: 60,
                  display: "flex",
                  flexDirection: "column",
                  gap: "6px",
                }}
              >
                <div
                  style={{
                    padding: "8px 10px",
                    borderRadius: "8px",
                    background: "rgba(30, 41, 59, 0.6)",
                    marginBottom: "4px",
                  }}
                >
                  <div style={{ fontSize: "10px", color: "#94A3B8", fontWeight: 700 }}>
                    AUTHORIZED HOST SESSION
                  </div>
                  <div style={{ fontSize: "12px", fontWeight: 800, color: "#F8FAFC", marginTop: "2px" }}>
                    {hostDisplayName}
                  </div>
                  <div style={{ fontSize: "11px", color: "#CBD5E1" }}>{hostDisplayEmail}</div>
                </div>

                <button
                  type="button"
                  onClick={() => {
                    setAccountMenuOpen(false);
                    onNavigateTool("settings");
                  }}
                  style={{
                    display: "flex",
                    alignItems: "center",
                    gap: "9px",
                    padding: "9px 10px",
                    borderRadius: "8px",
                    background: "transparent",
                    border: "1px solid transparent",
                    color: "#E2E8F0",
                    fontSize: "12px",
                    fontWeight: 600,
                    textAlign: "left",
                  }}
                >
                  <Settings size={15} color="#818CF8" />
                  <span>Host Account & Security Settings</span>
                </button>

                <button
                  type="button"
                  onClick={() => {
                    setAccountMenuOpen(false);
                    onOpenGatewayModal();
                  }}
                  style={{
                    display: "flex",
                    alignItems: "center",
                    gap: "9px",
                    padding: "9px 10px",
                    borderRadius: "8px",
                    background: "transparent",
                    border: "1px solid transparent",
                    color: "#E2E8F0",
                    fontSize: "12px",
                    fontWeight: 600,
                    textAlign: "left",
                  }}
                >
                  <CreditCard size={15} color="#34D399" />
                  <span>Payment Gateways & Settlement</span>
                </button>

                <button
                  type="button"
                  onClick={() => {
                    setAccountMenuOpen(false);
                    onNavigateTool("workers");
                  }}
                  style={{
                    display: "flex",
                    alignItems: "center",
                    gap: "9px",
                    padding: "9px 10px",
                    borderRadius: "8px",
                    background: "transparent",
                    border: "1px solid transparent",
                    color: "#E2E8F0",
                    fontSize: "12px",
                    fontWeight: 600,
                    textAlign: "left",
                  }}
                >
                  <Users size={15} color="#38BDF8" />
                  <span>Worker Assignments & Claim Codes</span>
                </button>

                <div
                  style={{
                    height: "1px",
                    background: "rgba(148, 163, 184, 0.16)",
                    margin: "4px 0",
                  }}
                />

                <button
                  type="button"
                  onClick={() => {
                    setAccountMenuOpen(false);
                    onResetDemo();
                  }}
                  style={{
                    display: "flex",
                    alignItems: "center",
                    gap: "9px",
                    padding: "9px 10px",
                    borderRadius: "8px",
                    background: "transparent",
                    border: "1px solid transparent",
                    color: "#FBBF24",
                    fontSize: "12px",
                    fontWeight: 600,
                    textAlign: "left",
                  }}
                >
                  <RefreshCw size={15} color="#FBBF24" />
                  <span>Reset Sandbox Demo State</span>
                </button>

                {onSignOut && sessionUser && (
                  <button
                    type="button"
                    onClick={() => {
                      setAccountMenuOpen(false);
                      onSignOut();
                    }}
                    style={{
                      display: "flex",
                      alignItems: "center",
                      gap: "9px",
                      padding: "9px 10px",
                      borderRadius: "8px",
                      background: "rgba(239, 68, 68, 0.12)",
                      border: "1px solid rgba(239, 68, 68, 0.3)",
                      color: "#FCA5A5",
                      fontSize: "12px",
                      fontWeight: 700,
                      textAlign: "left",
                    }}
                  >
                    <LogOut size={15} color="#F87171" />
                    <span>Sign Out of Host Session</span>
                  </button>
                )}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Bottom Row: Tenant Context & Role Perspective Controls */}
      <div
        style={{
          marginTop: "14px",
          paddingTop: "12px",
          borderTop: "1px solid rgba(148, 163, 184, 0.14)",
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          gap: "12px",
          flexWrap: "wrap",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: "10px", flexWrap: "wrap" }}>
          <span
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "5px",
              fontSize: "11px",
              color: "#94A3B8",
              fontWeight: 600,
            }}
          >
            <Building2 size={13} color="#34D399" />
            Host Organization:
          </span>
          <select
            aria-label="Select Active Host Organization"
            value={activeOrganizerId}
            onChange={(e) => onSwitchOrganizer(e.target.value)}
            style={{
              background: "#1E293B",
              color: "#F8FAFC",
              border: "1px solid rgba(148, 163, 184, 0.3)",
              borderRadius: "8px",
              padding: "5px 10px",
              fontSize: "11px",
              fontWeight: 700,
              cursor: "pointer",
            }}
          >
            {organizers.map((org) => (
              <option key={org.id} value={org.id}>
                {org.name} ({org.defaultCurrency})
              </option>
            ))}
          </select>

          <span
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "5px",
              fontSize: "11px",
              color: "#94A3B8",
              fontWeight: 600,
              marginLeft: "4px",
            }}
          >
            <UserCheck size={13} color="#818CF8" />
            Role Perspective:
          </span>
          <select
            aria-label="Select Active Role Perspective"
            value={activeRolePerspective}
            onChange={(e) => onSwitchRolePerspective(e.target.value as TenantRole)}
            style={{
              background: "#1E293B",
              color: "#F8FAFC",
              border: "1px solid rgba(129, 140, 248, 0.4)",
              borderRadius: "8px",
              padding: "5px 10px",
              fontSize: "11px",
              fontWeight: 700,
              cursor: "pointer",
            }}
          >
            <option value="event_owner">Event Host / Owner (Full Operational Access)</option>
            <option value="payment_verifier">Payment Verifier (Transfers & Cash Ledger)</option>
            <option value="food_collector">Food Collector (Prep Sheet & Portion Handover)</option>
            <option value="platform_admin">Platform SuperAdmin (Cross-Tenant Governance)</option>
          </select>
        </div>

        <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
          <span
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "5px",
              fontSize: "11px",
              color: "#CBD5E1",
              background: "rgba(30, 41, 59, 0.7)",
              border: "1px solid rgba(148, 163, 184, 0.2)",
              padding: "4px 10px",
              borderRadius: "999px",
            }}
          >
            <ShieldCheck size={13} color="#34D399" />
            Tenant Scope: <code style={{ color: "#34D399" }}>{activeOrganizer.id}</code>
          </span>
          <span
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "5px",
              fontSize: "11px",
              color: "#CBD5E1",
              background: "rgba(30, 41, 59, 0.7)",
              border: "1px solid rgba(148, 163, 184, 0.2)",
              padding: "4px 10px",
              borderRadius: "999px",
            }}
          >
            <Globe size={13} color="#38BDF8" />
            Region: <strong>{activeOrganizer.businessLocation}</strong>
          </span>
          <span
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "5px",
              fontSize: "11px",
              color: "#34D399",
              background: "rgba(16, 185, 129, 0.1)",
              border: "1px solid rgba(16, 185, 129, 0.28)",
              padding: "4px 10px",
              borderRadius: "999px",
              fontWeight: 700,
            }}
          >
            <CheckCircle2 size={13} />
            Pending ≠ Received Enforced
          </span>
        </div>
      </div>
    </header>
  );
}
