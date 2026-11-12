import { EventDetails, EventWizardStepId, MenuItem } from "../types";

export type RecommendationSeverity = "critical" | "warning" | "suggestion";

export const RECOMMENDATION_SEVERITY_META: Record<
  RecommendationSeverity,
  {
    label: string;
    subtitle: string;
    badgeColor: string;
  }
> = {
  critical: {
    label: "Critical",
    subtitle: "Must fix before publishing.",
    badgeColor: "#EF4444",
  },
  warning: {
    label: "Warning",
    subtitle: "Recommended correction.",
    badgeColor: "#F59E0B",
  },
  suggestion: {
    label: "Suggestion",
    subtitle: "Optional improvement.",
    badgeColor: "#38BDF8",
  },
};

export type ReadinessDomainKey =
  | "event_information"
  | "tickets_and_pricing"
  | "food_menu"
  | "marketing_materials"
  | "policies_and_logistics";

export type ReadinessDomainStatus = "Complete" | "Needs attention";

export interface EventIntelligenceRecommendation {
  id: string;
  ruleCode:
    | "capacity_exceeded_by_ticket_inventory"
    | "food_pickup_ends_before_event_ends"
    | "vip_ticket_missing_benefits"
    | "food_menu_missing_images"
    | "missing_cancellation_policy"
    | "ticket_sales_end_after_event_begins"
    | "meal_packages_contain_unavailable_items"
    | "incomplete_event_identity"
    | "missing_promotional_flyer"
    | "optional_attendee_faqs_suggestion"
    | "optional_early_bird_suggestion";
  severity: RecommendationSeverity;
  domain: ReadinessDomainKey;
  message: string;
  actionLabel: string;
  targetWizardStep: EventWizardStepId;
  targetStudioModuleId: EventStudioModuleId;
}

export interface ReadinessDomainSummary {
  key: ReadinessDomainKey;
  label: string;
  status: ReadinessDomainStatus;
  weightPoints: number;
  earnedPoints: number;
  issuesCount: number;
}

export interface EventReadinessIntelligenceReport {
  scorePercent: number;
  canPublish: boolean;
  domains: ReadinessDomainSummary[];
  recommendations: EventIntelligenceRecommendation[];
  criticalCount: number;
  warningCount: number;
  suggestionCount: number;
}

export type EventStudioModuleId =
  | "event_identity"
  | "culture_and_cuisine"
  | "date_and_venue"
  | "tickets_and_pricing"
  | "food_commerce"
  | "people_and_vendors"
  | "event_experience"
  | "marketing_studio"
  | "financial_setup"
  | "review_and_publish";

export interface EventStudioModuleDefinition {
  id: EventStudioModuleId;
  number: number;
  title: string;
  description: string;
  mappedWizardStep: EventWizardStepId;
  architecturalPillar: EventArchitecturePillarId;
}

export type EventArchitecturePillarId =
  | "event_creation"
  | "ticket_management"
  | "food_management"
  | "marketing"
  | "operations"
  | "analytics";

export interface EventArchitecturePillar {
  id: EventArchitecturePillarId;
  title: string;
  purpose: string;
  sharedPermissionsNote: string;
}

export const EVENT_ARCHITECTURE_PILLARS: EventArchitecturePillar[] = [
  {
    id: "event_creation",
    title: "Event creation",
    purpose: "Establishes the event and its essential information.",
    sharedPermissionsNote: "Shares event record & RBAC permissions",
  },
  {
    id: "ticket_management",
    title: "Ticket management",
    purpose: "Configures admission, tiers, benefits, and capacity.",
    sharedPermissionsNote: "Shares event record & RBAC permissions",
  },
  {
    id: "food_management",
    title: "Food management",
    purpose: "Configures ordering, menus, packages, and pickup windows.",
    sharedPermissionsNote: "Shares event record & RBAC permissions",
  },
  {
    id: "marketing",
    title: "Marketing",
    purpose: "Manages promotion, flyers, QR artwork, and sharing.",
    sharedPermissionsNote: "Shares event record & RBAC permissions",
  },
  {
    id: "operations",
    title: "Operations",
    purpose: "Manages workers, stations, shifts, and event-day logistics.",
    sharedPermissionsNote: "Shares event record & RBAC permissions",
  },
  {
    id: "analytics",
    title: "Analytics",
    purpose: "Manages performance, check-in progress, and post-event reports.",
    sharedPermissionsNote: "Shares event record & RBAC permissions",
  },
];

export const EVENT_STUDIO_MODULES: EventStudioModuleDefinition[] = [
  {
    id: "event_identity",
    number: 1,
    title: "Event Identity",
    description: "Basic information, branding, event type and description.",
    mappedWizardStep: "basic_details",
    architecturalPillar: "event_creation",
  },
  {
    id: "culture_and_cuisine",
    number: 2,
    title: "Culture and Cuisine",
    description: "Cultural regions, countries, cuisines and dishes.",
    mappedWizardStep: "basic_details",
    architecturalPillar: "event_creation",
  },
  {
    id: "date_and_venue",
    number: 3,
    title: "Date and Venue",
    description: "Scheduling, location, seating and capacity.",
    mappedWizardStep: "date_location",
    architecturalPillar: "event_creation",
  },
  {
    id: "tickets_and_pricing",
    number: 4,
    title: "Tickets and Pricing",
    description: "Ticket types, benefits, discounts and design.",
    mappedWizardStep: "tickets",
    architecturalPillar: "ticket_management",
  },
  {
    id: "food_commerce",
    number: 5,
    title: "Food Commerce",
    description: "Menu items, images, packages, customization and pickup.",
    mappedWizardStep: "food",
    architecturalPillar: "food_management",
  },
  {
    id: "people_and_vendors",
    number: 6,
    title: "People and Vendors",
    description: "Co-hosts, staff, vendors and sponsors.",
    mappedWizardStep: "date_location",
    architecturalPillar: "operations",
  },
  {
    id: "event_experience",
    number: 7,
    title: "Event Experience",
    description: "Activities, schedule, FAQs and attendee instructions.",
    mappedWizardStep: "basic_details",
    architecturalPillar: "event_creation",
  },
  {
    id: "marketing_studio",
    number: 8,
    title: "Marketing Studio",
    description: "Flyer, promotional artwork, QR code and social sharing.",
    mappedWizardStep: "basic_details",
    architecturalPillar: "marketing",
  },
  {
    id: "financial_setup",
    number: 9,
    title: "Financial Setup",
    description: "Expenses, pricing, targets and payment configuration.",
    mappedWizardStep: "tickets",
    architecturalPillar: "analytics",
  },
  {
    id: "review_and_publish",
    number: 10,
    title: "Review and Publish",
    description: "Event readiness, preview and final publication.",
    mappedWizardStep: "review",
    architecturalPillar: "event_creation",
  },
];

export interface GrowthFeatureCapability {
  id: string;
  title: string;
  description: string;
  phase: 1 | 2 | 3 | 4 | 5;
  defaultEnabled: boolean;
}

export const GROWTH_PLATFORM_CAPABILITIES: GrowthFeatureCapability[] = [
  {
    id: "recurring_events",
    title: "Recurring events",
    description: "Create weekly, monthly or seasonal events.",
    phase: 3,
    defaultEnabled: false,
  },
  {
    id: "event_duplication",
    title: "Event duplication",
    description: "Copy a previous event's settings, menu and ticket configuration.",
    phase: 3,
    defaultEnabled: true,
  },
  {
    id: "co_host_management",
    title: "Co-host management",
    description: "Give multiple authorized organizers access to an event.",
    phase: 3,
    defaultEnabled: true,
  },
  {
    id: "event_templates",
    title: "Event templates",
    description: "Save reusable configurations for different event types.",
    phase: 3,
    defaultEnabled: true,
  },
  {
    id: "multiple_venues",
    title: "Multiple venues",
    description: "Support events operating across several locations.",
    phase: 3,
    defaultEnabled: false,
  },
  {
    id: "weather_contingency",
    title: "Weather contingency",
    description: "Configure postponement and cancellation procedures.",
    phase: 2,
    defaultEnabled: true,
  },
  {
    id: "guest_checkin_analytics",
    title: "Guest check-in analytics",
    description: "View attendance and check-in progress.",
    phase: 4,
    defaultEnabled: true,
  },
  {
    id: "post_event_reports",
    title: "Post-event reports",
    description: "Summarize ticket sales, food sales and attendance.",
    phase: 4,
    defaultEnabled: true,
  },
  {
    id: "loyalty_rewards",
    title: "Loyalty rewards",
    description: "Reward returning attendees.",
    phase: 4,
    defaultEnabled: false,
  },
  {
    id: "guest_photo_gallery",
    title: "Guest photo gallery",
    description: "Collect and display approved event photos.",
    phase: 3,
    defaultEnabled: true,
  },
  {
    id: "waitlist_conversion",
    title: "Waitlist conversion",
    description: "Offer newly available tickets to waiting customers.",
    phase: 2,
    defaultEnabled: true,
  },
  {
    id: "event_feedback",
    title: "Event feedback",
    description: "Collect ratings and feedback after the event.",
    phase: 3,
    defaultEnabled: true,
  },
];

export const IMPLEMENTATION_ROADMAP_PHASES = [
  {
    phase: "Phase 1",
    features:
      "Country/cuisine explorer, food builder, meal packages, ticket designer",
    reason: "Core EventBite differentiation",
  },
  {
    phase: "Phase 2",
    features:
      "Inventory, pickup scheduling, kitchen dashboard, ticket discounts",
    reason: "Operational functionality",
  },
  {
    phase: "Phase 3",
    features: "Event schedule, vendors, sponsors, seating",
    reason: "More comprehensive event management",
  },
  {
    phase: "Phase 4",
    features: "Marketing studio, analytics, financial forecasting",
    reason: "Host business tools",
  },
  {
    phase: "Phase 5",
    features: "AI assistant, recommendations, event intelligence",
    reason: "Advanced capabilities",
  },
];

function format24hTo12hLabel(time24?: string): string {
  if (!time24 || !/^\d{1,2}:\d{2}$/.test(time24.trim())) {
    return time24 || "";
  }
  const [hStr, mStr] = time24.trim().split(":");
  const h = Number(hStr);
  const m = Number(mStr);
  const period = h >= 12 ? "PM" : "AM";
  const h12 = h % 12 === 0 ? 12 : h % 12;
  if (m === 0) {
    return `${h12} ${period}`;
  }
  return `${h12}:${String(m).padStart(2, "0")} ${period}`;
}

function parseMinutesSinceMidnight(time24?: string): number | null {
  if (!time24 || !/^\d{1,2}:\d{2}$/.test(time24.trim())) {
    return null;
  }
  const [h, m] = time24.trim().split(":").map(Number);
  if (Number.isNaN(h) || Number.isNaN(m)) return null;
  return h * 60 + m;
}

export function evaluateEventIntelligenceAndReadiness(
  eventDetails: Partial<EventDetails>,
  menu: MenuItem[] = []
): EventReadinessIntelligenceReport {
  const recommendations: EventIntelligenceRecommendation[] = [];

  // 1. Check venue capacity vs. ticket inventory
  // Example: "Your venue capacity is 200, but your ticket inventory is 300."
  const activeTiers = (eventDetails.ticketTiers || []).filter(
    (t) => t.isActive !== false
  );
  const totalTicketInventory = activeTiers.reduce(
    (sum, tier) => sum + (Number(tier.availableStock) || 0),
    0
  );
  const venueCapacity = Number(eventDetails.expectedAttendance || 0);

  if (venueCapacity > 0 && totalTicketInventory > venueCapacity) {
    recommendations.push({
      id: "rec-capacity-mismatch",
      ruleCode: "capacity_exceeded_by_ticket_inventory",
      severity: "critical",
      domain: "tickets_and_pricing",
      message: `Your venue capacity is ${venueCapacity}, but your ticket inventory is ${totalTicketInventory}.`,
      actionLabel: "Align Ticket Inventory with Venue Capacity",
      targetWizardStep: "tickets",
      targetStudioModuleId: "tickets_and_pricing",
    });
  }

  // 2. Check event end time vs. food pickup end time
  // Example: "Your event ends at 10 PM, but food pickup ends at 8 PM."
  const eventEndMin = parseMinutesSinceMidnight(eventDetails.endTime);
  const foodEndRaw =
    eventDetails.foodServiceEndTime ||
    eventDetails.foodServiceConfig?.serviceEndTime;
  const foodEndMin = parseMinutesSinceMidnight(foodEndRaw);

  if (
    eventDetails.supportsFoodOrdering !== false &&
    eventEndMin !== null &&
    foodEndMin !== null &&
    foodEndMin < eventEndMin
  ) {
    const eventEndLabel = format24hTo12hLabel(eventDetails.endTime);
    const foodEndLabel = format24hTo12hLabel(foodEndRaw);
    recommendations.push({
      id: "rec-food-pickup-window",
      ruleCode: "food_pickup_ends_before_event_ends",
      severity: "warning",
      domain: "food_menu",
      message: `Your event ends at ${eventEndLabel}, but food pickup ends at ${foodEndLabel}.`,
      actionLabel: "Adjust Food Pickup Window",
      targetWizardStep: "date_location",
      targetStudioModuleId: "food_commerce",
    });
  }

  // 3. Check VIP ticket benefits
  // Example: "Your VIP ticket does not have any defined benefits."
  const vipTiersWithoutBenefits = activeTiers.filter((tier) => {
    const isVip =
      tier.category === "VIP" || tier.name.toLowerCase().includes("vip");
    const hasBenefits =
      Array.isArray(tier.perks) &&
      tier.perks.some((p) => typeof p === "string" && p.trim().length > 0);
    return isVip && !hasBenefits;
  });

  if (vipTiersWithoutBenefits.length > 0) {
    recommendations.push({
      id: "rec-vip-missing-benefits",
      ruleCode: "vip_ticket_missing_benefits",
      severity: "warning",
      domain: "tickets_and_pricing",
      message: "Your VIP ticket does not have any defined benefits.",
      actionLabel: "Add VIP Perks & Fast-Track Benefits",
      targetWizardStep: "tickets",
      targetStudioModuleId: "tickets_and_pricing",
    });
  }

  // 4. Check food menu images
  // Example: "Your food menu has no images."
  if (eventDetails.supportsFoodOrdering !== false && menu.length > 0) {
    const itemsWithRealImage = menu.filter(
      (item) =>
        Boolean(item.imageUrl && item.imageUrl.trim().length > 0) &&
        !item.imageUrl?.includes("placeholder")
    );
    if (itemsWithRealImage.length === 0) {
      recommendations.push({
        id: "rec-food-menu-no-images",
        ruleCode: "food_menu_missing_images",
        severity: "warning",
        domain: "food_menu",
        message: "Your food menu has no images.",
        actionLabel: "Upload Dish Photography",
        targetWizardStep: "food",
        targetStudioModuleId: "food_commerce",
      });
    } else if (itemsWithRealImage.length < menu.length) {
      const missingCount = menu.length - itemsWithRealImage.length;
      recommendations.push({
        id: "rec-food-menu-partial-images",
        ruleCode: "food_menu_missing_images",
        severity: "suggestion",
        domain: "food_menu",
        message: `${missingCount} of ${menu.length} food menu items do not have dish photography yet.`,
        actionLabel: "Add Missing Dish Photos",
        targetWizardStep: "food",
        targetStudioModuleId: "food_commerce",
      });
    }
  }

  // 5. Check cancellation policy
  // Example: "Your event does not have a cancellation policy."
  const hasCancellationPolicy = Boolean(
    eventDetails.refundPolicyMode ||
      (eventDetails.refundPolicyNotes &&
        eventDetails.refundPolicyNotes.trim().length > 0) ||
      eventDetails.hostCancellationPolicy
  );

  if (!hasCancellationPolicy) {
    recommendations.push({
      id: "rec-missing-cancellation-policy",
      ruleCode: "missing_cancellation_policy",
      severity: "critical",
      domain: "policies_and_logistics",
      message: "Your event does not have a cancellation policy.",
      actionLabel: "Configure Cancellation & Refund Policy",
      targetWizardStep: "tickets",
      targetStudioModuleId: "financial_setup",
    });
  }

  // 6. Check ticket sales end after event begins
  // Example: "Your ticket sales end after the event begins."
  if (
    eventDetails.ticketSalesEndIso &&
    eventDetails.eventDateIso &&
    eventDetails.startTime
  ) {
    const eventStartIso = `${eventDetails.eventDateIso}T${eventDetails.startTime}`;
    const salesEndTs = Date.parse(eventDetails.ticketSalesEndIso);
    const eventStartTs = Date.parse(eventStartIso);
    if (
      !Number.isNaN(salesEndTs) &&
      !Number.isNaN(eventStartTs) &&
      salesEndTs > eventStartTs
    ) {
      recommendations.push({
        id: "rec-ticket-sales-after-start",
        ruleCode: "ticket_sales_end_after_event_begins",
        severity: "critical",
        domain: "tickets_and_pricing",
        message: "Your ticket sales end after the event begins.",
        actionLabel: "Set Ticket Sales Cutoff Before Event Start",
        targetWizardStep: "tickets",
        targetStudioModuleId: "tickets_and_pricing",
      });
    }
  }

  // 7. Check selected meal packages contain unavailable items
  // Example: "Your selected meal packages contain unavailable items."
  const packages = eventDetails.packages || [];
  if (packages.length > 0) {
    const menuById = new Map(menu.map((item) => [item.id, item]));
    let hasUnavailablePackageItem = false;

    for (const pkg of packages) {
      for (const inc of pkg.includedItems || []) {
        const matchedItem = menuById.get(inc.menuItemId);
        if (
          !matchedItem ||
          matchedItem.available === false ||
          (typeof matchedItem.stockRemaining === "number" &&
            matchedItem.stockRemaining <= 0)
        ) {
          hasUnavailablePackageItem = true;
          break;
        }
      }
      if (hasUnavailablePackageItem) break;
    }

    if (hasUnavailablePackageItem) {
      recommendations.push({
        id: "rec-package-unavailable-items",
        ruleCode: "meal_packages_contain_unavailable_items",
        severity: "critical",
        domain: "food_menu",
        message: "Your selected meal packages contain unavailable items.",
        actionLabel: "Update Meal Package Items",
        targetWizardStep: "food",
        targetStudioModuleId: "food_commerce",
      });
    }
  }

  // Additional baseline checks for Event Information & Marketing Materials
  const hasCoreIdentity = Boolean(
    eventDetails.title?.trim() &&
      eventDetails.description?.trim() &&
      eventDetails.venueName?.trim() &&
      eventDetails.eventDateIso
  );
  if (!hasCoreIdentity) {
    recommendations.push({
      id: "rec-incomplete-identity",
      ruleCode: "incomplete_event_identity",
      severity: "critical",
      domain: "event_information",
      message:
        "Core event identity (title, description, date, or venue) is incomplete.",
      actionLabel: "Complete Event Identity",
      targetWizardStep: "basic_details",
      targetStudioModuleId: "event_identity",
    });
  }

  const hasPromotionalFlyer = Boolean(
    eventDetails.flyerUrl && eventDetails.flyerUrl.trim().length > 0
  );
  if (!hasPromotionalFlyer) {
    recommendations.push({
      id: "rec-missing-flyer",
      ruleCode: "missing_promotional_flyer",
      severity: "warning",
      domain: "marketing_materials",
      message:
        "No promotional event flyer is uploaded yet for discovery cards and social sharing.",
      actionLabel: "Upload Event Flyer in Marketing Studio",
      targetWizardStep: "basic_details",
      targetStudioModuleId: "marketing_studio",
    });
  }

  // Optional Suggestion: Early Bird or Group perk
  const hasEarlyBird = activeTiers.some(
    (t) =>
      t.category === "Early Bird" ||
      t.name.toLowerCase().includes("early bird")
  );
  if (activeTiers.length > 0 && !hasEarlyBird) {
    recommendations.push({
      id: "rec-suggestion-early-bird",
      ruleCode: "optional_early_bird_suggestion",
      severity: "suggestion",
      domain: "tickets_and_pricing",
      message:
        "Consider adding an Early Bird ticket tier to accelerate early registrations.",
      actionLabel: "Add Early Bird Tier",
      targetWizardStep: "tickets",
      targetStudioModuleId: "tickets_and_pricing",
    });
  }

  // Compute the 5 canonical domain statuses and deterministic score (out of 100)
  const domainSpecs: {
    key: ReadinessDomainKey;
    label: string;
    weightPoints: number;
  }[] = [
    {
      key: "event_information",
      label: "Event information",
      weightPoints: 25,
    },
    {
      key: "tickets_and_pricing",
      label: "Tickets and pricing",
      weightPoints: 25,
    },
    {
      key: "food_menu",
      label: "Food menu",
      weightPoints: 20,
    },
    {
      key: "marketing_materials",
      label: "Marketing materials",
      weightPoints: 15,
    },
    {
      key: "policies_and_logistics",
      label: "Policies and logistics",
      weightPoints: 15,
    },
  ];

  const domains: ReadinessDomainSummary[] = domainSpecs.map((spec) => {
    const domainRecs = recommendations.filter((r) => r.domain === spec.key);
    const criticalInDomain = domainRecs.filter(
      (r) => r.severity === "critical"
    ).length;
    const warningInDomain = domainRecs.filter(
      (r) => r.severity === "warning"
    ).length;

    let penalty = criticalInDomain * 15 + warningInDomain * 7;
    if (spec.key === "tickets_and_pricing" && activeTiers.length === 0) {
      penalty = spec.weightPoints;
    }
    const earnedPoints = Math.max(0, spec.weightPoints - penalty);
    const needsAttention =
      criticalInDomain > 0 ||
      warningInDomain > 0 ||
      (spec.key === "tickets_and_pricing" && activeTiers.length === 0);

    return {
      key: spec.key,
      label: spec.label,
      status: needsAttention ? "Needs attention" : "Complete",
      weightPoints: spec.weightPoints,
      earnedPoints,
      issuesCount: criticalInDomain + warningInDomain,
    };
  });

  const totalEarned = domains.reduce((acc, d) => acc + d.earnedPoints, 0);
  const scorePercent = Math.max(0, Math.min(100, Math.round(totalEarned)));

  const criticalCount = recommendations.filter(
    (r) => r.severity === "critical"
  ).length;
  const warningCount = recommendations.filter(
    (r) => r.severity === "warning"
  ).length;
  const suggestionCount = recommendations.filter(
    (r) => r.severity === "suggestion"
  ).length;

  return {
    scorePercent,
    canPublish: criticalCount === 0 && activeTiers.length > 0 && hasCoreIdentity,
    domains,
    recommendations,
    criticalCount,
    warningCount,
    suggestionCount,
  };
}

