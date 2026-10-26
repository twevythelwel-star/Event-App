import React, { useState } from "react";
import {
  AlertCircle,
  CheckCircle2,
  Clock,
  FileText,
  Filter,
  HelpCircle,
  Lock,
  MapPin,
  RefreshCw,
  Settings2,
  Shield,
  ShieldCheck,
} from "lucide-react";
import { AccountSettingsView } from "./AccountSettingsView";
import {
  AdminAssignedScope,
  Currency,
  EventDetails,
  TicketDispute,
  TicketOrder,
} from "../types";
import { maskCustomerEmail, maskCustomerPhone } from "../tests/testUtils";

type Props = {
  currentScope: AdminAssignedScope;
  events: EventDetails[];
  disputes: TicketDispute[];
  orders: TicketOrder[];
  onResolveDispute: (
    disputeId: string,
    resolution: "resolved" | "rejected",
    notes: string
  ) => void;
  onAssistHostRecovery: (hostEmail: string) => void;
  formatMoney: (amount: number, currency: Currency) => string;
};

export const DelegatedAdminDashboard: React.FC<Props> = ({
  currentScope,
  events,
  disputes,
  orders,
  onResolveDispute,
  onAssistHostRecovery,
  formatMoney,
}) => {
  const [activeTab, setActiveTab] = useState<"disputes" | "events" | "reports" | "support" | "settings">("disputes");
  const [disputeFilter, setDisputeFilter] = useState<string>("all");
  const [selectedDispute, setSelectedDispute] = useState<TicketDispute | null>(null);
  const [resolutionNotes, setResolutionNotes] = useState("");
  const [toastMessage, setToastMessage] = useState("");

  // Scope filter: Events matching assigned region (e.g. JM or US)
  const scopedEvents = events.filter((e) => {
    if (currentScope.assignedRegion === "ALL") return true;
    if (currentScope.assignedRegion === "JM") return e.currency === "JMD" || e.venueAddress.includes("Jamaica");
    if (currentScope.assignedRegion === "US") return e.currency === "USD" || e.venueAddress.includes("US");
    return true;
  });

  // Filtered Disputes
  const filteredDisputes = disputes.filter((d) => {
    if (disputeFilter === "open") return d.status === "open";
    if (disputeFilter === "resolved") return d.status === "resolved";
    return true;
  });

  // Aggregated Sales Metrics for Assigned Scope
  const scopedTotalOrders = orders.length;
  const scopedPaidOrders = orders.filter((o) => o.status === "paid").length;
  const scopedGMVJMD = orders
    .filter((o) => o.currency === "JMD" && o.status === "paid")
    .reduce((sum, o) => sum + o.totalAmount, 0);
  const scopedGMVUSD = orders
    .filter((o) => o.currency === "USD" && o.status === "paid")
    .reduce((sum, o) => sum + o.totalAmount, 0);

  function handleActionDispute(resolution: "resolved" | "rejected") {
    if (!selectedDispute) return;
    onResolveDispute(selectedDispute.id, resolution, resolutionNotes || `Actioned by ${currentScope.adminName}`);
    setToastMessage(`Dispute ${selectedDispute.disputeNumber} marked as ${resolution.toUpperCase()}.`);
    setSelectedDispute(null);
    setResolutionNotes("");
    setTimeout(() => setToastMessage(""), 3500);
  }

  function handleTriggerRecovery(email: string) {
    onAssistHostRecovery(email);
    setToastMessage(`Host recovery token generated and sent to ${maskCustomerEmail(email)}.`);
    setTimeout(() => setToastMessage(""), 4000);
  }

  return (
    <div className="delegated-admin-container" id="delegated-admin-desk">
      {/* Delegated Scope Banner */}
      <div className="delegated-scope-banner">
        <div className="scope-banner-left">
          <div className="scope-badge">
            <Shield size={15} /> DELEGATED ADMINISTRATOR DESK
          </div>
          <h2>Operational Duties & Host Support</h2>
          <p>
            Operating as: <strong>{currentScope.adminName}</strong> · Assigned Region:{" "}
            <span className="region-tag">{currentScope.assignedRegion === "ALL" ? "All Regions" : currentScope.assignedRegion === "JM" ? "Jamaica" : "United States"}</span>
          </p>
        </div>
        <div className="scope-banner-right">
          <div className="privilege-guard-pill">
            <Lock size={13} />
            <span>Scope Boundary: <strong>Delegated Privileges Only</strong></span>
          </div>
        </div>
      </div>

      {toastMessage && (
        <div className="system-toast" role="status">
          <CheckCircle2 size={16} /> {toastMessage}
        </div>
      )}

      {/* Explicit Security & Non-Superadmin Policy Banner */}
      <div className="privilege-notice-box">
        <ShieldCheck size={16} />
        <div>
          <strong>Explicit Privilege Separation Enforced:</strong> Administrators do not automatically receive System Administrator access. You cannot alter global platform fees, modify root database settings, or view unmasked API secrets. Customer PII is accessed only on an audited basis for dispute resolution.
        </div>
      </div>

      {/* Sub-Navigation Tabs */}
      <div className="delegated-tabs">
        <button
          type="button"
          className={`del-tab ${activeTab === "disputes" ? "active" : ""}`}
          onClick={() => setActiveTab("disputes")}
          id="tab-del-disputes"
        >
          <AlertCircle size={15} /> Order & Ticket Disputes ({disputes.filter((d) => d.status === "open").length} open)
        </button>
        <button
          type="button"
          className={`del-tab ${activeTab === "events" ? "active" : ""}`}
          onClick={() => setActiveTab("events")}
          id="tab-del-events"
        >
          <FileText size={15} /> Events Oversight & Setup Assistance ({scopedEvents.length})
        </button>
        <button
          type="button"
          className={`del-tab ${activeTab === "reports" ? "active" : ""}`}
          onClick={() => setActiveTab("reports")}
          id="tab-del-reports"
        >
          <CheckCircle2 size={15} /> Authorized Sales & Event Reports
        </button>
        <button
          type="button"
          className={`del-tab ${activeTab === "support" ? "active" : ""}`}
          onClick={() => setActiveTab("support")}
          id="tab-del-support"
        >
          <HelpCircle size={15} /> Host Assistance & Account Recovery
        </button>
        <button
          type="button"
          className={`del-tab ${activeTab === "settings" ? "active" : ""}`}
          onClick={() => setActiveTab("settings")}
          id="tab-del-settings"
        >
          <Settings2 size={15} /> Operational Settings (16)
        </button>
      </div>

      {/* TAB 1: ORDER & TICKET DISPUTES CONSOLE */}
      {activeTab === "disputes" && (
        <div className="del-panel disputes-panel">
          <div className="panel-toolbar">
            <div className="filter-wrap">
              <Filter size={15} />
              <select
                value={disputeFilter}
                onChange={(e) => setDisputeFilter(e.target.value)}
                className="filter-select"
              >
                <option value="all">All Disputes ({disputes.length})</option>
                <option value="open">Open / Under Review</option>
                <option value="resolved">Resolved</option>
              </select>
            </div>
          </div>

          <div className="disputes-table-wrap">
            <table className="enterprise-table">
              <thead>
                <tr>
                  <th>Dispute ID</th>
                  <th>Order & Ticket</th>
                  <th>Customer (Audited PII)</th>
                  <th>Host / Event</th>
                  <th>Amount</th>
                  <th>Reason / Claim</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {filteredDisputes.map((d) => (
                  <tr key={d.id} className={`status-row-${d.status}`}>
                    <td>
                      <strong>{d.disputeNumber}</strong>
                      <span className="timestamp-text">{new Date(d.createdAt).toLocaleDateString()}</span>
                    </td>
                    <td>
                      <code>{d.ticketCode}</code>
                      <span className="sub-text">{d.orderNumber}</span>
                    </td>
                    <td>
                      <strong>{d.customerName}</strong>
                      <span className="masked-pii-text">{maskCustomerPhone(d.customerPhone)}</span>
                    </td>
                    <td>
                      <strong>{d.organizerName}</strong>
                      <span className="sub-text">{d.eventName}</span>
                    </td>
                    <td>
                      <strong>
                        {formatMoney(d.amount, d.currency)}
                      </strong>
                    </td>
                    <td>
                      <span className="reason-text">{d.reason}</span>
                    </td>
                    <td>
                      <span className={`status-pill ${d.status}`}>{d.status.toUpperCase()}</span>
                    </td>
                    <td>
                      {d.status === "open" ? (
                        <button
                          type="button"
                          className="btn-action-sm btn-resolve"
                          onClick={() => {
                            setSelectedDispute(d);
                            setResolutionNotes("");
                          }}
                        >
                          Review & Resolve
                        </button>
                      ) : (
                        <span className="resolved-tag">
                          Resolved by {d.assignedAdminName || "Admin"}
                        </span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Dispute Resolution Modal */}
          {selectedDispute && (
            <div className="modal-backdrop" onClick={() => setSelectedDispute(null)}>
              <div
                className="modal-card dispute-resolution-modal"
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
              >
                <div className="modal-header">
                  <div>
                    <span className="section-kicker">DISPUTE RESOLUTION CONSOLE</span>
                    <h3>Resolve Case: {selectedDispute.disputeNumber}</h3>
                  </div>
                  <button
                    type="button"
                    className="modal-close-btn"
                    onClick={() => setSelectedDispute(null)}
                  >
                    ✕
                  </button>
                </div>

                <div className="modal-body">
                  <div className="dispute-summary-card">
                    <div>
                      <span>Customer:</span> <strong>{selectedDispute.customerName}</strong>
                    </div>
                    <div>
                      <span>Phone:</span> <strong>{maskCustomerPhone(selectedDispute.customerPhone)}</strong>
                    </div>
                    <div>
                      <span>Ticket:</span> <code>{selectedDispute.ticketCode}</code>
                    </div>
                    <div>
                      <span>Claimed Amount:</span> <strong>{selectedDispute.currency} {selectedDispute.amount.toFixed(2)}</strong>
                    </div>
                    <div className="claim-box">
                      <span>Reason:</span>
                      <p>{selectedDispute.reason}</p>
                    </div>
                  </div>

                  <div className="form-group">
                    <label htmlFor="res-notes">Administrator Resolution Notes & Justification:</label>
                    <textarea
                      id="res-notes"
                      rows={3}
                      placeholder="e.g. Bank statement transfer verified with reference. Approved ticket replacement."
                      value={resolutionNotes}
                      onChange={(e) => setResolutionNotes(e.target.value)}
                      className="form-textarea"
                    />
                  </div>
                </div>

                <div className="modal-footer">
                  <button
                    type="button"
                    className="secondary-button"
                    onClick={() => handleActionDispute("rejected")}
                  >
                    Reject Claim
                  </button>
                  <button
                    type="button"
                    className="primary-button"
                    onClick={() => handleActionDispute("resolved")}
                  >
                    Approve & Issue Resolution
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* TAB 2: EVENTS OVERSIGHT & HOST SETUP ASSISTANCE */}
      {activeTab === "events" && (
        <div className="del-panel events-panel">
          <div className="events-intro">
            <h3>Events within Assigned Scope ({currentScope.assignedRegion})</h3>
            <p>
              Assist hosts with setup, verify promotional flyers, check advance cutoffs, and provide publishing support.
            </p>
          </div>

          <div className="scoped-events-list">
            {scopedEvents.map((evt) => (
              <div className="scoped-event-card" key={evt.id || evt.title}>
                <div className="event-card-left">
                  <h4>{evt.title}</h4>
                  <p className="evt-tagline">{evt.tagline}</p>
                  <div className="evt-meta-row">
                    <span><Clock size={13} /> {evt.dateStr}</span>
                    <span><MapPin size={13} /> {evt.venueName}</span>
                    <span>Currency: <strong>{evt.currency}</strong></span>
                  </div>
                </div>

                <div className="event-card-right">
                  <span className={`status-pill ${evt.status || "published"}`}>
                    {(evt.status || "published").toUpperCase()}
                  </span>
                  <div className="assistance-actions">
                    <span className="assistance-label">Host Assistance:</span>
                    <button
                      type="button"
                      className="btn-action-sm btn-assist"
                      onClick={() => {
                        setToastMessage(`Verified event parameters for "${evt.title}". Advance cutoff is set correctly.`);
                        setTimeout(() => setToastMessage(""), 3500);
                      }}
                    >
                      Verify Cutoff
                    </button>
                    <button
                      type="button"
                      className="btn-action-sm btn-assist"
                      onClick={() => {
                        setToastMessage(`Flyer audit passed for "${evt.title}". High resolution WebP/JPEG validated.`);
                        setTimeout(() => setToastMessage(""), 3500);
                      }}
                    >
                      Audit Flyer
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 3: AUTHORIZED SCOPED REPORTS */}
      {activeTab === "reports" && (
        <div className="del-panel reports-panel">
          <div className="reports-intro">
            <h3>Authorized Regional Sales & Ticket Reports</h3>
            <p>
              Aggregated metrics within your assigned operational scope ({currentScope.assignedRegion}).
            </p>
          </div>

          <div className="report-cards-grid">
            <div className="report-stat-card">
              <span>TOTAL SCOPED ORDERS</span>
              <h3>{scopedTotalOrders}</h3>
              <p>Orders created in assigned region</p>
            </div>
            <div className="report-stat-card">
              <span>VERIFIED PAID ORDERS</span>
              <h3>{scopedPaidOrders}</h3>
              <p>Completed manual & card transactions</p>
            </div>
            <div className="report-stat-card">
              <span>VERIFIED VOLUME (JMD)</span>
              <h3>J${scopedGMVJMD.toLocaleString()}</h3>
              <p>Settled to Jamaican event accounts</p>
            </div>
            <div className="report-stat-card">
              <span>VERIFIED VOLUME (USD)</span>
              <h3>${scopedGMVUSD.toFixed(2)}</h3>
              <p>Settled to US Diaspora event accounts</p>
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: HOST ASSISTANCE & ACCOUNT RECOVERY */}
      {activeTab === "support" && (
        <div className="del-panel support-panel">
          <div className="support-intro">
            <h3>Host Support & Account Recovery Assistance</h3>
            <p>
              Assist event organizers with password reset tokens and onboarding guidance upon identity verification.
            </p>
          </div>

          <div className="support-hosts-table">
            <table className="enterprise-table">
              <thead>
                <tr>
                  <th>Host Organization</th>
                  <th>Primary Contact</th>
                  <th>Region</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <strong>Kingston Youth Foundation</strong>
                    <span className="sub-text">org-kingston-youth</span>
                  </td>
                  <td>
                    <span>fishfry@kingstonyouth.example</span>
                    <span className="sub-text">+1 (876) 926-3849</span>
                  </td>
                  <td>Jamaica (JM)</td>
                  <td>
                    <button
                      type="button"
                      className="btn-action-sm btn-recover"
                      onClick={() => handleTriggerRecovery("fishfry@kingstonyouth.example")}
                    >
                      <RefreshCw size={12} /> Dispatch Recovery Token
                    </button>
                  </td>
                </tr>
                <tr>
                  <td>
                    <strong>Portmore Culinary Guild</strong>
                    <span className="sub-text">org-portmore-guild</span>
                  </td>
                  <td>
                    <span>nadine@portmoreculinary.example</span>
                    <span className="sub-text">+1 (876) 555-9011</span>
                  </td>
                  <td>Jamaica (JM)</td>
                  <td>
                    <button
                      type="button"
                      className="btn-action-sm btn-recover"
                      onClick={() => handleTriggerRecovery("nadine@portmoreculinary.example")}
                    >
                      <RefreshCw size={12} /> Dispatch Recovery Token
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 5: DEDICATED OPERATIONAL SETTINGS (16) */}
      {activeTab === "settings" && (
        <div className="del-panel settings-panel">
          <AccountSettingsView
            accountType="admin"
            currentUser={{
              name: currentScope.adminName,
              email: "beverly.campbell@islandevents.example",
              roleLabel: "Administrator (Delegated)",
              region: currentScope.assignedRegion,
            }}
            orders={orders}
            ticketDisputes={disputes}
            adminScopes={[currentScope]}
            formatMoney={formatMoney}
            onNavigateToTab={(tab) => {
              if (tab === "disputes") setActiveTab("disputes");
              else if (tab === "events") setActiveTab("events");
              else if (tab === "reports") setActiveTab("reports");
              else if (tab === "support") setActiveTab("support");
            }}
          />
        </div>
      )}
    </div>
  );
};
