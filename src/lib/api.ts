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
  avatar_url?: string | null;
  bio?: string | null;
  marketing_preferences?: Record<string, boolean> | null;
  membership_status?: string;
  member_since?: string;
  orders_count?: number;
  unread_notifications_count?: number;
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
  idempotency_key?: string;
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

export async function registerAccount(data: {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  password: string;
  password_confirmation: string;
  agree_terms: boolean;
}): Promise<SessionUser> {
  await fetchSession();
  const body = await request<{ user: SessionUser }>("/register", {
    method: "POST",
    body: JSON.stringify(data),
  });
  return body.user;
}

export async function signOut(): Promise<void> {
  await request("/session", { method: "DELETE" });
}

export async function updateProfile(data: {
  name?: string;
  email?: string;
  phone?: string;
  avatar_url?: string;
  bio?: string;
  marketing_preferences?: Record<string, boolean>;
}): Promise<{ user: SessionUser; message: string }> {
  return await request<{ user: SessionUser; message: string }>("/profile", {
    method: "PUT",
    body: JSON.stringify(data),
  });
}

export async function updatePassword(data: {
  current_password: string;
  password: string;
  password_confirmation: string;
}): Promise<{ success: boolean; message: string }> {
  return await request<{ success: boolean; message: string }>("/profile/password", {
    method: "PUT",
    body: JSON.stringify(data),
  });
}

export type ApiNotificationItem = {
  id: string;
  title?: string;
  message?: string;
  type?: string;
  read_at?: string | null;
  readAt?: string | null;
  created_at?: string;
  data?: {
    title?: string;
    message?: string;
    type?: string;
  };
};

export async function fetchNotifications(): Promise<{
  data: any[];
  unread_count: number;
  unreadCount?: number;
}> {
  return await request<{ data: any[]; unread_count: number; unreadCount?: number }>("/notifications");
}

export async function markNotificationRead(id: string): Promise<void> {
  await request(`/notifications/${encodeURIComponent(id)}/read`, { method: "POST" });
}

export async function markAllNotificationsRead(): Promise<void> {
  await request("/notifications/read-all", { method: "POST" });
}

export async function fetchEvents(params?: { search?: string; category?: string }): Promise<FishFryEventItem[]> {
  const qs = new URLSearchParams();
  if (params?.search && params.search.trim() !== "") {
    qs.set("search", params.search.trim());
  }
  if (params?.category && params.category.trim() !== "" && params.category !== "All" && params.category !== "All Events") {
    qs.set("category", params.category.trim());
  }
  const queryString = qs.toString();
  const path = queryString ? `/events?${queryString}` : "/events";
  const body = await request<{ data: FishFryEventItem[] }>(path);
  return body.data;
}

export type LocationCatalog = {
  country: string;
  countryCode: string;
  states: Array<{ code: string; name: string; default_timezone: string; is_state_or_dc: boolean }>;
  markets: Array<{
    id: string;
    city: string;
    stateCode: string;
    stateName: string;
    timezone: string;
    zipCodes: string[];
    featured: boolean;
    priority: number;
    publishedEvents: number;
    participatingHosts: number;
  }>;
};

export async function fetchLocationCatalog(): Promise<LocationCatalog> {
  return request<LocationCatalog>("/api/v1/locations");
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

export async function fetchMessageLog(): Promise<NotificationLogEntry[]> {
  const body = await request<{ data: NotificationLogEntry[] }>("/operations/notifications");
  return body.data;
}

export async function retryNotification(logId: string): Promise<NotificationLogEntry> {
  const body = await request<{ data: NotificationLogEntry }>(`/operations/notifications/${encodeURIComponent(logId)}/retry`, {
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
  street_address: string | null;
  venue_building_name: string | null;
  city: string | null;
  state_code: string | null;
  zip_code: string | null;
  country_code: string | null;
  latitude: number | null;
  longitude: number | null;
  target_market_id: string | null;
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
  category: string | null;
  event_date_iso: string | null;
  start_time: string | null;
  end_time: string | null;
  timezone: string | null;
  currency: "JMD" | "USD";
  organizer_name: string | null;
  supports_food_ordering: boolean;
  ticket_tiers: unknown[] | null;
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

export async function createHostEvent(payload: EventPatch): Promise<FishFryEventItem> {
  const body = await request<{ data: FishFryEventItem }>("/host/events", {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return body.data;
}

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

// ---- Host Dashboard Overview & Staff / Worker Assignments --------------------------------

export type HostDashboardSummaryResponse = {
  dashboard: string;
  host_name: string;
  organizer_id: string | null;
  role_on_event: string;
  total_events: number;
  total_orders: number;
  paid_orders_count: number;
  pending_verification_count: number;
  cash_receipts_total: number;
  gross_volume_jmd: number;
  verified_payments_jmd: number;
  confirmed_payments_jmd?: number;
  pending_payments_jmd?: number;
  forecast_revenue_jmd?: number;
  refunds_total_jmd?: number;
  settled_funds_jmd?: number;
  sold_portions: number;
  prep_with_buffer: number;
  buffer_percent: number;
  active_workers_count: number;
};

export type BackendStaffAssignment = {
  id: string;
  event_id: string;
  user_id: string;
  role_on_event: "payment_clerk" | "ticket_checker" | "food_station_worker" | "supervisor" | string;
  assigned_stations?: string[];
  status?: string;
  is_active: boolean;
  user?: {
    id: string;
    name: string;
    email?: string;
    phone?: string;
  };
};

export async function fetchHostDashboardSummary(): Promise<HostDashboardSummaryResponse> {
  return request<HostDashboardSummaryResponse>("/host/dashboard");
}

export async function fetchHostEventStaff(eventId: string): Promise<{
  success: boolean;
  assignments: BackendStaffAssignment[];
}> {
  return request<{ success: boolean; assignments: BackendStaffAssignment[] }>(
    `/host/events/${encodeURIComponent(eventId)}/staff`
  );
}

export async function verifyStaffClaimCode(
  eventId: string,
  claimCode: string
): Promise<{
  valid: boolean;
  candidate: {
    user_id: string;
    name: string;
    masked_email: string;
    masked_phone?: string | null;
    account_status: string;
    expires_at: string;
  };
}> {
  return request(`/host/events/${encodeURIComponent(eventId)}/staff/verify-code`, {
    method: "POST",
    body: JSON.stringify({ claim_code: claimCode }),
  });
}

export async function assignEventStaff(
  eventId: string,
  payload: {
    claim_code: string;
    role_on_event: "payment_clerk" | "ticket_checker" | "food_station_worker" | "supervisor";
    assigned_stations?: string[];
  }
): Promise<{
  success: boolean;
  message: string;
  assignment: {
    id: string;
    staff_name: string;
    role_on_event: string;
    assigned_stations: string[];
    status: string;
  };
}> {
  return request(`/host/events/${encodeURIComponent(eventId)}/staff/assign`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function revokeEventStaffAssignment(
  eventId: string,
  assignmentId: string
): Promise<{ success: boolean; message: string }> {
  return request(
    `/host/events/${encodeURIComponent(eventId)}/staff/${encodeURIComponent(assignmentId)}/revoke`,
    { method: "POST" }
  );
}

export async function generateStaffClaimCode(): Promise<{
  success: boolean;
  code: string;
  expires_at: string;
  expires_in_minutes: number;
}> {
  return request("/staff-codes/generate", { method: "POST" });
}
