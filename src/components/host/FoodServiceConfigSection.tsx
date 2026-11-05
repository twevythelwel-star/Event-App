import React, { useMemo } from "react";
import { AlertCircle, Clock, Package, ShieldAlert, Utensils } from "lucide-react";
import { Currency, FoodServiceConfig, FoodServiceMode, PickupWindow } from "../../types";
import { formatCurrencyAmount } from "../../tests/testUtils";

type Props = {
  config: FoodServiceConfig;
  currency: Currency;
  allowOvernight?: boolean;
  disabled?: boolean;
  onChange: (next: FoodServiceConfig) => void;
};

const labelStyle: React.CSSProperties = {
  display: "block",
  fontSize: "11px",
  fontWeight: 700,
  color: "#CBD5E1",
  marginBottom: "5px",
};

const inputStyle: React.CSSProperties = {
  width: "100%",
  height: "40px",
  padding: "0 12px",
  borderRadius: "10px",
  background: "#0B1120",
  border: "1px solid rgba(148, 163, 184, 0.28)",
  color: "#F8FAFC",
  fontSize: "12px",
  outline: "none",
};

const fieldErrorText = (err?: string) =>
  err ? (
    <span
      role="alert"
      style={{
        display: "flex",
        alignItems: "center",
        gap: "4px",
        marginTop: "4px",
        fontSize: "11px",
        color: "#F87171",
        fontWeight: 600,
      }}
    >
      <AlertCircle size={12} />
      {err}
    </span>
  ) : null;

export function FoodServiceConfigSection({
  config,
  currency,
  allowOvernight = false,
  disabled = false,
  onChange,
}: Props) {
  const pickupWindows = config.pickupWindows || [];
  const setField = <K extends keyof FoodServiceConfig>(
    key: K,
    value: FoodServiceConfig[K]
  ) => {
    onChange({ ...config, [key]: value });
  };

  const validation = useMemo(() => {
    const errs: Record<string, string> = {};
    if (config.serviceMode !== "disabled") {
      if (!config.foodServiceStartTime) errs.foodServiceStartTime = "Food service start time is required.";
      if (!config.foodServiceEndTime) errs.foodServiceEndTime = "Food service end time is required.";
      if (
        config.foodServiceStartTime &&
        config.foodServiceEndTime &&
        config.foodServiceEndTime <= config.foodServiceStartTime &&
        !allowOvernight
      ) {
        errs.foodServiceEndTime = "End time must be after the start time.";
      }
      if (
        ["preorder", "both"].includes(config.serviceMode) &&
        !config.orderingDeadlineIso
      ) {
        errs.orderingDeadlineIso = "Ordering deadline is required for preorders.";
      }
      if (
        (config.packagingFeeUSD ?? 0) < 0 ||
        (config.packagingFeeJMD ?? 0) < 0
      ) {
        errs.packagingFeeUSD = "Fees cannot be negative.";
      }
    }
    return errs;
  }, [config, allowOvernight]);

  const addPickupWindow = () => {
    const next: PickupWindow = {
      id: `pw-${Date.now()}`,
      label: "New window",
      startTime: "12:00",
      endTime: "13:00",
    };
    onChange({ ...config, pickupWindows: [...pickupWindows, next] });
  };

  const updatePickupWindow = (index: number, patch: Partial<PickupWindow>) => {
    const next = [...pickupWindows];
    next[index] = { ...next[index], ...patch };
    onChange({ ...config, pickupWindows: next });
  };

  const removePickupWindow = (id: string) => {
    const next = pickupWindows.filter((w) => w.id !== id);
    onChange({ ...config, pickupWindows: next });
  };

  const modeLabel: Record<FoodServiceMode, string> = {
    disabled: "Food ordering disabled",
    preorder: "Preorders only",
    eventDay: "Event-day purchases",
    both: "Preorders & event-day purchases",
  };

  return (
    <section
      id="wizard-food-service-config"
      aria-label="Food service configuration"
      style={{
        padding: "18px",
        borderRadius: "12px",
        background: "#0B1120",
        border: `1px solid ${
          config.enabled ? "rgba(52, 211, 153, 0.35)" : "rgba(148, 163, 184, 0.22)"
        }`,
        display: "flex",
        flexDirection: "column",
        gap: "14px",
      }}
    >
      <div
        style={{
          display: "flex",
          alignItems: "center",
          gap: "8px",
          marginBottom: "6px",
        }}
      >
        <Utensils size={14} color="#38BDF8" />
        <span
          style={{
            fontSize: "11px",
            fontWeight: 800,
            color: "#E2E8F0",
            textTransform: "uppercase",
            letterSpacing: "0.6px",
          }}
        >
          Food Service Configuration
        </span>
      </div>
      {/* Service mode toggle */}
      <div>
        <label style={labelStyle}>Food Ordering Mode *</label>
        <div style={{ display: "flex", gap: "10px", flexWrap: "wrap" }}>
          {(Object.keys(modeLabel) as FoodServiceMode[]).map((mode) => (
            <button
              key={mode}
              type="button"
              role="radio"
              aria-checked={config.serviceMode === mode}
              disabled={disabled}
              onClick={() => onChange({
                ...config,
                serviceMode: mode,
                enabled: mode !== "disabled",
              })}
              style={{
                padding: "8px 14px",
                borderRadius: "10px",
                border: `1px solid ${
                  config.serviceMode === mode
                    ? "rgba(52, 211, 153, 0.5)"
                    : "rgba(148, 163, 184, 0.25)"
                }`,
                background: config.serviceMode === mode
                  ? "rgba(52, 211, 153, 0.18)"
                  : "rgba(30, 41, 59, 0.7)",
                color:
                  config.serviceMode === mode ? "#34D399" : "#CBD5E1",
                fontSize: "11px",
                fontWeight: 700,
                cursor: disabled ? "not-allowed" : "pointer",
              }}
            >
              {modeLabel[mode]}
            </button>
          ))}
        </div>
        {fieldErrorText(validation.serviceMode)}
      </div>

      {config.serviceMode !== "disabled" ? (
        <>
          <div>
            <label style={labelStyle}>Food Fulfillment</label>
            <div style={{ display: "flex", gap: "10px", flexWrap: "wrap" }}>
              {([
                ["pickup", "Pickup only"],
                ["pickup_and_delivery", "Pickup or table / area delivery"],
              ] as const).map(([value, label]) => (
                <label key={value} style={{ ...labelStyle, display: "inline-flex", alignItems: "center", gap: "6px" }}>
                  <input
                    type="radio"
                    name="food-fulfillment-mode"
                    value={value}
                    checked={(config.fulfillmentMode || "pickup") === value}
                    disabled={disabled}
                    onChange={() => setField("fulfillmentMode", value)}
                  />
                  {label}
                </label>
              ))}
            </div>
            {config.fulfillmentMode === "pickup_and_delivery" && (
              <input
                type="text"
                aria-label="Food delivery instructions"
                value={config.deliveryInstructions || ""}
                disabled={disabled}
                onChange={(e) => setField("deliveryInstructions", e.target.value)}
                placeholder="Where can food be delivered?"
                style={inputStyle}
              />
            )}
          </div>

          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))", gap: "12px" }}>
            <div>
              <label htmlFor="fsc-start-time" style={labelStyle}>
                Food Service Start Time *
              </label>
              <input
                id="fsc-start-time"
                type="time"
                value={config.foodServiceStartTime || ""}
                disabled={disabled}
                onChange={(e) => setField("foodServiceStartTime", e.target.value)}
                style={inputStyle}
              />
              {fieldErrorText(
                validation.foodServiceStartTime
              )}
            </div>
            <div>
              <label htmlFor="fsc-end-time" style={labelStyle}>
                Food Service End Time *
              </label>
              <input
                id="fsc-end-time"
                type="time"
                value={config.foodServiceEndTime || ""}
                disabled={disabled}
                onChange={(e) => setField("foodServiceEndTime", e.target.value)}
                style={inputStyle}
              />
              {fieldErrorText(validation.foodServiceEndTime)}
            </div>
            <div>
              <label htmlFor="fsc-deadline" style={labelStyle}>
                Preorder Deadline {["preorder", "both"].includes(config.serviceMode) ? "*" : "(optional)"}
              </label>
              <input
                id="fsc-deadline"
                type="datetime-local"
                value={config.orderingDeadlineIso || ""}
                disabled={disabled}
                onChange={(e) => setField("orderingDeadlineIso", e.target.value)}
                placeholder="2026-11-20T20:00"
                style={inputStyle}
              />
              {fieldErrorText(validation.orderingDeadlineIso)}
            </div>
          </div>

          {/* Fees */}
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))", gap: "12px" }}>
            <div>
              <label htmlFor="fsc-pkg-fee" style={labelStyle}>
                Packaging Fee
              </label>
              <input
                id="fsc-pkg-fee"
                type="number"
                min={0}
                step={0.25}
                value={
                  currency === "JMD"
                    ? config.packagingFeeJMD ?? 0
                    : config.packagingFeeUSD ?? 0
                }
                disabled={disabled}
                onChange={(e) => {
                  const val = Number(e.target.value) || 0;
                  if (currency === "JMD") {
                    setField("packagingFeeJMD", val);
                  } else {
                    setField("packagingFeeUSD", val);
                  }
                }}
                style={inputStyle}
              />
              <span style={{ fontSize: "10px", color: "#94A3B8" }}>
                {formatCurrencyAmount(
                  currency === "JMD" ? config.packagingFeeJMD ?? 0 : config.packagingFeeUSD ?? 0,
                  currency
                )}
              </span>
            </div>
            <div>
              <label htmlFor="fsc-svc-fee" style={labelStyle}>
                Service Fee
              </label>
              <input
                id="fsc-svc-fee"
                type="number"
                min={0}
                step={0.25}
                value={
                  currency === "JMD"
                    ? config.serviceFeeJMD ?? 0
                    : config.serviceFeeUSD ?? 0
                }
                disabled={disabled}
                onChange={(e) => {
                  const val = Number(e.target.value) || 0;
                  if (currency === "JMD") {
                    setField("serviceFeeJMD", val);
                  } else {
                    setField("serviceFeeUSD", val);
                  }
                }}
                style={inputStyle}
              />
              <span style={{ fontSize: "10px", color: "#94A3B8" }}>
                {formatCurrencyAmount(
                  currency === "JMD" ? config.serviceFeeJMD ?? 0 : config.serviceFeeUSD ?? 0,
                  currency
                )}
              </span>
            </div>
            <div>
              <label htmlFor="fsc-max-orders" style={labelStyle}>
                Maximum Orders (event total)
              </label>
              <input
                id="fsc-max-orders"
                type="number"
                min={1}
                step={1}
                value={config.maxOrdersPerEvent ?? ""}
                disabled={disabled}
                onChange={(e) =>
                  setField("maxOrdersPerEvent", e.target.value ? Number(e.target.value) : undefined)
                }
                placeholder="e.g. 50"
                style={inputStyle}
              />
            </div>
          </div>

          {/* Pickup windows */}
          <div>
            <label style={labelStyle}>Pickup Windows</label>
            <p style={{ fontSize: "11px", color: "#94A3B8", margin: "0 0 8px" }}>
              Define when attendees may collect preordered food.
            </p>
            <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
              {pickupWindows.map((window, idx) => (
                <div
                  key={window.id}
                  style={{
                    display: "flex",
                    alignItems: "center",
                    gap: "8px",
                    padding: "8px 12px",
                    background: "rgba(30, 41, 59, 0.6)",
                    border: "1px solid rgba(148, 163, 184, 0.2)",
                    borderRadius: "10px",
                  }}
                >
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <label style={{ ...labelStyle, marginBottom: "2px", fontSize: "11px" }}>Window label</label>
                    <input
                      value={window.label}
                      disabled={disabled}
                      onChange={(e) =>
                        updatePickupWindow(idx, { label: e.target.value })
                      }
                      style={inputStyle}
                    />
                  </div>
                  <div style={{ minWidth: 110 }}>
                    <label style={{ ...labelStyle, marginBottom: "2px", fontSize: "11px" }}>Start</label>
                    <input
                      type="time"
                      value={window.startTime}
                      disabled={disabled}
                      onChange={(e) =>
                        updatePickupWindow(idx, { startTime: e.target.value })
                      }
                      style={inputStyle}
                    />
                  </div>
                  <div style={{ minWidth: 110 }}>
                    <label style={{ ...labelStyle, marginBottom: "2px", fontSize: "11px" }}>End</label>
                    <input
                      type="time"
                      value={window.endTime}
                      disabled={disabled}
                      onChange={(e) =>
                        updatePickupWindow(idx, { endTime: e.target.value })
                      }
                      style={inputStyle}
                    />
                  </div>
                  <div style={{ minWidth: 110 }}>
                    <label style={{ ...labelStyle, marginBottom: "2px", fontSize: "11px" }}>Max orders</label>
                    <input
                      type="number"
                      min={1}
                      step={1}
                      value={window.maxOrders ?? ""}
                      disabled={disabled}
                      onChange={(e) => updatePickupWindow(idx, {
                        maxOrders: e.target.value ? Number(e.target.value) : undefined,
                      })}
                      placeholder="Unlimited"
                      style={inputStyle}
                    />
                  </div>
                  <button
                    type="button"
                    disabled={disabled}
                    onClick={() => removePickupWindow(window.id)}
                    style={{
                      padding: "6px 10px",
                      borderRadius: "8px",
                      background: "rgba(239, 68, 68, 0.16)",
                      color: "#FCA5A5",
                      border: "1px solid rgba(248, 113, 113, 0.35)",
                      fontSize: "11px",
                      fontWeight: 700,
                      cursor: "pointer",
                    }}
                  >
                    Remove
                  </button>
                </div>
              ))}
              <button
                type="button"
                id="fsc-add-pickup-window"
                disabled={disabled}
                onClick={addPickupWindow}
                style={{
                  padding: "7px 14px",
                  borderRadius: "8px",
                  background: "rgba(99, 102, 241, 0.18)",
                  color: "#A5B4FC",
                  border: "1px solid rgba(129, 140, 248, 0.35)",
                  fontSize: "11px",
                  fontWeight: 700,
                  cursor: "pointer",
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "6px",
                }}
              >
                <Package size={13} />
                Add Pickup Window
              </button>
            </div>
          </div>

          <div
            style={{
              padding: "10px 12px",
              borderRadius: "8px",
              background: "rgba(56, 189, 248, 0.1)",
              border: "1px solid rgba(56, 189, 248, 0.25)",
              display: "flex",
              alignItems: "center",
              gap: "8px",
              fontSize: "11px",
              color: "#BAE6FD",
            }}
          >
            <Clock size={13} />
            <span>
              All fees and pickup windows are clearly shown to attendees before
              checkout. Historical orders keep their original prices unchanged.
            </span>
          </div>
        </>
      ) : (
        <div
          style={{
            padding: "12px",
            borderRadius: "8px",
            background: "rgba(15, 23, 42, 0.5)",
            border: "1px dashed rgba(148, 163, 184, 0.3)",
            color: "#94A3B8",
            fontSize: "12px",
          }}
        >
          <ShieldAlert size={14} />
          <span style={{ marginLeft: "6px" }}>
            Food ordering is disabled for this event. Attendees will purchase
            admission tickets only.
          </span>
        </div>
      )}
    </section>
  );
}
