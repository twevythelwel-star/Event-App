import { describe, expect, it } from "vitest";
import {
  EventDetails,
  HostCommunicationTemplates,
  HostEventDefaults,
  HostOperationalRules,
  HostPaymentPolicySettings,
  SystemPlatformSettings,
  TicketOrder,
} from "../types";
import {
  DEFAULT_ATTENDEE_ACCOUNT_SETTINGS,
  DEFAULT_HOST_COMMUNICATION_TEMPLATES,
  DEFAULT_HOST_EVENT_DEFAULTS,
  DEFAULT_HOST_OPERATIONAL_RULES,
  DEFAULT_HOST_PAYMENT_POLICIES,
  DEFAULT_SYSTEM_PLATFORM_SETTINGS,
} from "../data/defaultData";
import {
  evaluateAttendeeDisplayCurrencyConversion,
  evaluateHostPaymentEligibility,
  maskSecretKey,
  validateHistoricalOrderIntegrity,
} from "./testUtils";

describe("Phase 21: Settings That Need Special Attention", () => {
  // =========================================================================
  // SECTION 1: Event Host Settings Area - Event Defaults
  // =========================================================================
  describe("1. Event Host - Event Defaults", () => {
    it("configures defaults for currency, timezone, location, order deadline, instructions, and branding", () => {
      const defaults: HostEventDefaults = { ...DEFAULT_HOST_EVENT_DEFAULTS };

      expect(defaults.defaultCurrency).toBe("USD");

      expect(defaults.defaultTimezone).toBe("America/New_York");

      expect(defaults.defaultLocation).toContain("Miami, FL");

      // Default order deadline (hours before kickoff)
      expect(defaults.defaultOrderDeadlineHours).toBe(24);

      // Default ticket and pickup instructions
      expect(defaults.defaultPickupInstructions).toContain("Show digital ticket QR pass");

      // Default flyer and branding preferences
      expect(defaults.defaultFlyerAspectRatio).toBe("4:5");
      expect(defaults.defaultBrandTheme).toBe("eventbite_navy");
    });
  });

  // =========================================================================
  // SECTION 2: Event Host Settings - Payment Settings & Eligibility Gating
  // =========================================================================
  describe("2. Event Host - Payment Settings & Eligibility Gating", () => {
    it("configures cash, bank-transfer, verification rules, receipts, and refund policies", () => {
      const policies: HostPaymentPolicySettings = { ...DEFAULT_HOST_PAYMENT_POLICIES };

      expect(policies.enableCash).toBe(true);
      expect(policies.bankTransferInstructions).toContain("U.S. ACH");
      expect(policies.bankTransferInstructions).toContain("DEMO ONLY");
      expect(policies.requireSenderName).toBe(true);
      expect(policies.requireTransferReference).toBe(true);
      expect(policies.requireReceiptSnapshot).toBe(true);
      expect(policies.partialPaymentsEnabled).toBe(false);
      expect(policies.depositPercentage).toBe(50);
      expect(policies.autoEmailReceipt).toBe(true);
      expect(policies.autoSmsReceipt).toBe(true);
      expect(policies.refundPolicyDeadlineHours).toBe(48);
      expect(policies.refundPolicyNotes).toContain("Full refund available up to 48 hours");
    });

    it("strictly gates Zelle and Cash App to eligible US business entities only", () => {
      // Jamaican host (JM)
      const jmZelle = evaluateHostPaymentEligibility("JM", "zelle");
      expect(jmZelle.isEligible).toBe(false);
      expect(jmZelle.requirement).toContain("require a registered US business location");

      const jmCashApp = evaluateHostPaymentEligibility("JM", "cashapp");
      expect(jmCashApp.isEligible).toBe(false);

      // US host (US)
      const usZelle = evaluateHostPaymentEligibility("US", "zelle");
      expect(usZelle.isEligible).toBe(true);
      expect(usZelle.requirement).toContain("verified US entity");

      const usCashApp = evaluateHostPaymentEligibility("US", "cashapp");
      expect(usCashApp.isEligible).toBe(true);

      // Universal methods
      const jmCash = evaluateHostPaymentEligibility("JM", "cash");
      expect(jmCash.isEligible).toBe(true);

      const jmBank = evaluateHostPaymentEligibility("JM", "bank_transfer");
      expect(jmBank.isEligible).toBe(true);

      const jmLynk = evaluateHostPaymentEligibility("JM", "lynk");
      expect(jmLynk.isEligible).toBe(true);
    });
  });

  // =========================================================================
  // SECTION 3: Event Host Settings - Customer Communications
  // =========================================================================
  describe("3. Event Host - Customer Communications", () => {
    it("provides customizable tokenized templates for order confirmation, payment, reminders, and collection", () => {
      const comms: HostCommunicationTemplates = { ...DEFAULT_HOST_COMMUNICATION_TEMPLATES };

      // Token placeholders exist
      expect(comms.orderConfirmationTemplate).toContain("{customer_name}");
      expect(comms.orderConfirmationTemplate).toContain("{order_number}");

      expect(comms.paymentConfirmationTemplate).toContain("{total_amount}");
      expect(comms.paymentConfirmationTemplate).toContain("{order_number}");

      expect(comms.eventReminderTemplate).toContain("{venue_name}");

      expect(comms.collectionConfirmationTemplate).toContain("{order_number}");
      expect(comms.collectionConfirmationTemplate).toContain("{station_id}");
    });

    it("correctly interpolates tokens into customer communication messages", () => {
      const template = DEFAULT_HOST_COMMUNICATION_TEMPLATES.orderConfirmationTemplate;
      const tokens: Record<string, string> = {
        "{customer_name}": "Dwayne Johnson",
        "{order_number}": "ORD-2026-9999",
        "{ticket_code}": "TCK-9999",
        "{total_amount}": "JMD $3,600.00",
      };

      let rendered = template;
      for (const [key, value] of Object.entries(tokens)) {
        rendered = rendered.replace(new RegExp(key, "g"), value);
      }

      expect(rendered).toContain("Hi Dwayne Johnson!");
      expect(rendered).toContain("ORD-2026-9999");
      expect(rendered).toContain("TCK-9999");
      expect(rendered).toContain("JMD $3,600.00");
      expect(rendered).not.toContain("{customer_name}");
    });
  });

  // =========================================================================
  // SECTION 4: Event Host Settings - Event Operations & Historical Immutability
  // =========================================================================
  describe("4. Event Host - Event Operations & Inviolable Order Invariants", () => {
    it("configures staff stations, collection checkpoints, limits, and preparation buffers", () => {
      const ops: HostOperationalRules = { ...DEFAULT_HOST_OPERATIONAL_RULES };

      expect(ops.stations.length).toBeGreaterThanOrEqual(2);
      expect(ops.stations[0].name).toContain("Station A");
      expect(ops.stations[0].staffAssigned).toBe("Chef Marcus Johnson");
      expect(ops.maxEventCapacity).toBe(500);
      expect(ops.maxPerItemPortions).toBe(10);
      expect(ops.enforceAntiDuplicateRedemption).toBe(true);
      expect(ops.allowPartialCollection).toBe(true);
      expect(ops.foodPrepBufferPercent).toBe(10); // +10% kitchen safety buffer
    });

    it("calculates kitchen preparation buffer correctly with the operational buffer percentage", () => {
      const soldPortions = 150;
      const bufferPercent = DEFAULT_HOST_OPERATIONAL_RULES.foodPrepBufferPercent; // 10%
      const targetPrepQuantity = Math.ceil(soldPortions * (1 + bufferPercent / 100));

      expect(targetPrepQuantity).toBe(165); // 150 + 15 buffer
    });

    it("INVARIANT: Host changing event defaults NEVER silently mutates historical order snapshots", () => {
      // Historical order placed at JMD $3,500.00 with specific snapshot
      const historicalOrder: TicketOrder = {
        id: "ord_hist_001",
        orderNumber: "ORD-HIST-001",
        ticketCode: "TCK-HIST-001",
        secureTicketToken: "tok_valid_sig_hist_001",
        customerName: "Audrey Hepburn",
        email: "audrey@example.com",
        customerPhone: "+1 (876) 555-0144",
        currency: "JMD",
        items: [
          {
            menuItemId: "item_curry_goat",
            name: "Authentic Jamaican Curry Goat",
            category: "Mains",
            pricingType: "customizable",
            basePrice: 300000, // $3000.00 JMD
            selectedOptionsSummary: ["Extra Gravy"],
            optionsTotalExtra: 50000, // $500.00 JMD
            finalItemPrice: 350000, // $3500.00 JMD
            currency: "JMD",
            quantity: 1,
            collectedQuantity: 1,
            handoverStatus: "collected",
          },
        ],
        subtotal: 350000,
        packagingFee: 0,
        totalAmount: 350000,
        status: "paid",
        method: "bank",
        allHandedOver: true,
        createdAt: "2026-10-20T10:00:00Z",
      };

      // Host subsequently changes event defaults in dashboard:
      // Currency to USD, price changes, new pickup instructions
      const modifiedEventDetails: Partial<EventDetails> = {
        currency: "USD",
        venueAddress: "New Montego Bay Convention Center",
        pickupInstructions: "New Gates A and B only",
      };

      // Order snapshot must remain completely intact
      const verification = validateHistoricalOrderIntegrity(historicalOrder, modifiedEventDetails);
      expect(verification.isIntact).toBe(true);
      expect(verification.violations.length).toBe(0);

      // Verify individual historical fields are unaltered
      expect(historicalOrder.currency).toBe("JMD"); // Still JMD!
      expect(historicalOrder.totalAmount).toBe(350000); // Still 350,000 cents
      expect(historicalOrder.items[0].finalItemPrice).toBe(350000);
      expect(historicalOrder.items[0].collectedQuantity).toBe(1);
    });
  });

  // =========================================================================
  // SECTION 5: Attendee Settings & Invariants
  // =========================================================================
  describe("5. Attendee Settings & Display Currency Invariant", () => {
    it("configures contact details, verification, security, and display currency", () => {
      const attendeeSettings = { ...DEFAULT_ATTENDEE_ACCOUNT_SETTINGS };

      expect(attendeeSettings.fullName).toBe("Marcus Bailey");
      expect(attendeeSettings.phone).toBe("+1 (305) 555-4321");
      expect(attendeeSettings.email).toBe("marcus.bailey@islandevents.example");
      expect(attendeeSettings.isPhoneVerified).toBe(true);
      expect(attendeeSettings.isEmailVerified).toBe(true);
      expect(attendeeSettings.twoFactorEnabled).toBe(true);
      expect(attendeeSettings.preferredDisplayCurrency).toBe("USD");
      expect(attendeeSettings.activeSessionsCount).toBe(2);
    });

    it("INVARIANT: Preferred display currency does NOT change the currency or amount actually charged", () => {
      const orderJmd: TicketOrder = {
        id: "ord_att_001",
        orderNumber: "ORD-ATT-001",
        ticketCode: "TCK-ATT-001",
        secureTicketToken: "tok-att-001",
        customerName: "Neville Green",
        email: "neville@example.com",
        customerPhone: "+1 (876) 555-0812",
        currency: "JMD",
        items: [],
        subtotal: 200000, // $2,000.00 JMD (in cents)
        packagingFee: 0,
        totalAmount: 200000, // $2,000.00 JMD
        status: "paid",
        method: "cash",
        allHandedOver: false,
        createdAt: "2026-10-25T12:00:00Z",
      };

      // Attendee selects USD as preferred display currency
      const result = evaluateAttendeeDisplayCurrencyConversion(orderJmd, "USD", 158.0);

      // Actual charged currency and amount MUST remain JMD 200000 minor units
      expect(result.actualChargedCurrency).toBe("JMD");
      expect(result.actualChargedAmount).toBe(200000);

      // Display estimate is formatted for attendee guidance only
      expect(result.isConversionEstimateOnly).toBe(true);
      expect(result.displayFormattedEstimate).toContain("~USD $");
      // 2000 JMD / 158 ~= 12.66 USD
      expect(result.displayFormattedEstimate).toBe("~USD $12.66");

      // When preferred display currency matches charged currency
      const sameCurrencyResult = evaluateAttendeeDisplayCurrencyConversion(orderJmd, "JMD", 158.0);
      expect(sameCurrencyResult.actualChargedCurrency).toBe("JMD");
      expect(sameCurrencyResult.actualChargedAmount).toBe(200000);
      expect(sameCurrencyResult.isConversionEstimateOnly).toBe(false);
      expect(sameCurrencyResult.displayFormattedEstimate).toBe("~JMD $2000.00");
    });

    it("verifies attendee ticket retrieval lookup by code, order number, and phone number", () => {
      const mockOrders: TicketOrder[] = [
        {
          id: "ord_101",
          orderNumber: "ORD-2026-001",
          ticketCode: "TCK-789-XYZ",
          secureTicketToken: "tok-789-xyz",
          customerName: "Camille Smith",
          email: "camille@example.com",
          customerPhone: "+1 (876) 555-4321",
          currency: "JMD",
          items: [],
          subtotal: 150000,
          packagingFee: 0,
          totalAmount: 150000,
          status: "paid",
          method: "bank",
          allHandedOver: false,
          createdAt: "2026-10-25T10:00:00Z",
        },
      ];

      // Lookup by ticket code
      const findByCode = mockOrders.find(
        (o) => o.ticketCode.toLowerCase() === "tck-789-xyz".toLowerCase()
      );
      expect(findByCode).toBeDefined();
      expect(findByCode?.orderNumber).toBe("ORD-2026-001");

      // Lookup by order number
      const findByOrderNum = mockOrders.find(
        (o) => o.orderNumber.toLowerCase() === "ord-2026-001".toLowerCase()
      );
      expect(findByOrderNum).toBeDefined();

      // Lookup by phone digits
      const phoneDigits = "5554321";
      const findByPhone = mockOrders.find((o) =>
        o.customerPhone.replace(/\D/g, "").includes(phoneDigits)
      );
      expect(findByPhone).toBeDefined();
      expect(findByPhone?.customerName).toBe("Camille Smith");
    });
  });

  // =========================================================================
  // SECTION 6: System Administrator Settings & Secret Key Protection
  // =========================================================================
  describe("6. System Administrator Settings & Secret Key Protection", () => {
    it("configures platform branding, currencies, storage health, providers, and audit retention", () => {
      const sysSettings: SystemPlatformSettings = { ...DEFAULT_SYSTEM_PLATFORM_SETTINGS };

      expect(sysSettings.platformName).toBe("EventBite");
      expect(sysSettings.logoUrl).toBe("/brand/eventbite-wordmark.webp");
      expect(sysSettings.brandTheme).toBe("eventbite_navy");
      expect(sysSettings.supportedCurrencies).toEqual(["USD"]);
      expect(sysSettings.supportedPaymentMethods).toContain("cash");
      expect(sysSettings.supportedPaymentMethods).toContain("bank");

      // File storage health
      expect(sysSettings.fileStorageStatus).toBe("healthy");
      expect(sysSettings.storageBucket).toBe("eventbite-flyers-prod");
      expect(sysSettings.storageProvider).toBe("s3_compatible_r2");

      // Notification providers
      expect(sysSettings.notificationProviderStatus).toBe("operational");

      // Security requirements & timeouts
      expect(sysSettings.mandatory2FaForAdmins).toBe(true);
      expect(sysSettings.adminSessionTimeoutMinutes).toBe(30);
      expect(sysSettings.attendeeSessionTimeoutDays).toBe(30);

      // Audit retention policies
      expect(sysSettings.auditRetentionDays).toBe(365);
      expect(sysSettings.piiScrubDays).toBe(90);

      // Application operational settings
      expect(sysSettings.databasePoolMax).toBe(100);
      expect(sysSettings.queueWorkersCount).toBe(4);
    });

    it("INVARIANT: Secret keys must NEVER be exposed in plaintext through forms or readable logs", () => {
      const rawSecretKeys = [
        "sk_live_" + "51MzAbcDefGhiJklMnoPqrStuVwxYz123456",
        "AC_twilio_live_secret_token_99887766554433",
        "postmark_server_token_8877665544332211",
        "aws_secret_access_key_wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY",
      ];

      for (const secret of rawSecretKeys) {
        const masked = maskSecretKey(secret);

        // Masked version must NOT be equal to raw secret
        expect(masked).not.toBe(secret);

        // Masked version must contain masking asterisks
        expect(masked).toContain("••••••••");

        // The middle portion of the secret must NOT be visible
        const secretMiddle = secret.substring(6, secret.length - 4);
        expect(masked).not.toContain(secretMiddle);
      }
    });
  });
});
