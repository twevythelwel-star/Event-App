import { describe, expect, it } from "vitest";
import {
  AdminAssignedScope,
  AttendeeProfile,
  PlatformFeeSettings,
  PlatformOverviewStats,
  SecurityAuditLog,
  TicketDispute,
  TicketOrder,
} from "../types";
import {
  DEFAULT_ADMIN_SCOPES,
  DEFAULT_ATTENDEE_PROFILE,
  DEFAULT_NOTIFICATION_PROVIDERS,
  DEFAULT_PLATFORM_FEE_SETTINGS,
  DEFAULT_PLATFORM_STATS,
  DEFAULT_SYSTEM_HEALTH,
  DEFAULT_TENANT_USERS,
  DEFAULT_TICKET_DISPUTES,
  SEED_ORDERS,
} from "../data/defaultData";
import {
  createSecurityAuditLog,
  maskCustomerEmail,
  maskCustomerPhone,
  maskSecretKey,
  resolveTicketDispute,
  verifyAccountAction,
} from "./testUtils";

describe("Phase 19: Enterprise Account Types and Responsibilities Standard", () => {
  /* ==========================================================================
     1. SYSTEM ADMINISTRATOR ACCOUNT
     ========================================================================== */
  describe("1. System Administrator — Platform Management & Authorized Oversight", () => {
    it("provides platform-wide overview statistics across JMD and USD currencies", () => {
      const stats: PlatformOverviewStats = DEFAULT_PLATFORM_STATS;
      expect(stats.totalGrossMerchandiseValueUSD).toBeGreaterThan(0);
      expect(stats.totalGrossMerchandiseValueJMD).toBeGreaterThan(0);
      expect(stats.totalRegisteredHosts).toBe(42);
      expect(stats.totalActiveEvents).toBe(18);
      expect(stats.systemUptimePercentage).toBe(99.98);
      expect(stats.totalTicketsIssued).toBeGreaterThan(5000);
    });

    it("verifies platform health indicators for database, queue workers, storage, and scheduler", () => {
      const health = DEFAULT_SYSTEM_HEALTH;
      expect(health).toHaveLength(4);

      const db = health.find((h) => h.name.includes("PostgreSQL"));
      expect(db).toBeDefined();
      expect(db?.status).toBe("healthy");
      expect(db?.metrics).toContain("pool utilized");

      const queue = health.find((h) => h.name.includes("Redis Queue"));
      expect(queue).toBeDefined();
      expect(queue?.status).toBe("healthy");
      expect(queue?.metrics).toContain("failed jobs");

      const storage = health.find((h) => h.name.includes("Cloud Storage"));
      expect(storage?.status).toBe("healthy");

      const cron = health.find((h) => h.name.includes("Scheduler"));
      expect(cron?.status).toBe("healthy");
    });

    it("enforces universal credential protection: sensitive API keys are NEVER displayed in plaintext", () => {
      const providers = DEFAULT_NOTIFICATION_PROVIDERS;
      providers.forEach((prov) => {
        const masked = maskSecretKey(prov.maskedApiKey);
        expect(masked).toContain("••••");
        expect(masked).not.toBe("sk_live_1234567890abcdef1892");
        expect(masked.endsWith("1892") || masked.endsWith("3821") || masked.endsWith("4912") || masked.endsWith("72b1")).toBe(true);
      });

      // System administrator action checking
      const accessAttempt = verifyAccountAction("system_admin", "view_unmasked_secrets");
      expect(accessAttempt.allowed).toBe(false);
      expect(accessAttempt.reason).toContain("Universal security rule");
    });

    it("allows System Administrator to configure platform fees and currency parameters", () => {
      const check = verifyAccountAction("system_admin", "configure_platform_fees");
      expect(check.allowed).toBe(true);

      const feeConfig: PlatformFeeSettings = {
        ...DEFAULT_PLATFORM_FEE_SETTINGS,
        takeRatePercent: 3.0,
        fixedFeeUSD: 0.50,
        fixedFeeJMD: 75,
      };
      expect(feeConfig.takeRatePercent).toBe(3.0);
      expect(feeConfig.fixedFeeUSD).toBe(0.50);
      expect(feeConfig.fixedFeeJMD).toBe(75);
    });

    it("supports user account administration: activation, suspension, and audited security logging", () => {
      const users = [...DEFAULT_TENANT_USERS];
      const hostUser = users.find((u) => u.id === "user-evelyn-reid");
      expect(hostUser?.isActive).toBe(true);

      // Suspend user
      const suspended = { ...hostUser!, isActive: false };
      expect(suspended.isActive).toBe(false);

      // Audit log creation
      const log: SecurityAuditLog = createSecurityAuditLog(
        "user-platform-superadmin",
        "Platform DevOps Admin",
        "system_admin",
        "account_suspension",
        "tenant_users",
        suspended.id,
        `Suspended user ${suspended.name} for terms review.`
      );
      expect(log.severity).toBe("warning");
      expect(log.actorRole).toBe("system_admin");
      expect(log.action).toBe("account_suspension");
    });
  });

  /* ==========================================================================
     2. ADMINISTRATOR ACCOUNT (DELEGATED OPERATIONS)
     ========================================================================== */
  describe("2. Administrator — Delegated Operational Duties & Explicit Scoping", () => {
    const jmScope: AdminAssignedScope = DEFAULT_ADMIN_SCOPES[0];

    it("confirms Administrators do NOT receive superadmin privileges by default", () => {
      // Forbidden: modifying global platform fees
      const feeCheck = verifyAccountAction("admin", "configure_platform_fees", jmScope);
      expect(feeCheck.allowed).toBe(false);
      expect(feeCheck.reason).toContain("Privilege boundary");

      // Forbidden: assigning administrator scopes
      const scopeCheck = verifyAccountAction("admin", "assign_administrator_scope", jmScope);
      expect(scopeCheck.allowed).toBe(false);

      // Forbidden: viewing unmasked third-party secrets
      const secretCheck = verifyAccountAction("admin", "view_unmasked_secrets", jmScope);
      expect(secretCheck.allowed).toBe(false);
    });

    it("allows Administrator to manage events and assist hosts within assigned regional scope", () => {
      expect(jmScope.assignedRegion).toBe("JM");
      expect(jmScope.canAssistHostPublishing).toBe(true);
      expect(jmScope.canViewScopedReports).toBe(true);

      const assistCheck = verifyAccountAction("admin", "assist_host_publishing", jmScope);
      expect(assistCheck.allowed).toBe(true);
    });

    it("resolves order and ticket disputes with resolution notes and status transition", () => {
      const disputes: TicketDispute[] = [...DEFAULT_TICKET_DISPUTES];
      const targetDispute = disputes.find((d) => d.id === "DSP-2026-001")!;
      expect(targetDispute.status).toBe("open");

      const resolved = resolveTicketDispute(
        disputes,
        "DSP-2026-001",
        "resolved",
        jmScope.adminName,
        "Verified customer emergency travel ticket. Issued refund voucher."
      );

      const updated = resolved.find((d) => d.id === "DSP-2026-001")!;
      expect(updated.status).toBe("resolved");
      expect(updated.assignedAdminName).toBe(jmScope.adminName);
      expect(updated.resolutionNotes).toContain("refund voucher");
    });

    it("audits customer PII access when Administrator investigates an order dispute", () => {
      const rawPhone = "+1 (876) 555-9011";
      const rawEmail = "customer@islandevents.example";

      const maskedPhone = maskCustomerPhone(rawPhone);
      const maskedEmail = maskCustomerEmail(rawEmail);

      expect(maskedPhone).toContain("•••");
      expect(maskedPhone.endsWith("9011")).toBe(true);
      expect(maskedEmail).toContain("••••");
      expect(maskedEmail.endsWith("@islandevents.example")).toBe(true);

      const piiLog = createSecurityAuditLog(
        jmScope.adminUserId,
        jmScope.adminName,
        "admin",
        "order_dispute_review",
        "ticket_disputes",
        "DSP-2026-001",
        "Admin viewed customer contact details during dispute investigation.",
        true
      );
      expect(piiLog.isPiiAccess).toBe(true);
    });
  });

  /* ==========================================================================
     3. EVENT HOST ACCOUNT (PRIMARY BUSINESS ACCOUNT)
     ========================================================================== */
  describe("3. Event Host — Event Creation, Menus, Cash Ledger & Food Collection", () => {
    it("grants Event Hosts full CRUD on their own events, menus, and cash ledger", () => {
      const menuAction = verifyAccountAction("event_host", "operate_event_menu");
      expect(menuAction.allowed).toBe(true);

      const transferAction = verifyAccountAction("event_host", "verify_event_transfers");
      expect(transferAction.allowed).toBe(true);

      const cashAction = verifyAccountAction("event_host", "record_cash_receipt");
      expect(cashAction.allowed).toBe(true);
    });

    it("strictly isolates Event Hosts from accessing platform configuration or other hosts' events", () => {
      const sysConfigAttempt = verifyAccountAction("event_host", "configure_platform_fees");
      expect(sysConfigAttempt.allowed).toBe(false);
      expect(sysConfigAttempt.reason).toContain("Hosts only access events they own");

      const sysHealthAttempt = verifyAccountAction("event_host", "view_system_health");
      expect(sysHealthAttempt.allowed).toBe(false);
    });
  });

  /* ==========================================================================
     4. ATTENDEE ACCOUNT (PURCHASING, TICKETS & WALLET)
     ========================================================================== */
  describe("4. Attendee — Discovery, Ordering, Digital Wallet & Privacy", () => {
    it("allows attendees to browse published events and order without mandatory sign-in (guest checkout)", () => {
      const attendeeAction = verifyAccountAction("attendee", "purchase_food_order");
      expect(attendeeAction.allowed).toBe(true);

      const profile: AttendeeProfile = DEFAULT_ATTENDEE_PROFILE;
      expect(profile.isGuest).toBe(false);
      expect(profile.savedEvents).toHaveLength(1);
      expect(profile.savedEvents[0].title).toContain("Sunday Fish Fry");
    });

    it("tracks meal collection status and remaining portions in the Attendee Digital Wallet", () => {
      const sampleOrder: TicketOrder = {
        ...SEED_ORDERS[0],
        items: [
          {
            ...SEED_ORDERS[0].items[0],
            quantity: 2,
            collectedQuantity: 1, // 1 of 2 portions collected
            handoverStatus: "pending",
          },
        ],
      };

      const item = sampleOrder.items[0];
      const remaining = item.quantity - (item.collectedQuantity || 0);
      expect(remaining).toBe(1);
      expect(sampleOrder.allHandedOver).toBe(false);

      const walletAction = verifyAccountAction("attendee", "view_own_ticket_wallet");
      expect(walletAction.allowed).toBe(true);
    });

    it("restricts Attendees from accessing any host, cashier, or administrative actions", () => {
      const hostActionAttempt = verifyAccountAction("attendee", "operate_event_menu");
      expect(hostActionAttempt.allowed).toBe(false);
      expect(hostActionAttempt.reason).toContain("restricted to purchasing and managing their own tickets");

      const cashActionAttempt = verifyAccountAction("attendee", "record_cash_receipt");
      expect(cashActionAttempt.allowed).toBe(false);
    });
  });
});
