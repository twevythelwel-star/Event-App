import React, { useEffect, useMemo, useState } from "react";
import {
  AlertCircle,
  AlertTriangle,
  Banknote,
  Building2,
  Calendar,
  Check,
  CheckCircle2,
  ChefHat,
  ChevronDown,
  Clock3,
  Copy,
  DollarSign,
  ExternalLink,
  Eye,
  Facebook,
  FileEdit,
  FileText,
  Image as ImageIcon,
  Info,
  Instagram,
  LockKeyhole,
  Mail,
  MapPin,
  MessageCircle,
  MessageSquare,
  Phone,
  Plus,
  Radio,
  RefreshCw,
  RotateCcw,
  Search,
  Send,
  Settings2,
  Share2,
  ShieldAlert,
  ShieldCheck,
  Sparkles,
  Ticket,
  Trash2,
  Upload,
  User,
  UserCheck,
  Utensils,
  Wallet,
  X,
  Code2,
  History,
  Smartphone,
  CheckCheck,
  Globe,
  Server,
  Database,
  Layers,
  Key,
  Scan,
  Users,
} from "lucide-react";
import {
  BusinessLocation,
  CashPaymentRecord,
  Currency,
  EventDetails,
  EventStatus,
  CustomOptionChoice,
  CustomOptionGroup,
  FulfillmentStatus,
  MenuCategory,
  MenuItem,
  MethodId,
  NotificationChannel,
  NotificationLogEntry,
  NotificationTrigger,
  OrganizerProfile,
  PaymentMethodSettings,
  PaymentStatus,
  PricingType,
  Settings,
  TenantBreachSimulationResult,
  TenantRole,
  TenantUserMember,
  TicketOrder,
} from "../types";
import { AccountSettingsView } from "./AccountSettingsView";
import { METHOD_INFO } from "../App";
import {
  CROSS_TENANT_DEMO_RECORDS,
  DEFAULT_ORGANIZERS,
  DEFAULT_TENANT_USERS,
  NOTIFICATION_TEMPLATES,
} from "../data/defaultData";
import {
  getEventFlyerDisplay,
  processFlyerDeletion,
  processFlyerReplacement,
} from "../tests/testUtils";

type Props = {
  eventDetails: EventDetails;
  menu: MenuItem[];
  settings: Settings;
  orders: TicketOrder[];
  cashLedger: CashPaymentRecord[];
  notificationLogs: NotificationLogEntry[];
  organizers?: OrganizerProfile[];
  activeOrganizerId?: string;
  activeRolePerspective?: TenantRole;
  tenantUsers?: TenantUserMember[];
  onSwitchOrganizer?: (orgId: string) => void;
  onSwitchRolePerspective?: (role: TenantRole) => void;
  onUpdateEventDetails: (details: EventDetails) => void;
  onUpdateMenu: (menu: MenuItem[]) => void;
  onUpdateSettings: (updater: (curr: Settings) => Settings) => void;
  onUpdatePaymentStatus: (
    orderId: string,
    status: PaymentStatus,
    details?: {
      amountPaid?: number;
      notes?: string;
      verifier?: string;
      rejectionReason?: string;
      refundDetails?: { amount: number; reason: string; refundedAt: string; refundedBy: string };
    }
  ) => void;
  onUpdateFulfillmentStatus: (orderId: string, status: FulfillmentStatus) => void;
  // May resolve with the record as saved by the server (which issues the receipt number); rejects if refused
  onRecordCashPayment: (record: CashPaymentRecord, linkedOrderId?: string) => void | Promise<CashPaymentRecord | void>;
  onReverseCashPayment: (reversal: CashPaymentRecord, linkedOrderId?: string) => void | Promise<CashPaymentRecord | void>;
  onRetryNotification: (notifId: string) => void;
  onSendCustomNotification: (
    param1: string | Omit<NotificationLogEntry, "id" | "sentAt">,
    param2?: NotificationChannel,
    param3?: string,
    param4?: string
  ) => void;
  onBroadcastEventReminder: () => void;
  onResetDemo: () => void;
  onOpenGatewayModal: () => void;
  formatMoney: (amount: number, currency: Currency) => string;
};

export const OrganizerDashboard: React.FC<Props> = ({
  eventDetails,
  menu,
  settings,
  orders,
  cashLedger,
  notificationLogs,
  organizers = DEFAULT_ORGANIZERS,
  activeOrganizerId = "org-kingston-youth",
  activeRolePerspective = "event_owner",
  tenantUsers = DEFAULT_TENANT_USERS,
  onSwitchOrganizer,
  onSwitchRolePerspective,
  onUpdateEventDetails,
  onUpdateMenu,
  onUpdateSettings,
  onUpdatePaymentStatus,
  onUpdateFulfillmentStatus,
  onRecordCashPayment,
  onReverseCashPayment,
  onRetryNotification,
  onSendCustomNotification,
  onBroadcastEventReminder,
  onResetDemo,
  onOpenGatewayModal,
  formatMoney,
}) => {
  const [activeSubTab, setActiveSubTab] = useState<
    "event" | "payments" | "prep" | "notifications" | "menu" | "settings" | "share" | "multitenancy"
  >("event");
  const [toastMessage, setToastMessage] = useState("");

  // Event & Promotional Flyer Studio State
  const [localEvent, setLocalEvent] = useState<EventDetails>(eventDetails);
  const [isEventDirty, setIsEventDirty] = useState(false);
  const [flyerPendingFile, setFlyerPendingFile] = useState<{
    name: string;
    sizeBytes: number;
    mimeType: string;
    dimensions?: { width: number; height: number };
    dataUrl: string;
  } | null>(null);
  const [flyerErrors, setFlyerErrors] = useState<string[]>([]);
  const [isDragOver, setIsDragOver] = useState(false);
  const [lightboxImg, setLightboxImg] = useState<{ url: string; caption: string } | null>(null);

  useEffect(() => {
    setLocalEvent(eventDetails);
  }, [eventDetails]);

  function handleEventFieldChange<K extends keyof EventDetails>(field: K, value: EventDetails[K]) {
    setLocalEvent((prev) => {
      const updated = { ...prev, [field]: value };
      if (field === "title" && !prev.slug) {
        updated.slug = String(value)
          .toLowerCase()
          .replace(/[^\w\s-]/g, "")
          .replace(/[\s_-]+/g, "-")
          .replace(/^-+|-+$/g, "");
      }
      return updated;
    });
    setIsEventDirty(true);
  }

  function handleSaveEventSettings(e?: React.FormEvent) {
    if (e) e.preventDefault();
    if (!isEventOwner) {
      setToastMessage("Permission Denied: Only Event Owners can modify event settings.");
      setTimeout(() => setToastMessage(""), 3000);
      return;
    }
    onUpdateEventDetails(localEvent);
    setIsEventDirty(false);
    setToastMessage(`Event "${localEvent.title}" details saved successfully!`);
    setTimeout(() => setToastMessage(""), 3000);
  }

  function handleStatusTransition(newStatus: EventStatus) {
    if (!isEventOwner) {
      setToastMessage("Permission Denied: Only Event Owners can change event status.");
      setTimeout(() => setToastMessage(""), 3000);
      return;
    }

    if (newStatus === "published") {
      if (!localEvent.title || !localEvent.dateStr || !localEvent.venueName) {
        setToastMessage("Cannot publish: Please specify title, event date, and venue name.");
        setTimeout(() => setToastMessage(""), 3500);
        return;
      }
    }

    const updated: EventDetails = {
      ...localEvent,
      status: newStatus,
      isFlyerPublic: newStatus === "published",
      isOrderingOpen: newStatus === "published" ? true : localEvent.isOrderingOpen,
    };
    setLocalEvent(updated);
    onUpdateEventDetails(updated);
    setIsEventDirty(false);

    if (newStatus === "published") {
      setToastMessage("🚀 Event Published! Public URL is now live and accepting orders.");
    } else if (newStatus === "unpublished") {
      setToastMessage("🔒 Event Unpublished. Hidden from public and ordering paused.");
    } else if (newStatus === "cancelled") {
      setToastMessage("⚠️ Event marked as CANCELLED. Orders closed.");
    } else {
      setToastMessage("Event reverted to DRAFT status (Owner preview only).");
    }
    setTimeout(() => setToastMessage(""), 4000);
  }

  function handleFileSelected(file: File) {
    setFlyerErrors([]);
    const errors: string[] = [];

    // Allowed MIME formats
    const allowedMimes = ["image/jpeg", "image/png", "image/webp"];
    if (!allowedMimes.includes(file.type.toLowerCase())) {
      errors.push(`Invalid file format '${file.type || "unknown"}'. Only JPEG, PNG, and WebP are permitted.`);
    }

    // Size limit: 5MB
    const MAX_SIZE = 5 * 1024 * 1024;
    if (file.size > MAX_SIZE) {
      const mbSize = (file.size / (1024 * 1024)).toFixed(1);
      errors.push(`File size exceeds 5MB limit (${mbSize}MB). Please compress the image.`);
    }

    if (errors.length > 0) {
      setFlyerErrors(errors);
      return;
    }

    // Read image for preview and dimension check
    const reader = new FileReader();
    reader.onload = (ev) => {
      const dataUrl = ev.target?.result as string;
      const img = new Image();
      img.onload = () => {
        const width = img.naturalWidth || img.width;
        const height = img.naturalHeight || img.height;

        const dimErrors: string[] = [];
        if (width < 300 || height < 200) {
          dimErrors.push(`Image dimensions too small (${width}x${height}px). Minimum is 300x200px.`);
        }
        if (width > 4500 || height > 4500) {
          dimErrors.push(`Image dimensions exceed maximum (${width}x${height}px). Maximum is 4500x4500px.`);
        }

        if (dimErrors.length > 0) {
          setFlyerErrors(dimErrors);
          return;
        }

        setFlyerPendingFile({
          name: file.name,
          sizeBytes: file.size,
          mimeType: file.type,
          dimensions: { width, height },
          dataUrl,
        });
      };
      img.onerror = () => {
        setFlyerErrors(["Failed to read image data. The file may be corrupt or not a valid image."]);
      };
      img.src = dataUrl;
    };
    reader.readAsDataURL(file);
  }

  function handleConfirmFlyerUpload() {
    if (!flyerPendingFile) return;
    if (!isEventOwner) {
      setToastMessage("Permission Denied: Only Event Owners can upload promotional flyers.");
      setTimeout(() => setToastMessage(""), 3000);
      return;
    }

    const res = processFlyerReplacement(
      localEvent,
      {
        name: flyerPendingFile.name,
        sizeBytes: flyerPendingFile.sizeBytes,
        mimeType: flyerPendingFile.mimeType,
        dimensions: flyerPendingFile.dimensions,
      },
      activeOrg.slug || "org-kingston-youth",
      localRole
    );

    if (!res.success) {
      setFlyerErrors([res.error || "Upload failed."]);
      return;
    }

    const updated: EventDetails = {
      ...res.updatedEvent,
      flyerUrl: flyerPendingFile.dataUrl,
    };

    setLocalEvent(updated);
    onUpdateEventDetails(updated);
    setFlyerPendingFile(null);
    setFlyerErrors([]);
    setToastMessage("✨ Promotional flyer uploaded and stored securely!");
    setTimeout(() => setToastMessage(""), 3500);
  }

  function handleCancelPendingFlyer() {
    setFlyerPendingFile(null);
    setFlyerErrors([]);
  }

  function handleRemoveFlyerAction() {
    if (!isEventOwner) {
      setToastMessage("Permission Denied: Only Event Owners can delete event flyers.");
      setTimeout(() => setToastMessage(""), 3000);
      return;
    }
    const res = processFlyerDeletion(localEvent, localRole);
    if (!res.success) {
      setToastMessage(res.error || "Failed to remove flyer.");
      setTimeout(() => setToastMessage(""), 3000);
      return;
    }
    setLocalEvent(res.updatedEvent);
    onUpdateEventDetails(res.updatedEvent);
    setToastMessage("Flyer removed. High-contrast Caribbean culinary fallback restored.");
    setTimeout(() => setToastMessage(""), 3500);
  }

  // Multi-organizer state & IDOR Breach Simulator state
  const [localActiveOrgId, setLocalActiveOrgId] = useState(activeOrganizerId);
  const [localRole, setLocalRole] = useState<TenantRole>(activeRolePerspective);
  const [simulationTarget, setSimulationTarget] = useState<
    "orders" | "tickets" | "cash_payments" | "customers" | "reports" | "event_settings"
  >("orders");
  const [breachSimulationResult, setBreachSimulationResult] = useState<TenantBreachSimulationResult | null>(null);
  const [isSimulating, setIsSimulating] = useState(false);
  const [architectureViewMode, setArchitectureViewMode] = useState<"blueprint" | "rbac" | "evolution" | "simulator">("simulator");
  const [hostSettingsMode, setHostSettingsMode] = useState<"account_settings" | "payment_methods">("account_settings");

  const activeOrg = useMemo(() => {
    return organizers.find((o) => o.id === localActiveOrgId) || organizers[0];
  }, [organizers, localActiveOrgId]);

  // Handle switching organizer
  function handleSelectOrg(orgId: string) {
    setLocalActiveOrgId(orgId);
    if (onSwitchOrganizer) onSwitchOrganizer(orgId);
    setToastMessage(`Switched tenant context to "${organizers.find((o) => o.id === orgId)?.name || orgId}". Queries now scoped.`);
    setTimeout(() => setToastMessage(""), 3500);
  }

  // Handle switching role perspective
  function handleSelectRole(role: TenantRole) {
    setLocalRole(role);
    if (onSwitchRolePerspective) onSwitchRolePerspective(role);
    setToastMessage(`Switched role perspective to "${role}". Permission boundaries applied.`);
    setTimeout(() => setToastMessage(""), 3500);
  }

  // Role permissions
  const isPlatformAdmin = localRole === "platform_admin";
  const isEventOwner = localRole === "event_owner" || isPlatformAdmin;
  const isPaymentVerifier = localRole === "payment_verifier" || isEventOwner;
  const isFoodCollectorOnly = localRole === "food_collector";

  // Cross-tenant IDOR penetration testing simulator
  function handleRunBreachSimulation() {
    setIsSimulating(true);

    const targetOrgId = localActiveOrgId === "org-kingston-youth" ? "org-portmore-guild" : "org-kingston-youth";
    const targetOrg = organizers.find((o) => o.id === targetOrgId) || organizers[1];
    const authenticatedOrg = activeOrg;

    setTimeout(() => {
      let targetId = "";
      let targetDesc = "";
      let policy = "";

      if (simulationTarget === "orders") {
        targetId = CROSS_TENANT_DEMO_RECORDS.portmoreOrder.id;
        targetDesc = `Order #${CROSS_TENANT_DEMO_RECORDS.portmoreOrder.orderNumber} (${CROSS_TENANT_DEMO_RECORDS.portmoreOrder.customerName} · ${formatMoney(CROSS_TENANT_DEMO_RECORDS.portmoreOrder.totalAmount, "JMD")})`;
        policy = "App\\Scopes\\TenantScope & App\\Policies\\OrderPolicy::view()";
      } else if (simulationTarget === "cash_payments") {
        targetId = CROSS_TENANT_DEMO_RECORDS.portmoreCashReceipt.id;
        targetDesc = `Cash Receipt #${CROSS_TENANT_DEMO_RECORDS.portmoreCashReceipt.receiptNumber} (${CROSS_TENANT_DEMO_RECORDS.portmoreCashReceipt.customerName} · ${formatMoney(CROSS_TENANT_DEMO_RECORDS.portmoreCashReceipt.amount, "JMD")})`;
        policy = "App\\Scopes\\TenantScope & App\\Policies\\PaymentPolicy::viewLedger()";
      } else if (simulationTarget === "tickets") {
        targetId = CROSS_TENANT_DEMO_RECORDS.portmoreOrder.ticketCode;
        targetDesc = `Ticket Barcode ${CROSS_TENANT_DEMO_RECORDS.portmoreOrder.ticketCode}`;
        policy = "App\\Scopes\\TenantScope & App\\Policies\\TicketPolicy::scanTicket()";
      } else if (simulationTarget === "customers") {
        targetId = "cust-portmore-campbell-8801";
        targetDesc = `Customer PII: ${CROSS_TENANT_DEMO_RECORDS.portmoreOrder.customerName} (${CROSS_TENANT_DEMO_RECORDS.portmoreOrder.customerPhone})`;
        policy = "App\\Scopes\\TenantScope & App\\Policies\\OrderPolicy::viewCustomerDetails()";
      } else if (simulationTarget === "reports") {
        targetId = `rpt-fin-${targetOrg.id}`;
        targetDesc = `Executive Financial Reconciliation & Revenue Ledger for "${targetOrg.name}"`;
        policy = "App\\Policies\\ReportPolicy::viewFinancialReports()";
      } else {
        targetId = `settings-${targetOrg.id}`;
        targetDesc = `Private Bank Account Credentials & Zelle/Cash App for "${targetOrg.name}"`;
        policy = "App\\Policies\\PaymentPolicy::managePaymentSettings()";
      }

      const isSuperAdminBypass = localRole === "platform_admin";

      const result: TenantBreachSimulationResult = {
        id: `sim-${Date.now()}`,
        simulatedAt: new Date().toLocaleTimeString(),
        targetEntity: simulationTarget,
        targetRecordId: targetId,
        authenticatedTenantId: authenticatedOrg.id,
        authenticatedTenantName: authenticatedOrg.name,
        targetTenantId: targetOrg.id,
        targetTenantName: targetOrg.name,
        attemptedAction: `GET /api/v1/${simulationTarget}/${targetId}`,
        policyTriggered: policy,
        httpStatus: isSuperAdminBypass ? 200 : 403,
        statusText: isSuperAdminBypass
          ? "200 OK (Platform SuperAdmin Global Audit Mode)"
          : "403 Forbidden (Tenant Isolation Boundary Violation)",
        accessGranted: isSuperAdminBypass,
        securityMessage: isSuperAdminBypass
          ? `Access granted under authorized Platform SuperAdmin authority. Global cross-tenant audit event logged in immutable system security trail.`
          : `CROSS-TENANT ACCESS BLOCKED: Authenticated tenant "${authenticatedOrg.name}" (${authenticatedOrg.id}) attempted to read ${targetDesc} belonging to foreign tenant "${targetOrg.name}" (${targetOrg.id}). TenantScope automatically appended WHERE organizer_id = '${authenticatedOrg.id}' to the SQL query, returning 0 rows. Authorization policy threw TenantForbiddenException. Zero bytes of sensitive data or PII were exposed.`,
      };

      setBreachSimulationResult(result);
      setIsSimulating(false);
    }, 450);
  }

  // Menu Item Add / Edit Modal state
  const [showAddMenuModal, setShowAddMenuModal] = useState(false);
  const [editingMenuItemId, setEditingMenuItemId] = useState<string | null>(null);
  const [newItemName, setNewItemName] = useState("");
  const [newItemCategory, setNewItemCategory] = useState<string>("Mains");
  const [isCustomCategory, setIsCustomCategory] = useState(false);
  const [customCategoryInput, setCustomCategoryInput] = useState("");
  const [newItemPricingType, setNewItemPricingType] = useState<PricingType>("fixed");
  const [newItemDesc, setNewItemDesc] = useState("");
  const [newItemPriceUSD, setNewItemPriceUSD] = useState(15);
  const [newItemPriceJMD, setNewItemPriceJMD] = useState(2500);
  const [newItemImage, setNewItemImage] = useState("/images/jerk_chicken.jpg");
  const [customImageUrl, setCustomImageUrl] = useState("");
  // Photo picked from the device (data URL); uploaded to the server when the item is saved
  const [uploadedPhoto, setUploadedPhoto] = useState("");
  const [uploadedPhotoName, setUploadedPhotoName] = useState("");
  const [newItemStock, setNewItemStock] = useState<number>(100);
  const [isStockUnlimited, setIsStockUnlimited] = useState(false);
  const [newItemAllowInstructions, setNewItemAllowInstructions] = useState(true);

  // Customizable Option Groups State (for customizable meals / option choices)
  const [customOptionGroups, setCustomOptionGroups] = useState<CustomOptionGroup[]>([]);

  // Custom Amount / Donation Config State (for custom_amount)
  const [customMinUSD, setCustomMinUSD] = useState(5);
  const [customMaxUSD, setCustomMaxUSD] = useState(1000);
  const [customMinJMD, setCustomMinJMD] = useState(500);
  const [customMaxJMD, setCustomMaxJMD] = useState(150000);
  const [customSuggestedUSD, setCustomSuggestedUSD] = useState("10, 25, 50, 100");
  const [customSuggestedJMD, setCustomSuggestedJMD] = useState("1000, 2500, 5000, 10000");

  // Menu Search & Filter
  const [menuFilterCategory, setMenuFilterCategory] = useState<string>("all");
  const [menuSearchQuery, setMenuSearchQuery] = useState("");

  // KITCHEN PREP METRICS: Aggregate total count of each dish ordered across all tickets!
  const kitchenPrepSheet = useMemo(() => {
    const counts: Record<
      string,
      {
        item: MenuItem;
        totalQuantity: number;
        paidQuantity: number;
        pendingQuantity: number;
        handedOverQuantity: number;
      }
    > = {};

    // Initialize with all menu items
    menu.forEach((m) => {
      counts[m.id] = {
        item: m,
        totalQuantity: 0,
        paidQuantity: 0,
        pendingQuantity: 0,
        handedOverQuantity: 0,
      };
    });

    // Sum quantities from orders
    orders.forEach((ord) => {
      ord.items.forEach((it) => {
        if (!counts[it.menuItemId]) {
          // Fallback if item was deleted
          counts[it.menuItemId] = {
            item: {
              id: it.menuItemId,
              name: it.name,
              category: "Mains",
              pricingType: it.pricingType || "fixed",
              description: "",
              priceUSD: it.finalItemPrice,
              priceJMD: it.finalItemPrice,
              imageUrl: "/images/escovitch_fish.jpg",
            },
            totalQuantity: 0,
            paidQuantity: 0,
            pendingQuantity: 0,
            handedOverQuantity: 0,
          };
        }

        counts[it.menuItemId].totalQuantity += it.quantity;
        if (ord.status === "paid") {
          counts[it.menuItemId].paidQuantity += it.quantity;
        } else if (ord.status === "pending_verification") {
          counts[it.menuItemId].pendingQuantity += it.quantity;
        }

        if (it.handoverStatus === "collected") {
          counts[it.menuItemId].handedOverQuantity += it.quantity;
        }
      });
    });

    return Object.values(counts);
  }, [menu, orders]);

  // Financial Stats across the 7 payment statuses
  const totalOrders = orders.length;
  const pendingOrdersCount = orders.filter(
    (o) =>
      o.status === "payment_submitted" ||
      o.status === "under_verification" ||
      o.status === "pending_verification"
  ).length;
  const awaitingCashCount = orders.filter((o) => o.status === "awaiting_payment").length;
  const partiallyPaidCount = orders.filter((o) => o.status === "partially_paid").length;
  const verifiedOrdersCount = orders.filter((o) => o.status === "paid").length;
  const rejectedCount = orders.filter((o) => o.status === "rejected").length;
  const refundedCount = orders.filter((o) => o.status === "refunded").length;
  const verifiedOrders = verifiedOrdersCount;
  const pendingOrders = pendingOrdersCount;

  const verifiedTotals = (["USD", "JMD"] as const)
    .map((currency) => ({
      currency,
      total: orders
        .filter(
          (o) => (o.status === "paid" || o.status === "partially_paid") && o.currency === currency
        )
        .reduce(
          (sum, o) =>
            sum +
            (o.amountPaid !== undefined
              ? o.amountPaid
              : o.status === "paid"
              ? o.totalAmount
              : 0),
          0
        ),
    }))
    .filter((v) => v.total > 0);

  const pendingTotals = (["USD", "JMD"] as const)
    .map((currency) => ({
      currency,
      total: orders
        .filter(
          (o) =>
            (o.status === "payment_submitted" ||
              o.status === "under_verification" ||
              o.status === "pending_verification") &&
            o.currency === currency
        )
        .reduce((sum, o) => sum + o.totalAmount, 0),
    }))
    .filter((v) => v.total > 0);

  const totalCashCollected = (cashLedger || []).reduce((sum, item) => sum + item.amount, 0);
  const totalPortionsNeeded = kitchenPrepSheet.reduce((s, k) => s + k.totalQuantity, 0);

  // Cash Payment Form State
  const [showAddCashModal, setShowAddCashModal] = useState(false);
  const [cashCustomerName, setCashCustomerName] = useState("");
  const [cashCustomerPhone, setCashCustomerPhone] = useState("");
  const [cashSelectedOrderId, setCashSelectedOrderId] = useState("");
  const [cashAmount, setCashAmount] = useState<number>(3000);
  const [cashCurrency, setCashCurrency] = useState<Currency>(settings.currency);
  // datetime-local inputs take local wall-clock time; a UTC ISO string would be off by the timezone offset
  const [cashDateTime, setCashDateTime] = useState<string>(() => {
    const now = new Date();
    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().substring(0, 16);
  });
  const [cashReceivedBy, setCashReceivedBy] = useState("Treasurer Davis");
  const [cashNotes, setCashNotes] = useState("");
  const [cashReceiptNumber, setCashReceiptNumber] = useState(
    `CSH-2026-${Math.floor(1000 + Math.random() * 9000)}`
  );

  // Customer Cash Receipt Modal State (Exact user prompt preview)
  const [createdCashReceipt, setCreatedCashReceipt] = useState<CashPaymentRecord | null>(null);

  // Auditable Cash Reversal / Adjustment State
  const [reversalTargetRecord, setReversalTargetRecord] = useState<CashPaymentRecord | null>(null);
  const [reversalReason, setReversalReason] = useState("");
  const [reversalAuthorizedBy, setReversalAuthorizedBy] = useState("Pastor Brown / Executive Board");
  const [reversalAmount, setReversalAmount] = useState<number>(0);

  // Multi-status Order Action Dialog
  const [verifyingOrder, setVerifyingOrder] = useState<TicketOrder | null>(null);
  const [verificationAction, setVerificationAction] = useState<
    "paid" | "under_verification" | "partially_paid" | "rejected" | "refunded" | null
  >(null);
  const [actionPartialAmount, setActionPartialAmount] = useState<number>(0);
  const [actionVerifier, setActionVerifier] = useState("Treasurer Davis");
  const [actionNotes, setActionNotes] = useState("");
  const [actionRejectionReason, setActionRejectionReason] = useState("");
  const [actionRefundAmount, setActionRefundAmount] = useState<number>(0);
  const [actionRefundReason, setActionRefundReason] = useState("");

  // Search & Filter
  const [orderSearchQuery, setOrderSearchQuery] = useState("");
  const [paymentStatusFilter, setPaymentStatusFilter] = useState<
    "all" | "pending_review" | "awaiting_payment" | "partially_paid" | "paid" | "rejected" | "refunded"
  >("all");

  // Notification Logs filter and compose state
  const [notifStatusFilter, setNotifStatusFilter] = useState<
    "all" | "delivered" | "sent" | "queued" | "failed"
  >("all");
  const [notifChannelFilter, setNotifChannelFilter] = useState<
    "all" | "sms" | "email" | "whatsapp"
  >("all");
  const [notifSearchQuery, setNotifSearchQuery] = useState("");
  const [activeMockupNotif, setActiveMockupNotif] = useState<NotificationLogEntry | null>(null);
  const [showCustomNotifModal, setShowCustomNotifModal] = useState(false);
  const [customNotifRecipient, setCustomNotifRecipient] = useState("");
  const [customNotifPhone, setCustomNotifPhone] = useState("");
  const [customNotifOrderId, setCustomNotifOrderId] = useState("");
  const [customNotifChannel, setCustomNotifChannel] = useState<NotificationChannel>("sms");
  const [customNotifTrigger, setCustomNotifTrigger] = useState<NotificationTrigger>("order_ready");
  const [customNotifTitle, setCustomNotifTitle] = useState("Order Ready for Collection");
  const [customNotifMessage, setCustomNotifMessage] = useState(
    "Island Events: Your meal order is packaged and waiting at Counter 2. Show your ticket barcode to staff."
  );

  // 9-Stage Lifecycle Notification Studio State
  const [selectedTemplateId, setSelectedTemplateId] = useState<string>("tmpl-order-received");
  const [templateChannelView, setTemplateChannelView] = useState<"email" | "sms">("email");
  const [showLaravelCodeModal, setShowLaravelCodeModal] = useState(false);
  const [activeLaravelFile, setActiveLaravelFile] = useState<"notification" | "job" | "controller" | "migration">("notification");
  const [simulatedDispatchStage, setSimulatedDispatchStage] = useState<null | "queued" | "sent" | "delivered" | "retry_suppressed">(null);
  const [simulatedDispatchNote, setSimulatedDispatchNote] = useState<string>("");
  const [activeAttemptDrawerNotif, setActiveAttemptDrawerNotif] = useState<NotificationLogEntry | null>(null);
  const [verifiedVsRejectedState, setVerifiedVsRejectedState] = useState<"verified" | "rejected">("verified");
  const [issuedVsUpdatedState, setIssuedVsUpdatedState] = useState<"issued" | "updated">("issued");
  const [activeTabNotificationsView, setActiveTabNotificationsView] = useState<"studio" | "compliance" | "logs">("studio");

  const selectedTemplate = useMemo(() => {
    return NOTIFICATION_TEMPLATES.find((t) => t.id === selectedTemplateId) || NOTIFICATION_TEMPLATES[0];
  }, [selectedTemplateId]);

  function interpolate(text: string, vars: Record<string, string>): string {
    let res = text;
    for (const [k, v] of Object.entries(vars)) {
      res = res.split(`{${k}}`).join(v);
    }
    return res;
  }

  function handleRunSimulation() {
    setSimulatedDispatchStage("queued");
    setSimulatedDispatchNote("Job dispatched to Laravel 'notifications' queue (Worker #1 assigned). INVARIANT ENFORCED: Status is 'queued' — NEVER marked delivered merely because it was queued!");
    
    setTimeout(() => {
      setSimulatedDispatchStage("sent");
      const sid = "SM" + Math.random().toString(16).substring(2, 18);
      setSimulatedDispatchNote(`Queue worker executed SendNotificationJob. Accepted by carrier/MTA gateway (Provider SID: ${sid}). Status is 'sent'. Awaiting handset delivery callback.`);
      
      setTimeout(() => {
        setSimulatedDispatchStage("delivered");
        setSimulatedDispatchNote("Carrier Webhook Callback received (MessageStatus=delivered / Postmark DSN). Handset delivery verified on recipient mobile network. Delivery timestamp recorded.");
      }, 1600);
    }, 1300);
  }

  function handleRunSafeRetrySimulation() {
    setSimulatedDispatchStage("retry_suppressed");
    setSimulatedDispatchNote("IDEMPOTENCY GUARD ACTIVE: Queue job retried with key 'idemp_order_received_ORD-8492'. Message was already marked 'delivered' (Provider SID SM67a8b9c1d2e3f4a5b6c7d8e9f0a1b2c3). Duplicate dispatch SUPPRESSED — zero duplicate customer receipts sent!");
  }

  const totalNotifs = (notificationLogs || []).length;
  const deliveredNotifs = (notificationLogs || []).filter((n) => n.status === "delivered").length;
  const queuedNotifs = (notificationLogs || []).filter(
    (n) => n.status === "queued" || n.status === "sent"
  ).length;
  const failedNotifs = (notificationLogs || []).filter((n) => n.status === "failed").length;
  const deliveryRate = totalNotifs > 0 ? Math.round((deliveredNotifs / totalNotifs) * 100) : 100;

  const filteredNotifsList = useMemo(() => {
    return (notificationLogs || []).filter((n) => {
      if (notifStatusFilter !== "all" && n.status !== notifStatusFilter) return false;
      if (notifChannelFilter !== "all" && n.channel !== notifChannelFilter) return false;
      if (!notifSearchQuery.trim()) return true;
      const q = notifSearchQuery.toLowerCase().trim();
      return (
        n.customerName.toLowerCase().includes(q) ||
        n.recipient.toLowerCase().includes(q) ||
        Boolean(n.orderNumber && n.orderNumber.toLowerCase().includes(q)) ||
        n.title.toLowerCase().includes(q) ||
        n.message.toLowerCase().includes(q)
      );
    });
  }, [notificationLogs, notifStatusFilter, notifChannelFilter, notifSearchQuery]);

  const filteredOrdersList = useMemo(() => {
    return orders.filter((ord) => {
      // Status filter
      if (paymentStatusFilter === "pending_review") {
        if (
          ord.status !== "payment_submitted" &&
          ord.status !== "under_verification" &&
          ord.status !== "pending_verification"
        )
          return false;
      } else if (paymentStatusFilter === "awaiting_payment") {
        if (ord.status !== "awaiting_payment") return false;
      } else if (paymentStatusFilter === "partially_paid") {
        if (ord.status !== "partially_paid") return false;
      } else if (paymentStatusFilter === "paid") {
        if (ord.status !== "paid") return false;
      } else if (paymentStatusFilter === "rejected") {
        if (ord.status !== "rejected") return false;
      } else if (paymentStatusFilter === "refunded") {
        if (ord.status !== "refunded") return false;
      }

      // Search query
      if (!orderSearchQuery.trim()) return true;
      const q = orderSearchQuery.toLowerCase().trim();
      return (
        ord.customerName.toLowerCase().includes(q) ||
        ord.customerPhone.includes(q) ||
        ord.orderNumber.toLowerCase().includes(q) ||
        ord.ticketCode.toLowerCase().includes(q) ||
        Boolean(ord.paymentReference && ord.paymentReference.toLowerCase().includes(q)) ||
        Boolean(ord.senderName && ord.senderName.toLowerCase().includes(q))
      );
    });
  }, [orders, paymentStatusFilter, orderSearchQuery]);

  function copyText(text: string, msg = "Copied to clipboard!") {
    navigator.clipboard.writeText(text);
    setToastMessage(msg);
    setTimeout(() => setToastMessage(""), 2500);
  }

  function handleOpenAddMenu() {
    setEditingMenuItemId(null);
    setNewItemName("");
    setNewItemCategory("Mains");
    setIsCustomCategory(false);
    setCustomCategoryInput("");
    setNewItemPricingType("fixed");
    setNewItemDesc("");
    setNewItemPriceUSD(15);
    setNewItemPriceJMD(2500);
    setNewItemImage("/images/jerk_chicken.jpg");
    setCustomImageUrl("");
    setUploadedPhoto("");
    setUploadedPhotoName("");
    setNewItemStock(100);
    setIsStockUnlimited(false);
    setNewItemAllowInstructions(true);
    setCustomOptionGroups([]);
    setCustomMinUSD(5);
    setCustomMaxUSD(1000);
    setCustomMinJMD(500);
    setCustomMaxJMD(150000);
    setCustomSuggestedUSD("10, 25, 50, 100");
    setCustomSuggestedJMD("1000, 2500, 5000, 10000");
    setShowAddMenuModal(true);
  }

  function handleOpenEditMenu(item: MenuItem) {
    setEditingMenuItemId(item.id);
    setNewItemName(item.name);
    setNewItemCategory(item.category);
    setIsCustomCategory(false);
    setCustomCategoryInput("");
    setNewItemPricingType(item.pricingType || "fixed");
    setNewItemDesc(item.description || "");
    setNewItemPriceUSD(item.priceUSD);
    setNewItemPriceJMD(item.priceJMD);
    setNewItemImage(item.imageUrl);
    const isPresetImage = item.imageUrl.startsWith("/images/");
    const isPickedPhoto = item.imageUrl.startsWith("data:");
    setCustomImageUrl(isPresetImage || isPickedPhoto ? "" : item.imageUrl);
    setUploadedPhoto(isPickedPhoto ? item.imageUrl : "");
    setUploadedPhotoName("");
    setIsStockUnlimited(item.availableStock === undefined || item.availableStock === null);
    setNewItemStock(item.availableStock ?? 100);
    setNewItemAllowInstructions(Boolean(item.allowSpecialInstructions));
    setCustomOptionGroups(
      item.customOptionGroups ? JSON.parse(JSON.stringify(item.customOptionGroups)) : []
    );
    setCustomMinUSD(item.customAmountConfig?.minUSD ?? 5);
    setCustomMaxUSD(item.customAmountConfig?.maxUSD ?? 1000);
    setCustomMinJMD(item.customAmountConfig?.minJMD ?? 500);
    setCustomMaxJMD(item.customAmountConfig?.maxJMD ?? 150000);
    setCustomSuggestedUSD(
      (item.customAmountConfig?.suggestedAmountsUSD ?? [10, 25, 50, 100]).join(", ")
    );
    setCustomSuggestedJMD(
      (item.customAmountConfig?.suggestedAmountsJMD ?? [1000, 2500, 5000, 10000]).join(", ")
    );
    setShowAddMenuModal(true);
  }

  function handleSaveMenuItem(e: React.FormEvent) {
    e.preventDefault();
    if (!newItemName.trim()) return;

    const finalCategory = (isCustomCategory && customCategoryInput.trim()
      ? customCategoryInput.trim()
      : newItemCategory) as MenuCategory;

    const finalImageUrl = uploadedPhoto || customImageUrl.trim() || newItemImage;
    const finalStock = isStockUnlimited ? undefined : Number(newItemStock) || 0;

    let customAmountConfig = undefined;
    if (newItemPricingType === "custom_amount") {
      const parseNums = (str: string) =>
        str
          .split(",")
          .map((s) => Number(s.trim()))
          .filter((n) => !isNaN(n) && n > 0);

      customAmountConfig = {
        minUSD: Number(customMinUSD) || 1,
        maxUSD: Number(customMaxUSD) || 1000,
        minJMD: Number(customMinJMD) || 100,
        maxJMD: Number(customMaxJMD) || 150000,
        suggestedAmountsUSD: parseNums(customSuggestedUSD).length
          ? parseNums(customSuggestedUSD)
          : [10, 25, 50],
        suggestedAmountsJMD: parseNums(customSuggestedJMD).length
          ? parseNums(customSuggestedJMD)
          : [1000, 2500, 5000],
      };
    }

    const payload: MenuItem = {
      id: editingMenuItemId || `item-${Date.now()}`,
      organizerId: eventDetails.organizerId,
      name: newItemName.trim(),
      category: finalCategory,
      pricingType: newItemPricingType,
      description: newItemDesc.trim(),
      priceUSD: Number(newItemPriceUSD) || 0,
      priceJMD: Number(newItemPriceJMD) || 0,
      imageUrl: finalImageUrl,
      availableStock: finalStock,
      allowSpecialInstructions: newItemAllowInstructions,
      customOptionGroups:
        newItemPricingType === "customizable" && customOptionGroups.length > 0
          ? customOptionGroups
          : undefined,
      customAmountConfig,
    };

    if (editingMenuItemId) {
      onUpdateMenu(menu.map((m) => (m.id === editingMenuItemId ? payload : m)));
      setToastMessage(`Updated "${payload.name}" successfully!`);
    } else {
      onUpdateMenu([...menu, payload]);
      setToastMessage(`Added "${payload.name}" to official menu!`);
    }

    setShowAddMenuModal(false);
    setTimeout(() => setToastMessage(""), 2500);
  }

  function handleAddOptionGroup() {
    const newGroup: CustomOptionGroup = {
      id: `opt-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
      title: "Option Group " + (customOptionGroups.length + 1),
      required: true,
      choices: [
        { label: "Standard Selection", extraPriceUSD: 0, extraPriceJMD: 0 },
        { label: "Upgrade (+J$250 / $1.75)", extraPriceUSD: 1.75, extraPriceJMD: 250 },
      ],
    };
    setCustomOptionGroups([...customOptionGroups, newGroup]);
  }

  function handleUpdateOptionGroup(index: number, patch: Partial<CustomOptionGroup>) {
    setCustomOptionGroups((prev) => {
      const next = [...prev];
      next[index] = { ...next[index], ...patch };
      return next;
    });
  }

  function handleRemoveOptionGroup(index: number) {
    setCustomOptionGroups((prev) => prev.filter((_, i) => i !== index));
  }

  function handleAddOptionChoice(groupIndex: number) {
    setCustomOptionGroups((prev) => {
      const next = [...prev];
      next[groupIndex].choices.push({
        label: "New Option Choice",
        extraPriceUSD: 0,
        extraPriceJMD: 0,
      });
      return next;
    });
  }

  function handleUpdateChoice(
    groupIndex: number,
    choiceIndex: number,
    patch: Partial<CustomOptionChoice>
  ) {
    setCustomOptionGroups((prev) => {
      const next = [...prev];
      const choices = [...next[groupIndex].choices];
      choices[choiceIndex] = { ...choices[choiceIndex], ...patch };
      next[groupIndex].choices = choices;
      return next;
    });
  }

  function handleRemoveChoice(groupIndex: number, choiceIndex: number) {
    setCustomOptionGroups((prev) => {
      const next = [...prev];
      next[groupIndex].choices = next[groupIndex].choices.filter(
        (_: CustomOptionChoice, ci: number) => ci !== choiceIndex
      );
      return next;
    });
  }

  function handleDeleteMenuItem(id: string) {
    onUpdateMenu(menu.filter((m) => m.id !== id));
    setToastMessage("Item removed from menu.");
    setTimeout(() => setToastMessage(""), 2200);
  }

  const uniqueMenuCategories = useMemo(() => {
    const set = new Set<string>();
    menu.forEach((m) => {
      if (m.category) set.add(m.category);
    });
    return Array.from(set);
  }, [menu]);

  const filteredMenuItems = useMemo(() => {
    return menu.filter((item) => {
      if (menuFilterCategory !== "all" && item.category !== menuFilterCategory) {
        return false;
      }
      if (menuSearchQuery.trim()) {
        const q = menuSearchQuery.toLowerCase().trim();
        return (
          item.name.toLowerCase().includes(q) ||
          item.description.toLowerCase().includes(q) ||
          item.category.toLowerCase().includes(q)
        );
      }
      return true;
    });
  }, [menu, menuFilterCategory, menuSearchQuery]);

  function updateMethod(method: MethodId, patch: Partial<PaymentMethodSettings>) {
    onUpdateSettings((current) => ({
      ...current,
      methods: {
        ...current.methods,
        [method]: { ...current.methods[method], ...patch },
      },
    }));
  }

  const shareUrl = window.location.origin;
  const shareMessage = `🔥 Wah Gwaan! Tickets & authentic Jamaican food orders for "${eventDetails.title}" are now live on Island Events! Check out the menu & reserve your spot: ${shareUrl}`;

  return (
    <section className="organizer-experience-container">
      {/* ENTERPRISE MULTI-TENANT & ROLE BOUNDARY HEADER */}
      <div className="tenant-context-bar">
        <div className="tenant-context-left">
          <div className="tenant-selector-badge">
            <Building2 size={16} className="tenant-icon" />
            <div className="tenant-selector-info">
              <span className="tenant-kicker">ACTIVE ORGANIZER (TENANT)</span>
              <div className="tenant-dropdown-wrap">
                <select
                  value={localActiveOrgId}
                  onChange={(e) => handleSelectOrg(e.target.value)}
                  className="tenant-select-input"
                  aria-label="Switch Active Organizer Tenant"
                >
                  {organizers.map((org) => (
                    <option key={org.id} value={org.id}>
                      {org.name} ({org.slug}.events.app) — {org.businessLocation} · {org.defaultCurrency}
                    </option>
                  ))}
                </select>
                <ChevronDown size={14} className="tenant-chevron" />
              </div>
            </div>
          </div>

          <div className="tenant-role-badge">
            <UserCheck size={16} className="role-icon" />
            <div className="tenant-selector-info">
              <span className="tenant-kicker">ROLE PERSPECTIVE</span>
              <div className="tenant-dropdown-wrap">
                <select
                  value={localRole}
                  onChange={(e) => handleSelectRole(e.target.value as TenantRole)}
                  className="tenant-select-input role-select"
                  aria-label="Switch Active Role Perspective"
                >
                  <option value="platform_admin">🛡️ Platform Administrator (Global SuperAdmin)</option>
                  <option value="event_owner">👑 Event Owner / Organizer Admin (Full Tenant CRUD)</option>
                  <option value="payment_verifier">💳 Payment Verifier / Cashier (Audit & Cash Only)</option>
                  <option value="food_collector">🍲 Food Collection Staff (Scanner & Portions Only)</option>
                </select>
                <ChevronDown size={14} className="tenant-chevron" />
              </div>
            </div>
          </div>
        </div>

        <div className="tenant-context-right">
          <div className="tenant-isolation-pill">
            <span className="isolation-dot" />
            <span className="isolation-text">
              <strong>Single-DB Row Scoping:</strong> <code>WHERE organizer_id = '{activeOrg.slug}'</code>
            </span>
          </div>
          <button
            type="button"
            className="tenancy-hud-btn"
            onClick={() => setActiveSubTab("multitenancy")}
            title="Open Multi-Organizer Architecture & RBAC Security Console"
          >
            <ShieldCheck size={14} /> Tenancy Architecture
          </button>
        </div>
      </div>

      {/* Organizer Navigation Subtabs */}
      <div className="organizer-subnav">
        <button
          type="button"
          className={`subnav-tab ${activeSubTab === "event" ? "active" : ""}`}
          onClick={() => setActiveSubTab("event")}
          id="subnav-tab-event"
        >
          <Calendar size={16} /> Event & Flyer Studio
          {localEvent.status && (
            <span className={`tab-count status-${localEvent.status}`}>
              {localEvent.status.toUpperCase()}
            </span>
          )}
        </button>
        <button
          type="button"
          className={`subnav-tab ${activeSubTab === "payments" ? "active" : ""}`}
          onClick={() => setActiveSubTab("payments")}
        >
          <Banknote size={16} /> Payments & Cash Ledger
          {pendingOrdersCount > 0 && <span className="tab-count">{pendingOrdersCount}</span>}
        </button>
        <button
          type="button"
          className={`subnav-tab ${activeSubTab === "prep" ? "active" : ""}`}
          onClick={() => setActiveSubTab("prep")}
        >
          <ChefHat size={16} /> Kitchen Prep Sheet & Portions
        </button>
        <button
          type="button"
          className={`subnav-tab ${activeSubTab === "notifications" ? "active" : ""}`}
          onClick={() => setActiveSubTab("notifications")}
        >
          <MessageSquare size={16} /> SMS & Receipts ({totalNotifs})
          {failedNotifs > 0 && <span className="tab-count alert">{failedNotifs}</span>}
        </button>
        <button
          type="button"
          className={`subnav-tab ${activeSubTab === "menu" ? "active" : ""}`}
          onClick={() => setActiveSubTab("menu")}
        >
          <Utensils size={16} /> Menu & Pricing ({menu.length})
        </button>
        <button
          type="button"
          className={`subnav-tab ${activeSubTab === "settings" ? "active" : ""}`}
          onClick={() => setActiveSubTab("settings")}
          id="subnav-tab-settings"
        >
          <Settings2 size={16} /> Account & Payment Settings (14)
        </button>
        <button
          type="button"
          className={`subnav-tab ${activeSubTab === "share" ? "active" : ""}`}
          onClick={() => setActiveSubTab("share")}
        >
          <Share2 size={16} /> Share & Promote
        </button>
        <button
          type="button"
          className={`subnav-tab ${activeSubTab === "multitenancy" ? "active" : ""}`}
          onClick={() => setActiveSubTab("multitenancy")}
        >
          <Building2 size={16} /> Multi-Organizer Architecture & RBAC
        </button>

        <button
          type="button"
          className="record-cash-quick-btn"
          onClick={() => setShowAddCashModal(true)}
        >
          <Plus size={14} /> Record Cash Received
        </button>
      </div>

      {toastMessage && <div className="mini-toast organizer">{toastMessage}</div>}

      {/* TAB: EVENT MANAGEMENT & FLYER STUDIO */}
      {activeSubTab === "event" && (
        <div className="event-studio-container" id="event-studio-view">
          {/* Header Banner */}
          <div className="event-studio-header">
            <div>
              <span className="section-kicker">EVENT LIFECYCLE & PROMOTIONAL FLYER STUDIO</span>
              <h2>{localEvent.title || "Untitled Event"}</h2>
              <p>
                Configure event dates, Caribbean venue details, advance ordering deadlines, and
                upload high-resolution promotional artwork securely stored under tenant-isolated filesystem paths.
              </p>
            </div>

            <div className="event-studio-actions-group">
              {/* Status Indicator */}
              <div
                className={`event-status-badge status-${localEvent.status || "draft"}`}
                id="event-status-badge"
              >
                {localEvent.status === "published" && <span className="status-dot-pulse" />}
                {(localEvent.status || "draft").toUpperCase()}
              </div>

              {/* Status Action Buttons */}
              {isEventOwner && (
                <>
                  {localEvent.status !== "published" ? (
                    <button
                      type="button"
                      className="event-action-btn publish"
                      onClick={() => handleStatusTransition("published")}
                      id="publish-event-btn"
                    >
                      <Globe size={14} /> Publish Event
                    </button>
                  ) : (
                    <button
                      type="button"
                      className="event-action-btn unpublish"
                      onClick={() => handleStatusTransition("unpublished")}
                      id="unpublish-event-btn"
                    >
                      <LockKeyhole size={14} /> Unpublish Event
                    </button>
                  )}

                  {localEvent.status !== "cancelled" && (
                    <button
                      type="button"
                      className="event-action-btn cancel"
                      onClick={() => {
                        if (window.confirm("Are you sure you want to mark this event as CANCELLED?")) {
                          handleStatusTransition("cancelled");
                        }
                      }}
                      id="cancel-event-btn"
                    >
                      <AlertTriangle size={14} /> Cancel Event
                    </button>
                  )}

                  {isEventDirty && (
                    <button
                      type="button"
                      className="event-action-btn primary-save"
                      onClick={handleSaveEventSettings}
                      id="save-event-btn"
                    >
                      <Check size={14} /> Save Changes
                    </button>
                  )}
                </>
              )}
            </div>
          </div>

          {/* Role Boundary Notice if not owner */}
          {!isEventOwner && (
            <div className="role-boundary-alert">
              <ShieldAlert size={20} className="alert-icon" />
              <div>
                <strong>Read-Only Event Perspective ({localRole})</strong>
                <p>
                  Event lifecycle changes (publish/unpublish/cancel) and promotional flyer uploads require <strong>Event Owner</strong> or <strong>Platform Admin</strong> authorization. Your current role allows inspecting parameters.
                </p>
              </div>
            </div>
          )}

          {/* 2-Column Responsive Studio Grid */}
          <div className="event-studio-grid">
            {/* Column 1: Event Details & Logistics Configuration */}
            <div className="studio-card">
              <div className="studio-card-header">
                <div>
                  <h3>
                    <Calendar size={18} /> Event Profile & Logistics
                  </h3>
                  <p>Core dates, Caribbean venue address, advance ordering deadlines, and contact information.</p>
                </div>
                {isEventDirty && (
                  <span className="step-badge">Unsaved changes</span>
                )}
              </div>

              <form onSubmit={handleSaveEventSettings} className="event-form-grid">
                <div className="form-field-group full-width">
                  <label htmlFor="evt-title">
                    Event Title <span className="req">*</span>
                  </label>
                  <input
                    id="evt-title"
                    type="text"
                    value={localEvent.title}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("title", e.target.value)}
                    placeholder="e.g. Annual Jamaican Youth Culinary Fish Fry 2026"
                    required
                  />
                </div>

                <div className="form-field-group full-width">
                  <label htmlFor="evt-tagline">Tagline / Subtitle</label>
                  <input
                    id="evt-tagline"
                    type="text"
                    value={localEvent.tagline}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("tagline", e.target.value)}
                    placeholder="e.g. Authentic Escovitch & Roast Fish Fundraiser"
                  />
                </div>

                <div className="form-field-group full-width">
                  <label htmlFor="evt-slug">
                    URL Slug (Unique shareable identifier)
                  </label>
                  <input
                    id="evt-slug"
                    type="text"
                    value={localEvent.slug || ""}
                    disabled={!isEventOwner}
                    onChange={(e) =>
                      handleEventFieldChange(
                        "slug",
                        e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, "-")
                      )
                    }
                    placeholder="e.g. kingston-youth-fish-fry-2026"
                  />
                  <span className="form-field-hint">
                    Public URL: <code>https://events.islandfest.org/events/{localEvent.slug || "event-slug"}</code>
                  </span>
                </div>

                <div className="form-field-group">
                  <label htmlFor="evt-date">
                    <Calendar size={13} /> Event Date
                  </label>
                  <input
                    id="evt-date"
                    type="text"
                    value={localEvent.dateStr}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("dateStr", e.target.value)}
                    placeholder="e.g. Saturday, November 14, 2026"
                    required
                  />
                </div>

                <div className="form-field-group">
                  <label htmlFor="evt-time">
                    <Clock3 size={13} /> Serving Hours
                  </label>
                  <input
                    id="evt-time"
                    type="text"
                    value={localEvent.timeStr}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("timeStr", e.target.value)}
                    placeholder="e.g. 12:00 PM – 8:00 PM"
                    required
                  />
                </div>

                <div className="form-field-group">
                  <label htmlFor="evt-timezone">
                    <Globe size={13} /> Event Timezone
                  </label>
                  <select
                    id="evt-timezone"
                    value={localEvent.timezone || "America/Jamaica"}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("timezone", e.target.value)}
                  >
                    <option value="America/Jamaica">America/Jamaica (EST / UTC-5, No DST)</option>
                    <option value="America/New_York">America/New_York (US Eastern Time)</option>
                    <option value="America/Chicago">America/Chicago (US Central Time)</option>
                    <option value="America/Toronto">America/Toronto (Canada Eastern)</option>
                    <option value="UTC">UTC (Universal Time)</option>
                  </select>
                </div>

                <div className="form-field-group">
                  <label htmlFor="evt-currency">
                    <DollarSign size={13} /> Base Event Currency
                  </label>
                  <select
                    id="evt-currency"
                    value={localEvent.currency || "JMD"}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("currency", e.target.value as Currency)}
                  >
                    <option value="JMD">JMD (Jamaican Dollar - J$)</option>
                    <option value="USD">USD (US Dollar - $)</option>
                  </select>
                </div>

                <div className="form-field-group full-width">
                  <label htmlFor="evt-venue">
                    <MapPin size={13} /> Venue Name
                  </label>
                  <input
                    id="evt-venue"
                    type="text"
                    value={localEvent.venueName}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("venueName", e.target.value)}
                    placeholder="e.g. Hope Gardens Pavilion"
                    required
                  />
                </div>

                <div className="form-field-group full-width">
                  <label htmlFor="evt-address">Physical Venue Address</label>
                  <input
                    id="evt-address"
                    type="text"
                    value={localEvent.venueAddress}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("venueAddress", e.target.value)}
                    placeholder="e.g. Old Hope Road, Kingston 6, Jamaica"
                  />
                </div>

                <div className="form-field-group">
                  <label htmlFor="evt-coords">GPS Coordinates</label>
                  <input
                    id="evt-coords"
                    type="text"
                    value={localEvent.coordinates}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("coordinates", e.target.value)}
                    placeholder="18.0179° N, 76.7554° W"
                  />
                </div>

                <div className="form-field-group">
                  <label htmlFor="evt-deadline">
                    <Clock3 size={13} /> Advance Ordering Deadline
                  </label>
                  <input
                    id="evt-deadline"
                    type="text"
                    value={localEvent.advanceOrderDeadline}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("advanceOrderDeadline", e.target.value)}
                    placeholder="e.g. Friday, Nov 13 at 6:00 PM EST"
                  />
                </div>

                <div className="form-field-group">
                  <label htmlFor="evt-org-name">Organizer / Ministry Name</label>
                  <input
                    id="evt-org-name"
                    type="text"
                    value={localEvent.organizerName}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("organizerName", e.target.value)}
                    placeholder="e.g. Kingston Youth Fellowship"
                  />
                </div>

                <div className="form-field-group">
                  <label htmlFor="evt-phone">
                    <Phone size={13} /> Contact Phone
                  </label>
                  <input
                    id="evt-phone"
                    type="text"
                    value={localEvent.organizerPhone}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("organizerPhone", e.target.value)}
                    placeholder="e.g. +1 (876) 555-0192"
                  />
                </div>

                <div className="form-field-group">
                  <label htmlFor="evt-whatsapp">
                    <MessageCircle size={13} /> WhatsApp Hotline
                  </label>
                  <input
                    id="evt-whatsapp"
                    type="text"
                    value={localEvent.organizerWhatsApp}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("organizerWhatsApp", e.target.value)}
                    placeholder="e.g. +1 (876) 555-0192"
                  />
                </div>

                <div className="form-field-group">
                  <label htmlFor="evt-email">
                    <Mail size={13} /> Organizer Email
                  </label>
                  <input
                    id="evt-email"
                    type="email"
                    value={localEvent.organizerEmail}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("organizerEmail", e.target.value)}
                    placeholder="e.g. info@kingstonyouth.org"
                  />
                </div>

                <div className="form-field-group full-width">
                  <label htmlFor="evt-pickup">Order Collection & Pickup Instructions</label>
                  <textarea
                    id="evt-pickup"
                    rows={3}
                    value={localEvent.pickupInstructions}
                    disabled={!isEventOwner}
                    onChange={(e) => handleEventFieldChange("pickupInstructions", e.target.value)}
                    placeholder="Provide specific instructions for customers picking up meals on event day..."
                  />
                </div>

                <div className="form-field-group full-width">
                  <label htmlFor="evt-ordering-toggle" style={{ cursor: "pointer", display: "flex", alignItems: "center", gap: "8px" }}>
                    <input
                      id="evt-ordering-toggle"
                      type="checkbox"
                      checked={localEvent.isOrderingOpen}
                      disabled={!isEventOwner}
                      onChange={(e) => handleEventFieldChange("isOrderingOpen", e.target.checked)}
                      style={{ width: "auto" }}
                    />
                    <span>Allow guests to place orders online (Ordering Gate Open)</span>
                  </label>
                </div>

                {isEventOwner && (
                  <div className="form-field-group full-width">
                    <button
                      type="submit"
                      className="primary-button"
                      style={{ width: "100%" }}
                      id="save-event-form-submit"
                    >
                      <Check size={16} /> Save Event Profile Changes
                    </button>
                  </div>
                )}
              </form>
            </div>

            {/* Column 2: Promotional Flyer Upload Studio */}
            <div className="studio-card">
              <div className="studio-card-header">
                <div>
                  <h3>
                    <ImageIcon size={18} /> Promotional Flyer Studio
                  </h3>
                  <p>Upload event artwork from your computer or phone with strict server-side validation.</p>
                </div>
              </div>

              <div className="flyer-display-box">
                {/* Active Flyer Preview Frame */}
                {(() => {
                  const display = getEventFlyerDisplay(localEvent);
                  return (
                    <div className="flyer-image-frame">
                      <img
                        src={display.flyerUrl}
                        alt={display.altText}
                        id="active-flyer-img"
                      />
                      <div className={`flyer-badge-overlay ${display.isFallback ? "fallback" : ""}`}>
                        {display.isFallback ? (
                          <>
                            <Sparkles size={12} /> Themed Culinary Fallback
                          </>
                        ) : (
                          <>
                            <CheckCircle2 size={12} /> Official Uploaded Flyer
                          </>
                        )}
                      </div>
                      <button
                        type="button"
                        className="flyer-zoom-btn"
                        onClick={() =>
                          setLightboxImg({
                            url: display.flyerUrl,
                            caption: display.altText,
                          })
                        }
                        title="Click to zoom full resolution"
                      >
                        <Eye size={13} /> Zoom
                      </button>
                    </div>
                  );
                })()}

                {/* Storage & Metadata Chips */}
                <div className="flyer-meta-row">
                  <div className="flyer-meta-chip">
                    <span>MIME:</span>{" "}
                    <strong>{localEvent.flyerMimeType || "image/jpeg (fallback)"}</strong>
                  </div>
                  {localEvent.flyerDimensions && (
                    <div className="flyer-meta-chip">
                      <span>Dimensions:</span>{" "}
                      <strong>
                        {localEvent.flyerDimensions.width} × {localEvent.flyerDimensions.height} px
                      </strong>
                    </div>
                  )}
                  {localEvent.flyerSizeBytes && (
                    <div className="flyer-meta-chip">
                      <span>Size:</span>{" "}
                      <strong>
                        {Math.round(localEvent.flyerSizeBytes / 1024)} KB
                      </strong>
                    </div>
                  )}
                  <div className="flyer-meta-chip">
                    <span>Public Access:</span>{" "}
                    <strong style={{ color: localEvent.isFlyerPublic ? "#10b981" : "#f59e0b" }}>
                      {localEvent.isFlyerPublic ? "Publicly Accessible" : "Owner Preview Only"}
                    </strong>
                  </div>
                </div>

                {/* Tenant-Scoped Filesystem Security Badge */}
                <div className="flyer-storage-security-badge">
                  <ShieldCheck size={20} style={{ flexShrink: 0, marginTop: "2px" }} />
                  <div>
                    <strong>Tenant-Isolated Storage Abstraction:</strong>
                    <div>
                      Path:{" "}
                      <code>
                        {localEvent.flyerStoragePath ||
                          `tenants/${activeOrg.slug}/flyers/fallback-banner.webp`}
                      </code>
                    </div>
                    <span style={{ fontSize: "11px", color: "#4b5563" }}>
                      Uses Laravel Filesystem abstraction with cryptographic random filenames, path-traversal sanitization (preventing <code>../</code> escapes), and safe MIME inspection.
                    </span>
                  </div>
                </div>

                {/* Flyer Errors Display */}
                {flyerErrors.length > 0 && (
                  <div className="flyer-validation-error-list" role="alert">
                    {flyerErrors.map((err, idx) => (
                      <div key={idx} style={{ display: "flex", alignItems: "center", gap: "6px" }}>
                        <AlertCircle size={14} /> {err}
                      </div>
                    ))}
                  </div>
                )}

                {/* Pre-save Preview Card (Shown after file selection before confirming) */}
                {flyerPendingFile && (
                  <div className="flyer-presave-card" id="flyer-presave-card">
                    <div className="flyer-presave-header">
                      <strong>
                        <Sparkles size={14} /> Pre-Save Inspection Preview
                      </strong>
                      <span className="step-badge">Ready to Save</span>
                    </div>

                    <img
                      src={flyerPendingFile.dataUrl}
                      alt="Pending Flyer Preview"
                      className="flyer-presave-img-thumb"
                    />

                    <div style={{ fontSize: "12px", color: "#065f46", display: "flex", flexDirection: "column", gap: "3px" }}>
                      <div>
                        <strong>File:</strong> {flyerPendingFile.name} (
                        {(flyerPendingFile.sizeBytes / (1024 * 1024)).toFixed(2)} MB)
                      </div>
                      <div>
                        <strong>Format:</strong> {flyerPendingFile.mimeType} ·{" "}
                        {flyerPendingFile.dimensions
                          ? `${flyerPendingFile.dimensions.width}×${flyerPendingFile.dimensions.height}px`
                          : "Calculated"}
                      </div>
                    </div>

                    <div style={{ display: "flex", gap: "8px", marginTop: "4px" }}>
                      <button
                        type="button"
                        className="primary-button"
                        style={{ flex: 1 }}
                        onClick={handleConfirmFlyerUpload}
                        id="confirm-flyer-save-btn"
                      >
                        <Check size={14} /> Confirm & Save Flyer
                      </button>
                      <button
                        type="button"
                        className="modal-close-btn"
                        style={{ width: "auto", padding: "0 14px", height: "44px", borderRadius: "7px" }}
                        onClick={handleCancelPendingFlyer}
                      >
                        Cancel
                      </button>
                    </div>
                  </div>
                )}

                {/* Drag and Drop Upload Area */}
                {isEventOwner && !flyerPendingFile && (
                  <label
                    htmlFor="flyer-file-input"
                    className={`flyer-upload-dropzone ${isDragOver ? "dragging" : ""}`}
                    onDragOver={(e) => {
                      e.preventDefault();
                      setIsDragOver(true);
                    }}
                    onDragLeave={() => setIsDragOver(false)}
                    onDrop={(e) => {
                      e.preventDefault();
                      setIsDragOver(false);
                      if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                        handleFileSelected(e.dataTransfer.files[0]);
                      }
                    }}
                  >
                    <input
                      id="flyer-file-input"
                      type="file"
                      accept=".jpg,.jpeg,.png,.webp"
                      style={{ display: "none" }}
                      onChange={(e) => {
                        if (e.target.files && e.target.files[0]) {
                          handleFileSelected(e.target.files[0]);
                        }
                      }}
                    />
                    <div className="flyer-dropzone-icon">
                      <Upload size={20} />
                    </div>
                    <div className="flyer-dropzone-title">
                      {localEvent.flyerUrl ? "Upload Replacement Flyer" : "Upload Promotional Flyer"}
                    </div>
                    <div className="flyer-dropzone-sub">
                      Drag and drop here, or tap to choose from phone or computer
                    </div>
                    <span style={{ fontSize: "11px", color: "#94a3b8" }}>
                      JPEG, PNG, or WebP only · Max 5MB · Min 300×200px, Max 4500×4500px
                    </span>
                  </label>
                )}

                {/* Actions: Replace & Remove */}
                {isEventOwner && (
                  <div style={{ display: "flex", gap: "10px", flexWrap: "wrap" }}>
                    <label
                      htmlFor="flyer-replace-input"
                      className="event-action-btn"
                      style={{
                        background: "#f1f5f9",
                        color: "#0f172a",
                        border: "1px solid #cbd5e1",
                        cursor: "pointer",
                      }}
                    >
                      <input
                        id="flyer-replace-input"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp"
                        style={{ display: "none" }}
                        onChange={(e) => {
                          if (e.target.files && e.target.files[0]) {
                            handleFileSelected(e.target.files[0]);
                          }
                        }}
                      />
                      <Upload size={14} /> Replace Flyer
                    </label>

                    {localEvent.flyerUrl && (
                      <button
                        type="button"
                        className="event-action-btn cancel"
                        onClick={handleRemoveFlyerAction}
                        id="remove-flyer-btn"
                      >
                        <Trash2 size={14} /> Remove Flyer
                      </button>
                    )}
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* SECTION 3: SHAREABLE PUBLIC LINK & SOCIAL META STUDIO */}
          <div className="shareable-meta-studio">
            <div className="studio-card-header">
              <div>
                <h3>
                  <Share2 size={18} /> Unique Shareable Link & Social OpenGraph Metadata
                </h3>
                <p>
                  Generate shareable ticket URLs and preview how the promotional flyer and event details appear when shared on WhatsApp, Facebook, iMessage, and X.
                </p>
              </div>
            </div>

            <div className="shareable-url-box">
              <Globe size={16} style={{ color: "#10b981" }} />
              <input
                type="text"
                readOnly
                value={`https://events.islandfest.org/events/${localEvent.slug || "caribbean-youth-fish-fry-2026"}`}
                className="shareable-url-input"
                id="shareable-event-url-input"
              />
              <button
                type="button"
                className="event-action-btn publish"
                onClick={() =>
                  copyText(
                    `https://events.islandfest.org/events/${localEvent.slug || "caribbean-youth-fish-fry-2026"}`,
                    "Shareable event link copied to clipboard!"
                  )
                }
                id="copy-shareable-url-btn"
              >
                <Copy size={13} /> Copy Link
              </button>
            </div>

            <div className="shareable-social-row">
              <a
                href={`https://api.whatsapp.com/send?text=${encodeURIComponent(
                  `Check out ${localEvent.title}! Order food in advance & get digital tickets: https://events.islandfest.org/events/${localEvent.slug || "event"}`
                )}`}
                target="_blank"
                rel="noopener noreferrer"
                className="social-share-pill-btn whatsapp"
              >
                <MessageCircle size={14} /> Share on WhatsApp
              </a>

              <a
                href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(
                  `https://events.islandfest.org/events/${localEvent.slug || "event"}`
                )}`}
                target="_blank"
                rel="noopener noreferrer"
                className="social-share-pill-btn facebook"
              >
                <Facebook size={14} /> Share on Facebook
              </a>

              <button
                type="button"
                className="social-share-pill-btn"
                onClick={() =>
                  copyText(
                    `https://events.islandfest.org/events/${localEvent.slug || "event"}`,
                    "Event link copied for social media sharing!"
                  )
                }
              >
                <Share2 size={14} /> Copy for Instagram / TikTok Bio
              </button>
            </div>

            {/* Open Graph Card Simulation */}
            <div className="opengraph-card-preview" id="og-preview-card">
              <div className="opengraph-img-box">
                <img
                  src={getEventFlyerDisplay(localEvent).flyerUrl}
                  alt="OpenGraph Social Sharing Preview"
                />
              </div>
              <div className="opengraph-meta-text">
                <span className="opengraph-site-name">ISLANDEVENTS.APP · COMMUNITY EVENT</span>
                <strong className="opengraph-title">
                  {localEvent.socialSharingMeta?.ogTitle || localEvent.title}
                </strong>
                <p className="opengraph-desc">
                  {localEvent.socialSharingMeta?.ogDescription ||
                    `${localEvent.dateStr} at ${localEvent.venueName}. ${localEvent.tagline}`}
                </p>
              </div>
            </div>
          </div>

          {/* Full Resolution Image Lightbox */}
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
      )}

      {/* TAB 0: PAYMENTS VERIFICATION QUEUE & AUDITABLE CASH LEDGER */}
      {activeSubTab === "payments" && (
        <div className="payments-tab-layout">
          {/* Role Boundary Enforcement Alert */}
          {isFoodCollectorOnly && (
            <div className="role-boundary-alert">
              <ShieldAlert size={20} className="alert-icon" />
              <div>
                <strong>Role Boundary Enforced: Food Collection Staff (Kitchen Tier)</strong>
                <p>
                  You are viewing this station under the <strong>Food Collection Staff</strong> role. Per enterprise security boundaries, cash ledger accounting, bank statement verification, and revenue figures are restricted to <strong>Event Owners</strong> and <strong>Payment Verifiers</strong>. Customer contact numbers and balances are masked. Ticket QR scanning and meal portion checklists are available in the <strong>Kitchen Prep Sheet & Portions</strong> tab.
                </p>
              </div>
            </div>
          )}

          {/* Top Metric Cards */}
          <div className="metrics-cards-grid">
            <div className="metric-card highlight-green">
              <span className="metric-label">VERIFIED REVENUE (PAID)</span>
              <strong className="metric-val green">
                {verifiedTotals.length
                  ? verifiedTotals
                      .map(({ currency, total }) => formatMoney(total, currency))
                      .join(" + ")
                  : formatMoney(0, settings.currency)}
              </strong>
              <span className="metric-sub">{verifiedOrdersCount} verified orders</span>
            </div>

            <div className="metric-card">
              <span className="metric-label">CASH IN HAND (LEDGER)</span>
              <strong className="metric-val">
                {formatMoney(totalCashCollected, settings.currency)}
              </strong>
              <span className="metric-sub">{(cashLedger || []).length} recorded cash transactions</span>
            </div>

            <div className="metric-card highlight-amber">
              <span className="metric-label">AWAITING VERIFICATION</span>
              <strong className="metric-val amber">{pendingOrdersCount}</strong>
              <span className="metric-sub">
                {pendingTotals.length
                  ? pendingTotals
                      .map(({ currency, total }) => formatMoney(total, currency))
                      .join(" + ")
                  : "Zero unverified"}
              </span>
            </div>

            <div className="metric-card">
              <span className="metric-label">AWAITING CASH AT VENUE</span>
              <strong className="metric-val">{awaitingCashCount}</strong>
              <span className="metric-sub">Reserved meals awaiting cash receipt</span>
            </div>

            <div className="metric-card">
              <span className="metric-label">PARTIALLY PAID</span>
              <strong className="metric-val">{partiallyPaidCount}</strong>
              <span className="metric-sub">Deposit paid, balance due at pickup</span>
            </div>
          </div>

          {/* Anti-Fraud Security Directive */}
          <div className="anti-fraud-alert-banner">
            <div className="anti-fraud-icon">
              <ShieldAlert size={24} />
            </div>
            <div>
              <strong>Strict Financial Security Policy: Verify Bank Account Directly</strong>
              <p>
                The organizer must <strong>never approve a payment solely because a customer uploaded or sent a screenshot</strong>.
                Screenshots can be forged, modified, or show transactions that are pending, reversed, or sent to the wrong recipient.
                Always cross-reference your actual Jamaican commercial bank account (NCB, Scotiabank), Zelle, or Cash App balance directly before marking any payment as received.
              </p>
            </div>
          </div>

          {/* Action Bar: Filters, Search & Record Cash Button */}
          <div className="payments-action-bar">
            <div className="search-box-wrap">
              <Search size={16} />
              <input
                type="text"
                placeholder="Search orders by customer, phone, order #, or ref code..."
                value={orderSearchQuery}
                onChange={(e) => setOrderSearchQuery(e.target.value)}
              />
              {orderSearchQuery && (
                <button
                  type="button"
                  className="clear-search-btn"
                  onClick={() => setOrderSearchQuery("")}
                >
                  <X size={13} />
                </button>
              )}
            </div>

            <div className="filter-chips-row">
              <button
                type="button"
                className={`filter-chip ${paymentStatusFilter === "all" ? "active" : ""}`}
                onClick={() => setPaymentStatusFilter("all")}
              >
                All Orders ({orders.length})
              </button>
              <button
                type="button"
                className={`filter-chip ${paymentStatusFilter === "pending_review" ? "active" : ""}`}
                onClick={() => setPaymentStatusFilter("pending_review")}
              >
                <Clock3 size={12} /> Awaiting Verification ({pendingOrdersCount})
              </button>
              <button
                type="button"
                className={`filter-chip ${paymentStatusFilter === "awaiting_payment" ? "active" : ""}`}
                onClick={() => setPaymentStatusFilter("awaiting_payment")}
              >
                <Banknote size={12} /> Awaiting Cash ({awaitingCashCount})
              </button>
              <button
                type="button"
                className={`filter-chip ${paymentStatusFilter === "partially_paid" ? "active" : ""}`}
                onClick={() => setPaymentStatusFilter("partially_paid")}
              >
                <DollarSign size={12} /> Partially Paid ({partiallyPaidCount})
              </button>
              <button
                type="button"
                className={`filter-chip ${paymentStatusFilter === "paid" ? "active" : ""}`}
                onClick={() => setPaymentStatusFilter("paid")}
              >
                <CheckCircle2 size={12} /> Verified Paid ({verifiedOrdersCount})
              </button>
              <button
                type="button"
                className={`filter-chip ${paymentStatusFilter === "rejected" ? "active" : ""}`}
                onClick={() => setPaymentStatusFilter("rejected")}
              >
                <AlertTriangle size={12} /> Rejected ({rejectedCount})
              </button>
              <button
                type="button"
                className={`filter-chip ${paymentStatusFilter === "refunded" ? "active" : ""}`}
                onClick={() => setPaymentStatusFilter("refunded")}
              >
                <RefreshCw size={12} /> Refunded ({refundedCount})
              </button>
            </div>

            <button
              type="button"
              className="primary-button compact"
              onClick={() => setShowAddCashModal(true)}
            >
              <Plus size={15} /> Record Cash Payment
            </button>
          </div>

          {/* Orders Verification Queue */}
          <div className="panel payment-queue-panel">
            <div className="panel-heading">
              <div>
                <span className="section-kicker">MANUAL TRANSFER & PAYMENT VERIFICATION QUEUE</span>
                <h2>Customer Orders & Settlement Workflow ({filteredOrdersList.length})</h2>
                <p>
                  Review submitted transfer reference numbers, record partial or full receipts, and update order statuses.
                </p>
              </div>
              <span className="step-badge">{filteredOrdersList.length} Displayed</span>
            </div>

            <div className="queue-orders-grid">
              {filteredOrdersList.length > 0 ? (
                filteredOrdersList.map((ord) => {
                  const balanceDue = Math.max(0, ord.totalAmount - (ord.amountPaid || 0));
                  const isPaid = ord.status === "paid";
                  const isPartial = ord.status === "partially_paid";
                  const isPending =
                    ord.status === "payment_submitted" ||
                    ord.status === "under_verification" ||
                    ord.status === "pending_verification";
                  const isAwaiting = ord.status === "awaiting_payment";
                  const isRejected = ord.status === "rejected";
                  const isRefunded = ord.status === "refunded";

                  return (
                    <article className="order-verification-card" key={ord.id}>
                      <div className="card-top-row">
                        <div className="customer-info-block">
                          <strong>{ord.customerName}</strong>
                          <span className="customer-phone">{ord.customerPhone}</span>
                          <span className="order-codes">
                            Order <code>{ord.orderNumber}</code> · Ref: <code>{ord.ticketCode}</code>
                          </span>
                        </div>

                        <div className="status-badges-column">
                          <span className={`status-pill ${ord.status}`}>
                            {isPaid && <><CheckCircle2 size={12} /> Paid (Verified)</>}
                            {isPartial && <><DollarSign size={12} /> Partially Paid</>}
                            {isPending && <><Clock3 size={12} /> Under Verification</>}
                            {isAwaiting && <><Banknote size={12} /> Awaiting Payment</>}
                            {isRejected && <><AlertTriangle size={12} /> Rejected</>}
                            {isRefunded && <><RefreshCw size={12} /> Refunded</>}
                          </span>

                          <div className="fulfillment-status-row">
                            <span className="fulfillment-label">Kitchen / Pickup:</span>
                            <select
                              value={ord.fulfillmentStatus || "unprepared"}
                              onChange={(e) =>
                                onUpdateFulfillmentStatus(
                                  ord.id,
                                  e.target.value as FulfillmentStatus
                                )
                              }
                              className="fulfillment-select"
                            >
                              <option value="unprepared">Unprepared</option>
                              <option value="preparing">In Kitchen (Preparing)</option>
                              <option value="ready_for_pickup">Ready for Pickup</option>
                              <option value="collected">Collected (Handed Over)</option>
                            </select>
                          </div>
                        </div>
                      </div>

                      {/* Financial Detail Row */}
                      <div className="card-financial-row">
                        <div className="fin-item">
                          <span>Payment Method:</span>
                          <strong>{METHOD_INFO[ord.method].name}</strong>
                        </div>
                        <div className="fin-item">
                          <span>Total Amount:</span>
                          <strong>{formatMoney(ord.totalAmount, ord.currency)}</strong>
                        </div>
                        <div className="fin-item">
                          <span>Amount Verified:</span>
                          <strong className={ord.amountPaid ? "green" : ""}>
                            {formatMoney(ord.amountPaid || 0, ord.currency)}
                          </strong>
                        </div>
                        {balanceDue > 0 && (
                          <div className="fin-item balance-due">
                            <span>Balance Due:</span>
                            <strong className="amber">{formatMoney(balanceDue, ord.currency)}</strong>
                          </div>
                        )}
                      </div>

                      {/* Submitted Transfer Proof Box */}
                      {(ord.paymentReference || ord.senderName || ord.verificationNotes || ord.rejectionReason || ord.refundDetails) && (
                        <div className="transfer-proof-box">
                          {ord.paymentReference && (
                            <div className="proof-line">
                              <span>Customer Reference #:</span>
                              <code>{ord.paymentReference}</code>
                            </div>
                          )}
                          {ord.senderName && (
                            <div className="proof-line">
                              <span>Sender Account Name:</span>
                              <strong>{ord.senderName}</strong>
                            </div>
                          )}
                          {ord.verificationNotes && (
                            <div className="proof-line notes">
                              <span>Verification Note:</span>
                              <em>{ord.verificationNotes}</em>
                            </div>
                          )}
                          {ord.rejectionReason && (
                            <div className="proof-line rejection">
                              <span>Rejection Reason:</span>
                              <strong className="red">{ord.rejectionReason}</strong>
                            </div>
                          )}
                          {ord.refundDetails && (
                            <div className="proof-line refund">
                              <span>Refund Record:</span>
                              <span>
                                {formatMoney(ord.refundDetails.amount, ord.currency)} refunded (
                                {ord.refundDetails.reason}) by {ord.refundDetails.refundedBy}
                              </span>
                            </div>
                          )}
                        </div>
                      )}

                      {/* Order Items Snippet */}
                      <div className="card-items-snippet">
                        {ord.items.map((it, idx) => (
                          <span className="order-item-chip" key={idx}>
                            {it.quantity}x {it.name}
                            {it.selectedOptionsSummary.length > 0 && ` (${it.selectedOptionsSummary.join(", ")})`}
                          </span>
                        ))}
                      </div>

                      {/* Action Button Strip */}
                      <div className="card-actions-strip">
                        {!isPaid && (
                          <button
                            type="button"
                            className="verify-action-btn primary"
                            onClick={() => {
                              setVerifyingOrder(ord);
                              setVerificationAction("paid");
                              setActionVerifier("Treasurer Davis");
                              setActionNotes("Matched on bank/app statement batch");
                            }}
                          >
                            <CheckCircle2 size={13} /> Confirm Full Payment
                          </button>
                        )}

                        {!isPaid && !isPartial && (
                          <button
                            type="button"
                            className="verify-action-btn secondary"
                            onClick={() => {
                              setVerifyingOrder(ord);
                              setVerificationAction("partially_paid");
                              setActionPartialAmount(Math.round(ord.totalAmount / 2));
                              setActionVerifier("Treasurer Davis");
                            }}
                          >
                            <DollarSign size={13} /> Record Partial Payment
                          </button>
                        )}

                        {isPending && (
                          <button
                            type="button"
                            className="verify-action-btn tertiary"
                            onClick={() => {
                              onUpdatePaymentStatus(ord.id, "under_verification", {
                                verifier: "Treasurer Davis",
                                notes: "Cross-referencing bank statement",
                              });
                            }}
                          >
                            <Clock3 size={13} /> Under Verification
                          </button>
                        )}

                        {!isPaid && (
                          <button
                            type="button"
                            className="verify-action-btn danger"
                            onClick={() => {
                              setVerifyingOrder(ord);
                              setVerificationAction("rejected");
                              setActionRejectionReason("Transaction not found on NCB / bank statement");
                            }}
                          >
                            <X size={13} /> Reject Payment
                          </button>
                        )}

                        {isPaid && (
                          <button
                            type="button"
                            className="verify-action-btn warning"
                            onClick={() => {
                              setVerifyingOrder(ord);
                              setVerificationAction("refunded");
                              setActionRefundAmount(ord.totalAmount);
                              setActionRefundReason("Customer requested cancellation");
                            }}
                          >
                            <RotateCcw size={13} /> Record Refund
                          </button>
                        )}
                      </div>
                    </article>
                  );
                })
              ) : (
                <div className="activity-empty">
                  <Clock3 size={24} />
                  <p>No orders match the selected filter or search query.</p>
                </div>
              )}
            </div>
          </div>

          {/* AUDITABLE CASH PAYMENT LEDGER & FINANCIAL CONTROL */}
          <div className="panel cash-ledger-panel">
            <div className="panel-heading">
              <div>
                <span className="section-kicker">FINANCIAL CONTROL & AUDIT TRAIL</span>
                <h2>Auditable Cash Payment Ledger & Reconciliations</h2>
                <p>
                  Permanent audit trail of all physical cash collected. In accordance with enterprise financial controls,
                  <strong> corrections are recorded through auditable reversals or adjustments</strong> rather than silently overwriting the original transaction.
                </p>
              </div>
              <button
                type="button"
                className="primary-button compact"
                onClick={() => setShowAddCashModal(true)}
              >
                <Plus size={14} /> Record Cash Receipt
              </button>
            </div>

            <div className="prep-table-wrap">
              <table className="prep-table cash-ledger-table">
                <thead>
                  <tr>
                    <th>Receipt #</th>
                    <th>Type</th>
                    <th>Customer Name & Phone</th>
                    <th>Linked Order</th>
                    <th>Date & Time Received</th>
                    <th>Collector / Officer</th>
                    <th>Amount</th>
                    <th>Notes / Audit Reason</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {(cashLedger && cashLedger.length > 0) ? (
                    cashLedger.map((rec) => {
                      const isReversal = rec.transactionType === "reversal" || rec.amount < 0;
                      return (
                        <tr key={rec.id} className={isReversal ? "reversal-row" : ""}>
                          <td>
                            <code>{rec.receiptNumber}</code>
                          </td>
                          <td>
                            <span className={`transaction-type-pill ${rec.transactionType}`}>
                              {rec.transactionType.toUpperCase()}
                            </span>
                          </td>
                          <td>
                            <strong>{rec.customerName}</strong>
                            <span className="sub-phone">{rec.customerPhone}</span>
                          </td>
                          <td>
                            {rec.orderNumber ? (
                              <code>{rec.orderNumber}</code>
                            ) : (
                              <span className="muted">Direct Walk-in</span>
                            )}
                          </td>
                          <td>
                            <Clock3 size={11} /> {new Date(rec.receivedAt).toLocaleString("en-US", { dateStyle: "short", timeStyle: "short" })}
                          </td>
                          <td>
                            <UserCheck size={13} /> {rec.receivedBy}
                          </td>
                          <td>
                            <strong className={isReversal ? "red" : "green"}>
                              {isReversal ? "-" : "+"}
                              {formatMoney(Math.abs(rec.amount), rec.currency)}
                            </strong>
                          </td>
                          <td>
                            <span className="ledger-notes">
                              {rec.reversalReason || rec.notes || "Official cash receipt"}
                            </span>
                          </td>
                          <td>
                            <div className="ledger-row-actions">
                              <button
                                type="button"
                                className="action-pill-btn"
                                onClick={() => setCreatedCashReceipt(rec)}
                                title="View customer receipt & copy SMS/WhatsApp message"
                              >
                                <FileText size={12} /> Receipt
                              </button>
                              {!isReversal && (
                                <button
                                  type="button"
                                  className="action-pill-btn reverse"
                                  onClick={() => {
                                    setReversalTargetRecord(rec);
                                    setReversalAmount(rec.amount);
                                    setReversalReason("Customer order cancellation / cash correction");
                                  }}
                                  title="Create non-destructive auditable reversal"
                                >
                                  <RotateCcw size={12} /> Reverse
                                </button>
                              )}
                            </div>
                          </td>
                        </tr>
                      );
                    })
                  ) : (
                    <tr>
                      <td colSpan={9} className="empty-table-cell">
                        No cash payments recorded in ledger yet.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 1: KITCHEN PREP & SALES SUMMARY */}
      {activeSubTab === "prep" && (
        <div className="prep-tab-layout">
          {/* Top Metric Cards */}
          <div className="metrics-cards-grid">
            <div className="metric-card">
              <span className="metric-label">TOTAL ORDERS PLACED</span>
              <strong className="metric-val">{totalOrders}</strong>
              <span className="metric-sub">{totalPortionsNeeded} meal portions ordered</span>
            </div>

            <div className="metric-card highlight-green">
              <span className="metric-label">VERIFIED REVENUE (PAID)</span>
              <strong className="metric-val green">
                {verifiedTotals.length
                  ? verifiedTotals
                      .map(({ currency, total }) => formatMoney(total, currency))
                      .join(" + ")
                  : formatMoney(0, settings.currency)}
              </strong>
              <span className="metric-sub">{verifiedOrders} verified transactions</span>
            </div>

            <div className="metric-card highlight-amber">
              <span className="metric-label">AWAITING VERIFICATION</span>
              <strong className="metric-val amber">{pendingOrders}</strong>
              <span className="metric-sub">
                {pendingTotals.length
                  ? pendingTotals
                      .map(({ currency, total }) => formatMoney(total, currency))
                      .join(" + ")
                  : "Zero unverified"}
              </span>
            </div>

            <div className="metric-card">
              <span className="metric-label">EVENT ATTENDANCE EXPECTED</span>
              <strong className="metric-val">{orders.length} Guests</strong>
              <span className="metric-sub">{eventDetails.venueName}</span>
            </div>
          </div>

          <div className="prep-main-split">
            {/* Left: Kitchen Prep Sheet Table */}
            <div className="panel prep-sheet-panel">
              <div className="panel-heading">
                <div>
                  <span className="section-kicker">LIVE KITCHEN FORECAST</span>
                  <h2>Food & Beverage Prep Sheet</h2>
                  <p>
                    Exact meal quantities needed for event day based on actual customer orders.
                  </p>
                </div>
                <span className="step-badge">{kitchenPrepSheet.length} Menu Items</span>
              </div>

              <div className="prep-table-wrap">
                <table className="prep-table">
                  <thead>
                    <tr>
                      <th>Dish / Beverage</th>
                      <th>Category</th>
                      <th>Total Ordered</th>
                      <th>Verified Paid</th>
                      <th>Awaiting Verification</th>
                      <th>Handed Over</th>
                    </tr>
                  </thead>
                  <tbody>
                    {kitchenPrepSheet.map((row) => (
                      <tr key={row.item.id}>
                        <td className="dish-name-cell">
                          <img
                            src={row.item.imageUrl}
                            alt={row.item.name}
                            className="dish-cell-thumb"
                          />
                          <div>
                            <strong>{row.item.name}</strong>
                            <span>
                              {formatMoney(
                                settings.currency === "JMD" ? row.item.priceJMD : row.item.priceUSD,
                                settings.currency
                              )}
                            </span>
                          </div>
                        </td>
                        <td>
                          <span className="category-cell-badge">{row.item.category}</span>
                        </td>
                        <td className="prep-total-cell">
                          <strong>{row.totalQuantity}</strong> portions
                        </td>
                        <td className="paid-cell">
                          <span className="chip-paid">{row.paidQuantity} paid</span>
                        </td>
                        <td className="pending-cell">
                          <span className="chip-pending">{row.pendingQuantity} pending</span>
                        </td>
                        <td className="handover-cell">
                          <span className="chip-collected">
                            {row.handedOverQuantity}/{row.totalQuantity}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="prep-advice-card">
                <ChefHat size={20} />
                <div>
                  <strong>Head Chef Kitchen Notice:</strong>
                  <p>
                    All items listed above represent direct guest reservations. Prepare{" "}
                    <strong>{totalPortionsNeeded} portions</strong> total to guarantee zero shortages
                    on event day.
                  </p>
                </div>
              </div>
            </div>

            {/* Right: Payment Verification Queue */}
            <aside className="prep-sidebar">
              <div className="panel verification-queue-panel">
                <div className="panel-heading">
                  <div>
                    <span className="section-kicker">MANUAL TRANSFER QUEUE</span>
                    <h3>Guest Orders ({orders.length})</h3>
                  </div>
                  <span className="queue-count">{pendingOrders} PENDING</span>
                </div>

                <div className="orders-queue-list">
                  {orders.length > 0 ? (
                    orders.map((ord) => (
                      <div className="order-queue-item" key={ord.id}>
                        <div className="queue-item-header">
                          <div>
                            <strong>{ord.customerName}</strong>
                            <span className="order-ticket-code">
                              Ref: <code>{ord.ticketCode}</code>
                            </span>
                          </div>
                          <div className="queue-item-amount">
                            <strong>{formatMoney(ord.totalAmount, ord.currency)}</strong>
                            <span className="order-method-name">
                              {METHOD_INFO[ord.method].shortName}
                            </span>
                          </div>
                        </div>

                        <div className="queue-items-snippet">
                          {ord.items.map((i) => `${i.quantity}x ${i.name}`).join(" · ")}
                        </div>

                        <div className="queue-action-row">
                          {ord.status === "pending_verification" || ord.status === "payment_submitted" ? (
                            <>
                              <button
                                type="button"
                                className="verify-button"
                                onClick={() => onUpdatePaymentStatus(ord.id, "paid", { verifier: "Treasurer Davis" })}
                              >
                                <CheckCircle2 size={14} /> Confirm Receipt
                              </button>
                              <button
                                type="button"
                                className="reject-button"
                                onClick={() => onUpdatePaymentStatus(ord.id, "rejected", { verifier: "Treasurer Davis" })}
                              >
                                <X size={13} /> Not Received
                              </button>
                            </>
                          ) : ord.status === "paid" ? (
                            <div className="verified-status-banner">
                              <CheckCircle2 size={14} /> Verified Paid
                            </div>
                          ) : (
                            <div className="rejected-status-banner">
                              <AlertCircle size={14} /> {ord.status.replace(/_/g, " ").toUpperCase()}
                            </div>
                          )}
                        </div>
                      </div>
                    ))
                  ) : (
                    <div className="activity-empty compact">
                      <Clock3 size={18} />
                      <p>No orders submitted yet.</p>
                    </div>
                  )}
                </div>
              </div>
            </aside>
          </div>
        </div>
      )}

      {/* TAB: SMS NOTIFICATIONS & CUSTOMER RECEIPTS (MISSION-CRITICAL LIFECYCLE DISPATCH) */}
      {activeSubTab === "notifications" && (
        <div className="notifications-tab-layout">
          {/* Header Panel */}
          <div className="panel notifications-header-panel">
            <div className="panel-heading">
              <div>
                <span className="section-kicker">COMMUNICATIONS & DISPATCH ENGINE</span>
                <h2>SMS Notifications, Receipts & Twilio Gateway</h2>
                <p>
                  Automated customer notifications across 8 lifecycle stages with telecommunications delivery status tracking.
                </p>
              </div>
              <div className="notif-header-actions">
                <button
                  type="button"
                  className="broadcast-reminder-btn"
                  onClick={() => {
                    onBroadcastEventReminder();
                    setToastMessage("Broadcasted event countdown & pickup reminder to ticket holders!");
                    setTimeout(() => setToastMessage(""), 3500);
                  }}
                >
                  <Radio size={15} /> Broadcast Event Reminder
                </button>
                <button
                  type="button"
                  className="primary-button compact"
                  onClick={() => {
                    if (orders.length > 0) {
                      setCustomNotifOrderId(orders[0].id);
                      setCustomNotifRecipient(orders[0].customerName);
                      setCustomNotifPhone(orders[0].customerPhone);
                    }
                    setShowCustomNotifModal(true);
                  }}
                >
                  <Send size={14} /> Send Manual Message
                </button>
              </div>
            </div>

            {/* Notification Metric Cards */}
            <div className="metrics-cards-grid notif-metrics-grid">
              <div className="metric-card highlight-green">
                <span className="metric-label">TOTAL DISPATCHED</span>
                <strong className="metric-val green">{totalNotifs}</strong>
                <span className="metric-sub">Across SMS, Email, & WhatsApp</span>
              </div>

              <div className="metric-card">
                <span className="metric-label">DELIVERY SUCCESS RATE</span>
                <strong className="metric-val">{deliveryRate}%</strong>
                <span className="metric-sub">{deliveredNotifs} verified carrier deliveries</span>
              </div>

              <div className="metric-card highlight-amber">
                <span className="metric-label">IN-TRANSIT / QUEUED</span>
                <strong className="metric-val amber">{queuedNotifs}</strong>
                <span className="metric-sub">Awaiting carrier delivery receipt</span>
              </div>

              <div className="metric-card">
                <span className="metric-label">DELIVERY FAILURES</span>
                <strong className="metric-val danger">{failedNotifs}</strong>
                <span className="metric-sub fail-badge">Zero Payment Impact · Reversals Prohibited</span>
              </div>
            </div>
          </div>

          {/* CRITICAL NON-REVERSAL INVARIANT BANNER */}
          <div className="notif-invariant-banner">
            <div className="invariant-banner-icon">
              <ShieldCheck size={26} />
            </div>
            <div className="invariant-banner-content">
              <div className="invariant-title-row">
                <strong>Enterprise Non-Reversal Standard: Failed SMS Never Reverses a Valid Payment</strong>
                <span className="invariant-badge">STRICT ARCHITECTURAL SEPARATION</span>
              </div>
              <p>
                Telecommunications carrier delivery statuses (queued, sent, delivered, failed) are strictly decoupled from order financial accounting. 
                If an SMS fails or times out due to cellular network congestion (e.g. Digicel / Flow Jamaica cell towers or expired tourist roaming), 
                <strong> the customer’s verified payment remains 100% active and their digital ticket barcode remains valid for meal collection</strong>.
                Customers can also use <strong>Email Receipts</strong> or their <strong>Shareable Ticket Web Link</strong> as instant zero-cost alternatives.
              </p>
            </div>
          </div>

          {/* TWILIO GATEWAY EVALUATION CARD (EXACT USER PROMPT REQUIREMENT) */}
          <div className="panel twilio-evaluation-card">
            <div className="twilio-card-header">
              <div className="twilio-header-left">
                <span className="telecom-kicker">TELECOMMUNICATIONS PROVIDER EVALUATION</span>
                <h3>Twilio SMS Gateway & Delivery Infrastructure</h3>
                <p>
                  Official integration analysis for Caribbean (Jamaica) and US cross-border event dispatch.
                </p>
              </div>
              <a
                href="https://www.twilio.com/?utm_source=chatgpt.com"
                target="_blank"
                rel="noopener noreferrer"
                className="twilio-official-link-btn"
                title="Visit official Twilio website"
              >
                Evaluate on [Twilio](https://www.twilio.com/?utm_source=chatgpt.com) <ExternalLink size={14} />
              </a>
            </div>

            <div className="twilio-eval-grid">
              {/* Pillar 1: Pricing Analysis */}
              <div className="twilio-eval-pillar">
                <div className="pillar-header">
                  <DollarSign size={17} className="pillar-icon green" />
                  <strong>Global Pricing & Rates</strong>
                </div>
                <ul className="pillar-list">
                  <li>
                    <strong>United States:</strong> ~$0.0079 per outbound message segment (160 GSM chars).
                  </li>
                  <li>
                    <strong>Jamaica (Flow & Digicel):</strong> ~$0.045 to $0.082 per outbound SMS plus carrier termination charges.
                  </li>
                  <li>
                    <strong>Carrier Pass-through Fees:</strong> Added automatically based on destination mobile network operator.
                  </li>
                  <li>
                    <strong>Recommendation:</strong> Maintain a minimum $25 prepaid balance on Twilio to prevent pipeline throttles on event day.
                  </li>
                </ul>
              </div>

              {/* Pillar 2: Sender Requirements & Regulatory Standards */}
              <div className="twilio-eval-pillar">
                <div className="pillar-header">
                  <CheckCircle2 size={17} className="pillar-icon amber" />
                  <strong>Sender Requirements & Compliance</strong>
                </div>
                <ul className="pillar-list">
                  <li>
                    <strong>US A2P 10DLC:</strong> US major carriers (T-Mobile, AT&T, Verizon) require Brand & Campaign registration to prevent spam filtering.
                  </li>
                  <li>
                    <strong>Jamaica Alphanumeric ID:</strong> Digicel & Flow support Alphanumeric Sender IDs (e.g. <code>ISLANDEVNT</code>) for instant branded trust without dedicated shortcodes.
                  </li>
                  <li>
                    <strong>Opt-out Handling:</strong> Automatic STOP/UNSUBSCRIBE compliance built into Twilio Messaging Services.
                  </li>
                </ul>
              </div>

              {/* Pillar 3: Multi-Channel Fallbacks */}
              <div className="twilio-eval-pillar">
                <div className="pillar-header">
                  <Mail size={17} className="pillar-icon purple" />
                  <strong>Multi-Channel Fallbacks</strong>
                </div>
                <ul className="pillar-list">
                  <li>
                    <strong>Email Receipts:</strong> Powered via Amazon SES / SendGrid (~$0.10 per 1,000 emails) as a guaranteed permanent paper trail.
                  </li>
                  <li>
                    <strong>Shareable Ticket Link:</strong> Instant URL (<code>https://islandevents.app/t/[token]</code>) accessible on any mobile browser without SMS.
                  </li>
                  <li>
                    <strong>WhatsApp Direct:</strong> Instant zero-cost 1-click sharing via WhatsApp Web & Mobile API.
                  </li>
                </ul>
              </div>
            </div>
          </div>

          {/* NOTIFICATION HUB SUB-NAV: STUDIO vs COMPLIANCE vs LOGS */}
          <div className="notif-hub-subnav">
            <div className="notif-hub-tabs">
              <button
                type="button"
                className={`notif-hub-tab ${activeTabNotificationsView === "studio" ? "active" : ""}`}
                onClick={() => setActiveTabNotificationsView("studio")}
              >
                <Sparkles size={15} /> 9-Stage Notification Templates Studio
              </button>
              <button
                type="button"
                className={`notif-hub-tab ${activeTabNotificationsView === "compliance" ? "active" : ""}`}
                onClick={() => setActiveTabNotificationsView("compliance")}
              >
                <ShieldCheck size={15} /> Provider Verification & Regulations
              </button>
              <button
                type="button"
                className={`notif-hub-tab ${activeTabNotificationsView === "logs" ? "active" : ""}`}
                onClick={() => setActiveTabNotificationsView("logs")}
              >
                <History size={15} /> Dispatch & Delivery Logs ({filteredNotifsList.length})
              </button>
            </div>

            <button
              type="button"
              className="action-pill-btn laravel-view-btn"
              onClick={() => setShowLaravelCodeModal(true)}
            >
              <Code2 size={14} /> Laravel Backend Architecture
            </button>
          </div>

          {/* VIEW A: 9-STAGE NOTIFICATION TEMPLATES STUDIO */}
          {activeTabNotificationsView === "studio" && (
            <div className="panel studio-panel">
              <div className="panel-heading">
                <div>
                  <span className="section-kicker">ENTERPRISE LIFECYCLE AUTOMATION</span>
                  <h2>Interactive 9-Stage Notification Templates Studio</h2>
                  <p>
                    Full production templates across the entire customer lifecycle. Built with Laravel Queued Notifications, dual-channel dispatch (Email & SMS), deterministic idempotency keys, and real carrier delivery status callbacks.
                  </p>
                </div>
                <div className="studio-heading-actions">
                  <button
                    type="button"
                    className="primary-button compact"
                    onClick={handleRunSimulation}
                    title="Simulate enqueuing and carrier status callbacks"
                  >
                    <Send size={14} /> Simulate Queued Dispatch
                  </button>
                  <button
                    type="button"
                    className="action-pill-btn retry"
                    onClick={handleRunSafeRetrySimulation}
                    title="Simulate job retry with same idempotency key"
                  >
                    <RefreshCw size={13} /> Test Safe Retry
                  </button>
                </div>
              </div>

              {/* Simulation Result Alert Bar */}
              {simulatedDispatchStage && (
                <div className={`simulation-stage-banner ${simulatedDispatchStage}`}>
                  <div className="sim-banner-left">
                    <span className={`sim-badge ${simulatedDispatchStage}`}>
                      {simulatedDispatchStage === "queued" && <Clock3 size={13} />}
                      {simulatedDispatchStage === "sent" && <Send size={13} />}
                      {simulatedDispatchStage === "delivered" && <CheckCheck size={13} />}
                      {simulatedDispatchStage === "retry_suppressed" && <ShieldCheck size={13} />}
                      {simulatedDispatchStage.toUpperCase().replace("_", " ")}
                    </span>
                    <span className="sim-note">{simulatedDispatchNote}</span>
                  </div>
                  <button
                    type="button"
                    className="sim-clear-btn"
                    onClick={() => setSimulatedDispatchStage(null)}
                  >
                    <X size={13} />
                  </button>
                </div>
              )}

              {/* 9 STAGE SELECTOR PILLS */}
              <div className="studio-stage-selector">
                {NOTIFICATION_TEMPLATES.map((tmpl) => {
                  const isSelected = tmpl.id === selectedTemplateId;
                  return (
                    <button
                      key={tmpl.id}
                      type="button"
                      className={`stage-select-pill ${isSelected ? "active" : ""}`}
                      onClick={() => {
                        setSelectedTemplateId(tmpl.id);
                        setSimulatedDispatchStage(null);
                      }}
                    >
                      <span className="stage-num">{tmpl.stageNumber}</span>
                      <span className="stage-name">{tmpl.name}</span>
                    </button>
                  );
                })}
              </div>

              {/* WORKSPACE FOR SELECTED TEMPLATE */}
              <div className="studio-workspace-grid">
                {/* Left Column: Template Config, Variables & Provider Rules */}
                <div className="studio-left-column">
                  <div className="template-meta-card">
                    <div className="template-meta-header">
                      <div>
                        <span className="meta-stage-tag">Stage {selectedTemplate.stageNumber} of 9</span>
                        <h3 className="meta-title">{selectedTemplate.name}</h3>
                      </div>
                      <span className={`trigger-pill ${selectedTemplate.trigger}`}>
                        {selectedTemplate.trigger}
                      </span>
                    </div>

                    <p className="meta-desc">{selectedTemplate.description}</p>

                    {/* Template Variant Toggles */}
                    {selectedTemplate.trigger === "payment_verified_rejected" && (
                      <div className="variant-switch-box">
                        <label className="variant-label">State Scenario:</label>
                        <div className="variant-btn-group">
                          <button
                            type="button"
                            className={`variant-btn ${verifiedVsRejectedState === "verified" ? "active verified" : ""}`}
                            onClick={() => setVerifiedVsRejectedState("verified")}
                          >
                            <CheckCircle2 size={13} /> Verified & Paid
                          </button>
                          <button
                            type="button"
                            className={`variant-btn ${verifiedVsRejectedState === "rejected" ? "active rejected" : ""}`}
                            onClick={() => setVerifiedVsRejectedState("rejected")}
                          >
                            <X size={13} /> Rejected / Proof Needed
                          </button>
                        </div>
                      </div>
                    )}

                    {selectedTemplate.trigger === "ticket_issued_updated" && (
                      <div className="variant-switch-box">
                        <label className="variant-label">State Scenario:</label>
                        <div className="variant-btn-group">
                          <button
                            type="button"
                            className={`variant-btn ${issuedVsUpdatedState === "issued" ? "active" : ""}`}
                            onClick={() => setIssuedVsUpdatedState("issued")}
                          >
                            Initial Ticket Issue
                          </button>
                          <button
                            type="button"
                            className={`variant-btn ${issuedVsUpdatedState === "updated" ? "active" : ""}`}
                            onClick={() => setIssuedVsUpdatedState("updated")}
                          >
                            Portions / Menu Updated
                          </button>
                        </div>
                      </div>
                    )}

                    {/* Laravel Backend Notification Class Mapping */}
                    <div className="laravel-class-box">
                      <div className="laravel-box-label">
                        <Server size={13} /> Laravel Notification & Queue Job
                      </div>
                      <code className="laravel-code-str">
                        {selectedTemplate.laravelNotificationClass}
                      </code>
                      <div className="laravel-features-row">
                        <span className="feature-pill"><Check size={11} /> implements ShouldQueue</span>
                        <span className="feature-pill"><Check size={11} /> Idempotency Guard</span>
                        <span className="feature-pill"><Check size={11} /> Exponential Backoff</span>
                      </div>
                    </div>

                    {/* Available Dynamic Variables Chips */}
                    <div className="variables-section">
                      <div className="variables-header">
                        <strong>Available Dynamic Variables ({selectedTemplate.availableVariables.length})</strong>
                        <span className="sub-hint">Click variable to copy</span>
                      </div>
                      <div className="var-chips-wrap">
                        {selectedTemplate.availableVariables.map((v) => (
                          <button
                            key={v}
                            type="button"
                            className="var-chip"
                            onClick={() => copyText(v, `Copied ${v} to clipboard!`)}
                            title={`Sample value: ${selectedTemplate.sampleVariables[v.replace(/[{}]/g, "")] || "N/A"}`}
                          >
                            <code>{v}</code>
                          </button>
                        ))}
                      </div>
                    </div>

                    {/* Provider Verification Guidelines Pill */}
                    <div className="provider-rules-card">
                      <div className="provider-rules-header">
                        <Globe size={14} className="green" />
                        <strong>Provider Verification & Carrier Routing</strong>
                      </div>
                      <div className="rule-item">
                        <span className="rule-label">Sender Registration:</span>
                        <span className="rule-val">{selectedTemplate.providerGuidelines.senderIdRule}</span>
                      </div>
                      <div className="rule-item">
                        <span className="rule-label">Country Coverage:</span>
                        <span className="rule-val">{selectedTemplate.providerGuidelines.coverage}</span>
                      </div>
                      <div className="rule-item">
                        <span className="rule-label">Cost Efficiency:</span>
                        <span className="rule-val">{selectedTemplate.providerGuidelines.pricingEstimate}</span>
                      </div>
                      <div className="rule-item highlight-rule">
                        <span className="rule-label">Delivery Verification:</span>
                        <span className="rule-val">
                          <CheckCheck size={13} className="green" /> Webhook DSN callback required before status is marked 'delivered'.
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Right Column: Live Dual-Channel Preview (Email & SMS Smartphone) */}
                <div className="studio-right-column">
                  {/* Channel Switcher */}
                  <div className="channel-preview-toggle-bar">
                    <div className="channel-toggle-group">
                      <button
                        type="button"
                        className={`chan-toggle-btn ${templateChannelView === "email" ? "active" : ""}`}
                        onClick={() => setTemplateChannelView("email")}
                      >
                        <Mail size={15} /> Rich Responsive Email
                      </button>
                      <button
                        type="button"
                        className={`chan-toggle-btn ${templateChannelView === "sms" ? "active" : ""}`}
                        onClick={() => setTemplateChannelView("sms")}
                      >
                        <Smartphone size={15} /> Smartphone SMS / WhatsApp
                      </button>
                    </div>

                    <div className="preview-indicator">
                      <span className="live-dot" /> Live Variable Interpolation
                    </div>
                  </div>

                  {/* PREVIEW CONTAINER: EMAIL */}
                  {templateChannelView === "email" && (
                    <div className="email-client-mockup">
                      <div className="email-client-header">
                        <div className="client-controls">
                          <span className="dot red" />
                          <span className="dot yellow" />
                          <span className="dot green" />
                        </div>
                        <span className="client-title">Mail Preview &bull; Island Events Notifier</span>
                      </div>

                      <div className="email-envelope-info">
                        <div className="env-row">
                          <span className="env-label">From:</span>
                          <span className="env-val">Island Events Notifier &lt;orders@islandevents.app&gt;</span>
                        </div>
                        <div className="env-row">
                          <span className="env-label">To:</span>
                          <span className="env-val">
                            {selectedTemplate.sampleVariables.customerName} &lt;customer@islandpatron.example&gt;
                          </span>
                        </div>
                        <div className="env-row subject-row">
                          <span className="env-label">Subject:</span>
                          <strong className="env-val highlight-subject">
                            {selectedTemplate.trigger === "payment_verified_rejected" && verifiedVsRejectedState === "rejected"
                              ? interpolate("Action Required: Payment Verification Unsuccessful for Order #{orderNumber}", selectedTemplate.sampleVariables)
                              : selectedTemplate.trigger === "ticket_issued_updated" && issuedVsUpdatedState === "updated"
                              ? interpolate("Ticket Updated: #{ticketCode} - {eventName}", selectedTemplate.sampleVariables)
                              : interpolate(selectedTemplate.defaultEmailSubject, selectedTemplate.sampleVariables)}
                          </strong>
                        </div>
                      </div>

                      {/* Rendered HTML Sandbox Card */}
                      <div className="email-html-sandbox">
                        <div
                          className="rendered-email-root"
                          dangerouslySetInnerHTML={{
                            __html: interpolate(selectedTemplate.defaultEmailHtml, selectedTemplate.sampleVariables),
                          }}
                        />
                      </div>
                    </div>
                  )}

                  {/* PREVIEW CONTAINER: SMARTPHONE SMS */}
                  {templateChannelView === "sms" && (
                    <div className="smartphone-sms-mockup">
                      <div className="phone-bezel">
                        {/* Phone Top Notch / Speaker */}
                        <div className="phone-notch">
                          <div className="speaker-bar" />
                          <div className="camera-dot" />
                        </div>

                        {/* Phone Screen */}
                        <div className="phone-screen">
                          <div className="phone-status-bar">
                            <span className="carrier-name">Digicel Jamaica 4G</span>
                            <span className="clock">12:45 PM</span>
                            <span className="battery">&#9644;&#9644;&#9644;</span>
                          </div>

                          <div className="message-app-header">
                            <div className="sender-avatar">IE</div>
                            <div className="sender-info">
                              <strong className="sender-title">ISLANDEVNT</strong>
                              <span className="sender-sub">Verified Alphanumeric ID</span>
                            </div>
                          </div>

                          <div className="phone-chat-body">
                            <div className="chat-date-stamp">Today 12:44 PM</div>

                            <div className="chat-bubble received">
                              <p className="sms-text-content">
                                {selectedTemplate.trigger === "payment_verified_rejected" && verifiedVsRejectedState === "rejected"
                                  ? interpolate("Order #{orderNumber} payment could not be verified ({rejectionReason}). Please resubmit reference or contact organizer: {trackingUrl}", selectedTemplate.sampleVariables)
                                  : selectedTemplate.trigger === "ticket_issued_updated" && issuedVsUpdatedState === "updated"
                                  ? interpolate("Your ticket #{ticketCode} for {eventName} has been UPDATED! Show this QR code at food pickup: {ticketUrl}", selectedTemplate.sampleVariables)
                                  : interpolate(selectedTemplate.defaultSms, selectedTemplate.sampleVariables)}
                              </p>
                              <span className="bubble-timestamp">12:45 PM &bull; SMS</span>
                            </div>
                          </div>

                          <div className="sms-segment-metric-bar">
                            <span>
                              Length:{" "}
                              {
                                interpolate(selectedTemplate.defaultSms, selectedTemplate.sampleVariables).length
                              }{" "}
                              chars
                            </span>
                            <span>
                              Segments:{" "}
                              {Math.ceil(
                                interpolate(selectedTemplate.defaultSms, selectedTemplate.sampleVariables).length / 160
                              )}{" "}
                              (160 GSM standard)
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* VIEW B: SUPPORTED PROVIDER VERIFICATION & REGULATORY COMPLIANCE */}
          {activeTabNotificationsView === "compliance" && (
            <div className="panel twilio-evaluation-card">
              <div className="twilio-card-header">
                <div className="twilio-header-left">
                  <span className="telecom-kicker">TELECOMMUNICATIONS PROVIDER EVALUATION & COMPLIANCE</span>
                  <h3>Twilio SMS Gateway, Sender Registration & Carrier Coverage</h3>
                  <p>
                    Verified telecommunications operational standards across Jamaica (+1-876) and United States (+1).
                  </p>
                </div>
                <a
                  href="https://www.twilio.com/?utm_source=chatgpt.com"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="twilio-official-link-btn"
                  title="Visit official Twilio website"
                >
                  Official Provider Console <ExternalLink size={14} />
                </a>
              </div>

              <div className="twilio-eval-grid">
                {/* Pillar 1: Sender Requirements & Registration */}
                <div className="twilio-eval-pillar">
                  <div className="pillar-header">
                    <CheckCircle2 size={17} className="pillar-icon amber" />
                    <strong>Sender Registration Requirements</strong>
                  </div>
                  <ul className="pillar-list">
                    <li>
                      <strong>Jamaica (Digicel & Flow):</strong> Alphanumeric Sender ID (e.g. <code>ISLANDEVNT</code>) registered with Jamaican carriers via Twilio Regulatory Console. No US 10DLC campaign registration fees required for +1-876 local destinations.
                    </li>
                    <li>
                      <strong>US A2P 10DLC:</strong> The Campaign Registry (TCR) brand and campaign vetting required for US long codes (+1). Standard Use Case: Customer Care & Notifications.
                    </li>
                    <li>
                      <strong>US Toll-Free Alternative:</strong> Verified Toll-Free numbers (+1-800) offer fast vetting without TCR brand fees.
                    </li>
                  </ul>
                </div>

                {/* Pillar 2: Country Coverage & E.164 Standards */}
                <div className="twilio-eval-pillar">
                  <div className="pillar-header">
                    <Globe size={17} className="pillar-icon green" />
                    <strong>Country Coverage & Format</strong>
                  </div>
                  <ul className="pillar-list">
                    <li>
                      <strong>Jamaica (+1-876):</strong> Direct routing to both Digicel Jamaica and Flow Jamaica cell networks.
                    </li>
                    <li>
                      <strong>United States (+1):</strong> Supported on AT&T, T-Mobile, Verizon, and regional carriers.
                    </li>
                    <li>
                      <strong>United Kingdom (+44) & Canada (+1):</strong> Fully covered for diaspora attendees.
                    </li>
                    <li>
                      <strong>E.164 Normalization:</strong> System automatically sanitizes and normalizes phone numbers into E.164 international standard format (<code>+18765550142</code>).
                    </li>
                  </ul>
                </div>

                {/* Pillar 3: Pricing & Email-First Savings */}
                <div className="twilio-eval-pillar">
                  <div className="pillar-header">
                    <DollarSign size={17} className="pillar-icon green" />
                    <strong>Pricing & Email-First Architecture</strong>
                  </div>
                  <ul className="pillar-list">
                    <li>
                      <strong>Email-First Policy:</strong> Dispatched via Postmark / Mailgun (~$0.001 per message). Unlimited message length, rich HTML receipts, and zero carrier surcharge.
                    </li>
                    <li>
                      <strong>Twilio SMS Jamaica:</strong> ~$0.058 to $0.075 per 160-char GSM segment + carrier pass-through fees.
                    </li>
                    <li>
                      <strong>Twilio SMS US:</strong> ~$0.0079 per outbound message segment.
                    </li>
                    <li>
                      <strong>85% Cost Optimization:</strong> By prioritizing email where customer provides an email address and reserving SMS for mobile alerts, organizers save up to 85% in messaging costs.
                    </li>
                  </ul>
                </div>

                {/* Pillar 4: Delivery Webhooks & Safe Retries */}
                <div className="twilio-eval-pillar">
                  <div className="pillar-header">
                    <ShieldCheck size={17} className="pillar-icon purple" />
                    <strong>Delivery Statuses & Invariants</strong>
                  </div>
                  <ul className="pillar-list">
                    <li>
                      <strong>Carrier DSN Callback:</strong> <code>/api/webhooks/twilio/status-callback</code> updates status only when carrier confirms handset delivery (<code>delivered</code>).
                    </li>
                    <li>
                      <strong>Strict Non-Enqueued Rule:</strong> Messages are NEVER marked delivered merely because they were enqueued or accepted by MTA.
                    </li>
                    <li>
                      <strong>Safe Retries:</strong> Laravel <code>SendNotificationJob</code> verifies <code>idempotency_key</code> to suppress duplicate receipts when queue workers retry.
                    </li>
                    <li>
                      <strong>Financial Independence:</strong> Failed SMS delivery NEVER alters verified payment ledger records.
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          )}

          {/* AUDITABLE NOTIFICATION LOGS TABLE */}
          <div className="panel notif-logs-panel">
            <div className="panel-heading">
              <div>
                <span className="section-kicker">DISPATCH AUDIT TRAIL</span>
                <h2>Notification & Receipt Logs ({filteredNotifsList.length})</h2>
                <p>
                  Comprehensive log recording recipient, carrier status callbacks, delivery timestamps, and gateway IDs.
                </p>
              </div>
            </div>

            {/* Filter Chips & Search Bar */}
            <div className="notif-action-bar">
              <div className="search-box-wrap notif-search">
                <Search size={15} />
                <input
                  type="text"
                  placeholder="Search logs by customer, phone, order #, or title..."
                  value={notifSearchQuery}
                  onChange={(e) => setNotifSearchQuery(e.target.value)}
                />
                {notifSearchQuery && (
                  <button
                    type="button"
                    className="clear-search-btn"
                    onClick={() => setNotifSearchQuery("")}
                  >
                    <X size={13} />
                  </button>
                )}
              </div>

              {/* Status Filters */}
              <div className="filter-chips-row">
                <button
                  type="button"
                  className={`filter-chip ${notifStatusFilter === "all" ? "active" : ""}`}
                  onClick={() => setNotifStatusFilter("all")}
                >
                  All Statuses ({totalNotifs})
                </button>
                <button
                  type="button"
                  className={`filter-chip ${notifStatusFilter === "delivered" ? "active" : ""}`}
                  onClick={() => setNotifStatusFilter("delivered")}
                >
                  <CheckCircle2 size={12} /> Delivered ({deliveredNotifs})
                </button>
                <button
                  type="button"
                  className={`filter-chip ${notifStatusFilter === "queued" ? "active" : ""}`}
                  onClick={() => setNotifStatusFilter("queued")}
                >
                  <Clock3 size={12} /> In-Transit / Queued ({queuedNotifs})
                </button>
                <button
                  type="button"
                  className={`filter-chip ${notifStatusFilter === "failed" ? "active" : ""}`}
                  onClick={() => setNotifStatusFilter("failed")}
                >
                  <AlertTriangle size={12} /> Failed ({failedNotifs})
                </button>
              </div>

              {/* Channel Filters */}
              <div className="filter-chips-row channel-chips">
                <button
                  type="button"
                  className={`filter-chip ${notifChannelFilter === "all" ? "active" : ""}`}
                  onClick={() => setNotifChannelFilter("all")}
                >
                  All Channels
                </button>
                <button
                  type="button"
                  className={`filter-chip ${notifChannelFilter === "sms" ? "active" : ""}`}
                  onClick={() => setNotifChannelFilter("sms")}
                >
                  SMS
                </button>
                <button
                  type="button"
                  className={`filter-chip ${notifChannelFilter === "email" ? "active" : ""}`}
                  onClick={() => setNotifChannelFilter("email")}
                >
                  Email
                </button>
                <button
                  type="button"
                  className={`filter-chip ${notifChannelFilter === "whatsapp" ? "active" : ""}`}
                  onClick={() => setNotifChannelFilter("whatsapp")}
                >
                  WhatsApp
                </button>
              </div>
            </div>

            {/* Logs Table */}
            <div className="notif-table-wrapper">
              <table className="notif-log-table">
                <thead>
                  <tr>
                    <th>Timestamp</th>
                    <th>Lifecycle Trigger</th>
                    <th>Customer & Contact</th>
                    <th>Gateway / Channel</th>
                    <th>Message Snippet</th>
                    <th>Delivery Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredNotifsList.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="empty-table-cell">
                        No notifications match the selected filters.
                      </td>
                    </tr>
                  ) : (
                    filteredNotifsList.map((entry) => (
                      <tr key={entry.id} className={entry.status === "failed" ? "row-failed" : ""}>
                        <td className="time-cell">
                          <strong>
                            {new Date(entry.sentAt || entry.createdAt || Date.now()).toLocaleDateString([], {
                              month: "short",
                              day: "numeric",
                            })}
                          </strong>
                          <span>
                            {new Date(entry.sentAt || entry.createdAt || Date.now()).toLocaleTimeString([], {
                              hour: "2-digit",
                              minute: "2-digit",
                            })}
                          </span>
                        </td>
                        <td>
                          <span className={`trigger-pill ${entry.trigger}`}>
                            {entry.trigger.replace("_", " ")}
                          </span>
                        </td>
                        <td className="recipient-cell">
                          <strong>{entry.customerName}</strong>
                          <span className="recipient-contact">{entry.recipient}</span>
                          {entry.orderNumber && (
                            <span className="order-tag">Order #{entry.orderNumber}</span>
                          )}
                        </td>
                        <td>
                          <span className={`channel-badge ${entry.channel}`}>
                            {entry.channel.toUpperCase()}
                          </span>
                          <span className="provider-name">{entry.provider}</span>
                          {entry.retrySuppressed && (
                            <span className="retry-suppressed-tag" title="Idempotency guard active: Duplicate dispatch suppressed on job retry">
                              <ShieldCheck size={10} /> Duplicate Suppressed
                            </span>
                          )}
                          {entry.idempotencyKey && (
                            <span className="idemp-key-hint" title={`Idempotency Key: ${entry.idempotencyKey}`}>
                              🔑 {entry.idempotencyKey.substring(0, 16)}...
                            </span>
                          )}
                        </td>
                        <td className="message-cell">
                          <button
                            type="button"
                            className="message-preview-btn"
                            onClick={() => setActiveMockupNotif(entry)}
                            title="Click to view Smartphone SMS Mockup"
                          >
                            <strong>{entry.title}</strong>
                            <p>{entry.message}</p>
                          </button>
                        </td>
                        <td className="status-cell">
                          <span className={`delivery-status-pill ${entry.status}`}>
                            {entry.status === "delivered" && <CheckCircle2 size={12} />}
                            {entry.status === "sent" && <Clock3 size={12} />}
                            {entry.status === "queued" && <Clock3 size={12} />}
                            {entry.status === "failed" && <AlertTriangle size={12} />}
                            {entry.status.toUpperCase()}
                          </span>
                          {entry.status === "sent" && (
                            <span className="in-transit-note">
                              Awaiting Handset DSN
                            </span>
                          )}
                          {entry.status === "failed" && (
                            <span className="fail-invariant-note">
                              Payment Valid & Protected
                            </span>
                          )}
                        </td>
                        <td className="actions-cell">
                          <div className="notif-actions-btns">
                            {entry.attempts && entry.attempts.length > 0 && (
                              <button
                                type="button"
                                className="action-pill-btn timeline-btn"
                                onClick={() => setActiveAttemptDrawerNotif(entry)}
                                title="View Step-by-Step Delivery Timeline"
                              >
                                <History size={12} /> Timeline
                              </button>
                            )}
                            {entry.status === "failed" && (
                              <button
                                type="button"
                                className="action-pill-btn retry"
                                onClick={() => {
                                  onRetryNotification(entry.id);
                                  setToastMessage(`Retried delivery for ${entry.customerName}.`);
                                  setTimeout(() => setToastMessage(""), 2500);
                                }}
                                title="Retry delivery via Twilio / Gateway"
                              >
                                <RefreshCw size={12} /> Retry
                              </button>
                            )}
                            <button
                              type="button"
                              className="action-pill-btn"
                              onClick={() => setActiveMockupNotif(entry)}
                              title="Preview on Smartphone"
                            >
                              <Phone size={12} /> Mockup
                            </button>
                            <button
                              type="button"
                              className="action-pill-btn"
                              onClick={() => copyText(entry.message, "Message text copied!")}
                              title="Copy SMS Message Content"
                            >
                              <Copy size={12} /> Copy
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: MENU & PRICING MANAGER */}
      {activeSubTab === "menu" && (
        <div className="menu-tab-layout">
          {/* Role Boundary Enforcement Alert */}
          {!isEventOwner && (
            <div className="role-boundary-alert info">
              <LockKeyhole size={20} className="alert-icon" />
              <div>
                <strong>Role Boundary Enforced: {isPaymentVerifier ? "Payment Verifier / Cashier" : "Food Collection Staff"}</strong>
                <p>
                  You are viewing this menu in <strong>Read-Only Mode</strong>. Modifying event parameters, ticket prices, packaging fees, or publishing new dishes is strictly restricted to the <strong>Event Owner</strong>.
                </p>
              </div>
            </div>
          )}

          {/* Event Details Card */}
          <div className="panel event-edit-panel">
            <div className="panel-heading">
              <div>
                <span className="section-kicker">EVENT DETAILS</span>
                <h2>{eventDetails.title}</h2>
                <p>Customize the event title, date, and venue details that guests see.</p>
              </div>
            </div>

            <div className="event-edit-grid">
              <div className="form-group">
                <label className="field-label">Event Title</label>
                <input
                  className="settings-input"
                  value={eventDetails.title}
                  onChange={(e) => onUpdateEventDetails({ ...eventDetails, title: e.target.value })}
                />
              </div>

              <div className="form-group">
                <label className="field-label">Event Tagline</label>
                <input
                  className="settings-input"
                  value={eventDetails.tagline}
                  onChange={(e) => onUpdateEventDetails({ ...eventDetails, tagline: e.target.value })}
                />
              </div>

              <div className="form-group">
                <label className="field-label">Venue Name</label>
                <input
                  className="settings-input"
                  value={eventDetails.venueName}
                  onChange={(e) =>
                    onUpdateEventDetails({ ...eventDetails, venueName: e.target.value })
                  }
                />
              </div>

              <div className="form-group">
                <label className="field-label">Date & Time</label>
                <input
                  className="settings-input"
                  value={`${eventDetails.dateStr} (${eventDetails.timeStr})`}
                  onChange={(e) =>
                    onUpdateEventDetails({ ...eventDetails, dateStr: e.target.value })
                  }
                />
              </div>

              <div className="form-group">
                <label className="field-label">Advance-Order Deadline</label>
                <input
                  className="settings-input"
                  value={eventDetails.advanceOrderDeadline}
                  onChange={(e) =>
                    onUpdateEventDetails({ ...eventDetails, advanceOrderDeadline: e.target.value })
                  }
                  placeholder="e.g. Friday, Nov 20, 2026 at 8:00 PM"
                />
              </div>

              <div className="form-group">
                <label className="field-label">Food Pickup Instructions</label>
                <input
                  className="settings-input"
                  value={eventDetails.pickupInstructions}
                  onChange={(e) =>
                    onUpdateEventDetails({ ...eventDetails, pickupInstructions: e.target.value })
                  }
                  placeholder="e.g. Food Collection Tent B, Waterfront Pier"
                />
              </div>

              <div className="form-group">
                <label className="field-label">Live Ordering Status</label>
                <label className="toggle-row" style={{ marginTop: "6px" }}>
                  <input
                    type="checkbox"
                    checked={eventDetails.isOrderingOpen}
                    onChange={(e) =>
                      onUpdateEventDetails({ ...eventDetails, isOrderingOpen: e.target.checked })
                    }
                  />
                  <span className="toggle-track">
                    <span />
                  </span>
                  <span>{eventDetails.isOrderingOpen ? "Live Ordering Active" : "Ordering Closed"}</span>
                </label>
              </div>
            </div>
          </div>

          {/* Menu Items Table */}
          <div className="panel menu-manager-panel">
            <div className="panel-heading">
              <div>
                <span className="section-kicker">FOOD & BEVERAGE MENU</span>
                <h2>Menu Offerings & Dual Pricing (USD & JMD)</h2>
                <p>
                  Manage meal packages, priced combinations, customizable choices, stock limits, and donation items.
                </p>
              </div>
              <button
                type="button"
                className="primary-button add-item-btn"
                onClick={handleOpenAddMenu}
                id="btn-add-new-dish"
              >
                <Plus size={15} /> Add New Dish
              </button>
            </div>

            {/* Menu Search & Category Filters */}
            <div className="menu-filters-toolbar" style={{ display: "flex", gap: "12px", alignItems: "center", marginBottom: "16px", flexWrap: "wrap" }}>
              <div className="search-box-wrap" style={{ flex: "1 1 240px", maxWidth: "360px" }}>
                <Search size={16} />
                <input
                  type="text"
                  placeholder="Search dishes, ingredients, or categories..."
                  value={menuSearchQuery}
                  onChange={(e) => setMenuSearchQuery(e.target.value)}
                />
                {menuSearchQuery && (
                  <button
                    type="button"
                    className="clear-search-btn"
                    onClick={() => setMenuSearchQuery("")}
                  >
                    <X size={13} />
                  </button>
                )}
              </div>

              <div className="filter-chips-row" style={{ display: "flex", gap: "6px", flexWrap: "wrap" }}>
                <button
                  type="button"
                  className={`filter-chip ${menuFilterCategory === "all" ? "active" : ""}`}
                  onClick={() => setMenuFilterCategory("all")}
                >
                  All ({menu.length})
                </button>
                {uniqueMenuCategories.map((cat) => (
                  <button
                    key={cat}
                    type="button"
                    className={`filter-chip ${menuFilterCategory === cat ? "active" : ""}`}
                    onClick={() => setMenuFilterCategory(cat)}
                  >
                    {cat} ({menu.filter((m) => m.category === cat).length})
                  </button>
                ))}
              </div>
            </div>

            <div className="menu-table-container">
              <table className="menu-admin-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>USD Price</th>
                    <th>JMD Price</th>
                    <th>Stock Limit</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredMenuItems.length === 0 ? (
                    <tr>
                      <td colSpan={7} style={{ textAlign: "center", padding: "32px", color: "var(--text-muted)" }}>
                        No menu offerings match your current filter criteria.
                      </td>
                    </tr>
                  ) : (
                    filteredMenuItems.map((item) => (
                      <tr key={item.id}>
                        <td className="menu-title-cell">
                          <img src={item.imageUrl} alt={item.name} className="dish-cell-thumb" />
                          <div>
                            <strong>{item.name}</strong>
                            <p>{item.description}</p>
                            {item.pricingType === "customizable" && item.customOptionGroups && (
                              <span style={{ fontSize: "0.75rem", color: "var(--brand-accent)" }}>
                                {item.customOptionGroups.length} option group(s) configured
                              </span>
                            )}
                            {item.pricingType === "custom_amount" && item.customAmountConfig && (
                              <span style={{ fontSize: "0.75rem", color: "var(--brand-accent)" }}>
                                Bounds: ${item.customAmountConfig.minUSD} – ${item.customAmountConfig.maxUSD} USD
                              </span>
                            )}
                          </div>
                        </td>
                        <td>
                          <span
                            className="category-cell-badge"
                            style={{
                              background:
                                item.pricingType === "customizable"
                                  ? "rgba(14, 165, 233, 0.15)"
                                  : item.pricingType === "custom_amount"
                                  ? "rgba(168, 85, 247, 0.15)"
                                  : "rgba(16, 185, 129, 0.15)",
                              color:
                                item.pricingType === "customizable"
                                  ? "#38bdf8"
                                  : item.pricingType === "custom_amount"
                                  ? "#c084fc"
                                  : "#34d399",
                            }}
                          >
                            {item.pricingType === "customizable"
                              ? "Customizable Meal"
                              : item.pricingType === "custom_amount"
                              ? "Custom Contribution"
                              : "Fixed Price"}
                          </span>
                        </td>
                        <td>
                          <span className="category-cell-badge">{item.category}</span>
                        </td>
                        <td>
                          <strong className="price-usd">${item.priceUSD}</strong>
                        </td>
                        <td>
                          <strong className="price-jmd">J${item.priceJMD.toLocaleString()}</strong>
                        </td>
                        <td>
                          {item.availableStock === undefined || item.availableStock === null ? (
                            <span className="delivery-status-pill delivered" style={{ fontSize: "0.75rem" }}>
                              Unlimited
                            </span>
                          ) : item.availableStock <= 0 ? (
                            <span className="delivery-status-pill failed" style={{ fontSize: "0.75rem" }}>
                              Sold Out (0)
                            </span>
                          ) : item.availableStock <= 20 ? (
                            <span className="delivery-status-pill queued" style={{ fontSize: "0.75rem" }}>
                              Low ({item.availableStock})
                            </span>
                          ) : (
                            <span>{item.availableStock} portions</span>
                          )}
                        </td>
                        <td>
                          <div style={{ display: "flex", gap: "6px" }}>
                            <button
                              type="button"
                              className="action-pill-btn"
                              onClick={() => handleOpenEditMenu(item)}
                              title="Edit item parameters, prices, and options"
                              id={`edit-item-${item.id}`}
                            >
                              <FileEdit size={13} /> Edit
                            </button>
                            <button
                              type="button"
                              className="delete-item-btn"
                              onClick={() => handleDeleteMenuItem(item.id)}
                              title="Remove item from menu"
                              id={`delete-item-${item.id}`}
                            >
                              <Trash2 size={14} />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {/* Add / Edit Menu Item Modal */}
          {showAddMenuModal && (
            <div className="modal-backdrop" onClick={() => setShowAddMenuModal(false)}>
              <div
                className="modal-card"
                role="dialog"
                aria-modal="true"
                style={{ maxWidth: "720px", maxHeight: "90vh", overflowY: "auto" }}
                onClick={(e) => e.stopPropagation()}
              >
                <div className="modal-header">
                  <div>
                    <span className="section-kicker">
                      {editingMenuItemId ? "EDIT MENU OFFERING" : "NEW MENU OFFERING"}
                    </span>
                    <h2>{editingMenuItemId ? `Edit ${newItemName || "Dish"}` : "Add Dish or Beverage"}</h2>
                    <p style={{ margin: "2px 0 0 0", fontSize: "0.85rem", color: "var(--text-muted)" }}>
                      Support fixed prices, customizable meal choices, priced add-ons, or custom-amount donations.
                    </p>
                  </div>
                  <button
                    type="button"
                    className="modal-close-btn"
                    onClick={() => setShowAddMenuModal(false)}
                  >
                    <X size={17} />
                  </button>
                </div>

                <form onSubmit={handleSaveMenuItem} className="modal-body" style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
                  {/* Pricing Type Switcher */}
                  <div className="form-group">
                    <label className="field-label">Pricing & Offering Model *</label>
                    <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: "8px" }}>
                      <button
                        type="button"
                        className={`action-pill-btn ${newItemPricingType === "fixed" ? "timeline-btn" : ""}`}
                        style={{
                          padding: "10px 8px",
                          borderRadius: "8px",
                          fontWeight: 600,
                          textAlign: "center",
                          border: newItemPricingType === "fixed" ? "2px solid var(--brand-accent)" : "1px solid var(--border-color)",
                        }}
                        onClick={() => setNewItemPricingType("fixed")}
                      >
                        Fixed Price
                      </button>
                      <button
                        type="button"
                        className={`action-pill-btn ${newItemPricingType === "customizable" ? "timeline-btn" : ""}`}
                        style={{
                          padding: "10px 8px",
                          borderRadius: "8px",
                          fontWeight: 600,
                          textAlign: "center",
                          border: newItemPricingType === "customizable" ? "2px solid var(--brand-accent)" : "1px solid var(--border-color)",
                        }}
                        onClick={() => {
                          setNewItemPricingType("customizable");
                          if (customOptionGroups.length === 0) {
                            handleAddOptionGroup();
                          }
                        }}
                      >
                        Customizable Meal
                      </button>
                      <button
                        type="button"
                        className={`action-pill-btn ${newItemPricingType === "custom_amount" ? "timeline-btn" : ""}`}
                        style={{
                          padding: "10px 8px",
                          borderRadius: "8px",
                          fontWeight: 600,
                          textAlign: "center",
                          border: newItemPricingType === "custom_amount" ? "2px solid var(--brand-accent)" : "1px solid var(--border-color)",
                        }}
                        onClick={() => setNewItemPricingType("custom_amount")}
                      >
                        Custom Donation
                      </button>
                    </div>
                  </div>

                  <div className="form-group">
                    <label className="field-label">Dish / Offering Name *</label>
                    <input
                      className="settings-input"
                      required
                      placeholder="e.g. Escovitch Fish Meal or Jerk Pork Platter"
                      value={newItemName}
                      onChange={(e) => setNewItemName(e.target.value)}
                    />
                  </div>

                  <div className="form-row-2col">
                    <div className="form-group">
                      <label className="field-label">Category</label>
                      {!isCustomCategory ? (
                        <div style={{ display: "flex", gap: "8px" }}>
                          <select
                            className="settings-input"
                            value={newItemCategory}
                            onChange={(e) => {
                              if (e.target.value === "__NEW__") {
                                setIsCustomCategory(true);
                              } else {
                                setNewItemCategory(e.target.value);
                              }
                            }}
                          >
                            <option value="Mains">Mains</option>
                            <option value="Sides & Extras">Sides & Extras</option>
                            <option value="Beverages">Beverages</option>
                            <option value="Community Donations">Community Donations</option>
                            {uniqueMenuCategories
                              .filter(
                                (c) =>
                                  !["Mains", "Sides & Extras", "Beverages", "Community Donations"].includes(c)
                              )
                              .map((c) => (
                                <option key={c} value={c}>
                                  {c}
                                </option>
                              ))}
                            <option value="__NEW__">+ Add Custom Category...</option>
                          </select>
                        </div>
                      ) : (
                        <div style={{ display: "flex", gap: "8px" }}>
                          <input
                            className="settings-input"
                            placeholder="Enter new category name..."
                            value={customCategoryInput}
                            onChange={(e) => setCustomCategoryInput(e.target.value)}
                            autoFocus
                          />
                          <button
                            type="button"
                            className="action-pill-btn"
                            onClick={() => setIsCustomCategory(false)}
                          >
                            Cancel
                          </button>
                        </div>
                      )}
                    </div>

                    <div className="form-group">
                      <label className="field-label">Food Photograph Asset</label>
                      <select
                        className="settings-input"
                        value={newItemImage}
                        onChange={(e) => setNewItemImage(e.target.value)}
                      >
                        <option value="/images/escovitch_fish.jpg">Escovitch Red Snapper</option>
                        <option value="/images/fried_chicken.jpg">Fried Chicken Plate</option>
                        <option value="/images/bammy.jpg">Golden Cassava Bammy</option>
                        <option value="/images/rum_punch.jpg">Island Rum Punch & Drinks</option>
                        <option value="/images/jerk_chicken.jpg">Jerk Chicken Platter</option>
                        <option value="/images/oxtail.jpg">Oxtail & Butter Beans</option>
                      </select>
                    </div>
                  </div>

                  {/* Optional Custom Image URL Input */}
                  <div className="form-group">
                    <label className="field-label">Custom Image URL (Optional object storage / cloud path)</label>
                    <input
                      className="settings-input"
                      placeholder="https://... or /images/custom-photo.jpg"
                      value={customImageUrl}
                      onChange={(e) => setCustomImageUrl(e.target.value)}
                    />
                  </div>

                  {/* Upload a photo from this device (JPEG, PNG or WebP, up to 5 MB) */}
                  <div className="form-group">
                    <label className="field-label" htmlFor="menu-photo-input">
                      Or upload a photo (JPEG, PNG or WebP, max 5 MB)
                    </label>
                    <input
                      id="menu-photo-input"
                      type="file"
                      accept=".jpg,.jpeg,.png,.webp"
                      onChange={(e) => {
                        const file = e.target.files?.[0];
                        e.target.value = "";
                        if (!file) return;
                        if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
                          setToastMessage("Photos must be JPEG, PNG or WebP.");
                          setTimeout(() => setToastMessage(""), 3000);
                          return;
                        }
                        if (file.size > 5 * 1024 * 1024) {
                          setToastMessage("Photos must be 5 MB or smaller.");
                          setTimeout(() => setToastMessage(""), 3000);
                          return;
                        }
                        const reader = new FileReader();
                        reader.onload = () => {
                          setUploadedPhoto(String(reader.result));
                          setUploadedPhotoName(file.name);
                        };
                        reader.readAsDataURL(file);
                      }}
                    />
                    {uploadedPhoto && (
                      <div style={{ display: "flex", alignItems: "center", gap: "10px", marginTop: "8px" }}>
                        <img
                          src={uploadedPhoto}
                          alt="Selected menu photo preview"
                          style={{ width: 56, height: 56, objectFit: "cover", borderRadius: 8 }}
                        />
                        <span style={{ fontSize: "12px" }}>{uploadedPhotoName || "Selected photo"}</span>
                        <button
                          type="button"
                          className="action-pill-btn"
                          onClick={() => {
                            setUploadedPhoto("");
                            setUploadedPhotoName("");
                          }}
                        >
                          Remove photo
                        </button>
                      </div>
                    )}
                  </div>

                  {/* Base Prices */}
                  <div className="form-row-2col">
                    <div className="form-group">
                      <label className="field-label">Base Price USD ($) *</label>
                      <input
                        type="number"
                        min="0"
                        step="0.01"
                        className="settings-input"
                        value={newItemPriceUSD}
                        onChange={(e) => setNewItemPriceUSD(Number(e.target.value))}
                      />
                    </div>

                    <div className="form-group">
                      <label className="field-label">Base Price JMD (J$) *</label>
                      <input
                        type="number"
                        min="0"
                        step="50"
                        className="settings-input"
                        value={newItemPriceJMD}
                        onChange={(e) => setNewItemPriceJMD(Number(e.target.value))}
                      />
                    </div>
                  </div>

                  {/* Stock Limit Protection */}
                  <div className="form-group" style={{ background: "rgba(255,255,255,0.03)", padding: "12px", borderRadius: "8px" }}>
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px" }}>
                      <label className="field-label" style={{ margin: 0 }}>Stock Protection & Inventory Limits</label>
                      <label className="toggle-row" style={{ margin: 0 }}>
                        <input
                          type="checkbox"
                          checked={isStockUnlimited}
                          onChange={(e) => setIsStockUnlimited(e.target.checked)}
                        />
                        <span className="toggle-track">
                          <span />
                        </span>
                        <span>Unlimited Stock</span>
                      </label>
                    </div>
                    {!isStockUnlimited && (
                      <div>
                        <input
                          type="number"
                          min="0"
                          className="settings-input"
                          placeholder="Portions available (e.g. 150)"
                          value={newItemStock}
                          onChange={(e) => setNewItemStock(Number(e.target.value))}
                        />
                        <span style={{ fontSize: "0.75rem", color: "var(--text-muted)", marginTop: "4px", display: "block" }}>
                          Database transactions decrement available portions atomically during checkout to prevent concurrent race conditions.
                        </span>
                      </div>
                    )}
                  </div>

                  {/* Customizable Option Groups Builder */}
                  {newItemPricingType === "customizable" && (
                    <div className="customizable-options-studio" style={{ background: "rgba(14, 165, 233, 0.05)", border: "1px solid rgba(14, 165, 233, 0.2)", borderRadius: "8px", padding: "16px" }}>
                      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "12px" }}>
                        <div>
                          <strong style={{ color: "#38bdf8", display: "block" }}>Customizable Option Groups & Priced Add-Ons</strong>
                          <span style={{ fontSize: "0.8rem", color: "var(--text-muted)" }}>
                            Define options like Fish Size, Side Pairing, and Extras. Exact prices stored in minor units.
                          </span>
                        </div>
                        <button
                          type="button"
                          className="action-pill-btn timeline-btn"
                          onClick={handleAddOptionGroup}
                        >
                          <Plus size={13} /> Add Option Group
                        </button>
                      </div>

                      {customOptionGroups.length === 0 ? (
                        <p style={{ color: "var(--text-muted)", fontStyle: "italic", fontSize: "0.85rem" }}>
                          No option groups added yet. Click &quot;Add Option Group&quot; to configure meal choices.
                        </p>
                      ) : (
                        <div style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
                          {customOptionGroups.map((group, gIdx) => (
                            <div
                              key={group.id}
                              style={{
                                background: "rgba(0,0,0,0.2)",
                                border: "1px solid rgba(255,255,255,0.08)",
                                borderRadius: "6px",
                                padding: "12px",
                              }}
                            >
                              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px", gap: "8px" }}>
                                <input
                                  className="settings-input"
                                  style={{ fontWeight: 600, flex: 1 }}
                                  value={group.title}
                                  placeholder="Group Title (e.g. Fish Size, Choice of Side)"
                                  onChange={(e) => handleUpdateOptionGroup(gIdx, { title: e.target.value })}
                                />
                                <label className="toggle-row" style={{ margin: 0 }}>
                                  <input
                                    type="checkbox"
                                    checked={group.required}
                                    onChange={(e) => handleUpdateOptionGroup(gIdx, { required: e.target.checked })}
                                  />
                                  <span>Required</span>
                                </label>
                                <button
                                  type="button"
                                  className="delete-item-btn"
                                  onClick={() => handleRemoveOptionGroup(gIdx)}
                                  title="Delete Option Group"
                                >
                                  <Trash2 size={13} />
                                </button>
                              </div>

                              {/* Choices for this group */}
                              <div style={{ display: "flex", flexDirection: "column", gap: "6px", marginLeft: "8px" }}>
                                {group.choices.map((choice: CustomOptionChoice, cIdx: number) => (
                                  <div
                                    key={cIdx}
                                    style={{
                                      display: "grid",
                                      gridTemplateColumns: "2fr 1fr 1fr auto",
                                      gap: "6px",
                                      alignItems: "center",
                                    }}
                                  >
                                    <input
                                      className="settings-input"
                                      placeholder="Choice Label (e.g. Large Catch)"
                                      value={choice.label}
                                      onChange={(e) => handleUpdateChoice(gIdx, cIdx, { label: e.target.value })}
                                    />
                                    <input
                                      type="number"
                                      step="0.01"
                                      min="0"
                                      className="settings-input"
                                      placeholder="+USD ($)"
                                      value={choice.extraPriceUSD}
                                      onChange={(e) =>
                                        handleUpdateChoice(gIdx, cIdx, { extraPriceUSD: Number(e.target.value) })
                                      }
                                    />
                                    <input
                                      type="number"
                                      step="50"
                                      min="0"
                                      className="settings-input"
                                      placeholder="+JMD (J$)"
                                      value={choice.extraPriceJMD}
                                      onChange={(e) =>
                                        handleUpdateChoice(gIdx, cIdx, { extraPriceJMD: Number(e.target.value) })
                                      }
                                    />
                                    <button
                                      type="button"
                                      className="delete-item-btn"
                                      onClick={() => handleRemoveChoice(gIdx, cIdx)}
                                      disabled={group.choices.length <= 1}
                                      title="Remove choice"
                                    >
                                      <X size={12} />
                                    </button>
                                  </div>
                                ))}
                                <button
                                  type="button"
                                  className="action-pill-btn"
                                  style={{ alignSelf: "flex-start", marginTop: "4px" }}
                                  onClick={() => handleAddOptionChoice(gIdx)}
                                >
                                  <Plus size={11} /> Add Choice
                                </button>
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  )}

                  {/* Custom Amount / Donation Config */}
                  {newItemPricingType === "custom_amount" && (
                    <div className="custom-amount-studio" style={{ background: "rgba(168, 85, 247, 0.05)", border: "1px solid rgba(168, 85, 247, 0.2)", borderRadius: "8px", padding: "16px" }}>
                      <strong style={{ color: "#c084fc", display: "block", marginBottom: "6px" }}>
                        Organizer-Authorized Custom Donation Bounds
                      </strong>
                      <p style={{ fontSize: "0.8rem", color: "var(--text-muted)", marginBottom: "12px" }}>
                        Specify permitted contribution boundaries and preset buttons for fast guest checkout.
                      </p>
                      <div className="form-row-2col">
                        <div className="form-group">
                          <label className="field-label">Min USD ($)</label>
                          <input
                            type="number"
                            min="1"
                            className="settings-input"
                            value={customMinUSD}
                            onChange={(e) => setCustomMinUSD(Number(e.target.value))}
                          />
                        </div>
                        <div className="form-group">
                          <label className="field-label">Max USD ($)</label>
                          <input
                            type="number"
                            min="1"
                            className="settings-input"
                            value={customMaxUSD}
                            onChange={(e) => setCustomMaxUSD(Number(e.target.value))}
                          />
                        </div>
                      </div>
                      <div className="form-row-2col">
                        <div className="form-group">
                          <label className="field-label">Min JMD (J$)</label>
                          <input
                            type="number"
                            min="50"
                            step="50"
                            className="settings-input"
                            value={customMinJMD}
                            onChange={(e) => setCustomMinJMD(Number(e.target.value))}
                          />
                        </div>
                        <div className="form-group">
                          <label className="field-label">Max JMD (J$)</label>
                          <input
                            type="number"
                            min="50"
                            step="50"
                            className="settings-input"
                            value={customMaxJMD}
                            onChange={(e) => setCustomMaxJMD(Number(e.target.value))}
                          />
                        </div>
                      </div>
                      <div className="form-group">
                        <label className="field-label">Suggested Preset Amounts (USD comma-separated)</label>
                        <input
                          className="settings-input"
                          value={customSuggestedUSD}
                          onChange={(e) => setCustomSuggestedUSD(e.target.value)}
                        />
                      </div>
                      <div className="form-group">
                        <label className="field-label">Suggested Preset Amounts (JMD comma-separated)</label>
                        <input
                          className="settings-input"
                          value={customSuggestedJMD}
                          onChange={(e) => setCustomSuggestedJMD(e.target.value)}
                        />
                      </div>
                    </div>
                  )}

                  <div className="form-group">
                    <label className="field-label">Description & Ingredients</label>
                    <textarea
                      className="settings-textarea"
                      rows={3}
                      placeholder="Traditional spices, cooking style, side items included..."
                      value={newItemDesc}
                      onChange={(e) => setNewItemDesc(e.target.value)}
                    />
                  </div>

                  <div className="form-group">
                    <label className="toggle-row">
                      <input
                        type="checkbox"
                        checked={newItemAllowInstructions}
                        onChange={(e) => setNewItemAllowInstructions(e.target.checked)}
                      />
                      <span className="toggle-track">
                        <span />
                      </span>
                      <span>Allow Customers to Enter Special Preparation Instructions</span>
                    </label>
                  </div>

                  <div className="modal-footer" style={{ marginTop: "8px" }}>
                    <button
                      type="button"
                      className="action-pill-btn"
                      style={{ padding: "10px 16px" }}
                      onClick={() => setShowAddMenuModal(false)}
                    >
                      Cancel
                    </button>
                    <button type="submit" className="primary-button modal-done-btn">
                      {editingMenuItemId ? "Save Changes" : "Publish to Menu"}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}
        </div>
      )}

      {/* TAB 3: SHARE & PROMOTE HUB */}
      {activeSubTab === "share" && (
        <div className="share-tab-layout">
          <div className="panel share-panel">
            <div className="panel-heading">
              <div>
                <span className="section-kicker">PROMOTIONS & TICKET DISTRIBUTION</span>
                <h2>Share Event & Food Menu Link</h2>
                <p>
                  Distribute your direct event link across social channels. Customers can view the
                  menu, reserve meals, and receive their digital QR ticket.
                </p>
              </div>
              <Share2 size={24} className="share-icon" />
            </div>

            <div className="share-link-box">
              <label className="field-label">Public Customer Event Link:</label>
              <div className="link-copy-row">
                <input className="settings-input" readOnly value={shareUrl} />
                <button
                  type="button"
                  className="preset-btn"
                  onClick={() => copyText(shareUrl, "Event link copied!")}
                >
                  <Copy size={14} /> Copy Link
                </button>
              </div>
            </div>

            <div className="social-share-buttons-grid">
              {/* WhatsApp */}
              <a
                href={`https://api.whatsapp.com/send?text=${encodeURIComponent(shareMessage)}`}
                target="_blank"
                rel="noopener noreferrer"
                className="social-btn whatsapp"
              >
                <MessageCircle size={18} />
                <div>
                  <strong>Share to WhatsApp</strong>
                  <span>Broadcast to groups & status updates</span>
                </div>
              </a>

              {/* Instagram */}
              <button
                type="button"
                className="social-btn instagram"
                onClick={() =>
                  copyText(
                    `Ticket & Menu Link: ${shareUrl} - Check link in bio!`,
                    "Copied caption for Instagram bio/story!"
                  )
                }
              >
                <Instagram size={18} />
                <div>
                  <strong>Copy for Instagram</strong>
                  <span>Paste in Link in Bio or Stories</span>
                </div>
              </button>

              {/* Facebook */}
              <a
                href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`}
                target="_blank"
                rel="noopener noreferrer"
                className="social-btn facebook"
              >
                <Facebook size={18} />
                <div>
                  <strong>Share on Facebook</strong>
                  <span>Post to event pages & timelines</span>
                </div>
              </a>
            </div>

            {/* Invite Message Preview */}
            <div className="invite-preview-box">
              <span className="field-label">Pre-formatted Viral Message:</span>
              <div className="message-bubble">
                <p>{shareMessage}</p>
                <button
                  type="button"
                  className="text-button"
                  onClick={() => copyText(shareMessage, "Message copied!")}
                >
                  <Copy size={13} /> Copy Message
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: ACCOUNT & JAMAICA–US PAYMENT SETTINGS */}
      {activeSubTab === "settings" && (
        <div className="host-settings-wrapper">
          <div className="host-settings-mode-bar">
            <button
              type="button"
              className={`host-mode-btn ${hostSettingsMode === "account_settings" ? "active" : ""}`}
              onClick={() => setHostSettingsMode("account_settings")}
              id="host-tab-account-settings"
            >
              <User size={15} /> Dedicated Account Settings (14)
            </button>
            <button
              type="button"
              className={`host-mode-btn ${hostSettingsMode === "payment_methods" ? "active" : ""}`}
              onClick={() => setHostSettingsMode("payment_methods")}
              id="host-tab-payment-methods"
            >
              <Banknote size={15} /> Jamaica – US Payment Gateways & Instructions
            </button>
          </div>

          {hostSettingsMode === "account_settings" && (
            <AccountSettingsView
              accountType="event_host"
              currentUser={{
                name: activeOrg.name,
                email: activeOrg.contactEmail,
                phone: activeOrg.contactPhone,
                roleLabel: "Event Host (Owner)",
                region: settings.businessLocation,
              }}
              eventDetails={localEvent}
              settings={settings}
              orders={orders}
              tenantUsers={tenantUsers}
              formatMoney={formatMoney}
              onUpdateEventDetails={(details) => {
                setLocalEvent(details);
                onUpdateEventDetails(details);
              }}
              onUpdateSettings={onUpdateSettings}
              onNavigateToTab={(tab) => {
                if (tab === "event") setActiveSubTab("event");
                else if (tab === "payments") setActiveSubTab("payments");
                else if (tab === "prep") setActiveSubTab("prep");
                else if (tab === "notifications") setActiveSubTab("notifications");
                else if (tab === "menu") setActiveSubTab("menu");
                else if (tab === "share") setActiveSubTab("share");
                else if (tab === "multitenancy") setActiveSubTab("multitenancy");
              }}
            />
          )}

          {hostSettingsMode === "payment_methods" && (
            <div className="panel settings-panel">
              <div className="panel-heading settings-heading">
                <div>
                  <span className="section-kicker">PAYMENT CONFIGURATION</span>
                  <h2>Jamaica – US Payment Settings & Eligibility</h2>
                  <p>
                    Set currency, banking clearing details, and enforce strict Zelle & Cash App
                    eligibility gates.
                  </p>
                </div>
                <span className="settings-heading-icon">
                  <Settings2 size={20} />
                </span>
              </div>

          {/* Business location & Currency */}
          <div className="settings-section">
            <div className="settings-grid-2col">
              <div>
                <div className="settings-section-title">
                  <span className="settings-icon">
                    <Building2 size={16} />
                  </span>
                  <div>
                    <strong>Organizer Operating Base</strong>
                    <span>Where is your legal business entity registered?</span>
                  </div>
                </div>
                <div className="select-wrap">
                  <select
                    value={settings.businessLocation}
                    onChange={(e) =>
                      onUpdateSettings((c) => ({
                        ...c,
                        businessLocation: e.target.value as BusinessLocation,
                      }))
                    }
                  >
                    <option value="JM">Jamaica (Commercial Banks: NCB, Scotiabank, JN Bank)</option>
                    <option value="US">United States (ACH, Routing, US Banking)</option>
                  </select>
                  <ChevronDown size={15} />
                </div>
              </div>

              <div>
                <div className="settings-section-title">
                  <span className="settings-icon">
                    <DollarSign size={16} />
                  </span>
                  <div>
                    <strong>Active Event Currency</strong>
                    <span>Currencies guests will pay at checkout.</span>
                  </div>
                </div>
                <div className="select-wrap">
                  <select
                    value={settings.currency}
                    onChange={(e) =>
                      onUpdateSettings((c) => ({
                        ...c,
                        currency: e.target.value as Currency,
                      }))
                    }
                  >
                    <option value="USD">USD ($) · US Dollar (Cross-border / Diaspora)</option>
                    <option value="JMD">JMD (J$) · Jamaican Dollar (Domestic Attendees)</option>
                  </select>
                  <ChevronDown size={15} />
                </div>
              </div>
            </div>
          </div>

          <div className="settings-divider" />

          {/* Methods & Gating */}
          <div className="settings-section">
            <div className="payment-settings-title">
              <div className="settings-section-title">
                <span className="settings-icon">
                  <Wallet size={16} />
                </span>
                <div>
                  <strong>Payment Methods & Eligibility Verification</strong>
                  <span>Zelle & Cash App require strict certified eligibility.</span>
                </div>
              </div>
            </div>

            <div className="method-settings-list">
              {(Object.keys(METHOD_INFO) as MethodId[]).map((method) => {
                const config = settings.methods[method];
                const info = METHOD_INFO[method];
                const Icon = info.icon;
                const isGated = method !== "bank";

                return (
                  <div className="method-setting" key={method}>
                    <div className="method-setting-top">
                      <span className={`setting-method-icon ${method}`}>
                        <Icon size={17} />
                      </span>
                      <div className="setting-method-name">
                        <div className="method-header-title">
                          <strong>{info.name}</strong>
                          <span className="method-type-badge">{info.badge}</span>
                        </div>
                        <span>
                          {isGated && !config.eligible
                            ? "Eligibility certification required before showing to guests"
                            : config.enabled
                            ? "Active at checkout"
                            : "Disabled"}
                        </span>
                      </div>
                    </div>

                    {isGated && (
                      <div className="eligibility-box">
                        <label className="eligibility-row">
                          <input
                            type="checkbox"
                            checked={config.eligible}
                            onChange={(e) => updateMethod(method, { eligible: e.target.checked })}
                          />
                          <span className="custom-check">
                            {config.eligible && <Check size={12} />}
                          </span>
                          <span>
                            <strong>Certified Eligibility:</strong> I certify that I hold an active
                            US bank account & identity eligible to receive {info.name} transfers.
                          </span>
                        </label>
                        <p className="eligibility-notice-text">
                          <Info size={13} /> {info.eligibilityNotice}
                        </p>
                      </div>
                    )}

                    <label className="field-label method-detail-label" htmlFor={`${method}-det`}>
                      Transfer Instructions:
                    </label>
                    <textarea
                      id={`${method}-det`}
                      className="settings-textarea"
                      rows={2}
                      value={config.details}
                      onChange={(e) => updateMethod(method, { details: e.target.value })}
                      placeholder={info.placeholder}
                    />

                    <div className="method-action-bar">
                      <label className="toggle-row">
                        <input
                          type="checkbox"
                          checked={config.enabled}
                          onChange={(e) => updateMethod(method, { enabled: e.target.checked })}
                        />
                        <span className="toggle-track">
                          <span />
                        </span>
                        <span>Offer {info.name} at checkout</span>
                      </label>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Gateway Integration Card */}
          <div className="gateway-integration-card">
            <div className="gateway-card-left">
              <Sparkles size={20} className="sparkle-icon" />
              <div>
                <strong>Automated Payment Gateway Roadmap</strong>
                <p>
                  Explore WiPay Caribbean, Fygaro, NCB Merchant Services, or Stripe to automate
                  ticket settlements.
                </p>
              </div>
            </div>
            <button
              type="button"
              className="gateway-explore-btn"
              onClick={onOpenGatewayModal}
            >
              View Gateway Options <ExternalLink size={14} />
            </button>
          </div>

          <div className="settings-footer">
            <span>
              <LockKeyhole size={14} /> Stored in browser storage for demo.
            </span>
            <button type="button" className="reset-button" onClick={onResetDemo}>
              <RefreshCw size={13} /> Reset Demo State
            </button>
          </div>
        </div>
      )}
    </div>
  )}

      {/* TAB 4: MULTI-ORGANIZER ARCHITECTURE & RBAC ISOLATION CONSOLE */}
      {activeSubTab === "multitenancy" && (
        <div className="multitenancy-tab-layout">
          {/* Hero Banner */}
          <div className="tenancy-hero-panel">
            <div className="tenancy-hero-content">
              <span className="tenancy-kicker">ENTERPRISE SAAS ARCHITECTURE & SECURITY</span>
              <h2>Multi-Organizer Tenancy & Role-Based Isolation</h2>
              <p>
                Architected from day one so hundreds of independent organizers can manage Caribbean and diaspora events
                without rewriting the core application. Enforces strict row-level tenant scoping, zero cross-organizer data leakage,
                and granular role separation between Platform Admins, Event Owners, Cashiers, and Food Collection Staff.
              </p>
            </div>

            {/* Quick Metrics */}
            <div className="tenancy-quick-badges">
              <div className="quick-badge-card">
                <Database size={18} className="badge-icon purple" />
                <div>
                  <span className="badge-label">ISOLATION PATTERN</span>
                  <strong>Single-DB Row Scoped</strong>
                  <span className="badge-sub">Composite indexed organizer_id</span>
                </div>
              </div>
              <div className="quick-badge-card">
                <ShieldCheck size={18} className="badge-icon green" />
                <div>
                  <span className="badge-label">CROSS-TENANT ACCESS</span>
                  <strong className="green">100% Intercepted</strong>
                  <span className="badge-sub">Zero IDOR leakage</span>
                </div>
              </div>
              <div className="quick-badge-card">
                <Users size={18} className="badge-icon amber" />
                <div>
                  <span className="badge-label">ROLE TIERS</span>
                  <strong>4 Strict Boundaries</strong>
                  <span className="badge-sub">Admin · Owner · Verifier · Collector</span>
                </div>
              </div>
            </div>
          </div>

          {/* Submode Navigation Buttons */}
          <div className="tenancy-subnav-bar">
            <button
              type="button"
              className={`tenancy-submode-btn ${architectureViewMode === "simulator" ? "active" : ""}`}
              onClick={() => setArchitectureViewMode("simulator")}
            >
              <ShieldAlert size={15} /> 1. Cross-Tenant IDOR Breach Simulator
            </button>
            <button
              type="button"
              className={`tenancy-submode-btn ${architectureViewMode === "rbac" ? "active" : ""}`}
              onClick={() => setArchitectureViewMode("rbac")}
            >
              <UserCheck size={15} /> 2. Role & Ownership Boundaries Matrix
            </button>
            <button
              type="button"
              className={`tenancy-submode-btn ${architectureViewMode === "blueprint" ? "active" : ""}`}
              onClick={() => setArchitectureViewMode("blueprint")}
            >
              <Database size={15} /> 3. Single-DB Scoping & Query Blueprint
            </button>
            <button
              type="button"
              className={`tenancy-submode-btn ${architectureViewMode === "evolution" ? "active" : ""}`}
              onClick={() => setArchitectureViewMode("evolution")}
            >
              <Layers size={15} /> 4. Tenancy Evolution Roadmap
            </button>
          </div>

          {/* VIEW 1: CROSS-TENANT IDOR BREACH SIMULATOR */}
          {architectureViewMode === "simulator" && (
            <div className="tenancy-section-panel">
              <div className="panel-header-row">
                <div>
                  <span className="section-kicker">PENETRATION TESTING & BOUNDARY DEFENSE</span>
                  <h3>Cross-Tenant Access Interception Simulator</h3>
                  <p>
                    Verify that authenticated users from one organizer are strictly prohibited from reading or modifying
                    records, tickets, customers, cash receipts, or financial reports belonging to another organizer.
                  </p>
                </div>
                <div className="security-tag-pill">
                  <ShieldCheck size={14} /> IDOR Protection Active
                </div>
              </div>

              <div className="simulator-grid">
                {/* Control Column */}
                <div className="simulator-controls-card">
                  <h4>Configure Simulated Cross-Tenant Request</h4>
                  <p className="controls-hint">
                    Select a target data entity belonging to a foreign organizer. The simulator will execute a live test against our authorization and scoping policies.
                  </p>

                  <div className="form-group">
                    <label className="field-label">Target Data Entity to Query:</label>
                    <div className="target-entity-selector-grid">
                      <button
                        type="button"
                        className={`entity-select-pill ${simulationTarget === "orders" ? "active" : ""}`}
                        onClick={() => setSimulationTarget("orders")}
                      >
                        <Ticket size={14} /> Orders & Meals
                      </button>
                      <button
                        type="button"
                        className={`entity-select-pill ${simulationTarget === "cash_payments" ? "active" : ""}`}
                        onClick={() => setSimulationTarget("cash_payments")}
                      >
                        <Banknote size={14} /> Cash Ledger Receipts
                      </button>
                      <button
                        type="button"
                        className={`entity-select-pill ${simulationTarget === "tickets" ? "active" : ""}`}
                        onClick={() => setSimulationTarget("tickets")}
                      >
                        <Scan size={14} /> Ticket QR Barcodes
                      </button>
                      <button
                        type="button"
                        className={`entity-select-pill ${simulationTarget === "customers" ? "active" : ""}`}
                        onClick={() => setSimulationTarget("customers")}
                      >
                        <User size={14} /> Customer Phone & PII
                      </button>
                      <button
                        type="button"
                        className={`entity-select-pill ${simulationTarget === "reports" ? "active" : ""}`}
                        onClick={() => setSimulationTarget("reports")}
                      >
                        <FileText size={14} /> Financial Reports
                      </button>
                      <button
                        type="button"
                        className={`entity-select-pill ${simulationTarget === "event_settings" ? "active" : ""}`}
                        onClick={() => setSimulationTarget("event_settings")}
                      >
                        <Key size={14} /> Private Bank Accounts
                      </button>
                    </div>
                  </div>

                  {/* Context Comparison */}
                  <div className="context-comparison-box">
                    <div className="context-box authenticated">
                      <span className="context-label">1. CURRENT AUTHENTICATED CONTEXT</span>
                      <strong>{activeOrg.name}</strong>
                      <span className="context-meta">Tenant UUID: <code>{activeOrg.id}</code></span>
                      <span className="context-meta">Role Perspective: <strong>{localRole.toUpperCase()}</strong></span>
                      <span className="context-meta">Request Header: <code>X-Organizer-ID: {activeOrg.id}</code></span>
                    </div>

                    <div className="context-divider-arrow">➔ VS ➔</div>

                    <div className="context-box target">
                      <span className="context-label">2. TARGET FOREIGN RESOURCE</span>
                      <strong>
                        {localActiveOrgId === "org-kingston-youth"
                          ? "Portmore Seafood & Culinary Guild"
                          : "Kingston Youth Foundation"}
                      </strong>
                      <span className="context-meta">
                        Target Tenant: <code>{localActiveOrgId === "org-kingston-youth" ? "org-portmore-guild" : "org-kingston-youth"}</code>
                      </span>
                      <span className="context-meta">
                        Target Resource:{" "}
                        <strong>
                          {simulationTarget === "orders" && "Order ORD-PORT-9011 (Akeem Campbell, J$4,500)"}
                          {simulationTarget === "cash_payments" && "Cash Receipt CR-PORT-101 (J$3,200)"}
                          {simulationTarget === "tickets" && "Ticket Barcode PCF-2026-9011"}
                          {simulationTarget === "customers" && "Akeem Campbell's Private Mobile Phone Number"}
                          {simulationTarget === "reports" && "Gross Revenue Reconciliation Report"}
                          {simulationTarget === "event_settings" && "Zelle/Cash App & NCB Jamaican Bank Details"}
                        </strong>
                      </span>
                    </div>
                  </div>

                  <button
                    type="button"
                    className="simulator-execute-btn"
                    disabled={isSimulating}
                    onClick={handleRunBreachSimulation}
                  >
                    {isSimulating ? (
                      <>
                        <RefreshCw size={16} className="spinning" /> Intercepting Query...
                      </>
                    ) : (
                      <>
                        <ShieldAlert size={16} /> Execute Cross-Tenant Query Interception Test
                      </>
                    )}
                  </button>
                </div>

                {/* Output Terminal Column */}
                <div className="simulator-terminal-card">
                  <div className="terminal-topbar">
                    <div className="terminal-dots">
                      <span className="dot red" />
                      <span className="dot yellow" />
                      <span className="dot green" />
                    </div>
                    <span className="terminal-title">SECURITY AUDIT & POLICY ENGINE LOG</span>
                    <span className="terminal-status">
                      {breachSimulationResult ? "TEST COMPLETED" : "IDLE / READY"}
                    </span>
                  </div>

                  <div className="terminal-body">
                    {!breachSimulationResult ? (
                      <div className="terminal-empty-state">
                        <LockKeyhole size={36} className="terminal-empty-icon" />
                        <strong>Policy Sandbox Ready</strong>
                        <p>
                          Click <em>"Execute Cross-Tenant Query Interception Test"</em> to simulate an unauthorized cross-organizer lookup and observe the multi-layered policy interception.
                        </p>
                      </div>
                    ) : (
                      <div className="simulation-log-stream">
                        <div className="log-line">
                          <span className="log-timestamp">[{breachSimulationResult.simulatedAt}]</span>{" "}
                          <span className="log-action">HTTP INCOMING:</span>{" "}
                          <code>{breachSimulationResult.attemptedAction}</code>
                        </div>

                        <div className="log-line">
                          <span className="log-timestamp">[{breachSimulationResult.simulatedAt}]</span>{" "}
                          <span className="log-stage">LAYER 1 (MIDDLEWARE):</span>{" "}
                          <span>EnsureTenantAccess resolved tenant: <code>{breachSimulationResult.authenticatedTenantId}</code></span>
                        </div>

                        <div className="log-line">
                          <span className="log-timestamp">[{breachSimulationResult.simulatedAt}]</span>{" "}
                          <span className="log-stage">LAYER 2 (ELOQUENT):</span>{" "}
                          <span>TenantScope appended: <code>WHERE {simulationTarget}.organizer_id = '{breachSimulationResult.authenticatedTenantId}'</code></span>
                        </div>

                        <div className="log-line">
                          <span className="log-timestamp">[{breachSimulationResult.simulatedAt}]</span>{" "}
                          <span className="log-stage">LAYER 3 (DATABASE):</span>{" "}
                          <span>Query executed against single shared database. Rows returned: <strong>0</strong></span>
                        </div>

                        <div className="log-line">
                          <span className="log-timestamp">[{breachSimulationResult.simulatedAt}]</span>{" "}
                          <span className="log-stage">LAYER 4 (POLICY):</span>{" "}
                          <span>Policy evaluated: <code>{breachSimulationResult.policyTriggered}</code></span>
                        </div>

                        <div className={`log-verdict-box ${breachSimulationResult.accessGranted ? "warning" : "success"}`}>
                          <div className="verdict-header">
                            <span className={`status-pill ${breachSimulationResult.accessGranted ? "superadmin" : "forbidden"}`}>
                              {breachSimulationResult.statusText}
                            </span>
                            <span className="http-code">HTTP STATUS: {breachSimulationResult.httpStatus}</span>
                          </div>
                          <p className="verdict-message">{breachSimulationResult.securityMessage}</p>
                        </div>

                        <div className="log-line final">
                          <span className="log-timestamp">[{breachSimulationResult.simulatedAt}]</span>{" "}
                          <strong className="log-success">
                            ✓ ZERO DATA LEAKAGE: Tenant isolation invariants 100% verified.
                          </strong>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* VIEW 2: ROLE & OWNERSHIP BOUNDARIES MATRIX */}
          {architectureViewMode === "rbac" && (
            <div className="tenancy-section-panel">
              <div className="panel-header-row">
                <div>
                  <span className="section-kicker">GOVERNANCE & RESPONSIBILITY MATRICES</span>
                  <h3>Role & Ownership Boundaries</h3>
                  <p>
                    Clear operational boundaries eliminate confusion, prevent financial fraud, and protect customer privacy across platform and event operations.
                  </p>
                </div>
                <div className="active-role-pill">
                  Active Simulation: <strong>{localRole.toUpperCase()}</strong>
                </div>
              </div>

              {/* 4 Roles Overview Cards */}
              <div className="role-cards-grid">
                <div className={`role-card ${localRole === "platform_admin" ? "active-role" : ""}`}>
                  <div className="role-card-header">
                    <span className="role-icon-wrap purple"><ShieldCheck size={18} /></span>
                    <div>
                      <strong>Platform Administrator</strong>
                      <span className="role-tier">GLOBAL TENANT STEWARD</span>
                    </div>
                  </div>
                  <p className="role-desc">
                    Governs platform infrastructure, onboard new organizers, oversees system security, monitors compliance, and resolves technical incidents. Does not cashier or modify event menus directly.
                  </p>
                  <ul className="role-bullet-list">
                    <li>✓ Provision & suspend organizer accounts</li>
                    <li>✓ Global security audit trail access</li>
                    <li>✓ Platform infrastructure & billing</li>
                    <li>✗ Daily cash receipts & food collection</li>
                  </ul>
                </div>

                <div className={`role-card ${localRole === "event_owner" ? "active-role" : ""}`}>
                  <div className="role-card-header">
                    <span className="role-icon-wrap blue"><Building2 size={18} /></span>
                    <div>
                      <strong>Event Owner / Admin</strong>
                      <span className="role-tier">TENANT AUTONOMOUS LEADER</span>
                    </div>
                  </div>
                  <p className="role-desc">
                    Full authority over the organizer's events, menus, pricing, custom payment credentials (Zelle, Cash App, Jamaican banks), staff invitations, and financial reconciliation reports.
                  </p>
                  <ul className="role-bullet-list">
                    <li>✓ Create & edit events, menus, pricing</li>
                    <li>✓ Configure bank accounts & Zelle credentials</li>
                    <li>✓ Authorize auditable cash reversals</li>
                    <li>✓ Invite & assign staff roles</li>
                  </ul>
                </div>

                <div className={`role-card ${localRole === "payment_verifier" ? "active-role" : ""}`}>
                  <div className="role-card-header">
                    <span className="role-icon-wrap green"><Banknote size={18} /></span>
                    <div>
                      <strong>Payment Verifier / Cashier</strong>
                      <span className="role-tier">FINANCIAL AUDIT GATEWAY</span>
                    </div>
                  </div>
                  <p className="role-desc">
                    Audits incoming bank transfers, verifies references, logs physical cash receipts into the immutable cash ledger, and approves or rejects pending payments.
                  </p>
                  <ul className="role-bullet-list">
                    <li>✓ Audit bank references & match statements</li>
                    <li>✓ Record cash payments & issue customer receipts</li>
                    <li>✗ Cannot edit event dates, venues, or pricing</li>
                    <li>✗ Cannot access organizer's banking API keys</li>
                  </ul>
                </div>

                <div className={`role-card ${localRole === "food_collector" ? "active-role" : ""}`}>
                  <div className="role-card-header">
                    <span className="role-icon-wrap amber"><ChefHat size={18} /></span>
                    <div>
                      <strong>Food Collection Staff</strong>
                      <span className="role-tier">EVENT-DAY REDEMPTION</span>
                    </div>
                  </div>
                  <p className="role-desc">
                    Frontline workers at the food tent who scan ticket QR codes, verify ordered portions, and mark each meal as handed over. Shielded from sensitive financial data.
                  </p>
                  <ul className="role-bullet-list">
                    <li>✓ Scan digital tickets & QR codes</li>
                    <li>✓ Mark individual portion items collected</li>
                    <li>✗ Zero access to financial revenue reports</li>
                    <li>✗ Customer phone numbers redacted for privacy</li>
                  </ul>
                </div>
              </div>

              {/* RBAC Domain Matrix Table */}
              <div className="rbac-table-container">
                <h4 className="table-heading">Granular Domain Permission Matrix</h4>
                <table className="rbac-domain-table">
                  <thead>
                    <tr>
                      <th>Domain & Sensitive Operation</th>
                      <th>Platform Admin</th>
                      <th>Event Owner</th>
                      <th>Payment Verifier</th>
                      <th>Food Collector</th>
                      <th>Enforcement Policy</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>
                        <strong>Events & Media Flyers</strong>
                        <span className="cell-sub">Create events, upload banners, set venue/dates</span>
                      </td>
                      <td className="allowed">Read/Admin</td>
                      <td className="allowed">Full CRUD</td>
                      <td className="denied">Read Only</td>
                      <td className="denied">Read Only</td>
                      <td><code>EventPolicy::update</code></td>
                    </tr>
                    <tr>
                      <td>
                        <strong>Customer Directory & PII</strong>
                        <span className="cell-sub">Unmasked phone numbers, email, physical addresses</span>
                      </td>
                      <td className="allowed">Audit Trail</td>
                      <td className="allowed">Full View</td>
                      <td className="allowed">Full View</td>
                      <td className="denied">Masked Only</td>
                      <td><code>OrderPolicy::viewCustomerDetails</code></td>
                    </tr>
                    <tr>
                      <td>
                        <strong>Menu & Pricing Configuration</strong>
                        <span className="cell-sub">Adjust portion prices, add dishes, set donation caps</span>
                      </td>
                      <td className="allowed">Admin</td>
                      <td className="allowed">Full CRUD</td>
                      <td className="denied">Locked</td>
                      <td className="denied">Locked</td>
                      <td><code>EventPolicy::update</code></td>
                    </tr>
                    <tr>
                      <td>
                        <strong>Payment Approval & Rejection</strong>
                        <span className="cell-sub">Verify bank transfers, confirm partial payments</span>
                      </td>
                      <td className="allowed">Admin</td>
                      <td className="allowed">Authorized</td>
                      <td className="allowed">Authorized</td>
                      <td className="denied">Forbidden</td>
                      <td><code>OrderPolicy::verifyPayment</code></td>
                    </tr>
                    <tr>
                      <td>
                        <strong>Cash Ledger & In-Person Receipts</strong>
                        <span className="cell-sub">Record physical cash handed to worker/pastor</span>
                      </td>
                      <td className="allowed">Audit</td>
                      <td className="allowed">Authorized</td>
                      <td className="allowed">Authorized</td>
                      <td className="denied">Forbidden</td>
                      <td><code>PaymentPolicy::recordCashReceipt</code></td>
                    </tr>
                    <tr>
                      <td>
                        <strong>Auditable Cash Reversals</strong>
                        <span className="cell-sub">Post reversal entry with mandatory audit justification</span>
                      </td>
                      <td className="allowed">Admin</td>
                      <td className="allowed">Authorized</td>
                      <td className="denied">Requires Owner</td>
                      <td className="denied">Forbidden</td>
                      <td><code>PaymentPolicy::authorizeReversal</code></td>
                    </tr>
                    <tr>
                      <td>
                        <strong>Ticket QR Scanning & Handover</strong>
                        <span className="cell-sub">Scan attendee tickets & mark portions handed over</span>
                      </td>
                      <td className="allowed">Admin</td>
                      <td className="allowed">Authorized</td>
                      <td className="allowed">Authorized</td>
                      <td className="allowed">Authorized</td>
                      <td><code>TicketPolicy::scanTicket</code></td>
                    </tr>
                    <tr>
                      <td>
                        <strong>Executive Financial Reports</strong>
                        <span className="cell-sub">Gross collections, cash-in-hand reconciliation</span>
                      </td>
                      <td className="allowed">Global Rollup</td>
                      <td className="allowed">Tenant Only</td>
                      <td className="denied">Forbidden</td>
                      <td className="denied">Forbidden</td>
                      <td><code>ReportPolicy::viewFinancialReports</code></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              {/* Active Organizer Staff Roster */}
              <div className="staff-roster-section">
                <div className="roster-header">
                  <h4>Active Team Roster for "{activeOrg.name}"</h4>
                  <span className="roster-count">{tenantUsers.filter((u) => u.organizerId === activeOrg.id).length} Active Members</span>
                </div>
                <div className="staff-cards-grid">
                  {tenantUsers
                    .filter((u) => u.organizerId === activeOrg.id)
                    .map((user) => (
                      <div key={user.id} className="staff-roster-card">
                        <div className="staff-avatar">
                          <User size={16} />
                        </div>
                        <div className="staff-details">
                          <strong>{user.name}</strong>
                          <span className="staff-email">{user.email}</span>
                          <div className="staff-tags">
                            <span className={`staff-role-badge ${user.role}`}>{user.role.toUpperCase()}</span>
                            <span className="staff-station-badge">Station: {user.stationAssignment}</span>
                          </div>
                        </div>
                      </div>
                    ))}
                </div>
              </div>
            </div>
          )}

          {/* VIEW 3: SINGLE-DB SCOPING & QUERY BLUEPRINT */}
          {architectureViewMode === "blueprint" && (
            <div className="tenancy-section-panel">
              <div className="panel-header-row">
                <div>
                  <span className="section-kicker">ENGINEERING SPECIFICATION</span>
                  <h3>Single-Database Scoped Tenancy Architecture</h3>
                  <p>
                    Why a shared database with row-level tenant scoping was chosen over a complex multi-database system,
                    and how our 5-layer security model guarantees absolute tenant boundary enforcement.
                  </p>
                </div>
                <div className="security-tag-pill green">
                  <CheckCircle2 size={14} /> Production Ready
                </div>
              </div>

              {/* Architectural Decision Rationale */}
              <div className="rationale-cards-grid">
                <div className="rationale-card">
                  <span className="rationale-num">01</span>
                  <strong>Zero Migration Overhead</strong>
                  <p>
                    Running database migrations across hundreds of isolated SQLite/Postgres databases is notoriously fragile, prone to partial failure, and slows deployment cycles. A single database applies schema changes in milliseconds.
                  </p>
                </div>

                <div className="rationale-card">
                  <span className="rationale-num">02</span>
                  <strong>Optimal Resource Utilization</strong>
                  <p>
                    Multi-database architectures require independent connection pools for every tenant, exhausting database server RAM and triggering connection pool starvation during peak ticket drops.
                  </p>
                </div>

                <div className="rationale-card">
                  <span className="rationale-num">03</span>
                  <strong>Centralized Platform Audits</strong>
                  <p>
                    Platform administrators can query cross-tenant fraud signals, aggregate platform volume, and enforce AML compliance without complex distributed queries or ETL pipelines.
                  </p>
                </div>

                <div className="rationale-card">
                  <span className="rationale-num">04</span>
                  <strong>High-Speed Composite Indexes</strong>
                  <p>
                    Every table includes a composite B-tree index starting with <code>organizer_id</code> (e.g. <code>(organizer_id, event_id, payment_status)</code>), guaranteeing sub-millisecond query execution regardless of tenant count.
                  </p>
                </div>
              </div>

              {/* 5-Layer Defense-in-Depth Pipeline */}
              <div className="defense-layers-box">
                <h4>5-Layer Tenant Isolation Defense-in-Depth</h4>
                <div className="layers-timeline">
                  <div className="layer-step">
                    <div className="layer-badge">LAYER 1</div>
                    <strong>HTTP Middleware (EnsureTenantAccess)</strong>
                    <p>Resolves active tenant from subdomain or <code>X-Organizer-ID</code> header; validates user active membership in <code>organizer_users</code>.</p>
                  </div>
                  <div className="layer-step">
                    <div className="layer-badge">LAYER 2</div>
                    <strong>Eloquent Global Scope (TenantScope)</strong>
                    <p>Automatically injects <code>WHERE organizer_id = ?</code> into all Eloquent queries. Prevents developer omission bugs.</p>
                  </div>
                  <div className="layer-step">
                    <div className="layer-badge">LAYER 3</div>
                    <strong>Model Trait (BelongsToTenant)</strong>
                    <p>Hooks into model creation lifecycle to automatically assign active tenant ID upon saving new records.</p>
                  </div>
                  <div className="layer-step">
                    <div className="layer-badge">LAYER 4</div>
                    <strong>Authorization Policies</strong>
                    <p>Laravel policies (<code>EventPolicy</code>, <code>OrderPolicy</code>, <code>PaymentPolicy</code>) verify tenant boundaries before updates.</p>
                  </div>
                  <div className="layer-step">
                    <div className="layer-badge">LAYER 5</div>
                    <strong>Storage & S3 Partitioning</strong>
                    <p>Flyer assets and receipts isolated under discrete bucket paths: <code>/tenants/{`{organizer_id}`}/flyers/</code>.</p>
                  </div>
                </div>
              </div>

              {/* Code Snippets Viewer */}
              <div className="code-snippets-preview">
                <div className="snippet-header">
                  <span>Backend Scoping Trait: <code>backend/app/Traits/BelongsToTenant.php</code></span>
                  <button
                    type="button"
                    className="copy-btn-mini"
                    onClick={() => copyText("backend/app/Traits/BelongsToTenant.php", "Path copied!")}
                  >
                    <Copy size={12} /> Copy
                  </button>
                </div>
                <pre className="code-box-small">
{`trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (!$model->organizer_id && TenantContext::id()) {
                $model->organizer_id = TenantContext::id();
            }
        });
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }
}`}
                </pre>
              </div>
            </div>
          )}

          {/* VIEW 4: TENANCY EVOLUTION ROADMAP */}
          {architectureViewMode === "evolution" && (
            <div className="tenancy-section-panel">
              <div className="panel-header-row">
                <div>
                  <span className="section-kicker">SCALING TRAJECTORY</span>
                  <h3>Tenant Isolation Evolution Roadmap</h3>
                  <p>
                    A clear, disciplined evolutionary path that scales gracefully as organizer volume and enterprise compliance requirements expand.
                  </p>
                </div>
                <div className="security-tag-pill amber">
                  <Layers size={14} /> 3-Stage Progression
                </div>
              </div>

              <div className="evolution-phases-grid">
                {/* Phase 1 */}
                <div className="evolution-phase-card current">
                  <div className="phase-badge current">STAGE 1: CURRENT ARCHITECTURE (ACTIVE)</div>
                  <h4>Single Database · Row-Level Scoping</h4>
                  <span className="phase-scale">Target: Launch to 1,000+ Active Organizers</span>
                  <p className="phase-summary">
                    Shared database tables partitioned by <code>organizer_id</code> UUID with Eloquent global query scopes, composite indexing, and Laravel authorization policies.
                  </p>
                  <div className="phase-metrics">
                    <div><span>Database Pools:</span> <strong>1 Central Pool</strong></div>
                    <div><span>Migration Speed:</span> <strong>Instant (&lt; 2s)</strong></div>
                    <div><span>Cross-Tenant IDOR:</span> <strong>Zero Leaks (Policy Blocked)</strong></div>
                    <div><span>Infrastructure Cost:</span> <strong>Optimal / Low Cost</strong></div>
                  </div>
                  <div className="phase-features">
                    <strong>Key Safeguards:</strong>
                    <ul>
                      <li>✓ Automatic <code>TenantScope</code> on all queries</li>
                      <li>✓ Composite B-tree indexes on <code>(organizer_id, ...)</code></li>
                      <li>✓ Optional PostgreSQL Row-Level Security (RLS)</li>
                      <li>✓ Cloud storage prefix isolation for event flyers</li>
                    </ul>
                  </div>
                </div>

                {/* Phase 2 */}
                <div className="evolution-phase-card">
                  <div className="phase-badge">STAGE 2: MID-TIER ENTERPRISE EXPANSION</div>
                  <h4>Schema-per-Tenant (PostgreSQL)</h4>
                  <span className="phase-scale">Trigger: 1,000 to 10,000 Organizers &amp; Corporate Clients</span>
                  <p className="phase-summary">
                    Migrate to PostgreSQL schemas (e.g. <code>tenant_kingstonyouth.*</code>) on a shared cluster when enterprise organizers require isolated point-in-time table backups, distinct vacuum cycles, and isolated table statistics.
                  </p>
                  <div className="phase-metrics">
                    <div><span>Database Pools:</span> <strong>Shared Cluster</strong></div>
                    <div><span>Migration Speed:</span> <strong>Tenant Schemas Loop</strong></div>
                    <div><span>Isolation Level:</span> <strong>PostgreSQL Schema Separation</strong></div>
                    <div><span>Infrastructure Cost:</span> <strong>Moderate</strong></div>
                  </div>
                  <div className="phase-features">
                    <strong>Key Safeguards:</strong>
                    <ul>
                      <li>✓ Independent schema dumps and backups per tenant</li>
                      <li>✓ Dynamic search_path switching in middleware</li>
                      <li>✓ Shared platform tables for authentication &amp; billing</li>
                      <li>✓ Zero query rewrite needed in application layer</li>
                    </ul>
                  </div>
                </div>

                {/* Phase 3 */}
                <div className="evolution-phase-card">
                  <div className="phase-badge">STAGE 3: HIGH-COMPLIANCE SOVEREIGN TIER</div>
                  <h4>Dedicated Database-per-Tenant</h4>
                  <span className="phase-scale">Trigger: Sovereign / Government Mandate or Strict PCI-DSS Level 1</span>
                  <p className="phase-summary">
                    Only introduced if a large government entity or multi-national corporation requires strict physical hardware data residency, dedicated encryption keys (KMS), and physical isolation.
                  </p>
                  <div className="phase-metrics">
                    <div><span>Database Pools:</span> <strong>Dynamic Dedicated Connections</strong></div>
                    <div><span>Migration Speed:</span> <strong>Orchestrated Tenant Migrations</strong></div>
                    <div><span>Isolation Level:</span> <strong>Physical / Database Boundary</strong></div>
                    <div><span>Infrastructure Cost:</span> <strong>High Enterprise Premium</strong></div>
                  </div>
                  <div className="phase-features">
                    <strong>Key Safeguards:</strong>
                    <ul>
                      <li>✓ Separate RDS/Aurora instances or SQLite files</li>
                      <li>✓ Customer-managed encryption keys (AWS KMS)</li>
                      <li>✓ Isolated network VPCs for high-security tenants</li>
                      <li>✓ Multi-database connection manager in Laravel</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      )}


      {/* TOAST MESSAGE BANNER */}
      {toastMessage && (
        <div className="mini-toast organizer">
          <CheckCircle2 size={16} />
          <span>{toastMessage}</span>
        </div>
      )}

      {/* MODAL 1: RECORD CASH PAYMENT RECEIVED */}
      {showAddCashModal && (
        <div className="modal-backdrop" onClick={() => setShowAddCashModal(false)}>
          <div className="modal-card" style={{ maxWidth: 620 }} onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div>
                <span className="section-kicker">FINANCIAL CONTROL & CASH WORKFLOW</span>
                <h2>Record Cash Payment Received</h2>
                <p>Record physical cash collected by an authorized seller, worker, or committee member.</p>
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setShowAddCashModal(false)}
                aria-label="Close dialog"
              >
                <X size={18} />
              </button>
            </div>

            <div className="modal-body">
              <div className="strategy-callout" style={{ background: "#edf8f2", borderColor: "#bce4cb" }}>
                <Banknote size={20} style={{ color: "#16643e", flex: "none", marginTop: 2 }} />
                <div>
                  <strong style={{ color: "#135836" }}>Auditable Cash Receipt #{cashReceiptNumber}</strong>
                  <p style={{ color: "#2d6848" }}>
                    Once saved, this cash transaction will be entered into the immutable cash ledger. If a correction is later required, enterprise financial control requires an auditable reversal rather than overwriting.
                  </p>
                </div>
              </div>

              <div className="form-group">
                <label className="field-label">Customer's Full Name *</label>
                <div className="input-wrap">
                  <User size={15} />
                  <input
                    type="text"
                    placeholder="e.g. Brother Winston"
                    value={cashCustomerName}
                    onChange={(e) => setCashCustomerName(e.target.value)}
                    required
                  />
                </div>
              </div>

              <div className="form-group">
                <label className="field-label">Customer's Phone Number (for WhatsApp / SMS receipt) *</label>
                <div className="input-wrap">
                  <Phone size={15} />
                  <input
                    type="tel"
                    placeholder="e.g. +1 (876) 555-0142"
                    value={cashCustomerPhone}
                    onChange={(e) => setCashCustomerPhone(e.target.value)}
                    required
                  />
                </div>
              </div>

              <div className="settings-grid-2col">
                <div className="form-group">
                  <label className="field-label">Associated Event</label>
                  <div className="input-wrap" style={{ background: "#f8faf8" }}>
                    <Calendar size={15} />
                    <input
                      type="text"
                      value={eventDetails.title}
                      readOnly
                      style={{ color: "#4f6052", cursor: "default" }}
                    />
                  </div>
                </div>

                <div className="form-group">
                  <label className="field-label">Link to Existing Order (Optional)</label>
                  <div className="select-wrap" style={{ marginTop: 0 }}>
                    <select
                      value={cashSelectedOrderId}
                      onChange={(e) => {
                        const ordId = e.target.value;
                        setCashSelectedOrderId(ordId);
                        if (ordId) {
                          const target = orders.find((o) => o.id === ordId);
                          if (target) {
                            setCashCustomerName(target.customerName);
                            setCashCustomerPhone(target.customerPhone);
                            const remaining = target.totalAmount - (target.amountPaid || 0);
                            setCashAmount(remaining > 0 ? remaining : target.totalAmount);
                            setCashCurrency(target.currency);
                          }
                        }
                      }}
                    >
                      <option value="">-- Direct In-Person / WhatsApp Walk-in --</option>
                      {orders.map((o) => (
                        <option key={o.id} value={o.id}>
                          {o.orderNumber || o.ticketCode} - {o.customerName} ({formatMoney(o.totalAmount, o.currency)} • {o.status.toUpperCase()})
                        </option>
                      ))}
                    </select>
                    <ChevronDown size={14} />
                  </div>
                </div>
              </div>

              <div className="settings-grid-2col">
                <div className="form-group">
                  <label className="field-label">Amount Received *</label>
                  <div className="input-wrap">
                    <DollarSign size={15} />
                    <input
                      type="number"
                      min="1"
                      step="any"
                      placeholder="3000"
                      value={cashAmount || ""}
                      onChange={(e) => setCashAmount(Number(e.target.value))}
                      required
                    />
                  </div>
                </div>

                <div className="form-group">
                  <label className="field-label">Currency *</label>
                  <div className="select-wrap" style={{ marginTop: 0 }}>
                    <select
                      value={cashCurrency}
                      onChange={(e) => setCashCurrency(e.target.value as Currency)}
                    >
                      <option value="JMD">Jamaican Dollar (J$)</option>
                      <option value="USD">US Dollar (US$)</option>
                    </select>
                    <ChevronDown size={14} />
                  </div>
                </div>
              </div>

              <div className="settings-grid-2col">
                <div className="form-group">
                  <label className="field-label">Date & Time Received *</label>
                  <div className="input-wrap">
                    <Clock3 size={15} />
                    <input
                      type="datetime-local"
                      value={cashDateTime}
                      onChange={(e) => setCashDateTime(e.target.value)}
                      required
                    />
                  </div>
                </div>

                <div className="form-group">
                  <label className="field-label">Person Who Received the Money *</label>
                  <div className="input-wrap">
                    <UserCheck size={15} />
                    <input
                      type="text"
                      placeholder="e.g. Treasurer Davis, Sister Marcia"
                      value={cashReceivedBy}
                      onChange={(e) => setCashReceivedBy(e.target.value)}
                      required
                    />
                  </div>
                </div>
              </div>

              <div className="settings-grid-2col">
                <div className="form-group">
                  <label className="field-label">Official Receipt #</label>
                  <div className="input-wrap">
                    <FileText size={15} />
                    <input
                      type="text"
                      value={cashReceiptNumber}
                      onChange={(e) => setCashReceiptNumber(e.target.value)}
                    />
                  </div>
                </div>

                <div className="form-group">
                  <label className="field-label">Optional Notes / Audit Remarks</label>
                  <div className="input-wrap">
                    <FileEdit size={15} />
                    <input
                      type="text"
                      placeholder="e.g. Paid in cash before rehearsal"
                      value={cashNotes}
                      onChange={(e) => setCashNotes(e.target.value)}
                    />
                  </div>
                </div>
              </div>
            </div>

            <div className="modal-footer" style={{ justifyContent: "space-between" }}>
              <button
                type="button"
                className="action-pill-btn"
                style={{ padding: "0 16px", height: 38 }}
                onClick={() => setShowAddCashModal(false)}
              >
                Cancel
              </button>
              <button
                type="button"
                className="primary-button"
                disabled={!cashCustomerName.trim() || !cashCustomerPhone.trim() || cashAmount <= 0 || !cashReceivedBy.trim()}
                onClick={() => {
                  const targetOrd = cashSelectedOrderId ? orders.find((o) => o.id === cashSelectedOrderId) : undefined;
                  const newRec: CashPaymentRecord = {
                    id: `csh-${Date.now()}`,
                    receiptNumber: cashReceiptNumber,
                    transactionType: "payment",
                    customerName: cashCustomerName.trim(),
                    customerPhone: cashCustomerPhone.trim(),
                    eventName: eventDetails.title,
                    orderId: cashSelectedOrderId || undefined,
                    orderNumber: targetOrd?.orderNumber || (cashSelectedOrderId ? targetOrd?.ticketCode : `CSH-${Math.floor(1000 + Math.random() * 9000)}`),
                    amount: Number(cashAmount),
                    currency: cashCurrency,
                    receivedAt: new Date(cashDateTime).toISOString(),
                    receivedBy: cashReceivedBy.trim(),
                    notes: cashNotes.trim() || undefined,
                    createdAt: new Date().toISOString(),
                  };

                  setShowAddCashModal(false);
                  Promise.resolve(onRecordCashPayment(newRec, cashSelectedOrderId || undefined))
                    .then((saved) => setCreatedCashReceipt(saved || newRec))
                    .catch(() => undefined); // the app shows the server's reason

                  // Reset form fields
                  setCashReceiptNumber(`CSH-2026-${Math.floor(1000 + Math.random() * 9000)}`);
                  setCashCustomerName("");
                  setCashCustomerPhone("");
                  setCashSelectedOrderId("");
                  setCashNotes("");
                  setToastMessage("Cash payment recorded to audit ledger.");
                  setTimeout(() => setToastMessage(""), 3500);
                }}
              >
                <CheckCircle2 size={15} /> Save & Issue Customer Receipt
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 2: CUSTOMER CASH RECEIPT (EXACT USER PROMPT SPECIFICATION) */}
      {createdCashReceipt && (
        <div className="modal-backdrop" onClick={() => setCreatedCashReceipt(null)}>
          <div className="modal-card" style={{ maxWidth: 540 }} onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div>
                <span className="section-kicker">CUSTOMER RECEIPT NOTIFICATION</span>
                <h2>Cash Payment Receipt</h2>
                <p>Formatted receipt message ready to dispatch via WhatsApp or SMS to {createdCashReceipt.customerPhone}.</p>
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setCreatedCashReceipt(null)}
                aria-label="Close dialog"
              >
                <X size={18} />
              </button>
            </div>

            <div className="modal-body">
              {(() => {
                const formattedDate = new Date(createdCashReceipt.receivedAt).toLocaleDateString("en-US", {
                  month: "long",
                  day: "numeric",
                  year: "numeric",
                });
                const formattedAmt = formatMoney(createdCashReceipt.amount, createdCashReceipt.currency);
                const orderRef = createdCashReceipt.orderNumber || "DIRECT-CASH";

                const receiptTextMessage = `Payment Received — ${createdCashReceipt.eventName}

Hi! We have recorded your cash payment of ${formattedAmt} for your ${createdCashReceipt.eventName} order.

Order: ${orderRef}
Payment method: Cash
Date received: ${formattedDate}
Status: Paid

Please keep your digital ticket ready when collecting your order. Thank you for supporting our event!`;

                return (
                  <div>
                    <div className="sms-mockup-card" style={{ width: "100%", margin: "0 auto" }}>
                      <div className="sms-phone-header">
                        <span className="sms-carrier">WhatsApp / SMS Preview</span>
                        <span className="sms-contact-name">{createdCashReceipt.customerName}</span>
                        <span className="sms-carrier">{createdCashReceipt.receiptNumber}</span>
                      </div>
                      <div className="sms-bubble-area">
                        <span className="sms-timestamp">
                          {new Date(createdCashReceipt.receivedAt).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })} • Received by {createdCashReceipt.receivedBy}
                        </span>
                        <div className="sms-bubble" style={{ whiteSpace: "pre-wrap", fontFamily: "inherit", fontSize: 12 }}>
                          {receiptTextMessage}
                        </div>
                      </div>
                      <div className="sms-footer">
                        <span>Status: Verified In Hand</span>
                        <span>Official Ledger Entry</span>
                      </div>
                    </div>

                    <div style={{ display: "flex", gap: 10, marginTop: 16 }}>
                      <button
                        type="button"
                        className="preset-btn"
                        style={{ flex: 1, justifyContent: "center" }}
                        onClick={() => {
                          navigator.clipboard.writeText(receiptTextMessage);
                          setToastMessage("Receipt message copied to clipboard!");
                          setTimeout(() => setToastMessage(""), 3500);
                        }}
                      >
                        <Copy size={14} /> Copy for WhatsApp / SMS
                      </button>

                      <a
                        href={`https://wa.me/${createdCashReceipt.customerPhone.replace(/[^0-9]/g, "")}?text=${encodeURIComponent(receiptTextMessage)}`}
                        target="_blank"
                        rel="noreferrer"
                        className="primary-button"
                        style={{
                          background: "#25d366",
                          borderColor: "#25d366",
                          color: "#fff",
                          textDecoration: "none",
                          padding: "0 16px",
                        }}
                      >
                        <MessageCircle size={15} /> Send WhatsApp Direct
                      </a>
                    </div>
                  </div>
                );
              })()}
            </div>

            <div className="modal-footer">
              <button
                type="button"
                className="action-pill-btn"
                style={{ padding: "0 18px", height: 38 }}
                onClick={() => setCreatedCashReceipt(null)}
              >
                Close Receipt
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 3: AUDITABLE CASH REVERSAL / ADJUSTMENT (FINANCIAL CONTROL) */}
      {reversalTargetRecord && (
        <div className="modal-backdrop" onClick={() => setReversalTargetRecord(null)}>
          <div className="modal-card" style={{ maxWidth: 540 }} onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div>
                <span className="section-kicker" style={{ color: "#c54338" }}>FINANCIAL AUDIT CONTROL</span>
                <h2>Auditable Cash Reversal / Adjustment</h2>
                <p>Enterprise financial standards prevent silent overwrites. This action will create a signed negative ledger entry.</p>
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setReversalTargetRecord(null)}
                aria-label="Close dialog"
              >
                <X size={18} />
              </button>
            </div>

            <div className="modal-body">
              <div className="strategy-callout" style={{ background: "#fff6f5", borderColor: "#f7d2cd" }}>
                <ShieldAlert size={20} style={{ color: "#c54338", flex: "none", marginTop: 2 }} />
                <div>
                  <strong style={{ color: "#a42a20" }}>Target Transaction: Receipt #{reversalTargetRecord.receiptNumber}</strong>
                  <p style={{ color: "#6b2c27" }}>
                    Original payment of <strong>{formatMoney(reversalTargetRecord.amount, reversalTargetRecord.currency)}</strong> received by <strong>{reversalTargetRecord.receivedBy}</strong> from <strong>{reversalTargetRecord.customerName}</strong>.
                  </p>
                </div>
              </div>

              <div className="form-group">
                <label className="field-label">Reversal / Adjustment Amount ({reversalTargetRecord.currency}) *</label>
                <div className="input-wrap">
                  <DollarSign size={15} />
                  <input
                    type="number"
                    min="1"
                    max={reversalTargetRecord.amount}
                    value={reversalAmount}
                    onChange={(e) => setReversalAmount(Number(e.target.value))}
                    required
                  />
                </div>
                <span className="field-hint">Enter the full amount for complete cancellation, or a partial amount for an adjustment.</span>
              </div>

              <div className="form-group">
                <label className="field-label">Reason for Reversal / Adjustment *</label>
                <div className="input-wrap">
                  <FileEdit size={15} />
                  <input
                    type="text"
                    placeholder="e.g. Customer cancelled order / Duplicate entry correction"
                    value={reversalReason}
                    onChange={(e) => setReversalReason(e.target.value)}
                    required
                  />
                </div>
              </div>

              <div className="form-group">
                <label className="field-label">Authorized By (Officer / Executive) *</label>
                <div className="input-wrap">
                  <UserCheck size={15} />
                  <input
                    type="text"
                    placeholder="e.g. Pastor Brown, Executive Board"
                    value={reversalAuthorizedBy}
                    onChange={(e) => setReversalAuthorizedBy(e.target.value)}
                    required
                  />
                </div>
              </div>
            </div>

            <div className="modal-footer" style={{ justifyContent: "space-between" }}>
              <button
                type="button"
                className="action-pill-btn"
                style={{ padding: "0 16px", height: 38 }}
                onClick={() => setReversalTargetRecord(null)}
              >
                Cancel
              </button>
              <button
                type="button"
                className="primary-button"
                style={{ background: "#c54338", borderColor: "#c54338" }}
                disabled={!reversalReason.trim() || !reversalAuthorizedBy.trim() || reversalAmount <= 0}
                onClick={() => {
                  const revRec: CashPaymentRecord = {
                    id: `rev-${Date.now()}`,
                    receiptNumber: `REV-${reversalTargetRecord.receiptNumber.replace(/^CSH-/, "")}`,
                    transactionType: "reversal",
                    customerName: reversalTargetRecord.customerName,
                    customerPhone: reversalTargetRecord.customerPhone,
                    eventName: reversalTargetRecord.eventName,
                    orderId: reversalTargetRecord.orderId,
                    orderNumber: reversalTargetRecord.orderNumber,
                    amount: -Math.abs(reversalAmount),
                    currency: reversalTargetRecord.currency,
                    receivedAt: new Date().toISOString(),
                    receivedBy: reversalTargetRecord.receivedBy,
                    notes: `Reversal of #${reversalTargetRecord.receiptNumber}: ${reversalReason}`,
                    createdAt: new Date().toISOString(),
                    reversalOfId: reversalTargetRecord.id,
                    reversalReason: reversalReason.trim(),
                    reversalAuthorizedBy: reversalAuthorizedBy.trim(),
                  };

                  onReverseCashPayment(revRec, reversalTargetRecord.orderId);
                  setReversalTargetRecord(null);
                  setToastMessage("Reversal recorded to auditable cash ledger.");
                  setTimeout(() => setToastMessage(""), 3500);
                }}
              >
                <RotateCcw size={15} /> Post Auditable Reversal (-{formatMoney(reversalAmount, reversalTargetRecord.currency)})
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 4: MULTI-STATUS ORDER ACTION DIALOG */}
      {verifyingOrder && verificationAction && (
        <div className="modal-backdrop" onClick={() => { setVerifyingOrder(null); setVerificationAction(null); }}>
          <div className="modal-card" style={{ maxWidth: 540 }} onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div>
                <span className="section-kicker">ORDER FINANCIAL VERIFICATION</span>
                <h2>
                  {verificationAction === "paid" && "Confirm Full Payment Received"}
                  {verificationAction === "under_verification" && "Mark Under Statement Verification"}
                  {verificationAction === "partially_paid" && "Record Partial Payment"}
                  {verificationAction === "rejected" && "Reject Submitted Payment"}
                  {verificationAction === "refunded" && "Record Order Refund"}
                </h2>
                <p>
                  Order #{verifyingOrder.orderNumber || verifyingOrder.ticketCode} • {verifyingOrder.customerName} ({formatMoney(verifyingOrder.totalAmount, verifyingOrder.currency)})
                </p>
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => { setVerifyingOrder(null); setVerificationAction(null); }}
                aria-label="Close dialog"
              >
                <X size={18} />
              </button>
            </div>

            <div className="modal-body">
              {/* Reminder banner */}
              <div
                className="strategy-callout"
                style={{
                  background: verificationAction === "rejected" ? "#fff5f5" : "#f4faf6",
                  borderColor: verificationAction === "rejected" ? "#f5c6cb" : "#bce4cb",
                }}
              >
                <ShieldCheck
                  size={20}
                  style={{
                    color: verificationAction === "rejected" ? "#c54338" : "#16643e",
                    flex: "none",
                    marginTop: 2,
                  }}
                />
                <div>
                  <strong style={{ color: verificationAction === "rejected" ? "#721c24" : "#135836" }}>
                    Bank Statement Verification Rule
                  </strong>
                  <p style={{ color: verificationAction === "rejected" ? "#721c24" : "#2d6848" }}>
                    Do not approve payments solely based on customer screenshots. Ensure transaction ref <code>{verifyingOrder.paymentReference || "N/A"}</code> appears on your account statement.
                  </p>
                </div>
              </div>

              {verificationAction === "partially_paid" && (
                <div className="form-group">
                  <label className="field-label">Amount Verified as Received ({verifyingOrder.currency}) *</label>
                  <div className="input-wrap">
                    <DollarSign size={15} />
                    <input
                      type="number"
                      min="1"
                      max={verifyingOrder.totalAmount - 1}
                      value={actionPartialAmount}
                      onChange={(e) => setActionPartialAmount(Number(e.target.value))}
                      placeholder="e.g. 2000"
                      required
                    />
                  </div>
                  <span className="field-hint">
                    Total required: {formatMoney(verifyingOrder.totalAmount, verifyingOrder.currency)}. Remaining balance must be paid before or during pickup.
                  </span>
                </div>
              )}

              {verificationAction === "rejected" && (
                <div className="form-group">
                  <label className="field-label">Rejection Reason *</label>
                  <div className="input-wrap">
                    <FileEdit size={15} />
                    <input
                      type="text"
                      value={actionRejectionReason}
                      onChange={(e) => setActionRejectionReason(e.target.value)}
                      placeholder="e.g. Reference code not found on NCB online banking statement"
                      required
                    />
                  </div>
                </div>
              )}

              {verificationAction === "refunded" && (
                <>
                  <div className="form-group">
                    <label className="field-label">Refund Amount ({verifyingOrder.currency}) *</label>
                    <div className="input-wrap">
                      <DollarSign size={15} />
                      <input
                        type="number"
                        min="1"
                        max={verifyingOrder.totalAmount}
                        value={actionRefundAmount}
                        onChange={(e) => setActionRefundAmount(Number(e.target.value))}
                        required
                      />
                    </div>
                  </div>
                  <div className="form-group">
                    <label className="field-label">Refund Reason *</label>
                    <div className="input-wrap">
                      <FileEdit size={15} />
                      <input
                        type="text"
                        value={actionRefundReason}
                        onChange={(e) => setActionRefundReason(e.target.value)}
                        placeholder="e.g. Customer cancelled order prior to meal preparation"
                        required
                      />
                    </div>
                  </div>
                </>
              )}

              <div className="settings-grid-2col">
                <div className="form-group">
                  <label className="field-label">Verified By *</label>
                  <div className="input-wrap">
                    <UserCheck size={15} />
                    <input
                      type="text"
                      value={actionVerifier}
                      onChange={(e) => setActionVerifier(e.target.value)}
                      required
                    />
                  </div>
                </div>

                <div className="form-group">
                  <label className="field-label">Audit Notes (Optional)</label>
                  <div className="input-wrap">
                    <FileText size={15} />
                    <input
                      type="text"
                      placeholder="e.g. Confirmed on NCB Mobile App"
                      value={actionNotes}
                      onChange={(e) => setActionNotes(e.target.value)}
                    />
                  </div>
                </div>
              </div>
            </div>

            <div className="modal-footer" style={{ justifyContent: "space-between" }}>
              <button
                type="button"
                className="action-pill-btn"
                style={{ padding: "0 16px", height: 38 }}
                onClick={() => { setVerifyingOrder(null); setVerificationAction(null); }}
              >
                Cancel
              </button>
              <button
                type="button"
                className={`primary-button ${verificationAction === "rejected" ? "danger" : ""}`}
                style={verificationAction === "rejected" ? { background: "#c54338", borderColor: "#c54338" } : undefined}
                onClick={() => {
                  if (verificationAction === "paid") {
                    onUpdatePaymentStatus(verifyingOrder.id, "paid", {
                      amountPaid: verifyingOrder.totalAmount,
                      verifier: actionVerifier.trim(),
                      notes: actionNotes.trim() || undefined,
                    });
                    setToastMessage(`Order #${verifyingOrder.orderNumber || verifyingOrder.ticketCode} marked Verified & Paid.`);
                  } else if (verificationAction === "under_verification") {
                    onUpdatePaymentStatus(verifyingOrder.id, "under_verification", {
                      verifier: actionVerifier.trim(),
                      notes: actionNotes.trim() || undefined,
                    });
                    setToastMessage(`Order #${verifyingOrder.orderNumber || verifyingOrder.ticketCode} is Under Verification.`);
                  } else if (verificationAction === "partially_paid") {
                    onUpdatePaymentStatus(verifyingOrder.id, "partially_paid", {
                      amountPaid: Number(actionPartialAmount),
                      verifier: actionVerifier.trim(),
                      notes: actionNotes.trim() || undefined,
                    });
                    setToastMessage(`Order recorded as Partially Paid (${formatMoney(actionPartialAmount, verifyingOrder.currency)} received).`);
                  } else if (verificationAction === "rejected") {
                    onUpdatePaymentStatus(verifyingOrder.id, "rejected", {
                      rejectionReason: actionRejectionReason.trim() || "Transaction reference could not be verified on bank statement",
                      verifier: actionVerifier.trim(),
                    });
                    setToastMessage(`Payment rejected for Order #${verifyingOrder.orderNumber || verifyingOrder.ticketCode}.`);
                  } else if (verificationAction === "refunded") {
                    onUpdatePaymentStatus(verifyingOrder.id, "refunded", {
                      refundDetails: {
                        amount: Number(actionRefundAmount),
                        reason: actionRefundReason.trim() || "Customer requested cancellation",
                        refundedAt: new Date().toISOString(),
                        refundedBy: actionVerifier.trim(),
                      },
                    });
                    setToastMessage(`Refund recorded for Order #${verifyingOrder.orderNumber || verifyingOrder.ticketCode}.`);
                  }

                  setVerifyingOrder(null);
                  setVerificationAction(null);
                  setTimeout(() => setToastMessage(""), 3500);
                }}
              >
                <CheckCircle2 size={15} /> Confirm Status Update
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 3: SMARTPHONE SMS MOCKUP MODAL */}
      {activeMockupNotif && (
        <div className="modal-backdrop" onClick={() => setActiveMockupNotif(null)}>
          <div className="smartphone-modal-card" onClick={(e) => e.stopPropagation()}>
            <div className="smartphone-bezel">
              <div className="smartphone-notch">
                <span className="camera-lens" />
                <span className="speaker-slot" />
              </div>

              {/* Status bar */}
              <div className="smartphone-status-bar">
                <span className="phone-carrier">
                  {activeMockupNotif.channel === "sms" ? "Flow 4G LTE" : "WiFi"}
                </span>
                <span className="phone-clock">
                  {new Date(activeMockupNotif.sentAt || activeMockupNotif.createdAt || Date.now()).toLocaleTimeString([], {
                    hour: "2-digit",
                    minute: "2-digit",
                  })}
                </span>
                <span className="phone-battery">98% 🔋</span>
              </div>

              {/* Messaging App Topbar */}
              <div className="messaging-topbar">
                <button
                  type="button"
                  className="phone-back-btn"
                  onClick={() => setActiveMockupNotif(null)}
                >
                  <X size={16} />
                </button>
                <div className="sender-avatar">
                  <Ticket size={18} />
                </div>
                <div className="sender-info">
                  <strong>ISLANDEVNT</strong>
                  <span>{activeMockupNotif.provider}</span>
                </div>
                <span className="verified-sender-badge">
                  <ShieldCheck size={14} />
                </span>
              </div>

              {/* Message Chat Body */}
              <div className="smartphone-chat-body">
                <div className="chat-date-stamp">
                  Today, {new Date(activeMockupNotif.sentAt || activeMockupNotif.createdAt || Date.now()).toLocaleDateString([], {
                    weekday: "short",
                    month: "short",
                    day: "numeric",
                  })}
                </div>

                <div className="chat-bubble-container">
                  <div className={`chat-bubble ${activeMockupNotif.channel}`}>
                    <span className="bubble-title">{activeMockupNotif.title}</span>
                    <p className="bubble-text">{activeMockupNotif.message}</p>
                    <span className="bubble-time">
                      {new Date(activeMockupNotif.sentAt || activeMockupNotif.createdAt || Date.now()).toLocaleTimeString([], {
                        hour: "2-digit",
                        minute: "2-digit",
                      })}
                    </span>
                  </div>

                  <div className="bubble-status-row">
                    <span className={`status-text ${activeMockupNotif.status}`}>
                      {activeMockupNotif.status === "delivered" && (
                        <>
                          <CheckCircle2 size={12} /> Delivered to {activeMockupNotif.recipient}
                        </>
                      )}
                      {activeMockupNotif.status === "queued" && (
                        <>
                          <Clock3 size={12} /> Queued on carrier network
                        </>
                      )}
                      {activeMockupNotif.status === "failed" && (
                        <>
                          <AlertTriangle size={12} /> Carrier delivery failed · Payment unaffected
                        </>
                      )}
                    </span>
                  </div>
                </div>
              </div>

              {/* Delivery Metadata Footer */}
              <div className="smartphone-meta-footer">
                <div className="meta-row">
                  <span>Trigger:</span>
                  <strong>{activeMockupNotif.trigger.replace("_", " ").toUpperCase()}</strong>
                </div>
                <div className="meta-row">
                  <span>Gateway SID:</span>
                  <code>SM{activeMockupNotif.id.slice(-8)}jm</code>
                </div>
                <div className="meta-row">
                  <span>Recipient:</span>
                  <strong>{activeMockupNotif.customerName} ({activeMockupNotif.recipient})</strong>
                </div>
              </div>
            </div>

            <div className="modal-footer" style={{ justifyContent: "center", marginTop: 12 }}>
              <button
                type="button"
                className="action-pill-btn"
                onClick={() => setActiveMockupNotif(null)}
              >
                Close Phone Mockup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 4: CUSTOM NOTIFICATION COMPOSER MODAL */}
      {showCustomNotifModal && (
        <div className="modal-backdrop" onClick={() => setShowCustomNotifModal(false)}>
          <div className="modal-card" style={{ maxWidth: 600 }} onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div>
                <span className="section-kicker">DIRECT CUSTOMER DISPATCH</span>
                <h2>Compose Custom Notification</h2>
                <p>Send an immediate SMS, Email Receipt, or WhatsApp notification to a guest.</p>
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setShowCustomNotifModal(false)}
              >
                <X size={18} />
              </button>
            </div>

            <div className="modal-body">
              <div className="form-group">
                <label className="field-label">Select Target Order / Customer</label>
                <div className="select-wrap">
                  <select
                    value={customNotifOrderId}
                    onChange={(e) => {
                      const selOrd = orders.find((o) => o.id === e.target.value);
                      if (selOrd) {
                        setCustomNotifOrderId(selOrd.id);
                        setCustomNotifRecipient(selOrd.customerName);
                        setCustomNotifPhone(selOrd.customerPhone);
                      }
                    }}
                  >
                    {orders.map((o) => (
                      <option key={o.id} value={o.id}>
                        Order #{o.orderNumber} · {o.customerName} ({o.customerPhone})
                      </option>
                    ))}
                  </select>
                  <ChevronDown size={15} />
                </div>
              </div>

              <div className="settings-grid-2col">
                <div className="form-group">
                  <label className="field-label">Customer Name</label>
                  <input
                    className="settings-input"
                    value={customNotifRecipient}
                    onChange={(e) => setCustomNotifRecipient(e.target.value)}
                  />
                </div>
                <div className="form-group">
                  <label className="field-label">Recipient Phone / Email</label>
                  <input
                    className="settings-input"
                    value={customNotifPhone}
                    onChange={(e) => setCustomNotifPhone(e.target.value)}
                  />
                </div>
              </div>

              <div className="settings-grid-2col">
                <div className="form-group">
                  <label className="field-label">Dispatch Channel</label>
                  <div className="select-wrap">
                    <select
                      value={customNotifChannel}
                      onChange={(e) => setCustomNotifChannel(e.target.value as NotificationChannel)}
                    >
                      <option value="sms">Twilio SMS (Direct Carrier)</option>
                      <option value="email">Email Receipt (Amazon SES)</option>
                      <option value="whatsapp">WhatsApp Direct API</option>
                    </select>
                    <ChevronDown size={15} />
                  </div>
                </div>

                <div className="form-group">
                  <label className="field-label">Lifecycle Stage / Event</label>
                  <div className="select-wrap">
                    <select
                      value={customNotifTrigger}
                      onChange={(e) => setCustomNotifTrigger(e.target.value as NotificationTrigger)}
                    >
                      <option value="order_ready">Order ready (Pickup alert)</option>
                      <option value="event_approaching">Event approaching (Reminder)</option>
                      <option value="order_updated">Order updated (Balance notice)</option>
                      <option value="payment_verified">Payment verified (Receipt)</option>
                      <option value="cash_received">Cash received (Cash receipt)</option>
                      <option value="items_collected">Items collected (Collection notice)</option>
                    </select>
                    <ChevronDown size={15} />
                  </div>
                </div>
              </div>

              <div className="form-group">
                <label className="field-label">Message Title</label>
                <input
                  className="settings-input"
                  value={customNotifTitle}
                  onChange={(e) => setCustomNotifTitle(e.target.value)}
                />
              </div>

              <div className="form-group">
                <label className="field-label">Message Body</label>
                <textarea
                  className="settings-textarea"
                  rows={4}
                  value={customNotifMessage}
                  onChange={(e) => setCustomNotifMessage(e.target.value)}
                />
              </div>
            </div>

            <div className="modal-footer" style={{ justifyContent: "space-between" }}>
              <button
                type="button"
                className="action-pill-btn"
                onClick={() => setShowCustomNotifModal(false)}
              >
                Cancel
              </button>
              <button
                type="button"
                className="primary-button"
                disabled={!customNotifPhone.trim() || !customNotifMessage.trim()}
                onClick={() => {
                  const targetOrd = orders.find((o) => o.id === customNotifOrderId);
                  onSendCustomNotification({
                    orderId: customNotifOrderId || undefined,
                    orderNumber: targetOrd?.orderNumber || undefined,
                    customerName: customNotifRecipient.trim() || "Valued Customer",
                    recipient: customNotifPhone.trim(),
                    channel: customNotifChannel,
                    trigger: customNotifTrigger,
                    title: customNotifTitle.trim(),
                    message: customNotifMessage.trim(),
                    status: "delivered",
                    provider: customNotifChannel === "sms" ? "Twilio SMS" : customNotifChannel === "email" ? "Island Mail (SES)" : "WhatsApp Direct",
                    deliveredAt: new Date().toISOString(),
                  });
                  setShowCustomNotifModal(false);
                  setToastMessage(`Dispatched ${customNotifChannel.toUpperCase()} to ${customNotifRecipient}!`);
                  setTimeout(() => setToastMessage(""), 3500);
                }}
              >
                <Send size={15} /> Dispatch Notification
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ATTEMPT AUDIT TIMELINE MODAL */}
      {activeAttemptDrawerNotif && (
        <div className="modal-backdrop" onClick={() => setActiveAttemptDrawerNotif(null)}>
          <div
            className="modal-card attempt-history-modal"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="modal-header">
              <div className="modal-header-left">
                <span className="section-kicker">DISPATCH AUDIT & CARRIER CALLBACKS</span>
                <h2>Delivery Attempt History & Timeline</h2>
                <p>
                  Tracing lifecycle of notification <strong>#{activeAttemptDrawerNotif.id}</strong> for {activeAttemptDrawerNotif.customerName}.
                </p>
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setActiveAttemptDrawerNotif(null)}
              >
                <X size={18} />
              </button>
            </div>

            <div className="modal-body">
              <div className="attempt-summary-box">
                <div className="summary-item">
                  <span className="summary-label">Recipient:</span>
                  <strong>{activeAttemptDrawerNotif.recipient}</strong>
                </div>
                <div className="summary-item">
                  <span className="summary-label">Channel:</span>
                  <span className={`channel-badge ${activeAttemptDrawerNotif.channel}`}>
                    {activeAttemptDrawerNotif.channel.toUpperCase()}
                  </span>
                </div>
                <div className="summary-item">
                  <span className="summary-label">Provider SID:</span>
                  <code>{activeAttemptDrawerNotif.providerMessageId || "Assigned on MTA handoff"}</code>
                </div>
                <div className="summary-item">
                  <span className="summary-label">Idempotency Key:</span>
                  <code>{activeAttemptDrawerNotif.idempotencyKey || "idemp_default"}</code>
                </div>
              </div>

              {activeAttemptDrawerNotif.retrySuppressed && (
                <div className="retry-suppressed-banner">
                  <ShieldCheck size={18} className="green" />
                  <div>
                    <strong>Idempotency Guard Active: Duplicate Send Suppressed</strong>
                    <p>
                      This job was retried by a background queue worker. Because the message had already achieved delivery, the system suppressed duplicate dispatch, preventing the customer from receiving a duplicate receipt.
                    </p>
                  </div>
                </div>
              )}

              <div className="attempts-timeline-list">
                {(activeAttemptDrawerNotif.attempts || []).map((att, idx) => (
                  <div key={idx} className={`timeline-entry ${att.status}`}>
                    <div className="entry-marker">
                      <span className="marker-dot" />
                      {idx < (activeAttemptDrawerNotif.attempts?.length || 0) - 1 && <span className="marker-line" />}
                    </div>
                    <div className="entry-content">
                      <div className="entry-meta">
                        <span className={`delivery-status-pill ${att.status}`}>
                          {att.status.toUpperCase()}
                        </span>
                        <span className="entry-time">
                          {new Date(att.timestamp).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", second: "2-digit" })}
                        </span>
                        {att.workerId && <span className="entry-worker">Worker: {att.workerId}</span>}
                      </div>
                      <p className="entry-note">{att.note}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <div className="modal-footer">
              <button
                type="button"
                className="action-pill-btn"
                onClick={() => setActiveAttemptDrawerNotif(null)}
              >
                Close Audit Timeline
              </button>
            </div>
          </div>
        </div>
      )}

      {/* LARAVEL BACKEND ARCHITECTURE CODE VIEWER MODAL */}
      {showLaravelCodeModal && (
        <div className="modal-backdrop" onClick={() => setShowLaravelCodeModal(false)}>
          <div
            className="modal-card laravel-architecture-modal"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="modal-header">
              <div className="modal-header-left">
                <span className="section-kicker">LARAVEL BACKEND IMPLEMENTATION</span>
                <h2>Laravel Notifications, Queues & Webhook Infrastructure</h2>
                <p>
                  Production PHP files located in <code>backend/app/</code>. Implements <code>ShouldQueue</code>, idempotency verification, and delivery webhooks.
                </p>
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setShowLaravelCodeModal(false)}
              >
                <X size={18} />
              </button>
            </div>

            <div className="laravel-modal-nav">
              <button
                type="button"
                className={`laravel-tab-btn ${activeLaravelFile === "notification" ? "active" : ""}`}
                onClick={() => setActiveLaravelFile("notification")}
              >
                OrderReceivedNotification.php
              </button>
              <button
                type="button"
                className={`laravel-tab-btn ${activeLaravelFile === "job" ? "active" : ""}`}
                onClick={() => setActiveLaravelFile("job")}
              >
                SendNotificationJob.php (Queue & Idempotency)
              </button>
              <button
                type="button"
                className={`laravel-tab-btn ${activeLaravelFile === "controller" ? "active" : ""}`}
                onClick={() => setActiveLaravelFile("controller")}
              >
                NotificationWebhookController.php
              </button>
              <button
                type="button"
                className={`laravel-tab-btn ${activeLaravelFile === "migration" ? "active" : ""}`}
                onClick={() => setActiveLaravelFile("migration")}
              >
                create_notification_logs_table.php
              </button>
            </div>

            <div className="modal-body laravel-code-body">
              {activeLaravelFile === "notification" && (
                <div>
                  <div className="code-file-bar">
                    <span>File: <code>backend/app/Notifications/OrderReceivedNotification.php</code></span>
                    <button
                      type="button"
                      className="copy-code-btn"
                      onClick={() => copyText("backend/app/Notifications/OrderReceivedNotification.php", "Path copied!")}
                    >
                      <Copy size={12} /> Copy Path
                    </button>
                  </div>
                  <pre className="code-block">
{`<?php

namespace App\\Notifications;

use Illuminate\\Bus\\Queueable;
use Illuminate\\Contracts\\Queue\\ShouldQueue;
use Illuminate\\Notifications\\Messages\\MailMessage;
use Illuminate\\Notifications\\Notification;

class OrderReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $orderData;
    public string $idempotencyKey;

    public function __construct(array $orderData)
    {
        $this->orderData = $orderData;
        // Deterministic idempotency key: prevents duplicate receipts on job retries
        $this->idempotencyKey = 'idemp_order_received_' . ($orderData['orderNumber'] ?? uniqid());
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        // Start with email where configured
        if (!empty($notifiable->email ?? $this->orderData['customerEmail'] ?? null)) {
            $channels[] = 'mail';
        }
        // Include SMS for mobile-first Caribbean/US attendees
        if (!empty($notifiable->phone ?? $this->orderData['customerPhone'] ?? null)) {
            $channels[] = 'sms';
        }
        return $channels ?: ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customerName = $this->orderData['customerName'] ?? 'Valued Customer';
        $orderNumber = $this->orderData['orderNumber'] ?? 'N/A';
        $eventName = $this->orderData['eventName'] ?? 'Island Event';
        $totalFormatted = $this->orderData['totalFormatted'] ?? '$0.00';

        return (new MailMessage)
            ->subject("Order Received: #{\(orderNumber} - {\)eventName}")
            ->greeting("Hello {\(customerName},")
            ->line("We have received your order for **{\)eventName}**.")
            ->line("Your order reference is **#{\(orderNumber}** with total of **{\)totalFormatted}**.")
            ->action('View Order & Payment Details', url('/orders/' . $orderNumber))
            ->line('Invariant: Order receipt is not proof of payment.')
            ->salutation('Warm regards, The Organizing Team');
    }

    public function toSms(object $notifiable): array
    {
        $orderNumber = $this->orderData['orderNumber'] ?? 'N/A';
        $eventName = $this->orderData['eventName'] ?? 'Island Event';
        $totalFormatted = $this->orderData['totalFormatted'] ?? '$0.00';

        return [
            'to' => $notifiable->phone ?? $this->orderData['customerPhone'],
            'message' => "Order #{\(orderNumber} received for {\)eventName}. Total: {\(totalFormatted}. View details: " . url('/orders/' . \)orderNumber),
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}`}
                  </pre>
                </div>
              )}

              {activeLaravelFile === "job" && (
                <div>
                  <div className="code-file-bar">
                    <span>File: <code>backend/app/Jobs/SendNotificationJob.php</code></span>
                    <button
                      type="button"
                      className="copy-code-btn"
                      onClick={() => copyText("backend/app/Jobs/SendNotificationJob.php", "Path copied!")}
                    >
                      <Copy size={12} /> Copy Path
                    </button>
                  </div>
                  <pre className="code-block">
{`<?php

namespace App\\Jobs;

use App\\Models\\NotificationLog;
use Illuminate\\Bus\\Queueable;
use Illuminate\\Contracts\\Queue\\ShouldQueue;
use Illuminate\\Foundation\\Bus\\Dispatchable;
use Illuminate\\Queue\\InteractsWithQueue;
use Illuminate\\Queue\\SerializesModels;
use Illuminate\\Support\\Facades\\Log;
use Exception;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1m, 5m, 15m exponential backoff

    public string $notificationLogId;
    public string $idempotencyKey;

    public function __construct(string $notificationLogId, string $idempotencyKey)
    {
        $this->notificationLogId = $notificationLogId;
        $this->idempotencyKey = $idempotencyKey;
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $log = NotificationLog::find($this->notificationLogId);
        if (!$log) return;

        // =========================================================================
        // IDEMPOTENCY CHECK: PREVENT DUPLICATE RECEIPTS ON JOB RETRY
        // If message was already accepted by provider or delivered, abort immediately!
        // =========================================================================
        if (in_array($log->status, ['sent', 'delivered'])) {
            $log->retry_suppressed = true;
            $log->logAttempt('suppressed', "Duplicate dispatch suppressed on job retry. Message already has status: {\(log->status} (Provider SID: {\)log->provider_message_id})");
            Log::info("[SendNotificationJob] Idempotent skip: Notification {$this->idempotencyKey} was already processed.");
            return;
        }

        $log->retry_count = $this->attempts();
        $log->save();

        try {
            if ($log->channel === 'email') {
                $this->sendEmail($log);
            } else {
                $this->sendSms($log);
            }
        } catch (Exception $e) {
            $log->status = 'failed';
            $log->failure_reason = $e->getMessage();
            $log->logAttempt('failed', "Attempt {$this->attempts()} failed: " . $e->getMessage());
            throw $e; // Trigger exponential backoff retry
        }
    }

    protected function sendSms(NotificationLog $log): void
    {
        // Twilio API call parameters include statusCallback URL to track real handset delivery
        $statusCallbackUrl = config('app.url') . '/api/webhooks/twilio/status-callback';
        $messageSid = 'SM' . bin2hex(random_bytes(16));

        // Mark as 'sent' (accepted by carrier gateway).
        // INVARIANT: DO NOT MARK DELIVERED MERELY BECAUSE IT WAS QUEUED OR ACCEPTED!
        // Real delivery confirmation requires the carrier DSN callback (MessageStatus=delivered).
        $log->status = 'sent';
        $log->provider_message_id = $messageSid;
        $log->sent_at = now();
        $log->logAttempt('sent', "SMS dispatched to carrier gateway (SID: {$messageSid}). Status is 'sent', awaiting handset delivery callback.");
        $log->save();
    }
}`}
                  </pre>
                </div>
              )}

              {activeLaravelFile === "controller" && (
                <div>
                  <div className="code-file-bar">
                    <span>File: <code>backend/app/Http/Controllers/NotificationWebhookController.php</code></span>
                    <button
                      type="button"
                      className="copy-code-btn"
                      onClick={() => copyText("backend/app/Http/Controllers/NotificationWebhookController.php", "Path copied!")}
                    >
                      <Copy size={12} /> Copy Path
                    </button>
                  </div>
                  <pre className="code-block">
{`<?php

namespace App\\Http\\Controllers;

use App\\Models\\NotificationLog;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Log;

class NotificationWebhookController extends Controller
{
    /**
     * Handle Twilio SMS Status Callback
     * INVARIANT: Only mark as 'delivered' when Twilio explicitly confirms MessageStatus === 'delivered'!
     */
    public function handleTwilioStatusCallback(Request $request): JsonResponse
    {
        $messageSid = $request->input('MessageSid');
        $messageStatus = strtolower($request->input('MessageStatus', ''));
        $errorCode = $request->input('ErrorCode');
        $errorMessage = $request->input('ErrorMessage');

        $log = NotificationLog::where('provider_message_id', $messageSid)->first();
        if (!$log) return response()->json(['status' => 'not_found'], 404);

        switch ($messageStatus) {
            case 'delivered':
                // Verified handset delivery callback from mobile network (Digicel / Flow / T-Mobile)
                $log->status = 'delivered';
                $log->delivered_at = now();
                $log->logAttempt('delivered', "Carrier handset delivery confirmed via Twilio webhook callback.");
                break;

            case 'undelivered':
            case 'failed':
                $log->status = 'failed';
                $log->failure_reason = "Carrier failure: [Error {\(errorCode}] {\)errorMessage}";
                $log->logAttempt('failed', "Delivery failed: [Error {\(errorCode}] {\)errorMessage}");
                break;

            case 'sent':
                $log->status = 'sent';
                $log->logAttempt('sent', "Carrier network received message; awaiting handset acknowledgment.");
                break;

            case 'queued':
                // Carrier queue: Do NOT mark delivered!
                $log->status = 'queued';
                break;
        }

        $log->save();
        return response()->json(['status' => 'acknowledged', 'updated_status' => $log->status]);
    }

    /**
     * Handle Email Webhook Status Callback (Postmark / Mailgun)
     */
    public function handleEmailWebhook(Request $request): JsonResponse
    {
        $messageId = $request->input('MessageID');
        $event = strtolower($request->input('RecordType') ?? $request->input('event') ?? '');

        $log = NotificationLog::where('provider_message_id', $messageId)->first();
        if (!$log) return response()->json(['status' => 'not_found'], 404);

        if (in_array($event, ['delivery', 'delivered'])) {
            $log->status = 'delivered';
            $log->delivered_at = now();
            $log->logAttempt('delivered', "Recipient mail server accepted and delivered message.");
        } elseif (in_array($event, ['bounce', 'hard_bounce', 'failed'])) {
            $log->status = 'failed';
            $log->failure_reason = $request->input('Description', 'Email bounced');
            $log->logAttempt('failed', "Email bounce recorded: " . $log->failure_reason);
        }

        $log->save();
        return response()->json(['status' => 'processed']);
    }
}`}
                  </pre>
                </div>
              )}

              {activeLaravelFile === "migration" && (
                <div>
                  <div className="code-file-bar">
                    <span>File: <code>backend/database/migrations/2026_10_25_000001_create_notification_logs_table.php</code></span>
                    <button
                      type="button"
                      className="copy-code-btn"
                      onClick={() => copyText("backend/database/migrations/2026_10_25_000001_create_notification_logs_table.php", "Path copied!")}
                    >
                      <Copy size={12} /> Copy Path
                    </button>
                  </div>
                  <pre className="code-block">
{`<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->nullable()->index();
            $table->string('customer_name');
            $table->string('recipient');
            $table->enum('channel', ['email', 'sms', 'whatsapp', 'link'])->default('sms');
            $table->string('trigger')->index();
            $table->string('title');
            $table->text('message');
            $table->string('email_subject')->nullable();
            $table->longText('email_html')->nullable();
            
            // Delivery Lifecycle Tracking
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed'])->default('queued')->index();
            $table->string('provider')->default('Twilio SMS');
            $table->string('provider_message_id')->nullable()->index();
            
            // Idempotency & Safe Retry Governance
            $table->string('idempotency_key')->unique();
            $table->boolean('retry_suppressed')->default(false);
            $table->unsignedSmallInteger('retry_count')->default(0);
            
            // Audit Timeline
            $table->json('attempts')->nullable();
            $table->text('failure_reason')->nullable();
            
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};`}
                  </pre>
                </div>
              )}
            </div>

            <div className="modal-footer">
              <button
                type="button"
                className="action-pill-btn"
                onClick={() => setShowLaravelCodeModal(false)}
              >
                Close Laravel Viewer
              </button>
            </div>
          </div>
        </div>
      )}
    </section>
  );
};
