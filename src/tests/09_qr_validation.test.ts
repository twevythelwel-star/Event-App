import { describe, it, expect } from "vitest";
import { validateTicketToken } from "./testUtils";

describe("09: QR Validation Suite", () => {
  type QRPayload = {
    ticketCode: string;
    token: string;
    orderNumber: string;
    organizerId: string;
    expiresAt?: string;
  };

  function parseAndValidateQRPayload(rawText: string): {
    isValid: boolean;
    payload?: QRPayload;
    reason?: string;
  } {
    let parsed: any;
    try {
      // Support both JSON string and URL param payload
      if (rawText.startsWith("{")) {
        parsed = JSON.parse(rawText);
      } else if (rawText.includes("?payload=")) {
        const url = new URL(rawText);
        const encoded = url.searchParams.get("payload");
        if (!encoded) return { isValid: false, reason: "Missing payload parameter in URL" };
        parsed = JSON.parse(decodeURIComponent(encoded));
      } else {
        return { isValid: false, reason: "Unrecognized QR format. Must be JSON or URL with payload." };
      }
    } catch {
      return { isValid: false, reason: "Malformed or corrupted QR code payload." };
    }

    if (!parsed.ticketCode || !parsed.token || !parsed.orderNumber || !parsed.organizerId) {
      return { isValid: false, reason: "Incomplete QR payload: Missing required ticket credentials." };
    }

    const tokenValidation = validateTicketToken(parsed.ticketCode, parsed.token);
    if (!tokenValidation.isValid) {
      return { isValid: false, reason: tokenValidation.reason };
    }

    if (parsed.expiresAt) {
      const exp = new Date(parsed.expiresAt).getTime();
      if (Date.now() > exp) {
        return { isValid: false, reason: "QR Code expired. Please refresh your digital ticket." };
      }
    }

    return { isValid: true, payload: parsed as QRPayload };
  }

  it("should successfully parse and validate a genuine JSON QR payload", () => {
    const validJsonQR = JSON.stringify({
      ticketCode: "SFF-1024",
      token: "tok-sha256-verified-signature-99120",
      orderNumber: "ORD-2026-1024",
      organizerId: "org-island-fish-fry",
    });

    const result = parseAndValidateQRPayload(validJsonQR);
    expect(result.isValid).toBe(true);
    expect(result.payload?.ticketCode).toBe("SFF-1024");
    expect(result.payload?.orderNumber).toBe("ORD-2026-1024");
  });

  it("should successfully parse and validate a URL query param QR payload", () => {
    const payloadObj = {
      ticketCode: "PCF-2005",
      token: "tok-pcf-signature-8812",
      orderNumber: "ORD-2026-2005",
      organizerId: "org-portmore-church",
    };
    const validUrlQR = `https://islandevents.app/scanner?payload=${encodeURIComponent(JSON.stringify(payloadObj))}`;

    const result = parseAndValidateQRPayload(validUrlQR);
    expect(result.isValid).toBe(true);
    expect(result.payload?.ticketCode).toBe("PCF-2005");
  });

  it("should reject corrupted or malformed non-JSON QR strings", () => {
    const corruptQR = "NOT_A_VALID_JSON_STRING{{{";
    const result = parseAndValidateQRPayload(corruptQR);
    expect(result.isValid).toBe(false);
    expect(result.reason).toContain("Unrecognized QR format");
  });

  it("should detect and reject tampered QR tokens with invalid prefix or signature", () => {
    const tamperedPayload = JSON.stringify({
      ticketCode: "SFF-1024",
      token: "forged-fake-token-without-prefix", // Missing "tok-" prefix
      orderNumber: "ORD-2026-1024",
      organizerId: "org-island-fish-fry",
    });

    const result = parseAndValidateQRPayload(tamperedPayload);
    expect(result.isValid).toBe(false);
    expect(result.reason).toContain("Malformed token signature");
  });

  it("should reject QR payload with unrecognized ticket code prefix", () => {
    const counterfeitPayload = JSON.stringify({
      ticketCode: "FAKE-9999", // Unregistered prefix
      token: "tok-valid-prefix-but-bad-ticket",
      orderNumber: "ORD-2026-9999",
      organizerId: "org-island-fish-fry",
    });

    const result = parseAndValidateQRPayload(counterfeitPayload);
    expect(result.isValid).toBe(false);
    expect(result.reason).toContain("Unrecognized ticket code prefix");
  });

  it("should reject expired QR payloads if timestamp is in the past", () => {
    const expiredPayload = JSON.stringify({
      ticketCode: "SFF-1024",
      token: "tok-valid-token-12345",
      orderNumber: "ORD-2026-1024",
      organizerId: "org-island-fish-fry",
      expiresAt: "2026-01-01T00:00:00Z", // Expired
    });

    const result = parseAndValidateQRPayload(expiredPayload);
    expect(result.isValid).toBe(false);
    expect(result.reason).toContain("QR Code expired");
  });
});
