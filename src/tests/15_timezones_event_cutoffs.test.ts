import { describe, it, expect } from "vitest";
import { checkOrderCutoffStatus } from "./testUtils";

describe("15: Timezones and Event Cutoffs Suite", () => {
  // Jamaica is UTC-5 all year (no DST)
  const deadlineISO = "2026-11-27T18:00:00-05:00"; // 6:00 PM Jamaica Time on Nov 27, 2026

  it("should permit advance orders before the cutoff deadline", () => {
    // 2 hours before deadline
    const currentTimeISO = "2026-11-27T16:00:00-05:00";
    const status = checkOrderCutoffStatus(deadlineISO, currentTimeISO);

    expect(status.isCutoffPassed).toBe(false);
    expect(status.minutesRemaining).toBe(120);
  });

  it("should close ordering immediately once the cutoff deadline has passed", () => {
    // 15 minutes after deadline
    const currentTimeISO = "2026-11-27T18:15:00-05:00";
    const status = checkOrderCutoffStatus(deadlineISO, currentTimeISO);

    expect(status.isCutoffPassed).toBe(true);
    expect(status.minutesRemaining).toBeLessThan(0);
  });

  it("should format the deadline string accurately in America/Jamaica timezone", () => {
    const status = checkOrderCutoffStatus(deadlineISO, "2026-11-20T12:00:00-05:00");
    // Formatted in America/Jamaica
    expect(status.deadlineLocalStr).toContain("2026");
    expect(status.deadlineLocalStr).toMatch(/6:00\s*(PM|pm)/);
  });

  it("should reject checkout attempts when event ordering is closed or cutoff has passed", () => {
    function validateOrderingAllowed(isOrderingOpen: boolean, deadlineISO: string, nowISO: string): boolean {
      if (!isOrderingOpen) return false;
      const { isCutoffPassed } = checkOrderCutoffStatus(deadlineISO, nowISO);
      return !isCutoffPassed;
    }

    // Open and before deadline
    expect(validateOrderingAllowed(true, deadlineISO, "2026-11-25T10:00:00-05:00")).toBe(true);

    // After deadline
    expect(validateOrderingAllowed(true, deadlineISO, "2026-11-27T18:01:00-05:00")).toBe(false);

    // Explicitly closed by organizer even before deadline
    expect(validateOrderingAllowed(false, deadlineISO, "2026-11-25T10:00:00-05:00")).toBe(false);
  });
});
