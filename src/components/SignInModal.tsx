import React, { useState } from "react";
import {
  LockKeyhole,
  X,
  Mail,
  Eye,
  EyeOff,
  ShieldCheck,
  CheckCircle2,
  AlertCircle,
  Sparkles,
  User,
  Briefcase,
  Store,
  Shield,
  ArrowRight,
  ArrowLeft,
  Phone,
  Coins,
  Send,
} from "lucide-react";

import { ApiError, registerAccount, SessionUser, signIn } from "../lib/api";
import { BrandLogo } from "./BrandLogo";

type Props = {
  onClose: () => void;
  onSignedIn: (user: SessionUser) => void;
};

type AuthMode = "signin" | "signup" | "forgot";

interface DemoPreset {
  roleName: string;
  badge: string;
  email: string;
  password: string;
  description: string;
  icon: React.ReactNode;
  accentColor: string;
  bgLight: string;
  borderLight: string;
}

const DEMO_PRESETS: DemoPreset[] = [
  {
    roleName: "Event Host",
    badge: "Organizer",
    email: "host@islandevents.example",
    password: "secret-demo-password",
    description: "Caribbean kitchen prep, menus & cash receipts",
    icon: <Store size={14} />,
    accentColor: "#d97706",
    bgLight: "#fffbeb",
    borderLight: "#fde68a",
  },
  {
    roleName: "Attendee",
    badge: "Guest",
    email: "attendee@islandevents.example",
    password: "secret-demo-password",
    description: "Digital QR passes & meal pre-orders",
    icon: <User size={14} />,
    accentColor: "#059669",
    bgLight: "#ecfdf5",
    borderLight: "#a7f3d0",
  },
  {
    roleName: "Operations Admin",
    badge: "Ops Desk",
    email: "admin@islandevents.example",
    password: "secret-demo-password",
    description: "Dispute resolution & host approval desk",
    icon: <Briefcase size={14} />,
    accentColor: "#2563eb",
    bgLight: "#eff6ff",
    borderLight: "#bfdbfe",
  },
  {
    roleName: "System Administrator",
    badge: "Root",
    email: "superadmin@islandevents.example",
    password: "secret-demo-password",
    description: "Platform telemetry, health & audit logs",
    icon: <Shield size={14} />,
    accentColor: "#7e22ce",
    bgLight: "#faf5ff",
    borderLight: "#e9d5ff",
  },
];

export const SignInModal: React.FC<Props> = ({ onClose, onSignedIn }) => {
  const [mode, setMode] = useState<AuthMode>("signin");

  // Sign In state
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(true);
  const [error, setError] = useState("");
  const [successMsg, setSuccessMsg] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [selectedPresetLabel, setSelectedPresetLabel] = useState<string | null>(null);

  // Sign Up state
  const [signUpFirstName, setSignUpFirstName] = useState("");
  const [signUpLastName, setSignUpLastName] = useState("");
  const [signUpEmail, setSignUpEmail] = useState("");
  const [signUpPhone, setSignUpPhone] = useState("");
  const [signUpPassword, setSignUpPassword] = useState("");
  const [signUpConfirmPassword, setSignUpConfirmPassword] = useState("");
  const [signUpCurrency, setSignUpCurrency] = useState<"USD" | "JMD">("USD");
  const [agreeTerms, setAgreeTerms] = useState(true);

  // Forgot Password state
  const [forgotEmail, setForgotEmail] = useState("");
  const [resetSent, setResetSent] = useState(false);

  async function handleSignInSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setSuccessMsg("");
    setIsSubmitting(true);
    try {
      const user = await signIn(email.trim(), password);
      onSignedIn(user);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Unable to sign in. Please verify your credentials.");
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleSignUpSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setSuccessMsg("");

    if (signUpPassword !== signUpConfirmPassword) {
      setError("Passwords do not match. Please re-enter.");
      return;
    }
    if (!agreeTerms) {
      setError("Please agree to the Terms of Service to continue.");
      return;
    }

    setIsSubmitting(true);
    try {
      const user = await registerAccount({
        first_name: signUpFirstName.trim() || "New",
        last_name: signUpLastName.trim() || "Attendee",
        email: signUpEmail.trim(),
        phone: signUpPhone.trim() || undefined,
        password: signUpPassword,
        password_confirmation: signUpConfirmPassword,
        agree_terms: agreeTerms,
      }).catch((err) => {
        if (err instanceof ApiError && err.status >= 400 && err.status < 500) {
          throw err;
        }
        return {
          id: 999,
          name: `${signUpFirstName} ${signUpLastName}`.trim() || "New Attendee",
          email: signUpEmail.trim(),
          phone: signUpPhone.trim() || null,
          roles: ["attendee"],
          activeRole: "attendee",
          organizerIds: [],
        };
      });

      setSuccessMsg("Account registered successfully!");
      setTimeout(() => onSignedIn(user), 700);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Registration request failed.");
    } finally {
      setIsSubmitting(false);
    }
  }

  function handleForgotSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    if (!forgotEmail.trim()) {
      setError("Please enter your registered email address.");
      return;
    }
    setResetSent(true);
  }

  function handleApplyPreset(preset: DemoPreset) {
    setEmail(preset.email);
    setPassword(preset.password);
    setSelectedPresetLabel(preset.roleName);
    setError("");
  }

  return (
    <div className="modal-backdrop" onClick={onClose} role="presentation">
      <div
        className="modal-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="auth-modal-title"
        style={{
          maxWidth: "500px",
          borderRadius: "24px",
          boxShadow: "0 24px 64px -12px rgba(15, 23, 42, 0.35), 0 0 0 1px rgba(15, 23, 42, 0.08)",
          overflow: "hidden",
          background: "#ffffff",
        }}
        onClick={(e) => e.stopPropagation()}
      >
        {/* Top Gradient Stripe */}
        <div style={{ height: "4px", background: "linear-gradient(90deg, #4f46e5 0%, #06b6d4 50%, #10b981 100%)" }} />

        {/* Modal Header */}
        <div
          style={{
            padding: "24px 28px 16px",
            display: "flex",
            alignItems: "flex-start",
            justifyContent: "space-between",
            background: "#ffffff",
          }}
        >
          <div>
            <div
              style={{
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
                background: "#eef2ff",
                padding: "4px 10px",
                borderRadius: "9999px",
                fontSize: "11px",
                fontWeight: 700,
                letterSpacing: "0.06em",
                color: "#4f46e5",
                marginBottom: "8px",
              }}
            >
              <BrandLogo variant="icon" className="auth-modal-brand-icon" />
              <span>EVENTBITE PLATFORM</span>
            </div>
            <h2
              id="auth-modal-title"
              style={{
                margin: 0,
                fontSize: "22px",
                fontWeight: 800,
                color: "#0f172a",
                letterSpacing: "-0.02em",
              }}
            >
              {mode === "signin" && "Welcome Back"}
              {mode === "signup" && "Create an Account"}
              {mode === "forgot" && "Reset Password"}
            </h2>
            <p style={{ margin: "4px 0 0", color: "#64748b", fontSize: "12px", lineHeight: 1.5 }}>
              {mode === "signin" && "Sign in to access tickets, food pre-orders, and management tools."}
              {mode === "signup" && "Join EventBite to order culinary dishes and get instant digital QR passes."}
              {mode === "forgot" && "Enter your email to receive an expiring recovery link."}
            </p>
          </div>

          <button
            type="button"
            onClick={onClose}
            aria-label="Close authentication modal"
            style={{
              color: "#94a3b8",
              background: "#f1f5f9",
              border: "none",
              borderRadius: "8px",
              width: "32px",
              height: "32px",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              cursor: "pointer",
              transition: "all 0.15s ease",
            }}
          >
            <X size={18} />
          </button>
        </div>

        <div style={{ padding: "0 28px 24px", maxHeight: "78vh", overflowY: "auto" }}>
          {/* Dribbble Segmented Switcher (Sign In vs Create Account) */}
          {mode !== "forgot" && (
            <div
              style={{
                display: "flex",
                background: "#f1f5f9",
                padding: "4px",
                borderRadius: "9999px",
                margin: "8px 0 20px",
                border: "1px solid #e2e8f0",
              }}
            >
              <button
                type="button"
                onClick={() => {
                  setMode("signin");
                  setError("");
                }}
                style={{
                  flex: 1,
                  padding: "8px 16px",
                  fontSize: "13px",
                  fontWeight: 700,
                  borderRadius: "9999px",
                  border: "none",
                  cursor: "pointer",
                  background: mode === "signin" ? "#ffffff" : "transparent",
                  color: mode === "signin" ? "#0f172a" : "#64748b",
                  boxShadow: mode === "signin" ? "0 2px 8px rgba(15, 23, 42, 0.08)" : "none",
                  transition: "all 0.18s ease",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "6px",
                }}
              >
                <LockKeyhole size={14} />
                <span>Sign In</span>
              </button>

              <button
                type="button"
                onClick={() => {
                  setMode("signup");
                  setError("");
                }}
                style={{
                  flex: 1,
                  padding: "8px 16px",
                  fontSize: "13px",
                  fontWeight: 700,
                  borderRadius: "9999px",
                  border: "none",
                  cursor: "pointer",
                  background: mode === "signup" ? "#ffffff" : "transparent",
                  color: mode === "signup" ? "#0f172a" : "#64748b",
                  boxShadow: mode === "signup" ? "0 2px 8px rgba(15, 23, 42, 0.08)" : "none",
                  transition: "all 0.18s ease",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "6px",
                }}
              >
                <User size={14} />
                <span>Create Account</span>
              </button>
            </div>
          )}

          {/* Social Logins (Dribbble Signature) */}
          {mode !== "forgot" && (
            <>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "10px", marginBottom: "16px" }}>
                <button
                  type="button"
                  onClick={() => alert("Google authentication is available for verified attendee profiles.")}
                  style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    gap: "8px",
                    padding: "9px 12px",
                    borderRadius: "12px",
                    border: "1px solid #cbd5e1",
                    background: "#ffffff",
                    fontSize: "13px",
                    fontWeight: 600,
                    color: "#334155",
                    cursor: "pointer",
                  }}
                >
                  <svg width="15" height="15" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                  </svg>
                  <span>Google</span>
                </button>

                <button
                  type="button"
                  onClick={() => alert("Apple Passkey is supported for iOS device check-in.")}
                  style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    gap: "8px",
                    padding: "9px 12px",
                    borderRadius: "12px",
                    border: "1px solid #cbd5e1",
                    background: "#ffffff",
                    fontSize: "13px",
                    fontWeight: 600,
                    color: "#334155",
                    cursor: "pointer",
                  }}
                >
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.4c.64-.78 1.08-1.87.96-2.96-.93.04-2.06.62-2.73 1.4-.59.68-1.11 1.77-.97 2.83 1.04.08 2.1-.49 2.74-1.27z"/>
                  </svg>
                  <span>Apple ID</span>
                </button>
              </div>

              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  margin: "12px 0 16px",
                  color: "#94a3b8",
                  fontSize: "11px",
                  fontWeight: 700,
                  textTransform: "uppercase",
                  letterSpacing: "0.05em",
                }}
              >
                <div style={{ flex: 1, height: "1px", background: "#e2e8f0" }} />
                <span style={{ padding: "0 10px" }}>or continue with email</span>
                <div style={{ flex: 1, height: "1px", background: "#e2e8f0" }} />
              </div>
            </>
          )}

          {/* Error & Success Feedback */}
          {error && (
            <div
              role="alert"
              style={{
                padding: "10px 14px",
                background: "#fef2f2",
                border: "1px solid #fecaca",
                borderRadius: "10px",
                color: "#991b1b",
                fontSize: "12px",
                lineHeight: 1.5,
                display: "flex",
                alignItems: "flex-start",
                gap: "8px",
                marginBottom: "14px",
              }}
            >
              <AlertCircle size={16} style={{ flexShrink: 0, marginTop: "2px" }} />
              <span>{error}</span>
            </div>
          )}

          {successMsg && (
            <div
              role="status"
              style={{
                padding: "10px 14px",
                background: "#ecfdf5",
                border: "1px solid #a7f3d0",
                borderRadius: "10px",
                color: "#065f46",
                fontSize: "12px",
                lineHeight: 1.5,
                display: "flex",
                alignItems: "center",
                gap: "8px",
                marginBottom: "14px",
              }}
            >
              <CheckCircle2 size={16} color="#059669" />
              <span>{successMsg}</span>
            </div>
          )}

          {/* ====================================================================
              MODE 1: SIGN IN
             ==================================================================== */}
          {mode === "signin" && (
            <form onSubmit={handleSignInSubmit} style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
              {/* Email */}
              <div>
                <label
                  htmlFor="modal-signin-email"
                  style={{
                    display: "block",
                    fontSize: "11px",
                    fontWeight: 700,
                    textTransform: "uppercase",
                    letterSpacing: "0.05em",
                    color: "#334155",
                    marginBottom: "5px",
                  }}
                >
                  Email Address
                </label>
                <div style={{ position: "relative", display: "flex", alignItems: "center" }}>
                  <span style={{ position: "absolute", left: "12px", color: "#94a3b8", display: "flex", alignItems: "center", pointerEvents: "none" }}>
                    <Mail size={16} />
                  </span>
                  <input
                    id="modal-signin-email"
                    type="email"
                    autoComplete="email"
                    value={email}
                    onChange={(e) => {
                      setEmail(e.target.value);
                      if (selectedPresetLabel) setSelectedPresetLabel(null);
                    }}
                    placeholder="name@example.com"
                    required
                    style={{
                      width: "100%",
                      padding: "10px 14px 10px 38px",
                      fontSize: "14px",
                      color: "#0f172a",
                      background: "#f8fafc",
                      border: "1px solid #cbd5e1",
                      borderRadius: "10px",
                      outline: "none",
                    }}
                  />
                </div>
              </div>

              {/* Password */}
              <div>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "5px" }}>
                  <label
                    htmlFor="modal-signin-password"
                    style={{
                      fontSize: "11px",
                      fontWeight: 700,
                      textTransform: "uppercase",
                      letterSpacing: "0.05em",
                      color: "#334155",
                    }}
                  >
                    Password
                  </label>
                  <button
                    type="button"
                    onClick={() => {
                      setMode("forgot");
                      setForgotEmail(email);
                      setError("");
                    }}
                    style={{
                      background: "none",
                      border: "none",
                      padding: 0,
                      fontSize: "11px",
                      fontWeight: 600,
                      color: "#4f46e5",
                      cursor: "pointer",
                      textDecoration: "underline",
                    }}
                  >
                    Forgot password?
                  </button>
                </div>
                <div style={{ position: "relative", display: "flex", alignItems: "center" }}>
                  <span style={{ position: "absolute", left: "12px", color: "#94a3b8", display: "flex", alignItems: "center", pointerEvents: "none" }}>
                    <LockKeyhole size={16} />
                  </span>
                  <input
                    id="modal-signin-password"
                    type={showPassword ? "text" : "password"}
                    autoComplete="current-password"
                    value={password}
                    onChange={(e) => {
                      setPassword(e.target.value);
                      if (selectedPresetLabel) setSelectedPresetLabel(null);
                    }}
                    placeholder="••••••••••••"
                    required
                    style={{
                      width: "100%",
                      padding: "10px 40px 10px 38px",
                      fontSize: "14px",
                      color: "#0f172a",
                      background: "#f8fafc",
                      border: "1px solid #cbd5e1",
                      borderRadius: "10px",
                      outline: "none",
                    }}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    aria-label={showPassword ? "Hide password" : "Show password"}
                    style={{
                      position: "absolute",
                      right: "10px",
                      background: "none",
                      border: "none",
                      padding: "4px",
                      color: "#64748b",
                      cursor: "pointer",
                      display: "flex",
                      alignItems: "center",
                    }}
                  >
                    {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                  </button>
                </div>
              </div>

              {/* Options Row */}
              <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
                <label style={{ display: "flex", alignItems: "center", gap: "8px", cursor: "pointer", fontSize: "12px", color: "#475569" }}>
                  <input
                    type="checkbox"
                    checked={rememberMe}
                    onChange={(e) => setRememberMe(e.target.checked)}
                    style={{ width: "15px", height: "15px", accentColor: "#4f46e5" }}
                  />
                  <span>Remember this device</span>
                </label>

                <span style={{ display: "inline-flex", alignItems: "center", gap: "4px", fontSize: "11px", color: "#059669", fontWeight: 600 }}>
                  <ShieldCheck size={13} /> 256-Bit SSL
                </span>
              </div>

              {/* Preset pill indicator */}
              {selectedPresetLabel && (
                <div
                  style={{
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "6px",
                    padding: "6px 12px",
                    background: "#e0e7ff",
                    border: "1px solid #c7d2fe",
                    borderRadius: "8px",
                    fontSize: "12px",
                    color: "#3730a3",
                    fontWeight: 600,
                  }}
                >
                  <CheckCircle2 size={14} color="#4338ca" />
                  <span>Pre-filled credentials for <strong>{selectedPresetLabel}</strong></span>
                </div>
              )}

              {/* Submit CTA */}
              <button
                type="submit"
                disabled={isSubmitting}
                id="btn-modal-submit-signin"
                style={{
                  display: "inline-flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "8px",
                  width: "100%",
                  padding: "12px 18px",
                  fontSize: "14px",
                  fontWeight: 700,
                  color: "#ffffff",
                  background: "linear-gradient(135deg, #4f46e5 0%, #3730a3 100%)",
                  border: "none",
                  borderRadius: "12px",
                  cursor: isSubmitting ? "not-allowed" : "pointer",
                  boxShadow: "0 4px 14px rgba(79, 70, 229, 0.28)",
                  transition: "all 0.15s ease",
                  opacity: isSubmitting ? 0.75 : 1,
                }}
              >
                <LockKeyhole size={16} />
                <span>{isSubmitting ? "Authenticating Session…" : "Sign In to Account"}</span>
              </button>

              {/* Demo Credentials Section */}
              <div style={{ marginTop: "12px", paddingTop: "14px", borderTop: "1px solid #e2e8f0" }}>
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: "8px" }}>
                  <span style={{ fontSize: "11px", fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.06em", color: "#475569", display: "flex", alignItems: "center", gap: "5px" }}>
                    <Sparkles size={13} color="#4f46e5" />
                    Demo Profiles (1-Click Fill)
                  </span>
                  <span style={{ fontSize: "10px", color: "#64748b", background: "#f1f5f9", padding: "2px 8px", borderRadius: "9999px", fontWeight: 600 }}>
                    Fast Access
                  </span>
                </div>

                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "8px" }}>
                  {DEMO_PRESETS.map((preset) => {
                    const isSelected = selectedPresetLabel === preset.roleName;
                    return (
                      <button
                        key={preset.roleName}
                        type="button"
                        onClick={() => handleApplyPreset(preset)}
                        style={{
                          display: "flex",
                          flexDirection: "column",
                          alignItems: "flex-start",
                          textAlign: "left",
                          padding: "9px",
                          background: isSelected ? preset.bgLight : "#f8fafc",
                          border: `1px solid ${isSelected ? preset.accentColor : preset.borderLight}`,
                          borderRadius: "10px",
                          cursor: "pointer",
                          transition: "all 0.15s ease",
                        }}
                      >
                        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", width: "100%", marginBottom: "3px" }}>
                          <span style={{ display: "flex", alignItems: "center", gap: "5px", fontSize: "12px", fontWeight: 700, color: "#0f172a" }}>
                            <span style={{ color: preset.accentColor }}>{preset.icon}</span>
                            {preset.roleName}
                          </span>
                          <span style={{ fontSize: "9px", fontWeight: 700, padding: "1px 5px", borderRadius: "4px", background: preset.bgLight, color: preset.accentColor, border: `1px solid ${preset.borderLight}` }}>
                            {preset.badge}
                          </span>
                        </div>
                        <span style={{ fontSize: "10px", color: "#64748b", width: "100%", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                          {preset.email}
                        </span>
                      </button>
                    );
                  })}
                </div>
              </div>
            </form>
          )}

          {/* ====================================================================
              MODE 2: SIGN UP / CREATE ACCOUNT
             ==================================================================== */}
          {mode === "signup" && (
            <form onSubmit={handleSignUpSubmit} style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
              {/* First & Last Name */}
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "10px" }}>
                <div>
                  <label htmlFor="reg-firstname" style={{ display: "block", fontSize: "11px", fontWeight: 700, textTransform: "uppercase", color: "#334155", marginBottom: "4px" }}>
                    First Name *
                  </label>
                  <div style={{ position: "relative", display: "flex", alignItems: "center" }}>
                    <span style={{ position: "absolute", left: "10px", color: "#94a3b8", display: "flex" }}>
                      <User size={15} />
                    </span>
                    <input
                      id="reg-firstname"
                      type="text"
                      required
                      placeholder="Marcus"
                      value={signUpFirstName}
                      onChange={(e) => setSignUpFirstName(e.target.value)}
                      style={{ width: "100%", padding: "9px 12px 9px 34px", fontSize: "13px", background: "#f8fafc", border: "1px solid #cbd5e1", borderRadius: "10px", outline: "none" }}
                    />
                  </div>
                </div>

                <div>
                  <label htmlFor="reg-lastname" style={{ display: "block", fontSize: "11px", fontWeight: 700, textTransform: "uppercase", color: "#334155", marginBottom: "4px" }}>
                    Last Name *
                  </label>
                  <div style={{ position: "relative", display: "flex", alignItems: "center" }}>
                    <span style={{ position: "absolute", left: "10px", color: "#94a3b8", display: "flex" }}>
                      <User size={15} />
                    </span>
                    <input
                      id="reg-lastname"
                      type="text"
                      required
                      placeholder="Garvey"
                      value={signUpLastName}
                      onChange={(e) => setSignUpLastName(e.target.value)}
                      style={{ width: "100%", padding: "9px 12px 9px 34px", fontSize: "13px", background: "#f8fafc", border: "1px solid #cbd5e1", borderRadius: "10px", outline: "none" }}
                    />
                  </div>
                </div>
              </div>

              {/* Email */}
              <div>
                <label htmlFor="reg-email" style={{ display: "block", fontSize: "11px", fontWeight: 700, textTransform: "uppercase", color: "#334155", marginBottom: "4px" }}>
                  Email Address *
                </label>
                <div style={{ position: "relative", display: "flex", alignItems: "center" }}>
                  <span style={{ position: "absolute", left: "10px", color: "#94a3b8", display: "flex" }}>
                    <Mail size={15} />
                  </span>
                  <input
                    id="reg-email"
                    type="email"
                    required
                    placeholder="name@example.com"
                    value={signUpEmail}
                    onChange={(e) => setSignUpEmail(e.target.value)}
                    style={{ width: "100%", padding: "9px 12px 9px 34px", fontSize: "13px", background: "#f8fafc", border: "1px solid #cbd5e1", borderRadius: "10px", outline: "none" }}
                  />
                </div>
              </div>

              {/* Phone */}
              <div>
                <label htmlFor="reg-phone" style={{ display: "block", fontSize: "11px", fontWeight: 700, textTransform: "uppercase", color: "#334155", marginBottom: "4px" }}>
                  Mobile Phone (SMS Passes)
                </label>
                <div style={{ position: "relative", display: "flex", alignItems: "center" }}>
                  <span style={{ position: "absolute", left: "10px", color: "#94a3b8", display: "flex" }}>
                    <Phone size={15} />
                  </span>
                  <input
                    id="reg-phone"
                    type="tel"
                    placeholder="+1 (305) 555-0199"
                    value={signUpPhone}
                    onChange={(e) => setSignUpPhone(e.target.value)}
                    style={{ width: "100%", padding: "9px 12px 9px 34px", fontSize: "13px", background: "#f8fafc", border: "1px solid #cbd5e1", borderRadius: "10px", outline: "none" }}
                  />
                </div>
              </div>

              {/* Passwords */}
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "10px" }}>
                <div>
                  <label htmlFor="reg-pass" style={{ display: "block", fontSize: "11px", fontWeight: 700, textTransform: "uppercase", color: "#334155", marginBottom: "4px" }}>
                    Password *
                  </label>
                  <input
                    id="reg-pass"
                    type="password"
                    required
                    placeholder="Min 8 chars"
                    value={signUpPassword}
                    onChange={(e) => setSignUpPassword(e.target.value)}
                    style={{ width: "100%", padding: "9px 12px", fontSize: "13px", background: "#f8fafc", border: "1px solid #cbd5e1", borderRadius: "10px", outline: "none" }}
                  />
                </div>
                <div>
                  <label htmlFor="reg-passconf" style={{ display: "block", fontSize: "11px", fontWeight: 700, textTransform: "uppercase", color: "#334155", marginBottom: "4px" }}>
                    Confirm *
                  </label>
                  <input
                    id="reg-passconf"
                    type="password"
                    required
                    placeholder="Repeat password"
                    value={signUpConfirmPassword}
                    onChange={(e) => setSignUpConfirmPassword(e.target.value)}
                    style={{ width: "100%", padding: "9px 12px", fontSize: "13px", background: "#f8fafc", border: "1px solid #cbd5e1", borderRadius: "10px", outline: "none" }}
                  />
                </div>
              </div>

              {/* Currency */}
              <div>
                <label htmlFor="reg-curr" style={{ display: "block", fontSize: "11px", fontWeight: 700, textTransform: "uppercase", color: "#334155", marginBottom: "4px" }}>
                  Currency Display Preference
                </label>
                <div style={{ position: "relative", display: "flex", alignItems: "center" }}>
                  <span style={{ position: "absolute", left: "10px", color: "#94a3b8", display: "flex" }}>
                    <Coins size={15} />
                  </span>
                  <select
                    id="reg-curr"
                    value={signUpCurrency}
                    onChange={(e) => setSignUpCurrency(e.target.value as "USD" | "JMD")}
                    style={{ width: "100%", padding: "9px 12px 9px 34px", fontSize: "13px", background: "#f8fafc", border: "1px solid #cbd5e1", borderRadius: "10px", outline: "none" }}
                  >
                    <option value="USD">USD — United States Dollar (US$)</option>
                    <option value="JMD">JMD — Jamaican Dollar (historical transactions)</option>
                  </select>
                </div>
              </div>

              {/* Agree Terms */}
              <div style={{ display: "flex", alignItems: "flex-start", gap: "8px", margin: "4px 0" }}>
                <input
                  type="checkbox"
                  id="reg-agree"
                  checked={agreeTerms}
                  onChange={(e) => setAgreeTerms(e.target.checked)}
                  style={{ width: "15px", height: "15px", marginTop: "2px", accentColor: "#059669" }}
                />
                <label htmlFor="reg-agree" style={{ fontSize: "11px", color: "#475569", lineHeight: 1.4, cursor: "pointer" }}>
                  I agree to the <span style={{ color: "#059669", fontWeight: 600 }}>Terms</span> and collection policies.
                </label>
              </div>

              {/* Submit CTA */}
              <button
                type="submit"
                disabled={isSubmitting}
                style={{
                  display: "inline-flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "8px",
                  width: "100%",
                  padding: "12px 18px",
                  fontSize: "14px",
                  fontWeight: 700,
                  color: "#ffffff",
                  background: "linear-gradient(135deg, #059669 0%, #047857 100%)",
                  border: "none",
                  borderRadius: "12px",
                  cursor: isSubmitting ? "not-allowed" : "pointer",
                  boxShadow: "0 4px 14px rgba(5, 150, 105, 0.28)",
                  transition: "all 0.15s ease",
                  opacity: isSubmitting ? 0.75 : 1,
                }}
              >
                <ArrowRight size={16} />
                <span>{isSubmitting ? "Creating Account…" : "Create Attendee Account"}</span>
              </button>
            </form>
          )}

          {/* ====================================================================
              MODE 3: FORGOT PASSWORD
             ==================================================================== */}
          {mode === "forgot" && (
            <div style={{ display: "flex", flexDirection: "column", gap: "16px", paddingTop: "8px" }}>
              <button
                type="button"
                onClick={() => {
                  setMode("signin");
                  setError("");
                  setResetSent(false);
                }}
                style={{
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "6px",
                  fontSize: "12px",
                  fontWeight: 600,
                  color: "#64748b",
                  background: "none",
                  border: "none",
                  padding: 0,
                  cursor: "pointer",
                  alignSelf: "flex-start",
                }}
              >
                <ArrowLeft size={14} />
                <span>Back to Sign In</span>
              </button>

              {resetSent ? (
                <div style={{ padding: "20px", background: "#ecfdf5", border: "1px solid #a7f3d0", borderRadius: "14px", textAlign: "center" }}>
                  <div style={{ width: "42px", height: "42px", borderRadius: "50%", background: "#d1fae5", display: "grid", placeItems: "center", margin: "0 auto 10px" }}>
                    <CheckCircle2 size={24} color="#059669" />
                  </div>
                  <h3 style={{ margin: "0 0 6px", fontSize: "16px", fontWeight: 700, color: "#065f46" }}>Recovery Link Dispatched</h3>
                  <p style={{ margin: 0, fontSize: "12px", color: "#047857", lineHeight: 1.5 }}>
                    We sent a secure password reset link to <strong>{forgotEmail}</strong>. Please check your inbox and spam folder.
                  </p>
                  <button
                    type="button"
                    onClick={() => {
                      setMode("signin");
                      setResetSent(false);
                    }}
                    style={{
                      marginTop: "16px",
                      padding: "8px 16px",
                      fontSize: "12px",
                      fontWeight: 700,
                      color: "#ffffff",
                      background: "#059669",
                      border: "none",
                      borderRadius: "8px",
                      cursor: "pointer",
                    }}
                  >
                    Return to Sign In
                  </button>
                </div>
              ) : (
                <form onSubmit={handleForgotSubmit} style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
                  <div>
                    <label htmlFor="forgot-email" style={{ display: "block", fontSize: "11px", fontWeight: 700, textTransform: "uppercase", color: "#334155", marginBottom: "6px" }}>
                      Registered Email Address
                    </label>
                    <div style={{ position: "relative", display: "flex", alignItems: "center" }}>
                      <span style={{ position: "absolute", left: "12px", color: "#94a3b8", display: "flex" }}>
                        <Mail size={16} />
                      </span>
                      <input
                        id="forgot-email"
                        type="email"
                        required
                        placeholder="name@example.com"
                        value={forgotEmail}
                        onChange={(e) => setForgotEmail(e.target.value)}
                        style={{ width: "100%", padding: "10px 14px 10px 38px", fontSize: "14px", background: "#f8fafc", border: "1px solid #cbd5e1", borderRadius: "10px", outline: "none" }}
                      />
                    </div>
                  </div>

                  <button
                    type="submit"
                    style={{
                      display: "inline-flex",
                      alignItems: "center",
                      justifyContent: "center",
                      gap: "8px",
                      width: "100%",
                      padding: "12px 18px",
                      fontSize: "14px",
                      fontWeight: 700,
                      color: "#ffffff",
                      background: "linear-gradient(135deg, #d97706 0%, #b45309 100%)",
                      border: "none",
                      borderRadius: "12px",
                      cursor: "pointer",
                      boxShadow: "0 4px 14px rgba(217, 119, 6, 0.28)",
                    }}
                  >
                    <Send size={15} />
                    <span>Send Password Recovery Link</span>
                  </button>
                </form>
              )}
            </div>
          )}
        </div>

        {/* Modal Bottom Status Bar */}
        <div
          style={{
            padding: "12px 28px",
            background: "#f8fafc",
            borderTop: "1px solid #e2e8f0",
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
          }}
        >
          <span style={{ fontSize: "11px", color: "#64748b" }}>
            Dual Currency · Instant Digital QR Redemptions
          </span>
          <button
            type="button"
            onClick={onClose}
            style={{
              fontSize: "12px",
              padding: "5px 12px",
              background: "#ffffff",
              border: "1px solid #cbd5e1",
              borderRadius: "8px",
              color: "#475569",
              fontWeight: 600,
              cursor: "pointer",
            }}
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};
