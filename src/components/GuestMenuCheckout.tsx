import React, { useEffect, useMemo, useState } from "react";
import {
  AlertCircle,
  AlertTriangle,
  ArrowDownLeft,
  ArrowRight,
  Bell,
  Calendar,
  Check,
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
  Minus,
  Phone,
  Plus,
  Search,
  Send,
  Share2,
  ShieldAlert,
  ShieldCheck,
  ShoppingBag,
  Sliders,
  Ticket,
  UserRound,
  Utensils,
  Settings2,
  X,
  Compass,
} from "lucide-react";
import { EventDiscoveryHomepage, BottomNavItem } from "./EventDiscoveryHomepage";
import { EventDetailView } from "./EventDetailView";
import { TicketSelectionScreen } from "./TicketSelectionScreen";
import { FoodAndDrinksScreen } from "./FoodAndDrinksScreen";
import { AttendeeTicketManagementScreen } from "./AttendeeTicketManagementScreen";
import { IslandEventDigitalTicketScreen } from "./IslandEventDigitalTicketScreen";
import { IslandEventUserProfileScreen } from "./IslandEventUserProfileScreen";

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
  notificationLogs: _notificationLogs = [],
  availableMethods,
  isOwnerPreview = false,
  onPlaceOrder,
  onSendCustomNotification: _onSendCustomNotification,
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
    "discovery" | "event_details" | "ticket_selection" | "menu" | "checkout" | "ticket" | "orders_wallet" | "saved_events" | "settings"
  >("discovery");
  const [selectedTicketQuantities, setSelectedTicketQuantities] = useState<Record<string, number>>({
    "tier-ga": 1,
  });
  const [selectedFoodFromTickets, setSelectedFoodFromTickets] = useState<Record<string, number>>({});
  const [discoveryBottomNav, setDiscoveryBottomNav] = useState<BottomNavItem>("home");
  const [isTourGridExpanded, setIsTourGridExpanded] = useState(false);

  // Attendee Notification Preferences
  const [prefSms, setPrefSms] = useState(attendeeProfile?.notificationPreferences.smsReminders ?? true);
  const [prefWhatsapp, setPrefWhatsapp] = useState(attendeeProfile?.notificationPreferences.whatsAppTickets ?? true);
  const [prefEmail, setPrefEmail] = useState(attendeeProfile?.notificationPreferences.emailReceipts ?? true);

  // Transfer Ref & Dispute State in Wallet
  const [submittingRefOrderId, setSubmittingRefOrderId] = useState<string | null>(null);
  const [transferRefInput, setTransferRefInput] = useState("");
  const [disputeModalOrder, setDisputeModalOrder] = useState<TicketOrder | null>(null);
  const [disputeReasonInput, setDisputeReasonInput] = useState("");

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
  const [checkoutIdempotencyKey, setCheckoutIdempotencyKey] = useState<string>(() =>
    typeof crypto !== "undefined" && typeof crypto.randomUUID === "function"
      ? crypto.randomUUID()
      : `idem-${Date.now()}-${Math.floor(Math.random() * 100000)}`
  );
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

  // Each attendee screen starts at the top instead of inheriting the previous scroll position.
  useEffect(() => {
    window.scrollTo({ top: 0 });
  }, [activeStep, selectedEventIndex]);

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

  const hasFoodInCart = useMemo(
    () =>
      cart.some(
        (ci) =>
          !ci.menuItemId.startsWith("tier-") &&
          ci.menuItemId !== "general-admission" &&
          ci.menuItemId !== "vip-oceanfront" &&
          ci.menuItemId !== "group-pass"
      ),
    [cart]
  );

  const effectivePackagingFee = hasFoodInCart ? packagingFee : 0;

  const cartTotals = useMemo(() => {
    return calculateOrderTotals(
      cart.map((ci) => ({ finalItemPrice: ci.finalItemPrice, quantity: ci.quantity })),
      effectivePackagingFee,
      settings.currency
    );
  }, [cart, effectivePackagingFee, settings.currency]);

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
      packagingFee: effectivePackagingFee,
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
      idempotency_key: checkoutIdempotencyKey,
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
    setCheckoutIdempotencyKey(
      typeof crypto !== "undefined" && typeof crypto.randomUUID === "function"
        ? crypto.randomUUID()
        : `idem-${Date.now()}-${Math.floor(Math.random() * 100000)}`
    );
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


  const shareableTicketUrl = issuedTicket
    ? `${window.location.origin}?ticket=${issuedTicket.ticketCode}`
    : "";

  return (
    <div className="guest-journey-container">
      {/* Step 0: Dedicated Midnight-Navy Discovery Homepage */}
      {activeStep === "discovery" && allEvents && allEvents.length > 0 && (
        <EventDiscoveryHomepage
          events={allEvents}
          selectedEventIndex={selectedEventIndex}
          onSelectEvent={(idx) => {
            if (onSelectEvent) onSelectEvent(idx);
          }}
          onNavigateToDetails={(idx) => {
            if (onSelectEvent) onSelectEvent(idx);
            setActiveStep("event_details");
          }}
          isLoggedIn={isLoggedIn}
          userName={attendeeProfile?.name}
          userEmail={attendeeProfile?.email}
          onOpenAuthModal={() => onLogin && onLogin()}
          onLogout={onLogout || (() => {})}
          onNavigateToTickets={() => {
            setDiscoveryBottomNav("tickets");
            setActiveStep("orders_wallet");
          }}
          onNavigateToFood={(idx) => {
            if (idx !== undefined && onSelectEvent) onSelectEvent(idx);
            setDiscoveryBottomNav("food");
            setActiveStep("menu");
          }}
          onNavigateToProfile={() => {
            setDiscoveryBottomNav("profile");
            setActiveStep("settings");
          }}
          formatMoney={formatMoney}
          activeBottomNav={discoveryBottomNav}
          onChangeBottomNav={(nav) => setDiscoveryBottomNav(nav)}
        />
      )}

      {/* Step 0.5: Dedicated Premium Event Detail Screen */}
      {activeStep === "event_details" && (
        <EventDetailView
          event={allEvents && allEvents.length > 0 ? allEvents[selectedEventIndex] || eventDetails : eventDetails}
          eventIndex={selectedEventIndex}
          onBack={() => setActiveStep("discovery")}
          onNavigateToFood={(idx) => {
            if (onSelectEvent) onSelectEvent(idx);
            setActiveStep("menu");
          }}
          onNavigateToTickets={() => {
            setActiveStep("ticket_selection");
          }}
          isLoggedIn={isLoggedIn}
          onOpenAuthModal={() => onLogin && onLogin()}
          formatMoney={formatMoney}
        />
      )}

      {/* Step 0.75: Dedicated Ticket Selection Screen */}
      {activeStep === "ticket_selection" && (
        <TicketSelectionScreen
          event={allEvents && allEvents.length > 0 ? allEvents[selectedEventIndex] || eventDetails : eventDetails}
          eventIndex={selectedEventIndex}
          onBack={() => setActiveStep("event_details")}
          onOpenCompleteMenu={() => setActiveStep("menu")}
          onContinueToCheckout={(selectionData) => {
            setSelectedTicketQuantities(selectionData.selectedTickets);
            setSelectedFoodFromTickets(selectionData.selectedFood);

            const activeEvt: any =
              allEvents && allEvents.length > 0 ? allEvents[selectedEventIndex] || eventDetails : eventDetails;
            const newCartItems: CartItem[] = [];

            // 1. Populate cart with any selected ticket passes
            const defaultTiers: Record<string, { name: string; priceUSD: number; priceJMD: number }> = {
              "tier-ga": { name: "General Admission Pass", priceUSD: 15, priceJMD: 2250 },
              "tier-vip": { name: "VIP Oceanfront Lounge Pass", priceUSD: 45, priceJMD: 6800 },
              "tier-early-bird": { name: "Early-Bird Advance Pass", priceUSD: 12, priceJMD: 1800 },
              "tier-group": { name: "Family & Crew 4-Pack", priceUSD: 50, priceJMD: 7500 },
            };

            if (selectionData.selectedTickets) {
              Object.entries(selectionData.selectedTickets).forEach(([tierId, qty]) => {
                if (qty > 0) {
                  const customTier = Array.isArray(activeEvt?.ticketTiers)
                    ? activeEvt.ticketTiers.find((t: any, idx: number) => String(t.id || `tier-${idx}`) === tierId)
                    : null;
                  const tierMeta = customTier
                    ? {
                        name: String(customTier.name || "Admission Pass"),
                        priceUSD: Number(customTier.priceUSD ?? customTier.price_usd ?? 15),
                        priceJMD: Number(customTier.priceJMD ?? customTier.price_jmd ?? 2250),
                      }
                    : defaultTiers[tierId] || { name: "Event Admission Pass", priceUSD: 15, priceJMD: 2250 };
                  const unitPrice = settings.currency === "JMD" ? tierMeta.priceJMD : tierMeta.priceUSD;

                  newCartItems.push({
                    menuItemId: tierId,
                    name: tierMeta.name,
                    pricingType: "fixed",
                    basePrice: unitPrice,
                    optionsTotalExtra: 0,
                    selectedOptionsSummary: [],
                    finalItemPrice: unitPrice,
                    quantity: qty,
                    imageUrl: activeEvt?.flyerUrl || "/images/island_festival_sunset.jpg",
                  });
                }
              });
            }

            // 2. Populate cart with any selected food items
            if (menu && selectionData.selectedFood) {
              Object.entries(selectionData.selectedFood).forEach(([dishId, qty]) => {
                if (qty > 0) {
                  const dish = menu.find((m: MenuItem) => m.id === dishId);
                  if (dish) {
                    newCartItems.push({
                      menuItemId: dish.id,
                      name: dish.name,
                      pricingType: dish.pricingType,
                      basePrice: settings.currency === "JMD" ? dish.priceJMD : dish.priceUSD,
                      optionsTotalExtra: 0,
                      selectedOptionsSummary: [],
                      finalItemPrice: settings.currency === "JMD" ? dish.priceJMD : dish.priceUSD,
                      quantity: qty,
                      imageUrl: dish.imageUrl,
                    });
                  }
                }
              });
            }

            if (newCartItems.length > 0) {
              setCart(newCartItems);
            }
            setActiveStep("checkout");
          }}
          formatMoney={formatMoney}
          initialSelectedTickets={selectedTicketQuantities}
          initialSelectedFood={selectedFoodFromTickets}
        />
      )}

      {/* Step 0.85: Dedicated Island Event Food & Drinks Screen */}
      {activeStep === "menu" && (
        <FoodAndDrinksScreen
          eventDetails={allEvents && allEvents.length > 0 ? allEvents[selectedEventIndex] || eventDetails : eventDetails}
          menu={allEvents && allEvents.length > 0 && allEvents[selectedEventIndex]?.menu ? allEvents[selectedEventIndex].menu! : menu}
          settings={settings}
          cart={cart}
          onAddToCart={(item) => {
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
            showToast(`Added ${item.name} to cart`);
          }}
          onUpdateCartQuantity={(index, delta) => updateQuantity(index, delta)}
          onRemoveCartItem={(index) => updateQuantity(index, -cart[index].quantity)}
          onAddCustomizedItem={(item, chosenChoices, instructions, quantity) => {
            const calculated = calculateItemFinalPrice(
              item,
              settings.currency,
              chosenChoices
            );
            const newCartItem: CartItem = {
              menuItemId: item.id,
              name: item.name,
              pricingType: "customizable",
              basePrice: calculated.basePrice,
              optionsTotalExtra: calculated.optionsExtra,
              selectedOptionsSummary: calculated.summaryList,
              finalItemPrice: calculated.finalPrice,
              quantity: quantity || 1,
              imageUrl: item.imageUrl,
              specialInstructions: instructions || undefined,
              selectedChoices: { ...chosenChoices },
            };
            setCart((prev) => [...prev, newCartItem]);
            showToast(`Added ${quantity || 1}x ${item.name} to cart`);
          }}
          onAddDonationItem={(item, amt) => {
            const calculated = calculateItemFinalPrice(
              item,
              settings.currency,
              {},
              amt
            );
            const newCartItem: CartItem = {
              menuItemId: item.id,
              name: item.name,
              pricingType: "custom_amount",
              basePrice: calculated.basePrice,
              optionsTotalExtra: 0,
              selectedOptionsSummary: calculated.summaryList,
              customDonationAmount: amt,
              finalItemPrice: calculated.finalPrice,
              quantity: 1,
              imageUrl: item.imageUrl,
            };
            setCart((prev) => [...prev, newCartItem]);
            showToast(`Added contribution of ${formatMoney(amt, settings.currency)}`);
          }}
          onBack={() => {
            if (selectedTicketQuantities && Object.values(selectedTicketQuantities).some((q) => q > 0)) {
              setActiveStep("ticket_selection");
            } else {
              setActiveStep("event_details");
            }
          }}
          onProceedToCheckout={() => setActiveStep("checkout")}
          formatMoney={formatMoney}
        />
      )}

      {/* Step 0.9: Dedicated Authenticated Attendee Ticket Management Screen ("Your Tickets") */}
      {activeStep === "orders_wallet" && (
        <AttendeeTicketManagementScreen
          orders={orders}
          currentUser={attendeeProfile}
          isLoggedIn={isLoggedIn}
          onOpenAuthModal={() => onLogin && onLogin()}
          onDiscoverEvents={() => {
            setDiscoveryBottomNav("home");
            setActiveStep("discovery");
          }}
          onSelectEventDetails={(eventId) => {
            if (allEvents && allEvents.length > 0) {
              const idx = allEvents.findIndex((e) => e.id === eventId);
              if (idx >= 0 && onSelectEvent) {
                onSelectEvent(idx);
              }
            }
            setActiveStep("event_details");
          }}
          formatMoney={formatMoney}
          allEvents={allEvents}
        />
      )}

      {activeStep !== "discovery" && activeStep !== "event_details" && activeStep !== "ticket_selection" && activeStep !== "menu" && activeStep !== "orders_wallet" && (
        <>
      {/* Checkout and the ticket are focused screens: skip the browse chrome above them */}
      {activeStep !== "checkout" && activeStep !== "ticket" && (
      <>
          {/* Cinematic Island Event Welcome & Onboarding Hero (Live the Moment) */}
      {!isLoggedIn && (activeStep as string) !== "ticket" && (
        <div
          className="welcome-cinematic-hero"
          style={{
            position: "relative",
            minHeight: "340px",
            borderRadius: "20px",
            marginBottom: "20px",
            background: "linear-gradient(to top, rgba(15, 23, 42, 0.95) 0%, rgba(15, 23, 42, 0.55) 50%, rgba(15, 23, 42, 0.25) 100%), url('/images/island_festival_sunset.jpg') center bottom / cover no-repeat",
            color: "#ffffff",
            padding: "36px 32px 28px",
            display: "flex",
            flexDirection: "column",
            justifyContent: "flex-end",
            boxShadow: "0 16px 36px -10px rgba(15, 23, 42, 0.35)",
            overflow: "hidden",
          }}
          id="welcome-cinematic-hero-banner"
        >
          <div style={{ maxWidth: "660px", position: "relative", zIndex: 2 }}>
            <div
              style={{
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
                background: "rgba(255, 255, 255, 0.16)",
                backdropFilter: "blur(8px)",
                padding: "4px 12px",
                borderRadius: "9999px",
                fontSize: "11px",
                fontWeight: 700,
                letterSpacing: "0.06em",
                color: "#ffffff",
                marginBottom: "12px",
                border: "1px solid rgba(255, 255, 255, 0.25)",
              }}
            >
              <span>🌴</span>
              <span>ISLAND EVENT PLATFORM</span>
            </div>

            <h1
              style={{
                margin: "0 0 8px",
                fontSize: "36px",
                fontWeight: 900,
                letterSpacing: "-0.03em",
                color: "#ffffff",
                lineHeight: 1.1,
                textShadow: "0 2px 16px rgba(0,0,0,0.4)",
              }}
            >
              Live the Moment.
            </h1>

            <p
              style={{
                margin: "0 0 20px",
                fontSize: "14px",
                color: "#e2e8f0",
                lineHeight: 1.55,
                maxWidth: "540px",
                textShadow: "0 1px 8px rgba(0,0,0,0.3)",
              }}
            >
              Curated culinary gatherings, authentic Caribbean music festivals, and streamlined digital ticketing across the United States.
            </p>

            <div style={{ display: "flex", alignItems: "center", gap: "12px", flexWrap: "wrap", marginBottom: "14px" }}>
              <button
                type="button"
                onClick={onLogin}
                id="btn-welcome-cta-register"
                style={{
                  display: "inline-flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "8px",
                  padding: "11px 24px",
                  borderRadius: "9999px",
                  fontSize: "14px",
                  fontWeight: 800,
                  color: "#ffffff",
                  background: "linear-gradient(135deg, #7c3aed 0%, #ec4899 50%, #f97316 100%)",
                  border: "none",
                  cursor: "pointer",
                  boxShadow: "0 6px 20px -2px rgba(236, 72, 153, 0.45)",
                  transition: "all 0.18s ease",
                }}
              >
                <span>Get Started</span>
                <ArrowRight size={16} />
              </button>

              <button
                type="button"
                onClick={() => {
                  setDiscoveryBottomNav("home");
                  setActiveStep("discovery");
                }}
                id="btn-welcome-explore-events"
                style={{
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "6px",
                  padding: "11px 20px",
                  borderRadius: "9999px",
                  fontSize: "13px",
                  fontWeight: 700,
                  color: "#ffffff",
                  background: "rgba(255, 255, 255, 0.14)",
                  backdropFilter: "blur(8px)",
                  border: "1px solid rgba(255, 255, 255, 0.3)",
                  cursor: "pointer",
                }}
              >
                <Globe size={15} />
                <span>Explore Events</span>
              </button>
            </div>

            <div style={{ display: "flex", alignItems: "center", gap: "6px", fontSize: "12px", color: "#cbd5e1" }}>
              <span>Already have an Island Event account?</span>
              <button
                type="button"
                onClick={onLogin}
                id="btn-welcome-discreet-login"
                style={{
                  background: "none",
                  border: "none",
                  padding: 0,
                  color: "#ffffff",
                  fontWeight: 700,
                  textDecoration: "underline",
                  cursor: "pointer",
                  fontSize: "12px",
                }}
              >
                Sign In
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 10 Fish Fry Events Discovery Tour Strip */}
      {allEvents && allEvents.length > 0 && (
        <div
          className="fishfry-events-discovery-strip"
          style={{
            marginBottom: "16px",
            background: "#ffffff",
            border: "1px solid #e2e8f0",
            borderRadius: "14px",
            padding: "12px 16px",
            boxShadow: "0 2px 4px rgba(0,0,0,0.02)",
          }}
          id="fishfry-events-tour-container"
        >
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "10px", flexWrap: "wrap", gap: "8px" }}>
            <div>
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <span style={{ fontSize: "11px", fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.06em", color: "#D4AF37" }}>
                  🌴 Premier Cultural & Culinary Events Tour
                </span>
                <span style={{ fontSize: "11px", fontWeight: 700, background: "#EEF2FF", color: "#4338CA", padding: "1px 8px", borderRadius: "9999px", border: "1px solid #C7D2FE" }}>
                  {allEvents.length} Curated Experiences
                </span>
                <span style={{ fontSize: "11px", color: "#64748B" }}>
                  &bull; Active: <strong>#{selectedEventIndex + 1} {allEvents[selectedEventIndex]?.title || "Current Event"}</strong>
                </span>
              </div>
            </div>
            <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
              <span style={{ fontSize: "12px", color: "#64748B" }}>
                Showing <strong>{selectedEventIndex + 1} of {allEvents.length}</strong>
              </span>
              <button
                type="button"
                onClick={() => setIsTourGridExpanded(!isTourGridExpanded)}
                id="btn-toggle-event-tour-view"
                style={{
                  fontSize: "11px",
                  fontWeight: 700,
                  padding: "4px 10px",
                  borderRadius: "8px",
                  border: isTourGridExpanded ? "1px solid #4F46E5" : "1px solid #CBD5E1",
                  background: isTourGridExpanded ? "#4F46E5" : "#F8FAFC",
                  color: isTourGridExpanded ? "#FFFFFF" : "#1E293B",
                  cursor: "pointer",
                  transition: "all 0.15s ease",
                }}
              >
                {isTourGridExpanded ? "▲ Compact Carousel" : "▼ View All 10 Grid"}
              </button>
            </div>
          </div>

          <div
            style={
              isTourGridExpanded
                ? {
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fill, minmax(215px, 1fr))",
                    gap: "10px",
                  }
                : {
                    display: "flex",
                    gap: "10px",
                    overflowX: "auto",
                    paddingBottom: "6px",
                    scrollSnapType: "x mandatory",
                    WebkitOverflowScrolling: "touch",
                  }
            }
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
                    padding: "9px 12px",
                    textAlign: "left",
                    cursor: "pointer",
                    transition: "all 0.15s ease",
                    boxShadow: isSelected ? "0 4px 12px rgba(79, 70, 229, 0.14)" : "none",
                    minWidth: isTourGridExpanded ? "auto" : "210px",
                    maxWidth: isTourGridExpanded ? "none" : "235px",
                    flexShrink: isTourGridExpanded ? 1 : 0,
                    scrollSnapAlign: "start",
                  }}
                  id={`btn-select-event-${idx}`}
                >
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "3px" }}>
                    <span style={{ fontSize: "10px", fontWeight: 700, color: isSelected ? "#4338CA" : "#64748B", textTransform: "uppercase" }}>
                      #{idx + 1} · {evt.locationCategory || "United States"}
                    </span>
                    {isSelected && (
                      <span style={{ fontSize: "9px", fontWeight: 800, background: "#4F46E5", color: "#fff", padding: "1px 6px", borderRadius: "4px" }}>
                        Active
                      </span>
                    )}
                  </div>
                  <div style={{ fontSize: "12px", fontWeight: 700, color: "#111827", lineHeight: "1.3", marginBottom: "3px", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
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
            borderRadius: "12px",
            padding: "10px 16px",
            marginBottom: "16px",
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
            flexWrap: "wrap",
            gap: "10px",
          }}
          id="visitor-view-only-banner"
        >
          <div style={{ display: "flex", alignItems: "center", gap: "10px", maxWidth: "760px" }}>
            <span style={{ fontSize: "20px" }}>🌴</span>
            <div>
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <strong style={{ fontSize: "13px", color: "#92400E" }}>
                  Public Visitor Mode — Viewing Only
                </strong>
                <span style={{ fontSize: "10px", fontWeight: 700, background: "#FEF08A", color: "#854D0E", padding: "1px 7px", borderRadius: "9999px" }}>
                  Guest Access
                </span>
              </div>
              <p style={{ fontSize: "12px", color: "#78350F", margin: "1px 0 0", lineHeight: "1.35" }}>
                Open access to view all curated cultural events and complete culinary menus. Sign in to pre-order portions, claim tickets, and access your digital wallet.
              </p>
            </div>
          </div>
          <div style={{ display: "flex", gap: "8px" }}>
            <button
              type="button"
              className="primary-button"
              style={{ background: "#4F46E5", borderColor: "#4338CA", fontSize: "12px", padding: "7px 14px", fontWeight: 700 }}
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
          className="step-pill"
          onClick={() => {
            setDiscoveryBottomNav("home");
            setActiveStep("discovery");
          }}
          id="step-discovery-home-btn"
          style={{
            background: "#0B132B",
            color: "#ffffff",
            border: "1px solid rgba(255, 255, 255, 0.15)",
            fontWeight: 700,
          }}
        >
          <Compass size={14} />
          <span>🌴 Discovery Home</span>
        </button>
        <button
          type="button"
          className="step-pill"
          onClick={() => setActiveStep("event_details")}
          id="step-event-details-btn"
          style={{
            background: "transparent",
            color: "#1e293b",
            fontWeight: 700,
          }}
        >
          <Info size={14} />
          <span>Event Details</span>
        </button>
        <button
          type="button"
          className="step-pill"
          onClick={() => setActiveStep("ticket_selection")}
          id="step-ticket-selection-btn"
          style={{
            background: "transparent",
            color: "#1e293b",
            fontWeight: 700,
          }}
        >
          <Ticket size={14} />
          <span>Ticket Selection</span>
        </button>
        <button
          type="button"
          className={`step-pill ${(activeStep as string) === "menu" ? "active" : ""}`}
          onClick={() => setActiveStep("menu")}
          id="step-menu-btn"
        >
          <span className="step-num">1</span>
          <span>Event & Food Menu</span>
        </button>
        <button
          type="button"
          className={`step-pill ${(activeStep as string) === "checkout" ? "active" : ""}`}
          onClick={() => cart.length > 0 && setActiveStep("checkout")}
          disabled={cart.length === 0}
          id="step-checkout-btn"
        >
          <span className="step-num">2</span>
          <span>Checkout & Pay ({cartCount})</span>
        </button>
        <button
          type="button"
          className={`step-pill ${(activeStep as string) === "ticket" ? "active" : ""}`}
          disabled={!issuedTicket}
          onClick={() => issuedTicket && setActiveStep("ticket")}
          id="step-ticket-btn"
        >
          <span className="step-num">3</span>
          <span>Digital Ticket Voucher</span>
        </button>
        <button
          type="button"
          className={`step-pill ${(activeStep as string) === "orders_wallet" ? "active" : ""}`}
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


      </>
      )}

      {toastMessage && <div className="mini-toast">{toastMessage}</div>}

      {/* STEP 1: DISCOVER & BROWSE MENU */}
      {(activeStep as string) === "menu" && (
        <section className="menu-experience-section">
          <div style={{ marginBottom: "14px", display: "flex", justifyContent: "space-between", alignItems: "center", flexWrap: "wrap", gap: "8px" }}>
            <button
              type="button"
              onClick={() => {
                setDiscoveryBottomNav("home");
                setActiveStep("discovery");
              }}
              id="btn-back-to-discovery-home"
              style={{
                background: "#0B132B",
                color: "#818CF8",
                border: "1px solid rgba(129, 140, 248, 0.3)",
                borderRadius: "9999px",
                padding: "7px 16px",
                fontSize: "12px",
                fontWeight: 700,
                cursor: "pointer",
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
                boxShadow: "0 2px 6px rgba(0,0,0,0.15)",
              }}
            >
              <span>← Back to Discovery Home</span>
            </button>
            <button
              type="button"
              onClick={() => setActiveStep("event_details")}
              id="btn-goto-event-details-from-menu"
              style={{
                background: "rgba(99, 102, 241, 0.12)",
                border: "1px solid rgba(99, 102, 241, 0.3)",
                color: "#4338CA",
                padding: "6px 14px",
                borderRadius: "9999px",
                fontSize: "12px",
                fontWeight: 700,
                cursor: "pointer",
                display: "inline-flex",
                alignItems: "center",
                gap: "5px",
              }}
            >
              <Info size={13} />
              <span>Event Details</span>
            </button>
            <button
              type="button"
              onClick={() => setActiveStep("ticket_selection")}
              id="btn-return-to-tickets-from-menu"
              style={{
                background: "rgba(16, 185, 129, 0.12)",
                border: "1px solid rgba(16, 185, 129, 0.3)",
                color: "#059669",
                padding: "6px 14px",
                borderRadius: "9999px",
                fontSize: "12px",
                fontWeight: 700,
                cursor: "pointer",
                display: "inline-flex",
                alignItems: "center",
                gap: "5px",
              }}
            >
              <Ticket size={13} />
              <span>← Return to Ticket Selection</span>
            </button>
            <span style={{ fontSize: "11px", color: "#64748B" }}>
              Viewing culinary menu for: <strong>{eventDetails.title}</strong>
            </span>
          </div>
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
        <section className="checkout-experience-section att-checkout">
          <div className="checkout-split-layout">
            {/* Left: Frictionless Guest Details & Payment Method */}
            <div className="panel checkout-left-panel">
              <div className="panel-heading">
                <div>
                  <span className="section-kicker">Step 2 of 3</span>
                  <h2>Checkout</h2>
                  <p>No account needed. We'll text your ticket to the number below.</p>
                </div>
                <button
                  type="button"
                  className="text-button"
                  onClick={() => setActiveStep("menu")}
                >
                  Edit order
                </button>
              </div>

              <form onSubmit={handleCheckoutSubmit} className="checkout-form">
                <div className="form-group">
                  <label className="field-label" htmlFor="cust-name">
                    Full name
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
                    Phone or WhatsApp
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
                    We send your ticket here, and you can use it to find your order again.
                  </span>
                </div>

                <div className="form-group">
                  <label className="field-label" htmlFor="cust-email">
                    Email (optional, for a receipt)
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
                      <label className="field-label">Payment method</label>
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
                          <ArrowDownLeft size={15} /> How to pay
                        </span>
                        <span>{METHOD_INFO[selectedMethod].name}</span>
                      </div>

                      {selectedMethod === "cash" ? (
                        <div className="cash-instructions-card">
                          <p>Pay cash when you collect your order.</p>
                          <div className="recipient-detail">
                            <pre>{settings.methods[selectedMethod].details}</pre>
                          </div>
                          <p className="instruction-footnote">
                            <Info size={12} /> Your order is held as <strong>Awaiting payment</strong>. Staff mark it paid when you pay.
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
                                Transfer reference (optional)
                              </label>
                              <input
                                id="checkout-ref-num"
                                type="text"
                                placeholder="e.g. NCB-109284"
                                value={paymentReference}
                                onChange={(e) => setPaymentReference(e.target.value)}
                              />
                            </div>
                            <div className="input-group">
                              <label htmlFor="checkout-sender-name">
                                Name on the paying account (optional)
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
                            <Info size={12} /> Your ticket shows <strong>Pending Verification</strong> until the organiser verifies your transfer, then it turns <strong>Paid</strong>. EventBite never asks for or stores your payment-account password.
                          </p>
                        </div>
                      )}
                    </div>
                  )}
                </div>

                <button
                  type="submit"
                  className="primary-button att-place-order"
                  disabled={isSubmittingOrder || !customerName.trim() || !customerPhone.trim() || !selectedMethod}
                >
                  {isSubmittingOrder
                    ? "Placing order…"
                    : <>Place order · {formatMoney(finalTotalAmount, settings.currency)} <ArrowRight size={17} /></>}
                </button>
              </form>
            </div>

            {/* Right: Transparent Order Summary & Fee Calculation */}
            <aside className="checkout-summary-sidebar">
              <div className="panel order-summary-panel">
                <div className="panel-heading">
                  <div>
                    <span className="section-kicker">Your order</span>
                    <h3>Order summary</h3>
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
                    <span>Subtotal</span>
                    <span>{formatMoney(cartSubtotal, settings.currency)}</span>
                  </div>
                  {effectivePackagingFee > 0 && (
                    <div className="calc-row">
                      <span>Packaging fee</span>
                      <span>{formatMoney(effectivePackagingFee, settings.currency)}</span>
                    </div>
                  )}
                  <div className="calc-row total">
                    <strong>Total</strong>
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
        <IslandEventDigitalTicketScreen
          ticket={issuedTicket}
          eventDetails={allEvents?.find((e) => e.id === issuedTicket.eventId) || eventDetails}
          onReturnToTickets={() => {
            setActiveStep(isLoggedIn ? "orders_wallet" : "discovery");
          }}
          onViewEventDetails={(eventId) => {
            if (eventId && allEvents && onSelectEvent) {
              const idx = allEvents.findIndex((e) => e.id === eventId);
              if (idx >= 0) onSelectEvent(idx);
            }
            setActiveStep("event_details");
          }}
          onTransferTicket={(transferredTicket, recName, recEmail) => {
            transferredTicket.isTransferred = true;
            transferredTicket.transferredTo = `${recName} (${recEmail})`;
            transferredTicket.transferredAt = new Date().toISOString();
            transferredTicket.notes = `Transferred to ${recName} on ${new Date().toLocaleDateString()}`;
            setIssuedTicket({ ...transferredTicket });
            showToast(`Ticket #${transferredTicket.ticketCode} transferred to ${recName}`);
          }}
          formatMoney={formatMoney}
          isLoggedIn={isLoggedIn}
          currentUser={attendeeProfile}
        />
      )}


      {/* STEP 4: MY DIGITAL TICKETS & ORDER HISTORY (ATTENDEE WALLET) */}
      {(activeStep as string) === "orders_wallet" && (
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
        <IslandEventUserProfileScreen
          currentUser={attendeeProfile}
          attendeeProfile={attendeeProfile}
          orders={orders}
          allEvents={allEvents}
          notifications={_notificationLogs}
          formatMoney={formatMoney}
          onNavigateToTickets={() => {
            setDiscoveryBottomNav("tickets");
            setActiveStep("orders_wallet");
          }}
          onNavigateToFood={() => {
            setDiscoveryBottomNav("food");
            setActiveStep("menu");
          }}
          onNavigateToEventDetails={(eventId) => {
            const idx = allEvents?.findIndex((e) => e.id === eventId);
            if (idx !== undefined && idx >= 0 && onSelectEvent) onSelectEvent(idx);
            setActiveStep("event_details");
          }}
          onBackToDiscovery={() => {
            setDiscoveryBottomNav("home");
            setActiveStep("discovery");
          }}
          onLogout={onLogout || (() => {})}
          onProfileUpdated={(updated) => {
            if (attendeeProfile) {
              Object.assign(attendeeProfile, updated);
            }
          }}
        />
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
        </>
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
