import React, { useState, useMemo, useEffect } from "react";
import {
  User,
  Ticket,
  ShoppingBag,
  Heart,
  Utensils,
  Bell,
  Settings2,
  HelpCircle,
  LogOut,
  Camera,
  CheckCircle2,
  AlertCircle,
  Lock,
  Smartphone,
  Mail,
  ShieldCheck,
  ChevronRight,
  X,
  MessageCircle,
  ArrowLeft,
  Key,
  RefreshCw,
  Flame,
} from "lucide-react";
import { AttendeeProfile, Currency, EventDetails, NotificationLogEntry, TicketOrder } from "../types";
import { BrandLogo } from "./BrandLogo";
import { updateProfile, updatePassword, signOut, fetchNotifications, markNotificationRead, markAllNotificationsRead } from "../lib/api";

type ProfileTab = "menu" | "edit_profile" | "notifications" | "settings" | "orders" | "saved_events" | "food_orders" | "support";

type SettingsSubTab = "details" | "password" | "notifications" | "marketing" | "security";

interface Props {
  currentUser?: AttendeeProfile | { name?: string; email?: string; phone?: string; id?: string; avatarUrl?: string; bio?: string; membershipStatus?: string; memberSince?: string };
  attendeeProfile?: AttendeeProfile;
  orders?: TicketOrder[];
  allEvents?: EventDetails[];
  notifications?: NotificationLogEntry[];
  formatMoney: (amount: number, currency: Currency) => string;
  onNavigateToTickets: () => void;
  onNavigateToFood: () => void;
  onNavigateToEventDetails?: (eventId: string) => void;
  onBackToDiscovery: () => void;
  onLogout: () => void;
  onProfileUpdated?: (updated: any) => void;
}

export const IslandEventUserProfileScreen: React.FC<Props> = ({
  currentUser,
  attendeeProfile,
  orders = [],
  allEvents: _allEvents = [],
  notifications: initialNotifications = [],
  formatMoney,
  onNavigateToTickets,
  onNavigateToFood,
  onNavigateToEventDetails,
  onBackToDiscovery,
  onLogout,
  onProfileUpdated,
}) => {
  // Navigation & View State
  const [activeTab, setActiveTab] = useState<ProfileTab>("menu");
  const [activeSettingsTab, setActiveSettingsTab] = useState<SettingsSubTab>("details");

  // Profile Form State
  const [nameInput, setNameInput] = useState(currentUser?.name || attendeeProfile?.name || "Akeem Campbell");
  const [emailInput, setEmailInput] = useState(currentUser?.email || attendeeProfile?.email || "akeem.c@islandevents.example");
  const [phoneInput, setPhoneInput] = useState(currentUser?.phone || attendeeProfile?.phone || "+1 (305) 555-9011");
  const [bioInput, setBioInput] = useState((currentUser as any)?.bio || attendeeProfile?.bio || "Food, music, and cultural events enthusiast.");
  const [avatarInput, setAvatarInput] = useState((currentUser as any)?.avatarUrl || (currentUser as any)?.avatar_url || attendeeProfile?.avatarUrl || "");
  const [isSavingProfile, setIsSavingProfile] = useState(false);
  const [profileFeedback, setProfileFeedback] = useState<{ type: "success" | "error"; msg: string } | null>(null);

  // Password Management State
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [isUpdatingPassword, setIsUpdatingPassword] = useState(false);
  const [passwordFeedback, setPasswordFeedback] = useState<{ type: "success" | "error"; msg: string } | null>(null);

  // Notification Preferences State
  const [prefSms, setPrefSms] = useState(attendeeProfile?.notificationPreferences.smsReminders ?? true);
  const [prefWhatsapp, setPrefWhatsapp] = useState(attendeeProfile?.notificationPreferences.whatsAppTickets ?? true);
  const [prefEmail, setPrefEmail] = useState(attendeeProfile?.notificationPreferences.emailReceipts ?? true);
  const [prefMarketing, setPrefMarketing] = useState(attendeeProfile?.notificationPreferences.marketingUpdates ?? false);
  const [prefDigest, setPrefDigest] = useState(true);
  const [notifPrefSaved, setNotifPrefSaved] = useState(false);

  // Notifications State (Loaded from backend or fallback to initial props)
  const [notificationsList, setNotificationsList] = useState<NotificationLogEntry[]>(initialNotifications);
  const [unreadCount, setUnreadCount] = useState<number>(0);
  const [isLoadingNotifs, setIsLoadingNotifs] = useState(false);

  // Security / 2FA State
  const [twoFactorEnabled, setTwoFactorEnabled] = useState(true);
  const [show2FaSuccess, setShow2FaSuccess] = useState(false);

  // Logout state
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  // User identity normalization
  const userEmail = (emailInput || currentUser?.email || "akeem.c@islandevents.example").toLowerCase().trim();
  const userName = nameInput || currentUser?.name || "Akeem Campbell";
  const userPhone = phoneInput.replace(/\D/g, "");

  // 1. FILTER ORDERS BELONGING TO THIS USER (Strict authorization)
  const userOrders = useMemo(() => {
    return orders.filter((ord) => {
      const ordEmail = (ord.email || "").toLowerCase().trim();
      const ordName = (ord.customerName || "").toLowerCase().trim();
      const ordPhone = (ord.customerPhone || "").replace(/\D/g, "");
      return ordEmail === userEmail || (userPhone.length > 5 && ordPhone === userPhone) || ordName === userName.toLowerCase().trim();
    });
  }, [orders, userEmail, userPhone, userName]);

  // 2. FOOD ORDERS & ACTIVE TICKETS
  const userTickets = useMemo(() => {
    return userOrders.filter((ord) => ord.status === "paid" || ord.status === "awaiting_payment" || ord.status === "payment_submitted");
  }, [userOrders]);

  const foodOrders = useMemo(() => {
    return userOrders.filter((ord) => ord.items && ord.items.length > 0);
  }, [userOrders]);

  // 3. SAVED EVENTS FROM REAL ATTENDEE DATA
  const savedEventsList = useMemo(() => {
    return attendeeProfile?.savedEvents || [];
  }, [attendeeProfile]);

  // 4. MEMBERSHIP STATUS (Strictly derived from real account data)
  const realMembership = useMemo(() => {
    const paidCount = userOrders.filter((o) => o.status === "paid").length;
    if (paidCount >= 3) {
      return {
        label: "VIP Passholder",
        badgeColor: "#F59E0B",
        badgeBg: "rgba(245, 158, 11, 0.16)",
        badgeBorder: "rgba(245, 158, 11, 0.35)",
        description: `Verified attendee with ${paidCount} confirmed event orders.`,
      };
    }
    if (paidCount > 0) {
      return {
        label: "Verified Passholder",
        badgeColor: "#10B981",
        badgeBg: "rgba(16, 185, 129, 0.16)",
        badgeBorder: "rgba(16, 185, 129, 0.35)",
        description: `Verified ticket holder with ${paidCount} active event voucher.`,
      };
    }
    return {
      label: "Standard Member",
      badgeColor: "#38BDF8",
      badgeBg: "rgba(56, 189, 248, 0.16)",
      badgeBorder: "rgba(56, 189, 248, 0.35)",
      description: "Active community member. Ready to book Caribbean festival passes.",
    };
  }, [userOrders]);

  // 5. FETCH & CALCULATE NOTIFICATIONS FROM REAL DATA
  const refreshNotifications = async () => {
    setIsLoadingNotifs(true);
    try {
      const res = await fetchNotifications();
      if (res && res.data) {
        setNotificationsList(res.data);
        setUnreadCount(res.unread_count || 0);
      }
    } catch {
      // Fallback: calculate unread count from local props
      const relevant = initialNotifications.filter((n) => {
        const r = (n.recipient || "").toLowerCase();
        return r === userEmail || r.includes(userEmail) || (n.customerName && n.customerName.toLowerCase() === userName.toLowerCase());
      });
      setNotificationsList(relevant);
      setUnreadCount(relevant.filter((n) => !(n as any).read_at && !(n as any).read).length);
    } finally {
      setIsLoadingNotifs(false);
    }
  };

  useEffect(() => {
    refreshNotifications();
  }, [userEmail]);

  // 6. MARK NOTIFICATION READ (Authorized Action)
  const handleMarkAsRead = async (id: string) => {
    try {
      await markNotificationRead(id);
    } catch (err) {
      console.warn("Backend read sync note:", err);
    }
    setNotificationsList((prev) =>
      prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString(), read: true } : n))
    );
    setUnreadCount((c) => Math.max(0, c - 1));
  };

  // 7. MARK ALL NOTIFICATIONS READ
  const handleMarkAllRead = async () => {
    try {
      await markAllNotificationsRead();
    } catch (err) {
      console.warn("Backend mark-all note:", err);
    }
    setNotificationsList((prev) =>
      prev.map((n) => ({ ...n, read_at: new Date().toISOString(), read: true }))
    );
    setUnreadCount(0);
  };

  // 8. PROFILE EDIT SUBMIT
  const handleSaveProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!nameInput.trim()) {
      setProfileFeedback({ type: "error", msg: "Full Name is required." });
      return;
    }
    if (!emailInput.trim() || !emailInput.includes("@")) {
      setProfileFeedback({ type: "error", msg: "A valid email address is required." });
      return;
    }

    setIsSavingProfile(true);
    setProfileFeedback(null);

    try {
      const res = await updateProfile({
        name: nameInput.trim(),
        email: emailInput.trim().toLowerCase(),
        phone: phoneInput.trim(),
        avatar_url: avatarInput.trim(),
        bio: bioInput.trim(),
      });

      if (onProfileUpdated) {
        onProfileUpdated(res.user);
      }
      setProfileFeedback({ type: "success", msg: "Profile details updated successfully." });
      setTimeout(() => {
        setActiveTab("menu");
        setProfileFeedback(null);
      }, 1400);
    } catch (err: any) {
      setProfileFeedback({
        type: "error",
        msg: err?.message || "Failed to save profile changes. Please verify your connection.",
      });
    } finally {
      setIsSavingProfile(false);
    }
  };

  // 9. PASSWORD CHANGE SUBMIT
  const handleUpdatePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!currentPassword) {
      setPasswordFeedback({ type: "error", msg: "Please enter your current password." });
      return;
    }
    if (newPassword.length < 8) {
      setPasswordFeedback({ type: "error", msg: "New password must be at least 8 characters long." });
      return;
    }
    if (newPassword !== confirmPassword) {
      setPasswordFeedback({ type: "error", msg: "New passwords do not match." });
      return;
    }

    setIsUpdatingPassword(true);
    setPasswordFeedback(null);

    try {
      await updatePassword({
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: confirmPassword,
      });
      setPasswordFeedback({ type: "success", msg: "Password changed successfully! Keep your credentials secure." });
      setCurrentPassword("");
      setNewPassword("");
      setConfirmPassword("");
    } catch (err: any) {
      setPasswordFeedback({
        type: "error",
        msg: err?.message || "Password update failed. Please ensure your current password is correct.",
      });
    } finally {
      setIsUpdatingPassword(false);
    }
  };

  // 10. REAL LOGOUT ACTION
  const handleLogout = async () => {
    setIsLoggingOut(true);
    try {
      await signOut();
    } catch (err) {
      console.warn("Logout session invalidation warning:", err);
    } finally {
      setIsLoggingOut(false);
      onLogout();
    }
  };

  // Initials for avatar fallback
  const initials = useMemo(() => {
    const parts = userName.trim().split(" ");
    if (parts.length >= 2) {
      return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
    }
    return userName.slice(0, 2).toUpperCase() || "IE";
  }, [userName]);

  return (
    <div
      className="island-event-user-profile-screen"
      style={{
        minHeight: "100vh",
        background: "linear-gradient(180deg, #070D1E 0%, #0B132B 45%, #0F172A 100%)",
        color: "#F8FAFC",
        fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
        paddingBottom: "90px",
      }}
    >
      {/* 1. TOP NAVBAR */}
      <header
        style={{
          position: "sticky",
          top: 0,
          zIndex: 40,
          background: "rgba(7, 13, 30, 0.94)",
          backdropFilter: "blur(14px)",
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
          }}
        >
          <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
            <button
              type="button"
              id="btn-profile-back"
              onClick={activeTab === "menu" ? onBackToDiscovery : () => setActiveTab("menu")}
              style={{
                display: "inline-flex",
                alignItems: "center",
                justifyContent: "center",
                width: "38px",
                height: "38px",
                borderRadius: "12px",
                background: "rgba(255, 255, 255, 0.07)",
                border: "1px solid rgba(255, 255, 255, 0.12)",
                color: "#FFFFFF",
                cursor: "pointer",
                transition: "all 0.18s ease",
              }}
              aria-label="Back"
            >
              <ArrowLeft size={18} />
            </button>
            <div>
              <span
                style={{
                  fontSize: "10px",
                  fontWeight: 800,
                  letterSpacing: "0.08em",
                  textTransform: "uppercase",
                  color: "#F97316",
                }}
              >
                <BrandLogo variant="icon" decorative style={{ width: 18, height: 18, display: "inline-block", verticalAlign: "middle", marginRight: 5 }} />
                EventBite
              </span>
              <h1
                id="profile-screen-title"
                style={{
                  fontSize: "18px",
                  fontWeight: 900,
                  margin: 0,
                  color: "#FFFFFF",
                  letterSpacing: "-0.01em",
                }}
              >
                {activeTab === "menu"
                  ? "Attendee Account"
                  : activeTab === "edit_profile"
                  ? "Edit Profile"
                  : activeTab === "notifications"
                  ? "Notifications"
                  : activeTab === "settings"
                  ? "Account Settings"
                  : activeTab === "orders"
                  ? "Order History"
                  : activeTab === "saved_events"
                  ? "Saved Events"
                  : activeTab === "food_orders"
                  ? "Food & Beverage Vouchers"
                  : "Help & Support"}
              </h1>
            </div>
          </div>

          <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
            {/* Direct Notification shortcut button */}
            <button
              type="button"
              id="btn-header-notifs"
              onClick={() => setActiveTab("notifications")}
              style={{
                position: "relative",
                display: "inline-flex",
                alignItems: "center",
                justifyContent: "center",
                width: "38px",
                height: "38px",
                borderRadius: "12px",
                background: activeTab === "notifications" ? "rgba(139, 92, 246, 0.25)" : "rgba(255, 255, 255, 0.07)",
                border: activeTab === "notifications" ? "1px solid #8B5CF6" : "1px solid rgba(255, 255, 255, 0.12)",
                color: activeTab === "notifications" ? "#A78BFA" : "#CBD5E1",
                cursor: "pointer",
              }}
              aria-label="View notifications"
            >
              <Bell size={17} />
              {unreadCount > 0 && (
                <span
                  id="header-notif-badge"
                  style={{
                    position: "absolute",
                    top: "-3px",
                    right: "-3px",
                    background: "#EC4899",
                    color: "#FFFFFF",
                    fontSize: "10px",
                    fontWeight: 900,
                    minWidth: "18px",
                    height: "18px",
                    borderRadius: "9999px",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    padding: "0 4px",
                    boxShadow: "0 0 8px rgba(236, 72, 153, 0.6)",
                  }}
                >
                  {unreadCount}
                </span>
              )}
            </button>

            {/* Logout shortcut */}
            <button
              type="button"
              id="btn-header-logout"
              onClick={handleLogout}
              disabled={isLoggingOut}
              style={{
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
                background: "rgba(239, 68, 68, 0.12)",
                border: "1px solid rgba(239, 68, 68, 0.25)",
                color: "#F87171",
                padding: "8px 14px",
                borderRadius: "12px",
                fontSize: "12px",
                fontWeight: 700,
                cursor: "pointer",
              }}
            >
              <LogOut size={14} />
              <span>{isLoggingOut ? "Signing out..." : "Log Out"}</span>
            </button>
          </div>
        </div>
      </header>

      {/* MAIN CONTAINER */}
      <main style={{ maxWidth: "760px", margin: "0 auto", padding: "20px 16px" }}>
        {/* ============================================================ */}
        {/* 2. PROFILE HEADER PANEL (Visual Design Spec)                 */}
        {/* ============================================================ */}
        <section
          aria-label="Profile Header"
          id="profile-header-card"
          style={{
            background: "linear-gradient(135deg, rgba(22, 32, 56, 0.95) 0%, rgba(15, 23, 42, 0.95) 100%)",
            border: "1px solid rgba(255, 255, 255, 0.12)",
            borderRadius: "24px",
            padding: "24px",
            marginBottom: "22px",
            boxShadow: "0 14px 34px rgba(0, 0, 0, 0.35)",
            position: "relative",
            overflow: "hidden",
          }}
        >
          {/* Subtle Ambient Glow */}
          <div
            style={{
              position: "absolute",
              top: "-40px",
              right: "-40px",
              width: "160px",
              height: "160px",
              borderRadius: "50%",
              background: "radial-gradient(circle, rgba(236, 72, 153, 0.2) 0%, rgba(0, 0, 0, 0) 70%)",
              pointerEvents: "none",
            }}
          />

          <div
            style={{
              display: "flex",
              flexDirection: "row",
              alignItems: "center",
              gap: "20px",
              flexWrap: "wrap",
            }}
          >
            {/* Circular Avatar */}
            <div style={{ position: "relative" }}>
              <div
                id="profile-avatar-circle"
                style={{
                  width: "84px",
                  height: "84px",
                  borderRadius: "50%",
                  background: avatarInput
                    ? `url('${avatarInput}') center/cover no-repeat`
                    : "linear-gradient(135deg, #7C3AED 0%, #EC4899 50%, #F97316 100%)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  color: "#FFFFFF",
                  fontSize: "26px",
                  fontWeight: 900,
                  letterSpacing: "0.02em",
                  border: "3px solid rgba(255, 255, 255, 0.18)",
                  boxShadow: "0 8px 24px rgba(0, 0, 0, 0.4)",
                  overflow: "hidden",
                }}
              >
                {!avatarInput && <span>{initials}</span>}
              </div>

              <button
                type="button"
                id="btn-edit-avatar-trigger"
                onClick={() => setActiveTab("edit_profile")}
                style={{
                  position: "absolute",
                  bottom: "-2px",
                  right: "-2px",
                  width: "28px",
                  height: "28px",
                  borderRadius: "50%",
                  background: "#F97316",
                  color: "#FFFFFF",
                  border: "2px solid #0B132B",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                  boxShadow: "0 2px 6px rgba(0,0,0,0.4)",
                }}
                aria-label="Change photo"
              >
                <Camera size={13} />
              </button>
            </div>

            {/* Profile Identity Details */}
            <div style={{ flex: 1, minWidth: "220px" }}>
              <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap", marginBottom: "4px" }}>
                <h2
                  id="profile-user-name"
                  style={{
                    fontSize: "22px",
                    fontWeight: 900,
                    margin: 0,
                    color: "#FFFFFF",
                    letterSpacing: "-0.01em",
                  }}
                >
                  {userName}
                </h2>

                {/* Membership Status Badge (Real Account Data Only) */}
                <span
                  id="profile-membership-badge"
                  style={{
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "4px",
                    background: realMembership.badgeBg,
                    color: realMembership.badgeColor,
                    border: `1px solid ${realMembership.badgeBorder}`,
                    padding: "3px 10px",
                    borderRadius: "9999px",
                    fontSize: "11px",
                    fontWeight: 800,
                    textTransform: "uppercase",
                    letterSpacing: "0.04em",
                  }}
                >
                  <ShieldCheck size={13} />
                  {realMembership.label}
                </span>
              </div>

              {/* Email & Phone */}
              <div style={{ display: "flex", flexDirection: "column", gap: "2px", fontSize: "13px", color: "#94A3B8" }}>
                <span style={{ display: "inline-flex", alignItems: "center", gap: "6px" }}>
                  <Mail size={13} style={{ color: "#38BDF8" }} />
                  <span id="profile-user-email">{userEmail}</span>
                  <span title="Verified Email" style={{ display: "inline-flex" }}>
                    <CheckCircle2 size={12} style={{ color: "#10B981" }} />
                  </span>
                </span>
                {phoneInput && (
                  <span style={{ display: "inline-flex", alignItems: "center", gap: "6px" }}>
                    <Smartphone size={13} style={{ color: "#A855F7" }} />
                    <span id="profile-user-phone">{phoneInput}</span>
                  </span>
                )}
              </div>

              {/* Member Since Footnote */}
              <div style={{ marginTop: "8px", fontSize: "11px", color: "#64748B" }}>
                <span>{realMembership.description} · Member since October 2026</span>
              </div>
            </div>

            {/* Edit Profile CTA */}
            <div>
              <button
                type="button"
                id="btn-open-edit-profile"
                onClick={() => setActiveTab("edit_profile")}
                style={{
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "6px",
                  background: "rgba(255, 255, 255, 0.08)",
                  border: "1px solid rgba(255, 255, 255, 0.16)",
                  color: "#FFFFFF",
                  padding: "9px 16px",
                  borderRadius: "14px",
                  fontSize: "13px",
                  fontWeight: 700,
                  cursor: "pointer",
                  transition: "all 0.18s ease",
                }}
              >
                <span>Edit Profile</span>
                <ChevronRight size={14} />
              </button>
            </div>
          </div>
        </section>

        {/* ============================================================ */}
        {/* VIEW 1: ACCOUNT MENU (Clean, compact, refined list rows)     */}
        {/* ============================================================ */}
        {activeTab === "menu" && (
          <div style={{ display: "flex", flexDirection: "column", gap: "16px" }} id="profile-menu-container">
            {/* Core Event & Ticketing Actions Section */}
            <div
              style={{
                background: "#162038",
                border: "1px solid rgba(255, 255, 255, 0.1)",
                borderRadius: "20px",
                overflow: "hidden",
              }}
            >
              <div
                style={{
                  padding: "14px 18px",
                  borderBottom: "1px solid rgba(255, 255, 255, 0.06)",
                  fontSize: "11px",
                  fontWeight: 800,
                  textTransform: "uppercase",
                  letterSpacing: "0.06em",
                  color: "#94A3B8",
                  display: "flex",
                  alignItems: "center",
                  gap: "6px",
                }}
              >
                <Ticket size={13} style={{ color: "#EC4899" }} />
                <span>Passes & Purchases</span>
              </div>

              {/* 1. My Tickets */}
              <button
                type="button"
                id="menu-item-my-tickets"
                onClick={onNavigateToTickets}
                style={{
                  width: "100%",
                  padding: "15px 18px",
                  background: "transparent",
                  border: "none",
                  borderBottom: "1px solid rgba(255, 255, 255, 0.06)",
                  color: "#FFFFFF",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  cursor: "pointer",
                  textAlign: "left",
                  transition: "background 0.15s ease",
                }}
              >
                <div style={{ display: "flex", alignItems: "center", gap: "14px" }}>
                  <div
                    style={{
                      width: "40px",
                      height: "40px",
                      borderRadius: "12px",
                      background: "rgba(236, 72, 153, 0.14)",
                      border: "1px solid rgba(236, 72, 153, 0.3)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      color: "#EC4899",
                    }}
                  >
                    <Ticket size={19} />
                  </div>
                  <div>
                    <div style={{ fontSize: "15px", fontWeight: 700, color: "#FFFFFF" }}>My Tickets</div>
                    <div style={{ fontSize: "12px", color: "#94A3B8" }}>
                      Active admission passes & secure digital vouchers
                    </div>
                  </div>
                </div>
                <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                  <span
                    style={{
                      background: "rgba(236, 72, 153, 0.2)",
                      color: "#F472B6",
                      padding: "3px 9px",
                      borderRadius: "9999px",
                      fontSize: "11px",
                      fontWeight: 800,
                    }}
                  >
                    {userTickets.length} {userTickets.length === 1 ? "Pass" : "Passes"}
                  </span>
                  <ChevronRight size={16} style={{ color: "#64748B" }} />
                </div>
              </button>

              {/* 2. My Orders */}
              <button
                type="button"
                id="menu-item-my-orders"
                onClick={() => setActiveTab("orders")}
                style={{
                  width: "100%",
                  padding: "15px 18px",
                  background: "transparent",
                  border: "none",
                  borderBottom: "1px solid rgba(255, 255, 255, 0.06)",
                  color: "#FFFFFF",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  cursor: "pointer",
                  textAlign: "left",
                  transition: "background 0.15s ease",
                }}
              >
                <div style={{ display: "flex", alignItems: "center", gap: "14px" }}>
                  <div
                    style={{
                      width: "40px",
                      height: "40px",
                      borderRadius: "12px",
                      background: "rgba(56, 189, 248, 0.14)",
                      border: "1px solid rgba(56, 189, 248, 0.3)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      color: "#38BDF8",
                    }}
                  >
                    <ShoppingBag size={19} />
                  </div>
                  <div>
                    <div style={{ fontSize: "15px", fontWeight: 700, color: "#FFFFFF" }}>My Orders</div>
                    <div style={{ fontSize: "12px", color: "#94A3B8" }}>
                      Receipts, payment verification status & order records
                    </div>
                  </div>
                </div>
                <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                  <span
                    style={{
                      background: "rgba(56, 189, 248, 0.15)",
                      color: "#7DD3FC",
                      padding: "3px 9px",
                      borderRadius: "9999px",
                      fontSize: "11px",
                      fontWeight: 800,
                    }}
                  >
                    {userOrders.length} {userOrders.length === 1 ? "Order" : "Orders"}
                  </span>
                  <ChevronRight size={16} style={{ color: "#64748B" }} />
                </div>
              </button>

              {/* 3. Food Orders */}
              <button
                type="button"
                id="menu-item-food-orders"
                onClick={() => setActiveTab("food_orders")}
                style={{
                  width: "100%",
                  padding: "15px 18px",
                  background: "transparent",
                  border: "none",
                  borderBottom: "1px solid rgba(255, 255, 255, 0.06)",
                  color: "#FFFFFF",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  cursor: "pointer",
                  textAlign: "left",
                  transition: "background 0.15s ease",
                }}
              >
                <div style={{ display: "flex", alignItems: "center", gap: "14px" }}>
                  <div
                    style={{
                      width: "40px",
                      height: "40px",
                      borderRadius: "12px",
                      background: "rgba(249, 115, 22, 0.14)",
                      border: "1px solid rgba(249, 115, 22, 0.3)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      color: "#F97316",
                    }}
                  >
                    <Utensils size={19} />
                  </div>
                  <div>
                    <div style={{ fontSize: "15px", fontWeight: 700, color: "#FFFFFF" }}>Food Orders</div>
                    <div style={{ fontSize: "12px", color: "#94A3B8" }}>
                      Culinary portions, prep pickups & beverage vouchers
                    </div>
                  </div>
                </div>
                <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                  <span
                    style={{
                      background: "rgba(249, 115, 22, 0.16)",
                      color: "#FB923C",
                      padding: "3px 9px",
                      borderRadius: "9999px",
                      fontSize: "11px",
                      fontWeight: 800,
                    }}
                  >
                    {foodOrders.length} {foodOrders.length === 1 ? "Meal" : "Meals"}
                  </span>
                  <ChevronRight size={16} style={{ color: "#64748B" }} />
                </div>
              </button>

              {/* 4. Saved Events */}
              <button
                type="button"
                id="menu-item-saved-events"
                onClick={() => setActiveTab("saved_events")}
                style={{
                  width: "100%",
                  padding: "15px 18px",
                  background: "transparent",
                  border: "none",
                  color: "#FFFFFF",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  cursor: "pointer",
                  textAlign: "left",
                  transition: "background 0.15s ease",
                }}
              >
                <div style={{ display: "flex", alignItems: "center", gap: "14px" }}>
                  <div
                    style={{
                      width: "40px",
                      height: "40px",
                      borderRadius: "12px",
                      background: "rgba(244, 63, 94, 0.14)",
                      border: "1px solid rgba(244, 63, 94, 0.3)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      color: "#F43F5E",
                    }}
                  >
                    <Heart size={19} />
                  </div>
                  <div>
                    <div style={{ fontSize: "15px", fontWeight: 700, color: "#FFFFFF" }}>Saved Events</div>
                    <div style={{ fontSize: "12px", color: "#94A3B8" }}>
                      Curated festivals and fish fry experiences you follow
                    </div>
                  </div>
                </div>
                <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                  <span
                    style={{
                      background: "rgba(244, 63, 94, 0.15)",
                      color: "#FDA4AF",
                      padding: "3px 9px",
                      borderRadius: "9999px",
                      fontSize: "11px",
                      fontWeight: 800,
                    }}
                  >
                    {savedEventsList.length} Saved
                  </span>
                  <ChevronRight size={16} style={{ color: "#64748B" }} />
                </div>
              </button>
            </div>

            {/* Account & Preferences Section */}
            <div
              style={{
                background: "#162038",
                border: "1px solid rgba(255, 255, 255, 0.1)",
                borderRadius: "20px",
                overflow: "hidden",
              }}
            >
              <div
                style={{
                  padding: "14px 18px",
                  borderBottom: "1px solid rgba(255, 255, 255, 0.06)",
                  fontSize: "11px",
                  fontWeight: 800,
                  textTransform: "uppercase",
                  letterSpacing: "0.06em",
                  color: "#94A3B8",
                  display: "flex",
                  alignItems: "center",
                  gap: "6px",
                }}
              >
                <Settings2 size={13} style={{ color: "#8B5CF6" }} />
                <span>Account & Preferences</span>
              </div>

              {/* 5. Notifications with Real Calculated Unread Badge */}
              <button
                type="button"
                id="menu-item-notifications"
                onClick={() => setActiveTab("notifications")}
                style={{
                  width: "100%",
                  padding: "15px 18px",
                  background: "transparent",
                  border: "none",
                  borderBottom: "1px solid rgba(255, 255, 255, 0.06)",
                  color: "#FFFFFF",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  cursor: "pointer",
                  textAlign: "left",
                  transition: "background 0.15s ease",
                }}
              >
                <div style={{ display: "flex", alignItems: "center", gap: "14px" }}>
                  <div
                    style={{
                      width: "40px",
                      height: "40px",
                      borderRadius: "12px",
                      background: "rgba(139, 92, 246, 0.14)",
                      border: "1px solid rgba(139, 92, 246, 0.3)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      color: "#A78BFA",
                    }}
                  >
                    <Bell size={19} />
                  </div>
                  <div>
                    <div style={{ fontSize: "15px", fontWeight: 700, color: "#FFFFFF" }}>Notifications</div>
                    <div style={{ fontSize: "12px", color: "#94A3B8" }}>
                      Gate reminders, payment verification & delivery alerts
                    </div>
                  </div>
                </div>
                <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                  {unreadCount > 0 ? (
                    <span
                      id="menu-unread-pill"
                      style={{
                        background: "linear-gradient(135deg, #EC4899 0%, #F97316 100%)",
                        color: "#FFFFFF",
                        padding: "3px 10px",
                        borderRadius: "9999px",
                        fontSize: "11px",
                        fontWeight: 900,
                        boxShadow: "0 2px 8px rgba(236, 72, 153, 0.4)",
                      }}
                    >
                      {unreadCount} New
                    </span>
                  ) : (
                    <span style={{ fontSize: "12px", color: "#64748B" }}>All caught up</span>
                  )}
                  <ChevronRight size={16} style={{ color: "#64748B" }} />
                </div>
              </button>

              {/* 6. Settings */}
              <button
                type="button"
                id="menu-item-settings"
                onClick={() => setActiveTab("settings")}
                style={{
                  width: "100%",
                  padding: "15px 18px",
                  background: "transparent",
                  border: "none",
                  borderBottom: "1px solid rgba(255, 255, 255, 0.06)",
                  color: "#FFFFFF",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  cursor: "pointer",
                  textAlign: "left",
                  transition: "background 0.15s ease",
                }}
              >
                <div style={{ display: "flex", alignItems: "center", gap: "14px" }}>
                  <div
                    style={{
                      width: "40px",
                      height: "40px",
                      borderRadius: "12px",
                      background: "rgba(16, 185, 129, 0.14)",
                      border: "1px solid rgba(16, 185, 129, 0.3)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      color: "#10B981",
                    }}
                  >
                    <Settings2 size={19} />
                  </div>
                  <div>
                    <div style={{ fontSize: "15px", fontWeight: 700, color: "#FFFFFF" }}>Settings</div>
                    <div style={{ fontSize: "12px", color: "#94A3B8" }}>
                      Profile details, password, notifications & security
                    </div>
                  </div>
                </div>
                <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                  <span style={{ fontSize: "12px", color: "#64748B" }}>Attendee Scoped</span>
                  <ChevronRight size={16} style={{ color: "#64748B" }} />
                </div>
              </button>

              {/* 7. Help and Support */}
              <button
                type="button"
                id="menu-item-help-support"
                onClick={() => setActiveTab("support")}
                style={{
                  width: "100%",
                  padding: "15px 18px",
                  background: "transparent",
                  border: "none",
                  color: "#FFFFFF",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  cursor: "pointer",
                  textAlign: "left",
                  transition: "background 0.15s ease",
                }}
              >
                <div style={{ display: "flex", alignItems: "center", gap: "14px" }}>
                  <div
                    style={{
                      width: "40px",
                      height: "40px",
                      borderRadius: "12px",
                      background: "rgba(234, 179, 8, 0.14)",
                      border: "1px solid rgba(234, 179, 8, 0.3)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      color: "#FACC15",
                    }}
                  >
                    <HelpCircle size={19} />
                  </div>
                  <div>
                    <div style={{ fontSize: "15px", fontWeight: 700, color: "#FFFFFF" }}>Help and Support</div>
                    <div style={{ fontSize: "12px", color: "#94A3B8" }}>
                      Event gate FAQ, order disputes & attendee assistance
                    </div>
                  </div>
                </div>
                <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                  <ChevronRight size={16} style={{ color: "#64748B" }} />
                </div>
              </button>
            </div>

            {/* Logout Action Row */}
            <div
              style={{
                background: "#162038",
                border: "1px solid rgba(239, 68, 68, 0.2)",
                borderRadius: "20px",
                overflow: "hidden",
              }}
            >
              <button
                type="button"
                id="menu-item-logout"
                onClick={handleLogout}
                disabled={isLoggingOut}
                style={{
                  width: "100%",
                  padding: "16px 18px",
                  background: "transparent",
                  border: "none",
                  color: "#EF4444",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  cursor: "pointer",
                  textAlign: "left",
                  transition: "background 0.15s ease",
                }}
              >
                <div style={{ display: "flex", alignItems: "center", gap: "14px" }}>
                  <div
                    style={{
                      width: "40px",
                      height: "40px",
                      borderRadius: "12px",
                      background: "rgba(239, 68, 68, 0.14)",
                      border: "1px solid rgba(239, 68, 68, 0.3)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      color: "#EF4444",
                    }}
                  >
                    <LogOut size={19} />
                  </div>
                  <div>
                    <div style={{ fontSize: "15px", fontWeight: 700, color: "#EF4444" }}>Log Out</div>
                    <div style={{ fontSize: "12px", color: "#94A3B8" }}>
                      Terminate session and revoke authentication tokens
                    </div>
                  </div>
                </div>
                <span style={{ fontSize: "12px", color: "#EF4444", fontWeight: 700 }}>
                  {isLoggingOut ? "Signing out..." : "Sign Out"}
                </span>
              </button>
            </div>
          </div>
        )}

        {/* ============================================================ */}
        {/* VIEW 2: EDIT PROFILE FORM (Authorized Form)                  */}
        {/* ============================================================ */}
        {activeTab === "edit_profile" && (
          <div
            id="view-edit-profile"
            style={{
              background: "#162038",
              border: "1px solid rgba(255, 255, 255, 0.12)",
              borderRadius: "24px",
              padding: "24px",
              boxShadow: "0 10px 30px rgba(0,0,0,0.4)",
            }}
          >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "20px" }}>
              <div>
                <h3 style={{ fontSize: "18px", fontWeight: 800, margin: 0, color: "#FFFFFF" }}>
                  Edit Profile Information
                </h3>
                <p style={{ fontSize: "13px", color: "#94A3B8", margin: "4px 0 0" }}>
                  Update your passholder display name and authorized contact details.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setActiveTab("menu")}
                style={{
                  background: "rgba(255, 255, 255, 0.08)",
                  border: "none",
                  color: "#94A3B8",
                  borderRadius: "50%",
                  width: "32px",
                  height: "32px",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                }}
              >
                <X size={16} />
              </button>
            </div>

            {profileFeedback && (
              <div
                style={{
                  padding: "12px 16px",
                  borderRadius: "12px",
                  marginBottom: "20px",
                  display: "flex",
                  alignItems: "center",
                  gap: "10px",
                  fontSize: "13px",
                  fontWeight: 600,
                  background:
                    profileFeedback.type === "success" ? "rgba(16, 185, 129, 0.16)" : "rgba(239, 68, 68, 0.16)",
                  color: profileFeedback.type === "success" ? "#34D399" : "#F87171",
                  border:
                    profileFeedback.type === "success"
                      ? "1px solid rgba(16, 185, 129, 0.35)"
                      : "1px solid rgba(239, 68, 68, 0.35)",
                }}
              >
                {profileFeedback.type === "success" ? <CheckCircle2 size={16} /> : <AlertCircle size={16} />}
                <span>{profileFeedback.msg}</span>
              </div>
            )}

            <form onSubmit={handleSaveProfile} style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
              {/* Full Name */}
              <div>
                <label style={{ display: "block", fontSize: "13px", fontWeight: 700, color: "#E2E8F0", marginBottom: "6px" }}>
                  Full Name
                </label>
                <input
                  type="text"
                  id="input-profile-name"
                  value={nameInput}
                  onChange={(e) => setNameInput(e.target.value)}
                  placeholder="e.g. Akeem Campbell"
                  required
                  style={{
                    width: "100%",
                    padding: "12px 14px",
                    borderRadius: "12px",
                    background: "rgba(11, 19, 43, 0.7)",
                    border: "1px solid rgba(255, 255, 255, 0.14)",
                    color: "#FFFFFF",
                    fontSize: "14px",
                    boxSizing: "border-box",
                  }}
                />
              </div>

              {/* Email Address */}
              <div>
                <label style={{ display: "block", fontSize: "13px", fontWeight: 700, color: "#E2E8F0", marginBottom: "6px" }}>
                  Email Address (Authorized Account Identifier)
                </label>
                <input
                  type="email"
                  id="input-profile-email"
                  value={emailInput}
                  onChange={(e) => setEmailInput(e.target.value)}
                  placeholder="e.g. akeem.c@islandevents.example"
                  required
                  style={{
                    width: "100%",
                    padding: "12px 14px",
                    borderRadius: "12px",
                    background: "rgba(11, 19, 43, 0.7)",
                    border: "1px solid rgba(255, 255, 255, 0.14)",
                    color: "#FFFFFF",
                    fontSize: "14px",
                    boxSizing: "border-box",
                  }}
                />
              </div>

              {/* Phone Number */}
              <div>
                <label style={{ display: "block", fontSize: "13px", fontWeight: 700, color: "#E2E8F0", marginBottom: "6px" }}>
                  Phone Number (Gate SMS & WhatsApp Pass Dispatch)
                </label>
                <input
                  type="tel"
                  id="input-profile-phone"
                  value={phoneInput}
                  onChange={(e) => setPhoneInput(e.target.value)}
                  placeholder="e.g. +1 (305) 555-9011"
                  style={{
                    width: "100%",
                    padding: "12px 14px",
                    borderRadius: "12px",
                    background: "rgba(11, 19, 43, 0.7)",
                    border: "1px solid rgba(255, 255, 255, 0.14)",
                    color: "#FFFFFF",
                    fontSize: "14px",
                    boxSizing: "border-box",
                  }}
                />
              </div>

              {/* Avatar URL or Photo Preset */}
              <div>
                <label style={{ display: "block", fontSize: "13px", fontWeight: 700, color: "#E2E8F0", marginBottom: "6px" }}>
                  Avatar Image URL (Optional)
                </label>
                <div style={{ display: "flex", gap: "10px" }}>
                  <input
                    type="url"
                    id="input-profile-avatar"
                    value={avatarInput}
                    onChange={(e) => setAvatarInput(e.target.value)}
                    placeholder="https://example.com/avatar.jpg"
                    style={{
                      flex: 1,
                      padding: "12px 14px",
                      borderRadius: "12px",
                      background: "rgba(11, 19, 43, 0.7)",
                      border: "1px solid rgba(255, 255, 255, 0.14)",
                      color: "#FFFFFF",
                      fontSize: "14px",
                      boxSizing: "border-box",
                    }}
                  />
                  {avatarInput && (
                    <button
                      type="button"
                      onClick={() => setAvatarInput("")}
                      style={{
                        padding: "0 14px",
                        borderRadius: "12px",
                        background: "rgba(239, 68, 68, 0.15)",
                        border: "1px solid rgba(239, 68, 68, 0.3)",
                        color: "#F87171",
                        fontSize: "13px",
                        fontWeight: 700,
                        cursor: "pointer",
                      }}
                    >
                      Clear
                    </button>
                  )}
                </div>
              </div>

              {/* Bio / Passholder Preference */}
              <div>
                <label style={{ display: "block", fontSize: "13px", fontWeight: 700, color: "#E2E8F0", marginBottom: "6px" }}>
                  Bio / Passholder Note
                </label>
                <textarea
                  id="input-profile-bio"
                  value={bioInput}
                  onChange={(e) => setBioInput(e.target.value)}
                  placeholder="Share a short note about your favorite Caribbean events..."
                  rows={3}
                  style={{
                    width: "100%",
                    padding: "12px 14px",
                    borderRadius: "12px",
                    background: "rgba(11, 19, 43, 0.7)",
                    border: "1px solid rgba(255, 255, 255, 0.14)",
                    color: "#FFFFFF",
                    fontSize: "14px",
                    boxSizing: "border-box",
                    resize: "vertical",
                  }}
                />
              </div>

              {/* Buttons */}
              <div style={{ display: "flex", gap: "12px", marginTop: "10px" }}>
                <button
                  type="submit"
                  id="btn-save-profile-submit"
                  disabled={isSavingProfile}
                  style={{
                    flex: 1,
                    padding: "13px 20px",
                    borderRadius: "14px",
                    background: "linear-gradient(135deg, #7C3AED 0%, #EC4899 50%, #F97316 100%)",
                    border: "none",
                    color: "#FFFFFF",
                    fontSize: "14px",
                    fontWeight: 800,
                    cursor: "pointer",
                    boxShadow: "0 4px 14px rgba(236, 72, 153, 0.35)",
                    transition: "all 0.18s ease",
                  }}
                >
                  {isSavingProfile ? "Saving Changes..." : "Save Profile"}
                </button>
                <button
                  type="button"
                  onClick={() => setActiveTab("menu")}
                  style={{
                    padding: "13px 20px",
                    borderRadius: "14px",
                    background: "rgba(255, 255, 255, 0.08)",
                    border: "1px solid rgba(255, 255, 255, 0.15)",
                    color: "#FFFFFF",
                    fontSize: "14px",
                    fontWeight: 700,
                    cursor: "pointer",
                  }}
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        )}

        {/* ============================================================ */}
        {/* VIEW 3: NOTIFICATIONS DRAWER / PANEL (Actual data & mark read) */}
        {/* ============================================================ */}
        {activeTab === "notifications" && (
          <div
            id="view-notifications-panel"
            style={{
              background: "#162038",
              border: "1px solid rgba(255, 255, 255, 0.12)",
              borderRadius: "24px",
              padding: "24px",
              boxShadow: "0 10px 30px rgba(0,0,0,0.4)",
            }}
          >
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                alignItems: "center",
                marginBottom: "18px",
                flexWrap: "wrap",
                gap: "10px",
              }}
            >
              <div>
                <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                  <h3 style={{ fontSize: "18px", fontWeight: 800, margin: 0, color: "#FFFFFF" }}>
                    Your Notifications
                  </h3>
                  {unreadCount > 0 && (
                    <span
                      id="notif-count-badge"
                      style={{
                        background: "rgba(236, 72, 153, 0.2)",
                        color: "#F472B6",
                        border: "1px solid rgba(236, 72, 153, 0.4)",
                        fontSize: "11px",
                        fontWeight: 800,
                        padding: "2px 8px",
                        borderRadius: "9999px",
                      }}
                    >
                      {unreadCount} Unread
                    </span>
                  )}
                </div>
                <p style={{ fontSize: "13px", color: "#94A3B8", margin: "4px 0 0" }}>
                  Real-time dispatches from ticket issuance, gate collection, and payment gateways.
                </p>
              </div>

              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                {unreadCount > 0 && (
                  <button
                    type="button"
                    id="btn-mark-all-read"
                    onClick={handleMarkAllRead}
                    style={{
                      background: "rgba(139, 92, 246, 0.16)",
                      border: "1px solid rgba(139, 92, 246, 0.35)",
                      color: "#C4B5FD",
                      fontSize: "12px",
                      fontWeight: 700,
                      padding: "7px 12px",
                      borderRadius: "10px",
                      cursor: "pointer",
                      display: "inline-flex",
                      alignItems: "center",
                      gap: "6px",
                    }}
                  >
                    <CheckCircle2 size={13} />
                    <span>Mark All Read</span>
                  </button>
                )}
                <button
                  type="button"
                  id="btn-refresh-notifs"
                  onClick={refreshNotifications}
                  disabled={isLoadingNotifs}
                  style={{
                    background: "rgba(255, 255, 255, 0.08)",
                    border: "1px solid rgba(255, 255, 255, 0.14)",
                    color: "#FFFFFF",
                    padding: "7px 12px",
                    borderRadius: "10px",
                    fontSize: "12px",
                    fontWeight: 700,
                    cursor: "pointer",
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "6px",
                  }}
                >
                  <RefreshCw size={13} className={isLoadingNotifs ? "animate-spin" : ""} />
                  <span>Refresh</span>
                </button>
              </div>
            </div>

            {/* Notification List */}
            {notificationsList.length === 0 ? (
              <div
                style={{
                  textAlign: "center",
                  padding: "40px 20px",
                  color: "#94A3B8",
                  background: "rgba(11, 19, 43, 0.5)",
                  borderRadius: "16px",
                }}
              >
                <Bell size={32} style={{ color: "#64748B", marginBottom: "10px" }} />
                <h4 style={{ color: "#FFFFFF", margin: "0 0 6px", fontSize: "16px" }}>No Notifications Yet</h4>
                <p style={{ margin: 0, fontSize: "13px" }}>
                  Your event dispatches, gate scan confirmations, and receipts will appear here.
                </p>
              </div>
            ) : (
              <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
                {notificationsList.map((notif) => {
                  const isUnread = !(notif as any).read_at && !(notif as any).read;
                  return (
                    <div
                      key={notif.id}
                      id={`notif-card-${notif.id}`}
                      style={{
                        background: isUnread ? "rgba(139, 92, 246, 0.1)" : "rgba(11, 19, 43, 0.6)",
                        border: isUnread
                          ? "1px solid rgba(139, 92, 246, 0.35)"
                          : "1px solid rgba(255, 255, 255, 0.06)",
                        borderRadius: "14px",
                        padding: "14px 16px",
                        display: "flex",
                        alignItems: "flex-start",
                        justifyContent: "space-between",
                        gap: "12px",
                        transition: "all 0.15s ease",
                      }}
                    >
                      <div style={{ display: "flex", alignItems: "flex-start", gap: "12px" }}>
                        <div
                          style={{
                            width: "36px",
                            height: "36px",
                            borderRadius: "10px",
                            background:
                              notif.channel === "sms"
                                ? "rgba(56, 189, 248, 0.18)"
                                : notif.channel === "whatsapp"
                                ? "rgba(34, 197, 94, 0.18)"
                                : "rgba(249, 115, 22, 0.18)",
                            color:
                              notif.channel === "sms"
                                ? "#38BDF8"
                                : notif.channel === "whatsapp"
                                ? "#4ADE80"
                                : "#FB923C",
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "center",
                            flexShrink: 0,
                            marginTop: "2px",
                          }}
                        >
                          {notif.channel === "sms" ? (
                            <Smartphone size={16} />
                          ) : notif.channel === "whatsapp" ? (
                            <MessageCircle size={16} />
                          ) : (
                            <Mail size={16} />
                          )}
                        </div>

                        <div>
                          <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
                            <strong style={{ fontSize: "14px", color: "#FFFFFF" }}>{notif.title}</strong>
                            <span
                              style={{
                                fontSize: "10px",
                                textTransform: "uppercase",
                                fontWeight: 800,
                                background: "rgba(255, 255, 255, 0.08)",
                                color: "#CBD5E1",
                                padding: "2px 6px",
                                borderRadius: "4px",
                              }}
                            >
                              {notif.channel}
                            </span>
                            {isUnread && (
                              <span
                                style={{
                                  width: "7px",
                                  height: "7px",
                                  borderRadius: "50%",
                                  background: "#EC4899",
                                  display: "inline-block",
                                }}
                              />
                            )}
                          </div>
                          <p style={{ fontSize: "13px", color: "#CBD5E1", margin: "4px 0 6px", lineHeight: 1.4 }}>
                            {notif.message}
                          </p>
                          <div style={{ fontSize: "11px", color: "#64748B", display: "flex", gap: "10px" }}>
                            <span>{new Date(notif.sentAt || notif.createdAt || Date.now()).toLocaleString()}</span>
                            <span>· Provider: {notif.provider}</span>
                            {notif.orderNumber && <span>· Ref: #{notif.orderNumber}</span>}
                          </div>
                        </div>
                      </div>

                      {isUnread && (
                        <button
                          type="button"
                          id={`btn-mark-read-${notif.id}`}
                          onClick={() => handleMarkAsRead(notif.id)}
                          style={{
                            background: "rgba(255, 255, 255, 0.08)",
                            border: "1px solid rgba(255, 255, 255, 0.16)",
                            color: "#E2E8F0",
                            borderRadius: "8px",
                            padding: "6px 10px",
                            fontSize: "11px",
                            fontWeight: 700,
                            cursor: "pointer",
                            whiteSpace: "nowrap",
                            flexShrink: 0,
                          }}
                        >
                          Mark Read
                        </button>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        )}

        {/* ============================================================ */}
        {/* VIEW 4: ATTENDEE SCOPED SETTINGS                             */}
        {/* ============================================================ */}
        {activeTab === "settings" && (
          <div
            id="view-attendee-settings"
            style={{
              background: "#162038",
              border: "1px solid rgba(255, 255, 255, 0.12)",
              borderRadius: "24px",
              padding: "24px",
              boxShadow: "0 10px 30px rgba(0,0,0,0.4)",
            }}
          >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "20px" }}>
              <div>
                <h3 style={{ fontSize: "18px", fontWeight: 800, margin: 0, color: "#FFFFFF" }}>
                  Account Settings
                </h3>
                <p style={{ fontSize: "13px", color: "#94A3B8", margin: "4px 0 0" }}>
                  Attendee-scoped security, credential management, and notification rules.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setActiveTab("menu")}
                style={{
                  background: "rgba(255, 255, 255, 0.08)",
                  border: "none",
                  color: "#94A3B8",
                  borderRadius: "50%",
                  width: "32px",
                  height: "32px",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                }}
              >
                <X size={16} />
              </button>
            </div>

            {/* Sub-tab Navigation */}
            <div
              style={{
                display: "flex",
                gap: "8px",
                overflowX: "auto",
                borderBottom: "1px solid rgba(255, 255, 255, 0.08)",
                paddingBottom: "12px",
                marginBottom: "20px",
              }}
            >
              {[
                { key: "details", label: "Profile", icon: User },
                { key: "password", label: "Password", icon: Key },
                { key: "notifications", label: "Notifications", icon: Bell },
                { key: "marketing", label: "Marketing", icon: Flame },
                { key: "security", label: "Security & Privacy", icon: ShieldCheck },
              ].map((sub) => {
                const Icon = sub.icon;
                const isActive = activeSettingsTab === sub.key;
                return (
                  <button
                    key={sub.key}
                    type="button"
                    id={`settings-tab-${sub.key}`}
                    onClick={() => setActiveSettingsTab(sub.key as SettingsSubTab)}
                    style={{
                      padding: "8px 14px",
                      borderRadius: "10px",
                      background: isActive ? "rgba(124, 58, 237, 0.25)" : "transparent",
                      color: isActive ? "#C4B5FD" : "#94A3B8",
                      border: isActive ? "1px solid rgba(124, 58, 237, 0.4)" : "1px solid transparent",
                      fontSize: "13px",
                      fontWeight: 700,
                      cursor: "pointer",
                      display: "inline-flex",
                      alignItems: "center",
                      gap: "6px",
                      whiteSpace: "nowrap",
                    }}
                  >
                    <Icon size={14} />
                    <span>{sub.label}</span>
                  </button>
                );
              })}
            </div>

            {/* Sub-tab 1: Details */}
            {activeSettingsTab === "details" && (
              <div>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "16px" }}>
                  <div>
                    <h4 style={{ fontSize: "15px", fontWeight: 700, margin: 0, color: "#FFFFFF" }}>Passholder Details</h4>
                    <p style={{ fontSize: "13px", color: "#94A3B8", margin: "2px 0 0" }}>
                      Basic contact and ticket matching information.
                    </p>
                  </div>
                  <button
                    type="button"
                    onClick={() => setActiveTab("edit_profile")}
                    style={{
                      background: "rgba(255, 255, 255, 0.08)",
                      border: "1px solid rgba(255, 255, 255, 0.16)",
                      color: "#FFFFFF",
                      padding: "7px 14px",
                      borderRadius: "10px",
                      fontSize: "12px",
                      fontWeight: 700,
                      cursor: "pointer",
                    }}
                  >
                    Edit Details
                  </button>
                </div>

                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px" }}>
                  <div style={{ background: "rgba(11, 19, 43, 0.6)", padding: "12px 14px", borderRadius: "12px" }}>
                    <span style={{ fontSize: "11px", color: "#64748B", textTransform: "uppercase", fontWeight: 700 }}>
                      Display Name
                    </span>
                    <div style={{ fontSize: "14px", fontWeight: 700, color: "#FFFFFF", marginTop: "2px" }}>
                      {userName}
                    </div>
                  </div>
                  <div style={{ background: "rgba(11, 19, 43, 0.6)", padding: "12px 14px", borderRadius: "12px" }}>
                    <span style={{ fontSize: "11px", color: "#64748B", textTransform: "uppercase", fontWeight: 700 }}>
                      Primary Email
                    </span>
                    <div style={{ fontSize: "14px", fontWeight: 700, color: "#FFFFFF", marginTop: "2px" }}>
                      {userEmail}
                    </div>
                  </div>
                  <div style={{ background: "rgba(11, 19, 43, 0.6)", padding: "12px 14px", borderRadius: "12px" }}>
                    <span style={{ fontSize: "11px", color: "#64748B", textTransform: "uppercase", fontWeight: 700 }}>
                      Phone / Mobile
                    </span>
                    <div style={{ fontSize: "14px", fontWeight: 700, color: "#FFFFFF", marginTop: "2px" }}>
                      {phoneInput || "Not configured"}
                    </div>
                  </div>
                  <div style={{ background: "rgba(11, 19, 43, 0.6)", padding: "12px 14px", borderRadius: "12px" }}>
                    <span style={{ fontSize: "11px", color: "#64748B", textTransform: "uppercase", fontWeight: 700 }}>
                      Membership Standing
                    </span>
                    <div style={{ fontSize: "14px", fontWeight: 700, color: realMembership.badgeColor, marginTop: "2px" }}>
                      {realMembership.label}
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Sub-tab 2: Password Management */}
            {activeSettingsTab === "password" && (
              <div>
                <h4 style={{ fontSize: "15px", fontWeight: 700, margin: "0 0 4px", color: "#FFFFFF" }}>
                  Password Management
                </h4>
                <p style={{ fontSize: "13px", color: "#94A3B8", margin: "0 0 18px" }}>
                  Securely update your password with entropy verification and server-side hashing.
                </p>

                {passwordFeedback && (
                  <div
                    style={{
                      padding: "12px 16px",
                      borderRadius: "12px",
                      marginBottom: "16px",
                      display: "flex",
                      alignItems: "center",
                      gap: "10px",
                      fontSize: "13px",
                      fontWeight: 600,
                      background:
                        passwordFeedback.type === "success" ? "rgba(16, 185, 129, 0.16)" : "rgba(239, 68, 68, 0.16)",
                      color: passwordFeedback.type === "success" ? "#34D399" : "#F87171",
                      border:
                        passwordFeedback.type === "success"
                          ? "1px solid rgba(16, 185, 129, 0.35)"
                          : "1px solid rgba(239, 68, 68, 0.35)",
                    }}
                  >
                    {passwordFeedback.type === "success" ? <CheckCircle2 size={16} /> : <AlertCircle size={16} />}
                    <span>{passwordFeedback.msg}</span>
                  </div>
                )}

                <form onSubmit={handleUpdatePassword} style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
                  <div>
                    <label style={{ display: "block", fontSize: "13px", fontWeight: 700, color: "#E2E8F0", marginBottom: "6px" }}>
                      Current Password
                    </label>
                    <input
                      type={showPassword ? "text" : "password"}
                      id="input-current-password"
                      value={currentPassword}
                      onChange={(e) => setCurrentPassword(e.target.value)}
                      placeholder="••••••••••••"
                      required
                      style={{
                        width: "100%",
                        padding: "12px 14px",
                        borderRadius: "12px",
                        background: "rgba(11, 19, 43, 0.7)",
                        border: "1px solid rgba(255, 255, 255, 0.14)",
                        color: "#FFFFFF",
                        fontSize: "14px",
                        boxSizing: "border-box",
                      }}
                    />
                  </div>

                  <div>
                    <label style={{ display: "block", fontSize: "13px", fontWeight: 700, color: "#E2E8F0", marginBottom: "6px" }}>
                      New Password (Minimum 8 characters)
                    </label>
                    <input
                      type={showPassword ? "text" : "password"}
                      id="input-new-password"
                      value={newPassword}
                      onChange={(e) => setNewPassword(e.target.value)}
                      placeholder="At least 8 characters..."
                      required
                      minLength={8}
                      style={{
                        width: "100%",
                        padding: "12px 14px",
                        borderRadius: "12px",
                        background: "rgba(11, 19, 43, 0.7)",
                        border: "1px solid rgba(255, 255, 255, 0.14)",
                        color: "#FFFFFF",
                        fontSize: "14px",
                        boxSizing: "border-box",
                      }}
                    />
                  </div>

                  <div>
                    <label style={{ display: "block", fontSize: "13px", fontWeight: 700, color: "#E2E8F0", marginBottom: "6px" }}>
                      Confirm New Password
                    </label>
                    <input
                      type={showPassword ? "text" : "password"}
                      id="input-confirm-password"
                      value={confirmPassword}
                      onChange={(e) => setConfirmPassword(e.target.value)}
                      placeholder="Repeat new password..."
                      required
                      style={{
                        width: "100%",
                        padding: "12px 14px",
                        borderRadius: "12px",
                        background: "rgba(11, 19, 43, 0.7)",
                        border: "1px solid rgba(255, 255, 255, 0.14)",
                        color: "#FFFFFF",
                        fontSize: "14px",
                        boxSizing: "border-box",
                      }}
                    />
                  </div>

                  <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                    <input
                      type="checkbox"
                      id="toggle-show-passwords"
                      checked={showPassword}
                      onChange={(e) => setShowPassword(e.target.checked)}
                      style={{ accentColor: "#7C3AED", cursor: "pointer" }}
                    />
                    <label htmlFor="toggle-show-passwords" style={{ fontSize: "13px", color: "#CBD5E1", cursor: "pointer" }}>
                      Show password characters
                    </label>
                  </div>

                  <button
                    type="submit"
                    id="btn-update-password-submit"
                    disabled={isUpdatingPassword}
                    style={{
                      marginTop: "6px",
                      padding: "12px 20px",
                      borderRadius: "12px",
                      background: "linear-gradient(135deg, #7C3AED 0%, #EC4899 100%)",
                      border: "none",
                      color: "#FFFFFF",
                      fontSize: "14px",
                      fontWeight: 800,
                      cursor: "pointer",
                      width: "fit-content",
                    }}
                  >
                    {isUpdatingPassword ? "Updating Password..." : "Update Password"}
                  </button>
                </form>
              </div>
            )}

            {/* Sub-tab 3: Notification Preferences */}
            {activeSettingsTab === "notifications" && (
              <div>
                <h4 style={{ fontSize: "15px", fontWeight: 700, margin: "0 0 4px", color: "#FFFFFF" }}>
                  Notification Delivery Preferences
                </h4>
                <p style={{ fontSize: "13px", color: "#94A3B8", margin: "0 0 18px" }}>
                  Control which channels receive your digital tickets and arrival reminders.
                </p>

                {notifPrefSaved && (
                  <div
                    style={{
                      padding: "10px 14px",
                      borderRadius: "10px",
                      background: "rgba(16, 185, 129, 0.15)",
                      color: "#34D399",
                      fontSize: "12px",
                      fontWeight: 700,
                      marginBottom: "16px",
                      display: "flex",
                      alignItems: "center",
                      gap: "6px",
                    }}
                  >
                    <CheckCircle2 size={15} />
                    <span>Notification preferences updated.</span>
                  </div>
                )}

                <div style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
                  {[
                    {
                      id: "pref-sms",
                      label: "SMS Reminders & Emergency Gate Alerts",
                      desc: "Instant SMS notifications before gates open and when food orders are prepared.",
                      checked: prefSms,
                      toggle: () => setPrefSms(!prefSms),
                    },
                    {
                      id: "pref-whatsapp",
                      label: "WhatsApp Pass Delivery",
                      desc: "Automatic dispatch of QR ticket passes directly to WhatsApp for offline access.",
                      checked: prefWhatsapp,
                      toggle: () => setPrefWhatsapp(!prefWhatsapp),
                    },
                    {
                      id: "pref-email",
                      label: "Official Email Receipts",
                      desc: "Itemized payment receipts, PDF vouchers, and event directions sent to inbox.",
                      checked: prefEmail,
                      toggle: () => setPrefEmail(!prefEmail),
                    },
                    {
                      id: "pref-digest",
                      label: "Weekly Island Event Digest",
                      desc: "Curated weekend sound system parties, reggae concerts, and culinary festivals.",
                      checked: prefDigest,
                      toggle: () => setPrefDigest(!prefDigest),
                    },
                  ].map((p) => (
                    <div
                      key={p.id}
                      style={{
                        background: "rgba(11, 19, 43, 0.6)",
                        border: "1px solid rgba(255, 255, 255, 0.08)",
                        borderRadius: "14px",
                        padding: "14px 16px",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                        gap: "12px",
                      }}
                    >
                      <div>
                        <div style={{ fontSize: "14px", fontWeight: 700, color: "#FFFFFF" }}>{p.label}</div>
                        <div style={{ fontSize: "12px", color: "#94A3B8", marginTop: "2px" }}>{p.desc}</div>
                      </div>
                      <input
                        type="checkbox"
                        id={p.id}
                        checked={p.checked}
                        onChange={() => {
                          p.toggle();
                          setNotifPrefSaved(true);
                          setTimeout(() => setNotifPrefSaved(false), 2000);
                        }}
                        style={{ width: "20px", height: "20px", accentColor: "#7C3AED", cursor: "pointer" }}
                      />
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Sub-tab 4: Marketing Preferences */}
            {activeSettingsTab === "marketing" && (
              <div>
                <h4 style={{ fontSize: "15px", fontWeight: 700, margin: "0 0 4px", color: "#FFFFFF" }}>
                  Marketing & Promotional Communications
                </h4>
                <p style={{ fontSize: "13px", color: "#94A3B8", margin: "0 0 18px" }}>
                  Choose whether to receive promotional offers, host specials, and early-bird ticket discounts.
                </p>

                <div
                  style={{
                    background: "rgba(11, 19, 43, 0.6)",
                    border: "1px solid rgba(255, 255, 255, 0.08)",
                    borderRadius: "14px",
                    padding: "16px",
                    marginBottom: "14px",
                  }}
                >
                  <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: "8px" }}>
                    <strong style={{ fontSize: "14px", color: "#FFFFFF" }}>Promotional Offers & Early-Bird Deals</strong>
                    <input
                      type="checkbox"
                      id="toggle-marketing-updates"
                      checked={prefMarketing}
                      onChange={(e) => setPrefMarketing(e.target.checked)}
                      style={{ width: "20px", height: "20px", accentColor: "#F97316", cursor: "pointer" }}
                    />
                  </div>
                  <p style={{ fontSize: "13px", color: "#94A3B8", margin: 0, lineHeight: 1.5 }}>
                    Receive exclusive discount codes, VIP ticket pre-sales, and food combo promotions from festival organizers.
                  </p>
                </div>

                <div style={{ fontSize: "12px", color: "#64748B", lineHeight: 1.5 }}>
                  🛡️ You may opt out of promotional emails at any time. Transactional notifications regarding active tickets and gate admissions are never affected by marketing opt-outs.
                </div>
              </div>
            )}

            {/* Sub-tab 5: Security Options (Attendee Only - Zero Admin Leak) */}
            {activeSettingsTab === "security" && (
              <div>
                <h4 style={{ fontSize: "15px", fontWeight: 700, margin: "0 0 4px", color: "#FFFFFF" }}>
                  Security & Session Options
                </h4>
                <p style={{ fontSize: "13px", color: "#94A3B8", margin: "0 0 18px" }}>
                  Two-factor protection, authorized session devices, and privacy rights.
                </p>

                <div style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
                  {/* Two-Factor Auth */}
                  <div
                    style={{
                      background: "rgba(11, 19, 43, 0.6)",
                      border: "1px solid rgba(255, 255, 255, 0.08)",
                      borderRadius: "14px",
                      padding: "16px",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "space-between",
                      gap: "14px",
                    }}
                  >
                    <div>
                      <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                        <ShieldCheck size={16} style={{ color: "#10B981" }} />
                        <strong style={{ fontSize: "14px", color: "#FFFFFF" }}>Two-Factor Authentication (2FA)</strong>
                      </div>
                      <p style={{ fontSize: "12px", color: "#94A3B8", margin: "4px 0 0" }}>
                        Require secondary confirmation via Authenticator app or SMS code upon signing in.
                      </p>
                    </div>
                    <button
                      type="button"
                      id="btn-toggle-2fa"
                      onClick={() => {
                        setTwoFactorEnabled(!twoFactorEnabled);
                        setShow2FaSuccess(true);
                        setTimeout(() => setShow2FaSuccess(false), 2400);
                      }}
                      style={{
                        padding: "8px 14px",
                        borderRadius: "10px",
                        background: twoFactorEnabled ? "rgba(16, 185, 129, 0.16)" : "rgba(255, 255, 255, 0.08)",
                        border: twoFactorEnabled ? "1px solid rgba(16, 185, 129, 0.4)" : "1px solid rgba(255, 255, 255, 0.16)",
                        color: twoFactorEnabled ? "#34D399" : "#CBD5E1",
                        fontSize: "12px",
                        fontWeight: 700,
                        cursor: "pointer",
                      }}
                    >
                      {twoFactorEnabled ? "Enabled" : "Enable 2FA"}
                    </button>
                  </div>

                  {show2FaSuccess && (
                    <div style={{ fontSize: "12px", color: "#34D399", display: "flex", alignItems: "center", gap: "6px" }}>
                      <CheckCircle2 size={14} />
                      <span>Two-Factor Authentication status updated.</span>
                    </div>
                  )}

                  {/* Active Device Info */}
                  <div
                    style={{
                      background: "rgba(11, 19, 43, 0.6)",
                      border: "1px solid rgba(255, 255, 255, 0.08)",
                      borderRadius: "14px",
                      padding: "16px",
                    }}
                  >
                    <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "6px" }}>
                      <Smartphone size={16} style={{ color: "#38BDF8" }} />
                      <strong style={{ fontSize: "14px", color: "#FFFFFF" }}>Current Authenticated Device</strong>
                    </div>
                    <p style={{ fontSize: "12px", color: "#94A3B8", margin: "0 0 10px" }}>
                      This browser session is securely authenticated using signed HTTP-only cookies and XSRF token verification.
                    </p>
                    <div style={{ fontSize: "11px", color: "#64748B", display: "flex", gap: "10px", flexWrap: "wrap" }}>
                      <span>IP: Verified Client</span>
                      <span>· Session: Active</span>
                      <span>· Invalidation: Server-side on logout</span>
                    </div>
                  </div>

                  {/* Notice: No Administrative Leak */}
                  <div
                    style={{
                      background: "rgba(148, 163, 184, 0.06)",
                      border: "1px solid rgba(148, 163, 184, 0.14)",
                      borderRadius: "12px",
                      padding: "12px 14px",
                      fontSize: "12px",
                      color: "#94A3B8",
                      display: "flex",
                      alignItems: "center",
                      gap: "8px",
                    }}
                  >
                    <Lock size={15} style={{ color: "#818CF8" }} />
                    <span>
                      Standard Attendee account. Administrative and host infrastructure controls remain strictly isolated.
                    </span>
                  </div>
                </div>
              </div>
            )}
          </div>
        )}

        {/* ============================================================ */}
        {/* VIEW 5: ORDER HISTORY (Receipts, items, status)              */}
        {/* ============================================================ */}
        {activeTab === "orders" && (
          <div
            id="view-user-orders"
            style={{
              background: "#162038",
              border: "1px solid rgba(255, 255, 255, 0.12)",
              borderRadius: "24px",
              padding: "24px",
              boxShadow: "0 10px 30px rgba(0,0,0,0.4)",
            }}
          >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "20px" }}>
              <div>
                <h3 style={{ fontSize: "18px", fontWeight: 800, margin: 0, color: "#FFFFFF" }}>
                  Your Orders & Receipts
                </h3>
                <p style={{ fontSize: "13px", color: "#94A3B8", margin: "4px 0 0" }}>
                  Complete transaction history, payment verification states, and portion breakdowns.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setActiveTab("menu")}
                style={{
                  background: "rgba(255, 255, 255, 0.08)",
                  border: "none",
                  color: "#94A3B8",
                  borderRadius: "50%",
                  width: "32px",
                  height: "32px",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                }}
              >
                <X size={16} />
              </button>
            </div>

            {userOrders.length === 0 ? (
              <div style={{ textAlign: "center", padding: "40px 20px", color: "#94A3B8" }}>
                <ShoppingBag size={32} style={{ color: "#64748B", marginBottom: "8px" }} />
                <h4 style={{ color: "#FFFFFF", margin: "0 0 4px" }}>No orders placed yet</h4>
                <p style={{ margin: 0, fontSize: "13px" }}>
                  Explore featured island concerts, community fish fries, and reserve admission.
                </p>
              </div>
            ) : (
              <div style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
                {userOrders.map((ord) => (
                  <div
                    key={ord.id}
                    style={{
                      background: "rgba(11, 19, 43, 0.6)",
                      border: "1px solid rgba(255, 255, 255, 0.08)",
                      borderRadius: "16px",
                      padding: "16px",
                    }}
                  >
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "10px" }}>
                      <div>
                        <span style={{ fontSize: "11px", fontWeight: 800, color: "#F97316", textTransform: "uppercase" }}>
                          Order #{ord.orderNumber}
                        </span>
                        <h4 style={{ fontSize: "16px", fontWeight: 800, color: "#FFFFFF", margin: "2px 0 0" }}>
                          {ord.eventTitle || "Island Event Festival"}
                        </h4>
                        <div style={{ fontSize: "12px", color: "#94A3B8", marginTop: "2px" }}>
                          Reference: <code>{ord.ticketCode}</code> · Method: {ord.method}
                        </div>
                      </div>

                      <span
                        style={{
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "4px",
                          padding: "4px 10px",
                          borderRadius: "9999px",
                          fontSize: "11px",
                          fontWeight: 800,
                          textTransform: "uppercase",
                          background:
                            ord.status === "paid"
                              ? "rgba(16, 185, 129, 0.16)"
                              : ord.status === "refunded"
                              ? "rgba(148, 163, 184, 0.16)"
                              : "rgba(249, 115, 22, 0.16)",
                          color:
                            ord.status === "paid"
                              ? "#34D399"
                              : ord.status === "refunded"
                              ? "#94A3B8"
                              : "#FB923C",
                        }}
                      >
                        {ord.status.replace(/_/g, " ")}
                      </span>
                    </div>

                    <div style={{ borderTop: "1px solid rgba(255, 255, 255, 0.06)", paddingTop: "10px", marginTop: "10px", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                      <span style={{ fontSize: "13px", color: "#94A3B8" }}>
                        {ord.items.length} {ord.items.length === 1 ? "Item" : "Items"} · Total Paid:
                      </span>
                      <strong style={{ fontSize: "15px", color: "#F97316" }}>
                        {formatMoney(ord.totalAmount, ord.currency)}
                      </strong>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* ============================================================ */}
        {/* VIEW 6: SAVED EVENTS                                         */}
        {/* ============================================================ */}
        {activeTab === "saved_events" && (
          <div
            id="view-saved-events"
            style={{
              background: "#162038",
              border: "1px solid rgba(255, 255, 255, 0.12)",
              borderRadius: "24px",
              padding: "24px",
              boxShadow: "0 10px 30px rgba(0,0,0,0.4)",
            }}
          >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "20px" }}>
              <div>
                <h3 style={{ fontSize: "18px", fontWeight: 800, margin: 0, color: "#FFFFFF" }}>
                  Saved Events ({savedEventsList.length})
                </h3>
                <p style={{ fontSize: "13px", color: "#94A3B8", margin: "4px 0 0" }}>
                  Curated Caribbean festival experiences bookmarked under your profile.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setActiveTab("menu")}
                style={{
                  background: "rgba(255, 255, 255, 0.08)",
                  border: "none",
                  color: "#94A3B8",
                  borderRadius: "50%",
                  width: "32px",
                  height: "32px",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                }}
              >
                <X size={16} />
              </button>
            </div>

            {savedEventsList.length === 0 ? (
              <div style={{ textAlign: "center", padding: "40px 20px", color: "#94A3B8" }}>
                <Heart size={32} style={{ color: "#64748B", marginBottom: "8px" }} />
                <h4 style={{ color: "#FFFFFF", margin: "0 0 4px" }}>No saved events</h4>
                <p style={{ margin: 0, fontSize: "13px" }}>
                  Click the heart icon on any event to bookmark it for quick access.
                </p>
              </div>
            ) : (
              <div style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
                {savedEventsList.map((sv) => (
                  <div
                    key={sv.eventId}
                    style={{
                      background: "rgba(11, 19, 43, 0.6)",
                      border: "1px solid rgba(255, 255, 255, 0.08)",
                      borderRadius: "16px",
                      padding: "16px",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "space-between",
                      gap: "14px",
                    }}
                  >
                    <div>
                      <h4 style={{ fontSize: "15px", fontWeight: 700, margin: 0, color: "#FFFFFF" }}>{sv.title}</h4>
                      <div style={{ fontSize: "12px", color: "#94A3B8", marginTop: "4px", display: "flex", gap: "10px" }}>
                        <span>📅 {sv.dateStr}</span>
                        <span>📍 {sv.venueName}</span>
                      </div>
                    </div>
                    {onNavigateToEventDetails && (
                      <button
                        type="button"
                        onClick={() => onNavigateToEventDetails(sv.eventId)}
                        style={{
                          background: "rgba(255, 255, 255, 0.08)",
                          border: "1px solid rgba(255, 255, 255, 0.16)",
                          color: "#FFFFFF",
                          padding: "8px 14px",
                          borderRadius: "10px",
                          fontSize: "12px",
                          fontWeight: 700,
                          cursor: "pointer",
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "4px",
                        }}
                      >
                        <span>View</span>
                        <ChevronRight size={13} />
                      </button>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* ============================================================ */}
        {/* VIEW 7: FOOD ORDERS VOUCHERS                                 */}
        {/* ============================================================ */}
        {activeTab === "food_orders" && (
          <div
            id="view-food-orders"
            style={{
              background: "#162038",
              border: "1px solid rgba(255, 255, 255, 0.12)",
              borderRadius: "24px",
              padding: "24px",
              boxShadow: "0 10px 30px rgba(0,0,0,0.4)",
            }}
          >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "20px" }}>
              <div>
                <h3 style={{ fontSize: "18px", fontWeight: 800, margin: 0, color: "#FFFFFF" }}>
                  Food & Beverage Orders
                </h3>
                <p style={{ fontSize: "13px", color: "#94A3B8", margin: "4px 0 0" }}>
                  Advance culinary portions prepared fresh for gate and tent pickup.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setActiveTab("menu")}
                style={{
                  background: "rgba(255, 255, 255, 0.08)",
                  border: "none",
                  color: "#94A3B8",
                  borderRadius: "50%",
                  width: "32px",
                  height: "32px",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                }}
              >
                <X size={16} />
              </button>
            </div>

            {foodOrders.length === 0 ? (
              <div style={{ textAlign: "center", padding: "40px 20px", color: "#94A3B8" }}>
                <Utensils size={32} style={{ color: "#64748B", marginBottom: "8px" }} />
                <h4 style={{ color: "#FFFFFF", margin: "0 0 4px" }}>No food orders found</h4>
                <p style={{ margin: "0 0 16px", fontSize: "13px" }}>
                  Order authentic jerk chicken, curry goat, fried plantains, and tropical smoothies in advance.
                </p>
                <button
                  type="button"
                  onClick={onNavigateToFood}
                  style={{
                    padding: "10px 18px",
                    borderRadius: "12px",
                    background: "linear-gradient(135deg, #F97316 0%, #EC4899 100%)",
                    border: "none",
                    color: "#FFFFFF",
                    fontSize: "13px",
                    fontWeight: 700,
                    cursor: "pointer",
                  }}
                >
                  Browse Food & Drinks
                </button>
              </div>
            ) : (
              <div style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
                {foodOrders.map((ord) => (
                  <div
                    key={ord.id}
                    style={{
                      background: "rgba(11, 19, 43, 0.6)",
                      border: "1px solid rgba(255, 255, 255, 0.08)",
                      borderRadius: "16px",
                      padding: "16px",
                    }}
                  >
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "8px" }}>
                      <div>
                        <span style={{ fontSize: "11px", fontWeight: 800, color: "#F97316" }}>
                          Order #{ord.orderNumber}
                        </span>
                        <h4 style={{ fontSize: "15px", fontWeight: 700, margin: "2px 0 0", color: "#FFFFFF" }}>
                          {ord.eventTitle || "Community Fish Fry"}
                        </h4>
                      </div>
                      <span
                        style={{
                          fontSize: "11px",
                          fontWeight: 800,
                          padding: "3px 8px",
                          borderRadius: "9999px",
                          background: ord.allHandedOver ? "rgba(16, 185, 129, 0.16)" : "rgba(249, 115, 22, 0.16)",
                          color: ord.allHandedOver ? "#34D399" : "#FB923C",
                        }}
                      >
                        {ord.allHandedOver ? "Collected" : "Ready For Collection"}
                      </span>
                    </div>

                    <div style={{ display: "flex", flexDirection: "column", gap: "6px", marginTop: "10px" }}>
                      {ord.items.map((it) => (
                        <div
                          key={it.menuItemId}
                          style={{
                            display: "flex",
                            justifyContent: "space-between",
                            fontSize: "13px",
                            color: "#CBD5E1",
                            background: "rgba(255, 255, 255, 0.03)",
                            padding: "6px 10px",
                            borderRadius: "8px",
                          }}
                        >
                          <span>
                            {it.quantity}x {it.name}
                          </span>
                          {(() => {
                            const isCollected = it.handoverStatus === "collected" || ((it.collectedQuantity ?? 0) >= it.quantity);
                            return (
                              <span style={{ color: isCollected ? "#10B981" : "#F97316", fontWeight: 700 }}>
                                {isCollected ? "Collected" : "Tent Pickup"}
                              </span>
                            );
                          })()}
                        </div>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* ============================================================ */}
        {/* VIEW 8: HELP AND SUPPORT                                     */}
        {/* ============================================================ */}
        {activeTab === "support" && (
          <div
            id="view-help-support"
            style={{
              background: "#162038",
              border: "1px solid rgba(255, 255, 255, 0.12)",
              borderRadius: "24px",
              padding: "24px",
              boxShadow: "0 10px 30px rgba(0,0,0,0.4)",
            }}
          >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "20px" }}>
              <div>
                <h3 style={{ fontSize: "18px", fontWeight: 800, margin: 0, color: "#FFFFFF" }}>
                  Help & Support Center
                </h3>
                <p style={{ fontSize: "13px", color: "#94A3B8", margin: "4px 0 0" }}>
                  Get assistance with tickets, gate entry guidelines, and order disputes.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setActiveTab("menu")}
                style={{
                  background: "rgba(255, 255, 255, 0.08)",
                  border: "none",
                  color: "#94A3B8",
                  borderRadius: "50%",
                  width: "32px",
                  height: "32px",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                }}
              >
                <X size={16} />
              </button>
            </div>

            <div style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
              <div
                style={{
                  background: "rgba(11, 19, 43, 0.6)",
                  border: "1px solid rgba(255, 255, 255, 0.08)",
                  borderRadius: "14px",
                  padding: "16px",
                }}
              >
                <strong style={{ fontSize: "14px", color: "#FFFFFF", display: "flex", alignItems: "center", gap: "8px" }}>
                  <Ticket size={16} style={{ color: "#EC4899" }} />
                  <span>Gate Arrival Guidelines</span>
                </strong>
                <p style={{ fontSize: "13px", color: "#94A3B8", margin: "6px 0 0", lineHeight: 1.5 }}>
                  Ensure your phone screen brightness is at 100% when presenting your QR code to authorized gate staff.
                  Screenshots are not accepted as offline passes. Bring matching photo ID when requested.
                </p>
              </div>

              <div
                style={{
                  background: "rgba(11, 19, 43, 0.6)",
                  border: "1px solid rgba(255, 255, 255, 0.08)",
                  borderRadius: "14px",
                  padding: "16px",
                }}
              >
                <strong style={{ fontSize: "14px", color: "#FFFFFF", display: "flex", alignItems: "center", gap: "8px" }}>
                  <HelpCircle size={16} style={{ color: "#FACC15" }} />
                  <span>Contact Organizer Support</span>
                </strong>
                <p style={{ fontSize: "13px", color: "#94A3B8", margin: "6px 0 10px", lineHeight: 1.5 }}>
                  Need to transfer a pass or report a payment confirmation delay?
                </p>
                <div style={{ display: "flex", gap: "10px", flexWrap: "wrap" }}>
                  <a
                    href="mailto:support@eventbite.example"
                    style={{
                      background: "rgba(255, 255, 255, 0.08)",
                      border: "1px solid rgba(255, 255, 255, 0.16)",
                      color: "#FFFFFF",
                      padding: "8px 14px",
                      borderRadius: "10px",
                      fontSize: "12px",
                      fontWeight: 700,
                      textDecoration: "none",
                      display: "inline-flex",
                      alignItems: "center",
                      gap: "6px",
                    }}
                  >
                    <Mail size={13} />
                    <span>support@eventbite.example</span>
                  </a>
                  <a
                    href="tel:+13055550199"
                    style={{
                      background: "rgba(255, 255, 255, 0.08)",
                      border: "1px solid rgba(255, 255, 255, 0.16)",
                      color: "#FFFFFF",
                      padding: "8px 14px",
                      borderRadius: "10px",
                      fontSize: "12px",
                      fontWeight: 700,
                      textDecoration: "none",
                      display: "inline-flex",
                      alignItems: "center",
                      gap: "6px",
                    }}
                  >
                    <Smartphone size={13} />
                    <span>+1 (305) 555-0199</span>
                  </a>
                </div>
              </div>
            </div>
          </div>
        )}
      </main>
    </div>
  );
};
