import {
  EventDetails,
  EventTicketTier,
  EventWizardStepId,
  MenuItem,
} from "../../types";

export type TimezoneOption = {
  id: string;
  label: string;
  utcOffsetLabel: string;
  region: string;
};

export const SUPPORTED_EVENT_TIMEZONES: TimezoneOption[] = [
  {
    id: "America/Jamaica",
    label: "Jamaica Time (Kingston / Montego Bay)",
    utcOffsetLabel: "UTC-05:00 (EST · No DST)",
    region: "Caribbean",
  },
  {
    id: "America/Port_of_Spain",
    label: "Eastern Caribbean Time (Trinidad / Barbados)",
    utcOffsetLabel: "UTC-04:00 (AST · No DST)",
    region: "Caribbean",
  },
  {
    id: "America/New_York",
    label: "US Eastern Time (Miami / New York / Atlanta)",
    utcOffsetLabel: "UTC-05:00 / UTC-04:00 (ET)",
    region: "North America",
  },
  {
    id: "America/Toronto",
    label: "Canada Eastern Time (Toronto / Montreal)",
    utcOffsetLabel: "UTC-05:00 / UTC-04:00 (ET)",
    region: "North America",
  },
  {
    id: "America/Chicago",
    label: "US Central Time (Chicago / Houston)",
    utcOffsetLabel: "UTC-06:00 / UTC-05:00 (CT)",
    region: "North America",
  },
  {
    id: "America/Denver",
    label: "US Mountain Time (Denver / Phoenix)",
    utcOffsetLabel: "UTC-07:00 / UTC-06:00 (MT)",
    region: "North America",
  },
  {
    id: "America/Los_Angeles",
    label: "US Pacific Time (Los Angeles / Seattle)",
    utcOffsetLabel: "UTC-08:00 / UTC-07:00 (PT)",
    region: "North America",
  },
  {
    id: "Europe/London",
    label: "UK Time (London / Birmingham)",
    utcOffsetLabel: "UTC+00:00 / UTC+01:00 (GMT/BST)",
    region: "Europe",
  },
];

export const SUPPORTED_EVENT_CATEGORIES: string[] = [
  "Seafood & Fish Fry",
  "Reggae & Live Music",
  "Cultural Festival",
  "Community Fundraiser",
  "Sports & Outdoors",
  "Nightlife & Lounge",
];

export type WizardStepDefinition = {
  id: EventWizardStepId;
  stepNumber: number;
  title: string;
  shortTitle: string;
  subtitle: string;
};

export const EVENT_WIZARD_STEPS: WizardStepDefinition[] = [
  {
    id: "basic_details",
    stepNumber: 1,
    title: "Basic Details",
    shortTitle: "Basic",
    subtitle: "Event identity, category, artwork & host info",
  },
  {
    id: "date_location",
    stepNumber: 2,
    title: "Date & Location",
    shortTitle: "Schedule",
    subtitle: "Schedule, venue, address & IANA timezone",
  },
  {
    id: "tickets",
    stepNumber: 3,
    title: "Tickets",
    shortTitle: "Tickets",
    subtitle: "Ticket tiers, pricing, capacity & sale windows",
  },
  {
    id: "food",
    stepNumber: 4,
    title: "Food Menu",
    shortTitle: "Food",
    subtitle: "Meal choices, add-ons, prices & dish photos",
  },
  {
    id: "review",
    stepNumber: 5,
    title: "Review & Publish",
    shortTitle: "Review",
    subtitle: "Readiness checklist, live preview & publication",
  },
];

export function isValidIanaTimezone(tz: string | undefined | null): boolean {
  if (!tz || typeof tz !== "string" || tz.trim().length === 0) return false;
  try {
    Intl.DateTimeFormat(undefined, { timeZone: tz.trim() });
    return true;
  } catch {
    return false;
  }
}

/**
 * Formats an ISO timestamp or date string consistently in the event's IANA timezone
 * and also returns the canonical UTC ISO-8601 string for database storage.
 */
export function formatEventTimestampWithTimezone(
  isoOrDateStr: string | undefined,
  timezone: string = "America/Jamaica"
): {
  isValid: boolean;
  ianaTimezone: string;
  utcIso: string;
  localDisplay: string;
} {
  const safeTz = isValidIanaTimezone(timezone) ? timezone : "America/Jamaica";
  if (!isoOrDateStr || isoOrDateStr.trim().length === 0) {
    return {
      isValid: false,
      ianaTimezone: safeTz,
      utcIso: "",
      localDisplay: "Not configured",
    };
  }

  const parsed = new Date(isoOrDateStr);
  if (Number.isNaN(parsed.getTime())) {
    return {
      isValid: false,
      ianaTimezone: safeTz,
      utcIso: "",
      localDisplay: isoOrDateStr,
    };
  }

  const localDisplay = parsed.toLocaleString("en-US", {
    timeZone: safeTz,
    weekday: "short",
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
    timeZoneName: "short",
  });

  return {
    isValid: true,
    ianaTimezone: safeTz,
    utcIso: parsed.toISOString(),
    localDisplay,
  };
}

/**
 * Generates default ticket tiers for an event if none have been configured yet.
 */
export function getDefaultEventTicketTiers(
  event?: Partial<EventDetails>
): EventTicketTier[] {
  if (event?.ticketTiers && event.ticketTiers.length > 0) {
    return event.ticketTiers;
  }
  return [
    {
      id: "tier-general-admission",
      name: "General Admission & Plate Reservation",
      category: "General",
      priceUSD: 15,
      priceJMD: 2300,
      availableStock: 250,
      maxPerCustomer: 10,
      saleStartIso: "2026-10-01T00:00",
      saleEndIso: "2026-11-22T18:00",
      description:
        "Standard entry with express QR check-in and priority food counter lane.",
      benefits: ["Express QR Gate Entry", "Guaranteed Advance Prep Window"],
      isActive: true,
    },
    {
      id: "tier-vip-waterfront",
      name: "VIP Waterfront Cabana Pass",
      category: "VIP",
      priceUSD: 35,
      priceJMD: 5400,
      availableStock: 60,
      maxPerCustomer: 6,
      saleStartIso: "2026-10-01T00:00",
      saleEndIso: "2026-11-21T20:00",
      description:
        "Reserved shaded waterfront seating, dedicated server, and complimentary welcome drink.",
      benefits: [
        "Reserved Waterfront Lounge",
        "Dedicated Table Runner",
        "Complimentary Sorrel or Rum Punch",
      ],
      isActive: true,
    },
    {
      id: "tier-early-bird",
      name: "Early Bird Community Supporter",
      category: "Early Bird",
      priceUSD: 12,
      priceJMD: 1800,
      availableStock: 75,
      maxPerCustomer: 4,
      saleStartIso: "2026-10-01T00:00",
      saleEndIso: "2026-11-10T23:59",
      description:
        "Discounted early supporter pass available before advance cutoff.",
      benefits: ["Discounted Early Rate", "Digital QR Wallet Ticket"],
      isActive: true,
    },
  ];
}

export type StepValidationResult = {
  isValid: boolean;
  fieldErrors: Record<string, string>;
  warnings: string[];
};

/**
 * Step 1: Basic Event Information Validation
 */
export function validateWizardBasicDetails(
  event: Partial<EventDetails>
): StepValidationResult {
  const fieldErrors: Record<string, string> = {};
  const warnings: string[] = [];

  if (!event.title || event.title.trim().length < 3) {
    fieldErrors.title = "Event name must be at least 3 characters.";
  } else if (event.title.trim().length > 200) {
    fieldErrors.title = "Event name cannot exceed 200 characters.";
  }

  const category = event.category || "Seafood & Fish Fry";
  if (!category || category.trim().length === 0) {
    fieldErrors.category = "Please select an event category.";
  }

  if (!event.description || event.description.trim().length < 10) {
    fieldErrors.description =
      "Provide a clear event description (at least 10 characters).";
  }

  if (!event.organizerName || event.organizerName.trim().length < 2) {
    fieldErrors.organizerName = "Organizer name is required.";
  }

  if (!event.organizerPhone || event.organizerPhone.trim().length < 7) {
    fieldErrors.organizerPhone =
      "Valid organizer contact phone (min 7 digits) is required.";
  }

  if (
    event.organizerEmail &&
    event.organizerEmail.trim().length > 0 &&
    !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(event.organizerEmail.trim())
  ) {
    fieldErrors.organizerEmail = "Please enter a valid organizer email address.";
  }

  if (!event.flyerUrl || event.flyerUrl.trim().length === 0) {
    warnings.push(
      "No custom event flyer uploaded yet — a default Caribbean culinary banner will be used until you upload one."
    );
  }

  return {
    isValid: Object.keys(fieldErrors).length === 0,
    fieldErrors,
    warnings,
  };
}

/**
 * Step 2: Date, Location & IANA Timezone Validation
 */
export function validateWizardDateLocation(
  event: Partial<EventDetails>
): StepValidationResult & {
  utcDeadlineIso: string;
  localDeadlineDisplay: string;
} {
  const fieldErrors: Record<string, string> = {};
  const warnings: string[] = [];

  if (!event.dateStr || event.dateStr.trim().length < 3) {
    fieldErrors.dateStr = "Event date is required.";
  }

  if (!event.timeStr || event.timeStr.trim().length < 3) {
    fieldErrors.timeStr = "Event start and end times are required.";
  }

  if (event.startTime && event.endTime) {
    if (event.startTime >= event.endTime) {
      fieldErrors.endTime = "Event end time must be after the start time.";
    }
  }

  const tz = event.timezone || "America/Jamaica";
  if (!isValidIanaTimezone(tz)) {
    fieldErrors.timezone = `Invalid IANA timezone '${tz}'. Select a valid region timezone.`;
  }

  if (!event.venueName || event.venueName.trim().length < 2) {
    fieldErrors.venueName = "Venue name is required.";
  }

  if (!event.venueAddress || event.venueAddress.trim().length < 5) {
    fieldErrors.venueAddress = "Full street or parish venue address is required.";
  }

  if (!event.advanceOrderDeadline || event.advanceOrderDeadline.trim().length === 0) {
    fieldErrors.advanceOrderDeadline = "Advance order cutoff deadline is required.";
  }

  const formattedDeadline = formatEventTimestampWithTimezone(
    event.advanceOrderDeadline,
    tz
  );

  if (
    event.advanceOrderDeadline &&
    !formattedDeadline.isValid &&
    event.advanceOrderDeadline.trim().length < 6
  ) {
    fieldErrors.advanceOrderDeadline =
      "Enter a valid ISO date/time or schedule string for the advance order cutoff.";
  }

  if (!event.pickupInstructions || event.pickupInstructions.trim().length < 5) {
    warnings.push(
      "Adding clear pickup & gate instructions helps attendees locate collection stations quickly."
    );
  }

  return {
    isValid: Object.keys(fieldErrors).length === 0,
    fieldErrors,
    warnings,
    utcDeadlineIso: formattedDeadline.utcIso,
    localDeadlineDisplay: formattedDeadline.localDisplay,
  };
}

/**
 * Step 3: Tickets & Pricing Rules Validation
 */
export function validateWizardTickets(
  tiers: EventTicketTier[],
  packagingFeeUSD: number = 0,
  packagingFeeJMD: number = 0
): StepValidationResult & {
  tierErrors: Record<string, string[]>;
  activeTiersCount: number;
  totalCapacity: number;
} {
  const fieldErrors: Record<string, string> = {};
  const tierErrors: Record<string, string[]> = {};
  const warnings: string[] = [];

  if (packagingFeeUSD < 0 || Number.isNaN(packagingFeeUSD)) {
    fieldErrors.packagingFeeUSD = "USD packaging fee cannot be negative.";
  }
  if (packagingFeeJMD < 0 || Number.isNaN(packagingFeeJMD)) {
    fieldErrors.packagingFeeJMD = "JMD packaging fee cannot be negative.";
  }

  const activeTiers = tiers.filter((t) => t.isActive !== false);
  if (activeTiers.length === 0) {
    fieldErrors.ticketTiers =
      "At least one active ticket type must be configured.";
  }

  let totalCapacity = 0;

  tiers.forEach((tier) => {
    const errs: string[] = [];
    if (!tier.name || tier.name.trim().length < 2) {
      errs.push("Ticket tier name must be at least 2 characters.");
    }
    if (
      typeof tier.priceUSD !== "number" ||
      Number.isNaN(tier.priceUSD) ||
      tier.priceUSD < 0
    ) {
      errs.push("USD price must be 0 or greater.");
    }
    if (
      typeof tier.priceJMD !== "number" ||
      Number.isNaN(tier.priceJMD) ||
      tier.priceJMD < 0
    ) {
      errs.push("JMD price must be 0 or greater.");
    }
    if (
      !Number.isInteger(tier.availableStock) ||
      tier.availableStock <= 0
    ) {
      errs.push("Quantity limit must be a positive whole number.");
    } else if (tier.isActive !== false) {
      totalCapacity += tier.availableStock;
    }

    if (
      !Number.isInteger(tier.maxPerCustomer) ||
      tier.maxPerCustomer < 1 ||
      (tier.availableStock > 0 && tier.maxPerCustomer > tier.availableStock)
    ) {
      errs.push(
        `Max per order must be between 1 and ${Math.max(1, tier.availableStock)}.`
      );
    }

    if (tier.saleStartIso && tier.saleEndIso) {
      const startMs = new Date(tier.saleStartIso).getTime();
      const endMs = new Date(tier.saleEndIso).getTime();
      if (Number.isNaN(startMs) || Number.isNaN(endMs)) {
        errs.push("Sale start and end dates must be valid timestamps.");
      } else if (startMs > endMs) {
        errs.push("Sale start date must be on or before the sale end date.");
      }
    }

    if (errs.length > 0) {
      tierErrors[tier.id] = errs;
      fieldErrors[`tier_${tier.id}`] = errs[0];
    }
  });

  return {
    isValid: Object.keys(fieldErrors).length === 0,
    fieldErrors,
    tierErrors,
    warnings,
    activeTiersCount: activeTiers.length,
    totalCapacity,
  };
}

/**
 * Step 4: Food Menu & Add-Ons Validation
 */
export function validateWizardFood(
  supportsFoodOrdering: boolean,
  menu: MenuItem[]
): StepValidationResult & {
  itemErrors: Record<string, string[]>;
} {
  const fieldErrors: Record<string, string> = {};
  const itemErrors: Record<string, string[]> = {};
  const warnings: string[] = [];

  if (!supportsFoodOrdering) {
    warnings.push(
      "Food ordering is disabled for this event (Admission Tickets Only mode)."
    );
    return {
      isValid: true,
      fieldErrors,
      itemErrors,
      warnings,
    };
  }

  if (!menu || menu.length === 0) {
    fieldErrors.menu =
      "Add at least one food or beverage item when food ordering is enabled.";
  }

  menu.forEach((item) => {
    const errs: string[] = [];
    if (!item.name || item.name.trim().length < 2) {
      errs.push("Menu item name must be at least 2 characters.");
    }
    if (item.priceUSD < 0 || Number.isNaN(item.priceUSD)) {
      errs.push("USD price cannot be negative.");
    }
    if (item.priceJMD < 0 || Number.isNaN(item.priceJMD)) {
      errs.push("JMD price cannot be negative.");
    }
    if (
      item.availableStock !== undefined &&
      (item.availableStock < 0 || !Number.isInteger(item.availableStock))
    ) {
      errs.push("Available stock must be a non-negative integer.");
    }
    if (
      item.pricingType === "customizable" &&
      (!item.customOptionGroups || item.customOptionGroups.length === 0)
    ) {
      errs.push(
        "Customizable menu items require at least one meal choice / add-on group."
      );
    }
    if (item.pricingType === "custom_amount") {
      const cfg = item.customAmountConfig;
      if (
        !cfg ||
        cfg.minUSD <= 0 ||
        cfg.maxUSD < cfg.minUSD ||
        cfg.minJMD <= 0 ||
        cfg.maxJMD < cfg.minJMD
      ) {
        errs.push(
          "Custom-amount items require positive minimum and maximum bounds."
        );
      }
    }
    if (errs.length > 0) {
      itemErrors[item.id] = errs;
      fieldErrors[`menu_${item.id}`] = errs[0];
    }
  });

  return {
    isValid: Object.keys(fieldErrors).length === 0,
    fieldErrors,
    itemErrors,
    warnings,
  };
}

/**
 * Sanitizes a user-supplied filename into a safe, non-traversable storage filename.
 */
export function sanitizeSafeUploadFilename(rawName: string): string {
  const baseName = rawName.split(/[\\/]/).pop() || "upload.jpg";
  return baseName.replace(/[^a-zA-Z0-9._-]/g, "_").toLowerCase();
}

/**
 * Validates an uploaded image (flyer or menu dish photo) for safe format, size, dimensions, and authorization.
 */
export function validateWizardImageUpload(
  file: {
    name: string;
    sizeBytes: number;
    mimeType: string;
    dimensions?: { width: number; height: number };
  },
  organizerId: string,
  canManageEventsAndMenus: boolean,
  kind: "flyers" | "menu" = "flyers"
): {
  valid: boolean;
  safeFileName?: string;
  storagePath?: string;
  error?: string;
} {
  if (!canManageEventsAndMenus) {
    return {
      valid: false,
      error:
        "Authorization required: Only Event Owners or Platform Admins can upload event images.",
    };
  }

  if (
    file.name.includes("../") ||
    file.name.includes("..\\") ||
    file.name.includes("\0") ||
    file.name.includes("/") ||
    file.name.includes("\\")
  ) {
    return {
      valid: false,
      error: "Unsafe filename rejected: path traversal characters are not permitted.",
    };
  }

  const allowedMimeTypes = ["image/jpeg", "image/png", "image/webp"];
  const normalizedMime = file.mimeType.toLowerCase();
  if (!allowedMimeTypes.includes(normalizedMime)) {
    return {
      valid: false,
      error: `Unsupported image format '${file.mimeType}'. Permitted formats: JPG, PNG, and WebP only.`,
    };
  }

  const maxSizeBytes = 5 * 1024 * 1024; // 5 MB
  if (file.sizeBytes <= 0 || file.sizeBytes > maxSizeBytes) {
    return {
      valid: false,
      error: `Image file size (${(file.sizeBytes / (1024 * 1024)).toFixed(
        2
      )} MB) exceeds the 5 MB maximum limit.`,
    };
  }

  if (file.dimensions) {
    const minWidth = kind === "flyers" ? 300 : 200;
    const minHeight = kind === "flyers" ? 200 : 200;
    const maxWidth = 4500;
    const maxHeight = 4500;

    if (
      file.dimensions.width < minWidth ||
      file.dimensions.height < minHeight
    ) {
      return {
        valid: false,
        error: `Image dimensions (${file.dimensions.width}×${file.dimensions.height}px) are below the minimum ${minWidth}×${minHeight}px.`,
      };
    }

    if (
      file.dimensions.width > maxWidth ||
      file.dimensions.height > maxHeight
    ) {
      return {
        valid: false,
        error: `Image dimensions (${file.dimensions.width}×${file.dimensions.height}px) exceed the maximum ${maxWidth}×${maxHeight}px.`,
      };
    }
  }

  const safeFileName = sanitizeSafeUploadFilename(file.name);
  const ext =
    normalizedMime === "image/png"
      ? "png"
      : normalizedMime === "image/webp"
      ? "webp"
      : "jpg";
  const randomSuffix = Math.random().toString(16).substring(2, 10);
  const storagePath = `tenants/${organizerId}/${kind}/${Date.now()}_${randomSuffix}.${ext}`;

  return {
    valid: true,
    safeFileName,
    storagePath,
  };
}

export type PublishChecklistItem = {
  id: string;
  stepId: EventWizardStepId;
  label: string;
  detail: string;
  passed: boolean;
  requiredForPublish: boolean;
};

export type PublishReadinessReport = {
  canPublish: boolean;
  completedRequiredCount: number;
  totalRequiredCount: number;
  blockingReasons: string[];
  stepStatus: Record<EventWizardStepId, "complete" | "incomplete">;
  checklist: PublishChecklistItem[];
};

/**
 * Evaluates full readiness across all 5 wizard stages before allowing an event to be published.
 */
export function evaluateWizardPublishReadiness(
  event: EventDetails,
  tiers: EventTicketTier[],
  menu: MenuItem[]
): PublishReadinessReport {
  const supportsFood = event.supportsFoodOrdering !== false;
  const basic = validateWizardBasicDetails(event);
  const dateLoc = validateWizardDateLocation(event);
  const tickets = validateWizardTickets(
    tiers,
    event.packagingFeeUSD,
    event.packagingFeeJMD
  );
  const food = validateWizardFood(supportsFood, menu);

  const checklist: PublishChecklistItem[] = [
    {
      id: "chk-basic-details",
      stepId: "basic_details",
      label: "Basic Event Details & Host Contact",
      detail: basic.isValid
        ? `${event.title} · ${event.category || "Seafood & Fish Fry"} · Host: ${event.organizerName}`
        : Object.values(basic.fieldErrors)[0] || "Missing basic event fields.",
      passed: basic.isValid,
      requiredForPublish: true,
    },
    {
      id: "chk-flyer-artwork",
      stepId: "basic_details",
      label: "Event Promotional Artwork / Flyer",
      detail: event.flyerUrl
        ? "Official promotional flyer verified and attached"
        : "Using high-resolution Caribbean fallback banner (upload optional)",
      passed: Boolean(event.flyerUrl),
      requiredForPublish: false,
    },
    {
      id: "chk-date-location",
      stepId: "date_location",
      label: "Date, Venue Address & IANA Timezone",
      detail: dateLoc.isValid
        ? `${event.dateStr} (${event.timeStr}) · ${event.venueName} · TZ: ${
            event.timezone || "America/Jamaica"
          }`
        : Object.values(dateLoc.fieldErrors)[0] ||
          "Missing schedule or location fields.",
      passed: dateLoc.isValid,
      requiredForPublish: true,
    },
    {
      id: "chk-tickets-pricing",
      stepId: "tickets",
      label: "Ticket Tiers, Capacity Limits & Packaging Policy",
      detail: tickets.isValid
        ? `${tickets.activeTiersCount} active tier(s) · ${tickets.totalCapacity} total capacity · Packaging: $${event.packagingFeeUSD.toFixed(
            2
          )} USD / J$${event.packagingFeeJMD}`
        : Object.values(tickets.fieldErrors)[0] ||
          "Invalid ticket pricing or stock configuration.",
      passed: tickets.isValid,
      requiredForPublish: true,
    },
    {
      id: "chk-food-menu",
      stepId: "food",
      label: "Food Menu, Meal Choices & Add-On Availability",
      detail: !supportsFood
        ? "Food ordering disabled for this event (Admission Tickets Only)"
        : food.isValid
        ? `${menu.length} menu dish(es) configured with verified pricing & stock`
        : Object.values(food.fieldErrors)[0] ||
          "Menu configuration incomplete.",
      passed: food.isValid,
      requiredForPublish: true,
    },
  ];

  const requiredItems = checklist.filter((c) => c.requiredForPublish);
  const completedRequiredCount = requiredItems.filter((c) => c.passed).length;
  const blockingReasons = requiredItems
    .filter((c) => !c.passed)
    .map((c) => `${c.label}: ${c.detail}`);

  const canPublish = blockingReasons.length === 0;

  return {
    canPublish,
    completedRequiredCount,
    totalRequiredCount: requiredItems.length,
    blockingReasons,
    stepStatus: {
      basic_details: basic.isValid ? "complete" : "incomplete",
      date_location: dateLoc.isValid ? "complete" : "incomplete",
      tickets: tickets.isValid ? "complete" : "incomplete",
      food: food.isValid ? "complete" : "incomplete",
      review: canPublish ? "complete" : "incomplete",
    },
    checklist,
  };
}

