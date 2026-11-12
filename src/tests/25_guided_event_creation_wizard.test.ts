import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { EventDetails, MenuItem } from "../types";
import { INITIAL_SAVED_STATE } from "../data/defaultData";
import { computeHostRolePermissions } from "../components/host/financialCalculations";
import { FoodServiceConfigSection } from "../components/host/FoodServiceConfigSection";
import { TicketPreview } from "../components/host/TicketPreview";
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
import {
  DEFAULT_OPERATIONS_STATIONS,
  canActorAssignWorkerRole,
  canStaffAssignmentPerform,
  evaluateStaffTimeAccess,
  getDefaultEventOperationsConfig,
  sanitizePermissionsForRole,
} from "../lib/eventOperations";
import {
  DEFAULT_ATTENDEE_REGISTRATION_QUESTIONS,
  buildGoogleCalendarUrl,
  buildIcsCalendarContent,
  computeFeedbackAggregate,
  filterQuestionsVisibleToWorkerRole,
  formatPrivacyRetentionDisclosure,
  getDefaultAttendeeExperienceConfig,
  validateRegistrationQuestionResponses,
} from "../lib/attendeeExperience";
import { AttendeeExperienceHub } from "../components/AttendeeExperienceHub";
import {
  EVENT_ARCHITECTURE_PILLARS,
  EVENT_STUDIO_MODULES,
  GROWTH_PLATFORM_CAPABILITIES,
  RECOMMENDATION_SEVERITY_META,
  evaluateEventIntelligenceAndReadiness,
} from "../lib/eventIntelligence";
import {
  LegalComplianceSection,
  MANDATORY_HOST_RESPONSIBILITY_POLICIES,
  MANDATORY_REGISTRATION_POLICIES,
} from "../components/host/LegalComplianceSection";

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
      expect(SUPPORTED_EVENT_CATEGORIES[0]).toBe("Food & Drink");
      expect(SUPPORTED_EVENT_TIMEZONES[0].id).toBe("America/New_York");
      expect(SUPPORTED_EVENT_TIMEZONES.some((t) => t.id === "America/Jamaica")).toBe(false);
      expect(SUPPORTED_EVENT_TIMEZONES.some((t) => t.id === "America/Phoenix")).toBe(true);
      expect(SUPPORTED_EVENT_TIMEZONES.some((t) => t.id === "America/Anchorage")).toBe(true);
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

    it("allows overnight events and flags conflicting same-day times, missing venues, or invalid timezones", () => {
      const overnight = validateWizardDateLocation({
        ...sampleEvent,
        startTime: "21:00",
        endTime: "02:00",
      });
      expect(overnight.fieldErrors.endTime).toBeUndefined();

      const invalid = validateWizardDateLocation({
        ...sampleEvent,
        venueName: "",
        venueAddress: "abc",
        endDateIso: sampleEvent.eventDateIso,
        startTime: "21:00",
        endTime: "12:00",
        timezone: "Mars/Olympus_Mons",
      });
      expect(invalid.isValid).toBe(false);
      expect(invalid.fieldErrors.venueName).toBeDefined();
      expect(invalid.fieldErrors.venueAddress).toBeDefined();
      expect(invalid.fieldErrors.endTime).toContain("when the end date is the same");
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

    it("validates minimum order size, admissions per ticket, and requires a visible active tier", () => {
      const baseTier = getDefaultEventTicketTiers()[0];
      const invalid = validateWizardTickets([
        { ...baseTier, minPerOrder: baseTier.maxPerCustomer + 1, admissionCount: 0 },
      ]);
      expect(invalid.isValid).toBe(false);
      expect(invalid.tierErrors[baseTier.id].join(" ")).toContain("Minimum per order");
      expect(invalid.tierErrors[baseTier.id].join(" ")).toContain("Admissions per ticket");

      const hidden = validateWizardTickets([
        { ...baseTier, visibility: "hidden", isActive: true },
      ]);
      expect(hidden.isValid).toBe(false);
      expect(hidden.activeTiersCount).toBe(0);
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

    it("allows food service to cross midnight only when the event does", () => {
      const config = {
        enabled: true,
        serviceMode: "both" as const,
        fulfillmentMode: "pickup" as const,
        foodServiceStartTime: "20:00",
        foodServiceEndTime: "02:00",
        orderingDeadlineIso: "2026-11-20T20:00",
      };
      const onOvernightEvent = renderToStaticMarkup(
        React.createElement(FoodServiceConfigSection, { config, currency: "USD", allowOvernight: true, onChange: () => {} })
      );
      const onSameDayEvent = renderToStaticMarkup(
        React.createElement(FoodServiceConfigSection, { config, currency: "USD", onChange: () => {} })
      );

      expect(onOvernightEvent).not.toContain("End time must be after the start time.");
      expect(onSameDayEvent).toContain("End time must be after the start time.");
    });
  });

  describe("Ticket designer live preview", () => {
    it("reflects edited event times, date range, admission privileges, and included food", () => {
      const ticket = {
        ...getDefaultEventTicketTiers()[0],
        admissionPrivileges: "VIP lounge access",
        includedFoodAndDrinks: "Welcome drink",
        benefits: ["Priority entry"],
      };
      const event = {
        ...sampleEvent,
        timeStr: "Stale display time",
        startTime: "15:30",
        endTime: "22:15",
        endDateIso: "2026-11-23",
        endDateStr: "Monday, November 23, 2026",
      };
      const markup = renderToStaticMarkup(React.createElement(TicketPreview, {
        event,
        ticket,
        currency: "USD",
        onDesignChange: () => {},
        onAccentChange: () => {},
        onBackgroundChange: () => {},
      }));

      expect(markup).toContain("15:30 – 22:15");
      expect(markup).not.toContain("Stale display time");
      expect(markup).toContain("Monday, November 23, 2026");
      expect(markup).toContain("VIP lounge access");
      expect(markup).toContain("Welcome drink");
      expect(markup).toContain("Priority entry");
      expect(markup).toContain("Preview-only QR placeholder; not an admission code");
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
        if (step.id === "tickets") {
          expect(html).toContain("Ticket design style");
          expect(html).toContain("QR PLACEHOLDER");
          expect(html).toContain("Secure code added at issue");
        }
        if (step.id === "review" || step.id === "date_location") {
          expect(html).toContain("event-operations-setup-section");
          expect(html).toContain("Entrance A");
          expect(html).toContain("VIP Entrance");
          expect(html).toContain("Food Station 1");
          expect(html).toContain("Food Station 2");
          expect(html).toContain("Payment Desk");
          expect(html).toContain("Authorized cash/payment verification");
        }
      }
    });
  });

  describe("8. Optional Event Operations & Staffing Setup (Stations, RBAC, Time-Limited Access, Schedules & Escalation)", () => {
    it("configures the canonical 6-station Event-Day Operations Plan matching station responsibilities", () => {
      expect(
        DEFAULT_OPERATIONS_STATIONS.map((s) => ({
          station: s.stationName,
          responsibility: s.assignedResponsibility,
        }))
      ).toEqual([
        { station: "Entrance A", responsibility: "Ticket verification" },
        { station: "VIP Entrance", responsibility: "VIP ticket verification" },
        { station: "Food Station 1", responsibility: "Main meals" },
        { station: "Food Station 2", responsibility: "Drinks and desserts" },
        { station: "Payment Desk", responsibility: "Authorized cash/payment verification" },
        { station: "Supervisor", responsibility: "Event-wide oversight" },
      ]);
    });

    it("enforces role RBAC boundaries, prohibits privilege escalation, and respects time-limited shift windows", () => {
      // Ticket checkers must never receive payment or worker-management permissions
      const checkerPerms = sanitizePermissionsForRole("ticket_checker", [
        "scan_qr_tickets",
        "record_cash_received",
        "verify_payments",
        "manage_permitted_worker_assignments",
      ]);
      expect(checkerPerms).toEqual(["scan_qr_tickets"]);

      // Supervisors cannot appoint other Supervisors (anti-escalation)
      expect(canActorAssignWorkerRole("supervisor", "ticket_checker").allowed).toBe(true);
      expect(canActorAssignWorkerRole("supervisor", "supervisor").allowed).toBe(false);
      expect(canActorAssignWorkerRole("event_owner", "supervisor").allowed).toBe(true);

      // Time-limited access enforcement: scheduled -> active -> expired -> revoked
      const baseAssignment = {
        status: "active" as const,
        shiftStartsAt: "2026-11-22T12:00:00Z",
        shiftExpiresAt: "2026-11-22T20:00:00Z",
        roleOnEvent: "payment_clerk" as const,
        individualPermissions: sanitizePermissionsForRole("payment_clerk"),
      };

      const beforeShift = evaluateStaffTimeAccess(baseAssignment, "2026-11-22T10:00:00Z");
      expect(beforeShift.isCurrentlyAuthorized).toBe(false);
      expect(beforeShift.effectiveStatus).toBe("scheduled");

      const duringShift = evaluateStaffTimeAccess(baseAssignment, "2026-11-22T14:00:00Z");
      expect(duringShift.isCurrentlyAuthorized).toBe(true);
      expect(duringShift.effectiveStatus).toBe("active");
      expect(canStaffAssignmentPerform(baseAssignment, "record_cash_received", "2026-11-22T14:00:00Z")).toBe(true);
      expect(canStaffAssignmentPerform(baseAssignment, "manage_permitted_worker_assignments", "2026-11-22T14:00:00Z")).toBe(false);

      const afterShift = evaluateStaffTimeAccess(baseAssignment, "2026-11-22T21:00:00Z");
      expect(afterShift.isCurrentlyAuthorized).toBe(false);
      expect(afterShift.effectiveStatus).toBe("expired");
      expect(canStaffAssignmentPerform(baseAssignment, "record_cash_received", "2026-11-22T21:00:00Z")).toBe(false);
    });

    it("initializes staff schedules, check-in tracking, activity logs, emergency contacts, and 3-tier escalation contacts", () => {
      const config = getDefaultEventOperationsConfig(sampleEvent);
      expect(config.stations).toHaveLength(6);
      expect(config.staffAssignments.length).toBeGreaterThanOrEqual(6);
      expect(config.schedules.length).toBeGreaterThanOrEqual(6);
      expect(config.activityLogs.length).toBeGreaterThanOrEqual(4);
      expect(config.emergencyContacts.length).toBeGreaterThanOrEqual(2);
      expect(config.escalationContacts.map((e) => e.tierLevel)).toEqual([1, 2, 3]);
    });
  });

  // =========================================================================
  // 25.9 ATTENDEE EXPERIENCE IMPROVEMENTS & CUSTOM REGISTRATION QUESTIONS
  // =========================================================================
  describe("25.9 Attendee Experience Improvements & Custom Registration Questions", () => {
    it("provides the 3 canonical registration questions (dietary, group, accessibility) and validates optional vs. required responses", () => {
      const config = getDefaultAttendeeExperienceConfig(sampleEvent);
      const prompts = config.registrationQuestions.map((q) => q.prompt);

      expect(prompts).toContain("Do you have any dietary restrictions?");
      expect(prompts).toContain("Are you attending with a group?");
      expect(prompts).toContain("Do you require accessibility assistance?");

      // By default, questions are optional so empty responses pass validation
      const optionalCheck = validateRegistrationQuestionResponses(
        config.registrationQuestions,
        {}
      );
      expect(optionalCheck.valid).toBe(true);
      expect(optionalCheck.missingQuestionIds).toHaveLength(0);

      // When host configures a question as required, missing answers block validation
      const withRequired = config.registrationQuestions.map((q) =>
        q.id === "q-dietary-restrictions" ? { ...q, required: true } : q
      );
      const missingRequiredCheck = validateRegistrationQuestionResponses(
        withRequired,
        {}
      );
      expect(missingRequiredCheck.valid).toBe(false);
      expect(missingRequiredCheck.missingQuestionIds).toContain(
        "q-dietary-restrictions"
      );

      const answeredRequiredCheck = validateRegistrationQuestionResponses(
        withRequired,
        { "q-dietary-restrictions": ["Halal"] }
      );
      expect(answeredRequiredCheck.valid).toBe(true);
    });

    it("enforces role-scoped privacy & data-retention controls and calendar/feedback helpers", () => {
      const config = getDefaultAttendeeExperienceConfig(sampleEvent);
      const disclosure = formatPrivacyRetentionDisclosure(
        config.privacyRetentionPolicy
      );
      expect(disclosure).toContain("30 days after the event concludes");
      expect(disclosure).toContain("encrypted at rest");

      // Kitchen workers only see dietary questions; ticket checkers only see group/accessibility; payment clerks see none
      const kitchenVisible = filterQuestionsVisibleToWorkerRole(
        DEFAULT_ATTENDEE_REGISTRATION_QUESTIONS,
        "food_worker",
        true
      );
      expect(kitchenVisible.map((q) => q.category)).toEqual(["dietary"]);

      const gateVisible = filterQuestionsVisibleToWorkerRole(
        DEFAULT_ATTENDEE_REGISTRATION_QUESTIONS,
        "ticket_checker",
        true
      );
      expect(gateVisible.map((q) => q.category)).toEqual([
        "group",
        "accessibility",
      ]);

      const cashierVisible = filterQuestionsVisibleToWorkerRole(
        DEFAULT_ATTENDEE_REGISTRATION_QUESTIONS,
        "payment_clerk",
        true
      );
      expect(cashierVisible).toHaveLength(0);

      // Calendar integration (.ics & Google Calendar)
      const gcalUrl = buildGoogleCalendarUrl(sampleEvent);
      expect(gcalUrl).toContain("calendar.google.com/calendar/render");
      expect(gcalUrl).toContain(encodeURIComponent(sampleEvent.title));

      const icsContent = buildIcsCalendarContent(sampleEvent);
      expect(icsContent).toContain("BEGIN:VCALENDAR");
      expect(icsContent).toContain(`SUMMARY:${sampleEvent.title}`);

      // Post-event feedback aggregation
      const stats = computeFeedbackAggregate(config.feedbackSubmissions);
      expect(stats.count).toBeGreaterThanOrEqual(2);
      expect(stats.averageOverall).toBeGreaterThanOrEqual(4.5);
    });

    it("renders AttendeeExperienceSetupSection in EventCreationWizard and AttendeeExperienceHub for guests", () => {
      const ownerPermissions = computeHostRolePermissions("event_owner");
      const noop = () => {};
      const wizardHtml = renderToStaticMarkup(
        React.createElement(EventCreationWizard, {
          eventDetails: sampleEvent,
          menu: sampleMenu,
          activeOrganizer: INITIAL_SAVED_STATE.organizers[0],
          permissions: ownerPermissions,
          flyerWarningToast: null,
          preSaveConfirmFlyer: null,
          shareCopied: false,
          initialStep: "review",
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

      expect(wizardHtml).toContain("btn-toggle-attendee-experience-setup");
      expect(wizardHtml).toContain("attendee-experience-setup-section");
      expect(wizardHtml).toContain("Do you have any dietary restrictions?");
      expect(wizardHtml).toContain("Are you attending with a group?");
      expect(wizardHtml).toContain("Do you require accessibility assistance?");
      expect(wizardHtml).toContain("Privacy &amp; Data-Retention Controls");

      const hubHtml = renderToStaticMarkup(
        React.createElement(AttendeeExperienceHub, {
          event: sampleEvent,
        })
      );
      expect(hubHtml).toContain("attendee-experience-hub");
      expect(hubHtml).toContain("attendee-announcements-banner");
      expect(hubHtml).toContain("btn-add-google-calendar");
      expect(hubHtml).toContain("btn-download-ics-calendar");
      expect(hubHtml).toContain("attendee-registration-questions-card");
      expect(hubHtml).toContain("attendee-faqs-section");
      expect(hubHtml).toContain("attendee-post-event-feedback-section");
    });
  });

  // =========================================================================
  // 25.10 EVENT INTELLIGENCE, AUTOMATED RECOMMENDATIONS & 10-MODULE STUDIO
  // =========================================================================
  describe("25.10 Event Intelligence, Automated Recommendations, Readiness Score & 10-Module Event Studio", () => {
    it("detects all 7 host configuration anomalies and categorizes Critical, Warning, and Suggestion recommendations", () => {
      const menuWithoutImages: MenuItem[] = [
        {
          ...sampleMenu[0],
          id: "item-unavailable",
          imageUrl: "",
          available: false,
          stockRemaining: 0,
        },
      ];

      const anomalousEvent: EventDetails = {
        ...sampleEvent,
        expectedAttendance: 200,
        eventDateIso: "2026-11-22",
        startTime: "16:00",
        endTime: "22:00", // 10 PM
        foodServiceEndTime: "20:00", // 8 PM
        ticketSalesEndIso: "2026-11-22T18:00", // After 16:00 start
        refundPolicyMode: undefined,
        refundPolicyNotes: "",
        hostCancellationPolicy: undefined,
        ticketTiers: [
          {
            id: "tier-ga",
            name: "General Admission",
            category: "General Admission",
            description: "Standard entry",
            priceUSD: 25,
            priceJMD: 3800,
            availableStock: 200,
            maxPerCustomer: 10,
            perks: ["Entry"],
            isActive: true,
          },
          {
            id: "tier-vip",
            name: "VIP Cabana Pass",
            category: "VIP",
            description: "VIP pass",
            priceUSD: 85,
            priceJMD: 13000,
            availableStock: 100, // 200 + 100 = 300 > 200 venue capacity
            maxPerCustomer: 6,
            perks: [], // Empty benefits!
            isActive: true,
          },
        ],
        packages: [
          {
            id: "pkg-family",
            name: "Harbour Family Feast",
            description: "Includes unavailable fish",
            priceUSD: 95,
            priceJMD: 14500,
            includedItems: [{ menuItemId: "item-unavailable", quantity: 2 }],
          },
        ],
      };

      const report = evaluateEventIntelligenceAndReadiness(
        anomalousEvent,
        menuWithoutImages
      );

      const messages = report.recommendations.map((r) => r.message);
      expect(messages).toContain(
        "Your venue capacity is 200, but your ticket inventory is 300."
      );
      expect(messages).toContain(
        "Your event ends at 10 PM, but food pickup ends at 8 PM."
      );
      expect(messages).toContain(
        "Your VIP ticket does not have any defined benefits."
      );
      expect(messages).toContain("Your food menu has no images.");
      expect(messages).toContain(
        "Your event does not have a cancellation policy."
      );
      expect(messages).toContain(
        "Your ticket sales end after the event begins."
      );
      expect(messages).toContain(
        "Your selected meal packages contain unavailable items."
      );

      // Verify severity definitions and counts
      expect(RECOMMENDATION_SEVERITY_META.critical.subtitle).toBe(
        "Must fix before publishing."
      );
      expect(RECOMMENDATION_SEVERITY_META.warning.subtitle).toBe(
        "Recommended correction."
      );
      expect(RECOMMENDATION_SEVERITY_META.suggestion.subtitle).toBe(
        "Optional improvement."
      );
      expect(report.criticalCount).toBeGreaterThanOrEqual(4);
      expect(report.warningCount).toBeGreaterThanOrEqual(3);
      expect(report.canPublish).toBe(false);
    });

    it("computes the 5-domain Event Readiness Score and defines the 10-Module Event Studio & 6 Architectural Pillars", () => {
      const cleanMenu: MenuItem[] = sampleMenu.map((item) => ({
        ...item,
        imageUrl: "/images/dishes/jm-fried-fish.jpg",
        available: true,
        stockRemaining: 50,
      }));
      const cleanEvent: EventDetails = {
        ...sampleEvent,
        expectedAttendance: 1000,
        startTime: "12:00",
        endTime: "22:00",
        foodServiceEndTime: "22:00",
        ticketSalesEndIso: "2026-11-22T11:30",
        flyerUrl: "/images/events/food-festival.jpg",
        refundPolicyMode: "manual_full_and_partial",
        refundPolicyNotes: "Full refunds up to 48h before doors open.",
        ticketTiers: getDefaultEventTicketTiers().map((t) => ({
          ...t,
          perks:
            t.perks && t.perks.length > 0
              ? t.perks
              : ["Express gate entry & welcome drink"],
        })),
      };

      const readyReport = evaluateEventIntelligenceAndReadiness(
        cleanEvent,
        cleanMenu
      );
      expect(readyReport.canPublish).toBe(true);
      expect(readyReport.scorePercent).toBe(100);
      expect(readyReport.domains.map((d) => d.label)).toEqual([
        "Event information",
        "Tickets and pricing",
        "Food menu",
        "Marketing materials",
        "Policies and logistics",
      ]);
      expect(
        readyReport.domains.every((d) => d.status === "Complete")
      ).toBe(true);

      // Verify 10-Module EventBite Event Studio navigation
      expect(EVENT_STUDIO_MODULES.map((m) => `${m.number}. ${m.title}`)).toEqual([
        "1. Event Identity",
        "2. Culture and Cuisine",
        "3. Date and Venue",
        "4. Tickets and Pricing",
        "5. Food Commerce",
        "6. People and Vendors",
        "7. Event Experience",
        "8. Marketing Studio",
        "9. Financial Setup",
        "10. Review and Publish",
      ]);

      // Verify 6 Architectural Separation Pillars & 12 Growth Capabilities
      expect(EVENT_ARCHITECTURE_PILLARS).toHaveLength(6);
      expect(GROWTH_PLATFORM_CAPABILITIES).toHaveLength(12);
    });
  });

  // =========================================================================
  // 25.11 FOOD SAFETY, ALCOHOL SERVICE, LEGAL COMPLIANCE & HOST VERIFICATION
  // =========================================================================
  describe("25.11 Food Safety, Alcohol Service, Legal Compliance & Host Identity Verification", () => {
    it("defines the 3 mandatory registration policy sections (A, B, C) and 6 individual Host Responsibility acknowledgements (D)", () => {
      expect(MANDATORY_REGISTRATION_POLICIES.map((p) => p.title)).toEqual([
        "A. Platform Terms and Conditions",
        "B. Food Safety Disclaimer",
        "C. Alcohol Policy",
      ]);

      // Food Safety Disclaimer covers all 10 required food safety domains
      const foodSafety = MANDATORY_REGISTRATION_POLICIES.find(
        (p) => p.id === "food_safety_disclaimer"
      );
      expect(foodSafety?.clauses).toHaveLength(10);
      expect(foodSafety?.clauses).toContain(
        "Proper cooking and holding temperatures."
      );
      expect(foodSafety?.clauses).toContain(
        "Allergen identification and disclosure."
      );

      // Alcohol Policy covers all 9 required alcohol compliance clauses
      const alcoholPolicy = MANDATORY_REGISTRATION_POLICIES.find(
        (p) => p.id === "alcohol_policy"
      );
      expect(alcoholPolicy?.clauses).toHaveLength(9);
      expect(
        alcoholPolicy?.clauses.some((c) =>
          c.includes("EventBite does not grant an alcohol licence")
        )
      ).toBe(true);

      // Host Responsibility uses 6 separate, individually selectable checkboxes
      expect(MANDATORY_HOST_RESPONSIBILITY_POLICIES).toHaveLength(6);

      const complianceHtml = renderToStaticMarkup(
        React.createElement(LegalComplianceSection, {
          eventDetails: sampleEvent,
          organizerId: "org-kingston-youth",
        })
      );
      expect(complianceHtml).toContain("legal-compliance-verification-section");
      expect(complianceHtml).toContain("A. Platform Terms and Conditions");
      expect(complianceHtml).toContain("B. Food Safety Disclaimer");
      expect(complianceHtml).toContain("C. Alcohol Policy");
      expect(complianceHtml).toContain(
        "D. Mandatory Individual Host Responsibility Acknowledgements"
      );
      expect(complianceHtml).toContain("auditable-policy-acceptance-ledger");
      expect(complianceHtml).toContain("2026.11-v1");
    });
  });
});
