import React, { useState } from "react";
import { LockKeyhole, X } from "lucide-react";

import { SessionUser, signIn } from "../lib/api";

type Props = {
  onClose: () => void;
  onSignedIn: (user: SessionUser) => void;
};

export const SignInModal: React.FC<Props> = ({ onClose, onSignedIn }) => {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setIsSubmitting(true);
    try {
      onSignedIn(await signIn(email.trim(), password));
    } catch (err) {
      setError(err instanceof Error ? err.message : "Unable to sign in.");
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div
        className="modal-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="sign-in-title"
        style={{ maxWidth: "420px" }}
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-header">
          <div>
            <span className="section-kicker">ACCOUNT</span>
            <h2 id="sign-in-title">Sign in to Island Events</h2>
            <p>Use your Island Events account to place orders and open your dashboards.</p>
          </div>
          <button type="button" className="modal-close-btn" onClick={onClose} aria-label="Close sign in">
            <X size={18} />
          </button>
        </div>

        <form onSubmit={handleSubmit} style={{ display: "flex", flexDirection: "column", gap: "12px", padding: "4px 0" }}>
          <label style={{ display: "flex", flexDirection: "column", gap: "4px", fontSize: "13px", fontWeight: 600 }}>
            Email
            <input type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
          </label>
          <label style={{ display: "flex", flexDirection: "column", gap: "4px", fontSize: "13px", fontWeight: 600 }}>
            Password
            <input
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </label>

          {error && (
            <div role="alert" style={{ color: "#b91c1c", fontSize: "13px" }}>
              {error}
            </div>
          )}

          <button type="submit" className="primary-button" disabled={isSubmitting} style={{ justifyContent: "center" }}>
            <LockKeyhole size={15} /> {isSubmitting ? "Signing in…" : "Sign in"}
          </button>
        </form>
      </div>
    </div>
  );
};
