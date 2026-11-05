import React, { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertCircle,
  CheckCircle2,
  Image as ImageIcon,
  Plus,
  RefreshCw,
  Trash2,
  Upload,
} from "lucide-react";
import { MenuCategory, MenuItem, PricingType } from "../../types";

type Props = {
  item: MenuItem | null;
  timezone?: string;
  disabled?: boolean;
  onSave: (item: MenuItem) => void;
  onDelete: () => void;
  onClose: () => void;
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

const textareaStyle: React.CSSProperties = {
  ...inputStyle,
  height: "auto",
  padding: "10px 12px",
  resize: "vertical",
};

const selectStyle: React.CSSProperties = {
  ...inputStyle,
  appearance: "none",
  cursor: "pointer",
};

const fieldErrorText = (err?: string | false) =>
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

const CATEGORIES: MenuCategory[] = [
  "Main Dishes",
  "Seafood",
  "Poultry",
  "Meat",
  "Vegetarian",
  "Vegan",
  "Rice and Grains",
  "Sides",
  "Desserts",
  "Drinks",
  "Snacks",
  "Sauces and Condiments",
  "Extras",
  "Community Donations",
];

function toEventLocalDateTime(value: string | undefined, timezone: string): string {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  try {
    const parts = new Intl.DateTimeFormat("en-US", {
      timeZone: timezone,
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
      hourCycle: "h23",
    }).formatToParts(date);
    const part = (type: Intl.DateTimeFormatPartTypes) =>
      parts.find((value) => value.type === type)?.value ?? "";

    return `${part("year")}-${part("month")}-${part("day")}T${part("hour")}:${part("minute")}`;
  } catch {
    return value;
  }
}

export function MenuItemBuilder({
  item,
  timezone = "America/New_York",
  disabled = false,
  onSave,
  onDelete,
}: Props) {
  const createDraft = (): MenuItem => ({
    id: `item-${Date.now()}`,
    name: "",
    category: "Main Dishes",
    description: "",
    pricingType: "fixed",
    priceUSD: 0,
    priceJMD: 0,
    imageUrl: "",
    availableStock: 0,
    isAvailable: true,
    serviceMode: "both",
  });
  const [draft, setDraft] = useState<MenuItem>(
    item ?? createDraft()
  );

  const [imagePreview, setImagePreview] = useState<string | null>(
    draft.imageUrl || null
  );
  const [imageError, setImageError] = useState<string | null>(null);
  const [showErrors, setShowErrors] = useState(false);

  const [customOptions, setCustomOptions] = useState<{
    id: string;
    title: string;
    required: boolean;
    choices: { label: string; extraPriceUSD: number; extraPriceJMD: number }[];
  }[]>(draft.customOptionGroups || []);

  useEffect(() => {
    const source = item ?? createDraft();
    const next = item
      ? {
          ...source,
          availabilityStartIso: toEventLocalDateTime(source.availabilityStartIso, timezone),
          availabilityEndIso: toEventLocalDateTime(source.availabilityEndIso, timezone),
        }
      : source;
    setDraft(next);
    setImagePreview(next.imageUrl || null);
    setImageError(null);
    setShowErrors(false);
    setCustomOptions(next.customOptionGroups || []);
  }, [item, timezone]);

  const validation = useMemo(() => {
    const errs: Record<string, string> = {};
    if (!draft.name || draft.name.trim().length < 2) {
      errs.name = "Item name must be at least 2 characters.";
    }
    if (!draft.description || draft.description.trim().length < 5) {
      errs.description = "Description must be at least 5 characters.";
    }
    if (!imagePreview && !draft.imageUrl) {
      errs.imageUrl = "A food item photograph is required.";
    }
    if (draft.priceUSD < 0 || draft.priceJMD < 0) {
      errs.priceUSD = "Prices cannot be negative.";
    }
    if (
      draft.availableStock !== undefined &&
      (!Number.isInteger(draft.availableStock) || draft.availableStock < 0)
    ) {
      errs.availableStock = "Available quantity must be a whole number.";
    }
    if (draft.maxQtyPerOrder !== undefined && draft.maxQtyPerOrder < 1) {
      errs.maxQtyPerOrder = "Max per order must be at least 1.";
    }
    return errs;
  }, [draft, imagePreview]);

  const handleImageChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0];
      if (!file) return;
      setImageError(null);
      const allowed = ["image/jpeg", "image/png", "image/webp"];
      if (!allowed.includes(file.type.toLowerCase())) {
        setImageError("Only JPG, PNG, and WebP images are supported.");
        return;
      }
      const maxSize = 5 * 1024 * 1024;
      if (file.size > maxSize) {
        setImageError("Image must be 5 MB or smaller.");
        return;
      }
      const reader = new FileReader();
      reader.onload = () => {
        if (typeof reader.result !== "string") {
          setImageError("The selected image could not be read.");
          return;
        }
        setImagePreview(reader.result);
        setDraft((prev) => ({ ...prev, imageUrl: reader.result as string }));
      };
      reader.onerror = () => setImageError("The selected image could not be read.");
      reader.readAsDataURL(file);
    },
    []
  );

  const handleRemoveImage = useCallback(() => {
    setImagePreview(null);
    setImageError(null);
    setDraft((prev) => ({ ...prev, imageUrl: "" }));
  }, []);

  const updateField = <K extends keyof MenuItem>(key: K, value: MenuItem[K]) => {
    setDraft((prev) => ({ ...prev, [key]: value }));
  };

  const addOptionGroup = () => {
    const next = {
      id: `grp-${Date.now()}`,
      title: "New Option Group",
      required: true,
      choices: [
        { label: "Option 1", extraPriceUSD: 0, extraPriceJMD: 0 },
      ],
    };
    setCustomOptions([...customOptions, next]);
    setDraft((prev) => ({
      ...prev,
      customOptionGroups: [...customOptions, next],
    }));
  };

  const updateOptionGroup = (index: number, patch: Partial<typeof nextGroupPlaceholder>) => {
    const ng = [...customOptions];
    ng[index] = { ...ng[index], ...patch };
    setCustomOptions(ng);
    setDraft((prev) => {
      const groups = [...(prev.customOptionGroups || [])];
      groups[index] = { ...groups[index], ...patch };
      return { ...prev, customOptionGroups: groups };
    });
  };

  const removeOptionGroup = (id: string) => {
    const next = customOptions.filter((g) => g.id !== id);
    setCustomOptions(next);
    setDraft((prev) => ({
      ...prev,
      customOptionGroups: next,
    }));
  };

  const addChoice = (groupIndex: number) => {
    const ng = [...customOptions];
    ng[groupIndex].choices = [
      ...ng[groupIndex].choices,
      { label: "New choice", extraPriceUSD: 0, extraPriceJMD: 0 },
    ];
    setCustomOptions(ng);
    setDraft((prev) => {
      const groups = [...(prev.customOptionGroups || [])];
      groups[groupIndex] = { ...groups[groupIndex], choices: ng[groupIndex].choices };
      return { ...prev, customOptionGroups: groups };
    });
  };

  const removeChoice = (groupIndex: number, choiceIndex: number) => {
    const ng = [...customOptions];
    ng[groupIndex].choices = ng[groupIndex].choices.filter((_, i) => i !== choiceIndex);
    setCustomOptions(ng);
    setDraft((prev) => {
      const groups = [...(prev.customOptionGroups || [])];
      groups[groupIndex] = { ...groups[groupIndex], choices: ng[groupIndex].choices };
      return { ...prev, customOptionGroups: groups };
    });
  };

  const updateChoiceLabel = (groupIndex: number, choiceIndex: number, value: string) => {
    const ng = [...customOptions];
    ng[groupIndex].choices = ng[groupIndex].choices.map((c, i) =>
      i === choiceIndex ? { ...c, label: value } : c
    );
    setCustomOptions(ng);
    setDraft((prev) => {
      const groups = [...(prev.customOptionGroups || [])];
      groups[groupIndex] = { ...groups[groupIndex], choices: ng[groupIndex].choices };
      return { ...prev, customOptionGroups: groups };
    });
  };

  const updateChoicePriceUSD = (groupIndex: number, choiceIndex: number, value: string) => {
    const ng = [...customOptions];
    ng[groupIndex].choices = ng[groupIndex].choices.map((c, i) =>
      i === choiceIndex ? { ...c, extraPriceUSD: Number(value) || 0 } : c
    );
    setCustomOptions(ng);
    setDraft((prev) => {
      const groups = [...(prev.customOptionGroups || [])];
      groups[groupIndex] = { ...groups[groupIndex], choices: ng[groupIndex].choices };
      return { ...prev, customOptionGroups: groups };
    });
  };

  const updateChoicePriceJMD = (groupIndex: number, choiceIndex: number, value: string) => {
    const ng = [...customOptions];
    ng[groupIndex].choices = ng[groupIndex].choices.map((c, i) =>
      i === choiceIndex ? { ...c, extraPriceJMD: Math.round(Number(value) || 0) } : c
    );
    setCustomOptions(ng);
    setDraft((prev) => {
      const groups = [...(prev.customOptionGroups || [])];
      groups[groupIndex] = { ...groups[groupIndex], choices: ng[groupIndex].choices };
      return { ...prev, customOptionGroups: groups };
    });
  };

  const handleSave = () => {
    setShowErrors(true);
    if (Object.keys(validation).length > 0) return;
    onSave(draft);
  };

  const hasErrors = Object.keys(validation).length > 0;
  const nextGroupPlaceholder = { id: "", title: "", required: true, choices: [] };

  return (
    <section
      id="wizard-menu-item-builder"
      aria-label="Menu item builder"
      style={{
        padding: "18px",
        borderRadius: "12px",
        background: "#0B1120",
        border: "1px solid rgba(148, 163, 184, 0.22)",
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
          marginBottom: "4px",
        }}
      >
        <ImageIcon size={14} color="#38BDF8" />
        <span
          style={{
            fontSize: "11px",
            fontWeight: 800,
            color: "#E2E8F0",
            textTransform: "uppercase",
            letterSpacing: "0.6px",
          }}
        >
          Individual Menu Item Builder
        </span>
      </div>

      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))",
          gap: "12px",
        }}
      >
        <div>
          <label htmlFor="mib-item-name" style={labelStyle}>
            Item Name *
          </label>
          <input
            id="mib-item-name"
            type="text"
            value={draft.name}
            disabled={disabled}
            onChange={(e) => updateField("name", e.target.value)}
            placeholder="Jerk Chicken Plate"
            style={inputStyle}
          />
          {fieldErrorText(showErrors && validation.name)}
        </div>
        <div>
          <label htmlFor="mib-category" style={labelStyle}>
            Food Category *
          </label>
          <select
            id="mib-category"
            value={draft.category}
            disabled={disabled}
            onChange={(e) => updateField("category", e.target.value as MenuCategory)}
            style={selectStyle}
          >
            {CATEGORIES.map((cat) => (
              <option key={cat} value={cat}>
                {cat}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label htmlFor="mib-country-origin" style={labelStyle}>
            Country of Origin / Cuisine
          </label>
          <input
            id="mib-country-origin"
            type="text"
            value={draft.countryOfOrigin || ""}
            disabled={disabled}
            onChange={(e) => updateField("countryOfOrigin", e.target.value)}
            placeholder="Jamaica / Caribbean"
            style={inputStyle}
          />
        </div>
        <div>
          <label htmlFor="mib-pricing-type" style={labelStyle}>
            Pricing Type
          </label>
          <select
            id="mib-pricing-type"
            value={draft.pricingType}
            disabled={disabled}
            onChange={(e) => updateField("pricingType", e.target.value as PricingType)}
            style={selectStyle}
          >
            <option value="fixed">Fixed Price</option>
            <option value="customizable">
              Customizable (Required/Optional Selections)
            </option>
            <option value="custom_amount">Custom Amount (Donation)</option>
          </select>
        </div>
      </div>

      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))",
          gap: "12px",
        }}
      >
        <div>
          <label htmlFor="mib-price-usd" style={labelStyle}>
            Base Price (USD)
          </label>
          <input
            id="mib-price-usd"
            type="number"
            min={0}
            step={0.5}
            value={draft.priceUSD}
            disabled={disabled}
            onChange={(e) => updateField("priceUSD", Number(e.target.value) || 0)}
            style={inputStyle}
          />
          {fieldErrorText(showErrors && validation.priceUSD)}
        </div>
        <div>
          <label htmlFor="mib-price-jmd" style={labelStyle}>
            Base Price (JMD)
          </label>
          <input
            id="mib-price-jmd"
            type="number"
            min={0}
            step={50}
            value={draft.priceJMD}
            disabled={disabled}
            onChange={(e) => updateField("priceJMD", Math.round(Number(e.target.value) || 0))}
            style={inputStyle}
          />
        </div>
        <div>
          <label htmlFor="mib-available-qty" style={labelStyle}>
            Available Quantity
          </label>
          <input
            id="mib-available-qty"
            type="number"
            min={0}
            step={1}
            value={draft.availableStock ?? ""}
            disabled={disabled}
            onChange={(e) =>
              updateField(
                "availableStock",
                e.target.value ? Number(e.target.value) : undefined
              )
            }
            placeholder="Unlimited"
            style={inputStyle}
          />
          {fieldErrorText(showErrors && validation.availableStock)}
        </div>
        <div>
          <label htmlFor="mib-max-per-order" style={labelStyle}>
            Max Quantity per Order
          </label>
          <input
            id="mib-max-per-order"
            type="number"
            min={1}
            step={1}
            value={draft.maxQtyPerOrder ?? ""}
            disabled={disabled}
            onChange={(e) =>
              updateField(
                "maxQtyPerOrder",
                e.target.value ? Number(e.target.value) : undefined
              )
            }
            placeholder="No limit"
            style={inputStyle}
          />
          {fieldErrorText(showErrors && validation.maxQtyPerOrder)}
        </div>
      </div>

      {/* Image upload */}
      <div>
        <label style={labelStyle}>Item Photograph *</label>
        <div style={{ display: "flex", gap: "8px", alignItems: "center", flexWrap: "wrap" }}>
          <div
            style={{
              position: "relative",
              width: "170px",
              height: "170px",
              borderRadius: "10px",
              background: "#020617",
              border: `1px solid ${
                imageError ? "rgba(248, 113, 113, 0.4)" : "rgba(148, 163, 184, 0.3)"
              }`,
              overflow: "hidden",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            {imagePreview ? (
              <img
                src={imagePreview}
                alt={draft.name}
                style={{ width: "100%", height: "100%", objectFit: "cover" }}
              />
            ) : (
              <div style={{ color: "#64748B", fontSize: "11px", textAlign: "center" }}>
                <ImageIcon size={32} />
                <div>No image</div>
              </div>
            )}
            {imagePreview && (
              <button
                type="button"
                id="mib-replace-image"
                disabled={disabled}
                onClick={() => {
                  const input = document.getElementById(
                    "mib-image-input"
                  ) as HTMLInputElement | undefined;
                  input?.click();
                }}
                style={{
                  position: "absolute",
                  bottom: "8px",
                  left: "8px",
                  background: "rgba(15, 23, 42, 0.85)",
                  border: "1px solid rgba(255, 255, 255, 0.3)",
                  color: "#FFFFFF",
                  padding: "4px 8px",
                  borderRadius: "6px",
                  fontSize: "10px",
                  fontWeight: 700,
                  cursor: "pointer",
                }}
              >
                <RefreshCw size={11} /> Replace
              </button>
            )}
            {imagePreview && (
              <button
                type="button"
                id="mib-remove-image"
                disabled={disabled}
                onClick={handleRemoveImage}
                style={{
                  position: "absolute",
                  bottom: "8px",
                  right: "8px",
                  background: "rgba(239, 68, 68, 0.85)",
                  border: "none",
                  color: "#FFFFFF",
                  padding: "4px 8px",
                  borderRadius: "6px",
                  fontSize: "10px",
                  fontWeight: 700,
                  cursor: "pointer",
                }}
              >
                <Trash2 size={11} /> Remove
              </button>
            )}
          </div>
          <label
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "6px",
              padding: "8px 14px",
              borderRadius: "10px",
              background: "rgba(56, 189, 248, 0.15)",
              border: `1px solid ${
                imageError ? "rgba(248, 113, 113, 0.4)" : "rgba(56, 189, 248, 0.4)"
              }`,
              color: imageError ? "#FCA5A5" : "#38BDF8",
              fontSize: "11px",
              fontWeight: 700,
              cursor: disabled ? "not-allowed" : "pointer",
            }}
          >
            <Upload size={13} />
            {imagePreview ? "Replace Image" : "Upload Image"}
            <input
              id="mib-image-input"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              onChange={handleImageChange}
              style={{ display: "none" }}
              disabled={disabled}
            />
          </label>
          {imageError && (
            <p
              role="alert"
              style={{ margin: "6px 0 0", fontSize: "11px", color: "#FCA5A5", fontWeight: 600 }}
            >
              {imageError}
            </p>
          )}
          <p style={{ fontSize: "10px", color: "#94A3B8", margin: "4px 0 0" }}>
            JPG, PNG or WebP up to 5 MB. Use a photograph of the actual food — do
            not use unrelated stock images as if they represent the item for sale.
          </p>
        </div>
      </div>

      <div>
        <label htmlFor="mib-description" style={labelStyle}>
          Description *
        </label>
        <textarea
          id="mib-description"
          rows={3}
          value={draft.description}
          disabled={disabled}
          onChange={(e) => updateField("description", e.target.value)}
          placeholder="Describe the dish, key ingredients, and serving style…"
          style={textareaStyle}
        />
        {fieldErrorText(showErrors && validation.description)}
      </div>

      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(170px, 1fr))",
          gap: "12px",
        }}
      >
        <div>
          <label htmlFor="mib-prep-time" style={labelStyle}>
            Prep Time (minutes)
          </label>
          <input
            id="mib-prep-time"
            type="number"
            min={0}
            step={1}
            value={draft.prepTimeMinutes ?? ""}
            disabled={disabled}
            onChange={(e) =>
              updateField("prepTimeMinutes", e.target.value ? Number(e.target.value) : undefined)
            }
            placeholder="e.g. 20"
            style={inputStyle}
          />
        </div>
        <div>
          <label htmlFor="mib-availability-start" style={labelStyle}>
            Availability Start (ISO)
          </label>
          <input
            id="mib-availability-start"
            type="datetime-local"
            value={draft.availabilityStartIso || ""}
            disabled={disabled}
            onChange={(e) => updateField("availabilityStartIso", e.target.value)}
            style={inputStyle}
          />
        </div>
        <div>
          <label htmlFor="mib-availability-end" style={labelStyle}>
            Availability End (ISO)
          </label>
          <input
            id="mib-availability-end"
            type="datetime-local"
            value={draft.availabilityEndIso || ""}
            disabled={disabled}
            onChange={(e) => updateField("availabilityEndIso", e.target.value)}
            style={inputStyle}
          />
        </div>
        <div>
          <label style={{ ...labelStyle, display: "flex", alignItems: "center", gap: "6px" }}>
            <input
              type="checkbox"
              checked={draft.isFeatured ?? false}
              disabled={disabled}
              onChange={(e) => updateField("isFeatured", e.target.checked)}
            />
            Featured Item
          </label>
        </div>
        <div>
          <label style={{ ...labelStyle, display: "flex", alignItems: "center", gap: "6px" }}>
            <input
              type="checkbox"
              checked={draft.isAvailable !== false}
              disabled={disabled}
              onChange={(e) => updateField("isAvailable", e.target.checked)}
            />
            Available for Ordering
          </label>
        </div>
      </div>

      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(170px, 1fr))",
          gap: "12px",
        }}
      >
        <div>
          <label htmlFor="mib-dietary" style={labelStyle}>
            Dietary Classification
          </label>
          <select
            id="mib-dietary"
            value={draft.dietaryClassification || ""}
            disabled={disabled}
            onChange={(e) =>
              updateField(
                "dietaryClassification",
                (e.target.value || undefined) as MenuItem["dietaryClassification"]
              )
            }
            style={selectStyle}
          >
            <option value="">None specified</option>
            <option value="vegetarian">Vegetarian</option>
            <option value="vegan">Vegan</option>
            <option value="pescatarian">Pescatarian</option>
            <option value="halal">Halal</option>
            <option value="kosher">Kosher</option>
            <option value="glutenFree">Gluten Free</option>
            <option value="dairyFree">Dairy Free</option>
            <option value="nutFree">Nut Free</option>
          </select>
        </div>
        <div>
          <label htmlFor="mib-allergens" style={labelStyle}>
            Allergens (comma-separated)
          </label>
          <input
            id="mib-allergens"
            type="text"
            value={draft.allergens?.join(", ") || ""}
            disabled={disabled}
            onChange={(e) =>
              updateField(
                "allergens",
                e.target.value.split(",").map((s) => s.trim()).filter(Boolean)
              )
            }
            placeholder="gluten, dairy, nuts"
            style={inputStyle}
          />
        </div>
        <div>
          <label htmlFor="mib-service-mode" style={labelStyle}>
            Service Mode
          </label>
          <select
            id="mib-service-mode"
            value={draft.serviceMode || "both"}
            disabled={disabled}
            onChange={(e) =>
              updateField("serviceMode", e.target.value as MenuItem["serviceMode"])
            }
            style={selectStyle}
          >
            <option value="both">Preorder & Event-day</option>
            <option value="preorder">Preorder Only</option>
            <option value="eventDay">Event-day Only</option>
          </select>
        </div>
      </div>

      <div>
        <label htmlFor="mib-ingredients" style={labelStyle}>
          Ingredients
        </label>
        <textarea
          id="mib-ingredients"
          rows={2}
          value={draft.ingredients || ""}
          disabled={disabled}
          onChange={(e) => updateField("ingredients", e.target.value)}
          placeholder="List key ingredients…"
          style={textareaStyle}
        />
      </div>

      <div>
        <label style={{ ...labelStyle, display: "flex", alignItems: "center", gap: "6px", marginBottom: "6px" }}>
          <input
            type="checkbox"
            checked={draft.allowSpecialInstructions ?? false}
            disabled={disabled}
            onChange={(e) => updateField("allowSpecialInstructions", e.target.checked)}
          />
          Allow customer special instructions
        </label>
        <textarea
          id="mib-instructions"
          rows={2}
          value={draft.description || ""}
          disabled={disabled}
          placeholder="e.g. Extra scotch bonnet on the side…"
          style={textareaStyle}
        />
      </div>

      {/* Customizable option groups */}
      {draft.pricingType === "customizable" && (
        <div>
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              gap: "8px",
              marginBottom: "8px",
            }}
          >
            <label style={labelStyle}>Required & Optional Selections</label>
            <button
              type="button"
              disabled={disabled}
              onClick={addOptionGroup}
              style={{
                padding: "5px 12px",
                borderRadius: "8px",
                background: "rgba(16, 185, 129, 0.18)",
                color: "#34D399",
                border: "1px solid rgba(52, 211, 153, 0.4)",
                fontSize: "11px",
                fontWeight: 700,
                cursor: "pointer",
              }}
            >
              <Plus size={13} /> Add Selection Group
            </button>
          </div>
          {customOptions.map((group, gi) => (
            <div
              key={group.id}
              style={{
                padding: "10px 12px",
                borderRadius: "10px",
                background: "rgba(30, 41, 59, 0.6)",
                border: "1px solid rgba(148, 163, 184, 0.2)",
                marginBottom: "8px",
                display: "flex",
                flexDirection: "column",
                gap: "8px",
              }}
            >
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  gap: "8px",
                }}
              >
                <input
                  type="text"
                  value={group.title}
                  disabled={disabled}
                  onChange={(e) => updateOptionGroup(gi, { title: e.target.value })}
                  style={{
                    ...inputStyle,
                    fontWeight: 700,
                    color: "#F8FAFC",
                  }}
                />
                <label
                  style={{
                    ...labelStyle,
                    marginBottom: 0,
                    fontSize: "10px",
                    cursor: "pointer",
                  }}
                >
                  <input
                    type="checkbox"
                    checked={group.required}
                    disabled={disabled}
                    onChange={() => updateOptionGroup(gi, { required: !group.required })}
                    style={{ margin: 0 }}
                  />
                  {group.required ? "Required" : "Optional"}
                </label>
                <button
                  type="button"
                  disabled={disabled}
                  onClick={() => removeOptionGroup(group.id)}
                  style={{
                    padding: "4px 8px",
                    borderRadius: "6px",
                    background: "rgba(239, 68, 68, 0.16)",
                    color: "#FCA5A5",
                    border: "none",
                    fontSize: "10px",
                    cursor: "pointer",
                  }}
                >
                  <Trash2 size={12} />
                </button>
              </div>
              <div style={{ display: "flex", flexDirection: "column", gap: "4px" }}>
                {group.choices.map((choice, ci) => (
                  <div
                    key={choice.label + ci}
                    style={{ display: "flex", gap: "8px", alignItems: "center" }}
                  >
                    <input
                      type="text"
                      value={choice.label}
                      disabled={disabled}
                      onChange={(e) => updateChoiceLabel(gi, ci, e.target.value)}
                      style={inputStyle}
                    />
                    <div style={{ display: "flex", gap: "6px", flex: 1 }}>
                      <input
                        type="number"
                        min={0}
                        step={0.5}
                        value={choice.extraPriceUSD}
                        disabled={disabled}
                        onChange={(e) => updateChoicePriceUSD(gi, ci, e.target.value)}
                        placeholder="USD"
                        style={{ ...inputStyle, maxWidth: "90px" }}
                      />
                      <input
                        type="number"
                        min={0}
                        step={50}
                        value={choice.extraPriceJMD}
                        disabled={disabled}
                        onChange={(e) => updateChoicePriceJMD(gi, ci, e.target.value)}
                        placeholder="JMD"
                        style={{ ...inputStyle, maxWidth: "90px" }}
                      />
                    </div>
                    <button
                      type="button"
                      disabled={disabled}
                      onClick={() => removeChoice(gi, ci)}
                      style={{
                        padding: "4px 8px",
                        borderRadius: "6px",
                        background: "rgba(239, 68, 68, 0.16)",
                        color: "#FCA5A5",
                        border: "none",
                        fontSize: "10px",
                        cursor: "pointer",
                      }}
                    >
                      <Trash2 size={11} />
                    </button>
                  </div>
                ))}
                <button
                  type="button"
                  disabled={disabled}
                  onClick={() => addChoice(gi)}
                  style={{
                    padding: "4px 10px",
                    borderRadius: "6px",
                    background: "rgba(99, 102, 241, 0.15)",
                    color: "#A5B4FC",
                    border: "1px solid rgba(129, 140, 248, 0.3)",
                    fontSize: "10px",
                    cursor: "pointer",
                    alignSelf: "flex-start",
                  }}
                >
                  <Plus size={11} /> Add Choice
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      <div
        style={{
          padding: "12px",
          borderRadius: "8px",
          background: hasErrors
            ? "rgba(239, 68, 68, 0.1)"
            : "rgba(52, 211, 153, 0.1)",
          border: `1px solid ${hasErrors ? "rgba(248, 113, 113, 0.35)" : "rgba(52, 211, 153, 0.35)"}`,
          display: "flex",
          alignItems: "center",
          gap: "8px",
          fontSize: "11px",
          color: hasErrors ? "#FCA5A5" : "#34D399",
        }}
      >
        {hasErrors ? (
          <>
            <AlertCircle size={13} />
            <span>Please fix the highlighted fields before saving.</span>
          </>
        ) : (
          <>
            <CheckCircle2 size={13} />
            <span>Item configuration is valid.</span>
          </>
        )}
      </div>

      <div style={{ display: "flex", gap: "10px", justifyContent: "flex-end", marginTop: "4px" }}>
        <button
          type="button"
          id="mib-delete-item"
          disabled={disabled || !item}
          onClick={onDelete}
          style={{
            padding: "8px 14px",
            borderRadius: "10px",
            background: "rgba(239, 68, 68, 0.16)",
            color: "#FCA5A5",
            border: "1px solid rgba(248, 113, 113, 0.35)",
            fontSize: "11px",
            fontWeight: 700,
            cursor: disabled || !item ? "not-allowed" : "pointer",
          }}
        >
          Delete Item
        </button>
        <button
          type="button"
          id="mib-save-item"
          disabled={disabled || hasErrors}
          onClick={handleSave}
          style={{
            padding: "8px 16px",
            borderRadius: "10px",
            background: "linear-gradient(135deg, #10B981 0%, #059669 100%)",
            color: "#052E16",
            border: "none",
            fontSize: "11px",
            fontWeight: 800,
            cursor: disabled || hasErrors ? "not-allowed" : "pointer",
          }}
        >
          Save Item
        </button>
      </div>
    </section>
  );
}
