import { useState } from "react";
import {
  ArrowDown,
  ArrowUp,
  Ban,
  CheckCircle2,
  ChefHat,
  Edit3,
  Plus,
  ShieldCheck,
  Trash2,
  UtensilsCrossed,
} from "lucide-react";
import { Currency, MenuItem } from "../../types";
import { HostRolePermissions } from "./types";

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
}: Props) {
  const [activeSubView, setActiveSubView] = useState<"prep_sheet" | "menu_studio">(
    "prep_sheet"
  );
  const [prepFilter, setPrepFilter] = useState<string>("all");
  const [menuCategoryFilter, setMenuCategoryFilter] = useState<string>("all");
  const [localMenuOverride, setLocalMenuOverride] = useState<MenuItem[] | null>(null);

  const effectiveMenu = localMenuOverride ?? menu;

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
                        <img
                          src={item.imageUrl}
                          alt={item.name}
                          onError={(e) => {
                            e.currentTarget.src = "/images/escovitch_fish.jpg";
                          }}
                          style={{
                            width: "40px",
                            height: "40px",
                            borderRadius: "8px",
                            objectFit: "cover",
                          }}
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
                          <img
                            src={item.imageUrl}
                            alt={item.name}
                            onError={(e) => {
                              e.currentTarget.src = "/images/escovitch_fish.jpg";
                            }}
                            style={{
                              width: "42px",
                              height: "42px",
                              borderRadius: "8px",
                              objectFit: "cover",
                            }}
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
