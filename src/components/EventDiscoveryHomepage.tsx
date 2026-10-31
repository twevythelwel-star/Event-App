import React, { useState, useMemo } from "react";
import { BrandLogo } from "./BrandLogo";
import type { LocationCatalog } from "../lib/api";
import {
  Search,
  Music,
  Utensils,
  Trophy,
  Sparkles,
  MapPin,
  Calendar,
  Clock,
  ArrowRight,
  TrendingUp,
  Home,
  Compass,
  Ticket,
  ChefHat,
  User,
  RefreshCw,
  X,
  ChevronRight,
  Flame,
} from "lucide-react";

export type DiscoveryCategory = "All" | "Music" | "Food" | "Sports" | "Culture" | "Community" | "Parties";

export type BottomNavItem = "home" | "explore" | "tickets" | "food" | "profile";

export interface EventDiscoveryHomepageProps {
  events: any[];
  markets?: LocationCatalog["markets"];
  states?: LocationCatalog["states"];
  selectedEventIndex: number;
  onSelectEvent: (index: number) => void;
  isLoggedIn: boolean;
  userName?: string;
  userEmail?: string;
  onOpenAuthModal: (mode?: "login" | "register") => void;
  onLogout?: () => void;
  onNavigateToTickets: () => void;
  onNavigateToFood: (eventIndex?: number) => void;
  onNavigateToDetails?: (eventIndex: number) => void;
  onNavigateToProfile: () => void;
  formatMoney: (amount: number, currency?: any) => string;
  activeBottomNav?: BottomNavItem;
  onChangeBottomNav?: (nav: BottomNavItem) => void;
}

export function getEventCategory(event: any, _index: number): DiscoveryCategory {
  const explicitCat = String(event.category || "").toLowerCase();
  if (explicitCat.includes("community")) return "Community";
  if (explicitCat.includes("part")) return "Parties";
  if (explicitCat.includes("music") || explicitCat.includes("concert")) return "Music";
  if (explicitCat.includes("sport")) return "Sports";
  if (explicitCat.includes("cultur")) return "Culture";
  if (explicitCat.includes("food") || explicitCat.includes("fish")) return "Food";

  const text = `${event.title} ${event.tagline || ""} ${event.description || ""} ${event.cuisine || ""}`.toLowerCase();
  if (text.includes("music") || text.includes("concert") || text.includes("band") || text.includes("blues") || text.includes("dj")) {
    return "Music";
  }
  if (text.includes("sport") || text.includes("regatta") || text.includes("cricket") || text.includes("tournament") || text.includes("match")) {
    return "Sports";
  }
  if (text.includes("heritage") || text.includes("culture") || text.includes("historic") || text.includes("arts") || text.includes("festival")) {
    return "Culture";
  }
  if (text.includes("food") || text.includes("market") || text.includes("cuisine") || text.includes("cookout")) return "Food";
  return "Community";
}

export function getEventPerformer(event: any, _index: number): string {
  return event.featuredPerformer || "Local artists and community performers";
}

export function getEventImage(event: any, _index: number): string {
  if (event.flyerUrl && event.flyerUrl.trim().length > 0) return event.flyerUrl;
  return "/images/island_festival_sunset.jpg";
}

export const EventDiscoveryHomepage: React.FC<EventDiscoveryHomepageProps> = ({
  events,
  markets = [],
  states = [],
  selectedEventIndex,
  onSelectEvent,
  isLoggedIn,
  userName = "EventBite Explorer",
  userEmail,
  onOpenAuthModal,
  onLogout,
  onNavigateToTickets,
  onNavigateToFood,
  onNavigateToDetails,
  onNavigateToProfile,
  formatMoney,
  activeBottomNav = "home",
  onChangeBottomNav,
}) => {
  // Search & Filter State
  const [searchQuery, setSearchQuery] = useState("");
  const [selectedCategory, setSelectedCategory] = useState<DiscoveryCategory>("All");
  const [selectedState, setSelectedState] = useState<string>("All");
  const [selectedCity, setSelectedCity] = useState<string>("All");
  const [zipQuery, setZipQuery] = useState("");
  const [selectedDateRange, setSelectedDateRange] = useState<string>("All");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [minPrice, setMinPrice] = useState("");
  const [maxPrice, setMaxPrice] = useState("");
  const [availableOnly, setAvailableOnly] = useState(false);
  const [nearbyPoint, setNearbyPoint] = useState<{ latitude: number; longitude: number } | null>(null);
  const [locationMessage, setLocationMessage] = useState("");
  const [visibleCount, setVisibleCount] = useState<number>(6);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [refreshKey, setRefreshKey] = useState<number>(0);

  const eventLocation = (evt: any) => {
    const addressMatch = String(evt.venueAddress || "").match(/,\s*([^,]+),\s*([A-Z]{2})\s+(\d{5}(?:-\d{4})?)/);
    return {
      city: String(evt.city || addressMatch?.[1] || ""),
      stateCode: String(evt.stateCode || addressMatch?.[2] || ""),
      zipCode: String(evt.zipCode || addressMatch?.[3] || ""),
    };
  };

  const availableStates = useMemo(() => {
    const stateNames = new Map<string, string>();
    states.forEach((state) => stateNames.set(state.code, state.name));
    markets.forEach((market) => stateNames.set(market.stateCode, market.stateName));
    events.forEach((evt) => {
      const location = eventLocation(evt);
      if (location.stateCode) stateNames.set(location.stateCode, evt.stateName || location.stateCode);
    });
    return [...stateNames.entries()].sort((a, b) => a[1].localeCompare(b[1]));
  }, [events, markets, states]);

  const availableCities = useMemo(() => {
    const cities = new Map<string, { city: string; stateCode: string }>();
    markets.forEach((market) => {
      if (selectedState === "All" || market.stateCode === selectedState) {
        cities.set(`${market.stateCode}:${market.city.toLowerCase()}`, { city: market.city, stateCode: market.stateCode });
      }
    });
    events.forEach((evt) => {
      const location = eventLocation(evt);
      if (location.city && location.stateCode && (selectedState === "All" || location.stateCode === selectedState)) {
        cities.set(`${location.stateCode}:${location.city.toLowerCase()}`, location);
      }
    });
    return [...cities.values()].sort((a, b) => a.city.localeCompare(b.city));
  }, [events, markets, selectedState]);

  const requestNearbyLocation = () => {
    if (!navigator.geolocation) {
      setLocationMessage("Location permission is unavailable. Choose a state or city instead.");
      return;
    }
    setLocationMessage("Requesting your location…");
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        setNearbyPoint({ latitude: coords.latitude, longitude: coords.longitude });
        setLocationMessage("Showing events within 25 miles when venue coordinates are available.");
      },
      () => {
        setNearbyPoint(null);
        setLocationMessage("Location was not shared. Choose a state or city to browse manually.");
      },
      { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
    );
  };

  const nearbyDistance = (evt: any): number | null => {
    if (!nearbyPoint || evt.latitude == null || evt.longitude == null) return null;
    const latitude = Number(evt.latitude);
    const longitude = Number(evt.longitude);
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return null;
    const radians = (value: number) => value * (Math.PI / 180);
    const dLat = radians(latitude - nearbyPoint.latitude);
    const dLon = radians(longitude - nearbyPoint.longitude);
    const a = Math.sin(dLat / 2) ** 2 +
      Math.cos(radians(nearbyPoint.latitude)) * Math.cos(radians(latitude)) * Math.sin(dLon / 2) ** 2;
    return 3959 * 2 * Math.asin(Math.min(1, Math.sqrt(a)));
  };

  // Handle simulated refresh behavior
  const handleRefresh = () => {
    setIsLoading(true);
    setTimeout(() => {
      setRefreshKey((k) => k + 1);
      setIsLoading(false);
    }, 450);
  };

  // Filtered genuine event list
  const filteredEvents = useMemo(() => {
    return events.filter((evt, idx) => {
      const cat = getEventCategory(evt, idx);
      const performer = getEventPerformer(evt, idx);
      const location = eventLocation(evt);

      // Category filter
      if (selectedCategory !== "All" && cat !== selectedCategory) {
        return false;
      }

      // Location filter
      if (selectedState !== "All" && location.stateCode !== selectedState) return false;
      if (selectedCity !== "All") {
        const [cityState, cityName] = selectedCity.split(":", 2);
        if (location.stateCode !== cityState || location.city.toLowerCase() !== cityName) return false;
      }
      if (zipQuery && !location.zipCode.startsWith(zipQuery.trim())) return false;

      // Date range filter
      const eventDate = evt.eventDateIso
        ? new Date(`${String(evt.eventDateIso).slice(0, 10)}T00:00:00`)
        : null;
      if (selectedDateRange === "Weekend") {
        const today = new Date();
        const saturday = new Date(today.getFullYear(), today.getMonth(), today.getDate() + ((6 - today.getDay() + 7) % 7));
        const sunday = new Date(saturday.getFullYear(), saturday.getMonth(), saturday.getDate() + 1);
        if (!eventDate || Number.isNaN(eventDate.getTime()) || eventDate < saturday || eventDate > sunday) return false;
      }
      if (dateFrom && (!evt.eventDateIso || evt.eventDateIso < dateFrom)) return false;
      if (dateTo && (!evt.eventDateIso || evt.eventDateIso > dateTo)) return false;
      const eventPrices = [
        ...(evt.menu || []).map((item: any) => Number(item.priceUSD ?? item.price_usd ?? 0)),
        ...(evt.ticketTiers || []).map((tier: any) => Number(tier.priceUSD ?? tier.price_usd ?? 0)),
      ].filter((price: number) => Number.isFinite(price) && price >= 0);
      const startingPrice = eventPrices.length ? Math.min(...eventPrices) : 0;
      if (minPrice && startingPrice < Number(minPrice)) return false;
      if (maxPrice && startingPrice > Number(maxPrice)) return false;
      if (availableOnly && (!evt.isOrderingOpen || (evt.advanceOrderDeadlineIso && Date.parse(evt.advanceOrderDeadlineIso) < Date.now()))) return false;
      if (nearbyPoint) {
        const distance = nearbyDistance(evt);
        if (distance === null || distance > 25) return false;
      }

      // Search query (names, artists/performers, organizers, venues, categories)
      if (searchQuery.trim().length > 0) {
        const q = searchQuery.toLowerCase().trim();
        const matchesName = evt.title.toLowerCase().includes(q) || (evt.tagline || "").toLowerCase().includes(q);
        const matchesPerformer = performer.toLowerCase().includes(q);
        const matchesOrganizer = evt.organizerName.toLowerCase().includes(q);
        const matchesVenue = evt.venueName.toLowerCase().includes(q) || evt.venueAddress.toLowerCase().includes(q);
        const matchesCategory = cat.toLowerCase().includes(q) || (evt.locationCategory || "").toLowerCase().includes(q);
        const matchesMenu = (evt.menu || []).some((m: any) => (m.name || "").toLowerCase().includes(q));

        if (!matchesName && !matchesPerformer && !matchesOrganizer && !matchesVenue && !matchesCategory && !matchesMenu) {
          return false;
        }
      }

      return true;
    });
  }, [events, selectedCategory, selectedState, selectedCity, zipQuery, selectedDateRange, dateFrom, dateTo, minPrice, maxPrice, availableOnly, nearbyPoint, searchQuery, refreshKey]);

  // Featured primary event (first matched or selected event)
  const primaryFeaturedIndex = useMemo(() => {
    if (filteredEvents.length === 0) return -1;
    const foundIdx = filteredEvents.findIndex((e) => e.id === events[selectedEventIndex]?.id);
    return foundIdx >= 0 ? foundIdx : 0;
  }, [filteredEvents, selectedEventIndex, events]);

  const primaryFeaturedEvent = primaryFeaturedIndex >= 0 ? filteredEvents[primaryFeaturedIndex] : null;

  // Real popularity metrics calculation from actual available stock & menus
  const getEventPopularityStats = (evt: any) => {
    const totalStock = (evt.menu || []).reduce((acc: number, item: any) => acc + (item.availableStock || 0), 0);
    const minPrice = (evt.menu || []).length > 0
      ? Math.min(...(evt.menu || []).map((m: any) => m.priceUSD || 10))
      : 10;
    return {
      availableDishes: totalStock,
      entryStartingUSD: minPrice,
      isKitchenOpen: evt.isOrderingOpen,
    };
  };

  // Reset all filters
  const handleClearFilters = () => {
    setSearchQuery("");
    setSelectedCategory("All");
    setSelectedState("All");
    setSelectedCity("All");
    setZipQuery("");
    setSelectedDateRange("All");
    setDateFrom("");
    setDateTo("");
    setMinPrice("");
    setMaxPrice("");
    setAvailableOnly(false);
    setNearbyPoint(null);
    setLocationMessage("");
    setVisibleCount(6);
  };

  const handleNavClick = (nav: BottomNavItem) => {
    if (onChangeBottomNav) {
      onChangeBottomNav(nav);
    }
    if (nav === "tickets") onNavigateToTickets();
    else if (nav === "food") onNavigateToFood(selectedEventIndex);
    else if (nav === "profile") {
      if (isLoggedIn) onNavigateToProfile();
      else onOpenAuthModal("login");
    }
  };

  return (
    <div
      id="island-discovery-homepage"
      style={{
        backgroundColor: "var(--att-bg)",
        color: "var(--att-text-1)",
        minHeight: "100vh",
        width: "100%",
        minWidth: 0,
        paddingBottom: "100px",
        fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
      }}
    >
      {/* 1. Header Section */}
      <header
        style={{
          background: "var(--att-bg)",
          borderBottom: "1px solid rgba(255, 255, 255, 0.08)",
          padding: "20px 20px 18px",
        }}
        className="att-discovery-header"
      >
        <div
          style={{
            maxWidth: "1200px",
            margin: "0 auto",
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            gap: "16px",
          }}
        >
          {/* EventBite Brand Logo, Greeting & Discovery Subtitle */}
          <div style={{ minWidth: 0 }}>
            <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "6px" }} id="eventbite-brand-logo">
              <BrandLogo variant="compact" />
              <span style={{ fontSize: "12px", fontWeight: 600, color: "var(--att-text-2)" }}>
                &bull; {isLoggedIn ? `Hello, ${userName}` : "Discover events"}
              </span>
            </div>
            <h1
              id="discovery-header-greeting"
              style={{
                fontSize: "22px",
                fontWeight: 800,
                color: "#FFFFFF",
                margin: 0,
                lineHeight: "1.25",
                letterSpacing: "-0.02em",
              }}
            >
              Good food. Good vibes.
            </h1>
            <p
              style={{
                fontSize: "13px",
                color: "var(--att-text-2)",
                margin: "4px 0 0",
                lineHeight: "1.4",
              }}
            >
              Discover food, music, and cultural events across the United States.
            </p>
          </div>

          {/* Account Action / Profile Icon */}
          <div style={{ display: "flex", alignItems: "center", gap: "12px" }}>
            {isLoggedIn ? (
              <>
                <button
                  type="button"
                  onClick={onNavigateToProfile}
                  id="btn-discovery-profile-icon"
                  title={`Logged in as ${userName} (${userEmail || "Attendee"})`}
                  style={{
                    display: "flex",
                    alignItems: "center",
                    gap: "10px",
                    background: "var(--att-surface-2)",
                    border: "1px solid rgba(255, 255, 255, 0.15)",
                    borderRadius: "9999px",
                    padding: "6px 14px 6px 6px",
                    color: "#FFFFFF",
                    cursor: "pointer",
                    transition: "all 0.2s ease",
                  }}
                >
                  <div
                    style={{
                      width: "34px",
                      height: "34px",
                      borderRadius: "50%",
                      background: "var(--att-accent)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      fontSize: "14px",
                      fontWeight: 700,
                      color: "var(--att-on-accent)",
                      position: "relative",
                    }}
                  >
                    {userName.charAt(0).toUpperCase()}
                    <span
                      style={{
                        position: "absolute",
                        bottom: "-1px",
                        right: "-1px",
                        width: "10px",
                        height: "10px",
                        borderRadius: "50%",
                        backgroundColor: "var(--att-ok)",
                        border: "2px solid var(--att-surface-2)",
                      }}
                    />
                  </div>
                  <div style={{ textAlign: "left" }}>
                    <div style={{ fontSize: "13px", fontWeight: 700, lineHeight: 1.2 }}>
                      {userName}
                    </div>
                    <div style={{ fontSize: "10px", color: "var(--att-text-2)" }}>Active Attendee</div>
                  </div>
                </button>
                {onLogout && (
                  <button
                    type="button"
                    onClick={onLogout}
                    title="Sign Out"
                    id="btn-discovery-signout"
                    style={{
                      background: "rgba(255, 255, 255, 0.08)",
                      border: "1px solid rgba(255, 255, 255, 0.15)",
                      borderRadius: "9999px",
                      padding: "6px 12px",
                      fontSize: "11px",
                      color: "var(--att-text-body)",
                      cursor: "pointer",
                    }}
                  >
                    Sign Out
                  </button>
                )}
              </>
            ) : (
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <button
                  type="button"
                  onClick={() => onOpenAuthModal("login")}
                  id="btn-discovery-header-login"
                  style={{
                    background: "transparent",
                    border: "1px solid rgba(255, 255, 255, 0.2)",
                    borderRadius: "9999px",
                    padding: "8px 16px",
                    fontSize: "13px",
                    fontWeight: 600,
                    color: "var(--att-text-1)",
                    cursor: "pointer",
                    transition: "all 0.18s ease",
                  }}
                >
                  Sign In
                </button>
                <button
                  type="button"
                  onClick={() => onOpenAuthModal("register")}
                  id="btn-discovery-header-register"
                  style={{
                    background: "var(--att-accent)",
                    border: "none",
                    borderRadius: "9999px",
                    padding: "8px 18px",
                    fontSize: "13px",
                    fontWeight: 700,
                    color: "var(--att-on-accent)",
                    cursor: "pointer",
                    boxShadow: "none",
                    transition: "all 0.18s ease",
                  }}
                >
                  Get Started
                </button>
              </div>
            )}

            <button
              type="button"
              onClick={handleRefresh}
              title="Refresh Event Catalog"
              id="btn-discovery-refresh"
              className="att-hide-mobile"
              style={{
                background: "rgba(255, 255, 255, 0.06)",
                border: "1px solid rgba(255, 255, 255, 0.12)",
                borderRadius: "50%",
                width: "36px",
                height: "36px",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                color: "var(--att-text-2)",
                cursor: "pointer",
                transition: "all 0.2s ease",
              }}
            >
              <RefreshCw size={15} className={isLoading ? "animate-spin" : ""} />
            </button>
          </div>
        </div>
      </header>

      {/* Main Discovery Container */}
      <main style={{ maxWidth: "1200px", margin: "0 auto", padding: "24px 20px 40px" }}>
        {/* 1.5. EventBite Hero Section */}
        <section
          id="discovery-hero-banner"
          aria-label="EventBite hero banner"
          style={{
            position: "relative",
            borderRadius: "20px",
            overflow: "hidden",
            marginBottom: "24px",
            minHeight: "230px",
            border: "1px solid var(--color-border, #26384D)",
            background: "var(--color-surface, #10243A)",
            display: "flex",
            alignItems: "flex-end",
          }}
        >
          <img
            src={primaryFeaturedEvent ? getEventImage(primaryFeaturedEvent, primaryFeaturedIndex) : "/images/island_festival_sunset.jpg"}
            alt="EventBite Featured Gathering"
            style={{
              position: "absolute",
              inset: 0,
              width: "100%",
              height: "100%",
              objectFit: "cover",
            }}
          />
          <div
            style={{
              position: "absolute",
              inset: 0,
              background: "linear-gradient(180deg, rgba(7, 17, 31, 0.35) 0%, rgba(7, 17, 31, 0.88) 70%, rgba(7, 17, 31, 0.96) 100%)",
            }}
          />
          <div style={{ position: "relative", zIndex: 2, padding: "28px 24px", width: "100%", display: "flex", flexWrap: "wrap", alignItems: "flex-end", justifyContent: "space-between", gap: "16px" }}>
            <div style={{ maxWidth: "640px" }}>
              <span
                style={{
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "6px",
                  padding: "4px 12px",
                  borderRadius: "9999px",
                  background: "rgba(199, 255, 69, 0.16)",
                  border: "1px solid rgba(199, 255, 69, 0.4)",
                  color: "var(--color-accent, #C7FF45)",
                  fontSize: "11px",
                  fontWeight: 700,
                  textTransform: "uppercase",
                  letterSpacing: "0.05em",
                  marginBottom: "10px",
                }}
              >
                EventBite Curated Experiences
              </span>
              <h2
                id="discovery-hero-headline"
                style={{
                  margin: "0 0 8px",
                  fontSize: "clamp(22px, 3.5vw, 32px)",
                  fontWeight: 800,
                  lineHeight: 1.18,
                  letterSpacing: "-0.02em",
                  color: "var(--color-text-primary, #F8FAFC)",
                }}
              >
                Good Food. Great Vibes. Unforgettable Events.
              </h2>
              <p style={{ margin: 0, fontSize: "14px", color: "var(--color-text-secondary, #A8B6C8)", lineHeight: 1.5 }}>
                Discover authentic fish fries, food festivals, live reggae concerts, and community celebrations with verified digital passes and advance meal ordering.
              </p>
            </div>
            <button
              type="button"
              id="btn-hero-explore-events"
              onClick={() => {
                const target = document.getElementById("card-primary-featured-event") || document.getElementById("input-discovery-search");
                if (target && typeof target.scrollIntoView === "function") {
                  target.scrollIntoView({ behavior: "smooth" });
                }
              }}
              style={{
                background: "var(--color-accent, #C7FF45)",
                color: "var(--color-accent-text, #14210A)",
                border: "none",
                borderRadius: "14px",
                padding: "12px 22px",
                fontSize: "14px",
                fontWeight: 800,
                cursor: "pointer",
                display: "inline-flex",
                alignItems: "center",
                gap: "8px",
                minHeight: "44px",
              }}
            >
              <span>Explore Events</span>
              <ArrowRight size={16} />
            </button>
          </div>
        </section>

        {/* 2. Search Area & Selectors */}
        <section style={{ marginBottom: "28px" }} aria-label="Event search and filter controls">
          <div
            style={{
              background: "var(--att-surface-1)",
              border: "1px solid rgba(255, 255, 255, 0.1)",
              borderRadius: "20px",
              padding: "16px",
              boxShadow: "0 8px 24px rgba(0, 0, 0, 0.25)",
            }}
          >
            {/* Search Input Row */}
            <div style={{ position: "relative", marginBottom: "12px" }}>
              <Search
                size={18}
                style={{
                  position: "absolute",
                  left: "16px",
                  top: "50%",
                  transform: "translateY(-50%)",
                  color: "var(--att-accent)",
                  pointerEvents: "none",
                }}
              />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search events, artists, performers, venues, organizers..."
                id="input-discovery-search"
                style={{
                  width: "100%",
                  backgroundColor: "var(--att-surface-2)",
                  border: "1px solid rgba(255, 255, 255, 0.15)",
                  borderRadius: "9999px",
                  padding: "12px 42px 12px 44px",
                  color: "#FFFFFF",
                  fontSize: "14px",
                  outline: "none",
                  boxSizing: "border-box",
                  transition: "border-color 0.2s ease, box-shadow 0.2s ease",
                }}
              />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => setSearchQuery("")}
                  id="btn-clear-discovery-search"
                  title="Clear search"
                  style={{
                    position: "absolute",
                    right: "14px",
                    top: "50%",
                    transform: "translateY(-50%)",
                    background: "none",
                    border: "none",
                    color: "var(--att-text-2)",
                    cursor: "pointer",
                    padding: "4px",
                  }}
                >
                  <X size={16} />
                </button>
              )}
            </div>

            {/* Selectors Beneath / Alongside Search Field */}
            <div
              style={{
                display: "flex",
                flexWrap: "wrap",
                alignItems: "center",
                justifyContent: "space-between",
                gap: "10px",
              }}
            >
              <div style={{ display: "flex", flexWrap: "wrap", gap: "10px", flex: 1 }}>
                <div style={{ display: "flex", alignItems: "center", gap: "6px", flexWrap: "wrap" }}>
                  <MapPin size={14} style={{ color: "var(--att-accent)" }} />
                  <select
                    value={selectedState}
                    onChange={(e) => { setSelectedState(e.target.value); setSelectedCity("All"); }}
                    id="select-discovery-state"
                    style={{
                      backgroundColor: "var(--att-surface-2)",
                      color: "var(--att-text-1)",
                      border: "1px solid rgba(255, 255, 255, 0.12)",
                      borderRadius: "10px",
                      padding: "8px 12px",
                      fontSize: "12px",
                      fontWeight: 600,
                      cursor: "pointer",
                      outline: "none",
                    }}
                  >
                    <option value="All">All U.S. states & territories</option>
                    {availableStates.map(([code, name]) => (
                      <option key={code} value={code}>
                        {name}
                      </option>
                    ))}
                  </select>
                  <select
                    value={selectedCity}
                    onChange={(e) => setSelectedCity(e.target.value)}
                    id="select-discovery-city"
                    style={{
                      backgroundColor: "var(--att-surface-2)", color: "var(--att-text-1)",
                      border: "1px solid rgba(255, 255, 255, 0.12)", borderRadius: "10px",
                      padding: "8px 12px", fontSize: "12px", fontWeight: 600, cursor: "pointer", outline: "none",
                    }}
                  >
                    <option value="All">All cities and towns</option>
                    {availableCities.map((location) => (
                      <option key={`${location.stateCode}:${location.city}`} value={`${location.stateCode}:${location.city.toLowerCase()}`}>
                        {location.city}, {location.stateCode}
                      </option>
                    ))}
                  </select>
                  <input
                    type="text" inputMode="numeric" maxLength={10} value={zipQuery}
                    onChange={(e) => setZipQuery(e.target.value.replace(/[^\d-]/g, ""))}
                    placeholder="ZIP code" aria-label="Filter by ZIP code"
                    style={{ backgroundColor: "var(--att-surface-2)", color: "var(--att-text-1)", border: "1px solid rgba(255, 255, 255, 0.12)", borderRadius: "10px", padding: "8px 12px", fontSize: "12px", width: "90px" }}
                  />
                  <button type="button" onClick={requestNearbyLocation} style={{ backgroundColor: "var(--att-surface-2)", color: "var(--att-text-1)", border: "1px solid rgba(255, 255, 255, 0.12)", borderRadius: "10px", padding: "8px 12px", fontSize: "12px", cursor: "pointer" }}>
                    Nearby (25 mi)
                  </button>
                </div>

                {/* Date Selector */}
                <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                  <Calendar size={14} style={{ color: "var(--att-text-2)" }} />
                  <select
                    value={selectedDateRange}
                    onChange={(e) => setSelectedDateRange(e.target.value)}
                    id="select-discovery-date"
                    style={{
                      backgroundColor: "var(--att-surface-2)",
                      color: "var(--att-text-1)",
                      border: "1px solid rgba(255, 255, 255, 0.12)",
                      borderRadius: "10px",
                      padding: "8px 12px",
                      fontSize: "12px",
                      fontWeight: 600,
                      cursor: "pointer",
                      outline: "none",
                    }}
                  >
                    <option value="All">All Dates</option>
                    <option value="Weekend">This Weekend</option>
                  </select>
                  <input type="date" aria-label="Events from date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
                  <input type="date" aria-label="Events through date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
                  <input type="number" aria-label="Minimum event price" min="0" step="0.01" placeholder="Min $" value={minPrice} onChange={(e) => setMinPrice(e.target.value)} style={{ width: "72px" }} />
                  <input type="number" aria-label="Maximum event price" min="0" step="0.01" placeholder="Max $" value={maxPrice} onChange={(e) => setMaxPrice(e.target.value)} style={{ width: "72px" }} />
                  <label style={{ fontSize: "12px", color: "var(--att-text-1)" }}>
                    <input type="checkbox" checked={availableOnly} onChange={(e) => setAvailableOnly(e.target.checked)} /> Taking orders
                  </label>
                </div>
              </div>
              {locationMessage && <span role="status" style={{ width: "100%", fontSize: "11px", color: "var(--att-text-2)" }}>{locationMessage}</span>}

              {/* Real-time Result Count & Reset */}
              <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                <span style={{ fontSize: "12px", color: "var(--att-text-2)", fontWeight: 600 }}>
                  Showing <strong style={{ color: "#FFFFFF" }}>{filteredEvents.length}</strong> of {events.length} events
                </span>
                {(searchQuery || selectedCategory !== "All" || selectedState !== "All" || selectedCity !== "All" || zipQuery || selectedDateRange !== "All" || dateFrom || dateTo || minPrice || maxPrice || availableOnly || nearbyPoint) && (
                  <button
                    type="button"
                    onClick={handleClearFilters}
                    id="btn-reset-discovery-filters"
                    style={{
                      background: "rgba(239, 68, 68, 0.15)",
                      border: "1px solid rgba(239, 68, 68, 0.3)",
                      borderRadius: "8px",
                      padding: "5px 10px",
                      color: "var(--att-bad)",
                      fontSize: "11px",
                      fontWeight: 700,
                      cursor: "pointer",
                    }}
                  >
                    Reset Filters
                  </button>
                )}
              </div>
            </div>
          </div>
        </section>

        {/* 3. Category Shortcuts & Horizontally Scrollable Category Chips */}
        <section style={{ marginBottom: "36px" }} aria-label="Category shortcuts">
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: "14px" }}>
            <h2 style={{ fontSize: "16px", fontWeight: 800, color: "#FFFFFF", margin: 0, letterSpacing: "-0.01em" }}>
              Explore by Category
            </h2>
            {selectedCategory !== "All" && (
              <button
                type="button"
                onClick={() => setSelectedCategory("All")}
                style={{
                  background: "none",
                  border: "none",
                  color: "var(--att-accent)",
                  fontSize: "12px",
                  fontWeight: 600,
                  cursor: "pointer",
                }}
              >
                Clear Category (View All)
              </button>
            )}
          </div>

          {/* Horizontally Scrollable Category Chips */}
          <div
            id="discovery-category-chips"
            role="tablist"
            aria-label="Event category filters"
            style={{
              display: "flex",
              alignItems: "center",
              gap: "8px",
              overflowX: "auto",
              paddingBottom: "12px",
              marginBottom: "14px",
              scrollbarWidth: "none",
            }}
          >
            {(
              [
                { label: "All Events", value: "All" as DiscoveryCategory },
                { label: "Food Festivals", value: "Food" as DiscoveryCategory },
                { label: "Music", value: "Music" as DiscoveryCategory },
                { label: "Community", value: "Community" as DiscoveryCategory },
                { label: "Parties", value: "Parties" as DiscoveryCategory },
                { label: "Sports", value: "Sports" as DiscoveryCategory },
                { label: "Culture", value: "Culture" as DiscoveryCategory },
              ]
            ).map((chip) => {
              const isActive = selectedCategory === chip.value;
              return (
                <button
                  key={chip.value}
                  type="button"
                  role="tab"
                  aria-selected={isActive}
                  id={`chip-category-${chip.value.toLowerCase()}`}
                  onClick={() => setSelectedCategory(isActive && chip.value !== "All" ? "All" : chip.value)}
                  style={{
                    minHeight: "40px",
                    padding: "8px 16px",
                    borderRadius: "9999px",
                    border: isActive
                      ? "1px solid var(--color-accent, #C7FF45)"
                      : "1px solid var(--color-border, #26384D)",
                    background: isActive
                      ? "var(--color-accent, #C7FF45)"
                      : "var(--color-surface, #10243A)",
                    color: isActive
                      ? "var(--color-accent-text, #14210A)"
                      : "var(--color-text-secondary, #A8B6C8)",
                    fontSize: "13px",
                    fontWeight: isActive ? 700 : 600,
                    whiteSpace: "nowrap",
                    cursor: "pointer",
                    transition: "all 0.15s ease",
                  }}
                >
                  {chip.label}
                </button>
              );
            })}
          </div>

          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(min(130px, 100%), 1fr))",
              gap: "14px",
            }}
          >
            {/* Category: Music */}
            <button
              type="button"
              onClick={() => setSelectedCategory(selectedCategory === "Music" ? "All" : "Music")}
              id="cat-shortcut-music"
              style={{
                background: selectedCategory === "Music" ? "rgba(182, 240, 58, 0.35)" : "var(--att-surface-1)",
                border: selectedCategory === "Music" ? "2px solid var(--att-accent)" : "1px solid rgba(255, 255, 255, 0.08)",
                borderRadius: "18px",
                padding: "16px 12px",
                display: "flex",
                flexDirection: "column",
                alignItems: "center",
                gap: "10px",
                cursor: "pointer",
                transition: "all 0.2s cubic-bezier(0.4, 0, 0.2, 1)",
                boxShadow: selectedCategory === "Music" ? "none" : "none",
              }}
            >
              <div
                style={{
                  width: "56px",
                  height: "56px",
                  borderRadius: "50%",
                  background: "rgba(182, 240, 58, 0.18)",
                  border: "1px solid rgba(182, 240, 58, 0.3)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  color: "var(--att-accent)",
                }}
              >
                <Music size={26} />
              </div>
              <span style={{ fontSize: "13px", fontWeight: 700, color: selectedCategory === "Music" ? "#FFFFFF" : "var(--att-text-1)" }}>
                Music
              </span>
              <span style={{ fontSize: "10px", color: "var(--att-text-2)" }}>Live Reggae & Sets</span>
            </button>

            {/* Category: Food */}
            <button
              type="button"
              onClick={() => setSelectedCategory(selectedCategory === "Food" ? "All" : "Food")}
              id="cat-shortcut-food"
              style={{
                background: selectedCategory === "Food" ? "rgba(245, 158, 11, 0.35)" : "var(--att-surface-1)",
                border: selectedCategory === "Food" ? "2px solid var(--att-warn)" : "1px solid rgba(255, 255, 255, 0.08)",
                borderRadius: "18px",
                padding: "16px 12px",
                display: "flex",
                flexDirection: "column",
                alignItems: "center",
                gap: "10px",
                cursor: "pointer",
                transition: "all 0.2s cubic-bezier(0.4, 0, 0.2, 1)",
                boxShadow: selectedCategory === "Food" ? "0 8px 20px rgba(245, 158, 11, 0.25)" : "none",
              }}
            >
              <div
                style={{
                  width: "56px",
                  height: "56px",
                  borderRadius: "50%",
                  background: "rgba(245, 158, 11, 0.18)",
                  border: "1px solid rgba(245, 158, 11, 0.3)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  color: "var(--att-warn)",
                }}
              >
                <Utensils size={26} />
              </div>
              <span style={{ fontSize: "13px", fontWeight: 700, color: selectedCategory === "Food" ? "#FFFFFF" : "var(--att-text-1)" }}>
                Food
              </span>
              <span style={{ fontSize: "10px", color: "var(--att-text-2)" }}>Fish Fries & Feasts</span>
            </button>

            {/* Category: Sports */}
            <button
              type="button"
              onClick={() => setSelectedCategory(selectedCategory === "Sports" ? "All" : "Sports")}
              id="cat-shortcut-sports"
              style={{
                background: selectedCategory === "Sports" ? "rgba(34, 197, 94, 0.35)" : "var(--att-surface-1)",
                border: selectedCategory === "Sports" ? "2px solid var(--att-ok)" : "1px solid rgba(255, 255, 255, 0.08)",
                borderRadius: "18px",
                padding: "16px 12px",
                display: "flex",
                flexDirection: "column",
                alignItems: "center",
                gap: "10px",
                cursor: "pointer",
                transition: "all 0.2s cubic-bezier(0.4, 0, 0.2, 1)",
                boxShadow: selectedCategory === "Sports" ? "0 8px 20px rgba(34, 197, 94, 0.25)" : "none",
              }}
            >
              <div
                style={{
                  width: "56px",
                  height: "56px",
                  borderRadius: "50%",
                  background: "rgba(34, 197, 94, 0.18)",
                  border: "1px solid rgba(34, 197, 94, 0.3)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  color: "var(--att-ok)",
                }}
              >
                <Trophy size={26} />
              </div>
              <span style={{ fontSize: "13px", fontWeight: 700, color: selectedCategory === "Sports" ? "#FFFFFF" : "var(--att-text-1)" }}>
                Sports
              </span>
              <span style={{ fontSize: "10px", color: "var(--att-text-2)" }}>Regattas & Cricket</span>
            </button>

            {/* Category: Culture */}
            <button
              type="button"
              onClick={() => setSelectedCategory(selectedCategory === "Culture" ? "All" : "Culture")}
              id="cat-shortcut-culture"
              style={{
                background: selectedCategory === "Culture" ? "rgba(182, 240, 58, 0.35)" : "var(--att-surface-1)",
                border: selectedCategory === "Culture" ? "2px solid var(--att-text-2)" : "1px solid rgba(255, 255, 255, 0.08)",
                borderRadius: "18px",
                padding: "16px 12px",
                display: "flex",
                flexDirection: "column",
                alignItems: "center",
                gap: "10px",
                cursor: "pointer",
                transition: "all 0.2s cubic-bezier(0.4, 0, 0.2, 1)",
                boxShadow: selectedCategory === "Culture" ? "none" : "none",
              }}
            >
              <div
                style={{
                  width: "56px",
                  height: "56px",
                  borderRadius: "50%",
                  background: "rgba(182, 240, 58, 0.18)",
                  border: "1px solid rgba(182, 240, 58, 0.3)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  color: "var(--att-text-2)",
                }}
              >
                <Sparkles size={26} />
              </div>
              <span style={{ fontSize: "13px", fontWeight: 700, color: selectedCategory === "Culture" ? "#FFFFFF" : "var(--att-text-1)" }}>
                Culture
              </span>
              <span style={{ fontSize: "10px", color: "var(--att-text-2)" }}>Heritage & Parades</span>
            </button>
          </div>
        </section>

        {/* 4. Featured Event Section (Prominent Large Image Card) */}
        {primaryFeaturedEvent && (
          <section style={{ marginBottom: "40px" }} aria-label="Featured primary event">
            <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "14px" }}>
              <Flame size={18} style={{ color: "var(--att-accent)" }} />
              <h2 style={{ fontSize: "18px", fontWeight: 800, color: "#FFFFFF", margin: 0, letterSpacing: "-0.01em" }}>
                Featured Event
              </h2>
            </div>

            <div
              id="card-primary-featured-event"
              style={{
                backgroundColor: "var(--att-surface-1)",
                borderRadius: "24px",
                overflow: "hidden",
                border: "1px solid rgba(255, 255, 255, 0.12)",
                boxShadow: "0 14px 34px rgba(0, 0, 0, 0.35)",
                display: "grid",
                gridTemplateColumns: "repeat(auto-fit, minmax(min(320px, 100%), 1fr))",
                transition: "transform 0.2s ease, box-shadow 0.2s ease",
              }}
            >
              {/* Event Image */}
              <div style={{ position: "relative", minHeight: "260px", maxHeight: "380px" }}>
                <img
                  src={getEventImage(primaryFeaturedEvent, primaryFeaturedIndex)}
                  alt={primaryFeaturedEvent.title}
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
                    background: "linear-gradient(180deg, rgba(10, 15, 22,0.15) 0%, rgba(10, 15, 22,0.85) 100%)",
                  }}
                />
                <div style={{ position: "absolute", top: "16px", left: "16px", display: "flex", gap: "8px" }}>
                  <span
                    style={{
                      background: "rgba(10, 15, 22, 0.8)",
                      backdropFilter: "blur(8px)",
                      color: "#FFFFFF",
                      padding: "4px 12px",
                      borderRadius: "9999px",
                      fontSize: "11px",
                      fontWeight: 700,
                      border: "1px solid rgba(255, 255, 255, 0.2)",
                    }}
                  >
                    ★ FEATURED
                  </span>
                  <span
                    style={{
                      background: "rgba(182, 240, 58, 0.85)",
                      color: "#FFFFFF",
                      padding: "4px 12px",
                      borderRadius: "9999px",
                      fontSize: "11px",
                      fontWeight: 700,
                    }}
                  >
                    {getEventCategory(primaryFeaturedEvent, primaryFeaturedIndex)}
                  </span>
                </div>
                <div style={{ position: "absolute", bottom: "16px", left: "16px", right: "16px" }}>
                  <div style={{ fontSize: "11px", fontWeight: 700, color: "var(--att-text-body)", textTransform: "uppercase" }}>
                    Performer Highlight
                  </div>
                  <div style={{ fontSize: "14px", fontWeight: 700, color: "#FFFFFF" }}>
                    🎤 {getEventPerformer(primaryFeaturedEvent, primaryFeaturedIndex)}
                  </div>
                </div>
              </div>

              {/* Event Details Content */}
              <div style={{ padding: "28px", display: "flex", flexDirection: "column", justifyContent: "space-between" }}>
                <div>
                  <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "8px", color: "var(--att-accent)", fontSize: "12px", fontWeight: 600 }}>
                    <Calendar size={14} />
                    <span>{primaryFeaturedEvent.dateStr}</span>
                    <span>&bull;</span>
                    <Clock size={14} />
                    <span>{primaryFeaturedEvent.timeStr}</span>
                  </div>

                  <h3
                    style={{
                      fontSize: "22px",
                      fontWeight: 800,
                      color: "#FFFFFF",
                      lineHeight: "1.3",
                      marginBottom: "10px",
                    }}
                  >
                    {primaryFeaturedEvent.title}
                  </h3>

                  <p style={{ fontSize: "13px", color: "var(--att-text-2)", lineHeight: "1.6", marginBottom: "16px" }}>
                    {primaryFeaturedEvent.tagline || primaryFeaturedEvent.description}
                  </p>

                  <div style={{ display: "flex", alignItems: "center", gap: "6px", color: "var(--att-text-body)", fontSize: "12px", marginBottom: "16px" }}>
                    <MapPin size={14} style={{ color: "var(--att-text-2)", flexShrink: 0 }} />
                    <span style={{ fontWeight: 600 }}>{primaryFeaturedEvent.venueName}</span>
                    <span style={{ color: "var(--att-text-3)" }}>({primaryFeaturedEvent.locationCategory || "United States"})</span>
                  </div>
                </div>

                {/* Real popularity pill & CTA */}
                <div>
                  <div
                    style={{
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "space-between",
                      padding: "12px 16px",
                      background: "rgba(10, 15, 22, 0.6)",
                      borderRadius: "14px",
                      marginBottom: "18px",
                      border: "1px solid rgba(255, 255, 255, 0.06)",
                    }}
                  >
                    <div>
                      <div style={{ fontSize: "10px", textTransform: "uppercase", color: "var(--att-text-2)", fontWeight: 700 }}>
                        Genuine Inventory
                      </div>
                      <div style={{ fontSize: "13px", fontWeight: 700, color: "var(--att-ok)" }}>
                        ✓ {getEventPopularityStats(primaryFeaturedEvent).availableDishes} portions listed
                      </div>
                    </div>
                    <div style={{ textAlign: "right" }}>
                      <div style={{ fontSize: "10px", textTransform: "uppercase", color: "var(--att-text-2)", fontWeight: 700 }}>
                        Menu Starts At
                      </div>
                      <div style={{ fontSize: "14px", fontWeight: 800, color: "#FFFFFF" }}>
                        {formatMoney(getEventPopularityStats(primaryFeaturedEvent).entryStartingUSD)}
                      </div>
                    </div>
                  </div>

                  <div style={{ display: "flex", gap: "10px", flexWrap: "wrap" }}>
                    <button
                      type="button"
                      onClick={() => {
                        const originalIdx = events.findIndex((e) => e.id === primaryFeaturedEvent.id);
                        const targetIdx = originalIdx >= 0 ? originalIdx : 0;
                        onSelectEvent(targetIdx);
                        if (onNavigateToDetails) {
                          onNavigateToDetails(targetIdx);
                        } else {
                          onNavigateToFood(targetIdx);
                        }
                      }}
                      id="btn-view-featured-event-cta"
                      style={{
                        flex: 1,
                        minWidth: "160px",
                        background: "var(--att-accent)",
                        border: "none",
                        borderRadius: "12px",
                        padding: "12px 20px",
                        fontSize: "14px",
                        fontWeight: 700,
                        color: "var(--att-on-accent)",
                        cursor: "pointer",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        gap: "8px",
                        boxShadow: "none",
                      }}
                    >
                      <span>View Event & Passes</span>
                      <ArrowRight size={16} />
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        const originalIdx = events.findIndex((e) => e.id === primaryFeaturedEvent.id);
                        const targetIdx = originalIdx >= 0 ? originalIdx : 0;
                        onSelectEvent(targetIdx);
                        onNavigateToFood(targetIdx);
                      }}
                      id="btn-featured-preorder-food"
                      style={{
                        background: "rgba(255, 255, 255, 0.08)",
                        border: "1px solid rgba(255, 255, 255, 0.15)",
                        borderRadius: "12px",
                        padding: "12px 18px",
                        fontSize: "13px",
                        fontWeight: 700,
                        color: "var(--att-text-1)",
                        cursor: "pointer",
                        display: "flex",
                        alignItems: "center",
                        gap: "6px",
                      }}
                    >
                      <Utensils size={14} style={{ color: "var(--att-ok)" }} />
                      <span>Dining Menu</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </section>
        )}

        {/* 5. Trending Near You (Horizontal Collection) */}
        <section style={{ marginBottom: "40px" }} aria-label="Trending events collection">
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: "14px" }}>
            <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
              <TrendingUp size={18} style={{ color: "var(--att-ok)" }} />
              <h2 style={{ fontSize: "18px", fontWeight: 800, color: "#FFFFFF", margin: 0, letterSpacing: "-0.01em" }}>
                Trending Near You
              </h2>
            </div>
            <span style={{ fontSize: "12px", color: "var(--att-text-2)" }}>
              Live real-time demand & verified menus
            </span>
          </div>

          <div
            id="trending-horizontal-scroll"
            style={{
              display: "flex",
              gap: "16px",
              overflowX: "auto",
              paddingBottom: "12px",
              scrollSnapType: "x mandatory",
              WebkitOverflowScrolling: "touch",
            }}
          >
            {events.slice(0, 6).map((evt, idx) => {
              const cat = getEventCategory(evt, idx);
              const stats = getEventPopularityStats(evt);
              const isSelected = selectedEventIndex === idx;

              return (
                <div
                  key={evt.id || idx}
                  style={{
                    minWidth: "260px",
                    maxWidth: "280px",
                    flexShrink: 0,
                    scrollSnapAlign: "start",
                    backgroundColor: "var(--att-surface-1)",
                    borderRadius: "18px",
                    border: isSelected ? "2px solid var(--att-accent)" : "1px solid rgba(255, 255, 255, 0.08)",
                    overflow: "hidden",
                    display: "flex",
                    flexDirection: "column",
                    boxShadow: isSelected ? "none" : "0 4px 12px rgba(0,0,0,0.2)",
                    transition: "transform 0.18s ease, border-color 0.18s ease",
                  }}
                  id={`card-trending-event-${idx}`}
                >
                  {/* Thumbnail */}
                  <div style={{ height: "135px", position: "relative" }}>
                    <img
                      src={getEventImage(evt, idx)}
                      alt={evt.title}
                      style={{ width: "100%", height: "100%", objectFit: "cover" }}
                    />
                    <div
                      style={{
                        position: "absolute",
                        top: "10px",
                        left: "10px",
                        background: "rgba(10, 15, 22, 0.8)",
                        backdropFilter: "blur(6px)",
                        color: "#FFFFFF",
                        padding: "3px 8px",
                        borderRadius: "9999px",
                        fontSize: "10px",
                        fontWeight: 700,
                      }}
                    >
                      {cat}
                    </div>
                    {isSelected && (
                      <div
                        style={{
                          position: "absolute",
                          top: "10px",
                          right: "10px",
                          background: "var(--att-accent)",
                          color: "var(--att-on-accent)",
                          padding: "3px 8px",
                          borderRadius: "9999px",
                          fontSize: "10px",
                          fontWeight: 800,
                        }}
                      >
                        Active
                      </div>
                    )}
                  </div>

                  {/* Body */}
                  <div style={{ padding: "14px", display: "flex", flexDirection: "column", flex: 1, justifyContent: "space-between" }}>
                    <div>
                      <div style={{ fontSize: "11px", color: "var(--att-accent)", fontWeight: 600, marginBottom: "4px" }}>
                        📅 {evt.dateStr.split(",")[0] || evt.dateStr}
                      </div>
                      <h4
                        style={{
                          fontSize: "13px",
                          fontWeight: 700,
                          color: "#FFFFFF",
                          lineHeight: "1.3",
                          margin: "0 0 6px",
                          display: "-webkit-box",
                          WebkitLineClamp: 2,
                          WebkitBoxOrient: "vertical",
                          overflow: "hidden",
                        }}
                      >
                        {evt.title}
                      </h4>
                      <div style={{ fontSize: "11px", color: "var(--att-text-2)", display: "flex", alignItems: "center", gap: "4px", marginBottom: "8px" }}>
                        <MapPin size={12} style={{ color: "var(--att-text-2)", flexShrink: 0 }} />
                        <span style={{ whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                          {evt.venueName}
                        </span>
                      </div>
                    </div>

                    <div>
                      <div style={{ fontSize: "11px", color: "var(--att-ok)", fontWeight: 600, marginBottom: "8px" }}>
                        ● {stats.availableDishes} genuine portions available
                      </div>
                      <button
                        type="button"
                        onClick={() => {
                          onSelectEvent(idx);
                          if (onNavigateToDetails) {
                            onNavigateToDetails(idx);
                          } else {
                            onNavigateToFood(idx);
                          }
                        }}
                        id={`btn-trending-details-${idx}`}
                        style={{
                          width: "100%",
                          background: isSelected ? "var(--att-accent)" : "rgba(255, 255, 255, 0.08)",
                          border: "none",
                          borderRadius: "8px",
                          padding: "7px 10px",
                          fontSize: "12px",
                          fontWeight: 700,
                          color: "var(--att-on-accent)",
                          cursor: "pointer",
                          display: "flex",
                          alignItems: "center",
                          justifyContent: "center",
                          gap: "6px",
                        }}
                      >
                        <span>{isSelected ? "View Active Pass" : "View Details & Passes"}</span>
                        <ChevronRight size={13} />
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </section>

        {/* 6. All Discovered Events Grid (Incremental Loading & Data States) */}
        <section aria-label="All discovered events">
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: "14px" }}>
            <h2 style={{ fontSize: "18px", fontWeight: 800, color: "#FFFFFF", margin: 0, letterSpacing: "-0.01em" }}>
              All Events ({filteredEvents.length})
            </h2>
            <span style={{ fontSize: "12px", color: "var(--att-text-2)" }}>
              Category: <strong style={{ color: "var(--att-accent)" }}>{selectedCategory}</strong>
            </span>
          </div>

          {/* Empty Results State */}
          {filteredEvents.length === 0 && (
            <div
              id="discovery-empty-state"
              style={{
                background: "var(--att-surface-1)",
                border: "1px dashed rgba(255, 255, 255, 0.15)",
                borderRadius: "20px",
                padding: "48px 24px",
                textAlign: "center",
                margin: "20px 0",
              }}
            >
              <div style={{ fontSize: "40px", marginBottom: "12px" }}>🔍</div>
              <h3 style={{ fontSize: "18px", fontWeight: 700, color: "#FFFFFF", marginBottom: "6px" }}>
                No Events Found Matching Your Search
              </h3>
              <p style={{ fontSize: "13px", color: "var(--att-text-2)", maxWidth: "460px", margin: "0 auto 18px", lineHeight: "1.5" }}>
                We couldn't find events matching "{searchQuery}" in {selectedCity !== "All" ? availableCities.find((city) => `${city.stateCode}:${city.city.toLowerCase()}` === selectedCity)?.city : selectedState} for {selectedDateRange}. Try broadening your search terms or clearing active filters.
              </p>
              <button
                type="button"
                onClick={handleClearFilters}
                style={{
                  background: "var(--att-accent)",
                  border: "none",
                  borderRadius: "9999px",
                  padding: "10px 24px",
                  fontSize: "13px",
                  fontWeight: 700,
                  color: "var(--att-on-accent)",
                  cursor: "pointer",
                }}
              >
                Clear All Filters & Reset
              </button>
            </div>
          )}

          {/* Event Cards Grid */}
          {filteredEvents.length > 0 && (
            <div
              style={{
                display: "grid",
                gridTemplateColumns: "repeat(auto-fill, minmax(min(300px, 100%), 1fr))",
                gap: "20px",
                marginBottom: "28px",
              }}
            >
              {filteredEvents.slice(0, visibleCount).map((evt, idx) => {
                const originalIndex = events.findIndex((e) => e.id === evt.id);
                const isSelected = selectedEventIndex === originalIndex;
                const cat = getEventCategory(evt, originalIndex);
                const performer = getEventPerformer(evt, originalIndex);
                const stats = getEventPopularityStats(evt);

                return (
                  <div
                    key={evt.id || idx}
                    style={{
                      backgroundColor: "var(--att-surface-1)",
                      borderRadius: "20px",
                      border: isSelected ? "2px solid var(--att-accent)" : "1px solid rgba(255, 255, 255, 0.08)",
                      overflow: "hidden",
                      display: "flex",
                      flexDirection: "column",
                      boxShadow: isSelected ? "none" : "0 4px 16px rgba(0,0,0,0.25)",
                      transition: "transform 0.2s ease, box-shadow 0.2s ease",
                    }}
                    id={`card-all-events-${idx}`}
                  >
                    {/* Flyer / Image Banner */}
                    <div style={{ height: "170px", position: "relative" }}>
                      <img
                        src={getEventImage(evt, originalIndex)}
                        alt={evt.title}
                        style={{ width: "100%", height: "100%", objectFit: "cover" }}
                      />
                      <div
                        style={{
                          position: "absolute",
                          inset: 0,
                          background: "linear-gradient(180deg, transparent 40%, rgba(10, 15, 22, 0.85) 100%)",
                        }}
                      />
                      <div style={{ position: "absolute", top: "12px", left: "12px", display: "flex", gap: "6px" }}>
                        <span
                          style={{
                            background: "rgba(10, 15, 22, 0.8)",
                            color: "#FFFFFF",
                            padding: "3px 10px",
                            borderRadius: "9999px",
                            fontSize: "11px",
                            fontWeight: 700,
                          }}
                        >
                          {cat}
                        </span>
                      </div>
                      <div style={{ position: "absolute", bottom: "10px", left: "14px", right: "14px" }}>
                        <div style={{ fontSize: "11px", color: "var(--att-text-1)", fontWeight: 600 }}>
                          🎤 {performer}
                        </div>
                      </div>
                    </div>

                    {/* Content Details */}
                    <div style={{ padding: "18px", display: "flex", flexDirection: "column", flex: 1, justifyContent: "space-between" }}>
                      <div>
                        <div style={{ display: "flex", alignItems: "center", gap: "6px", fontSize: "11px", color: "var(--att-accent)", fontWeight: 600, marginBottom: "6px" }}>
                          <Calendar size={13} />
                          <span>{evt.dateStr}</span>
                          <span>&bull;</span>
                          <span>{evt.timeStr}</span>
                        </div>

                        <h3
                          style={{
                            fontSize: "16px",
                            fontWeight: 700,
                            color: "#FFFFFF",
                            lineHeight: "1.35",
                            marginBottom: "8px",
                          }}
                        >
                          {evt.title}
                        </h3>

                        <p
                          style={{
                            fontSize: "12px",
                            color: "var(--att-text-2)",
                            lineHeight: "1.5",
                            marginBottom: "14px",
                            display: "-webkit-box",
                            WebkitLineClamp: 2,
                            WebkitBoxOrient: "vertical",
                            overflow: "hidden",
                          }}
                        >
                          {evt.tagline || evt.description}
                        </p>

                        <div style={{ display: "flex", alignItems: "center", gap: "6px", color: "var(--att-text-body)", fontSize: "11px", marginBottom: "14px" }}>
                          <MapPin size={13} style={{ color: "var(--att-text-2)", flexShrink: 0 }} />
                          <span style={{ fontWeight: 600, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                            {evt.venueName}
                          </span>
                        </div>
                      </div>

                      {/* Genuine popularity footer & CTA */}
                      <div>
                        <div
                          style={{
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "space-between",
                            fontSize: "11px",
                            color: "var(--att-ok)",
                            padding: "8px 0",
                            borderTop: "1px solid rgba(255, 255, 255, 0.08)",
                            marginBottom: "12px",
                          }}
                        >
                          <span>✓ {stats.availableDishes} verified portions</span>
                          <span style={{ color: "var(--att-text-1)", fontWeight: 700 }}>
                            From {formatMoney(stats.entryStartingUSD)}
                          </span>
                        </div>

                        <div style={{ display: "flex", gap: "8px" }}>
                          <button
                            type="button"
                            onClick={() => {
                              onSelectEvent(originalIndex);
                              if (onNavigateToDetails) {
                                onNavigateToDetails(originalIndex);
                              } else {
                                onNavigateToFood(originalIndex);
                              }
                            }}
                            id={`btn-explore-event-details-${idx}`}
                            style={{
                              flex: 1,
                              background: isSelected ? "var(--att-accent)" : "var(--att-surface-2)",
                              border: isSelected ? "none" : "1px solid rgba(255, 255, 255, 0.15)",
                              borderRadius: "10px",
                              padding: "10px",
                              fontSize: "13px",
                              fontWeight: 700,
                              color: "var(--att-on-accent)",
                              cursor: "pointer",
                              display: "flex",
                              alignItems: "center",
                              justifyContent: "center",
                              gap: "6px",
                            }}
                          >
                            <span>View Details & Passes</span>
                            <ArrowRight size={14} />
                          </button>
                          <button
                            type="button"
                            onClick={() => {
                              onSelectEvent(originalIndex);
                              onNavigateToFood(originalIndex);
                            }}
                            id={`btn-explore-event-food-${idx}`}
                            title="Pre-Order Food"
                            style={{
                              background: "rgba(255, 255, 255, 0.08)",
                              border: "1px solid rgba(255, 255, 255, 0.15)",
                              borderRadius: "10px",
                              padding: "10px 14px",
                              fontSize: "12px",
                              fontWeight: 700,
                              color: "var(--att-ok)",
                              cursor: "pointer",
                              display: "flex",
                              alignItems: "center",
                              gap: "4px",
                            }}
                          >
                            <Utensils size={14} />
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}

          {/* Incremental Loading / Pagination */}
          {filteredEvents.length > visibleCount && (
            <div style={{ textAlign: "center", marginTop: "20px" }}>
              <button
                type="button"
                onClick={() => setVisibleCount((prev) => prev + 6)}
                id="btn-load-more-events"
                style={{
                  background: "var(--att-surface-1)",
                  border: "1px solid rgba(255, 255, 255, 0.15)",
                  borderRadius: "9999px",
                  padding: "12px 28px",
                  fontSize: "13px",
                  fontWeight: 700,
                  color: "var(--att-text-1)",
                  cursor: "pointer",
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "8px",
                  boxShadow: "0 4px 12px rgba(0,0,0,0.2)",
                  transition: "all 0.2s ease",
                }}
              >
                <span>Load More Events</span>
                <ChevronRight size={14} />
              </button>
            </div>
          )}
        </section>
      </main>

      {/* 7. Bottom Navigation (Fixed 5-item bar with clear active-state styling) */}
      <nav
        aria-label="Discovery bottom navigation"
        id="island-bottom-nav"
        style={{
          position: "fixed",
          bottom: 0,
          left: 0,
          right: 0,
          backgroundColor: "var(--att-bg)",
          borderTop: "1px solid rgba(255, 255, 255, 0.1)",
          padding: "8px 16px",
          display: "flex",
          justifyContent: "space-around",
          alignItems: "center",
          zIndex: 50,
          backdropFilter: "blur(16px)",
          boxShadow: "0 -4px 20px rgba(0, 0, 0, 0.4)",
        }}
      >
        {/* Item 1: Home */}
        <button
          type="button"
          onClick={() => handleNavClick("home")}
          id="bottom-nav-home"
          aria-current={activeBottomNav === "home" ? "page" : undefined}
          aria-label="Home discovery page"
          style={{
            background: "none",
            border: "none",
            color: activeBottomNav === "home" ? "var(--att-accent)" : "var(--att-text-2)",
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            gap: "3px",
            cursor: "pointer",
            padding: "6px 12px",
            borderRadius: "12px",
            transition: "all 0.15s ease",
          }}
        >
          <Home size={20} />
          <span style={{ fontSize: "11px", fontWeight: activeBottomNav === "home" ? 800 : 500 }}>
            Home
          </span>
          {activeBottomNav === "home" && (
            <span
              style={{
                width: "4px",
                height: "4px",
                borderRadius: "50%",
                backgroundColor: "var(--att-accent)",
              }}
            />
          )}
        </button>

        {/* Item 2: Explore */}
        <button
          type="button"
          onClick={() => {
            handleNavClick("explore");
            const el = document.getElementById("input-discovery-search");
            if (el) el.focus();
          }}
          id="bottom-nav-explore"
          aria-current={activeBottomNav === "explore" ? "page" : undefined}
          aria-label="Explore and search events"
          style={{
            background: "none",
            border: "none",
            color: activeBottomNav === "explore" ? "var(--att-accent)" : "var(--att-text-2)",
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            gap: "3px",
            cursor: "pointer",
            padding: "6px 12px",
            borderRadius: "12px",
            transition: "all 0.15s ease",
          }}
        >
          <Compass size={20} />
          <span style={{ fontSize: "11px", fontWeight: activeBottomNav === "explore" ? 800 : 500 }}>
            Explore
          </span>
          {activeBottomNav === "explore" && (
            <span
              style={{
                width: "4px",
                height: "4px",
                borderRadius: "50%",
                backgroundColor: "var(--att-accent)",
              }}
            />
          )}
        </button>

        {/* Item 3: Tickets */}
        <button
          type="button"
          onClick={() => handleNavClick("tickets")}
          id="bottom-nav-tickets"
          aria-current={activeBottomNav === "tickets" ? "page" : undefined}
          aria-label="Digital tickets wallet"
          style={{
            background: "none",
            border: "none",
            color: activeBottomNav === "tickets" ? "var(--att-accent)" : "var(--att-text-2)",
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            gap: "3px",
            cursor: "pointer",
            padding: "6px 12px",
            borderRadius: "12px",
            transition: "all 0.15s ease",
          }}
        >
          <Ticket size={20} />
          <span style={{ fontSize: "11px", fontWeight: activeBottomNav === "tickets" ? 800 : 500 }}>
            My Tickets
          </span>
          {activeBottomNav === "tickets" && (
            <span
              style={{
                width: "4px",
                height: "4px",
                borderRadius: "50%",
                backgroundColor: "var(--att-accent)",
              }}
            />
          )}
        </button>

        {/* Item 4: Food */}
        <button
          type="button"
          onClick={() => handleNavClick("food")}
          id="bottom-nav-food"
          aria-current={activeBottomNav === "food" ? "page" : undefined}
          aria-label="Food culinary ordering"
          style={{
            background: "none",
            border: "none",
            color: activeBottomNav === "food" ? "var(--att-accent)" : "var(--att-text-2)",
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            gap: "3px",
            cursor: "pointer",
            padding: "6px 12px",
            borderRadius: "12px",
            transition: "all 0.15s ease",
          }}
        >
          <ChefHat size={20} />
          <span style={{ fontSize: "11px", fontWeight: activeBottomNav === "food" ? 800 : 500 }}>
            Orders
          </span>
          {activeBottomNav === "food" && (
            <span
              style={{
                width: "4px",
                height: "4px",
                borderRadius: "50%",
                backgroundColor: "var(--att-accent)",
              }}
            />
          )}
        </button>

        {/* Item 5: Profile */}
        <button
          type="button"
          onClick={() => handleNavClick("profile")}
          id="bottom-nav-profile"
          aria-current={activeBottomNav === "profile" ? "page" : undefined}
          aria-label="Profile and account settings"
          style={{
            background: "none",
            border: "none",
            color: activeBottomNav === "profile" ? "var(--att-accent)" : "var(--att-text-2)",
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            gap: "3px",
            cursor: "pointer",
            padding: "6px 12px",
            borderRadius: "12px",
            transition: "all 0.15s ease",
          }}
        >
          <User size={20} />
          <span style={{ fontSize: "11px", fontWeight: activeBottomNav === "profile" ? 800 : 500 }}>
            Profile
          </span>
          {activeBottomNav === "profile" && (
            <span
              style={{
                width: "4px",
                height: "4px",
                borderRadius: "50%",
                backgroundColor: "var(--att-accent)",
              }}
            />
          )}
        </button>
      </nav>
    </div>
  );
};
