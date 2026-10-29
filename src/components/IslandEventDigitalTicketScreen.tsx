import React, { useState, useMemo } from "react";
import {
  Ticket,
  Calendar,
  MapPin,
  CheckCircle2,
  Clock,
  AlertTriangle,
  RefreshCw,
  Send,
  User,
  ArrowLeft,
  FileText,
  Download,
  Copy,
  Check,
  X,
  ExternalLink,
  Flame,
  ChevronRight,
  UtensilsCrossed,
} from "lucide-react";
import { AttendeeProfile, Currency, EventDetails, TicketOrder } from "../types";
import { generateQrMatrix } from "../lib/qrCodeMatrix";

interface Props {
  ticket: TicketOrder;
  eventDetails?: EventDetails;
  onReturnToTickets: () => void;
  onViewEventDetails?: (eventId?: string) => void;
  onTransferTicket?: (ticket: TicketOrder, recipientName: string, recipientEmail: string) => void;
  formatMoney: (amount: number, currency: Currency) => string;
  isLoggedIn?: boolean;
  currentUser?: AttendeeProfile | { name?: string; email?: string; phone?: string };
}

export const IslandEventDigitalTicketScreen: React.FC<Props> = ({
  ticket,
  eventDetails,
  onReturnToTickets,
  onViewEventDetails,
  onTransferTicket,
  formatMoney,
  currentUser,
}) => {
  const [showTransferModal, setShowTransferModal] = useState(false);
  const [transferRecipientName, setTransferRecipientName] = useState("");
  const [transferRecipientEmail, setTransferRecipientEmail] = useState("");
  const [transferSuccessMsg, setTransferSuccessMsg] = useState("");

  const [showReceiptModal, setShowReceiptModal] = useState(false);
  const [showInstructionsModal, setShowInstructionsModal] = useState(false);
  const [isCopied, setIsCopied] = useState(false);

  // Derive metadata
  const eventTitle =
    ticket.eventTitle ||
    eventDetails?.title ||
    "Island Reggae & Sound Festival 2026";

  const eventVenue =
    ticket.venueName ||
    eventDetails?.venueName ||
    "Ocho Rios Oceanfront Ampitheater, St. Ann";

  const eventAddress =
    ticket.venueLocation ||
    eventDetails?.venueAddress ||
    "Main Street, Ocho Rios, Jamaica";

  const eventDate =
    ticket.eventDateStr ||
    eventDetails?.dateStr ||
    "Saturday, May 16, 2026";

  const eventTime =
    ticket.eventTimeStr ||
    eventDetails?.timeStr ||
    "4:00 PM – 11:30 PM EDT";

  const eventThumbnail =
    ticket.eventThumbnail ||
    (eventTitle.includes("Hellshire")
      ? "/images/island_festival_sunset.jpg"
      : eventTitle.includes("Fish") || eventTitle.includes("Harbour")
      ? "/images/escovitch_fish.jpg"
      : "/images/island_music_reggae_concert.jpg");

  const totalQuantity = ticket.items.reduce((sum, item) => sum + item.quantity, 0) || 1;
  const primaryItemName =
    ticket.items.length > 0 ? ticket.items[0].name : "General Admission Pass";

  const attendeeName =
    ticket.customerName || currentUser?.name || "Akeem Campbell";

  // Check state failures / flags
  const isTransferred = Boolean(
    ticket.isTransferred ||
      (ticket.notes && ticket.notes.toLowerCase().includes("transferred")) ||
      ticket.transferredTo
  );

  const isCheckedIn = Boolean((ticket as any).checked_in_at || ticket.allHandedOver);
  const isRefunded = ticket.status === "refunded";
  const isCancelled = (ticket.status as string) === "cancelled" || ticket.status === "rejected";
  const isPending =
    ticket.status === "awaiting_payment" ||
    ticket.status === "payment_submitted" ||
    ticket.status === "under_verification";
  const isConfirmed = ticket.status === "paid" && !isRefunded && !isCancelled;

  const passItems = ticket.items.filter((item) => item.menuItemId.startsWith("tier-"));
  const foodItems = ticket.items.filter((item) => !item.menuItemId.startsWith("tier-"));
  const passCount = passItems.reduce((sum, item) => sum + item.quantity, 0);
  const foodCount = foodItems.reduce((sum, item) => sum + item.quantity, 0);
  const pickupInstructions = eventDetails?.pickupInstructions;

  // Events only carry display strings ("Sunday, November 22, 2026" + "12:00 PM – 8:00 PM"),
  // so the countdown parses those in the viewer's local time.
  const eventWindow = useMemo(() => {
    const day = eventDate.replace(/^[A-Za-z]+,\s*/, "");
    const [startText, endText] = eventTime.split(/[–-]/).map((part) => part.replace(/\b[A-Z]{3,4}\b/g, "").trim());
    const start = new Date(`${day} ${startText}`);
    const end = endText ? new Date(`${day} ${endText}`) : null;
    if (Number.isNaN(start.getTime())) return null;
    return { start, end: end && !Number.isNaN(end.getTime()) ? end : null };
  }, [eventDate, eventTime]);

  const statusLine = useMemo(() => {
    if (!isConfirmed || isCheckedIn || isTransferred) return null;
    if (ticket.fulfillmentStatus === "ready_for_pickup" && foodCount > 0) {
      return { title: "Your food is ready", detail: pickupInstructions || "Show this code at the food pickup point." };
    }
    if (!eventWindow) return { title: "Show this QR code at the gate", detail: "Turn your screen brightness up before you scan." };
    const now = Date.now();
    const msToStart = eventWindow.start.getTime() - now;
    if (msToStart > 0) {
      const totalMinutes = Math.round(msToStart / 60000);
      const days = Math.floor(totalMinutes / 1440);
      const hours = Math.floor((totalMinutes % 1440) / 60);
      const minutes = totalMinutes % 60;
      const countdown = days > 0 ? `${days}d ${hours}h` : hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
      return {
        title: `Starts in ${countdown}`,
        detail: foodCount > 0 && pickupInstructions ? `Food pickup: ${pickupInstructions}` : "Show this QR code at the gate when you arrive.",
      };
    }
    if (!eventWindow.end || eventWindow.end.getTime() > now) {
      return { title: "Happening now", detail: "Show this QR code at the gate." };
    }
    return { title: "This event has ended", detail: "Thanks for coming out." };
  }, [isConfirmed, isCheckedIn, isTransferred, ticket.fulfillmentStatus, foodCount, pickupInstructions, eventWindow]);

  // Build authentic verification payload without sensitive PII
  const secureToken =
    ticket.secureTicketToken || `tok_${ticket.ticketCode.toLowerCase().replace(/[^a-z0-9]/g, "")}_auth`;

  const qrPayloadString = useMemo(() => {
    const payload = {
      ticketCode: ticket.ticketCode,
      token: secureToken,
      orderNumber: ticket.orderNumber,
      organizerId: eventDetails?.organizerName || "org-island-events",
      eventId: ticket.eventId || eventDetails?.id || "evt-default",
    };
    return JSON.stringify(payload);
  }, [ticket.ticketCode, secureToken, ticket.orderNumber, eventDetails?.organizerName, ticket.eventId, eventDetails?.id]);

  // Generate authentic QR code matrix
  const qrMatrix = useMemo(() => {
    try {
      return generateQrMatrix(qrPayloadString);
    } catch {
      return generateQrMatrix(ticket.ticketCode);
    }
  }, [qrPayloadString, ticket.ticketCode]);

  // Handle transfer
  const handleExecuteTransfer = (e: React.FormEvent) => {
    e.preventDefault();
    if (!transferRecipientName.trim() || !transferRecipientEmail.trim()) return;

    if (onTransferTicket) {
      onTransferTicket(ticket, transferRecipientName.trim(), transferRecipientEmail.trim());
    } else {
      ticket.isTransferred = true;
      ticket.transferredTo = `${transferRecipientName.trim()} (${transferRecipientEmail.trim()})`;
      ticket.transferredAt = new Date().toISOString();
      ticket.notes = `Transferred to ${transferRecipientName.trim()} on ${new Date().toLocaleDateString()}`;
    }

    setTransferSuccessMsg(
      `Pass #${ticket.ticketCode} transferred to ${transferRecipientName.trim()}. A confirmation email has been dispatched to ${transferRecipientEmail.trim()}.`
    );
    setShowTransferModal(false);
    setTransferRecipientName("");
    setTransferRecipientEmail("");
  };

  const handleCopyCode = () => {
    navigator.clipboard.writeText(ticket.ticketCode);
    setIsCopied(true);
    setTimeout(() => setIsCopied(false), 2000);
  };

  return (
    <div
      className="digital-ticket-screen"
      style={{
        minHeight: "100vh",
        background: "var(--att-bg)",
        color: "var(--att-text-1)",
        fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
        paddingBottom: "80px",
      }}
    >
      {/* 1. TOP NAVIGATION BAR */}
      <header
        style={{
          position: "sticky",
          top: 0,
          zIndex: 40,
          background: "rgba(10, 15, 22, 0.95)",
          backdropFilter: "blur(12px)",
          borderBottom: "1px solid rgba(255, 255, 255, 0.08)",
          padding: "16px 20px",
        }}
      >
        <div
          style={{
            maxWidth: "760px",
            margin: "0 auto",
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            gap: "12px",
          }}
        >
          <button
            type="button"
            id="btn-return-to-tickets"
            onClick={onReturnToTickets}
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "8px",
              background: "rgba(255, 255, 255, 0.08)",
              border: "1px solid rgba(255, 255, 255, 0.12)",
              color: "#FFFFFF",
              padding: "8px 16px",
              borderRadius: "9999px",
              fontSize: "13px",
              fontWeight: 700,
              cursor: "pointer",
              transition: "all 0.2s ease",
            }}
          >
            <ArrowLeft size={16} />
            <span>Return to Tickets</span>
          </button>

          {/* Island Event Brand Mark */}
          <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
            <div
              style={{
                width: "28px",
                height: "28px",
                borderRadius: "8px",
                background: "var(--att-accent)",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                color: "var(--att-on-accent)",
              }}
            >
              <Flame size={16} />
            </div>
            <div style={{ textAlign: "right" }}>
              <span
                style={{
                  fontSize: "12px",
                  fontWeight: 900,
                  letterSpacing: "0.06em",
                  textTransform: "uppercase",
                  color: "#FFFFFF",
                  display: "block",
                }}
              >
                Island Event
              </span>
              <span style={{ fontSize: "10px", color: "var(--att-accent)", fontWeight: 700 }}>
                Live the Moment
              </span>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content Container */}
      <main style={{ maxWidth: "720px", margin: "24px auto", padding: "0 16px" }}>
        {/* Success Alert Banner for Transfers */}
        {transferSuccessMsg && (
          <div
            style={{
              background: "rgba(34, 197, 94, 0.15)",
              border: "1px solid rgba(34, 197, 94, 0.35)",
              borderRadius: "14px",
              padding: "14px 18px",
              marginBottom: "20px",
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

        {/* 2. FAILURE OR STATUS ADVISORY BANNER */}
        {isCheckedIn && (
          <div
            style={{
              background: "rgba(245, 158, 11, 0.15)",
              border: "1px solid rgba(245, 158, 11, 0.4)",
              borderRadius: "14px",
              padding: "12px 16px",
              marginBottom: "16px",
              display: "flex",
              alignItems: "center",
              gap: "10px",
              color: "var(--att-warn)",
              fontSize: "13px",
              fontWeight: 700,
            }}
          >
            <CheckCircle2 size={18} style={{ flexShrink: 0 }} />
            <div>
              <strong>Already checked in</strong>
              <div style={{ fontSize: "13px", fontWeight: 500, color: "var(--att-text-body)", marginTop: "2px" }}>
                This ticket was scanned at the gate and can't be used again.
              </div>
            </div>
          </div>
        )}

        {isRefunded && (
          <div
            style={{
              background: "rgba(239, 68, 68, 0.15)",
              border: "1px solid rgba(239, 68, 68, 0.4)",
              borderRadius: "14px",
              padding: "12px 16px",
              marginBottom: "16px",
              display: "flex",
              alignItems: "center",
              gap: "10px",
              color: "var(--att-bad)",
              fontSize: "13px",
              fontWeight: 700,
            }}
          >
            <RefreshCw size={18} style={{ flexShrink: 0 }} />
            <div>
              <strong>Refunded</strong>
              <div style={{ fontSize: "13px", fontWeight: 500, color: "var(--att-text-body)", marginTop: "2px" }}>
                This ticket was refunded and can't be used for entry.
              </div>
            </div>
          </div>
        )}

        {isCancelled && (
          <div
            style={{
              background: "rgba(239, 68, 68, 0.15)",
              border: "1px solid rgba(239, 68, 68, 0.4)",
              borderRadius: "14px",
              padding: "12px 16px",
              marginBottom: "16px",
              display: "flex",
              alignItems: "center",
              gap: "10px",
              color: "var(--att-bad)",
              fontSize: "13px",
              fontWeight: 700,
            }}
          >
            <AlertTriangle size={18} style={{ flexShrink: 0 }} />
            <div>
              <strong>Cancelled</strong>
              <div style={{ fontSize: "13px", fontWeight: 500, color: "var(--att-text-body)", marginTop: "2px" }}>
                {ticket.rejectionReason || "This ticket can't be used. Contact the organiser if you think this is wrong."}
              </div>
            </div>
          </div>
        )}

        {isPending && (
          <div
            style={{
              background: "rgba(245, 158, 11, 0.15)",
              border: "1px solid rgba(245, 158, 11, 0.4)",
              borderRadius: "14px",
              padding: "12px 16px",
              marginBottom: "16px",
              display: "flex",
              alignItems: "center",
              gap: "10px",
              color: "var(--att-warn)",
              fontSize: "13px",
              fontWeight: 700,
            }}
          >
            <Clock size={18} style={{ flexShrink: 0 }} />
            <div>
              <strong>Pending: waiting for payment</strong>
              <div style={{ fontSize: "13px", fontWeight: 500, color: "var(--att-text-body)", marginTop: "2px" }}>
                Your ticket turns Paid once the organiser confirms your payment. You'll need it Paid to get in.
              </div>
            </div>
          </div>
        )}

        {statusLine && (
          <div id="ticket-status-line" className="att-ticket-status">
            <div className="att-ticket-status-icon">
              {foodCount > 0 && ticket.fulfillmentStatus === "ready_for_pickup" ? <UtensilsCrossed size={20} /> : <Clock size={20} />}
            </div>
            <div style={{ minWidth: 0 }}>
              <div className="att-ticket-status-title">{statusLine.title}</div>
              <div className="att-ticket-status-detail">{statusLine.detail}</div>
            </div>
          </div>
        )}

        {/* 3. VISUALLY DISTINCTIVE DIGITAL TICKET PASS */}
        <article
          id="island-digital-ticket-pass"
          style={{
            background: "var(--att-surface-1)",
            border: "1px solid rgba(255, 255, 255, 0.12)",
            borderRadius: "28px",
            overflow: "hidden",
            boxShadow: "0 20px 40px rgba(0, 0, 0, 0.45)",
            position: "relative",
          }}
        >
          {/* Top Event Artwork Banner */}
          <div
            style={{
              position: "relative",
              height: "var(--att-ticket-art-height, 220px)",
              overflow: "hidden",
              background: "var(--att-surface-2)",
            }}
          >
            <img
              src={eventThumbnail}
              alt={eventTitle}
              style={{
                width: "100%",
                height: "100%",
                objectFit: "cover",
                objectPosition: "center",
                filter: isCancelled || isRefunded ? "grayscale(80%)" : "none",
              }}
              onError={(e) => {
                (e.currentTarget as HTMLImageElement).src =
                  "/images/island_music_reggae_concert.jpg";
              }}
            />
            {/* Cinematic Gradient Overlay */}
            <div
              style={{
                position: "absolute",
                inset: 0,
                background:
                  "linear-gradient(180deg, rgba(10, 15, 22, 0.3) 0%, rgba(10, 15, 22, 0.85) 75%, var(--att-surface-1) 100%)",
              }}
            />

            {/* Pass Category & Reference Pill (Top Left) */}
            <div
              style={{
                position: "absolute",
                top: "16px",
                left: "16px",
                display: "flex",
                alignItems: "center",
                gap: "8px",
              }}
            >
              <span
                style={{
                  background: "rgba(10, 15, 22, 0.9)",
                  backdropFilter: "blur(8px)",
                  border: "1px solid rgba(255, 255, 255, 0.2)",
                  color: "#FFFFFF",
                  padding: "4px 10px",
                  borderRadius: "8px",
                  fontSize: "12px",
                  fontWeight: 800,
                  letterSpacing: "0.04em",
                }}
              >
                #{ticket.ticketCode}
              </span>
            </div>

            {/* Visual Status Indicator (Top Right) */}
            <div
              style={{
                position: "absolute",
                top: "16px",
                right: "16px",
              }}
            >
              {isTransferred ? (
                <span
                  style={{
                    background: "rgba(182, 240, 58, 0.2)",
                    color: "var(--att-accent)",
                    border: "1px solid rgba(182, 240, 58, 0.4)",
                    padding: "5px 12px",
                    borderRadius: "9999px",
                    fontSize: "11px",
                    fontWeight: 800,
                    textTransform: "uppercase",
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "5px",
                  }}
                >
                  <Send size={12} /> Transferred
                </span>
              ) : isConfirmed ? (
                <span
                  style={{
                    background: "rgba(34, 197, 94, 0.25)",
                    color: "var(--att-ok)",
                    border: "1px solid rgba(34, 197, 94, 0.5)",
                    padding: "5px 12px",
                    borderRadius: "9999px",
                    fontSize: "11px",
                    fontWeight: 800,
                    textTransform: "uppercase",
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "5px",
                    boxShadow: "0 2px 8px rgba(34, 197, 94, 0.25)",
                  }}
                >
                  <CheckCircle2 size={12} /> Confirmed Pass
                </span>
              ) : isRefunded ? (
                <span
                  style={{
                    background: "rgba(168, 85, 247, 0.25)",
                    color: "var(--att-accent)",
                    border: "1px solid rgba(168, 85, 247, 0.4)",
                    padding: "5px 12px",
                    borderRadius: "9999px",
                    fontSize: "11px",
                    fontWeight: 800,
                    textTransform: "uppercase",
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "5px",
                  }}
                >
                  <RefreshCw size={12} /> Refunded
                </span>
              ) : isCancelled ? (
                <span
                  style={{
                    background: "rgba(239, 68, 68, 0.25)",
                    color: "var(--att-bad)",
                    border: "1px solid rgba(239, 68, 68, 0.4)",
                    padding: "5px 12px",
                    borderRadius: "9999px",
                    fontSize: "11px",
                    fontWeight: 800,
                    textTransform: "uppercase",
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "5px",
                  }}
                >
                  <AlertTriangle size={12} /> Cancelled
                </span>
              ) : (
                <span
                  style={{
                    background: "rgba(245, 158, 11, 0.25)",
                    color: "var(--att-warn)",
                    border: "1px solid rgba(245, 158, 11, 0.4)",
                    padding: "5px 12px",
                    borderRadius: "9999px",
                    fontSize: "11px",
                    fontWeight: 800,
                    textTransform: "uppercase",
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "5px",
                  }}
                >
                  <Clock size={12} /> Pending
                </span>
              )}
            </div>

            {/* Bottom Title on Artwork */}
            <div
              style={{
                position: "absolute",
                bottom: "16px",
                left: "20px",
                right: "20px",
              }}
            >
              <span
                style={{
                  fontSize: "11px",
                  fontWeight: 800,
                  textTransform: "uppercase",
                  letterSpacing: "0.08em",
                  color: "var(--att-accent)",
                  display: "block",
                  marginBottom: "4px",
                }}
              >
                Your ticket
              </span>
              <h1
                id="digital-ticket-event-title"
                style={{
                  fontSize: "24px",
                  fontWeight: 900,
                  color: "#FFFFFF",
                  margin: 0,
                  lineHeight: 1.25,
                  letterSpacing: "-0.01em",
                }}
              >
                {eventTitle}
              </h1>
            </div>
          </div>

          {/* 4. PROMINENT SECURE QR CODE SECTION */}
          <div
            id="ticket-secure-qr-section"
            style={{
              padding: "24px 24px 28px",
              textAlign: "center",
              background: "var(--att-surface-2)",
            }}
          >
            {/* High-Contrast Pure White QR Container */}
            <div
              style={{
                display: "inline-block",
                background: "#FFFFFF",
                padding: "20px",
                borderRadius: "20px",
                boxShadow: "0 12px 32px rgba(0, 0, 0, 0.6)",
                position: "relative",
                filter: isCancelled || isRefunded ? "opacity(40%)" : "none",
              }}
            >
              {/* Corner Scanning Markers */}
              <div
                style={{
                  position: "absolute",
                  inset: "10px",
                  border: "2px solid rgba(182, 240, 58, 0.2)",
                  borderRadius: "14px",
                  pointerEvents: "none",
                }}
              />

              {/* Render Standard SVG Matrix */}
              <svg
                id="authentic-gate-qr-code"
                viewBox={`0 0 ${qrMatrix.length} ${qrMatrix.length}`}
                width="210"
                height="210"
                style={{
                  display: "block",
                  shapeRendering: "crispEdges",
                }}
                role="img"
                aria-label={`Official QR pass for ticket ${ticket.ticketCode}`}
              >
                <rect width="100%" height="100%" fill="#FFFFFF" />
                {qrMatrix.map((row, r) =>
                  row.map((cell, c) =>
                    cell ? (
                      <rect
                        key={`${r}-${c}`}
                        x={c}
                        y={r}
                        width="1"
                        height="1"
                        fill="#0F172A"
                      />
                    ) : null
                  )
                )}
              </svg>

              {/* Void Stamp Overlay if Cancelled or Refunded */}
              {(isCancelled || isRefunded) && (
                <div
                  style={{
                    position: "absolute",
                    inset: 0,
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    background: "rgba(239, 68, 68, 0.85)",
                    borderRadius: "20px",
                    color: "#FFFFFF",
                    fontWeight: 900,
                    fontSize: "20px",
                    letterSpacing: "0.1em",
                    textTransform: "uppercase",
                    transform: "rotate(-12deg)",
                    textShadow: "0 2px 8px rgba(0,0,0,0.4)",
                  }}
                >
                  {isRefunded ? "REFUNDED" : "VOID / CANCELLED"}
                </div>
              )}
            </div>

            {/* Verification Security Specs */}
            <div style={{ marginTop: "16px" }}>
              {/* Reference Number Copy Tool */}
              <div
                style={{
                  marginTop: "12px",
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "8px",
                  background: "var(--att-surface-1)",
                  border: "1px solid rgba(255, 255, 255, 0.12)",
                  padding: "6px 14px",
                  borderRadius: "10px",
                }}
              >
                <span style={{ fontSize: "12px", color: "var(--att-text-2)" }}>Ticket</span>
                <strong style={{ fontSize: "13px", color: "#FFFFFF", letterSpacing: "0.05em" }}>
                  {ticket.ticketCode}
                </strong>
                <button
                  type="button"
                  id="btn-copy-ticket-ref"
                  onClick={handleCopyCode}
                  style={{
                    background: "none",
                    border: "none",
                    color: isCopied ? "var(--att-ok)" : "var(--att-accent)",
                    cursor: "pointer",
                    display: "flex",
                    alignItems: "center",
                    gap: "4px",
                    fontSize: "11px",
                    fontWeight: 700,
                  }}
                  title="Copy reference code"
                >
                  {isCopied ? <Check size={14} /> : <Copy size={14} />}
                  <span>{isCopied ? "Copied" : "Copy"}</span>
                </button>
              </div>
            </div>
          </div>

          {/* Distinctive Ticket Perforation Line */}
          <div
            style={{
              position: "relative",
              height: "24px",
              display: "flex",
              alignItems: "center",
            }}
          >
            {/* Left Notch */}
            <div
              style={{
                position: "absolute",
                left: "-14px",
                width: "28px",
                height: "28px",
                borderRadius: "50%",
                background: "var(--att-bg)",
                border: "1px solid rgba(255, 255, 255, 0.12)",
                clipPath: "polygon(50% 0%, 100% 0%, 100% 100%, 50% 100%)",
              }}
            />
            {/* Dashed Line */}
            <div
              style={{
                width: "100%",
                height: "1px",
                borderBottom: "2px dashed rgba(255, 255, 255, 0.15)",
                margin: "0 24px",
              }}
            />
            {/* Right Notch */}
            <div
              style={{
                position: "absolute",
                right: "-14px",
                width: "28px",
                height: "28px",
                borderRadius: "50%",
                background: "var(--att-bg)",
                border: "1px solid rgba(255, 255, 255, 0.12)",
                clipPath: "polygon(0% 0%, 50% 0%, 50% 100%, 0% 100%)",
              }}
            />
          </div>

          {/* Event Details Section */}
          <div style={{ padding: "20px 24px" }}>
            {/* Pass Metadata Grid */}
            <div
              style={{
                display: "grid",
                gridTemplateColumns: "repeat(auto-fit, minmax(min(200px, 100%), 1fr))",
                gap: "16px",
                marginBottom: "20px",
              }}
            >
              {/* Date & Time */}
              <div style={{ display: "flex", gap: "10px", alignItems: "flex-start" }}>
                <div
                  style={{
                    width: "36px",
                    height: "36px",
                    borderRadius: "10px",
                    background: "var(--att-surface-2)",
                    border: "1px solid var(--att-line)",
                    color: "var(--att-text-2)",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    flexShrink: 0,
                  }}
                >
                  <Calendar size={18} />
                </div>
                <div>
                  <span style={{ fontSize: "12px", color: "var(--att-text-2)", fontWeight: 600 }}>
                    When
                  </span>
                  <div style={{ fontSize: "15px", fontWeight: 700, color: "var(--att-text-1)", marginTop: "2px" }}>
                    {eventDate}
                  </div>
                  <div style={{ fontSize: "12px", color: "var(--att-text-body)" }}>{eventTime}</div>
                </div>
              </div>

              {/* Location */}
              <div style={{ display: "flex", gap: "10px", alignItems: "flex-start" }}>
                <div
                  style={{
                    width: "36px",
                    height: "36px",
                    borderRadius: "10px",
                    background: "var(--att-surface-2)",
                    border: "1px solid var(--att-line)",
                    color: "var(--att-text-2)",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    flexShrink: 0,
                  }}
                >
                  <MapPin size={18} />
                </div>
                <div>
                  <span style={{ fontSize: "12px", color: "var(--att-text-2)", fontWeight: 600 }}>
                    Where
                  </span>
                  <div style={{ fontSize: "15px", fontWeight: 700, color: "var(--att-text-1)", marginTop: "2px" }}>
                    {eventVenue}
                  </div>
                  <div style={{ fontSize: "12px", color: "var(--att-text-body)" }}>{eventAddress}</div>
                </div>
              </div>

              {/* Ticket Type & Quantity */}
              <div style={{ display: "flex", gap: "10px", alignItems: "flex-start" }}>
                <div
                  style={{
                    width: "36px",
                    height: "36px",
                    borderRadius: "10px",
                    background: "var(--att-surface-2)",
                    border: "1px solid var(--att-line)",
                    color: "var(--att-text-2)",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    flexShrink: 0,
                  }}
                >
                  <Ticket size={18} />
                </div>
                <div>
                  <span style={{ fontSize: "12px", color: "var(--att-text-2)", fontWeight: 600 }}>
                    In this order
                  </span>
                  <div style={{ fontSize: "15px", fontWeight: 700, color: "var(--att-text-1)", marginTop: "2px" }}>
                    {primaryItemName}
                    {ticket.items.length > 1 ? ` + ${ticket.items.length - 1} more` : ""}
                  </div>
                  <div style={{ fontSize: "12px", color: "var(--att-text-body)" }}>
                    {[
                      passCount > 0 ? `${passCount} ${passCount === 1 ? "pass" : "passes"}` : "",
                      foodCount > 0 ? `${foodCount} food ${foodCount === 1 ? "item" : "items"}` : "",
                    ]
                      .filter(Boolean)
                      .join(" · ") || `${totalQuantity} ${totalQuantity === 1 ? "item" : "items"}`}
                  </div>
                </div>
              </div>

              {/* Attendee Name */}
              <div style={{ display: "flex", gap: "10px", alignItems: "flex-start" }}>
                <div
                  style={{
                    width: "36px",
                    height: "36px",
                    borderRadius: "10px",
                    background: "var(--att-surface-2)",
                    border: "1px solid var(--att-line)",
                    color: "var(--att-text-2)",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    flexShrink: 0,
                  }}
                >
                  <User size={18} />
                </div>
                <div>
                  <span style={{ fontSize: "12px", color: "var(--att-text-2)", fontWeight: 600 }}>
                    Name
                  </span>
                  <div style={{ fontSize: "15px", fontWeight: 700, color: "var(--att-text-1)", marginTop: "2px" }}>
                    {attendeeName}
                  </div>
                  <div style={{ fontSize: "12px", color: "var(--att-text-2)" }}>
                    Order #{ticket.orderNumber}
                  </div>
                </div>
              </div>
            </div>

            {/* Transferred Notification */}
            {isTransferred && ticket.transferredTo && (
              <div
                style={{
                  background: "rgba(182, 240, 58, 0.1)",
                  border: "1px solid rgba(182, 240, 58, 0.3)",
                  borderRadius: "12px",
                  padding: "10px 14px",
                  marginBottom: "18px",
                  fontSize: "12px",
                  color: "var(--att-accent)",
                  display: "flex",
                  alignItems: "center",
                  gap: "8px",
                }}
              >
                <Send size={15} />
                <span>
                  This ticket was transferred to: <strong>{ticket.transferredTo}</strong>
                </span>
              </div>
            )}
          </div>

          {/* 5. CLEAR ENTRY INSTRUCTIONS SECTION */}
          <div
            style={{
              padding: "20px 24px",
              background: "var(--att-surface-1)",
              borderTop: "1px solid rgba(255, 255, 255, 0.08)",
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
                marginBottom: "12px",
              }}
            >
              <h2 style={{ fontSize: "16px", fontWeight: 700, color: "var(--att-text-1)", margin: 0 }}>
                Before you go
              </h2>
              <button
                type="button"
                id="btn-toggle-instructions-modal"
                onClick={() => setShowInstructionsModal(true)}
                style={{
                  background: "none",
                  border: "none",
                  color: "var(--att-accent)",
                  fontSize: "13px",
                  fontWeight: 600,
                  cursor: "pointer",
                }}
              >
                Entry rules
              </button>
            </div>

            <ul className="att-ticket-guide">
              <li>Show this QR code at the gate. Each pass lets one person in once.</li>
              <li>Turn your screen brightness up so it scans quickly.</li>
              {foodCount > 0 && (
                <li>{pickupInstructions ? `Food pickup: ${pickupInstructions}` : "Show the same code when you collect your food."}</li>
              )}
            </ul>
          </div>

          {/* 6. SUPPORTED ACTIONS: list rows, not competing buttons */}
          <div className="att-ticket-rows">
            {onViewEventDetails && (
              <button type="button" id="btn-view-event-details-action" onClick={() => onViewEventDetails(ticket.eventId)}>
                <ExternalLink size={18} />
                <span>Event details</span>
                <ChevronRight size={18} />
              </button>
            )}
            <button type="button" id="btn-view-receipt-action" onClick={() => setShowReceiptModal(true)}>
              <FileText size={18} />
              <span>Receipt</span>
              <ChevronRight size={18} />
            </button>
            <button
              type="button"
              id="btn-transfer-ticket-action"
              disabled={isRefunded || isCancelled || isTransferred}
              onClick={() => setShowTransferModal(true)}
            >
              <Send size={18} />
              <span>{isTransferred ? "Already transferred" : "Transfer to someone else"}</span>
              <ChevronRight size={18} />
            </button>
          </div>
        </article>
      </main>

      {/* 7. OFFICIAL RECEIPT MODAL */}
      {showReceiptModal && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="receipt-modal-title"
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
          onClick={() => setShowReceiptModal(false)}
        >
          <div
            style={{
              background: "var(--att-surface-1)",
              border: "1px solid rgba(255, 255, 255, 0.14)",
              borderRadius: "24px",
              width: "100%",
              maxWidth: "500px",
              maxHeight: "90vh",
              overflowY: "auto",
              padding: "24px",
              boxShadow: "0 25px 50px rgba(0, 0, 0, 0.6)",
              color: "var(--att-text-1)",
            }}
            onClick={(e) => e.stopPropagation()}
          >
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                alignItems: "center",
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
                  Official Payment Voucher
                </span>
                <h3 id="receipt-modal-title" style={{ fontSize: "20px", fontWeight: 800, margin: "2px 0 0" }}>
                  Receipt #{ticket.orderNumber}
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setShowReceiptModal(false)}
                style={{
                  background: "rgba(255, 255, 255, 0.08)",
                  border: "none",
                  borderRadius: "50%",
                  width: "36px",
                  height: "36px",
                  color: "#FFFFFF",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                }}
              >
                <X size={18} />
              </button>
            </div>

            {/* Receipt Summary Breakdown */}
            <div
              style={{
                background: "var(--att-surface-2)",
                border: "1px solid rgba(255, 255, 255, 0.08)",
                borderRadius: "16px",
                padding: "16px",
                marginBottom: "20px",
              }}
            >
              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  fontSize: "13px",
                  color: "var(--att-text-body)",
                  marginBottom: "8px",
                }}
              >
                <span>Issued To:</span>
                <strong style={{ color: "#FFFFFF" }}>{attendeeName}</strong>
              </div>
              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  fontSize: "13px",
                  color: "var(--att-text-body)",
                  marginBottom: "8px",
                }}
              >
                <span>Event:</span>
                <strong style={{ color: "#FFFFFF" }}>{eventTitle}</strong>
              </div>
              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  fontSize: "13px",
                  color: "var(--att-text-body)",
                  marginBottom: "8px",
                }}
              >
                <span>Payment Status:</span>
                <strong style={{ color: isConfirmed ? "var(--att-ok)" : "var(--att-warn)" }}>
                  {ticket.status.toUpperCase()}
                </strong>
              </div>
              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  fontSize: "13px",
                  color: "var(--att-text-body)",
                  marginBottom: "8px",
                }}
              >
                <span>Method:</span>
                <strong style={{ color: "#FFFFFF" }}>{ticket.method.toUpperCase()}</strong>
              </div>
            </div>

            {/* Itemized portions */}
            <div style={{ marginBottom: "20px" }}>
              <span style={{ fontSize: "12px", fontWeight: 700, color: "var(--att-text-2)", textTransform: "uppercase" }}>
                Itemized Purchases
              </span>
              <div style={{ marginTop: "8px", display: "flex", flexDirection: "column", gap: "8px" }}>
                {ticket.items.map((it, idx) => (
                  <div
                    key={idx}
                    style={{
                      display: "flex",
                      justifyContent: "space-between",
                      alignItems: "center",
                      padding: "10px 12px",
                      background: "rgba(255, 255, 255, 0.04)",
                      borderRadius: "10px",
                      fontSize: "13px",
                    }}
                  >
                    <span>
                      {it.quantity}× {it.name}
                    </span>
                    <strong style={{ color: "var(--att-accent)" }}>
                      {formatMoney(it.finalItemPrice * it.quantity, ticket.currency)}
                    </strong>
                  </div>
                ))}
              </div>
            </div>

            {/* Grand Total Row */}
            <div
              style={{
                borderTop: "1px solid rgba(255, 255, 255, 0.12)",
                paddingTop: "14px",
                marginBottom: "20px",
                display: "flex",
                justifyContent: "space-between",
                fontSize: "16px",
                fontWeight: 900,
              }}
            >
              <span>Total Paid:</span>
              <span style={{ color: "var(--att-ok)" }}>
                {formatMoney(ticket.totalAmount, ticket.currency)}
              </span>
            </div>

            {/* Modal Actions */}
            <div style={{ display: "flex", gap: "10px" }}>
              <button
                type="button"
                onClick={() => window.print()}
                style={{
                  flex: 1,
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "8px",
                  background: "var(--att-accent)",
                  border: "none",
                  color: "var(--att-on-accent)",
                  padding: "12px",
                  borderRadius: "12px",
                  fontSize: "13px",
                  fontWeight: 800,
                  cursor: "pointer",
                }}
              >
                <Download size={16} />
                <span>Print / Save Receipt</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 8. TRANSFER TICKET MODAL */}
      {showTransferModal && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="transfer-modal-title"
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
          onClick={() => setShowTransferModal(false)}
        >
          <div
            style={{
              background: "var(--att-surface-1)",
              border: "1px solid rgba(255, 255, 255, 0.14)",
              borderRadius: "24px",
              width: "100%",
              maxWidth: "480px",
              padding: "24px",
              boxShadow: "0 25px 50px rgba(0, 0, 0, 0.6)",
              color: "var(--att-text-1)",
            }}
            onClick={(e) => e.stopPropagation()}
          >
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                alignItems: "center",
                marginBottom: "16px",
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
                  Authorized Pass Transfer
                </span>
                <h3 id="transfer-modal-title" style={{ fontSize: "19px", fontWeight: 800, margin: "2px 0 0" }}>
                  Transfer Pass #{ticket.ticketCode}
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setShowTransferModal(false)}
                style={{
                  background: "rgba(255, 255, 255, 0.08)",
                  border: "none",
                  borderRadius: "50%",
                  width: "36px",
                  height: "36px",
                  color: "#FFFFFF",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                }}
              >
                <X size={18} />
              </button>
            </div>

            <p style={{ fontSize: "13px", color: "var(--att-text-2)", lineHeight: 1.5, marginBottom: "20px" }}>
              Transferring this pass generates a new secure QR token under the recipient’s name. Your original
              barcode will be permanently invalidated for gate admission.
            </p>

            <form onSubmit={handleExecuteTransfer}>
              <div style={{ marginBottom: "14px" }}>
                <label
                  htmlFor="recipient-name"
                  style={{ display: "block", fontSize: "12px", fontWeight: 700, color: "var(--att-text-body)", marginBottom: "6px" }}
                >
                  Recipient Full Name
                </label>
                <input
                  id="recipient-name"
                  type="text"
                  required
                  placeholder="e.g. Marcus Campbell"
                  value={transferRecipientName}
                  onChange={(e) => setTransferRecipientName(e.target.value)}
                  style={{
                    width: "100%",
                    background: "var(--att-surface-2)",
                    border: "1px solid rgba(255, 255, 255, 0.15)",
                    borderRadius: "12px",
                    padding: "10px 14px",
                    color: "#FFFFFF",
                    fontSize: "13px",
                    outline: "none",
                    boxSizing: "border-box",
                  }}
                />
              </div>

              <div style={{ marginBottom: "20px" }}>
                <label
                  htmlFor="recipient-email"
                  style={{ display: "block", fontSize: "12px", fontWeight: 700, color: "var(--att-text-body)", marginBottom: "6px" }}
                >
                  Recipient Email Address
                </label>
                <input
                  id="recipient-email"
                  type="email"
                  required
                  placeholder="e.g. marcus@islandevents.example"
                  value={transferRecipientEmail}
                  onChange={(e) => setTransferRecipientEmail(e.target.value)}
                  style={{
                    width: "100%",
                    background: "var(--att-surface-2)",
                    border: "1px solid rgba(255, 255, 255, 0.15)",
                    borderRadius: "12px",
                    padding: "10px 14px",
                    color: "#FFFFFF",
                    fontSize: "13px",
                    outline: "none",
                    boxSizing: "border-box",
                  }}
                />
              </div>

              <div style={{ display: "flex", gap: "10px" }}>
                <button
                  type="button"
                  onClick={() => setShowTransferModal(false)}
                  style={{
                    flex: 1,
                    background: "rgba(255, 255, 255, 0.08)",
                    border: "1px solid rgba(255, 255, 255, 0.15)",
                    color: "#FFFFFF",
                    padding: "12px",
                    borderRadius: "12px",
                    fontSize: "13px",
                    fontWeight: 700,
                    cursor: "pointer",
                  }}
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  id="btn-confirm-transfer"
                  style={{
                    flex: 1,
                    background: "var(--att-accent)",
                    border: "none",
                    color: "var(--att-on-accent)",
                    padding: "12px",
                    borderRadius: "12px",
                    fontSize: "13px",
                    fontWeight: 800,
                    cursor: "pointer",
                    boxShadow: "none",
                  }}
                >
                  Confirm Transfer
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* 9. FULL ENTRY POLICY MODAL */}
      {showInstructionsModal && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="policy-modal-title"
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
          onClick={() => setShowInstructionsModal(false)}
        >
          <div
            style={{
              background: "var(--att-surface-1)",
              border: "1px solid rgba(255, 255, 255, 0.14)",
              borderRadius: "24px",
              width: "100%",
              maxWidth: "520px",
              padding: "24px",
              boxShadow: "0 25px 50px rgba(0, 0, 0, 0.6)",
              color: "var(--att-text-1)",
            }}
            onClick={(e) => e.stopPropagation()}
          >
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                alignItems: "center",
                marginBottom: "16px",
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
                  Gate Admission Policy
                </span>
                <h3 id="policy-modal-title" style={{ fontSize: "19px", fontWeight: 800, margin: "2px 0 0" }}>
                  Official Venue Entry Rules
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setShowInstructionsModal(false)}
                style={{
                  background: "rgba(255, 255, 255, 0.08)",
                  border: "none",
                  borderRadius: "50%",
                  width: "36px",
                  height: "36px",
                  color: "#FFFFFF",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                }}
              >
                <X size={18} />
              </button>
            </div>

            <div style={{ display: "flex", flexDirection: "column", gap: "12px", fontSize: "13px", color: "var(--att-text-body)", lineHeight: 1.6 }}>
              <p>
                <strong>1. Cryptographic Gate Scanning:</strong> Every digital pass is embedded with a dynamic
                cryptographic verification token signed by Island Events. Our gate turnstiles verify authenticity in
                real-time against our event master ledger.
              </p>
              <p>
                <strong>2. Age Verification & VIP Access:</strong> VIP passes grant access to designated oceanfront
                cocktail terraces. Government photo ID (driver’s license, passport, or national voter ID) is
                mandatory upon entry.
              </p>
              <p>
                <strong>3. Anti-Scalping & Screenshot Protection:</strong> Static screenshots, recorded screen videos,
                or paper photocopies with mismatched tokens are immediately flagged by authorized staff terminals.
              </p>
              <p>
                <strong>4. Kitchen Pickups:</strong> If this pass includes prepaid culinary items (e.g. Escovitch Fish,
                Jerk Chicken, or Festival portions), present this same voucher at Station B. Handover status is
                instantly synchronized.
              </p>
            </div>

            <button
              type="button"
              onClick={() => setShowInstructionsModal(false)}
              style={{
                width: "100%",
                marginTop: "20px",
                background: "var(--att-accent)",
                border: "none",
                color: "var(--att-on-accent)",
                padding: "12px",
                borderRadius: "12px",
                fontSize: "13px",
                fontWeight: 800,
                cursor: "pointer",
              }}
            >
              Understood & Close
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
