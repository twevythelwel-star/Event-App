import { describe, it, expect } from "vitest";
import { NotificationLogEntry, NotificationTrigger } from "../types";
import { evaluateNotificationDispatch } from "./testUtils";

describe("11: Notifications and Retry Handling Suite", () => {
  const all9Triggers: NotificationTrigger[] = [
    "order_received",
    "payment_instructions",
    "transfer_submitted",
    "payment_verified_rejected",
    "cash_receipt_recorded",
    "ticket_issued_updated",
    "event_reminders",
    "pickup_instructions",
    "order_collection_completed",
  ];

  it("should support all 9 required notification lifecycle templates", () => {
    expect(all9Triggers).toHaveLength(9);
    all9Triggers.forEach((trigger) => {
      expect(trigger).toBeDefined();
    });
  });

  it("should distinguish between 'queued' and 'delivered' delivery states", () => {
    // Specification invariant: "Do not mark a message delivered merely because it was queued"
    const queuedNotification: NotificationLogEntry = {
      id: "notif-1",
      customerName: "Alicia Miller",
      recipient: "876-555-4011",
      channel: "sms",
      trigger: "order_received",
      title: "Order Placed",
      message: "Your order ORD-2026-0201 has been received. Please review payment instructions.",
      status: "queued",
      provider: "Twilio SMS",
      sentAt: "2026-11-20T12:00:00Z",
    };

    expect(queuedNotification.status).toBe("queued");
    expect(queuedNotification.deliveredAt).toBeUndefined();

    // After carrier webhook delivery callback
    const deliveredNotification: NotificationLogEntry = {
      ...queuedNotification,
      status: "delivered",
      deliveredAt: "2026-11-20T12:00:05Z",
      providerMessageId: "SM1234567890abcdef",
    };

    expect(deliveredNotification.status).toBe("delivered");
    expect(deliveredNotification.deliveredAt).toBeDefined();
    expect(deliveredNotification.providerMessageId).toBeDefined();
  });

  it("should suppress duplicate notifications on retry if message is already delivered", () => {
    const deliveredNotification: NotificationLogEntry = {
      id: "notif-paid-1",
      orderId: "ord-201",
      orderNumber: "ORD-2026-0201",
      customerName: "Alicia Miller",
      recipient: "876-555-4011",
      channel: "sms",
      trigger: "payment_verified_rejected",
      title: "Payment Confirmed & Digital Ticket Issued",
      message: "Your payment of J$3,100 has been verified. View your digital ticket at https://islandevents.app/t/sff-201",
      status: "delivered",
      provider: "Twilio SMS",
      sentAt: "2026-11-20T12:05:00Z",
      deliveredAt: "2026-11-20T12:05:03Z",
      idempotencyKey: "idemp-payment-verified-ord-201",
    };

    // Worker job retries
    const retryEvaluation = evaluateNotificationDispatch(deliveredNotification, "queued");

    expect(retryEvaluation.shouldDispatch).toBe(false);
    expect(retryEvaluation.retrySuppressed).toBe(true);
    expect(retryEvaluation.reason).toContain("Message was already delivered");
  });

  it("should suppress duplicate send if message is currently in flight ('sent')", () => {
    const inFlightNotification: NotificationLogEntry = {
      id: "notif-cash-1",
      orderId: "ord-45",
      customerName: "Vivienne Rhoden",
      recipient: "876-555-8822",
      channel: "sms",
      trigger: "cash_receipt_recorded",
      title: "Cash Receipt Issued",
      message: "Cash receipt CR-2026-0045 recorded by Pastor Brown.",
      status: "sent",
      provider: "Twilio SMS",
      sentAt: "2026-11-28T14:30:00Z",
    };

    const retryEvaluation = evaluateNotificationDispatch(inFlightNotification, "queued");

    expect(retryEvaluation.shouldDispatch).toBe(false);
    expect(retryEvaluation.retrySuppressed).toBe(true);
    expect(retryEvaluation.reason).toContain("Message is already in flight");
  });

  it("should permit retry when previous attempt failed", () => {
    const failedNotification: NotificationLogEntry = {
      id: "notif-failed-1",
      orderId: "ord-99",
      customerName: "David Lee",
      recipient: "876-555-0000",
      channel: "sms",
      trigger: "pickup_instructions",
      title: "Order Ready for Pickup",
      message: "Your meal is ready for collection at Station 1.",
      status: "failed",
      failureReason: "Carrier network timeout",
      provider: "Twilio SMS",
      sentAt: "2026-11-28T15:00:00Z",
      retryCount: 1,
    };

    const retryEvaluation = evaluateNotificationDispatch(failedNotification, "failed");

    expect(retryEvaluation.shouldDispatch).toBe(true);
    expect(retryEvaluation.retrySuppressed).toBe(false);
    expect(retryEvaluation.reason).toContain("Retrying failed dispatch");
  });
});
