import {
  ArrowRight,
  BarChart3,
  Calendar,
  CheckCircle2,
  Clock,
  Edit3,
  MapPin,
  Plus,
  Receipt,
  Sparkles,
  Ticket,
  Users,
  UtensilsCrossed,
} from "lucide-react";
import { EventDetails } from "../../types";
import { FishFryEventItem } from "../../data/fishFryEvents";
import {
  HostNavToolId,
  HostRolePermissions,
  HostUpcomingEventCardData,
} from "./types";

type Props = {
  upcomingEvents: HostUpcomingEventCardData[];
  onManageEvent: (event: EventDetails | FishFryEventItem) => void;
  onNavigateTool: (tool: HostNavToolId) => void;
  permissions?: HostRolePermissions;
  onOpenCreateWizard?: () => void;
};

export function HostUpcomingEventsSection({
  upcomingEvents,
  onManageEvent,
  onNavigateTool,
  permissions,
  onOpenCreateWizard,
}: Props) {
  const canEditEvents = permissions ? permissions.canManageEventsAndMenus : true;
  const canManageMenus = permissions
    ? permissions.canManageEventsAndMenus || permissions.canManageAttendeesAndFulfillment
    : true;
  const canManageStaff = permissions ? permissions.canManageWorkers : true;
  const canViewReports = permissions ? permissions.canViewReports : true;
  const getStatusBadge = (
    status: HostUpcomingEventCardData["status"],
    isOrderingOpen: boolean
  ) => {
    const statusMap: Record<
      HostUpcomingEventCardData["status"],
      { label: string; bg: string; color: string; border: string }
    > = {
      published: {
        label: "PUBLISHED",
        bg: "rgba(16, 185, 129, 0.22)",
        color: "#34D399",
        border: "1px solid rgba(52, 211, 153, 0.45)",
      },
      draft: {
        label: "DRAFT",
        bg: "rgba(148, 163, 184, 0.2)",
        color: "#CBD5E1",
        border: "1px solid rgba(148, 163, 184, 0.4)",
      },
      unpublished: {
        label: "UNPUBLISHED",
        bg: "rgba(245, 158, 11, 0.22)",
        color: "#FBBF24",
        border: "1px solid rgba(251, 191, 36, 0.45)",
      },
      cancelled: {
        label: "CANCELLED",
        bg: "rgba(239, 68, 68, 0.22)",
        color: "#F87171",
        border: "1px solid rgba(248, 113, 113, 0.45)",
      },
    };

    const primary = statusMap[status] || statusMap.published;

    return {
      primary,
      operational: isOrderingOpen
        ? {
            label: "ORDERING OPEN",
            bg: "rgba(56, 189, 248, 0.16)",
            color: "#38BDF8",
            border: "1px solid rgba(56, 189, 248, 0.35)",
          }
        : {
            label: "ORDERING PAUSED",
            bg: "rgba(245, 158, 11, 0.16)",
            color: "#FBBF24",
            border: "1px solid rgba(251, 191, 36, 0.35)",
          },
    };
  };

  return (
    <section
      id="host-upcoming-events-section"
      style={{
        background: "#0B1120",
        border: "1px solid rgba(148, 163, 184, 0.16)",
        borderRadius: "16px",
        padding: "20px 22px",
        marginBottom: "22px",
        boxShadow: "0 12px 32px rgba(2, 6, 23, 0.4)",
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
          <span
            style={{
              fontSize: "10px",
              fontWeight: 800,
              letterSpacing: "1.4px",
              color: "#FBBF24",
              textTransform: "uppercase",
            }}
          >
            Host Event Portfolio
          </span>
          <h2
            style={{
              margin: "2px 0 0",
              fontSize: "18px",
              fontWeight: 800,
              color: "#F8FAFC",
              letterSpacing: "-0.3px",
            }}
          >
            Upcoming Events ({upcomingEvents.length})
          </h2>
          <p style={{ margin: "3px 0 0", fontSize: "12px", color: "#94A3B8" }}>
            Events managed by your authorized host organization. Select any event to configure flyers, menus, and live box office settings.
          </p>
        </div>

        <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
          {canEditEvents && (
            <button
              type="button"
              id="btn-quick-create-event"
              onClick={() => {
                if (onOpenCreateWizard) {
                  onOpenCreateWizard();
                } else {
                  onNavigateTool("events");
                }
              }}
              style={{
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
                padding: "9px 14px",
                borderRadius: "10px",
                background: "#C7FF45",
                border: "1px solid #D7FF77",
                color: "#14210A",
                fontSize: "12px",
                fontWeight: 800,
                boxShadow: "0 4px 14px rgba(199, 255, 69, 0.25)",
              }}
            >
              <Plus size={14} />
              <span>Create Event</span>
            </button>
          )}

          <button
            type="button"
            onClick={() => onNavigateTool("events")}
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "7px",
              padding: "9px 14px",
              borderRadius: "10px",
              background: "linear-gradient(135deg, #4F46E5 0%, #3730A3 100%)",
              border: "1px solid rgba(165, 180, 252, 0.35)",
              color: "#FFFFFF",
              fontSize: "12px",
              fontWeight: 700,
              boxShadow: "0 4px 14px rgba(79, 70, 229, 0.35)",
            }}
          >
            <Edit3 size={14} />
            <span>Open Event & Flyer Studio</span>
          </button>
        </div>
      </div>

      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fill, minmax(290px, 1fr))",
          gap: "16px",
        }}
      >
        {upcomingEvents.map((ev) => {
          const badges = getStatusBadge(ev.status, ev.isOrderingOpen);

          return (
            <article
              key={ev.id}
              id={`host-upcoming-event-card-${ev.id}`}
              style={{
                background: ev.isCurrentActiveEvent
                  ? "linear-gradient(160deg, #111C35 0%, #0F172A 100%)"
                  : "#0F172A",
                border: ev.isCurrentActiveEvent
                  ? "1px solid rgba(129, 140, 248, 0.55)"
                  : "1px solid rgba(148, 163, 184, 0.18)",
                borderRadius: "14px",
                overflow: "hidden",
                display: "flex",
                flexDirection: "column",
                boxShadow: ev.isCurrentActiveEvent
                  ? "0 10px 28px rgba(79, 70, 229, 0.22)"
                  : "0 6px 20px rgba(2, 6, 23, 0.35)",
              }}
            >
              {/* Event Thumbnail Header */}
              <div
                style={{
                  position: "relative",
                  height: "156px",
                  background: "#1E293B",
                  overflow: "hidden",
                }}
              >
                <img
                  src={ev.imageUrl}
                  alt={ev.title}
                  onError={(e) => {
                    const target = e.currentTarget;
                    if (!target.src.endsWith("/images/escovitch_fish.jpg")) {
                      target.src = "/images/escovitch_fish.jpg";
                    }
                  }}
                  style={{
                    width: "100%",
                    height: "100%",
                    objectFit: "cover",
                    display: "block",
                  }}
                />
                <div
                  style={{
                    position: "absolute",
                    inset: 0,
                    background:
                      "linear-gradient(180deg, rgba(2,6,23,0.55) 0%, rgba(2,6,23,0.15) 45%, rgba(2,6,23,0.85) 100%)",
                  }}
                />

                {/* Top Status Badges */}
                <div
                  style={{
                    position: "absolute",
                    top: "10px",
                    left: "10px",
                    right: "10px",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    gap: "6px",
                    flexWrap: "wrap",
                  }}
                >
                  <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                    <span
                      style={{
                        fontSize: "9px",
                        fontWeight: 800,
                        letterSpacing: "0.7px",
                        padding: "3px 8px",
                        borderRadius: "6px",
                        background: badges.primary.bg,
                        color: badges.primary.color,
                        border: badges.primary.border,
                        backdropFilter: "blur(6px)",
                      }}
                    >
                      {badges.primary.label}
                    </span>
                    <span
                      style={{
                        fontSize: "9px",
                        fontWeight: 800,
                        letterSpacing: "0.6px",
                        padding: "3px 8px",
                        borderRadius: "6px",
                        background: badges.operational.bg,
                        color: badges.operational.color,
                        border: badges.operational.border,
                        backdropFilter: "blur(6px)",
                      }}
                    >
                      {badges.operational.label}
                    </span>
                  </div>

                  {ev.isCurrentActiveEvent && (
                    <span
                      style={{
                        display: "inline-flex",
                        alignItems: "center",
                        gap: "4px",
                        fontSize: "9px",
                        fontWeight: 800,
                        padding: "3px 8px",
                        borderRadius: "999px",
                        background: "rgba(79, 70, 229, 0.9)",
                        color: "#FFFFFF",
                        border: "1px solid rgba(199, 210, 254, 0.5)",
                      }}
                    >
                      <Sparkles size={10} />
                      ACTIVE WORKSPACE
                    </span>
                  )}
                </div>

                {/* Bottom Venue Overlay */}
                <div
                  style={{
                    position: "absolute",
                    bottom: "10px",
                    left: "12px",
                    right: "12px",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    gap: "8px",
                  }}
                >
                  <span
                    style={{
                      display: "inline-flex",
                      alignItems: "center",
                      gap: "5px",
                      fontSize: "11px",
                      color: "#E2E8F0",
                      fontWeight: 600,
                      overflow: "hidden",
                      textOverflow: "ellipsis",
                      whiteSpace: "nowrap",
                    }}
                  >
                    <MapPin size={12} color="#34D399" style={{ flexShrink: 0 }} />
                    {ev.venueName}
                  </span>
                  <span
                    style={{
                      fontSize: "10px",
                      fontWeight: 800,
                      padding: "2px 7px",
                      borderRadius: "5px",
                      background: "rgba(15, 23, 42, 0.85)",
                      color: "#FBBF24",
                      border: "1px solid rgba(251, 191, 36, 0.35)",
                      flexShrink: 0,
                    }}
                  >
                    {ev.currency}
                  </span>
                </div>
              </div>

              {/* Event Card Body */}
              <div
                style={{
                  padding: "14px 16px",
                  display: "flex",
                  flexDirection: "column",
                  flex: 1,
                  justifyContent: "space-between",
                  gap: "12px",
                }}
              >
                <div>
                  <h3
                    style={{
                      margin: 0,
                      fontSize: "15px",
                      fontWeight: 800,
                      color: "#F8FAFC",
                      lineHeight: 1.35,
                    }}
                  >
                    {ev.title}
                  </h3>

                  {/* Date and Time */}
                  <div
                    style={{
                      marginTop: "8px",
                      display: "flex",
                      flexDirection: "column",
                      gap: "4px",
                      fontSize: "11px",
                      color: "#CBD5E1",
                    }}
                  >
                    <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                      <Calendar size={13} color="#818CF8" style={{ flexShrink: 0 }} />
                      <span style={{ fontWeight: 600 }}>{ev.dateStr}</span>
                    </div>
                    <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                      <Clock size={13} color="#38BDF8" style={{ flexShrink: 0 }} />
                      <span>{ev.timeStr}</span>
                    </div>
                  </div>

                  {/* Operational Snapshot Strip */}
                  <div
                    style={{
                      marginTop: "12px",
                      padding: "8px 10px",
                      borderRadius: "9px",
                      background: "rgba(15, 23, 42, 0.75)",
                      border: "1px solid rgba(148, 163, 184, 0.14)",
                      display: "grid",
                      gridTemplateColumns: "repeat(4, 1fr)",
                      gap: "8px",
                    }}
                  >
                    <div>
                      <span style={{ fontSize: "9px", color: "#94A3B8", display: "block" }}>
                        Confirmed Sales
                      </span>
                      <strong style={{ fontSize: "12px", color: "#34D399" }}>
                        {ev.confirmedSalesFormatted}
                      </strong>
                    </div>
                    <div>
                      <span style={{ fontSize: "9px", color: "#94A3B8", display: "block" }}>
                        Tickets Sold
                      </span>
                      <strong style={{ fontSize: "12px", color: "#F8FAFC" }}>
                        {ev.ticketsSoldCount}
                      </strong>
                    </div>
                    <div>
                      <span style={{ fontSize: "9px", color: "#94A3B8", display: "block" }}>
                        Order Summary
                      </span>
                      <strong style={{ fontSize: "12px", color: "#38BDF8" }}>
                        {ev.ticketsSoldCount + ev.pendingOrdersCount} ({ev.pendingOrdersCount} pending)
                      </strong>
                    </div>
                    <div>
                      <span style={{ fontSize: "9px", color: "#94A3B8", display: "block" }}>
                        Menu Dishes
                      </span>
                      <strong style={{ fontSize: "12px", color: "#A5B4FC" }}>
                        {ev.menuItemsCount} items
                      </strong>
                    </div>
                  </div>
                </div>

                {/* Role-Gated Quick Actions Strip */}
                <div
                  style={{
                    display: "flex",
                    flexDirection: "column",
                    gap: "8px",
                    paddingTop: "10px",
                    borderTop: "1px solid rgba(148, 163, 184, 0.14)",
                  }}
                >
                  <div
                    style={{
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "space-between",
                      gap: "8px",
                    }}
                  >
                    <button
                      type="button"
                      onClick={() => onManageEvent(ev.sourceEvent)}
                      style={{
                        flex: 1,
                        height: "36px",
                        borderRadius: "8px",
                        background: ev.isCurrentActiveEvent
                          ? "#C7FF45"
                          : "linear-gradient(135deg, #4F46E5 0%, #3730A3 100%)",
                        color: ev.isCurrentActiveEvent ? "#14210A" : "#FFFFFF",
                        border: "none",
                        fontSize: "11px",
                        fontWeight: 800,
                        display: "inline-flex",
                        alignItems: "center",
                        justifyContent: "center",
                        gap: "6px",
                      }}
                    >
                      {ev.isCurrentActiveEvent ? (
                        <>
                          <CheckCircle2 size={13} />
                          <span>Manage Event Studio</span>
                        </>
                      ) : (
                        <>
                          <span>Manage Event</span>
                          <ArrowRight size={13} />
                        </>
                      )}
                    </button>

                    {canEditEvents && (
                      <button
                        type="button"
                        id={`btn-quick-edit-event-${ev.id}`}
                        title="Edit Event"
                        onClick={() => {
                          onManageEvent(ev.sourceEvent);
                          onNavigateTool("events");
                        }}
                        style={{
                          height: "36px",
                          padding: "0 10px",
                          borderRadius: "8px",
                          background: "rgba(30, 41, 59, 0.85)",
                          border: "1px solid rgba(148, 163, 184, 0.25)",
                          color: "#CBD5E1",
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "5px",
                          fontSize: "11px",
                          fontWeight: 700,
                        }}
                      >
                        <Edit3 size={12} color="#C7FF45" />
                        <span>Edit Event</span>
                      </button>
                    )}
                  </div>

                  <div style={{ display: "flex", alignItems: "center", gap: "6px", flexWrap: "wrap" }}>
                    {canEditEvents && (
                      <button
                        type="button"
                        id={`btn-quick-tickets-${ev.id}`}
                        title="Manage Tickets"
                        onClick={() => {
                          onManageEvent(ev.sourceEvent);
                          onNavigateTool("events");
                        }}
                        style={{
                          padding: "5px 9px",
                          borderRadius: "7px",
                          background: "rgba(30, 41, 59, 0.85)",
                          border: "1px solid rgba(148, 163, 184, 0.22)",
                          color: "#CBD5E1",
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "4px",
                          fontSize: "10px",
                          fontWeight: 700,
                        }}
                      >
                        <Ticket size={11} color="#C7FF45" />
                        <span>Manage Tickets</span>
                      </button>
                    )}

                    {canManageMenus && (
                      <button
                        type="button"
                        id={`btn-quick-menus-${ev.id}`}
                        title="Manage Menus"
                        onClick={() => {
                          onManageEvent(ev.sourceEvent);
                          onNavigateTool("food_orders");
                        }}
                        style={{
                          padding: "5px 9px",
                          borderRadius: "7px",
                          background: "rgba(30, 41, 59, 0.85)",
                          border: "1px solid rgba(148, 163, 184, 0.22)",
                          color: "#CBD5E1",
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "4px",
                          fontSize: "10px",
                          fontWeight: 700,
                        }}
                      >
                        <UtensilsCrossed size={11} color="#34D399" />
                        <span>Manage Menus</span>
                      </button>
                    )}

                    <button
                      type="button"
                      id={`btn-quick-orders-${ev.id}`}
                      title="View Event Orders"
                      onClick={() => {
                        onManageEvent(ev.sourceEvent);
                        onNavigateTool("orders");
                      }}
                      style={{
                        padding: "5px 9px",
                        borderRadius: "7px",
                        background: "rgba(30, 41, 59, 0.85)",
                        border: "1px solid rgba(148, 163, 184, 0.22)",
                        color: "#CBD5E1",
                        display: "inline-flex",
                        alignItems: "center",
                        gap: "4px",
                        fontSize: "10px",
                        fontWeight: 700,
                      }}
                    >
                      <Receipt size={11} color="#38BDF8" />
                      <span>View Event Orders</span>
                    </button>

                    {canManageStaff && (
                      <button
                        type="button"
                        id={`btn-quick-staff-${ev.id}`}
                        title="Manage Event Staff"
                        onClick={() => {
                          onManageEvent(ev.sourceEvent);
                          onNavigateTool("workers");
                        }}
                        style={{
                          padding: "5px 9px",
                          borderRadius: "7px",
                          background: "rgba(30, 41, 59, 0.85)",
                          border: "1px solid rgba(148, 163, 184, 0.22)",
                          color: "#CBD5E1",
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "4px",
                          fontSize: "10px",
                          fontWeight: 700,
                        }}
                      >
                        <Users size={11} color="#A5B4FC" />
                        <span>Manage Staff</span>
                      </button>
                    )}

                    {canViewReports && (
                      <button
                        type="button"
                        id={`btn-quick-reports-${ev.id}`}
                        title="Open Reports"
                        onClick={() => {
                          onManageEvent(ev.sourceEvent);
                          onNavigateTool("reports");
                        }}
                        style={{
                          padding: "5px 9px",
                          borderRadius: "7px",
                          background: "rgba(30, 41, 59, 0.85)",
                          border: "1px solid rgba(148, 163, 184, 0.22)",
                          color: "#CBD5E1",
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "4px",
                          fontSize: "10px",
                          fontWeight: 700,
                        }}
                      >
                        <BarChart3 size={11} color="#FBBF24" />
                        <span>Open Reports</span>
                      </button>
                    )}
                  </div>
                </div>
              </div>
            </article>
          );
        })}
      </div>
    </section>
  );
}
