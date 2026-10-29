import { describe, it, expect } from "vitest";
import {
  EVENTBITE_TOKENS,
  EVENTBITE_BREAKPOINTS,
  calculateContrastRatio,
  formatPaymentStatusLabel,
  getAuthorizedCustomerMobileNav,
  getAuthorizedManagementSidebarItems,
} from "../components/design-system/EventBitePrimitives";
import {
  EVENTBITE_ASSET_INVENTORY,
  resolveEventFallbackImage,
  resolveFoodFallbackImage,
} from "../lib/assetInventory";

describe("26. EventBite Master Design System Verification", () => {
  it("centralizes all required color tokens in EVENTBITE_TOKENS", () => {
    expect(EVENTBITE_TOKENS.colorBackground).toBe("#07111F");
    expect(EVENTBITE_TOKENS.colorBackgroundSecondary).toBe("#0B192B");
    expect(EVENTBITE_TOKENS.colorSurface).toBe("#10243A");
    expect(EVENTBITE_TOKENS.colorSurfaceElevated).toBe("#152C45");
    expect(EVENTBITE_TOKENS.colorAccent).toBe("#C7FF45");
    expect(EVENTBITE_TOKENS.colorAccentHover).toBe("#D7FF77");
    expect(EVENTBITE_TOKENS.colorAccentText).toBe("#14210A");
    expect(EVENTBITE_TOKENS.colorPurple).toBe("#8B5CF6");
    expect(EVENTBITE_TOKENS.colorTextPrimary).toBe("#F8FAFC");
    expect(EVENTBITE_TOKENS.colorTextSecondary).toBe("#A8B6C8");
    expect(EVENTBITE_TOKENS.colorTextMuted).toBe("#8494A8");
    expect(EVENTBITE_TOKENS.colorBorder).toBe("#26384D");
    expect(EVENTBITE_TOKENS.colorSuccess).toBe("#22C55E");
    expect(EVENTBITE_TOKENS.colorWarning).toBe("#F59E0B");
    expect(EVENTBITE_TOKENS.colorError).toBe("#EF4444");
  });

  it("passes WCAG 2.1 AA/AAA contrast ratios across dark surfaces and primary buttons", () => {
    // Primary text on all dark background/surface tiers (>= 7.0 AAA)
    expect(
      calculateContrastRatio(EVENTBITE_TOKENS.colorTextPrimary, EVENTBITE_TOKENS.colorBackground)
    ).toBeGreaterThanOrEqual(15);
    expect(
      calculateContrastRatio(EVENTBITE_TOKENS.colorTextPrimary, EVENTBITE_TOKENS.colorSurfaceElevated)
    ).toBeGreaterThanOrEqual(12);

    // Secondary text on all dark surface tiers (>= 4.5 AA)
    expect(
      calculateContrastRatio(EVENTBITE_TOKENS.colorTextSecondary, EVENTBITE_TOKENS.colorBackground)
    ).toBeGreaterThanOrEqual(7);
    expect(
      calculateContrastRatio(EVENTBITE_TOKENS.colorTextSecondary, EVENTBITE_TOKENS.colorSurfaceElevated)
    ).toBeGreaterThanOrEqual(6);

    // Muted text on all dark surface tiers (>= 4.5 AA)
    expect(
      calculateContrastRatio(EVENTBITE_TOKENS.colorTextMuted, EVENTBITE_TOKENS.colorBackground)
    ).toBeGreaterThanOrEqual(5.5);
    expect(
      calculateContrastRatio(EVENTBITE_TOKENS.colorTextMuted, EVENTBITE_TOKENS.colorSurfaceElevated)
    ).toBeGreaterThanOrEqual(4.5);

    // Primary Electric Lime CTA button (#C7FF45 background with #14210A dark text >= 10:1 AAA)
    expect(
      calculateContrastRatio(EVENTBITE_TOKENS.colorAccent, EVENTBITE_TOKENS.colorAccentText)
    ).toBeGreaterThanOrEqual(10);
  });

  it("enforces explicit textual payment status labels and never treats unverified payments as verified", () => {
    const paid = formatPaymentStatusLabel("paid");
    expect(paid.label).toBe("Paid (Verified)");
    expect(paid.tone).toBe("success");
    expect(paid.isIndependentlyVerified).toBe(true);

    const submitted = formatPaymentStatusLabel("payment_submitted");
    expect(submitted.label).toContain("Pending Verification");
    expect(submitted.tone).toBe("warning");
    expect(submitted.isIndependentlyVerified).toBe(false);

    const awaiting = formatPaymentStatusLabel("awaiting_payment");
    expect(awaiting.label).toBe("Awaiting Payment");
    expect(awaiting.tone).toBe("warning");
    expect(awaiting.isIndependentlyVerified).toBe(false);

    const underVerif = formatPaymentStatusLabel("under_verification");
    expect(underVerif.isIndependentlyVerified).toBe(false);
    expect(underVerif.tone).toBe("warning");
  });

  it("gates Customer Mobile Navigation and Management Sidebar items by user permissions", () => {
    // Guest customer sees only public Home
    const guestNav = getAuthorizedCustomerMobileNav(false);
    expect(guestNav.map((i) => i.key)).toEqual(["home"]);

    // Signed-in customer sees Home, My Tickets, Orders, Profile
    const authedNav = getAuthorizedCustomerMobileNav(true);
    expect(authedNav.map((i) => i.key)).toEqual(["home", "tickets", "orders", "profile"]);

    // Attendee gets zero management sidebar items
    expect(getAuthorizedManagementSidebarItems({ activeRole: "attendee" })).toEqual([]);

    // Event Host gets host operational tools, but not platform Roles & Permissions or Audit Logs
    const hostItems = getAuthorizedManagementSidebarItems({ activeRole: "event_host" }).map((i) => i.key);
    expect(hostItems).toContain("dashboard");
    expect(hostItems).toContain("events");
    expect(hostItems).toContain("menu_management");
    expect(hostItems).toContain("staff");
    expect(hostItems).toContain("check_in");
    expect(hostItems).not.toContain("roles_permissions");
    expect(hostItems).not.toContain("audit_logs");

    // Ticket Checker staff only gets Check-in & Tickets, never Menu Management or Settings
    const gateStaff = getAuthorizedManagementSidebarItems({
      activeRole: "staff",
      workerPermissions: ["scan_qr_tickets", "validate_entry"],
    }).map((i) => i.key);
    expect(gateStaff).toEqual(["check_in", "tickets"]);

    // System Admin receives all 12 governance items
    const sysAdmin = getAuthorizedManagementSidebarItems({ activeRole: "system_admin" });
    expect(sysAdmin).toHaveLength(12);
  });

  it("maintains a verified local asset inventory with zero Dribbble hotlinks and category fallbacks", () => {
    const records = Object.values(EVENTBITE_ASSET_INVENTORY);
    expect(records.length).toBeGreaterThanOrEqual(14);

    for (const asset of records) {
      expect(asset.path.startsWith("/images/")).toBe(true);
      expect(asset.path).not.toContain("dribbble.com");
      expect(asset.licenseSource).toBe("First-Party Project Asset (/public/images)");
    }

    // Event fallback rejects Dribbble hotlinks and selects appropriate category image
    const concertFallback = resolveEventFallbackImage({
      flyerUrl: "https://dribbble.com/shots/24997398-Ticket-App-WIP",
      title: "Kingston Roots Reggae Concert",
    });
    expect(concertFallback.src).toBe("/images/island_music_reggae_concert.jpg");

    const foodFallback = resolveFoodFallbackImage({
      imageUrl: "",
      name: "Pimento Jerk Chicken Platter",
      category: "Mains",
    });
    expect(foodFallback.src).toBe("/images/jerk_chicken.jpg");
  });

  it("defines the 6 required responsive test widths (360, 390, 768, 1024, 1440, 1920)", () => {
    expect(EVENTBITE_BREAKPOINTS).toEqual({
      mobileCompact: 360,
      mobileStandard: 390,
      tablet: 768,
      desktop: 1024,
      desktopWide: 1440,
      desktopUltra: 1920,
    });
  });
});
