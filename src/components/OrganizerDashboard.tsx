import React, { useEffect, useMemo, useState } from "react";
import { Check } from "lucide-react";
import {
  CashPaymentRecord,
  Currency,
  CustomOptionGroup,
  EventDetails,
  MenuCategory,
  MenuItem,
  MenuPricingType,
  NotificationTrigger,
  OrderStatus,
  Settings,
  TicketOrder,
} from "../types";
import { FishFryEventItem } from "../data/fishFryEvents";
import { fetchHostDashboardSummary } from "../lib/api";
import {
  HostNavToolId,
  OrganizerDashboardProps,
} from "./host/types";
import {
  buildHostSummaryMetricCards,
  buildHostUpcomingEventsList,
  computeHostCheckInStats,
  computeHostFinancialBreakdown,
  computeHostRolePermissions,
  computeHostTicketSalesStats,
} from "./host/financialCalculations";
import { HostDashboardHeader } from "./host/HostDashboardHeader";
import { HostNavigation } from "./host/HostNavigation";
import { HostOperationsOverview } from "./host/HostOperationsOverview";
import { HostEventStudioView } from "./host/HostEventStudioView";
import { HostOrdersView } from "./host/HostOrdersView";
import { HostPaymentsLedgerView } from "./host/HostPaymentsLedgerView";
import { HostAttendeesView } from "./host/HostAttendeesView";
import { HostFoodOrdersPrepView, MenuStatItem } from "./host/HostFoodOrdersPrepView";
import { HostReportsView } from "./host/HostReportsView";
import { HostWorkerAssignmentsView } from "./host/HostWorkerAssignmentsView";
import { HostSettingsSection } from "./host/HostSettingsSection";
import { HostModals } from "./host/HostModals";

export function OrganizerDashboard({
  eventDetails,
  menu,
  settings,
  orders,
  cashLedger,
  notificationLogs,
  organizers,
  activeOrganizerId,
  activeRolePerspective,
  tenantUsers,
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
  sessionUser,
  hostEvents = [],
  catalogEvents = [],
  handoverAuditLogs = [],
  isApiOnline = false,
  onSelectHostEvent,
  onSignOut,
}: OrganizerDashboardProps) {
  const [activeTool, setActiveTool] = useState<HostNavToolId>(() => {
    if (typeof window !== "undefined") {
      const t = new URLSearchParams(window.location.search).get("tool") as HostNavToolId | null;
      if (
        t &&
        [
          "dashboard",
          "events",
          "orders",
          "payments",
          "attendees",
          "food_orders",
          "reports",
          "workers",
          "settings",
        ].includes(t)
      ) {
        return t;
      }
    }
    return "dashboard";
  });

  // Menu Add/Edit Modal State
  const [editingItem, setEditingItem] = useState<MenuItem | null>(null);
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [dishName, setDishName] = useState("");
  const [dishCategory, setDishCategory] = useState<MenuCategory>("Mains");
  const [dishPricingType, setDishPricingType] = useState<MenuPricingType>("fixed");
  const [dishDescription, setDishDescription] = useState("");
  const [dishPriceUSD, setDishPriceUSD] = useState("10");
  const [dishPriceJMD, setDishPriceJMD] = useState("1500");
  const [dishImage, setDishImage] = useState("");
  const [dishStock, setDishStock] = useState("100");
  const [dishOptionGroups, setDishOptionGroups] = useState<CustomOptionGroup[]>([]);

  // Cash Receipt Form State
  const [cashFormName, setCashFormName] = useState("");
  const [cashFormPhone, setCashFormPhone] = useState("");
  const [cashFormOrderNum, setCashFormOrderNum] = useState("");
  const [cashFormAmount, setCashFormAmount] = useState("");
  const [cashFormCollector, setCashFormCollector] = useState("Treasurer Davis");
  const [cashFormNotes, setCashFormNotes] = useState("");

  // Reversal Form State
  const [reversingReceiptId, setReversingReceiptId] = useState<string | null>(null);
  const [reversalSupervisor, setReversalSupervisor] = useState("Supervisor Davis");
  const [reversalReason, setReversalReason] = useState("");

  // Operational Modals State
  const [customModalOrder, setCustomModalOrder] = useState<TicketOrder | null>(null);
  const [customChannel, setCustomChannel] = useState<"sms" | "email" | "whatsapp">("sms");
  const [customTrigger, setCustomTrigger] = useState<NotificationTrigger>("pickup_instructions");
  const [customMessage, setCustomMessage] = useState("");

  const [cashReceiptModalOrder, setCashReceiptModalOrder] = useState<TicketOrder | null>(null);
  const [modalCashCollector, setModalCashCollector] = useState("Treasurer Davis");
  const [modalCashNotes, setModalCashNotes] = useState(
    "Cash collected in person and verified against order total."
  );

  const [rejectModalOrder, setRejectModalOrder] = useState<TicketOrder | null>(null);
  const [rejectReasonText, setRejectReasonText] = useState(
    "Transaction reference not found on bank statement — please verify reference or resubmit."
  );

  const [partialModalOrder, setPartialModalOrder] = useState<TicketOrder | null>(null);
  const [partialAmountInput, setPartialAmountInput] = useState("");
  const [partialNotesInput, setPartialNotesInput] = useState(
    "Partial deposit verified on statement. Remaining balance due at pickup."
  );

  // Event Lifecycle & Flyer Pre-save State
  const [unpublishModalOpen, setUnpublishModalOpen] = useState(false);
  const [cancelEventModalOpen, setCancelEventModalOpen] = useState(false);
  const [flyerWarningToast, setFlyerWarningToast] = useState<string | null>(null);
  const [preSaveConfirmFlyer, setPreSaveConfirmFlyer] = useState<{
    url: string;
    mimeType: string;
    sizeBytes: number;
    width: number;
    height: number;
    storagePath: string;
    fileName: string;
  } | null>(null);

  const [shareCopied, setShareCopied] = useState(false);
  const [copiedMethodKey, setCopiedMethodKey] = useState<string | null>(null);
  const [savedToast, setSavedToast] = useState<string | null>(null);

  // Sync with backend HostDashboardController when authenticated
  useEffect(() => {
    if (!sessionUser) return;
    fetchHostDashboardSummary().catch(() => {
      // Offline / bundled fallback handled seamlessly
    });
  }, [sessionUser, activeOrganizerId]);

  const activeOrganizer = useMemo(
    () => organizers.find((o) => o.id === activeOrganizerId) || organizers[0],
    [organizers, activeOrganizerId]
  );

  const activeRoleUser = useMemo(
    () =>
      tenantUsers.find(
        (u) =>
          (u.organizerId === activeOrganizerId || u.role === "platform_admin") &&
          u.role === activeRolePerspective
      ) || tenantUsers[0],
    [tenantUsers, activeOrganizerId, activeRolePerspective]
  );

  const activeCurrency: Currency = eventDetails.currency || settings.currency || "USD";

  const permissions = useMemo(
    () => computeHostRolePermissions(activeRolePerspective),
    [activeRolePerspective]
  );

  const financials = useMemo(
    () => computeHostFinancialBreakdown(orders, cashLedger, activeCurrency),
    [orders, cashLedger, activeCurrency]
  );

  const ticketStats = useMemo(
    () => computeHostTicketSalesStats(orders, activeCurrency),
    [orders, activeCurrency]
  );

  const checkInStats = useMemo(
    () => computeHostCheckInStats(orders, activeCurrency),
    [orders, activeCurrency]
  );

  const metricCards = useMemo(
    () => buildHostSummaryMetricCards(financials, ticketStats, checkInStats, formatMoney),
    [financials, ticketStats, checkInStats, formatMoney]
  );

  const upcomingEvents = useMemo(
    () =>
      buildHostUpcomingEventsList({
        activeEvent: eventDetails,
        activeMenu: menu,
        activeOrganizerId,
        activeRole: activeRolePerspective,
        hostEvents,
        catalogEvents,
        orders,
        formatMoney,
      }),
    [
      eventDetails,
      menu,
      activeOrganizerId,
      activeRolePerspective,
      hostEvents,
      catalogEvents,
      orders,
      formatMoney,
    ]
  );

  const menuStats: MenuStatItem[] = useMemo(() => {
    return menu.map((item) => {
      let totalOrdered = 0;
      let paidCount = 0;
      let pendingCount = 0;
      let collectedCount = 0;

      for (const order of orders) {
        if (order.status === "rejected" || order.status === "refunded") continue;
        for (const orderItem of order.items) {
          if (orderItem.menuItemId === item.id || orderItem.name === item.name) {
            totalOrdered += orderItem.quantity;
            if (order.status === "paid" || order.status === "partially_paid") {
              paidCount += orderItem.quantity;
            } else {
              pendingCount += orderItem.quantity;
            }
            const col =
              orderItem.collectedQuantity !== undefined
                ? orderItem.collectedQuantity
                : orderItem.handoverStatus === "collected"
                ? orderItem.quantity
                : 0;
            collectedCount += col;
          }
        }
      }

      return {
        ...item,
        totalOrdered,
        paidCount,
        pendingCount,
        collectedCount,
        remainingToCook: Math.max(0, totalOrdered - collectedCount),
      };
    });
  }, [menu, orders]);

  const totalPortionsOrdered = useMemo(
    () => menuStats.reduce((acc, m) => acc + m.totalOrdered, 0),
    [menuStats]
  );

  const triggerToast = (msg: string) => {
    setSavedToast(msg);
    setTimeout(() => setSavedToast(null), 2800);
  };

  const handleManageEvent = (selectedEvent: EventDetails | FishFryEventItem) => {
    if (onSelectHostEvent) {
      onSelectHostEvent(selectedEvent);
    } else {
      onUpdateEventDetails(selectedEvent);
      if ("menu" in selectedEvent && Array.isArray(selectedEvent.menu)) {
        onUpdateMenu(selectedEvent.menu);
      }
    }
    setActiveTool("events");
    triggerToast(`Active event workspace switched to "${selectedEvent.title}"`);
  };

  // Menu Dish Handlers
  const openAddDishModal = () => {
    setEditingItem(null);
    setDishName("");
    setDishCategory("Mains");
    setDishPricingType("fixed");
    setDishDescription("");
    setDishPriceUSD("10");
    setDishPriceJMD("1500");
    setDishImage("/images/escovitch_fish.jpg");
    setDishStock("120");
    setDishOptionGroups([]);
    setIsAddModalOpen(true);
  };

  const openEditDishModal = (item: MenuItem) => {
    setEditingItem(item);
    setDishName(item.name);
    setDishCategory(item.category);
    setDishPricingType(item.pricingType || "fixed");
    setDishDescription(item.description);
    setDishPriceUSD(String(item.priceUSD));
    setDishPriceJMD(String(item.priceJMD));
    setDishImage(item.imageUrl);
    setDishStock(String(item.availableStock || 100));
    setDishOptionGroups(
      item.customOptionGroups ? JSON.parse(JSON.stringify(item.customOptionGroups)) : []
    );
    setIsAddModalOpen(false);
  };

  const handleSaveDish = (e: React.FormEvent) => {
    e.preventDefault();
    if (!dishName.trim()) return;
    const usd = parseFloat(dishPriceUSD) || 8;
    const jmd = parseFloat(dishPriceJMD) || 1200;
    const stock = parseInt(dishStock, 10) || 100;

    if (editingItem) {
      const updated = menu.map((m) =>
        m.id === editingItem.id
          ? {
              ...m,
              name: dishName.trim(),
              category: dishCategory,
              pricingType: dishPricingType,
              description: dishDescription.trim(),
              priceUSD: usd,
              priceJMD: jmd,
              imageUrl: dishImage.trim() || m.imageUrl,
              availableStock: stock,
              customOptionGroups:
                dishPricingType === "customizable" ? dishOptionGroups : undefined,
            }
          : m
      );
      onUpdateMenu(updated);
      setEditingItem(null);
      triggerToast(`Updated "${dishName.trim()}"`);
    } else {
      const newItem: MenuItem = {
        id: `item-${Date.now()}`,
        name: dishName.trim(),
        category: dishCategory,
        pricingType: dishPricingType,
        description: dishDescription.trim() || "Freshly prepared for your event menu.",
        priceUSD: usd,
        priceJMD: jmd,
        imageUrl: dishImage.trim() || "/images/escovitch_fish.jpg",
        availableStock: stock,
        allowSpecialInstructions: true,
        customOptionGroups:
          dishPricingType === "customizable" ? dishOptionGroups : undefined,
      };
      onUpdateMenu([...menu, newItem]);
      setIsAddModalOpen(false);
      triggerToast(`Added "${dishName.trim()}" to event menu`);
    }
  };

  const handleDeleteDish = (id: string) => {
    onUpdateMenu(menu.filter((m) => m.id !== id));
    triggerToast("Dish removed from menu");
  };

  const handlePaymentStatusAdapter = (
    orderId: string,
    status: OrderStatus,
    notes?: string,
    amountPaid?: number
  ) => {
    onUpdatePaymentStatus(
      orderId,
      status,
      {
        amountPaid,
        notes,
        verifier: activeRoleUser?.name || "Event Host",
        rejectionReason: status === "rejected" ? notes : undefined,
      }
    );
  };

  const issueCashReceiptRecord = (data: {
    customerName: string;
    customerPhone: string;
    orderNumber?: string;
    ticketCode?: string;
    amount: number;
    receivedBy: string;
    notes: string;
  }) => {
    const linkedOrder = orders.find(
      (o) =>
        (data.orderNumber && o.orderNumber === data.orderNumber) ||
        (data.ticketCode && o.ticketCode === data.ticketCode)
    );
    const record: CashPaymentRecord = {
      id: `cash-${Date.now()}-${Math.floor(100 + Math.random() * 900)}`,
      organizerId: activeOrganizerId,
      transactionType: "receipt",
      receiptNumber: `RCPT-${new Date().getFullYear()}-${String(cashLedger.length + 101).padStart(4, "0")}`,
      customerName: data.customerName,
      customerPhone: data.customerPhone,
      eventName: eventDetails.title,
      orderId: linkedOrder?.id,
      orderNumber: data.orderNumber || linkedOrder?.orderNumber,
      ticketCode: data.ticketCode || linkedOrder?.ticketCode,
      amount: data.amount,
      currency: activeCurrency,
      receivedAt: new Date().toISOString(),
      receivedBy: data.receivedBy,
      notes: data.notes,
      eventId: eventDetails.id,
    };
    onRecordCashPayment(record, linkedOrder?.id);
  };

  const handleReverseCashAdapter = (
    receiptId: string,
    reversedBy: string,
    reason: string
  ) => {
    const orig = cashLedger.find((r) => r.id === receiptId);
    if (!orig) return;
    const reversalRecord: CashPaymentRecord = {
      id: `rev-${Date.now()}`,
      organizerId: activeOrganizerId,
      transactionType: "reversal",
      receiptNumber: `REV-${orig.receiptNumber}`,
      customerName: orig.customerName,
      customerPhone: orig.customerPhone,
      eventName: orig.eventName || eventDetails.title,
      orderId: orig.orderId,
      orderNumber: orig.orderNumber,
      ticketCode: orig.ticketCode,
      amount: -Math.abs(orig.amount),
      currency: orig.currency,
      receivedAt: new Date().toISOString(),
      receivedBy: reversedBy,
      notes: `Reversal of ${orig.receiptNumber}: ${reason}`,
      originalRecordId: orig.id,
      reversalOfId: orig.id,
      reversalReason: reason,
      reversalAuthorizedBy: reversedBy,
      eventId: eventDetails.id,
      isReversed: true,
    };
    onReverseCashPayment(reversalRecord, orig.orderId);
  };

  const handleUpdateSettingsAdapter = (nextSettings: Settings) => {
    onUpdateSettings(() => nextSettings);
  };

  const handleRecordCashSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!cashFormName.trim() || !cashFormPhone.trim() || !cashFormAmount.trim()) return;
    const amt = parseFloat(cashFormAmount);
    if (isNaN(amt) || amt <= 0) return;

    issueCashReceiptRecord({
      customerName: cashFormName.trim(),
      customerPhone: cashFormPhone.trim(),
      orderNumber: cashFormOrderNum.trim() || undefined,
      amount: amt,
      receivedBy: cashFormCollector.trim() || "Event Treasurer",
      notes: cashFormNotes.trim() || "In-person cash payment verified.",
    });

    setCashFormName("");
    setCashFormPhone("");
    setCashFormOrderNum("");
    setCashFormAmount("");
    setCashFormNotes("");
    triggerToast("Official cash receipt logged in ledger");
  };

  const handleFlyerUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const allowedMimeTypes = ["image/jpeg", "image/png", "image/webp"];
    if (!allowedMimeTypes.includes(file.type)) {
      setFlyerWarningToast("Unsupported format. Please upload JPG, PNG, or WebP.");
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      setFlyerWarningToast("Flyer exceeds 5MB limit.");
      return;
    }
    setFlyerWarningToast(null);
    const reader = new FileReader();
    reader.onload = () => {
      if (typeof reader.result === "string") {
        const dataUrl = reader.result;
        const img = new Image();
        img.onload = () => {
          setPreSaveConfirmFlyer({
            url: dataUrl,
            mimeType: file.type,
            sizeBytes: file.size,
            width: img.width,
            height: img.height,
            storagePath: `${activeOrganizer.flyerStoragePrefix}${Date.now()}_${file.name.replace(/[^a-zA-Z0-9._-]/g, "_")}`,
            fileName: file.name,
          });
        };
        img.src = dataUrl;
      }
    };
    reader.readAsDataURL(file);
  };

  const handleDishImageUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
      if (typeof reader.result === "string") {
        setDishImage(reader.result);
      }
    };
    reader.readAsDataURL(file);
  };

  const copyShareUrl = (text: string) => {
    navigator.clipboard?.writeText(text);
    setShareCopied(true);
    setTimeout(() => setShareCopied(false), 2000);
  };

  const copyMethodDetails = (text: string, key: string) => {
    navigator.clipboard?.writeText(text);
    setCopiedMethodKey(key);
    setTimeout(() => setCopiedMethodKey(null), 1800);
  };

  const tenantScopedWorkerCount = tenantUsers.filter(
    (u) => u.organizerId === activeOrganizerId
  ).length;

  return (
    <div
      id="island-event-host-dashboard-workspace"
      style={{
        background: "linear-gradient(180deg, #060913 0%, #0B1120 45%, #090E1A 100%)",
        borderRadius: "20px",
        padding: "20px",
        border: "1px solid rgba(148, 163, 184, 0.16)",
        boxShadow: "0 24px 64px rgba(2, 6, 23, 0.55)",
        color: "#F8FAFC",
        display: "flex",
        flexDirection: "column",
        gap: "18px",
      }}
    >
      {savedToast && (
        <div className="mini-toast organizer">
          <Check size={14} />
          <span>{savedToast}</span>
        </div>
      )}

      {/* 1. Host Dashboard Header */}
      <HostDashboardHeader
        eventDetails={eventDetails}
        organizers={organizers}
        activeOrganizer={activeOrganizer}
        activeOrganizerId={activeOrganizerId}
        activeRolePerspective={activeRolePerspective}
        activeRoleUser={activeRoleUser}
        permissions={permissions}
        sessionUser={sessionUser}
        notificationLogs={notificationLogs}
        isApiOnline={isApiOnline}
        activeTool={activeTool}
        onNavigateTool={setActiveTool}
        onSwitchOrganizer={onSwitchOrganizer}
        onSwitchRolePerspective={onSwitchRolePerspective}
        onOpenGatewayModal={onOpenGatewayModal}
        onResetDemo={onResetDemo}
        onSignOut={onSignOut}
      />

      {/* 2. Role-Adapted Host Navigation (9 Authorized Tools) */}
      <HostNavigation
        activeTool={activeTool}
        onSelectTool={setActiveTool}
        permissions={permissions}
        upcomingEventsCount={upcomingEvents.length}
        totalOrdersCount={orders.length}
        pendingPaymentsCount={financials.pendingOrdersCount}
        checkedInCount={checkInStats.checkedInOrdersCount}
        confirmedEligibleCount={checkInStats.confirmedEligibleOrdersCount}
        totalPortionsOrdered={totalPortionsOrdered}
        workersCount={tenantScopedWorkerCount}
      />

      {/* 3. Active Host Tool Workspace */}
      {activeTool === "dashboard" && (
        <HostOperationsOverview
          eventDetails={eventDetails}
          menu={menu}
          settings={settings}
          orders={orders}
          permissions={permissions}
          financials={financials}
          checkInStats={checkInStats}
          metricCards={metricCards}
          upcomingEvents={upcomingEvents}
          shareCopied={shareCopied}
          onCopyShareLink={() =>
            copyShareUrl(
              eventDetails.shareableUrl ||
                `${window.location.origin}/e/${eventDetails.slug || eventDetails.id}`
            )
          }
          onNavigateTool={setActiveTool}
          onManageEvent={handleManageEvent}
          onUpdatePaymentStatus={handlePaymentStatusAdapter}
          onUpdateFulfillmentStatus={onUpdateFulfillmentStatus}
          onOpenPartialModal={(order) => {
            setPartialModalOrder(order);
            setPartialAmountInput(String(Math.round(order.totalAmount / 2)));
          }}
          onOpenCashModal={(order) => setCashReceiptModalOrder(order)}
          onOpenRejectModal={(order) => setRejectModalOrder(order)}
          formatMoney={formatMoney}
        />
      )}

      {activeTool === "events" && (
        <HostEventStudioView
          eventDetails={eventDetails}
          activeOrganizer={activeOrganizer}
          permissions={permissions}
          upcomingEvents={upcomingEvents}
          flyerWarningToast={flyerWarningToast}
          preSaveConfirmFlyer={preSaveConfirmFlyer}
          shareCopied={shareCopied}
          onManageEvent={handleManageEvent}
          onNavigateTool={setActiveTool}
          onUpdateEventDetails={onUpdateEventDetails}
          onOpenUnpublishModal={() => setUnpublishModalOpen(true)}
          onOpenCancelEventModal={() => setCancelEventModalOpen(true)}
          onFlyerUpload={handleFlyerUpload}
          onConfirmPreSavedFlyer={() => {
            if (!preSaveConfirmFlyer) return;
            onUpdateEventDetails({
              ...eventDetails,
              flyerUrl: preSaveConfirmFlyer.url,
              flyerMimeType: preSaveConfirmFlyer.mimeType,
              flyerSizeBytes: preSaveConfirmFlyer.sizeBytes,
              flyerDimensions: {
                width: preSaveConfirmFlyer.width,
                height: preSaveConfirmFlyer.height,
              },
              flyerStoragePath: preSaveConfirmFlyer.storagePath,
              isFlyerPublic: true,
            });
            setPreSaveConfirmFlyer(null);
            triggerToast("Event flyer verified and saved!");
          }}
          onCancelPreSavedFlyer={() => setPreSaveConfirmFlyer(null)}
          onRemoveFlyer={() => {
            onUpdateEventDetails({ ...eventDetails, flyerUrl: undefined });
            triggerToast("Event flyer removed");
          }}
          onCopyShareUrl={copyShareUrl}
          onSaveToast={triggerToast}
          menu={menu}
          onUpdateMenu={onUpdateMenu}
          onOpenAddDishModal={openAddDishModal}
          onOpenEditDishModal={openEditDishModal}
          onDeleteDish={handleDeleteDish}
          formatMoney={formatMoney}
        />
      )}

      {activeTool === "orders" && (
        <HostOrdersView
          orders={orders}
          currency={activeCurrency}
          permissions={permissions}
          financials={financials}
          ticketStats={ticketStats}
          onNavigateTool={setActiveTool}
          onUpdatePaymentStatus={handlePaymentStatusAdapter}
          onUpdateFulfillmentStatus={onUpdateFulfillmentStatus}
          onOpenPartialModal={(order) => {
            setPartialModalOrder(order);
            setPartialAmountInput(String(Math.round(order.totalAmount / 2)));
          }}
          onOpenCashModal={(order) => setCashReceiptModalOrder(order)}
          onOpenRejectModal={(order) => setRejectModalOrder(order)}
          onOpenCustomMessageModal={(order) => {
            setCustomModalOrder(order);
            setCustomMessage(
              `Hello ${order.customerName}, your order #${order.orderNumber} for ${eventDetails.title} is ready for pickup!`
            );
          }}
          formatMoney={formatMoney}
        />
      )}

      {activeTool === "payments" && (
        <HostPaymentsLedgerView
          eventDetails={eventDetails}
          orders={orders}
          cashLedger={cashLedger}
          permissions={permissions}
          financials={financials}
          cashFormName={cashFormName}
          setCashFormName={setCashFormName}
          cashFormPhone={cashFormPhone}
          setCashFormPhone={setCashFormPhone}
          cashFormOrderNum={cashFormOrderNum}
          setCashFormOrderNum={setCashFormOrderNum}
          cashFormAmount={cashFormAmount}
          setCashFormAmount={setCashFormAmount}
          cashFormCollector={cashFormCollector}
          setCashFormCollector={setCashFormCollector}
          cashFormNotes={cashFormNotes}
          setCashFormNotes={setCashFormNotes}
          onRecordCashSubmit={handleRecordCashSubmit}
          reversingReceiptId={reversingReceiptId}
          setReversingReceiptId={setReversingReceiptId}
          reversalSupervisor={reversalSupervisor}
          setReversalSupervisor={setReversalSupervisor}
          reversalReason={reversalReason}
          setReversalReason={setReversalReason}
          onReverseCashPayment={handleReverseCashAdapter}
          onUpdatePaymentStatus={handlePaymentStatusAdapter}
          onOpenPartialModal={(order) => {
            setPartialModalOrder(order);
            setPartialAmountInput(String(Math.round(order.totalAmount / 2)));
          }}
          onOpenCashModal={(order) => setCashReceiptModalOrder(order)}
          onOpenRejectModal={(order) => setRejectModalOrder(order)}
          onOpenCustomMessageModal={(order) => {
            setCustomModalOrder(order);
            setCustomMessage(
              `Hello ${order.customerName}, regarding your payment for Order #${order.orderNumber}:`
            );
          }}
          formatMoney={formatMoney}
        />
      )}

      {activeTool === "attendees" && (
        <HostAttendeesView
          orders={orders}
          currency={activeCurrency}
          permissions={permissions}
          checkInStats={checkInStats}
          handoverAuditLogs={handoverAuditLogs}
          onNavigateTool={setActiveTool}
          onUpdateFulfillmentStatus={onUpdateFulfillmentStatus}
          formatMoney={formatMoney}
        />
      )}

      {activeTool === "food_orders" && (
        <HostFoodOrdersPrepView
          menu={menu}
          menuStats={menuStats}
          totalPortionsOrdered={totalPortionsOrdered}
          currency={activeCurrency}
          permissions={permissions}
          onOpenAddDishModal={openAddDishModal}
          onOpenEditDishModal={openEditDishModal}
          onDeleteDish={handleDeleteDish}
          formatMoney={formatMoney}
        />
      )}

      {activeTool === "reports" && (
        <HostReportsView
          orders={orders}
          cashLedger={cashLedger}
          notificationLogs={notificationLogs}
          activeOrganizer={activeOrganizer}
          permissions={permissions}
          financials={financials}
          onRetryNotification={onRetryNotification}
          onBroadcastEventReminder={() => {
            onBroadcastEventReminder();
            triggerToast("Broadcast event reminder dispatched to verified attendees");
          }}
          formatMoney={formatMoney}
        />
      )}

      {activeTool === "workers" && (
        <HostWorkerAssignmentsView
          eventDetails={eventDetails}
          activeOrganizerId={activeOrganizerId}
          tenantUsers={tenantUsers}
          permissions={permissions}
          onSwitchRolePerspective={onSwitchRolePerspective}
        />
      )}

      {activeTool === "settings" && (
        <HostSettingsSection
          eventDetails={eventDetails}
          menu={menu}
          settings={settings}
          orders={orders}
          cashLedger={cashLedger}
          notificationLogs={notificationLogs}
          activeOrganizer={activeOrganizer}
          tenantUsers={tenantUsers}
          permissions={permissions}
          copiedMethodKey={copiedMethodKey}
          onCopyMethodDetails={copyMethodDetails}
          onUpdateEventDetails={onUpdateEventDetails}
          onUpdateMenu={onUpdateMenu}
          onUpdateSettings={handleUpdateSettingsAdapter}
          onOpenGatewayModal={onOpenGatewayModal}
          onSaveSettingsToast={() => triggerToast("Payment & regional settings saved")}
          formatMoney={formatMoney}
        />
      )}

      {/* 4. Operational Modals */}
      <HostModals
        eventDetails={eventDetails}
        formatMoney={formatMoney}
        isDishModalOpen={isAddModalOpen || !!editingItem}
        editingItem={editingItem}
        dishName={dishName}
        setDishName={setDishName}
        dishCategory={dishCategory}
        setDishCategory={setDishCategory}
        dishPricingType={dishPricingType}
        setDishPricingType={setDishPricingType}
        dishDescription={dishDescription}
        setDishDescription={setDishDescription}
        dishPriceUSD={dishPriceUSD}
        setDishPriceUSD={setDishPriceUSD}
        dishPriceJMD={dishPriceJMD}
        setDishPriceJMD={setDishPriceJMD}
        dishImage={dishImage}
        setDishImage={setDishImage}
        dishStock={dishStock}
        setDishStock={setDishStock}
        dishOptionGroups={dishOptionGroups}
        onDishImageUpload={handleDishImageUpload}
        onAddOptionGroup={() =>
          setDishOptionGroups([
            ...dishOptionGroups,
            {
              id: `grp-${Date.now()}`,
              title: "New Option Group",
              required: true,
              choices: [{ label: "Standard Choice", extraPriceUSD: 0, extraPriceJMD: 0 }],
            },
          ])
        }
        onUpdateOptionGroupTitle={(groupId, title) =>
          setDishOptionGroups(
            dishOptionGroups.map((g) => (g.id === groupId ? { ...g, title } : g))
          )
        }
        onRemoveOptionGroup={(groupId) =>
          setDishOptionGroups(dishOptionGroups.filter((g) => g.id !== groupId))
        }
        onAddChoiceToGroup={(groupId) =>
          setDishOptionGroups(
            dishOptionGroups.map((g) =>
              g.id === groupId
                ? {
                    ...g,
                    choices: [
                      ...g.choices,
                      { label: "New Option", extraPriceUSD: 0, extraPriceJMD: 0 },
                    ],
                  }
                : g
            )
          )
        }
        onUpdateChoiceInGroup={(groupId, index, field, val) =>
          setDishOptionGroups(
            dishOptionGroups.map((g) => {
              if (g.id !== groupId) return g;
              const updated = [...g.choices];
              if (field === "label") {
                updated[index] = { ...updated[index], label: val };
              } else if (field === "extraPriceUSD") {
                updated[index] = { ...updated[index], extraPriceUSD: parseFloat(val) || 0 };
              } else {
                updated[index] = { ...updated[index], extraPriceJMD: parseFloat(val) || 0 };
              }
              return { ...g, choices: updated };
            })
          )
        }
        onRemoveChoiceFromGroup={(groupId, index) =>
          setDishOptionGroups(
            dishOptionGroups.map((g) =>
              g.id === groupId
                ? { ...g, choices: g.choices.filter((_, i) => i !== index) }
                : g
            )
          )
        }
        onSaveDish={handleSaveDish}
        onCloseDishModal={() => {
          setIsAddModalOpen(false);
          setEditingItem(null);
        }}
        customModalOrder={customModalOrder}
        customChannel={customChannel}
        setCustomChannel={setCustomChannel}
        customTrigger={customTrigger}
        setCustomTrigger={setCustomTrigger}
        customMessage={customMessage}
        setCustomMessage={setCustomMessage}
        onSendCustomSubmit={(e) => {
          e.preventDefault();
          if (!customModalOrder || !customMessage.trim()) return;
          onSendCustomNotification(
            customModalOrder.id,
            customChannel,
            customTrigger,
            customMessage.trim()
          );
          setCustomModalOrder(null);
          setCustomMessage("");
          triggerToast("Custom notification queued for delivery");
        }}
        onCloseCustomModal={() => setCustomModalOrder(null)}
        cashReceiptModalOrder={cashReceiptModalOrder}
        modalCashCollector={modalCashCollector}
        setModalCashCollector={setModalCashCollector}
        modalCashNotes={modalCashNotes}
        setModalCashNotes={setModalCashNotes}
        onConfirmModalCashReceipt={() => {
          if (!cashReceiptModalOrder) return;
          issueCashReceiptRecord({
            customerName: cashReceiptModalOrder.customerName,
            customerPhone: cashReceiptModalOrder.customerPhone,
            orderNumber: cashReceiptModalOrder.orderNumber,
            ticketCode: cashReceiptModalOrder.ticketCode,
            amount: cashReceiptModalOrder.totalAmount,
            receivedBy: modalCashCollector.trim() || "Treasurer Davis",
            notes: modalCashNotes.trim() || "Cash payment verified in person.",
          });
          setCashReceiptModalOrder(null);
          triggerToast("Cash receipt issued and order marked PAID");
        }}
        onCloseCashReceiptModal={() => setCashReceiptModalOrder(null)}
        rejectModalOrder={rejectModalOrder}
        rejectReasonText={rejectReasonText}
        setRejectReasonText={setRejectReasonText}
        onConfirmRejectOrder={() => {
          if (!rejectModalOrder) return;
          handlePaymentStatusAdapter(
            rejectModalOrder.id,
            "rejected",
            rejectReasonText.trim() || "Payment reference not verified."
          );
          setRejectModalOrder(null);
          triggerToast("Payment rejected and customer notified");
        }}
        onCloseRejectModal={() => setRejectModalOrder(null)}
        partialModalOrder={partialModalOrder}
        partialAmountInput={partialAmountInput}
        setPartialAmountInput={setPartialAmountInput}
        partialNotesInput={partialNotesInput}
        setPartialNotesInput={setPartialNotesInput}
        onConfirmPartialOrder={() => {
          if (!partialModalOrder) return;
          const amt = parseFloat(partialAmountInput);
          if (isNaN(amt) || amt <= 0) return;
          handlePaymentStatusAdapter(
            partialModalOrder.id,
            "partially_paid",
            partialNotesInput.trim(),
            amt
          );
          setPartialModalOrder(null);
          triggerToast("Partial deposit verified and recorded");
        }}
        onClosePartialModal={() => setPartialModalOrder(null)}
        unpublishModalOpen={unpublishModalOpen}
        onConfirmUnpublishEvent={() => {
          onUpdateEventDetails({
            ...eventDetails,
            status: "unpublished",
            isOrderingOpen: false,
          });
          setUnpublishModalOpen(false);
          triggerToast("Event moved to UNPUBLISHED");
        }}
        onCloseUnpublishModal={() => setUnpublishModalOpen(false)}
        cancelEventModalOpen={cancelEventModalOpen}
        onConfirmCancelEvent={() => {
          onUpdateEventDetails({
            ...eventDetails,
            status: "cancelled",
            isOrderingOpen: false,
          });
          setCancelEventModalOpen(false);
          triggerToast("Event marked as CANCELLED");
        }}
        onCloseCancelEventModal={() => setCancelEventModalOpen(false)}
      />
    </div>
  );
}
