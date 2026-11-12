import { Currency, FulfillmentStatus, MenuItem, TicketOrder } from "../types";

export type KdsStage = {
  id: FulfillmentStatus;
  label: string;
  shortLabel: string;
  stepNumber: number;
  color: string;
  bg: string;
  border: string;
  actionLabel?: string;
};

export const KDS_STAGES: KdsStage[] = [
  {
    id: "unprepared",
    label: "New order",
    shortLabel: "New Order",
    stepNumber: 1,
    color: "#F59E0B",
    bg: "rgba(245, 158, 11, 0.14)",
    border: "rgba(245, 158, 11, 0.35)",
    actionLabel: "Accept Order",
  },
  {
    id: "accepted",
    label: "Accepted",
    shortLabel: "Accepted",
    stepNumber: 2,
    color: "#38BDF8",
    bg: "rgba(56, 189, 248, 0.14)",
    border: "rgba(56, 189, 248, 0.35)",
    actionLabel: "Start Preparing",
  },
  {
    id: "preparing",
    label: "Preparing",
    shortLabel: "Preparing",
    stepNumber: 3,
    color: "#A855F7",
    bg: "rgba(168, 85, 247, 0.14)",
    border: "rgba(168, 85, 247, 0.35)",
    actionLabel: "Mark Ready for Pickup",
  },
  {
    id: "ready_for_pickup",
    label: "Ready for pickup",
    shortLabel: "Ready for Pickup",
    stepNumber: 4,
    color: "#B6F03A",
    bg: "rgba(182, 240, 58, 0.16)",
    border: "rgba(182, 240, 58, 0.4)",
    actionLabel: "Mark Collected",
  },
  {
    id: "collected",
    label: "Collected",
    shortLabel: "Collected",
    stepNumber: 5,
    color: "#22C55E",
    bg: "rgba(34, 197, 94, 0.14)",
    border: "rgba(34, 197, 94, 0.35)",
  },
];

export function getKdsStageMeta(status?: FulfillmentStatus): KdsStage {
  return KDS_STAGES.find((s) => s.id === (status || "unprepared")) || KDS_STAGES[0];
}

export function getNextKdsStatus(status?: FulfillmentStatus): FulfillmentStatus | null {
  const current = status || "unprepared";
  const idx = KDS_STAGES.findIndex((s) => s.id === current);
  if (idx === -1 || idx >= KDS_STAGES.length - 1) return null;
  return KDS_STAGES[idx + 1].id;
}

export const DEFAULT_PICKUP_WINDOWS: { id: string; label: string; startTime: string; endTime: string }[] = [
  { id: "win-asap", label: "ASAP · Express Kitchen Queue", startTime: "00:00", endTime: "23:59" },
  { id: "win-breakfast", label: "10:00 AM – 11:00 AM (Morning / Breakfast)", startTime: "10:00", endTime: "11:00" },
  { id: "win-1200", label: "12:00 PM – 12:30 PM (Lunch Wave 1)", startTime: "12:00", endTime: "12:30" },
  { id: "win-1230", label: "12:30 PM – 1:00 PM (Lunch Wave 2)", startTime: "12:30", endTime: "13:00" },
  { id: "win-1300", label: "1:00 PM – 1:30 PM (Afternoon Pickup)", startTime: "13:00", endTime: "13:30" },
  { id: "win-1400", label: "2:00 PM – 2:30 PM (Main Stage Break)", startTime: "14:00", endTime: "14:30" },
];

export function getOrderPickupNumber(
  order: Pick<TicketOrder, "pickupNumber" | "ticketCode" | "orderNumber" | "id">
): string {
  if (order.pickupNumber) return order.pickupNumber;
  const source = order.ticketCode || order.orderNumber || order.id || "101";
  const digits = source.replace(/\D/g, "");
  if (digits.length >= 3) {
    return `PU-${digits.slice(-3)}`;
  }
  let hash = 0;
  for (let i = 0; i < source.length; i++) {
    hash = (hash * 31 + source.charCodeAt(i)) % 900;
  }
  return `PU-${100 + Math.abs(hash)}`;
}

export function getOrderPickupWindow(
  order: Pick<TicketOrder, "pickupWindow" | "notes" | "createdAt">,
  index = 0
): string {
  if (order.pickupWindow) return order.pickupWindow;
  if (order.notes) {
    const match = order.notes.match(/Pickup Window:\s*([^|]+)/i);
    if (match?.[1]) return match[1].trim();
  }
  const windows = [
    "12:00 PM – 12:30 PM",
    "12:30 PM – 1:00 PM",
    "1:00 PM – 1:30 PM",
    "2:00 PM – 2:30 PM",
  ];
  return windows[index % windows.length];
}

export type DietaryFilterKey =
  | "all"
  | "vegetarian"
  | "vegan"
  | "halal"
  | "pescatarian"
  | "glutenFree"
  | "dairyFree"
  | "nutFree";

export const DIETARY_FILTERS: { id: DietaryFilterKey; label: string; shortBadge: string }[] = [
  { id: "all", label: "All Dietary", shortBadge: "All" },
  { id: "vegetarian", label: "Vegetarian", shortBadge: "Vegetarian" },
  { id: "vegan", label: "Vegan", shortBadge: "Vegan" },
  { id: "halal", label: "Halal", shortBadge: "Halal" },
  { id: "pescatarian", label: "Pescatarian", shortBadge: "Pescatarian" },
  { id: "glutenFree", label: "Gluten-Free", shortBadge: "GF" },
  { id: "dairyFree", label: "Dairy-Free", shortBadge: "Dairy-Free" },
  { id: "nutFree", label: "Nut-Free", shortBadge: "Nut-Free" },
];

export type ServiceTimeMode = "all_day" | "breakfast_before_11" | "lunch_after_11";

export type EnrichedFoodMeta = {
  vendorName: string;
  interactiveSection: string;
  dietaryTags: Exclude<DietaryFilterKey, "all">[];
  ingredients: string;
  allergens: string[];
  prepTimeMinutes: number;
  availableFromTime?: string;
  availableUntilTime?: string;
  timeWindowBadge: string;
  preorderCutoffLabel: string;
  isAvailableForTimeMode: (mode: ServiceTimeMode) => boolean;
  portionSizes: { id: string; label: string; extraPriceUSD: number; extraPriceJMD: number }[];
};

export function getEnrichedFoodMeta(item: MenuItem): EnrichedFoodMeta {
  const lowerName = (item.name || "").toLowerCase();
  const lowerCat = (item.category || "").toLowerCase();
  const lowerDesc = (item.description || "").toLowerCase();

  // 1. Vendor Assignment
  let vendorName = item.vendorName || "";
  if (!vendorName) {
    if (
      lowerName.includes("bammy") ||
      lowerName.includes("festival") ||
      lowerName.includes("rice") ||
      lowerName.includes("ackee") ||
      lowerName.includes("callaloo") ||
      lowerCat.includes("side")
    ) {
      vendorName = "Heritage Pot & Sides";
    } else if (
      lowerName.includes("snapper") ||
      lowerName.includes("fish") ||
      lowerName.includes("escovitch") ||
      lowerName.includes("shrimp") ||
      lowerName.includes("lobster") ||
      lowerCat.includes("seafood")
    ) {
      vendorName = "Harbour Catch Seafood";
    } else if (
      lowerName.includes("smoothie") ||
      lowerName.includes("sorrel") ||
      lowerName.includes("coconut") ||
      lowerName.includes("juice") ||
      lowerName.includes("punch") ||
      lowerName.includes("cake") ||
      lowerCat.includes("drink") ||
      lowerCat.includes("beverage") ||
      lowerCat.includes("dessert")
    ) {
      vendorName = "Island Sip & Sweet Bar";
    } else {
      vendorName = "Yard Smoke & Jerk Grill";
    }
  }

  // 2. Interactive Menu Section
  let interactiveSection = item.category || "Mains";
  if (
    lowerName.includes("ackee") ||
    lowerName.includes("oxtail") ||
    lowerName.includes("curry goat") ||
    lowerName.includes("bammy") ||
    lowerCat === "cultural specialties"
  ) {
    interactiveSection = "Cultural Specialties";
  } else if (
    lowerName.includes("snapper") ||
    lowerName.includes("fish") ||
    lowerName.includes("escovitch") ||
    lowerName.includes("shrimp") ||
    lowerName.includes("lobster") ||
    lowerCat === "seafood"
  ) {
    interactiveSection = "Seafood";
  } else if (
    lowerName.includes("package") ||
    lowerName.includes("platter") ||
    lowerName.includes("family") ||
    lowerName.includes("combo") ||
    lowerCat === "meal packages"
  ) {
    interactiveSection = "Meal Packages";
  } else if (lowerCat === "main dishes" || lowerCat === "mains" || lowerCat === "food") {
    interactiveSection = "Mains";
  }

  // 3. Dietary Tags
  const tagSet = new Set<Exclude<DietaryFilterKey, "all">>();
  if (item.dietaryTags) {
    item.dietaryTags.forEach((t) => {
      if (t !== ("kosher" as any)) tagSet.add(t as Exclude<DietaryFilterKey, "all">);
    });
  }
  if (item.dietaryClassification && item.dietaryClassification !== "none" && item.dietaryClassification !== "kosher") {
    tagSet.add(item.dietaryClassification);
  }

  if (
    lowerName.includes("smoothie") ||
    lowerName.includes("sorrel") ||
    lowerName.includes("coconut") ||
    lowerName.includes("callaloo") ||
    lowerName.includes("ital") ||
    lowerCat === "vegan"
  ) {
    tagSet.add("vegan");
    tagSet.add("vegetarian");
    tagSet.add("dairyFree");
    tagSet.add("glutenFree");
    tagSet.add("nutFree");
  } else if (
    lowerName.includes("festival") ||
    lowerName.includes("rice and peas") ||
    lowerName.includes("plantain") ||
    lowerCat === "vegetarian"
  ) {
    tagSet.add("vegetarian");
    tagSet.add("dairyFree");
    tagSet.add("nutFree");
  } else if (lowerName.includes("bammy")) {
    tagSet.add("vegetarian");
    tagSet.add("vegan");
    tagSet.add("glutenFree");
    tagSet.add("dairyFree");
    tagSet.add("nutFree");
  } else if (
    lowerName.includes("fish") ||
    lowerName.includes("snapper") ||
    lowerName.includes("escovitch") ||
    lowerName.includes("ackee")
  ) {
    tagSet.add("pescatarian");
    tagSet.add("dairyFree");
    tagSet.add("nutFree");
  } else if (
    lowerName.includes("jerk chicken") ||
    lowerName.includes("curry goat") ||
    lowerName.includes("oxtail") ||
    lowerName.includes("brown stew")
  ) {
    tagSet.add("halal");
    tagSet.add("dairyFree");
    tagSet.add("nutFree");
  }

  // 4. Ingredients & Allergens
  let ingredients = item.ingredients || "";
  if (!ingredients) {
    if (lowerName.includes("escovitch") || lowerName.includes("snapper")) {
      ingredients =
        "Whole Caribbean red snapper, cane vinegar escovitch pickle, scotch bonnet peppers, pimento berries, julienned carrots, bell peppers, sweet onions";
    } else if (lowerName.includes("jerk chicken")) {
      ingredients =
        "Pimento-wood smoked chicken quarter, scallion, fresh thyme, allspice, scotch bonnet pepper, fresh ginger, garlic, rice & peas";
    } else if (lowerName.includes("jerk pork")) {
      ingredients =
        "Slow-charred pork shoulder, pimento berries, scotch bonnet glaze, thyme, garlic, steamed cabbage & rice and peas";
    } else if (lowerName.includes("ackee")) {
      ingredients =
        "Tree-ripened Jamaican ackee pods, cured Atlantic codfish, tomatoes, scallions, thyme, sweet peppers, black pepper";
    } else if (lowerName.includes("bammy")) {
      ingredients = "Traditional grated cassava flatbread, coconut milk soak, sea salt, light pan-sear";
    } else if (lowerName.includes("festival")) {
      ingredients = "Fine yellow cornmeal, wheat flour, brown sugar, vanilla, nutmeg, pinch of sea salt";
    } else if (lowerName.includes("smoothie")) {
      ingredients = "Sun-ripened Julie mango, passion fruit puree, pineapple juice, lime zest, crushed ice";
    } else {
      ingredients = item.description || "Freshly prepared authentic event recipe with locally sourced seasonings.";
    }
  }

  let allergens = item.allergens && item.allergens.length > 0 ? item.allergens : [];
  if (allergens.length === 0) {
    if (lowerName.includes("ackee")) {
      allergens = ["Fish (Saltfish)", "Wheat (Dumplings)"];
    } else if (lowerName.includes("snapper") || lowerName.includes("fish") || lowerName.includes("escovitch")) {
      allergens = ["Fish", "Wheat (Festival side)"];
    } else if (lowerName.includes("festival") || lowerName.includes("patty") || lowerName.includes("patties")) {
      allergens = ["Wheat / Gluten"];
    } else if (lowerName.includes("jerk")) {
      allergens = ["Soy (Marinade trace)"];
    } else if (lowerDesc.includes("shellfish") || lowerName.includes("shrimp") || lowerName.includes("lobster")) {
      allergens = ["Shellfish"];
    } else {
      allergens = ["None declared"];
    }
  }

  // 5. Time-of-Day Menu Availability (e.g., Breakfast stops selling at 11:00 AM, Lunch becomes available at 11:00 AM)
  const isBreakfastItem =
    item.availableUntilTime === "11:00" ||
    lowerName.includes("ackee") ||
    lowerName.includes("porridge") ||
    lowerName.includes("breakfast") ||
    lowerName.includes("morning");

  const isLunchDinnerItem =
    item.availableFromTime === "11:00" ||
    (!isBreakfastItem &&
      (interactiveSection === "Mains" ||
        interactiveSection === "Seafood" ||
        interactiveSection === "Meal Packages" ||
        lowerName.includes("jerk") ||
        lowerName.includes("snapper") ||
        lowerName.includes("escovitch") ||
        lowerName.includes("oxtail") ||
        lowerName.includes("curry")));

  const availableFromTime = item.availableFromTime || (isBreakfastItem ? "07:00" : isLunchDinnerItem ? "11:00" : "07:00");
  const availableUntilTime = item.availableUntilTime || (isBreakfastItem ? "11:00" : "23:00");

  const timeWindowBadge = isBreakfastItem
    ? "Breakfast · Until 11:00 AM"
    : isLunchDinnerItem
    ? "Lunch & Dinner · From 11:00 AM"
    : "All-Day Menu";

  const isAvailableForTimeMode = (mode: ServiceTimeMode): boolean => {
    if (mode === "all_day") return true;
    if (mode === "breakfast_before_11") {
      return availableFromTime < "11:00";
    }
    if (mode === "lunch_after_11") {
      return availableUntilTime > "11:00";
    }
    return true;
  };

  // 6. Portion Sizes
  const portionSizes =
    item.portionSizes && item.portionSizes.length > 0
      ? item.portionSizes
      : item.pricingType === "custom_amount"
      ? []
      : [
          { id: "regular", label: "Regular Portion", extraPriceUSD: 0, extraPriceJMD: 0 },
          { id: "large", label: "Large Portion (+50% protein)", extraPriceUSD: 4, extraPriceJMD: 600 },
          { id: "family", label: "Family / Sharing Platter (Serves 3–4)", extraPriceUSD: 14, extraPriceJMD: 2100 },
        ];

  return {
    vendorName,
    interactiveSection,
    dietaryTags: Array.from(tagSet),
    ingredients,
    allergens,
    prepTimeMinutes: item.prepTimeMinutes || (interactiveSection === "Drinks" ? 4 : interactiveSection === "Sides" ? 7 : 15),
    availableFromTime,
    availableUntilTime,
    timeWindowBadge,
    preorderCutoffLabel: item.preorderCutoffIso
      ? `Preorder cutoff: ${new Date(item.preorderCutoffIso).toLocaleTimeString([], { hour: "numeric", minute: "2-digit" })}`
      : isBreakfastItem
      ? "Morning cutoff: 10:45 AM"
      : "Preorder open until 2 hrs before pickup",
    isAvailableForTimeMode,
    portionSizes,
  };
}

export type WasteInventoryRow = {
  itemId: string;
  itemName: string;
  category: string;
  vendorName: string;
  configuredStock: number;
  cookTargetWithBuffer: number;
  soldPortions: number;
  collectedPortions: number;
  readyUncollectedPortions: number;
  inPrepPortions: number;
  remainingUnsoldStock: number;
  estimatedWastePortions: number;
  wasteRatePercent: number;
  utilizationPercent: number;
  revenueUSD: number;
  revenueJMD: number;
};

export function computeFoodWasteAndInventoryReport(
  menu: MenuItem[],
  orders: TicketOrder[],
  currency: Currency
): {
  rows: WasteInventoryRow[];
  totalConfiguredStock: number;
  totalCookTarget: number;
  totalSoldPortions: number;
  totalCollectedPortions: number;
  totalUncollectedPortions: number;
  totalRemainingStock: number;
  overallUtilizationPercent: number;
  byVendor: Record<
    string,
    {
      vendorName: string;
      dishCount: number;
      soldPortions: number;
      collectedPortions: number;
      uncollectedPortions: number;
      remainingStock: number;
    }
  >;
} {
  const validOrders = orders.filter((o) => o.status !== "rejected" && o.status !== "refunded" && !o.isReversed);

  const rows: WasteInventoryRow[] = menu.map((dish) => {
    const meta = getEnrichedFoodMeta(dish);
    let soldPortions = 0;
    let collectedPortions = 0;
    let readyUncollectedPortions = 0;
    let inPrepPortions = 0;
    let revenueUSD = 0;
    let revenueJMD = 0;

    validOrders.forEach((order) => {
      const isPaidOrAuth = order.status === "paid" || order.status === "partially_paid" || Boolean(order.authorizedUnpaid);
      if (!isPaidOrAuth) return;

      order.items.forEach((item) => {
        if (item.menuItemId === dish.id || item.name === dish.name) {
          soldPortions += item.quantity;
          const isCollected =
            order.fulfillmentStatus === "collected" ||
            item.handoverStatus === "collected" ||
            item.preparationStatus === "collected";
          const collectedQty = isCollected
            ? item.quantity
            : item.collectedQuantity ?? item.fulfilledQuantity ?? 0;

          collectedPortions += collectedQty;
          const uncollected = Math.max(0, item.quantity - collectedQty);
          if (order.fulfillmentStatus === "ready_for_pickup" || item.preparationStatus === "ready") {
            readyUncollectedPortions += uncollected;
          } else if (uncollected > 0) {
            inPrepPortions += uncollected;
          }

          if (order.currency === "USD") {
            revenueUSD += item.finalItemPrice * item.quantity;
          } else {
            revenueJMD += item.finalItemPrice * item.quantity;
          }
        }
      });
    });

    const remainingUnsoldStock = dish.availableStock ?? 40;
    const configuredStock = remainingUnsoldStock + soldPortions;
    const cookTargetWithBuffer = Math.ceil(soldPortions * 1.1);
    const bufferOverrun = Math.max(0, cookTargetWithBuffer - soldPortions);
    const estimatedWastePortions = readyUncollectedPortions + bufferOverrun;
    const wasteRatePercent =
      cookTargetWithBuffer > 0 ? Math.round((estimatedWastePortions / cookTargetWithBuffer) * 100) : 0;
    const utilizationPercent =
      configuredStock > 0 ? Math.min(100, Math.round((soldPortions / configuredStock) * 100)) : 0;

    return {
      itemId: dish.id,
      itemName: dish.name,
      category: dish.category,
      vendorName: meta.vendorName,
      configuredStock,
      cookTargetWithBuffer,
      soldPortions,
      collectedPortions,
      readyUncollectedPortions,
      inPrepPortions,
      remainingUnsoldStock,
      estimatedWastePortions,
      wasteRatePercent,
      utilizationPercent,
      revenueUSD,
      revenueJMD,
    };
  });

  const totalConfiguredStock = rows.reduce((s, r) => s + r.configuredStock, 0);
  const totalCookTarget = rows.reduce((s, r) => s + r.cookTargetWithBuffer, 0);
  const totalSoldPortions = rows.reduce((s, r) => s + r.soldPortions, 0);
  const totalCollectedPortions = rows.reduce((s, r) => s + r.collectedPortions, 0);
  const totalUncollectedPortions = rows.reduce((s, r) => s + r.readyUncollectedPortions + r.inPrepPortions, 0);
  const totalRemainingStock = rows.reduce((s, r) => s + r.remainingUnsoldStock, 0);
  const overallUtilizationPercent =
    totalConfiguredStock > 0 ? Math.min(100, Math.round((totalSoldPortions / totalConfiguredStock) * 100)) : 0;

  const byVendor: Record<
    string,
    {
      vendorName: string;
      dishCount: number;
      soldPortions: number;
      collectedPortions: number;
      uncollectedPortions: number;
      remainingStock: number;
    }
  > = {};

  rows.forEach((r) => {
    if (!byVendor[r.vendorName]) {
      byVendor[r.vendorName] = {
        vendorName: r.vendorName,
        dishCount: 0,
        soldPortions: 0,
        collectedPortions: 0,
        uncollectedPortions: 0,
        remainingStock: 0,
      };
    }
    byVendor[r.vendorName].dishCount += 1;
    byVendor[r.vendorName].soldPortions += r.soldPortions;
    byVendor[r.vendorName].collectedPortions += r.collectedPortions;
    byVendor[r.vendorName].uncollectedPortions += r.readyUncollectedPortions + r.inPrepPortions;
    byVendor[r.vendorName].remainingStock += r.remainingUnsoldStock;
  });

  return {
    rows,
    totalConfiguredStock,
    totalCookTarget,
    totalSoldPortions,
    totalCollectedPortions,
    totalUncollectedPortions,
    totalRemainingStock,
    overallUtilizationPercent,
    byVendor,
  };
}
