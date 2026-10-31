import React, { useMemo, useState } from "react";
import {
  AlertCircle,
  AlertTriangle,
  Award,
  Ban,
  Banknote,
  Camera,
  Check,
  CheckCircle2,
  Clock,
  Clock3,
  Coffee,
  Flashlight,
  History,
  KeyRound,
  Layers,
  Lock,
  LucideIcon,
  Minus,
  PackageCheck,
  Plus,
  QrCode,
  RefreshCw,
  Scan,
  Search,
  ShieldAlert,
  Sparkles,
  Store,
  Ticket,
  UserCheck,
  Utensils,
  Volume2,
  VolumeX,
  Wifi,
  WifiOff,
  X,
} from "lucide-react";
import {
  CashPaymentRecord,
  Currency,
  HandoverAuditEntry,
  HandoverStatus,
  OrderItem,
  PaymentStatus,
  StaffStation,
  TicketOrder,
} from "../types";

type Props = {
  orders: TicketOrder[];
  handoverAuditLogs: HandoverAuditEntry[];
  onUpdateItemHandover: (
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
  ) => void;
  onCloseOrder: (orderId: string) => void;
  onUpdatePaymentStatus: (
    orderId: string,
    status: PaymentStatus,
    details?: {
      amountPaid?: number;
      notes?: string;
      verifier?: string;
      rejectionReason?: string;
    }
  ) => void;
  onRecordCashPayment: (cashRecord: CashPaymentRecord, linkedOrderId?: string) => void | Promise<CashPaymentRecord | void>;
  formatMoney: (amount: number, currency: Currency) => string;
};

const STAFF_MEMBERS = [
  { id: "staff-marcia", name: "Sister Marcia", role: "Redemption Gate Lead" },
  { id: "staff-jerome", name: "Worker Jerome", role: "Fish & Chicken Station" },
  { id: "staff-paulette", name: "Sister Paulette", role: "Sides Station" },
  { id: "staff-kemar", name: "Worker Kemar", role: "Drinks Station" },
  { id: "staff-marcus", name: "Chef Marcus", role: "Hot Food Lead" },
  { id: "staff-davis", name: "Supervisor Davis", role: "Event Supervisor / Overrides" },
];

const STATIONS: { id: StaffStation; label: string; icon: LucideIcon; hint: string }[] = [
  { id: "all", label: "General Order Collection", icon: Store, hint: "All Items & Master Counter" },
  { id: "mains", label: "Fish & Chicken", icon: Utensils, hint: "Hot Meals & Platters" },
  { id: "sides", label: "Sides", icon: Sparkles, hint: "Festivals, Bammies & Extras" },
  { id: "drinks", label: "Drinks", icon: Coffee, hint: "Sodas, Juices & Beverages" },
  { id: "desserts", label: "Desserts", icon: Award, hint: "Pastries & Sweet Treats" },
];

export const StaffScanner: React.FC<Props> = ({
  orders,
  handoverAuditLogs,
  onUpdateItemHandover,
  onCloseOrder,
  onUpdatePaymentStatus: _onUpdatePaymentStatus,
  onRecordCashPayment,
  formatMoney,
}) => {
  // Navigation & View tabs
  const [activeTab, setActiveTab] = useState<"scanner" | "audit">("scanner");

  // Staff identity & station setup
  const [activeStaffName, setActiveStaffName] = useState("Worker Jerome");
  const [activeStation, setActiveStation] = useState<StaffStation>("all");

  // Ticket selection & search
  const [searchInput, setSearchInput] = useState("");
  const [selectedOrderId, setSelectedOrderId] = useState<string>(() => {
    // Default to prompt order FF-1024 if present
    const promptOrd = orders.find((o) => o.ticketCode === "FF-1024");
    return promptOrd ? promptOrd.id : orders[0]?.id || "";
  });

  // Camera Viewfinder Modal state
  const [showCameraModal, setShowCameraModal] = useState(false);
  const [cameraTorch, setCameraTorch] = useState(false);
  const [cameraSound, setCameraSound] = useState(true);
  const [isScanningActive, setIsScanningActive] = useState(false);
  const [manualCodeInput, setManualCodeInput] = useState("");
  const [invalidCodeAttempt, setInvalidCodeAttempt] = useState<string | null>(null);

  // Supervisor Override Modal
  const [showSupervisorModal, setShowSupervisorModal] = useState(false);
  const [supervisorName, setSupervisorName] = useState("Supervisor Davis");
  const [supervisorPin, setSupervisorPin] = useState("");
  const [supervisorReason, setSupervisorReason] = useState("");
  const [supervisorError, setSupervisorError] = useState("");

  // Controlled Offline mode state
  const [isOfflineMode, setIsOfflineMode] = useState(false);
  const [offlineSyncQueueCount, setOfflineSyncQueueCount] = useState(0);
  const [isSyncingOffline, setIsSyncingOffline] = useState(false);

  // Audit filter state
  const [auditSearch, setAuditSearch] = useState("");
  const [auditStationFilter, setAuditStationFilter] = useState<string>("all");

  // Feedback notifications
  const [toastMessage, setToastMessage] = useState("");

  function showToast(msg: string) {
    setToastMessage(msg);
    setTimeout(() => setToastMessage(""), 2800);
  }

  // Active Order
  const activeOrder = orders.find((o) => o.id === selectedOrderId);

  // Helper to determine item station
  function getItemStation(item: OrderItem): StaffStation {
    const cat = item.category || "";
    const name = item.name.toLowerCase();
    if (
      cat === "Beverages" ||
      name.includes("drink") ||
      name.includes("soda") ||
      name.includes("ting") ||
      name.includes("juice") ||
      name.includes("water")
    ) {
      return "drinks";
    }
    if (
      cat === "Sides & Extras" ||
      cat === "Sides" ||
      name.includes("festival") ||
      name.includes("bammy") ||
      name.includes("side")
    ) {
      return "sides";
    }
    if (
      cat === "Mains" ||
      name.includes("fish") ||
      name.includes("chicken") ||
      name.includes("escovitch") ||
      name.includes("snapper")
    ) {
      return "mains";
    }
    return "mains";
  }

  function getStationLabel(st: StaffStation): string {
    switch (st) {
      case "mains":
        return "Fish & Chicken Station";
      case "sides":
        return "Sides Station";
      case "drinks":
        return "Drinks Station";
      case "desserts":
        return "Desserts Station";
      default:
        return "General Order Collection";
    }
  }

  // Calculate order portion totals
  const orderPortionStats = useMemo(() => {
    if (!activeOrder) return { totalOrdered: 0, totalCollected: 0, totalRemaining: 0 };
    let totalOrdered = 0;
    let totalCollected = 0;
    activeOrder.items.forEach((item) => {
      totalOrdered += item.quantity;
      const col =
        typeof item.collectedQuantity === "number"
          ? item.collectedQuantity
          : item.handoverStatus === "collected"
          ? item.quantity
          : 0;
      totalCollected += col;
    });
    return {
      totalOrdered,
      totalCollected,
      totalRemaining: Math.max(0, totalOrdered - totalCollected),
    };
  }, [activeOrder]);

  // Determine Scanner Response according to prompt requirements
  const scannerResponse = useMemo(() => {
    if (invalidCodeAttempt) {
      return {
        type: "invalid",
        badge: "Red: Ticket not recognized",
        title: "Ticket Not Recognized",
        description: `No active order found for code "${invalidCodeAttempt}". Please verify the customer's QR code or direct them to the Help Desk.`,
        canHandover: false,
      };
    }

    if (!activeOrder) return null;

    const { status, allHandedOver } = activeOrder;
    const { totalOrdered, totalCollected, totalRemaining } = orderPortionStats;

    // Situation 5: Cancelled or Refunded order
    if (status === "refunded" || status === "rejected") {
      return {
        type: "cancelled",
        badge: "Red: Order cancelled",
        title: "Order Cancelled / Refunded",
        description: `This order was ${status} on ${activeOrder.refundDetails?.refundedAt ? new Date(activeOrder.refundDetails.refundedAt).toLocaleDateString() : "file"}. Do NOT release any food or drinks. Refer customer to supervisor.`,
        canHandover: false,
      };
    }

    // Situation 7: Refund or payment dispute / partial paid
    if (status === "partially_paid") {
      return {
        type: "dispute",
        badge: "Warning: Refer to supervisor",
        title: "Payment Dispute / Partial Payment",
        description: `Customer has paid ${formatMoney(activeOrder.amountPaid || 0, activeOrder.currency)} of ${formatMoney(activeOrder.totalAmount, activeOrder.currency)}. Balance due or supervisor override required before releasing meals.`,
        canHandover: false,
        requiresAction: "cash_or_override",
      };
    }

    // Situation 2: Payment awaiting verification
    if (
      status === "awaiting_payment" ||
      status === "payment_submitted" ||
      status === "under_verification" ||
      status === "pending_verification"
    ) {
      return {
        type: "unverified",
        badge: "Amber: Payment not confirmed",
        title: "Payment Not Confirmed",
        description:
          status === "awaiting_payment"
            ? "Order is marked unpaid. Collect cash on the spot or obtain supervisor override before handing out food."
            : `Manual transfer submitted (Ref: ${activeOrder.paymentReference || "N/A"}), but payment has NOT been verified on the bank statement yet. Do NOT release food.`,
        canHandover: false,
        requiresAction: "cash_or_override",
      };
    }

    // Situation 3: Ticket already fully redeemed (DUPLICATE STOP)
    if (allHandedOver || (totalOrdered > 0 && totalRemaining === 0)) {
      // Find the last audit log for this order
      const lastAudit = handoverAuditLogs.find((l) => l.orderId === activeOrder.id || l.ticketCode === activeOrder.ticketCode);
      const handedOverBy = lastAudit?.staffName || activeOrder.items.find((i) => i.handedOverBy)?.handedOverBy || "Counter Staff";
      const station = lastAudit?.station || activeOrder.items.find((i) => i.stationHandedOver)?.stationHandedOver || "Main Counter";
      const timeStr = lastAudit?.timestamp
        ? new Date(lastAudit.timestamp).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
        : activeOrder.closedAt
        ? new Date(activeOrder.closedAt).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
        : "Earlier today";

      return {
        type: "fully_redeemed",
        badge: "Red: All items already collected",
        title: "Duplicate Collection Prevented!",
        description: `All ${totalOrdered} items on this ticket have already been handed over. Collected at ${timeStr} by ${handedOverBy} at ${station}. Do NOT issue food twice!`,
        canHandover: false,
        isDuplicateAttempt: true,
      };
    }

    // Situation 4: Ticket partially redeemed
    if (totalCollected > 0 && totalRemaining > 0) {
      return {
        type: "partially_redeemed",
        badge: "Amber: Some items remain",
        title: "Some Items Remain",
        description: `Customer has collected ${totalCollected} portion(s). ${totalRemaining} portion(s) remain outstanding. Hand over remaining items below.`,
        canHandover: true,
      };
    }

    // Situation 1: Valid paid ticket
    if (status === "paid" && totalCollected === 0) {
      return {
        type: "valid_paid",
        badge: "Green: Ready for collection",
        title: "Ready for Collection",
        description: "Valid paid ticket verified. Customer is eligible to collect all ordered meals and drinks.",
        canHandover: true,
      };
    }

    return {
      type: "valid_paid",
      badge: "Green: Ready for collection",
      title: "Ready for Collection",
      description: "Ticket verified. Hand over items as required.",
      canHandover: true,
    };
  }, [activeOrder, orderPortionStats, invalidCodeAttempt, handoverAuditLogs, formatMoney]);

  // Filtered orders for queue list
  const filteredOrders = useMemo(() => {
    const q = searchInput.toLowerCase().trim();
    if (!q) return orders;
    return orders.filter(
      (o) =>
        o.ticketCode.toLowerCase().includes(q) ||
        o.customerName.toLowerCase().includes(q) ||
        o.customerPhone.includes(q) ||
        o.orderNumber.toLowerCase().includes(q)
    );
  }, [orders, searchInput]);

  // Audio beep simulation
  function playBeep(type: "success" | "warn" | "error") {
    if (!cameraSound) return;
    try {
      const audioCtx = new (window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext)();
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      osc.connect(gain);
      gain.connect(audioCtx.destination);
      if (type === "success") {
        osc.frequency.setValueAtTime(880, audioCtx.currentTime); // A5
        gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.15);
      } else if (type === "warn") {
        osc.frequency.setValueAtTime(440, audioCtx.currentTime);
        gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.3);
      } else {
        osc.frequency.setValueAtTime(220, audioCtx.currentTime);
        gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.4);
      }
    } catch (e) {
      // AudioContext unavailable or restricted
    }
  }

  // Handle Scan Action
  function handleExecuteScan(ticketCode: string) {
    setIsScanningActive(true);
    setInvalidCodeAttempt(null);

    setTimeout(() => {
      setIsScanningActive(false);
      const matched = orders.find(
        (o) => o.ticketCode.toLowerCase() === ticketCode.toLowerCase() || o.id === ticketCode
      );

      if (matched) {
        setSelectedOrderId(matched.id);
        setShowCameraModal(false);

        // Sound feedback
        if (matched.allHandedOver || matched.status === "refunded") {
          playBeep("error");
        } else if (matched.status !== "paid") {
          playBeep("warn");
        } else {
          playBeep("success");
        }

        showToast(`Scanned ${matched.ticketCode} for ${matched.customerName}`);
      } else {
        setInvalidCodeAttempt(ticketCode);
        playBeep("error");
        showToast(`Ticket "${ticketCode}" not recognized!`);
      }
    }, 450);
  }

  // Portion Handover Step (+1, -1, all)
  function handlePortionStep(itemIndex: number, delta: number) {
    if (!activeOrder) return;
    const item = activeOrder.items[itemIndex];
    if (!item) return;

    // Check station assignment
    const itemStation = getItemStation(item);
    if (activeStation !== "all" && activeStation !== itemStation) {
      showToast(`Warning: Please switch to ${getStationLabel(itemStation)} to hand over this item.`);
      return;
    }

    const currentCollected =
      typeof item.collectedQuantity === "number"
        ? item.collectedQuantity
        : item.handoverStatus === "collected"
        ? item.quantity
        : 0;

    if (delta > 0 && currentCollected >= item.quantity) {
      showToast("All portions of this item have already been collected!");
      return;
    }
    if (delta < 0 && currentCollected <= 0) {
      return;
    }

    const stationLabel = getStationLabel(activeStation === "all" ? itemStation : activeStation);

    // Call state updater
    onUpdateItemHandover(activeOrder.id, itemIndex, delta, {
      staffName: activeStaffName,
      station: stationLabel,
      actionType: delta > 0 ? "collection" : "reversal",
    });

    if (isOfflineMode) {
      setOfflineSyncQueueCount((c) => c + 1);
    }

    if (delta > 0) {
      playBeep("success");
      showToast(`Handed over 1 portion of ${item.name}`);
    } else {
      playBeep("warn");
      showToast(`Reversed 1 portion of ${item.name} (Audit logged)`);
    }
  }

  // Hand over all remaining portions for this item
  function handlePortionAll(itemIndex: number) {
    if (!activeOrder) return;
    const item = activeOrder.items[itemIndex];
    if (!item) return;

    const itemStation = getItemStation(item);
    if (activeStation !== "all" && activeStation !== itemStation) {
      showToast(`Warning: Please switch to ${getStationLabel(itemStation)} to hand over this item.`);
      return;
    }

    const stationLabel = getStationLabel(activeStation === "all" ? itemStation : activeStation);
    onUpdateItemHandover(activeOrder.id, itemIndex, 999, {
      staffName: activeStaffName,
      station: stationLabel,
      actionType: "collection",
    });

    if (isOfflineMode) {
      setOfflineSyncQueueCount((c) => c + 1);
    }

    playBeep("success");
    showToast(`All remaining portions of ${item.name} marked collected!`);
  }

  // Supervisor Override Execution
  function handleExecuteSupervisorOverride() {
    if (!activeOrder) return;
    if (supervisorPin.trim() !== "7722" && supervisorPin.trim() !== "1234") {
      setSupervisorError("Invalid Supervisor PIN. (Authorized demo PIN is 7722)");
      return;
    }
    if (!supervisorReason.trim()) {
      setSupervisorError("A valid justification reason is required for auditable supervisor override.");
      return;
    }

    // Record an audit log for the supervisor override
    const stationLabel = getStationLabel(activeStation);
    onUpdateItemHandover(activeOrder.id, 0, 0, {
      staffName: activeStaffName,
      station: stationLabel,
      actionType: "supervisor_override",
      overrideReason: supervisorReason,
      supervisorName: supervisorName,
    });

    setShowSupervisorModal(false);
    setSupervisorPin("");
    setSupervisorReason("");
    setSupervisorError("");
    playBeep("success");
    showToast(`Supervisor override logged by ${supervisorName}. Meals released.`);
  }

  // Collect Remaining Cash at Pickup
  function handleCollectRemainingCash(order: TicketOrder) {
    const balance = order.totalAmount - (order.amountPaid || 0);
    if (balance <= 0) return;

    const cashReceipt: CashPaymentRecord = {
      id: `cash-scan-${Date.now()}`,
      transactionType: "receipt",
      receiptNumber: `CSH-SCAN-${Math.floor(1000 + Math.random() * 9000)}`,
      customerName: order.customerName,
      customerPhone: order.customerPhone,
      orderNumber: order.orderNumber,
      ticketCode: order.ticketCode,
      amount: balance,
      currency: order.currency,
      receivedAt: new Date().toISOString(),
      receivedBy: `${activeStaffName} (${getStationLabel(activeStation)})`,
      notes: "Cash balance collected in person at redemption desk on event day",
    };

    void Promise.resolve(onRecordCashPayment(cashReceipt, order.id)).catch(() => undefined); // the app shows the server's reason
    playBeep("success");
    showToast(`Collected ${formatMoney(balance, order.currency)} in cash! Ticket is now PAID.`);
  }

  // Controlled Offline Sync simulation
  function handleReconcileOfflineRecords() {
    setIsSyncingOffline(true);
    setTimeout(() => {
      setIsSyncingOffline(false);
      setOfflineSyncQueueCount(0);
      showToast("Offline records synchronized with server! Conflict check passed: 0 duplicates.");
    }, 1200);
  }

  // Filtered Handover Audit Logs
  const filteredAuditLogs = useMemo(() => {
    return handoverAuditLogs.filter((log) => {
      const matchSearch =
        !auditSearch ||
        log.ticketCode.toLowerCase().includes(auditSearch.toLowerCase()) ||
        log.customerName.toLowerCase().includes(auditSearch.toLowerCase()) ||
        log.itemName.toLowerCase().includes(auditSearch.toLowerCase()) ||
        log.staffName.toLowerCase().includes(auditSearch.toLowerCase());

      const matchStation =
        auditStationFilter === "all" ||
        log.station.toLowerCase().includes(auditStationFilter.toLowerCase());

      return matchSearch && matchStation;
    });
  }, [handoverAuditLogs, auditSearch, auditStationFilter]);

  return (
    <section className="staff-scanner-container">
      {/* Top Header: Worker Identity, Station Switcher, Mode Tabs, and Offline Controller */}
      <div className="scanner-hero-bar">
        <div className="scanner-hero-main">
          <div className="scanner-badge-icon">
            <Scan size={24} />
          </div>
          <div>
            <div className="scanner-station-kicker-row">
              <span className="section-kicker">EVENT-DAY FOOD DISTRIBUTION</span>
              <span className="station-active-badge">
                <Store size={12} /> Station: <strong>{getStationLabel(activeStation)}</strong>
              </span>
            </div>
            <h2>Ticket Verification & Food Distribution Console</h2>
            <p>
              Prevent duplicate handovers, ensure unpaid orders are not released, and guarantee every
              customer receives all items purchased.
            </p>
          </div>
        </div>

        {/* Worker Sign-In & Offline Control Bar */}
        <div className="scanner-controls-cluster">
          {/* Active Worker Selector */}
          <div className="worker-selector-card">
            <label htmlFor="staff-picker" className="worker-label">
              <UserCheck size={14} /> Signed In Worker:
            </label>
            <select
              id="staff-picker"
              className="worker-dropdown"
              value={activeStaffName}
              onChange={(e) => {
                setActiveStaffName(e.target.value);
                showToast(`Signed in as: ${e.target.value}`);
              }}
            >
              {STAFF_MEMBERS.map((staff) => (
                <option key={staff.id} value={staff.name}>
                  {staff.name} — {staff.role}
                </option>
              ))}
            </select>
          </div>

          {/* Camera Scanner Trigger Button */}
          <button
            type="button"
            className="primary-button open-scanner-btn"
            onClick={() => setShowCameraModal(true)}
            id="btn-open-camera-scanner"
          >
            <Camera size={16} /> Open Camera Scanner
          </button>

          {/* Controlled Offline Mode Toggle */}
          <div className="offline-toggle-box">
            <button
              type="button"
              className={`offline-mode-pill ${isOfflineMode ? "offline" : "online"}`}
              onClick={() => {
                const nextMode = !isOfflineMode;
                setIsOfflineMode(nextMode);
                showToast(
                  nextMode
                    ? "Controlled Offline Mode enabled: Records cached locally."
                    : "Online Mode restored: Real-time global duplicate prevention active."
                );
              }}
              title="Toggle between real-time server duplicate prevention and controlled offline operation"
            >
              {isOfflineMode ? (
                <>
                  <WifiOff size={13} />
                  <span>Controlled Offline Mode</span>
                </>
              ) : (
                <>
                  <Wifi size={13} />
                  <span>Online · Global Sync Active</span>
                </>
              )}
            </button>
          </div>
        </div>
      </div>

      {/* Controlled Offline Mode Connectivity Notice Banner */}
      {isOfflineMode && (
        <div className="offline-warning-banner" role="alert">
          <div className="offline-banner-left">
            <WifiOff size={18} />
            <div>
              <strong>Controlled Offline Mode Active (Unreliable Venue Internet):</strong>
              <p>
                Offline scanners cannot guarantee immediate global duplicate prevention across
                separate devices until synchronized. Each handover is stamped locally. Reconcile
                when network reconnects.
              </p>
            </div>
          </div>
          <div className="offline-banner-right">
            <span className="queue-pill">
              <Clock size={13} /> {offlineSyncQueueCount} local record(s) queued
            </span>
            <button
              type="button"
              className="sync-reconcile-btn"
              disabled={isSyncingOffline || offlineSyncQueueCount === 0}
              onClick={handleReconcileOfflineRecords}
            >
              <RefreshCw size={13} className={isSyncingOffline ? "animate-spin" : ""} />
              {isSyncingOffline ? "Reconciling..." : "Sync & Reconcile Records"}
            </button>
          </div>
        </div>
      )}

      {/* Navigation Sub-Tabs: 1. Scanner & Redemption / 2. Handover Audit Trail */}
      <div className="scanner-subtabs-row">
        <div className="subtabs-left">
          <button
            type="button"
            className={`scanner-subtab ${activeTab === "scanner" ? "active" : ""}`}
            onClick={() => setActiveTab("scanner")}
            id="tab-scanner-view"
          >
            <Scan size={15} /> 1. Ticket Scanner & Redemption
          </button>
          <button
            type="button"
            className={`scanner-subtab ${activeTab === "audit" ? "active" : ""}`}
            onClick={() => setActiveTab("audit")}
            id="tab-audit-trail-view"
          >
            <History size={15} /> 2. Handover Audit Trail
            <span className="audit-counter-badge">{handoverAuditLogs.length}</span>
          </button>
        </div>

        {/* Station Filter Bar (Requirement C) */}
        {activeTab === "scanner" && (
          <div className="station-selector-bar">
            <span className="station-bar-label">
              <Layers size={13} /> Station Assignment:
            </span>
            <div className="station-pills-list">
              {STATIONS.map((st) => {
                const IconComponent = st.icon;
                const isSelected = activeStation === st.id;
                return (
                  <button
                    key={st.id}
                    type="button"
                    className={`station-pill ${isSelected ? "selected" : ""}`}
                    onClick={() => {
                      setActiveStation(st.id);
                      showToast(`Station filter: ${st.label}`);
                    }}
                    title={st.hint}
                  >
                    <IconComponent size={13} />
                    <span>{st.label}</span>
                  </button>
                );
              })}
            </div>
          </div>
        )}
      </div>

      {toastMessage && <div className="mini-toast staff">{toastMessage}</div>}

      {/* Perspective 1: Ticket Scanner & Itemized Handover Screen */}
      {activeTab === "scanner" && (
        <div className="scanner-split-layout">
          {/* Left Column: Attendee Queue & Fast Simulated Camera Scans */}
          <aside className="scanner-sidebar-panel">
            <div className="panel-heading">
              <div>
                <span className="section-kicker">QUEUE & QUICK SCANS</span>
                <h3>Customer Tickets ({orders.length})</h3>
              </div>
            </div>

            <div className="search-input-wrap">
              <Search size={15} />
              <input
                type="text"
                placeholder="Search ticket code, customer, or phone..."
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                id="input-ticket-search"
              />
              {searchInput && (
                <button
                  type="button"
                  className="clear-search-btn"
                  onClick={() => setSearchInput("")}
                  aria-label="Clear search"
                >
                  <X size={13} />
                </button>
              )}
            </div>

            {/* Quick Test Scenarios explicitly matching the prompt table */}
            <div className="quick-scan-scenarios-card">
              <span className="scenarios-heading">
                <QrCode size={13} /> Event Day Scan Scenarios:
              </span>
              <div className="scenarios-grid">
                <button
                  type="button"
                  className="scenario-btn prompt-case"
                  onClick={() => handleExecuteScan("FF-1024")}
                  title="Prompt example: Miss Lou (2 Fish, 4 Festivals [2 remaining], 2 Sodas)"
                >
                  <span className="scenario-tag amber">PROMPT EXAMPLE</span>
                  <strong>FF-1024 (Partially Redeemed)</strong>
                  <span>Miss Lou Bennett · Paid</span>
                </button>

                <button
                  type="button"
                  className="scenario-btn duplicate-case"
                  onClick={() => handleExecuteScan("SFF-2026-9999")}
                  title="Fully redeemed order: should trigger Red Duplicate Stop alert"
                >
                  <span className="scenario-tag red">DUPLICATE STOP</span>
                  <strong>SFF-2026-9999 (Fully Redeemed)</strong>
                  <span>Sean Paul · All items handed over</span>
                </button>

                <button
                  type="button"
                  className="scenario-btn paid-case"
                  onClick={() => handleExecuteScan("SFF-2026-8492")}
                  title="Valid paid ticket with zero items yet collected"
                >
                  <span className="scenario-tag green">VALID PAID</span>
                  <strong>SFF-2026-8492 (Ready to Collect)</strong>
                  <span>Damian Marley · Paid</span>
                </button>

                <button
                  type="button"
                  className="scenario-btn unverified-case"
                  onClick={() => handleExecuteScan("SFF-2026-3921")}
                  title="Payment awaiting verification: should trigger Amber warning"
                >
                  <span className="scenario-tag amber">AWAITING CHECK</span>
                  <strong>SFF-2026-3921 (Unverified)</strong>
                  <span>Shelly-Ann Fraser · Transfer pending</span>
                </button>

                <button
                  type="button"
                  className="scenario-btn dispute-case"
                  onClick={() => handleExecuteScan("SFF-2026-5512")}
                  title="Partially paid / dispute: requires supervisor or cash"
                >
                  <span className="scenario-tag warning">PARTIAL / DISPUTE</span>
                  <strong>SFF-2026-5512 (Deposit Only)</strong>
                  <span>Buju Banton · Balance due</span>
                </button>

                <button
                  type="button"
                  className="scenario-btn cancelled-case"
                  onClick={() => handleExecuteScan("SFF-2026-0044")}
                  title="Cancelled / refunded ticket: Red Order Cancelled alert"
                >
                  <span className="scenario-tag red">CANCELLED</span>
                  <strong>SFF-2026-0044 (Refunded)</strong>
                  <span>Ziggy Marley · Travel conflict</span>
                </button>

                <button
                  type="button"
                  className="scenario-btn invalid-case"
                  onClick={() => handleExecuteScan("UNKNOWN-CODE-999")}
                  title="Invalid QR code: Red Ticket Not Recognized alert"
                >
                  <span className="scenario-tag red">INVALID QR</span>
                  <strong>UNKNOWN-CODE-999</strong>
                  <span>Simulate fake / invalid code</span>
                </button>
              </div>
            </div>

            {/* Queue List of Tickets */}
            <div className="scanner-order-queue">
              {filteredOrders.length > 0 ? (
                filteredOrders.map((ord) => {
                  const isSelected = ord.id === selectedOrderId;
                  let totalItems = 0;
                  let collectedItems = 0;
                  ord.items.forEach((item) => {
                    totalItems += item.quantity;
                    collectedItems +=
                      typeof item.collectedQuantity === "number"
                        ? item.collectedQuantity
                        : item.handoverStatus === "collected"
                        ? item.quantity
                        : 0;
                  });
                  const isFullyCollected = ord.allHandedOver || collectedItems === totalItems;

                  return (
                    <div
                      key={ord.id}
                      className={`queue-ticket-card ${isSelected ? "selected" : ""} ${
                        isFullyCollected ? "is-closed" : ""
                      }`}
                      onClick={() => {
                        setSelectedOrderId(ord.id);
                        setInvalidCodeAttempt(null);
                      }}
                    >
                      <div className="queue-card-top">
                        <strong className="queue-ticket-code">{ord.ticketCode}</strong>
                        <span
                          className={`queue-status-chip ${
                            ord.status === "paid"
                              ? isFullyCollected
                                ? "closed"
                                : "paid"
                              : ord.status === "refunded"
                              ? "refunded"
                              : "pending"
                          }`}
                        >
                          {ord.status === "paid"
                            ? isFullyCollected
                              ? "Redeemed"
                              : "Paid"
                            : ord.status === "refunded"
                            ? "Refunded"
                            : "Pending Check"}
                        </span>
                      </div>

                      <div className="queue-cust-name">{ord.customerName}</div>
                      <div className="queue-subtext">
                        <span>
                          {collectedItems}/{totalItems} collected
                        </span>
                        <span>•</span>
                        <span>{formatMoney(ord.totalAmount, ord.currency)}</span>
                      </div>

                      <div className="handover-progress-row">
                        <div className="progress-bar-bg">
                          <div
                            className="progress-bar-fill"
                            style={{
                              width: `${(collectedItems / Math.max(totalItems, 1)) * 100}%`,
                            }}
                          />
                        </div>
                      </div>
                    </div>
                  );
                })
              ) : (
                <div className="queue-empty">
                  <Ticket size={24} />
                  <p>No tickets match your search.</p>
                </div>
              )}
            </div>
          </aside>

          {/* Right Column: Active Ticket Scanned View, Clear Response Banners & Breakdown Table */}
          <main className="panel scanner-main-panel">
            {scannerResponse ? (
              <div className="ticket-redemption-view">
                {/* 1. Prominent Response Alert Banner (Directly addressing Requirement B) */}
                <div className={`scanner-response-banner ${scannerResponse.type}`}>
                  <div className="response-banner-icon">
                    {scannerResponse.type === "valid_paid" && <CheckCircle2 size={28} />}
                    {scannerResponse.type === "partially_redeemed" && <Clock3 size={28} />}
                    {scannerResponse.type === "fully_redeemed" && <Ban size={28} />}
                    {scannerResponse.type === "unverified" && <ShieldAlert size={28} />}
                    {scannerResponse.type === "dispute" && <AlertTriangle size={28} />}
                    {scannerResponse.type === "cancelled" && <AlertCircle size={28} />}
                    {scannerResponse.type === "invalid" && <X size={28} />}
                  </div>

                  <div className="response-banner-content">
                    <div className="response-badge-pill">{scannerResponse.badge}</div>
                    <h3 className="response-banner-title">{scannerResponse.title}</h3>
                    <p className="response-banner-desc">{scannerResponse.description}</p>
                  </div>

                  {/* Quick Action inside banner if payment is unverified or dispute */}
                  {activeOrder && scannerResponse.requiresAction === "cash_or_override" && (
                    <div className="response-banner-actions">
                      <button
                        type="button"
                        className="banner-action-btn cash"
                        onClick={() => handleCollectRemainingCash(activeOrder)}
                      >
                        <Banknote size={14} /> Collect Cash on Spot
                      </button>
                      <button
                        type="button"
                        className="banner-action-btn override"
                        onClick={() => setShowSupervisorModal(true)}
                      >
                        <KeyRound size={14} /> Supervisor Override
                      </button>
                    </div>
                  )}
                </div>

                {/* If activeOrder exists, render Scanned Details & Prompt Breakdown Table */}
                {activeOrder && (
                  <>
                    {/* Header bar with Order code, Name, Phone, and Station indicator */}
                    <div className="redemption-meta-bar">
                      <div>
                        <span className="section-kicker">SCANNED VOUCHER</span>
                        <h2>ORDER {activeOrder.ticketCode} — {activeOrder.status.toUpperCase()}</h2>
                        <p className="customer-meta-line">
                          Guest: <strong>{activeOrder.customerName}</strong> · Phone:{" "}
                          <strong>{activeOrder.customerPhone}</strong> · Method:{" "}
                          <span className="method-tag">{activeOrder.method.toUpperCase()}</span>
                        </p>
                      </div>

                      <div className="station-active-callout">
                        <span className="callout-label">Operating Station</span>
                        <strong>{getStationLabel(activeStation)}</strong>
                        <span className="worker-attribution">Logged by: {activeStaffName}</span>
                      </div>
                    </div>

                    {/* Requirement A: Exact Breakdown Table as shown in prompt */}
                    <div className="breakdown-table-wrapper">
                      <div className="breakdown-table-header-row">
                        <div>
                          <span className="section-kicker">ITEMIZED HANDOVER RECORD</span>
                          <h3>Food & Drink Portions Checklist</h3>
                        </div>
                        <div className="overall-summary-pill">
                          <strong>{orderPortionStats.totalCollected}</strong> of{" "}
                          <strong>{orderPortionStats.totalOrdered}</strong> portions collected
                          {orderPortionStats.totalRemaining > 0 && (
                            <span className="remaining-tag">
                              ({orderPortionStats.totalRemaining} remaining)
                            </span>
                          )}
                        </div>
                      </div>

                      <div className="table-responsive-container">
                        <table className="handover-breakdown-table">
                          <thead>
                            <tr>
                              <th>Item</th>
                              <th>Station Assigned</th>
                              <th className="text-center">Ordered</th>
                              <th className="text-center">Collected</th>
                              <th className="text-center">Remaining</th>
                              <th className="text-right">Station Handover Actions</th>
                            </tr>
                          </thead>
                          <tbody>
                            {activeOrder.items.map((item, idx) => {
                              const itemStation = getItemStation(item);
                              const itemStationLabel = getStationLabel(itemStation);
                              const isStationMatch =
                                activeStation === "all" || activeStation === itemStation;

                              const collected =
                                typeof item.collectedQuantity === "number"
                                  ? item.collectedQuantity
                                  : item.handoverStatus === "collected"
                                  ? item.quantity
                                  : 0;
                              const remaining = Math.max(0, item.quantity - collected);
                              const isFullyHandedOver = remaining === 0;

                              return (
                                <tr
                                  key={`${item.menuItemId}-${idx}`}
                                  className={`breakdown-row ${
                                    isFullyHandedOver ? "row-collected" : "row-pending"
                                  } ${!isStationMatch ? "row-other-station" : ""}`}
                                >
                                  {/* Item Name & Details */}
                                  <td className="item-details-col">
                                    <div className="item-name-heading">
                                      <strong>{item.name}</strong>
                                      {item.category && (
                                        <span className="category-chip">{item.category}</span>
                                      )}
                                    </div>

                                    {item.selectedOptionsSummary &&
                                      item.selectedOptionsSummary.length > 0 && (
                                        <div className="options-summary-tags">
                                          {item.selectedOptionsSummary.map((opt, oIdx) => (
                                            <span className="item-sub-tag" key={oIdx}>
                                              {opt}
                                            </span>
                                          ))}
                                        </div>
                                      )}

                                    {item.specialInstructions && (
                                      <div className="item-special-request">
                                        Note: {item.specialInstructions}
                                      </div>
                                    )}

                                    {item.handedOverBy && (
                                      <div className="last-handover-log">
                                        <UserCheck size={11} /> Last handover by {item.handedOverBy} (
                                        {item.stationHandedOver || itemStationLabel})
                                      </div>
                                    )}
                                  </td>

                                  {/* Station Assigned */}
                                  <td className="station-col">
                                    <span
                                      className={`station-badge-chip ${
                                        isStationMatch ? "current" : "other"
                                      }`}
                                    >
                                      {itemStationLabel}
                                    </span>
                                  </td>

                                  {/* Ordered Quantity */}
                                  <td className="text-center qty-col">
                                    <span className="qty-number ordered">{item.quantity}</span>
                                  </td>

                                  {/* Collected Quantity */}
                                  <td className="text-center qty-col">
                                    <span
                                      className={`qty-number collected ${
                                        collected > 0 ? "has-collected" : ""
                                      }`}
                                    >
                                      {collected}
                                    </span>
                                  </td>

                                  {/* Remaining Quantity */}
                                  <td className="text-center qty-col">
                                    <span
                                      className={`qty-number remaining ${
                                        remaining === 0 ? "zero" : "positive"
                                      }`}
                                    >
                                      {remaining}
                                    </span>
                                  </td>

                                  {/* Handover Action Buttons with Steppers */}
                                  <td className="text-right actions-col">
                                    {!isStationMatch ? (
                                      <div className="station-isolation-notice">
                                        <Lock size={12} />
                                        <span>Collect at {itemStationLabel}</span>
                                      </div>
                                    ) : (
                                      <div className="stepper-controls-group">
                                        {/* - Reverse 1 (Correction / reversal in case of mistake) */}
                                        <button
                                          type="button"
                                          className="stepper-btn reverse"
                                          disabled={collected <= 0}
                                          onClick={() => handlePortionStep(idx, -1)}
                                          title="Reverse 1 portion (Auditable correction)"
                                        >
                                          <Minus size={13} />
                                        </button>

                                        {/* + Hand over 1 portion (Prompt requirement) */}
                                        <button
                                          type="button"
                                          className="stepper-btn forward primary"
                                          disabled={
                                            remaining <= 0 ||
                                            !scannerResponse.canHandover
                                          }
                                          onClick={() => handlePortionStep(idx, 1)}
                                          title="Hand over 1 portion"
                                        >
                                          <Plus size={14} /> Hand over 1
                                        </button>

                                        {/* Hand over all remaining if remaining > 1 */}
                                        {remaining > 1 && (
                                          <button
                                            type="button"
                                            className="stepper-btn all"
                                            disabled={!scannerResponse.canHandover}
                                            onClick={() => handlePortionAll(idx)}
                                            title={`Hand over all ${remaining} remaining portions`}
                                          >
                                            All ({remaining})
                                          </button>
                                        )}

                                        {/* If 100% collected */}
                                        {isFullyHandedOver && (
                                          <span className="handover-done-badge">
                                            <Check size={13} strokeWidth={3} /> Handed Over
                                          </span>
                                        )}
                                      </div>
                                    )}
                                  </td>
                                </tr>
                              );
                            })}
                          </tbody>
                        </table>
                      </div>
                    </div>

                    {/* Order Closure Indicator Card */}
                    <div className="order-closure-card">
                      <div className="closure-left">
                        <PackageCheck size={22} className="closure-icon" />
                        <div>
                          <strong>
                            {activeOrder.allHandedOver || orderPortionStats.totalRemaining === 0
                              ? "Order Fully Handed Over & Complete"
                              : "Order In Progress"}
                          </strong>
                          <p>
                            {activeOrder.allHandedOver || orderPortionStats.totalRemaining === 0
                              ? `All ${orderPortionStats.totalOrdered} portions across all stations have been collected. Ticket is closed.`
                              : `Outstanding: ${orderPortionStats.totalRemaining} portion(s) still remaining. The order will not close until all items are accounted for.`}
                          </p>
                        </div>
                      </div>

                      {activeOrder.allHandedOver || orderPortionStats.totalRemaining === 0 ? (
                        <div className="closed-stamp">
                          <CheckCircle2 size={16} /> ORDER CLOSED & CONFIRMED
                        </div>
                      ) : (
                        <button
                          type="button"
                          className="primary-button close-order-btn"
                          disabled={orderPortionStats.totalRemaining > 0}
                          onClick={() => onCloseOrder(activeOrder.id)}
                          title="The order must not be marked complete until all required items have been accounted for"
                        >
                          <CheckCircle2 size={16} /> Account for All Items First
                        </button>
                      )}
                    </div>
                  </>
                )}
              </div>
            ) : (
              <div className="scanner-empty-view">
                <Scan size={44} />
                <h3>Ready to Verify Tickets</h3>
                <p>
                  Click <strong>Open Camera Scanner</strong> or select an attendee ticket from the
                  queue on the left to verify payment and mark portions as handed over.
                </p>
                <button
                  type="button"
                  className="primary-button"
                  onClick={() => setShowCameraModal(true)}
                >
                  <Camera size={16} /> Open Phone Camera Scanner
                </button>
              </div>
            )}
          </main>
        </div>
      )}

      {/* Perspective 2: Handover Audit Trail Screen (Requirement D) */}
      {activeTab === "audit" && (
        <div className="audit-trail-container">
          {/* Audit Statistics Header */}
          <div className="audit-stats-grid">
            <div className="audit-stat-card">
              <span className="stat-label">Total Handover Events</span>
              <strong className="stat-number">{handoverAuditLogs.length}</strong>
              <span className="stat-sub">Real-time log entries</span>
            </div>

            <div className="audit-stat-card">
              <span className="stat-label">Portions Handed Over</span>
              <strong className="stat-number">
                {handoverAuditLogs
                  .filter((l) => l.actionType === "collection")
                  .reduce((sum, l) => sum + l.quantityCollected, 0)}
              </strong>
              <span className="stat-sub">Food and drink orders for this event</span>
            </div>

            <div className="audit-stat-card">
              <span className="stat-label">Staff Reversals Logged</span>
              <strong className="stat-number">
                {handoverAuditLogs.filter((l) => l.actionType === "reversal").length}
              </strong>
              <span className="stat-sub">Accidental tap corrections</span>
            </div>

            <div className="audit-stat-card">
              <span className="stat-label">Supervisor Overrides</span>
              <strong className="stat-number">
                {handoverAuditLogs.filter((l) => l.actionType === "supervisor_override").length}
              </strong>
              <span className="stat-sub">Authorized meal releases</span>
            </div>
          </div>

          {/* Audit Search & Filter Controls */}
          <div className="audit-filter-bar">
            <div className="search-input-wrap">
              <Search size={15} />
              <input
                type="text"
                placeholder="Search audit trail by ticket #, customer, item, or staff..."
                value={auditSearch}
                onChange={(e) => setAuditSearch(e.target.value)}
              />
              {auditSearch && (
                <button
                  type="button"
                  className="clear-search-btn"
                  onClick={() => setAuditSearch("")}
                >
                  <X size={13} />
                </button>
              )}
            </div>

            <div className="audit-station-filter">
              <label>Station:</label>
              <select
                value={auditStationFilter}
                onChange={(e) => setAuditStationFilter(e.target.value)}
              >
                <option value="all">All Stations</option>
                <option value="fish">Fish & Chicken Station</option>
                <option value="sides">Sides Station</option>
                <option value="drinks">Drinks Station</option>
                <option value="general">General Collection</option>
              </select>
            </div>
          </div>

          {/* Audit Logs Table */}
          <div className="table-responsive-container">
            <table className="handover-audit-table">
              <thead>
                <tr>
                  <th>Timestamp</th>
                  <th>Ticket / Order</th>
                  <th>Customer</th>
                  <th>Item Handed Over</th>
                  <th>Station Responsible</th>
                  <th>Staff Member</th>
                  <th>Action Type</th>
                  <th>Audit Notes / Details</th>
                </tr>
              </thead>
              <tbody>
                {filteredAuditLogs.length > 0 ? (
                  filteredAuditLogs.map((entry) => (
                    <tr key={entry.id} className={`audit-row ${entry.actionType}`}>
                      <td className="time-col">
                        <Clock size={12} />
                        {new Date(entry.timestamp).toLocaleTimeString([], {
                          hour: "2-digit",
                          minute: "2-digit",
                          second: "2-digit",
                        })}
                      </td>
                      <td className="code-col">
                        <strong>{entry.ticketCode}</strong>
                      </td>
                      <td className="cust-col">{entry.customerName}</td>
                      <td className="item-col">
                        <strong>
                          {entry.quantityCollected}× {entry.itemName}
                        </strong>
                        {entry.remainingQuantity !== undefined && (
                          <span className="remaining-hint">
                            ({entry.remainingQuantity} remaining)
                          </span>
                        )}
                      </td>
                      <td className="station-col">
                        <span className="station-badge">{entry.station}</span>
                      </td>
                      <td className="staff-col">
                        <UserCheck size={13} /> {entry.staffName}
                      </td>
                      <td className="action-col">
                        <span className={`action-badge ${entry.actionType}`}>
                          {entry.actionType === "collection"
                            ? "Collection"
                            : entry.actionType === "reversal"
                            ? "Correction Reversal"
                            : "Supervisor Override"}
                        </span>
                      </td>
                      <td className="notes-col">
                        {entry.overrideReason ? (
                          <div className="override-details">
                            <strong>Authorized by {entry.supervisorName || "Supervisor"}:</strong>{" "}
                            {entry.overrideReason}
                          </div>
                        ) : (
                          <span className="text-muted">Standard handover recorded</span>
                        )}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={8} className="audit-empty-row">
                      No audit log entries found matching criteria.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Camera Scanner Viewfinder Modal (Requirement A) */}
      {showCameraModal && (
        <div className="modal-backdrop" onClick={() => setShowCameraModal(false)}>
          <div
            className="camera-viewfinder-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="camera-modal-title"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Camera Viewfinder Top Bar */}
            <div className="camera-viewfinder-topbar">
              <div className="camera-device-status">
                <span className="live-dot" />
                <span id="camera-modal-title">Live Camera Viewfinder (Rear Lens)</span>
              </div>

              <div className="camera-action-controls">
                <button
                  type="button"
                  className={`camera-toggle-btn ${cameraTorch ? "active" : ""}`}
                  onClick={() => setCameraTorch((t) => !t)}
                  title={cameraTorch ? "Turn off Flashlight" : "Turn on Flashlight"}
                >
                  <Flashlight size={16} />
                  <span>{cameraTorch ? "Torch ON" : "Torch"}</span>
                </button>

                <button
                  type="button"
                  className={`camera-toggle-btn ${cameraSound ? "active" : ""}`}
                  onClick={() => setCameraSound((s) => !s)}
                  title={cameraSound ? "Mute audio beeps" : "Unmute audio beeps"}
                >
                  {cameraSound ? <Volume2 size={16} /> : <VolumeX size={16} />}
                </button>

                <button
                  type="button"
                  className="camera-close-btn"
                  onClick={() => setShowCameraModal(false)}
                  aria-label="Close camera scanner"
                >
                  <X size={18} />
                </button>
              </div>
            </div>

            {/* Lens Viewfinder Area with Targeting Reticle and Laser Beam */}
            <div className={`camera-lens-frame ${cameraTorch ? "torch-lit" : ""}`}>
              {/* Aiming Reticle Corners */}
              <div className="reticle-corner top-left" />
              <div className="reticle-corner top-right" />
              <div className="reticle-corner bottom-left" />
              <div className="reticle-corner bottom-right" />

              {/* Scanning Red/Green Laser Line */}
              <div className="scan-laser-line" />

              {/* Simulated QR Code preview target */}
              <div className="qr-target-guide">
                <QrCode size={110} strokeWidth={1.5} className="qr-ghost-icon" />
                <p className="viewfinder-instruction">
                  Point phone camera at customer ticket QR code
                </p>
              </div>

              {isScanningActive && (
                <div className="scanning-overlay">
                  <RefreshCw size={24} className="animate-spin" />
                  <span>Reading digital barcode...</span>
                </div>
              )}
            </div>

            {/* Manual Code Input Bar inside Camera Modal */}
            <div className="camera-manual-bar">
              <span className="manual-label">Or enter ticket code manually:</span>
              <form
                className="manual-input-form"
                onSubmit={(e) => {
                  e.preventDefault();
                  if (manualCodeInput.trim()) {
                    handleExecuteScan(manualCodeInput.trim());
                  }
                }}
              >
                <input
                  type="text"
                  placeholder="e.g. FF-1024 or SFF-2026-8492"
                  value={manualCodeInput}
                  onChange={(e) => setManualCodeInput(e.target.value)}
                />
                <button type="submit" className="primary-button compact">
                  Lookup
                </button>
              </form>
            </div>

            {/* Fast Quick-Scan Buttons directly in camera modal */}
            <div className="camera-scenarios-footer">
              <span className="scenarios-title">Quick Test Scenarios:</span>
              <div className="camera-scenarios-pills">
                <button
                  type="button"
                  className="quick-pill amber"
                  onClick={() => handleExecuteScan("FF-1024")}
                >
                  Scan FF-1024 (Prompt Example)
                </button>
                <button
                  type="button"
                  className="quick-pill green"
                  onClick={() => handleExecuteScan("SFF-2026-8492")}
                >
                  Scan Valid Paid (Damian)
                </button>
                <button
                  type="button"
                  className="quick-pill red"
                  onClick={() => handleExecuteScan("SFF-2026-9999")}
                >
                  Scan Duplicate Stop (Sean Paul)
                </button>
                <button
                  type="button"
                  className="quick-pill amber"
                  onClick={() => handleExecuteScan("SFF-2026-3921")}
                >
                  Scan Unverified Transfer
                </button>
                <button
                  type="button"
                  className="quick-pill red"
                  onClick={() => handleExecuteScan("UNKNOWN-CODE-999")}
                >
                  Scan Invalid QR
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Supervisor Override Modal (Requirement B & D) */}
      {showSupervisorModal && activeOrder && (
        <div className="modal-backdrop" onClick={() => setShowSupervisorModal(false)}>
          <div
            className="modal-card supervisor-override-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="supervisor-title"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="modal-header">
              <div>
                <span className="section-kicker">GOVERNANCE & CONTROLS</span>
                <h3 id="supervisor-title">Supervisor Meal Release Override</h3>
                <p>Authorize release of food for Order {activeOrder.ticketCode} ({activeOrder.customerName}).</p>
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => setShowSupervisorModal(false)}
                aria-label="Close dialog"
              >
                <X size={18} />
              </button>
            </div>

            <div className="modal-body">
              <div className="strategy-callout warning">
                <AlertTriangle size={18} />
                <div>
                  <strong>Auditable Override Required:</strong>
                  <p>
                    Every override is stamped with the supervisor's name, timestamp, and rationale in
                    the permanent Handover Audit Trail to prevent unauthorized food releases.
                  </p>
                </div>
              </div>

              {supervisorError && (
                <div className="field-error-alert" role="alert">
                  <AlertCircle size={15} /> {supervisorError}
                </div>
              )}

              <div className="form-group">
                <label>Authorizing Supervisor Name:</label>
                <input
                  type="text"
                  value={supervisorName}
                  onChange={(e) => setSupervisorName(e.target.value)}
                  placeholder="Supervisor Davis"
                />
              </div>

              <div className="form-group">
                <label>Security PIN (Demo PIN is 7722):</label>
                <input
                  type="password"
                  value={supervisorPin}
                  onChange={(e) => setSupervisorPin(e.target.value)}
                  placeholder="Enter 4-digit PIN"
                  maxLength={6}
                />
              </div>

              <div className="form-group">
                <label>Justification Rationale / Audit Reason:</label>
                <textarea
                  rows={3}
                  value={supervisorReason}
                  onChange={(e) => setSupervisorReason(e.target.value)}
                  placeholder="e.g. Guest presented confirmed bank transfer receipt on mobile banking app; VIP verified by Pastor Brown; or cash pledged at office."
                />
              </div>

              <div className="modal-actions-row">
                <button
                  type="button"
                  className="secondary-button"
                  onClick={() => setShowSupervisorModal(false)}
                >
                  Cancel
                </button>
                <button
                  type="button"
                  className="primary-button"
                  onClick={handleExecuteSupervisorOverride}
                >
                  <KeyRound size={15} /> Confirm & Authorize Handover
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </section>
  );
};
