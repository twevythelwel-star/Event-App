/**
 * EventBite — Documented Visual Asset Inventory & Fallback Imagery Resolver
 *
 * All visual assets are local, verified, high-resolution production images served
 * from `/images/*` (zero external Dribbble hotlinks or unrelated stock URLs).
 */

export type EventImageCategory =
  | "concert"
  | "fish_fry"
  | "food_festival"
  | "party"
  | "community"
  | "flyer";

export type FoodImageCategory =
  | "fish"
  | "jerk_chicken"
  | "fried_chicken"
  | "wings"
  | "curry_goat"
  | "oxtail"
  | "bammy"
  | "plantains"
  | "rum_punch"
  | "smoothie"
  | "default_food";

export type MediaAspectRatio = "21:9" | "16:10" | "4:3" | "3:4" | "1:1";

export interface AssetInventoryRecord {
  id: string;
  path: string;
  category: EventImageCategory | FoodImageCategory;
  aspectRatio: MediaAspectRatio;
  focalPoint: string;
  altDescription: string;
  licenseSource: "First-Party Project Asset (/public/images)";
}

export const EVENTBITE_ASSET_INVENTORY: Record<string, AssetInventoryRecord> = {
  island_music_reggae_concert: {
    id: "island_music_reggae_concert",
    path: "/images/island_music_reggae_concert.jpg",
    category: "concert",
    aspectRatio: "16:10",
    focalPoint: "center 40%",
    altDescription: "Live open-air island reggae concert stage with warm evening lighting",
    licenseSource: "First-Party Project Asset (/public/images)",
  },
  escovitch_fish: {
    id: "escovitch_fish",
    path: "/images/escovitch_fish.jpg",
    category: "fish_fry",
    aspectRatio: "4:3",
    focalPoint: "center center",
    altDescription: "Authentic Jamaican escovitch whole snapper topped with pickled peppers and onions",
    licenseSource: "First-Party Project Asset (/public/images)",
  },
  island_festival_sunset: {
    id: "island_festival_sunset",
    path: "/images/island_festival_sunset.jpg",
    category: "food_festival",
    aspectRatio: "16:10",
    focalPoint: "center 45%",
    altDescription: "Waterfront island food festival and community gathering at golden sunset",
    licenseSource: "First-Party Project Asset (/public/images)",
  },
  island_culture_carnival: {
    id: "island_culture_carnival",
    path: "/images/island_culture_carnival.jpg",
    category: "party",
    aspectRatio: "16:10",
    focalPoint: "center 35%",
    altDescription: "Vibrant Caribbean cultural celebration and evening party atmosphere",
    licenseSource: "First-Party Project Asset (/public/images)",
  },
  island_sports_championship: {
    id: "island_sports_championship",
    path: "/images/island_sports_championship.jpg",
    category: "community",
    aspectRatio: "16:10",
    focalPoint: "center center",
    altDescription: "Community sports championship and outdoor spectator gathering",
    licenseSource: "First-Party Project Asset (/public/images)",
  },
  jerk_chicken: {
    id: "jerk_chicken",
    path: "/images/jerk_chicken.jpg",
    category: "jerk_chicken",
    aspectRatio: "4:3",
    focalPoint: "center center",
    altDescription: "Pimento-smoked jerk chicken quarter with charred glaze and festival",
    licenseSource: "First-Party Project Asset (/public/images)",
  },
  fried_chicken: {
    id: "fried_chicken",
    path: "/images/fried_chicken.jpg",
    category: "fried_chicken",
    aspectRatio: "4:3",
    focalPoint: "center center",
    altDescription: "Crispy golden island seasoned fried chicken platter",
    licenseSource: "First-Party Project Asset (/public/images)",
  },
  crispy_island_wings: {
    id: "crispy_island_wings",
    path: "/images/crispy_island_wings.jpg",
    category: "wings",
    aspectRatio: "4:3",
    focalPoint: "center center",
    altDescription: "Crispy spiced island chicken wings with herb garnish",
    licenseSource: "First-Party Project Asset (/public/images)",
  },
  caribbean_curry_goat: {
    id: "caribbean_curry_goat",
    path: "/images/caribbean_curry_goat.jpg",
    category: "curry_goat",
    aspectRatio: "4:3",
    focalPoint: "center center",
    altDescription: "Slow-braised Caribbean curry goat with rice and peas",
    licenseSource: "First-Party Project Asset (/public/images)",
  },
  oxtail: {
    id: "oxtail",
    path: "/images/oxtail.jpg",
    category: "oxtail",
    aspectRatio: "4:3",
    focalPoint: "center center",
    altDescription: "Braised Jamaican oxtail with butter beans in rich gravy",
    licenseSource: "First-Party Project Asset (/public/images)",
  },
  bammy: {
    id: "bammy",
    path: "/images/bammy.jpg",
    category: "bammy",
    aspectRatio: "4:3",
    focalPoint: "center center",
    altDescription: "Golden fried cassava bammy wedges soaked in coconut milk",
    licenseSource: "First-Party Project Asset (/public/images)",
  },
  fried_plantains_sides: {
    id: "fried_plantains_sides",
    path: "/images/fried_plantains_sides.jpg",
    category: "plantains",
    aspectRatio: "4:3",
    focalPoint: "center center",
    altDescription: "Caramelized sweet fried ripe plantains and island sides",
    licenseSource: "First-Party Project Asset (/public/images)",
  },
  rum_punch: {
    id: "rum_punch",
    path: "/images/rum_punch.jpg",
    category: "rum_punch",
    aspectRatio: "4:3",
    focalPoint: "center 40%",
    altDescription: "Chilled island fruit punch with citrus and tropical garnish",
    licenseSource: "First-Party Project Asset (/public/images)",
  },
  tropical_island_smoothie: {
    id: "tropical_island_smoothie",
    path: "/images/tropical_island_smoothie.jpg",
    category: "smoothie",
    aspectRatio: "4:3",
    focalPoint: "center 40%",
    altDescription: "Fresh mango passionfruit tropical smoothie",
    licenseSource: "First-Party Project Asset (/public/images)",
  },
};

/**
 * Resolves a safe, non-empty fallback image URL and focal point for any event category
 * without ever using external hotlinks or misleading stock photos.
 */
export function resolveEventFallbackImage(event: {
  flyerUrl?: string | null;
  category?: string | null;
  title?: string | null;
  description?: string | null;
}): { src: string; focalPoint: string; alt: string } {
  if (
    event.flyerUrl &&
    typeof event.flyerUrl === "string" &&
    event.flyerUrl.trim().length > 0 &&
    !event.flyerUrl.includes("dribbble.com")
  ) {
    return {
      src: event.flyerUrl.trim(),
      focalPoint: "center center",
      alt: event.title ? `${event.title} event flyer` : "Event promotional image",
    };
  }

  const corpus = `${event.category || ""} ${event.title || ""} ${event.description || ""}`.toLowerCase();

  if (corpus.includes("concert") || corpus.includes("reggae") || corpus.includes("music") || corpus.includes("sound")) {
    const rec = EVENTBITE_ASSET_INVENTORY.island_music_reggae_concert;
    return { src: rec.path, focalPoint: rec.focalPoint, alt: rec.altDescription };
  }
  if (corpus.includes("fish fry") || corpus.includes("escovitch") || corpus.includes("snapper") || corpus.includes("seafood")) {
    const rec = EVENTBITE_ASSET_INVENTORY.escovitch_fish;
    return { src: rec.path, focalPoint: rec.focalPoint, alt: rec.altDescription };
  }
  if (corpus.includes("party") || corpus.includes("carnival") || corpus.includes("fete") || corpus.includes("culture")) {
    const rec = EVENTBITE_ASSET_INVENTORY.island_culture_carnival;
    return { src: rec.path, focalPoint: rec.focalPoint, alt: rec.altDescription };
  }
  if (corpus.includes("sport") || corpus.includes("regatta") || corpus.includes("cricket") || corpus.includes("community")) {
    const rec = EVENTBITE_ASSET_INVENTORY.island_sports_championship;
    return { src: rec.path, focalPoint: rec.focalPoint, alt: rec.altDescription };
  }

  const fallback = EVENTBITE_ASSET_INVENTORY.island_festival_sunset;
  return { src: fallback.path, focalPoint: fallback.focalPoint, alt: fallback.altDescription };
}

/**
 * Resolves a warm editorial food photograph for any menu item, falling back to
 * category-matched local imagery when no custom upload is present.
 */
export function resolveFoodFallbackImage(item: {
  imageUrl?: string | null;
  name?: string | null;
  category?: string | null;
}): { src: string; focalPoint: string; alt: string } {
  if (
    item.imageUrl &&
    typeof item.imageUrl === "string" &&
    item.imageUrl.trim().length > 0 &&
    !item.imageUrl.includes("dribbble.com")
  ) {
    return {
      src: item.imageUrl.trim(),
      focalPoint: "center center",
      alt: item.name || "Menu item photograph",
    };
  }

  const corpus = `${item.name || ""} ${item.category || ""}`.toLowerCase();

  if (corpus.includes("jerk")) {
    const rec = EVENTBITE_ASSET_INVENTORY.jerk_chicken;
    return { src: rec.path, focalPoint: rec.focalPoint, alt: rec.altDescription };
  }
  if (corpus.includes("wing")) {
    const rec = EVENTBITE_ASSET_INVENTORY.crispy_island_wings;
    return { src: rec.path, focalPoint: rec.focalPoint, alt: rec.altDescription };
  }
  if (corpus.includes("fried chicken")) {
    const rec = EVENTBITE_ASSET_INVENTORY.fried_chicken;
    return { src: rec.path, focalPoint: rec.focalPoint, alt: rec.altDescription };
  }
  if (corpus.includes("curry") || corpus.includes("goat")) {
    const rec = EVENTBITE_ASSET_INVENTORY.caribbean_curry_goat;
    return { src: rec.path, focalPoint: rec.focalPoint, alt: rec.altDescription };
  }
  if (corpus.includes("oxtail")) {
    const rec = EVENTBITE_ASSET_INVENTORY.oxtail;
    return { src: rec.path, focalPoint: rec.focalPoint, alt: rec.altDescription };
  }
  if (corpus.includes("bammy") || corpus.includes("festival")) {
    const rec = EVENTBITE_ASSET_INVENTORY.bammy;
    return { src: rec.path, focalPoint: rec.focalPoint, alt: rec.altDescription };
  }
  if (corpus.includes("plantain") || corpus.includes("side")) {
    const rec = EVENTBITE_ASSET_INVENTORY.fried_plantains_sides;
    return { src: rec.path, focalPoint: rec.focalPoint, alt: rec.altDescription };
  }
  if (corpus.includes("punch") || corpus.includes("sorrel") || corpus.includes("drink") || corpus.includes("beverage")) {
    const rec = EVENTBITE_ASSET_INVENTORY.rum_punch;
    return { src: rec.path, focalPoint: rec.focalPoint, alt: rec.altDescription };
  }
  if (corpus.includes("smoothie") || corpus.includes("juice")) {
    const rec = EVENTBITE_ASSET_INVENTORY.tropical_island_smoothie;
    return { src: rec.path, focalPoint: rec.focalPoint, alt: rec.altDescription };
  }

  const fallback = EVENTBITE_ASSET_INVENTORY.escovitch_fish;
  return { src: fallback.path, focalPoint: fallback.focalPoint, alt: fallback.altDescription };
}

