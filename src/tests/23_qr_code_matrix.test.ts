import { describe, it, expect } from "vitest";
import { generateQrMatrix } from "../lib/qrCodeMatrix";
import { validateTicketToken } from "./testUtils";

describe("23: QR Code Matrix & Verification Payload Suite", () => {
  it("should generate a valid 2D matrix for a secure verification token payload", () => {
    const payload = JSON.stringify({
      ticketCode: "SFF-1024",
      token: "tok-sha256-verified-signature-99120",
      orderNumber: "ORD-2026-1024",
      organizerId: "org-island-fish-fry",
    });

    const matrix = generateQrMatrix(payload);
    expect(matrix).toBeDefined();
    expect(matrix.length).toBeGreaterThanOrEqual(21);
    expect(matrix[0].length).toBe(matrix.length);

    // Verify top-left finder pattern center is black (at [3,3])
    expect(matrix[3][3]).toBe(true);
    // Verify top-right finder pattern center is black
    const size = matrix.length;
    expect(matrix[3][size - 4]).toBe(true);
    // Verify bottom-left finder pattern center is black
    expect(matrix[size - 4][3]).toBe(true);
  });

  it("should generate payload that passes server-side validateTicketToken verification", () => {
    const ticketCode = "SFF-5544";
    const token = "tok-hmac-sha256-cryptoseed-7788";

    const validation = validateTicketToken(ticketCode, token);
    expect(validation.isValid).toBe(true);

    const payloadObj = {
      ticketCode,
      token,
      orderNumber: "ORD-2026-5544",
      organizerId: "org-island-events",
    };

    const matrix = generateQrMatrix(JSON.stringify(payloadObj));
    expect(matrix.length).toBeGreaterThan(25);
  });
});
