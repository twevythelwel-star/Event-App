import React, { useState, useMemo } from "react";
import {
  ArrowLeft,
  ShoppingBag,
  Sliders,
  Plus,
  Minus,
  Check,
  X,
  Heart,
  Trash2,
  ChevronRight,
  Sparkles,
  UtensilsCrossed,
  Filter,
} from "lucide-react";
import { Currency, EventDetails, MenuItem, Settings } from "../types";

export type CartItem = {
  menuItemId: string;
  name: string;
  pricingType: "fixed" | "customizable" | "custom_amount";
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

type Props = {
  eventDetails: EventDetails;
  menu: MenuItem[];
  settings: Settings;
  cart: CartItem[];
  onAddToCart: (item: MenuItem, qty?: number) => void;
  onUpdateCartQuantity: (index: number, delta: number) => void;
  onRemoveCartItem: (index: number) => void;
  onAddCustomizedItem: (
    item: MenuItem,
    chosenChoices: Record<string, number>,
    instructions: string,
    quantity: number
  ) => void;
  onAddDonationItem: (item: MenuItem, amount: number) => void;
  onBack: () => void;
  onProceedToCheckout: () => void;
  formatMoney: (amount: number, currency: Currency) => string;
};

export const FoodAndDrinksScreen: React.FC<Props> = ({
  eventDetails,
  menu,
  settings,
  cart,
  onAddToCart,
  onUpdateCartQuantity,
  onRemoveCartItem,
  onAddCustomizedItem,
  onAddDonationItem,
  onBack,
  onProceedToCheckout,
  formatMoney,
}) => {
  const [selectedCategory, setSelectedCategory] = useState<string>("All");
  const [customizingItem, setCustomizingItem] = useState<MenuItem | null>(null);
  const [chosenOptionChoices, setChosenOptionChoices] = useState<Record<string, number>>({});
  const [itemSpecialInstructions, setItemSpecialInstructions] = useState("");
  const [customizingQuantity, setCustomizingQuantity] = useState(1);
  const [isCartDrawerOpen, setIsCartDrawerOpen] = useState(false);

  // Donation item modal state
  const [donationItem, setDonationItem] = useState<MenuItem | null>(null);
  const [donationAmount, setDonationAmount] = useState<number>(10);

  // Derive categories dynamically from actual host-configured menu items
  const categories = useMemo(() => {
    const rawCategories = Array.from(new Set(menu.map((m) => m.category))).filter(Boolean);
    // Order standard categories logically if present, followed by any custom categories
    const priority = ["Food", "Mains", "Drinks", "Beverages", "Sides", "Sides & Extras", "Snacks", "Desserts", "Community Donations"];
    const sorted = [...rawCategories].sort((a, b) => {
      const idxA = priority.indexOf(a);
      const idxB = priority.indexOf(b);
      if (idxA !== -1 && idxB !== -1) return idxA - idxB;
      if (idxA !== -1) return -1;
      if (idxB !== -1) return 1;
      return a.localeCompare(b);
    });
    return ["All", ...sorted];
  }, [menu]);

  // Filter real menu items
  const filteredMenu = useMemo(() => {
    if (selectedCategory === "All") return menu;
    return menu.filter((item) => item.category === selectedCategory);
  }, [menu, selectedCategory]);

  const getItemBasePrice = (item: MenuItem): number =>
    settings.currency === "JMD" ? item.priceJMD : item.priceUSD;

  // Cart calculations
  const cartItemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
  const cartSubtotal = cart.reduce(
    (sum, item) => sum + item.finalItemPrice * item.quantity,
    0
  );
  const packagingFee =
    settings.currency === "JMD"
      ? eventDetails.packagingFeeJMD || 0
      : eventDetails.packagingFeeUSD || 0;
  const grandTotal = cartSubtotal + (cart.length > 0 ? packagingFee : 0);

  // Customization helpers
  function openCustomization(item: MenuItem) {
    const initialChoices: Record<string, number> = {};
    if (item.customOptionGroups) {
      item.customOptionGroups.forEach((group) => {
        initialChoices[group.id] = 0; // Default to first option
      });
    }
    setChosenOptionChoices(initialChoices);
    setItemSpecialInstructions("");
    setCustomizingQuantity(1);
    setCustomizingItem(item);
  }

  function handleSaveCustomization() {
    if (!customizingItem) return;
    onAddCustomizedItem(
      customizingItem,
      chosenOptionChoices,
      itemSpecialInstructions.trim(),
      customizingQuantity
    );
    setCustomizingItem(null);
  }

  // Calculate live price of customizing item
  const customizationPriceSummary = useMemo(() => {
    if (!customizingItem) return { base: 0, extras: 0, totalPerUnit: 0, total: 0 };
    const base = getItemBasePrice(customizingItem);
    let extras = 0;
    if (customizingItem.customOptionGroups) {
      customizingItem.customOptionGroups.forEach((group) => {
        const choiceIdx = chosenOptionChoices[group.id] ?? 0;
        const choice = group.choices[choiceIdx];
        if (choice) {
          extras += settings.currency === "JMD" ? choice.extraPriceJMD : choice.extraPriceUSD;
        }
      });
    }
    const totalPerUnit = base + extras;
    return {
      base,
      extras,
      totalPerUnit,
      total: totalPerUnit * customizingQuantity,
    };
  }, [customizingItem, chosenOptionChoices, customizingQuantity, settings.currency]);

  return (
    <div
      className="food-and-drinks-container"
      style={{
        minHeight: "100vh",
        background: "var(--att-bg)",
        color: "var(--att-text-1)",
        paddingBottom: "140px",
        fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
      }}
    >
      {/* 1. HEADER */}
      <header
        style={{
          position: "sticky",
          top: 0,
          zIndex: 40,
          background: "rgba(10, 15, 22, 0.94)",
          backdropFilter: "blur(14px)",
          borderBottom: "1px solid rgba(255, 255, 255, 0.08)",
          padding: "16px 20px",
        }}
      >
        <div
          style={{
            maxWidth: "1140px",
            margin: "0 auto",
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            gap: "14px",
          }}
        >
          {/* Back button */}
          <button
            type="button"
            onClick={onBack}
            id="food-screen-back-btn"
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "8px",
              background: "rgba(255, 255, 255, 0.06)",
              border: "1px solid rgba(255, 255, 255, 0.12)",
              color: "var(--att-text-1)",
              padding: "8px 16px",
              borderRadius: "9999px",
              fontSize: "13px",
              fontWeight: 600,
              cursor: "pointer",
              transition: "all 0.2s ease",
            }}
          >
            <ArrowLeft size={16} />
            <span>Back</span>
          </button>

          {/* Title and Event name */}
          <div style={{ textAlign: "center", flex: 1, minWidth: 0 }}>
            <h1
              id="food-screen-title"
              style={{
                fontSize: "20px",
                fontWeight: 800,
                color: "#FFFFFF",
                margin: 0,
                letterSpacing: "-0.02em",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                gap: "8px",
              }}
            >
              <UtensilsCrossed size={18} style={{ color: "var(--att-accent)" }} />
              Food & Drinks
            </h1>
            <p
              style={{
                fontSize: "12px",
                color: "var(--att-text-2)",
                margin: "2px 0 0",
                whiteSpace: "nowrap",
                overflow: "hidden",
                textOverflow: "ellipsis",
                maxWidth: "340px",
              }}
            >
              {eventDetails.title}
            </p>
          </div>

          {/* Shopping Cart Header Access */}
          <button
            type="button"
            onClick={() => setIsCartDrawerOpen(true)}
            id="food-screen-cart-header-btn"
            aria-label={`Shopping cart with ${cartItemCount} items`}
            style={{
              position: "relative",
              display: "inline-flex",
              alignItems: "center",
              gap: "8px",
              background: cartItemCount > 0 ? "rgba(182, 240, 58, 0.16)" : "rgba(255, 255, 255, 0.06)",
              border: cartItemCount > 0 ? "1px solid rgba(182, 240, 58, 0.4)" : "1px solid rgba(255, 255, 255, 0.12)",
              color: cartItemCount > 0 ? "var(--att-accent)" : "var(--att-text-1)",
              padding: "8px 16px",
              borderRadius: "9999px",
              fontSize: "13px",
              fontWeight: 700,
              cursor: "pointer",
              transition: "all 0.2s ease",
            }}
          >
            <ShoppingBag size={17} />
            <span className="cart-header-text">Cart</span>
            {cartItemCount > 0 && (
              <span
                id="cart-header-badge"
                style={{
                  background: "var(--att-accent)",
                  color: "var(--att-on-accent)",
                  fontSize: "11px",
                  fontWeight: 800,
                  padding: "1px 7px",
                  borderRadius: "9999px",
                  marginLeft: "2px",
                }}
              >
                {cartItemCount}
              </span>
            )}
          </button>
        </div>
      </header>

      {/* Main Content Area */}
      <main style={{ maxWidth: "1140px", margin: "0 auto", padding: "24px 20px" }}>
        {/* Intro Banner */}
        <div
          style={{
            background: "linear-gradient(135deg, rgba(30, 41, 59, 0.7) 0%, rgba(10, 15, 22, 0.8) 100%)",
            border: "1px solid rgba(255, 255, 255, 0.08)",
            borderRadius: "16px",
            padding: "18px 22px",
            marginBottom: "24px",
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            flexWrap: "wrap",
            gap: "14px",
          }}
        >
          <div style={{ display: "flex", alignItems: "center", gap: "12px" }}>
            <div
              style={{
                width: "40px",
                height: "40px",
                borderRadius: "12px",
                background: "rgba(182, 240, 58, 0.15)",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                color: "var(--att-accent)",
              }}
            >
              <Sparkles size={20} />
            </div>
            <div>
              <h2 style={{ fontSize: "15px", fontWeight: 700, color: "#FFFFFF", margin: 0 }}>
                Authentic Island Kitchen & Bar
              </h2>
              <p style={{ fontSize: "12px", color: "var(--att-text-2)", margin: "2px 0 0" }}>
                Pre-order your festival favorites for expedited express pickup at the kitchen station.
              </p>
            </div>
          </div>
          <div
            style={{
              fontSize: "12px",
              color: "var(--att-accent)",
              background: "rgba(182, 240, 58, 0.1)",
              border: "1px solid rgba(182, 240, 58, 0.25)",
              padding: "4px 12px",
              borderRadius: "9999px",
              fontWeight: 600,
            }}
          >
            Prices in {settings.currency === "JMD" ? "JMD (J$)" : "USD ($)"}
          </div>
        </div>

        {/* 2. CATEGORIES FILTER TABS */}
        <section style={{ marginBottom: "28px" }} aria-label="Menu categories">
          <div
            style={{
              display: "flex",
              alignItems: "center",
              gap: "8px",
              overflowX: "auto",
              paddingBottom: "8px",
              scrollbarWidth: "none",
            }}
            id="category-tabs-container"
          >
            <div style={{ display: "flex", alignItems: "center", color: "var(--att-text-3)", marginRight: "4px" }}>
              <Filter size={14} />
            </div>
            {categories.map((cat) => {
              const count = cat === "All" ? menu.length : menu.filter((m) => m.category === cat).length;
              const isActive = selectedCategory === cat;
              return (
                <button
                  key={cat}
                  type="button"
                  id={`cat-tab-${cat.toLowerCase().replace(/[^a-z0-9]/g, "-")}`}
                  onClick={() => setSelectedCategory(cat)}
                  style={{
                    padding: "9px 18px",
                    borderRadius: "9999px",
                    fontSize: "13px",
                    fontWeight: 700,
                    cursor: "pointer",
                    whiteSpace: "nowrap",
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "8px",
                    transition: "all 0.2s ease",
                    background: isActive
                      ? "var(--att-accent)"
                      : "var(--att-surface-1)",
                    color: isActive ? "var(--att-on-accent)" : "var(--att-text-2)",
                    border: isActive
                      ? "1px solid transparent"
                      : "1px solid rgba(255, 255, 255, 0.08)",
                    boxShadow: isActive ? "none" : "none",
                  }}
                >
                  <span>{cat}</span>
                  <span
                    style={{
                      fontSize: "11px",
                      opacity: isActive ? 0.9 : 0.6,
                      background: isActive ? "rgba(0,0,0,0.2)" : "rgba(255,255,255,0.06)",
                      padding: "1px 6px",
                      borderRadius: "9999px",
                    }}
                  >
                    {count}
                  </span>
                </button>
              );
            })}
          </div>
        </section>

        {/* 3. MENU CARDS GRID */}
        <section aria-label="Menu items grid">
          {filteredMenu.length === 0 ? (
            <div
              style={{
                textAlign: "center",
                padding: "60px 20px",
                background: "var(--att-surface-1)",
                borderRadius: "18px",
                border: "1px solid rgba(255, 255, 255, 0.08)",
              }}
            >
              <UtensilsCrossed size={36} style={{ color: "var(--att-text-3)", marginBottom: "12px" }} />
              <h3 style={{ fontSize: "16px", fontWeight: 700, color: "var(--att-text-1)" }}>
                No dishes found in this category
              </h3>
              <p style={{ fontSize: "13px", color: "var(--att-text-2)", marginTop: "4px" }}>
                Select a different category or choose "All" to view all configured dishes.
              </p>
            </div>
          ) : (
            <div
              style={{
                display: "grid",
                gridTemplateColumns: "repeat(auto-fill, minmax(min(320px, 100%), 1fr))",
                gap: "20px",
              }}
              id="menu-cards-grid"
              className="att-menu-grid"
            >
              {filteredMenu.map((item, itemIndex) => {
                const basePrice = getItemBasePrice(item);
                const isCustomizable = item.pricingType === "customizable";
                const isCustomAmount = item.pricingType === "custom_amount";
                const isSoldOut = item.availableStock !== undefined && item.availableStock <= 0;
                const isLowStock =
                  item.availableStock !== undefined && item.availableStock > 0 && item.availableStock <= 10;

                // Check cart item quantity for fixed items
                const inCartQty = cart
                  .filter((ci) => ci.menuItemId === item.id)
                  .reduce((sum, ci) => sum + ci.quantity, 0);

                return (
                  <article
                    key={item.id}
                    id={`menu-card-${item.id}`}
                    className="att-menu-card"
                    style={{
                      background: "var(--att-surface-1)",
                      border: "1px solid rgba(255, 255, 255, 0.08)",
                      borderRadius: "18px",
                      overflow: "hidden",
                      display: "flex",
                      flexDirection: "column",
                      transition: "transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease",
                      opacity: isSoldOut ? 0.65 : 1,
                    }}
                  >
                    {/* Food Photograph */}
                    <div
                      className="att-menu-photo"
                      style={{
                        position: "relative",
                        width: "100%",
                        aspectRatio: "4/3",
                        background: "var(--att-surface-2)",
                        overflow: "hidden",
                      }}
                    >
                      <img
                        src={item.imageUrl}
                        alt={item.name}
                        loading={itemIndex < 4 ? "eager" : "lazy"}
                        style={{
                          width: "100%",
                          height: "100%",
                          objectFit: "cover",
                          transition: "transform 0.3s ease",
                        }}
                        onError={(e) => {
                          (e.currentTarget as HTMLImageElement).src = "/images/escovitch_fish.jpg";
                        }}
                      />
                      {/* Category Pill Tag */}
                      <span
                        className="att-menu-cat"
                        style={{
                          position: "absolute",
                          top: "12px",
                          left: "12px",
                          background: "rgba(10, 15, 22, 0.85)",
                          backdropFilter: "blur(8px)",
                          color: "var(--att-text-1)",
                          fontSize: "11px",
                          fontWeight: 700,
                          padding: "4px 10px",
                          borderRadius: "9999px",
                          border: "1px solid rgba(255, 255, 255, 0.12)",
                        }}
                      >
                        {item.category}
                      </span>

                      {/* Availability Tag */}
                      <span
                        className={`att-menu-stock${isSoldOut || isLowStock ? "" : " is-available"}`}
                        style={{
                          position: "absolute",
                          top: "12px",
                          right: "12px",
                          fontSize: "11px",
                          fontWeight: 700,
                          padding: "4px 10px",
                          borderRadius: "9999px",
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "5px",
                          background: isSoldOut
                            ? "rgba(239, 68, 68, 0.9)"
                            : isLowStock
                            ? "rgba(245, 158, 11, 0.9)"
                            : "rgba(34, 197, 94, 0.85)",
                          color: "#FFFFFF",
                          backdropFilter: "blur(6px)",
                        }}
                      >
                        <span
                          style={{
                            width: "6px",
                            height: "6px",
                            borderRadius: "50%",
                            background: "#FFFFFF",
                          }}
                        />
                        {isSoldOut
                          ? "Sold Out"
                          : isLowStock
                          ? `${item.availableStock} left`
                          : "Available"}
                      </span>
                    </div>

                    {/* Card Content Body */}
                    <div
                      className="att-menu-body"
                      style={{
                        padding: "18px",
                        display: "flex",
                        flexDirection: "column",
                        flex: 1,
                        justifyContent: "space-between",
                      }}
                    >
                      <div>
                        {/* Name and Price Header */}
                        <div
                          className="att-menu-head"
                          style={{
                            display: "flex",
                            justifyContent: "space-between",
                            alignItems: "flex-start",
                            gap: "10px",
                            marginBottom: "8px",
                          }}
                        >
                          <h3
                            style={{
                              fontSize: "16px",
                              fontWeight: 700,
                              color: "#FFFFFF",
                              margin: 0,
                              lineHeight: 1.3,
                            }}
                          >
                            {item.name}
                          </h3>
                          <div style={{ textAlign: "right", whiteSpace: "nowrap" }}>
                            <strong
                              style={{
                                fontSize: "17px",
                                fontWeight: 800,
                                color: "var(--att-text-1)",
                              }}
                            >
                              {isCustomAmount ? (
                                <span style={{ fontSize: "13px", color: "var(--att-accent)" }}>
                                  Min {formatMoney(item.customAmountConfig ? (settings.currency === "JMD" ? item.customAmountConfig.minJMD : item.customAmountConfig.minUSD) : 0, settings.currency)}
                                </span>
                              ) : (
                                <>
                                  {isCustomizable && (
                                    <span style={{ fontSize: "12px", color: "var(--att-text-2)", fontWeight: 500 }}>
                                      From{" "}
                                    </span>
                                  )}
                                  {formatMoney(basePrice, settings.currency)}
                                </>
                              )}
                            </strong>
                          </div>
                        </div>

                        {/* Short Description */}
                        <p
                          className="att-menu-desc"
                          style={{
                            fontSize: "13px",
                            color: "var(--att-text-2)",
                            lineHeight: 1.5,
                            margin: "0 0 14px",
                          }}
                        >
                          {item.description}
                        </p>

                        {/* Customization Feature Badges */}
                        {isCustomizable && item.customOptionGroups && (
                          <div
                            className="att-menu-opts"
                            style={{
                              display: "flex",
                              alignItems: "center",
                              gap: "6px",
                              background: "rgba(182, 240, 58, 0.12)",
                              border: "1px solid rgba(182, 240, 58, 0.25)",
                              borderRadius: "8px",
                              padding: "6px 10px",
                              fontSize: "11px",
                              color: "var(--att-accent)",
                              marginBottom: "16px",
                            }}
                          >
                            <Sliders size={13} style={{ color: "var(--att-accent)" }} />
                            <span>
                              Options: {item.customOptionGroups.map((g) => g.title).join(" · ")}
                            </span>
                          </div>
                        )}
                      </div>

                      {/* Card Action Control */}
                      <div className="att-menu-action" style={{ marginTop: "auto", paddingTop: "8px" }}>
                        {isSoldOut ? (
                          <button
                            type="button"
                            disabled
                            style={{
                              width: "100%",
                              padding: "11px 16px",
                              borderRadius: "12px",
                              background: "rgba(255, 255, 255, 0.05)",
                              color: "var(--att-text-3)",
                              border: "1px solid rgba(255, 255, 255, 0.08)",
                              fontSize: "13px",
                              fontWeight: 600,
                              cursor: "not-allowed",
                            }}
                          >
                            Sold out
                          </button>
                        ) : isCustomizable ? (
                          <button
                            type="button"
                            id={`btn-customize-${item.id}`}
                            onClick={() => openCustomization(item)}
                            style={{
                              width: "100%",
                              padding: "11px 16px",
                              borderRadius: "12px",
                              background: "rgba(182, 240, 58, 0.2)",
                              color: "var(--att-accent)",
                              border: "1px solid rgba(182, 240, 58, 0.4)",
                              fontSize: "13px",
                              fontWeight: 700,
                              cursor: "pointer",
                              display: "inline-flex",
                              alignItems: "center",
                              justifyContent: "center",
                              gap: "8px",
                              transition: "all 0.2s ease",
                            }}
                          >
                            <Sliders size={15} />
                            <span>Choose options</span>
                          </button>
                        ) : isCustomAmount ? (
                          <button
                            type="button"
                            id={`btn-donation-${item.id}`}
                            onClick={() => {
                              setDonationItem(item);
                              setDonationAmount(
                                item.customAmountConfig
                                  ? settings.currency === "JMD"
                                    ? item.customAmountConfig.minJMD
                                    : item.customAmountConfig.minUSD
                                  : 10
                              );
                            }}
                            style={{
                              width: "100%",
                              padding: "11px 16px",
                              borderRadius: "12px",
                              background: "rgba(182, 240, 58, 0.2)",
                              color: "var(--att-text-2)",
                              border: "1px solid rgba(182, 240, 58, 0.4)",
                              fontSize: "13px",
                              fontWeight: 700,
                              cursor: "pointer",
                              display: "inline-flex",
                              alignItems: "center",
                              justifyContent: "center",
                              gap: "8px",
                              transition: "all 0.2s ease",
                            }}
                          >
                            <Heart size={15} />
                            <span>Choose Donation Amount</span>
                          </button>
                        ) : inCartQty > 0 ? (
                          /* Quantity Stepper when already in cart */
                          <div
                            style={{
                              display: "flex",
                              alignItems: "center",
                              justifyContent: "space-between",
                              background: "rgba(10, 15, 22, 0.8)",
                              border: "1px solid rgba(182, 240, 58, 0.35)",
                              borderRadius: "12px",
                              padding: "4px",
                            }}
                          >
                            <button
                              type="button"
                              id={`btn-decrease-qty-${item.id}`}
                              onClick={() => {
                                const idx = cart.findIndex((ci) => ci.menuItemId === item.id);
                                if (idx >= 0) onUpdateCartQuantity(idx, -1);
                              }}
                              style={{
                                width: "36px",
                                height: "36px",
                                borderRadius: "8px",
                                background: "rgba(255, 255, 255, 0.08)",
                                border: "none",
                                color: "#FFFFFF",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                cursor: "pointer",
                              }}
                              aria-label="Decrease quantity"
                            >
                              <Minus size={15} />
                            </button>
                            <span
                              style={{
                                fontSize: "14px",
                                fontWeight: 700,
                                color: "#FFFFFF",
                              }}
                            >
                              {inCartQty} in cart
                            </span>
                            <button
                              type="button"
                              id={`btn-increase-qty-${item.id}`}
                              onClick={() => onAddToCart(item)}
                              style={{
                                width: "36px",
                                height: "36px",
                                borderRadius: "8px",
                                background: "var(--att-accent)",
                                border: "none",
                                color: "var(--att-on-accent)",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                cursor: "pointer",
                              }}
                              aria-label="Increase quantity"
                            >
                              <Plus size={15} />
                            </button>
                          </div>
                        ) : (
                          /* Initial Add to Cart */
                          <button
                            type="button"
                            id={`btn-add-item-${item.id}`}
                            onClick={() => onAddToCart(item)}
                            style={{
                              width: "100%",
                              padding: "11px 16px",
                              borderRadius: "12px",
                              background: "var(--att-accent)",
                              color: "var(--att-on-accent)",
                              border: "none",
                              fontSize: "13px",
                              fontWeight: 700,
                              cursor: "pointer",
                              display: "inline-flex",
                              alignItems: "center",
                              justifyContent: "center",
                              gap: "8px",
                              boxShadow: "none",
                              transition: "all 0.2s ease",
                            }}
                          >
                            <Plus size={15} />
                            <span>Add</span>
                          </button>
                        )}
                      </div>
                    </div>
                  </article>
                );
              })}
            </div>
          )}
        </section>
      </main>

      {/* 4. CUSTOMIZATION MODAL / ACCESSIBLE DETAIL PANEL */}
      {customizingItem && (
        <div
          className="customization-dialog-backdrop"
          style={{
            position: "fixed",
            inset: 0,
            zIndex: 60,
            background: "rgba(5, 10, 20, 0.8)",
            backdropFilter: "blur(8px)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            padding: "16px",
          }}
          onClick={() => setCustomizingItem(null)}
        >
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="customization-modal-title"
            style={{
              background: "var(--att-surface-1)",
              border: "1px solid rgba(255, 255, 255, 0.12)",
              borderRadius: "20px",
              width: "100%",
              maxWidth: "520px",
              maxHeight: "90vh",
              overflowY: "auto",
              boxShadow: "0 20px 40px rgba(0, 0, 0, 0.5)",
              color: "var(--att-text-1)",
            }}
            onClick={(e) => e.stopPropagation()}
            id="customization-dialog-panel"
          >
            {/* Modal Header with Food Artwork */}
            <div
              style={{
                position: "relative",
                height: "140px",
                overflow: "hidden",
                borderTopLeftRadius: "20px",
                borderTopRightRadius: "20px",
              }}
            >
              <img
                src={customizingItem.imageUrl}
                alt={customizingItem.name}
                style={{ width: "100%", height: "100%", objectFit: "cover" }}
              />
              <div
                style={{
                  position: "absolute",
                  inset: 0,
                  background: "linear-gradient(180deg, rgba(10, 15, 22,0.2) 0%, rgba(10, 15, 22,0.95) 100%)",
                }}
              />
              <button
                type="button"
                onClick={() => setCustomizingItem(null)}
                style={{
                  position: "absolute",
                  top: "14px",
                  right: "14px",
                  width: "32px",
                  height: "32px",
                  borderRadius: "50%",
                  background: "rgba(0,0,0,0.5)",
                  border: "none",
                  color: "#FFFFFF",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                }}
                aria-label="Close customization"
              >
                <X size={18} />
              </button>
              <div
                style={{
                  position: "absolute",
                  bottom: "12px",
                  left: "18px",
                  right: "18px",
                }}
              >
                <span
                  style={{
                    fontSize: "11px",
                    fontWeight: 800,
                    color: "var(--att-accent)",
                    textTransform: "uppercase",
                    letterSpacing: "0.06em",
                  }}
                >
                  Customize Your Dish
                </span>
                <h2
                  id="customization-modal-title"
                  style={{
                    fontSize: "18px",
                    fontWeight: 800,
                    color: "#FFFFFF",
                    margin: "2px 0 0",
                  }}
                >
                  {customizingItem.name}
                </h2>
              </div>
            </div>

            {/* Modal Body */}
            <div style={{ padding: "20px" }}>
              {/* Option Groups (Portion, Sides, Substitutions, Add-ons) */}
              {customizingItem.customOptionGroups?.map((group) => {
                const selectedIdx = chosenOptionChoices[group.id] ?? 0;
                return (
                  <div key={group.id} style={{ marginBottom: "20px" }}>
                    <div
                      style={{
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                        marginBottom: "10px",
                      }}
                    >
                      <label
                        style={{
                          fontSize: "13px",
                          fontWeight: 700,
                          color: "var(--att-text-1)",
                          display: "flex",
                          alignItems: "center",
                          gap: "6px",
                        }}
                      >
                        {group.title}
                        {group.required && (
                          <span
                            style={{
                              fontSize: "10px",
                              color: "var(--att-text-2)",
                              background: "rgba(182, 240, 58, 0.15)",
                              padding: "1px 6px",
                              borderRadius: "9999px",
                            }}
                          >
                            Required
                          </span>
                        )}
                      </label>
                      <span style={{ fontSize: "11px", color: "var(--att-text-3)" }}>Choose 1</span>
                    </div>

                    <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
                      {group.choices.map((choice, cIdx) => {
                        const isSelected = selectedIdx === cIdx;
                        const extra =
                          settings.currency === "JMD" ? choice.extraPriceJMD : choice.extraPriceUSD;

                        return (
                          <button
                            key={cIdx}
                            type="button"
                            onClick={() =>
                              setChosenOptionChoices((prev) => ({
                                ...prev,
                                [group.id]: cIdx,
                              }))
                            }
                            style={{
                              display: "flex",
                              alignItems: "center",
                              justifyContent: "space-between",
                              padding: "10px 14px",
                              borderRadius: "12px",
                              background: isSelected
                                ? "rgba(182, 240, 58, 0.18)"
                                : "rgba(255, 255, 255, 0.04)",
                              border: isSelected
                                ? "1.5px solid var(--att-accent)"
                                : "1px solid rgba(255, 255, 255, 0.08)",
                              color: isSelected ? "#FFFFFF" : "var(--att-text-body)",
                              cursor: "pointer",
                              textAlign: "left",
                              transition: "all 0.15s ease",
                            }}
                          >
                            <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                              <div
                                style={{
                                  width: "18px",
                                  height: "18px",
                                  borderRadius: "50%",
                                  border: isSelected
                                    ? "5px solid var(--att-accent)"
                                    : "2px solid rgba(255, 255, 255, 0.3)",
                                  background: isSelected ? "#FFFFFF" : "transparent",
                                }}
                              />
                              <span style={{ fontSize: "13px", fontWeight: isSelected ? 600 : 400 }}>
                                {choice.label}
                              </span>
                            </div>
                            <span
                              style={{
                                fontSize: "12px",
                                fontWeight: 700,
                                color: extra > 0 ? "var(--att-accent)" : "var(--att-ok)",
                              }}
                            >
                              {extra > 0 ? `+${formatMoney(extra, settings.currency)}` : "Included"}
                            </span>
                          </button>
                        );
                      })}
                    </div>
                  </div>
                );
              })}

              {/* Special Preparation Instructions */}
              {customizingItem.allowSpecialInstructions && (
                <div style={{ marginBottom: "20px" }}>
                  <label
                    htmlFor="custom-special-notes"
                    style={{
                      display: "block",
                      fontSize: "13px",
                      fontWeight: 700,
                      color: "var(--att-text-1)",
                      marginBottom: "6px",
                    }}
                  >
                    Special Preparation Instructions (Optional)
                  </label>
                  <textarea
                    id="custom-special-notes"
                    rows={2}
                    placeholder="e.g. Extra scotch bonnet sauce on side, rice well done..."
                    value={itemSpecialInstructions}
                    onChange={(e) => setItemSpecialInstructions(e.target.value)}
                    style={{
                      width: "100%",
                      padding: "10px 14px",
                      borderRadius: "12px",
                      background: "rgba(10, 15, 22, 0.8)",
                      border: "1px solid rgba(255, 255, 255, 0.12)",
                      color: "#FFFFFF",
                      fontSize: "13px",
                      resize: "none",
                      outline: "none",
                      fontFamily: "inherit",
                      boxSizing: "border-box",
                    }}
                  />
                </div>
              )}

              {/* Quantity Limit Selector */}
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  background: "rgba(10, 15, 22, 0.5)",
                  border: "1px solid rgba(255, 255, 255, 0.08)",
                  borderRadius: "14px",
                  padding: "12px 16px",
                  marginBottom: "20px",
                }}
              >
                <div>
                  <div style={{ fontSize: "13px", fontWeight: 700, color: "#FFFFFF" }}>
                    Quantity
                  </div>
                  <div style={{ fontSize: "11px", color: "var(--att-text-2)" }}>
                    Max 10 per customized order
                  </div>
                </div>
                <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                  <button
                    type="button"
                    disabled={customizingQuantity <= 1}
                    onClick={() => setCustomizingQuantity((q) => Math.max(1, q - 1))}
                    style={{
                      width: "34px",
                      height: "34px",
                      borderRadius: "8px",
                      background: "rgba(255, 255, 255, 0.08)",
                      border: "none",
                      color: "#FFFFFF",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      cursor: customizingQuantity <= 1 ? "not-allowed" : "pointer",
                      opacity: customizingQuantity <= 1 ? 0.4 : 1,
                    }}
                    aria-label="Decrease quantity"
                  >
                    <Minus size={15} />
                  </button>
                  <span style={{ fontSize: "15px", fontWeight: 800, minWidth: "24px", textAlign: "center" }}>
                    {customizingQuantity}
                  </span>
                  <button
                    type="button"
                    disabled={customizingQuantity >= 10}
                    onClick={() => setCustomizingQuantity((q) => Math.min(10, q + 1))}
                    style={{
                      width: "34px",
                      height: "34px",
                      borderRadius: "8px",
                      background: "rgba(255, 255, 255, 0.08)",
                      border: "none",
                      color: "#FFFFFF",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      cursor: customizingQuantity >= 10 ? "not-allowed" : "pointer",
                      opacity: customizingQuantity >= 10 ? 0.4 : 1,
                    }}
                    aria-label="Increase quantity"
                  >
                    <Plus size={15} />
                  </button>
                </div>
              </div>

              {/* Price Summary Breakdown inside Modal */}
              <div
                style={{
                  background: "rgba(10, 15, 22, 0.6)",
                  border: "1px solid rgba(255, 255, 255, 0.08)",
                  borderRadius: "12px",
                  padding: "12px 16px",
                  marginBottom: "20px",
                }}
              >
                <div style={{ display: "flex", justifyContent: "space-between", fontSize: "12px", color: "var(--att-text-2)", marginBottom: "4px" }}>
                  <span>Base unit price:</span>
                  <span>{formatMoney(customizationPriceSummary.base, settings.currency)}</span>
                </div>
                {customizationPriceSummary.extras > 0 && (
                  <div style={{ display: "flex", justifyContent: "space-between", fontSize: "12px", color: "var(--att-text-2)", marginBottom: "4px" }}>
                    <span>Selected options extra:</span>
                    <span>+{formatMoney(customizationPriceSummary.extras, settings.currency)}</span>
                  </div>
                )}
                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    fontSize: "14px",
                    fontWeight: 700,
                    color: "#FFFFFF",
                    paddingTop: "6px",
                    borderTop: "1px solid rgba(255, 255, 255, 0.08)",
                  }}
                >
                  <span>Item Subtotal ({customizingQuantity}x):</span>
                  <span style={{ color: "var(--att-accent)" }}>
                    {formatMoney(customizationPriceSummary.total, settings.currency)}
                  </span>
                </div>
                <div style={{ fontSize: "10px", color: "var(--att-text-3)", marginTop: "4px" }}>
                  *Prices validated securely by server at checkout.
                </div>
              </div>

              {/* Submit Customized Item Button */}
              <button
                type="button"
                id="btn-confirm-add-customized"
                onClick={handleSaveCustomization}
                style={{
                  width: "100%",
                  padding: "13px 20px",
                  borderRadius: "14px",
                  background: "var(--att-accent)",
                  color: "var(--att-on-accent)",
                  border: "none",
                  fontSize: "14px",
                  fontWeight: 800,
                  cursor: "pointer",
                  display: "inline-flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "8px",
                  boxShadow: "none",
                }}
              >
                <Check size={16} />
                <span>
                  Add {customizingQuantity} to Cart (
                  {formatMoney(customizationPriceSummary.total, settings.currency)})
                </span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 5. DONATION ITEM MODAL */}
      {donationItem && donationItem.customAmountConfig && (
        <div
          style={{
            position: "fixed",
            inset: 0,
            zIndex: 60,
            background: "rgba(5, 10, 20, 0.8)",
            backdropFilter: "blur(8px)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            padding: "16px",
          }}
          onClick={() => setDonationItem(null)}
        >
          <div
            style={{
              background: "var(--att-surface-1)",
              border: "1px solid rgba(255, 255, 255, 0.12)",
              borderRadius: "20px",
              width: "100%",
              maxWidth: "460px",
              padding: "24px",
              color: "var(--att-text-1)",
            }}
            onClick={(e) => e.stopPropagation()}
            id="donation-modal-panel"
          >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "16px" }}>
              <div>
                <span style={{ fontSize: "11px", fontWeight: 700, color: "var(--att-text-2)" }}>
                  COMMUNITY CONTRIBUTION
                </span>
                <h3 style={{ fontSize: "17px", fontWeight: 700, margin: "2px 0 0", color: "#FFFFFF" }}>
                  {donationItem.name}
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setDonationItem(null)}
                style={{
                  background: "transparent",
                  border: "none",
                  color: "var(--att-text-2)",
                  cursor: "pointer",
                }}
              >
                <X size={18} />
              </button>
            </div>
            <p style={{ fontSize: "13px", color: "var(--att-text-2)", lineHeight: 1.5, marginBottom: "16px" }}>
              {donationItem.description}
            </p>

            {/* Suggested Amounts Pills */}
            <div style={{ display: "flex", gap: "8px", flexWrap: "wrap", marginBottom: "16px" }}>
              {(settings.currency === "JMD"
                ? donationItem.customAmountConfig.suggestedAmountsJMD
                : donationItem.customAmountConfig.suggestedAmountsUSD
              ).map((amt) => (
                <button
                  key={amt}
                  type="button"
                  onClick={() => setDonationAmount(amt)}
                  style={{
                    padding: "8px 14px",
                    borderRadius: "9999px",
                    background: donationAmount === amt ? "var(--att-text-2)" : "rgba(255,255,255,0.06)",
                    border: donationAmount === amt ? "1px solid var(--att-text-2)" : "1px solid rgba(255,255,255,0.1)",
                    color: "#FFFFFF",
                    fontSize: "12px",
                    fontWeight: 700,
                    cursor: "pointer",
                  }}
                >
                  {formatMoney(amt, settings.currency)}
                </button>
              ))}
            </div>

            {/* Custom Input */}
            <div style={{ marginBottom: "20px" }}>
              <label style={{ fontSize: "12px", color: "var(--att-text-2)", display: "block", marginBottom: "6px" }}>
                Enter Custom Amount ({settings.currency}):
              </label>
              <input
                type="number"
                min={settings.currency === "JMD" ? donationItem.customAmountConfig.minJMD : donationItem.customAmountConfig.minUSD}
                max={settings.currency === "JMD" ? donationItem.customAmountConfig.maxJMD : donationItem.customAmountConfig.maxUSD}
                value={donationAmount}
                onChange={(e) => setDonationAmount(Number(e.target.value))}
                style={{
                  width: "100%",
                  padding: "10px 14px",
                  borderRadius: "12px",
                  background: "rgba(10, 15, 22, 0.8)",
                  border: "1px solid rgba(255, 255, 255, 0.12)",
                  color: "#FFFFFF",
                  fontSize: "16px",
                  fontWeight: 700,
                  outline: "none",
                  boxSizing: "border-box",
                }}
              />
            </div>

            <button
              type="button"
              onClick={() => {
                onAddDonationItem(donationItem, donationAmount);
                setDonationItem(null);
              }}
              style={{
                width: "100%",
                padding: "12px 18px",
                borderRadius: "14px",
                background: "var(--att-accent)",
                color: "var(--att-on-accent)",
                border: "none",
                fontSize: "14px",
                fontWeight: 700,
                cursor: "pointer",
              }}
            >
              Add Contribution ({formatMoney(donationAmount, settings.currency)})
            </button>
          </div>
        </div>
      )}

      {/* 6. SLIDE-OUT CART DRAWER */}
      {isCartDrawerOpen && (
        <div
          style={{
            position: "fixed",
            inset: 0,
            zIndex: 70,
            background: "rgba(5, 10, 20, 0.7)",
            backdropFilter: "blur(6px)",
            display: "flex",
            justifyContent: "flex-end",
          }}
          onClick={() => setIsCartDrawerOpen(false)}
        >
          <div
            style={{
              width: "100%",
              maxWidth: "420px",
              height: "100%",
              background: "var(--att-surface-1)",
              borderLeft: "1px solid rgba(255, 255, 255, 0.1)",
              display: "flex",
              flexDirection: "column",
              color: "var(--att-text-1)",
            }}
            onClick={(e) => e.stopPropagation()}
            id="shopping-cart-drawer"
          >
            {/* Drawer Header */}
            <div
              style={{
                padding: "20px",
                borderBottom: "1px solid rgba(255, 255, 255, 0.08)",
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
              }}
            >
              <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                <ShoppingBag size={20} style={{ color: "var(--att-accent)" }} />
                <h3 style={{ fontSize: "17px", fontWeight: 700, margin: 0, color: "#FFFFFF" }}>
                  Your Culinary Cart
                </h3>
                <span
                  style={{
                    background: "rgba(182, 240, 58, 0.2)",
                    color: "var(--att-accent)",
                    fontSize: "11px",
                    fontWeight: 700,
                    padding: "2px 8px",
                    borderRadius: "9999px",
                  }}
                >
                  {cartItemCount}
                </span>
              </div>
              <button
                type="button"
                onClick={() => setIsCartDrawerOpen(false)}
                id="close-cart-drawer-btn"
                style={{
                  background: "transparent",
                  border: "none",
                  color: "var(--att-text-2)",
                  cursor: "pointer",
                }}
              >
                <X size={20} />
              </button>
            </div>

            {/* Drawer Items List */}
            <div style={{ flex: 1, overflowY: "auto", padding: "20px" }}>
              {cart.length === 0 ? (
                <div style={{ textAlign: "center", padding: "60px 0" }}>
                  <ShoppingBag size={40} style={{ color: "var(--att-text-3)", marginBottom: "12px" }} />
                  <p style={{ fontSize: "14px", color: "var(--att-text-2)" }}>Your food cart is currently empty.</p>
                  <button
                    type="button"
                    onClick={() => setIsCartDrawerOpen(false)}
                    style={{
                      marginTop: "12px",
                      padding: "8px 16px",
                      borderRadius: "9999px",
                      background: "rgba(255,255,255,0.06)",
                      border: "1px solid rgba(255,255,255,0.1)",
                      color: "#FFFFFF",
                      fontSize: "12px",
                      cursor: "pointer",
                    }}
                  >
                    Browse Menu
                  </button>
                </div>
              ) : (
                <div style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
                  {cart.map((cartItem, idx) => {
                    return (
                      <div
                        key={`${cartItem.menuItemId}-${idx}`}
                        style={{
                          background: "var(--att-surface-2)",
                          border: "1px solid rgba(255, 255, 255, 0.08)",
                          borderRadius: "14px",
                          padding: "14px",
                        }}
                      >
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: "10px" }}>
                          <div>
                            <h4 style={{ fontSize: "14px", fontWeight: 700, color: "#FFFFFF", margin: "0 0 2px" }}>
                              {cartItem.name}
                            </h4>
                            <div style={{ fontSize: "13px", fontWeight: 700, color: "var(--att-accent)" }}>
                              {formatMoney(cartItem.finalItemPrice, settings.currency)} each
                            </div>
                          </div>
                          <button
                            type="button"
                            onClick={() => onRemoveCartItem(idx)}
                            style={{
                              background: "transparent",
                              border: "none",
                              color: "var(--att-text-3)",
                              cursor: "pointer",
                              padding: "4px",
                            }}
                            title="Remove item"
                            aria-label={`Remove ${cartItem.name}`}
                          >
                            <Trash2 size={15} />
                          </button>
                        </div>

                        {/* Options summary */}
                        {cartItem.selectedOptionsSummary && cartItem.selectedOptionsSummary.length > 0 && (
                          <div style={{ fontSize: "11px", color: "var(--att-text-2)", marginTop: "6px", lineHeight: 1.4 }}>
                            {cartItem.selectedOptionsSummary.join(" · ")}
                          </div>
                        )}
                        {cartItem.specialInstructions && (
                          <div style={{ fontSize: "11px", color: "var(--att-accent)", marginTop: "4px", fontStyle: "italic" }}>
                            Note: {cartItem.specialInstructions}
                          </div>
                        )}

                        {/* Stepper controls */}
                        <div
                          style={{
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "space-between",
                            marginTop: "12px",
                            paddingTop: "10px",
                            borderTop: "1px solid rgba(255, 255, 255, 0.06)",
                          }}
                        >
                          <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                            <button
                              type="button"
                              onClick={() => onUpdateCartQuantity(idx, -1)}
                              style={{
                                width: "28px",
                                height: "28px",
                                borderRadius: "6px",
                                background: "rgba(255, 255, 255, 0.08)",
                                border: "none",
                                color: "#FFFFFF",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                cursor: "pointer",
                              }}
                            >
                              <Minus size={13} />
                            </button>
                            <span style={{ fontSize: "13px", fontWeight: 700, minWidth: "18px", textAlign: "center" }}>
                              {cartItem.quantity}
                            </span>
                            <button
                              type="button"
                              onClick={() => onUpdateCartQuantity(idx, 1)}
                              style={{
                                width: "28px",
                                height: "28px",
                                borderRadius: "6px",
                                background: "rgba(255, 255, 255, 0.08)",
                                border: "none",
                                color: "#FFFFFF",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                cursor: "pointer",
                              }}
                            >
                              <Plus size={13} />
                            </button>
                          </div>
                          <span style={{ fontSize: "14px", fontWeight: 800, color: "#FFFFFF" }}>
                            {formatMoney(cartItem.finalItemPrice * cartItem.quantity, settings.currency)}
                          </span>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>

            {/* Drawer Footer with authorative price breakdown */}
            {cart.length > 0 && (
              <div
                style={{
                  padding: "20px",
                  borderTop: "1px solid rgba(255, 255, 255, 0.08)",
                  background: "var(--att-surface-2)",
                }}
              >
                <div style={{ display: "flex", justifyContent: "space-between", fontSize: "13px", color: "var(--att-text-2)", marginBottom: "6px" }}>
                  <span>Food Subtotal:</span>
                  <span>{formatMoney(cartSubtotal, settings.currency)}</span>
                </div>
                {packagingFee > 0 && (
                  <div style={{ display: "flex", justifyContent: "space-between", fontSize: "13px", color: "var(--att-text-2)", marginBottom: "8px" }}>
                    <span>Packaging & Station Fee:</span>
                    <span>{formatMoney(packagingFee, settings.currency)}</span>
                  </div>
                )}
                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    fontSize: "16px",
                    fontWeight: 800,
                    color: "#FFFFFF",
                    paddingTop: "8px",
                    borderTop: "1px solid rgba(255, 255, 255, 0.1)",
                    marginBottom: "16px",
                  }}
                >
                  <span>Grand Total:</span>
                  <span style={{ color: "var(--att-accent)" }}>
                    {formatMoney(grandTotal, settings.currency)}
                  </span>
                </div>
                <button
                  type="button"
                  id="btn-drawer-proceed-checkout"
                  onClick={() => {
                    setIsCartDrawerOpen(false);
                    onProceedToCheckout();
                  }}
                  style={{
                    width: "100%",
                    padding: "14px 20px",
                    borderRadius: "14px",
                    background: "var(--att-accent)",
                    color: "var(--att-on-accent)",
                    border: "none",
                    fontSize: "14px",
                    fontWeight: 800,
                    cursor: "pointer",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    gap: "8px",
                    boxShadow: "none",
                  }}
                >
                  <span>Review & Checkout</span>
                  <ChevronRight size={16} />
                </button>
              </div>
            )}
          </div>
        </div>
      )}

      {/* 7. FIXED BOTTOM CART SUMMARY BAR (MOBILE & DESKTOP) */}
      {cart.length > 0 && (
        <aside
          id="mobile-food-cart-bar"
          aria-label="Order Cart Summary"
          style={{
            position: "fixed",
            bottom: "16px",
            left: "16px",
            right: "16px",
            maxWidth: "760px",
            margin: "0 auto",
            zIndex: 50,
            background: "rgba(10, 15, 22, 0.95)",
            backdropFilter: "blur(16px)",
            border: "1px solid rgba(255, 255, 255, 0.14)",
            borderRadius: "18px",
            padding: "14px 20px",
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            gap: "14px",
            boxShadow: "0 10px 30px rgba(0, 0, 0, 0.6)",
          }}
        >
          <div
            onClick={() => setIsCartDrawerOpen(true)}
            style={{ cursor: "pointer", display: "flex", alignItems: "center", gap: "12px" }}
          >
            <div
              style={{
                width: "42px",
                height: "42px",
                borderRadius: "12px",
                background: "var(--att-accent)",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                color: "var(--att-on-accent)",
                position: "relative",
              }}
            >
              <ShoppingBag size={20} />
              <span
                style={{
                  position: "absolute",
                  top: "-4px",
                  right: "-4px",
                  background: "var(--att-accent)",
                  color: "var(--att-on-accent)",
                  fontSize: "10px",
                  fontWeight: 800,
                  width: "18px",
                  height: "18px",
                  borderRadius: "50%",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  border: "2px solid var(--att-surface-2)",
                }}
              >
                {cartItemCount}
              </span>
            </div>
            <div>
              <div style={{ fontSize: "14px", fontWeight: 800, color: "#FFFFFF" }}>
                {cartItemCount} {cartItemCount === 1 ? "Item" : "Items"} in Cart
              </div>
              <div style={{ fontSize: "12px", color: "var(--att-text-2)" }}>
                Total: <strong style={{ color: "var(--att-accent)" }}>{formatMoney(grandTotal, settings.currency)}</strong>
              </div>
            </div>
          </div>

          <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
            <button
              type="button"
              onClick={() => setIsCartDrawerOpen(true)}
              style={{
                background: "rgba(255, 255, 255, 0.08)",
                border: "1px solid rgba(255, 255, 255, 0.12)",
                color: "var(--att-text-1)",
                padding: "10px 14px",
                borderRadius: "12px",
                fontSize: "13px",
                fontWeight: 600,
                cursor: "pointer",
              }}
            >
              View Cart
            </button>
            <button
              type="button"
              id="btn-food-bar-checkout"
              onClick={onProceedToCheckout}
              style={{
                background: "var(--att-accent)",
                color: "var(--att-on-accent)",
                border: "none",
                padding: "10px 20px",
                borderRadius: "12px",
                fontSize: "13px",
                fontWeight: 800,
                cursor: "pointer",
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
                boxShadow: "none",
              }}
            >
              <span>Checkout</span>
              <ChevronRight size={15} />
            </button>
          </div>
        </aside>
      )}
    </div>
  );
};
