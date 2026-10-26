import { describe, it, expect } from "vitest";
import { evaluateTenantAccess } from "./testUtils";

describe("13: Authorization and Cross-Organizer Isolation Suite", () => {
  const tenantKingston = "org-kingston-youth";
  const tenantPortmore = "org-portmore-church";

  describe("Cross-Organizer Boundary (IDOR Defense)", () => {
    it("should block cross-organizer resource access with HTTP 403", () => {
      // User authenticated under Kingston Youth attempts to access Portmore Church orders
      const check = evaluateTenantAccess(
        tenantKingston,
        "event_owner",
        tenantPortmore,
        "access_resource"
      );

      expect(check.allowed).toBe(false);
      expect(check.httpStatus).toBe(403);
      expect(check.message).toContain("Tenant Isolation Boundary Violation");
    });

    it("should allow Platform Admin global authority across all tenants", () => {
      const checkKingston = evaluateTenantAccess(
        "platform-admin-org",
        "platform_admin",
        tenantKingston,
        "view_financials"
      );
      expect(checkKingston.allowed).toBe(true);
      expect(checkKingston.httpStatus).toBe(200);

      const checkPortmore = evaluateTenantAccess(
        "platform-admin-org",
        "platform_admin",
        tenantPortmore,
        "edit_menu"
      );
      expect(checkPortmore.allowed).toBe(true);
      expect(checkPortmore.httpStatus).toBe(200);
    });

    it("should allow legitimate within-tenant resource access", () => {
      const check = evaluateTenantAccess(
        tenantKingston,
        "event_owner",
        tenantKingston,
        "access_resource"
      );

      expect(check.allowed).toBe(true);
      expect(check.httpStatus).toBe(200);
    });
  });

  describe("Role-Based Access Control (RBAC) Boundaries", () => {
    it("should allow Event Owner to view financials and edit menu", () => {
      const finCheck = evaluateTenantAccess(
        tenantKingston,
        "event_owner",
        tenantKingston,
        "view_financials"
      );
      expect(finCheck.allowed).toBe(true);

      const menuCheck = evaluateTenantAccess(
        tenantKingston,
        "event_owner",
        tenantKingston,
        "edit_menu"
      );
      expect(menuCheck.allowed).toBe(true);
    });

    it("should prevent Food Collection Staff from verifying payments or viewing financials", () => {
      const finCheck = evaluateTenantAccess(
        tenantKingston,
        "food_collector",
        tenantKingston,
        "view_financials"
      );
      expect(finCheck.allowed).toBe(false);
      expect(finCheck.httpStatus).toBe(403);
      expect(finCheck.message).toContain("Requires Event Owner authority to view financials");

      const verifyCheck = evaluateTenantAccess(
        tenantKingston,
        "food_collector",
        tenantKingston,
        "verify_payment"
      );
      expect(verifyCheck.allowed).toBe(false);
      expect(verifyCheck.httpStatus).toBe(403);
      expect(verifyCheck.message).toContain("Food Collection Staff cannot verify payments");
    });

    it("should allow Food Collection Staff to collect food", () => {
      const foodCheck = evaluateTenantAccess(
        tenantKingston,
        "food_collector",
        tenantKingston,
        "collect_food"
      );
      expect(foodCheck.allowed).toBe(true);
      expect(foodCheck.httpStatus).toBe(200);
    });

    it("should allow Payment Verifier to verify payments but block menu edits", () => {
      const verifyCheck = evaluateTenantAccess(
        tenantKingston,
        "payment_verifier",
        tenantKingston,
        "verify_payment"
      );
      expect(verifyCheck.allowed).toBe(true);

      const menuCheck = evaluateTenantAccess(
        tenantKingston,
        "payment_verifier",
        tenantKingston,
        "edit_menu"
      );
      expect(menuCheck.allowed).toBe(false);
      expect(menuCheck.httpStatus).toBe(403);
      expect(menuCheck.message).toContain("Requires Event Owner authority to alter menu or prices");
    });
  });
});
