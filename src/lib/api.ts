import type { FishFryEventItem } from "../data/fishFryEvents";
import type {
  CashPaymentRecord,
  Currency,
  DisputeType,
  FulfillmentStatus,
  HandoverAuditEntry,
  MethodId,
  NotificationLogEntry,
  TicketDispute,
  TicketOrder,
} from "../types";

// Requests go to the same origin; in development Vite proxies /api to the Laravel server.
const API_BASE = "/api/v1";

export type SessionUser = {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  roles: string[];
  activeRole: string;
  organizerIds: string[];
};

export type OrderRequest = {
  customer_name: string;
  customer_phone: string;
  email?: string;
  method: MethodId;
  currency: Currency;
  payment_reference?: string;
  sender_name?: string;
  items: {
    menu_item_id: string;
    quantity: number;
    selected_choices?: Record<string, number>;
    custom_donation_amount?: number;
    special_instructions?: string;
  }[];
};

export class ApiError extends Error {
  constructor(message: string, public status: number) {
    super(message);
  }
}

function readXsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : null;
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  // File uploads (FormData) set their own multipart boundary
  if (init.body && !(init.body instanceof FormData)) headers.set("Content-Type", "application/json");

  // Laravel issues the XSRF-TOKEN cookie on every response and checks it on state-changing requests
  const xsrf = readXsrfToken();
  if (xsrf) headers.set("X-XSRF-TOKEN", xsrf);

  const response = await fetch(`${API_BASE}${path}`, { ...init, headers, credentials: "same-origin" });
  const body = response.status === 204 ? null : await response.json().catch(() => null);

  if (!response.ok) {
    const firstValidationError = body?.errors ? Object.values(body.errors as Record<string, string[]>)[0]?.[0] : null;
    throw new ApiError(body?.error || firstValidationError || body?.message || `Request failed (${response.status})`, response.status);
  }

  return body as T;
}

export async function fetchSession(): Promise<SessionUser | null> {
  const body = await request<{ user: SessionUser | null }>("/session");
  return body.user;
}

export async function signIn(email: string, password: string): Promise<SessionUser> {
  // Fetch the session first so a fresh XSRF-TOKEN cookie exists for the login POST
  await fetchSession();
  const body = await request<{ user: SessionUser }>("/session", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
  return body.user;
}

export async function signOut(): Promise<void> {
  await request("/session", { method: "DELETE" });
}

export async function fetchEvents(): Promise<FishFryEventItem[]> {
  const body = await request<{ data: FishFryEventItem[] }>("/events");
  return body.data;
}

export async function placeOrder(eventSlug: string, order: OrderRequest): Promise<TicketOrder> {
  const body = await request<{ data: TicketOrder }>(`/events/${encodeURIComponent(eventSlug)}/orders`, {
    method: "POST",
    body: JSON.stringify(order),
  });
  return body.data;
}

export async function fetchMyOrders(): Promise<TicketOrder[]> {
  const body = await request<{ data: TicketOrder[] }>("/orders");
  return body.data;
}

// ---- Host verification & event-day scanner -------------------------------------------------

export type ManualPaymentStatus = "under_verification" | "partially_paid" | "paid" | "rejected";

export type HandoverRequest = {
  quantity_delta: number;
  station?: string;
  action_type?: "collection" | "reversal" | "supervisor_override";
  override_reason?: string;
  supervisor_name?: string;
};

/** Orders the signed-in user can verify, scan or hand over (their organizers' events and staff assignments). */
export async function fetchOperationsOrders(): Promise<TicketOrder[]> {
  const body = await request<{ data: TicketOrder[] }>("/operations/orders");
  return body.data;
}

export async function lookupTicket(code: string): Promise<TicketOrder> {
  const body = await request<{ data: TicketOrder }>("/operations/lookup", {
    method: "POST",
    body: JSON.stringify({ code }),
  });
  return body.data;
}

export async function updatePaymentStatus(
  orderId: string,
  payload: { status: ManualPaymentStatus; amount_paid?: number; notes?: string; rejection_reason?: string }
): Promise<TicketOrder> {
  const body = await request<{ data: TicketOrder }>(`/operations/orders/${encodeURIComponent(orderId)}/payment`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return body.data;
}

export async function updateFulfillmentStatus(orderId: string, status: FulfillmentStatus): Promise<TicketOrder> {
  const body = await request<{ data: TicketOrder }>(`/operations/orders/${encodeURIComponent(orderId)}/fulfillment`, {
    method: "POST",
    body: JSON.stringify({ fulfillment_status: status }),
  });
  return body.data;
}

export async function recordHandover(
  orderId: string,
  itemId: string,
  payload: HandoverRequest
): Promise<{ order: TicketOrder; audit: HandoverAuditEntry }> {
  const body = await request<{ data: TicketOrder; audit: HandoverAuditEntry }>(
    `/operations/orders/${encodeURIComponent(orderId)}/items/${encodeURIComponent(itemId)}/handover`,
    { method: "POST", body: JSON.stringify(payload) }
  );
  return { order: body.data, audit: body.audit };
}

export async function closeOrder(orderId: string, staff?: { station?: string }): Promise<TicketOrder> {
  const body = await request<{ data: TicketOrder }>(`/operations/orders/${encodeURIComponent(orderId)}/close`, {
    method: "POST",
    body: JSON.stringify(staff ?? {}),
  });
  return body.data;
}

// ---- Cash ledger & refunds ---------------------------------------------------------------

export type CashReceiptRequest = {
  order_id?: string;
  event_id?: string;
  customer_name?: string;
  customer_phone?: string;
  amount: number;
  currency?: Currency;
  received_at?: string;
  received_from?: string;
  notes?: string;
};

export async function fetchCashLedger(): Promise<CashPaymentRecord[]> {
  const body = await request<{ data: CashPaymentRecord[] }>("/operations/cash");
  return body.data;
}

export async function recordCashReceipt(
  payload: CashReceiptRequest
): Promise<{ receipt: CashPaymentRecord; order: TicketOrder | null }> {
  return request("/operations/cash", { method: "POST", body: JSON.stringify(payload) });
}

export async function reverseCashReceipt(
  receiptId: string,
  payload: { amount: number; reason: string }
): Promise<{ reversal: CashPaymentRecord; order: TicketOrder | null }> {
  return request(`/operations/cash/${encodeURIComponent(receiptId)}/reverse`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function refundOrder(orderId: string, payload: { amount: number; reason: string }): Promise<TicketOrder> {
  const body = await request<{ data: TicketOrder }>(`/operations/orders/${encodeURIComponent(orderId)}/refund`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return body.data;
}

// ---- Event Host applications -------------------------------------------------------------

export type HostApplication = {
  id: number;
  organizationName: string;
  description: string | null;
  country: "JM" | "US";
  contactPhone: string;
  websiteOrSocial: string | null;
  status: "pending" | "approved" | "rejected";
  rejectionReason: string | null;
  reviewedAt: string | null;
  createdAt: string;
  // Administrator view only
  applicantName?: string;
  applicantEmail?: string;
  reviewedBy?: string | null;
  reviewNotes?: string | null;
};

export async function fetchMyHostApplications(): Promise<HostApplication[]> {
  const body = await request<{ data: HostApplication[] }>("/host-applications");
  return body.data;
}

export async function submitHostApplication(payload: {
  organization_name: string;
  description?: string;
  country: "JM" | "US";
  contact_phone: string;
  website_or_social?: string;
}): Promise<HostApplication> {
  const body = await request<{ data: HostApplication }>("/host-applications", {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return body.data;
}

export async function fetchAdminHostApplications(status: "pending" | "all" = "pending"): Promise<HostApplication[]> {
  const body = await request<{ data: HostApplication[] }>(`/admin/host-applications?status=${status}`);
  return body.data;
}

export async function approveHostApplication(id: number, notes?: string): Promise<HostApplication> {
  const body = await request<{ data: HostApplication }>(`/admin/host-applications/${id}/approve`, {
    method: "POST",
    body: JSON.stringify({ notes }),
  });
  return body.data;
}

export async function rejectHostApplication(id: number, reason: string, notes?: string): Promise<HostApplication> {
  const body = await request<{ data: HostApplication }>(`/admin/host-applications/${id}/reject`, {
    method: "POST",
    body: JSON.stringify({ reason, notes }),
  });
  return body.data;
}

// ---- Ticket disputes ---------------------------------------------------------------------

export async function fetchMyDisputes(): Promise<TicketDispute[]> {
  const body = await request<{ data: TicketDispute[] }>("/disputes");
  return body.data;
}

export async function submitDispute(payload: {
  order_id: string;
  dispute_type?: DisputeType;
  reason: string;
  amount?: number;
}): Promise<TicketDispute> {
  const body = await request<{ data: TicketDispute }>("/disputes", { method: "POST", body: JSON.stringify(payload) });
  return body.data;
}

export async function fetchAdminDisputes(): Promise<TicketDispute[]> {
  const body = await request<{ data: TicketDispute[] }>("/admin/disputes");
  return body.data;
}

export async function updateDisputeStatus(
  id: string,
  status: "under_review" | "resolved" | "rejected",
  notes?: string
): Promise<TicketDispute> {
  const body = await request<{ data: TicketDispute }>(`/admin/disputes/${encodeURIComponent(id)}/status`, {
    method: "POST",
    body: JSON.stringify({ status, notes }),
  });
  return body.data;
}

// ---- Event Host: own events & menus ------------------------------------------------------

/** snake_case event fields accepted by PATCH /host/events/{id} */
export type EventPatch = Partial<{
  title: string;
  tagline: string | null;
  date_str: string;
  time_str: string;
  venue_name: string;
  venue_address: string;
  coordinates: string | null;
  description: string | null;
  pickup_instructions: string | null;
  organizer_phone: string;
  organizer_whatsapp: string | null;
  organizer_email: string | null;
  packaging_fee_usd: number;
  packaging_fee_jmd: number;
  advance_order_deadline: string;
  is_ordering_open: boolean;
}>;

/** snake_case menu item fields accepted by the host menu endpoints */
export type MenuItemPayload = {
  name: string;
  category: string;
  description?: string | null;
  pricing_type: "fixed" | "customizable" | "custom_amount";
  price_usd: number;
  price_jmd: number;
  image_url?: string | null;
  custom_option_groups?: unknown[] | null;
  custom_amount_config?: Record<string, unknown> | null;
  allow_special_instructions?: boolean;
  available_stock?: number | null;
};

export async function fetchHostEvents(): Promise<FishFryEventItem[]> {
  const body = await request<{ data: FishFryEventItem[] }>("/host/events");
  return body.data;
}

export async function updateHostEvent(eventId: string, patch: EventPatch): Promise<FishFryEventItem> {
  const body = await request<{ data: FishFryEventItem }>(`/host/events/${encodeURIComponent(eventId)}`, {
    method: "PATCH",
    body: JSON.stringify(patch),
  });
  return body.data;
}

export async function setHostEventStatus(
  eventId: string,
  status: "draft" | "published" | "unpublished" | "cancelled"
): Promise<FishFryEventItem> {
  const body = await request<{ data: FishFryEventItem }>(`/host/events/${encodeURIComponent(eventId)}/status`, {
    method: "POST",
    body: JSON.stringify({ status }),
  });
  return body.data;
}

export async function createHostMenuItem(eventId: string, item: MenuItemPayload): Promise<FishFryEventItem> {
  const body = await request<{ data: FishFryEventItem }>(`/host/events/${encodeURIComponent(eventId)}/menu`, {
    method: "POST",
    body: JSON.stringify(item),
  });
  return body.data;
}

export async function updateHostMenuItem(
  eventId: string,
  itemId: string,
  item: Partial<MenuItemPayload>
): Promise<FishFryEventItem> {
  const body = await request<{ data: FishFryEventItem }>(
    `/host/events/${encodeURIComponent(eventId)}/menu/${encodeURIComponent(itemId)}`,
    { method: "PUT", body: JSON.stringify(item) }
  );
  return body.data;
}

export async function deleteHostMenuItem(eventId: string, itemId: string): Promise<FishFryEventItem> {
  const body = await request<{ data: FishFryEventItem }>(
    `/host/events/${encodeURIComponent(eventId)}/menu/${encodeURIComponent(itemId)}`,
    { method: "DELETE" }
  );
  return body.data;
}

// ---- Flyer & menu photo uploads ----------------------------------------------------------

/** Turns a browser data URL (from FileReader) back into an uploadable Blob. */
export async function dataUrlToBlob(dataUrl: string): Promise<Blob> {
  const response = await fetch(dataUrl);
  return response.blob();
}

function imageForm(field: string, image: Blob, filename: string): FormData {
  const form = new FormData();
  form.append(field, image, filename);
  return form;
}

export async function uploadHostFlyer(eventId: string, image: Blob, filename = "flyer"): Promise<FishFryEventItem> {
  const body = await request<{ data: FishFryEventItem }>(`/host/events/${encodeURIComponent(eventId)}/flyer`, {
    method: "POST",
    body: imageForm("flyer", image, filename),
  });
  return body.data;
}

export async function removeHostFlyer(eventId: string): Promise<FishFryEventItem> {
  const body = await request<{ data: FishFryEventItem }>(`/host/events/${encodeURIComponent(eventId)}/flyer`, {
    method: "DELETE",
  });
  return body.data;
}

export async function uploadHostMenuPhoto(
  eventId: string,
  itemId: string,
  image: Blob,
  filename = "photo"
): Promise<FishFryEventItem> {
  const body = await request<{ data: FishFryEventItem }>(
    `/host/events/${encodeURIComponent(eventId)}/menu/${encodeURIComponent(itemId)}/photo`,
    { method: "POST", body: imageForm("photo", image, filename) }
  );
  return body.data;
}

// ---- Customer notifications (SMS / email) ------------------------------------------------

/** Real messages sent for orders this user can handle payments for. */
export async function fetchMessageLog(): Promise<NotificationLogEntry[]> {
  const body = await request<{ data: NotificationLogEntry[] }>("/operations/notifications");
  return body.data;
}

export async function retryNotification(id: string): Promise<NotificationLogEntry> {
  const body = await request<{ data: NotificationLogEntry }>(`/operations/notifications/${encodeURIComponent(id)}/retry`, {
    method: "POST",
  });
  return body.data;
}

export async function sendEventReminders(eventId: string): Promise<number> {
  const body = await request<{ reminded: number }>(`/operations/events/${encodeURIComponent(eventId)}/reminders`, {
    method: "POST",
  });
  return body.reminded;
}
