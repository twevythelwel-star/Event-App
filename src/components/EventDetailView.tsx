import React, { useState, useEffect } from "react";
import {
  ArrowLeft,
  Heart,
  Share2,
  Calendar,
  Clock,
  MapPin,
  CheckCircle2,
  ShieldCheck,
  Ticket,
  Utensils,
  Info,
  Phone,
  Mail,
  MessageCircle,
  ChevronRight,
  Plus,
  Minus,
  X,
  Copy,
  Check,
} from "lucide-react";

export type EventSectionTab = "tickets" | "food" | "details";

export interface TicketTier {
  id: string;
  name: string;
  description: string;
  priceUSD: number;
  priceJMD: number;
  availableStock: number;
  perks: string[];
  badge?: string;
}

export interface EventDetailViewProps {
  event: any;
  eventIndex: number;
  onBack: () => void;
  onNavigateToFood: (eventIndex: number) => void;
  onNavigateToTickets?: () => void;
  isLoggedIn: boolean;
  onOpenAuthModal: (mode?: "login" | "register") => void;
  formatMoney: (amount: number, currency?: any) => string;
  onOrderPlaced?: (orderData: any) => void;
}

export const EventDetailView: React.FC<EventDetailViewProps> = ({
  event,
  eventIndex,
  onBack,
  onNavigateToFood,
  onNavigateToTickets,
  isLoggedIn,
  onOpenAuthModal,
  formatMoney,
  onOrderPlaced,
}) => {
  // Section Navigation Tabs
  const [activeSection, setActiveSection] = useState<EventSectionTab>("tickets");

  // Favorite / Saved State (with localStorage persistence)
  const [isSaved, setIsSaved] = useState<boolean>(() => {
    try {
      const savedList = JSON.parse(localStorage.getItem("island_saved_events") || "[]");
      return savedList.includes(event?.id || String(eventIndex));
    } catch {
      return false;
    }
  });

  // Ticket Selection State
  const currency = event?.currency || "USD";
  const baseEntryPrice = currency === "JMD" ? 2250 : 15;

  // Authentic ticket tiers for this event (uses host-configured event.ticketTiers when present)
  const defaultTicketTiers: TicketTier[] = [
    {
      id: "general-admission",
      name: "General Admission Pass",
      description: "Includes event admission, live performance access, and instant digital QR pass on mobile.",
      priceUSD: 15,
      priceJMD: 2250,
      availableStock: 250,
      perks: ["Entry to main waterfront festival grounds", "Access to live DJ & reggae stage", "Digital QR pass for mobile"],
      badge: "POPULAR",
    },
    {
      id: "vip-oceanfront",
      name: "VIP Waterfront Lounge",
      description: "Priority express entry, shaded stage-side seating, and complimentary welcome island refreshment.",
      priceUSD: 45,
      priceJMD: 6800,
      availableStock: 60,
      perks: [
        "Express VIP check-in lane",
        "Reserved shaded oceanfront lounge seating",
        "1 Complimentary artisanal island rum punch or tropical juice voucher",
        "Dedicated VIP bar & food server",
      ],
      badge: "VIP EXPERIENCE",
    },
    {
      id: "group-pass",
      name: "Family & Crew 4-Pack",
      description: "Discounted group package providing digital entry passes for up to four attendees.",
      priceUSD: 50,
      priceJMD: 7500,
      availableStock: 40,
      perks: ["Admission for 4 guests", "Reserved community table (subject to arrival)", "Individual QR passes for each guest"],
    },
  ];

  const ticketTiers: TicketTier[] =
    Array.isArray(event?.ticketTiers) && event.ticketTiers.length > 0
      ? event.ticketTiers.map((t: any, idx: number) => ({
          id: String(t.id || `tier-${idx}`),
          name: String(t.name || "Admission Pass"),
          description: String(t.description || "Includes event admission and instant digital QR pass."),
          priceUSD: Number(t.priceUSD ?? t.price_usd ?? 15),
          priceJMD: Number(t.priceJMD ?? t.price_jmd ?? 2250),
          availableStock: Number(t.quantityLimit ?? t.quantity_limit ?? t.availableStock ?? 100),
          perks: Array.isArray(t.perks) && t.perks.length > 0 ? t.perks : ["Event admission", "Digital QR pass for mobile"],
          badge: idx === 0 ? "POPULAR" : undefined,
        }))
      : defaultTicketTiers;

  const [quantities, setQuantities] = useState<Record<string, number>>(() => {
    const initial: Record<string, number> = {
      "general-admission": 1,
      "vip-oceanfront": 0,
      "group-pass": 0,
    };
    if (Array.isArray(event?.ticketTiers) && event.ticketTiers.length > 0) {
      const firstId = String(event.ticketTiers[0]?.id || "tier-0");
      initial[firstId] = 1;
    }
    return initial;
  });

  // Toast / Share Modal State
  const [toastMessage, setToastMessage] = useState<string | null>(null);
  const [showShareModal, setShowShareModal] = useState<boolean>(false);
  const [copiedLink, setCopiedLink] = useState<boolean>(false);
  const [lightboxImage, setLightboxImage] = useState<{ url: string; caption: string } | null>(null);
  const [isReserving, setIsReserving] = useState<boolean>(false);

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  }, []);

  const showToast = (msg: string) => {
    setToastMessage(msg);
    setTimeout(() => {
      setToastMessage(null);
    }, 3500);
  };

  // Toggle Favorite
  const toggleSave = () => {
    const nextSaved = !isSaved;
    setIsSaved(nextSaved);
    try {
      const eventId = event?.id || String(eventIndex);
      const savedList: string[] = JSON.parse(localStorage.getItem("island_saved_events") || "[]");
      let updated: string[];
      if (nextSaved) {
        updated = Array.from(new Set([...savedList, eventId]));
        showToast("Saved to your Island Favorites! ❤️");
      } else {
        updated = savedList.filter((id) => id !== eventId);
        showToast("Removed from saved events.");
      }
      localStorage.setItem("island_saved_events", JSON.stringify(updated));
    } catch {
      showToast(nextSaved ? "Event saved!" : "Event removed from saved.");
    }
  };

  // Share Event
  const shareUrl = window.location.href;
  const handleShare = async () => {
    if (navigator.share) {
      try {
        await navigator.share({
          title: event.title,
          text: event.tagline || `Join me at ${event.title}!`,
          url: shareUrl,
        });
        return;
      } catch (err: any) {
        if (err.name !== "AbortError") {
          setShowShareModal(true);
        }
      }
    } else {
      setShowShareModal(true);
    }
  };

  const copyToClipboard = () => {
    navigator.clipboard.writeText(shareUrl);
    setCopiedLink(true);
    showToast("Event link copied to clipboard! 📋");
    setTimeout(() => setCopiedLink(false), 2500);
  };

  // Subtotal calculation
  const totalAmount = ticketTiers.reduce((acc, tier) => {
    const qty = quantities[tier.id] || 0;
    const unitPrice = currency === "JMD" ? tier.priceJMD : tier.priceUSD;
    return acc + qty * unitPrice;
  }, 0);

  const totalTicketsCount = Object.values(quantities).reduce((a, b) => a + b, 0);

  // Authenticate & Handle Ticket Reservation
  const handleProceedToPurchase = () => {
    if (totalTicketsCount === 0) {
      showToast("Please select at least 1 ticket pass.");
      return;
    }

    if (!isLoggedIn) {
      showToast("Please sign in to confirm your personal ticket pass.");
      onOpenAuthModal("login");
      return;
    }

    setIsReserving(true);
    setTimeout(() => {
      setIsReserving(false);
      showToast(`🎉 Success! Reserved ${totalTicketsCount} pass(es) for ${event.title}!`);
      if (onOrderPlaced) {
        onOrderPlaced({
          eventId: event.id,
          eventTitle: event.title,
          ticketCount: totalTicketsCount,
          totalAmount,
          currency,
        });
      }
    }, 900);
  };

  // Authentic Gallery Media for this Event
  const galleryMedia: { url: string; caption: string; category: string }[] = [
    {
      url: event.flyerUrl || "/images/escovitch_fish.jpg",
      caption: "Culinary Highlights & Signature Dish",
      category: "Food",
    },
    {
      url: "/images/island_music_reggae_concert.jpg",
      caption: "Live Musical Performance Stage",
      category: "Concert",
    },
    {
      url: "/images/island_festival_sunset.jpg",
      caption: "Atmosphere & Sunset Waterfront Venue",
      category: "Venue",
    },
    {
      url: "/images/jerk_chicken.jpg",
      caption: "Authentic Pimento Wood Smoked Jerk Special",
      category: "Food",
    },
    {
      url: "/images/rum_punch.jpg",
      caption: "Complimentary Artisanal Island Refreshments",
      category: "Beverage",
    },
  ];

  return (
    <div
      style={{
        backgroundColor: "var(--att-bg)",
        minHeight: "100vh",
        color: "#FFFFFF",
        fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
        paddingBottom: "120px",
      }}
      id="island-event-detail-screen"
    >
      {/* Toast Notification */}
      {toastMessage && (
        <div
          style={{
            position: "fixed",
            top: "24px",
            left: "50%",
            transform: "translateX(-50%)",
            backgroundColor: "var(--att-surface-2)",
            color: "#FFFFFF",
            padding: "12px 24px",
            borderRadius: "9999px",
            boxShadow: "0 10px 30px rgba(0, 0, 0, 0.4)",
            border: "1px solid rgba(255, 255, 255, 0.15)",
            zIndex: 9999,
            fontSize: "14px",
            fontWeight: 600,
            display: "flex",
            alignItems: "center",
            gap: "8px",
            animation: "fadeIn 0.2s ease-out",
          }}
          role="status"
          aria-live="polite"
        >
          <span>{toastMessage}</span>
        </div>
      )}

      {/* 1. Cinematic Hero Section */}
      <section
        style={{
          position: "relative",
          width: "100%",
          height: "440px",
          maxHeight: "55vh",
          overflow: "hidden",
        }}
        aria-label="Event hero image banner"
      >
        {/* Background Event Photo */}
        <img
          src={event.flyerUrl || "/images/island_festival_sunset.jpg"}
          alt={event.title}
          style={{
            width: "100%",
            height: "100%",
            objectFit: "cover",
            objectPosition: "center 30%",
            filter: "brightness(0.9)",
          }}
        />

        {/* Gradient Overlay for Legibility */}
        <div
          style={{
            position: "absolute",
            inset: 0,
            background:
              "linear-gradient(180deg, rgba(10, 15, 22, 0.6) 0%, rgba(10, 15, 22, 0.15) 45%, rgba(10, 15, 22, 0.95) 90%, var(--att-bg) 100%)",
          }}
        />

        {/* Top Floating Action Bar */}
        <div
          style={{
            position: "absolute",
            top: "24px",
            left: "0",
            right: "0",
            maxWidth: "1200px",
            margin: "0 auto",
            padding: "0 24px",
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
            zIndex: 10,
          }}
        >
          {/* Back Navigation Button */}
          <button
            type="button"
            onClick={onBack}
            id="btn-detail-back"
            aria-label="Back to discovery homepage"
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "8px",
              backgroundColor: "rgba(10, 15, 22, 0.75)",
              backdropFilter: "blur(12px)",
              color: "#FFFFFF",
              border: "1px solid rgba(255, 255, 255, 0.2)",
              borderRadius: "9999px",
              padding: "10px 18px",
              fontSize: "14px",
              fontWeight: 700,
              cursor: "pointer",
              transition: "all 0.2s ease",
            }}
          >
            <ArrowLeft size={18} />
            <span>Back to Discovery</span>
          </button>

          {/* Right Action Icons (Favorite & Share) */}
          <div style={{ display: "flex", alignItems: "center", gap: "12px" }}>
            {/* Save / Favorite Control */}
            <button
              type="button"
              onClick={toggleSave}
              id="btn-detail-save-favorite"
              aria-label={isSaved ? "Remove from favorites" : "Save to favorites"}
              style={{
                width: "44px",
                height: "44px",
                borderRadius: "50%",
                backgroundColor: isSaved ? "rgba(182, 240, 58, 0.3)" : "rgba(10, 15, 22, 0.75)",
                backdropFilter: "blur(12px)",
                border: isSaved ? "2px solid var(--att-text-2)" : "1px solid rgba(255, 255, 255, 0.2)",
                color: isSaved ? "var(--att-text-2)" : "#FFFFFF",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                cursor: "pointer",
                transition: "all 0.2s ease",
              }}
            >
              <Heart size={20} fill={isSaved ? "#B6F03A" : "none"} />
            </button>

            {/* Share Control */}
            <button
              type="button"
              onClick={handleShare}
              id="btn-detail-share-event"
              aria-label="Share this event"
              style={{
                width: "44px",
                height: "44px",
                borderRadius: "50%",
                backgroundColor: "rgba(10, 15, 22, 0.75)",
                backdropFilter: "blur(12px)",
                border: "1px solid rgba(255, 255, 255, 0.2)",
                color: "#FFFFFF",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                cursor: "pointer",
                transition: "all 0.2s ease",
              }}
            >
              <Share2 size={20} />
            </button>
          </div>
        </div>

        {/* Hero Badges */}
        <div
          style={{
            position: "absolute",
            bottom: "24px",
            left: "0",
            right: "0",
            maxWidth: "1200px",
            margin: "0 auto",
            padding: "0 24px",
            display: "flex",
            alignItems: "center",
            gap: "10px",
            flexWrap: "wrap",
            zIndex: 5,
          }}
        >
          <span
            style={{
              backgroundColor: "rgba(182, 240, 58, 0.9)",
              color: "#FFFFFF",
              fontSize: "12px",
              fontWeight: 800,
              textTransform: "uppercase",
              letterSpacing: "0.06em",
              padding: "6px 14px",
              borderRadius: "9999px",
            }}
          >
            🌴 {event.locationCategory || "Caribbean Event"}
          </span>

          {event.isOrderingOpen && (
            <span
              style={{
                backgroundColor: "rgba(34, 197, 94, 0.9)",
                color: "#FFFFFF",
                fontSize: "12px",
                fontWeight: 700,
                padding: "6px 14px",
                borderRadius: "9999px",
                display: "inline-flex",
                alignItems: "center",
                gap: "5px",
              }}
            >
              <CheckCircle2 size={14} />
              <span>Advance Dining Open</span>
            </span>
          )}

          <span
            style={{
              backgroundColor: "rgba(10, 15, 22, 0.8)",
              backdropFilter: "blur(6px)",
              color: "var(--att-text-1)",
              fontSize: "12px",
              fontWeight: 600,
              padding: "6px 14px",
              borderRadius: "9999px",
              border: "1px solid rgba(255, 255, 255, 0.15)",
            }}
          >
            Verified Host
          </span>
        </div>
      </section>

      {/* Main Content Area: Responsive Grid (Desktop 2-Column: Details on Left, Ticket Panel on Right) */}
      <main
        style={{
          maxWidth: "1200px",
          margin: "0 auto",
          padding: "0 16px",
        }}
      >
        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(min(340px, 100%), 1fr))",
            gap: "36px",
            alignItems: "start",
          }}
        >
          {/* LEFT COLUMN: Event Metadata, Description, Media Gallery & Organizer */}
          <div style={{ display: "flex", flexDirection: "column", gap: "32px" }}>
            {/* Title & Metadata Header */}
            <div>
              <h1
                id="event-detail-title"
                style={{
                  fontSize: "32px",
                  fontWeight: 900,
                  color: "#FFFFFF",
                  lineHeight: "1.2",
                  letterSpacing: "-0.02em",
                  marginBottom: "14px",
                }}
              >
                {event.title}
              </h1>

              {event.tagline && (
                <p
                  style={{
                    fontSize: "16px",
                    color: "var(--att-text-2)",
                    lineHeight: "1.5",
                    marginBottom: "20px",
                    fontWeight: 500,
                  }}
                >
                  {event.tagline}
                </p>
              )}

              {/* Differentiated Metadata Pills */}
              <div
                style={{
                  display: "grid",
                  gridTemplateColumns: "repeat(auto-fit, minmax(min(220px, 100%), 1fr))",
                  gap: "14px",
                  padding: "18px",
                  backgroundColor: "var(--att-surface-1)",
                  borderRadius: "18px",
                  border: "1px solid rgba(255, 255, 255, 0.08)",
                }}
              >
                {/* Date & Time */}
                <div style={{ display: "flex", alignItems: "flex-start", gap: "12px" }}>
                  <div
                    style={{
                      width: "40px",
                      height: "40px",
                      borderRadius: "10px",
                      backgroundColor: "rgba(182, 240, 58, 0.15)",
                      color: "var(--att-accent)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      flexShrink: 0,
                    }}
                  >
                    <Calendar size={20} />
                  </div>
                  <div>
                    <div style={{ fontSize: "11px", textTransform: "uppercase", color: "var(--att-text-2)", fontWeight: 700 }}>
                      Date & Time
                    </div>
                    <div style={{ fontSize: "14px", fontWeight: 700, color: "#FFFFFF" }}>{event.dateStr}</div>
                    <div style={{ fontSize: "12px", color: "var(--att-text-body)" }}>
                      {event.timeStr} {event.timezone ? `(${event.timezone})` : ""}
                    </div>
                  </div>
                </div>

                {/* Venue & Location */}
                <div style={{ display: "flex", alignItems: "flex-start", gap: "12px" }}>
                  <div
                    style={{
                      width: "40px",
                      height: "40px",
                      borderRadius: "10px",
                      backgroundColor: "rgba(182, 240, 58, 0.15)",
                      color: "var(--att-text-2)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      flexShrink: 0,
                    }}
                  >
                    <MapPin size={20} />
                  </div>
                  <div>
                    <div style={{ fontSize: "11px", textTransform: "uppercase", color: "var(--att-text-2)", fontWeight: 700 }}>
                      Location & Venue
                    </div>
                    <div style={{ fontSize: "14px", fontWeight: 700, color: "#FFFFFF" }}>{event.venueName}</div>
                    <div style={{ fontSize: "12px", color: "var(--att-text-body)" }}>{event.venueAddress}</div>
                  </div>
                </div>
              </div>
            </div>

            {/* Section Switcher Tabs (Tickets · Food · Details) */}
            <div
              style={{
                backgroundColor: "var(--att-surface-1)",
                padding: "6px",
                borderRadius: "16px",
                display: "flex",
                border: "1px solid rgba(255, 255, 255, 0.08)",
                gap: "6px",
              }}
              role="tablist"
              aria-label="Event Sections"
            >
              <button
                type="button"
                role="tab"
                aria-selected={activeSection === "tickets"}
                id="tab-section-tickets"
                onClick={() => setActiveSection("tickets")}
                style={{
                  flex: 1,
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "8px",
                  padding: "12px 16px",
                  borderRadius: "12px",
                  border: "none",
                  backgroundColor: activeSection === "tickets" ? "var(--att-accent)" : "transparent",
                  color: activeSection === "tickets" ? "var(--att-on-accent)" : "var(--att-text-2)",
                  fontWeight: 700,
                  fontSize: "14px",
                  cursor: "pointer",
                  transition: "all 0.2s ease",
                }}
              >
                <Ticket size={18} />
                <span>Tickets</span>
              </button>

              <button
                type="button"
                role="tab"
                aria-selected={activeSection === "food"}
                id="tab-section-food"
                onClick={() => setActiveSection("food")}
                style={{
                  flex: 1,
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "8px",
                  padding: "12px 16px",
                  borderRadius: "12px",
                  border: "none",
                  backgroundColor: activeSection === "food" ? "var(--att-accent)" : "transparent",
                  color: activeSection === "food" ? "var(--att-on-accent)" : "var(--att-text-2)",
                  fontWeight: 700,
                  fontSize: "14px",
                  cursor: "pointer",
                  transition: "all 0.2s ease",
                }}
              >
                <Utensils size={18} />
                <span>Food</span>
                {event.menu && event.menu.length > 0 && (
                  <span
                    style={{
                      backgroundColor: "rgba(255, 255, 255, 0.2)",
                      padding: "2px 6px",
                      borderRadius: "9999px",
                      fontSize: "10px",
                    }}
                  >
                    {event.menu.length}
                  </span>
                )}
              </button>

              <button
                type="button"
                role="tab"
                aria-selected={activeSection === "details"}
                id="tab-section-details"
                onClick={() => setActiveSection("details")}
                style={{
                  flex: 1,
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "8px",
                  padding: "12px 16px",
                  borderRadius: "12px",
                  border: "none",
                  backgroundColor: activeSection === "details" ? "var(--att-accent)" : "transparent",
                  color: activeSection === "details" ? "var(--att-on-accent)" : "var(--att-text-2)",
                  fontWeight: 700,
                  fontSize: "14px",
                  cursor: "pointer",
                  transition: "all 0.2s ease",
                }}
              >
                <Info size={18} />
                <span>Details</span>
              </button>
            </div>

            {/* TAB CONTENT: THE ACTIVE SECTION SWAPS THE REAL CONTENT */}

            {/* TAB 1: TICKETS */}
            {activeSection === "tickets" && (
              <section id="section-content-tickets" aria-label="Available Ticket Tiers">
                <div style={{ marginBottom: "16px" }}>
                  <h2 style={{ fontSize: "20px", fontWeight: 800, color: "#FFFFFF", marginBottom: "6px" }}>
                    Select Your Entry Passes
                  </h2>
                  <p style={{ fontSize: "14px", color: "var(--att-text-2)" }}>
                    Genuine ticket passes issued directly with unique security QR passes.
                  </p>
                </div>

                <div style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
                  {ticketTiers.map((tier) => {
                    const qty = quantities[tier.id] || 0;
                    const price = currency === "JMD" ? tier.priceJMD : tier.priceUSD;

                    return (
                      <div
                        key={tier.id}
                        id={`card-ticket-tier-${tier.id}`}
                        style={{
                          backgroundColor: qty > 0 ? "var(--att-surface-1)" : "var(--att-surface-1)",
                          border: qty > 0 ? "2px solid var(--att-accent)" : "1px solid rgba(255, 255, 255, 0.08)",
                          borderRadius: "18px",
                          padding: "20px",
                          transition: "all 0.2s ease",
                        }}
                      >
                        <div
                          style={{
                            display: "flex",
                            justifyContent: "space-between",
                            alignItems: "flex-start",
                            marginBottom: "10px",
                          }}
                        >
                          <div>
                            <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                              <h3 style={{ fontSize: "17px", fontWeight: 800, color: "#FFFFFF", margin: 0 }}>
                                {tier.name}
                              </h3>
                              {tier.badge && (
                                <span
                                  style={{
                                    backgroundColor: "rgba(182, 240, 58, 0.2)",
                                    color: "var(--att-text-2)",
                                    fontSize: "10px",
                                    fontWeight: 800,
                                    padding: "3px 8px",
                                    borderRadius: "9999px",
                                    border: "1px solid rgba(182, 240, 58, 0.3)",
                                  }}
                                >
                                  {tier.badge}
                                </span>
                              )}
                            </div>
                            <p style={{ fontSize: "13px", color: "var(--att-text-2)", marginTop: "4px", marginBottom: "12px" }}>
                              {tier.description}
                            </p>
                          </div>

                          <div style={{ textAlign: "right", flexShrink: 0 }}>
                            <div style={{ fontSize: "20px", fontWeight: 900, color: "#FFFFFF" }}>
                              {formatMoney(price, currency)}
                            </div>
                            <div style={{ fontSize: "11px", color: "var(--att-text-2)" }}>per pass</div>
                          </div>
                        </div>

                        {/* Perks Bullet List */}
                        <div style={{ display: "flex", flexDirection: "column", gap: "6px", marginBottom: "16px" }}>
                          {tier.perks.map((perk, pIdx) => (
                            <div
                              key={pIdx}
                              style={{ display: "flex", alignItems: "center", gap: "8px", fontSize: "12px", color: "var(--att-text-body)" }}
                            >
                              <CheckCircle2 size={14} style={{ color: "var(--att-ok)", flexShrink: 0 }} />
                              <span>{perk}</span>
                            </div>
                          ))}
                        </div>

                        {/* Quantity Counter Control */}
                        <div
                          style={{
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "space-between",
                            paddingTop: "14px",
                            borderTop: "1px solid rgba(255, 255, 255, 0.08)",
                          }}
                        >
                          <span style={{ fontSize: "12px", color: "var(--att-text-2)" }}>
                            Stock: <strong style={{ color: "var(--att-text-1)" }}>{tier.availableStock} passes remaining</strong>
                          </span>

                          <div
                            style={{
                              display: "flex",
                              alignItems: "center",
                              gap: "12px",
                              backgroundColor: "var(--att-surface-2)",
                              padding: "4px 8px",
                              borderRadius: "10px",
                              border: "1px solid rgba(255, 255, 255, 0.1)",
                            }}
                          >
                            <button
                              type="button"
                              onClick={() => {
                                setQuantities((prev) => ({
                                  ...prev,
                                  [tier.id]: Math.max(0, (prev[tier.id] || 0) - 1),
                                }));
                              }}
                              disabled={qty <= 0}
                              aria-label={`Decrease ${tier.name} quantity`}
                              style={{
                                width: "28px",
                                height: "28px",
                                borderRadius: "6px",
                                border: "none",
                                backgroundColor: qty > 0 ? "rgba(255, 255, 255, 0.1)" : "transparent",
                                color: qty > 0 ? "#FFFFFF" : "var(--att-text-3)",
                                cursor: qty > 0 ? "pointer" : "default",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                              }}
                            >
                              <Minus size={14} />
                            </button>

                            <span
                              style={{
                                fontSize: "15px",
                                fontWeight: 800,
                                color: "#FFFFFF",
                                minWidth: "20px",
                                textAlign: "center",
                              }}
                            >
                              {qty}
                            </span>

                            <button
                              type="button"
                              onClick={() => {
                                setQuantities((prev) => ({
                                  ...prev,
                                  [tier.id]: Math.min(tier.availableStock, (prev[tier.id] || 0) + 1),
                                }));
                              }}
                              disabled={qty >= tier.availableStock}
                              aria-label={`Increase ${tier.name} quantity`}
                              style={{
                                width: "28px",
                                height: "28px",
                                borderRadius: "6px",
                                border: "none",
                                backgroundColor: "rgba(255, 255, 255, 0.15)",
                                color: "#FFFFFF",
                                cursor: "pointer",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                              }}
                            >
                              <Plus size={14} />
                            </button>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </section>
            )}

            {/* TAB 2: FOOD & DINING */}
            {activeSection === "food" && (
              <section id="section-content-food" aria-label="Caribbean Food & Dining Offerings">
                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                    marginBottom: "16px",
                    flexWrap: "wrap",
                    gap: "10px",
                  }}
                >
                  <div>
                    <h2 style={{ fontSize: "20px", fontWeight: 800, color: "#FFFFFF", margin: "0 0 4px" }}>
                      Featured Event Dining
                    </h2>
                    <p style={{ fontSize: "13px", color: "var(--att-text-2)", margin: 0 }}>
                      Authentic meals prepared fresh on-site. Pre-order to guarantee your portions.
                    </p>
                  </div>

                  <button
                    type="button"
                    onClick={() => onNavigateToFood(eventIndex)}
                    id="btn-goto-food-ordering"
                    style={{
                      background: "var(--att-ok)",
                      color: "#FFFFFF",
                      border: "none",
                      borderRadius: "10px",
                      padding: "8px 16px",
                      fontSize: "13px",
                      fontWeight: 700,
                      cursor: "pointer",
                      display: "flex",
                      alignItems: "center",
                      gap: "6px",
                    }}
                  >
                    <span>Open Full Kitchen Menu</span>
                    <ChevronRight size={16} />
                  </button>
                </div>

                {event.menu && event.menu.length > 0 ? (
                  <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(min(260px, 100%), 1fr))", gap: "14px" }}>
                    {event.menu.map((item: any, iIdx: number) => (
                      <div
                        key={item.id || iIdx}
                        style={{
                          backgroundColor: "var(--att-surface-1)",
                          borderRadius: "16px",
                          border: "1px solid rgba(255, 255, 255, 0.08)",
                          overflow: "hidden",
                          display: "flex",
                          flexDirection: "column",
                        }}
                      >
                        <div style={{ height: "130px", position: "relative" }}>
                          <img
                            src={item.imageUrl || "/images/escovitch_fish.jpg"}
                            alt={item.name}
                            style={{ width: "100%", height: "100%", objectFit: "cover" }}
                          />
                          <div
                            style={{
                              position: "absolute",
                              top: "8px",
                              right: "8px",
                              backgroundColor: "rgba(10, 15, 22, 0.8)",
                              backdropFilter: "blur(6px)",
                              padding: "3px 8px",
                              borderRadius: "9999px",
                              fontSize: "11px",
                              fontWeight: 700,
                              color: "var(--att-ok)",
                            }}
                          >
                            {item.availableStock} portions
                          </div>
                        </div>

                        <div style={{ padding: "14px", display: "flex", flexDirection: "column", flex: 1, justifyContent: "space-between" }}>
                          <div>
                            <div style={{ fontSize: "11px", color: "var(--att-accent)", fontWeight: 700, textTransform: "uppercase" }}>
                              {item.category || "Main Entrée"}
                            </div>
                            <h4 style={{ fontSize: "15px", fontWeight: 800, color: "#FFFFFF", margin: "4px 0 6px" }}>
                              {item.name}
                            </h4>
                            <p style={{ fontSize: "12px", color: "var(--att-text-2)", margin: 0, lineHeight: "1.4" }}>
                              {item.description || "Authentic freshly prepared island specialty."}
                            </p>
                          </div>

                          <div
                            style={{
                              display: "flex",
                              justifyContent: "space-between",
                              alignItems: "center",
                              marginTop: "12px",
                              paddingTop: "10px",
                              borderTop: "1px solid rgba(255, 255, 255, 0.06)",
                            }}
                          >
                            <span style={{ fontSize: "16px", fontWeight: 800, color: "#FFFFFF" }}>
                              {formatMoney(currency === "JMD" ? item.priceJMD : item.priceUSD, currency)}
                            </span>

                            <button
                              type="button"
                              onClick={() => onNavigateToFood(eventIndex)}
                              style={{
                                backgroundColor: "rgba(182, 240, 58, 0.15)",
                                border: "1px solid rgba(182, 240, 58, 0.3)",
                                color: "var(--att-accent)",
                                borderRadius: "8px",
                                padding: "6px 12px",
                                fontSize: "12px",
                                fontWeight: 700,
                                cursor: "pointer",
                              }}
                            >
                              Add to Order
                            </button>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div
                    style={{
                      padding: "32px",
                      backgroundColor: "var(--att-surface-1)",
                      borderRadius: "18px",
                      textAlign: "center",
                      border: "1px dashed rgba(255, 255, 255, 0.15)",
                    }}
                  >
                    <Utensils size={36} style={{ color: "var(--att-accent)", margin: "0 auto 12px" }} />
                    <div style={{ fontSize: "16px", fontWeight: 700, color: "#FFFFFF" }}>Concert & Live Event Only</div>
                    <p style={{ fontSize: "13px", color: "var(--att-text-2)", maxWidth: "420px", margin: "8px auto 0" }}>
                      This performance offers on-site bar service and refreshments without pre-order packages.
                    </p>
                  </div>
                )}
              </section>
            )}

            {/* TAB 3: DETAILS & EVENT GUIDE */}
            {activeSection === "details" && (
              <section id="section-content-details" aria-label="Event Guidelines & Logistics">
                <div style={{ display: "flex", flexDirection: "column", gap: "20px" }}>
                  {/* Detailed Description */}
                  <div
                    style={{
                      backgroundColor: "var(--att-surface-1)",
                      borderRadius: "18px",
                      padding: "24px",
                      border: "1px solid rgba(255, 255, 255, 0.08)",
                    }}
                  >
                    <h3 style={{ fontSize: "18px", fontWeight: 800, color: "#FFFFFF", marginBottom: "12px" }}>
                      About This Experience
                    </h3>
                    <p style={{ fontSize: "14px", color: "var(--att-text-body)", lineHeight: "1.7", margin: 0 }}>
                      {event.description}
                    </p>
                  </div>

                  {/* Pickup & Food Protocol */}
                  {event.pickupInstructions && (
                    <div
                      style={{
                        backgroundColor: "var(--att-surface-1)",
                        borderRadius: "18px",
                        padding: "24px",
                        border: "1px solid rgba(255, 255, 255, 0.08)",
                      }}
                    >
                      <h3 style={{ fontSize: "18px", fontWeight: 800, color: "#FFFFFF", marginBottom: "12px" }}>
                        Food Collection & Ticket Redemption Protocol
                      </h3>
                      <p style={{ fontSize: "14px", color: "var(--att-text-body)", lineHeight: "1.6", margin: "0 0 14px" }}>
                        {event.pickupInstructions}
                      </p>
                      <div
                        style={{
                          backgroundColor: "rgba(10, 15, 22, 0.6)",
                          padding: "14px",
                          borderRadius: "12px",
                          border: "1px solid rgba(255, 255, 255, 0.06)",
                          fontSize: "13px",
                          color: "var(--att-text-2)",
                          display: "flex",
                          flexDirection: "column",
                          gap: "6px",
                        }}
                      >
                        <div>&bull; Have your digital QR voucher ready on your mobile device.</div>
                        <div>&bull; Staff terminals scan instantly without paper ticket requirements.</div>
                        <div>&bull; Orders are prepared sequentially to guarantee fresh temperature.</div>
                      </div>
                    </div>
                  )}

                  {/* Advance Order Deadline Warning */}
                  {event.advanceOrderDeadline && (
                    <div
                      style={{
                        backgroundColor: "rgba(234, 179, 8, 0.1)",
                        border: "1px solid rgba(234, 179, 8, 0.3)",
                        borderRadius: "16px",
                        padding: "16px 20px",
                        display: "flex",
                        alignItems: "center",
                        gap: "12px",
                      }}
                    >
                      <Clock size={22} style={{ color: "var(--att-warn)", flexShrink: 0 }} />
                      <div>
                        <div style={{ fontSize: "13px", fontWeight: 800, color: "var(--att-warn)" }}>
                          Pre-Order Cutoff Deadline
                        </div>
                        <div style={{ fontSize: "12px", color: "var(--att-text-body)" }}>
                          Advance dining reservations close promptly on: <strong>{event.advanceOrderDeadline}</strong>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              </section>
            )}

            {/* 3. Authentic Media Section (Compact Horizontal Gallery) */}
            <section aria-label="Event photograph gallery">
              <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: "14px" }}>
                <h3 style={{ fontSize: "18px", fontWeight: 800, color: "#FFFFFF", margin: 0 }}>
                  Event & Atmosphere Gallery
                </h3>
                <span style={{ fontSize: "12px", color: "var(--att-text-2)" }}>5 verified event photos</span>
              </div>

              <div
                style={{
                  display: "flex",
                  gap: "14px",
                  overflowX: "auto",
                  paddingBottom: "10px",
                  scrollSnapType: "x mandatory",
                  WebkitOverflowScrolling: "touch",
                }}
              >
                {galleryMedia.map((media, mIdx) => (
                  <button
                    key={mIdx}
                    type="button"
                    onClick={() => setLightboxImage(media)}
                    style={{
                      minWidth: "180px",
                      maxWidth: "200px",
                      height: "130px",
                      borderRadius: "14px",
                      overflow: "hidden",
                      border: "1px solid rgba(255, 255, 255, 0.12)",
                      position: "relative",
                      cursor: "pointer",
                      padding: 0,
                      backgroundColor: "var(--att-surface-1)",
                      flexShrink: 0,
                      scrollSnapAlign: "start",
                    }}
                    id={`gallery-thumb-${mIdx}`}
                    aria-label={`View photo: ${media.caption}`}
                  >
                    <img
                      src={media.url}
                      alt={media.caption}
                      style={{ width: "100%", height: "100%", objectFit: "cover", display: "block" }}
                    />
                    <div
                      style={{
                        position: "absolute",
                        inset: 0,
                        background: "linear-gradient(180deg, rgba(0,0,0,0) 40%, rgba(10, 15, 22,0.85) 100%)",
                      }}
                    />
                    <div
                      style={{
                        position: "absolute",
                        bottom: "8px",
                        left: "10px",
                        right: "10px",
                        fontSize: "11px",
                        fontWeight: 700,
                        color: "#FFFFFF",
                        whiteSpace: "nowrap",
                        overflow: "hidden",
                        textOverflow: "ellipsis",
                        textAlign: "left",
                      }}
                    >
                      {media.caption}
                    </div>
                  </button>
                ))}
              </div>
            </section>

            {/* 4. Host & Organizer Information Card */}
            <section
              style={{
                backgroundColor: "var(--att-surface-1)",
                borderRadius: "20px",
                padding: "24px",
                border: "1px solid rgba(255, 255, 255, 0.08)",
              }}
              aria-label="Organizer information"
            >
              <div style={{ display: "flex", alignItems: "center", gap: "16px", marginBottom: "16px" }}>
                <div
                  style={{
                    width: "54px",
                    height: "54px",
                    borderRadius: "16px",
                    background: "var(--att-accent)",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    fontSize: "22px",
                    fontWeight: 900,
                    color: "var(--att-on-accent)",
                    flexShrink: 0,
                  }}
                >
                  {event.organizerName ? event.organizerName.charAt(0) : "🌴"}
                </div>
                <div>
                  <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                    <h3 style={{ fontSize: "17px", fontWeight: 800, color: "#FFFFFF", margin: 0 }}>
                      {event.organizerName || "Island Community Foundation"}
                    </h3>
                    <ShieldCheck size={18} style={{ color: "var(--att-ok)" }} />
                  </div>
                  <div style={{ fontSize: "12px", color: "var(--att-ok)", fontWeight: 600 }}>
                    Official Verified Event Producer
                  </div>
                </div>
              </div>

              <p style={{ fontSize: "13px", color: "var(--att-text-2)", lineHeight: "1.6", margin: "0 0 18px" }}>
                Committed to delivering authentic Caribbean culinary and entertainment experiences with direct community proceeds.
              </p>

              {/* Organizer Direct Contacts */}
              <div
                style={{
                  display: "flex",
                  flexWrap: "wrap",
                  gap: "10px",
                }}
              >
                {event.organizerPhone && (
                  <a
                    href={`tel:${event.organizerPhone}`}
                    style={{
                      display: "inline-flex",
                      alignItems: "center",
                      gap: "6px",
                      backgroundColor: "rgba(255, 255, 255, 0.06)",
                      border: "1px solid rgba(255, 255, 255, 0.12)",
                      borderRadius: "8px",
                      padding: "8px 12px",
                      fontSize: "12px",
                      color: "var(--att-text-body)",
                      textDecoration: "none",
                      fontWeight: 600,
                    }}
                  >
                    <Phone size={14} style={{ color: "var(--att-accent)" }} />
                    <span>{event.organizerPhone}</span>
                  </a>
                )}

                {event.organizerWhatsApp && (
                  <a
                    href={`https://wa.me/${event.organizerWhatsApp.replace(/[^0-9]/g, "")}`}
                    target="_blank"
                    rel="noreferrer"
                    style={{
                      display: "inline-flex",
                      alignItems: "center",
                      gap: "6px",
                      backgroundColor: "rgba(34, 197, 94, 0.12)",
                      border: "1px solid rgba(34, 197, 94, 0.25)",
                      borderRadius: "8px",
                      padding: "8px 12px",
                      fontSize: "12px",
                      color: "var(--att-ok)",
                      textDecoration: "none",
                      fontWeight: 600,
                    }}
                  >
                    <MessageCircle size={14} />
                    <span>WhatsApp Host</span>
                  </a>
                )}

                {event.organizerEmail && (
                  <a
                    href={`mailto:${event.organizerEmail}`}
                    style={{
                      display: "inline-flex",
                      alignItems: "center",
                      gap: "6px",
                      backgroundColor: "rgba(255, 255, 255, 0.06)",
                      border: "1px solid rgba(255, 255, 255, 0.12)",
                      borderRadius: "8px",
                      padding: "8px 12px",
                      fontSize: "12px",
                      color: "var(--att-text-body)",
                      textDecoration: "none",
                      fontWeight: 600,
                    }}
                  >
                    <Mail size={14} style={{ color: "var(--att-text-2)" }} />
                    <span>{event.organizerEmail}</span>
                  </a>
                )}
              </div>
            </section>
          </div>

          {/* RIGHT COLUMN: Sticky Purchase / Checkout Card on Desktop */}
          <div
            style={{
              position: "sticky",
              top: "24px",
              display: "flex",
              flexDirection: "column",
              gap: "20px",
            }}
          >
            <div
              id="sticky-ticket-purchase-card"
              style={{
                backgroundColor: "var(--att-surface-1)",
                borderRadius: "24px",
                padding: "28px",
                border: "1px solid rgba(255, 255, 255, 0.12)",
                boxShadow: "0 20px 40px rgba(0, 0, 0, 0.45)",
              }}
            >
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline", marginBottom: "16px" }}>
                <div>
                  <div style={{ fontSize: "11px", textTransform: "uppercase", color: "var(--att-text-2)", fontWeight: 700 }}>
                    Starting From
                  </div>
                  <div style={{ fontSize: "28px", fontWeight: 900, color: "#FFFFFF" }}>
                    {formatMoney(baseEntryPrice, currency)}
                  </div>
                </div>

                <span
                  style={{
                    backgroundColor: "rgba(34, 197, 94, 0.15)",
                    border: "1px solid rgba(34, 197, 94, 0.3)",
                    color: "var(--att-ok)",
                    padding: "4px 10px",
                    borderRadius: "9999px",
                    fontSize: "12px",
                    fontWeight: 700,
                  }}
                >
                  ✓ Real-time Inventory
                </span>
              </div>

              {/* Order Summary Breakdown */}
              <div
                style={{
                  backgroundColor: "var(--att-surface-2)",
                  borderRadius: "14px",
                  padding: "16px",
                  marginBottom: "20px",
                  border: "1px solid rgba(255, 255, 255, 0.06)",
                }}
              >
                <div style={{ fontSize: "12px", color: "var(--att-text-2)", fontWeight: 700, textTransform: "uppercase", marginBottom: "10px" }}>
                  Pass Selection Summary
                </div>

                <div style={{ display: "flex", flexDirection: "column", gap: "8px", fontSize: "13px" }}>
                  {ticketTiers.map((tier) => {
                    const qty = quantities[tier.id] || 0;
                    if (qty === 0) return null;
                    const tierPrice = (currency === "JMD" ? tier.priceJMD : tier.priceUSD) * qty;

                    return (
                      <div key={tier.id} style={{ display: "flex", justifyContent: "space-between", color: "var(--att-text-body)" }}>
                        <span>
                          {qty}x {tier.name}
                        </span>
                        <span style={{ fontWeight: 700, color: "#FFFFFF" }}>{formatMoney(tierPrice, currency)}</span>
                      </div>
                    );
                  })}

                  {totalTicketsCount === 0 && (
                    <div style={{ color: "var(--att-text-3)", fontStyle: "italic", textAlign: "center", padding: "8px 0" }}>
                      No passes selected. Use the counter controls to select tickets.
                    </div>
                  )}

                  <div
                    style={{
                      display: "flex",
                      justifyContent: "space-between",
                      alignItems: "center",
                      paddingTop: "12px",
                      borderTop: "1px solid rgba(255, 255, 255, 0.1)",
                      fontSize: "15px",
                      fontWeight: 800,
                      color: "#FFFFFF",
                    }}
                  >
                    <span>Total Amount</span>
                    <span style={{ color: "var(--att-accent)", fontSize: "18px" }}>{formatMoney(totalAmount, currency)}</span>
                  </div>
                </div>
              </div>

              {/* Prominent Gradient Purchase Button */}
              <button
                type="button"
                onClick={() => {
                  if (onNavigateToTickets) {
                    onNavigateToTickets();
                  } else {
                    handleProceedToPurchase();
                  }
                }}
                disabled={isReserving || totalTicketsCount === 0}
                id="btn-confirm-ticket-purchase"
                style={{
                  width: "100%",
                  background:
                    totalTicketsCount === 0
                      ? "var(--att-surface-3)"
                      : "var(--att-accent)",
                  border: "none",
                  borderRadius: "14px",
                  padding: "16px 24px",
                  fontSize: "16px",
                  fontWeight: 800,
                  color: "var(--att-on-accent)",
                  cursor: totalTicketsCount === 0 ? "not-allowed" : "pointer",
                  boxShadow:
                    totalTicketsCount === 0 ? "none" : "none",
                  transition: "all 0.2s cubic-bezier(0.4, 0, 0.2, 1)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "8px",
                }}
              >
                {isReserving ? (
                  <span>Reserving Your Passes...</span>
                ) : (
                  <>
                    <Ticket size={18} />
                    <span>
                      {totalTicketsCount > 0
                        ? `Continue to Ticket Selection • ${formatMoney(totalAmount, currency)}`
                        : "Select Passes to Continue"}
                    </span>
                  </>
                )}
              </button>

              {/* Quick Jump to Food Menu */}
              {event.menu && event.menu.length > 0 && (
                <button
                  type="button"
                  onClick={() => onNavigateToFood(eventIndex)}
                  id="btn-sidebar-order-food"
                  style={{
                    width: "100%",
                    marginTop: "12px",
                    background: "rgba(255, 255, 255, 0.06)",
                    border: "1px solid rgba(255, 255, 255, 0.12)",
                    borderRadius: "14px",
                    padding: "12px 18px",
                    fontSize: "14px",
                    fontWeight: 700,
                    color: "var(--att-text-body)",
                    cursor: "pointer",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    gap: "8px",
                    transition: "all 0.2s ease",
                  }}
                >
                  <Utensils size={16} style={{ color: "var(--att-ok)" }} />
                  <span>Pre-Order Caribbean Food Menu</span>
                </button>
              )}

              {/* Authentication Requirement Note */}
              <div
                style={{
                  marginTop: "16px",
                  fontSize: "11px",
                  color: "var(--att-text-2)",
                  textAlign: "center",
                  lineHeight: "1.4",
                }}
              >
                🔒 Direct verification & instant mobile QR passes. Secure checkout powered by EventBite.
              </div>
            </div>
          </div>
        </div>
      </main>

      {/* Mobile sticky action bar: the total lives on the button */}
      <div className="att-mobile-only att-action-bar" id="event-detail-mobile-action-bar">
        <div style={{ minWidth: 0 }}>
          <div style={{ fontSize: "12px", color: "var(--att-text-2)" }}>
            {totalTicketsCount > 0
              ? `${totalTicketsCount} pass${totalTicketsCount === 1 ? "" : "es"}`
              : "From"}
          </div>
          <div style={{ fontSize: "20px", fontWeight: 800, color: "var(--att-text-1)" }}>
            {formatMoney(totalTicketsCount > 0 ? totalAmount : baseEntryPrice, currency)}
          </div>
        </div>
        <button
          type="button"
          className="att-btn-primary"
          disabled={isReserving || totalTicketsCount === 0}
          onClick={() => {
            if (onNavigateToTickets) {
              onNavigateToTickets();
            } else {
              handleProceedToPurchase();
            }
          }}
        >
          {isReserving ? "Reserving..." : totalTicketsCount > 0 ? "Get Tickets" : "Pick a pass"}
        </button>
      </div>

      {/* Share Modal Dialog */}
      {showShareModal && (
        <div
          style={{
            position: "fixed",
            inset: 0,
            backgroundColor: "rgba(10, 15, 22, 0.75)",
            backdropFilter: "blur(8px)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            zIndex: 9999,
            padding: "20px",
          }}
          onClick={() => setShowShareModal(false)}
        >
          <div
            style={{
              backgroundColor: "var(--att-surface-2)",
              borderRadius: "20px",
              padding: "28px",
              maxWidth: "460px",
              width: "100%",
              border: "1px solid rgba(255, 255, 255, 0.15)",
              boxShadow: "0 20px 40px rgba(0, 0, 0, 0.5)",
            }}
            onClick={(e) => e.stopPropagation()}
          >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "16px" }}>
              <h3 style={{ fontSize: "18px", fontWeight: 800, color: "#FFFFFF", margin: 0 }}>
                Share This Island Event
              </h3>
              <button
                type="button"
                onClick={() => setShowShareModal(false)}
                style={{
                  background: "none",
                  border: "none",
                  color: "var(--att-text-2)",
                  cursor: "pointer",
                }}
              >
                <X size={20} />
              </button>
            </div>

            <p style={{ fontSize: "13px", color: "var(--att-text-body)", marginBottom: "20px" }}>
              Invite your friends and family to join you at <strong>{event.title}</strong>!
            </p>

            <div
              style={{
                display: "flex",
                alignItems: "center",
                backgroundColor: "var(--att-surface-2)",
                padding: "8px 12px",
                borderRadius: "10px",
                border: "1px solid rgba(255, 255, 255, 0.1)",
                marginBottom: "20px",
              }}
            >
              <input
                type="text"
                readOnly
                value={shareUrl}
                style={{
                  background: "transparent",
                  border: "none",
                  color: "#FFFFFF",
                  fontSize: "13px",
                  width: "100%",
                  outline: "none",
                }}
              />
              <button
                type="button"
                onClick={copyToClipboard}
                id="btn-copy-share-link"
                style={{
                  background: copiedLink ? "var(--att-ok)" : "var(--att-accent)",
                  border: "none",
                  borderRadius: "8px",
                  padding: "8px 14px",
                  fontSize: "12px",
                  fontWeight: 700,
                  color: "var(--att-on-accent)",
                  cursor: "pointer",
                  display: "flex",
                  alignItems: "center",
                  gap: "4px",
                  flexShrink: 0,
                }}
              >
                {copiedLink ? <Check size={14} /> : <Copy size={14} />}
                <span>{copiedLink ? "Copied!" : "Copy"}</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Lightbox Photo Preview */}
      {lightboxImage && (
        <div
          style={{
            position: "fixed",
            inset: 0,
            backgroundColor: "rgba(0, 0, 0, 0.9)",
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            justifyContent: "center",
            zIndex: 9999,
            padding: "24px",
          }}
          onClick={() => setLightboxImage(null)}
        >
          <button
            type="button"
            onClick={() => setLightboxImage(null)}
            style={{
              position: "absolute",
              top: "24px",
              right: "24px",
              background: "rgba(255, 255, 255, 0.2)",
              border: "none",
              borderRadius: "50%",
              width: "44px",
              height: "44px",
              color: "#FFFFFF",
              cursor: "pointer",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            <X size={24} />
          </button>
          <img
            src={lightboxImage.url}
            alt={lightboxImage.caption}
            style={{
              maxWidth: "90vw",
              maxHeight: "80vh",
              objectFit: "contain",
              borderRadius: "12px",
              boxShadow: "0 20px 50px rgba(0,0,0,0.8)",
            }}
          />
          <div style={{ marginTop: "16px", color: "#FFFFFF", fontSize: "15px", fontWeight: 700 }}>
            {lightboxImage.caption}
          </div>
        </div>
      )}
    </div>
  );
};
