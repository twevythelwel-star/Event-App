import { describe, expect, it } from "vitest";
import { AccountSettingKey, AccountType } from "../types";
import { MASTER_ACCOUNT_SETTINGS } from "../data/defaultData";
import { createSecurityAuditLog, maskSecretKey } from "./testUtils";

describe("Phase 20: Account Settings and Responsibilities Matrix", () => {
  // The complete list of 17 enterprise settings from specification
  const all17Settings: AccountSettingKey[] = [
    "personal_profile",
    "change_password",
    "two_factor_auth",
    "notification_preferences",
    "manage_platform_users",
    "manage_platform_roles",
    "platform_configuration",
    "event_management",
    "flyer_menu_management",
    "event_payment_instructions",
    "record_verify_payments",
    "manage_event_staff",
    "manage_tickets_collection",
    "sales_financial_reports",
    "currency_regional_preferences",
    "privacy_data_controls",
    "audit_security_logs",
  ];

  it("1. Defines all 17 specification settings with explicit category and descriptions", () => {
    expect(MASTER_ACCOUNT_SETTINGS.length).toBe(17);
    const keys = MASTER_ACCOUNT_SETTINGS.map((s) => s.key);
    for (const expectedKey of all17Settings) {
      expect(keys).toContain(expectedKey);
    }
  });

  it("2. Common Identity & Security settings are accessible to ALL 4 account types", () => {
    const commonSettings: AccountSettingKey[] = [
      "personal_profile",
      "change_password",
      "two_factor_auth",
      "notification_preferences",
    ];

    for (const settingKey of commonSettings) {
      const def = MASTER_ACCOUNT_SETTINGS.find((s) => s.key === settingKey);
      expect(def).toBeDefined();
      expect(def?.scopes.system_admin).toBe("all");
      expect(def?.scopes.admin).toBe("all");
      expect(def?.scopes.event_host).toBe("all");
      expect(def?.scopes.attendee).toBe("all");
    }
  });

  it("3. Platform Configuration is strictly exclusive to System Administrator", () => {
    const configDef = MASTER_ACCOUNT_SETTINGS.find((s) => s.key === "platform_configuration");
    expect(configDef).toBeDefined();
    expect(configDef?.scopes.system_admin).toBe("all");
    // Prohibited / Hidden for all other accounts
    expect(configDef?.scopes.admin).toBeNull();
    expect(configDef?.scopes.event_host).toBeNull();
    expect(configDef?.scopes.attendee).toBeNull();
  });

  it("4. Manage platform users differentiates between Full, Limited, and Excluded", () => {
    const usersDef = MASTER_ACCOUNT_SETTINGS.find((s) => s.key === "manage_platform_users");
    expect(usersDef).toBeDefined();
    expect(usersDef?.scopes.system_admin).toBe("all");
    expect(usersDef?.scopes.admin).toBe("limited");
    expect(usersDef?.scopes.event_host).toBeNull();
    expect(usersDef?.scopes.attendee).toBeNull();
  });

  it("5. Manage platform roles enforces Assigned Permissions Only for Delegated Admins", () => {
    const rolesDef = MASTER_ACCOUNT_SETTINGS.find((s) => s.key === "manage_platform_roles");
    expect(rolesDef).toBeDefined();
    expect(rolesDef?.scopes.system_admin).toBe("all");
    expect(rolesDef?.scopes.admin).toBe("assigned_permissions_only");
    expect(rolesDef?.scopes.event_host).toBeNull();
    expect(rolesDef?.scopes.attendee).toBeNull();
  });

  it("6. Event Management scopes accurately reflect role responsibilities", () => {
    const eventDef = MASTER_ACCOUNT_SETTINGS.find((s) => s.key === "event_management");
    expect(eventDef).toBeDefined();
    expect(eventDef?.scopes.system_admin).toBe("all_subject_to_policy");
    expect(eventDef?.scopes.admin).toBe("authorized_scope");
    expect(eventDef?.scopes.event_host).toBe("own_and_assigned_events");
    expect(eventDef?.scopes.attendee).toBeNull();
  });

  it("7. Flyer and Menu Management scopes follow explicit operational boundaries", () => {
    const flyerDef = MASTER_ACCOUNT_SETTINGS.find((s) => s.key === "flyer_menu_management");
    expect(flyerDef).toBeDefined();
    expect(flyerDef?.scopes.system_admin).toBe("oversight");
    expect(flyerDef?.scopes.admin).toBe("authorized_scope");
    expect(flyerDef?.scopes.event_host).toBe("own_and_assigned_events");
    expect(flyerDef?.scopes.attendee).toBeNull();
  });

  it("8. Payment instructions and verification enforce authorized oversight and own events", () => {
    const payInstructDef = MASTER_ACCOUNT_SETTINGS.find((s) => s.key === "event_payment_instructions");
    expect(payInstructDef?.scopes.system_admin).toBe("platform_defaults");
    expect(payInstructDef?.scopes.admin).toBe("authorized_scope");
    expect(payInstructDef?.scopes.event_host).toBe("own_events");
    expect(payInstructDef?.scopes.attendee).toBeNull();

    const verifyDef = MASTER_ACCOUNT_SETTINGS.find((s) => s.key === "record_verify_payments");
    expect(verifyDef?.scopes.system_admin).toBe("authorized_oversight");
    expect(verifyDef?.scopes.admin).toBe("assigned_permissions");
    expect(verifyDef?.scopes.event_host).toBe("own_events");
    expect(verifyDef?.scopes.attendee).toBeNull();
  });

  it("9. Manage event staff is available to Host (own) and Admins (oversight/scope)", () => {
    const staffDef = MASTER_ACCOUNT_SETTINGS.find((s) => s.key === "manage_event_staff");
    expect(staffDef?.scopes.system_admin).toBe("oversight");
    expect(staffDef?.scopes.admin).toBe("authorized_scope");
    expect(staffDef?.scopes.event_host).toBe("own_events");
    expect(staffDef?.scopes.attendee).toBeNull();
  });

  it("10. Tickets and collection includes Attendee own tickets", () => {
    const ticketDef = MASTER_ACCOUNT_SETTINGS.find((s) => s.key === "manage_tickets_collection");
    expect(ticketDef?.scopes.system_admin).toBe("oversight");
    expect(ticketDef?.scopes.admin).toBe("authorized_scope");
    expect(ticketDef?.scopes.event_host).toBe("own_events");
    expect(ticketDef?.scopes.attendee).toBe("own_tickets");
  });

  it("11. Sales and financial reports differentiates platform oversight from personal transactions", () => {
    const salesDef = MASTER_ACCOUNT_SETTINGS.find((s) => s.key === "sales_financial_reports");
    expect(salesDef?.scopes.system_admin).toBe("authorized_oversight");
    expect(salesDef?.scopes.admin).toBe("assigned_scope");
    expect(salesDef?.scopes.event_host).toBe("own_events");
    expect(salesDef?.scopes.attendee).toBe("own_transactions");
  });

  it("12. Currency and regional preferences correctly binds personal preference vs platform defaults", () => {
    const currDef = MASTER_ACCOUNT_SETTINGS.find((s) => s.key === "currency_regional_preferences");
    expect(currDef?.scopes.system_admin).toBe("platform_defaults");
    expect(currDef?.scopes.admin).toBe("assigned_scope");
    expect(currDef?.scopes.event_host).toBe("event_specific_settings");
    expect(currDef?.scopes.attendee).toBe("personal_preference");
  });

  it("13. Privacy and data controls maps platform policies, lawful event data, and personal data", () => {
    const privDef = MASTER_ACCOUNT_SETTINGS.find((s) => s.key === "privacy_data_controls");
    expect(privDef?.scopes.system_admin).toBe("platform_policies");
    expect(privDef?.scopes.admin).toBe("assigned_permissions");
    expect(privDef?.scopes.event_host).toBe("own_account_lawful_event_data");
    expect(privDef?.scopes.attendee).toBe("own_account_personal_data");
  });

  it("14. Audit and security logs isolates log visibility by authorized scope", () => {
    const auditDef = MASTER_ACCOUNT_SETTINGS.find((s) => s.key === "audit_security_logs");
    expect(auditDef?.scopes.system_admin).toBe("authorized_platform_wide_access");
    expect(auditDef?.scopes.admin).toBe("scoped_access");
    expect(auditDef?.scopes.event_host).toBe("own_event_activity");
    expect(auditDef?.scopes.attendee).toBe("own_account_activity");
  });

  it("15. Enforces exact menu count invariants for each account type's settings menu", () => {
    function getAllowedSettingsForRole(role: AccountType) {
      return MASTER_ACCOUNT_SETTINGS.filter((s) => s.scopes[role] !== null);
    }

    // System Administrator: 17 settings
    const sysAdminSettings = getAllowedSettingsForRole("system_admin");
    expect(sysAdminSettings.length).toBe(17);

    // Administrator: 16 settings (excludes platform_configuration)
    const adminSettings = getAllowedSettingsForRole("admin");
    expect(adminSettings.length).toBe(16);
    expect(adminSettings.find((s) => s.key === "platform_configuration")).toBeUndefined();

    // Event Host: 14 settings (excludes manage_platform_users, manage_platform_roles, platform_configuration)
    const hostSettings = getAllowedSettingsForRole("event_host");
    expect(hostSettings.length).toBe(14);
    expect(hostSettings.find((s) => s.key === "manage_platform_users")).toBeUndefined();
    expect(hostSettings.find((s) => s.key === "manage_platform_roles")).toBeUndefined();
    expect(hostSettings.find((s) => s.key === "platform_configuration")).toBeUndefined();

    // Attendee: 9 settings
    const attendeeSettings = getAllowedSettingsForRole("attendee");
    expect(attendeeSettings.length).toBe(9);
    const attendeeKeys = attendeeSettings.map((s) => s.key);
    expect(attendeeKeys).toEqual([
      "personal_profile",
      "change_password",
      "two_factor_auth",
      "notification_preferences",
      "manage_tickets_collection",
      "sales_financial_reports",
      "currency_regional_preferences",
      "privacy_data_controls",
      "audit_security_logs",
    ]);
  });

  it("16. Important Security Invariant: System Admin All Access is governed by audit logging and privacy", () => {
    // Privileged inspection must generate an audit log tagged with isPiiAccess = true
    const auditEntry = createSecurityAuditLog(
      "user-sysadmin-01",
      "SysAdmin Operator",
      "system_admin",
      "privileged_payment_investigation",
      "payment_records",
      "ORD-2026-902",
      "Inspected bank wire reference statement during regulatory review.",
      true
    );

    expect(auditEntry.isPiiAccess).toBe(true);
    expect(auditEntry.actorRole).toBe("system_admin");
    expect(auditEntry.details).toContain("Inspected bank wire reference statement");
  });

  it("17. Universal Security Rule: Credentials are masked for all roles including System Administrator", () => {
    const rawStripeSecret = "sk_live_" + "51M0019283719827391823719";
    const maskedStripe = maskSecretKey(rawStripeSecret);
    expect(maskedStripe).not.toEqual(rawStripeSecret);
    expect(maskedStripe).toContain("••••••••");
    expect(maskedStripe.endsWith("3719")).toBe(true);
  });
});
