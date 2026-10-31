import { useState } from "react";
import {
  Copy,
  CreditCard,
  Lock,
  Save,
  ShieldCheck,
} from "lucide-react";
import {
  CashPaymentRecord,
  Currency,
  EventDetails,
  MenuItem,
  NotificationLogEntry,
  OrganizerProfile,
  PaymentMethodKey,
  Settings,
  TenantUserMember,
  TicketOrder,
} from "../../types";
import { AccountSettingsView } from "../AccountSettingsView";
import { HostRolePermissions } from "./types";

type Props = {
  eventDetails: EventDetails;
  menu: MenuItem[];
  settings: Settings;
  orders: TicketOrder[];
  cashLedger: CashPaymentRecord[];
  notificationLogs: NotificationLogEntry[];
  activeOrganizer: OrganizerProfile;
  tenantUsers: TenantUserMember[];
  permissions: HostRolePermissions;
  copiedMethodKey: string | null;
  onCopyMethodDetails: (text: string, methodKey: string) => void;
  onUpdateEventDetails: (details: EventDetails) => void;
  onUpdateMenu: (menu: MenuItem[]) => void;
  onUpdateSettings: (settings: Settings) => void;
  onOpenGatewayModal: () => void;
  onSaveSettingsToast: () => void;
  formatMoney: (amount: number, currency: Currency) => string;
};

export function HostSettingsSection({
  eventDetails,
  settings,
  orders,
  activeOrganizer,
  tenantUsers,
  permissions,
  copiedMethodKey,
  onCopyMethodDetails,
  onUpdateEventDetails,
  onUpdateSettings,
  onOpenGatewayModal,
  onSaveSettingsToast,
  formatMoney,
}: Props) {
  const [hostSettingsSubView, setHostSettingsSubView] = useState<
    "account_settings" | "payment_methods"
  >("account_settings");

  const methodSpecs: Array<{
    key: PaymentMethodKey;
    title: string;
    subtitle: string;
  }> = [
    {
      key: "bank",
      title: "Bank Transfer (NCB / Scotiabank / ACH)",
      subtitle: "Direct local bank transfer with reference matching",
    },
    {
      key: "card",
      title: "Card Payment Gateway (WiPay / Stripe)",
      subtitle: "Automated card clearance with instant settlement",
    },
    {
      key: "cash",
      title: "In-Person Cash Box Office",
      subtitle: "Physical cash collection with official receipt ledger",
    },
    {
      key: "zelle",
      title: "Zelle Transfer (US Eligible Accounts)",
      subtitle: "US diaspora & domestic instant bank-to-bank transfer",
    },
    {
      key: "cashapp",
      title: "Cash App ($Cashtag)",
      subtitle: "Mobile peer-to-peer wallet transfer",
    },
  ];

  return (
    <div id="host-settings-view" style={{ display: "flex", flexDirection: "column", gap: "18px" }}>
      {!permissions.canManageSettingsAndBroadcast && (
        <div
          style={{
            padding: "14px 18px",
            borderRadius: "10px",
            background: "rgba(239, 68, 68, 0.15)",
            border: "1px solid rgba(248, 113, 113, 0.4)",
            color: "#FCA5A5",
            fontSize: "12px",
            display: "flex",
            alignItems: "center",
            gap: "10px",
          }}
        >
          <Lock size={16} />
          <span>
            <strong>Role Access Control Enforced:</strong> Your current role (
            {permissions.roleLabel}) has read-only access to host account & payment settings.
          </span>
        </div>
      )}

      {/* Sub-navigation bar preserving #host-tab-account-settings and #host-tab-payment-methods */}
      <div
        style={{
          display: "flex",
          gap: "10px",
          padding: "8px",
          background: "#0B1120",
          borderRadius: "12px",
          border: "1px solid rgba(148, 163, 184, 0.18)",
          flexWrap: "wrap",
        }}
      >
        <button
          id="host-tab-account-settings"
          type="button"
          onClick={() => setHostSettingsSubView("account_settings")}
          style={{
            flex: 1,
            minWidth: "220px",
            padding: "10px 16px",
            borderRadius: "8px",
            border:
              hostSettingsSubView === "account_settings"
                ? "1px solid #10B981"
                : "1px solid transparent",
            background:
              hostSettingsSubView === "account_settings"
                ? "rgba(16, 185, 129, 0.16)"
                : "transparent",
            color:
              hostSettingsSubView === "account_settings" ? "#34D399" : "#94A3B8",
            fontWeight: 800,
            fontSize: "12px",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            gap: "8px",
          }}
        >
          <ShieldCheck size={16} />
          Scoped Account Settings (14 Host Scopes)
        </button>

        <button
          id="host-tab-payment-methods"
          type="button"
          onClick={() => setHostSettingsSubView("payment_methods")}
          style={{
            flex: 1,
            minWidth: "220px",
            padding: "10px 16px",
            borderRadius: "8px",
            border:
              hostSettingsSubView === "payment_methods"
                ? "1px solid #818CF8"
                : "1px solid transparent",
            background:
              hostSettingsSubView === "payment_methods"
                ? "rgba(99, 102, 241, 0.2)"
                : "transparent",
            color:
              hostSettingsSubView === "payment_methods" ? "#A5B4FC" : "#94A3B8",
            fontWeight: 800,
            fontSize: "12px",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            gap: "8px",
          }}
        >
          <CreditCard size={16} />
          Payment Methods & Regional Currency ({settings.currency})
        </button>
      </div>

      {hostSettingsSubView === "account_settings" ? (
        <AccountSettingsView
          accountType="event_host"
          currentUser={{
            name: activeOrganizer.name,
            email: activeOrganizer.contactEmail,
            phone: activeOrganizer.contactPhone,
            roleLabel: permissions.roleLabel,
            region: activeOrganizer.businessLocation === "JM" ? "Legacy international organizer" : "United States",
          }}
          eventDetails={eventDetails}
          settings={settings}
          orders={orders}
          tenantUsers={tenantUsers}
          formatMoney={formatMoney}
          onUpdateEventDetails={onUpdateEventDetails}
          onUpdateSettings={(updater) => onUpdateSettings(updater(settings))}
        />
      ) : (
        <section
          style={{
            background: "#0B1120",
            border: "1px solid rgba(148, 163, 184, 0.18)",
            borderRadius: "16px",
            padding: "20px 22px",
          }}
        >
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              gap: "12px",
              marginBottom: "16px",
              flexWrap: "wrap",
            }}
          >
            <div>
              <span style={{ fontSize: "10px", fontWeight: 800, color: "#818CF8", textTransform: "uppercase" }}>
                Settlement & Payment Rails
              </span>
              <h3 style={{ margin: "2px 0 0", fontSize: "17px", fontWeight: 800, color: "#F8FAFC" }}>
                Event Payment Methods & Currency Configuration
              </h3>
            </div>

            <div style={{ display: "flex", gap: "8px" }}>
              <button
                type="button"
                onClick={onOpenGatewayModal}
                style={{
                  padding: "8px 13px",
                  borderRadius: "8px",
                  background: "rgba(99, 102, 241, 0.18)",
                  border: "1px solid rgba(129, 140, 248, 0.35)",
                  color: "#A5B4FC",
                  fontSize: "11px",
                  fontWeight: 700,
                }}
              >
                Gateway Architecture Modal
              </button>
              <button
                type="button"
                disabled={!permissions.canManageSettingsAndBroadcast}
                onClick={onSaveSettingsToast}
                style={{
                  padding: "8px 14px",
                  borderRadius: "8px",
                  background: "#10B981",
                  color: "#052E16",
                  border: "none",
                  fontSize: "11px",
                  fontWeight: 800,
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "6px",
                }}
              >
                <Save size={13} />
                Save Payment Config
              </button>
            </div>
          </div>

          {/* Region & Currency Switcher */}
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
              gap: "12px",
              marginBottom: "18px",
            }}
          >
            <div
              style={{
                padding: "14px",
                borderRadius: "11px",
                background: "#0F172A",
                border: "1px solid rgba(148, 163, 184, 0.2)",
              }}
            >
              <label style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "6px" }}>
                Active Settlement Currency
              </label>
              <select
                value={settings.currency}
                onChange={(e) => {
                  const nextCurr = e.target.value as Currency;
                  onUpdateSettings({ ...settings, currency: nextCurr });
                  onUpdateEventDetails({ ...eventDetails, currency: nextCurr });
                }}
                style={{
                  width: "100%",
                  height: "36px",
                  padding: "0 10px",
                  borderRadius: "7px",
                  background: "#1E293B",
                  color: "#F8FAFC",
                  border: "1px solid rgba(148, 163, 184, 0.35)",
                  fontSize: "12px",
                  fontWeight: 700,
                }}
              >
                <option value="USD">USD — United States Dollar ($)</option>
                <option value="JMD">JMD — Jamaican Dollar (historical transactions)</option>
              </select>
            </div>

            <div
              style={{
                padding: "14px",
                borderRadius: "11px",
                background: "#0F172A",
                border: "1px solid rgba(148, 163, 184, 0.2)",
              }}
            >
              <label style={{ display: "block", fontSize: "11px", fontWeight: 700, color: "#CBD5E1", marginBottom: "6px" }}>
                Business Operating Jurisdiction
              </label>
              <select
                value={settings.businessLocation}
                onChange={(e) =>
                  onUpdateSettings({
                    ...settings,
                    businessLocation: e.target.value as Settings["businessLocation"],
                  })
                }
                style={{
                  width: "100%",
                  height: "36px",
                  padding: "0 10px",
                  borderRadius: "7px",
                  background: "#1E293B",
                  color: "#F8FAFC",
                  border: "1px solid rgba(148, 163, 184, 0.35)",
                  fontSize: "12px",
                  fontWeight: 700,
                }}
              >
                <option value="US">United States (US — ACH / Zelle / Cash App / Stripe)</option>
                <option value="JM">Jamaica (legacy organizers only)</option>
              </select>
            </div>
          </div>

          {/* Payment Methods List */}
          <div style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
            {methodSpecs.map((spec) => {
              const cfg = settings.methods[spec.key];
              return (
                <div
                  key={spec.key}
                  style={{
                    padding: "14px 16px",
                    borderRadius: "12px",
                    background: "#0F172A",
                    border: cfg.enabled
                      ? "1px solid rgba(52, 211, 153, 0.35)"
                      : "1px solid rgba(148, 163, 184, 0.18)",
                  }}
                >
                  <div
                    style={{
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "space-between",
                      gap: "10px",
                      marginBottom: "8px",
                      flexWrap: "wrap",
                    }}
                  >
                    <div>
                      <strong style={{ fontSize: "13px", color: "#F8FAFC" }}>{spec.title}</strong>
                      <div style={{ fontSize: "11px", color: "#94A3B8" }}>{spec.subtitle}</div>
                    </div>

                    <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                      <button
                        type="button"
                        onClick={() => onCopyMethodDetails(cfg.details, spec.key)}
                        style={{
                          padding: "5px 10px",
                          borderRadius: "6px",
                          background: "rgba(30, 41, 59, 0.85)",
                          border: "1px solid rgba(148, 163, 184, 0.25)",
                          color: "#CBD5E1",
                          fontSize: "10px",
                          fontWeight: 700,
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "4px",
                        }}
                      >
                        <Copy size={11} />
                        {copiedMethodKey === spec.key ? "Copied!" : "Copy Details"}
                      </button>

                      <label
                        style={{
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "6px",
                          fontSize: "11px",
                          fontWeight: 700,
                          color: cfg.enabled ? "#34D399" : "#94A3B8",
                          cursor: "pointer",
                        }}
                      >
                        <input
                          type="checkbox"
                          checked={cfg.enabled}
                          onChange={(e) =>
                            onUpdateSettings({
                              ...settings,
                              methods: {
                                ...settings.methods,
                                [spec.key]: {
                                  ...cfg,
                                  enabled: e.target.checked,
                                },
                              },
                            })
                          }
                        />
                        {cfg.enabled ? "Enabled" : "Disabled"}
                      </label>
                    </div>
                  </div>

                  <textarea
                    rows={2}
                    value={cfg.details}
                    onChange={(e) =>
                      onUpdateSettings({
                        ...settings,
                        methods: {
                          ...settings.methods,
                          [spec.key]: {
                            ...cfg,
                            details: e.target.value,
                          },
                        },
                      })
                    }
                    style={{
                      width: "100%",
                      padding: "8px 10px",
                      borderRadius: "8px",
                      background: "#1E293B",
                      border: "1px solid rgba(148, 163, 184, 0.28)",
                      color: "#F8FAFC",
                      fontSize: "11px",
                      fontFamily: "monospace",
                    }}
                  />
                </div>
              );
            })}
          </div>
        </section>
      )}
    </div>
  );
}
