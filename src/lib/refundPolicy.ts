import type { EventDetails } from "../types";

export function refundPolicyPreviewLines(event: Partial<EventDetails>): string[] {
  const mode = event.refundPolicyMode || "manual_full_and_partial";
  const refundLine = mode === "non_refundable"
    ? "This event is marked non-refundable."
    : mode === "manual_full_only"
      ? "Organizers may record full manual refunds for supported payment methods; partial refunds are not offered."
      : "Organizers may record full or partial manual refunds for supported payment methods.";
  const hostCancellation = event.hostCancellationPolicy === "manual_refund_if_supported"
    ? "If the organizer cancels: a manual refund may be recorded only for a supported payment method."
    : "If the organizer cancels: contact the organizer; no automatic refund is promised.";
  const postponement = event.postponementPolicy === "tickets_valid_for_new_date"
    ? "If postponed or rescheduled: existing tickets remain valid for the new date."
    : "If postponed or rescheduled: contact the organizer for next steps.";
  const weather = event.weatherCancellationPolicy === "reschedule_tickets_valid"
    ? "For weather cancellation: the event will be rescheduled and existing tickets remain valid."
    : event.weatherCancellationPolicy === "manual_refund_if_supported"
      ? "For weather cancellation: a manual refund may be recorded only for a supported payment method."
      : "For weather cancellation: contact the organizer; no automatic refund is promised.";
  const lines = [refundLine];

  if (event.refundPolicyDeadlineHours != null) {
    lines.push(`Refund requests close ${event.refundPolicyDeadlineHours} hours before the event starts.`);
  }
  lines.push(hostCancellation, postponement, weather);
  if (event.foodOrderCancellationDeadlineHours != null) {
    lines.push(`Food cancellation requests close ${event.foodOrderCancellationDeadlineHours} hours before the event starts.`);
  }
  if (event.foodOrderModificationDeadlineHours != null) {
    lines.push(`Food changes cannot be made in EventBite; contact the organizer at least ${event.foodOrderModificationDeadlineHours} hours before the event starts.`);
  }
  if (event.foodOrderCancellationDeadlineHours != null && event.refundPolicyDeadlineHours != null) {
    lines.push("If multiple cancellation cutoffs apply, the earliest deadline controls.");
  }
  if (event.nonRefundableTicketCategories?.length) {
    lines.push(`Non-refundable ticket types: ${event.nonRefundableTicketCategories.join(", ")}.`);
  }
  if (event.refundPolicyNotes?.trim()) lines.push(event.refundPolicyNotes.trim());
  lines.push("EventBite cannot process provider-backed refunds. A cancellation request or approval is not a completed refund; the organizer must complete any supported manual refund and record its reference.");
  return lines;
}