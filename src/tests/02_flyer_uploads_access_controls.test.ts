import { describe, it, expect } from "vitest";
import { validateAndScopeFlyerUpload } from "./testUtils";

describe("02: Flyer Uploads and Access Controls Suite", () => {
  const tenantId = "org-kingston-youth";

  it("should allow Event Owner to upload valid image formats (JPG, PNG, WebP)", () => {
    const jpgUpload = validateAndScopeFlyerUpload(
      { name: "event-flyer.jpg", sizeBytes: 1024 * 1024, mimeType: "image/jpeg" },
      tenantId,
      "event_owner"
    );
    expect(jpgUpload.allowed).toBe(true);
    expect(jpgUpload.storagePath).toContain(`tenants/${tenantId}/flyers/`);
    expect(jpgUpload.storagePath).toMatch(/\.jpg$/);

    const pngUpload = validateAndScopeFlyerUpload(
      { name: "high-res-poster.png", sizeBytes: 2 * 1024 * 1024, mimeType: "image/png" },
      tenantId,
      "event_owner"
    );
    expect(pngUpload.allowed).toBe(true);
    expect(pngUpload.storagePath).toContain(`tenants/${tenantId}/flyers/`);

    const webpUpload = validateAndScopeFlyerUpload(
      { name: "compressed-banner.webp", sizeBytes: 500 * 1024, mimeType: "image/webp" },
      tenantId,
      "event_owner"
    );
    expect(webpUpload.allowed).toBe(true);
    expect(webpUpload.storagePath).toContain(`tenants/${tenantId}/flyers/`);
  });

  it("should allow Platform Admin global authority to upload flyers", () => {
    const adminUpload = validateAndScopeFlyerUpload(
      { name: "sponsored-banner.png", sizeBytes: 800 * 1024, mimeType: "image/png" },
      tenantId,
      "platform_admin"
    );
    expect(adminUpload.allowed).toBe(true);
    expect(adminUpload.storagePath).toContain(`tenants/${tenantId}/flyers/`);
  });

  it("should reject flyer uploads from unauthorized roles (food_collector, payment_verifier)", () => {
    const collectorAttempt = validateAndScopeFlyerUpload(
      { name: "new-poster.jpg", sizeBytes: 1024 * 1024, mimeType: "image/jpeg" },
      tenantId,
      "food_collector"
    );
    expect(collectorAttempt.allowed).toBe(false);
    expect(collectorAttempt.error).toContain("Only Event Owners or Platform Admins");

    const verifierAttempt = validateAndScopeFlyerUpload(
      { name: "new-poster.jpg", sizeBytes: 1024 * 1024, mimeType: "image/jpeg" },
      tenantId,
      "payment_verifier"
    );
    expect(verifierAttempt.allowed).toBe(false);
    expect(verifierAttempt.error).toContain("Only Event Owners or Platform Admins");
  });

  it("should reject prohibited MIME types and executable scripts", () => {
    const exeAttempt = validateAndScopeFlyerUpload(
      { name: "malicious_script.exe", sizeBytes: 50000, mimeType: "application/x-msdownload" },
      tenantId,
      "event_owner"
    );
    expect(exeAttempt.allowed).toBe(false);
    expect(exeAttempt.error).toContain("Invalid file type");

    const svgAttempt = validateAndScopeFlyerUpload(
      { name: "vector_graphic.svg", sizeBytes: 50000, mimeType: "image/svg+xml" },
      tenantId,
      "event_owner"
    );
    expect(svgAttempt.allowed).toBe(false);
    expect(svgAttempt.error).toContain("Invalid file type");
  });

  it("should enforce a strict 5MB upload size limit", () => {
    const oversizedFile = validateAndScopeFlyerUpload(
      { name: "giant_poster.png", sizeBytes: 6 * 1024 * 1024, mimeType: "image/png" }, // 6MB
      tenantId,
      "event_owner"
    );
    expect(oversizedFile.allowed).toBe(false);
    expect(oversizedFile.error).toContain("File size exceeds 5MB limit");
  });
});
