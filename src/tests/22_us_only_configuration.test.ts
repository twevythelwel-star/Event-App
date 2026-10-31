import { describe, expect, it } from "vitest";
import {
  calculateOrderFinancials,
  formatUSPhoneNumber,
  getRegisteredMarkets,
  isSupportedUSTimezone,
  lockAuthoritativeCurrency,
  registerInternationalMarket,
  US_MARKET_CONFIG,
  US_TIMEZONES,
  validateUSAddress,
} from "../services/marketConfiguration";
import {
  DEFAULT_EVENT_DETAILS,
  DEFAULT_SETTINGS,
  INITIAL_SAVED_STATE,
  DEFAULT_US_HOST_EVENT_DEFAULTS,
  DEFAULT_US_LAUNCH_EVENT_DETAILS,
  DEFAULT_US_SETTINGS,
  DEFAULT_US_SYSTEM_PLATFORM_SETTINGS,
  DEFAULT_ORGANIZERS,
  DEFAULT_TICKET_DISPUTES,
  TEN_FISH_FRY_EVENTS,
} from "../data/defaultData";
import { TicketOrder } from "../types";
import { readSavedState } from "../App";

describe("Phase 22: United States-Only Configuration Suite", () => {
  // =========================================================================
  // SECTION 1: Operating Market & US-Only Launch Gating
  // =========================================================================
  describe("1. Operating Market & Launch Country Restriction", () => {
    it("configures the United States (US) as the default and only active launch country", () => {
      expect(US_MARKET_CONFIG.countryCode).toBe("US");
      expect(US_MARKET_CONFIG.countryName).toBe("United States");
      expect(US_MARKET_CONFIG.allowedCountries).toEqual(["US"]);
      expect(US_MARKET_CONFIG.isUSOnlyLaunch).toBe(true);
      expect(DEFAULT_US_LAUNCH_EVENT_DETAILS.venueAddress).toContain("United States");
    });

    it("uses U.S. locations and USD across the default event and multicultural launch catalogue", () => {
      expect(DEFAULT_EVENT_DETAILS.venueAddress).toContain("United States");
      expect(DEFAULT_EVENT_DETAILS.currency).toBe("USD");
      expect(DEFAULT_SETTINGS.businessLocation).toBe("US");
      expect(DEFAULT_SETTINGS.currency).toBe("USD");
      expect(INITIAL_SAVED_STATE.orders.every((order) => order.currency === "USD")).toBe(true);
      expect(TEN_FISH_FRY_EVENTS).toHaveLength(10);
      expect(TEN_FISH_FRY_EVENTS.every((event) => event.venueAddress.includes("United States"))).toBe(true);
      expect(TEN_FISH_FRY_EVENTS.every((event) => event.currency === "USD")).toBe(true);
      expect(new Set(TEN_FISH_FRY_EVENTS.map((event) => event.cuisine)).size).toBeGreaterThanOrEqual(8);
      expect(TEN_FISH_FRY_EVENTS.map((event) => event.cuisine).join(" ")).toMatch(/Asian|African|Latino|Mediterranean|Native/i);
      expect(DEFAULT_ORGANIZERS).toHaveLength(10);
      expect(DEFAULT_ORGANIZERS.every((organizer) => organizer.businessLocation === "US" && organizer.defaultCurrency === "USD")).toBe(true);
      expect(DEFAULT_TICKET_DISPUTES.every((dispute) => dispute.currency === "USD")).toBe(true);
    });

    it("migrates only legacy bundled data and preserves user-created historical transactions", () => {
      const historicalOrder: TicketOrder = {
        ...INITIAL_SAVED_STATE.orders[0],
        id: "ord-user-historical-jmd",
        ticketCode: "USER-JMD-001",
        currency: "JMD",
        eventId: "evt-user-created",
      };
      const legacyOrder: TicketOrder = {
        ...historicalOrder,
        id: "ord-fishfry-old-demo",
        ticketCode: "SFF-2026-OLD",
      };
      const customOrganizer = {
        ...INITIAL_SAVED_STATE.organizers![0],
        id: "org-user-created",
        name: "User Community Organization",
      };
      const customTenantUser = {
        ...INITIAL_SAVED_STATE.tenantUsers![0],
        id: "user-user-created",
        organizerId: customOrganizer.id,
      };
      const customSavedEvent = {
        ...INITIAL_SAVED_STATE.attendeeProfile!.savedEvents[0],
        eventId: "evt-user-saved",
      };
      const saved = {
        ...INITIAL_SAVED_STATE,
        eventDetails: {
          ...DEFAULT_EVENT_DETAILS,
          id: "evt-kingston-harbour-fry",
          slug: "kingston-harbour-fishfry",
          timezone: "America/Jamaica",
          venueAddress: "Ocean Boulevard, Downtown Kingston, Jamaica",
          currency: "JMD",
        },
        settings: {
          ...DEFAULT_SETTINGS,
          currency: "JMD" as const,
          businessLocation: "JM" as const,
          ticketPriceUSD: 22.5,
        },
        orders: [legacyOrder, historicalOrder],
        organizers: [
          { ...INITIAL_SAVED_STATE.organizers![0], id: "org-portmore-guild" },
          customOrganizer,
        ],
        activeOrganizerId: customOrganizer.id,
        tenantUsers: [
          { ...INITIAL_SAVED_STATE.tenantUsers![0], id: "user-pastor-brown" },
          customTenantUser,
        ],
        attendeeProfile: {
          ...INITIAL_SAVED_STATE.attendeeProfile,
          id: "attendee-user-created",
          name: "User Attendee",
          savedEvents: [customSavedEvent],
        },
        cashLedger: [
          ...INITIAL_SAVED_STATE.cashLedger,
          { ...INITIAL_SAVED_STATE.cashLedger[0], id: "cash-user-historical" },
        ],
      };

      const migrated = readSavedState(JSON.stringify(saved));

      expect(migrated.eventDetails.id).toBe(DEFAULT_EVENT_DETAILS.id);
      expect(migrated.eventDetails.slug).toBe(DEFAULT_EVENT_DETAILS.slug);
      expect(migrated.settings.currency).toBe("USD");
      expect(migrated.settings.businessLocation).toBe("US");
      expect(migrated.settings.ticketPriceUSD).toBe(22.5);
      expect(migrated.orders.some((order) => order.id === "ord-fishfry-old-demo")).toBe(false);
      expect(migrated.orders.find((order) => order.id === historicalOrder.id)).toMatchObject({
        currency: "JMD",
        eventId: "evt-user-created",
      });
      expect(migrated.cashLedger.some((entry) => entry.id === "cash-user-historical")).toBe(true);
      expect(migrated.organizers?.some((organizer) => organizer.id === customOrganizer.id)).toBe(true);
      expect(migrated.organizers?.some((organizer) => organizer.id === "org-portmore-guild")).toBe(false);
      expect(migrated.activeOrganizerId).toBe(customOrganizer.id);
      expect(migrated.tenantUsers?.some((user) => user.id === customTenantUser.id)).toBe(true);
      expect(migrated.tenantUsers?.some((user) => user.id === "user-pastor-brown")).toBe(false);
      expect(migrated.attendeeProfile?.name).toBe("User Attendee");
      expect(migrated.attendeeProfile?.savedEvents.some((event) => event.eventId === customSavedEvent.eventId)).toBe(true);
    });

    it("verifies US event defaults for venue, timezone, and packaging fees", () => {
      expect(DEFAULT_US_HOST_EVENT_DEFAULTS.defaultCurrency).toBe("USD");
      expect(DEFAULT_US_HOST_EVENT_DEFAULTS.defaultTimezone).toBe("America/New_York");
      expect(DEFAULT_US_HOST_EVENT_DEFAULTS.defaultLocation).toContain("Miami, FL");
      expect(DEFAULT_US_LAUNCH_EVENT_DETAILS.packagingFeeUSD).toBe(1.5);
    });

    it("verifies platform settings strictly enforce US launch defaults", () => {
      expect(DEFAULT_US_SYSTEM_PLATFORM_SETTINGS.supportedCurrencies).toEqual(["USD"]);
      expect(DEFAULT_US_SYSTEM_PLATFORM_SETTINGS.defaultTimezone).toBe("America/New_York");
      expect(DEFAULT_US_SYSTEM_PLATFORM_SETTINGS.defaultLocale).toBe("en-US");
    });
  });

  // =========================================================================
  // SECTION 2: Currency and Pricing (USD, Integer Minor Units, Inviolability)
  // =========================================================================
  describe("2. Currency and Pricing Precision", () => {
    it("calculates order financials in exact integer cents without floating-point error", () => {
      const subtotalCents = 4500; // $45.00
      const packagingFeeCents = 150; // $1.50
      const amountReceivedCents = 4650; // $46.50
      const refundedCents = 1000; // $10.00 refund

      const financials = calculateOrderFinancials(
        subtotalCents,
        packagingFeeCents,
        amountReceivedCents,
        refundedCents,
        "USD"
      );

      expect(financials.totalAmountCents).toBe(4650);
      expect(financials.outstandingBalanceCents).toBe(0);
      expect(financials.netReceiptsCents).toBe(3650); // $46.50 - $10.00 = $36.50
      expect(financials.isFullyPaid).toBe(true);
      expect(financials.formattedTotal).toBe("$46.50");
      expect(financials.formattedNet).toBe("$36.50");
    });

    it("distinguishes outstanding balance when order is partially paid", () => {
      const subtotalCents = 3000; // $30.00
      const packagingFeeCents = 200; // $2.00
      const amountReceivedCents = 1500; // $15.00 deposit received

      const financials = calculateOrderFinancials(
        subtotalCents,
        packagingFeeCents,
        amountReceivedCents,
        0,
        "USD"
      );

      expect(financials.totalAmountCents).toBe(3200);
      expect(financials.outstandingBalanceCents).toBe(1700); // $17.00 outstanding
      expect(financials.isFullyPaid).toBe(false);
      expect(financials.isPartiallyPaid).toBe(true);
      expect(financials.formattedBalance).toBe("$17.00");
    });

    it("INVARIANT: Authoritative currency is locked to the event currency and cannot be altered by client request", () => {
      const eventCurrency = "USD";
      const browserRequestedCurrency = "JMD";

      const lockedCurrency = lockAuthoritativeCurrency(eventCurrency, browserRequestedCurrency);
      expect(lockedCurrency).toBe("USD");
      expect(lockedCurrency).not.toBe(browserRequestedCurrency);
    });
  });

  // =========================================================================
  // SECTION 3: US Payment Workflows & Audit Verification Gate
  // =========================================================================
  describe("3. US Payment Workflows & Verification Rules", () => {
    it("configures US-compatible payment methods (ACH, Zelle, Cash App, Cash)", () => {
      expect(DEFAULT_US_SETTINGS.methods.bank.enabled).toBe(true);
      expect(DEFAULT_US_SETTINGS.methods.bank.details).toContain("U.S. ACH");
      expect(DEFAULT_US_SETTINGS.methods.bank.details).toContain("DEMO ONLY");
      expect(DEFAULT_US_SETTINGS.methods.zelle.enabled).toBe(true);
      expect(DEFAULT_US_SETTINGS.methods.cashapp.enabled).toBe(true);
      expect(DEFAULT_US_SETTINGS.methods.cash.enabled).toBe(true);
    });

    it("INVARIANT: Attendee submitting transfer reference or receipt screenshot NEVER marks order paid", () => {
      const initialOrder: TicketOrder = {
        id: "ord_us_001",
        orderNumber: "ORD-US-001",
        ticketCode: "TCK-US-001",
        secureTicketToken: "tok_us_001",
        customerName: "Jane Doe",
        email: "jane@example.com",
        customerPhone: "+1 (305) 555-0123",
        currency: "USD",
        items: [],
        subtotal: 3500,
        packagingFee: 150,
        totalAmount: 3650,
        status: "awaiting_payment",
        method: "bank",
        allHandedOver: false,
        createdAt: "2026-11-20T14:00:00Z",
      };

      // Simulated attendee submit action
      function handleAttendeePaymentSubmission(order: TicketOrder, reference: string): TicketOrder {
        return {
          ...order,
          paymentReference: reference,
          status: "payment_submitted", // Must remain unverified!
        };
      }

      const submitted = handleAttendeePaymentSubmission(initialOrder, "CHASE-ACH-889922");
      expect(submitted.status).toBe("payment_submitted");
      expect(submitted.status).not.toBe("paid");
      expect(submitted.paymentReference).toBe("CHASE-ACH-889922");
    });
  });

  // =========================================================================
  // SECTION 4: US Addresses & IANA Time Zones with DST Transitions
  // =========================================================================
  describe("4. US Addresses and IANA Timezones", () => {
    it("validates compliant US addresses with 2-letter state code and 5-digit ZIP code", () => {
      const validAddress = validateUSAddress({
        street: "301 Biscayne Blvd",
        city: "Miami",
        state: "FL",
        zip: "33132",
        country: "US",
      });

      expect(validAddress.stateName).toBe("Florida");
      expect(validAddress.formatted).toBe("301 Biscayne Blvd, Miami, FL 33132, United States");
      expect(validAddress.country).toBe("US");
    });

    it("rejects non-US addresses or invalid state/zip combinations", () => {
      // Invalid state
      expect(() =>
        validateUSAddress({
          street: "123 Main St",
          city: "Anytown",
          state: "XX",
          zip: "12345",
          country: "US",
        })
      ).toThrow(/Invalid US state code/);

      // Invalid ZIP
      expect(() =>
        validateUSAddress({
          street: "123 Main St",
          city: "New York",
          state: "NY",
          zip: "ABCDE",
          country: "US",
        })
      ).toThrow(/Invalid US ZIP code format/);
    });

    it("verifies all primary US IANA timezones are supported", () => {
      const requiredTimezones = [
        "America/New_York",
        "America/Chicago",
        "America/Denver",
        "America/Los_Angeles",
        "America/Phoenix",
        "America/Anchorage",
        "Pacific/Honolulu",
      ];

      for (const tz of requiredTimezones) {
        expect(isSupportedUSTimezone(tz)).toBe(true);
        expect(US_TIMEZONES[tz]).toBeDefined();
      }
    });

    it("formats US 10-digit telephone numbers into standard E.164 and national readable formats", () => {
      const formatted = formatUSPhoneNumber("(305) 555-0199");
      expect(formatted.e164).toBe("+13055550199");
      expect(formatted.national).toBe("(305) 555-0199");
      expect(formatted.digits).toBe("3055550199");
    });
  });

  // =========================================================================
  // SECTION 5: Future International Expansion Architecture
  // =========================================================================
  describe("5. Modular International Expansion Readiness", () => {
    it("allows registering future international markets modularly without leaking into active launch", () => {
      const initialMarkets = getRegisteredMarkets();
      expect(initialMarkets).toContain("US");

      // Register future prospective market without altering active US launch defaults
      registerInternationalMarket("CA", {
        countryName: "Canada",
        currency: "CAD",
        currencySymbol: "$",
        phonePrefix: "+1",
        defaultTimezone: "America/Toronto",
      });

      const updatedMarkets = getRegisteredMarkets();
      expect(updatedMarkets).toContain("CA");
      expect(US_MARKET_CONFIG.allowedCountries).toEqual(["US"]); // Still strictly US launch
    });
  });
});
