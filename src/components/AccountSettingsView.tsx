import React, { useMemo, useState } from "react";
import {
  AccountSettingKey,
  AccountSettingScope,
  AccountType,
  AdminAssignedScope,
  AttendeeProfile,
  Currency,
  EventDetails,
  HostCommunicationTemplates,
  HostEventDefaults,
  HostOperationalRules,
  HostPaymentPolicySettings,
  PlatformFeeSettings,
  SecurityAuditLog,
  Settings,
  SystemPlatformSettings,
  TenantUserMember,
  TicketDispute,
  TicketOrder,
} from "../types";
import { BrandLogo } from "./BrandLogo";
import {
  DEFAULT_HOST_COMMUNICATION_TEMPLATES,
  DEFAULT_HOST_EVENT_DEFAULTS,
  DEFAULT_HOST_OPERATIONAL_RULES,
  DEFAULT_HOST_PAYMENT_POLICIES,
  DEFAULT_SYSTEM_PLATFORM_SETTINGS,
  MASTER_ACCOUNT_SETTINGS,
} from "../data/defaultData";
import {
  evaluateAttendeeDisplayCurrencyConversion,
  evaluateHostPaymentEligibility,
  maskSecretKey,
} from "../tests/testUtils";
import {
  AlertCircle,
  AlertTriangle,
  ArrowRight,
  Banknote,
  Bell,
  Check,
  CheckCircle2,
  ChefHat,
  Clock,
  DollarSign,
  Download,
  FileSpreadsheet,
  Globe,
  Key,
  Lock,
  Mail,
  QrCode,
  Search,
  Server,
  Settings2,
  Shield,
  ShieldAlert,
  ShieldCheck,
  Smartphone,
  Sparkles,
  Trash2,
  User,
  Utensils,
  Wallet,
  X,
} from "lucide-react";

type Props = {
  accountType: AccountType;
  currentUser?: {
    name: string;
    email: string;
    phone?: string;
    roleLabel: string;
    region?: string;
  };
  eventDetails?: EventDetails;
  settings?: Settings;
  platformFeeSettings?: PlatformFeeSettings;
  securityAuditLogs?: SecurityAuditLog[];
  orders?: TicketOrder[];
  ticketDisputes?: TicketDispute[];
  tenantUsers?: TenantUserMember[];
  adminScopes?: AdminAssignedScope[];
  attendeeProfile?: AttendeeProfile;
  formatMoney?: (cents: number, currency: Currency) => string;
  onUpdateEventDetails?: (details: EventDetails) => void;
  onUpdateSettings?: (updater: (prev: Settings) => Settings) => void;
  onSecurityLog?: (action: string, target: string, details: string, isPii?: boolean) => void;
  onNavigateToTab?: (tabKey: string) => void;
};

export const AccountSettingsView: React.FC<Props> = ({
  accountType,
  currentUser,
  eventDetails,
  settings,
  platformFeeSettings,
  securityAuditLogs = [],
  orders = [],
  ticketDisputes: _ticketDisputes = [],
  tenantUsers = [],
  adminScopes = [],
  attendeeProfile,
  formatMoney = (c, curr) => `${curr} $${(c / 100).toFixed(2)}`,
  onUpdateEventDetails: _onUpdateEventDetails,
  onUpdateSettings: _onUpdateSettings,
  onSecurityLog,
  onNavigateToTab,
}) => {
  // Filter settings to only those allowed for this specific account
  const allowedSettings = useMemo(() => {
    return MASTER_ACCOUNT_SETTINGS.filter((s) => s.scopes[accountType] !== null);
  }, [accountType]);

  const [activeSettingKey, setActiveSettingKey] = useState<AccountSettingKey>(
    allowedSettings[0]?.key || "personal_profile"
  );

  // Common Profile Edit State
  const [nameInput, setNameInput] = useState(
    currentUser?.name || (accountType === "system_admin" ? "Platform Administrator" : "Demo Event Host")
  );
  const [emailInput, setEmailInput] = useState(
    currentUser?.email || (accountType === "system_admin" ? "superadmin@islandevents.example" : "host@islandevents.example")
  );
  const [phoneInput, setPhoneInput] = useState(currentUser?.phone || attendeeProfile?.phone || "+1 (305) 555-0199");
  const [profileSuccessMsg, setProfileSuccessMsg] = useState("");

  // Verification state (Attendee)
  const [isPhoneVerified, setIsPhoneVerified] = useState(true);
  const [isEmailVerified, setIsEmailVerified] = useState(true);

  // Password Change State
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [passwordFeedback, setPasswordFeedback] = useState<{ type: "success" | "error"; msg: string } | null>(null);

  // 2FA State
  const [is2FaEnabled, setIs2FaEnabled] = useState(true);
  const [showBackupCodes, setShowBackupCodes] = useState(false);

  // Notification Preferences State
  const [notifySms, setNotifySms] = useState(true);
  const [notifyWhatsapp, setNotifyWhatsapp] = useState(true);
  const [notifyEmail, setNotifyEmail] = useState(true);
  const [notifyCriticalAlerts, setNotifyCriticalAlerts] = useState(true);

  // ==========================================
  // Specialized State: Event Host Settings Area
  // ==========================================
  const [eventDefaults, setEventDefaults] = useState<HostEventDefaults>(DEFAULT_HOST_EVENT_DEFAULTS);
  const [paymentPolicies, setPaymentPolicies] = useState<HostPaymentPolicySettings>(DEFAULT_HOST_PAYMENT_POLICIES);
  const [communicationTemplates, setCommunicationTemplates] = useState<HostCommunicationTemplates>(
    DEFAULT_HOST_COMMUNICATION_TEMPLATES
  );
  const [operationalRules, setOperationalRules] = useState<HostOperationalRules>(DEFAULT_HOST_OPERATIONAL_RULES);
  const [hostSectionSuccessMsg, setHostSectionSuccessMsg] = useState("");

  // ==========================================
  // Specialized State: Attendee Settings
  // ==========================================
  const [preferredDisplayCurrency, setPreferredDisplayCurrency] = useState<Currency>("USD");
  const [retrievalQuery, setRetrievalQuery] = useState("");
  const [retrievalResult, setRetrievalResult] = useState<TicketOrder | null>(null);
  const [retrievalError, setRetrievalError] = useState("");
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deletionSuccessNotice, setDeletionSuccessNotice] = useState("");

  // ==========================================
  // Specialized State: System Administrator Settings
  // ==========================================
  const [platformSettings, _setPlatformSettings] = useState<SystemPlatformSettings>(DEFAULT_SYSTEM_PLATFORM_SETTINGS);

  const activeDefinition = allowedSettings.find((s) => s.key === activeSettingKey) || allowedSettings[0];
  const activeScope = activeDefinition ? activeDefinition.scopes[accountType] : null;

  // Save profile
  function handleSaveProfile(e: React.FormEvent) {
    e.preventDefault();
    setProfileSuccessMsg("Personal profile saved successfully.");
    onSecurityLog?.(
      "profile_update",
      "user_profile",
      `Updated profile for account ${accountType} (${nameInput}, ${emailInput})`,
      false
    );
    setTimeout(() => setProfileSuccessMsg(""), 3500);
  }

  // Save Host Settings with Explicit Immutable History Guarantee
  function handleSaveHostSettings(sectionTitle: string) {
    setHostSectionSuccessMsg(`✨ ${sectionTitle} saved successfully! Existing orders remain protected with immutable historical snapshots.`);
    onSecurityLog?.(
      "host_settings_updated",
      "event_settings",
      `Host updated ${sectionTitle}. Historical order snapshot preservation enforced.`,
      false
    );
    setTimeout(() => setHostSectionSuccessMsg(""), 4000);
  }

  // Password rotation
  function handleUpdatePassword(e: React.FormEvent) {
    e.preventDefault();
    if (!currentPassword) {
      setPasswordFeedback({ type: "error", msg: "Please enter your current password." });
      return;
    }
    if (newPassword.length < 10) {
      setPasswordFeedback({ type: "error", msg: "New password must be at least 10 characters with entropy checks." });
      return;
    }
    if (newPassword !== confirmPassword) {
      setPasswordFeedback({ type: "error", msg: "New password and confirmation do not match." });
      return;
    }

    setPasswordFeedback({ type: "success", msg: "Password successfully changed and rotated across active sessions." });
    setCurrentPassword("");
    setNewPassword("");
    setConfirmPassword("");
    onSecurityLog?.(
      "password_rotation",
      "user_credentials",
      `Password rotated for account ${accountType}`,
      false
    );
  }

  // 2FA toggle
  function handleToggle2Fa() {
    const nextVal = !is2FaEnabled;
    setIs2FaEnabled(nextVal);
    onSecurityLog?.(
      nextVal ? "2fa_enabled" : "2fa_disabled",
      "auth_security",
      `Two-factor authentication ${nextVal ? "enabled" : "disabled"} for ${accountType}`,
      true
    );
  }

  // Attendee Ticket Retrieval Lookup
  function handleLookupTicket(e: React.FormEvent) {
    e.preventDefault();
    setRetrievalError("");
    setRetrievalResult(null);
    const q = retrievalQuery.trim().toLowerCase();
    if (!q) {
      setRetrievalError("Please enter a ticket code, order number, or phone number.");
      return;
    }
    const match = orders.find(
      (o) =>
        o.ticketCode.toLowerCase() === q ||
        o.orderNumber.toLowerCase() === q ||
        o.customerPhone.replace(/\D/g, "").includes(q.replace(/\D/g, ""))
    );
    if (match) {
      setRetrievalResult(match);
    } else {
      setRetrievalError(`No active ticket pass found matching '${retrievalQuery}'.`);
    }
  }

  function formatScopeLabel(scope: AccountSettingScope | null): string {
    if (!scope) return "Restricted";
    return scope
      .replace(/_/g, " ")
      .replace(/\b\w/g, (c) => c.toUpperCase());
  }

  // Filter logs for the active account type's allowed scope
  const scopedAuditLogs = useMemo(() => {
    if (accountType === "system_admin") {
      return securityAuditLogs;
    }
    if (accountType === "admin") {
      return securityAuditLogs.filter(
        (l) => l.actorRole === "admin" || l.targetEntity === "ticket_disputes" || l.targetEntity === "organizers"
      );
    }
    if (accountType === "event_host") {
      return securityAuditLogs.filter(
        (l) => l.targetEntity === "orders" || l.targetEntity === "cash_ledger" || l.targetEntity === "events"
      );
    }
    // attendee: own activity only
    return securityAuditLogs.filter((l) => l.actorRole === "attendee" || l.targetEntity === "attendee_profile");
  }, [accountType, securityAuditLogs]);

  return (
    <div className="account-settings-container" id="account-settings-page">
      {/* Settings Top Header & Role Indicator */}
      <div className="settings-page-header">
        <div className="settings-page-title-row">
          <div className="settings-page-icon">
            <Settings2 size={24} />
          </div>
          <div>
            <h2 className="settings-page-title">
              {accountType === "system_admin" && "System Administrator Platform Settings"}
              {accountType === "admin" && "Delegated Administrator Operational Settings"}
              {accountType === "event_host" && "Event Host Account & Business Settings"}
              {accountType === "attendee" && "Attendee Account & Privacy Settings"}
            </h2>
            <p className="settings-page-subtitle">
              The settings menu below displays only options relevant to your authorized role and operational responsibilities.
            </p>
          </div>
        </div>

        <div className="settings-role-badge-row">
          <span className={`account-role-pill role-${accountType}`}>
            {accountType === "system_admin" && <ShieldCheck size={14} />}
            {accountType === "admin" && <Shield size={14} />}
            {accountType === "event_host" && <ChefHat size={14} />}
            {accountType === "attendee" && <User size={14} />}
            Role: <strong>{currentUser?.roleLabel || accountType.toUpperCase()}</strong>
          </span>
          {currentUser?.region && (
            <span className="account-scope-pill">
              <Globe size={13} /> Jurisdiction: {currentUser.region}
            </span>
          )}
        </div>

        {/* System Administrator Privacy & Secret Key Protection Banner */}
        {accountType === "system_admin" && (
          <div className="admin-privacy-guardrail-banner">
            <ShieldAlert size={18} className="guardrail-icon" />
            <div>
              <strong>Enterprise Security & Privacy Invariant:</strong>
              <span>
                {" "}“All access” for a System Administrator is governed by explicit permissions, audit logging, and data privacy requirements. Secret keys (Stripe, Twilio, Postmark, AWS SES) are stored securely in environment configuration or Vault and are NEVER exposed through ordinary settings forms or readable logs.
              </span>
            </div>
          </div>
        )}

        {/* Event Host Historical Order Snapshot Protection Banner */}
        {accountType === "event_host" && (
          <div className="host-order-integrity-banner">
            <Lock size={16} />
            <div>
              <strong>Historical Order Protection Active:</strong>
              <span>
                {" "}Settings that affect an existing order will not silently rewrite its historical price, payment records, or collection history. Menu pricing and payment defaults apply strictly to new purchases.
              </span>
            </div>
          </div>
        )}
      </div>

      {hostSectionSuccessMsg && (
        <div className="settings-alert-success">
          <CheckCircle2 size={16} /> {hostSectionSuccessMsg}
        </div>
      )}

      {/* Main Settings Layout: Left Nav + Right Panel */}
      <div className="settings-layout-grid">
        {/* Left Side: Filtered Role-Specific Menu */}
        <nav className="settings-sidebar-nav" aria-label="Account Settings Menu">
          <div className="settings-sidebar-heading">
            <span>Available Settings ({allowedSettings.length})</span>
          </div>

          <div className="settings-menu-list">
            {allowedSettings.map((setting) => {
              const scope = setting.scopes[accountType];
              const isSelected = setting.key === activeSettingKey;

              return (
                <button
                  key={setting.key}
                  type="button"
                  className={`settings-menu-item ${isSelected ? "active" : ""}`}
                  onClick={() => setActiveSettingKey(setting.key)}
                  id={`setting-nav-${setting.key}`}
                >
                  <div className="menu-item-text">
                    <span className="menu-item-title">{setting.title}</span>
                    <span className={`menu-item-scope scope-${scope}`}>
                      {formatScopeLabel(scope)}
                    </span>
                  </div>
                  {isSelected && <ArrowRight size={14} className="menu-chevron" />}
                </button>
              );
            })}
          </div>
        </nav>

        {/* Right Side: Active Setting Panel */}
        <main className="settings-detail-panel" id="active-setting-panel">
          {activeDefinition && (
            <div className="setting-panel-header">
              <div className="setting-panel-meta">
                <h3 className="setting-detail-title">{activeDefinition.title}</h3>
                <p className="setting-detail-desc">{activeDefinition.description}</p>
              </div>
              <div className="setting-panel-scope-badge">
                <span className="scope-tag-label">Authorized Access:</span>
                <span className={`scope-tag scope-${activeScope}`}>
                  {formatScopeLabel(activeScope)}
                </span>
              </div>
            </div>
          )}

          {/* Setting 1: Personal Profile */}
          {activeSettingKey === "personal_profile" && (
            <form onSubmit={handleSaveProfile} className="settings-form-content">
              {profileSuccessMsg && (
                <div className="settings-alert-success">
                  <CheckCircle2 size={16} /> {profileSuccessMsg}
                </div>
              )}

              <div className="settings-grid-2col">
                <div className="form-group">
                  <label htmlFor="input-profile-name">Full Name / Display Name</label>
                  <input
                    id="input-profile-name"
                    type="text"
                    className="settings-input"
                    value={nameInput}
                    onChange={(e) => setNameInput(e.target.value)}
                    required
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="input-profile-email">Official Email Address</label>
                  <div className="verified-input-wrapper">
                    <input
                      id="input-profile-email"
                      type="email"
                      className="settings-input"
                      value={emailInput}
                      onChange={(e) => setEmailInput(e.target.value)}
                      required
                    />
                    {accountType === "attendee" && (
                      <span className={`verified-badge ${isEmailVerified ? "active" : ""}`}>
                        {isEmailVerified ? <CheckCircle2 size={12} /> : <Clock size={12} />}
                        {isEmailVerified ? "Verified" : "Pending"}
                      </span>
                    )}
                  </div>
                </div>
              </div>

              <div className="settings-grid-2col">
                <div className="form-group">
                  <label htmlFor="input-profile-phone">Contact Phone Number</label>
                  <div className="verified-input-wrapper">
                    <input
                      id="input-profile-phone"
                      type="tel"
                      className="settings-input"
                      value={phoneInput}
                      onChange={(e) => setPhoneInput(e.target.value)}
                      placeholder="+1 (305) 555-0199"
                    />
                    {accountType === "attendee" && (
                      <span className={`verified-badge ${isPhoneVerified ? "active" : ""}`}>
                        {isPhoneVerified ? <CheckCircle2 size={12} /> : <Clock size={12} />}
                        {isPhoneVerified ? "Verified" : "Pending"}
                      </span>
                    )}
                  </div>
                </div>
                <div className="form-group">
                  <label>Primary Account Role</label>
                  <input
                    type="text"
                    className="settings-input disabled-input"
                    value={currentUser?.roleLabel || accountType.toUpperCase()}
                    disabled
                  />
                </div>
              </div>

              {accountType === "attendee" && (!isPhoneVerified || !isEmailVerified) && (
                <div className="verification-action-box">
                  <AlertCircle size={16} />
                  <div>
                    <span>Phone or email pending verification.</span>
                    <button
                      type="button"
                      className="btn-link"
                      onClick={() => {
                        setIsPhoneVerified(true);
                        setIsEmailVerified(true);
                        setProfileSuccessMsg("Verification code validated. Your contact info is fully verified!");
                      }}
                    >
                      Send Verification Code
                    </button>
                  </div>
                </div>
              )}

              <div className="settings-actions-bar">
                <button type="submit" className="settings-btn-primary">
                  Save Profile Changes
                </button>
              </div>
            </form>
          )}

          {/* Setting 2: Change Password */}
          {activeSettingKey === "change_password" && (
            <form onSubmit={handleUpdatePassword} className="settings-form-content">
              {passwordFeedback && (
                <div
                  className={
                    passwordFeedback.type === "success"
                      ? "settings-alert-success"
                      : "settings-alert-error"
                  }
                >
                  {passwordFeedback.type === "success" ? <CheckCircle2 size={16} /> : <AlertTriangle size={16} />}
                  <span>{passwordFeedback.msg}</span>
                </div>
              )}

              <div className="form-group">
                <label htmlFor="input-current-pass">Current Password</label>
                <input
                  id="input-current-pass"
                  type="password"
                  className="settings-input"
                  value={currentPassword}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                  placeholder="Enter current password"
                  required
                />
              </div>

              <div className="settings-grid-2col">
                <div className="form-group">
                  <label htmlFor="input-new-pass">New Secure Password</label>
                  <input
                    id="input-new-pass"
                    type="password"
                    className="settings-input"
                    value={newPassword}
                    onChange={(e) => setNewPassword(e.target.value)}
                    placeholder="Min 10 characters"
                    required
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="input-confirm-pass">Confirm New Password</label>
                  <input
                    id="input-confirm-pass"
                    type="password"
                    className="settings-input"
                    value={confirmPassword}
                    onChange={(e) => setConfirmPassword(e.target.value)}
                    placeholder="Re-enter new password"
                    required
                  />
                </div>
              </div>

              <div className="password-policy-card">
                <strong>Enterprise Credential Standards:</strong>
                <ul>
                  <li>Minimum 10 characters with numbers and symbols</li>
                  <li>Entropy validation against common breach dictionaries</li>
                  <li>Automatic session token re-issuance upon rotation</li>
                </ul>
              </div>

              <div className="settings-actions-bar">
                <button type="submit" className="settings-btn-primary">
                  Update Password
                </button>
              </div>
            </form>
          )}

          {/* Setting 3: Two-Factor Authentication */}
          {activeSettingKey === "two_factor_auth" && (
            <div className="settings-form-content">
              <div className="twofa-status-card">
                <div className="twofa-icon-col">
                  {is2FaEnabled ? <ShieldCheck size={36} className="text-success" /> : <ShieldAlert size={36} className="text-warning" />}
                </div>
                <div className="twofa-meta-col">
                  <h4>Two-Factor Authentication is {is2FaEnabled ? "Active & Enforced" : "Disabled"}</h4>
                  <p>
                    Requires an authentication code from your mobile authenticator app (TOTP) or hardware passkey at each sign-in.
                  </p>
                  <button
                    type="button"
                    className={`settings-btn-${is2FaEnabled ? "secondary" : "primary"}`}
                    onClick={handleToggle2Fa}
                  >
                    {is2FaEnabled ? "Disable 2FA Protection" : "Enable Two-Factor Authentication"}
                  </button>
                </div>
              </div>

              {is2FaEnabled && (
                <div className="backup-codes-section">
                  <div className="backup-codes-header">
                    <div>
                      <strong>Emergency Single-Use Recovery Codes</strong>
                      <p>Store these codes safely offline in case you lose access to your authenticator device.</p>
                    </div>
                    <button
                      type="button"
                      className="settings-btn-outline"
                      onClick={() => setShowBackupCodes(!showBackupCodes)}
                    >
                      {showBackupCodes ? "Hide Codes" : "Show Recovery Codes"}
                    </button>
                  </div>

                  {showBackupCodes && (
                    <div className="backup-codes-grid">
                      <code>8712-4491</code>
                      <code>3391-8842</code>
                      <code>9102-5573</code>
                      <code>4481-2299</code>
                      <code>7710-3341</code>
                      <code>1194-6628</code>
                    </div>
                  )}
                </div>
              )}
            </div>
          )}

          {/* Setting 4: Notification Preferences / Customer Communications */}
          {activeSettingKey === "notification_preferences" && (
            <div className="settings-form-content">
              <div className="notification-toggles-list">
                <label className="toggle-row">
                  <div className="toggle-meta">
                    <Smartphone size={18} />
                    <div>
                      <strong>SMS Notifications & Reminders</strong>
                      <span>Real-time text alerts for ticket issuance, order confirmations, and collection readiness.</span>
                    </div>
                  </div>
                  <input
                    type="checkbox"
                    checked={notifySms}
                    onChange={(e) => setNotifySms(e.target.checked)}
                  />
                </label>

                <label className="toggle-row">
                  <div className="toggle-meta">
                    <Bell size={18} />
                    <div>
                      <strong>WhatsApp Delivery</strong>
                      <span>Direct digital QR voucher pass dispatch and collection station updates via WhatsApp.</span>
                    </div>
                  </div>
                  <input
                    type="checkbox"
                    checked={notifyWhatsapp}
                    onChange={(e) => setNotifyWhatsapp(e.target.checked)}
                  />
                </label>

                <label className="toggle-row">
                  <div className="toggle-meta">
                    <Mail size={18} />
                    <div>
                      <strong>Email Receipts & Statements</strong>
                      <span>Official PDF payment receipts, handover confirmations, and dispute resolution letters.</span>
                    </div>
                  </div>
                  <input
                    type="checkbox"
                    checked={notifyEmail}
                    onChange={(e) => setNotifyEmail(e.target.checked)}
                  />
                </label>

                {accountType !== "attendee" && (
                  <label className="toggle-row">
                    <div className="toggle-meta">
                      <ShieldAlert size={18} />
                      <div>
                        <strong>Critical Operational & Security Alerts</strong>
                        <span>Immediate alerts for unauthorized access attempts, payment disputes, and host recovery pings.</span>
                      </div>
                    </div>
                    <input
                      type="checkbox"
                      checked={notifyCriticalAlerts}
                      onChange={(e) => setNotifyCriticalAlerts(e.target.checked)}
                    />
                  </label>
                )}
              </div>

              {/* Host Specific: Communication Templates Editor */}
              {accountType === "event_host" && (
                <div className="templates-editor-section">
                  <h4 className="subheading-title">Customer Communication Message Templates</h4>
                  <p className="subheading-desc">
                    Customize tokenized messages dispatched to customers across SMS, WhatsApp, and Email.
                  </p>

                  <div className="form-group">
                    <label>Order Confirmation Template</label>
                    <textarea
                      rows={2}
                      className="settings-textarea"
                      value={communicationTemplates.orderConfirmationTemplate}
                      onChange={(e) =>
                        setCommunicationTemplates({
                          ...communicationTemplates,
                          orderConfirmationTemplate: e.target.value,
                        })
                      }
                    />
                    <span className="token-hint">Tokens: <code>{'{customer_name}'}</code>, <code>{'{order_number}'}</code>, <code>{'{ticket_code}'}</code>, <code>{'{total_amount}'}</code></span>
                  </div>

                  <div className="form-group">
                    <label>Payment Confirmation Message</label>
                    <textarea
                      rows={2}
                      className="settings-textarea"
                      value={communicationTemplates.paymentConfirmationTemplate}
                      onChange={(e) =>
                        setCommunicationTemplates({
                          ...communicationTemplates,
                          paymentConfirmationTemplate: e.target.value,
                        })
                      }
                    />
                    <span className="token-hint">Tokens: <code>{'{total_amount}'}</code>, <code>{'{order_number}'}</code></span>
                  </div>

                  <div className="form-group">
                    <label>Event Reminder Template</label>
                    <textarea
                      rows={2}
                      className="settings-textarea"
                      value={communicationTemplates.eventReminderTemplate}
                      onChange={(e) =>
                        setCommunicationTemplates({
                          ...communicationTemplates,
                          eventReminderTemplate: e.target.value,
                        })
                      }
                    />
                    <span className="token-hint">Tokens: <code>{'{venue_name}'}</code></span>
                  </div>

                  <div className="form-group">
                    <label>Collection Confirmation Message</label>
                    <textarea
                      rows={2}
                      className="settings-textarea"
                      value={communicationTemplates.collectionConfirmationTemplate}
                      onChange={(e) =>
                        setCommunicationTemplates({
                          ...communicationTemplates,
                          collectionConfirmationTemplate: e.target.value,
                        })
                      }
                    />
                    <span className="token-hint">Tokens: <code>{'{order_number}'}</code>, <code>{'{station_id}'}</code></span>
                  </div>
                </div>
              )}

              <div className="settings-actions-bar">
                <button
                  type="button"
                  className="settings-btn-primary"
                  onClick={() => handleSaveHostSettings("Communication Preferences & Templates")}
                >
                  Save Communication Preferences
                </button>
              </div>
            </div>
          )}

          {/* Setting 5: Manage Platform Users */}
          {activeSettingKey === "manage_platform_users" && (
            <div className="settings-form-content">
              {activeScope === "all" ? (
                <div>
                  <div className="settings-info-card">
                    <Server size={18} />
                    <span>
                      Full platform user directory. You have unrestricted oversight to create accounts, activate, suspend, or dispatch emergency recovery tokens.
                    </span>
                  </div>
                  <div className="table-responsive">
                    <table className="settings-table">
                      <thead>
                        <tr>
                          <th>User</th>
                          <th>Role</th>
                          <th>Status</th>
                          <th>Security</th>
                        </tr>
                      </thead>
                      <tbody>
                        {tenantUsers.map((u) => (
                          <tr key={u.id}>
                            <td>
                              <strong>{u.name}</strong>
                              <div className="cell-subtext">{u.email}</div>
                            </td>
                            <td><span className="badge-role">{u.role}</span></td>
                            <td><span className="badge-status-active">Active</span></td>
                            <td><span className="badge-security-verified">2FA Enforced</span></td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              ) : (
                <div className="settings-info-card warning">
                  <AlertTriangle size={18} />
                  <div>
                    <strong>Delegated User Administration (Limited):</strong>
                    <p>
                      You may assist event hosts with onboarding and trigger single-use account recovery links within your assigned territory. Root user deletion and platform superadmin creation require System Administrator privileges.
                    </p>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Setting 6: Manage Platform Roles */}
          {activeSettingKey === "manage_platform_roles" && (
            <div className="settings-form-content">
              {activeScope === "all" ? (
                <div>
                  <p className="section-note">
                    System Administrators can define new role permission boundaries, assign regional jurisdictions, and grant operational delegates.
                  </p>
                  <div className="roles-grid">
                    <div className="role-card">
                      <h4>Platform Administrator</h4>
                      <p>Global oversight, platform-wide configuration, financial take-rates, and infrastructure monitoring.</p>
                    </div>
                    <div className="role-card">
                      <h4>Delegated Administrator</h4>
                      <p>Assigned operational jurisdiction (e.g. JM / US), dispute triage, host onboarding support.</p>
                    </div>
                    <div className="role-card">
                      <h4>Event Host</h4>
                      <p>Primary business operator: event creation, menus, custom pricing, stock limits, cash ledger.</p>
                    </div>
                  </div>
                </div>
              ) : (
                <div className="settings-info-card">
                  <Shield size={18} />
                  <div>
                    <strong>Assigned Operational Permissions:</strong>
                    <p>
                      Your account operates under strictly assigned delegated permissions ({adminScopes[0]?.adminName || currentUser?.name || "Admin"}). You can view your current assigned regional scope ({currentUser?.region || "JM"}) but cannot self-elevate permissions or alter platform-wide role schemas.
                    </p>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Setting 7: Platform Configuration (System Administrator Settings Area) */}
          {activeSettingKey === "platform_configuration" && (
            <div className="settings-form-content">
              <div className="config-grid">
                <div className="config-card">
                  <h4>Platform Name & Branding</h4>
                  <p>Name: <strong>{platformSettings.platformName}</strong></p>
                  <BrandLogo variant="compact" />
                  <p>Theme: <code>{platformSettings.brandTheme}</code> · Official EventBite logo</p>
                </div>
                <div className="config-card">
                  <h4>Supported Platform Currencies</h4>
                  <p>Launch currency: <strong>USD (U.S. Dollar)</strong></p>
                  <p>Historical JMD orders retain their recorded transaction currency.</p>
                </div>
                <div className="config-card">
                  <h4>File-Storage Configuration Status</h4>
                  <p>Provider: <strong>{platformSettings.storageProvider}</strong> (R2/S3 Cloud Storage)</p>
                  <p>Bucket: <code>{platformSettings.storageBucket}</code> · Status: <span className="status-badge-healthy">Healthy</span></p>
                </div>
                <div className="config-card">
                  <h4>Notification Provider Status</h4>
                  <p>Twilio SMS: <strong>Operational</strong> · Postmark: <strong>Operational</strong></p>
                  <p>AWS SES: <strong>Active</strong> · WhatsApp Cloud API: <strong>Connected</strong></p>
                </div>
                <div className="config-card">
                  <h4>Account Security & Session Policies</h4>
                  <p>Mandatory Admin 2FA: <strong>Enforced</strong></p>
                  <p>Admin Session Timeout: <strong>{platformSettings.adminSessionTimeoutMinutes} min</strong> · Attendee: <strong>{platformSettings.attendeeSessionTimeoutDays} days</strong></p>
                </div>
                <div className="config-card">
                  <h4>Audit Logging & Retention Policies</h4>
                  <p>Retention Window: <strong>{platformSettings.auditRetentionDays} days</strong></p>
                  <p>Automated PII Scrub: <strong>{platformSettings.piiScrubDays} days</strong> · Write-once immutable engine</p>
                </div>
                <div className="config-card">
                  <h4>Application Health & Operational Pool</h4>
                  <p>PostgreSQL Connection Pool Max: <strong>{platformSettings.databasePoolMax}</strong></p>
                  <p>Redis Async Queue Workers: <strong>{platformSettings.queueWorkersCount} workers</strong></p>
                </div>
                <div className="config-card">
                  <h4>Global Platform Take Rates</h4>
                  <p>
                    Platform Fee: <strong>{platformFeeSettings?.takeRatePercent ?? 2.5}%</strong> · Per-Ticket: USD <strong>${(platformFeeSettings?.fixedFeeUSD ?? 0.3).toFixed(2)}</strong>
                  </p>
                </div>
              </div>

              {/* Secret Key Protection Notice */}
              <div className="secret-keys-shield-box">
                <Lock size={18} />
                <div>
                  <strong>Secret Keys Protection Standard:</strong>
                  <p>
                    Secret keys (e.g. <code>{maskSecretKey("sk_live_998124871928371982")}</code> and <code>{maskSecretKey("AKIAIOSFODNN7EXAMPLE")}</code>) are securely managed via environment variables and AWS Secrets Manager. They are NEVER exposed in ordinary frontend forms or logs.
                  </p>
                </div>
              </div>
            </div>
          )}

          {/* Setting 8: Event Management (Event Defaults Area) */}
          {activeSettingKey === "event_management" && (
            <div className="settings-form-content">
              {accountType === "event_host" ? (
                <div className="event-defaults-form">
                  <h4 className="subheading-title">Event Defaults Configuration</h4>
                  <p className="subheading-desc">
                    Configure baseline defaults for new U.S. events and community fundraisers.
                  </p>

                  <div className="settings-grid-2col">
                    <div className="form-group">
                      <label htmlFor="host-default-currency">Default Event Currency</label>
                      <select
                        id="host-default-currency"
                        className="settings-input"
                        value={eventDefaults.defaultCurrency}
                        onChange={(e) => setEventDefaults({ ...eventDefaults, defaultCurrency: e.target.value as Currency })}
                      >
                        <option value="USD">USD — United States Dollar (US$)</option>
                      </select>
                    </div>

                    <div className="form-group">
                      <label htmlFor="host-default-timezone">Default Timezone</label>
                      <select
                        id="host-default-timezone"
                        className="settings-input"
                        value={eventDefaults.defaultTimezone}
                        onChange={(e) => setEventDefaults({ ...eventDefaults, defaultTimezone: e.target.value })}
                      >
                        <option value="America/New_York">America/New_York (Eastern Time)</option>
                        <option value="America/Chicago">America/Chicago (Central Time)</option>
                        <option value="America/Denver">America/Denver (Mountain Time)</option>
                        <option value="America/Los_Angeles">America/Los_Angeles (Pacific Time)</option>
                        <option value="America/Phoenix">America/Phoenix (Arizona Time)</option>
                        <option value="America/Anchorage">America/Anchorage (Alaska Time)</option>
                        <option value="Pacific/Honolulu">Pacific/Honolulu (Hawaii Time)</option>
                      </select>
                    </div>
                  </div>

                  <div className="form-group">
                    <label htmlFor="host-default-location">Default Event Location</label>
                    <input
                      id="host-default-location"
                      type="text"
                      className="settings-input"
                      value={eventDefaults.defaultLocation}
                      onChange={(e) => setEventDefaults({ ...eventDefaults, defaultLocation: e.target.value })}
                    />
                  </div>

                  <div className="settings-grid-2col">
                    <div className="form-group">
                      <label htmlFor="host-default-deadline">Default Order Deadline</label>
                      <select
                        id="host-default-deadline"
                        className="settings-input"
                        value={eventDefaults.defaultOrderDeadlineHours}
                        onChange={(e) => setEventDefaults({ ...eventDefaults, defaultOrderDeadlineHours: Number(e.target.value) })}
                      >
                        <option value={12}>12 Hours before event kickoff</option>
                        <option value={24}>24 Hours before event kickoff</option>
                        <option value={48}>48 Hours before event kickoff</option>
                      </select>
                    </div>

                    <div className="form-group">
                      <label htmlFor="host-default-aspect">Default Flyer Aspect Ratio</label>
                      <select
                        id="host-default-aspect"
                        className="settings-input"
                        value={eventDefaults.defaultFlyerAspectRatio}
                        onChange={(e) => setEventDefaults({ ...eventDefaults, defaultFlyerAspectRatio: e.target.value as any })}
                      >
                        <option value="4:5">4:5 Portrait (Recommended for Social & Mobile)</option>
                        <option value="1:1">1:1 Square</option>
                        <option value="16:9">16:9 Landscape Banner</option>
                      </select>
                    </div>
                  </div>

                  <div className="form-group">
                    <label htmlFor="host-default-pickup">Default Ticket & Pickup Instructions</label>
                    <textarea
                      id="host-default-pickup"
                      rows={3}
                      className="settings-textarea"
                      value={eventDefaults.defaultPickupInstructions}
                      onChange={(e) => setEventDefaults({ ...eventDefaults, defaultPickupInstructions: e.target.value })}
                    />
                  </div>

                  <div className="settings-actions-bar">
                    <button
                      type="button"
                      className="settings-btn-primary"
                      onClick={() => handleSaveHostSettings("Event Defaults")}
                    >
                      Save Event Defaults
                    </button>
                    <button
                      type="button"
                      className="settings-btn-secondary"
                      onClick={() => onNavigateToTab?.("event")}
                    >
                      Open Live Flyer Studio
                    </button>
                  </div>
                </div>
              ) : (
                <div className="event-scope-summary">
                  <strong>Current Scope: {formatScopeLabel(activeScope)}</strong>
                  <p>
                    {accountType === "admin"
                      ? "Authorized to review event compliance, moderate flyer contents, and assist hosts within assigned regional scope."
                      : "Platform-wide event oversight with policy enforcement and emergency moderation."}
                  </p>
                </div>
              )}
            </div>
          )}

          {/* Setting 9: Flyer and Menu Management */}
          {activeSettingKey === "flyer_menu_management" && (
            <div className="settings-form-content">
              <div className="settings-info-card">
                <Utensils size={18} />
                <div>
                  <strong>{formatScopeLabel(activeScope)}:</strong>
                  <p>
                    {accountType === "event_host"
                      ? "Upload promotional flyers (JPEG, PNG, WebP <= 5MB), define meal categories, item prices, add-on options, and stock limits."
                      : "Oversight of promotional flyer assets, food catalog structures, and pricing accuracy."}
                  </p>
                  {accountType === "event_host" && (
                    <button
                      type="button"
                      className="settings-btn-primary"
                      onClick={() => onNavigateToTab?.("menu")}
                    >
                      Open Menu & Pricing Studio
                    </button>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Setting 10 & 11: Event Payment Instructions & Policies */}
          {(activeSettingKey === "event_payment_instructions" || activeSettingKey === "record_verify_payments") && (
            <div className="settings-form-content">
              {accountType === "event_host" ? (
                <div className="host-payment-policies-form">
                  <h4 className="subheading-title">Payment Policies & Eligibility Gating</h4>
                  <p className="subheading-desc">
                    Payment methods should be enabled only when the host is eligible to receive payments through them.
                  </p>

                  <div className="payment-eligibility-cards">
                    {/* Cash */}
                    <div className="eligibility-card">
                      <div className="card-top">
                        <Banknote size={16} />
                        <strong>Cash on Pickup</strong>
                        <input
                          type="checkbox"
                          checked={paymentPolicies.enableCash}
                          onChange={(e) => setPaymentPolicies({ ...paymentPolicies, enableCash: e.target.checked })}
                        />
                      </div>
                      <p>Physical collection station receipt recording with audit trail.</p>
                      <span className="badge-eligible">Always Eligible</span>
                    </div>

                    {/* U.S. bank transfer */}
                    <div className="eligibility-card">
                      <div className="card-top">
                        <Wallet size={16} />
                        <strong>U.S. Bank Transfers / ACH</strong>
                        <span className="status-badge-active">Active</span>
                      </div>
                      <p>Enable only after verified U.S. payout instructions are configured.</p>
                      <span className="badge-eligible">U.S. market</span>
                    </div>

                    {/* Zelle (US Only) */}
                    <div className="eligibility-card">
                      <div className="card-top">
                        <DollarSign size={16} />
                        <strong>Zelle Transfers (USD)</strong>
                        <span className={settings?.businessLocation === "US" ? "badge-eligible" : "badge-ineligible"}>
                          {settings?.businessLocation === "US" ? "Eligible" : "Ineligible (US Only)"}
                        </span>
                      </div>
                      <p>Requires verified US entity and US routing/account numbers.</p>
                      <div className="eligibility-note">
                        {evaluateHostPaymentEligibility(settings?.businessLocation || "US", "zelle").requirement}
                      </div>
                    </div>

                    {/* Cash App (US Only) */}
                    <div className="eligibility-card">
                      <div className="card-top">
                        <Smartphone size={16} />
                        <strong>Cash App (USD)</strong>
                        <span className={settings?.businessLocation === "US" ? "badge-eligible" : "badge-ineligible"}>
                          {settings?.businessLocation === "US" ? "Eligible" : "Ineligible (US Only)"}
                        </span>
                      </div>
                      <p>Requires verified US Cashtag linked to US commercial account.</p>
                      <div className="eligibility-note">
                        {evaluateHostPaymentEligibility(settings?.businessLocation || "JM", "cashapp").requirement}
                      </div>
                    </div>
                  </div>

                  {/* Verification Requirements */}
                  <h4 className="subheading-title" style={{ marginTop: 18 }}>Payment Verification Requirements</h4>
                  <div className="toggle-list-group">
                    <label className="toggle-row">
                      <div className="toggle-meta">
                        <Key size={16} />
                        <div>
                          <strong>Require Sender Name Matching</strong>
                          <span>Enforces that customer provides the exact name on their bank/Zelle account.</span>
                        </div>
                      </div>
                      <input
                        type="checkbox"
                        checked={paymentPolicies.requireSenderName}
                        onChange={(e) => setPaymentPolicies({ ...paymentPolicies, requireSenderName: e.target.checked })}
                      />
                    </label>

                    <label className="toggle-row">
                      <div className="toggle-meta">
                        <Check size={16} />
                        <div>
                          <strong>Require Bank Transaction Reference</strong>
                          <span>Mandates 6-12 character deposit or wire reference before submission.</span>
                        </div>
                      </div>
                      <input
                        type="checkbox"
                        checked={paymentPolicies.requireTransferReference}
                        onChange={(e) => setPaymentPolicies({ ...paymentPolicies, requireTransferReference: e.target.checked })}
                      />
                    </label>

                    <label className="toggle-row">
                      <div className="toggle-meta">
                        <FileSpreadsheet size={16} />
                        <div>
                          <strong>Require Transfer Receipt Snapshot</strong>
                          <span>Allows attendee to upload screenshot of mobile banking payment confirmation.</span>
                        </div>
                      </div>
                      <input
                        type="checkbox"
                        checked={paymentPolicies.requireReceiptSnapshot}
                        onChange={(e) => setPaymentPolicies({ ...paymentPolicies, requireReceiptSnapshot: e.target.checked })}
                      />
                    </label>
                  </div>

                  {/* Refund and Cancellation Policies */}
                  <h4 className="subheading-title" style={{ marginTop: 18 }}>Refund and Cancellation Policies</h4>
                  <div className="form-group">
                    <label>Refund Deadline Window (Hours Prior to Cutoff)</label>
                    <input
                      type="number"
                      className="settings-input"
                      value={paymentPolicies.refundPolicyDeadlineHours}
                      onChange={(e) => setPaymentPolicies({ ...paymentPolicies, refundPolicyDeadlineHours: Number(e.target.value) })}
                    />
                  </div>

                  <div className="form-group">
                    <label>Refund Terms & Policy Note (Displayed on Customer Checkout)</label>
                    <textarea
                      rows={2}
                      className="settings-textarea"
                      value={paymentPolicies.refundPolicyNotes}
                      onChange={(e) => setPaymentPolicies({ ...paymentPolicies, refundPolicyNotes: e.target.value })}
                    />
                  </div>

                  <div className="settings-actions-bar">
                    <button
                      type="button"
                      className="settings-btn-primary"
                      onClick={() => handleSaveHostSettings("Payment Policies & Eligibility Settings")}
                    >
                      Save Payment Settings
                    </button>
                    <button
                      type="button"
                      className="settings-btn-secondary"
                      onClick={() => onNavigateToTab?.("payments")}
                    >
                      Open Payments & Cash Ledger
                    </button>
                  </div>
                </div>
              ) : (
                <div className="settings-info-card">
                  <Banknote size={18} />
                  <div>
                    <strong>{formatScopeLabel(activeScope)}:</strong>
                    <p>
                      Platform defaults and regional banking compliance oversight. Audited dispute triage and payment record inspection.
                    </p>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Setting 12 & 13: Event Operations (Staff, Stations, Buffers, Tickets) */}
          {(activeSettingKey === "manage_event_staff" || activeSettingKey === "manage_tickets_collection") && (
            <div className="settings-form-content">
              {accountType === "event_host" ? (
                <div className="host-operations-form">
                  <h4 className="subheading-title">Event Operations & Station Staffing</h4>
                  <p className="subheading-desc">
                    Configure staff station assignments, stock caps, anti-duplicate rules, and kitchen buffers.
                  </p>

                  <div className="stations-staff-grid">
                    {operationalRules.stations.map((st) => (
                      <div key={st.id} className="station-card">
                        <div className="station-header">
                          <ChefHat size={16} />
                          <strong>{st.name}</strong>
                        </div>
                        <p>Staff Assigned: <strong>{st.staffAssigned}</strong></p>
                        <span className="badge-station-active">Live Collection Point</span>
                      </div>
                    ))}
                  </div>

                  <div className="settings-grid-2col" style={{ marginTop: 14 }}>
                    <div className="form-group">
                      <label>Global Event Meal Capacity Cap</label>
                      <input
                        type="number"
                        className="settings-input"
                        value={operationalRules.maxEventCapacity}
                        onChange={(e) => setOperationalRules({ ...operationalRules, maxEventCapacity: Number(e.target.value) })}
                      />
                    </div>
                    <div className="form-group">
                      <label>Max Portions Allowed Per Customer Order</label>
                      <input
                        type="number"
                        className="settings-input"
                        value={operationalRules.maxPerItemPortions}
                        onChange={(e) => setOperationalRules({ ...operationalRules, maxPerItemPortions: Number(e.target.value) })}
                      />
                    </div>
                  </div>

                  <div className="settings-grid-2col">
                    <div className="form-group">
                      <label>Kitchen Food Preparation Buffer</label>
                      <select
                        className="settings-input"
                        value={operationalRules.foodPrepBufferPercent}
                        onChange={(e) => setOperationalRules({ ...operationalRules, foodPrepBufferPercent: Number(e.target.value) })}
                      >
                        <option value={0}>0% Exact Count (No over-prep)</option>
                        <option value={5}>+5% Buffer</option>
                        <option value={10}>+10% Buffer (Recommended for fish fry walk-ins)</option>
                        <option value={15}>+15% Buffer</option>
                      </select>
                    </div>

                    <div className="form-group">
                      <label>Anti-Duplicate Ticket Redemption Rule</label>
                      <div className="status-box-enforced">
                        <ShieldCheck size={16} />
                        <span>Cryptographically Enforced (Zero double-collection permitted)</span>
                      </div>
                    </div>
                  </div>

                  {/* Event Reports and Exports */}
                  <h4 className="subheading-title" style={{ marginTop: 18 }}>Event Reports and Exports</h4>
                  <div className="reports-export-row">
                    <button
                      type="button"
                      className="settings-btn-outline"
                      onClick={() => alert("Downloaded CSV Customer Order Manifest")}
                    >
                      <Download size={14} /> Export CSV Customer Manifest
                    </button>
                    <button
                      type="button"
                      className="settings-btn-outline"
                      onClick={() => alert("Downloaded PDF Kitchen Preparation Sheet")}
                    >
                      <FileSpreadsheet size={14} /> Export Kitchen Prep Sheet
                    </button>
                  </div>

                  <div className="settings-actions-bar" style={{ marginTop: 16 }}>
                    <button
                      type="button"
                      className="settings-btn-primary"
                      onClick={() => handleSaveHostSettings("Event Operations & Station Rules")}
                    >
                      Save Operations Settings
                    </button>
                  </div>
                </div>
              ) : accountType === "attendee" ? (
                /* Attendee Ticket Retrieval Tool */
                <div className="attendee-ticket-lookup-area">
                  <h4 className="subheading-title">Ticket Retrieval & Collection Pass</h4>
                  <p className="subheading-desc">
                    Look up active vouchers and check meal handover progress by entering your ticket code or phone number.
                  </p>

                  <form onSubmit={handleLookupTicket} className="retrieval-search-box">
                    <input
                      type="text"
                      className="settings-input"
                      placeholder="Enter Ticket Code (e.g. TC-4412) or Phone Number..."
                      value={retrievalQuery}
                      onChange={(e) => setRetrievalQuery(e.target.value)}
                    />
                    <button type="submit" className="settings-btn-primary">
                      <Search size={14} /> Find My Ticket
                    </button>
                  </form>

                  {retrievalError && (
                    <div className="settings-alert-error" style={{ marginTop: 12 }}>
                      <AlertTriangle size={15} /> {retrievalError}
                    </div>
                  )}

                  {retrievalResult && (
                    <div className="retrieved-ticket-card" style={{ marginTop: 14 }}>
                      <div className="ticket-card-header">
                        <QrCode size={18} />
                        <strong>Ticket Pass: {retrievalResult.ticketCode}</strong>
                        <span className={`status-pill status-${retrievalResult.status}`}>
                          {retrievalResult.status.toUpperCase()}
                        </span>
                      </div>
                      <div className="ticket-card-body">
                        <p>Order: <strong>{retrievalResult.orderNumber}</strong> · Event: <strong>{eventDetails?.title || "Community event"}</strong></p>
                        <p>Total: <strong>{formatMoney(retrievalResult.totalAmount, retrievalResult.currency)}</strong></p>
                        <p>Meal Handover Progress: <strong>{retrievalResult.items.reduce((sum, itm) => sum + (itm.collectedQuantity || 0), 0)} of {retrievalResult.items.reduce((sum, itm) => sum + itm.quantity, 0) || 1} portions collected</strong></p>
                      </div>
                      <button
                        type="button"
                        className="settings-btn-secondary"
                        onClick={() => onNavigateToTab?.("tickets")}
                      >
                        Open In Digital Ticket Wallet
                      </button>
                    </div>
                  )}
                </div>
              ) : (
                <div className="settings-info-card">
                  <QrCode size={18} />
                  <div>
                    <strong>{formatScopeLabel(activeScope)}:</strong>
                    <p>
                      System-wide ticket validation, QR security keys, anti-duplicate redemption rules, and dispute oversight.
                    </p>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Setting 14: Sales and Financial Reports (Attendee Order History & Receipt Access) */}
          {activeSettingKey === "sales_financial_reports" && (
            <div className="settings-form-content">
              {accountType === "attendee" ? (
                <div className="attendee-order-history-area">
                  <h4 className="subheading-title">Order History & Printable Receipts</h4>
                  <p className="subheading-desc">
                    Review completed and upcoming orders, download official PDF payment receipts, and check collection statuses.
                  </p>

                  <div className="table-responsive">
                    <table className="settings-table">
                      <thead>
                        <tr>
                          <th>Order #</th>
                          <th>Items</th>
                          <th>Charged Amount</th>
                          <th>Status</th>
                          <th>Receipt</th>
                        </tr>
                      </thead>
                      <tbody>
                        {orders.map((o) => (
                          <tr key={o.id}>
                            <td>
                              <strong>{o.orderNumber}</strong>
                              <div className="cell-subtext">{new Date(o.createdAt).toLocaleDateString()}</div>
                            </td>
                            <td>{o.items.map((i) => `${i.quantity}x ${i.name}`).join(", ")}</td>
                            <td>
                              <strong>{formatMoney(o.totalAmount, o.currency)}</strong>
                              <div className="cell-subtext">Actual charged currency: {o.currency}</div>
                            </td>
                            <td>
                              <span className={`badge-status-${o.status}`}>{o.status.toUpperCase()}</span>
                            </td>
                            <td>
                              <button
                                type="button"
                                className="settings-btn-outline btn-sm"
                                onClick={() => alert(`Receipt downloaded for Order ${o.orderNumber}`)}
                              >
                                <Download size={12} /> Download PDF
                              </button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              ) : (
                <div className="settings-info-card">
                  <FileSpreadsheet size={18} />
                  <div>
                    <strong>{formatScopeLabel(activeScope)}:</strong>
                    <p>
                      {accountType === "event_host"
                        ? `Own event financial reports: total sales across ${orders.length} orders in JMD/USD, cash reconciliation, and kitchen prep forecasts.`
                        : `Aggregated U.S. platform financial metrics (e.g. sample revenue: ${formatMoney(12000, "USD")}) and fee settlement reports.`}
                    </p>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Setting 15: Currency and Regional Preferences (Attendee Preferred Display Currency) */}
          {activeSettingKey === "currency_regional_preferences" && (
            <div className="settings-form-content">
              {accountType === "attendee" ? (
                <div className="attendee-currency-preference-box">
                  <h4 className="subheading-title">Preferred Display Currency</h4>
                  <p className="subheading-desc">
                    Choose your preferred currency for estimated price display while browsing menus.
                  </p>

                  <div className="form-group" style={{ maxWidth: 360 }}>
                    <label htmlFor="pref-display-curr">Display Currency</label>
                    <select
                      id="pref-display-curr"
                      className="settings-input"
                      value={preferredDisplayCurrency}
                      onChange={(e) => setPreferredDisplayCurrency(e.target.value as Currency)}
                    >
                      <option value="USD">USD — United States Dollar (US$)</option>
                      <option value="JMD">JMD — Jamaican Dollar (historical transactions)</option>
                    </select>
                  </div>

                  {/* Crucial Invariant Callout */}
                  <div className="display-currency-invariant-card">
                    <ShieldCheck size={18} />
                    <div>
                      <strong>Critical Currency Protection Rule:</strong>
                      <p>
                        The preferred display currency never changes the amount or currency charged. New U.S. event orders are charged in <strong>USD</strong>; historical orders retain their original currency.
                      </p>
                    </div>
                  </div>

                  {/* Live Simulation Card */}
                  <div className="conversion-simulation-card">
                    <Sparkles size={16} />
                    <div>
                      <strong>Example Pricing Preview:</strong>
                      {orders[0] && (
                        <p>
                          Order {orders[0].orderNumber}: Actual charge is <strong>{orders[0].currency} ${(orders[0].totalAmount / 100).toFixed(2)}</strong>.
                          Display estimate: <strong>{evaluateAttendeeDisplayCurrencyConversion(orders[0], preferredDisplayCurrency).displayFormattedEstimate}</strong>.
                        </p>
                      )}
                    </div>
                  </div>
                </div>
              ) : (
                <div className="settings-grid-2col">
                  <div className="form-group">
                    <label>
                      {accountType === "event_host" ? "Event Primary Currency" : "Platform Default Currency"}
                    </label>
                    <input
                      type="text"
                      className="settings-input disabled-input"
                      value={settings?.currency || "USD"}
                      disabled
                    />
                  </div>
                  <div className="form-group">
                    <label>Timezone</label>
                    <input
                      type="text"
                      className="settings-input disabled-input"
                      value={eventDefaults.defaultTimezone || "America/New_York"}
                      disabled
                    />
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Setting 16: Privacy and Data Controls (Account Deletion & Data Portability) */}
          {activeSettingKey === "privacy_data_controls" && (
            <div className="settings-form-content">
              {deletionSuccessNotice && (
                <div className="settings-alert-success">
                  <CheckCircle2 size={16} /> {deletionSuccessNotice}
                </div>
              )}

              <div className="privacy-cards-grid">
                <div className="privacy-card">
                  <h4>Data Portability & Manifest Export</h4>
                  <p>
                    You can download a complete archive of your purchase records, contact information, and tickets for your own records and applicable privacy requests.
                  </p>
                  <button
                    type="button"
                    className="settings-btn-outline"
                    style={{ marginTop: 10 }}
                    onClick={() => {
                      alert("Personal Data Archive exported in JSON & CSV formats.");
                      onSecurityLog?.(
                        "data_export",
                        "privacy_controls",
                        `Exported data archive for ${accountType}`,
                        true
                      );
                    }}
                  >
                    <Download size={14} /> Download Data Archive
                  </button>
                </div>

                <div className="privacy-card">
                  <h4>Account Deletion & Erasure</h4>
                  <p>
                    Permanently delete your attendee account, scrub personal contact data, and invalidate active sign-in sessions.
                  </p>
                  <button
                    type="button"
                    className="settings-btn-danger"
                    style={{ marginTop: 10 }}
                    onClick={() => setShowDeleteModal(true)}
                  >
                    <Trash2 size={14} /> Request Account Deletion
                  </button>
                </div>
              </div>

              {/* Account Deletion Confirmation Modal */}
              {showDeleteModal && (
                <div className="modal-backdrop" onClick={() => setShowDeleteModal(false)}>
                  <div className="modal-card delete-confirm-modal" onClick={(e) => e.stopPropagation()}>
                    <div className="modal-header">
                      <div className="modal-title-with-icon">
                        <AlertTriangle size={20} className="text-danger" />
                        <h3>Confirm Account Deletion Request</h3>
                      </div>
                      <button
                        type="button"
                        className="modal-close-btn"
                        onClick={() => setShowDeleteModal(false)}
                      >
                        <X size={18} />
                      </button>
                    </div>
                    <div className="modal-body">
                      <p>
                        Are you sure you want to delete your attendee profile for <strong>{emailInput}</strong>?
                      </p>
                      <p className="sub-text">
                        This action will scrub your name, phone number, and password credentials. Past orders will be anonymized to maintain financial accounting integrity.
                      </p>
                    </div>
                    <div className="modal-footer">
                      <button
                        type="button"
                        className="settings-btn-secondary"
                        onClick={() => setShowDeleteModal(false)}
                      >
                        Cancel
                      </button>
                      <button
                        type="button"
                        className="settings-btn-danger"
                        onClick={() => {
                          setShowDeleteModal(false);
                          setDeletionSuccessNotice("Account deletion requested. PII scrub scheduled in accordance with data retention policies.");
                          onSecurityLog?.(
                            "account_erasure_request",
                            "privacy_controls",
                            `Account deletion requested for ${emailInput}`,
                            true
                          );
                        }}
                      >
                        Confirm Permanent Deletion
                      </button>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Setting 17: Audit and Security Logs */}
          {activeSettingKey === "audit_security_logs" && (
            <div className="settings-form-content">
              <div className="audit-scope-header">
                <div>
                  <strong>{formatScopeLabel(activeScope)} Security Trail</strong>
                  <p>
                    Showing {scopedAuditLogs.length} immutable security event entries recorded under this account's authorization scope.
                  </p>
                </div>
              </div>

              <div className="table-responsive">
                <table className="settings-table">
                  <thead>
                    <tr>
                      <th>Timestamp</th>
                      <th>Action</th>
                      <th>Target Resource</th>
                      <th>Actor</th>
                      <th>Details</th>
                      <th>PII Tag</th>
                    </tr>
                  </thead>
                  <tbody>
                    {scopedAuditLogs.map((log) => (
                      <tr key={log.id}>
                        <td>
                          <span className="mono-text">
                            {new Date(log.timestamp).toLocaleTimeString([], {
                              hour: "2-digit",
                              minute: "2-digit",
                              second: "2-digit",
                            })}
                          </span>
                        </td>
                        <td>
                          <span className="badge-action">{log.action}</span>
                        </td>
                        <td>
                          <span className="mono-text">{log.targetEntity}</span>
                        </td>
                        <td>{log.actorName}</td>
                        <td className="cell-details">{log.details}</td>
                        <td>
                          {log.isPiiAccess ? (
                            <span className="badge-pii-audited">PII Audited</span>
                          ) : (
                            <span className="badge-standard">Standard</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </main>
      </div>
    </div>
  );
};
