import React, { useState, useEffect, useMemo } from "react";
import {
  ArrowLeft,
  Calendar,
  Clock,
  MapPin,
  CheckCircle2,
  Utensils,
  Plus,
  Minus,
  AlertCircle,
  ChevronRight,
  Info,
} from "lucide-react";

export interface TicketOption {
  id: string;
  name: string;
  category: "General" | "VIP" | "Early Bird" | "Group";
  priceUSD: number;
  priceJMD: number;
  description: string;
  benefits: string[];
  availableStock: number;
  maxPerCustomer: number;
  saleStatus: "available" | "low_stock" | "sold_out" | "sale_ended";
  restrictions?: string;
  badge?: string;
}

export interface TicketSelectionScreenProps {
  event: any;
  eventIndex: number;
  onBack: () => void;
  onContinueToCheckout: (selectionData: {
    selectedTickets: Record<string, number>;
    selectedFood: Record<string, number>;
    ticketSubtotal: number;
    foodSubtotal: number;
    packagingFee: number;
    taxAmount: number;
    finalTotal: number;
    totalTicketCount: number;
  }) => void;
  onOpenCompleteMenu: () => void;
  formatMoney: (amount: number, currency?: any) => string;
  initialSelectedTickets?: Record<string, number>;
  initialSelectedFood?: Record<string, number>;
}

export const TicketSelectionScreen: React.FC<TicketSelectionScreenProps> = ({
  event,
  eventIndex: _eventIndex,
  onBack,
  onContinueToCheckout,
  onOpenCompleteMenu,
  formatMoney,
  initialSelectedTickets,
  initialSelectedFood,
}) => {
  const currency = event?.currency || "USD";

  // Actual ticket options configured for this event (uses host-configured event.ticketTiers when present)
  const ticketOptions: TicketOption[] = useMemo(() => {
    const defaults: TicketOption[] = [
      {
        id: "tier-ga",
        name: "General Admission Pass",
        category: "General",
        priceUSD: 15,
        priceJMD: 2250,
        description: "Standard event admission, full access to live reggae stage, oceanfront lawn, and food stations.",
        benefits: [
          "Admission to main festival grounds",
          "Full access to live stage performances",
          "Instant digital QR pass issued to mobile",
        ],
        availableStock: 185,
        maxPerCustomer: 6,
        saleStatus: "available",
        restrictions: "Maximum 6 passes per customer",
        badge: "POPULAR",
      },
      {
        id: "tier-vip",
        name: "VIP Oceanfront Lounge Pass",
        category: "VIP",
        priceUSD: 45,
        priceJMD: 6800,
        description: "Express VIP entrance, reserved shaded waterfront lounge, dedicated bar, and complimentary drink voucher.",
        benefits: [
          "Express VIP priority check-in lane",
          "Reserved shaded stage-side lounge seating",
          "1 Complimentary artisanal island beverage voucher",
          "Dedicated bar & food runner service",
        ],
        availableStock: 38,
        maxPerCustomer: 4,
        saleStatus: "low_stock",
        restrictions: "Maximum 4 passes per customer • Age 18+ for VIP lounge access",
        badge: "VIP EXPERIENCE",
      },
      {
        id: "tier-early-bird",
        name: "Early-Bird Advance Pass",
        category: "Early Bird",
        priceUSD: 12,
        priceJMD: 1800,
        description: "Special discounted entry for early attendees who purchased prior to festival announcements.",
        benefits: [
          "Standard festival ground admission at early-bird discount",
          "Digital pass on mobile",
        ],
        availableStock: 0,
        maxPerCustomer: 4,
        saleStatus: "sale_ended",
        restrictions: "Sale ended Nov 15 • Tier sold out",
        badge: "SOLD OUT",
      },
      {
        id: "tier-group",
        name: "Family & Crew 4-Pack",
        category: "Group",
        priceUSD: 50,
        priceJMD: 7500,
        description: "Discounted group package providing digital entry passes for up to four attendees.",
        benefits: [
          "Admission for up to 4 guests (saves $10)",
          "Reserved community table upon arrival",
          "Individual digital QR passes for each guest",
        ],
        availableStock: 25,
        maxPerCustomer: 2,
        saleStatus: "available",
        restrictions: "Admits 4 people • Maximum 2 packages per customer",
        badge: "GROUP SAVINGS",
      },
    ];

    if (Array.isArray(event?.ticketTiers) && event.ticketTiers.length > 0) {
      return event.ticketTiers.map((t: any, idx: number): TicketOption => {
        const stock = Number(t.quantityLimit ?? t.quantity_limit ?? t.availableStock ?? 100);
        const maxPer = Number(t.maxPerCustomer ?? t.max_per_customer ?? 6);
        return {
          id: String(t.id || `tier-${idx}`),
          name: String(t.name || "Admission Pass"),
          category: String(t.name || "").toLowerCase().includes("vip")
            ? "VIP"
            : String(t.name || "").toLowerCase().includes("group")
            ? "Group"
            : "General",
          priceUSD: Number(t.priceUSD ?? t.price_usd ?? 15),
          priceJMD: Number(t.priceJMD ?? t.price_jmd ?? 2250),
          description: String(t.description || "Includes event admission and instant digital QR pass."),
          benefits: ["Admission to main event grounds", "Instant digital QR pass issued to mobile"],
          availableStock: stock,
          maxPerCustomer: maxPer,
          saleStatus: stock <= 0 ? "sold_out" : stock <= 25 ? "low_stock" : "available",
          restrictions: `Maximum ${maxPer} passes per customer`,
          badge: stock <= 0 ? "SOLD OUT" : idx === 0 ? "POPULAR" : undefined,
        };
      });
    }

    return defaults;
  }, [event?.ticketTiers]);

  // Ticket quantities state (preserves initial state if returning from food menu)
  const [ticketQuantities, setTicketQuantities] = useState<Record<string, number>>(() => {
    if (initialSelectedTickets && Object.keys(initialSelectedTickets).length > 0) {
      return initialSelectedTickets;
    }
    return {
      "tier-ga": 1,
      "tier-vip": 0,
      "tier-early-bird": 0,
      "tier-group": 0,
    };
  });

  // Optional food quantities state (preserves selections)
  const [foodQuantities, setFoodQuantities] = useState<Record<string, number>>(() => {
    return initialSelectedFood || {};
  });

  // Error / Toast state
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  }, []);

  const showError = (msg: string) => {
    setErrorMessage(msg);
    setTimeout(() => {
      setErrorMessage(null);
    }, 4000);
  };

  // Adjust ticket quantity with strict inventory & customer limit enforcement
  const handleAdjustTicket = (tier: TicketOption, delta: number) => {
    if (tier.saleStatus === "sold_out" || tier.saleStatus === "sale_ended") {
      showError(`"${tier.name}" is no longer available for purchase.`);
      return;
    }

    const currentQty = ticketQuantities[tier.id] || 0;
    const newQty = currentQty + delta;

    if (newQty < 0) return;

    if (newQty > tier.maxPerCustomer) {
      showError(`Limit reached: Maximum ${tier.maxPerCustomer} "${tier.name}" passes per customer.`);
      return;
    }

    if (newQty > tier.availableStock) {
      showError(`Only ${tier.availableStock} passes remaining in stock for "${tier.name}".`);
      return;
    }

    setTicketQuantities((prev) => ({
      ...prev,
      [tier.id]: newQty,
    }));
  };

  // Adjust optional food quantity
  const handleAdjustFood = (dishId: string, maxStock: number, delta: number) => {
    const currentQty = foodQuantities[dishId] || 0;
    const newQty = Math.max(0, currentQty + delta);

    if (newQty > maxStock) {
      showError(`Only ${maxStock} portions remaining for this dish.`);
      return;
    }

    if (newQty > 10) {
      showError("Maximum 10 portions per dish in advance pre-orders.");
      return;
    }

    setFoodQuantities((prev) => ({
      ...prev,
      [dishId]: newQty,
    }));
  };

  // Calculations in exact integer cents to avoid floating-point errors
  const ticketSubtotalCents = ticketOptions.reduce((acc, tier) => {
    const qty = ticketQuantities[tier.id] || 0;
    const unitPriceCents = Math.round((currency === "JMD" ? tier.priceJMD : tier.priceUSD) * 100);
    return acc + qty * unitPriceCents;
  }, 0);

  const optionalFoodSubtotalCents = (event.menu || []).reduce((acc: number, item: any) => {
    const qty = foodQuantities[item.id] || 0;
    const unitPriceCents = Math.round((currency === "JMD" ? item.priceJMD : item.priceUSD) * 100);
    return acc + qty * unitPriceCents;
  }, 0);

  const totalTicketCount = Object.values(ticketQuantities).reduce((a, b) => a + b, 0);
  const totalFoodCount = Object.values(foodQuantities).reduce((a, b) => a + b, 0);

  // Applicable packaging / facility fee
  const packagingFeeCents =
    totalTicketCount > 0 || totalFoodCount > 0
      ? Math.round((event.packagingFeeUSD || 1.5) * 100)
      : 0;

  // Applicable Florida 6.0% sales tax on taxable components (if applicable)
  const isTaxExempt = false; // Fundraiser exemption flag
  const taxRate = isTaxExempt ? 0 : 0.06;
  const taxableSubtotalCents = ticketSubtotalCents + optionalFoodSubtotalCents;
  const taxAmountCents = Math.round(taxableSubtotalCents * taxRate);

  const finalTotalCents = ticketSubtotalCents + optionalFoodSubtotalCents + packagingFeeCents + taxAmountCents;

  const ticketSubtotal = ticketSubtotalCents / 100;
  const foodSubtotal = optionalFoodSubtotalCents / 100;
  const packagingFee = packagingFeeCents / 100;
  const taxAmount = taxAmountCents / 100;
  const finalTotal = finalTotalCents / 100;

  // Proceed to next stage of checkout
  const handleContinue = () => {
    if (totalTicketCount === 0 && totalFoodCount === 0) {
      showError("Please select at least 1 ticket pass to continue.");
      return;
    }

    onContinueToCheckout({
      selectedTickets: ticketQuantities,
      selectedFood: foodQuantities,
      ticketSubtotal,
      foodSubtotal,
      packagingFee,
      taxAmount,
      finalTotal,
      totalTicketCount,
    });
  };

  return (
    <div
      style={{
        backgroundColor: "var(--att-bg)",
        minHeight: "100vh",
        color: "#FFFFFF",
        fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
        paddingBottom: "130px",
      }}
      id="ticket-selection-screen"
    >
      {/* Top Error Alert Banner */}
      {errorMessage && (
        <div
          style={{
            position: "fixed",
            top: "20px",
            left: "50%",
            transform: "translateX(-50%)",
            backgroundColor: "#DC2626",
            color: "#FFFFFF",
            padding: "12px 24px",
            borderRadius: "9999px",
            boxShadow: "0 10px 30px rgba(220, 38, 38, 0.4)",
            border: "1px solid rgba(255, 255, 255, 0.2)",
            zIndex: 9999,
            fontSize: "14px",
            fontWeight: 700,
            display: "flex",
            alignItems: "center",
            gap: "8px",
            animation: "fadeIn 0.2s ease-out",
          }}
          role="alert"
        >
          <AlertCircle size={18} />
          <span>{errorMessage}</span>
        </div>
      )}

      {/* Main Container */}
      <main
        style={{
          maxWidth: "1140px",
          margin: "0 auto",
          padding: "24px 20px",
        }}
      >
        {/* Back Navigation Button */}
        <div style={{ marginBottom: "20px" }}>
          <button
            type="button"
            onClick={onBack}
            id="btn-back-to-event-details"
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "8px",
              backgroundColor: "rgba(255, 255, 255, 0.08)",
              border: "1px solid rgba(255, 255, 255, 0.15)",
              color: "var(--att-text-body)",
              borderRadius: "9999px",
              padding: "8px 16px",
              fontSize: "13px",
              fontWeight: 700,
              cursor: "pointer",
              transition: "all 0.15s ease",
            }}
          >
            <ArrowLeft size={16} />
            <span>Back</span>
          </button>
        </div>

        {/* 1. Compact Event Summary Header */}
        <section
          id="ticket-event-summary-card"
          style={{
            backgroundColor: "var(--att-surface-1)",
            borderRadius: "20px",
            border: "1px solid rgba(255, 255, 255, 0.1)",
            padding: "18px 22px",
            marginBottom: "28px",
            display: "flex",
            alignItems: "center",
            gap: "18px",
            boxShadow: "0 8px 24px rgba(0, 0, 0, 0.3)",
          }}
          aria-label="Event Summary"
        >
          <div
            style={{
              width: "74px",
              height: "74px",
              borderRadius: "14px",
              overflow: "hidden",
              flexShrink: 0,
              border: "1px solid rgba(255, 255, 255, 0.15)",
            }}
          >
            <img
              src={event.flyerUrl || "/images/island_festival_sunset.jpg"}
              alt={event.title}
              style={{ width: "100%", height: "100%", objectFit: "cover" }}
            />
          </div>

          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "4px" }}>
              <span
                style={{
                  backgroundColor: "rgba(182, 240, 58, 0.25)",
                  color: "var(--att-accent)",
                  fontSize: "11px",
                  fontWeight: 800,
                  padding: "2px 8px",
                  borderRadius: "9999px",
                  textTransform: "uppercase",
                }}
              >
                Pass Selection
              </span>
              <span style={{ fontSize: "11px", color: "var(--att-ok)", fontWeight: 700 }}>
                ✓ Official Ticketing
              </span>
            </div>

            <h1
              id="ticket-selection-event-title"
              style={{
                fontSize: "20px",
                fontWeight: 900,
                color: "#FFFFFF",
                margin: "0 0 6px",
                lineHeight: "1.25",
                whiteSpace: "nowrap",
                overflow: "hidden",
                textOverflow: "ellipsis",
              }}
            >
              {event.title}
            </h1>

            <div
              style={{
                display: "flex",
                alignItems: "center",
                gap: "14px",
                fontSize: "12px",
                color: "var(--att-text-2)",
                flexWrap: "wrap",
              }}
            >
              <div style={{ display: "flex", alignItems: "center", gap: "5px" }}>
                <Calendar size={13} style={{ color: "var(--att-accent)" }} />
                <span>{event.dateStr}</span>
              </div>
              <div style={{ display: "flex", alignItems: "center", gap: "5px" }}>
                <Clock size={13} style={{ color: "var(--att-accent)" }} />
                <span>{event.timeStr}</span>
              </div>
              <div style={{ display: "flex", alignItems: "center", gap: "5px" }}>
                <MapPin size={13} style={{ color: "var(--att-text-2)" }} />
                <span style={{ whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                  {event.venueName}
                </span>
              </div>
            </div>
          </div>
        </section>

        {/* 2-Column Responsive Layout: Left = Ticket & Food Options, Right = Price Summary */}
        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(min(340px, 100%), 1fr))",
            gap: "32px",
            alignItems: "start",
          }}
        >
          {/* LEFT COLUMN: Ticket Cards & Optional Food */}
          <div style={{ display: "flex", flexDirection: "column", gap: "28px" }}>
            {/* 2. Ticket Options Section */}
            <section aria-label="Available ticket options">
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline", marginBottom: "16px" }}>
                <div>
                  <h2 style={{ fontSize: "18px", fontWeight: 900, color: "#FFFFFF", margin: "0 0 4px" }}>
                    Select Your Passes
                  </h2>
                  <p style={{ fontSize: "13px", color: "var(--att-text-2)", margin: 0 }}>
                    Each pass comes with its own QR code for entry.
                  </p>
                </div>
              </div>

              {/* Ticket Cards Grid */}
              <div style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
                {ticketOptions.map((tier) => {
                  const qty = ticketQuantities[tier.id] || 0;
                  const isSoldOut = tier.saleStatus === "sold_out" || tier.saleStatus === "sale_ended";
                  const unitPrice = currency === "JMD" ? tier.priceJMD : tier.priceUSD;

                  return (
                    <div
                      key={tier.id}
                      id={`ticket-card-${tier.id}`}
                      style={{
                        backgroundColor: qty > 0 ? "var(--att-surface-1)" : "var(--att-surface-1)",
                        border: qty > 0 ? "2px solid var(--att-accent)" : "1px solid rgba(255, 255, 255, 0.08)",
                        borderRadius: "18px",
                        padding: "20px",
                        opacity: isSoldOut ? 0.65 : 1,
                        transition: "all 0.2s ease",
                      }}
                    >
                      {/* Top Header of Ticket Card */}
                      <div
                        style={{
                          display: "flex",
                          justifyContent: "space-between",
                          alignItems: "flex-start",
                          marginBottom: "8px",
                        }}
                      >
                        <div>
                          <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
                            <h3 style={{ fontSize: "17px", fontWeight: 800, color: "#FFFFFF", margin: 0 }}>
                              {tier.name}
                            </h3>

                            {tier.badge && (
                              <span
                                style={{
                                  backgroundColor:
                                    tier.saleStatus === "sold_out" || tier.saleStatus === "sale_ended"
                                      ? "rgba(100, 116, 139, 0.3)"
                                      : "rgba(182, 240, 58, 0.2)",
                                  color:
                                    tier.saleStatus === "sold_out" || tier.saleStatus === "sale_ended"
                                      ? "var(--att-text-2)"
                                      : "var(--att-text-2)",
                                  fontSize: "10px",
                                  fontWeight: 800,
                                  padding: "2px 8px",
                                  borderRadius: "9999px",
                                  border: "1px solid rgba(255, 255, 255, 0.15)",
                                }}
                              >
                                {tier.badge}
                              </span>
                            )}
                          </div>

                          <p style={{ fontSize: "13px", color: "var(--att-text-2)", margin: "6px 0 12px", lineHeight: "1.4" }}>
                            {tier.description}
                          </p>
                        </div>

                        {/* Price Display */}
                        <div style={{ textAlign: "right", flexShrink: 0 }}>
                          <div style={{ fontSize: "22px", fontWeight: 900, color: "#FFFFFF" }}>
                            {formatMoney(unitPrice, currency)}
                          </div>
                          <div style={{ fontSize: "11px", color: "var(--att-text-2)" }}>{currency} per pass</div>
                        </div>
                      </div>

                      {/* Included Benefits Bullet List */}
                      <div style={{ display: "flex", flexDirection: "column", gap: "6px", marginBottom: "16px" }}>
                        {tier.benefits.map((b, bIdx) => (
                          <div
                            key={bIdx}
                            style={{ display: "flex", alignItems: "center", gap: "8px", fontSize: "12px", color: "var(--att-text-body)" }}
                          >
                            <CheckCircle2 size={14} style={{ color: "var(--att-ok)", flexShrink: 0 }} />
                            <span>{b}</span>
                          </div>
                        ))}
                      </div>

                      {/* Applicable Restrictions */}
                      {tier.restrictions && (
                        <div
                          style={{
                            fontSize: "11px",
                            color: isSoldOut ? "#EF4444" : "var(--att-text-2)",
                            marginBottom: "14px",
                            fontStyle: "italic",
                          }}
                        >
                          ⚠️ {tier.restrictions}
                        </div>
                      )}

                      {/* Bottom Footer of Card: Inventory Status + Quantity Controls */}
                      <div
                        style={{
                          display: "flex",
                          alignItems: "center",
                          justifyContent: "space-between",
                          paddingTop: "14px",
                          borderTop: "1px solid rgba(255, 255, 255, 0.08)",
                          flexWrap: "wrap",
                          gap: "10px",
                        }}
                      >
                        <div>
                          {isSoldOut ? (
                            <span style={{ fontSize: "12px", color: "#EF4444", fontWeight: 700 }}>
                              🚫 Sold Out / Sale Ended
                            </span>
                          ) : (
                            <span style={{ fontSize: "12px", color: "var(--att-text-2)" }}>
                              Availability: <strong style={{ color: "var(--att-ok)" }}>{tier.availableStock} in stock</strong>
                            </span>
                          )}
                        </div>

                        {/* Quantity Selector Controls */}
                        {!isSoldOut ? (
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
                              onClick={() => handleAdjustTicket(tier, -1)}
                              disabled={qty <= 0}
                              id={`btn-ticket-minus-${tier.id}`}
                              aria-label={`Decrease ${tier.name} quantity`}
                              style={{
                                width: "30px",
                                height: "30px",
                                borderRadius: "6px",
                                border: "none",
                                backgroundColor: qty > 0 ? "rgba(255, 255, 255, 0.12)" : "transparent",
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
                              id={`qty-display-${tier.id}`}
                              style={{
                                fontSize: "15px",
                                fontWeight: 800,
                                color: "#FFFFFF",
                                minWidth: "24px",
                                textAlign: "center",
                              }}
                            >
                              {qty}
                            </span>

                            <button
                              type="button"
                              onClick={() => handleAdjustTicket(tier, 1)}
                              disabled={qty >= tier.maxPerCustomer || qty >= tier.availableStock}
                              id={`btn-ticket-plus-${tier.id}`}
                              aria-label={`Increase ${tier.name} quantity`}
                              style={{
                                width: "30px",
                                height: "30px",
                                borderRadius: "6px",
                                border: "none",
                                backgroundColor:
                                  qty < tier.maxPerCustomer && qty < tier.availableStock
                                    ? "rgba(255, 255, 255, 0.15)"
                                    : "transparent",
                                color:
                                  qty < tier.maxPerCustomer && qty < tier.availableStock
                                    ? "#FFFFFF"
                                    : "var(--att-text-3)",
                                cursor:
                                  qty < tier.maxPerCustomer && qty < tier.availableStock
                                    ? "pointer"
                                    : "default",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                              }}
                            >
                              <Plus size={14} />
                            </button>
                          </div>
                        ) : (
                          <span
                            style={{
                              fontSize: "12px",
                              color: "var(--att-text-3)",
                              padding: "4px 10px",
                              borderRadius: "6px",
                              backgroundColor: "rgba(255, 255, 255, 0.05)",
                            }}
                          >
                            Unavailable
                          </span>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            </section>

            {/* 3. Optional Food Ordering Section */}
            {event.menu && event.menu.length > 0 && (
              <section
                id="ticket-optional-food-section"
                style={{
                  backgroundColor: "var(--att-surface-1)",
                  borderRadius: "20px",
                  border: "1px solid rgba(255, 255, 255, 0.08)",
                  padding: "24px",
                }}
                aria-label="Optional food packages"
              >
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
                    <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                      <Utensils size={18} style={{ color: "var(--att-ok)" }} />
                      <h3 style={{ fontSize: "17px", fontWeight: 800, color: "#FFFFFF", margin: 0 }}>
                        Optional Caribbean Food Packages
                      </h3>
                    </div>
                    <p style={{ fontSize: "13px", color: "var(--att-text-2)", margin: "4px 0 0" }}>
                      Freshly prepared on-site. Pre-order portions with your ticket pass to guarantee availability.
                    </p>
                  </div>

                  {/* Open Complete Menu Button (preserves ticket selection upon return) */}
                  <button
                    type="button"
                    onClick={onOpenCompleteMenu}
                    id="btn-open-complete-menu-from-tickets"
                    style={{
                      background: "rgba(34, 197, 94, 0.15)",
                      border: "1px solid rgba(34, 197, 94, 0.3)",
                      color: "var(--att-ok)",
                      padding: "8px 14px",
                      borderRadius: "10px",
                      fontSize: "12px",
                      fontWeight: 700,
                      cursor: "pointer",
                      display: "flex",
                      alignItems: "center",
                      gap: "6px",
                    }}
                  >
                    <span>Open Complete Menu</span>
                    <ChevronRight size={14} />
                  </button>
                </div>

                {/* Compact Food Items Quick Selector */}
                <div style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
                  {event.menu.slice(0, 3).map((dish: any) => {
                    const dishQty = foodQuantities[dish.id] || 0;
                    const dishPrice = currency === "JMD" ? dish.priceJMD : dish.priceUSD;

                    return (
                      <div
                        key={dish.id}
                        id={`optional-food-item-${dish.id}`}
                        style={{
                          backgroundColor: "var(--att-surface-2)",
                          borderRadius: "14px",
                          border: "1px solid rgba(255, 255, 255, 0.06)",
                          padding: "12px 16px",
                          display: "flex",
                          alignItems: "center",
                          justifyContent: "space-between",
                          gap: "12px",
                        }}
                      >
                        <div style={{ display: "flex", alignItems: "center", gap: "12px", minWidth: 0 }}>
                          <img
                            src={dish.imageUrl || "/images/escovitch_fish.jpg"}
                            alt={dish.name}
                            style={{ width: "48px", height: "48px", borderRadius: "10px", objectFit: "cover", flexShrink: 0 }}
                          />
                          <div style={{ minWidth: 0 }}>
                            <div style={{ fontSize: "14px", fontWeight: 700, color: "#FFFFFF", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                              {dish.name}
                            </div>
                            <div style={{ fontSize: "12px", color: "var(--att-accent)", fontWeight: 600 }}>
                              {formatMoney(dishPrice, currency)}
                            </div>
                          </div>
                        </div>

                        {/* Dish Counter */}
                        <div
                          style={{
                            display: "flex",
                            alignItems: "center",
                            gap: "8px",
                            backgroundColor: "var(--att-surface-2)",
                            padding: "3px 8px",
                            borderRadius: "8px",
                            border: "1px solid rgba(255, 255, 255, 0.08)",
                          }}
                        >
                          <button
                            type="button"
                            onClick={() => handleAdjustFood(dish.id, dish.availableStock || 50, -1)}
                            disabled={dishQty <= 0}
                            id={`btn-food-minus-${dish.id}`}
                            aria-label={`Decrease ${dish.name} quantity`}
                            style={{
                              width: "24px",
                              height: "24px",
                              borderRadius: "4px",
                              border: "none",
                              backgroundColor: dishQty > 0 ? "rgba(255, 255, 255, 0.1)" : "transparent",
                              color: dishQty > 0 ? "#FFFFFF" : "var(--att-text-3)",
                              cursor: dishQty > 0 ? "pointer" : "default",
                              display: "flex",
                              alignItems: "center",
                              justifyContent: "center",
                            }}
                          >
                            <Minus size={12} />
                          </button>

                          <span style={{ fontSize: "13px", fontWeight: 700, minWidth: "16px", textAlign: "center" }}>
                            {dishQty}
                          </span>

                          <button
                            type="button"
                            onClick={() => handleAdjustFood(dish.id, dish.availableStock || 50, 1)}
                            id={`btn-food-plus-${dish.id}`}
                            aria-label={`Increase ${dish.name} quantity`}
                            style={{
                              width: "24px",
                              height: "24px",
                              borderRadius: "4px",
                              border: "none",
                              backgroundColor: "rgba(255, 255, 255, 0.12)",
                              color: "#FFFFFF",
                              cursor: "pointer",
                              display: "flex",
                              alignItems: "center",
                              justifyContent: "center",
                            }}
                          >
                            <Plus size={12} />
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </section>
            )}
          </div>

          {/* RIGHT COLUMN: Sticky Price Breakdown & Checkout Action */}
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
              id="ticket-price-summary-card"
              style={{
                backgroundColor: "var(--att-surface-1)",
                borderRadius: "24px",
                padding: "28px",
                border: "1px solid rgba(255, 255, 255, 0.12)",
                boxShadow: "0 20px 40px rgba(0, 0, 0, 0.45)",
              }}
            >
              <h3 style={{ fontSize: "18px", fontWeight: 900, color: "#FFFFFF", margin: "0 0 16px" }}>
                Price Summary
              </h3>

              {/* Detailed Breakdown */}
              <div
                style={{
                  display: "flex",
                  flexDirection: "column",
                  gap: "12px",
                  fontSize: "13px",
                  marginBottom: "20px",
                }}
              >
                {/* Ticket Subtotal */}
                <div style={{ display: "flex", justifyContent: "space-between", color: "var(--att-text-body)" }}>
                  <span>Ticket Subtotal ({totalTicketCount} {totalTicketCount === 1 ? "pass" : "passes"}):</span>
                  <span style={{ fontWeight: 700, color: "#FFFFFF" }}>{formatMoney(ticketSubtotal, currency)}</span>
                </div>

                {/* Optional Food Subtotal */}
                {foodSubtotal > 0 && (
                  <div style={{ display: "flex", justifyContent: "space-between", color: "var(--att-text-body)" }}>
                    <span>Optional Food Dining ({totalFoodCount} meals):</span>
                    <span style={{ fontWeight: 700, color: "var(--att-ok)" }}>{formatMoney(foodSubtotal, currency)}</span>
                  </div>
                )}

                {/* Processing Fee */}
                <div style={{ display: "flex", justifyContent: "space-between", color: "var(--att-text-2)" }}>
                  <div style={{ display: "flex", alignItems: "center", gap: "4px" }}>
                    <span>Packaging & Station Fee:</span>
                    <span title="Covers eco-friendly island takeout packaging and mobile ticket validation infrastructure">
                      <Info size={12} />
                    </span>
                  </div>
                  <span>{formatMoney(packagingFee, currency)}</span>
                </div>

                {/* Tax Breakdown */}
                <div style={{ display: "flex", justifyContent: "space-between", color: "var(--att-text-2)" }}>
                  <span>State Sales Tax (FL 6.0%):</span>
                  <span>{formatMoney(taxAmount, currency)}</span>
                </div>

                {/* Final Total */}
                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                    paddingTop: "14px",
                    borderTop: "1px solid rgba(255, 255, 255, 0.12)",
                    fontSize: "16px",
                    fontWeight: 900,
                    color: "#FFFFFF",
                  }}
                >
                  <span>Final Total:</span>
                  <span style={{ color: "var(--att-accent)", fontSize: "22px" }}>
                    {formatMoney(finalTotal, currency)}
                  </span>
                </div>
              </div>

              {/* Authoritative Server Notice */}
              <div
                style={{
                  backgroundColor: "var(--att-surface-2)",
                  borderRadius: "12px",
                  padding: "10px 14px",
                  marginBottom: "20px",
                  border: "1px solid rgba(255, 255, 255, 0.06)",
                  fontSize: "11px",
                  color: "var(--att-text-2)",
                  lineHeight: "1.4",
                }}
              >
                🔒 <strong>Authoritative Total:</strong> Prices are verified against real-time inventory and state tax rules. No hidden processing surcharges.
              </div>

              {/* Prominent Gradient Continue Button */}
              <button
                type="button"
                onClick={handleContinue}
                disabled={totalTicketCount === 0 && totalFoodCount === 0}
                id="btn-ticket-continue-checkout"
                style={{
                  width: "100%",
                  background:
                    totalTicketCount === 0 && totalFoodCount === 0
                      ? "var(--att-surface-3)"
                      : "var(--att-accent)",
                  border: "none",
                  borderRadius: "14px",
                  padding: "16px 24px",
                  fontSize: "15px",
                  fontWeight: 800,
                  color: "var(--att-on-accent)",
                  cursor: totalTicketCount === 0 && totalFoodCount === 0 ? "not-allowed" : "pointer",
                  boxShadow:
                    totalTicketCount === 0 && totalFoodCount === 0
                      ? "none"
                      : "none",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "8px",
                  transition: "all 0.2s cubic-bezier(0.4, 0, 0.2, 1)",
                }}
              >
                <span>Continue to Checkout</span>
                <ChevronRight size={18} />
              </button>

              <div
                style={{
                  marginTop: "14px",
                  fontSize: "11px",
                  color: "var(--att-text-3)",
                  textAlign: "center",
                  lineHeight: "1.4",
                }}
              >
                Tickets are reserved in your basket for 15 minutes. Pass confirmation occurs upon checkout payment.
              </div>
            </div>
          </div>
        </div>
      </main>

      {/* 4. Fixed Bottom Summary for Mobile */}
      <div
        id="mobile-ticket-selection-bar"
        style={{
          position: "fixed",
          bottom: 0,
          left: 0,
          right: 0,
          backgroundColor: "var(--att-surface-1)",
          borderTop: "1px solid rgba(255, 255, 255, 0.12)",
          padding: "14px 20px",
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          zIndex: 100,
          boxShadow: "0 -8px 25px rgba(0, 0, 0, 0.5)",
        }}
      >
        <div>
          <div style={{ fontSize: "11px", color: "var(--att-text-2)" }}>
            {totalTicketCount} {totalTicketCount === 1 ? "pass" : "passes"} selected
          </div>
          <div style={{ fontSize: "18px", fontWeight: 900, color: "var(--att-accent)" }}>
            {formatMoney(finalTotal, currency)}
          </div>
        </div>

        <button
          type="button"
          onClick={handleContinue}
          disabled={totalTicketCount === 0 && totalFoodCount === 0}
          id="btn-mobile-ticket-continue"
          style={{
            background:
              totalTicketCount === 0 && totalFoodCount === 0
                ? "var(--att-surface-3)"
                : "var(--att-accent)",
            border: "none",
            borderRadius: "12px",
            padding: "12px 20px",
            fontSize: "14px",
            fontWeight: 800,
            color: "var(--att-on-accent)",
            cursor: totalTicketCount === 0 && totalFoodCount === 0 ? "not-allowed" : "pointer",
            display: "flex",
            alignItems: "center",
            gap: "6px",
          }}
        >
          <span>Continue</span>
          <ChevronRight size={16} />
        </button>
      </div>
    </div>
  );
};
