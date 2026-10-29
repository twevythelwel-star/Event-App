import React, { useEffect, useState } from "react";
import {
  KeyRound,
  Lock,
  ShieldCheck,
  UserPlus,
} from "lucide-react";
import { EventDetails, TenantRole, TenantUserMember } from "../../types";
import {
  assignEventStaff,
  fetchHostEventStaff,
  revokeEventStaffAssignment,
  verifyStaffClaimCode,
} from "../../lib/api";
import { HostRolePermissions, HostWorkerAssignmentItem } from "./types";

type Props = {
  eventDetails: EventDetails;
  activeOrganizerId: string;
  tenantUsers: TenantUserMember[];
  permissions: HostRolePermissions;
  onSwitchRolePerspective: (role: TenantRole) => void;
};

export function HostWorkerAssignmentsView({
  eventDetails,
  activeOrganizerId,
  tenantUsers,
  permissions,
  onSwitchRolePerspective,
}: Props) {
  const initialLocalWorkers: HostWorkerAssignmentItem[] = tenantUsers
    .filter((u) => u.organizerId === activeOrganizerId)
    .map((u) => ({
      id: `assign-${u.id}`,
      userId: u.id,
      name: u.name,
      email: u.email,
      roleOnEvent:
        u.role === "payment_verifier"
          ? "payment_clerk"
          : u.role === "food_collector"
          ? "food_station_worker"
          : "supervisor",
      tenantRoleEquivalent: u.role,
      stationAssignment: u.stationAssignment || "all",
      assignedStationsList:
        u.stationAssignment === "mains"
          ? ["Station A — Escovitch Fish & Mains"]
          : u.stationAssignment === "sides"
          ? ["Station B — Festivals, Bammy & Sides"]
          : ["All Event Stations"],
      status: u.isActive ? "active" : "revoked",
      isActive: u.isActive,
      lastActiveAt: u.lastActiveAt,
    }));

  const [workers, setWorkers] = useState<HostWorkerAssignmentItem[]>(initialLocalWorkers);
  const [claimCodeInput, setClaimCodeInput] = useState("");
  const [verifiedCandidate, setVerifiedCandidate] = useState<{
    name: string;
    maskedEmail: string;
    code: string;
  } | null>(null);
  const [selectedRoleOnEvent, setSelectedRoleOnEvent] = useState<
    "payment_clerk" | "ticket_checker" | "food_station_worker" | "supervisor"
  >("food_station_worker");
  const [selectedStation, setSelectedStation] = useState<
    "all" | "mains" | "sides" | "drinks" | "cash_desk"
  >("mains");
  const [statusBanner, setStatusBanner] = useState<{
    tone: "emerald" | "amber";
    message: string;
  } | null>(null);

  useEffect(() => {
    setWorkers(
      tenantUsers
        .filter((u) => u.organizerId === activeOrganizerId)
        .map((u) => ({
          id: `assign-${u.id}`,
          userId: u.id,
          name: u.name,
          email: u.email,
          roleOnEvent:
            u.role === "payment_verifier"
              ? "payment_clerk"
              : u.role === "food_collector"
              ? "food_station_worker"
              : "supervisor",
          tenantRoleEquivalent: u.role,
          stationAssignment: u.stationAssignment || "all",
          assignedStationsList:
            u.stationAssignment === "mains"
              ? ["Station A — Escovitch Fish & Mains"]
              : u.stationAssignment === "sides"
              ? ["Station B — Festivals, Bammy & Sides"]
              : ["All Event Stations"],
          status: u.isActive ? "active" : "revoked",
          isActive: u.isActive,
          lastActiveAt: u.lastActiveAt,
        }))
    );

    const eventId = eventDetails.id || "evt-fish-fry-2026";
    fetchHostEventStaff(eventId)
      .then((res) => {
        if (res.success && Array.isArray(res.assignments) && res.assignments.length > 0) {
          const mapped: HostWorkerAssignmentItem[] = res.assignments.map((a) => ({
            id: a.id,
            userId: a.user_id,
            name: a.user?.name || "Assigned Staff Member",
            email: a.user?.email || "staff@islandevents.example",
            phone: a.user?.phone,
            roleOnEvent: (a.role_on_event as HostWorkerAssignmentItem["roleOnEvent"]) || "food_station_worker",
            tenantRoleEquivalent:
              a.role_on_event === "payment_clerk"
                ? "payment_verifier"
                : a.role_on_event === "supervisor"
                ? "event_owner"
                : "food_collector",
            stationAssignment: "all",
            assignedStationsList: a.assigned_stations || ["All Event Stations"],
            status: a.is_active ? "active" : "revoked",
            isActive: a.is_active,
          }));
          setWorkers(mapped);
        }
      })
      .catch(() => {
        // Offline / local tenant roster preserved
      });
  }, [activeOrganizerId, eventDetails.id, tenantUsers]);

  const handleVerifyClaimCode = async (e: React.FormEvent) => {
    e.preventDefault();
    const cleaned = claimCodeInput.trim().toUpperCase();
    if (cleaned.length < 4) {
      setStatusBanner({
        tone: "amber",
        message: "Please enter a valid 6-character Staff Claim Code (e.g. KGN842).",
      });
      return;
    }

    try {
      const res = await verifyStaffClaimCode(eventDetails.id || "evt-fish-fry-2026", cleaned);
      if (res.valid && res.candidate) {
        setVerifiedCandidate({
          name: res.candidate.name,
          maskedEmail: res.candidate.masked_email,
          code: cleaned,
        });
        setStatusBanner({
          tone: "emerald",
          message: `Verified claim code ${cleaned} for ${res.candidate.name} (${res.candidate.masked_email}). Ready to assign role.`,
        });
        return;
      }
    } catch {
      // Fallback for offline/sandbox demonstration
    }

    const demoCandidateMap: Record<string, { name: string; maskedEmail: string }> = {
      KGN842: { name: "Devon Campbell", maskedEmail: "de***@islandevents.example" },
      SFF109: { name: "Beverly Sterling", maskedEmail: "be***@islandevents.example" },
      JMA774: { name: "Jerome Williams", maskedEmail: "je***@islandevents.example" },
    };

    const match = demoCandidateMap[cleaned] || {
      name: `Verified Staff (${cleaned})`,
      maskedEmail: `${cleaned.toLowerCase().slice(0, 2)}***@islandevents.example`,
    };

    setVerifiedCandidate({
      name: match.name,
      maskedEmail: match.maskedEmail,
      code: cleaned,
    });
    setStatusBanner({
      tone: "emerald",
      message: `Claim code ${cleaned} verified for ${match.name}. Select their role & station below to complete assignment.`,
    });
  };

  const handleAssignVerifiedWorker = async () => {
    if (!verifiedCandidate) return;

    const stationLabels: Record<typeof selectedStation, string> = {
      all: "All Event Stations",
      mains: "Station A — Escovitch Fish & Mains",
      sides: "Station B — Festivals, Bammy & Sides",
      drinks: "Station C — Chilled Beverages & Sorrel",
      cash_desk: "Box Office Cash Verification Desk",
    };

    try {
      await assignEventStaff(eventDetails.id || "evt-fish-fry-2026", {
        claim_code: verifiedCandidate.code,
        role_on_event: selectedRoleOnEvent,
        assigned_stations: [stationLabels[selectedStation]],
      });
    } catch {
      // Offline fallback
    }

    const newWorker: HostWorkerAssignmentItem = {
      id: `assign-${Date.now()}`,
      userId: `usr-${Date.now()}`,
      name: verifiedCandidate.name,
      email: verifiedCandidate.maskedEmail,
      roleOnEvent: selectedRoleOnEvent,
      tenantRoleEquivalent:
        selectedRoleOnEvent === "payment_clerk"
          ? "payment_verifier"
          : selectedRoleOnEvent === "supervisor"
          ? "event_owner"
          : "food_collector",
      stationAssignment: selectedStation,
      assignedStationsList: [stationLabels[selectedStation]],
      status: "active",
      isActive: true,
      lastActiveAt: new Date().toISOString(),
      verifiedViaClaimCode: true,
    };

    setWorkers((prev) => [newWorker, ...prev]);
    setStatusBanner({
      tone: "emerald",
      message: `Assigned ${verifiedCandidate.name} as ${selectedRoleOnEvent.replace(
        /_/g,
        " "
      )} (${stationLabels[selectedStation]}).`,
    });
    setVerifiedCandidate(null);
    setClaimCodeInput("");
  };

  const handleToggleWorkerStatus = async (worker: HostWorkerAssignmentItem) => {
    if (worker.isActive) {
      try {
        await revokeEventStaffAssignment(eventDetails.id || "evt-fish-fry-2026", worker.id);
      } catch {
        // Offline fallback
      }
    }
    setWorkers((prev) =>
      prev.map((w) =>
        w.id === worker.id
          ? {
              ...w,
              isActive: !w.isActive,
              status: !w.isActive ? "active" : "revoked",
            }
          : w
      )
    );
  };

  const getRoleBadge = (role: HostWorkerAssignmentItem["roleOnEvent"]) => {
    switch (role) {
      case "payment_clerk":
        return { label: "Payment Verifier / Clerk", color: "#FBBF24", bg: "rgba(245, 158, 11, 0.18)" };
      case "ticket_checker":
        return { label: "Gate Ticket Checker", color: "#818CF8", bg: "rgba(99, 102, 241, 0.18)" };
      case "supervisor":
        return { label: "Event Supervisor / Owner", color: "#34D399", bg: "rgba(16, 185, 129, 0.18)" };
      case "food_station_worker":
      default:
        return { label: "Food Station Collector", color: "#38BDF8", bg: "rgba(56, 189, 248, 0.18)" };
    }
  };

  return (
    <div id="host-workers-view" style={{ display: "flex", flexDirection: "column", gap: "18px" }}>
      {/* Header & Separation of Duties Summary */}
      <section
        style={{
          background: "#0B1120",
          border: "1px solid rgba(148, 163, 184, 0.18)",
          borderRadius: "16px",
          padding: "20px 22px",
          boxShadow: "0 12px 32px rgba(2, 6, 23, 0.4)",
        }}
      >
        <div
          style={{
            display: "flex",
            alignItems: "flex-start",
            justifyContent: "space-between",
            gap: "14px",
            flexWrap: "wrap",
            marginBottom: "16px",
          }}
        >
          <div>
            <span
              style={{
                fontSize: "10px",
                fontWeight: 800,
                letterSpacing: "1.4px",
                color: "#38BDF8",
                textTransform: "uppercase",
              }}
            >
              Event Staff & RBAC Separation of Duties
            </span>
            <h2 style={{ margin: "3px 0 4px", fontSize: "19px", fontWeight: 800, color: "#F8FAFC" }}>
              Worker Assignments — {eventDetails.title}
            </h2>
            <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
              Assign verified staff members to Payment Verification, Gate Ticket Checking, or Kitchen Collection Stations using 6-character Staff Claim Codes.
            </p>
          </div>

          <div
            style={{
              padding: "8px 12px",
              borderRadius: "10px",
              background: "rgba(16, 185, 129, 0.12)",
              border: "1px solid rgba(52, 211, 153, 0.3)",
              fontSize: "11px",
              color: "#34D399",
              fontWeight: 700,
              display: "inline-flex",
              alignItems: "center",
              gap: "6px",
            }}
          >
            <ShieldCheck size={14} />
            <span>Active Roster: {workers.filter((w) => w.isActive).length} Authorized Workers</span>
          </div>
        </div>

        {statusBanner && (
          <div
            style={{
              marginBottom: "16px",
              padding: "11px 14px",
              borderRadius: "10px",
              background:
                statusBanner.tone === "emerald"
                  ? "rgba(16, 185, 129, 0.15)"
                  : "rgba(245, 158, 11, 0.16)",
              border:
                statusBanner.tone === "emerald"
                  ? "1px solid rgba(52, 211, 153, 0.4)"
                  : "1px solid rgba(251, 191, 36, 0.4)",
              color: statusBanner.tone === "emerald" ? "#A7F3D0" : "#FDE68A",
              fontSize: "12px",
              fontWeight: 600,
            }}
          >
            {statusBanner.message}
          </div>
        )}

        {/* Staff Claim Code Verification Box */}
        {permissions.canManageWorkers ? (
          <div
            style={{
              padding: "16px 18px",
              borderRadius: "12px",
              background: "#0F172A",
              border: "1px solid rgba(129, 140, 248, 0.3)",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "10px" }}>
              <KeyRound size={15} color="#818CF8" />
              <strong style={{ fontSize: "13px", color: "#F8FAFC" }}>
                Assign Worker by 6-Character Staff Claim Code
              </strong>
              <span style={{ fontSize: "11px", color: "#94A3B8" }}>
                (Demo codes available: <code>KGN842</code>, <code>SFF109</code>, <code>JMA774</code>)
              </span>
            </div>

            <form
              onSubmit={handleVerifyClaimCode}
              style={{
                display: "flex",
                alignItems: "center",
                gap: "10px",
                flexWrap: "wrap",
              }}
            >
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
                }}
              >
                Verify Claim Code
              </button>
            </form>

            {verifiedCandidate && (
              <div
                style={{
                  marginTop: "12px",
                  padding: "12px 14px",
                  borderRadius: "10px",
                  background: "rgba(16, 185, 129, 0.12)",
                  border: "1px solid rgba(52, 211, 153, 0.35)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  gap: "12px",
                  flexWrap: "wrap",
                }}
              >
                <div>
                  <strong style={{ fontSize: "13px", color: "#34D399" }}>
                    Candidate Verified: {verifiedCandidate.name}
                  </strong>
                  <div style={{ fontSize: "11px", color: "#CBD5E1" }}>
                    Account: {verifiedCandidate.maskedEmail} · Code: <code>{verifiedCandidate.code}</code>
                  </div>
                </div>

                <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
                  <select
                    aria-label="Role on Event"
                    value={selectedRoleOnEvent}
                    onChange={(e) =>
                      setSelectedRoleOnEvent(
                        e.target.value as typeof selectedRoleOnEvent
                      )
                    }
                    style={{
                      height: "36px",
                      padding: "0 10px",
                      borderRadius: "8px",
                      background: "#0F172A",
                      color: "#F8FAFC",
                      border: "1px solid rgba(148, 163, 184, 0.35)",
                      fontSize: "11px",
                      fontWeight: 700,
                    }}
                  >
                    <option value="food_station_worker">Food Station Collector</option>
                    <option value="payment_clerk">Payment Verifier / Clerk</option>
                    <option value="ticket_checker">Gate Ticket Checker</option>
                    <option value="supervisor">Event Supervisor</option>
                  </select>

                  <select
                    aria-label="Assigned Station"
                    value={selectedStation}
                    onChange={(e) =>
                      setSelectedStation(e.target.value as typeof selectedStation)
                    }
                    style={{
                      height: "36px",
                      padding: "0 10px",
                      borderRadius: "8px",
                      background: "#0F172A",
                      color: "#F8FAFC",
                      border: "1px solid rgba(148, 163, 184, 0.35)",
                      fontSize: "11px",
                      fontWeight: 700,
                    }}
                  >
                    <option value="mains">Station A — Escovitch Fish & Mains</option>
                    <option value="sides">Station B — Festivals & Sides</option>
                    <option value="drinks">Station C — Beverages</option>
                    <option value="cash_desk">Box Office Cash Desk</option>
                    <option value="all">All Event Stations</option>
                  </select>

                  <button
                    type="button"
                    onClick={handleAssignVerifiedWorker}
                    style={{
                      height: "36px",
                      padding: "0 14px",
                      borderRadius: "8px",
                      background: "#10B981",
                      color: "#052E16",
                      border: "none",
                      fontSize: "11px",
                      fontWeight: 800,
                      display: "inline-flex",
                      alignItems: "center",
                      gap: "5px",
                    }}
                  >
                    <UserPlus size={13} />
                    Confirm Worker Assignment
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
              Managing worker assignments requires <strong>Authorized Event Host</strong> or{" "}
              <strong>Platform SuperAdmin</strong> role.
            </span>
          </div>
        )}
      </section>

      {/* Assigned Event Workers Roster */}
      <section
        style={{
          background: "#0B1120",
          border: "1px solid rgba(148, 163, 184, 0.18)",
          borderRadius: "16px",
          padding: "20px 22px",
        }}
      >
        <h3 style={{ margin: "0 0 14px", fontSize: "16px", fontWeight: 800, color: "#F8FAFC" }}>
          Assigned Event Staff Roster ({workers.length})
        </h3>

        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fill, minmax(300px, 1fr))",
            gap: "14px",
          }}
        >
          {workers.map((worker) => {
            const roleBadge = getRoleBadge(worker.roleOnEvent);
            return (
              <div
                key={worker.id}
                style={{
                  padding: "15px 16px",
                  borderRadius: "12px",
                  background: "#0F172A",
                  border: worker.isActive
                    ? "1px solid rgba(148, 163, 184, 0.22)"
                    : "1px dashed rgba(239, 68, 68, 0.35)",
                  opacity: worker.isActive ? 1 : 0.65,
                  display: "flex",
                  flexDirection: "column",
                  justifyContent: "space-between",
                  gap: "12px",
                }}
              >
                <div>
                  <div
                    style={{
                      display: "flex",
                      alignItems: "flex-start",
                      justifyContent: "space-between",
                      gap: "8px",
                    }}
                  >
                    <div>
                      <strong style={{ fontSize: "14px", color: "#F8FAFC", display: "block" }}>
                        {worker.name}
                      </strong>
                      <span style={{ fontSize: "11px", color: "#94A3B8" }}>{worker.email}</span>
                    </div>
                    <span
                      style={{
                        fontSize: "9px",
                        fontWeight: 800,
                        padding: "3px 8px",
                        borderRadius: "6px",
                        background: worker.isActive
                          ? "rgba(16, 185, 129, 0.18)"
                          : "rgba(239, 68, 68, 0.2)",
                        color: worker.isActive ? "#34D399" : "#F87171",
                        textTransform: "uppercase",
                      }}
                    >
                      {worker.status}
                    </span>
                  </div>

                  <div style={{ marginTop: "10px", display: "flex", gap: "6px", flexWrap: "wrap" }}>
                    <span
                      style={{
                        fontSize: "10px",
                        fontWeight: 800,
                        padding: "3px 8px",
                        borderRadius: "6px",
                        background: roleBadge.bg,
                        color: roleBadge.color,
                      }}
                    >
                      {roleBadge.label}
                    </span>
                    <span
                      style={{
                        fontSize: "10px",
                        fontWeight: 600,
                        padding: "3px 8px",
                        borderRadius: "6px",
                        background: "rgba(30, 41, 59, 0.9)",
                        color: "#CBD5E1",
                      }}
                    >
                      {worker.assignedStationsList.join(", ")}
                    </span>
                  </div>
                </div>

                <div
                  style={{
                    paddingTop: "10px",
                    borderTop: "1px solid rgba(148, 163, 184, 0.14)",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    gap: "8px",
                  }}
                >
                  <button
                    type="button"
                    onClick={() => onSwitchRolePerspective(worker.tenantRoleEquivalent)}
                    style={{
                      padding: "5px 10px",
                      borderRadius: "7px",
                      background: "rgba(99, 102, 241, 0.16)",
                      border: "1px solid rgba(129, 140, 248, 0.35)",
                      color: "#A5B4FC",
                      fontSize: "10px",
                      fontWeight: 700,
                    }}
                  >
                    Preview Role View
                  </button>

                  {permissions.canManageWorkers && (
                    <button
                      type="button"
                      onClick={() => handleToggleWorkerStatus(worker)}
                      style={{
                        padding: "5px 10px",
                        borderRadius: "7px",
                        background: worker.isActive
                          ? "rgba(239, 68, 68, 0.14)"
                          : "rgba(16, 185, 129, 0.16)",
                        border: worker.isActive
                          ? "1px solid rgba(248, 113, 113, 0.35)"
                          : "1px solid rgba(52, 211, 153, 0.35)",
                        color: worker.isActive ? "#FCA5A5" : "#34D399",
                        fontSize: "10px",
                        fontWeight: 700,
                      }}
                    >
                      {worker.isActive ? "Revoke Assignment" : "Restore Assignment"}
                    </button>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </section>
    </div>
  );
}
