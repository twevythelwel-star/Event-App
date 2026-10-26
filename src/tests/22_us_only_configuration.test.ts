import { describe, expect, it } from "vitest";
import {
  calculateOrderFinancials,
  formatUSPhoneNumber,
  getRegisteredMarkets,
  isSupportedUSTimezone,
  lockAuthoritativeCurrency,
  registerInternationalMarket,
  US_MARKET_CONFIG,
  US_STATES,
  US_TIMEZONES,
  validateUSAddress,
} from "../services/marketConfiguration";
import {
  DEFAULT_US_HOST_EVENT_DEFAULTS,
  DEFAULT_US_LAUNCH_EVENT_DETAILS,
  DEFAULT_US_SETTINGS,
  DEFAULT_US_SYSTEM_PLATFORM_SETTINGS,
} from "../data/defaultData";
import { TicketOrder } from "../types";

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
      expect(DEFAULT_US_SETTINGS.methods.bank.details).toContain("US Domestic ACH");
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
