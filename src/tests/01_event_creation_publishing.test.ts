import { describe, it, expect } from "vitest";
import { EventDetails } from "../types";
import { validateEventCreation } from "./testUtils";

describe("01: Event Creation and Publishing Suite", () => {
  const validEvent: EventDetails = {
    organizerId: "org-island-fish-fry",
    title: "Old Harbour Bay Annual Seafood Festival",
    tagline: "Authentic Jamaican Escovitch, Bammy & Festival",
    dateStr: "Saturday, November 28, 2026",
    timeStr: "12:00 PM - 8:00 PM EST",
    venueName: "Old Harbour Bay Beachfront Park",
    venueAddress: "Old Harbour Bay Main Road, St. Catherine, Jamaica",
    coordinates: "17.9042° N, 77.1086° W",
    description: "Annual community seafood feast with live reggae and freshly caught escovitch fish.",
    organizerName: "St. Catherine Youth & Sports Club",
    organizerPhone: "876-555-0144",
    organizerWhatsApp: "876-555-0144",
    organizerEmail: "orders@islandfishfry.com",
    advanceOrderDeadline: "2026-11-27T18:00:00-05:00",
    isOrderingOpen: true,
    pickupInstructions: "Show your digital ticket or QR code at Station 1 (Main Fish Station).",
    packagingFeeUSD: 2.0,
    packagingFeeJMD: 300,
  };

  it("should validate and approve a well-formed event definition", () => {
    const result = validateEventCreation(validEvent);
    expect(result.isValid).toBe(true);
    expect(result.errors).toHaveLength(0);
  });

  it("should reject event creation if title is empty or too short", () => {
    const invalidEvent = { ...validEvent, title: "Hi" };
    const result = validateEventCreation(invalidEvent);
    expect(result.isValid).toBe(false);
    expect(result.errors).toContain("Title must be at least 3 characters");
  });

  it("should require venue name, address, and advance order deadline", () => {
    const invalidEvent = {
      ...validEvent,
      venueName: "",
      venueAddress: "",
      advanceOrderDeadline: "",
    };
    const result = validateEventCreation(invalidEvent);
    expect(result.isValid).toBe(false);
    expect(result.errors).toContain("Venue name is required");
    expect(result.errors).toContain("Venue address is required");
    expect(result.errors).toContain("Advance order deadline is required");
  });

  it("should prevent negative packaging fees in both currencies", () => {
    const invalidEventUSD = { ...validEvent, packagingFeeUSD: -5 };
    const resUSD = validateEventCreation(invalidEventUSD);
    expect(resUSD.isValid).toBe(false);
    expect(resUSD.errors).toContain("Packaging fees cannot be negative");

    const invalidEventJMD = { ...validEvent, packagingFeeJMD: -100 };
    const resJMD = validateEventCreation(invalidEventJMD);
    expect(resJMD.isValid).toBe(false);
    expect(resJMD.errors).toContain("Packaging fees cannot be negative");
  });

  it("should support toggling published state for advance ordering", () => {
    const draftEvent = { ...validEvent, isOrderingOpen: false };
    expect(draftEvent.isOrderingOpen).toBe(false);

    const publishedEvent = { ...draftEvent, isOrderingOpen: true };
    expect(publishedEvent.isOrderingOpen).toBe(true);
  });
});
