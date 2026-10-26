import { describe, it, expect } from "vitest";
import { EventDetails } from "../types";
import {
  evaluateEventVisibility,
  getEventFlyerDisplay,
  processFlyerDeletion,
  processFlyerReplacement,
  validateAndScopeFlyerUpload,
  validateEventCreation,
} from "./testUtils";

describe("17: Event Management and Flyer Upload Comprehensive Suite", () => {
  const tenantId = "org-kingston-youth";

  const baseEvent: EventDetails = {
    id: "evt-kingston-2026",
    organizerId: tenantId,
    slug: "kingston-fishfry-2026",
    title: "Sunday Fish Fry — Community Fundraiser",
    tagline: "Authentic Escovitch, Fried Chicken & Island Drinks",
    dateStr: "Sunday, November 22, 2026",
    timeStr: "12:00 PM – 8:00 PM",
    timezone: "America/Jamaica",
    venueName: "Kingston Waterfront Pier",
    venueAddress: "Ocean Boulevard, Downtown Kingston, Jamaica",
    coordinates: "18° 00' N · 76° 48' W",
    description: "Annual fundraiser supporting youth athletic kits and community development.",
    currency: "JMD",
    organizerName: "Kingston Youth Foundation",
    organizerPhone: "+1 (876) 926-3849",
    organizerWhatsApp: "+1 (876) 555-4321",
    organizerEmail: "fishfry@kingstonyouth.example",
    advanceOrderDeadline: "2026-11-20T20:00:00-05:00",
    isOrderingOpen: true,
    status: "published",
    pickupInstructions: "Food Collection Tent B, Kingston Waterfront Pier.",
    packagingFeeUSD: 0.75,
    packagingFeeJMD: 100,
    flyerUrl: "https://islandevents.app/storage/tenants/org-kingston-youth/flyers/initial_flyer.jpg",
    flyerStoragePath: "tenants/org-kingston-youth/flyers/initial_flyer.jpg",
    flyerMimeType: "image/jpeg",
    flyerSizeBytes: 1024 * 650,
    flyerDimensions: { width: 1200, height: 800 },
    isFlyerPublic: true,
    shareableUrl: "https://islandevents.app/e/kingston-fishfry-2026",
    socialSharingMeta: {
      ogTitle: "Sunday Fish Fry — Community Fundraiser | Island Events",
      ogDescription: "Authentic Escovitch, Fried Chicken & Island Drinks at Ocean Boulevard.",
      ogImage: "https://islandevents.app/storage/tenants/org-kingston-youth/flyers/initial_flyer.jpg",
    },
  };

  describe("1. Event Permissions & RBAC Enforcement", () => {
    it("should allow Event Owners and Platform Admins to upload flyers", () => {
      const ownerUpload = validateAndScopeFlyerUpload(
        { name: "promo.png", sizeBytes: 1024 * 500, mimeType: "image/png" },
        tenantId,
        "event_owner"
      );
      expect(ownerUpload.allowed).toBe(true);

      const adminUpload = validateAndScopeFlyerUpload(
        { name: "promo.png", sizeBytes: 1024 * 500, mimeType: "image/png" },
        tenantId,
        "platform_admin"
      );
      expect(adminUpload.allowed).toBe(true);
    });

    it("should strictly block Food Collectors and Payment Verifiers from uploading flyers", () => {
      const collectorUpload = validateAndScopeFlyerUpload(
        { name: "promo.png", sizeBytes: 1024 * 500, mimeType: "image/png" },
        tenantId,
        "food_collector"
      );
      expect(collectorUpload.allowed).toBe(false);
      expect(collectorUpload.error).toContain("Only Event Owners or Platform Admins");

      const verifierUpload = validateAndScopeFlyerUpload(
        { name: "promo.png", sizeBytes: 1024 * 500, mimeType: "image/png" },
        tenantId,
        "payment_verifier"
      );
      expect(verifierUpload.allowed).toBe(false);
      expect(verifierUpload.error).toContain("Only Event Owners or Platform Admins");
    });
  });

  describe("2. File Validation, MIME Constraints & Dimensions", () => {
    it("should accept JPEG, PNG, and WebP images within dimensions and size limits", () => {
      const validJpg = validateAndScopeFlyerUpload(
        {
          name: "poster.jpg",
          sizeBytes: 2 * 1024 * 1024,
          mimeType: "image/jpeg",
          dimensions: { width: 1920, height: 1080 },
        },
        tenantId,
        "event_owner"
      );
      expect(validJpg.allowed).toBe(true);
      expect(validJpg.storagePath).toContain(`tenants/${tenantId}/flyers/`);

      const validWebp = validateAndScopeFlyerUpload(
        {
          name: "banner.webp",
          sizeBytes: 800 * 1024,
          mimeType: "image/webp",
          dimensions: { width: 1200, height: 630 },
        },
        tenantId,
        "event_owner"
      );
      expect(validWebp.allowed).toBe(true);
    });

    it("should reject disallowed file formats (GIF, SVG with scripts, PDF, EXE)", () => {
      const gifAttempt = validateAndScopeFlyerUpload(
        { name: "animation.gif", sizeBytes: 1024 * 300, mimeType: "image/gif" },
        tenantId,
        "event_owner"
      );
      expect(gifAttempt.allowed).toBe(false);
      expect(gifAttempt.error).toContain("Allowed formats: JPEG, PNG, WebP only");

      const svgAttempt = validateAndScopeFlyerUpload(
        { name: "vector.svg", sizeBytes: 50000, mimeType: "image/svg+xml" },
        tenantId,
        "event_owner"
      );
      expect(svgAttempt.allowed).toBe(false);

      const exeAttempt = validateAndScopeFlyerUpload(
        { name: "installer.exe", sizeBytes: 100000, mimeType: "application/x-msdownload" },
        tenantId,
        "event_owner"
      );
      expect(exeAttempt.allowed).toBe(false);
    });

    it("should reject files exceeding the 5MB size ceiling", () => {
      const oversized = validateAndScopeFlyerUpload(
        { name: "raw_billboard.png", sizeBytes: 6 * 1024 * 1024, mimeType: "image/png" },
        tenantId,
        "event_owner"
      );
      expect(oversized.allowed).toBe(false);
      expect(oversized.error).toContain("exceeds 5MB limit");
    });

    it("should enforce minimum and maximum image dimension limits", () => {
      // Too small (< 300x200)
      const tooSmall = validateAndScopeFlyerUpload(
        {
          name: "tiny_icon.jpg",
          sizeBytes: 50000,
          mimeType: "image/jpeg",
          dimensions: { width: 150, height: 100 },
        },
        tenantId,
        "event_owner"
      );
      expect(tooSmall.allowed).toBe(false);
      expect(tooSmall.error).toContain("below the minimum required");

      // Too large (> 4500x4500)
      const tooLarge = validateAndScopeFlyerUpload(
        {
          name: "huge_canvas.jpg",
          sizeBytes: 3 * 1024 * 1024,
          mimeType: "image/jpeg",
          dimensions: { width: 5000, height: 5000 },
        },
        tenantId,
        "event_owner"
      );
      expect(tooLarge.allowed).toBe(false);
      expect(tooLarge.error).toContain("exceed the maximum allowable");
    });
  });

  describe("3. Security: Path Traversal & Cryptographic Filename Isolation", () => {
    it("should detect and reject directory traversal strings in filename", () => {
      const traversalAttempt = validateAndScopeFlyerUpload(
        { name: "../../../etc/passwd.jpg", sizeBytes: 1024, mimeType: "image/jpeg" },
        tenantId,
        "event_owner"
      );
      expect(traversalAttempt.allowed).toBe(false);
      expect(traversalAttempt.error).toContain("Path traversal attempt detected");
    });

    it("should generate random unguessable storage paths preventing enumeration", () => {
      const upload1 = validateAndScopeFlyerUpload(
        { name: "flyer.png", sizeBytes: 100000, mimeType: "image/png" },
        tenantId,
        "event_owner"
      );
      const upload2 = validateAndScopeFlyerUpload(
        { name: "flyer.png", sizeBytes: 100000, mimeType: "image/png" },
        tenantId,
        "event_owner"
      );

      expect(upload1.storagePath).toBeDefined();
      expect(upload2.storagePath).toBeDefined();
      // Filenames must be uniquely randomized
      expect(upload1.storagePath).not.toBe(upload2.storagePath);
      expect(upload1.storagePath).toMatch(/^tenants\/org-kingston-youth\/flyers\/\d+_[a-f0-9]+\.png$/);
    });
  });

  describe("4. Flyer Replacement & Deletion Workflow", () => {
    it("should replace existing flyer with validated new file and track previous path for cleanup", () => {
      const replacementFile = {
        name: "revised_fishfry_flyer.webp",
        sizeBytes: 1024 * 400,
        mimeType: "image/webp",
        dimensions: { width: 1400, height: 900 },
      };

      const { success, updatedEvent, previousStoragePath, error } = processFlyerReplacement(
        baseEvent,
        replacementFile,
        tenantId,
        "event_owner"
      );

      expect(success).toBe(true);
      expect(error).toBeUndefined();
      expect(previousStoragePath).toBe("tenants/org-kingston-youth/flyers/initial_flyer.jpg");
      expect(updatedEvent.flyerStoragePath).toContain("tenants/org-kingston-youth/flyers/");
      expect(updatedEvent.flyerMimeType).toBe("image/webp");
      expect(updatedEvent.flyerSizeBytes).toBe(1024 * 400);
      expect(updatedEvent.isFlyerPublic).toBe(true); // Published event
    });

    it("should remove flyer cleanly and revert to safe fallback banner", () => {
      const { success, updatedEvent } = processFlyerDeletion(baseEvent, "event_owner");

      expect(success).toBe(true);
      expect(updatedEvent.flyerUrl).toBeUndefined();
      expect(updatedEvent.flyerStoragePath).toBeUndefined();
      expect(updatedEvent.isFlyerPublic).toBe(false);

      // Verify display helper falls back to reliable Caribbean themed visual
      const display = getEventFlyerDisplay(updatedEvent);
      expect(display.isFallback).toBe(true);
      expect(display.flyerUrl).toContain("unsplash.com");
      expect(display.altText).toContain("Feast Showcase Banner");
    });
  });

  describe("5. Public vs Private Event Visibility & Draft Protection", () => {
    it("should allow anonymous visitors to view published events without signing in", () => {
      const publishedEvent: EventDetails = { ...baseEvent, status: "published" };
      const visibility = evaluateEventVisibility(publishedEvent, false);

      expect(visibility.isPubliclyAccessible).toBe(true);
      expect(visibility.httpStatus).toBe(200);
      expect(visibility.mode).toBe("public_view");
    });

    it("should hide draft events from anonymous public visitors with HTTP 404", () => {
      const draftEvent: EventDetails = { ...baseEvent, status: "draft" };
      const visibility = evaluateEventVisibility(draftEvent, false);

      expect(visibility.isPubliclyAccessible).toBe(false);
      expect(visibility.httpStatus).toBe(404);
      expect(visibility.mode).toBe("access_denied");
      expect(visibility.notice).toContain("unpublished draft");
    });

    it("should allow authorized event owners to preview draft events before publishing", () => {
      const draftEvent: EventDetails = { ...baseEvent, status: "draft" };
      const visibility = evaluateEventVisibility(draftEvent, true); // Owner authenticated

      expect(visibility.isPubliclyAccessible).toBe(false); // Still hidden from public
      expect(visibility.httpStatus).toBe(200); // Owner can inspect
      expect(visibility.mode).toBe("owner_preview");
      expect(visibility.notice).toContain("Owner Preview Mode");
    });

    it("should block public access to cancelled events with HTTP 403", () => {
      const cancelledEvent: EventDetails = { ...baseEvent, status: "cancelled" };
      const visibility = evaluateEventVisibility(cancelledEvent, false);

      expect(visibility.isPubliclyAccessible).toBe(false);
      expect(visibility.httpStatus).toBe(403);
      expect(visibility.notice).toContain("cancelled by the organizer");
    });
  });

  describe("6. Event Creation & Mandatory Fields Validation", () => {
    it("should approve valid event configuration with complete fields", () => {
      const validation = validateEventCreation(baseEvent);
      expect(validation.isValid).toBe(true);
      expect(validation.errors).toHaveLength(0);
    });

    it("should reject event configuration with missing title, venue, or deadline", () => {
      const invalidEvent = {
        ...baseEvent,
        title: "",
        venueAddress: "",
        advanceOrderDeadline: "",
      };
      const validation = validateEventCreation(invalidEvent);
      expect(validation.isValid).toBe(false);
      expect(validation.errors).toContain("Title must be at least 3 characters");
      expect(validation.errors).toContain("Venue address is required");
      expect(validation.errors).toContain("Advance order deadline is required");
    });
  });
});
