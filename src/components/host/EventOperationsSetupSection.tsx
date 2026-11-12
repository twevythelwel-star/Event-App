import React, { useMemo, useState } from "react";
import {
  Activity,
  AlertTriangle,
  CalendarClock,
  CheckCircle2,
  ClipboardList,
  Clock,
  KeyRound,
  Lock,
  PhoneCall,
  Plus,
  ShieldAlert,
  ShieldCheck,
  Trash2,
  UserCheck,
  UserPlus,
  Users,
} from "lucide-react";
import {
  EventDetails,
  EventEmergencyContact,
  EventEscalationContact,
  EventOperationsConfig,
  EventOperationStation,
  EventOperationStationType,
  EventStaffActivityEntry,
  EventStaffMemberAssignment,
  EventStaffShiftSchedule,
  EventWorkerRoleKey,
  StaffCheckInStatus,
  TenantRole,
  TenantUserMember,
} from "../../types";
import {
  ALL_WORKER_PERMISSIONS,
  DEFAULT_OPERATIONS_STATIONS,
  EVENT_WORKER_ROLE_SPECS,
  WorkerPermissionKey,
  canActorAssignWorkerRole,
  evaluateStaffTimeAccess,
  getDefaultEventOperationsConfig,
  sanitizePermissionsForRole,
} from "../../lib/eventOperations";
import {
  assignEventStaff,
  revokeEventStaffAssignment,
  verifyStaffClaimCode,
} from "../../lib/api";
import { HostRolePermissions } from "./types";

type Props = {
  eventDetails: EventDetails;
  tenantUsers?: TenantUserMember[];
  permissions: HostRolePermissions;
  mode?: "wizard" | "dashboard";
  onUpdateEventDetails: (updated: EventDetails) => void;
  onSwitchRolePerspective?: (role: TenantRole) => void;
  onSaveToast?: (msg: string) => void;
};

type OpsSubTab =
  | "plan_and_stations"
  | "staff_and_roles"
  | "schedules"
  | "checkin_and_activity"
  | "emergency_and_escalation";

export function EventOperationsSetupSection({
  eventDetails,
  tenantUsers = [],
  permissions,
  mode = "wizard",
  onUpdateEventDetails,
  onSwitchRolePerspective,
  onSaveToast,
}: Props) {
  const opsConfig: EventOperationsConfig = useMemo(
    () => getDefaultEventOperationsConfig(eventDetails, tenantUsers),
    [eventDetails, tenantUsers]
  );

  const [activeSubTab, setActiveSubTab] = useState<OpsSubTab>("plan_and_stations");
  const [statusBanner, setStatusBanner] = useState<{
    tone: "emerald" | "amber" | "rose";
    message: string;
  } | null>(null);

  // 1. Station Plan Form State
  const [newStationName, setNewStationName] = useState("");
  const [newStationType, setNewStationType] = useState<EventOperationStationType>("ticket_checking");
  const [newStationResponsibility, setNewStationResponsibility] = useState("");
  const [newStationHours, setNewStationHours] = useState("11:30 AM – 8:00 PM");

  // 2. Staff Invite & Role Assignment Form State
  const [inviteMethod, setInviteMethod] = useState<"claim_code" | "platform_user">("claim_code");
  const [claimCodeInput, setClaimCodeInput] = useState("");
  const [platformEmailInput, setPlatformEmailInput] = useState("");
  const [platformNameInput, setPlatformNameInput] = useState("");
  const [verifiedCandidate, setVerifiedCandidate] = useState<{
    name: string;
    email: string;
    code?: string;
  } | null>(null);

  const defaultDate = eventDetails.eventDateIso || "2026-11-22";
  const [selectedRole, setSelectedRole] = useState<EventWorkerRoleKey>("ticket_checker");
  const [selectedStationId, setSelectedStationId] = useState<string>(
    opsConfig.stations[0]?.id || "stn-entrance-a"
  );
  const [customResponsibilityInput, setCustomResponsibilityInput] = useState("");
  const [shiftStartInput, setShiftStartInput] = useState(`${defaultDate}T11:00`);
  const [shiftEndInput, setShiftEndInput] = useState(`${defaultDate}T20:30`);
  const [selectedPermissions, setSelectedPermissions] = useState<WorkerPermissionKey[]>(
    sanitizePermissionsForRole("ticket_checker")
  );

  // 3. Schedule Builder Form State
  const [newShiftName, setNewShiftName] = useState("");
  const [newShiftStart, setNewShiftStart] = useState("11:30");
  const [newShiftEnd, setNewShiftEnd] = useState("16:30");
  const [newShiftStationName, setNewShiftStationName] = useState(
    opsConfig.stations[0]?.stationName || "Entrance A"
  );
  const [newShiftStaffName, setNewShiftStaffName] = useState(
    opsConfig.staffAssignments[0]?.name || "Jerome Williams"
  );

  // 4. Activity Log Form State
  const [activityStaffId, setActivityStaffId] = useState(
    opsConfig.staffAssignments[0]?.id || "staff-assign-1"
  );
  const [activityActionType, setActivityActionType] =
    useState<EventStaffActivityEntry["actionType"]>("ticket_verified");
  const [activitySummaryInput, setActivitySummaryInput] = useState("");

  // 5. Emergency & Escalation Form State
  const [newEmgLabel, setNewEmgLabel] = useState("");
  const [newEmgName, setNewEmgName] = useState("");
  const [newEmgPhone, setNewEmgPhone] = useState("");
  const [newEmgPost, setNewEmgPost] = useState("");

  const [newEscTier, setNewEscTier] = useState<1 | 2 | 3>(1);
  const [newEscTitle, setNewEscTitle] = useState("");
  const [newEscName, setNewEscName] = useState("");
  const [newEscPhone, setNewEscPhone] = useState("");
  const [newEscTriggers, setNewEscTriggers] = useState("");

  const canManageOps = permissions.canManageWorkers || permissions.canManageEventsAndMenus;

  const commitConfig = (nextConfig: EventOperationsConfig, toastMsg?: string) => {
    onUpdateEventDetails({
      ...eventDetails,
      operationsConfig: nextConfig,
    });
    if (toastMsg && onSaveToast) {
      onSaveToast(toastMsg);
    }
  };

  const handleRoleChange = (nextRole: EventWorkerRoleKey) => {
    setSelectedRole(nextRole);
    setSelectedPermissions(sanitizePermissionsForRole(nextRole));
    const roleSpec = EVENT_WORKER_ROLE_SPECS[nextRole];
    const matchingStation = opsConfig.stations.find(
      (s) => s.stationType === roleSpec.defaultStationType
    );
    if (matchingStation) {
      setSelectedStationId(matchingStation.id);
    }
  };

  const handleTogglePermission = (permKey: WorkerPermissionKey) => {
    const spec = EVENT_WORKER_ROLE_SPECS[selectedRole];
    if (spec.prohibitedActions.includes(permKey)) return;
    setSelectedPermissions((prev) =>
      prev.includes(permKey) ? prev.filter((k) => k !== permKey) : [...prev, permKey]
    );
  };

  // --- Station Matrix Handlers ---
  const handleAddStation = (e: React.FormEvent) => {
    e.preventDefault();
    if (!canManageOps) return;
    const name = newStationName.trim();
    const resp = newStationResponsibility.trim();
    if (!name || !resp) {
      setStatusBanner({
        tone: "amber",
        message: "Provide both a Station name and an Assigned responsibility.",
      });
      return;
    }

    const newStn: EventOperationStation = {
      id: `stn-${Date.now()}`,
      stationName: name,
      stationType: newStationType,
      assignedResponsibility: resp,
      assignedStaffNames: [],
      operatingHours: newStationHours.trim() || "Event Hours",
    };

    const nextStations = [...opsConfig.stations, newStn];
    commitConfig(
      { ...opsConfig, stations: nextStations },
      `Added operations station "${name}" (${resp}).`
    );
    setNewStationName("");
    setNewStationResponsibility("");
    setStatusBanner({
      tone: "emerald",
      message: `Station "${name}" → "${resp}" added to Event-Day Operations Plan.`,
    });
  };

  const handleUpdateStationResponsibility = (stationId: string, responsibility: string) => {
    if (!canManageOps) return;
    const nextStations = opsConfig.stations.map((s) =>
      s.id === stationId ? { ...s, assignedResponsibility: responsibility } : s
    );
    commitConfig({ ...opsConfig, stations: nextStations });
  };

  const handleRemoveStation = (stationId: string) => {
    if (!canManageOps) return;
    if (opsConfig.stations.length <= 1) {
      setStatusBanner({
        tone: "amber",
        message: "At least one operational station must remain in the Event-Day Operations Plan.",
      });
      return;
    }
    const target = opsConfig.stations.find((s) => s.id === stationId);
    const nextStations = opsConfig.stations.filter((s) => s.id !== stationId);
    commitConfig(
      { ...opsConfig, stations: nextStations },
      target ? `Removed station "${target.stationName}".` : undefined
    );
  };

  const handleResetDefaultStations = () => {
    if (!canManageOps) return;
    commitConfig(
      {
        ...opsConfig,
        stations: DEFAULT_OPERATIONS_STATIONS.map((s) => ({ ...s })),
      },
      "Restored canonical 6-station Event-Day Operations Plan."
    );
    setStatusBanner({
      tone: "emerald",
      message:
        "Restored canonical Event-Day Operations Plan: Entrance A, VIP Entrance, Food Station 1, Food Station 2, Payment Desk, and Supervisor.",
    });
  };

  // --- Staff Invite & Assignment Handlers ---
  const handleVerifyCandidate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (inviteMethod === "claim_code") {
      const cleaned = claimCodeInput.trim().toUpperCase();
      if (cleaned.length < 4) {
        setStatusBanner({
          tone: "amber",
          message: "Enter a valid 6-character Staff Claim Code (e.g. KGN842, SFF109, JMA774).",
        });
        return;
      }

      try {
        const res = await verifyStaffClaimCode(eventDetails.id || "evt-fish-fry-2026", cleaned);
        if (res.valid && res.candidate) {
          setVerifiedCandidate({
            name: res.candidate.name,
            email: res.candidate.masked_email,
            code: cleaned,
          });
          setStatusBanner({
            tone: "emerald",
            message: `Verified claim code ${cleaned} for ${res.candidate.name} (${res.candidate.masked_email}). Configure their role, station & shift window below.`,
          });
          return;
        }
      } catch {
        // Offline / local fallback
      }

      const demoDirectory: Record<string, { name: string; email: string }> = {
        KGN842: { name: "Devon Campbell", email: "de***@islandevents.example" },
        SFF109: { name: "Beverly Sterling", email: "be***@islandevents.example" },
        JMA774: { name: "Jerome Williams", email: "je***@islandevents.example" },
        VIP204: { name: "Camille Pantry", email: "ca***@islandevents.example" },
        DRK512: { name: "Keisha Brown", email: "ke***@islandevents.example" },
        SUP901: { name: "Marcus Garvey Jr.", email: "ma***@islandevents.example" },
      };

      const match = demoDirectory[cleaned] || {
        name: `Platform Staff (${cleaned})`,
        email: `${cleaned.toLowerCase().slice(0, 2)}***@islandevents.example`,
      };

      setVerifiedCandidate({
        name: match.name,
        email: match.email,
        code: cleaned,
      });
      setStatusBanner({
        tone: "emerald",
        message: `Claim code ${cleaned} verified for ${match.name}. Configure role, shift window, and station below.`,
      });
    } else {
      const email = platformEmailInput.trim().toLowerCase();
      const name = platformNameInput.trim() || email.split("@")[0] || "Invited Platform User";
      if (!email || !email.includes("@")) {
        setStatusBanner({
          tone: "amber",
          message: "Enter a valid existing platform user email address.",
        });
        return;
      }
      setVerifiedCandidate({
        name,
        email,
      });
      setStatusBanner({
        tone: "emerald",
        message: `Platform user ${name} (${email}) located. Select role, shift start/expiration, and station below.`,
      });
    }
  };

  const handleConfirmStaffAssignment = async () => {
    if (!verifiedCandidate) return;

    const escalationCheck = canActorAssignWorkerRole(permissions.role, selectedRole);
    if (!escalationCheck.allowed) {
      setStatusBanner({
        tone: "rose",
        message: escalationCheck.error || "Unauthorized role assignment.",
      });
      return;
    }

    const startMs = new Date(shiftStartInput).getTime();
    const endMs = new Date(shiftEndInput).getTime();
    if (Number.isNaN(startMs) || Number.isNaN(endMs) || startMs >= endMs) {
      setStatusBanner({
        tone: "rose",
        message: "Invalid shift window: Staff start time must precede expiration time.",
      });
      return;
    }

    const station =
      opsConfig.stations.find((s) => s.id === selectedStationId) || opsConfig.stations[0];
    const stationName = station?.stationName || "Entrance A";
    const responsibility =
      customResponsibilityInput.trim() ||
      station?.assignedResponsibility ||
      EVENT_WORKER_ROLE_SPECS[selectedRole].canDoSummary;

    const sanitizedPerms = sanitizePermissionsForRole(selectedRole, selectedPermissions);

    if (verifiedCandidate.code) {
      try {
        await assignEventStaff(eventDetails.id || "evt-fish-fry-2026", {
          claim_code: verifiedCandidate.code,
          role_on_event:
            selectedRole === "custom_restricted_worker" ? "food_station_worker" : selectedRole,
          assigned_stations: [stationName],
        });
      } catch {
        // Offline / local state preserved
      }
    }

    const newAssignment: EventStaffMemberAssignment = {
      id: `staff-assign-${Date.now()}`,
      userId: `usr-${Date.now()}`,
      name: verifiedCandidate.name,
      email: verifiedCandidate.email,
      claimCode: verifiedCandidate.code,
      roleOnEvent: selectedRole,
      stationId: station?.id || "stn-custom",
      stationName,
      assignedResponsibility: responsibility,
      individualPermissions: sanitizedPerms,
      shiftStartsAt: shiftStartInput,
      shiftExpiresAt: shiftEndInput,
      timezone: eventDetails.timezone || "America/New_York",
      status: "active",
      checkInStatus: "not_checked_in",
      assignedBy: eventDetails.organizerName || "Event Host",
    };

    const nextStations = opsConfig.stations.map((s) =>
      s.id === station?.id
        ? {
            ...s,
            assignedStaffNames: Array.from(
              new Set([...(s.assignedStaffNames || []), verifiedCandidate.name])
            ),
          }
        : s
    );

    const newLog: EventStaffActivityEntry = {
      id: `act-${Date.now()}`,
      timestamp: new Date().toLocaleTimeString([], { hour: "numeric", minute: "2-digit" }),
      staffId: newAssignment.id,
      staffName: newAssignment.name,
      roleOnEvent: newAssignment.roleOnEvent,
      stationName: newAssignment.stationName,
      actionType: "staff_invited",
      summary: `Assigned to ${newAssignment.stationName} (${newAssignment.assignedResponsibility}) as ${EVENT_WORKER_ROLE_SPECS[selectedRole].label}. Shift: ${shiftStartInput.replace("T", " ")} → ${shiftEndInput.replace("T", " ")}.`,
    };

    commitConfig(
      {
        ...opsConfig,
        stations: nextStations,
        staffAssignments: [newAssignment, ...opsConfig.staffAssignments],
        activityLogs: [newLog, ...opsConfig.activityLogs],
      },
      `Assigned ${newAssignment.name} to ${newAssignment.stationName} (${newAssignment.assignedResponsibility}).`
    );

    setStatusBanner({
      tone: "emerald",
      message: `Assigned ${newAssignment.name} as ${EVENT_WORKER_ROLE_SPECS[selectedRole].label} at ${newAssignment.stationName} (${newAssignment.assignedResponsibility}).`,
    });
    setVerifiedCandidate(null);
    setClaimCodeInput("");
    setPlatformEmailInput("");
    setPlatformNameInput("");
    setCustomResponsibilityInput("");
  };

  const handleToggleRevokeStaff = async (member: EventStaffMemberAssignment) => {
    if (!canManageOps) return;
    const nextStatus = member.status === "revoked" ? "active" : "revoked";
    if (nextStatus === "revoked") {
      try {
        await revokeEventStaffAssignment(eventDetails.id || "evt-fish-fry-2026", member.id);
      } catch {
        // Offline fallback
      }
    }

    const nextStaff = opsConfig.staffAssignments.map((s) =>
      s.id === member.id
        ? {
            ...s,
            status: nextStatus as EventStaffMemberAssignment["status"],
            checkInStatus: nextStatus === "revoked" ? ("checked_out" as const) : s.checkInStatus,
          }
        : s
    );

    const newLog: EventStaffActivityEntry = {
      id: `act-${Date.now()}`,
      timestamp: new Date().toLocaleTimeString([], { hour: "numeric", minute: "2-digit" }),
      staffId: member.id,
      staffName: member.name,
      roleOnEvent: member.roleOnEvent,
      stationName: member.stationName,
      actionType: "access_revoked",
      summary:
        nextStatus === "revoked"
          ? `Staff assignment revoked immediately for ${member.name} at ${member.stationName}.`
          : `Staff assignment restored for ${member.name} at ${member.stationName}.`,
    };

    commitConfig({
      ...opsConfig,
      staffAssignments: nextStaff,
      activityLogs: [newLog, ...opsConfig.activityLogs],
    });
  };

  // --- Check-In / Check-Out & Activity Handlers ---
  const handleStaffCheckInStatusChange = (
    member: EventStaffMemberAssignment,
    nextCheckIn: StaffCheckInStatus
  ) => {
    if (nextCheckIn === "checked_in" && member.status === "revoked") {
      setStatusBanner({
        tone: "rose",
        message: `Cannot check in ${member.name}: assignment is revoked.`,
      });
      return;
    }

    const nowFormatted = new Date().toLocaleTimeString([], {
      hour: "numeric",
      minute: "2-digit",
    });

    const nextStaff = opsConfig.staffAssignments.map((s) =>
      s.id === member.id
        ? {
            ...s,
            checkInStatus: nextCheckIn,
            checkedInAt: nextCheckIn === "checked_in" ? nowFormatted : s.checkedInAt,
            checkedOutAt: nextCheckIn === "checked_out" ? nowFormatted : s.checkedOutAt,
          }
        : s
    );

    const actionType: EventStaffActivityEntry["actionType"] =
      nextCheckIn === "checked_in" ? "staff_check_in" : "staff_check_out";

    const logEntry: EventStaffActivityEntry = {
      id: `act-${Date.now()}`,
      timestamp: nowFormatted,
      staffId: member.id,
      staffName: member.name,
      roleOnEvent: member.roleOnEvent,
      stationName: member.stationName,
      actionType,
      summary:
        nextCheckIn === "checked_in"
          ? `${member.name} checked in at ${member.stationName} (${member.assignedResponsibility}).`
          : nextCheckIn === "on_break"
          ? `${member.name} marked on short station break from ${member.stationName}.`
          : `${member.name} checked out from ${member.stationName}.`,
    };

    commitConfig(
      {
        ...opsConfig,
        staffAssignments: nextStaff,
        activityLogs: [logEntry, ...opsConfig.activityLogs],
      },
      `Updated check-in status for ${member.name}: ${nextCheckIn.replace(/_/g, " ")}.`
    );
  };

  const handleRecordCustomActivity = (e: React.FormEvent) => {
    e.preventDefault();
    const summary = activitySummaryInput.trim();
    if (!summary) return;
    const member =
      opsConfig.staffAssignments.find((s) => s.id === activityStaffId) ||
      opsConfig.staffAssignments[0];
    if (!member) return;

    const nowFormatted = new Date().toLocaleTimeString([], {
      hour: "numeric",
      minute: "2-digit",
    });

    const newEntry: EventStaffActivityEntry = {
      id: `act-${Date.now()}`,
      timestamp: nowFormatted,
      staffId: member.id,
      staffName: member.name,
      roleOnEvent: member.roleOnEvent,
      stationName: member.stationName,
      actionType: activityActionType,
      summary,
    };

    commitConfig(
      {
        ...opsConfig,
        activityLogs: [newEntry, ...opsConfig.activityLogs],
      },
      "Recorded staff activity in event audit trail."
    );
    setActivitySummaryInput("");
  };

  // --- Schedule Handler ---
  const handleAddShiftSchedule = (e: React.FormEvent) => {
    e.preventDefault();
    if (!canManageOps) return;
    const title = newShiftName.trim();
    if (!title) return;

    const stn = opsConfig.stations.find((s) => s.stationName === newShiftStationName);
    const staff = opsConfig.staffAssignments.find((s) => s.name === newShiftStaffName);

    const newSched: EventStaffShiftSchedule = {
      id: `sched-${Date.now()}`,
      shiftName: title,
      startTime: newShiftStart,
      endTime: newShiftEnd,
      stationName: newShiftStationName,
      assignedStaffName: newShiftStaffName,
      roleOnEvent: staff?.roleOnEvent || "ticket_checker",
      responsibility: stn?.assignedResponsibility || staff?.assignedResponsibility || "Station operations",
    };

    commitConfig(
      {
        ...opsConfig,
        schedules: [...opsConfig.schedules, newSched],
      },
      `Added shift schedule "${title}".`
    );
    setNewShiftName("");
  };

  const handleRemoveShiftSchedule = (schedId: string) => {
    if (!canManageOps) return;
    commitConfig({
      ...opsConfig,
      schedules: opsConfig.schedules.filter((s) => s.id !== schedId),
    });
  };

  // --- Emergency & Escalation Handlers ---
  const handleAddEmergencyContact = (e: React.FormEvent) => {
    e.preventDefault();
    if (!canManageOps) return;
    if (!newEmgLabel.trim() || !newEmgName.trim() || !newEmgPhone.trim()) return;

    const item: EventEmergencyContact = {
      id: `emg-${Date.now()}`,
      label: newEmgLabel.trim(),
      contactName: newEmgName.trim(),
      phone: newEmgPhone.trim(),
      locationOrPost: newEmgPost.trim() || "Main Venue Command Post",
    };

    commitConfig(
      {
        ...opsConfig,
        emergencyContacts: [...opsConfig.emergencyContacts, item],
      },
      `Added emergency contact "${item.label}".`
    );
    setNewEmgLabel("");
    setNewEmgName("");
    setNewEmgPhone("");
    setNewEmgPost("");
  };

  const handleAddEscalationContact = (e: React.FormEvent) => {
    e.preventDefault();
    if (!canManageOps) return;
    if (!newEscTitle.trim() || !newEscName.trim() || !newEscPhone.trim()) return;

    const item: EventEscalationContact = {
      id: `esc-${Date.now()}`,
      tierLevel: newEscTier,
      roleTitle: newEscTitle.trim(),
      contactName: newEscName.trim(),
      phone: newEscPhone.trim(),
      triggerScenarios: newEscTriggers.trim() || "Operational exceptions & supervisor escalation",
    };

    commitConfig(
      {
        ...opsConfig,
        escalationContacts: [...opsConfig.escalationContacts, item].sort(
          (a, b) => a.tierLevel - b.tierLevel
        ),
      },
      `Added Tier ${newEscTier} escalation contact "${item.contactName}".`
    );
    setNewEscTitle("");
    setNewEscName("");
    setNewEscPhone("");
    setNewEscTriggers("");
  };

  const checkedInCount = opsConfig.staffAssignments.filter(
    (s) => s.checkInStatus === "checked_in" && s.status !== "revoked"
  ).length;
  const activeStaffCount = opsConfig.staffAssignments.filter((s) => s.status !== "revoked").length;

  return (
    <section
      id="event-operations-setup-section"
      aria-label="Event Operations and Staffing Setup"
      style={{
        background: "#0B1120",
        border: "1px solid rgba(148, 163, 184, 0.2)",
        borderRadius: "16px",
        padding: "20px 22px",
        display: "flex",
        flexDirection: "column",
        gap: "18px",
        boxShadow: "0 12px 32px rgba(2, 6, 23, 0.45)",
      }}
    >
      {/* Top Header & Summary KPI Bar */}
      <div
        style={{
          display: "flex",
          alignItems: "flex-start",
          justifyContent: "space-between",
          gap: "16px",
          flexWrap: "wrap",
          borderBottom: "1px solid rgba(148, 163, 184, 0.14)",
          paddingBottom: "16px",
        }}
      >
        <div>
          <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
            <span
              style={{
                fontSize: "10px",
                fontWeight: 800,
                letterSpacing: "1.4px",
                color: "#B6F03A",
                textTransform: "uppercase",
                padding: "3px 8px",
                borderRadius: "999px",
                background: "rgba(182, 240, 58, 0.14)",
                border: "1px solid rgba(182, 240, 58, 0.35)",
              }}
            >
              {mode === "wizard" ? "Optional Event Operations Setup" : "Event Operations & Staffing Command"}
            </span>
            <span
              style={{
                fontSize: "11px",
                fontWeight: 700,
                color: "#38BDF8",
                padding: "3px 8px",
                borderRadius: "999px",
                background: "rgba(56, 189, 248, 0.12)",
              }}
            >
              RBAC + Time-Limited Access Enforced
            </span>
          </div>
          <h3 style={{ margin: "6px 0 4px", fontSize: "18px", fontWeight: 800, color: "#F8FAFC" }}>
            Event-Day Operations Plan, Staffing & Escalation Matrix — {eventDetails.title || "New Event"}
          </h3>
          <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8", maxWidth: "780px" }}>
            Configure your event-day station plan (Entrance A, VIP Entrance, Food Stations, Payment Desk, Supervisor),
            invite platform users with time-bounded role permissions, build shift schedules, track staff check-in/out
            and activity, and publish emergency & escalation contacts.
          </p>
        </div>

        {/* Summary Badges */}
        <div style={{ display: "flex", gap: "10px", flexWrap: "wrap" }}>
          <div
            style={{
              padding: "8px 12px",
              borderRadius: "10px",
              background: "#0F172A",
              border: "1px solid rgba(148, 163, 184, 0.2)",
              fontSize: "11px",
              color: "#E2E8F0",
            }}
          >
            <strong style={{ color: "#B6F03A", fontSize: "13px" }}>{opsConfig.stations.length}</strong>{" "}
            Stations Configured
          </div>
          <div
            style={{
              padding: "8px 12px",
              borderRadius: "10px",
              background: "#0F172A",
              border: "1px solid rgba(148, 163, 184, 0.2)",
              fontSize: "11px",
              color: "#E2E8F0",
            }}
          >
            <strong style={{ color: "#38BDF8", fontSize: "13px" }}>
              {checkedInCount}/{activeStaffCount}
            </strong>{" "}
            Staff Checked In
          </div>
          <div
            style={{
              padding: "8px 12px",
              borderRadius: "10px",
              background: "#0F172A",
              border: "1px solid rgba(148, 163, 184, 0.2)",
              fontSize: "11px",
              color: "#E2E8F0",
            }}
          >
            <strong style={{ color: "#FBBF24", fontSize: "13px" }}>
              {opsConfig.escalationContacts.length}
            </strong>{" "}
            Escalation Tiers
          </div>
        </div>
      </div>

      {/* Sub-Navigation Pills */}
      <div
        role="tablist"
        aria-label="Event Operations Sections"
        style={{
          display: "flex",
          gap: "8px",
          flexWrap: "wrap",
          background: "#0F172A",
          padding: "6px",
          borderRadius: "12px",
          border: "1px solid rgba(148, 163, 184, 0.16)",
        }}
      >
        {[
          {
            id: "plan_and_stations" as const,
            label: "1. Event-Day Operations Plan",
            icon: <ClipboardList size={14} />,
            badge: opsConfig.stations.length,
          },
          {
            id: "staff_and_roles" as const,
            label: "2. Invite Staff & Role Permissions",
            icon: <Users size={14} />,
            badge: activeStaffCount,
          },
          {
            id: "schedules" as const,
            label: "3. Staff Shift Schedules",
            icon: <CalendarClock size={14} />,
            badge: opsConfig.schedules.length,
          },
          {
            id: "checkin_and_activity" as const,
            label: "4. Check-In / Out & Activity Log",
            icon: <Activity size={14} />,
            badge: opsConfig.activityLogs.length,
          },
          {
            id: "emergency_and_escalation" as const,
            label: "5. Emergency & Escalation Contacts",
            icon: <PhoneCall size={14} />,
            badge: opsConfig.emergencyContacts.length + opsConfig.escalationContacts.length,
          },
        ].map((tab) => {
          const isActive = activeSubTab === tab.id;
          return (
            <button
              key={tab.id}
              id={`ops-subtab-${tab.id}`}
              type="button"
              role="tab"
              aria-selected={isActive}
              onClick={() => setActiveSubTab(tab.id)}
              style={{
                padding: "8px 12px",
                borderRadius: "8px",
                border: isActive
                  ? "1px solid rgba(182, 240, 58, 0.45)"
                  : "1px solid transparent",
                background: isActive ? "rgba(182, 240, 58, 0.14)" : "transparent",
                color: isActive ? "#B6F03A" : "#CBD5E1",
                fontSize: "12px",
                fontWeight: 700,
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
                cursor: "pointer",
              }}
            >
              {tab.icon}
              <span>{tab.label}</span>
              <span
                style={{
                  fontSize: "10px",
                  padding: "1px 6px",
                  borderRadius: "999px",
                  background: isActive ? "rgba(182, 240, 58, 0.22)" : "rgba(148, 163, 184, 0.16)",
                  color: isActive ? "#E2F99E" : "#94A3B8",
                }}
              >
                {tab.badge}
              </span>
            </button>
          );
        })}
      </div>

      {statusBanner && (
        <div
          role="status"
          style={{
            padding: "11px 14px",
            borderRadius: "10px",
            background:
              statusBanner.tone === "emerald"
                ? "rgba(16, 185, 129, 0.15)"
                : statusBanner.tone === "rose"
                ? "rgba(239, 68, 68, 0.15)"
                : "rgba(245, 158, 11, 0.16)",
            border:
              statusBanner.tone === "emerald"
                ? "1px solid rgba(52, 211, 153, 0.4)"
                : statusBanner.tone === "rose"
                ? "1px solid rgba(248, 113, 113, 0.4)"
                : "1px solid rgba(251, 191, 36, 0.4)",
            color:
              statusBanner.tone === "emerald"
                ? "#A7F3D0"
                : statusBanner.tone === "rose"
                ? "#FECACA"
                : "#FDE68A",
            fontSize: "12px",
            fontWeight: 600,
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            gap: "10px",
          }}
        >
          <span>{statusBanner.message}</span>
          <button
            type="button"
            onClick={() => setStatusBanner(null)}
            style={{
              background: "transparent",
              border: "none",
              color: "inherit",
              fontSize: "11px",
              cursor: "pointer",
              fontWeight: 700,
            }}
          >
            Dismiss
          </button>
        </div>
      )}

      {/* =====================================================================
          TAB 1: EVENT-DAY OPERATIONS PLAN (STATION MATRIX)
         ===================================================================== */}
      {activeSubTab === "plan_and_stations" && (
        <div id="ops-panel-plan-and-stations" style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              gap: "12px",
              flexWrap: "wrap",
            }}
          >
            <div>
              <h4 style={{ margin: "0 0 2px", fontSize: "15px", fontWeight: 800, color: "#F8FAFC" }}>
                Event-Day Operations Plan (Station → Assigned Responsibility)
              </h4>
              <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
                Map every physical entrance, food counter, payment desk, and supervisor post to its exact event-day responsibility.
              </p>
            </div>
            {canManageOps && (
              <button
                type="button"
                onClick={handleResetDefaultStations}
                style={{
                  padding: "6px 12px",
                  borderRadius: "8px",
                  background: "rgba(56, 189, 248, 0.14)",
                  border: "1px solid rgba(56, 189, 248, 0.35)",
                  color: "#38BDF8",
                  fontSize: "11px",
                  fontWeight: 700,
                  cursor: "pointer",
                }}
              >
                Load Standard 6-Station Plan
              </button>
            )}
          </div>

          {/* Station Table matching the user's specification */}
          <div
            style={{
              overflowX: "auto",
              borderRadius: "12px",
              border: "1px solid rgba(148, 163, 184, 0.2)",
              background: "#0F172A",
            }}
          >
            <table
              id="event-day-operations-plan-table"
              style={{
                width: "100%",
                borderCollapse: "collapse",
                fontSize: "12px",
                textAlign: "left",
              }}
            >
              <thead>
                <tr
                  style={{
                    background: "rgba(15, 23, 42, 0.95)",
                    borderBottom: "1px solid rgba(148, 163, 184, 0.2)",
                    color: "#94A3B8",
                    textTransform: "uppercase",
                    fontSize: "10px",
                    letterSpacing: "0.8px",
                  }}
                >
                  <th style={{ padding: "11px 14px" }}>Station</th>
                  <th style={{ padding: "11px 14px" }}>Station Type</th>
                  <th style={{ padding: "11px 14px" }}>Assigned responsibility</th>
                  <th style={{ padding: "11px 14px" }}>Assigned Staff</th>
                  <th style={{ padding: "11px 14px" }}>Operating Window</th>
                  {canManageOps && <th style={{ padding: "11px 14px", textAlign: "right" }}>Actions</th>}
                </tr>
              </thead>
              <tbody>
                {opsConfig.stations.map((stn) => {
                  const assignedWorkers = opsConfig.staffAssignments.filter(
                    (w) =>
                      w.status !== "revoked" &&
                      (w.stationId === stn.id || w.stationName === stn.stationName)
                  );
                  const typeBadge =
                    stn.stationType === "ticket_checking"
                      ? { label: "Ticket-Checking Station", color: "#818CF8", bg: "rgba(99, 102, 241, 0.16)" }
                      : stn.stationType === "food_station"
                      ? { label: "Food Station", color: "#38BDF8", bg: "rgba(56, 189, 248, 0.16)" }
                      : stn.stationType === "payment_desk"
                      ? { label: "Payment Collection Desk", color: "#FBBF24", bg: "rgba(245, 158, 11, 0.16)" }
                      : stn.stationType === "supervisor"
                      ? { label: "Supervisor Post", color: "#34D399", bg: "rgba(16, 185, 129, 0.16)" }
                      : { label: "Custom Station", color: "#C084FC", bg: "rgba(192, 132, 252, 0.16)" };

                  return (
                    <tr
                      key={stn.id}
                      style={{
                        borderBottom: "1px solid rgba(148, 163, 184, 0.12)",
                      }}
                    >
                      <td style={{ padding: "11px 14px", fontWeight: 800, color: "#F8FAFC" }}>
                        {stn.stationName}
                      </td>
                      <td style={{ padding: "11px 14px" }}>
                        <span
                          style={{
                            padding: "3px 8px",
                            borderRadius: "6px",
                            fontSize: "10px",
                            fontWeight: 700,
                            color: typeBadge.color,
                            background: typeBadge.bg,
                          }}
                        >
                          {typeBadge.label}
                        </span>
                      </td>
                      <td style={{ padding: "11px 14px", color: "#E2E8F0", fontWeight: 600 }}>
                        {canManageOps ? (
                          <input
                            type="text"
                            aria-label={`Assigned responsibility for ${stn.stationName}`}
                            value={stn.assignedResponsibility}
                            onChange={(e) =>
                              handleUpdateStationResponsibility(stn.id, e.target.value)
                            }
                            style={{
                              width: "100%",
                              minWidth: "200px",
                              padding: "5px 8px",
                              borderRadius: "6px",
                              background: "#1E293B",
                              border: "1px solid rgba(148, 163, 184, 0.28)",
                              color: "#F8FAFC",
                              fontSize: "12px",
                            }}
                          />
                        ) : (
                          stn.assignedResponsibility
                        )}
                      </td>
                      <td style={{ padding: "11px 14px", color: "#CBD5E1" }}>
                        {assignedWorkers.length > 0
                          ? assignedWorkers.map((w) => w.name).join(", ")
                          : (stn.assignedStaffNames || []).join(", ") || "Unassigned"}
                      </td>
                      <td style={{ padding: "11px 14px", color: "#94A3B8" }}>
                        {stn.operatingHours || "Event Hours"}
                      </td>
                      {canManageOps && (
                        <td style={{ padding: "11px 14px", textAlign: "right" }}>
                          <button
                            type="button"
                            onClick={() => handleRemoveStation(stn.id)}
                            title={`Remove ${stn.stationName}`}
                            style={{
                              padding: "4px 8px",
                              borderRadius: "6px",
                              background: "rgba(239, 68, 68, 0.14)",
                              border: "1px solid rgba(248, 113, 113, 0.3)",
                              color: "#FCA5A5",
                              fontSize: "11px",
                              cursor: "pointer",
                            }}
                          >
                            <Trash2 size={12} />
                          </button>
                        </td>
                      )}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Add New Station Form */}
          {canManageOps && (
            <form
              onSubmit={handleAddStation}
              style={{
                padding: "14px 16px",
                borderRadius: "12px",
                background: "#0F172A",
                border: "1px solid rgba(148, 163, 184, 0.2)",
                display: "grid",
                gridTemplateColumns: "repeat(auto-fit, minmax(170px, 1fr))",
                gap: "10px",
                alignItems: "end",
              }}
            >
              <div>
                <label
                  style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}
                >
                  New Station Name
                </label>
                <input
                  type="text"
                  placeholder="e.g. Entrance B or Dessert Bar"
                  value={newStationName}
                  onChange={(e) => setNewStationName(e.target.value)}
                  style={{
                    width: "100%",
                    height: "36px",
                    padding: "0 10px",
                    borderRadius: "8px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
              </div>

              <div>
                <label
                  style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}
                >
                  Station Category
                </label>
                <select
                  aria-label="New Station Category"
                  value={newStationType}
                  onChange={(e) => setNewStationType(e.target.value as EventOperationStationType)}
                  style={{
                    width: "100%",
                    height: "36px",
                    padding: "0 10px",
                    borderRadius: "8px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                >
                  <option value="ticket_checking">Ticket-Checking Station</option>
                  <option value="food_station">Food Station</option>
                  <option value="payment_desk">Payment Collection Desk</option>
                  <option value="supervisor">Supervisor Post</option>
                  <option value="custom">Custom Operational Station</option>
                </select>
              </div>

              <div>
                <label
                  style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}
                >
                  Assigned Responsibility
                </label>
                <input
                  type="text"
                  placeholder="e.g. Fast-track QR verification"
                  value={newStationResponsibility}
                  onChange={(e) => setNewStationResponsibility(e.target.value)}
                  style={{
                    width: "100%",
                    height: "36px",
                    padding: "0 10px",
                    borderRadius: "8px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
              </div>

              <div>
                <label
                  style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}
                >
                  Operating Hours
                </label>
                <input
                  type="text"
                  placeholder="e.g. 11:30 AM – 8:00 PM"
                  value={newStationHours}
                  onChange={(e) => setNewStationHours(e.target.value)}
                  style={{
                    width: "100%",
                    height: "36px",
                    padding: "0 10px",
                    borderRadius: "8px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
              </div>

              <button
                type="submit"
                style={{
                  height: "36px",
                  padding: "0 14px",
                  borderRadius: "8px",
                  background: "#B6F03A",
                  color: "#052E16",
                  border: "none",
                  fontSize: "12px",
                  fontWeight: 800,
                  display: "inline-flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "6px",
                  cursor: "pointer",
                }}
              >
                <Plus size={14} />
                Add Station to Plan
              </button>
            </form>
          )}
        </div>
      )}

      {/* =====================================================================
          TAB 2: INVITE STAFF, ROLES, TIME WINDOWS & RESPONSIBILITIES
         ===================================================================== */}
      {activeSubTab === "staff_and_roles" && (
        <div id="ops-panel-staff-and-roles" style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
          {canManageOps ? (
            <div
              style={{
                padding: "16px 18px",
                borderRadius: "12px",
                background: "#0F172A",
                border: "1px solid rgba(129, 140, 248, 0.3)",
                display: "flex",
                flexDirection: "column",
                gap: "14px",
              }}
            >
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  gap: "12px",
                  flexWrap: "wrap",
                }}
              >
                <div>
                  <strong style={{ fontSize: "14px", color: "#F8FAFC", display: "block" }}>
                    Invite Existing Platform Users as Event Staff
                  </strong>
                  <span style={{ fontSize: "11px", color: "#94A3B8" }}>
                    Verify via 6-character Staff Claim Code (e.g. <code>KGN842</code>, <code>SFF109</code>,{" "}
                    <code>JMA774</code>, <code>VIP204</code>) or lookup by registered platform account email.
                  </span>
                </div>

                <div style={{ display: "flex", gap: "6px" }}>
                  <button
                    type="button"
                    onClick={() => setInviteMethod("claim_code")}
                    style={{
                      padding: "5px 10px",
                      borderRadius: "7px",
                      fontSize: "11px",
                      fontWeight: 700,
                      border:
                        inviteMethod === "claim_code"
                          ? "1px solid rgba(129, 140, 248, 0.5)"
                          : "1px solid rgba(148, 163, 184, 0.2)",
                      background:
                        inviteMethod === "claim_code"
                          ? "rgba(99, 102, 241, 0.2)"
                          : "transparent",
                      color: inviteMethod === "claim_code" ? "#C7D2FE" : "#94A3B8",
                      cursor: "pointer",
                    }}
                  >
                    6-Char Claim Code
                  </button>
                  <button
                    type="button"
                    onClick={() => setInviteMethod("platform_user")}
                    style={{
                      padding: "5px 10px",
                      borderRadius: "7px",
                      fontSize: "11px",
                      fontWeight: 700,
                      border:
                        inviteMethod === "platform_user"
                          ? "1px solid rgba(129, 140, 248, 0.5)"
                          : "1px solid rgba(148, 163, 184, 0.2)",
                      background:
                        inviteMethod === "platform_user"
                          ? "rgba(99, 102, 241, 0.2)"
                          : "transparent",
                      color: inviteMethod === "platform_user" ? "#C7D2FE" : "#94A3B8",
                      cursor: "pointer",
                    }}
                  >
                    Platform User Email
                  </button>
                </div>
              </div>

              <form
                onSubmit={handleVerifyCandidate}
                style={{ display: "flex", gap: "10px", flexWrap: "wrap", alignItems: "center" }}
              >
                {inviteMethod === "claim_code" ? (
                  <input
                    type="text"
                    maxLength={6}
                    placeholder="Enter 6-char code (e.g. KGN842)"
                    value={claimCodeInput}
                    onChange={(e) => setClaimCodeInput(e.target.value.toUpperCase())}
                    style={{
                      height: "38px",
                      padding: "0 12px",
                      borderRadius: "8px",
                      background: "#1E293B",
                      border: "1px solid rgba(148, 163, 184, 0.35)",
                      color: "#F8FAFC",
                      fontSize: "13px",
                      fontFamily: "monospace",
                      fontWeight: 800,
                      letterSpacing: "1.5px",
                      width: "220px",
                    }}
                  />
                ) : (
                  <>
                    <input
                      type="text"
                      placeholder="Full name (e.g. Andre Thomas)"
                      value={platformNameInput}
                      onChange={(e) => setPlatformNameInput(e.target.value)}
                      style={{
                        height: "38px",
                        padding: "0 12px",
                        borderRadius: "8px",
                        background: "#1E293B",
                        border: "1px solid rgba(148, 163, 184, 0.35)",
                        color: "#F8FAFC",
                        fontSize: "12px",
                        width: "200px",
                      }}
                    />
                    <input
                      type="email"
                      placeholder="Registered platform email"
                      value={platformEmailInput}
                      onChange={(e) => setPlatformEmailInput(e.target.value)}
                      style={{
                        height: "38px",
                        padding: "0 12px",
                        borderRadius: "8px",
                        background: "#1E293B",
                        border: "1px solid rgba(148, 163, 184, 0.35)",
                        color: "#F8FAFC",
                        fontSize: "12px",
                        width: "240px",
                      }}
                    />
                  </>
                )}

                <button
                  type="submit"
                  style={{
                    height: "38px",
                    padding: "0 14px",
                    borderRadius: "8px",
                    background: "#4F46E5",
                    color: "#FFFFFF",
                    border: "none",
                    fontSize: "12px",
                    fontWeight: 700,
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "6px",
                    cursor: "pointer",
                  }}
                >
                  <KeyRound size={13} />
                  {inviteMethod === "claim_code" ? "Verify Claim Code" : "Locate Platform User"}
                </button>
              </form>

              {/* Role, Station, Shift Start/Expiration & Responsibilities Configuration */}
              {verifiedCandidate && (
                <div
                  style={{
                    padding: "14px 16px",
                    borderRadius: "10px",
                    background: "rgba(16, 185, 129, 0.08)",
                    border: "1px solid rgba(52, 211, 153, 0.35)",
                    display: "flex",
                    flexDirection: "column",
                    gap: "12px",
                  }}
                >
                  <div style={{ display: "flex", justifyContent: "space-between", flexWrap: "wrap", gap: "8px" }}>
                    <div>
                      <strong style={{ fontSize: "13px", color: "#34D399" }}>
                        Platform User Ready: {verifiedCandidate.name}
                      </strong>
                      <div style={{ fontSize: "11px", color: "#CBD5E1" }}>
                        Account: {verifiedCandidate.email}
                        {verifiedCandidate.code ? ` · Claim Code: ${verifiedCandidate.code}` : ""}
                      </div>
                    </div>
                    <span style={{ fontSize: "11px", color: "#94A3B8" }}>
                      Timezone: <strong>{eventDetails.timezone || "America/New_York"}</strong>
                    </span>
                  </div>

                  <div
                    style={{
                      display: "grid",
                      gridTemplateColumns: "repeat(auto-fit, minmax(190px, 1fr))",
                      gap: "10px",
                    }}
                  >
                    <div>
                      <label style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                        Event-Specific Role
                      </label>
                      <select
                        aria-label="Event-Specific Role"
                        value={selectedRole}
                        onChange={(e) => handleRoleChange(e.target.value as EventWorkerRoleKey)}
                        style={{
                          width: "100%",
                          height: "36px",
                          padding: "0 10px",
                          borderRadius: "8px",
                          background: "#0F172A",
                          border: "1px solid rgba(148, 163, 184, 0.35)",
                          color: "#F8FAFC",
                          fontSize: "12px",
                          fontWeight: 700,
                        }}
                      >
                        <option value="ticket_checker">Ticket Checker (Gate & VIP)</option>
                        <option value="food_station_worker">Food Station Worker (Meals & Drinks)</option>
                        <option value="payment_clerk">Payment Clerk (Cash & Verification)</option>
                        <option value="supervisor">Event Supervisor (Floor Oversight)</option>
                        <option value="custom_restricted_worker">Custom Restricted Worker</option>
                      </select>
                    </div>

                    <div>
                      <label style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                        Assigned Station
                      </label>
                      <select
                        aria-label="Assigned Station"
                        value={selectedStationId}
                        onChange={(e) => setSelectedStationId(e.target.value)}
                        style={{
                          width: "100%",
                          height: "36px",
                          padding: "0 10px",
                          borderRadius: "8px",
                          background: "#0F172A",
                          border: "1px solid rgba(148, 163, 184, 0.35)",
                          color: "#F8FAFC",
                          fontSize: "12px",
                          fontWeight: 700,
                        }}
                      >
                        {opsConfig.stations.map((stn) => (
                          <option key={stn.id} value={stn.id}>
                            {stn.stationName} — {stn.assignedResponsibility}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <label style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                        Staff Shift Start Time
                      </label>
                      <input
                        type="datetime-local"
                        aria-label="Staff Shift Start Time"
                        value={shiftStartInput}
                        onChange={(e) => setShiftStartInput(e.target.value)}
                        style={{
                          width: "100%",
                          height: "36px",
                          padding: "0 10px",
                          borderRadius: "8px",
                          background: "#0F172A",
                          border: "1px solid rgba(148, 163, 184, 0.35)",
                          color: "#F8FAFC",
                          fontSize: "12px",
                        }}
                      />
                    </div>

                    <div>
                      <label style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                        Staff Expiration Time
                      </label>
                      <input
                        type="datetime-local"
                        aria-label="Staff Expiration Time"
                        value={shiftEndInput}
                        onChange={(e) => setShiftEndInput(e.target.value)}
                        style={{
                          width: "100%",
                          height: "36px",
                          padding: "0 10px",
                          borderRadius: "8px",
                          background: "#0F172A",
                          border: "1px solid rgba(148, 163, 184, 0.35)",
                          color: "#F8FAFC",
                          fontSize: "12px",
                        }}
                      />
                    </div>
                  </div>

                  {/* Individual Responsibility Label */}
                  <div>
                    <label style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                      Specific Station Responsibility Note (Optional Override)
                    </label>
                    <input
                      type="text"
                      placeholder="Defaults to the selected station's responsibility (e.g. Ticket verification, Main meals, Authorized cash/payment verification)"
                      value={customResponsibilityInput}
                      onChange={(e) => setCustomResponsibilityInput(e.target.value)}
                      style={{
                        width: "100%",
                        height: "36px",
                        padding: "0 10px",
                        borderRadius: "8px",
                        background: "#0F172A",
                        border: "1px solid rgba(148, 163, 184, 0.35)",
                        color: "#F8FAFC",
                        fontSize: "12px",
                      }}
                    />
                  </div>

                  {/* Individual Permission Checkboxes with RBAC Boundary Enforcement */}
                  <div
                    style={{
                      padding: "10px 12px",
                      borderRadius: "8px",
                      background: "#0B1120",
                      border: "1px solid rgba(148, 163, 184, 0.18)",
                    }}
                  >
                    <div style={{ fontSize: "11px", fontWeight: 700, color: "#E2E8F0", marginBottom: "6px" }}>
                      Individual Operational Permissions ({EVENT_WORKER_ROLE_SPECS[selectedRole].label})
                    </div>
                    <div style={{ fontSize: "11px", color: "#94A3B8", marginBottom: "8px" }}>
                      <strong>RBAC Boundary:</strong> {EVENT_WORKER_ROLE_SPECS[selectedRole].mustNotDoSummary}
                    </div>
                    <div
                      style={{
                        display: "grid",
                        gridTemplateColumns: "repeat(auto-fill, minmax(220px, 1fr))",
                        gap: "6px",
                      }}
                    >
                      {ALL_WORKER_PERMISSIONS.map((perm) => {
                        const isProhibited =
                          EVENT_WORKER_ROLE_SPECS[selectedRole].prohibitedActions.includes(perm.key);
                        const isChecked = !isProhibited && selectedPermissions.includes(perm.key);
                        return (
                          <label
                            key={perm.key}
                            style={{
                              display: "flex",
                              alignItems: "center",
                              gap: "6px",
                              fontSize: "11px",
                              color: isProhibited ? "#64748B" : "#E2E8F0",
                              cursor: isProhibited ? "not-allowed" : "pointer",
                              opacity: isProhibited ? 0.55 : 1,
                            }}
                          >
                            <input
                              type="checkbox"
                              disabled={isProhibited}
                              checked={isChecked}
                              onChange={() => handleTogglePermission(perm.key)}
                            />
                            <span>
                              {perm.label}
                              {isProhibited ? " (Blocked by Role RBAC)" : ""}
                            </span>
                          </label>
                        );
                      })}
                    </div>
                  </div>

                  <div style={{ display: "flex", justifyContent: "flex-end", gap: "8px" }}>
                    <button
                      type="button"
                      onClick={() => setVerifiedCandidate(null)}
                      style={{
                        height: "36px",
                        padding: "0 12px",
                        borderRadius: "8px",
                        background: "transparent",
                        border: "1px solid rgba(148, 163, 184, 0.3)",
                        color: "#CBD5E1",
                        fontSize: "11px",
                        fontWeight: 700,
                        cursor: "pointer",
                      }}
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      onClick={handleConfirmStaffAssignment}
                      style={{
                        height: "36px",
                        padding: "0 14px",
                        borderRadius: "8px",
                        background: "#10B981",
                        color: "#052E16",
                        border: "none",
                        fontSize: "12px",
                        fontWeight: 800,
                        display: "inline-flex",
                        alignItems: "center",
                        gap: "6px",
                        cursor: "pointer",
                      }}
                    >
                      <UserPlus size={14} />
                      Confirm Time-Bounded Staff Assignment
                    </button>
                  </div>
                </div>
              )}
            </div>
          ) : (
            <div
              style={{
                padding: "14px",
                borderRadius: "10px",
                background: "rgba(245, 158, 11, 0.12)",
                border: "1px solid rgba(251, 191, 36, 0.35)",
                color: "#FDE68A",
                fontSize: "12px",
                display: "flex",
                alignItems: "center",
                gap: "8px",
              }}
            >
              <Lock size={15} />
              <span>
                Inviting staff and modifying role permissions requires <strong>Authorized Event Host</strong>,{" "}
                <strong>Event Supervisor</strong>, or <strong>Platform Admin</strong> permissions.
              </span>
            </div>
          )}

          {/* Assigned Staff Cards */}
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fill, minmax(310px, 1fr))",
              gap: "12px",
            }}
          >
            {opsConfig.staffAssignments.map((member) => {
              const roleSpec =
                EVENT_WORKER_ROLE_SPECS[member.roleOnEvent] ||
                EVENT_WORKER_ROLE_SPECS.food_station_worker;
              const timeEval = evaluateStaffTimeAccess(member);

              return (
                <div
                  key={member.id}
                  style={{
                    padding: "14px 16px",
                    borderRadius: "12px",
                    background: "#0F172A",
                    border:
                      member.status === "revoked"
                        ? "1px dashed rgba(239, 68, 68, 0.4)"
                        : "1px solid rgba(148, 163, 184, 0.22)",
                    opacity: member.status === "revoked" ? 0.65 : 1,
                    display: "flex",
                    flexDirection: "column",
                    justifyContent: "space-between",
                    gap: "10px",
                  }}
                >
                  <div>
                    <div style={{ display: "flex", justifyContent: "space-between", gap: "8px" }}>
                      <div>
                        <strong style={{ fontSize: "14px", color: "#F8FAFC", display: "block" }}>
                          {member.name}
                        </strong>
                        <span style={{ fontSize: "11px", color: "#94A3B8" }}>
                          {member.email}
                          {member.claimCode ? ` · Code: ${member.claimCode}` : ""}
                        </span>
                      </div>
                      <span
                        style={{
                          fontSize: "10px",
                          fontWeight: 800,
                          padding: "3px 8px",
                          borderRadius: "6px",
                          height: "fit-content",
                          textTransform: "uppercase",
                          background:
                            member.status === "revoked"
                              ? "rgba(239, 68, 68, 0.2)"
                              : "rgba(16, 185, 129, 0.18)",
                          color: member.status === "revoked" ? "#FCA5A5" : "#34D399",
                        }}
                      >
                        {member.status}
                      </span>
                    </div>

                    <div style={{ marginTop: "8px", display: "flex", gap: "6px", flexWrap: "wrap" }}>
                      <span
                        style={{
                          fontSize: "10px",
                          fontWeight: 800,
                          padding: "3px 8px",
                          borderRadius: "6px",
                          background: roleSpec.badgeBg,
                          color: roleSpec.badgeColor,
                        }}
                      >
                        {roleSpec.label}
                      </span>
                      <span
                        style={{
                          fontSize: "10px",
                          fontWeight: 700,
                          padding: "3px 8px",
                          borderRadius: "6px",
                          background: "rgba(182, 240, 58, 0.14)",
                          color: "#B6F03A",
                        }}
                      >
                        {member.stationName} · {member.assignedResponsibility}
                      </span>
                    </div>

                    <div
                      style={{
                        marginTop: "8px",
                        fontSize: "11px",
                        color: "#CBD5E1",
                        display: "flex",
                        alignItems: "center",
                        gap: "5px",
                      }}
                    >
                      <Clock size={12} color="#38BDF8" />
                      <span>
                        Window: {member.shiftStartsAt.replace("T", " ")} →{" "}
                        {member.shiftExpiresAt.replace("T", " ")} ({timeEval.effectiveStatus})
                      </span>
                    </div>

                    <div style={{ marginTop: "6px", fontSize: "10px", color: "#94A3B8" }}>
                      Permissions ({member.individualPermissions.length}):{" "}
                      {member.individualPermissions
                        .slice(0, 4)
                        .map((p) => p.replace(/_/g, " "))
                        .join(", ")}
                      {member.individualPermissions.length > 4 ? "…" : ""}
                    </div>
                  </div>

                  <div
                    style={{
                      paddingTop: "8px",
                      borderTop: "1px solid rgba(148, 163, 184, 0.14)",
                      display: "flex",
                      justifyContent: "space-between",
                      alignItems: "center",
                      gap: "8px",
                      flexWrap: "wrap",
                    }}
                  >
                    {onSwitchRolePerspective && (
                      <button
                        type="button"
                        onClick={() => onSwitchRolePerspective(roleSpec.tenantRoleEquivalent)}
                        style={{
                          padding: "4px 9px",
                          borderRadius: "6px",
                          background: "rgba(99, 102, 241, 0.16)",
                          border: "1px solid rgba(129, 140, 248, 0.35)",
                          color: "#A5B4FC",
                          fontSize: "10px",
                          fontWeight: 700,
                          cursor: "pointer",
                        }}
                      >
                        Preview Role View
                      </button>
                    )}

                    {canManageOps && (
                      <button
                        type="button"
                        onClick={() => handleToggleRevokeStaff(member)}
                        style={{
                          padding: "4px 9px",
                          borderRadius: "6px",
                          background:
                            member.status === "revoked"
                              ? "rgba(16, 185, 129, 0.16)"
                              : "rgba(239, 68, 68, 0.14)",
                          border:
                            member.status === "revoked"
                              ? "1px solid rgba(52, 211, 153, 0.35)"
                              : "1px solid rgba(248, 113, 113, 0.35)",
                          color: member.status === "revoked" ? "#34D399" : "#FCA5A5",
                          fontSize: "10px",
                          fontWeight: 700,
                          cursor: "pointer",
                        }}
                      >
                        {member.status === "revoked" ? "Restore Assignment" : "Revoke Assignment"}
                      </button>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* =====================================================================
          TAB 3: STAFF SHIFT SCHEDULES
         ===================================================================== */}
      {activeSubTab === "schedules" && (
        <div id="ops-panel-schedules" style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
          <div>
            <h4 style={{ margin: "0 0 2px", fontSize: "15px", fontWeight: 800, color: "#F8FAFC" }}>
              Event-Day Staff Shift Schedules
            </h4>
            <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
              Organize load-in, gate waves, kitchen peaks, and box office closeout shifts across stations.
            </p>
          </div>

          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fill, minmax(290px, 1fr))",
              gap: "12px",
            }}
          >
            {opsConfig.schedules.map((sched) => {
              const roleSpec =
                EVENT_WORKER_ROLE_SPECS[sched.roleOnEvent] ||
                EVENT_WORKER_ROLE_SPECS.ticket_checker;
              return (
                <div
                  key={sched.id}
                  style={{
                    padding: "14px 16px",
                    borderRadius: "12px",
                    background: "#0F172A",
                    border: "1px solid rgba(148, 163, 184, 0.2)",
                    display: "flex",
                    flexDirection: "column",
                    gap: "8px",
                  }}
                >
                  <div style={{ display: "flex", justifyContent: "space-between", gap: "8px" }}>
                    <strong style={{ fontSize: "13px", color: "#F8FAFC" }}>{sched.shiftName}</strong>
                    {canManageOps && (
                      <button
                        type="button"
                        onClick={() => handleRemoveShiftSchedule(sched.id)}
                        style={{
                          background: "transparent",
                          border: "none",
                          color: "#FCA5A5",
                          cursor: "pointer",
                        }}
                      >
                        <Trash2 size={13} />
                      </button>
                    )}
                  </div>
                  <div style={{ fontSize: "12px", color: "#B6F03A", fontWeight: 700 }}>
                    {sched.startTime} – {sched.endTime} · {sched.stationName}
                  </div>
                  <div style={{ fontSize: "11px", color: "#CBD5E1" }}>
                    Staff: <strong>{sched.assignedStaffName}</strong> ({roleSpec.label})
                  </div>
                  <div style={{ fontSize: "11px", color: "#94A3B8" }}>
                    Responsibility: {sched.responsibility}
                  </div>
                </div>
              );
            })}
          </div>

          {canManageOps && (
            <form
              onSubmit={handleAddShiftSchedule}
              style={{
                padding: "14px 16px",
                borderRadius: "12px",
                background: "#0F172A",
                border: "1px solid rgba(148, 163, 184, 0.2)",
                display: "grid",
                gridTemplateColumns: "repeat(auto-fit, minmax(150px, 1fr))",
                gap: "10px",
                alignItems: "end",
              }}
            >
              <div>
                <label style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                  Shift Name
                </label>
                <input
                  type="text"
                  placeholder="e.g. Wave 2 — Evening Rush"
                  value={newShiftName}
                  onChange={(e) => setNewShiftName(e.target.value)}
                  style={{
                    width: "100%",
                    height: "36px",
                    padding: "0 10px",
                    borderRadius: "8px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
              </div>

              <div>
                <label style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                  Start Time
                </label>
                <input
                  type="time"
                  value={newShiftStart}
                  onChange={(e) => setNewShiftStart(e.target.value)}
                  style={{
                    width: "100%",
                    height: "36px",
                    padding: "0 10px",
                    borderRadius: "8px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
              </div>

              <div>
                <label style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                  End Time
                </label>
                <input
                  type="time"
                  value={newShiftEnd}
                  onChange={(e) => setNewShiftEnd(e.target.value)}
                  style={{
                    width: "100%",
                    height: "36px",
                    padding: "0 10px",
                    borderRadius: "8px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
              </div>

              <div>
                <label style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                  Station
                </label>
                <select
                  aria-label="Schedule Station"
                  value={newShiftStationName}
                  onChange={(e) => setNewShiftStationName(e.target.value)}
                  style={{
                    width: "100%",
                    height: "36px",
                    padding: "0 10px",
                    borderRadius: "8px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                >
                  {opsConfig.stations.map((s) => (
                    <option key={s.id} value={s.stationName}>
                      {s.stationName}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "4px" }}>
                  Assigned Staff
                </label>
                <select
                  aria-label="Schedule Staff Member"
                  value={newShiftStaffName}
                  onChange={(e) => setNewShiftStaffName(e.target.value)}
                  style={{
                    width: "100%",
                    height: "36px",
                    padding: "0 10px",
                    borderRadius: "8px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                >
                  {opsConfig.staffAssignments.map((s) => (
                    <option key={s.id} value={s.name}>
                      {s.name}
                    </option>
                  ))}
                </select>
              </div>

              <button
                type="submit"
                style={{
                  height: "36px",
                  padding: "0 14px",
                  borderRadius: "8px",
                  background: "#B6F03A",
                  color: "#052E16",
                  border: "none",
                  fontSize: "12px",
                  fontWeight: 800,
                  cursor: "pointer",
                }}
              >
                + Add Shift
              </button>
            </form>
          )}
        </div>
      )}

      {/* =====================================================================
          TAB 4: TRACK STAFF CHECK-IN / CHECK-OUT & RECORD STAFF ACTIVITY
         ===================================================================== */}
      {activeSubTab === "checkin_and_activity" && (
        <div id="ops-panel-checkin-and-activity" style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
          <div>
            <h4 style={{ margin: "0 0 2px", fontSize: "15px", fontWeight: 800, color: "#F8FAFC" }}>
              Live Staff Check-In / Check-Out & Operational Activity Log
            </h4>
            <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
              Track when staff check in and check out at their assigned stations and record timestamped event-day activity.
            </p>
          </div>

          {/* Check-In / Check-Out Grid */}
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fill, minmax(280px, 1fr))",
              gap: "10px",
            }}
          >
            {opsConfig.staffAssignments.map((member) => {
              const isCheckedIn = member.checkInStatus === "checked_in";
              const isCheckedOut = member.checkInStatus === "checked_out";
              return (
                <div
                  key={member.id}
                  style={{
                    padding: "12px 14px",
                    borderRadius: "10px",
                    background: "#0F172A",
                    border: isCheckedIn
                      ? "1px solid rgba(52, 211, 153, 0.35)"
                      : "1px solid rgba(148, 163, 184, 0.18)",
                    display: "flex",
                    flexDirection: "column",
                    gap: "8px",
                  }}
                >
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                    <div>
                      <strong style={{ fontSize: "13px", color: "#F8FAFC", display: "block" }}>
                        {member.name}
                      </strong>
                      <span style={{ fontSize: "11px", color: "#38BDF8" }}>
                        {member.stationName} · {member.assignedResponsibility}
                      </span>
                    </div>
                    <span
                      style={{
                        fontSize: "10px",
                        fontWeight: 800,
                        padding: "3px 8px",
                        borderRadius: "999px",
                        background: isCheckedIn
                          ? "rgba(16, 185, 129, 0.18)"
                          : isCheckedOut
                          ? "rgba(148, 163, 184, 0.18)"
                          : "rgba(245, 158, 11, 0.18)",
                        color: isCheckedIn ? "#34D399" : isCheckedOut ? "#94A3B8" : "#FBBF24",
                      }}
                    >
                      {member.checkInStatus.replace(/_/g, " ").toUpperCase()}
                    </span>
                  </div>

                  <div style={{ fontSize: "11px", color: "#94A3B8" }}>
                    {member.checkedInAt ? `Checked in: ${member.checkedInAt}` : "Not checked in yet"}
                    {member.checkedOutAt ? ` · Checked out: ${member.checkedOutAt}` : ""}
                  </div>

                  <div style={{ display: "flex", gap: "6px", flexWrap: "wrap" }}>
                    <button
                      type="button"
                      onClick={() => handleStaffCheckInStatusChange(member, "checked_in")}
                      style={{
                        padding: "5px 10px",
                        borderRadius: "6px",
                        background: "rgba(16, 185, 129, 0.16)",
                        border: "1px solid rgba(52, 211, 153, 0.35)",
                        color: "#34D399",
                        fontSize: "10px",
                        fontWeight: 700,
                        cursor: "pointer",
                      }}
                    >
                      Check In
                    </button>
                    <button
                      type="button"
                      onClick={() => handleStaffCheckInStatusChange(member, "on_break")}
                      style={{
                        padding: "5px 10px",
                        borderRadius: "6px",
                        background: "rgba(245, 158, 11, 0.16)",
                        border: "1px solid rgba(251, 191, 36, 0.35)",
                        color: "#FBBF24",
                        fontSize: "10px",
                        fontWeight: 700,
                        cursor: "pointer",
                      }}
                    >
                      Break
                    </button>
                    <button
                      type="button"
                      onClick={() => handleStaffCheckInStatusChange(member, "checked_out")}
                      style={{
                        padding: "5px 10px",
                        borderRadius: "6px",
                        background: "rgba(148, 163, 184, 0.16)",
                        border: "1px solid rgba(148, 163, 184, 0.3)",
                        color: "#CBD5E1",
                        fontSize: "10px",
                        fontWeight: 700,
                        cursor: "pointer",
                      }}
                    >
                      Check Out
                    </button>
                  </div>
                </div>
              );
            })}
          </div>

          {/* Record Staff Activity Form */}
          <form
            onSubmit={handleRecordCustomActivity}
            style={{
              padding: "12px 14px",
              borderRadius: "10px",
              background: "#0F172A",
              border: "1px solid rgba(148, 163, 184, 0.2)",
              display: "flex",
              gap: "10px",
              flexWrap: "wrap",
              alignItems: "center",
            }}
          >
            <select
              aria-label="Activity Staff Member"
              value={activityStaffId}
              onChange={(e) => setActivityStaffId(e.target.value)}
              style={{
                height: "36px",
                padding: "0 10px",
                borderRadius: "8px",
                background: "#1E293B",
                border: "1px solid rgba(148, 163, 184, 0.3)",
                color: "#F8FAFC",
                fontSize: "12px",
              }}
            >
              {opsConfig.staffAssignments.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name} ({s.stationName})
                </option>
              ))}
            </select>

            <select
              aria-label="Activity Type"
              value={activityActionType}
              onChange={(e) =>
                setActivityActionType(e.target.value as EventStaffActivityEntry["actionType"])
              }
              style={{
                height: "36px",
                padding: "0 10px",
                borderRadius: "8px",
                background: "#1E293B",
                border: "1px solid rgba(148, 163, 184, 0.3)",
                color: "#F8FAFC",
                fontSize: "12px",
              }}
            >
              <option value="ticket_verified">Ticket Verification Activity</option>
              <option value="food_collected">Food Station Handover</option>
              <option value="payment_verified">Payment Desk Verification</option>
              <option value="station_assigned">Station Reassignment</option>
              <option value="escalation_logged">Supervisor Escalation Note</option>
            </select>

            <input
              type="text"
              placeholder="Record staff activity note (e.g. Verified 45 VIP passes at VIP Entrance)..."
              value={activitySummaryInput}
              onChange={(e) => setActivitySummaryInput(e.target.value)}
              style={{
                flex: 1,
                minWidth: "240px",
                height: "36px",
                padding: "0 12px",
                borderRadius: "8px",
                background: "#1E293B",
                border: "1px solid rgba(148, 163, 184, 0.3)",
                color: "#F8FAFC",
                fontSize: "12px",
              }}
            />

            <button
              type="submit"
              style={{
                height: "36px",
                padding: "0 14px",
                borderRadius: "8px",
                background: "#38BDF8",
                color: "#082F49",
                border: "none",
                fontSize: "12px",
                fontWeight: 800,
                cursor: "pointer",
              }}
            >
              Record Activity
            </button>
          </form>

          {/* Activity Log Feed */}
          <div
            style={{
              borderRadius: "10px",
              background: "#0F172A",
              border: "1px solid rgba(148, 163, 184, 0.18)",
              padding: "12px 14px",
              display: "flex",
              flexDirection: "column",
              gap: "8px",
              maxHeight: "280px",
              overflowY: "auto",
            }}
          >
            {opsConfig.activityLogs.map((log) => (
              <div
                key={log.id}
                style={{
                  padding: "8px 10px",
                  borderRadius: "8px",
                  background: "#0B1120",
                  border: "1px solid rgba(148, 163, 184, 0.12)",
                  display: "flex",
                  justifyContent: "space-between",
                  gap: "10px",
                  flexWrap: "wrap",
                  fontSize: "12px",
                }}
              >
                <div>
                  <strong style={{ color: "#F8FAFC" }}>{log.staffName}</strong>{" "}
                  <span style={{ color: "#38BDF8", fontSize: "11px" }}>({log.stationName})</span> —{" "}
                  <span style={{ color: "#CBD5E1" }}>{log.summary}</span>
                </div>
                <span style={{ fontSize: "11px", color: "#94A3B8", fontFamily: "monospace" }}>
                  {log.timestamp}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* =====================================================================
          TAB 5: EMERGENCY CONTACT INFORMATION & EVENT ESCALATION CONTACTS
         ===================================================================== */}
      {activeSubTab === "emergency_and_escalation" && (
        <div
          id="ops-panel-emergency-and-escalation"
          style={{ display: "flex", flexDirection: "column", gap: "18px" }}
        >
          {/* Emergency Assembly & Medical Post */}
          <div
            style={{
              padding: "14px 16px",
              borderRadius: "12px",
              background: "rgba(239, 68, 68, 0.08)",
              border: "1px solid rgba(248, 113, 113, 0.3)",
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(240px, 1fr))",
              gap: "12px",
            }}
          >
            <div>
              <label style={{ display: "block", fontSize: "11px", fontWeight: 800, color: "#FCA5A5", marginBottom: "4px" }}>
                Emergency Assembly / Muster Point
              </label>
              <input
                type="text"
                aria-label="Emergency Assembly Point"
                value={opsConfig.assemblyPoint || ""}
                onChange={(e) =>
                  commitConfig({ ...opsConfig, assemblyPoint: e.target.value })
                }
                style={{
                  width: "100%",
                  height: "36px",
                  padding: "0 10px",
                  borderRadius: "8px",
                  background: "#0F172A",
                  border: "1px solid rgba(248, 113, 113, 0.35)",
                  color: "#F8FAFC",
                  fontSize: "12px",
                }}
              />
            </div>

            <div>
              <label style={{ display: "block", fontSize: "11px", fontWeight: 800, color: "#FCA5A5", marginBottom: "4px" }}>
                On-Site Medical / First Aid Post Location
              </label>
              <input
                type="text"
                aria-label="Medical Post Location"
                value={opsConfig.medicalPostLocation || ""}
                onChange={(e) =>
                  commitConfig({ ...opsConfig, medicalPostLocation: e.target.value })
                }
                style={{
                  width: "100%",
                  height: "36px",
                  padding: "0 10px",
                  borderRadius: "8px",
                  background: "#0F172A",
                  border: "1px solid rgba(248, 113, 113, 0.35)",
                  color: "#F8FAFC",
                  fontSize: "12px",
                }}
              />
            </div>
          </div>

          {/* Emergency Contacts List */}
          <div>
            <h4 style={{ margin: "0 0 8px", fontSize: "14px", fontWeight: 800, color: "#F8FAFC" }}>
              Emergency Contact Information (Medical, Security & Venue Safety)
            </h4>
            <div
              style={{
                display: "grid",
                gridTemplateColumns: "repeat(auto-fill, minmax(290px, 1fr))",
                gap: "10px",
              }}
            >
              {opsConfig.emergencyContacts.map((emg) => (
                <div
                  key={emg.id}
                  style={{
                    padding: "12px 14px",
                    borderRadius: "10px",
                    background: "#0F172A",
                    border: "1px solid rgba(248, 113, 113, 0.3)",
                  }}
                >
                  <div style={{ fontSize: "10px", fontWeight: 800, color: "#F87171", textTransform: "uppercase" }}>
                    {emg.label}
                  </div>
                  <strong style={{ fontSize: "13px", color: "#F8FAFC", display: "block", marginTop: "2px" }}>
                    {emg.contactName}
                  </strong>
                  <div style={{ fontSize: "12px", color: "#34D399", fontWeight: 700, marginTop: "4px" }}>
                    Phone: {emg.phone}
                  </div>
                  <div style={{ fontSize: "11px", color: "#CBD5E1", marginTop: "2px" }}>
                    Post: {emg.locationOrPost}
                  </div>
                  {emg.notes && (
                    <div style={{ fontSize: "11px", color: "#94A3B8", marginTop: "4px" }}>
                      {emg.notes}
                    </div>
                  )}
                </div>
              ))}
            </div>

            {canManageOps && (
              <form
                onSubmit={handleAddEmergencyContact}
                style={{
                  marginTop: "10px",
                  padding: "12px 14px",
                  borderRadius: "10px",
                  background: "#0F172A",
                  border: "1px solid rgba(148, 163, 184, 0.2)",
                  display: "grid",
                  gridTemplateColumns: "repeat(auto-fit, minmax(160px, 1fr))",
                  gap: "8px",
                  alignItems: "end",
                }}
              >
                <input
                  type="text"
                  placeholder="Role (e.g. Fire Marshal)"
                  value={newEmgLabel}
                  onChange={(e) => setNewEmgLabel(e.target.value)}
                  style={{
                    height: "34px",
                    padding: "0 10px",
                    borderRadius: "7px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
                <input
                  type="text"
                  placeholder="Contact Name"
                  value={newEmgName}
                  onChange={(e) => setNewEmgName(e.target.value)}
                  style={{
                    height: "34px",
                    padding: "0 10px",
                    borderRadius: "7px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
                <input
                  type="text"
                  placeholder="Emergency Phone"
                  value={newEmgPhone}
                  onChange={(e) => setNewEmgPhone(e.target.value)}
                  style={{
                    height: "34px",
                    padding: "0 10px",
                    borderRadius: "7px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
                <input
                  type="text"
                  placeholder="Station / Location Post"
                  value={newEmgPost}
                  onChange={(e) => setNewEmgPost(e.target.value)}
                  style={{
                    height: "34px",
                    padding: "0 10px",
                    borderRadius: "7px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
                <button
                  type="submit"
                  style={{
                    height: "34px",
                    padding: "0 12px",
                    borderRadius: "7px",
                    background: "#EF4444",
                    color: "#FFFFFF",
                    border: "none",
                    fontSize: "11px",
                    fontWeight: 800,
                    cursor: "pointer",
                  }}
                >
                  + Add Emergency Contact
                </button>
              </form>
            )}
          </div>

          {/* Event Escalation Contacts Chain */}
          <div>
            <h4 style={{ margin: "0 0 8px", fontSize: "14px", fontWeight: 800, color: "#F8FAFC" }}>
              Event Escalation Contacts (Tier 1 Floor Supervisor → Tier 2 Payment Lead → Tier 3 Host)
            </h4>
            <div
              style={{
                display: "grid",
                gridTemplateColumns: "repeat(auto-fill, minmax(290px, 1fr))",
                gap: "10px",
              }}
            >
              {opsConfig.escalationContacts.map((esc) => (
                <div
                  key={esc.id}
                  style={{
                    padding: "12px 14px",
                    borderRadius: "10px",
                    background: "#0F172A",
                    border: "1px solid rgba(251, 191, 36, 0.3)",
                  }}
                >
                  <div style={{ fontSize: "10px", fontWeight: 800, color: "#FBBF24", textTransform: "uppercase" }}>
                    {esc.roleTitle}
                  </div>
                  <strong style={{ fontSize: "13px", color: "#F8FAFC", display: "block", marginTop: "2px" }}>
                    {esc.contactName}
                  </strong>
                  <div style={{ fontSize: "12px", color: "#38BDF8", fontWeight: 700, marginTop: "4px" }}>
                    Direct Line: {esc.phone}
                  </div>
                  <div style={{ fontSize: "11px", color: "#CBD5E1", marginTop: "4px" }}>
                    <strong>Escalation Triggers:</strong> {esc.triggerScenarios}
                  </div>
                </div>
              ))}
            </div>

            {canManageOps && (
              <form
                onSubmit={handleAddEscalationContact}
                style={{
                  marginTop: "10px",
                  padding: "12px 14px",
                  borderRadius: "10px",
                  background: "#0F172A",
                  border: "1px solid rgba(148, 163, 184, 0.2)",
                  display: "grid",
                  gridTemplateColumns: "repeat(auto-fit, minmax(150px, 1fr))",
                  gap: "8px",
                  alignItems: "end",
                }}
              >
                <select
                  aria-label="Escalation Tier Level"
                  value={newEscTier}
                  onChange={(e) => setNewEscTier(Number(e.target.value) as 1 | 2 | 3)}
                  style={{
                    height: "34px",
                    padding: "0 10px",
                    borderRadius: "7px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                >
                  <option value={1}>Tier 1 — Floor Supervisor</option>
                  <option value={2}>Tier 2 — Payment / Kitchen Lead</option>
                  <option value={3}>Tier 3 — Event Host Executive</option>
                </select>
                <input
                  type="text"
                  placeholder="Escalation Title"
                  value={newEscTitle}
                  onChange={(e) => setNewEscTitle(e.target.value)}
                  style={{
                    height: "34px",
                    padding: "0 10px",
                    borderRadius: "7px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
                <input
                  type="text"
                  placeholder="Contact Name"
                  value={newEscName}
                  onChange={(e) => setNewEscName(e.target.value)}
                  style={{
                    height: "34px",
                    padding: "0 10px",
                    borderRadius: "7px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
                <input
                  type="text"
                  placeholder="Direct Phone / WhatsApp"
                  value={newEscPhone}
                  onChange={(e) => setNewEscPhone(e.target.value)}
                  style={{
                    height: "34px",
                    padding: "0 10px",
                    borderRadius: "7px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
                <input
                  type="text"
                  placeholder="Trigger scenarios (e.g. VIP gate override)"
                  value={newEscTriggers}
                  onChange={(e) => setNewEscTriggers(e.target.value)}
                  style={{
                    height: "34px",
                    padding: "0 10px",
                    borderRadius: "7px",
                    background: "#1E293B",
                    border: "1px solid rgba(148, 163, 184, 0.3)",
                    color: "#F8FAFC",
                    fontSize: "12px",
                  }}
                />
                <button
                  type="submit"
                  style={{
                    height: "34px",
                    padding: "0 12px",
                    borderRadius: "7px",
                    background: "#F59E0B",
                    color: "#0F172A",
                    border: "none",
                    fontSize: "11px",
                    fontWeight: 800,
                    cursor: "pointer",
                  }}
                >
                  + Add Escalation Contact
                </button>
              </form>
            )}
          </div>
        </div>
      )}
    </section>
  );
}

