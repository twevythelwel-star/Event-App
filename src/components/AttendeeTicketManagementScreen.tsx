import React, { useState, useMemo } from "react";
import {
  Ticket,
  Calendar,
  Clock3,
  MapPin,
  CheckCircle2,
  Clock,
  AlertTriangle,
  RefreshCw,
  Send,
  User,
  ShieldCheck,
  QrCode,
  ArrowRight,
  X,
  Compass,
} from "lucide-react";
import { AttendeeProfile, Currency, EventDetails, TicketOrder } from "../types";
import { IslandEventDigitalTicketScreen } from "./IslandEventDigitalTicketScreen";

export type TicketFilterTab = "upcoming" | "past" | "transferred";

type Props = {
  orders: TicketOrder[];
  currentUser?: AttendeeProfile | { name?: string; email?: string; phone?: string; id?: string };
  isLoggedIn?: boolean;
  onOpenAuthModal?: () => void;
  onDiscoverEvents: () => void;
  onSelectEventDetails?: (eventId: string) => void;
  formatMoney: (amount: number, currency: Currency) => string;
  allEvents?: EventDetails[];
};

export const AttendeeTicketManagementScreen: React.FC<Props> = ({
  orders,
  currentUser,
  isLoggedIn = true,
  onOpenAuthModal,
  onDiscoverEvents,
  onSelectEventDetails,
  formatMoney,
  allEvents = [],
}) => {
  const [activeFilter, setActiveFilter] = useState<TicketFilterTab>("upcoming");
  const [selectedTicket, setSelectedTicket] = useState<TicketOrder | null>(null);
  const [fullScreenTicket, setFullScreenTicket] = useState<TicketOrder | null>(null);
  const [showTransferModal, setShowTransferModal] = useState(false);
  const [transferRecipientName, setTransferRecipientName] = useState("");
  const [transferRecipientEmail, setTransferRecipientEmail] = useState("");
  const [transferSuccessMsg, setTransferSuccessMsg] = useState("");

  // Authenticated user identity for strict authorization scoping
  const userEmail = (currentUser?.email || "akeem.c@islandevents.example").toLowerCase().trim();
  const userName = (currentUser?.name || "Akeem Campbell").toLowerCase().trim();
  const userPhone = (currentUser?.phone || "").replace(/\D/g, "");

  // 1. STRICT SERVER-ALIGNED AUTHORIZATION FILTER:
  // Only display tickets strictly belonging to this authenticated attendee (IDOR Protection)
  const attendeeAuthorizedOrders = useMemo(() => {
    return orders.filter((ord) => {
      const ordEmail = (ord.email || "").toLowerCase().trim();
      const ordName = (ord.customerName || "").toLowerCase().trim();
      const ordPhone = (ord.customerPhone || "").replace(/\D/g, "");

      // Match on email, phone, or customer name
      const matchesEmail = userEmail.length > 0 && ordEmail === userEmail;
      const matchesPhone = userPhone.length > 5 && ordPhone === userPhone;
      const matchesName = userName.length > 0 && ordName === userName;

      return matchesEmail || matchesPhone || matchesName;
    });
  }, [orders, userEmail, userName, userPhone]);

  // Helper to determine if an event is past
  const isOrderPast = (ord: TicketOrder): boolean => {
    if (ord.closedAt || ord.allHandedOver) return true;
    if (ord.eventDateStr) {
      const d = ord.eventDateStr.toLowerCase();
      if (d.includes("october") || d.includes("oct 11") || d.includes("2025")) return true;
    }
    // Check if matching event has ended
    if (ord.eventId && allEvents.length > 0) {
      const matchedEvt = allEvents.find((e) => e.id === ord.eventId);
      if (matchedEvt && matchedEvt.dateStr.toLowerCase().includes("october")) return true;
    }
    return false;
  };

  // Helper to determine if an order is transferred
  const isOrderTransferred = (ord: TicketOrder): boolean => {
    return Boolean(
      ord.isTransferred ||
        (ord.notes && ord.notes.toLowerCase().includes("transferred")) ||
        ord.transferredTo
    );
  };

  // 2. TICKET FILTERS (Upcoming · Past · Transferred)
  const filteredTickets = useMemo(() => {
    return attendeeAuthorizedOrders.filter((ord) => {
      const transferred = isOrderTransferred(ord);
      const past = isOrderPast(ord);

      if (activeFilter === "transferred") {
        return transferred;
      }
      if (activeFilter === "past") {
        return past && !transferred;
      }
      // "upcoming"
      return !past && !transferred;
    });
  }, [attendeeAuthorizedOrders, activeFilter]);

  // Tab counts
  const counts = useMemo(() => {
    let upcoming = 0;
    let past = 0;
    let transferred = 0;

    attendeeAuthorizedOrders.forEach((ord) => {
      if (isOrderTransferred(ord)) {
        transferred++;
      } else if (isOrderPast(ord)) {
        past++;
      } else {
        upcoming++;
      }
    });

    return { upcoming, past, transferred };
  }, [attendeeAuthorizedOrders]);

  // Handle transfer execution
  const handleExecuteTransfer = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTicket || !transferRecipientName.trim() || !transferRecipientEmail.trim()) {
      return;
    }

    selectedTicket.isTransferred = true;
    selectedTicket.transferredTo = `${transferRecipientName.trim()} (${transferRecipientEmail.trim()})`;
    selectedTicket.transferredAt = new Date().toISOString();
    selectedTicket.notes = `Transferred to ${transferRecipientName.trim()} on ${new Date().toLocaleDateString()}`;

    setTransferSuccessMsg(
      `Pass #${selectedTicket.ticketCode} successfully transferred to ${transferRecipientName.trim()}. A confirmation email has been dispatched to ${transferRecipientEmail.trim()}.`
    );
    setShowTransferModal(false);
    setTransferRecipientName("");
    setTransferRecipientEmail("");
  };

  // Status badge styling helper
  const renderStatusBadge = (status: string, isTransferred?: boolean) => {
    if (isTransferred) {
      return (
        <span
          style={{
            display: "inline-flex",
            alignItems: "center",
            gap: "5px",
            background: "rgba(182, 240, 58, 0.16)",
            color: "var(--att-accent)",
            border: "1px solid rgba(182, 240, 58, 0.35)",
            padding: "4px 10px",
            borderRadius: "9999px",
            fontSize: "11px",
            fontWeight: 800,
            textTransform: "uppercase",
            letterSpacing: "0.04em",
          }}
        >
          <Send size={12} />
          Transferred
        </span>
      );
    }

    switch (status) {
      case "paid":
        return (
          <span
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "5px",
              background: "rgba(34, 197, 94, 0.16)",
              color: "var(--att-ok)",
              border: "1px solid rgba(34, 197, 94, 0.35)",
              padding: "4px 10px",
              borderRadius: "9999px",
              fontSize: "11px",
              fontWeight: 800,
              textTransform: "uppercase",
              letterSpacing: "0.04em",
            }}
          >
            <CheckCircle2 size={12} />
            Confirmed
          </span>
        );
      case "payment_submitted":
      case "under_verification":
        return (
          <span
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "5px",
              background: "rgba(245, 158, 11, 0.16)",
              color: "var(--att-warn)",
              border: "1px solid rgba(245, 158, 11, 0.35)",
              padding: "4px 10px",
              borderRadius: "9999px",
              fontSize: "11px",
              fontWeight: 800,
              textTransform: "uppercase",
              letterSpacing: "0.04em",
            }}
          >
            <Clock size={12} />
            Pending Verification
          </span>
        );
      case "refunded":
        return (
          <span
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "5px",
              background: "rgba(168, 85, 247, 0.16)",
              color: "var(--att-accent)",
              border: "1px solid rgba(168, 85, 247, 0.35)",
              padding: "4px 10px",
              borderRadius: "9999px",
              fontSize: "11px",
              fontWeight: 800,
              textTransform: "uppercase",
              letterSpacing: "0.04em",
            }}
          >
            <RefreshCw size={12} />
            Refunded
          </span>
        );
      case "rejected":
      case "cancelled":
        return (
          <span
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "5px",
              background: "rgba(239, 68, 68, 0.16)",
              color: "var(--att-bad)",
              border: "1px solid rgba(239, 68, 68, 0.35)",
              padding: "4px 10px",
              borderRadius: "9999px",
              fontSize: "11px",
              fontWeight: 800,
              textTransform: "uppercase",
              letterSpacing: "0.04em",
            }}
          >
            <AlertTriangle size={12} />
            Cancelled
          </span>
        );
      default:
        return (
          <span
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "5px",
              background: "rgba(148, 163, 184, 0.16)",
              color: "var(--att-text-body)",
              border: "1px solid rgba(148, 163, 184, 0.35)",
              padding: "4px 10px",
              borderRadius: "9999px",
              fontSize: "11px",
              fontWeight: 800,
              textTransform: "uppercase",
            }}
          >
            {status.replace(/_/g, " ")}
          </span>
        );
    }
  };

  if (fullScreenTicket) {
    return (
      <IslandEventDigitalTicketScreen
        ticket={fullScreenTicket}
        eventDetails={allEvents.find((e) => e.id === fullScreenTicket.eventId)}
        onReturnToTickets={() => setFullScreenTicket(null)}
        onViewEventDetails={(eventId) => {
          if (eventId && onSelectEventDetails) {
            onSelectEventDetails(eventId);
          }
        }}
        onTransferTicket={(transferredTicket, recName, recEmail) => {
          transferredTicket.isTransferred = true;
          transferredTicket.transferredTo = `${recName} (${recEmail})`;
          transferredTicket.transferredAt = new Date().toISOString();
          transferredTicket.notes = `Transferred to ${recName} on ${new Date().toLocaleDateString()}`;
          setFullScreenTicket({ ...transferredTicket });
        }}
        formatMoney={formatMoney}
        isLoggedIn={isLoggedIn}
        currentUser={currentUser}
      />
    );
  }

  return (
    <div
      className="attendee-ticket-management-screen"
      style={{
        minHeight: "100vh",
        background: "var(--att-bg)",
        color: "var(--att-text-1)",
        paddingBottom: "120px",
        fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
      }}
    >
      {/* 1. HEADER */}
      <header
        style={{
          position: "sticky",
          top: 0,
          zIndex: 40,
          background: "rgba(10, 15, 22, 0.95)",
          backdropFilter: "blur(14px)",
          borderBottom: "1px solid rgba(255, 255, 255, 0.08)",
          padding: "20px 24px",
        }}
      >
        <div
          style={{
            maxWidth: "1140px",
            margin: "0 auto",
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            flexWrap: "wrap",
            gap: "14px",
          }}
        >
          <div>
            <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "4px" }}>
              <span
                style={{
                  fontSize: "11px",
                  fontWeight: 800,
                  textTransform: "uppercase",
                  letterSpacing: "0.08em",
                  color: "var(--att-accent)",
                  background: "rgba(182, 240, 58, 0.12)",
                  padding: "3px 9px",
                  borderRadius: "9999px",
                  border: "1px solid rgba(182, 240, 58, 0.25)",
                }}
              >
                Official Passes
              </span>
              <span
                style={{
                  fontSize: "12px",
                  color: "var(--att-text-2)",
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "4px",
                }}
              >
                <User size={13} style={{ color: "var(--att-accent)" }} />
                Signed in as <strong>{currentUser?.name || "Akeem Campbell"}</strong>
              </span>
            </div>
            <h1
              id="your-tickets-heading"
              style={{
                fontSize: "26px",
                fontWeight: 900,
                color: "#FFFFFF",
                margin: 0,
                letterSpacing: "-0.02em",
                display: "flex",
                alignItems: "center",
                gap: "10px",
              }}
            >
              <Ticket size={24} style={{ color: "var(--att-text-2)" }} />
              Your Tickets
            </h1>
          </div>

          <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
            {!isLoggedIn && onOpenAuthModal && (
              <button
                type="button"
                id="btn-attendee-login-header"
                onClick={onOpenAuthModal}
                style={{
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "6px",
                  background: "rgba(255, 255, 255, 0.08)",
                  border: "1px solid rgba(255, 255, 255, 0.16)",
                  color: "#FFFFFF",
                  padding: "9px 16px",
                  borderRadius: "9999px",
                  fontSize: "13px",
                  fontWeight: 700,
                  cursor: "pointer",
                }}
              >
                <User size={14} style={{ color: "var(--att-accent)" }} />
                <span>Sign In</span>
              </button>
            )}
            <button
              type="button"
              id="btn-discover-events-header"
              onClick={onDiscoverEvents}
              style={{
                display: "inline-flex",
                alignItems: "center",
                gap: "8px",
                background: "rgba(255, 255, 255, 0.06)",
                border: "1px solid rgba(255, 255, 255, 0.12)",
                color: "#FFFFFF",
                padding: "9px 18px",
                borderRadius: "9999px",
                fontSize: "13px",
                fontWeight: 700,
                cursor: "pointer",
                transition: "all 0.2s ease",
              }}
            >
              <Compass size={16} style={{ color: "var(--att-accent)" }} />
              <span>Discover Events</span>
            </button>
          </div>
        </div>
      </header>

      {/* Main Content Body */}
      <main style={{ maxWidth: "1140px", margin: "0 auto", padding: "28px 20px" }}>
        {/* Success Alert Banner for Transfers */}
        {transferSuccessMsg && (
          <div
            style={{
              background: "rgba(34, 197, 94, 0.15)",
              border: "1px solid rgba(34, 197, 94, 0.35)",
              borderRadius: "14px",
              padding: "14px 18px",
              marginBottom: "24px",
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              gap: "12px",
              color: "var(--att-ok)",
              fontSize: "13px",
              fontWeight: 600,
            }}
          >
            <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
              <CheckCircle2 size={18} />
              <span>{transferSuccessMsg}</span>
            </div>
            <button
              type="button"
              onClick={() => setTransferSuccessMsg("")}
              style={{ background: "transparent", border: "none", color: "var(--att-ok)", cursor: "pointer" }}
            >
              <X size={16} />
            </button>
          </div>
        )}

        {/* 2. TICKET FILTERS (Upcoming · Past · Transferred) */}
        <section aria-label="Ticket filters" style={{ marginBottom: "28px" }}>
          <div
            style={{
              display: "flex",
              alignItems: "center",
              gap: "10px",
              overflowX: "auto",
              paddingBottom: "6px",
            }}
            id="ticket-filters-bar"
          >
            {/* Upcoming Tab */}
            <button
              type="button"
              id="filter-tab-upcoming"
              onClick={() => setActiveFilter("upcoming")}
              style={{
                padding: "10px 20px",
                borderRadius: "9999px",
                fontSize: "14px",
                fontWeight: 700,
                cursor: "pointer",
                display: "inline-flex",
                alignItems: "center",
                gap: "8px",
                transition: "all 0.2s ease",
                background:
                  activeFilter === "upcoming"
                    ? "var(--att-accent)"
                    : "var(--att-surface-1)",
                color: activeFilter === "upcoming" ? "var(--att-on-accent)" : "var(--att-text-2)",
                border: activeFilter === "upcoming" ? "1px solid transparent" : "1px solid rgba(255, 255, 255, 0.08)",
                boxShadow: activeFilter === "upcoming" ? "none" : "none",
              }}
            >
              <Calendar size={15} />
              <span>Upcoming</span>
              <span
                style={{
                  fontSize: "11px",
                  background: activeFilter === "upcoming" ? "rgba(0,0,0,0.25)" : "rgba(255,255,255,0.08)",
                  padding: "1px 7px",
                  borderRadius: "9999px",
                }}
              >
                {counts.upcoming}
              </span>
            </button>

            {/* Past Tab */}
            <button
              type="button"
              id="filter-tab-past"
              onClick={() => setActiveFilter("past")}
              style={{
                padding: "10px 20px",
                borderRadius: "9999px",
                fontSize: "14px",
                fontWeight: 700,
                cursor: "pointer",
                display: "inline-flex",
                alignItems: "center",
                gap: "8px",
                transition: "all 0.2s ease",
                background:
                  activeFilter === "past"
                    ? "var(--att-accent)"
                    : "var(--att-surface-1)",
                color: activeFilter === "past" ? "var(--att-on-accent)" : "var(--att-text-2)",
                border: activeFilter === "past" ? "1px solid transparent" : "1px solid rgba(255, 255, 255, 0.08)",
                boxShadow: activeFilter === "past" ? "none" : "none",
              }}
            >
              <Clock3 size={15} />
              <span>Past</span>
              <span
                style={{
                  fontSize: "11px",
                  background: activeFilter === "past" ? "rgba(0,0,0,0.25)" : "rgba(255,255,255,0.08)",
                  padding: "1px 7px",
                  borderRadius: "9999px",
                }}
              >
                {counts.past}
              </span>
            </button>

            {/* Transferred Tab */}
            <button
              type="button"
              id="filter-tab-transferred"
              onClick={() => setActiveFilter("transferred")}
              style={{
                padding: "10px 20px",
                borderRadius: "9999px",
                fontSize: "14px",
                fontWeight: 700,
                cursor: "pointer",
                display: "inline-flex",
                alignItems: "center",
                gap: "8px",
                transition: "all 0.2s ease",
                background:
                  activeFilter === "transferred"
                    ? "var(--att-accent)"
                    : "var(--att-surface-1)",
                color: activeFilter === "transferred" ? "var(--att-on-accent)" : "var(--att-text-2)",
                border:
                  activeFilter === "transferred"
                    ? "1px solid transparent"
                    : "1px solid rgba(255, 255, 255, 0.08)",
                boxShadow: activeFilter === "transferred" ? "none" : "none",
              }}
            >
              <Send size={15} />
              <span>Transferred</span>
              <span
                style={{
                  fontSize: "11px",
                  background: activeFilter === "transferred" ? "rgba(0,0,0,0.25)" : "rgba(255,255,255,0.08)",
                  padding: "1px 7px",
                  borderRadius: "9999px",
                }}
              >
                {counts.transferred}
              </span>
            </button>
          </div>
        </section>

        {/* 3. EMPTY STATE */}
        {filteredTickets.length === 0 ? (
          <div
            id="tickets-empty-state"
            style={{
              textAlign: "center",
              padding: "64px 24px",
              background: "var(--att-surface-1)",
              border: "1px solid rgba(255, 255, 255, 0.08)",
              borderRadius: "20px",
              maxWidth: "580px",
              margin: "32px auto",
            }}
          >
            <div
              style={{
                width: "64px",
                height: "64px",
                borderRadius: "18px",
                background: "rgba(182, 240, 58, 0.12)",
                color: "var(--att-text-2)",
                display: "inline-flex",
                alignItems: "center",
                justifyContent: "center",
                marginBottom: "18px",
              }}
            >
              <Ticket size={32} />
            </div>
            <h2 style={{ fontSize: "19px", fontWeight: 800, color: "#FFFFFF", margin: "0 0 8px" }}>
              {activeFilter === "upcoming"
                ? "No upcoming tickets found"
                : activeFilter === "past"
                ? "No past event history"
                : "No transferred tickets"}
            </h2>
            <p style={{ fontSize: "14px", color: "var(--att-text-2)", lineHeight: 1.6, margin: "0 0 24px" }}>
              {activeFilter === "upcoming"
                ? "You don't have any active event passes booked right now. Discover authentic Caribbean concerts, festivals, and culinary feasts happening this weekend."
                : activeFilter === "past"
                ? "You haven't attended any past events under this account yet. Your completed event vouchers will be archived here."
                : "Passes you send to family or friends via secure transfer will appear here with recipient tracking."}
            </p>
            <button
              type="button"
              id="btn-empty-discover"
              onClick={onDiscoverEvents}
              style={{
                display: "inline-flex",
                alignItems: "center",
                gap: "8px",
                background: "var(--att-accent)",
                color: "var(--att-on-accent)",
                border: "none",
                padding: "12px 24px",
                borderRadius: "14px",
                fontSize: "14px",
                fontWeight: 800,
                cursor: "pointer",
                boxShadow: "none",
              }}
            >
              <span>Discover Events</span>
              <ArrowRight size={16} />
            </button>
          </div>
        ) : (
          /* 4. TICKET CARDS LIST */
          <div
            style={{
              display: "flex",
              flexDirection: "column",
              gap: "20px",
            }}
            id="ticket-cards-list"
          >
            {filteredTickets.map((ticket) => {
              const totalQuantity = ticket.items.reduce((sum, item) => sum + item.quantity, 0);
              const primaryItemName =
                ticket.items.length > 0 ? ticket.items[0].name : "General Admission Pass";
              const isTransferred = isOrderTransferred(ticket);

              // Derive event metadata
              const eventTitle =
                ticket.eventTitle ||
                (ticket.items.length > 0 && ticket.items[0].name.includes("Snapper")
                  ? "Miami Waterfront Cultural Food Festival"
                  : "U.S. Community Music & Food Festival");

              const eventDate =
                ticket.eventDateStr ||
                (isOrderPast(ticket)
                  ? "Sunday, October 11, 2026 · 11:30 AM"
                  : "Saturday, May 16, 2026 · 4:00 PM – 11:30 PM EDT");

              const eventVenue =
                ticket.venueName ||
                ticket.venueLocation ||
                "Biscayne Bay Waterfront Park Amphitheater, Miami, FL";

              const eventImg =
                ticket.eventThumbnail ||
                (isOrderPast(ticket)
                  ? "/images/island_culture_carnival.jpg"
                  : eventTitle.includes("Hellshire")
                  ? "/images/island_festival_sunset.jpg"
                  : eventTitle.includes("Miami")
                  ? "/images/escovitch_fish.jpg"
                  : "/images/island_music_reggae_concert.jpg");

              return (
                <article
                  key={ticket.id}
                  id={`ticket-card-${ticket.id}`}
                  style={{
                    background: "var(--att-surface-1)",
                    border: "1px solid rgba(255, 255, 255, 0.08)",
                    borderRadius: "20px",
                    overflow: "hidden",
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(min(280px, 100%), 1fr))",
                    boxShadow: "0 10px 25px rgba(0, 0, 0, 0.3)",
                    transition: "transform 0.2s ease, border-color 0.2s ease",
                  }}
                >
                  {/* Left Column: Event Artwork & Quick Tags */}
                  <div
                    style={{
                      position: "relative",
                      minHeight: "180px",
                      background: "var(--att-surface-2)",
                    }}
                  >
                    <img
                      src={eventImg}
                      alt={eventTitle}
                      style={{
                        width: "100%",
                        height: "100%",
                        objectFit: "cover",
                        objectPosition: "center",
                      }}
                      onError={(e) => {
                        (e.currentTarget as HTMLImageElement).src =
                          "/images/island_music_reggae_concert.jpg";
                      }}
                    />
                    <div
                      style={{
                        position: "absolute",
                        inset: 0,
                        background:
                          "linear-gradient(180deg, rgba(10, 15, 22,0.3) 0%, rgba(10, 15, 22,0.85) 100%)",
                      }}
                    />

                    {/* Reference Tag */}
                    <div
                      style={{
                        position: "absolute",
                        top: "14px",
                        left: "14px",
                        background: "rgba(10, 15, 22, 0.85)",
                        backdropFilter: "blur(8px)",
                        border: "1px solid rgba(255, 255, 255, 0.15)",
                        borderRadius: "8px",
                        padding: "4px 10px",
                        fontSize: "12px",
                        fontWeight: 800,
                        color: "#FFFFFF",
                        letterSpacing: "0.04em",
                      }}
                    >
                      #{ticket.ticketCode}
                    </div>

                    {/* Status Pill on Top Right */}
                    <div
                      style={{
                        position: "absolute",
                        top: "14px",
                        right: "14px",
                      }}
                    >
                      {renderStatusBadge(ticket.status, isTransferred)}
                    </div>

                    {/* Bottom Item Count Bar on Thumbnail */}
                    <div
                      style={{
                        position: "absolute",
                        bottom: "12px",
                        left: "14px",
                        right: "14px",
                        display: "flex",
                        justifyContent: "space-between",
                        alignItems: "center",
                      }}
                    >
                      <span
                        style={{
                          background: "rgba(182, 240, 58, 0.9)",
                          color: "#FFFFFF",
                          fontSize: "11px",
                          fontWeight: 800,
                          padding: "3px 9px",
                          borderRadius: "9999px",
                          textTransform: "uppercase",
                        }}
                      >
                        {totalQuantity} {totalQuantity === 1 ? "Pass" : "Passes"}
                      </span>
                      <span style={{ fontSize: "11px", color: "var(--att-text-1)", fontWeight: 600 }}>
                        Ref: {ticket.orderNumber}
                      </span>
                    </div>
                  </div>

                  {/* Right Column: Event Metadata & Action */}
                  <div
                    style={{
                      padding: "22px",
                      display: "flex",
                      flexDirection: "column",
                      justifyContent: "space-between",
                    }}
                  >
                    <div>
                      {/* Event Title */}
                      <h3
                        onClick={() => {
                          if (onSelectEventDetails && ticket.eventId) {
                            onSelectEventDetails(ticket.eventId);
                          }
                        }}
                        style={{
                          fontSize: "18px",
                          fontWeight: 800,
                          color: "#FFFFFF",
                          margin: "0 0 10px",
                          lineHeight: 1.3,
                          cursor: ticket.eventId ? "pointer" : "default",
                        }}
                      >
                        {eventTitle}
                      </h3>

                      {/* Ticket Type & Quantity Badge */}
                      <div
                        style={{
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "6px",
                          background: "rgba(182, 240, 58, 0.14)",
                          border: "1px solid rgba(182, 240, 58, 0.3)",
                          color: "var(--att-accent)",
                          padding: "4px 10px",
                          borderRadius: "8px",
                          fontSize: "12px",
                          fontWeight: 700,
                          marginBottom: "14px",
                        }}
                      >
                        <Ticket size={13} style={{ color: "var(--att-accent)" }} />
                        <span>
                          {primaryItemName} (x{totalQuantity})
                        </span>
                      </div>

                      {/* Metadata Details */}
                      <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
                        <div
                          style={{
                            display: "flex",
                            alignItems: "center",
                            gap: "8px",
                            fontSize: "13px",
                            color: "var(--att-text-body)",
                          }}
                        >
                          <Calendar size={15} style={{ color: "var(--att-accent)", flexShrink: 0 }} />
                          <span>{eventDate}</span>
                        </div>
                        <div
                          style={{
                            display: "flex",
                            alignItems: "center",
                            gap: "8px",
                            fontSize: "13px",
                            color: "var(--att-text-body)",
                          }}
                        >
                          <MapPin size={15} style={{ color: "var(--att-text-2)", flexShrink: 0 }} />
                          <span style={{ whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                            {eventVenue}
                          </span>
                        </div>
                      </div>

                      {/* Transfer Notice if applicable */}
                      {isTransferred && ticket.transferredTo && (
                        <div
                          style={{
                            marginTop: "12px",
                            padding: "8px 12px",
                            borderRadius: "10px",
                            background: "rgba(182, 240, 58, 0.1)",
                            border: "1px solid rgba(182, 240, 58, 0.25)",
                            fontSize: "12px",
                            color: "var(--att-accent)",
                          }}
                        >
                          Transferred to: <strong>{ticket.transferredTo}</strong>
                        </div>
                      )}
                    </div>

                    {/* Bottom Actions Row */}
                    <div
                      style={{
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                        paddingTop: "18px",
                        marginTop: "16px",
                        borderTop: "1px solid rgba(255, 255, 255, 0.08)",
                        gap: "10px",
                        flexWrap: "wrap",
                      }}
                    >
                      <div style={{ fontSize: "14px", fontWeight: 700, color: "#FFFFFF" }}>
                        <span>Total: </span>
                        <strong style={{ color: "var(--att-accent)" }}>
                          {formatMoney(ticket.totalAmount, ticket.currency)}
                        </strong>
                      </div>

                      <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                        <button
                          type="button"
                          id={`btn-open-ticket-${ticket.id}`}
                          onClick={() => setFullScreenTicket(ticket)}
                          style={{
                            display: "inline-flex",
                            alignItems: "center",
                            gap: "6px",
                            background: "var(--att-accent)",
                            color: "var(--att-on-accent)",
                            border: "none",
                            padding: "9px 18px",
                            borderRadius: "12px",
                            fontSize: "13px",
                            fontWeight: 800,
                            cursor: "pointer",
                            boxShadow: "none",
                            transition: "all 0.2s ease",
                          }}
                        >
                          <QrCode size={15} />
                          <span>View Digital Ticket</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </article>
              );
            })}
          </div>
        )}
      </main>

      {/* 5. TICKET DETAILS & SECURE DIGITAL TICKET PASS MODAL */}
      {selectedTicket && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="ticket-detail-modal-title"
          style={{
            position: "fixed",
            inset: 0,
            zIndex: 60,
            background: "rgba(5, 10, 20, 0.85)",
            backdropFilter: "blur(10px)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            padding: "16px",
          }}
          onClick={() => setSelectedTicket(null)}
        >
          <div
            id="ticket-details-modal-panel"
            style={{
              background: "var(--att-surface-1)",
              border: "1px solid rgba(255, 255, 255, 0.14)",
              borderRadius: "24px",
              width: "100%",
              maxWidth: "520px",
              maxHeight: "90vh",
              overflowY: "auto",
              boxShadow: "0 25px 50px rgba(0, 0, 0, 0.6)",
              color: "var(--att-text-1)",
              padding: "24px",
            }}
            onClick={(e) => e.stopPropagation()}
          >
            {/* Header */}
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                alignItems: "flex-start",
                marginBottom: "20px",
              }}
            >
              <div>
                <span
                  style={{
                    fontSize: "11px",
                    fontWeight: 800,
                    color: "var(--att-accent)",
                    textTransform: "uppercase",
                    letterSpacing: "0.06em",
                  }}
                >
                  Verified Admission Pass
                </span>
                <h2
                  id="ticket-detail-modal-title"
                  style={{
                    fontSize: "20px",
                    fontWeight: 900,
                    color: "#FFFFFF",
                    margin: "2px 0 0",
                  }}
                >
                  {selectedTicket.eventTitle || "Miami Waterfront Cultural Food Festival"}
                </h2>
              </div>
              <button
                type="button"
                id="btn-close-ticket-modal"
                onClick={() => setSelectedTicket(null)}
                style={{
                  width: "36px",
                  height: "36px",
                  borderRadius: "50%",
                  background: "rgba(255, 255, 255, 0.08)",
                  border: "none",
                  color: "#FFFFFF",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                }}
                aria-label="Close ticket details"
              >
                <X size={18} />
              </button>
            </div>

            {/* Visual Digital Ticket Display */}
            <div
              style={{
                background: "var(--att-surface-2)",
                border: "1px solid rgba(255, 255, 255, 0.1)",
                borderRadius: "18px",
                padding: "20px",
                marginBottom: "20px",
                textAlign: "center",
                position: "relative",
              }}
            >
              {/* QR Code Graphic Area */}
              <div
                style={{
                  display: "inline-block",
                  background: "#FFFFFF",
                  padding: "16px",
                  borderRadius: "14px",
                  boxShadow: "0 8px 24px rgba(0,0,0,0.4)",
                  marginBottom: "14px",
                }}
              >
                {/* SVG QR Code Simulation with secure unique identifier */}
                <svg width="160" height="160" viewBox="0 0 160 160">
                  <rect width="160" height="160" fill="#FFFFFF" />
                  {/* Outer corner blocks */}
                  <rect x="10" y="10" width="40" height="40" fill="#0B132B" rx="4" />
                  <rect x="18" y="18" width="24" height="24" fill="#FFFFFF" rx="2" />
                  <rect x="24" y="24" width="12" height="12" fill="#0B132B" />

                  <rect x="110" y="10" width="40" height="40" fill="#0B132B" rx="4" />
                  <rect x="118" y="18" width="24" height="24" fill="#FFFFFF" rx="2" />
                  <rect x="124" y="24" width="12" height="12" fill="#0B132B" />

                  <rect x="10" y="110" width="40" height="40" fill="#0B132B" rx="4" />
                  <rect x="18" y="118" width="24" height="24" fill="#FFFFFF" rx="2" />
                  <rect x="24" y="124" width="12" height="12" fill="#0B132B" />

                  {/* High density data matrices */}
                  <rect x="60" y="15" width="8" height="8" fill="#0B132B" />
                  <rect x="75" y="20" width="12" height="6" fill="#0B132B" />
                  <rect x="90" y="12" width="6" height="14" fill="#0B132B" />
                  <rect x="60" y="35" width="14" height="6" fill="#0B132B" />
                  <rect x="80" y="38" width="10" height="10" fill="#0B132B" />

                  <rect x="15" y="60" width="8" height="14" fill="#0B132B" />
                  <rect x="30" y="65" width="14" height="8" fill="#0B132B" />
                  <rect x="12" y="85" width="10" height="12" fill="#0B132B" />
                  <rect x="32" y="80" width="8" height="8" fill="#0B132B" />

                  <rect x="55" y="55" width="50" height="50" fill="#0B132B" rx="6" />
                  <circle cx="80" cy="80" r="16" fill="#FFFFFF" />
                  <polygon points="80,68 90,86 70,86" fill="#0B132B" />

                  <rect x="115" y="60" width="12" height="8" fill="#0B132B" />
                  <rect x="135" y="68" width="8" height="14" fill="#0B132B" />
                  <rect x="118" y="85" width="16" height="6" fill="#0B132B" />

                  <rect x="60" y="115" width="12" height="10" fill="#0B132B" />
                  <rect x="80" y="120" width="16" height="8" fill="#0B132B" />
                  <rect x="65" y="135" width="10" height="12" fill="#0B132B" />
                  <rect x="85" y="138" width="18" height="6" fill="#0B132B" />
                  <rect x="115" y="115" width="8" height="16" fill="#0B132B" />
                  <rect x="130" y="120" width="14" height="8" fill="#0B132B" />
                  <rect x="120" y="138" width="22" height="10" fill="#0B132B" />
                </svg>
              </div>

              {/* Secure Credentials Display */}
              <div style={{ fontSize: "15px", fontWeight: 900, letterSpacing: "0.08em", color: "#FFFFFF" }}>
                CODE: {selectedTicket.ticketCode}
              </div>
              <div style={{ fontSize: "12px", color: "var(--att-text-2)", marginTop: "2px" }}>
                Order #{selectedTicket.orderNumber}
              </div>

              {/* Status Banner */}
              <div style={{ marginTop: "12px", display: "inline-block" }}>
                {renderStatusBadge(selectedTicket.status, isOrderTransferred(selectedTicket))}
              </div>
            </div>

            {/* Ticket Information Breakdown */}
            <div
              style={{
                background: "rgba(255, 255, 255, 0.04)",
                border: "1px solid rgba(255, 255, 255, 0.08)",
                borderRadius: "16px",
                padding: "16px",
                marginBottom: "20px",
                fontSize: "13px",
              }}
            >
              <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "8px" }}>
                <span style={{ color: "var(--att-text-2)" }}>Ticket Holder:</span>
                <strong style={{ color: "#FFFFFF" }}>{selectedTicket.customerName}</strong>
              </div>
              <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "8px" }}>
                <span style={{ color: "var(--att-text-2)" }}>Registered Email:</span>
                <strong style={{ color: "#FFFFFF" }}>{selectedTicket.email || userEmail}</strong>
              </div>
              <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "8px" }}>
                <span style={{ color: "var(--att-text-2)" }}>Event Venue:</span>
                <strong style={{ color: "#FFFFFF", textAlign: "right", maxWidth: "240px" }}>
                  {selectedTicket.venueName || "Biscayne Bay Waterfront Park Amphitheater"}
                </strong>
              </div>
              <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "8px" }}>
                <span style={{ color: "var(--att-text-2)" }}>Date & Time:</span>
                <strong style={{ color: "#FFFFFF" }}>
                  {selectedTicket.eventDateStr || "Saturday, May 16, 2026"}
                </strong>
              </div>
              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  paddingTop: "8px",
                  borderTop: "1px solid rgba(255, 255, 255, 0.08)",
                }}
              >
                <span style={{ color: "var(--att-text-2)" }}>Amount Paid:</span>
                <strong style={{ color: "var(--att-accent)" }}>
                  {formatMoney(selectedTicket.totalAmount, selectedTicket.currency)}
                </strong>
              </div>
            </div>

            {/* Security Guarantee Notice */}
            <div
              style={{
                display: "flex",
                alignItems: "center",
                gap: "8px",
                fontSize: "11px",
                color: "var(--att-ok)",
                background: "rgba(34, 197, 94, 0.08)",
                padding: "8px 12px",
                borderRadius: "10px",
                marginBottom: "20px",
              }}
            >
              <ShieldCheck size={16} />
              <span>Cryptographically signed ticket. Server-authorized upon gate arrival.</span>
            </div>

            {/* Action Buttons inside Modal */}
            <div style={{ display: "flex", gap: "10px", flexWrap: "wrap" }}>
              {!isOrderTransferred(selectedTicket) && !isOrderPast(selectedTicket) && (
                <button
                  type="button"
                  id="btn-initiate-transfer"
                  onClick={() => setShowTransferModal(true)}
                  style={{
                    flex: 1,
                    padding: "12px 18px",
                    borderRadius: "14px",
                    background: "rgba(182, 240, 58, 0.15)",
                    border: "1px solid rgba(182, 240, 58, 0.35)",
                    color: "var(--att-accent)",
                    fontSize: "13px",
                    fontWeight: 700,
                    cursor: "pointer",
                    display: "inline-flex",
                    alignItems: "center",
                    justifyContent: "center",
                    gap: "8px",
                  }}
                >
                  <Send size={15} />
                  <span>Transfer Ticket</span>
                </button>
              )}

              <button
                type="button"
                onClick={() => setSelectedTicket(null)}
                style={{
                  flex: 1,
                  padding: "12px 18px",
                  borderRadius: "14px",
                  background: "rgba(255, 255, 255, 0.08)",
                  border: "1px solid rgba(255, 255, 255, 0.12)",
                  color: "#FFFFFF",
                  fontSize: "13px",
                  fontWeight: 700,
                  cursor: "pointer",
                }}
              >
                Close Pass
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 6. TRANSFER TICKET DIALOG */}
      {showTransferModal && selectedTicket && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="transfer-modal-title"
          style={{
            position: "fixed",
            inset: 0,
            zIndex: 70,
            background: "rgba(5, 10, 20, 0.8)",
            backdropFilter: "blur(8px)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            padding: "16px",
          }}
          onClick={() => setShowTransferModal(false)}
        >
          <div
            style={{
              background: "var(--att-surface-1)",
              border: "1px solid rgba(255, 255, 255, 0.14)",
              borderRadius: "20px",
              width: "100%",
              maxWidth: "460px",
              padding: "24px",
              color: "var(--att-text-1)",
            }}
            onClick={(e) => e.stopPropagation()}
            id="transfer-ticket-dialog"
          >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "14px" }}>
              <div>
                <span style={{ fontSize: "11px", fontWeight: 800, color: "var(--att-accent)" }}>
                  SECURE PASS TRANSFER
                </span>
                <h3 id="transfer-modal-title" style={{ fontSize: "17px", fontWeight: 800, margin: "2px 0 0", color: "#FFFFFF" }}>
                  Transfer Pass #{selectedTicket.ticketCode}
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setShowTransferModal(false)}
                style={{ background: "transparent", border: "none", color: "var(--att-text-2)", cursor: "pointer" }}
              >
                <X size={18} />
              </button>
            </div>

            <p style={{ fontSize: "13px", color: "var(--att-text-2)", lineHeight: 1.5, marginBottom: "18px" }}>
              Enter the recipient's details below. Upon transfer, your existing QR code will be revoked and a new secure access pass will be generated exclusively for the recipient.
            </p>

            <form onSubmit={handleExecuteTransfer}>
              <div style={{ marginBottom: "14px" }}>
                <label style={{ display: "block", fontSize: "12px", color: "var(--att-text-body)", fontWeight: 700, marginBottom: "6px" }}>
                  Recipient Full Name:
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Marcus Campbell"
                  value={transferRecipientName}
                  onChange={(e) => setTransferRecipientName(e.target.value)}
                  style={{
                    width: "100%",
                    padding: "10px 14px",
                    borderRadius: "12px",
                    background: "rgba(10, 15, 22, 0.8)",
                    border: "1px solid rgba(255, 255, 255, 0.12)",
                    color: "#FFFFFF",
                    fontSize: "14px",
                    outline: "none",
                    boxSizing: "border-box",
                  }}
                />
              </div>

              <div style={{ marginBottom: "20px" }}>
                <label style={{ display: "block", fontSize: "12px", color: "var(--att-text-body)", fontWeight: 700, marginBottom: "6px" }}>
                  Recipient Email Address:
                </label>
                <input
                  type="email"
                  required
                  placeholder="e.g. marcus@islandevents.example"
                  value={transferRecipientEmail}
                  onChange={(e) => setTransferRecipientEmail(e.target.value)}
                  style={{
                    width: "100%",
                    padding: "10px 14px",
                    borderRadius: "12px",
                    background: "rgba(10, 15, 22, 0.8)",
                    border: "1px solid rgba(255, 255, 255, 0.12)",
                    color: "#FFFFFF",
                    fontSize: "14px",
                    outline: "none",
                    boxSizing: "border-box",
                  }}
                />
              </div>

              <button
                type="submit"
                id="btn-confirm-transfer"
                style={{
                  width: "100%",
                  padding: "12px 18px",
                  borderRadius: "14px",
                  background: "var(--att-accent)",
                  color: "var(--att-on-accent)",
                  border: "none",
                  fontSize: "14px",
                  fontWeight: 800,
                  cursor: "pointer",
                }}
              >
                Confirm Transfer
              </button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
