import React, { useEffect, useMemo, useState } from "react";
import {
  AlertCircle,
  AlertTriangle,
  ArrowDownLeft,
  ArrowRight,
  Banknote,
  Bell,
  Calendar,
  Check,
  CheckCircle2,
  Clock3,
  Copy,
  DollarSign,
  Download,
  Eye,
  Globe,
  Heart,
  Info,
  Lock,
  Mail,
  MapPin,
  MessageCircle,
  MessageSquare,
  Minus,
  Phone,
  Plus,
  RefreshCw,
  Search,
  Send,
  Share2,
  ShieldAlert,
  ShieldCheck,
  ShoppingBag,
  Sliders,
  Sparkles,
  Ticket,
  UserRound,
  Utensils,
  Settings2,
  X,
} from "lucide-react";
import { AccountSettingsView } from "./AccountSettingsView";

import {
  AttendeeProfile,
  Currency,
  EventDetails,
  MenuItem,
  MethodId,
  NotificationLogEntry,
  OrderItem,
  PaymentStatus,
  PricingType,
  Settings,
  TicketOrder,
} from "../types";
import { METHOD_INFO } from "../App";
import type { OrderRequest } from "../lib/api";
import {
  calculateItemFinalPrice,
  calculateOrderTotals,
  evaluateEventVisibility,
  getEventFlyerDisplay,
  retrieveOrderSecurely,
} from "../tests/testUtils";

type Props = {
  eventDetails: EventDetails;
  menu: MenuItem[];
  settings: Settings;
  orders: TicketOrder[];
  notificationLogs?: NotificationLogEntry[];
  availableMethods: MethodId[];
  isOwnerPreview?: boolean;
  // Resolves with the order as recorded (by the API when connected) or rejects with a customer-facing error
  onPlaceOrder: (order: TicketOrder, request: OrderRequest) => Promise<TicketOrder>;
  onSendCustomNotification?: (entry: Omit<NotificationLogEntry, "id" | "sentAt" | "createdAt">) => void;
  onOpenSettings: () => void;
  formatMoney: (amount: number, currency: Currency) => string;
  attendeeProfile?: AttendeeProfile;
  onSubmitDispute?: (claim: {
    orderNumber: string;
    ticketCode: string;
    reason: string;
    amount: number;
    currency: Currency;
    customerName: string;
    customerPhone: string;
    organizerId: string;
    organizerName: string;
    eventName: string;
  }) => void;
  allEvents?: (EventDetails & { menu?: MenuItem[]; locationCategory?: string })[];
  onSelectEvent?: (index: number) => void;
  selectedEventIndex?: number;
  isLoggedIn?: boolean;
  onLogin?: () => void;
  onLogout?: () => void;
  signInLabel?: string;
};

type CartItem = {
  menuItemId: string;
  name: string;
  pricingType: PricingType;
  basePrice: number;
  optionsTotalExtra: number;
  selectedOptionsSummary: string[];
  customDonationAmount?: number;
  finalItemPrice: number;
  quantity: number;
  imageUrl: string;
  specialInstructions?: string;
  selectedChoices?: Record<string, number>;
};

export const GuestMenuCheckout: React.FC<Props> = ({
  eventDetails,
  menu,
  settings,
  orders,
  notificationLogs = [],
  availableMethods,
  isOwnerPreview = false,
  onPlaceOrder,
  onSendCustomNotification,
  onOpenSettings,
  formatMoney,
  attendeeProfile,
  onSubmitDispute,
  allEvents,
  onSelectEvent,
  selectedEventIndex = 0,
  isLoggedIn = false,
  onLogin,
  onLogout,
  signInLabel = "✨ One-Click Sign In (Attendee)",
}) => {
  const [selectedCategory, setSelectedCategory] = useState<string>("All");
  const [cart, setCart] = useState<CartItem[]>([]);
  const [showLoginPromptModal, setShowLoginPromptModal] = useState(false);
  const [activeStep, setActiveStep] = useState<
    "menu" | "checkout" | "ticket" | "orders_wallet" | "saved_events" | "settings"
  >("menu");

  // Attendee Notification Preferences
  const [prefSms, setPrefSms] = useState(attendeeProfile?.notificationPreferences.smsReminders ?? true);
  const [prefWhatsapp, setPrefWhatsapp] = useState(attendeeProfile?.notificationPreferences.whatsAppTickets ?? true);
  const [prefEmail, setPrefEmail] = useState(attendeeProfile?.notificationPreferences.emailReceipts ?? true);

  // Transfer Ref & Dispute State in Wallet
  const [submittingRefOrderId, setSubmittingRefOrderId] = useState<string | null>(null);
  const [transferRefInput, setTransferRefInput] = useState("");
  const [disputeModalOrder, setDisputeModalOrder] = useState<TicketOrder | null>(null);
  const [disputeReasonInput, setDisputeReasonInput] = useState("");

  // Email Receipt State
  const [receiptEmailInput, setReceiptEmailInput] = useState("");
  const [receiptEmailSent, setReceiptEmailSent] = useState(false);

  // Customer Contact State
  const [customerName, setCustomerName] = useState("");
  const [customerPhone, setCustomerPhone] = useState("");
  const [customerEmail, setCustomerEmail] = useState("");
  const [selectedMethod, setSelectedMethod] = useState<MethodId | "">("");
  const [paymentReference, setPaymentReference] = useState("");
  const [senderName, setSenderName] = useState("");

  // Ticket viewing state
  const [issuedTicket, setIssuedTicket] = useState<TicketOrder | null>(null);
  const [isSubmittingOrder, setIsSubmittingOrder] = useState(false);
  const [showSmsModal, setShowSmsModal] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [lightboxImg, setLightboxImg] = useState<{ url: string; caption: string } | null>(null);

  const visibility = evaluateEventVisibility(eventDetails, Boolean(isOwnerPreview));

  // Retrieve lost ticket search state
  const [showRetrieveBar, setShowRetrieveBar] = useState(false);
  const [retrieveQuery, setRetrieveQuery] = useState("");

  // Customization Modal State for "customizable" meals
  const [customizingItem, setCustomizingItem] = useState<MenuItem | null>(null);
  const [chosenOptionChoices, setChosenOptionChoices] = useState<Record<string, number>>({});
  const [itemSpecialInstructions, setItemSpecialInstructions] = useState("");

  // Custom Amount / Donation Modal State for "custom_amount"
  const [donationItem, setDonationItem] = useState<MenuItem | null>(null);
  const [customAmountValue, setCustomAmountValue] = useState<number>(1500);

  // Auto-detect and display ticket if opened via secure direct link (?ticket=... or #ticket=...)
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const ticketParam = params.get("ticket") || params.get("order") || window.location.hash.replace("#", "");
    if (ticketParam && orders.length > 0) {
      const q = ticketParam.trim().toLowerCase();
      const matched = orders.find(
        (o) =>
          o.ticketCode.toLowerCase() === q ||
          o.orderNumber.toLowerCase() === q ||
          o.secureTicketToken.toLowerCase() === q
      );
      if (matched) {
        setIssuedTicket(matched);
        setActiveStep("ticket");
      }
    }
  }, [orders]);

  const categories = useMemo(() => {
    const set = new Set<string>();
    menu.forEach((item) => set.add(item.category));
    return ["All", ...Array.from(set)];
  }, [menu]);

  const filteredMenu = useMemo(() => {
    if (selectedCategory === "All") return menu;
    return menu.filter((item) => item.category === selectedCategory);
  }, [menu, selectedCategory]);

  const getItemBasePrice = (item: MenuItem): number =>
    settings.currency === "JMD" ? item.priceJMD : item.priceUSD;

  const packagingFee =
    settings.currency === "JMD" ? eventDetails.packagingFeeJMD : eventDetails.packagingFeeUSD;

  const cartCount = cart.reduce((sum, item) => sum + item.quantity, 0);

  const cartTotals = useMemo(() => {
    return calculateOrderTotals(
      cart.map((ci) => ({ finalItemPrice: ci.finalItemPrice, quantity: ci.quantity })),
      cart.length > 0 ? packagingFee : 0,
      settings.currency
    );
  }, [cart, packagingFee, settings.currency]);

  const cartSubtotal = cartTotals.subtotal;
  const finalTotalAmount = cartTotals.totalAmount;

  function showToast(msg: string) {
    setToastMessage(msg);
    setTimeout(() => setToastMessage(""), 2800);
  }

  // Adding Fixed-Price Item (Tamper-proof: uses server/config base price)
  function handleAddFixedItem(item: MenuItem) {
    if (!isLoggedIn) {
      setShowLoginPromptModal(true);
      return;
    }
    const basePrice = getItemBasePrice(item);

    setCart((prev) => {
      const existingIdx = prev.findIndex(
        (ci) => ci.menuItemId === item.id && ci.pricingType === "fixed"
      );
      if (existingIdx >= 0) {
        const next = [...prev];
        next[existingIdx].quantity += 1;
        return next;
      }
      return [
        ...prev,
        {
          menuItemId: item.id,
          name: item.name,
          pricingType: "fixed",
          basePrice,
          optionsTotalExtra: 0,
          selectedOptionsSummary: [],
          finalItemPrice: basePrice,
          quantity: 1,
          imageUrl: item.imageUrl,
        },
      ];
    });
    showToast(`Added ${item.name} to order`);
  }

  // Open Customization Modal for customizable meals
  function openCustomization(item: MenuItem) {
    if (!isLoggedIn) {
      setShowLoginPromptModal(true);
      return;
    }
    setCustomizingItem(item);
    const initialChoices: Record<string, number> = {};
    if (item.customOptionGroups) {
      item.customOptionGroups.forEach((group) => {
        initialChoices[group.id] = 0; // default to first option
      });
    }
    setChosenOptionChoices(initialChoices);
    setItemSpecialInstructions("");
  }

  // Confirm Customization & Add to Cart using exact integer minor units
  function handleAddCustomizedItem() {
    if (!customizingItem) return;

    const calculated = calculateItemFinalPrice(
      customizingItem,
      settings.currency,
      chosenOptionChoices
    );

    const newCartItem: CartItem = {
      menuItemId: customizingItem.id,
      name: customizingItem.name,
      pricingType: "customizable",
      basePrice: calculated.basePrice,
      optionsTotalExtra: calculated.optionsExtra,
      selectedOptionsSummary: calculated.summaryList,
      finalItemPrice: calculated.finalPrice,
      quantity: 1,
      imageUrl: customizingItem.imageUrl,
      specialInstructions: itemSpecialInstructions.trim() || undefined,
      selectedChoices: { ...chosenOptionChoices },
    };

    setCart((prev) => [...prev, newCartItem]);
    setCustomizingItem(null);
    showToast(`Added custom ${customizingItem.name} to order`);
  }

  // Open Custom Amount / Donation Modal
  function openDonationModal(item: MenuItem) {
    if (!isLoggedIn) {
      setShowLoginPromptModal(true);
      return;
    }
    setDonationItem(item);
    const defaultAmt =
      settings.currency === "JMD"
        ? item.customAmountConfig?.suggestedAmountsJMD[0] || 1000
        : item.customAmountConfig?.suggestedAmountsUSD[0] || 10;
    setCustomAmountValue(defaultAmt);
  }

  // Confirm Custom Amount Item using exact integer minor units
  function handleAddCustomAmountItem() {
    if (!donationItem || !donationItem.customAmountConfig) return;

    const config = donationItem.customAmountConfig;
    const minVal = settings.currency === "JMD" ? config.minJMD : config.minUSD;
    const maxVal = settings.currency === "JMD" ? config.maxJMD : config.maxUSD;

    if (customAmountValue < minVal || customAmountValue > maxVal) {
      showToast(
        `Amount must be between ${formatMoney(minVal, settings.currency)} and ${formatMoney(
          maxVal,
          settings.currency
        )}`
      );
      return;
    }

    const calculated = calculateItemFinalPrice(
      donationItem,
      settings.currency,
      {},
      customAmountValue
    );

    const newCartItem: CartItem = {
      menuItemId: donationItem.id,
      name: donationItem.name,
      pricingType: "custom_amount",
      basePrice: calculated.basePrice,
      optionsTotalExtra: 0,
      selectedOptionsSummary: calculated.summaryList,
      customDonationAmount: customAmountValue,
      finalItemPrice: calculated.finalPrice,
      quantity: 1,
      imageUrl: donationItem.imageUrl,
    };

    setCart((prev) => [...prev, newCartItem]);
    setDonationItem(null);
    showToast(`Added donation of ${formatMoney(customAmountValue, settings.currency)}`);
  }

  function updateQuantity(itemIndex: number, delta: number) {
    setCart((prev) => {
      const next = [...prev];
      const newQty = next[itemIndex].quantity + delta;
      if (newQty <= 0) {
        return next.filter((_, idx) => idx !== itemIndex);
      }
      next[itemIndex].quantity = newQty;
      return next;
    });
  }

  async function handleCheckoutSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (isSubmittingOrder || !customerName.trim() || !customerPhone.trim() || !selectedMethod || cart.length === 0) {
      return;
    }

    const randomDigits = Math.floor(1000 + Math.random() * 9000);
    const orderNumber = `ORD-${new Date().getFullYear()}-${randomDigits}`;
    const ticketCode = `SFF-${randomDigits}`;
    const token = `tok-secure-${Date.now()}-${randomDigits}`;

    const orderItems: OrderItem[] = cart.map((ci) => {
      const sourceItem = menu.find((m) => m.id === ci.menuItemId);
      return {
        menuItemId: ci.menuItemId,
        name: ci.name,
        category: sourceItem?.category || "Mains",
        pricingType: ci.pricingType,
        basePrice: ci.basePrice,
        selectedOptionsSummary: ci.selectedOptionsSummary,
        optionsTotalExtra: ci.optionsTotalExtra,
        customDonationAmount: ci.customDonationAmount,
        finalItemPrice: ci.finalItemPrice,
        currency: settings.currency,
        quantity: ci.quantity,
        specialInstructions: ci.specialInstructions,
        handoverStatus: "pending",
      };
    });

    // RULE: Do not mark an order paid merely because it was submitted!
    // Initially create orders with the appropriate unpaid or awaiting-payment status.
    let initialStatus: PaymentStatus = "awaiting_payment";
    const hasTransferRef = Boolean(
      (paymentReference.trim().length > 0) || (senderName.trim().length > 0 && selectedMethod !== "cash")
    );

    if (selectedMethod === "cash") {
      initialStatus = "awaiting_payment";
    } else if (hasTransferRef) {
      initialStatus = "payment_submitted";
    } else {
      initialStatus = "awaiting_payment";
    }

    const newOrder: TicketOrder = {
      id: crypto.randomUUID(),
      organizerId: eventDetails.organizerId,
      ticketCode,
      orderNumber,
      customerName: customerName.trim(),
      customerPhone: customerPhone.trim(),
      email: customerEmail.trim() || undefined,
      method: selectedMethod,
      subtotal: cartSubtotal,
      packagingFee: cart.length > 0 ? packagingFee : 0,
      totalAmount: finalTotalAmount,
      amountPaid: 0, // Invariant: Initially 0 until verified by event owner / cashier
      currency: settings.currency,
      status: initialStatus,
      fulfillmentStatus: "unprepared",
      paymentReference: paymentReference.trim() || undefined,
      senderName: senderName.trim() || customerName.trim(),
      authorizedUnpaid: selectedMethod === "cash",
      createdAt: new Date().toISOString(),
      items: orderItems,
      allHandedOver: false,
      secureTicketToken: token,
    };

    // Only ids, quantities and choices are sent: the server recomputes every price
    const orderRequest: OrderRequest = {
      customer_name: newOrder.customerName,
      customer_phone: newOrder.customerPhone,
      email: newOrder.email,
      method: selectedMethod,
      currency: settings.currency,
      payment_reference: newOrder.paymentReference,
      sender_name: senderName.trim() || undefined,
      items: cart.map((ci) => ({
        menu_item_id: ci.menuItemId,
        quantity: ci.quantity,
        selected_choices: ci.selectedChoices,
        custom_donation_amount: ci.customDonationAmount,
        special_instructions: ci.specialInstructions,
      })),
    };

    setIsSubmittingOrder(true);
    let placedOrder: TicketOrder;
    try {
      placedOrder = await onPlaceOrder(newOrder, orderRequest);
    } catch (error) {
      showToast(error instanceof Error ? error.message : "Your order could not be placed. Please try again.");
      return;
    } finally {
      setIsSubmittingOrder(false);
    }

    setIssuedTicket(placedOrder);
    setCart([]);
    setPaymentReference("");
    setSenderName("");
    setActiveStep("ticket");
  }

  function handleRetrieveTicket(e: React.FormEvent) {
    e.preventDefault();
    const q = retrieveQuery.trim();
    if (!q) return;

    // Check secure retrieval via retrieveOrderSecurely first
    const secureRes = retrieveOrderSecurely(orders, q);
    if (secureRes.success && secureRes.order) {
      setIssuedTicket(secureRes.order);
      setActiveStep("ticket");
      setShowRetrieveBar(false);
      setRetrieveQuery("");
      showToast(`Found ticket for ${secureRes.order.customerName}!`);
      return;
    }

    const qLower = q.toLowerCase();
    const cleanDigits = q.replace(/\D/g, "");

    const found = orders.find(
      (o) =>
        o.ticketCode.toLowerCase() === qLower ||
        o.orderNumber.toLowerCase() === qLower ||
        o.secureTicketToken.toLowerCase() === qLower ||
        (cleanDigits.length >= 7 && o.customerPhone.replace(/\D/g, "").includes(cleanDigits))
    );

    if (found) {
      setIssuedTicket(found);
      setActiveStep("ticket");
      setShowRetrieveBar(false);
      setRetrieveQuery("");
      showToast(`Found ticket for ${found.customerName}!`);
    } else {
      showToast(secureRes.error || "No ticket found with that order code, token, or phone number.");
    }
  }

  async function copyText(text: string, msg = "Copied to clipboard!") {
    try {
      await navigator.clipboard.writeText(text);
      showToast(msg);
    } catch {
      showToast("Unable to copy automatically.");
    }
  }

  // Mask phone for customer privacy on public passes
  const maskedPhone = (phoneStr: string) => {
    if (phoneStr.length < 7) return phoneStr;
    return phoneStr.replace(/(\+?\d{1,3}[\s-]?)?(\d{3})\d{3}(\d{4})/, "$1$2-***-$3");
  };

  const shareableTicketUrl = issuedTicket
    ? `${window.location.origin}?ticket=${issuedTicket.ticketCode}`
    : "";

  return (
    <div className="guest-journey-container">
      {/* 10 Fish Fry Events Discovery Tour Strip */}
      {allEvents && allEvents.length > 0 && (
        <div
          className="fishfry-events-discovery-strip"
          style={{
            marginBottom: "20px",
            background: "#ffffff",
            border: "1px solid #e2e8f0",
            borderRadius: "14px",
            padding: "16px 18px",
            boxShadow: "0 2px 4px rgba(0,0,0,0.02)",
          }}
          id="fishfry-events-tour-container"
        >
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "12px", flexWrap: "wrap", gap: "8px" }}>
            <div>
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <span style={{ fontSize: "12px", fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.06em", color: "#D4AF37" }}>
                  🌴 Premier Cultural & Culinary Events Tour
                </span>
                <span style={{ fontSize: "11px", fontWeight: 700, background: "#EEF2FF", color: "#4338CA", padding: "1px 8px", borderRadius: "9999px", border: "1px solid #C7D2FE" }}>
                  {allEvents.length} Curated Experiences
                </span>
              </div>
              <h3 style={{ fontSize: "16px", fontWeight: 800, color: "#111827", margin: "3px 0 0" }}>
                Select an Event to View Its Venue, Schedule & Exclusive Menu
              </h3>
            </div>
            <div style={{ fontSize: "12px", color: "#64748B" }}>
              Showing: <strong>{selectedEventIndex + 1} of {allEvents.length}</strong> &bull; Click any card to switch
            </div>
          </div>

          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fill, minmax(215px, 1fr))",
              gap: "10px",
            }}
          >
            {allEvents.map((evt, idx) => {
              const isSelected = selectedEventIndex === idx;
              return (
                <button
                  type="button"
                  key={evt.id || idx}
                  onClick={() => onSelectEvent && onSelectEvent(idx)}
                  style={{
                    border: isSelected ? "2px solid #4F46E5" : "1px solid #E2E8F0",
                    background: isSelected ? "#EEF2FF" : "#ffffff",
                    borderRadius: "12px",
                    padding: "10px 12px",
                    textAlign: "left",
                    cursor: "pointer",
                    transition: "all 0.15s ease",
                    boxShadow: isSelected ? "0 4px 14px rgba(79, 70, 229, 0.16)" : "none",
                  }}
                  id={`btn-select-event-${idx}`}
                >
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "4px" }}>
                    <span style={{ fontSize: "10px", fontWeight: 700, color: isSelected ? "#4338CA" : "#64748B", textTransform: "uppercase" }}>
                      #{idx + 1} · {evt.locationCategory || "United States"}
                    </span>
                    {isSelected && (
                      <span style={{ fontSize: "9px", fontWeight: 800, background: "#4F46E5", color: "#fff", padding: "1px 6px", borderRadius: "4px" }}>
                        Selected
                      </span>
                    )}
                  </div>
                  <div style={{ fontSize: "12px", fontWeight: 700, color: "#111827", lineHeight: "1.3", marginBottom: "4px" }}>
                    {evt.title}
                  </div>
                  <div style={{ fontSize: "11px", color: "#475569" }}>
                    📅 {evt.dateStr}
                  </div>
                  <div style={{ fontSize: "10px", color: "#64748B", marginTop: "2px", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                    📍 {evt.venueName}
                  </div>
                </button>
              );
            })}
          </div>
        </div>
      )}

      {/* Public Visitor Mode Notice vs Logged-In Attendee Notice */}
      {!isLoggedIn ? (
        <div
          style={{
            background: "linear-gradient(135deg, #FEF3C7 0%, #FFFBEB 100%)",
            border: "1px solid #FDE68A",
            borderRadius: "14px",
            padding: "14px 18px",
            marginBottom: "20px",
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
            flexWrap: "wrap",
            gap: "12px",
          }}
          id="visitor-view-only-banner"
        >
          <div style={{ display: "flex", alignItems: "center", gap: "12px", maxWidth: "760px" }}>
            <span style={{ fontSize: "26px" }}>🌴</span>
            <div>
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <strong style={{ fontSize: "14px", color: "#92400E" }}>
                  Public Visitor Mode — Viewing Only
                </strong>
                <span style={{ fontSize: "10px", fontWeight: 700, background: "#FEF08A", color: "#854D0E", padding: "2px 8px", borderRadius: "9999px" }}>
                  Not Logged In
                </span>
              </div>
              <p style={{ fontSize: "12px", color: "#78350F", margin: "2px 0 0", lineHeight: "1.4" }}>
                You have open access to view all curated events and explore their full culinary menus. To place food pre-orders, reserve portions, or claim digital QR tickets, please sign in.
              </p>
            </div>
          </div>
          <div style={{ display: "flex", gap: "8px" }}>
            <button
              type="button"
              className="primary-button"
              style={{ background: "#4F46E5", borderColor: "#4338CA", fontSize: "12px", padding: "8px 16px", fontWeight: 700 }}
              onClick={onLogin}
              id="btn-visitor-quick-login"
            >
              {signInLabel}
            </button>
          </div>
        </div>
      ) : (
        <div
          style={{
            background: "#EEF2FF",
            border: "1px solid #C7D2FE",
            borderRadius: "14px",
            padding: "12px 18px",
            marginBottom: "20px",
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
            flexWrap: "wrap",
            gap: "10px",
          }}
          id="attendee-authenticated-banner"
        >
          <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
            <span style={{ fontSize: "20px" }}>👤</span>
            <div style={{ fontSize: "13px", color: "#312E81" }}>
              Signed in as <strong>{attendeeProfile?.name || "Marcus Attendee"}</strong> (<code>{attendeeProfile?.email || "attendee@islandevents.app"}</code>) &bull; <span style={{ fontWeight: 600 }}>Attendee Account Active (Ordering & Digital Wallet Enabled)</span>
            </div>
          </div>
          <button
            type="button"
            className="secondary-button"
            style={{ fontSize: "11px", padding: "5px 12px", color: "#4338CA", borderColor: "#C7D2FE", background: "#FFFFFF", borderRadius: "8px" }}
            onClick={onLogout}
            id="btn-attendee-signout"
          >
            Sign Out (Switch to Guest Mode)
          </button>
        </div>
      )}

      {/* Top Utility Bar: Lost Ticket Recovery & Ordering Status */}
      <div className="guest-top-utility">
        <div className="utility-left">
          {eventDetails.isOrderingOpen ? (
            <span className="live-status-pill open">
              <span className="live-dot" /> LIVE ORDERS ACCEPTED
            </span>
          ) : (
            <span className="live-status-pill closed">
              <AlertTriangle size={12} /> ORDERS PAUSED BY ORGANIZER
            </span>
          )}
          <span className="deadline-badge">
            <Clock3 size={12} /> Order Deadline: <strong>{eventDetails.advanceOrderDeadline}</strong>
          </span>
          <span
            className="market-badge"
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "5px",
              background: "#f1f5f9",
              border: "1px solid #e2e8f0",
              padding: "4px 9px",
              borderRadius: "6px",
              fontSize: "11px",
              fontWeight: 600,
              color: "#334155",
            }}
            id="us-market-launch-indicator"
          >
            🇺🇸 US Launch: <strong>USD ($)</strong> · Timezone: <strong>{eventDetails.timezone || "America/New_York"}</strong>
          </span>
        </div>

        <div className="utility-right">
          <button
            type="button"
            className="retrieve-ticket-btn"
            onClick={() => setShowRetrieveBar(!showRetrieveBar)}
          >
            <Search size={13} /> {showRetrieveBar ? "Close Lookup" : "Lost Ticket / SMS? Retrieve Here"}
          </button>
        </div>
      </div>

      {/* Lost Ticket Search Bar */}
      {showRetrieveBar && (
        <form onSubmit={handleRetrieveTicket} className="retrieve-search-box">
          <Search size={16} />
          <input
            type="text"
            placeholder="Enter Order # (e.g. SFF-2026-8492) or Phone Number..."
            value={retrieveQuery}
            onChange={(e) => setRetrieveQuery(e.target.value)}
          />
          <button type="submit" className="retrieve-submit-btn">
            Find My Ticket
          </button>
        </form>
      )}

      {/* Step Indicator */}
      <div className="journey-stepper" aria-label="Customer order steps">
        <button
          type="button"
          className={`step-pill ${activeStep === "menu" ? "active" : ""}`}
          onClick={() => setActiveStep("menu")}
          id="step-menu-btn"
        >
          <span className="step-num">1</span>
          <span>Event & Food Menu</span>
        </button>
        <button
          type="button"
          className={`step-pill ${activeStep === "checkout" ? "active" : ""}`}
          onClick={() => cart.length > 0 && setActiveStep("checkout")}
          disabled={cart.length === 0}
          id="step-checkout-btn"
        >
          <span className="step-num">2</span>
          <span>Checkout & Pay ({cartCount})</span>
        </button>
        <button
          type="button"
          className={`step-pill ${activeStep === "ticket" ? "active" : ""}`}
          disabled={!issuedTicket}
          onClick={() => issuedTicket && setActiveStep("ticket")}
          id="step-ticket-btn"
        >
          <span className="step-num">3</span>
          <span>Digital Ticket Voucher</span>
        </button>
        <button
          type="button"
          className={`step-pill ${activeStep === "orders_wallet" ? "active" : ""}`}
          onClick={() => setActiveStep("orders_wallet")}
          id="step-wallet-btn"
        >
          <Ticket size={14} />
          <span>My Tickets & Orders ({orders.length})</span>
        </button>
        <button
          type="button"
          className={`step-pill ${activeStep === "saved_events" ? "active" : ""}`}
          onClick={() => setActiveStep("saved_events")}
          id="step-saved-btn"
        >
          <Heart size={14} />
          <span>Saved Events & Preferences</span>
        </button>
        <button
          type="button"
          className={`step-pill ${activeStep === "settings" ? "active" : ""}`}
          onClick={() => setActiveStep("settings")}
          id="step-attendee-settings-btn"
        >
          <Settings2 size={14} />
          <span>Account Settings & Privacy (9)</span>
        </button>
      </div>

      {toastMessage && <div className="mini-toast">{toastMessage}</div>}

      {/* STEP 1: DISCOVER & BROWSE MENU */}
      {activeStep === "menu" && (
        <section className="menu-experience-section">
          {/* Public / Owner Preview Status Notice */}
          {visibility.mode === "owner_preview" && (
            <div className={`public-preview-banner ${eventDetails.status || "draft"}`} id="preview-mode-banner">
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <AlertTriangle size={18} />
                <div>
                  <strong>{visibility.notice}</strong>
                  <div style={{ fontSize: "11px", opacity: 0.9 }}>
                    As an authorized organizer, you are viewing this page in Owner Preview Mode. Customers cannot access this URL until you click "Publish Event".
                  </div>
                </div>
              </div>
              <span className="step-badge">Preview Mode</span>
            </div>
          )}

          {eventDetails.status === "cancelled" && (
            <div className="public-preview-banner cancelled" id="cancelled-event-banner">
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <ShieldAlert size={18} />
                <div>
                  <strong>Notice: This event has been marked as CANCELLED by the organizer.</strong>
                  <div style={{ fontSize: "11px" }}>
                    Online orders and ticketing are disabled. Please contact {eventDetails.organizerPhone} for questions or refunds.
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Official Shareable Event Showcase Banner */}
          <div className="event-spotlight-bar fishfry-theme">
            {/* Promotional Flyer Thumbnail Spotlight */}
            {(() => {
              const flyerDisplay = getEventFlyerDisplay(eventDetails);
              return (
                <div
                  className="guest-event-flyer-box"
                  onClick={() =>
                    setLightboxImg({
                      url: flyerDisplay.flyerUrl,
                      caption: flyerDisplay.altText,
                    })
                  }
                  title="Click to view full resolution promotional flyer"
                  id="guest-spotlight-flyer-box"
                >
                  <img
                    src={flyerDisplay.flyerUrl}
                    alt={flyerDisplay.altText}
                    id="guest-spotlight-flyer-img"
                  />
                  <div className="guest-flyer-tag">
                    <Eye size={11} /> {flyerDisplay.isFallback ? "Culinary Showcase" : "View Official Flyer"}
                  </div>
                </div>
              );
            })()}

            <div className="spotlight-content">
              <span className="event-kicker">COMMUNITY FUNDRAISER & EVENT PAGE</span>
              <h2>{eventDetails.title}</h2>
              <p className="event-subtitle">{eventDetails.tagline}</p>

              {/* Event Metadata Chips */}
              <div className="event-meta-chips">
                <span className="meta-chip">
                  <Calendar size={13} /> {eventDetails.dateStr}
                </span>
                <span className="meta-chip">
                  <Clock3 size={13} /> {eventDetails.timeStr}
                </span>
                <span className="meta-chip">
                  <Globe size={13} /> {eventDetails.timezone || "America/Jamaica"}
                </span>
                <span className="meta-chip">
                  <MapPin size={13} /> {eventDetails.venueName} · {eventDetails.venueAddress}
                </span>
                <span className="meta-chip coordinates">{eventDetails.coordinates}</span>
              </div>

              {/* Organizer Contact & Pickup Info */}
              <div className="event-organizer-contact-row">
                <div className="organizer-contact-badge">
                  <span>Organizer:</span>
                  <strong>{eventDetails.organizerName}</strong>
                </div>
                <a
                  href={`tel:${eventDetails.organizerPhone}`}
                  className="contact-pill"
                  title="Call organizer"
                >
                  <Phone size={12} /> {eventDetails.organizerPhone}
                </a>
                <a
                  href={`https://api.whatsapp.com/send?phone=${eventDetails.organizerWhatsApp.replace(/\D/g, "")}&text=Hi,%20I'm%20asking%20about%20${encodeURIComponent(eventDetails.title)}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="contact-pill whatsapp"
                  title="WhatsApp organizer"
                >
                  <MessageCircle size={12} /> WhatsApp
                </a>
              </div>

              {/* Shareable Link & Social Share Actions */}
              <div className="public-share-bar">
                <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                  <Share2 size={13} style={{ color: "#a4e5c2" }} />
                  <span className="public-share-url-text">
                    https://events.islandfest.org/events/{eventDetails.slug || "caribbean-youth-fish-fry-2026"}
                  </span>
                </div>
                <div className="public-share-actions">
                  <button
                    type="button"
                    className="public-share-btn"
                    onClick={() =>
                      copyText(
                        `https://events.islandfest.org/events/${eventDetails.slug || "caribbean-youth-fish-fry-2026"}`,
                        "Shareable event link copied to clipboard!"
                      )
                    }
                    id="guest-copy-link-btn"
                  >
                    <Copy size={11} /> Copy Link
                  </button>
                  <a
                    href={`https://api.whatsapp.com/send?text=${encodeURIComponent(
                      `Check out ${eventDetails.title}! Order food in advance: https://events.islandfest.org/events/${eventDetails.slug || "event"}`
                    )}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="public-share-btn"
                    title="Share via WhatsApp"
                  >
                    <MessageCircle size={11} /> Share
                  </a>
                </div>
              </div>
            </div>

            <div className="currency-selector-badge">
              <span>Event Currency:</span>
              <strong>{settings.currency === "JMD" ? "JMD (J$)" : "USD ($)"}</strong>
            </div>
          </div>

          {/* Pickup Instructions Callout */}
          <div className="pickup-instructions-banner">
            <MapPin size={18} />
            <div>
              <strong>Order Collection & Pickup Instructions:</strong>
              <p>{eventDetails.pickupInstructions}</p>
            </div>
          </div>

          {/* Category Filter Pills */}
          <div className="category-filter-row">
            <span className="filter-label">Menu Category:</span>
            {categories.map((cat) => (
              <button
                key={cat}
                type="button"
                className={`category-pill ${selectedCategory === cat ? "active" : ""}`}
                onClick={() => setSelectedCategory(cat)}
              >
                {cat}
              </button>
            ))}
          </div>

          {/* Food Cards Grid */}
          <div className="menu-cards-grid">
            {filteredMenu.map((item) => {
              const basePrice = getItemBasePrice(item);
              const inCartQty = cart
                .filter((ci) => ci.menuItemId === item.id)
                .reduce((sum, ci) => sum + ci.quantity, 0);

              const isCustomizable = item.pricingType === "customizable";
              const isCustomAmount = item.pricingType === "custom_amount";

              return (
                <article className="food-card" key={item.id}>
                  <div className="food-card-media">
                    <img
                      src={item.imageUrl}
                      alt={item.name}
                      loading="lazy"
                      className="food-photo"
                      onError={(e) => {
                        (e.currentTarget as HTMLImageElement).style.display = "none";
                      }}
                    />
                    <span className="category-badge">{item.category}</span>
                    <span className={`pricing-type-tag ${item.pricingType}`}>
                      {item.pricingType === "fixed"
                        ? "Fixed Price"
                        : item.pricingType === "customizable"
                        ? "Customizable Meal"
                        : "Custom Donation"}
                    </span>
                  </div>

                  <div className="food-card-body">
                    <div className="food-card-header">
                      <h3>{item.name}</h3>
                      <strong className="food-price">
                        {isCustomAmount ? (
                          <span className="custom-amt-text">
                            Min {formatMoney(item.customAmountConfig ? (settings.currency === "JMD" ? item.customAmountConfig.minJMD : item.customAmountConfig.minUSD) : 0, settings.currency)}
                          </span>
                        ) : (
                          <>
                            {isCustomizable && <span className="from-prefix">From </span>}
                            {formatMoney(basePrice, settings.currency)}
                          </>
                        )}
                      </strong>
                    </div>
                    <p className="food-description">{item.description}</p>

                    {/* Features Indicator */}
                    {isCustomizable && (
                      <div className="custom-features-badge">
                        <Sliders size={12} />
                        <span>Choose fish size, side dishes & drinks</span>
                      </div>
                    )}

                    <div className="food-card-footer">
                      {isCustomizable ? (
                        <button
                          type="button"
                          className="add-to-order-btn customize"
                          onClick={() => openCustomization(item)}
                        >
                          <Sliders size={14} /> Customize Meal Options
                        </button>
                      ) : isCustomAmount ? (
                        <button
                          type="button"
                          className="add-to-order-btn donation"
                          onClick={() => openDonationModal(item)}
                        >
                          <Heart size={14} /> Enter Donation Amount
                        </button>
                      ) : inCartQty > 0 ? (
                        <div className="qty-control-row">
                          <button
                            type="button"
                            className="qty-btn"
                            onClick={() => {
                              const idx = cart.findIndex((ci) => ci.menuItemId === item.id);
                              if (idx >= 0) updateQuantity(idx, -1);
                            }}
                            aria-label="Decrease quantity"
                          >
                            <Minus size={14} />
                          </button>
                          <span className="qty-number">{inCartQty} in order</span>
                          <button
                            type="button"
                            className="qty-btn"
                            onClick={() => handleAddFixedItem(item)}
                            aria-label="Increase quantity"
                          >
                            <Plus size={14} />
                          </button>
                        </div>
                      ) : (
                        <button
                          type="button"
                          className="add-to-order-btn"
                          onClick={() => handleAddFixedItem(item)}
                        >
                          <ShoppingBag size={14} /> Add to Order
                        </button>
                      )}
                    </div>
                  </div>
                </article>
              );
            })}
          </div>

          {/* Floating Sticky Cart Bar */}
          {cart.length > 0 && (
            <div className="floating-cart-bar">
              <div className="floating-cart-info">
                <span className="cart-badge-icon">
                  <ShoppingBag size={18} />
                </span>
                <div>
                  <strong>
                    {cartCount} {cartCount === 1 ? "item" : "items"} selected
                  </strong>
                  <span>
                    Subtotal: {formatMoney(cartSubtotal, settings.currency)}
                    {packagingFee > 0 && ` + Fee: ${formatMoney(packagingFee, settings.currency)}`}
                  </span>
                </div>
              </div>
              <button
                type="button"
                className="cart-checkout-btn"
                onClick={() => setActiveStep("checkout")}
              >
                Review & Checkout ({formatMoney(finalTotalAmount, settings.currency)}) <ArrowRight size={16} />
              </button>
            </div>
          )}
        </section>
      )}

      {/* STEP 2: CHECKOUT & PAYMENT */}
      {activeStep === "checkout" && (
        <section className="checkout-experience-section">
          <div className="checkout-split-layout">
            {/* Left: Frictionless Guest Details & Payment Method */}
            <div className="panel checkout-left-panel">
              <div className="panel-heading">
                <div>
                  <span className="section-kicker">STEP 2 OF 3</span>
                  <h2>Guest Information & Direct Payment</h2>
                  <p>No account required. Enter your details to receive your digital ticket voucher.</p>
                </div>
                <button
                  type="button"
                  className="text-button"
                  onClick={() => setActiveStep("menu")}
                >
                  ← Edit Food Items
                </button>
              </div>

              <form onSubmit={handleCheckoutSubmit} className="checkout-form">
                <div className="form-group">
                  <label className="field-label" htmlFor="cust-name">
                    Full Name (Reservation Name) *
                  </label>
                  <div className="input-wrap">
                    <UserRound size={16} />
                    <input
                      id="cust-name"
                      type="text"
                      required
                      placeholder="e.g. Damian Marley"
                      value={customerName}
                      onChange={(e) => setCustomerName(e.target.value)}
                    />
                  </div>
                </div>

                <div className="form-group">
                  <label className="field-label" htmlFor="cust-phone">
                    Mobile Phone / WhatsApp Number * (For Digital Ticket SMS)
                  </label>
                  <div className="input-wrap">
                    <Phone size={16} />
                    <input
                      id="cust-phone"
                      type="tel"
                      required
                      placeholder="+1 (876) 555-0142"
                      value={customerPhone}
                      onChange={(e) => setCustomerPhone(e.target.value)}
                    />
                  </div>
                  <span className="field-hint">
                    Used to retrieve your order if you lose your link. Never shared with third parties.
                  </span>
                </div>

                <div className="form-group">
                  <label className="field-label" htmlFor="cust-email">
                    Email Address (Optional email receipt)
                  </label>
                  <div className="input-wrap">
                    <Mail size={16} />
                    <input
                      id="cust-email"
                      type="email"
                      placeholder="e.g. guest@example.com"
                      value={customerEmail}
                      onChange={(e) => setCustomerEmail(e.target.value)}
                    />
                  </div>
                </div>

                <div className="payment-method-block">
                  <div className="payment-title">
                    <div>
                      <label className="field-label">Select Payment Method *</label>
                      <span>
                        Currency: {settings.currency === "JMD" ? "Jamaican Dollar (JMD)" : "US Dollar (USD)"}
                      </span>
                    </div>
                    <span className="currency-badge">{settings.currency}</span>
                  </div>

                  {availableMethods.length > 0 ? (
                    <div className="method-options">
                      {availableMethods.map((m) => {
                        const info = METHOD_INFO[m];
                        const Icon = info.icon;
                        const isSelected = selectedMethod === m;
                        return (
                          <button
                            key={m}
                            type="button"
                            className={`method-option ${isSelected ? "selected" : ""}`}
                            onClick={() => setSelectedMethod(m)}
                          >
                            <span className="method-icon">
                              <Icon size={17} />
                            </span>
                            <span className="method-copy">
                              <strong>{info.name}</strong>
                              <span>{info.badge}</span>
                            </span>
                            <span className={`radio ${isSelected ? "checked" : ""}`}>
                              {isSelected && <Check size={11} />}
                            </span>
                          </button>
                        );
                      })}
                    </div>
                  ) : (
                    <div className="empty-methods">
                      <AlertCircle size={18} />
                      <div>
                        <strong>No eligible payment methods configured</strong>
                        <span>The organizer has not yet enabled payment methods for this currency.</span>
                      </div>
                      <button
                        type="button"
                        className="preset-btn"
                        onClick={onOpenSettings}
                        style={{ marginLeft: "auto" }}
                      >
                        Configure Settings
                      </button>
                    </div>
                  )}

                  {/* Transfer Details preview & Reference submission */}
                  {selectedMethod && settings.methods[selectedMethod] && (
                    <div className="transfer-instructions">
                      <div className="instructions-title">
                        <span>
                          <ArrowDownLeft size={15} /> PAYMENT & VERIFICATION DETAILS
                        </span>
                        <span>{METHOD_INFO[selectedMethod].name}</span>
                      </div>

                      {selectedMethod === "cash" ? (
                        <div className="cash-instructions-card">
                          <p>
                            Pay cash in person at <strong>Kingston Youth Center</strong> or upon arrival at <strong>Food Collection Tent B</strong>.
                          </p>
                          <div className="recipient-detail">
                            <pre>{settings.methods[selectedMethod].details}</pre>
                          </div>
                          <p className="instruction-footnote">
                            <Info size={12} /> Your order will be placed as <strong>Awaiting Payment</strong>, reserving your meal portions. Staff will issue your official cash receipt and activate your voucher upon payment.
                          </p>
                        </div>
                      ) : selectedMethod === "card" ? (
                        <div className="card-instructions-card">
                          <p>
                            Online card payments are cleared instantly. Total to be charged:{" "}
                            <strong>{formatMoney(finalTotalAmount, settings.currency)}</strong>.
                          </p>
                          <div className="recipient-detail">
                            <pre>{settings.methods[selectedMethod].details}</pre>
                          </div>
                        </div>
                      ) : (
                        <div>
                          <p>
                            Transfer exactly{" "}
                            <strong>{formatMoney(finalTotalAmount, settings.currency)}</strong> to the
                            organizer account:
                          </p>
                          <div className="recipient-detail">
                            <pre>{settings.methods[selectedMethod].details}</pre>
                            <button
                              type="button"
                              onClick={() => copyText(settings.methods[selectedMethod].details)}
                              title="Copy details"
                            >
                              <Copy size={15} />
                            </button>
                          </div>

                          {/* Reference Number & Sender Name Inputs */}
                          <div className="payment-proof-inputs">
                            <div className="input-group">
                              <label htmlFor="checkout-ref-num">
                                Bank / App Reference Number (Optional)
                              </label>
                              <input
                                id="checkout-ref-num"
                                type="text"
                                placeholder="e.g. NCB-109284 or Zelle Conf #..."
                                value={paymentReference}
                                onChange={(e) => setPaymentReference(e.target.value)}
                              />
                            </div>
                            <div className="input-group">
                              <label htmlFor="checkout-sender-name">
                                Name on Sender's Account (Optional)
                              </label>
                              <input
                                id="checkout-sender-name"
                                type="text"
                                placeholder="e.g. Damian Marley"
                                value={senderName}
                                onChange={(e) => setSenderName(e.target.value)}
                              />
                            </div>
                          </div>

                          <p className="instruction-footnote">
                            <Info size={12} /> <strong>Anti-fraud notice:</strong> Tickets remain in <strong>Payment Submitted / Under Verification</strong> until the organizer cross-checks the bank or payment account. Submitting a screenshot is not immediate verification.
                          </p>
                        </div>
                      )}
                    </div>
                  )}
                </div>

                <button
                  type="submit"
                  className="primary-button"
                  disabled={isSubmittingOrder || !customerName.trim() || !customerPhone.trim() || !selectedMethod}
                >
                  {isSubmittingOrder
                    ? "Submitting order…"
                    : <>Submit Order & Issue Digital Ticket ({formatMoney(finalTotalAmount, settings.currency)}) <ArrowRight size={17} /></>}
                </button>

                <div className="form-safety-notice">
                  <ShieldAlert size={14} />
                  <span>
                    <strong>Strict Verification Policy:</strong> In accordance with organizer safety rules,
                    tickets remain <strong>Pending Verification</strong> until the organizer cross-references
                    the bank/app statement.
                  </span>
                </div>
              </form>
            </div>

            {/* Right: Transparent Order Summary & Fee Calculation */}
            <aside className="checkout-summary-sidebar">
              <div className="panel order-summary-panel">
                <div className="panel-heading">
                  <div>
                    <span className="section-kicker">ORDER BREAKDOWN</span>
                    <h3>Items Selected</h3>
                  </div>
                  <span className="step-badge">{cartCount} items</span>
                </div>

                <div className="cart-items-list">
                  {cart.map((ci, idx) => (
                    <div className="cart-summary-item" key={`${ci.menuItemId}-${idx}`}>
                      <img
                        src={ci.imageUrl}
                        alt={ci.name}
                        className="cart-item-thumb"
                      />
                      <div className="cart-item-details">
                        <strong>{ci.name}</strong>
                        {ci.selectedOptionsSummary.length > 0 && (
                          <div className="selected-options-list">
                            {ci.selectedOptionsSummary.map((opt, oIdx) => (
                              <span className="addon-tag" key={oIdx}>
                                {opt}
                              </span>
                            ))}
                          </div>
                        )}
                        {ci.specialInstructions && (
                          <span className="instructions-snippet">
                            Note: {ci.specialInstructions}
                          </span>
                        )}
                        <span className="cart-item-calc">
                          {ci.quantity} × {formatMoney(ci.finalItemPrice, settings.currency)}
                        </span>
                      </div>
                      <div className="cart-item-right">
                        <strong>
                          {formatMoney(ci.finalItemPrice * ci.quantity, settings.currency)}
                        </strong>
                        <div className="mini-qty-row">
                          <button
                            type="button"
                            onClick={() => updateQuantity(idx, -1)}
                            aria-label="Decrease"
                          >
                            <Minus size={12} />
                          </button>
                          <span>{ci.quantity}</span>
                          <button
                            type="button"
                            onClick={() => updateQuantity(idx, 1)}
                            aria-label="Increase"
                          >
                            <Plus size={12} />
                          </button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>

                <div className="panel-divider" />

                {/* Calculation Table */}
                <div className="calculation-breakdown">
                  <div className="calc-row">
                    <span>Items Subtotal:</span>
                    <span>{formatMoney(cartSubtotal, settings.currency)}</span>
                  </div>
                  {packagingFee > 0 && (
                    <div className="calc-row">
                      <span>Packaging & Eco Fee:</span>
                      <span>{formatMoney(packagingFee, settings.currency)}</span>
                    </div>
                  )}
                  <div className="calc-row total">
                    <strong>Final Amount Due:</strong>
                    <strong className="final-total">
                      {formatMoney(finalTotalAmount, settings.currency)}
                    </strong>
                  </div>
                </div>

                <div className="event-reminder-box">
                  <MapPin size={15} />
                  <div>
                    <strong>{eventDetails.venueName}</strong>
                    <p>{eventDetails.dateStr} · {eventDetails.pickupInstructions}</p>
                  </div>
                </div>
              </div>
            </aside>
          </div>
        </section>
      )}

      {/* STEP 3: DIGITAL TICKET & NOTIFICATION CONFIRMATION */}
      {activeStep === "ticket" && issuedTicket && (
        <section className="ticket-experience-section">
          <div className="ticket-success-banner fishfry">
            <CheckCircle2 size={24} />
            <div>
              <h2>Order Placed & Digital Ticket Generated!</h2>
              <p>
                Order #{issuedTicket.orderNumber} · Reference <code>{issuedTicket.ticketCode}</code>
              </p>
            </div>
            <div className="banner-actions">
              <button
                type="button"
                className="sms-preview-btn"
                onClick={() => setShowSmsModal(true)}
              >
                <MessageSquare size={15} /> View SMS Voucher Preview
              </button>
            </div>
          </div>

          {/* Secure Direct Ticket URL Bar */}
          <div className="secure-link-bar">
            <div className="secure-link-left">
              <Sparkles size={16} />
              <div>
                <strong>Bookmark or Share This Secure Ticket Link:</strong>
                <p>Lost your SMS? You can always retrieve your order anytime using this permanent link.</p>
              </div>
            </div>
            <div className="secure-link-action">
              <input readOnly value={shareableTicketUrl} className="secure-link-input" />
              <button
                type="button"
                className="preset-btn"
                onClick={() => copyText(shareableTicketUrl, "Ticket link copied to clipboard!")}
              >
                <Copy size={13} /> Copy Ticket Link
              </button>
            </div>
          </div>

          {/* Consolidated Digital Ticket Pass Card */}
          <div className="ticket-card-container">
            <div className="digital-ticket-card">
              {/* Ticket Header */}
              <div className="ticket-card-header">
                <div className="ticket-brand">
                  <Ticket size={20} />
                  <span>{eventDetails.title}</span>
                </div>
                <span
                  className={`ticket-status-pill ${
                    issuedTicket.status === "paid"
                      ? "paid"
                      : issuedTicket.status === "partially_paid"
                      ? "partially-paid"
                      : issuedTicket.status === "rejected"
                      ? "rejected"
                      : issuedTicket.status === "refunded"
                      ? "refunded"
                      : issuedTicket.status === "awaiting_payment"
                      ? "awaiting-payment"
                      : "pending"
                  }`}
                >
                  {issuedTicket.status === "paid" ? (
                    <>
                      <CheckCircle2 size={13} /> VERIFIED PAID
                    </>
                  ) : issuedTicket.status === "partially_paid" ? (
                    <>
                      <DollarSign size={13} /> PARTIALLY PAID (
                      {formatMoney(issuedTicket.amountPaid || 0, issuedTicket.currency)} /{" "}
                      {formatMoney(issuedTicket.totalAmount, issuedTicket.currency)})
                    </>
                  ) : issuedTicket.status === "awaiting_payment" ? (
                    <>
                      <Banknote size={13} /> AWAITING PAYMENT (CASH / ADVANCE)
                    </>
                  ) : issuedTicket.status === "rejected" ? (
                    <>
                      <AlertTriangle size={13} /> NOT VERIFIED / REJECTED
                    </>
                  ) : issuedTicket.status === "refunded" ? (
                    <>
                      <RefreshCw size={13} /> REFUNDED
                    </>
                  ) : (
                    <>
                      <Clock3 size={13} /> PAYMENT SUBMITTED (UNDER VERIFICATION)
                    </>
                  )}
                </span>
              </div>

              {/* Event Main Info */}
              <div className="ticket-card-event">
                <span className="ticket-label">OFFICIAL EVENT ADMISSION & FOOD VOUCHER</span>
                <h3>{eventDetails.title}</h3>
                <p className="ticket-venue">
                  <MapPin size={14} /> {eventDetails.venueName} · {eventDetails.venueAddress}
                </p>
                <p className="ticket-date">
                  <Clock3 size={14} /> {eventDetails.dateStr} ({eventDetails.timeStr})
                </p>
                <p className="ticket-pickup-hint">
                  <strong>Pickup Instructions:</strong> {eventDetails.pickupInstructions}
                </p>

                {/* Status Guidance Banner */}
                {issuedTicket.status === "paid" ? (
                  <div className="ticket-status-guidance paid">
                    <CheckCircle2 size={15} />
                    <span>Payment verified in full. Present this QR code at Collection Tent B to receive your meal portions.</span>
                  </div>
                ) : issuedTicket.status === "partially_paid" ? (
                  <div className="ticket-status-guidance partial">
                    <DollarSign size={15} />
                    <span>
                      Deposit of {formatMoney(issuedTicket.amountPaid || 0, issuedTicket.currency)} verified.
                      Remaining balance of{" "}
                      <strong>
                        {formatMoney(
                          issuedTicket.totalAmount - (issuedTicket.amountPaid || 0),
                          issuedTicket.currency
                        )}
                      </strong>{" "}
                      payable in cash upon collection.
                    </span>
                  </div>
                ) : issuedTicket.status === "awaiting_payment" ? (
                  <div className="ticket-status-guidance awaiting">
                    <Banknote size={15} />
                    <span>
                      Meals reserved! Please pay{" "}
                      <strong>{formatMoney(issuedTicket.totalAmount, issuedTicket.currency)}</strong> in cash to authorized staff. Your digital ticket will be confirmed on the spot.
                    </span>
                  </div>
                ) : issuedTicket.status === "rejected" ? (
                  <div className="ticket-status-guidance rejected">
                    <AlertTriangle size={15} />
                    <span>
                      Payment could not be verified on the bank statement.{" "}
                      {issuedTicket.rejectionReason ? `Reason: ${issuedTicket.rejectionReason}. ` : ""}
                      Please contact {eventDetails.organizerPhone} or WhatsApp the organizer.
                    </span>
                  </div>
                ) : (
                  <div className="ticket-status-guidance pending">
                    <Clock3 size={15} />
                    <span>
                      Your transfer report has been logged. The organizer is cross-referencing the account statement.
                      {issuedTicket.paymentReference ? ` Ref: ${issuedTicket.paymentReference}.` : ""}
                    </span>
                  </div>
                )}
              </div>

              {/* QR Code and Code Row */}
              <div className="ticket-qr-section">
                <div className="qr-box">
                  <svg
                    viewBox="0 0 120 120"
                    width="120"
                    height="120"
                    className="qr-svg"
                    role="img"
                    aria-label="Ticket QR code"
                  >
                    <rect width="120" height="120" fill="white" />
                    {/* Top-Left Finder */}
                    <rect x="10" y="10" width="30" height="30" fill="#1e2820" />
                    <rect x="15" y="15" width="20" height="20" fill="white" />
                    <rect x="20" y="20" width="10" height="10" fill="#1e2820" />
                    {/* Top-Right Finder */}
                    <rect x="80" y="10" width="30" height="30" fill="#1e2820" />
                    <rect x="85" y="15" width="20" height="20" fill="white" />
                    <rect x="90" y="20" width="10" height="10" fill="#1e2820" />
                    {/* Bottom-Left Finder */}
                    <rect x="10" y="80" width="30" height="30" fill="#1e2820" />
                    <rect x="15" y="85" width="20" height="20" fill="white" />
                    <rect x="20" y="90" width="10" height="10" fill="#1e2820" />
                    {/* Timing & Data Patterns */}
                    <rect x="45" y="15" width="5" height="5" fill="#1e2820" />
                    <rect x="55" y="15" width="5" height="5" fill="#1e2820" />
                    <rect x="65" y="15" width="5" height="5" fill="#1e2820" />
                    <rect x="45" y="25" width="15" height="5" fill="#1e2820" />
                    <rect x="45" y="35" width="5" height="10" fill="#1e2820" />
                    <rect x="60" y="40" width="10" height="10" fill="#1e2820" />
                    <rect x="25" y="45" width="10" height="10" fill="#1e2820" />
                    <rect x="45" y="55" width="30" height="10" fill="#1e2820" />
                    <rect x="80" y="50" width="10" height="15" fill="#1e2820" />
                    <rect x="95" y="60" width="15" height="5" fill="#1e2820" />
                    <rect x="50" y="75" width="20" height="10" fill="#1e2820" />
                    <rect x="80" y="80" width="10" height="10" fill="#1e2820" />
                    <rect x="95" y="95" width="15" height="15" fill="#1e2820" />
                    <rect x="45" y="95" width="10" height="15" fill="#1e2820" />
                    <rect x="65" y="95" width="10" height="10" fill="#1e2820" />
                  </svg>
                </div>

                <div className="ticket-meta-block">
                  <div>
                    <span className="ticket-label">TICKET NUMBER</span>
                    <strong className="ticket-code-display">{issuedTicket.ticketCode}</strong>
                  </div>
                  <div>
                    <span className="ticket-label">CUSTOMER NAME</span>
                    <p>{issuedTicket.customerName}</p>
                  </div>
                  <div>
                    <span className="ticket-label">CUSTOMER PHONE</span>
                    <p>{maskedPhone(issuedTicket.customerPhone)}</p>
                  </div>
                  <div>
                    <span className="ticket-label">ORDER ID</span>
                    <p>{issuedTicket.orderNumber}</p>
                  </div>
                </div>
              </div>

              {/* Perforation Divider */}
              <div className="ticket-perforation">
                <span className="perforation-notch left" />
                <div className="perforation-line" />
                <span className="perforation-notch right" />
              </div>

              {/* Itemized Meal & Beverage Vouchers */}
              <div className="ticket-items-section">
                <span className="ticket-label">CONSOLIDATED MEAL & DRINK ITEMS (EVENT DAY REDEMPTION)</span>
                <div className="ticket-vouchers-list">
                  {issuedTicket.items.map((it, idx) => (
                    <div className="ticket-voucher-item" key={idx}>
                      <div className="voucher-left">
                        <Utensils size={15} />
                        <div>
                          <strong>
                            {it.quantity}× {it.name}
                          </strong>
                          {it.selectedOptionsSummary && it.selectedOptionsSummary.length > 0 && (
                            <div className="selected-options-list">
                              {it.selectedOptionsSummary.map((opt, oIdx) => (
                                <span className="addon-tag" key={oIdx}>
                                  {opt}
                                </span>
                              ))}
                            </div>
                          )}
                          {it.specialInstructions && (
                            <span className="instructions-snippet">
                              Note: {it.specialInstructions}
                            </span>
                          )}
                        </div>
                      </div>
                      <span
                        className={`handover-badge ${
                          it.handoverStatus === "collected" ? "collected" : "pending"
                        }`}
                      >
                        {it.handoverStatus === "collected" ? (
                          <>
                            <Check size={12} /> Handed Over
                          </>
                        ) : (
                          "Ready for Collection"
                        )}
                      </span>
                    </div>
                  ))}
                </div>
              </div>

              {/* Calculation Summary in Ticket */}
              <div className="ticket-fee-summary-row">
                <span>Subtotal: {formatMoney(issuedTicket.subtotal, issuedTicket.currency)}</span>
                {issuedTicket.packagingFee > 0 && (
                  <span>Fee: {formatMoney(issuedTicket.packagingFee, issuedTicket.currency)}</span>
                )}
                <strong>
                  Total: {formatMoney(issuedTicket.totalAmount, issuedTicket.currency)}
                </strong>
              </div>

              {/* Ticket Footer Action */}
              <div className="ticket-card-footer">
                <div className="ticket-price-paid">
                  <span>Payment Method:</span>
                  <strong>{METHOD_INFO[issuedTicket.method].name}</strong>
                </div>
                <div className="ticket-actions-group">
                  <button
                    type="button"
                    className="ticket-action-btn"
                    onClick={() => copyText(shareableTicketUrl, "Ticket link copied!")}
                  >
                    <Share2 size={14} /> Share Link
                  </button>
                  <button
                    type="button"
                    className="ticket-action-btn primary"
                    onClick={() => window.print()}
                  >
                    <Download size={14} /> Print / Save Voucher
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* MULTI-CHANNEL RECEIPT ALTERNATIVES & NOTIFICATION TRACKING */}
          <div className="ticket-receipt-delivery-card">
            <div className="receipt-delivery-header">
              <div className="receipt-header-left">
                <span className="notif-badge-pill">MULTI-CHANNEL RECEIPT ALTERNATIVES</span>
                <h3>Email Receipt & Shareable Ticket Access</h3>
                <p>
                  Cannot receive SMS due to roaming or carrier delays? Access your digital ticket
                  through email or our permanent shareable web link.
                </p>
              </div>
              <div className="decoupled-guarantee-pill">
                <ShieldAlert size={14} />
                <span>SMS Failure Never Reverses Payment</span>
              </div>
            </div>

            <div className="receipt-options-grid">
              {/* Option A: Send Email Receipt */}
              <div className="receipt-option-box">
                <div className="receipt-option-title">
                  <Mail size={16} className="email-icon" />
                  <strong>Send Official Email Receipt</strong>
                </div>
                <p>
                  Receive an itemized proof-of-order and pickup voucher sent directly to your inbox.
                </p>
                <div className="receipt-input-action">
                  <input
                    type="email"
                    placeholder="Enter your email address..."
                    value={receiptEmailInput}
                    onChange={(e) => setReceiptEmailInput(e.target.value)}
                    className="receipt-email-input"
                  />
                  <button
                    type="button"
                    className="send-receipt-btn"
                    disabled={!receiptEmailInput.includes("@")}
                    onClick={() => {
                      if (onSendCustomNotification && issuedTicket) {
                        onSendCustomNotification({
                          orderId: issuedTicket.id,
                          orderNumber: issuedTicket.orderNumber,
                          customerName: issuedTicket.customerName,
                          recipient: receiptEmailInput.trim(),
                          channel: "email",
                          trigger: "payment_verified",
                          title: `Official Receipt — ${eventDetails.title}`,
                          message: `Island Events: Official Receipt for Order ${issuedTicket.orderNumber}. Amount: ${formatMoney(issuedTicket.totalAmount, issuedTicket.currency)}. Present Ticket ${issuedTicket.ticketCode} on ${eventDetails.dateStr} at ${eventDetails.venueName}.`,
                          status: "delivered",
                          provider: "Island Mail",
                          deliveredAt: new Date().toISOString(),
                        });
                        setReceiptEmailSent(true);
                        copyText(
                          receiptEmailInput,
                          `Official receipt sent to ${receiptEmailInput.trim()}!`
                        );
                      }
                    }}
                  >
                    <Mail size={13} /> {receiptEmailSent ? "Receipt Sent!" : "Send Receipt"}
                  </button>
                </div>
                {receiptEmailSent && (
                  <span className="receipt-sent-note">
                    <CheckCircle2 size={12} /> Email receipt dispatched via secure gateway.
                  </span>
                )}
              </div>

              {/* Option B: Shareable Ticket Link & WhatsApp */}
              <div className="receipt-option-box">
                <div className="receipt-option-title">
                  <Share2 size={16} className="share-icon" />
                  <strong>Permanent Shareable Web Link</strong>
                </div>
                <p>
                  Bookmark or share this live ticket URL. Works on any browser without needing SMS.
                </p>
                <div className="receipt-input-action">
                  <input
                    readOnly
                    value={shareableTicketUrl}
                    className="receipt-email-input read-only"
                  />
                  <button
                    type="button"
                    className="copy-ticket-url-btn"
                    onClick={() => copyText(shareableTicketUrl, "Ticket URL copied to clipboard!")}
                  >
                    <Copy size={13} /> Copy Link
                  </button>
                </div>
                <div className="whatsapp-ticket-share">
                  <a
                    href={`https://api.whatsapp.com/send?text=${encodeURIComponent(
                      `Here is my official ticket and food voucher for ${eventDetails.title}: ${shareableTicketUrl}`
                    )}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="whatsapp-ticket-link"
                  >
                    <MessageCircle size={14} /> Send Voucher via WhatsApp
                  </a>
                </div>
              </div>
            </div>

            {/* Notification Delivery Timeline for this order */}
            {(() => {
              const orderNotifs = notificationLogs.filter(
                (n) => n.orderId === issuedTicket.id || n.orderNumber === issuedTicket.orderNumber
              );
              if (orderNotifs.length === 0) return null;

              return (
                <div className="ticket-notifications-timeline">
                  <div className="timeline-heading">
                    <MessageSquare size={15} />
                    <strong>Order Notification & SMS Delivery Logs ({orderNotifs.length})</strong>
                  </div>
                  <div className="ticket-notif-items">
                    {orderNotifs.map((n) => (
                      <div className="ticket-notif-entry" key={n.id}>
                        <div className="entry-left">
                          <span className={`channel-badge ${n.channel}`}>
                            {n.channel.toUpperCase()}
                          </span>
                          <div>
                            <strong>{n.title}</strong>
                            <p>{n.message}</p>
                            <span className="entry-time">
                              {new Date(n.sentAt || n.createdAt || Date.now()).toLocaleTimeString([], {
                                hour: "2-digit",
                                minute: "2-digit",
                              })}{" "}
                              · Gateway: {n.provider}
                            </span>
                          </div>
                        </div>
                        <div className="entry-right">
                          <span className={`delivery-status-tag ${n.status}`}>
                            {n.status === "delivered" && <CheckCircle2 size={12} />}
                            {n.status === "failed" && <AlertTriangle size={12} />}
                            {n.status === "queued" && <Clock3 size={12} />}
                            {n.status === "sent" && <Clock3 size={12} />}
                            {n.status.toUpperCase()}
                          </span>
                          {n.status === "failed" && (
                            <span className="notif-fail-guarantee">
                              Payment Valid & Preserved
                            </span>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              );
            })()}
          </div>

          <div className="ticket-back-row">
            <button
              type="button"
              className="text-button"
              onClick={() => {
                setActiveStep("menu");
                setIssuedTicket(null);
              }}
            >
              ← Order More Meals
            </button>
          </div>
        </section>
      )}

      {/* STEP 4: MY DIGITAL TICKETS & ORDER HISTORY (ATTENDEE WALLET) */}
      {activeStep === "orders_wallet" && (
        <section className="attendee-wallet-section" id="attendee-wallet-view">
          <div className="attendee-wallet-header">
            <div className="wallet-title-wrap">
              <div className="attendee-badge">
                <UserRound size={14} /> ATTENDEE ACCOUNT & DIGITAL WALLET
              </div>
              <h2>My Upcoming & Past Orders</h2>
              <p>
                Track verified payments, view live kitchen meal collection status, download receipts, and access digital tickets.
              </p>
            </div>
            <div className="wallet-guest-badge">
              <Lock size={13} />
              <span>Guest Checkout Supported · Session Active</span>
            </div>
          </div>

          <div className="attendee-orders-list">
            {orders.length === 0 ? (
              <div className="empty-wallet-card">
                <Ticket size={32} />
                <h3>No Orders Found in This Session</h3>
                <p>Browse our authentic event menu and place an order to receive your digital ticket.</p>
                <button
                  type="button"
                  className="primary-button"
                  onClick={() => setActiveStep("menu")}
                >
                  Browse Event Menu
                </button>
              </div>
            ) : (
              orders.map((ord) => {
                const totalPortions = ord.items.reduce((s, i) => s + i.quantity, 0);
                const collectedPortions = ord.items.reduce((s, i) => s + (i.collectedQuantity || 0), 0);
                const isFullyCollected = ord.allHandedOver || (totalPortions > 0 && collectedPortions >= totalPortions);

                return (
                  <div className="attendee-order-card" key={ord.id}>
                    <div className="order-card-top">
                      <div>
                        <div className="order-code-row">
                          <span className="order-num-tag">{ord.orderNumber}</span>
                          <span className={`status-pill ${ord.status}`}>
                            {ord.status === "paid"
                              ? "VERIFIED PAID"
                              : ord.status === "payment_submitted"
                              ? "PAYMENT SUBMITTED"
                              : ord.status === "awaiting_payment"
                              ? "AWAITING PAYMENT"
                              : ord.status.toUpperCase()}
                          </span>
                        </div>
                        <h3 className="order-event-title">{eventDetails.title}</h3>
                        <p className="order-venue-sub">
                          <MapPin size={12} /> {eventDetails.venueName} · <Clock3 size={12} /> {eventDetails.dateStr}
                        </p>
                      </div>

                      <div className="order-card-price">
                        <strong>{formatMoney(ord.totalAmount, ord.currency)}</strong>
                        <span className="method-label">Via {ord.method.toUpperCase()}</span>
                      </div>
                    </div>

                    {/* Portions Collection Progress Indicator */}
                    <div className="handover-progress-box">
                      <div className="progress-label-row">
                        <span>
                          <strong>Meal Handover Status:</strong>{" "}
                          {isFullyCollected
                            ? "All portions collected!"
                            : collectedPortions > 0
                            ? `Partially Collected (${collectedPortions} of ${totalPortions} portions)`
                            : "Ready for Pickup at Collection Tent"}
                        </span>
                        <span className="portion-counts">
                          {collectedPortions}/{totalPortions} Portions
                        </span>
                      </div>
                      <div className="progress-bar-track">
                        <div
                          className={`progress-bar-fill ${isFullyCollected ? "complete" : ""}`}
                          style={{
                            width: `${totalPortions > 0 ? (collectedPortions / totalPortions) * 100 : 0}%`,
                          }}
                        />
                      </div>
                    </div>

                    {/* Itemized summary */}
                    <div className="order-items-compact-list">
                      {ord.items.map((it, idx) => (
                        <div className="order-item-compact" key={idx}>
                          <span>
                            <strong>{it.quantity}x</strong> {it.name}
                            {it.selectedOptionsSummary && it.selectedOptionsSummary.length > 0 && (
                              <span className="item-options-text"> ({it.selectedOptionsSummary.join(", ")})</span>
                            )}
                          </span>
                          <span className="item-collection-status">
                            {it.collectedQuantity && it.collectedQuantity > 0
                              ? `Collected: ${it.collectedQuantity}/${it.quantity}`
                              : "Pending collection"}
                          </span>
                        </div>
                      ))}
                    </div>

                    {/* Order Actions */}
                    <div className="order-card-actions">
                      <button
                        type="button"
                        className="primary-button view-ticket-btn"
                        onClick={() => {
                          setIssuedTicket(ord);
                          setActiveStep("ticket");
                        }}
                      >
                        <Ticket size={14} /> Open Digital Ticket QR Pass
                      </button>

                      <button
                        type="button"
                        className="secondary-button"
                        onClick={() => {
                          showToast(`Digital Receipt downloaded for ${ord.orderNumber}.`);
                        }}
                      >
                        <Download size={14} /> Download Receipt
                      </button>

                      {ord.status === "awaiting_payment" && (
                        <button
                          type="button"
                          className="secondary-button btn-transfer-ref"
                          onClick={() => {
                            setSubmittingRefOrderId(ord.id);
                            setTransferRefInput("");
                          }}
                        >
                          <Send size={14} /> Submit Bank/Zelle Reference
                        </button>
                      )}

                      <button
                        type="button"
                        className="text-button btn-dispute-trigger"
                        onClick={() => {
                          setDisputeModalOrder(ord);
                          setDisputeReasonInput("");
                        }}
                      >
                        Need Help / Request Dispute
                      </button>
                    </div>

                    {/* Inline Transfer Reference Submission Form */}
                    {submittingRefOrderId === ord.id && (
                      <div className="transfer-ref-box">
                        <label htmlFor="ref-input">
                          Submit Bank Transaction # or Zelle Confirmation:
                        </label>
                        <div className="ref-input-row">
                          <input
                            id="ref-input"
                            type="text"
                            placeholder="e.g. ZEL-994827 or Scotia Ref #88219"
                            value={transferRefInput}
                            onChange={(e) => setTransferRefInput(e.target.value)}
                            className="form-input"
                          />
                          <button
                            type="button"
                            className="primary-button"
                            onClick={() => {
                              if (!transferRefInput.trim()) return;
                              ord.paymentReference = transferRefInput.trim();
                              ord.status = "payment_submitted";
                              setSubmittingRefOrderId(null);
                              showToast(`Transfer reference "${transferRefInput}" submitted for organizer verification!`);
                            }}
                          >
                            Submit Reference
                          </button>
                          <button
                            type="button"
                            className="secondary-button"
                            onClick={() => setSubmittingRefOrderId(null)}
                          >
                            Cancel
                          </button>
                        </div>
                      </div>
                    )}
                  </div>
                );
              })
            )}
          </div>
        </section>
      )}

      {/* STEP 5: SAVED EVENTS & NOTIFICATION PREFERENCES */}
      {activeStep === "saved_events" && (
        <section className="saved-events-section" id="attendee-saved-events-view">
          <div className="saved-events-header">
            <div>
              <div className="attendee-badge">
                <Heart size={14} /> DISCOVER & SAVED EVENTS
              </div>
              <h2>Saved Events & Community Fundraisers</h2>
              <p>Keep track of your favorite upcoming fish frys, banquets, and community fundraisers.</p>
            </div>
          </div>

          <div className="saved-cards-grid">
            <div className="saved-event-card">
              <div className="saved-event-img">
                <img src={eventDetails.flyerUrl || "/images/escovitch_fish.jpg"} alt={eventDetails.title} />
                <span className="saved-tag">⭐ SAVED</span>
              </div>
              <div className="saved-event-body">
                <h3>{eventDetails.title}</h3>
                <p className="saved-desc">{eventDetails.tagline}</p>
                <div className="saved-meta">
                  <span><Calendar size={13} /> {eventDetails.dateStr}</span>
                  <span><MapPin size={13} /> {eventDetails.venueName}</span>
                  <span>Currency: <strong>{eventDetails.currency || "JMD"}</strong></span>
                </div>
                <button
                  type="button"
                  className="primary-button order-now-btn"
                  onClick={() => setActiveStep("menu")}
                >
                  <Utensils size={14} /> Order Customizable Food Now
                </button>
              </div>
            </div>
          </div>

          {/* Attendee Notification Preferences & Account Privacy */}
          <div className="attendee-prefs-card">
            <h3>
              <Bell size={18} /> Notification Preferences & Account Security
            </h3>
            <p className="prefs-intro">
              Control how you receive digital ticket receipts, advance event reminders, and order collection updates.
            </p>

            <div className="prefs-options-grid">
              <label className="pref-checkbox-label">
                <input
                  type="checkbox"
                  checked={prefSms}
                  onChange={(e) => setPrefSms(e.target.checked)}
                />
                <div>
                  <strong>SMS Order Updates & Digital Ticket Links</strong>
                  <span>Receive text alerts when payment is verified or meal is ready.</span>
                </div>
              </label>

              <label className="pref-checkbox-label">
                <input
                  type="checkbox"
                  checked={prefWhatsapp}
                  onChange={(e) => setPrefWhatsapp(e.target.checked)}
                />
                <div>
                  <strong>WhatsApp Digital Ticket Passes</strong>
                  <span>Instant high-resolution QR vouchers sent to your WhatsApp.</span>
                </div>
              </label>

              <label className="pref-checkbox-label">
                <input
                  type="checkbox"
                  checked={prefEmail}
                  onChange={(e) => setPrefEmail(e.target.checked)}
                />
                <div>
                  <strong>Email Invoices & Printable Receipts</strong>
                  <span>Itemized PDF-ready receipts delivered to your inbox.</span>
                </div>
              </label>
            </div>

            <div className="guest-privacy-statement">
              <ShieldCheck size={18} />
              <div>
                <strong>Guest Checkout Policy:</strong> Attendees are never required to create an account or password to order food. You can order freely as a guest, and retrieve your tickets anytime using your phone number or order reference code.
              </div>
            </div>
          </div>
        </section>
      )}

      {/* STEP 6: ATTENDEE ACCOUNT SETTINGS & PRIVACY (9) */}
      {activeStep === "settings" && (
        <section className="attendee-settings-section">
          <AccountSettingsView
            accountType="attendee"
            currentUser={{
              name: attendeeProfile?.name || "Marcus Bailey",
              email: attendeeProfile?.email || "marcus.bailey@islandevents.example",
              phone: attendeeProfile?.phone || "+1 (876) 555-4321",
              roleLabel: "Attendee",
            }}
            orders={orders}
            attendeeProfile={attendeeProfile}
            formatMoney={formatMoney}
            onNavigateToTab={(tab) => {
              if (tab === "orders" || tab === "tickets") setActiveStep("orders_wallet");
              else if (tab === "menu") setActiveStep("menu");
            }}
          />
        </section>
      )}

      {/* DISPUTE SUBMISSION MODAL */}
      {disputeModalOrder && (
        <div className="modal-backdrop" onClick={() => setDisputeModalOrder(null)}>
          <div
            className="modal-card dispute-submit-modal"
            role="dialog"
            aria-modal="true"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="modal-header">
              <div>
                <span className="section-kicker">CUSTOMER DISPUTE & SUPPORT</span>
                <h3>Submit Claim for Order {disputeModalOrder.orderNumber}</h3>
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setDisputeModalOrder(null)}
              >
                <X size={18} />
              </button>
            </div>

            <div className="modal-body">
              <div className="dispute-modal-info">
                <div>
                  <span>Ticket Code:</span> <code>{disputeModalOrder.ticketCode}</code>
                </div>
                <div>
                  <span>Amount:</span>{" "}
                  <strong>{formatMoney(disputeModalOrder.totalAmount, disputeModalOrder.currency)}</strong>
                </div>
              </div>

              <div className="form-group">
                <label htmlFor="dispute-reason">Describe Your Issue or Dispute Reason:</label>
                <textarea
                  id="dispute-reason"
                  rows={3}
                  placeholder="e.g. Sent Zelle payment from spouse's account / unable to attend event / meal question..."
                  value={disputeReasonInput}
                  onChange={(e) => setDisputeReasonInput(e.target.value)}
                  className="form-textarea"
                />
              </div>
            </div>

            <div className="modal-footer">
              <button
                type="button"
                className="secondary-button"
                onClick={() => setDisputeModalOrder(null)}
              >
                Cancel
              </button>
              <button
                type="button"
                className="primary-button"
                onClick={() => {
                  if (!disputeReasonInput.trim()) return;
                  if (onSubmitDispute) {
                    onSubmitDispute({
                      orderNumber: disputeModalOrder.orderNumber,
                      ticketCode: disputeModalOrder.ticketCode,
                      reason: disputeReasonInput.trim(),
                      amount: disputeModalOrder.totalAmount,
                      currency: disputeModalOrder.currency,
                      customerName: disputeModalOrder.customerName,
                      customerPhone: disputeModalOrder.customerPhone,
                      organizerId: disputeModalOrder.organizerId || eventDetails.organizerId || "org-kingston-youth",
                      organizerName: eventDetails.organizerName,
                      eventName: eventDetails.title,
                    });
                  }
                  showToast("Dispute ticket submitted to Platform Administrator. We will review your claim.");
                  setDisputeModalOrder(null);
                  setDisputeReasonInput("");
                }}
              >
                Submit Dispute Claim
              </button>
            </div>
          </div>
        </div>
      )}

      {/* CUSTOMIZABLE MEAL OPTIONS MODAL */}
      {customizingItem && (
        <div className="modal-backdrop" onClick={() => setCustomizingItem(null)}>
          <div
            className="modal-card customization-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="custom-meal-title"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="modal-header">
              <div>
                <span className="section-kicker">CUSTOMIZABLE MEAL OPTIONS</span>
                <h2 id="custom-meal-title">Customize {customizingItem.name}</h2>
                <p>
                  Base Price:{" "}
                  <strong>{formatMoney(getItemBasePrice(customizingItem), settings.currency)}</strong>
                </p>
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setCustomizingItem(null)}
              >
                <X size={18} />
              </button>
            </div>

            <div className="modal-body">
              {customizingItem.customOptionGroups?.map((group) => (
                <div className="custom-option-group" key={group.id}>
                  <label className="field-label option-group-title">
                    {group.title} {group.required && <span className="req">*</span>}
                  </label>
                  <div className="custom-choice-options">
                    {group.choices.map((choice, cIdx) => {
                      const isChosen = (chosenOptionChoices[group.id] ?? 0) === cIdx;
                      const extra =
                        settings.currency === "JMD" ? choice.extraPriceJMD : choice.extraPriceUSD;

                      return (
                        <button
                          key={cIdx}
                          type="button"
                          className={`custom-choice-pill ${isChosen ? "selected" : ""}`}
                          onClick={() =>
                            setChosenOptionChoices((prev) => ({
                              ...prev,
                              [group.id]: cIdx,
                            }))
                          }
                        >
                          <span className="choice-label">{choice.label}</span>
                          <span className="choice-extra">
                            {extra > 0 ? `+${formatMoney(extra, settings.currency)}` : "Included"}
                          </span>
                        </button>
                      );
                    })}
                  </div>
                </div>
              ))}

              {customizingItem.allowSpecialInstructions && (
                <div className="form-group special-instructions-group">
                  <label className="field-label" htmlFor="special-notes">
                    Special Preparation Instructions (Optional)
                  </label>
                  <textarea
                    id="special-notes"
                    rows={2}
                    className="settings-textarea"
                    placeholder="e.g. Extra crispy skin, scotch bonnet sauce on side..."
                    value={itemSpecialInstructions}
                    onChange={(e) => setItemSpecialInstructions(e.target.value)}
                  />
                </div>
              )}
            </div>

            <div className="modal-footer">
              <button
                type="button"
                className="primary-button modal-done-btn"
                onClick={handleAddCustomizedItem}
              >
                Add Customized Meal to Order <Check size={15} />
              </button>
            </div>
          </div>
        </div>
      )}

      {/* CUSTOM AMOUNT / DONATION MODAL */}
      {donationItem && donationItem.customAmountConfig && (
        <div className="modal-backdrop" onClick={() => setDonationItem(null)}>
          <div
            className="modal-card donation-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="custom-amt-title"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="modal-header">
              <div>
                <span className="section-kicker">CUSTOM AMOUNT DONATION</span>
                <h2 id="custom-amt-title">{donationItem.name}</h2>
                <p>Support the community fundraiser with an amount of your choice.</p>
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setDonationItem(null)}
              >
                <X size={18} />
              </button>
            </div>

            <div className="modal-body">
              <label className="field-label">Quick Select Contribution:</label>
              <div className="suggested-amounts-row">
                {(settings.currency === "JMD"
                  ? donationItem.customAmountConfig.suggestedAmountsJMD
                  : donationItem.customAmountConfig.suggestedAmountsUSD
                ).map((amt) => (
                  <button
                    key={amt}
                    type="button"
                    className={`suggested-amt-pill ${customAmountValue === amt ? "active" : ""}`}
                    onClick={() => setCustomAmountValue(amt)}
                  >
                    {formatMoney(amt, settings.currency)}
                  </button>
                ))}
              </div>

              <div className="form-group custom-input-group">
                <label className="field-label" htmlFor="custom-amt-input">
                  Or enter any custom amount ({settings.currency}):
                </label>
                <div className="input-wrap">
                  <DollarSign size={16} />
                  <input
                    id="custom-amt-input"
                    type="number"
                    min={
                      settings.currency === "JMD"
                        ? donationItem.customAmountConfig.minJMD
                        : donationItem.customAmountConfig.minUSD
                    }
                    max={
                      settings.currency === "JMD"
                        ? donationItem.customAmountConfig.maxJMD
                        : donationItem.customAmountConfig.maxUSD
                    }
                    value={customAmountValue}
                    onChange={(e) => setCustomAmountValue(Number(e.target.value))}
                  />
                </div>
                <span className="field-hint">
                  Allowed range:{" "}
                  {formatMoney(
                    settings.currency === "JMD"
                      ? donationItem.customAmountConfig.minJMD
                      : donationItem.customAmountConfig.minUSD,
                    settings.currency
                  )}{" "}
                  –{" "}
                  {formatMoney(
                    settings.currency === "JMD"
                      ? donationItem.customAmountConfig.maxJMD
                      : donationItem.customAmountConfig.maxUSD,
                    settings.currency
                  )}
                </span>
              </div>
            </div>

            <div className="modal-footer">
              <button
                type="button"
                className="primary-button modal-done-btn"
                onClick={handleAddCustomAmountItem}
              >
                Confirm Donation of {formatMoney(customAmountValue, settings.currency)} <Heart size={15} />
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Simulated SMS Notification Modal (Step 6) */}
      {showSmsModal && issuedTicket && (
        <div className="modal-backdrop" onClick={() => setShowSmsModal(false)}>
          <div
            className="sms-mockup-card"
            role="dialog"
            aria-modal="true"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="sms-phone-header">
              <span className="sms-carrier">Digicel / Flow 4G</span>
              <span className="sms-contact-name">Island Events Fish Fry</span>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setShowSmsModal(false)}
              >
                <X size={16} />
              </button>
            </div>
            <div className="sms-bubble-area">
              <div className="sms-timestamp">
                Today at {new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}
              </div>
              <div className="sms-bubble">
                <p>
                  🐟 <strong>{eventDetails.title}:</strong> Big up {issuedTicket.customerName}! Your order has been placed.
                </p>
                <p>
                  🎫 <strong>Order #:</strong> {issuedTicket.orderNumber}
                  <br />
                  📋 <strong>Ticket Ref:</strong> {issuedTicket.ticketCode}
                </p>
                <p>
                  🍱 <strong>Dishes:</strong>
                  <br />
                  {issuedTicket.items
                    .map((i) => `• ${i.quantity}x ${i.name} (${formatMoney(i.finalItemPrice, issuedTicket.currency)})`)
                    .join("\n")}
                </p>
                <p>
                  💰 <strong>Total Amount:</strong> {formatMoney(issuedTicket.totalAmount, issuedTicket.currency)} (
                  {issuedTicket.status === "paid" ? "Verified Paid" : "Pending Verification"})
                </p>
                <p>
                  📍 <strong>Pickup:</strong> {eventDetails.pickupInstructions}
                </p>
                <p>
                  🔗 <strong>View & Retrieve Voucher:</strong>
                  <br />
                  {shareableTicketUrl}
                </p>
              </div>
            </div>
            <div className="sms-footer">
              <span>Simulated SMS message sent to {issuedTicket.customerPhone}</span>
              <button
                type="button"
                className="primary-button modal-done-btn"
                onClick={() => setShowSmsModal(false)}
              >
                Close Message
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Login Required Modal for Public Visitors */}
      {showLoginPromptModal && (
        <div className="modal-backdrop" onClick={() => setShowLoginPromptModal(false)}>
          <div
            className="modal-card"
            style={{ maxWidth: "490px" }}
            role="dialog"
            aria-modal="true"
            aria-labelledby="login-prompt-title"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="modal-header">
              <div>
                <span className="section-kicker" style={{ color: "#d97706" }}>MANDATORY AUTHENTICATION</span>
                <h2 id="login-prompt-title" style={{ fontSize: "1.3rem" }}>Sign In Required to Order</h2>
                <p>Public visitors can browse all 10 fish fry events. To place food orders or reserve tickets, please sign in.</p>
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setShowLoginPromptModal(false)}
                aria-label="Close dialog"
              >
                <X size={18} />
              </button>
            </div>
            <div className="modal-body">
              <div style={{ background: "#f8fafc", border: "1px solid #e2e8f0", borderRadius: "10px", padding: "12px 14px", marginBottom: "16px" }}>
                <div style={{ fontSize: "13px", fontWeight: 700, color: "#0f172a", marginBottom: "4px" }}>
                  Selected Event: {eventDetails.title}
                </div>
                <div style={{ fontSize: "12px", color: "#64748b" }}>
                  📍 {eventDetails.venueName} &bull; 📅 {eventDetails.dateStr}
                </div>
              </div>

              <p style={{ fontSize: "13px", color: "#475569", lineHeight: "1.5" }}>
                Per Island Event security policies, every customer must have an authenticated Attendee account before meal pre-orders are sent to the kitchen stations.
              </p>

              <div style={{ marginTop: "18px", display: "flex", flexDirection: "column", gap: "10px" }}>
                <button
                  type="button"
                  className="primary-button"
                  style={{ width: "100%", justifyContent: "center", padding: "10px 16px", background: "#059669", borderColor: "#047857", fontSize: "13px", fontWeight: 700 }}
                  onClick={() => {
                    setShowLoginPromptModal(false);
                    if (onLogin) onLogin();
                  }}
                  id="btn-modal-quick-login"
                >
                  {signInLabel}
                </button>
                <button
                  type="button"
                  className="secondary-button"
                  style={{ width: "100%", justifyContent: "center", padding: "10px 16px", fontSize: "13px" }}
                  onClick={() => setShowLoginPromptModal(false)}
                >
                  Cancel & Continue Browsing Events
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Full Resolution Image Lightbox for Promotional Flyer */}
      {lightboxImg && (
        <div
          className="lightbox-backdrop"
          onClick={() => setLightboxImg(null)}
          role="dialog"
          aria-modal="true"
        >
          <div className="lightbox-content" onClick={(e) => e.stopPropagation()}>
            <button
              type="button"
              className="lightbox-close-btn"
              onClick={() => setLightboxImg(null)}
              aria-label="Close flyer preview"
            >
              <X size={20} />
            </button>
            <img src={lightboxImg.url} alt={lightboxImg.caption} />
            <div className="lightbox-caption">{lightboxImg.caption}</div>
          </div>
        </div>
      )}
    </div>
  );
};
