import {
  EventDetails,
  EventEmergencyContact,
  EventEscalationContact,
  EventOperationsConfig,
  EventOperationStation,
  EventStaffActivityEntry,
  EventStaffMemberAssignment,
  EventStaffShiftSchedule,
  EventWorkerRoleKey,
  TenantRole,
  TenantUserMember,
} from "../types";

export type WorkerPermissionKey =
  | "scan_qr_tickets"
  | "validate_ticket_status"
  | "record_entry"
  | "verify_vip_admission"
  | "view_assigned_food_items"
  | "view_collection_instructions"
  | "mark_quantities_collected"
  | "see_remaining_quantities"
  | "view_relevant_orders"
  | "record_cash_received"
  | "review_transfer_references"
  | "verify_payments"
  | "oversee_assigned_operations"
  | "view_relevant_reports"
  | "manage_permitted_worker_assignments";

export const ALL_WORKER_PERMISSIONS: {
  key: WorkerPermissionKey;
  label: string;
  category: "Ticket Verification" | "Food Stations" | "Payment Collection" | "Supervision";
}[] = [
  { key: "scan_qr_tickets", label: "Scan QR tickets", category: "Ticket Verification" },
  { key: "validate_ticket_status", label: "Validate ticket status", category: "Ticket Verification" },
  { key: "record_entry", label: "Record attendee gate entry", category: "Ticket Verification" },
  { key: "verify_vip_admission", label: "Verify VIP & priority tier admission", category: "Ticket Verification" },
  { key: "view_assigned_food_items", label: "View assigned food station items", category: "Food Stations" },
  { key: "view_collection_instructions", label: "View meal prep & collection instructions", category: "Food Stations" },
  { key: "mark_quantities_collected", label: "Mark meal portions collected", category: "Food Stations" },
  { key: "see_remaining_quantities", label: "View remaining inventory & uncollected portions", category: "Food Stations" },
  { key: "view_relevant_orders", label: "View order payment status & references", category: "Payment Collection" },
  { key: "record_cash_received", label: "Record authorized on-site cash receipts", category: "Payment Collection" },
  { key: "review_transfer_references", label: "Review bank/Zelle/Cash App transfer references", category: "Payment Collection" },
  { key: "verify_payments", label: "Verify submitted payments (subject to separation of duties)", category: "Payment Collection" },
  { key: "oversee_assigned_operations", label: "Event-wide operational oversight & override", category: "Supervision" },
  { key: "view_relevant_reports", label: "View event check-in & station fulfillment reports", category: "Supervision" },
  { key: "manage_permitted_worker_assignments", label: "Manage non-supervisor worker station assignments", category: "Supervision" },
];

export type WorkerRoleSpec = {
  role: EventWorkerRoleKey;
  label: string;
  tenantRoleEquivalent: TenantRole;
  badgeColor: string;
  badgeBg: string;
  defaultStationType: EventOperationStation["stationType"];
  defaultPermissions: WorkerPermissionKey[];
  prohibitedActions: string[];
  canDoSummary: string;
  mustNotDoSummary: string;
};

export const EVENT_WORKER_ROLE_SPECS: Record<EventWorkerRoleKey, WorkerRoleSpec> = {
  ticket_checker: {
    role: "ticket_checker",
    label: "Ticket Checker",
    tenantRoleEquivalent: "food_collector",
    badgeColor: "#818CF8",
    badgeBg: "rgba(99, 102, 241, 0.18)",
    defaultStationType: "ticket_checking",
    defaultPermissions: [
      "scan_qr_tickets",
      "validate_ticket_status",
      "record_entry",
      "verify_vip_admission",
    ],
    prohibitedActions: [
      "record_cash_received",
      "verify_payments",
      "review_transfer_references",
      "view_payment_pii",
      "issue_refunds",
      "change_ticket_prices",
      "change_menu_prices",
      "manage_permitted_worker_assignments",
    ],
    canDoSummary: "Scan QR tickets, validate ticket status, verify VIP admission, and record gate entry.",
    mustNotDoSummary: "Cannot view customer payment PII, record cash, verify bank transfers, issue refunds, or alter prices.",
  },
  food_station_worker: {
    role: "food_station_worker",
    label: "Food Station Worker",
    tenantRoleEquivalent: "food_collector",
    badgeColor: "#38BDF8",
    badgeBg: "rgba(56, 189, 248, 0.18)",
    defaultStationType: "food_station",
    defaultPermissions: [
      "view_assigned_food_items",
      "view_collection_instructions",
      "mark_quantities_collected",
      "see_remaining_quantities",
    ],
    prohibitedActions: [
      "record_cash_received",
      "verify_payments",
      "review_transfer_references",
      "view_payment_pii",
      "access_financial_reports",
      "change_menu_prices",
      "change_ticket_prices",
      "issue_refunds",
      "manage_permitted_worker_assignments",
    ],
    canDoSummary: "View assigned station meals, update kitchen preparation stages, and mark portions collected.",
    mustNotDoSummary: "Cannot view financial reports, record cash, verify payments, or change menu prices.",
  },
  payment_clerk: {
    role: "payment_clerk",
    label: "Payment Clerk",
    tenantRoleEquivalent: "payment_verifier",
    badgeColor: "#FBBF24",
    badgeBg: "rgba(245, 158, 11, 0.18)",
    defaultStationType: "payment_desk",
    defaultPermissions: [
      "view_relevant_orders",
      "record_cash_received",
      "review_transfer_references",
      "verify_payments",
    ],
    prohibitedActions: [
      "change_ticket_prices",
      "change_menu_prices",
      "manage_permitted_worker_assignments",
      "change_event_ownership",
      "delete_orders",
    ],
    canDoSummary: "Record cash received at Payment Desk, review transfer references, and verify payments with audit logging.",
    mustNotDoSummary: "Cannot self-approve unverified cash without audit, change prices, or manage worker assignments.",
  },
  supervisor: {
    role: "supervisor",
    label: "Event Supervisor",
    tenantRoleEquivalent: "event_owner",
    badgeColor: "#34D399",
    badgeBg: "rgba(16, 185, 129, 0.18)",
    defaultStationType: "supervisor",
    defaultPermissions: [
      "oversee_assigned_operations",
      "view_relevant_reports",
      "manage_permitted_worker_assignments",
      "scan_qr_tickets",
      "validate_ticket_status",
      "record_entry",
      "verify_vip_admission",
      "view_assigned_food_items",
      "mark_quantities_collected",
      "see_remaining_quantities",
      "view_relevant_orders",
      "verify_payments",
    ],
    prohibitedActions: [
      "appoint_supervisor",
      "change_event_ownership",
      "access_other_hosts_events",
      "modify_global_platform_fees",
    ],
    canDoSummary: "Event-wide oversight, station reassignments, exception overrides, and operational reporting.",
    mustNotDoSummary: "Cannot appoint additional Supervisors (privilege escalation blocked) or access other hosts' events.",
  },
  custom_restricted_worker: {
    role: "custom_restricted_worker",
    label: "Custom Restricted Worker",
    tenantRoleEquivalent: "food_collector",
    badgeColor: "#C084FC",
    badgeBg: "rgba(192, 132, 252, 0.18)",
    defaultStationType: "custom",
    defaultPermissions: ["scan_qr_tickets", "validate_ticket_status"],
    prohibitedActions: [
      "change_event_ownership",
      "change_system_settings",
      "access_unrelated_events",
      "alter_financials_without_audit",
      "appoint_supervisor",
    ],
    canDoSummary: "Operates strictly on explicitly whitelisted station responsibilities granted by the Event Host.",
    mustNotDoSummary: "Cannot perform any action outside their explicitly checked permission list.",
  },
};

/**
 * Canonical default event-day operations plan stations matching the EventBite specification:
 * - Entrance A -> Ticket verification
 * - VIP Entrance -> VIP ticket verification
 * - Food Station 1 -> Main meals
 * - Food Station 2 -> Drinks and desserts
 * - Payment Desk -> Authorized cash/payment verification
 * - Supervisor -> Event-wide oversight
 */
export const DEFAULT_OPERATIONS_STATIONS: EventOperationStation[] = [
  {
    id: "stn-entrance-a",
    stationName: "Entrance A",
    stationType: "ticket_checking",
    assignedResponsibility: "Ticket verification",
    assignedStaffNames: ["Jerome Williams"],
    operatingHours: "11:30 AM – 8:00 PM",
    notes: "Primary general admission QR scanning lane.",
  },
  {
    id: "stn-vip-entrance",
    stationName: "VIP Entrance",
    stationType: "ticket_checking",
    assignedResponsibility: "VIP ticket verification",
    assignedStaffNames: ["Camille Pantry"],
    operatingHours: "11:30 AM – 8:00 PM",
    notes: "Fast-track VIP wristband & cabana pass verification.",
  },
  {
    id: "stn-food-1",
    stationName: "Food Station 1",
    stationType: "food_station",
    assignedResponsibility: "Main meals",
    assignedStaffNames: ["Devon Campbell"],
    operatingHours: "12:00 PM – 7:30 PM",
    notes: "Escovitch snapper, jerk chicken, curry goat & hot platters.",
  },
  {
    id: "stn-food-2",
    stationName: "Food Station 2",
    stationType: "food_station",
    assignedResponsibility: "Drinks and desserts",
    assignedStaffNames: ["Keisha Brown"],
    operatingHours: "12:00 PM – 8:00 PM",
    notes: "Chilled sorrel, tropical smoothies, rum punch & sweet festival sides.",
  },
  {
    id: "stn-payment-desk",
    stationName: "Payment Desk",
    stationType: "payment_desk",
    assignedResponsibility: "Authorized cash/payment verification",
    assignedStaffNames: ["Beverly Sterling"],
    operatingHours: "11:30 AM – 7:30 PM",
    notes: "Authorized cash collection, receipt issuance & transfer reference verification.",
  },
  {
    id: "stn-supervisor",
    stationName: "Supervisor",
    stationType: "supervisor",
    assignedResponsibility: "Event-wide oversight",
    assignedStaffNames: ["Marcus Garvey Jr."],
    operatingHours: "10:30 AM – 9:00 PM",
    notes: "All-station oversight, escalation resolution & shift handovers.",
  },
];

/**
 * Sanitizes a list of requested permissions for a given role, stripping any prohibited actions.
 */
export function sanitizePermissionsForRole(
  role: EventWorkerRoleKey,
  requestedPermissions?: string[]
): WorkerPermissionKey[] {
  const spec = EVENT_WORKER_ROLE_SPECS[role] || EVENT_WORKER_ROLE_SPECS.food_station_worker;
  const base =
    requestedPermissions && requestedPermissions.length > 0
      ? requestedPermissions
      : spec.defaultPermissions;

  const validKeys = new Set<string>(ALL_WORKER_PERMISSIONS.map((p) => p.key));
  const prohibited = new Set<string>(spec.prohibitedActions);

  return base.filter(
    (perm): perm is WorkerPermissionKey => validKeys.has(perm) && !prohibited.has(perm)
  );
}

/**
 * Checks whether a staff assignment is permitted to perform a specific operational action
 * at the given reference timestamp, enforcing both role RBAC and time-limited shift boundaries.
 */
export function evaluateStaffTimeAccess(
  assignment: Pick<
    EventStaffMemberAssignment,
    "status" | "shiftStartsAt" | "shiftExpiresAt" | "roleOnEvent" | "individualPermissions"
  >,
  referenceTimeIso?: string
): {
  isCurrentlyAuthorized: boolean;
  effectiveStatus: "active" | "scheduled" | "expired" | "revoked" | "pending_acceptance";
  reason: string;
} {
  if (assignment.status === "revoked") {
    return {
      isCurrentlyAuthorized: false,
      effectiveStatus: "revoked",
      reason: "Access Denied: Staff assignment has been revoked.",
    };
  }

  if (assignment.status === "pending_acceptance") {
    return {
      isCurrentlyAuthorized: false,
      effectiveStatus: "pending_acceptance",
      reason: "Access Denied: Staff assignment is awaiting recipient acceptance.",
    };
  }

  const nowMs = referenceTimeIso ? new Date(referenceTimeIso).getTime() : Date.now();
  const startMs = assignment.shiftStartsAt ? new Date(assignment.shiftStartsAt).getTime() : NaN;
  const endMs = assignment.shiftExpiresAt ? new Date(assignment.shiftExpiresAt).getTime() : NaN;

  if (!Number.isNaN(startMs) && !Number.isNaN(endMs) && startMs >= endMs) {
    return {
      isCurrentlyAuthorized: false,
      effectiveStatus: "expired",
      reason: "Invalid shift window: Start time must precede expiration time.",
    };
  }

  if (!Number.isNaN(startMs) && nowMs < startMs) {
    return {
      isCurrentlyAuthorized: false,
      effectiveStatus: "scheduled",
      reason: `Scheduled shift has not started yet (starts ${assignment.shiftStartsAt}).`,
    };
  }

  if (!Number.isNaN(endMs) && nowMs > endMs) {
    return {
      isCurrentlyAuthorized: false,
      effectiveStatus: "expired",
      reason: `Staff assignment expired at ${assignment.shiftExpiresAt}.`,
    };
  }

  return {
    isCurrentlyAuthorized: true,
    effectiveStatus: "active",
    reason: "Active within authorized shift window.",
  };
}

/**
 * Validates whether an actor role (`host`, `admin`, or `supervisor`) can assign the target worker role.
 * Enforces the anti-escalation invariant: Supervisors cannot appoint other Supervisors.
 */
export function canActorAssignWorkerRole(
  actorRole: "event_owner" | "platform_admin" | "supervisor" | TenantRole,
  targetWorkerRole: EventWorkerRoleKey
): { allowed: boolean; error?: string } {
  if (actorRole === "payment_verifier" || actorRole === "food_collector") {
    return {
      allowed: false,
      error: "Unauthorized: Only Event Hosts, Platform Admins, or active Event Supervisors can assign staff.",
    };
  }
  if (actorRole === "supervisor" && targetWorkerRole === "supervisor") {
    return {
      allowed: false,
      error: "Privilege Escalation Blocked: Event Supervisors cannot appoint additional Event Supervisors.",
    };
  }
  return { allowed: true };
}

/**
 * Checks whether a staff assignment can perform a specific permission key right now.
 */
export function canStaffAssignmentPerform(
  assignment: Pick<
    EventStaffMemberAssignment,
    "status" | "shiftStartsAt" | "shiftExpiresAt" | "roleOnEvent" | "individualPermissions"
  >,
  action: WorkerPermissionKey | string,
  referenceTimeIso?: string
): boolean {
  const timeAccess = evaluateStaffTimeAccess(assignment, referenceTimeIso);
  if (!timeAccess.isCurrentlyAuthorized) {
    return false;
  }

  const spec = EVENT_WORKER_ROLE_SPECS[assignment.roleOnEvent];
  if (!spec) return false;

  if (spec.prohibitedActions.includes(action)) {
    return false;
  }

  const effectivePerms = sanitizePermissionsForRole(
    assignment.roleOnEvent,
    assignment.individualPermissions
  );
  return effectivePerms.includes(action as WorkerPermissionKey);
}

/**
 * Builds the default EventOperationsConfig for an event when not yet initialized.
 */
export function getDefaultEventOperationsConfig(
  eventDetails?: Partial<EventDetails>,
  tenantUsers: TenantUserMember[] = []
): EventOperationsConfig {
  if (eventDetails?.operationsConfig) {
    return eventDetails.operationsConfig;
  }

  const eventDate = eventDetails?.eventDateIso || "2026-11-22";
  const tz = eventDetails?.timezone || "America/New_York";

  const defaultStaff: EventStaffMemberAssignment[] = [
    {
      id: "staff-assign-1",
      userId: "usr-jerome",
      name: "Jerome Williams",
      email: "je***@islandevents.example",
      phone: "+1 (305) 555-0148",
      claimCode: "JMA774",
      roleOnEvent: "ticket_checker",
      stationId: "stn-entrance-a",
      stationName: "Entrance A",
      assignedResponsibility: "Ticket verification",
      individualPermissions: sanitizePermissionsForRole("ticket_checker"),
      shiftStartsAt: `${eventDate}T11:00`,
      shiftExpiresAt: `${eventDate}T20:30`,
      timezone: tz,
      status: "active",
      checkInStatus: "checked_in",
      checkedInAt: "11:04 AM",
      assignedBy: eventDetails?.organizerName || "Event Host",
    },
    {
      id: "staff-assign-2",
      userId: "usr-camille",
      name: "Camille Pantry",
      email: "ca***@islandevents.example",
      phone: "+1 (305) 555-0182",
      claimCode: "VIP204",
      roleOnEvent: "ticket_checker",
      stationId: "stn-vip-entrance",
      stationName: "VIP Entrance",
      assignedResponsibility: "VIP ticket verification",
      individualPermissions: sanitizePermissionsForRole("ticket_checker"),
      shiftStartsAt: `${eventDate}T11:30`,
      shiftExpiresAt: `${eventDate}T20:30`,
      timezone: tz,
      status: "active",
      checkInStatus: "checked_in",
      checkedInAt: "11:25 AM",
      assignedBy: eventDetails?.organizerName || "Event Host",
    },
    {
      id: "staff-assign-3",
      userId: "usr-devon",
      name: "Devon Campbell",
      email: "de***@islandevents.example",
      phone: "+1 (305) 555-0191",
      claimCode: "KGN842",
      roleOnEvent: "food_station_worker",
      stationId: "stn-food-1",
      stationName: "Food Station 1",
      assignedResponsibility: "Main meals",
      individualPermissions: sanitizePermissionsForRole("food_station_worker"),
      shiftStartsAt: `${eventDate}T11:30`,
      shiftExpiresAt: `${eventDate}T20:00`,
      timezone: tz,
      status: "active",
      checkInStatus: "checked_in",
      checkedInAt: "11:28 AM",
      assignedBy: eventDetails?.organizerName || "Event Host",
    },
    {
      id: "staff-assign-4",
      userId: "usr-keisha",
      name: "Keisha Brown",
      email: "ke***@islandevents.example",
      phone: "+1 (305) 555-0166",
      claimCode: "DRK512",
      roleOnEvent: "food_station_worker",
      stationId: "stn-food-2",
      stationName: "Food Station 2",
      assignedResponsibility: "Drinks and desserts",
      individualPermissions: sanitizePermissionsForRole("food_station_worker"),
      shiftStartsAt: `${eventDate}T11:30`,
      shiftExpiresAt: `${eventDate}T20:00`,
      timezone: tz,
      status: "active",
      checkInStatus: "not_checked_in",
      assignedBy: eventDetails?.organizerName || "Event Host",
    },
    {
      id: "staff-assign-5",
      userId: "usr-beverly",
      name: "Beverly Sterling",
      email: "be***@islandevents.example",
      phone: "+1 (305) 555-0139",
      claimCode: "SFF109",
      roleOnEvent: "payment_clerk",
      stationId: "stn-payment-desk",
      stationName: "Payment Desk",
      assignedResponsibility: "Authorized cash/payment verification",
      individualPermissions: sanitizePermissionsForRole("payment_clerk"),
      shiftStartsAt: `${eventDate}T11:30`,
      shiftExpiresAt: `${eventDate}T19:30`,
      timezone: tz,
      status: "active",
      checkInStatus: "checked_in",
      checkedInAt: "11:20 AM",
      assignedBy: eventDetails?.organizerName || "Event Host",
    },
    {
      id: "staff-assign-6",
      userId: "usr-marcus",
      name: "Marcus Garvey Jr.",
      email: "ma***@islandevents.example",
      phone: "+1 (305) 555-0110",
      claimCode: "SUP901",
      roleOnEvent: "supervisor",
      stationId: "stn-supervisor",
      stationName: "Supervisor",
      assignedResponsibility: "Event-wide oversight",
      individualPermissions: sanitizePermissionsForRole("supervisor"),
      shiftStartsAt: `${eventDate}T10:30`,
      shiftExpiresAt: `${eventDate}T21:30`,
      timezone: tz,
      status: "active",
      checkInStatus: "checked_in",
      checkedInAt: "10:32 AM",
      assignedBy: eventDetails?.organizerName || "Event Host",
    },
  ];

  // Merge any additional tenantUsers not already represented
  tenantUsers.forEach((tu, idx) => {
    if (!defaultStaff.some((s) => s.name.toLowerCase() === tu.name.toLowerCase())) {
      const mappedRole: EventWorkerRoleKey =
        tu.role === "payment_verifier"
          ? "payment_clerk"
          : tu.role === "event_owner" || tu.role === "platform_admin"
          ? "supervisor"
          : "food_station_worker";
      const targetStation =
        mappedRole === "payment_clerk"
          ? DEFAULT_OPERATIONS_STATIONS[4]
          : mappedRole === "supervisor"
          ? DEFAULT_OPERATIONS_STATIONS[5]
          : DEFAULT_OPERATIONS_STATIONS[2];

      defaultStaff.push({
        id: `staff-tenant-${tu.id || idx}`,
        userId: tu.id,
        name: tu.name,
        email: tu.email,
        roleOnEvent: mappedRole,
        stationId: targetStation.id,
        stationName: targetStation.stationName,
        assignedResponsibility: targetStation.assignedResponsibility,
        individualPermissions: sanitizePermissionsForRole(mappedRole),
        shiftStartsAt: `${eventDate}T11:30`,
        shiftExpiresAt: `${eventDate}T20:30`,
        timezone: tz,
        status: tu.isActive ? "active" : "revoked",
        checkInStatus: tu.isActive ? "checked_in" : "not_checked_in",
        checkedInAt: tu.isActive ? "11:30 AM" : undefined,
        assignedBy: eventDetails?.organizerName || "Event Host",
      });
    }
  });

  const schedules: EventStaffShiftSchedule[] = [
    {
      id: "sched-1",
      shiftName: "Wave 1 — Gate Setup & Early Bird Admission",
      startTime: "11:00",
      endTime: "15:30",
      stationName: "Entrance A",
      assignedStaffName: "Jerome Williams",
      roleOnEvent: "ticket_checker",
      responsibility: "Ticket verification",
    },
    {
      id: "sched-2",
      shiftName: "Wave 1 — VIP Cabana & Fast-Track Check-In",
      startTime: "11:30",
      endTime: "16:00",
      stationName: "VIP Entrance",
      assignedStaffName: "Camille Pantry",
      roleOnEvent: "ticket_checker",
      responsibility: "VIP ticket verification",
    },
    {
      id: "sched-3",
      shiftName: "Lunch & Main Service — Hot Kitchen Pass",
      startTime: "12:00",
      endTime: "18:30",
      stationName: "Food Station 1",
      assignedStaffName: "Devon Campbell",
      roleOnEvent: "food_station_worker",
      responsibility: "Main meals",
    },
    {
      id: "sched-4",
      shiftName: "Afternoon & Sunset — Beverage & Dessert Bar",
      startTime: "12:00",
      endTime: "20:00",
      stationName: "Food Station 2",
      assignedStaffName: "Keisha Brown",
      roleOnEvent: "food_station_worker",
      responsibility: "Drinks and desserts",
    },
    {
      id: "sched-5",
      shiftName: "All-Day Box Office — Cash & Transfer Audit",
      startTime: "11:30",
      endTime: "19:30",
      stationName: "Payment Desk",
      assignedStaffName: "Beverly Sterling",
      roleOnEvent: "payment_clerk",
      responsibility: "Authorized cash/payment verification",
    },
    {
      id: "sched-6",
      shiftName: "Full Event Command — Floor & Station Supervision",
      startTime: "10:30",
      endTime: "21:30",
      stationName: "Supervisor",
      assignedStaffName: "Marcus Garvey Jr.",
      roleOnEvent: "supervisor",
      responsibility: "Event-wide oversight",
    },
  ];

  const activityLogs: EventStaffActivityEntry[] = [
    {
      id: "act-1",
      timestamp: "10:32 AM",
      staffId: "staff-assign-6",
      staffName: "Marcus Garvey Jr.",
      roleOnEvent: "supervisor",
      stationName: "Supervisor",
      actionType: "staff_check_in",
      summary: "Checked in for Event-wide oversight shift and verified all 6 station terminals.",
    },
    {
      id: "act-2",
      timestamp: "11:04 AM",
      staffId: "staff-assign-1",
      staffName: "Jerome Williams",
      roleOnEvent: "ticket_checker",
      stationName: "Entrance A",
      actionType: "staff_check_in",
      summary: "Checked in at Entrance A (Ticket verification) with QR scanner active.",
    },
    {
      id: "act-3",
      timestamp: "11:20 AM",
      staffId: "staff-assign-5",
      staffName: "Beverly Sterling",
      roleOnEvent: "payment_clerk",
      stationName: "Payment Desk",
      actionType: "staff_check_in",
      summary: "Opened Payment Desk cash float and transfer verification queue.",
    },
    {
      id: "act-4",
      timestamp: "11:28 AM",
      staffId: "staff-assign-3",
      staffName: "Devon Campbell",
      roleOnEvent: "food_station_worker",
      stationName: "Food Station 1",
      actionType: "staff_check_in",
      summary: "Checked in at Food Station 1 (Main meals) and initialized KDS prep board.",
    },
  ];

  const emergencyContacts: EventEmergencyContact[] = [
    {
      id: "emg-1",
      label: "On-Site Medical & First Aid Station",
      contactName: "Dr. Nadine Clarke (Event Medic Lead)",
      phone: "+1 (305) 555-0911",
      locationOrPost: "Medical Tent — North Pavilion Gate",
      notes: "AED, heat-relief kit & trauma first-response stationed beside Entrance A.",
    },
    {
      id: "emg-2",
      label: "Venue Security & Crowd Safety Dispatch",
      contactName: "Capt. Winston Reid (Perimeter Security)",
      phone: "+1 (305) 555-0844",
      locationOrPost: "Main Security Command Post — Entrance A",
      notes: "Direct radio link on Channel 1 for gate perimeter and parking traffic control.",
    },
  ];

  const escalationContacts: EventEscalationContact[] = [
    {
      id: "esc-1",
      tierLevel: 1,
      roleTitle: "Tier 1 — Event Supervisor (Floor Operations)",
      contactName: "Marcus Garvey Jr.",
      phone: "+1 (305) 555-0110",
      email: "supervisor@islandevents.example",
      triggerScenarios: "Duplicate QR ticket scan disputes, VIP gate overrides, food station queue bottlenecks.",
    },
    {
      id: "esc-2",
      tierLevel: 2,
      roleTitle: "Tier 2 — Payment & Box Office Lead",
      contactName: "Beverly Sterling",
      phone: "+1 (305) 555-0139",
      email: "payments@islandevents.example",
      triggerScenarios: "Unverified bank transfer references, cash receipt discrepancies, partial payment holds.",
    },
    {
      id: "esc-3",
      tierLevel: 3,
      roleTitle: "Tier 3 — Event Host / Organizer Executive",
      contactName: eventDetails?.organizerName || "Kingston Youth & Community Foundation",
      phone: eventDetails?.organizerPhone || "+1 (876) 926-3849",
      email: eventDetails?.organizerEmail || "host@islandevents.example",
      triggerScenarios: "Emergency venue incidents, refund authorizations, staff assignment revocations.",
    },
  ];

  return {
    enabled: true,
    stations: DEFAULT_OPERATIONS_STATIONS.map((s) => ({ ...s })),
    staffAssignments: defaultStaff,
    schedules,
    activityLogs,
    emergencyContacts,
    escalationContacts,
    assemblyPoint: "North Waterfront Lawn — Flagpole Muster Zone",
    medicalPostLocation: "Medical Tent beside Entrance A",
  };
}

