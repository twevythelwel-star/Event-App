import { useMemo, useState } from "react";
import {
  ArrowDown,
  ArrowUp,
  Ban,
  BarChart3,
  Bell,
  CheckCircle2,
  ChefHat,
  Clock,
  Edit3,
  ListChecks,
  Plus,
  ShieldCheck,
  Store,
  Trash2,
  UtensilsCrossed,
} from "lucide-react";
import { Currency, FulfillmentStatus, MenuItem, TicketOrder } from "../../types";
import { FoodPhoto } from "../FoodPhoto";
import { HostRolePermissions } from "./types";
import {
  KDS_STAGES,
  computeFoodWasteAndInventoryReport,
  getEnrichedFoodMeta,
  getKdsStageMeta,
  getNextKdsStatus,
  getOrderPickupNumber,
  getOrderPickupWindow,
} from "../../lib/foodCommerce";

export type MenuStatItem = MenuItem & {
  totalOrdered: number;
  paidCount: number;
  pendingCount: number;
  collectedCount: number;
  remainingToCook: number;
};

type Props = {
  menu: MenuItem[];
  menuStats: MenuStatItem[];
  totalPortionsOrdered: number;
  currency: Currency;
  permissions: HostRolePermissions;
  onOpenAddDishModal: () => void;
  onOpenEditDishModal: (item: MenuItem) => void;
  onDeleteDish: (id: string) => void;
  formatMoney: (amount: number, currency: Currency) => string;
  onUpdateMenu?: (nextMenu: MenuItem[]) => void;
  orders?: TicketOrder[];
  onUpdateFulfillmentStatus?: (orderId: string, status: FulfillmentStatus) => void;
  onNotifyOrderReady?: (order: TicketOrder, pickupNumber: string) => void;
};

export function HostFoodOrdersPrepView({
  menu,
  menuStats,
  totalPortionsOrdered,
  currency,
  permissions,
  onOpenAddDishModal,
  onOpenEditDishModal,
  onDeleteDish,
  formatMoney,
  onUpdateMenu,
  orders = [],
  onUpdateFulfillmentStatus,
  onNotifyOrderReady,
}: Props) {
  const [activeSubView, setActiveSubView] = useState<
    "prep_sheet" | "kds_queue" | "waste_report" | "menu_studio"
  >("prep_sheet");
  const [prepFilter, setPrepFilter] = useState<string>("all");
  const [menuCategoryFilter, setMenuCategoryFilter] = useState<string>("all");
  const [localMenuOverride, setLocalMenuOverride] = useState<MenuItem[] | null>(null);

  // KDS & Pickup Queue state
  const [kdsStageFilter, setKdsStageFilter] = useState<"all" | FulfillmentStatus>("all");
  const [kdsVendorFilter, setKdsVendorFilter] = useState<string>("all");
  const [kdsWindowFilter, setKdsWindowFilter] = useState<string>("all");
  const [localStatusOverrides, setLocalStatusOverrides] = useState<Record<string, FulfillmentStatus>>({});
  const [notifiedOrders, setNotifiedOrders] = useState<Record<string, string>>({});

  const effectiveMenu = localMenuOverride ?? menu;

  const foodOrders = useMemo(() => {
    return orders
      .filter((o) => o.status !== "rejected" && o.status !== "refunded" && !o.isReversed)
      .map((o, idx) => {
        const effectiveStatus = localStatusOverrides[o.id] || o.fulfillmentStatus || "unprepared";
        return {
          ...o,
          fulfillmentStatus: effectiveStatus,
          pickupNumber: getOrderPickupNumber(o),
          pickupWindow: getOrderPickupWindow(o, idx),
        };
      });
  }, [orders, localStatusOverrides]);

  const handleSetOrderKdsStatus = (order: TicketOrder & { pickupNumber: string }, nextStatus: FulfillmentStatus) => {
    setLocalStatusOverrides((prev) => ({ ...prev, [order.id]: nextStatus }));
    if (onUpdateFulfillmentStatus) {
      onUpdateFulfillmentStatus(order.id, nextStatus);
    }
    if (nextStatus === "ready_for_pickup" && !notifiedOrders[order.id]) {
      const nowLabel = new Date().toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
      setNotifiedOrders((prev) => ({ ...prev, [order.id]: nowLabel }));
      if (onNotifyOrderReady) {
        onNotifyOrderReady(order, order.pickupNumber);
      }
    }
  };

  const wasteReport = useMemo(
    () => computeFoodWasteAndInventoryReport(effectiveMenu, foodOrders, currency),
    [effectiveMenu, foodOrders, currency]
  );

  const distinctVendors = useMemo(() => {
    const s = new Set<string>();
    effectiveMenu.forEach((m) => s.add(getEnrichedFoodMeta(m).vendorName));
    return ["all", ...Array.from(s)];
  }, [effectiveMenu]);

  const distinctPickupWindows = useMemo(() => {
    const s = new Set<string>();
    foodOrders.forEach((o) => s.add(o.pickupWindow));
    return ["all", ...Array.from(s)];
  }, [foodOrders]);

  const filteredKdsOrders = useMemo(() => {
    return foodOrders.filter((order) => {
      if (kdsStageFilter !== "all" && order.fulfillmentStatus !== kdsStageFilter) {
        return false;
      }
      if (kdsWindowFilter !== "all" && order.pickupWindow !== kdsWindowFilter) {
        return false;
      }
      if (kdsVendorFilter !== "all") {
        const hasVendorItem = order.items.some((oi) => {
          const matchedDish = effectiveMenu.find((m) => m.id === oi.menuItemId || m.name === oi.name);
          if (!matchedDish) return false;
          return getEnrichedFoodMeta(matchedDish).vendorName === kdsVendorFilter;
        });
        if (!hasVendorItem) return false;
      }
      return true;
    });
  }, [foodOrders, kdsStageFilter, kdsWindowFilter, kdsVendorFilter, effectiveMenu]);

  const applyMenuUpdate = (nextMenu: MenuItem[]) => {
    setLocalMenuOverride(nextMenu);
    if (onUpdateMenu) {
      onUpdateMenu(nextMenu);
    }
  };

  const handleMoveDish = (itemId: string, direction: "up" | "down") => {
    if (!permissions.canManageEventsAndMenus) return;
    const idx = effectiveMenu.findIndex((m) => m.id === itemId);
    if (idx < 0) return;
    const targetIdx = direction === "up" ? idx - 1 : idx + 1;
    if (targetIdx < 0 || targetIdx >= effectiveMenu.length) return;
    const next = [...effectiveMenu];
    const [moved] = next.splice(idx, 1);
    next.splice(targetIdx, 0, moved);
    applyMenuUpdate(next);
  };

  const handleToggleDishAvailability = (item: MenuItem) => {
    if (!permissions.canManageEventsAndMenus) return;
    const isCurrentlyDisabled =
      item.availableStock !== undefined &&
      item.availableStock !== null &&
      item.availableStock <= 0;
    const next = effectiveMenu.map((m) =>
      m.id === item.id
        ? {
            ...m,
            availableStock: isCurrentlyDisabled ? 25 : 0,
          }
        : m
    );
    applyMenuUpdate(next);
  };

  const filteredPrepStats =
    prepFilter === "all"
      ? menuStats
      : menuStats.filter((item) => item.category === prepFilter);

  const filteredMenuItems =
    menuCategoryFilter === "all"
      ? effectiveMenu
      : effectiveMenu.filter((item) => item.category === menuCategoryFilter);

  const totalPaidPortions = menuStats.reduce((s, m) => s + m.paidCount, 0);
  const totalPendingPortions = menuStats.reduce((s, m) => s + m.pendingCount, 0);
  const totalCollectedPortions = menuStats.reduce((s, m) => s + m.collectedCount, 0);

  return (
    <div id="host-food-orders-view" style={{ display: "flex", flexDirection: "column", gap: "18px" }}>
      {/* Header & Mode Switcher */}
      <section
        style={{
          background: "#0B1120",
          border: "1px solid rgba(148, 163, 184, 0.18)",
          borderRadius: "16px",
          padding: "20px 22px",
          boxShadow: "0 12px 32px rgba(2, 6, 23, 0.4)",
        }}
      >
        <div
          style={{
            display: "flex",
            alignItems: "flex-start",
            justifyContent: "space-between",
            gap: "14px",
            flexWrap: "wrap",
            marginBottom: "16px",
          }}
        >
          <div>
            <span
              style={{
                fontSize: "10px",
                fontWeight: 800,
                letterSpacing: "1.4px",
                color: "#34D399",
                textTransform: "uppercase",
              }}
            >
              Kitchen Operations & Menu Studio
            </span>
            <h2 style={{ margin: "3px 0 4px", fontSize: "19px", fontWeight: 800, color: "#F8FAFC" }}>
              Food Orders, Portion Forecasting & Menu Pricing
            </h2>
            <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
              Track live meal portion demand, distinguish verified paid portions from unverified pending reservations, and manage your event menu.
            </p>
          </div>

          <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
            <button
              type="button"
              onClick={() => setActiveSubView("prep_sheet")}
              style={{
                padding: "8px 14px",
                borderRadius: "9px",
                background:
                  activeSubView === "prep_sheet"
                    ? "#4F46E5"
                    : "rgba(30, 41, 59, 0.8)",
                border:
                  activeSubView === "prep_sheet"
                    ? "1px solid #818CF8"
                    : "1px solid rgba(148, 163, 184, 0.25)",
                color: "#FFFFFF",
                fontSize: "12px",
                fontWeight: 700,
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
              }}
            >
              <ChefHat size={14} />
              <span>Kitchen Prep Sheet ({totalPortionsOrdered})</span>
            </button>

            <button
              type="button"
              id="btn-kds-queue-view"
              onClick={() => setActiveSubView("kds_queue")}
              style={{
                padding: "8px 14px",
                borderRadius: "9px",
                background:
                  activeSubView === "kds_queue"
                    ? "#4F46E5"
                    : "rgba(30, 41, 59, 0.8)",
                border:
                  activeSubView === "kds_queue"
                    ? "1px solid #818CF8"
                    : "1px solid rgba(148, 163, 184, 0.25)",
                color: "#FFFFFF",
                fontSize: "12px",
                fontWeight: 700,
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
              }}
            >
              <ListChecks size={14} />
              <span>Kitchen KDS & Pickup Queue ({foodOrders.length})</span>
            </button>

            <button
              type="button"
              id="btn-waste-inventory-view"
              onClick={() => setActiveSubView("waste_report")}
              style={{
                padding: "8px 14px",
                borderRadius: "9px",
                background:
                  activeSubView === "waste_report"
                    ? "#4F46E5"
                    : "rgba(30, 41, 59, 0.8)",
                border:
                  activeSubView === "waste_report"
                    ? "1px solid #818CF8"
                    : "1px solid rgba(148, 163, 184, 0.25)",
                color: "#FFFFFF",
                fontSize: "12px",
                fontWeight: 700,
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
              }}
            >
              <BarChart3 size={14} />
              <span>Inventory & Waste Report</span>
            </button>

            <button
              type="button"
              onClick={() => setActiveSubView("menu_studio")}
              style={{
                padding: "8px 14px",
                borderRadius: "9px",
                background:
                  activeSubView === "menu_studio"
                    ? "#4F46E5"
                    : "rgba(30, 41, 59, 0.8)",
                border:
                  activeSubView === "menu_studio"
                    ? "1px solid #818CF8"
                    : "1px solid rgba(148, 163, 184, 0.25)",
                color: "#FFFFFF",
                fontSize: "12px",
                fontWeight: 700,
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
              }}
            >
              <UtensilsCrossed size={14} />
              <span>Menu & Pricing Studio ({menu.length})</span>
            </button>

            {permissions.canManageEventsAndMenus && (
              <button
                type="button"
                id="btn-add-new-dish"
                onClick={onOpenAddDishModal}
                style={{
                  padding: "8px 14px",
                  borderRadius: "9px",
                  background: "#10B981",
                  color: "#052E16",
                  border: "none",
                  fontSize: "12px",
                  fontWeight: 800,
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "6px",
                }}
              >
                <Plus size={14} />
                <span>Add New Dish</span>
              </button>
            )}
          </div>
        </div>

        {/* Portion Summary Cards */}
        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))",
            gap: "12px",
          }}
        >
          <div
            style={{
              padding: "13px 15px",
              borderRadius: "11px",
              background: "#0F172A",
              border: "1px solid rgba(52, 211, 153, 0.3)",
            }}
          >
            <span style={{ fontSize: "10px", color: "#34D399", fontWeight: 800 }}>
              VERIFIED PAID PORTIONS
            </span>
            <div style={{ fontSize: "22px", fontWeight: 800, color: "#FFFFFF", marginTop: "4px" }}>
              {totalPaidPortions} Portions
            </div>
            <span style={{ fontSize: "11px", color: "#94A3B8" }}>
              Guaranteed kitchen prep obligation
            </span>
          </div>

          <div
            style={{
              padding: "13px 15px",
              borderRadius: "11px",
              background: "#0F172A",
              border: "1px solid rgba(251, 191, 36, 0.3)",
            }}
          >
            <span style={{ fontSize: "10px", color: "#FBBF24", fontWeight: 800 }}>
              PENDING UNVERIFIED PORTIONS
            </span>
            <div style={{ fontSize: "22px", fontWeight: 800, color: "#FDE68A", marginTop: "4px" }}>
              {totalPendingPortions} Portions
            </div>
            <span style={{ fontSize: "11px", color: "#94A3B8" }}>
              Held in reserve awaiting payment review
            </span>
          </div>

          <div
            style={{
              padding: "13px 15px",
              borderRadius: "11px",
              background: "#0F172A",
              border: "1px solid rgba(56, 189, 248, 0.3)",
            }}
          >
            <span style={{ fontSize: "10px", color: "#38BDF8", fontWeight: 800 }}>
              COLLECTED / SERVED
            </span>
            <div style={{ fontSize: "22px", fontWeight: 800, color: "#E0F2FE", marginTop: "4px" }}>
              {totalCollectedPortions} Portions
            </div>
            <span style={{ fontSize: "11px", color: "#94A3B8" }}>
              Handed over at pickup stations
            </span>
          </div>

          <div
            style={{
              padding: "13px 15px",
              borderRadius: "11px",
              background: "#0F172A",
              border: "1px solid rgba(129, 140, 248, 0.3)",
            }}
          >
            <span style={{ fontSize: "10px", color: "#A5B4FC", fontWeight: 800 }}>
              RECOMMENDED COOK TARGET (+10% BUFFER)
            </span>
            <div style={{ fontSize: "22px", fontWeight: 800, color: "#E0E7FF", marginTop: "4px" }}>
              {Math.ceil(totalPaidPortions * 1.1)} Portions
            </div>
            <span style={{ fontSize: "11px", color: "#94A3B8" }}>
              Based on verified paid demand + 10% walk-up buffer
            </span>
          </div>
        </div>
      </section>

      {activeSubView === "prep_sheet" ? (
        <section
          style={{
            background: "#0B1120",
            border: "1px solid rgba(148, 163, 184, 0.18)",
            borderRadius: "16px",
            padding: "20px 22px",
          }}
        >
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              gap: "12px",
              marginBottom: "16px",
              flexWrap: "wrap",
            }}
          >
            <h3 style={{ margin: 0, fontSize: "16px", fontWeight: 800, color: "#F8FAFC" }}>
              Dish-by-Dish Kitchen Prep Sheet
            </h3>

            <div style={{ display: "flex", gap: "6px", flexWrap: "wrap" }}>
              {(
                [
                  "all",
                  "Mains",
                  "Food",
                  "Sides & Extras",
                  "Sides",
                  "Beverages",
                  "Drinks",
                  "Snacks",
                  "Desserts",
                ] as const
              ).map((cat) => (
                <button
                  key={cat}
                  type="button"
                  onClick={() => setPrepFilter(cat)}
                  style={{
                    padding: "5px 10px",
                    borderRadius: "7px",
                    fontSize: "11px",
                    fontWeight: 700,
                    background:
                      prepFilter === cat ? "#4F46E5" : "rgba(30, 41, 59, 0.75)",
                    color: prepFilter === cat ? "#FFFFFF" : "#CBD5E1",
                    border:
                      prepFilter === cat
                        ? "1px solid #818CF8"
                        : "1px solid rgba(148, 163, 184, 0.2)",
                  }}
                >
                  {cat === "all" ? "All Categories" : cat}
                </button>
              ))}
            </div>
          </div>

          <div style={{ overflowX: "auto" }}>
            <table style={{ width: "100%", borderCollapse: "collapse", textAlign: "left" }}>
              <thead>
                <tr style={{ borderBottom: "1px solid rgba(148, 163, 184, 0.2)" }}>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Menu Item
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Category
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Total Ordered
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Verified Paid
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Pending Payment
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Collected
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Remaining to Serve
                  </th>
                </tr>
              </thead>
              <tbody>
                {filteredPrepStats.map((item) => (
                  <tr
                    key={item.id}
                    style={{ borderBottom: "1px solid rgba(148, 163, 184, 0.12)" }}
                  >
                    <td style={{ padding: "12px" }}>
                      <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                        <FoodPhoto
                          src={item.imageUrl}
                          alt={item.name}
                          showUnavailableText={false}
                          style={{ width: "40px", height: "40px", borderRadius: "8px" }}
                        />
                        <div>
                          <strong style={{ fontSize: "12px", color: "#F8FAFC", display: "block" }}>
                            {item.name}
                          </strong>
                          <span style={{ fontSize: "10px", color: "#94A3B8" }}>
                            {formatMoney(
                              currency === "USD" ? item.priceUSD : item.priceJMD,
                              currency
                            )}{" "}
                            · Stock: {item.availableStock ?? "Unlimited"}
                          </span>
                        </div>
                      </div>
                    </td>
                    <td style={{ padding: "12px" }}>
                      <span
                        style={{
                          fontSize: "10px",
                          fontWeight: 700,
                          padding: "3px 8px",
                          borderRadius: "6px",
                          background: "rgba(30, 41, 59, 0.9)",
                          color: "#CBD5E1",
                        }}
                      >
                        {item.category}
                      </span>
                    </td>
                    <td style={{ padding: "12px" }}>
                      <strong style={{ fontSize: "14px", color: "#FFFFFF" }}>
                        {item.totalOrdered}
                      </strong>
                    </td>
                    <td style={{ padding: "12px" }}>
                      <span
                        style={{
                          fontSize: "11px",
                          fontWeight: 800,
                          padding: "3px 8px",
                          borderRadius: "6px",
                          background: "rgba(16, 185, 129, 0.18)",
                          color: "#34D399",
                        }}
                      >
                        {item.paidCount} paid
                      </span>
                    </td>
                    <td style={{ padding: "12px" }}>
                      <span
                        style={{
                          fontSize: "11px",
                          fontWeight: 800,
                          padding: "3px 8px",
                          borderRadius: "6px",
                          background: "rgba(245, 158, 11, 0.18)",
                          color: "#FBBF24",
                        }}
                      >
                        {item.pendingCount} pending
                      </span>
                    </td>
                    <td style={{ padding: "12px" }}>
                      <span
                        style={{
                          fontSize: "11px",
                          fontWeight: 800,
                          padding: "3px 8px",
                          borderRadius: "6px",
                          background: "rgba(56, 189, 248, 0.18)",
                          color: "#38BDF8",
                        }}
                      >
                        {item.collectedCount} served
                      </span>
                    </td>
                    <td style={{ padding: "12px" }}>
                      <strong style={{ fontSize: "13px", color: "#A5B4FC" }}>
                        {item.remainingToCook} portions
                      </strong>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      ) : activeSubView === "kds_queue" ? (
        <section
          id="host-kds-pickup-queue-panel"
          style={{
            background: "#0B1120",
            border: "1px solid rgba(148, 163, 184, 0.18)",
            borderRadius: "16px",
            padding: "20px 22px",
            display: "flex",
            flexDirection: "column",
            gap: "18px",
          }}
        >
          {/* Live Pickup Queue Call Board */}
          <div
            id="pickup-queue-call-board"
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(260px, 1fr))",
              gap: "14px",
            }}
          >
            <div
              style={{
                background: "rgba(168, 85, 247, 0.1)",
                border: "1px solid rgba(168, 85, 247, 0.35)",
                borderRadius: "14px",
                padding: "14px 16px",
              }}
            >
              <div style={{ fontSize: "11px", fontWeight: 800, color: "#C084FC", letterSpacing: "0.06em" }}>
                PREPARING IN KITCHEN ({foodOrders.filter((o) => o.fulfillmentStatus === "accepted" || o.fulfillmentStatus === "preparing").length})
              </div>
              <div style={{ display: "flex", flexWrap: "wrap", gap: "8px", marginTop: "10px" }}>
                {foodOrders
                  .filter((o) => o.fulfillmentStatus === "accepted" || o.fulfillmentStatus === "preparing")
                  .map((o) => (
                    <span
                      key={o.id}
                      style={{
                        padding: "5px 11px",
                        borderRadius: "8px",
                        background: "rgba(15, 23, 42, 0.9)",
                        border: "1px solid rgba(168, 85, 247, 0.45)",
                        color: "#E9D5FF",
                        fontSize: "13px",
                        fontWeight: 800,
                      }}
                    >
                      #{o.pickupNumber}
                    </span>
                  ))}
                {foodOrders.filter((o) => o.fulfillmentStatus === "accepted" || o.fulfillmentStatus === "preparing")
                  .length === 0 && (
                  <span style={{ fontSize: "12px", color: "#94A3B8" }}>No orders currently in active prep</span>
                )}
              </div>
            </div>

            <div
              style={{
                background: "rgba(182, 240, 58, 0.1)",
                border: "1px solid rgba(182, 240, 58, 0.4)",
                borderRadius: "14px",
                padding: "14px 16px",
              }}
            >
              <div style={{ fontSize: "11px", fontWeight: 800, color: "#B6F03A", letterSpacing: "0.06em" }}>
                READY FOR PICKUP — CALLING NOW ({foodOrders.filter((o) => o.fulfillmentStatus === "ready_for_pickup").length})
              </div>
              <div style={{ display: "flex", flexWrap: "wrap", gap: "8px", marginTop: "10px" }}>
                {foodOrders
                  .filter((o) => o.fulfillmentStatus === "ready_for_pickup")
                  .map((o) => (
                    <span
                      key={o.id}
                      style={{
                        padding: "5px 11px",
                        borderRadius: "8px",
                        background: "#B6F03A",
                        color: "#090D16",
                        fontSize: "13px",
                        fontWeight: 900,
                      }}
                    >
                      #{o.pickupNumber} · {o.customerName.split(" ")[0]}
                    </span>
                  ))}
                {foodOrders.filter((o) => o.fulfillmentStatus === "ready_for_pickup").length === 0 && (
                  <span style={{ fontSize: "12px", color: "#94A3B8" }}>No orders waiting at pickup counter</span>
                )}
              </div>
            </div>
          </div>

          {/* KDS 5-Stage Filter & Multi-Vendor / Collection Window Controls */}
          <div
            style={{
              display: "flex",
              flexWrap: "wrap",
              alignItems: "center",
              justifyContent: "space-between",
              gap: "12px",
              paddingBottom: "12px",
              borderBottom: "1px solid rgba(148, 163, 184, 0.15)",
            }}
          >
            <div style={{ display: "flex", gap: "6px", flexWrap: "wrap" }}>
              <button
                type="button"
                id="kds-stage-filter-all"
                onClick={() => setKdsStageFilter("all")}
                style={{
                  padding: "6px 12px",
                  borderRadius: "8px",
                  fontSize: "11px",
                  fontWeight: 700,
                  background: kdsStageFilter === "all" ? "#4F46E5" : "rgba(30, 41, 59, 0.8)",
                  color: "#FFFFFF",
                  border: kdsStageFilter === "all" ? "1px solid #818CF8" : "1px solid rgba(148,163,184,0.2)",
                  cursor: "pointer",
                }}
              >
                All Stages ({foodOrders.length})
              </button>
              {KDS_STAGES.map((stage) => {
                const count = foodOrders.filter((o) => o.fulfillmentStatus === stage.id).length;
                const active = kdsStageFilter === stage.id;
                return (
                  <button
                    key={stage.id}
                    type="button"
                    id={`kds-stage-filter-${stage.id}`}
                    onClick={() => setKdsStageFilter(stage.id)}
                    style={{
                      padding: "6px 12px",
                      borderRadius: "8px",
                      fontSize: "11px",
                      fontWeight: 700,
                      background: active ? stage.bg : "rgba(30, 41, 59, 0.75)",
                      color: active ? stage.color : "#CBD5E1",
                      border: active ? `1px solid ${stage.border}` : "1px solid rgba(148,163,184,0.2)",
                      cursor: "pointer",
                    }}
                  >
                    {stage.stepNumber}. {stage.label} ({count})
                  </button>
                );
              })}
            </div>

            <div style={{ display: "flex", gap: "10px", flexWrap: "wrap", alignItems: "center" }}>
              <div style={{ display: "inline-flex", alignItems: "center", gap: "6px" }}>
                <Store size={13} color="#38BDF8" />
                <select
                  aria-label="Filter KDS by Vendor"
                  value={kdsVendorFilter}
                  onChange={(e) => setKdsVendorFilter(e.target.value)}
                  style={{
                    background: "#0F172A",
                    color: "#F8FAFC",
                    border: "1px solid rgba(148, 163, 184, 0.25)",
                    borderRadius: "8px",
                    padding: "5px 10px",
                    fontSize: "11px",
                    fontWeight: 700,
                  }}
                >
                  {distinctVendors.map((v) => (
                    <option key={v} value={v}>
                      {v === "all" ? "All Event Vendors" : v}
                    </option>
                  ))}
                </select>
              </div>

              <div style={{ display: "inline-flex", alignItems: "center", gap: "6px" }}>
                <Clock size={13} color="#34D399" />
                <select
                  aria-label="Filter KDS by Pickup Window"
                  value={kdsWindowFilter}
                  onChange={(e) => setKdsWindowFilter(e.target.value)}
                  style={{
                    background: "#0F172A",
                    color: "#F8FAFC",
                    border: "1px solid rgba(148, 163, 184, 0.25)",
                    borderRadius: "8px",
                    padding: "5px 10px",
                    fontSize: "11px",
                    fontWeight: 700,
                  }}
                >
                  {distinctPickupWindows.map((w) => (
                    <option key={w} value={w}>
                      {w === "all" ? "All Pickup Windows" : w}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          {/* KDS Tickets Grid */}
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fill, minmax(min(340px, 100%), 1fr))",
              gap: "14px",
            }}
          >
            {filteredKdsOrders.map((order) => {
              const stageMeta = getKdsStageMeta(order.fulfillmentStatus);
              const nextStatus = getNextKdsStatus(order.fulfillmentStatus);
              const notifiedAt = notifiedOrders[order.id];

              return (
                <div
                  key={order.id}
                  id={`kds-ticket-${order.id}`}
                  style={{
                    background: "#0F172A",
                    border: `1px solid ${stageMeta.border}`,
                    borderRadius: "14px",
                    padding: "16px",
                    display: "flex",
                    flexDirection: "column",
                    justifyContent: "space-between",
                    gap: "12px",
                  }}
                >
                  <div>
                    {/* Queue Number & Collection Window Header */}
                    <div
                      style={{
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                        gap: "8px",
                        marginBottom: "8px",
                      }}
                    >
                      <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                        <span
                          style={{
                            background: stageMeta.bg,
                            color: stageMeta.color,
                            border: `1px solid ${stageMeta.border}`,
                            fontWeight: 900,
                            fontSize: "14px",
                            padding: "4px 10px",
                            borderRadius: "8px",
                          }}
                        >
                          #{order.pickupNumber}
                        </span>
                        <div>
                          <div style={{ fontSize: "13px", fontWeight: 800, color: "#FFFFFF" }}>
                            {order.customerName}
                          </div>
                          <div style={{ fontSize: "11px", color: "#94A3B8" }}>
                            {order.orderNumber} · {order.ticketCode}
                          </div>
                        </div>
                      </div>

                      <span
                        style={{
                          fontSize: "10px",
                          fontWeight: 800,
                          padding: "4px 8px",
                          borderRadius: "999px",
                          background: stageMeta.bg,
                          color: stageMeta.color,
                          border: `1px solid ${stageMeta.border}`,
                          textTransform: "uppercase",
                        }}
                      >
                        {stageMeta.label}
                      </span>
                    </div>

                    {/* Collection Window Pill */}
                    <div
                      style={{
                        fontSize: "11px",
                        color: "#38BDF8",
                        background: "rgba(56, 189, 248, 0.1)",
                        border: "1px solid rgba(56, 189, 248, 0.25)",
                        borderRadius: "8px",
                        padding: "5px 10px",
                        marginBottom: "10px",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                      }}
                    >
                      <span>
                        <Clock size={11} style={{ display: "inline", marginRight: "5px" }} />
                        Window: <strong>{order.pickupWindow}</strong>
                      </span>
                      <span style={{ color: order.status === "paid" ? "#34D399" : "#FBBF24", fontWeight: 700 }}>
                        {order.status === "paid" ? "PAID" : order.status.toUpperCase()}
                      </span>
                    </div>

                    {/* 5-Stage Progress Bar */}
                    <div
                      style={{
                        display: "grid",
                        gridTemplateColumns: "repeat(5, 1fr)",
                        gap: "4px",
                        marginBottom: "10px",
                      }}
                    >
                      {KDS_STAGES.map((st) => {
                        const isReached = st.stepNumber <= stageMeta.stepNumber;
                        const isCurrent = st.id === order.fulfillmentStatus;
                        return (
                          <button
                            key={st.id}
                            type="button"
                            onClick={() => handleSetOrderKdsStatus(order, st.id)}
                            title={`Set stage to ${st.label}`}
                            style={{
                              padding: "4px 2px",
                              borderRadius: "6px",
                              fontSize: "9px",
                              fontWeight: isCurrent ? 800 : 600,
                              background: isReached ? st.bg : "rgba(30, 41, 59, 0.6)",
                              color: isReached ? st.color : "#64748B",
                              border: isCurrent
                                ? `1px solid ${st.color}`
                                : "1px solid rgba(148, 163, 184, 0.15)",
                              cursor: "pointer",
                              textAlign: "center",
                            }}
                          >
                            {st.shortLabel}
                          </button>
                        );
                      })}
                    </div>

                    {/* Order Items List */}
                    <div
                      style={{
                        background: "rgba(2, 6, 23, 0.55)",
                        borderRadius: "8px",
                        padding: "8px 10px",
                        display: "flex",
                        flexDirection: "column",
                        gap: "6px",
                      }}
                    >
                      {order.items.map((item, iIdx) => {
                        const dish = effectiveMenu.find((m) => m.id === item.menuItemId || m.name === item.name);
                        const vendor = dish ? getEnrichedFoodMeta(dish).vendorName : "Event Kitchen";
                        return (
                          <div
                            key={`${item.menuItemId}-${iIdx}`}
                            style={{
                              fontSize: "12px",
                              color: "#E2E8F0",
                              borderBottom:
                                iIdx < order.items.length - 1
                                  ? "1px solid rgba(148, 163, 184, 0.1)"
                                  : "none",
                              paddingBottom: iIdx < order.items.length - 1 ? "5px" : 0,
                            }}
                          >
                            <div style={{ display: "flex", justifyContent: "space-between", fontWeight: 700 }}>
                              <span>
                                {item.quantity}x {item.name}
                              </span>
                              <span style={{ fontSize: "10px", color: "#38BDF8" }}>{vendor}</span>
                            </div>
                            {item.selectedOptionsSummary && item.selectedOptionsSummary.length > 0 && (
                              <div style={{ fontSize: "10px", color: "#94A3B8", marginTop: "2px" }}>
                                {item.selectedOptionsSummary.join(" · ")}
                              </div>
                            )}
                            {item.specialInstructions && (
                              <div style={{ fontSize: "10px", color: "#FBBF24", marginTop: "2px", fontStyle: "italic" }}>
                                Note: {item.specialInstructions}
                              </div>
                            )}
                          </div>
                        );
                      })}
                    </div>
                  </div>

                  {/* Action Footer: Advance KDS Stage & Notify Attendee */}
                  <div style={{ display: "flex", gap: "8px", flexWrap: "wrap", marginTop: "4px" }}>
                    {nextStatus && (
                      <button
                        type="button"
                        id={`btn-advance-kds-${order.id}`}
                        onClick={() => handleSetOrderKdsStatus(order, nextStatus)}
                        style={{
                          flex: 1,
                          padding: "8px 12px",
                          borderRadius: "8px",
                          background: "#10B981",
                          color: "#052E16",
                          border: "none",
                          fontSize: "11px",
                          fontWeight: 800,
                          cursor: "pointer",
                        }}
                      >
                        {stageMeta.actionLabel} →
                      </button>
                    )}

                    <button
                      type="button"
                      id={`btn-notify-ready-${order.id}`}
                      onClick={() => {
                        const nowLabel = new Date().toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
                        setNotifiedOrders((prev) => ({ ...prev, [order.id]: nowLabel }));
                        if (onNotifyOrderReady) {
                          onNotifyOrderReady(order, order.pickupNumber);
                        }
                      }}
                      style={{
                        padding: "8px 10px",
                        borderRadius: "8px",
                        background: notifiedAt
                          ? "rgba(52, 211, 153, 0.16)"
                          : "rgba(56, 189, 248, 0.16)",
                        color: notifiedAt ? "#34D399" : "#38BDF8",
                        border: notifiedAt
                          ? "1px solid rgba(52, 211, 153, 0.35)"
                          : "1px solid rgba(56, 189, 248, 0.35)",
                        fontSize: "11px",
                        fontWeight: 700,
                        cursor: "pointer",
                        display: "inline-flex",
                        alignItems: "center",
                        gap: "5px",
                      }}
                    >
                      <Bell size={12} />
                      <span>{notifiedAt ? `Notified (${notifiedAt})` : "Notify Attendee"}</span>
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </section>
      ) : activeSubView === "waste_report" ? (
        <section
          id="host-food-waste-inventory-panel"
          style={{
            background: "#0B1120",
            border: "1px solid rgba(148, 163, 184, 0.18)",
            borderRadius: "16px",
            padding: "20px 22px",
            display: "flex",
            flexDirection: "column",
            gap: "18px",
          }}
        >
          <div>
            <h3 style={{ margin: 0, fontSize: "16px", fontWeight: 800, color: "#F8FAFC" }}>
              Food Waste, Inventory Utilization & Multi-Vendor Accounting
            </h3>
            <p style={{ margin: "4px 0 0", fontSize: "12px", color: "#94A3B8" }}>
              Monitor stock utilization, compare recommended cook targets against collected portions, and identify uncollected portions to minimize event food waste.
            </p>
          </div>

          {/* Vendor Summary Cards */}
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
              gap: "12px",
            }}
          >
            {Object.values(wasteReport.byVendor).map((v) => (
              <div
                key={v.vendorName}
                style={{
                  background: "#0F172A",
                  border: "1px solid rgba(56, 189, 248, 0.25)",
                  borderRadius: "12px",
                  padding: "12px 14px",
                }}
              >
                <div style={{ fontSize: "11px", fontWeight: 800, color: "#38BDF8" }}>{v.vendorName}</div>
                <div style={{ fontSize: "18px", fontWeight: 800, color: "#FFFFFF", marginTop: "4px" }}>
                  {v.soldPortions} Sold · {v.collectedPortions} Collected
                </div>
                <div style={{ fontSize: "11px", color: "#94A3B8", marginTop: "3px" }}>
                  {v.dishCount} Dishes · {v.remainingStock} Remaining Stock · {v.uncollectedPortions} Uncollected
                </div>
              </div>
            ))}
          </div>

          {/* Dish-Level Waste & Inventory Table */}
          <div style={{ overflowX: "auto" }}>
            <table style={{ width: "100%", borderCollapse: "collapse", textAlign: "left" }}>
              <thead>
                <tr style={{ borderBottom: "1px solid rgba(148, 163, 184, 0.2)" }}>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Dish & Vendor
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Initial Stock
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Cook Target (+10%)
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Sold
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Collected
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Remaining Stock
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Uncollected / Est. Waste
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Utilization
                  </th>
                </tr>
              </thead>
              <tbody>
                {wasteReport.rows.map((r) => (
                  <tr key={r.itemId} style={{ borderBottom: "1px solid rgba(148, 163, 184, 0.12)" }}>
                    <td style={{ padding: "11px 12px" }}>
                      <strong style={{ fontSize: "12px", color: "#F8FAFC", display: "block" }}>{r.itemName}</strong>
                      <span style={{ fontSize: "10px", color: "#38BDF8" }}>{r.vendorName}</span>
                    </td>
                    <td style={{ padding: "11px 12px", fontSize: "12px", color: "#CBD5E1" }}>{r.configuredStock}</td>
                    <td style={{ padding: "11px 12px", fontSize: "12px", color: "#A5B4FC", fontWeight: 700 }}>
                      {r.cookTargetWithBuffer}
                    </td>
                    <td style={{ padding: "11px 12px", fontSize: "12px", color: "#34D399", fontWeight: 700 }}>
                      {r.soldPortions}
                    </td>
                    <td style={{ padding: "11px 12px", fontSize: "12px", color: "#38BDF8", fontWeight: 700 }}>
                      {r.collectedPortions}
                    </td>
                    <td style={{ padding: "11px 12px", fontSize: "12px", color: "#E2E8F0" }}>
                      {r.remainingUnsoldStock}
                    </td>
                    <td style={{ padding: "11px 12px" }}>
                      <span
                        style={{
                          fontSize: "11px",
                          fontWeight: 700,
                          padding: "3px 8px",
                          borderRadius: "6px",
                          background:
                            r.estimatedWastePortions > 3
                              ? "rgba(245, 158, 11, 0.18)"
                              : "rgba(16, 185, 129, 0.15)",
                          color: r.estimatedWastePortions > 3 ? "#FBBF24" : "#34D399",
                        }}
                      >
                        {r.estimatedWastePortions} portions ({r.wasteRatePercent}%)
                      </span>
                    </td>
                    <td style={{ padding: "11px 12px", fontSize: "12px", fontWeight: 800, color: "#FFFFFF" }}>
                      {r.utilizationPercent}%
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      ) : (
        <section
          style={{
            background: "#0B1120",
            border: "1px solid rgba(148, 163, 184, 0.18)",
            borderRadius: "16px",
            padding: "20px 22px",
          }}
        >
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              gap: "12px",
              marginBottom: "16px",
              flexWrap: "wrap",
            }}
          >
            <div>
              <h3 style={{ margin: 0, fontSize: "16px", fontWeight: 800, color: "#F8FAFC" }}>
                Menu & Pricing Configuration ({filteredMenuItems.length} Dishes)
              </h3>
              <p
                id="menu-historical-snapshot-guarantee"
                style={{
                  margin: "4px 0 0",
                  fontSize: "11px",
                  color: "#A7F3D0",
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "5px",
                }}
              >
                <ShieldCheck size={12} color="#34D399" />
                <span>
                  Historical orders retain their immutable purchased item and price snapshot even if a menu item is later edited, reordered, or disabled.
                </span>
              </p>
            </div>

            <div style={{ display: "flex", gap: "6px", flexWrap: "wrap" }}>
              {(
                [
                  "all",
                  "Mains",
                  "Food",
                  "Sides & Extras",
                  "Sides",
                  "Beverages",
                  "Drinks",
                  "Snacks",
                  "Desserts",
                  "Community Donations",
                ] as const
              ).map((cat) => (
                <button
                  key={cat}
                  type="button"
                  onClick={() => setMenuCategoryFilter(cat)}
                  style={{
                    padding: "5px 10px",
                    borderRadius: "7px",
                    fontSize: "11px",
                    fontWeight: 700,
                    background:
                      menuCategoryFilter === cat
                        ? "#4F46E5"
                        : "rgba(30, 41, 59, 0.75)",
                    color: menuCategoryFilter === cat ? "#FFFFFF" : "#CBD5E1",
                    border:
                      menuCategoryFilter === cat
                        ? "1px solid #818CF8"
                        : "1px solid rgba(148, 163, 184, 0.2)",
                  }}
                >
                  {cat === "all" ? "All Items" : cat}
                </button>
              ))}
            </div>
          </div>

          <div style={{ overflowX: "auto" }}>
            <table style={{ width: "100%", borderCollapse: "collapse", textAlign: "left" }}>
              <thead>
                <tr style={{ borderBottom: "1px solid rgba(148, 163, 184, 0.2)" }}>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Dish / Item
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Category & Type
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    JMD Price
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    USD Price
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase" }}>
                    Stock Limit
                  </th>
                  <th style={{ padding: "10px 12px", fontSize: "10px", color: "#94A3B8", textTransform: "uppercase", textAlign: "right" }}>
                    Order & Actions
                  </th>
                </tr>
              </thead>
              <tbody>
                {filteredMenuItems.map((item, idx) => {
                  const isDisabledStock =
                    item.availableStock !== undefined &&
                    item.availableStock !== null &&
                    item.availableStock <= 0;

                  return (
                    <tr
                      key={item.id}
                      style={{
                        borderBottom: "1px solid rgba(148, 163, 184, 0.12)",
                        opacity: isDisabledStock ? 0.72 : 1,
                      }}
                    >
                      <td style={{ padding: "12px" }}>
                        <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                          <FoodPhoto
                            src={item.imageUrl}
                            alt={item.name}
                            showUnavailableText={false}
                            style={{ width: "42px", height: "42px", borderRadius: "8px" }}
                          />
                          <div>
                            <strong style={{ fontSize: "12px", color: "#F8FAFC", display: "block" }}>
                              {item.name}
                            </strong>
                            <span style={{ fontSize: "10px", color: "#94A3B8" }}>
                              {item.description.slice(0, 75)}...
                            </span>
                          </div>
                        </div>
                      </td>
                      <td style={{ padding: "12px" }}>
                        <span
                          style={{
                            fontSize: "10px",
                            fontWeight: 700,
                            padding: "3px 8px",
                            borderRadius: "6px",
                            background: "rgba(30, 41, 59, 0.9)",
                            color: "#CBD5E1",
                          }}
                        >
                          {item.category}
                        </span>
                        <div style={{ fontSize: "10px", color: "#818CF8", marginTop: "3px" }}>
                          {item.pricingType}
                        </div>
                      </td>
                      <td style={{ padding: "12px", fontWeight: 700, color: "#34D399", fontSize: "12px" }}>
                        {formatMoney(item.priceJMD, "JMD")}
                      </td>
                      <td style={{ padding: "12px", fontWeight: 700, color: "#38BDF8", fontSize: "12px" }}>
                        {formatMoney(item.priceUSD, "USD")}
                      </td>
                      <td style={{ padding: "12px", color: "#CBD5E1", fontSize: "12px" }}>
                        {isDisabledStock ? (
                          <span
                            style={{
                              fontSize: "10px",
                              fontWeight: 800,
                              padding: "2px 8px",
                              borderRadius: "999px",
                              background: "rgba(239, 68, 68, 0.2)",
                              color: "#FCA5A5",
                            }}
                          >
                            DISABLED / SOLD OUT (0)
                          </span>
                        ) : (
                          item.availableStock ?? "Unlimited"
                        )}
                      </td>
                      <td style={{ padding: "12px", textAlign: "right" }}>
                        <div style={{ display: "inline-flex", gap: "6px", flexWrap: "wrap", justifyContent: "flex-end" }}>
                          <button
                            type="button"
                            id={`move-up-item-${item.id}`}
                            aria-label={`Move ${item.name} up`}
                            disabled={!permissions.canManageEventsAndMenus || idx === 0}
                            onClick={() => handleMoveDish(item.id, "up")}
                            style={{
                              padding: "5px 7px",
                              borderRadius: "6px",
                              background: "rgba(30, 41, 59, 0.85)",
                              color: "#CBD5E1",
                              border: "1px solid rgba(148, 163, 184, 0.25)",
                              fontSize: "10px",
                              fontWeight: 700,
                              display: "inline-flex",
                              alignItems: "center",
                            }}
                          >
                            <ArrowUp size={11} />
                          </button>
                          <button
                            type="button"
                            id={`move-down-item-${item.id}`}
                            aria-label={`Move ${item.name} down`}
                            disabled={
                              !permissions.canManageEventsAndMenus ||
                              idx === filteredMenuItems.length - 1
                            }
                            onClick={() => handleMoveDish(item.id, "down")}
                            style={{
                              padding: "5px 7px",
                              borderRadius: "6px",
                              background: "rgba(30, 41, 59, 0.85)",
                              color: "#CBD5E1",
                              border: "1px solid rgba(148, 163, 184, 0.25)",
                              fontSize: "10px",
                              fontWeight: 700,
                              display: "inline-flex",
                              alignItems: "center",
                            }}
                          >
                            <ArrowDown size={11} />
                          </button>
                          <button
                            type="button"
                            id={`toggle-stock-item-${item.id}`}
                            disabled={!permissions.canManageEventsAndMenus}
                            onClick={() => handleToggleDishAvailability(item)}
                            style={{
                              padding: "5px 9px",
                              borderRadius: "6px",
                              background: isDisabledStock
                                ? "rgba(16, 185, 129, 0.18)"
                                : "rgba(245, 158, 11, 0.16)",
                              color: isDisabledStock ? "#34D399" : "#FBBF24",
                              border: isDisabledStock
                                ? "1px solid rgba(52, 211, 153, 0.35)"
                                : "1px solid rgba(251, 191, 36, 0.35)",
                              fontSize: "10px",
                              fontWeight: 700,
                              display: "inline-flex",
                              alignItems: "center",
                              gap: "4px",
                            }}
                          >
                            {isDisabledStock ? <CheckCircle2 size={11} /> : <Ban size={11} />}
                            {isDisabledStock ? "Enable Item" : "Disable Item"}
                          </button>
                          <button
                            type="button"
                            id={`edit-item-${item.id}`}
                            disabled={!permissions.canManageEventsAndMenus}
                            onClick={() => onOpenEditDishModal(item)}
                            style={{
                              padding: "5px 10px",
                              borderRadius: "6px",
                              background: "rgba(99, 102, 241, 0.18)",
                              color: "#A5B4FC",
                              border: "1px solid rgba(129, 140, 248, 0.35)",
                              fontSize: "10px",
                              fontWeight: 700,
                              display: "inline-flex",
                              alignItems: "center",
                              gap: "4px",
                            }}
                          >
                            <Edit3 size={11} />
                            Edit
                          </button>
                          <button
                            type="button"
                            id={`delete-item-${item.id}`}
                            disabled={!permissions.canManageEventsAndMenus}
                            onClick={() => onDeleteDish(item.id)}
                            style={{
                              padding: "5px 9px",
                              borderRadius: "6px",
                              background: "rgba(239, 68, 68, 0.16)",
                              color: "#FCA5A5",
                              border: "1px solid rgba(248, 113, 113, 0.35)",
                              fontSize: "10px",
                              fontWeight: 700,
                            }}
                          >
                            <Trash2 size={11} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </div>
  );
}
