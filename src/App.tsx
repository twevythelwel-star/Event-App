import { useEffect, useMemo, useRef, useState } from "react";
import {
  ArrowDownLeft,
  Banknote,
  ChefHat,
  CreditCard,
  Globe,
  Info,
  Landmark,
  LockKeyhole,
  Scan,
  Shield,
  ShieldCheck,
  Sparkles,
  Wallet,
  X,
} from "lucide-react";

import {
  AdminAssignedScope,
  CashPaymentRecord,
  Currency,
  EventDetails,
  FulfillmentStatus,
  HandoverAuditEntry,
  HandoverStatus,
  MenuItem,
  MethodId,
  NotificationChannel,
  NotificationLogEntry,
  PaymentStatus,
  PlatformFeeSettings,
  SavedState,
  TicketDispute,
  TicketOrder,
} from "./types";
import {
  DEFAULT_ADMIN_SCOPES,
  DEFAULT_ATTENDEE_PROFILE,
  DEFAULT_EVENT_DETAILS,
  DEFAULT_MENU,
  DEFAULT_NOTIFICATION_PROVIDERS,
  DEFAULT_PLATFORM_FEE_SETTINGS,
  DEFAULT_PLATFORM_STATS,
  DEFAULT_SECURITY_AUDIT_LOGS,
  DEFAULT_SETTINGS,
  DEFAULT_SYSTEM_HEALTH,
  DEFAULT_TICKET_DISPUTES,
  INITIAL_SAVED_STATE,
  TEN_FISH_FRY_EVENTS,
} from "./data/defaultData";
import { GuestMenuCheckout } from "./components/GuestMenuCheckout";
import { StaffScanner } from "./components/StaffScanner";
import { OrganizerDashboard } from "./components/OrganizerDashboard";
import { SystemAdminDashboard } from "./components/SystemAdminDashboard";
import { DelegatedAdminDashboard } from "./components/DelegatedAdminDashboard";
import { SignInModal } from "./components/SignInModal";
import { BrandLogo } from "./components/BrandLogo";
import { HostApplicationCard } from "./components/HostApplicationCard";
import { HostApplicationsReview } from "./components/HostApplicationsReview";
import type { FishFryEventItem } from "./data/fishFryEvents";
import {
  ManualPaymentStatus,
  OrderRequest,
  SessionUser,
  EventPatch,
  MenuItemPayload,
  closeOrder as closeOrderLive,
  createHostMenuItem,
  dataUrlToBlob,
  deleteHostMenuItem,
  fetchAdminDisputes,
  fetchCashLedger,
  fetchEvents,
  fetchLocationCatalog,
  fetchHostEvents,
  fetchMyDisputes,
  fetchMessageLog,
  fetchMyOrders,
  fetchOperationsOrders,
  fetchSession,
  placeOrder,
  recordCashReceipt,
  recordHandover,
  removeHostFlyer,
  retryNotification as retryNotificationLive,
  sendEventReminders,
  setHostEventStatus,
  refundOrder as refundOrderLive,
  reverseCashReceipt,
  signOut,
  submitDispute,
  updateDisputeStatus,
  updateFulfillmentStatus as updateFulfillmentStatusLive,
  updateHostEvent,
  updateHostMenuItem,
  uploadHostFlyer,
  uploadHostMenuPhoto,
  updatePaymentStatus as updatePaymentStatusLive,
} from "./lib/api";

const STORAGE_KEY = "island-events-fishfry-v2";

export const METHOD_INFO: Record<
  MethodId,
  {
    name: string;
    shortName: string;
    icon: typeof Landmark;
    placeholder: string;
    badge: string;
    eligibilityNotice: string;
  }
> = {
  bank: {
    name: "Bank transfer",
    shortName: "Bank",
    icon: Landmark,
    placeholder: "U.S. bank and ACH transfer details provided by the event host",
    badge: "Direct clearing",
    eligibilityNotice: "U.S. bank transfer or ACH, subject to the event host's verified payment instructions.",
  },
  zelle: {
    name: "Zelle",
    shortName: "Zelle",
    icon: ArrowDownLeft,
    placeholder: "US-registered Zelle email address or 10-digit mobile number",
    badge: "US bank required",
    eligibilityNotice:
      "Requires an eligible U.S. bank account and a registered U.S. mobile number or email address.",
  },
  cashapp: {
    name: "Cash App",
    shortName: "Cash App",
    icon: Wallet,
    placeholder: "$Cashtag (e.g. $EventBiteHost)",
    badge: "US / UK only",
    eligibilityNotice:
      "Available when the event host has configured an eligible U.S. Cash App account.",
  },
  cash: {
    name: "Cash in person",
    shortName: "Cash",
    icon: Banknote,
    placeholder: "Pickup location or authorized collector contact",
    badge: "In-person receipt",
    eligibilityNotice: "Universal availability. Recorded directly into auditable cash ledger by authorized staff.",
  },
  card: {
    name: "Card payment",
    shortName: "Card",
    icon: CreditCard,
    placeholder: "Merchant processor API or hosted checkout URL",
    badge: "Instant processor",
    eligibilityNotice: "Requires an integrated card payment processor such as Stripe, Square, or PayPal/Braintree.",
  },
};

export const GATEWAY_RECOMMENDATIONS = {
  US: [
    {
      name: "Stripe",
      coverage: "United States & Global",
      currencies: "USD, 135+ currencies",
      methods: "Apple Pay, Google Pay, Credit/Debit, ACH Direct Debit",
      badge: "Industry Standard",
      description: "Comprehensive developer-first platform with automated webhooks and fraud detection.",
      bestFor: "Complete end-to-end event ticketing with instant confirmation.",
    },
    {
      name: "Square",
      coverage: "United States",
      currencies: "USD",
      methods: "In-person POS + Online payments, Cash App Pay",
      badge: "Best for In-person + Online",
      description: "Native Cash App Pay integration and hardware terminals for at-the-door gate sales.",
      bestFor: "Events with both presale tickets and on-site box office gate entry.",
    },
    {
      name: "PayPal & Braintree",
      coverage: "United States & Global",
      currencies: "USD, Global",
      methods: "PayPal, Venmo (US), Major credit cards",
      badge: "Trusted Brand",
      description: "High brand recognition among US event attendees and instant mobile checkout.",
      bestFor: "Reaching US buyers who prefer paying via stored PayPal balances or Venmo.",
    },
  ],
};

const formatMoney = (amount: number, currency: Currency = "USD") => {
  const curr = currency || "USD";
  return new Intl.NumberFormat(curr === "JMD" ? "en-JM" : "en-US", {
    style: "currency",
    currency: curr,
    maximumFractionDigits: 0,
  }).format(amount);
};

export function readSavedState(rawState: string | null = window.localStorage.getItem(STORAGE_KEY)): SavedState {
  if (!rawState) return INITIAL_SAVED_STATE;

  try {
    const parsed = JSON.parse(rawState) as Partial<SavedState>;
    if (!parsed || typeof parsed !== "object") return INITIAL_SAVED_STATE;
    const isLegacyDemo =
      parsed.eventDetails?.id === "evt-kingston-fishfry-2026" ||
      parsed.eventDetails?.slug === "kingston-fishfry-2026" ||
      parsed.eventDetails?.id === "evt-us-miami-festival-2026" ||
      parsed.eventDetails?.organizerId === "org-us-caribbean-heritage" ||
      [
        "evt-hellshire-sunset-fry",
        "evt-port-royal-heritage-fry",
        "evt-ochorios-fishermans-fry",
        "evt-negril-7mile-catch",
        "evt-mobay-pier1-fiesta",
        "evt-treasure-beach-calabash",
        "evt-boston-bay-jerk-fish",
        "evt-whitehouse-cooperative",
        "evt-runaway-bay-carnival",
      ].includes(parsed.eventDetails?.id || "") ||
      (parsed.eventDetails?.id === "evt-kingston-harbour-fry" &&
        (parsed.eventDetails.timezone === "America/Jamaica" ||
          parsed.eventDetails.venueAddress.includes("Jamaica")));
    const legacyOrganizerIds = new Set([
      ...(INITIAL_SAVED_STATE.organizers || []).map((organizer) => organizer.id),
      "org-us-caribbean-heritage",
      "org-portmore-guild",
      "org-mobay-culture",
    ]);
    const legacyTenantUserIds = new Set([
      ...(INITIAL_SAVED_STATE.tenantUsers || []).map((user) => user.id),
      "user-pastor-brown",
      "user-treasurer-davis",
      "user-chef-marcus",
      "user-evelyn-reid",
      "user-nadine-miller",
      "user-andre-watson",
    ]);
    const savedOrganizers = Array.isArray(parsed.organizers) ? parsed.organizers : [];
    const savedTenantUsers = Array.isArray(parsed.tenantUsers) ? parsed.tenantUsers : [];
    const savedAttendeeProfile = parsed.attendeeProfile;
    const savedAttendeeEvents = Array.isArray(savedAttendeeProfile?.savedEvents)
      ? savedAttendeeProfile.savedEvents.filter(
          (event) => event.eventId !== "evt-kingston-fishfry-2026" &&
            event.eventId !== DEFAULT_ATTENDEE_PROFILE.savedEvents[0]?.eventId,
        )
      : [];
    const hasLegacyMarketSettings =
      parsed.settings?.businessLocation === "JM" || parsed.settings?.currency === "JMD";

    // Drop only the known bundled demo orders; user-created and historical orders remain untouched.
    let mergedOrders: TicketOrder[] = Array.isArray(parsed.orders)
      ? parsed.orders.filter(
          (order) =>
            !order.id.startsWith("ord-fishfry-") &&
            !order.ticketCode.startsWith("SFF-2026-"),
        )
      : [...INITIAL_SAVED_STATE.orders];

    INITIAL_SAVED_STATE.orders.forEach((seedOrd) => {
      if (!mergedOrders.some((o) => o.ticketCode === seedOrd.ticketCode)) {
        mergedOrders.unshift(seedOrd);
      }
    });

    // Ensure item quantities have collectedQuantity normalized
    mergedOrders = mergedOrders.map((ord) => ({
      ...ord,
      items: (ord.items || []).map((itm) => ({
        ...itm,
        collectedQuantity:
          typeof itm.collectedQuantity === "number"
            ? itm.collectedQuantity
            : itm.handoverStatus === "collected"
            ? itm.quantity
            : 0,
      })),
    }));

    const savedNotificationLogs = Array.isArray(parsed.notificationLogs)
      ? parsed.notificationLogs.filter((log) => !log.orderId?.startsWith("ord-fishfry-"))
      : [];
    const savedHandoverLogs = Array.isArray(parsed.handoverAuditLogs)
      ? parsed.handoverAuditLogs.filter((log) => !log.orderId.startsWith("ord-fishfry-"))
      : [];

    return {
      eventDetails: isLegacyDemo ? DEFAULT_EVENT_DETAILS : parsed.eventDetails || DEFAULT_EVENT_DETAILS,
      menu: isLegacyDemo
        ? DEFAULT_MENU
        : Array.isArray(parsed.menu) && parsed.menu.length
          ? parsed.menu
          : DEFAULT_MENU,
      settings: {
        ...DEFAULT_SETTINGS,
        ...parsed.settings,
        ...(isLegacyDemo ? { businessLocation: "US", currency: "USD" } : {}),
        methods: {
          ...DEFAULT_SETTINGS.methods,
          ...(!isLegacyDemo || !hasLegacyMarketSettings ? parsed.settings?.methods : {}),
        },
      },
      orders: mergedOrders,
      cashLedger: Array.isArray(parsed.cashLedger)
        ? [
            ...parsed.cashLedger.filter((entry) => !["cash-rec-1", "cash-rec-2"].includes(entry.id)),
            ...INITIAL_SAVED_STATE.cashLedger.filter(
              (entry) => !parsed.cashLedger?.some((saved) => saved.id === entry.id),
            ),
          ]
        : INITIAL_SAVED_STATE.cashLedger,
      notificationLogs: [
        ...savedNotificationLogs,
        ...INITIAL_SAVED_STATE.notificationLogs.filter(
          (log) => !savedNotificationLogs.some((saved) => saved.id === log.id),
        ),
      ],
      handoverAuditLogs: [
        ...savedHandoverLogs,
        ...(INITIAL_SAVED_STATE.handoverAuditLogs || []).filter(
          (log) => !savedHandoverLogs.some((saved) => saved.id === log.id),
        ),
      ],
      organizers: isLegacyDemo
        ? [
            ...savedOrganizers.filter((organizer) => !legacyOrganizerIds.has(organizer.id)),
            ...(INITIAL_SAVED_STATE.organizers || []),
          ]
        : savedOrganizers.length
          ? savedOrganizers
          : INITIAL_SAVED_STATE.organizers,
      activeOrganizerId: isLegacyDemo
        ? parsed.activeOrganizerId && !legacyOrganizerIds.has(parsed.activeOrganizerId)
          ? parsed.activeOrganizerId
          : INITIAL_SAVED_STATE.activeOrganizerId
        : parsed.activeOrganizerId || INITIAL_SAVED_STATE.activeOrganizerId,
      activeRolePerspective: parsed.activeRolePerspective || INITIAL_SAVED_STATE.activeRolePerspective,
      tenantUsers: isLegacyDemo
        ? [
            ...(INITIAL_SAVED_STATE.tenantUsers || []),
            ...savedTenantUsers.filter((user) => !legacyTenantUserIds.has(user.id)),
          ]
        : savedTenantUsers.length
          ? savedTenantUsers
          : INITIAL_SAVED_STATE.tenantUsers,
      activeAccountType: parsed.activeAccountType || INITIAL_SAVED_STATE.activeAccountType || "event_host",
      platformOverviewStats: parsed.platformOverviewStats || DEFAULT_PLATFORM_STATS,
      systemHealthComponents: parsed.systemHealthComponents || DEFAULT_SYSTEM_HEALTH,
      notificationProviders: parsed.notificationProviders || DEFAULT_NOTIFICATION_PROVIDERS,
      platformFeeSettings: parsed.platformFeeSettings || DEFAULT_PLATFORM_FEE_SETTINGS,
      securityAuditLogs: parsed.securityAuditLogs || DEFAULT_SECURITY_AUDIT_LOGS,
      adminScopes: parsed.adminScopes || DEFAULT_ADMIN_SCOPES,
      ticketDisputes: parsed.ticketDisputes || DEFAULT_TICKET_DISPUTES,
      attendeeProfile: isLegacyDemo
        ? {
            ...(savedAttendeeProfile?.id === "att-akeem-campbell" || !savedAttendeeProfile
              ? DEFAULT_ATTENDEE_PROFILE
              : savedAttendeeProfile),
            savedEvents: [
              ...DEFAULT_ATTENDEE_PROFILE.savedEvents,
              ...savedAttendeeEvents.filter(
                (event) => !DEFAULT_ATTENDEE_PROFILE.savedEvents.some((sample) => sample.eventId === event.eventId),
              ),
            ],
          }
        : savedAttendeeProfile || DEFAULT_ATTENDEE_PROFILE,
    };
  } catch (error) {
    console.warn("Falling back to default initial state.", error);
    return INITIAL_SAVED_STATE;
  }
}

function loadInitialState() {
  try {
    return { state: readSavedState(), error: "" };
  } catch (error) {
    console.error("Unable to load saved event data.", error);
    return {
      state: INITIAL_SAVED_STATE,
      error: error instanceof Error ? error.message : "Unable to load saved data.",
    };
  }
}

export function App() {
  const [initialState] = useState(loadInitialState);
  const [savedState, setSavedState] = useState<SavedState>(initialState.state);
  const [activeTab, setActiveTab] = useState<"guest" | "staff" | "organizer" | "admin" | "system_admin">(() => {
    if (typeof window !== "undefined") {
      const q = new URLSearchParams(window.location.search).get("tab");
      if (q === "organizer" || q === "host") return "organizer";
      if (q === "staff" || q === "admin" || q === "system_admin") return q;
    }
    return "guest";
  });
  const [showGatewayModal, setShowGatewayModal] = useState(false);
  const [showAuthProfilesModal, setShowAuthProfilesModal] = useState(false);
  const [selectedEventIndex, setSelectedEventIndex] = useState(0);
  const [isGuestAuthenticated, setIsGuestAuthenticated] = useState(false);
  const [storageError, setStorageError] = useState(initialState.error);
  const [toastMessage, setToastMessage] = useState("");
  // "online" once the Laravel API answers; "offline" falls back to the bundled demo data in this browser
  const [apiStatus, setApiStatus] = useState<"connecting" | "online" | "offline">("connecting");
  const [sessionUser, setSessionUser] = useState<SessionUser | null>(null);
  const [apiEvents, setApiEvents] = useState<FishFryEventItem[] | null>(null);
  const [locationCatalog, setLocationCatalog] = useState<Awaited<ReturnType<typeof fetchLocationCatalog>> | null>(null);
  const [showSignInModal, setShowSignInModal] = useState(false);

  const {
    eventDetails,
    menu,
    settings,
    orders,
    cashLedger,
    notificationLogs,
    handoverAuditLogs = [],
    organizers = [],
    activeOrganizerId = "org-kingston-youth",
    activeRolePerspective = "event_owner",
    tenantUsers = [],
    platformOverviewStats = DEFAULT_PLATFORM_STATS,
    systemHealthComponents = DEFAULT_SYSTEM_HEALTH,
    notificationProviders = DEFAULT_NOTIFICATION_PROVIDERS,
    platformFeeSettings = DEFAULT_PLATFORM_FEE_SETTINGS,
    securityAuditLogs = DEFAULT_SECURITY_AUDIT_LOGS,
    adminScopes = DEFAULT_ADMIN_SCOPES,
    ticketDisputes = DEFAULT_TICKET_DISPUTES,
    attendeeProfile = DEFAULT_ATTENDEE_PROFILE,
  } = savedState;

  useEffect(() => {
    let cancelled = false;
    Promise.all([fetchSession(), fetchEvents(), fetchLocationCatalog()])
      .then(([user, events, locations]) => {
        if (cancelled) return;
        setSessionUser(user);
        setApiEvents(events);
        setLocationCatalog(locations);
        setApiStatus("online");
      })
      .catch((error) => {
        console.warn("EventBite API unreachable; using bundled demo data.", error);
        if (!cancelled) setApiStatus("offline");
      });
    return () => {
      cancelled = true;
    };
  }, []);

  // With a live backend the order list is exactly what the server returns for this user: their own orders plus
  // orders they can verify or hand over as a host or staff member (those carry `capabilities`)
  const [hasOperableOrders, setHasOperableOrders] = useState(false);
  // Supervisor overrides confirmed in the scanner, sent with later handovers on that order (server-enforced)
  const [supervisorOverrides, setSupervisorOverrides] = useState<Record<string, { reason: string; supervisorName?: string }>>({});

  useEffect(() => {
    if (apiStatus !== "online" || !sessionUser) {
      setHasOperableOrders(false);
      return;
    }
    Promise.all([fetchMyOrders(), fetchOperationsOrders(), fetchCashLedger(), fetchMessageLog()])
      .then(([myOrders, operableOrders, ledger, notifications]) => {
        const operableIds = new Set(operableOrders.map((o) => o.id));
        setHasOperableOrders(operableOrders.length > 0);
        setSavedState((curr) => ({
          ...curr,
          orders: [...operableOrders, ...myOrders.filter((o) => !operableIds.has(o.id))],
          cashLedger: ledger,
          notificationLogs: notifications,
        }));
      })
      .catch((error) => console.warn("Unable to load orders.", error));
  }, [apiStatus, sessionUser]);

  const isApiOnline = apiStatus === "online";
  const catalogEvents: FishFryEventItem[] = apiEvents ?? TEN_FISH_FRY_EVENTS;
  const sessionRoles = sessionUser?.roles ?? [];
  const hasSessionRole = (...roles: string[]) =>
    sessionRoles.includes("system_admin") || roles.some((role) => sessionRoles.includes(role));
  // With a live backend, dashboards are only offered to accounts that hold the matching role
  const canViewTab = {
    organizer: !isApiOnline || hasSessionRole("event_host"),
    admin: !isApiOnline || hasSessionRole("admin"),
    system_admin: !isApiOnline || hasSessionRole("system_admin"),
    staff: !isApiOnline || hasSessionRole("event_host") || hasOperableOrders,
  };
  const isLiveSession = isApiOnline && Boolean(sessionUser);
  const isLiveAdmin = isLiveSession && hasSessionRole("admin");

  useEffect(() => {
    if (!isLiveSession) return;
    (isLiveAdmin ? fetchAdminDisputes() : fetchMyDisputes())
      .then((disputes) => setSavedState((curr) => ({ ...curr, ticketDisputes: disputes })))
      .catch((error) => console.warn("Unable to load disputes.", error));
  }, [isLiveSession, isLiveAdmin]);
  // Host and scanner views only show orders the server says this user may operate on
  const operableOrders = isLiveSession ? orders.filter((o) => o.capabilities) : orders;
  // Events the signed-in host may edit (every status, so unpublished events stay reachable)
  const [hostEvents, setHostEvents] = useState<FishFryEventItem[]>([]);
  const isLiveHost = isLiveSession && hasSessionRole("event_host");

  useEffect(() => {
    if (!isLiveHost) {
      setHostEvents([]);
      return;
    }
    fetchHostEvents()
      .then(setHostEvents)
      .catch((error) => console.warn("Unable to load your events.", error));
  }, [isLiveHost]);

  // The live event the host dashboard edits, and walk-up cash is recorded against
  const hostLiveEvent: FishFryEventItem | undefined = isLiveSession
    ? hostEvents[0] ?? catalogEvents.find((e) => e.organizerId && sessionUser?.organizerIds?.includes(e.organizerId))
    : undefined;

  useEffect(() => {
    if (activeTab !== "guest" && !canViewTab[activeTab]) setActiveTab("guest");
  }, [activeTab, isApiOnline, sessionUser, hasOperableOrders]);

  useEffect(() => {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(savedState));
      setStorageError("");
    } catch (error) {
      console.error("Unable to save data in localStorage.", error);
      setStorageError("Changes could not be saved to browser storage.");
    }
  }, [savedState]);

  // Methods offered at checkout: must be enabled, have instructions, and if gated (Zelle/Cash App), must have eligible === true
  const availableMethods = useMemo(
    () =>
      (Object.keys(METHOD_INFO) as MethodId[]).filter((method) => {
        const config = settings.methods[method];
        if (!config || !config.enabled || !config.details.trim()) return false;
        if ((method === "zelle" || method === "cashapp") && !config.eligible) return false;
        return true;
      }),
    [settings.methods]
  );

  const pendingVerificationCount = orders.filter(
    (o) =>
      o.status === "payment_submitted" ||
      o.status === "under_verification" ||
      o.status === "pending_verification"
  ).length;

  function showToast(msg: string) {
    setToastMessage(msg);
    setTimeout(() => setToastMessage(""), 3000);
  }

  // Helper to append a notification log entry
  function logNotification(newLog: Omit<NotificationLogEntry, "id" | "sentAt">) {
    const nowIso = new Date().toISOString();
    if (isLiveSession) {
      // Live messages are sent and logged by the server; pick up its log instead of simulating one
      refreshNotifications();
      return { id: "", sentAt: nowIso, ...newLog } as NotificationLogEntry;
    }
    const entry: NotificationLogEntry = {
      id: `notif-${Date.now()}-${Math.floor(100 + Math.random() * 900)}`,
      sentAt: nowIso,
      createdAt: nowIso,
      ...newLog,
    };
    setSavedState((curr) => ({
      ...curr,
      notificationLogs: [entry, ...(curr.notificationLogs || [])],
    }));
    return entry;
  }

  // Resend / Retry a notification (e.g. after carrier delay)
  function refreshNotifications() {
    // The server queues messages after the action completes, so give it a moment
    setTimeout(() => {
      fetchMessageLog()
        .then((notifications) => setSavedState((curr) => ({ ...curr, notificationLogs: notifications })))
        .catch(() => undefined);
    }, 1200);
  }

  function handleRetryNotification(notifId: string) {
    if (isLiveSession) {
      retryNotificationLive(notifId)
        .then((updated) => {
          setSavedState((curr) => ({
            ...curr,
            notificationLogs: (curr.notificationLogs || []).map((n) => (n.id === updated.id ? updated : n)),
          }));
          showToast(updated.status === "sent" ? "Message re-sent." : `Message re-queued (${updated.status}).`);
        })
        .catch(reportLiveError);
      return;
    }

    setSavedState((curr) => ({
      ...curr,
      notificationLogs: (curr.notificationLogs || []).map((n) =>
        n.id === notifId
          ? {
              ...n,
              status: "delivered" as const,
              deliveredAt: new Date().toISOString(),
              retryCount: (n.retryCount || 0) + 1,
              failureReason: undefined,
            }
          : n
      ),
    }));
    showToast("SMS re-queued and delivered via Twilio carrier route.");
  }

  // Send an Email receipt or alternative notification
  function handleSendCustomNotification(
    param1: string | Omit<NotificationLogEntry, "id" | "sentAt">,
    param2?: NotificationChannel,
    param3?: string,
    param4?: string
  ) {
    if (typeof param1 === "object") {
      logNotification(param1);
      showToast(`Dispatched ${param1.channel.toUpperCase()} to ${param1.recipient}!`);
      return;
    }

    const orderId = param1;
    const channel = param2 || "sms";
    const recipient = param3 || "";
    const customMessage = param4;

    const ord = orders.find((o) => o.id === orderId);
    if (!ord) return;

    const messageText =
      customMessage ||
      `EventBite: Receipt & digital ticket for ${eventDetails.title}. Order: ${ord.orderNumber}. View pass: ${window.location.origin}/t/${ord.secureTicketToken}`;

    logNotification({
      orderId: ord.id,
      orderNumber: ord.orderNumber,
      customerName: ord.customerName,
      recipient,
      channel,
      trigger: "order_updated",
      title: channel === "email" ? "Official Email Receipt" : "Customer Notification",
      message: messageText,
      status: "delivered",
      provider:
        channel === "email"
          ? "Island Mail"
          : channel === "whatsapp"
          ? "WhatsApp Direct"
          : "Twilio SMS",
      deliveredAt: new Date().toISOString(),
    });
    showToast(`${channel === "email" ? "Email receipt" : "Notification"} dispatched to ${recipient}!`);
  }

  // Broadcast Event Approaching reminder to all orders
  function handleBroadcastEventReminder() {
    if (isLiveSession) {
      if (!hostLiveEvent?.id) {
        showToast("No live event to send reminders for.");
        return;
      }
      sendEventReminders(hostLiveEvent.id)
        .then((count) => {
          showToast(`Reminders queued for ${count} ticket holder${count === 1 ? "" : "s"}.`);
          refreshNotifications();
        })
        .catch(reportLiveError);
      return;
    }

    let count = 0;
    orders.forEach((ord) => {
      count++;
      logNotification({
        orderId: ord.id,
        orderNumber: ord.orderNumber,
        customerName: ord.customerName,
        recipient: ord.customerPhone,
        channel: "sms",
        trigger: "event_approaching",
        title: "Event Approaching — Pickup Reminder",
        message: `EventBite Reminder: ${eventDetails.title} is approaching on ${eventDetails.dateStr} at ${eventDetails.timeStr}! Location: ${eventDetails.venueName}. Show digital ticket ${ord.ticketCode} for express pickup.`,
        status: "delivered",
        provider: "Twilio SMS",
        deliveredAt: new Date().toISOString(),
      });
    });
    showToast(`Event reminder SMS broadcast dispatched to ${count} ticket holders!`);
  }

  // Guest Order Placement (Lifecycle Trigger: Order Created & Transfer Submitted)
  async function handlePlaceOrder(draftOrder: TicketOrder, request: OrderRequest): Promise<TicketOrder> {
    const selectedEvent = catalogEvents[selectedEventIndex];
    // The API prices and numbers the order; the draft is only used in offline demo mode
    const newOrder =
      isApiOnline && selectedEvent?.slug ? await placeOrder(selectedEvent.slug, request) : draftOrder;

    setSavedState((curr) => ({
      ...curr,
      orders: [newOrder, ...curr.orders],
    }));

    const isTransfer =
      newOrder.method === "bank" || newOrder.method === "zelle" || newOrder.method === "cashapp";
    const paymentInstructions = isTransfer
      ? `Total: ${formatMoney(newOrder.totalAmount, newOrder.currency)}. Follow ${METHOD_INFO[newOrder.method].name} instructions and provide your ref code.`
      : newOrder.method === "cash"
      ? `Total: ${formatMoney(newOrder.totalAmount, newOrder.currency)}. Cash payment reserved; pay at collection counter.`
      : `Payment confirmed via card.`;

    const ticketLink = `${window.location.origin}/t/${newOrder.secureTicketToken}`;
    const orderCreatedMsg = `EventBite: Order ${newOrder.orderNumber} created for ${eventDetails.title}! ${paymentInstructions} Digital ticket: ${ticketLink}`;

    // Stage 1: Order created notification
    logNotification({
      orderId: newOrder.id,
      orderNumber: newOrder.orderNumber,
      customerName: newOrder.customerName,
      recipient: newOrder.customerPhone,
      channel: "sms",
      trigger: "order_created",
      title: "Order Created — Payment Instructions",
      message: orderCreatedMsg,
      status: "delivered",
      provider: "Twilio SMS",
      deliveredAt: new Date().toISOString(),
    });

    // Stage 2: Transfer submitted notification (if ref was entered at checkout)
    if (newOrder.paymentReference) {
      logNotification({
        orderId: newOrder.id,
        orderNumber: newOrder.orderNumber,
        customerName: newOrder.customerName,
        recipient: newOrder.customerPhone,
        channel: "sms",
        trigger: "transfer_submitted",
        title: "Transfer Submitted — Verification Pending",
        message: `EventBite: Payment details received for Order ${newOrder.orderNumber} (Ref: ${newOrder.paymentReference}). We are verifying the transaction on our account statement. Note: This confirms details were received, NOT that payment has succeeded yet.`,
        status: "delivered",
        provider: "Twilio SMS",
        deliveredAt: new Date().toISOString(),
      });
    }

    showToast(`Order placed for ${newOrder.customerName}! Ticket issued.`);
    return newOrder;
  }

  async function handleSignOut() {
    try {
      await signOut();
    } finally {
      setSessionUser(null);
      setSavedState((curr) => ({
        ...curr,
        orders: INITIAL_SAVED_STATE.orders,
        cashLedger: INITIAL_SAVED_STATE.cashLedger,
        ticketDisputes: INITIAL_SAVED_STATE.ticketDisputes,
        notificationLogs: INITIAL_SAVED_STATE.notificationLogs,
      }));
      showToast("Signed out.");
    }
  }

  // ---- Live event & menu editing (host dashboard) ----------------------------------------

  // Event fields typed in the dashboard are saved together after a short pause, not on every keystroke
  const pendingEventPatch = useRef<EventPatch>({});
  const eventSaveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  function applyServerEvent(serverEvent: FishFryEventItem) {
    setHostEvents((curr) => curr.map((e) => (e.id === serverEvent.id ? serverEvent : e)));
    // Keep the public catalog in step: published events appear there, others drop out
    setApiEvents((curr) => {
      if (!curr) return curr;
      const others = curr.filter((e) => e.id !== serverEvent.id);
      return serverEvent.status === "published" ? [...others, serverEvent] : others;
    });
  }

  function reloadHostEvents() {
    fetchHostEvents().then(setHostEvents).catch(() => undefined);
  }

  function flushEventPatch(eventId: string) {
    if (eventSaveTimer.current) clearTimeout(eventSaveTimer.current);
    eventSaveTimer.current = null;
    const patch = pendingEventPatch.current;
    pendingEventPatch.current = {};
    if (Object.keys(patch).length === 0) return Promise.resolve();

    return updateHostEvent(eventId, patch)
      .then((saved) => {
        // Ignore a response that would overwrite fields typed since this save was sent
        if (Object.keys(pendingEventPatch.current).length === 0) applyServerEvent(saved);
      })
      .catch((error) => {
        reportLiveError(error);
        reloadHostEvents();
      });
  }

  /** Parses the dashboard's free-text deadline ("Friday, Nov 20, 2026 at 8:00 PM"); undefined while incomplete. */
  function parseDeadlineText(text: string): string | undefined {
    const normalized = text.replace(/\s+at\s+/i, " ").trim();
    return Number.isNaN(Date.parse(normalized)) ? undefined : normalized;
  }

  function liveUpdateEventDetails(next: EventDetails) {
    const current = hostLiveEvent;
    if (!current?.id) return;

    // Show the edit immediately; the server response later replaces it
    setHostEvents((curr) => curr.map((e) => (e.id === current.id ? { ...e, ...next, menu: e.menu } : e)));

    if (next.status && next.status !== current.status) {
      const eventId = current.id;
      void flushEventPatch(eventId).then(() =>
        setHostEventStatus(eventId, next.status as "draft" | "published" | "unpublished" | "cancelled")
          .then(applyServerEvent)
          .catch((error) => {
            reportLiveError(error);
            reloadHostEvents();
          })
      );
      return;
    }

    // A new flyer arrives as a browser data URL; removing it clears the URL
    if (next.flyerUrl !== current.flyerUrl) {
      const eventId = current.id;
      const flyerUrl = next.flyerUrl;
      const save = flyerUrl?.startsWith("data:")
        ? dataUrlToBlob(flyerUrl).then((blob) => uploadHostFlyer(eventId, blob))
        : !flyerUrl
        ? removeHostFlyer(eventId)
        : null;
      if (save) {
        save.then(applyServerEvent).catch((error) => {
          reportLiveError(error);
          reloadHostEvents();
        });
        return;
      }
    }

    const fields: [keyof EventDetails, keyof EventPatch][] = [
      ["title", "title"],
      ["tagline", "tagline"],
      ["dateStr", "date_str"],
      ["timeStr", "time_str"],
      ["venueName", "venue_name"],
      ["venueAddress", "venue_address"],
      ["streetAddress", "street_address"],
      ["venueBuildingName", "venue_building_name"],
      ["city", "city"],
      ["stateCode", "state_code"],
      ["zipCode", "zip_code"],
      ["countryCode", "country_code"],
      ["targetMarketId", "target_market_id"],
      ["timezone", "timezone"],
      ["coordinates", "coordinates"],
      ["description", "description"],
      ["pickupInstructions", "pickup_instructions"],
      ["organizerPhone", "organizer_phone"],
      ["organizerWhatsApp", "organizer_whatsapp"],
      ["organizerEmail", "organizer_email"],
      ["packagingFeeUSD", "packaging_fee_usd"],
      ["packagingFeeJMD", "packaging_fee_jmd"],
      ["isOrderingOpen", "is_ordering_open"],
    ];
    const patch: Record<string, unknown> = {};
    for (const [from, to] of fields) {
      if (next[from] !== undefined && next[from] !== current[from]) patch[to] = next[from];
    }
    const coordinatesChanged =
      next.latitude !== current.latitude || next.longitude !== current.longitude;
    if (coordinatesChanged) {
      if (next.latitude == null && next.longitude == null) {
        patch.latitude = null;
        patch.longitude = null;
      } else if (next.latitude != null && next.longitude != null) {
        patch.latitude = next.latitude;
        patch.longitude = next.longitude;
      }
    }
    if (next.advanceOrderDeadline !== current.advanceOrderDeadline) {
      const deadline = parseDeadlineText(next.advanceOrderDeadline);
      if (deadline) patch.advance_order_deadline = deadline;
    }
    if (Object.keys(patch).length === 0) return;

    pendingEventPatch.current = { ...pendingEventPatch.current, ...(patch as EventPatch) };
    if (eventSaveTimer.current) clearTimeout(eventSaveTimer.current);
    // Toggling ordering is a deliberate switch: save it right away
    const delay = "is_ordering_open" in patch ? 0 : 900;
    const eventId = current.id;
    eventSaveTimer.current = setTimeout(() => void flushEventPatch(eventId), delay);
  }

  function toMenuPayload(item: MenuItem): MenuItemPayload {
    return {
      name: item.name,
      category: item.category,
      description: item.description || null,
      pricing_type: item.pricingType,
      price_usd: Number(item.priceUSD) || 0,
      price_jmd: Math.round(Number(item.priceJMD) || 0),
      // A freshly picked photo is a browser data URL: it is uploaded separately, so send no link for it
      image_url: item.imageUrl && !item.imageUrl.startsWith("data:") ? item.imageUrl : null,
      custom_option_groups: item.pricingType === "customizable" ? item.customOptionGroups ?? null : null,
      custom_amount_config: item.pricingType === "custom_amount" ? item.customAmountConfig ?? null : null,
      allow_special_instructions: item.allowSpecialInstructions ?? true,
      available_stock: item.availableStock ?? null,
    };
  }

  function liveUpdateMenu(nextMenu: MenuItem[]) {
    const current = hostLiveEvent;
    if (!current?.id) return;
    const eventId = current.id;
    const previous = current.menu || [];

    setHostEvents((curr) => curr.map((e) => (e.id === eventId ? { ...e, menu: nextMenu } : e)));

    const previousIds = new Set(previous.map((m) => m.id));
    const nextIds = new Set(nextMenu.map((m) => m.id));
    const calls: Promise<FishFryEventItem>[] = [];

    // An uploaded photo (data URL) is sent once the item exists on the server
    const withPhoto = (item: MenuItem, saved: Promise<FishFryEventItem>) =>
      item.imageUrl?.startsWith("data:")
        ? saved.then(async (event) => {
            // New items get their server id here: the one item that was not on the menu before
            const serverItem = previousIds.has(item.id)
              ? event.menu.find((m) => m.id === item.id)
              : event.menu.find((m) => !previousIds.has(m.id) && m.name === item.name);
            if (!serverItem) return event;
            return uploadHostMenuPhoto(eventId, serverItem.id, await dataUrlToBlob(item.imageUrl));
          })
        : saved;

    for (const item of nextMenu) {
      if (!previousIds.has(item.id)) {
        calls.push(withPhoto(item, createHostMenuItem(eventId, toMenuPayload(item))));
      } else {
        const before = previous.find((m) => m.id === item.id)!;
        if (JSON.stringify(toMenuPayload(before)) !== JSON.stringify(toMenuPayload(item))) {
          calls.push(withPhoto(item, updateHostMenuItem(eventId, item.id, toMenuPayload(item))));
        }
      }
    }
    for (const item of previous) {
      if (!nextIds.has(item.id)) calls.push(deleteHostMenuItem(eventId, item.id));
    }

    // Server ids replace the dashboard's temporary ids once the calls settle
    Promise.all(calls)
      .then((results) => {
        if (results.length) applyServerEvent(results[results.length - 1]);
      })
      .catch((error) => {
        reportLiveError(error);
        reloadHostEvents();
      });
  }

  function applyServerOrder(serverOrder: TicketOrder) {
    setSavedState((curr) => ({
      ...curr,
      orders: curr.orders.map((o) => (o.id === serverOrder.id ? serverOrder : o)),
    }));
    // Payment, pickup and handover changes may have sent the customer a message
    if (hasSessionRole("event_host")) refreshNotifications();
  }

  function reportLiveError(error: unknown) {
    showToast(error instanceof Error ? error.message : "The server rejected that change.");
  }

  function liveItemHandover(
    orderId: string,
    itemIndex: number,
    quantityDeltaOrStatus: number | HandoverStatus,
    auditDetails?: Parameters<typeof handleUpdateItemHandover>[3]
  ) {
    // A zero-quantity supervisor override is the scanner unlocking an unpaid order; keep it for the next handovers
    if (auditDetails?.actionType === "supervisor_override" && quantityDeltaOrStatus === 0) {
      setSupervisorOverrides((curr) => ({
        ...curr,
        [orderId]: { reason: auditDetails.overrideReason || "", supervisorName: auditDetails.supervisorName },
      }));
      return;
    }

    const order = orders.find((o) => o.id === orderId);
    const item = order?.items[itemIndex];
    if (!order || !item?.id) {
      showToast("This order is not in the live system.");
      return;
    }

    const collected = item.collectedQuantity ?? (item.handoverStatus === "collected" ? item.quantity : 0);
    const target =
      typeof quantityDeltaOrStatus === "number"
        ? quantityDeltaOrStatus === 999
          ? item.quantity
          : Math.max(0, Math.min(item.quantity, collected + quantityDeltaOrStatus))
        : quantityDeltaOrStatus === "collected"
        ? item.quantity
        : 0;
    const delta = target - collected;
    if (delta === 0) return;

    const override = supervisorOverrides[orderId];
    const isUnpaid = order.status !== "paid" && !order.authorizedUnpaid;
    const actionType = delta < 0 ? "reversal" : isUnpaid && override ? "supervisor_override" : "collection";
    const overrideReason =
      actionType === "reversal"
        ? auditDetails?.overrideReason || `Staff correction from the scanner by ${auditDetails?.staffName || "staff"}`
        : actionType === "supervisor_override"
        ? override?.reason
        : undefined;

    recordHandover(orderId, item.id, {
      quantity_delta: delta,
      station: auditDetails?.station,
      action_type: actionType,
      override_reason: overrideReason,
      supervisor_name: actionType === "supervisor_override" ? override?.supervisorName : undefined,
    })
      .then(({ order: updated, audit }) => {
        applyServerOrder(updated);
        setSavedState((curr) => ({ ...curr, handoverAuditLogs: [audit, ...(curr.handoverAuditLogs || [])] }));
        if (updated.allHandedOver && !order.allHandedOver) showToast("All portions handed over. Order closed.");
      })
      .catch(reportLiveError);
  }

  // Staff Item Handover update with portion stepper, station audit trail, and auto-complete
  function handleUpdateItemHandover(
    orderId: string,
    itemIndex: number,
    quantityDeltaOrStatus: number | HandoverStatus,
    auditDetails?: {
      staffName: string;
      station: string;
      actionType?: "collection" | "reversal" | "supervisor_override";
      overrideReason?: string;
      supervisorName?: string;
    }
  ) {
    if (isLiveSession) {
      liveItemHandover(orderId, itemIndex, quantityDeltaOrStatus, auditDetails);
      return;
    }

    setSavedState((curr) => {
      let newlyClosedOrder: TicketOrder | null = null;
      let newAuditEntry: HandoverAuditEntry | null = null;

      const nextOrders = curr.orders.map((ord) => {
        if (ord.id !== orderId) return ord;
        const nextItems = [...ord.items];
        const targetItem = nextItems[itemIndex];
        if (!targetItem) return ord;

        const currentCollected =
          typeof targetItem.collectedQuantity === "number"
            ? targetItem.collectedQuantity
            : targetItem.handoverStatus === "collected"
            ? targetItem.quantity
            : 0;

        let newCollected = currentCollected;
        if (typeof quantityDeltaOrStatus === "number") {
          if (quantityDeltaOrStatus === 999) {
            newCollected = targetItem.quantity;
          } else {
            newCollected = Math.max(0, Math.min(targetItem.quantity, currentCollected + quantityDeltaOrStatus));
          }
        } else {
          newCollected = quantityDeltaOrStatus === "collected" ? targetItem.quantity : 0;
        }

        const deltaRecorded = newCollected - currentCollected;
        const isNowCollected = newCollected >= targetItem.quantity;

        nextItems[itemIndex] = {
          ...targetItem,
          collectedQuantity: newCollected,
          handoverStatus: isNowCollected ? "collected" : "pending",
          handedOverAt: isNowCollected ? new Date().toISOString() : targetItem.handedOverAt,
          handedOverBy: auditDetails?.staffName || targetItem.handedOverBy,
          stationHandedOver: auditDetails?.station || targetItem.stationHandedOver,
        };

        if (deltaRecorded !== 0 && auditDetails) {
          newAuditEntry = {
            id: `audit-${Date.now()}-${Math.floor(100 + Math.random() * 900)}`,
            orderId: ord.id,
            orderNumber: ord.orderNumber,
            ticketCode: ord.ticketCode,
            customerName: ord.customerName,
            itemName: targetItem.name,
            itemCategory: targetItem.category,
            quantityCollected: Math.abs(deltaRecorded),
            remainingQuantity: Math.max(0, targetItem.quantity - newCollected),
            timestamp: new Date().toISOString(),
            staffName: auditDetails.staffName,
            station: auditDetails.station,
            actionType: auditDetails.actionType || (deltaRecorded > 0 ? "collection" : "reversal"),
            overrideReason: auditDetails.overrideReason,
            supervisorName: auditDetails.supervisorName,
          };
        }

        const allDone = nextItems.every((i) => {
          const col =
            typeof i.collectedQuantity === "number"
              ? i.collectedQuantity
              : i.handoverStatus === "collected"
              ? i.quantity
              : 0;
          return col >= i.quantity;
        });

        const updatedOrd = {
          ...ord,
          items: nextItems,
          allHandedOver: allDone,
          fulfillmentStatus: allDone ? ("collected" as FulfillmentStatus) : ord.fulfillmentStatus,
          closedAt: allDone ? ord.closedAt || new Date().toISOString() : undefined,
        };

        if (allDone && !ord.allHandedOver) {
          newlyClosedOrder = updatedOrd;
        }

        return updatedOrd;
      });

      if (newlyClosedOrder) {
        logNotification({
          orderId: (newlyClosedOrder as TicketOrder).id,
          orderNumber: (newlyClosedOrder as TicketOrder).orderNumber,
          customerName: (newlyClosedOrder as TicketOrder).customerName,
          recipient: (newlyClosedOrder as TicketOrder).customerPhone,
          channel: "sms",
          trigger: "items_collected",
          title: "Items Collected — Handover Completed",
          message: `EventBite: Handover completed! All meal portions for ticket ${(newlyClosedOrder as TicketOrder).ticketCode} have been collected. Big up and enjoy your authentic food!`,
          status: "delivered",
          provider: "Twilio SMS",
          deliveredAt: new Date().toISOString(),
        });
      }

      return {
        ...curr,
        orders: nextOrders,
        handoverAuditLogs: newAuditEntry
          ? [newAuditEntry, ...(curr.handoverAuditLogs || [])]
          : curr.handoverAuditLogs,
      };
    });
  }

  // Staff Close Order (Lifecycle Trigger: Items Collected)
  function handleCloseOrder(orderId: string) {
    if (isLiveSession) {
      closeOrderLive(orderId)
        .then((updated) => {
          applyServerOrder(updated);
          showToast("Order closed! All items collected.");
        })
        .catch(reportLiveError);
      return;
    }

    setSavedState((curr) => {
      const targetOrd = curr.orders.find((o) => o.id === orderId);
      if (targetOrd) {
        logNotification({
          orderId: targetOrd.id,
          orderNumber: targetOrd.orderNumber,
          customerName: targetOrd.customerName,
          recipient: targetOrd.customerPhone,
          channel: "sms",
          trigger: "items_collected",
          title: "Items Collected — Final Confirmation",
          message: `EventBite: Handover completed! All meal portions for ticket ${targetOrd.ticketCode} have been collected. Big up and enjoy your authentic food!`,
          status: "delivered",
          provider: "Twilio SMS",
          deliveredAt: new Date().toISOString(),
        });
      }

      return {
        ...curr,
        orders: curr.orders.map((ord) =>
          ord.id === orderId
            ? {
                ...ord,
                allHandedOver: true,
                fulfillmentStatus: "collected" as FulfillmentStatus,
                closedAt: new Date().toISOString(),
                items: ord.items.map((i) => ({
                  ...i,
                  collectedQuantity: i.quantity,
                  handoverStatus: "collected",
                  handedOverAt: i.handedOverAt || new Date().toISOString(),
                })),
              }
            : ord
        ),
      };
    });
    showToast("Order closed! All items collected.");
  }

  // Multi-status Payment Workflow handler (Lifecycle Trigger: Payment Verified / Order Updated)
  function handleUpdatePaymentStatus(
    orderId: string,
    newStatus: PaymentStatus,
    details?: {
      amountPaid?: number;
      notes?: string;
      verifier?: string;
      rejectionReason?: string;
      refundDetails?: { amount: number; reason: string; refundedAt: string; refundedBy: string };
    }
  ) {
    if (isLiveSession && newStatus === "refunded") {
      const refund = details?.refundDetails;
      if (!refund || !(refund.amount > 0)) {
        showToast("Enter the refund amount.");
        return;
      }
      refundOrderLive(orderId, { amount: refund.amount, reason: refund.reason })
        .then((updated) => {
          applyServerOrder(updated);
          showToast(
            updated.status === "refunded"
              ? "Order fully refunded."
              : `Partial refund of ${formatMoney(refund.amount, updated.currency)} recorded.`
          );
        })
        .catch(reportLiveError);
      return;
    }

    if (isLiveSession) {
      const manualStatuses: PaymentStatus[] = ["under_verification", "partially_paid", "paid", "rejected"];
      if (!manualStatuses.includes(newStatus)) {
        showToast(`"${newStatus.replace("_", " ")}" is not available with live data yet.`);
        return;
      }
      updatePaymentStatusLive(orderId, {
        status: newStatus as ManualPaymentStatus,
        amount_paid: newStatus === "partially_paid" ? details?.amountPaid : undefined,
        notes: details?.notes,
        rejection_reason:
          newStatus === "rejected" ? details?.rejectionReason || "Payment not found on the statement" : undefined,
      })
        .then((updated) => {
          applyServerOrder(updated);
          showToast(`Payment status updated to: ${newStatus.replace("_", " ").toUpperCase()}`);
        })
        .catch(reportLiveError);
      return;
    }

    setSavedState((curr) => {
      const targetOrd = curr.orders.find((o) => o.id === orderId);
      if (targetOrd) {
        if (newStatus === "paid" && targetOrd.status !== "paid") {
          // Stage 3: Payment verified
          logNotification({
            orderId: targetOrd.id,
            orderNumber: targetOrd.orderNumber,
            customerName: targetOrd.customerName,
            recipient: targetOrd.customerPhone,
            channel: "sms",
            trigger: "payment_verified",
            title: "Payment Verified — Ticket Active",
            message: `EventBite: Payment of ${formatMoney(targetOrd.totalAmount, targetOrd.currency)} verified for ${eventDetails.title}! Your digital ticket ${targetOrd.ticketCode} is now PAID & ACTIVE. View pass: ${window.location.origin}/t/${targetOrd.secureTicketToken}`,
            status: "delivered",
            provider: "Twilio SMS",
            deliveredAt: new Date().toISOString(),
          });
        } else if (newStatus === "partially_paid") {
          // Stage 5: Order updated (Revised items or payment balance)
          const paidAmt = details?.amountPaid || targetOrd.amountPaid || 0;
          const remaining = Math.max(0, targetOrd.totalAmount - paidAmt);
          logNotification({
            orderId: targetOrd.id,
            orderNumber: targetOrd.orderNumber,
            customerName: targetOrd.customerName,
            recipient: targetOrd.customerPhone,
            channel: "sms",
            trigger: "order_updated",
            title: "Order Updated — Partial Balance Verified",
            message: `EventBite: Order ${targetOrd.orderNumber} updated. Partial deposit of ${formatMoney(paidAmt, targetOrd.currency)} confirmed. Remaining balance due: ${formatMoney(remaining, targetOrd.currency)}.`,
            status: "delivered",
            provider: "Twilio SMS",
            deliveredAt: new Date().toISOString(),
          });
        }
      }

      return {
        ...curr,
        orders: curr.orders.map((ord) => {
          if (ord.id !== orderId) return ord;
          const isNowPaid = newStatus === "paid";
          return {
            ...ord,
            status: newStatus,
            amountPaid:
              details?.amountPaid !== undefined
                ? details.amountPaid
                : isNowPaid
                ? ord.totalAmount
                : ord.amountPaid,
            verifiedAt:
              isNowPaid || newStatus === "partially_paid"
                ? ord.verifiedAt || new Date().toISOString()
                : ord.verifiedAt,
            verifiedBy: details?.verifier || ord.verifiedBy,
            verificationNotes: details?.notes || ord.verificationNotes,
            rejectionReason: details?.rejectionReason || ord.rejectionReason,
            refundDetails: details?.refundDetails || ord.refundDetails,
          };
        }),
      };
    });
    showToast(`Payment status updated to: ${newStatus.replace("_", " ").toUpperCase()}`);
  }

  // Fulfillment / Kitchen Status update (Lifecycle Trigger: Order Ready)
  function handleUpdateFulfillmentStatus(orderId: string, newFulfillment: FulfillmentStatus) {
    if (isLiveSession) {
      updateFulfillmentStatusLive(orderId, newFulfillment)
        .then((updated) => {
          applyServerOrder(updated);
          showToast(`Fulfillment status: ${newFulfillment.replace("_", " ").toUpperCase()}`);
        })
        .catch(reportLiveError);
      return;
    }

    setSavedState((curr) => {
      if (newFulfillment === "ready_for_pickup") {
        const targetOrd = curr.orders.find((o) => o.id === orderId);
        if (targetOrd) {
          logNotification({
            orderId: targetOrd.id,
            orderNumber: targetOrd.orderNumber,
            customerName: targetOrd.customerName,
            recipient: targetOrd.customerPhone,
            channel: "sms",
            trigger: "order_ready",
            title: "Order Ready — Kitchen Pickup Notice",
            message: `EventBite: Your meal order ${targetOrd.orderNumber} is FRESH & READY for collection at ${eventDetails.venueName}! Show ticket ${targetOrd.ticketCode} to counter staff.`,
            status: "delivered",
            provider: "Twilio SMS",
            deliveredAt: new Date().toISOString(),
          });
        }
      }

      return {
        ...curr,
        orders: curr.orders.map((ord) =>
          ord.id === orderId ? { ...ord, fulfillmentStatus: newFulfillment } : ord
        ),
      };
    });
    showToast(`Fulfillment status: ${newFulfillment.replace("_", " ").toUpperCase()}`);
  }

  // Record Cash Payment Form Submission (Lifecycle Trigger: Cash Received)
  function handleRecordCashPayment(
    newCashRecord: CashPaymentRecord,
    linkedOrderId?: string
  ): Promise<CashPaymentRecord> | void {
    if (isLiveSession) {
      if (!linkedOrderId && !hostLiveEvent?.id) {
        showToast("No live event found for your organizer to record walk-up cash against.");
        return Promise.reject(new Error("No live event"));
      }
      // The server issues the receipt number and records the signed-in account as the receiver
      return recordCashReceipt({
        order_id: linkedOrderId,
        event_id: linkedOrderId ? undefined : hostLiveEvent?.id,
        customer_name: newCashRecord.customerName,
        customer_phone: newCashRecord.customerPhone,
        amount: newCashRecord.amount,
        currency: newCashRecord.currency,
        received_at: newCashRecord.receivedAt,
        received_from: newCashRecord.receivedBy,
        notes: newCashRecord.notes,
      }).then(
        ({ receipt, order }) => {
          setSavedState((curr) => ({ ...curr, cashLedger: [receipt, ...(curr.cashLedger || [])] }));
          if (order) applyServerOrder(order);
          showToast(`Cash payment of ${formatMoney(receipt.amount, receipt.currency)} recorded (${receipt.receiptNumber}).`);
          return receipt;
        },
        (error) => {
          reportLiveError(error);
          throw error;
        }
      );
    }

    setSavedState((curr) => {
      let updatedOrders = curr.orders;
      if (linkedOrderId) {
        updatedOrders = curr.orders.map((ord) => {
          if (ord.id !== linkedOrderId) return ord;
          const prevPaid = ord.amountPaid || 0;
          const totalPaid = prevPaid + newCashRecord.amount;
          const isFullyPaid = totalPaid >= ord.totalAmount;
          return {
            ...ord,
            status: isFullyPaid ? "paid" : "partially_paid",
            amountPaid: totalPaid,
            method: "cash",
            verifiedAt: new Date().toISOString(),
            verifiedBy: newCashRecord.receivedBy,
            verificationNotes: `Cash receipt ${newCashRecord.receiptNumber} recorded by ${newCashRecord.receivedBy}.`,
          };
        });
      }

      // Stage 4: Cash received notification (Exact illustrative template)
      const dateStr = new Date(newCashRecord.receivedAt).toLocaleDateString("en-US", {
        month: "long",
        day: "numeric",
        year: "numeric",
      });
      const cashReceiptMsg = `Payment Received — ${eventDetails.title}\n\nHi! We have recorded your cash payment of ${formatMoney(newCashRecord.amount, newCashRecord.currency)} for your ${eventDetails.title} order.\n\nOrder: ${newCashRecord.orderNumber || "DIRECT-CASH"}\nPayment method: Cash\nDate received: ${dateStr}\nStatus: Paid\n\nPlease keep your digital ticket ready when collecting your order. Thank you for supporting our event!`;

      logNotification({
        orderId: linkedOrderId || newCashRecord.id,
        orderNumber: newCashRecord.orderNumber || newCashRecord.receiptNumber,
        customerName: newCashRecord.customerName,
        recipient: newCashRecord.customerPhone,
        channel: "sms",
        trigger: "cash_received",
        title: "Cash Received — Official Receipt",
        message: cashReceiptMsg,
        status: "delivered",
        provider: "Twilio SMS",
        deliveredAt: new Date().toISOString(),
      });

      return {
        ...curr,
        orders: updatedOrders,
        cashLedger: [newCashRecord, ...(curr.cashLedger || [])],
      };
    });
    showToast(`Cash payment of ${formatMoney(newCashRecord.amount, newCashRecord.currency)} recorded!`);
  }

  // Financial Control: Auditable Cash Reversal / Adjustment
  function handleReverseCashPayment(reversalRecord: CashPaymentRecord, linkedOrderId?: string) {
    if (isLiveSession) {
      if (!reversalRecord.reversalOfId) {
        showToast("Choose the cash receipt to reverse.");
        return;
      }
      reverseCashReceipt(reversalRecord.reversalOfId, {
        amount: Math.abs(reversalRecord.amount),
        reason: reversalRecord.reversalReason || "",
      })
        .then(({ reversal, order }) => {
          setSavedState((curr) => ({ ...curr, cashLedger: [reversal, ...(curr.cashLedger || [])] }));
          if (order) applyServerOrder(order);
          showToast(`Auditable cash adjustment logged: ${reversal.receiptNumber}`);
        })
        .catch(reportLiveError);
      return;
    }

    setSavedState((curr) => {
      let updatedOrders = curr.orders;
      if (linkedOrderId) {
        updatedOrders = curr.orders.map((ord) => {
          if (ord.id !== linkedOrderId) return ord;
          const prevPaid = ord.amountPaid || ord.totalAmount;
          const newPaid = Math.max(0, prevPaid + reversalRecord.amount); // negative amount
          return {
            ...ord,
            amountPaid: newPaid,
            status: newPaid === 0 ? "awaiting_payment" : "partially_paid",
            verificationNotes: `Reversal ${reversalRecord.receiptNumber} applied: ${reversalRecord.reversalReason}`,
          };
        });
      }
      return {
        ...curr,
        orders: updatedOrders,
        cashLedger: [reversalRecord, ...(curr.cashLedger || [])],
      };
    });
    showToast(`Auditable cash adjustment logged: ${reversalRecord.receiptNumber}`);
  }

  function handleResetDemo() {
    setSavedState(INITIAL_SAVED_STATE);
    showToast("Demo reset to default state.");
  }

  function handleUpdateFeeSettings(newFees: PlatformFeeSettings) {
    setSavedState((c) => ({
      ...c,
      platformFeeSettings: newFees,
      securityAuditLogs: [
        {
          id: `sec-${Date.now()}`,
          timestamp: new Date().toISOString(),
          actorId: "user-platform-superadmin",
          actorName: "Platform Security & DevOps Admin",
          actorRole: "system_admin",
          action: "modify_platform_fees",
          targetEntity: "platform_fee_settings",
          targetId: "global-fees",
          ipAddress: "127.0.0.1",
          severity: "warning",
          details: `Updated platform take rate to ${newFees.takeRatePercent}%, USD fee $${newFees.fixedFeeUSD}, JMD fee J$${newFees.fixedFeeJMD}.`,
          isPiiAccess: false,
        },
        ...(c.securityAuditLogs || []),
      ],
    }));
    showToast("Platform fee and currency configurations saved.");
  }

  function handleToggleUserStatus(userId: string) {
    setSavedState((c) => {
      const updatedUsers = (c.tenantUsers || []).map((u) => {
        if (u.id !== userId) return u;
        const newActive = !u.isActive;
        return { ...u, isActive: newActive };
      });
      const targeted = (c.tenantUsers || []).find((u) => u.id === userId);
      const actionName = targeted?.isActive ? "account_suspension" : "account_activation";

      return {
        ...c,
        tenantUsers: updatedUsers,
        securityAuditLogs: [
          {
            id: `sec-${Date.now()}`,
            timestamp: new Date().toISOString(),
            actorId: "user-platform-superadmin",
            actorName: "Platform Security & DevOps Admin",
            actorRole: "system_admin",
            action: actionName,
            targetEntity: "tenant_users",
            targetId: userId,
            ipAddress: "127.0.0.1",
            severity: "warning",
            details: `${actionName === "account_suspension" ? "Suspended" : "Activated"} user ${targeted?.name || userId}.`,
            isPiiAccess: false,
          },
          ...(c.securityAuditLogs || []),
        ],
      };
    });
    showToast("Account status updated.");
  }

  function handleAssignAdminScope(scope: AdminAssignedScope) {
    setSavedState((c) => ({
      ...c,
      adminScopes: [scope, ...(c.adminScopes || []).filter((s) => s.id !== scope.id)],
    }));
    showToast(`Updated scope for ${scope.adminName}.`);
  }

  function handleResolveDispute(disputeId: string, resolution: "resolved" | "rejected", notes: string) {
    if (isLiveSession) {
      updateDisputeStatus(disputeId, resolution, notes)
        .then((updated) =>
          setSavedState((c) => ({
            ...c,
            ticketDisputes: (c.ticketDisputes || []).map((d) => (d.id === updated.id ? updated : d)),
          }))
        )
        .catch(reportLiveError);
      return;
    }

    setSavedState((c) => {
      const updatedDisputes = (c.ticketDisputes || []).map((d) => {
        if (d.id !== disputeId) return d;
        return {
          ...d,
          status: resolution,
          resolutionNotes: notes,
          assignedAdminName: "Sister Beverly Campbell",
          updatedAt: new Date().toISOString(),
        };
      });

      return {
        ...c,
        ticketDisputes: updatedDisputes,
        securityAuditLogs: [
          {
            id: `sec-${Date.now()}`,
            timestamp: new Date().toISOString(),
            actorId: "user-admin-beverly",
            actorName: "Sister Beverly Campbell",
            actorRole: "admin",
            action: `dispute_${resolution}`,
            targetEntity: "ticket_disputes",
            targetId: disputeId,
            ipAddress: "127.0.0.1",
            severity: "info",
            details: `Dispute ${disputeId} marked as ${resolution.toUpperCase()}: ${notes}`,
            isPiiAccess: true,
          },
          ...(c.securityAuditLogs || []),
        ],
      };
    });
    showToast(`Dispute resolved as ${resolution}.`);
  }

  function handleSubmitDispute(claim: {
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
  }) {
    if (isLiveSession) {
      const order = orders.find((o) => o.orderNumber === claim.orderNumber);
      if (!order) {
        showToast("That order is not in the live system.");
        return;
      }
      submitDispute({ order_id: order.id, reason: claim.reason, amount: claim.amount })
        .then((created) => {
          setSavedState((c) => ({ ...c, ticketDisputes: [created, ...(c.ticketDisputes || [])] }));
          showToast(`Dispute ${created.disputeNumber} submitted for administrator review.`);
        })
        .catch(reportLiveError);
      return;
    }

    const newDispute: TicketDispute = {
      id: `DSP-${Date.now().toString().slice(-4)}`,
      disputeNumber: `DSP-2026-${Math.floor(100 + Math.random() * 900)}`,
      orderNumber: claim.orderNumber,
      ticketCode: claim.ticketCode,
      customerName: claim.customerName,
      customerPhone: claim.customerPhone,
      organizerId: claim.organizerId,
      organizerName: claim.organizerName,
      eventName: claim.eventName,
      disputeType: "ticket_refund",
      status: "open",
      amount: claim.amount,
      currency: claim.currency,
      reason: claim.reason,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };

    setSavedState((c) => ({
      ...c,
      ticketDisputes: [newDispute, ...(c.ticketDisputes || [])],
    }));
    showToast(`Dispute ticket ${newDispute.disputeNumber} submitted for administrator review.`);
  }

  function handleAssistHostRecovery(hostEmail: string) {
    setSavedState((c) => ({
      ...c,
      securityAuditLogs: [
        {
          id: `sec-${Date.now()}`,
          timestamp: new Date().toISOString(),
          actorId: "user-admin-beverly",
          actorName: "Sister Beverly Campbell",
          actorRole: "admin",
          action: "host_account_recovery_dispatch",
          targetEntity: "organizers",
          targetId: hostEmail,
          ipAddress: "127.0.0.1",
          severity: "info",
          details: `Dispatched single-use recovery token to host email: ${hostEmail}`,
          isPiiAccess: true,
        },
        ...(c.securityAuditLogs || []),
      ],
    }));
  }

  return (
    <main className="app-shell">
      {/* Top Application Header */}
      <header className="topbar">
        <a className="brand" href="#" aria-label="EventBite Home">
          <BrandLogo />
        </a>

        <div className="topbar-right">
          <div className="location-pill">
            <Globe size={13} />
            <span>
              United States Base (US · USD)
            </span>
          </div>

          <div className="currency-pill">
            <span>Currency:</span>
            <strong>{settings.currency}</strong>
          </div>

          <button
            type="button"
            className="gateway-header-btn"
            onClick={() => setShowGatewayModal(true)}
            title="Explore automated payment gateways"
          >
            <Sparkles size={14} /> Gateways
          </button>

          {isApiOnline ? (
            sessionUser ? (
              <button
                type="button"
                className="gateway-header-btn"
                onClick={() => void handleSignOut()}
                id="btn-sign-out"
              >
                <LockKeyhole size={14} /> Sign out ({sessionUser.name})
              </button>
            ) : (
              <button
                type="button"
                className="gateway-header-btn"
                style={{ background: "#4f46e5", color: "#fff", borderColor: "#4338ca" }}
                onClick={() => setShowSignInModal(true)}
                id="btn-sign-in"
              >
                <LockKeyhole size={14} /> Sign in
              </button>
            )
          ) : (
            <button
              type="button"
              className="gateway-header-btn"
              style={{ background: "#4f46e5", color: "#fff", borderColor: "#4338ca" }}
              onClick={() => setShowAuthProfilesModal(true)}
              title="View logins, credentials and test profiles"
              id="btn-profile-logins-modal"
            >
              <LockKeyhole size={14} /> Profile Logins
            </button>
          )}
        </div>
      </header>

      {/* Main 4 Enterprise Account Types + Staff Scanner Navigation Bar */}
      <nav className="tabs" aria-label="Application perspective">
        <button
          type="button"
          className={`tab ${activeTab === "guest" ? "active" : ""}`}
          onClick={() => setActiveTab("guest")}
          id="nav-tab-attendee"
        >
          <CreditCard size={16} /> 1. Attendee (Discover, Order & Tickets)
        </button>

        {canViewTab.organizer && (
          <button
            type="button"
            className={`tab ${activeTab === "organizer" ? "active" : ""}`}
            onClick={() => setActiveTab("organizer")}
            id="nav-tab-host"
          >
            <ChefHat size={16} /> 2. Event Host (Studio, Menu, Orders & Cash)
            {pendingVerificationCount > 0 && (
              <span className="tab-count">{pendingVerificationCount}</span>
            )}
          </button>
        )}

        {canViewTab.admin && (
          <button
            type="button"
            className={`tab ${activeTab === "admin" ? "active" : ""}`}
            onClick={() => setActiveTab("admin")}
            id="nav-tab-delegated-admin"
          >
            <Shield size={16} /> 3. Administrator Desk (Disputes & Oversight)
            {ticketDisputes.filter((d) => d.status === "open").length > 0 && (
              <span className="tab-count alert">
                {ticketDisputes.filter((d) => d.status === "open").length}
              </span>
            )}
          </button>
        )}

        {canViewTab.system_admin && (
          <button
            type="button"
            className={`tab ${activeTab === "system_admin" ? "active" : ""}`}
            onClick={() => setActiveTab("system_admin")}
            id="nav-tab-system-admin"
          >
            <ShieldCheck size={16} /> 4. System Administrator (Platform & Health)
          </button>
        )}

        {canViewTab.staff && (
          <button
            type="button"
            className={`tab staff-scanner-tab ${activeTab === "staff" ? "active" : ""}`}
            onClick={() => setActiveTab("staff")}
            id="nav-tab-staff-scanner"
          >
            <Scan size={16} /> 5. Event Day Staff Scanner
          </button>
        )}

        <span className="tabs-note">
          <LockKeyhole size={13} /> Strict manual verification protocol enforced
        </span>
      </nav>

      {apiStatus === "offline" && (
        <div className="storage-alert" role="status">
          <Info size={16} /> Backend not reachable: showing bundled demo data. Orders and sign-ins stay in this
          browser. Start the Laravel server (php artisan serve) to use live data.
        </div>
      )}

      {isApiOnline && sessionUser && (hasOperableOrders || sessionRoles.some((role) => role !== "attendee")) && (
        <div className="storage-alert" role="note" style={{ background: "#eef2ff", borderColor: "#c7d2fe", color: "#3730a3" }}>
          <Info size={16} /> Live: ordering, payment verification, cash ledger, refunds, kitchen status, scanner
          handovers, Event Host applications and disputes. Still demo data in this browser: menu and event editing, SMS
          notifications, and the administrator dashboards' stats, users, fees and health panels.
        </div>
      )}

      {storageError && (
        <div className="storage-alert" role="alert">
          <Info size={16} /> {storageError}
        </div>
      )}

      {/* Active Perspective Render */}
      {activeTab === "guest" && isLiveSession && !hasSessionRole("event_host") && (
        <HostApplicationCard defaultPhone={sessionUser?.phone} onNotify={showToast} />
      )}

      {activeTab === "guest" && (
        <GuestMenuCheckout
          eventDetails={catalogEvents[selectedEventIndex] || eventDetails}
          menu={catalogEvents[selectedEventIndex]?.menu || menu}
          settings={settings}
          orders={orders}
          notificationLogs={notificationLogs || []}
          availableMethods={availableMethods}
          isOwnerPreview={activeRolePerspective === "event_owner" || activeRolePerspective === "platform_admin"}
          onPlaceOrder={handlePlaceOrder}
          onSendCustomNotification={handleSendCustomNotification}
          onOpenSettings={() => setActiveTab("organizer")}
          formatMoney={formatMoney}
          attendeeProfile={
            isApiOnline && sessionUser
              ? {
                  ...attendeeProfile,
                  name: sessionUser.name,
                  email: sessionUser.email,
                  phone: sessionUser.phone ?? attendeeProfile.phone,
                  avatarUrl: sessionUser.avatar_url ?? attendeeProfile.avatarUrl,
                  bio: sessionUser.bio ?? attendeeProfile.bio,
                  membershipStatus: sessionUser.membership_status ?? attendeeProfile.membershipStatus,
                  memberSince: sessionUser.member_since ?? attendeeProfile.memberSince,
                  isGuest: false,
                }
              : attendeeProfile
          }
          onSubmitDispute={handleSubmitDispute}
          allEvents={catalogEvents}
          markets={locationCatalog?.markets || []}
          states={locationCatalog?.states || []}
          selectedEventIndex={selectedEventIndex}
          onSelectEvent={(idx) => {
            setSelectedEventIndex(idx);
            showToast(`Selected: ${catalogEvents[idx].title}`);
          }}
          isLoggedIn={isApiOnline ? Boolean(sessionUser) : isGuestAuthenticated}
          signInLabel={isApiOnline ? "Sign in to order" : undefined}
          onLogin={() => {
            if (isApiOnline) {
              setShowSignInModal(true);
              return;
            }
            setIsGuestAuthenticated(true);
            showToast("Signed in as Attendee! You can now place food orders.");
          }}
          onLogout={() => {
            if (isApiOnline) {
              void handleSignOut();
              return;
            }
            setIsGuestAuthenticated(false);
            showToast("Switched to Public Visitor mode (Viewing only).");
          }}
        />
      )}

      {activeTab === "organizer" && (
        <OrganizerDashboard
          eventDetails={isLiveSession && hostLiveEvent ? hostLiveEvent : eventDetails}
          menu={isLiveSession && hostLiveEvent ? hostLiveEvent.menu || [] : menu}
          settings={settings}
          orders={operableOrders}
          cashLedger={cashLedger || []}
          notificationLogs={notificationLogs || []}
          organizers={organizers}
          activeOrganizerId={activeOrganizerId}
          activeRolePerspective={activeRolePerspective}
          tenantUsers={tenantUsers}
          sessionUser={sessionUser}
          hostEvents={hostEvents}
          catalogEvents={catalogEvents}
          handoverAuditLogs={handoverAuditLogs}
          isApiOnline={isApiOnline}
          onSelectHostEvent={(ev) => {
            if (isLiveSession && "menu" in ev) {
              setHostEvents((curr) => [ev, ...curr.filter((e) => e.id !== ev.id)]);
            } else {
              setSavedState((c) => ({
                ...c,
                eventDetails: ev,
                menu: "menu" in ev && Array.isArray(ev.menu) ? ev.menu : c.menu,
              }));
            }
          }}
          onSignOut={() => {
            void handleSignOut();
          }}
          onSwitchOrganizer={(orgId) => setSavedState((c) => ({ ...c, activeOrganizerId: orgId }))}
          onSwitchRolePerspective={(role) => setSavedState((c) => ({ ...c, activeRolePerspective: role }))}
          onUpdateEventDetails={(details) =>
            isLiveSession && hostLiveEvent
              ? liveUpdateEventDetails(details)
              : setSavedState((c) => ({ ...c, eventDetails: details }))
          }
          onUpdateMenu={(newMenu) =>
            isLiveSession && hostLiveEvent ? liveUpdateMenu(newMenu) : setSavedState((c) => ({ ...c, menu: newMenu }))
          }
          onUpdateSettings={(updater) =>
            setSavedState((c) => ({ ...c, settings: typeof updater === "function" ? updater(c.settings) : updater }))
          }
          onUpdatePaymentStatus={handleUpdatePaymentStatus}
          onUpdateFulfillmentStatus={handleUpdateFulfillmentStatus}
          onRecordCashPayment={handleRecordCashPayment}
          onReverseCashPayment={handleReverseCashPayment}
          onRetryNotification={handleRetryNotification}
          onSendCustomNotification={handleSendCustomNotification}
          onBroadcastEventReminder={handleBroadcastEventReminder}
          onResetDemo={handleResetDemo}
          onOpenGatewayModal={() => setShowGatewayModal(true)}
          formatMoney={formatMoney}
        />
      )}

      {(activeTab === "admin" || activeTab === "system_admin") && isLiveAdmin && (
        <HostApplicationsReview onNotify={showToast} />
      )}

      {activeTab === "admin" && (
        <DelegatedAdminDashboard
          currentScope={adminScopes[0] || DEFAULT_ADMIN_SCOPES[0]}
          events={[eventDetails]}
          disputes={ticketDisputes}
          orders={orders}
          onResolveDispute={handleResolveDispute}
          onAssistHostRecovery={handleAssistHostRecovery}
          formatMoney={formatMoney}
        />
      )}

      {activeTab === "system_admin" && (
        <SystemAdminDashboard
          stats={platformOverviewStats}
          health={systemHealthComponents}
          providers={notificationProviders}
          feeSettings={platformFeeSettings}
          auditLogs={securityAuditLogs}
          adminScopes={adminScopes}
          users={tenantUsers}
          onUpdateFeeSettings={handleUpdateFeeSettings}
          onToggleUserStatus={handleToggleUserStatus}
          onAssignAdminScope={handleAssignAdminScope}
          formatMoney={formatMoney}
        />
      )}

      {activeTab === "staff" && (
        <StaffScanner
          orders={operableOrders}
          handoverAuditLogs={handoverAuditLogs || []}
          onUpdateItemHandover={handleUpdateItemHandover}
          onCloseOrder={handleCloseOrder}
          onUpdatePaymentStatus={handleUpdatePaymentStatus}
          onRecordCashPayment={handleRecordCashPayment}
          formatMoney={formatMoney}
        />
      )}

      {showSignInModal && (
        <SignInModal
          onClose={() => setShowSignInModal(false)}
          onSignedIn={(user) => {
            setSessionUser(user);
            setShowSignInModal(false);
            showToast(`Signed in as ${user.name}.`);
          }}
        />
      )}

      {/* Gateway Integration Roadmap Modal */}
      {showGatewayModal && (
        <div className="modal-backdrop" onClick={() => setShowGatewayModal(false)}>
          <div
            className="modal-card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="gateway-title"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="modal-header">
              <div>
                <span className="section-kicker">AUTOMATION ROADMAP</span>
                <h2 id="gateway-title">U.S. Event Payment Providers</h2>
                <p>
                  Transitioning from manual transfer verification to automated checkout with instant
                  ticket issuance.
                </p>
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setShowGatewayModal(false)}
                aria-label="Close dialog"
              >
                <X size={18} />
              </button>
            </div>

            <div className="modal-body">
              <div className="strategy-callout">
                <Globe size={18} />
                <div>
                  <strong>Why manual transfers first?</strong>
                  <p>
                    U.S. payment providers have different onboarding, verification, and settlement
                    requirements. Manual transfers still require host verification; a transfer
                    reference never confirms payment automatically. A vetted processor can automate
                    settlement once the host completes its onboarding.
                  </p>
                </div>
              </div>

              <h3>Recommended Gateways for U.S. Registered Businesses</h3>

              <div className="gateway-cards-grid">
                {GATEWAY_RECOMMENDATIONS.US.map((gw) => (
                  <div className="gateway-card" key={gw.name}>
                    <div className="gateway-card-top">
                      <strong>{gw.name}</strong>
                      <span className="gateway-badge">{gw.badge}</span>
                    </div>
                    <p className="gateway-desc">{gw.description}</p>
                    <div className="gateway-details">
                      <div>
                        <span>Currencies:</span> <strong>{gw.currencies}</strong>
                      </div>
                      <div>
                        <span>Methods:</span> <strong>{gw.methods}</strong>
                      </div>
                      <div>
                        <span>Coverage:</span> <strong>{gw.coverage}</strong>
                      </div>
                    </div>
                    <div className="gateway-bestfor">
                      <span>Best for:</span> {gw.bestFor}
                    </div>
                  </div>
                ))}
              </div>

              <div className="architecture-box">
                <h4>
                  <LockKeyhole size={15} /> Production Integration Architecture (Phase 2):
                </h4>
                <ol>
                  <li>
                    <strong>Hosted Checkout / SDK:</strong> Replace the manual transfer form with
                    the provider's hosted checkout redirect or client SDK.
                  </li>
                  <li>
                    <strong>Webhook Listener:</strong> Implement an authenticated webhook endpoint
                    (e.g., <code>/api/webhooks/payment</code>) validating provider cryptographic
                    signatures.
                  </li>
                  <li>
                    <strong>State Invariant:</strong> Tickets transition from{" "}
                    <code>pending_payment</code> to <code>paid</code> ONLY upon receipt of a verified{" "}
                    <code>charge.successful</code> webhook event.
                  </li>
                </ol>
              </div>
            </div>

            <div className="modal-footer">
              <button
                type="button"
                className="primary-button modal-done-btn"
                onClick={() => setShowGatewayModal(false)}
              >
                Close Integration Guide
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Role-Based Authentication & Profiles Modal */}
      {showAuthProfilesModal && (
        <div className="modal-backdrop" onClick={() => setShowAuthProfilesModal(false)}>
          <div
            className="modal-card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="profiles-title"
            style={{ maxWidth: "780px" }}
            onClick={(e) => e.stopPropagation()}
          >
            <div className="modal-header">
              <div>
                <span className="section-kicker">ENTERPRISE ROLE-BASED ACCESS CONTROL</span>
                <h2 id="profiles-title">Platform Logins & Role Profiles</h2>
                <p>
                  Explore the 4 primary platform roles and event-scoped staff assignments with live credentials, permitted capabilities, and direct perspective switching.
                </p>
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setShowAuthProfilesModal(false)}
                aria-label="Close dialog"
              >
                <X size={18} />
              </button>
            </div>

            <div className="modal-body" style={{ maxHeight: "70vh", overflowY: "auto" }}>
              <div style={{ display: "grid", gridTemplateColumns: "1fr", gap: "14px" }}>
                
                {/* 1. System Administrator */}
                <div style={{ border: "1px solid #e9d5ff", background: "#faf5ff", borderRadius: "12px", padding: "16px" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "8px" }}>
                    <div>
                      <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                        <span style={{ fontWeight: 700, fontSize: "15px", color: "#581c87" }}>1. System Administrator</span>
                        <span style={{ fontSize: "11px", fontWeight: 700, background: "#f3e8ff", color: "#7e22ce", padding: "2px 8px", borderRadius: "9999px" }}>Platform Root</span>
                      </div>
                      <div style={{ fontSize: "12px", color: "#6b21a8", marginTop: "3px" }}>
                        <strong>Email:</strong> <code>superadmin@islandevents.example</code> &nbsp;|&nbsp; <strong>Password:</strong> local demo password (see <code>backend/database/seeders/DemoAccountsSeeder.php</code>)
                      </div>
                    </div>
                    <button
                      type="button"
                      className="primary-button"
                      style={{ fontSize: "12px", padding: "6px 12px", background: "#7e22ce", borderColor: "#6b21a8" }}
                      onClick={() => {
                        setActiveTab("system_admin");
                        setShowAuthProfilesModal(false);
                        showToast("Switched to System Administrator Console");
                      }}
                    >
                      Switch to Profile
                    </button>
                  </div>
                  <div style={{ fontSize: "12px", color: "#475569", lineHeight: "1.5" }}>
                    <strong>Destination:</strong> <code>/dashboard/system-admin</code><br />
                    <strong>Permissions:</strong> Platform telemetry, global fee take rates, storage health, immutable security audit logs, account suspension, and root provisioning.
                  </div>
                </div>

                {/* 2. Administrator (Delegated Operations) */}
                <div style={{ border: "1px solid #bfdbfe", background: "#eff6ff", borderRadius: "12px", padding: "16px" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "8px" }}>
                    <div>
                      <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                        <span style={{ fontWeight: 700, fontSize: "15px", color: "#1e3a8a" }}>2. Administrator (Operations Desk)</span>
                        <span style={{ fontSize: "11px", fontWeight: 700, background: "#dbeafe", color: "#1d4ed8", padding: "2px 8px", borderRadius: "9999px" }}>Delegated Ops</span>
                      </div>
                      <div style={{ fontSize: "12px", color: "#1d4ed8", marginTop: "3px" }}>
                        <strong>Email:</strong> <code>admin@islandevents.example</code> &nbsp;|&nbsp; <strong>Password:</strong> local demo password (see <code>backend/database/seeders/DemoAccountsSeeder.php</code>)
                      </div>
                    </div>
                    <button
                      type="button"
                      className="primary-button"
                      style={{ fontSize: "12px", padding: "6px 12px", background: "#2563eb", borderColor: "#1d4ed8" }}
                      onClick={() => {
                        setActiveTab("admin");
                        setShowAuthProfilesModal(false);
                        showToast("Switched to Administrator Operations Desk");
                      }}
                    >
                      Switch to Profile
                    </button>
                  </div>
                  <div style={{ fontSize: "12px", color: "#475569", lineHeight: "1.5" }}>
                    <strong>Destination:</strong> <code>/dashboard/admin</code><br />
                    <strong>Permissions:</strong> Ticket dispute investigation, customer refunds, host application approval, event oversight, and organizer account recovery assistance.
                  </div>
                </div>

                {/* 3. Event Host */}
                <div style={{ border: "1px solid #fde68a", background: "#fffbeb", borderRadius: "12px", padding: "16px" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "8px" }}>
                    <div>
                      <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                        <span style={{ fontWeight: 700, fontSize: "15px", color: "#78350f" }}>3. Event Host (Organizer)</span>
                        <span style={{ fontSize: "11px", fontWeight: 700, background: "#fef3c7", color: "#b45309", padding: "2px 8px", borderRadius: "9999px" }}>Event Owner</span>
                      </div>
                      <div style={{ fontSize: "12px", color: "#b45309", marginTop: "3px" }}>
                        <strong>Email:</strong> <code>host@islandevents.example</code> &nbsp;|&nbsp; <strong>Password:</strong> local demo password (see <code>backend/database/seeders/DemoAccountsSeeder.php</code>)
                      </div>
                    </div>
                    <button
                      type="button"
                      className="primary-button"
                      style={{ fontSize: "12px", padding: "6px 12px", background: "#d97706", borderColor: "#b45309" }}
                      onClick={() => {
                        setActiveTab("organizer");
                        setShowAuthProfilesModal(false);
                        showToast("Switched to Event Host Command Center");
                      }}
                    >
                      Switch to Profile
                    </button>
                  </div>
                  <div style={{ fontSize: "12px", color: "#475569", lineHeight: "1.5" }}>
                    <strong>Destination:</strong> <code>/dashboard/host</code><br />
                    <strong>Permissions:</strong> Create & publish events, flyer uploads, Caribbean menu builder, advance food prep forecasting, manual transfer approval, cash ledger, and worker staff invitations.
                  </div>
                </div>

                {/* 4. Attendee */}
                <div style={{ border: "1px solid #a7f3d0", background: "#ecfdf5", borderRadius: "12px", padding: "16px" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "8px" }}>
                    <div>
                      <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                        <span style={{ fontWeight: 700, fontSize: "15px", color: "#064e3b" }}>4. Attendee (Guest & Ticket Buyer)</span>
                        <span style={{ fontSize: "11px", fontWeight: 700, background: "#d1fae5", color: "#047857", padding: "2px 8px", borderRadius: "9999px" }}>Default Safe Role</span>
                      </div>
                      <div style={{ fontSize: "12px", color: "#047857", marginTop: "3px" }}>
                        <strong>Email:</strong> <code>attendee@islandevents.example</code> &nbsp;|&nbsp; <strong>Password:</strong> local demo password (see <code>backend/database/seeders/DemoAccountsSeeder.php</code>)
                      </div>
                    </div>
                    <button
                      type="button"
                      className="primary-button"
                      style={{ fontSize: "12px", padding: "6px 12px", background: "#059669", borderColor: "#047857" }}
                      onClick={() => {
                        setActiveTab("guest");
                        setShowAuthProfilesModal(false);
                        showToast("Switched to Attendee Discovery & Wallet");
                      }}
                    >
                      Switch to Profile
                    </button>
                  </div>
                  <div style={{ fontSize: "12px", color: "#475569", lineHeight: "1.5" }}>
                    <strong>Destination:</strong> <code>/dashboard/attendee</code><br />
                    <strong>Permissions:</strong> Discover public U.S. events across cuisines and communities, pre-order food, view tickets and QR passes, review order history, and submit dispute claims.
                  </div>
                </div>

                {/* 5. Event-Scoped Worker Assignment */}
                <div style={{ border: "1px solid #cbd5e1", background: "#f8fafc", borderRadius: "12px", padding: "16px" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "8px" }}>
                    <div>
                      <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                        <span style={{ fontWeight: 700, fontSize: "15px", color: "#1e293b" }}>5. Event-Scoped Worker (Staff Scanner)</span>
                        <span style={{ fontSize: "11px", fontWeight: 700, background: "#e2e8f0", color: "#475569", padding: "2px 8px", borderRadius: "9999px" }}>Shift-Bound</span>
                      </div>
                      <div style={{ fontSize: "12px", color: "#475569", marginTop: "3px" }}>
                        <strong>Authentication:</strong> One-time Staff Claim Code (<code>STF-XXXX-XXXX</code>) or Signed Invitation Token
                      </div>
                    </div>
                    <button
                      type="button"
                      className="primary-button"
                      style={{ fontSize: "12px", padding: "6px 12px", background: "#334155", borderColor: "#1e293b" }}
                      onClick={() => {
                        setActiveTab("staff");
                        setShowAuthProfilesModal(false);
                        showToast("Switched to Event Day Staff Scanner");
                      }}
                    >
                      Switch to Profile
                    </button>
                  </div>
                  <div style={{ fontSize: "12px", color: "#475569", lineHeight: "1.5" }}>
                    <strong>Destination:</strong> <code>/staff/terminal</code><br />
                    <strong>Permissions:</strong> High-speed QR ticket scanning, live food station pickup handover, and cash payment collection. Strictly bound to assigned event and shift window.
                  </div>
                </div>

              </div>
            </div>

            <div className="modal-footer" style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
              <span style={{ fontSize: "12px", color: "#64748b" }}>
                Laravel Production Login Route: <code>/login</code>
              </span>
              <button
                type="button"
                className="primary-button modal-done-btn"
                onClick={() => setShowAuthProfilesModal(false)}
              >
                Close Profiles
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Global Page Footer */}
      <footer className="page-footer">
        <span>
          ISLAND EVENTS TICKETING & KITCHEN PLATFORM <span className="footer-separator">·</span>{" "}
          KINGSTON TO THE WORLD
        </span>
        <span>
          <Globe size={13} /> Dual Currency · Kitchen Prep Automation · Digital QR Redemptions
        </span>
      </footer>

      {toastMessage && (
        <div className="toast" role="status">
          <Sparkles size={16} /> {toastMessage}
        </div>
      )}
    </main>
  );
}

export default App;
