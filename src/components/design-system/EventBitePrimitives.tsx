import React, { useRef } from "react";

/* ==========================================================================
   EVENTBITE MASTER DESIGN SYSTEM — CENTRALIZED TOKENS & REACT PRIMITIVES
   ========================================================================== */

export const EVENTBITE_TOKENS = {
  colorBackground: "#07111F",
  colorBackgroundSecondary: "#0B192B",
  colorSurface: "#10243A",
  colorSurfaceElevated: "#152C45",
  colorAccent: "#C7FF45",
  colorAccentHover: "#D7FF77",
  colorAccentText: "#14210A",
  colorPurple: "#8B5CF6",
  colorPurpleText: "#C4B5FD",
  colorTextPrimary: "#F8FAFC",
  colorTextSecondary: "#A8B6C8",
  colorTextMuted: "#8494A8",
  colorBorder: "#26384D",
  colorSuccess: "#22C55E",
  colorSuccessText: "#4ADE80",
  colorWarning: "#F59E0B",
  colorWarningText: "#FBBF24",
  colorError: "#EF4444",
  colorErrorText: "#FCA5A5",
} as const;

export const EVENTBITE_BREAKPOINTS = {
  mobileCompact: 360,
  mobileStandard: 390,
  tablet: 768,
  desktop: 1024,
  desktopWide: 1440,
  desktopUltra: 1920,
} as const;

/**
 * WCAG 2.1 Relative Luminance & Contrast Ratio Calculator
 */
export function calculateRelativeLuminance(hex: string): number {
  const clean = hex.replace("#", "").trim();
  const r = parseInt(clean.substring(0, 2), 16) / 255;
  const g = parseInt(clean.substring(2, 4), 16) / 255;
  const b = parseInt(clean.substring(4, 6), 16) / 255;

  const toLinear = (c: number) => (c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
  return 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b);
}

export function calculateContrastRatio(fgHex: string, bgHex: string): number {
  const l1 = calculateRelativeLuminance(fgHex);
  const l2 = calculateRelativeLuminance(bgHex);
  const lighter = Math.max(l1, l2);
  const darker = Math.min(l1, l2);
  return Number(((lighter + 0.05) / (darker + 0.05)).toFixed(2));
}

/**
 * Section 2 Rule: Payment status must always have an explicit textual label.
 * Never use green alone to imply that a payment has been independently verified.
 */
export type PaymentOrOrderStatus =
  | "paid"
  | "verified"
  | "payment_submitted"
  | "under_verification"
  | "awaiting_payment"
  | "partially_paid"
  | "refunded"
  | "rejected"
  | "cancelled";

export function formatPaymentStatusLabel(status: string): {
  label: string;
  tone: "success" | "warning" | "error" | "purple" | "info";
  isIndependentlyVerified: boolean;
} {
  const normalized = status.toLowerCase().trim();
  switch (normalized) {
    case "paid":
    case "verified":
      return {
        label: "Paid (Verified)",
        tone: "success",
        isIndependentlyVerified: true,
      };
    case "payment_submitted":
      return {
        label: "Transfer Submitted — Pending Verification",
        tone: "warning",
        isIndependentlyVerified: false,
      };
    case "under_verification":
      return {
        label: "Under Host Verification",
        tone: "warning",
        isIndependentlyVerified: false,
      };
    case "awaiting_payment":
      return {
        label: "Awaiting Payment",
        tone: "warning",
        isIndependentlyVerified: false,
      };
    case "partially_paid":
      return {
        label: "Partially Paid — Balance Due",
        tone: "warning",
        isIndependentlyVerified: false,
      };
    case "refunded":
      return {
        label: "Refunded",
        tone: "purple",
        isIndependentlyVerified: false,
      };
    case "rejected":
      return {
        label: "Payment Rejected",
        tone: "error",
        isIndependentlyVerified: false,
      };
    case "cancelled":
      return {
        label: "Cancelled",
        tone: "error",
        isIndependentlyVerified: false,
      };
    default:
      return {
        label: normalized.replace(/_/g, " "),
        tone: "info",
        isIndependentlyVerified: false,
      };
  }
}

/* --------------------------------------------------------------------------
   Section 6: Button System Component (with Duplicate-Submission Lock)
   -------------------------------------------------------------------------- */

export type ButtonVariant =
  | "primary"
  | "secondary"
  | "outline"
  | "ghost"
  | "destructive"
  | "icon";

export interface EventBiteButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: "sm" | "md" | "lg";
  loading?: boolean;
  block?: boolean;
  preventDuplicateSubmit?: boolean;
}

export const EventBiteButton: React.FC<EventBiteButtonProps> = ({
  variant = "primary",
  size = "md",
  loading = false,
  disabled = false,
  block = false,
  preventDuplicateSubmit = true,
  onClick,
  children,
  className = "",
  type = "button",
  ...rest
}) => {
  const inflightRef = useRef(false);

  const variantClass =
    variant === "primary"
      ? "eb-btn-primary"
      : variant === "secondary"
      ? "eb-btn-secondary"
      : variant === "outline"
      ? "eb-btn-outline"
      : variant === "ghost"
      ? "eb-btn-ghost"
      : variant === "destructive"
      ? "eb-btn-destructive"
      : "eb-btn-icon";

  const sizeClass = size === "sm" ? "eb-btn-sm" : size === "lg" ? "eb-btn-lg" : "";

  const isDisabled = disabled || loading;

  const handleClick = async (e: React.MouseEvent<HTMLButtonElement>) => {
    if (isDisabled) {
      e.preventDefault();
      return;
    }
    if (preventDuplicateSubmit && inflightRef.current) {
      e.preventDefault();
      return;
    }
    if (!onClick) return;
    try {
      inflightRef.current = true;
      await onClick(e);
    } finally {
      inflightRef.current = false;
    }
  };

  return (
    <button
      type={type}
      disabled={isDisabled}
      aria-disabled={isDisabled}
      aria-busy={loading}
      data-loading={loading ? "true" : undefined}
      onClick={handleClick}
      className={`eb-btn ${variantClass} ${sizeClass} ${block ? "eb-btn-block" : ""} ${
        loading ? "eb-btn-loading" : ""
      } ${className}`.trim()}
      {...rest}
    >
      {loading && <span className="eb-btn-spinner" aria-hidden="true" />}
      {children}
    </button>
  );
};

/* --------------------------------------------------------------------------
   Section 8: Permission-Aware Navigation Resolvers
   -------------------------------------------------------------------------- */

export type CustomerNavKey = "home" | "tickets" | "orders" | "profile";

export interface CustomerNavOption {
  key: CustomerNavKey;
  label: string;
}

/**
 * Returns only the customer mobile navigation options the current user may access.
 */
export function getAuthorizedCustomerMobileNav(isLoggedIn: boolean): CustomerNavOption[] {
  const base: CustomerNavOption[] = [{ key: "home", label: "Home" }];
  if (!isLoggedIn) {
    return base;
  }
  return [
    ...base,
    { key: "tickets", label: "My Tickets" },
    { key: "orders", label: "Orders" },
    { key: "profile", label: "Profile" },
  ];
}

export type ManagementNavKey =
  | "dashboard"
  | "events"
  | "tickets"
  | "orders"
  | "menu_management"
  | "staff"
  | "check_in"
  | "reports"
  | "users"
  | "roles_permissions"
  | "settings"
  | "audit_logs";

export interface ManagementNavItem {
  key: ManagementNavKey;
  label: string;
}

/**
 * Returns the structured management sidebar items permitted for a given role or worker assignment.
 */
export function getAuthorizedManagementSidebarItems(params: {
  activeRole: "attendee" | "event_host" | "admin" | "system_admin" | "staff";
  workerPermissions?: string[];
}): ManagementNavItem[] {
  const { activeRole, workerPermissions = [] } = params;

  if (activeRole === "system_admin") {
    return [
      { key: "dashboard", label: "Dashboard" },
      { key: "events", label: "Events" },
      { key: "tickets", label: "Tickets" },
      { key: "orders", label: "Orders" },
      { key: "menu_management", label: "Menu Management" },
      { key: "staff", label: "Staff" },
      { key: "check_in", label: "Check-in" },
      { key: "reports", label: "Reports" },
      { key: "users", label: "Users" },
      { key: "roles_permissions", label: "Roles and Permissions" },
      { key: "settings", label: "Settings" },
      { key: "audit_logs", label: "Audit Logs" },
    ];
  }

  if (activeRole === "admin") {
    return [
      { key: "dashboard", label: "Dashboard" },
      { key: "events", label: "Events" },
      { key: "tickets", label: "Tickets" },
      { key: "orders", label: "Orders" },
      { key: "reports", label: "Reports" },
      { key: "users", label: "Users" },
      { key: "audit_logs", label: "Audit Logs" },
    ];
  }

  if (activeRole === "event_host") {
    return [
      { key: "dashboard", label: "Dashboard" },
      { key: "events", label: "Events" },
      { key: "tickets", label: "Tickets" },
      { key: "orders", label: "Orders" },
      { key: "menu_management", label: "Menu Management" },
      { key: "staff", label: "Staff" },
      { key: "check_in", label: "Check-in" },
      { key: "reports", label: "Reports" },
      { key: "settings", label: "Settings" },
    ];
  }

  if (activeRole === "staff") {
    const items: ManagementNavItem[] = [];
    if (
      workerPermissions.includes("scan_qr_tickets") ||
      workerPermissions.includes("validate_entry") ||
      workerPermissions.includes("mark_quantities_collected")
    ) {
      items.push({ key: "check_in", label: "Check-in" });
    }
    if (
      workerPermissions.includes("view_attendee_orders") ||
      workerPermissions.includes("verify_payments") ||
      workerPermissions.includes("view_food_orders")
    ) {
      items.push({ key: "orders", label: "Orders" });
    }
    if (workerPermissions.includes("scan_qr_tickets")) {
      items.push({ key: "tickets", label: "Tickets" });
    }
    return items;
  }

  return [];
}

