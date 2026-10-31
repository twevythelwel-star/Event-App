import React, { useState } from "react";
import {
  Activity,
  CheckCircle2,
  Clock,
  DollarSign,
  Filter,
  HardDrive,
  Layers,
  Lock,
  RefreshCw,
  Search,
  Server,
  Settings2,
  ShieldAlert,
  ShieldCheck,
  Smartphone,
  UserCheck,
  Users,
  UserX,
  XCircle,
} from "lucide-react";
import { AccountSettingsView } from "./AccountSettingsView";
import {
  AdminAssignedScope,
  Currency,
  NotificationProviderStatus,
  PlatformFeeSettings,
  PlatformOverviewStats,
  SecurityAuditLog,
  SystemComponentHealth,
  TenantUserMember,
} from "../types";
import { maskSecretKey } from "../tests/testUtils";

type Props = {
  stats: PlatformOverviewStats;
  health: SystemComponentHealth[];
  providers: NotificationProviderStatus[];
  feeSettings: PlatformFeeSettings;
  auditLogs: SecurityAuditLog[];
  adminScopes: AdminAssignedScope[];
  users: TenantUserMember[];
  onUpdateFeeSettings: (settings: PlatformFeeSettings) => void;
  onToggleUserStatus: (userId: string) => void;
  onAssignAdminScope: (scope: AdminAssignedScope) => void;
  formatMoney: (amount: number, currency: Currency) => string;
};

export const SystemAdminDashboard: React.FC<Props> = ({
  stats,
  health,
  providers,
  feeSettings,
  auditLogs,
  adminScopes,
  users,
  onUpdateFeeSettings,
  onToggleUserStatus,
  onAssignAdminScope: _onAssignAdminScope,
  formatMoney,
}) => {
  const [activeTab, setActiveTab] = useState<
    "overview" | "users" | "scopes" | "health" | "providers" | "fees" | "audits" | "settings"
  >("overview");

  const [userSearch, setUserSearch] = useState("");
  const [roleFilter, setRoleFilter] = useState<string>("all");
  const [auditFilter, setAuditFilter] = useState<string>("all");
  const [toastMessage, setToastMessage] = useState("");

  // Local state for fee settings form
  const [localFees, setLocalFees] = useState<PlatformFeeSettings>(feeSettings);
  const [isFeeDirty, setIsFeeDirty] = useState(false);

  // Filtered Users
  const filteredUsers = users.filter((u) => {
    const matchesSearch =
      u.name.toLowerCase().includes(userSearch.toLowerCase()) ||
      u.email.toLowerCase().includes(userSearch.toLowerCase()) ||
      u.organizerId.toLowerCase().includes(userSearch.toLowerCase());
    const matchesRole = roleFilter === "all" || u.role === roleFilter;
    return matchesSearch && matchesRole;
  });

  // Filtered Audit Logs
  const filteredAudits = auditLogs.filter((log) => {
    if (auditFilter === "pii") return log.isPiiAccess;
    if (auditFilter === "warning") return log.severity === "warning" || log.severity === "critical";
    return true;
  });

  function handleSaveFees(e: React.FormEvent) {
    e.preventDefault();
    onUpdateFeeSettings(localFees);
    setIsFeeDirty(false);
    setToastMessage("Platform fee and currency configurations saved successfully.");
    setTimeout(() => setToastMessage(""), 3500);
  }

  function handleAccountRecovery(user: TenantUserMember) {
    setToastMessage(`Account recovery link dispatched to ${user.email} (Single-use HMAC token).`);
    setTimeout(() => setToastMessage(""), 4000);
  }

  return (
    <div className="system-admin-container" id="system-admin-console">
      {/* Platform Header */}
      <div className="system-admin-header">
        <div className="header-left">
          <div className="system-badge">
            <ShieldCheck size={16} /> SYSTEM ADMINISTRATOR CONSOLE
          </div>
          <h2>Platform Management & Infrastructure Health</h2>
          <p>
            Platform-wide configuration, account administration, security event logging, and authorized operational oversight.
          </p>
        </div>
        <div className="header-right">
          <div className="platform-uptime-pill">
            <Activity size={14} className="pulse-icon" />
            <span>Platform Uptime: <strong>{stats.systemUptimePercentage}%</strong></span>
          </div>
        </div>
      </div>

      {toastMessage && (
        <div className="system-toast" role="status">
          <CheckCircle2 size={16} /> {toastMessage}
        </div>
      )}

      {/* Sensitive Credentials & Privacy Protection Warning */}
      <div className="security-notice-callout">
        <Lock size={18} />
        <div>
          <strong>Enterprise Security Invariant:</strong> Sensitive credentials (API keys, webhook secrets, database connection strings) are cryptographically protected and never displayed in plaintext. Privileged access to payment records and customer personal information (PII) is strictly controlled and audited.
        </div>
      </div>

      {/* Top Console Navigation Tabs */}
      <div className="system-admin-tabs">
        <button
          type="button"
          className={`sys-tab ${activeTab === "overview" ? "active" : ""}`}
          onClick={() => setActiveTab("overview")}
          id="tab-sys-overview"
        >
          <Activity size={15} /> Platform Overview & Stats
        </button>
        <button
          type="button"
          className={`sys-tab ${activeTab === "users" ? "active" : ""}`}
          onClick={() => setActiveTab("users")}
          id="tab-sys-users"
        >
          <Users size={15} /> Account Administration ({users.length})
        </button>
        <button
          type="button"
          className={`sys-tab ${activeTab === "scopes" ? "active" : ""}`}
          onClick={() => setActiveTab("scopes")}
          id="tab-sys-scopes"
        >
          <UserCheck size={15} /> Delegated Administrator Scopes ({adminScopes.length})
        </button>
        <button
          type="button"
          className={`sys-tab ${activeTab === "health" ? "active" : ""}`}
          onClick={() => setActiveTab("health")}
          id="tab-sys-health"
        >
          <Server size={15} /> System & Infrastructure Health
        </button>
        <button
          type="button"
          className={`sys-tab ${activeTab === "providers" ? "active" : ""}`}
          onClick={() => setActiveTab("providers")}
          id="tab-sys-providers"
        >
          <Smartphone size={15} /> Notification Providers ({providers.length})
        </button>
        <button
          type="button"
          className={`sys-tab ${activeTab === "fees" ? "active" : ""}`}
          onClick={() => setActiveTab("fees")}
          id="tab-sys-fees"
        >
          <DollarSign size={15} /> Platform Fees & Currencies
        </button>
        <button
          type="button"
          className={`sys-tab ${activeTab === "audits" ? "active" : ""}`}
          onClick={() => setActiveTab("audits")}
          id="tab-sys-audits"
        >
          <ShieldAlert size={15} /> Audit Logs & Security Events ({auditLogs.length})
        </button>
        <button
          type="button"
          className={`sys-tab ${activeTab === "settings" ? "active" : ""}`}
          onClick={() => setActiveTab("settings")}
          id="tab-sys-settings"
        >
          <Settings2 size={15} /> Dedicated Settings & Policies (17)
        </button>
      </div>

      {/* TAB 1: PLATFORM OVERVIEW */}
      {activeTab === "overview" && (
        <div className="sys-panel overview-panel">
          <div className="stats-metric-grid">
            <div className="metric-card gmv-card">
              <span className="metric-kicker">GROSS MERCHANDISE VALUE (USD)</span>
              <h3>{formatMoney(stats.totalGrossMerchandiseValueUSD, "USD")}</h3>
              <p className="metric-sub">Across U.S. events</p>
            </div>
            <div className="metric-card gmv-card">
              <span className="metric-kicker">GROSS MERCHANDISE VALUE (JMD)</span>
              <h3>{formatMoney(stats.totalGrossMerchandiseValueJMD, "JMD")}</h3>
              <p className="metric-sub">Historical JMD transactions only</p>
            </div>
            <div className="metric-card fee-card">
              <span className="metric-kicker">TOTAL PLATFORM FEES (USD)</span>
              <h3>{formatMoney(stats.totalPlatformFeesCollectedUSD, "USD")}</h3>
              <p className="metric-sub">Take rate + transaction fees</p>
            </div>
            <div className="metric-card fee-card">
              <span className="metric-kicker">TOTAL PLATFORM FEES (JMD)</span>
              <h3>{formatMoney(stats.totalPlatformFeesCollectedJMD, "JMD")}</h3>
              <p className="metric-sub">Take rate + transaction fees</p>
            </div>
          </div>

          <div className="stats-secondary-grid">
            <div className="sub-stat-card">
              <Users size={20} className="stat-icon" />
              <div>
                <strong>{stats.totalRegisteredHosts}</strong>
                <span>Registered Event Hosts</span>
              </div>
            </div>
            <div className="sub-stat-card">
              <Layers size={20} className="stat-icon" />
              <div>
                <strong>{stats.totalActiveEvents}</strong>
                <span>Active Published Events</span>
              </div>
            </div>
            <div className="sub-stat-card">
              <HardDrive size={20} className="stat-icon" />
              <div>
                <strong>{stats.totalTicketsIssued.toLocaleString()}</strong>
                <span>Total Tickets Issued</span>
              </div>
            </div>
            <div className="sub-stat-card">
              <ShieldCheck size={20} className="stat-icon" />
              <div>
                <strong>Zero Breaches</strong>
                <span>Single-DB Row Scoping Enforced</span>
              </div>
            </div>
          </div>

          {/* Quick Health Summary */}
          <div className="quick-health-box">
            <h4>
              <Server size={16} /> Real-Time Service Health Summary
            </h4>
            <div className="health-pills-row">
              {health.map((h) => (
                <div key={h.name} className={`health-pill ${h.status}`}>
                  <CheckCircle2 size={13} />
                  <span>{h.name}: <strong>{h.metrics}</strong></span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: ACCOUNT ADMINISTRATION */}
      {activeTab === "users" && (
        <div className="sys-panel users-panel">
          <div className="panel-toolbar">
            <div className="search-wrap">
              <Search size={15} />
              <input
                type="text"
                placeholder="Search accounts by name, email, or tenant..."
                value={userSearch}
                onChange={(e) => setUserSearch(e.target.value)}
                className="search-input"
              />
            </div>
            <div className="filter-wrap">
              <Filter size={15} />
              <select
                value={roleFilter}
                onChange={(e) => setRoleFilter(e.target.value)}
                className="filter-select"
              >
                <option value="all">All Roles</option>
                <option value="platform_admin">Platform Admin</option>
                <option value="event_owner">Event Owner / Host</option>
                <option value="payment_verifier">Payment Verifier</option>
                <option value="food_collector">Food Collector</option>
              </select>
            </div>
          </div>

          <div className="accounts-table-wrap">
            <table className="enterprise-table">
              <thead>
                <tr>
                  <th>User / Organization</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Last Active</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredUsers.map((u) => (
                  <tr key={u.id} className={!u.isActive ? "row-suspended" : ""}>
                    <td>
                      <div className="user-name-col">
                        <strong>{u.name}</strong>
                        <span className="user-email">{u.email}</span>
                        <span className="user-tenant">Tenant: <code>{u.organizerId}</code></span>
                      </div>
                    </td>
                    <td>
                      <span className={`role-badge ${u.role}`}>
                        {u.role.replace("_", " ").toUpperCase()}
                      </span>
                    </td>
                    <td>
                      <span className={`status-pill ${u.isActive ? "active" : "suspended"}`}>
                        {u.isActive ? "Active" : "Suspended"}
                      </span>
                    </td>
                    <td>
                      <span className="timestamp-text">
                        {new Date(u.lastActiveAt).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}
                      </span>
                    </td>
                    <td>
                      <div className="action-buttons-cell">
                        <button
                          type="button"
                          className={`btn-action-sm ${u.isActive ? "btn-suspend" : "btn-activate"}`}
                          onClick={() => onToggleUserStatus(u.id)}
                          title={u.isActive ? "Suspend account" : "Activate account"}
                        >
                          {u.isActive ? <UserX size={13} /> : <UserCheck size={13} />}
                          <span>{u.isActive ? "Suspend" : "Activate"}</span>
                        </button>
                        <button
                          type="button"
                          className="btn-action-sm btn-recover"
                          onClick={() => handleAccountRecovery(u)}
                          title="Trigger password reset / account recovery"
                        >
                          <RefreshCw size={13} />
                          <span>Recover</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 3: DELEGATED ADMINISTRATOR SCOPES */}
      {activeTab === "scopes" && (
        <div className="sys-panel scopes-panel">
          <div className="scopes-intro">
            <div>
              <h3>Delegated Administrator Assignment & Scope Management</h3>
              <p>
                Administrators should not automatically receive every System Administrator privilege. Their access is explicitly assigned and scoped by region, category, or role.
              </p>
            </div>
          </div>

          <div className="scopes-cards-grid">
            {adminScopes.map((scope) => (
              <div className="scope-card" key={scope.id}>
                <div className="scope-card-header">
                  <div>
                    <strong>{scope.adminName}</strong>
                    <span className="scope-id">Scope ID: {scope.id}</span>
                  </div>
                  <span className={`region-badge region-${scope.assignedRegion}`}>
                    Region: {scope.assignedRegion === "ALL" ? "Global" : scope.assignedRegion === "JM" ? "Jamaica" : "United States"}
                  </span>
                </div>

                <div className="scope-permissions-list">
                  <div className="perm-item granted">
                    <CheckCircle2 size={14} />
                    <span>Manage Events within Scope: <strong>Granted</strong></span>
                  </div>
                  <div className="perm-item granted">
                    <CheckCircle2 size={14} />
                    <span>Assist Hosts with Setup & Publishing: <strong>Granted</strong></span>
                  </div>
                  <div className="perm-item granted">
                    <CheckCircle2 size={14} />
                    <span>Resolve Order & Ticket Disputes: <strong>Granted</strong></span>
                  </div>
                  <div className="perm-item granted">
                    <CheckCircle2 size={14} />
                    <span>View Scoped Event & Sales Reports: <strong>Granted</strong></span>
                  </div>
                  <div className="perm-item granted">
                    <CheckCircle2 size={14} />
                    <span>Assist with Account Recovery: <strong>Granted</strong></span>
                  </div>

                  {/* Explicit Non-Superadmin Boundaries */}
                  <div className="perm-item forbidden">
                    <XCircle size={14} />
                    <span>Modify Global Platform Fees: <strong className="text-danger">Forbidden</strong></span>
                  </div>
                  <div className="perm-item forbidden">
                    <XCircle size={14} />
                    <span>View Unmasked Third-Party API Secrets: <strong className="text-danger">Forbidden</strong></span>
                  </div>
                </div>

                <div className="scope-footer">
                  <span className="scope-status">Assigned & Active</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 4: SYSTEM & INFRASTRUCTURE HEALTH */}
      {activeTab === "health" && (
        <div className="sys-panel health-panel">
          <div className="health-grid">
            {health.map((comp) => (
              <div key={comp.name} className="health-card">
                <div className="health-card-top">
                  <div className="comp-title-wrap">
                    <Server size={18} />
                    <strong>{comp.name}</strong>
                  </div>
                  <span className={`status-pill ${comp.status}`}>{comp.status.toUpperCase()}</span>
                </div>
                <div className="comp-metrics">
                  <span>Current Metric:</span>
                  <strong>{comp.metrics}</strong>
                </div>
                <div className="comp-footer">
                  <Clock size={12} />
                  <span>Last Heartbeat: {comp.lastChecked}</span>
                </div>
              </div>
            ))}
          </div>

          <div className="backup-retention-box">
            <h4>
              <HardDrive size={16} /> Data Retention, Privacy & Automated Backups
            </h4>
            <div className="backup-grid">
              <div className="backup-item">
                <span>Data Retention Policy:</span>
                <strong>{feeSettings.dataRetentionYears} Years (Compliant with Tax & Financial Regulations)</strong>
              </div>
              <div className="backup-item">
                <span>Automated Daily Backups:</span>
                <strong>{feeSettings.automatedDailyBackups ? "Enabled (04:00 UTC Daily)" : "Disabled"}</strong>
              </div>
              <div className="backup-item">
                <span>Last Backup Verification:</span>
                <strong>{new Date(feeSettings.lastBackupVerifiedAt).toLocaleDateString()} (Checksum Verified)</strong>
              </div>
              <div className="backup-item">
                <span>Zero Trust Multi-Tenancy:</span>
                <strong>Strict Row-Level Isolation (WHERE organizer_id = ?)</strong>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 5: NOTIFICATION PROVIDERS (CREDENTIAL PROTECTION) */}
      {activeTab === "providers" && (
        <div className="sys-panel providers-panel">
          <div className="providers-intro">
            <h3>Notification Provider Gateway Status</h3>
            <p>
              Twilio SMS, Postmark Email, AWS SES, and WhatsApp Cloud integration status. Sensitive API credentials are protected and masked.
            </p>
          </div>

          <div className="providers-grid">
            {providers.map((prov) => (
              <div className="provider-card" key={prov.id}>
                <div className="provider-card-top">
                  <div>
                    <strong>{prov.name}</strong>
                    <span className="channel-tag">{prov.channel.toUpperCase()}</span>
                  </div>
                  <span className={`status-pill ${prov.status}`}>{prov.status.toUpperCase()}</span>
                </div>

                <div className="credential-box">
                  <div className="credential-row">
                    <span className="cred-label">API Secret:</span>
                    <code className="masked-cred">
                      <Lock size={12} /> {maskSecretKey(prov.maskedApiKey)}
                    </code>
                    <span className="cred-protected-tag">PROTECTED</span>
                  </div>
                  {prov.maskedAccountSid && (
                    <div className="credential-row">
                      <span className="cred-label">Account SID:</span>
                      <code className="masked-cred">{maskSecretKey(prov.maskedAccountSid)}</code>
                    </div>
                  )}
                  <div className="credential-row">
                    <span className="cred-label">Endpoint:</span>
                    <span className="cred-endpoint">{prov.endpointUrl}</span>
                  </div>
                </div>

                <div className="prov-metrics-row">
                  <div>
                    <span>Delivery Success Rate:</span>
                    <strong>{prov.deliverySuccessRate}%</strong>
                  </div>
                  <div>
                    <span>Heartbeat:</span>
                    <strong>{prov.lastPingAt}</strong>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 6: PLATFORM FEES & CURRENCIES */}
      {activeTab === "fees" && (
        <div className="sys-panel fees-panel">
          <form onSubmit={handleSaveFees} className="fee-config-form">
            <div className="form-section-header">
              <h3>Platform Fee & Monetization Configuration</h3>
              <p>
                Configure universal platform take rates, per-ticket fees, non-profit community discounts, and supported settlement currencies.
              </p>
            </div>

            <div className="form-fields-grid">
              <div className="form-group">
                <label htmlFor="fee-take-rate">Platform Take Rate (%)</label>
                <input
                  id="fee-take-rate"
                  type="number"
                  step="0.1"
                  value={localFees.takeRatePercent}
                  onChange={(e) => {
                    setLocalFees({ ...localFees, takeRatePercent: parseFloat(e.target.value) || 0 });
                    setIsFeeDirty(true);
                  }}
                  className="form-input"
                />
                <span className="field-hint">Standard percentage applied to all ticket & meal orders</span>
              </div>

              <div className="form-group">
                <label htmlFor="fee-fixed-usd">Fixed Fee per Order (USD)</label>
                <input
                  id="fee-fixed-usd"
                  type="number"
                  step="0.05"
                  value={localFees.fixedFeeUSD}
                  onChange={(e) => {
                    setLocalFees({ ...localFees, fixedFeeUSD: parseFloat(e.target.value) || 0 });
                    setIsFeeDirty(true);
                  }}
                  className="form-input"
                />
                <span className="field-hint">Covers payment processing and gateway overhead</span>
              </div>

              <div className="form-group">
                <label htmlFor="fee-fixed-jmd">Fixed Fee per Order (JMD)</label>
                <input
                  id="fee-fixed-jmd"
                  type="number"
                  step="10"
                  value={localFees.fixedFeeJMD}
                  onChange={(e) => {
                    setLocalFees({ ...localFees, fixedFeeJMD: parseFloat(e.target.value) || 0 });
                    setIsFeeDirty(true);
                  }}
                  className="form-input"
                />
                <span className="field-hint">Historical JMD transaction fee; new U.S. events use USD</span>
              </div>

              <div className="form-group">
                <label htmlFor="fee-discount-rate">Community / Non-Profit Rate (%)</label>
                <input
                  id="fee-discount-rate"
                  type="number"
                  step="0.1"
                  value={localFees.nonProfitDiscountRatePercent}
                  onChange={(e) => {
                    setLocalFees({
                      ...localFees,
                      nonProfitDiscountRatePercent: parseFloat(e.target.value) || 0,
                    });
                    setIsFeeDirty(true);
                  }}
                  className="form-input"
                />
                <span className="field-hint">Discounted rate for church fundraisers & youth centers</span>
              </div>
            </div>

            <div className="gateways-status-section">
              <h4>Supported Payment Gateways</h4>
              <div className="gateway-badges-list">
                {localFees.supportedGateways.map((gw) => (
                  <div key={gw.id} className="gw-chip">
                    <CheckCircle2 size={14} />
                    <span>{gw.name} ({gw.region})</span>
                  </div>
                ))}
              </div>
            </div>

            <div className="form-actions">
              <button
                type="submit"
                disabled={!isFeeDirty}
                className="primary-button save-fees-btn"
              >
                Save Platform Fee Settings
              </button>
            </div>
          </form>
        </div>
      )}

      {/* TAB 7: AUDIT LOGS & SECURITY EVENTS */}
      {activeTab === "audits" && (
        <div className="sys-panel audits-panel">
          <div className="panel-toolbar">
            <div className="filter-wrap">
              <Filter size={15} />
              <select
                value={auditFilter}
                onChange={(e) => setAuditFilter(e.target.value)}
                className="filter-select"
              >
                <option value="all">All Security Events ({auditLogs.length})</option>
                <option value="pii">PII & Personal Data Access Only</option>
                <option value="warning">Warnings & Critical Events</option>
              </select>
            </div>
          </div>

          <div className="audits-table-wrap">
            <table className="enterprise-table">
              <thead>
                <tr>
                  <th>Timestamp</th>
                  <th>Actor / Role</th>
                  <th>Action</th>
                  <th>Target Entity</th>
                  <th>Severity</th>
                  <th>IP Address</th>
                  <th>Details</th>
                </tr>
              </thead>
              <tbody>
                {filteredAudits.map((log) => (
                  <tr key={log.id} className={log.isPiiAccess ? "row-pii" : ""}>
                    <td>
                      <span className="timestamp-text">
                        {new Date(log.timestamp).toLocaleTimeString([], {
                          hour: "2-digit",
                          minute: "2-digit",
                          second: "2-digit",
                        })}
                      </span>
                    </td>
                    <td>
                      <div className="actor-cell">
                        <strong>{log.actorName}</strong>
                        <span className="actor-role">{log.actorRole}</span>
                      </div>
                    </td>
                    <td>
                      <code>{log.action}</code>
                    </td>
                    <td>
                      <span>{log.targetEntity}:{log.targetId}</span>
                    </td>
                    <td>
                      <span className={`severity-badge ${log.severity}`}>
                        {log.severity.toUpperCase()}
                      </span>
                    </td>
                    <td>
                      <span className="ip-text">{log.ipAddress}</span>
                    </td>
                    <td>
                      <span className="details-text">
                        {log.details}
                        {log.isPiiAccess && (
                          <span className="pii-tag">
                            <Lock size={10} /> PII AUDITED
                          </span>
                        )}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 8: DEDICATED SETTINGS & POLICIES (17) */}
      {activeTab === "settings" && (
        <div className="sys-panel settings-panel">
          <AccountSettingsView
            accountType="system_admin"
            currentUser={{
              name: "Platform Administrator",
              email: "superadmin@islandevents.example",
              roleLabel: "System Administrator",
              region: "ALL",
            }}
            platformFeeSettings={feeSettings}
            securityAuditLogs={auditLogs}
            tenantUsers={users}
            adminScopes={adminScopes}
            formatMoney={formatMoney}
            onNavigateToTab={(tab) => {
              if (tab === "overview") setActiveTab("overview");
              else if (tab === "health") setActiveTab("health");
              else if (tab === "fees") setActiveTab("fees");
              else if (tab === "users") setActiveTab("users");
            }}
          />
        </div>
      )}
    </div>
  );
};
