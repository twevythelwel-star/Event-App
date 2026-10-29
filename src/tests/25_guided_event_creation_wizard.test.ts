import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { EventDetails, MenuItem } from "../types";
import { INITIAL_SAVED_STATE } from "../data/defaultData";
import { computeHostRolePermissions } from "../components/host/financialCalculations";
import {
  EVENT_WIZARD_STEPS,
  SUPPORTED_EVENT_CATEGORIES,
  SUPPORTED_EVENT_TIMEZONES,
  evaluateWizardPublishReadiness,
  formatEventTimestampWithTimezone,
  getDefaultEventTicketTiers,
  isValidIanaTimezone,
  sanitizeSafeUploadFilename,
  validateWizardBasicDetails,
  validateWizardDateLocation,
  validateWizardFood,
  validateWizardImageUpload,
  validateWizardTickets,
} from "../components/host/wizardValidation";
import { EventCreationWizard } from "../components/host/EventCreationWizard";

describe("25: Guided Event-Creation Wizard & Readiness Gate Suite", () => {
  const sampleEvent: EventDetails = {
    id: "evt-kingston-harbour-fry",
    organizerId: "org-kingston-youth",
    slug: "kingston-harbour-sunday-fish-fry",
    title: "Kingston Harbour Sunday Fish Fry",
    tagline: "Authentic Escovitch Snapper, Roast Breadfruit & Festival",
    category: "Seafood & Fish Fry",
    dateStr: "Sunday, November 22, 2026",
    eventDateIso: "2026-11-22",
    timeStr: "12:00 PM – 8:00 PM",
    startTime: "12:00",
    endTime: "20:00",
    timezone: "America/Jamaica",
    venueName: "Kingston Waterfront Pier",
    venueAddress: "Ocean Boulevard, Downtown Kingston, Jamaica",
    coordinates: "17.9647° N, 76.7936° W",
    description:
      "Annual community waterfront fish fry supporting youth athletics and neighborhood programs.",
    currency: "JMD",
    organizerName: "Kingston Youth & Community Foundation",
    organizerPhone: "+1 (876) 926-3849",
    organizerWhatsApp: "+1 (876) 555-4321",
    organizerEmail: "events@kingstonyouth.org.jm",
    advanceOrderDeadline: "2026-11-20T20:00:00-05:00",
    isOrderingOpen: false,
    status: "draft",
    pickupInstructions:
      "Present your verified QR code at Express Collection Tent B.",
    packagingFeeUSD: 1.0,
    packagingFeeJMD: 150,
    supportsFoodOrdering: true,
    ticketTiers: getDefaultEventTicketTiers(),
    flyerUrl: "/images/escovitch_fish.jpg",
    isFlyerPublic: false,
    shareableUrl: "https://islandevents.app/e/kingston-harbour-sunday-fish-fry",
  };

  const sampleMenu: MenuItem[] = [
    {
      id: "menu-escovitch-snapper",
      organizerId: "org-kingston-youth",
      name: "Large Escovitch Red Snapper Plate",
      category: "Mains",
      description:
        "Whole fried red snapper topped with spicy pickled scotch bonnetmedley.",
      pricingType: "customizable",
      priceUSD: 18,
      priceJMD: 2800,
      imageUrl: "/images/escovitch_fish.jpg",
      availableStock: 90,
      customOptionGroups: [
        {
          id: "grp-side",
          title: "Choice of Side",
          required: true,
          choices: [
            { label: "Bammy & Festival", extraPriceUSD: 0, extraPriceJMD: 0 },
            { label: "Roast Breadfruit", extraPriceUSD: 2, extraPriceJMD: 300 },
          ],
        },
      ],
    },
  ];

  describe("1. Multi-Step Progress Indicator & Stage Definitions", () => {
    it("defines the 5 ordered wizard stages matching event-creation requirements", () => {
      expect(EVENT_WIZARD_STEPS.map((s) => s.id)).toEqual([
        "basic_details",
        "date_location",
        "tickets",
        "food",
        "review",
      ]);
      expect(EVENT_WIZARD_STEPS.map((s) => s.stepNumber)).toEqual([
        1, 2, 3, 4, 5,
      ]);
      expect(SUPPORTED_EVENT_CATEGORIES.length).toBeGreaterThanOrEqual(5);
      expect(SUPPORTED_EVENT_TIMEZONES.some((t) => t.id === "America/Jamaica")).toBe(
        true
      );
    });
  });

  describe("2. Stage 1 — Basic Event Information Validation", () => {
    it("passes when event name, category, description, and organizer info are valid", () => {
      const result = validateWizardBasicDetails(sampleEvent);
      expect(result.isValid).toBe(true);
      expect(Object.keys(result.fieldErrors)).toHaveLength(0);
    });

    it("returns clear field-level errors for missing or invalid basic fields", () => {
      const invalid = validateWizardBasicDetails({
        ...sampleEvent,
        title: "Ab",
        description: "Too short",
        organizerName: "",
        organizerPhone: "123",
        organizerEmail: "not-an-email",
      });
      expect(invalid.isValid).toBe(false);
      expect(invalid.fieldErrors.title).toContain("at least 3 characters");
      expect(invalid.fieldErrors.description).toContain("at least 10 characters");
      expect(invalid.fieldErrors.organizerName).toContain("required");
      expect(invalid.fieldErrors.organizerPhone).toContain("min 7 digits");
      expect(invalid.fieldErrors.organizerEmail).toContain("valid organizer email");
    });
  });

  describe("3. Stage 2 — Date, Location & IANA Timezone Handling", () => {
    it("validates IANA timezones and converts local cutoff timestamps to UTC consistently", () => {
      expect(isValidIanaTimezone("America/Jamaica")).toBe(true);
      expect(isValidIanaTimezone("America/New_York")).toBe(true);
      expect(isValidIanaTimezone("Europe/London")).toBe(true);
      expect(isValidIanaTimezone("Invalid/Timezone_Name")).toBe(false);

      // 8:00 PM Jamaica time (UTC-5) on Nov 20, 2026 is 01:00 UTC on Nov 21, 2026
      const formatted = formatEventTimestampWithTimezone(
        "2026-11-20T20:00:00-05:00",
        "America/Jamaica"
      );
      expect(formatted.isValid).toBe(true);
      expect(formatted.ianaTimezone).toBe("America/Jamaica");
      expect(formatted.utcIso).toBe("2026-11-21T01:00:00.000Z");
      expect(formatted.localDisplay).toContain("Nov 20, 2026");
    });

    it("flags errors when end time precedes start time, venue is missing, or timezone is invalid", () => {
      const invalid = validateWizardDateLocation({
        ...sampleEvent,
        venueName: "",
        venueAddress: "abc",
        startTime: "21:00",
        endTime: "12:00",
        timezone: "Mars/Olympus_Mons",
      });
      expect(invalid.isValid).toBe(false);
      expect(invalid.fieldErrors.venueName).toBeDefined();
      expect(invalid.fieldErrors.venueAddress).toBeDefined();
      expect(invalid.fieldErrors.endTime).toContain("after the start time");
      expect(invalid.fieldErrors.timezone).toContain("Invalid IANA timezone");
    });
  });

  describe("4. Stage 3 — Tickets, Pricing, Quantity Limits & Sale Periods", () => {
    it("validates supported ticket tiers, prices, stock limits, and sale windows", () => {
      const valid = validateWizardTickets(
        sampleEvent.ticketTiers!,
        sampleEvent.packagingFeeUSD,
        sampleEvent.packagingFeeJMD
      );
      expect(valid.isValid).toBe(true);
      expect(valid.activeTiersCount).toBe(3);
      expect(valid.totalCapacity).toBe(385);
    });

    it("rejects negative prices, zero stock, maxPerCustomer > stock, and inverted sale dates", () => {
      const brokenTiers = [
        {
          id: "tier-bad",
          name: "VIP Pass",
          category: "VIP" as const,
          priceUSD: -10,
          priceJMD: 2000,
          availableStock: 10,
          maxPerCustomer: 25,
          saleStartIso: "2026-11-25T12:00",
          saleEndIso: "2026-11-10T12:00",
          isActive: true,
        },
      ];
      const result = validateWizardTickets(brokenTiers, -1, 100);
      expect(result.isValid).toBe(false);
      expect(result.fieldErrors.packagingFeeUSD).toContain("cannot be negative");
      expect(result.tierErrors["tier-bad"]).toBeDefined();
      expect(result.tierErrors["tier-bad"].join(" ")).toContain(
        "USD price must be 0 or greater"
      );
      expect(result.tierErrors["tier-bad"].join(" ")).toContain(
        "Max per order must be between 1 and 10"
      );
      expect(result.tierErrors["tier-bad"].join(" ")).toContain(
        "Sale start date must be on or before the sale end date"
      );
    });
  });

  describe("5. Stage 4 — Food Menu Configuration & Meal Customizations", () => {
    it("validates attached food menu items and customizable add-on groups when food ordering is enabled", () => {
      const valid = validateWizardFood(true, sampleMenu);
      expect(valid.isValid).toBe(true);

      const emptyWhenRequired = validateWizardFood(true, []);
      expect(emptyWhenRequired.isValid).toBe(false);
      expect(emptyWhenRequired.fieldErrors.menu).toContain(
        "Add at least one food or beverage item"
      );

      // When food ordering is disabled for admission-only events, empty menu is permitted
      const disabledFood = validateWizardFood(false, []);
      expect(disabledFood.isValid).toBe(true);
    });

    it("rejects customizable menu items that have no option groups", () => {
      const invalidCustomizable = validateWizardFood(true, [
        {
          ...sampleMenu[0],
          id: "bad-custom",
          pricingType: "customizable",
          customOptionGroups: [],
        },
      ]);
      expect(invalidCustomizable.isValid).toBe(false);
      expect(invalidCustomizable.itemErrors["bad-custom"][0]).toContain(
        "require at least one meal choice / add-on group"
      );
    });
  });

  describe("6. Image Uploads, Safe Filenames & Role Authorization", () => {
    it("sanitizes filenames and validates format, size, dimensions, and role permissions", () => {
      expect(sanitizeSafeUploadFilename("My Summer Flyer (Final)!.JPG")).toBe(
        "my_summer_flyer__final__.jpg"
      );

      const validUpload = validateWizardImageUpload(
        {
          name: "waterfront_poster.webp",
          sizeBytes: 1024 * 600,
          mimeType: "image/webp",
          dimensions: { width: 1200, height: 800 },
        },
        "org-kingston-youth",
        true,
        "flyers"
      );
      expect(validUpload.valid).toBe(true);
      expect(validUpload.storagePath).toMatch(
        /^tenants\/org-kingston-youth\/flyers\/\d+_[a-f0-9]+\.webp$/
      );

      // Unauthorized role blocked
      const unauthorized = validateWizardImageUpload(
        {
          name: "poster.jpg",
          sizeBytes: 1024 * 200,
          mimeType: "image/jpeg",
        },
        "org-kingston-youth",
        false,
        "flyers"
      );
      expect(unauthorized.valid).toBe(false);
      expect(unauthorized.error).toContain("Authorization required");

      // Path traversal blocked
      const traversal = validateWizardImageUpload(
        {
          name: "../../etc/shadow.png",
          sizeBytes: 1024 * 100,
          mimeType: "image/png",
        },
        "org-kingston-youth",
        true,
        "flyers"
      );
      expect(traversal.valid).toBe(false);
      expect(traversal.error).toContain("Unsafe filename rejected");

      // Disallowed format (SVG) blocked
      const svgUpload = validateWizardImageUpload(
        {
          name: "banner.svg",
          sizeBytes: 50000,
          mimeType: "image/svg+xml",
        },
        "org-kingston-youth",
        true,
        "flyers"
      );
      expect(svgUpload.valid).toBe(false);
      expect(svgUpload.error).toContain("Permitted formats: JPG, PNG, and WebP");
    });
  });

  describe("7. Stage 5 — Publish Readiness Checklist & Blocking Incomplete Events", () => {
    it("allows publishing only when all required checklist stages pass", () => {
      const readyReport = evaluateWizardPublishReadiness(
        sampleEvent,
        sampleEvent.ticketTiers!,
        sampleMenu
      );
      expect(readyReport.canPublish).toBe(true);
      expect(readyReport.blockingReasons).toHaveLength(0);
      expect(readyReport.stepStatus).toEqual({
        basic_details: "complete",
        date_location: "complete",
        tickets: "complete",
        food: "complete",
        review: "complete",
      });

      // Incomplete event (missing venue address and empty menu with food enabled)
      const incompleteReport = evaluateWizardPublishReadiness(
        { ...sampleEvent, venueAddress: "" },
        sampleEvent.ticketTiers!,
        []
      );
      expect(incompleteReport.canPublish).toBe(false);
      expect(incompleteReport.blockingReasons.length).toBeGreaterThanOrEqual(2);
      expect(incompleteReport.stepStatus.date_location).toBe("incomplete");
      expect(incompleteReport.stepStatus.food).toBe("incomplete");
      expect(incompleteReport.stepStatus.review).toBe("incomplete");
    });

    it("renders all 5 wizard stages and contextual preview cleanly via SSR", () => {
      const ownerPerms = computeHostRolePermissions("event_owner");
      const noop = () => {};

      for (const step of EVENT_WIZARD_STEPS) {
        const html = renderToStaticMarkup(
          React.createElement(EventCreationWizard, {
            eventDetails: sampleEvent,
            menu: sampleMenu,
            activeOrganizer: INITIAL_SAVED_STATE.organizers![0],
            permissions: ownerPerms,
            flyerWarningToast: null,
            preSaveConfirmFlyer: null,
            shareCopied: false,
            initialStep: step.id,
            onUpdateEventDetails: noop,
            onUpdateMenu: noop,
            onOpenUnpublishModal: noop,
            onOpenCancelEventModal: noop,
            onFlyerUpload: noop,
            onConfirmPreSavedFlyer: noop,
            onCancelPreSavedFlyer: noop,
            onRemoveFlyer: noop,
            onCopyShareUrl: noop,
            onSaveToast: noop,
          })
        );

        expect(html).toContain("event-wizard-progress-bar");
        expect(html).toContain(`wizard-step-${step.id}`);
        expect(html).toContain("event-wizard-contextual-preview");
        expect(html).toContain("wizard-save-draft-btn");
        expect(html).toContain("wizard-preview-btn");
      }
    });
  });
});
