import React, { useCallback, useEffect, useMemo, useState } from "react";
import { AlertCircle, Image as ImageIcon, Plus, Trash2, Utensils } from "lucide-react";
import {
  Currency,
  MenuItem,
  MenuPackage,
  MenuPackageAddition,
  MenuPackageChoice,
  MenuPackageChoiceGroup,
  MenuPackageComponent,
} from "../../types";
import { formatCurrencyAmount } from "../../tests/testUtils";

type Props = {
  menu: MenuItem[];
  currency: Currency;
  disabled?: boolean;
  editingPackage?: MenuPackage | null;
  onSave: (pkg: MenuPackage) => void;
  onDelete: (id: string) => void;
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

export function PackageBuilder({
  menu,
  currency,
  disabled = false,
  editingPackage,
  onSave,
  onDelete,
}: Props) {
  const createDraft = (): MenuPackage => ({
    id: `pkg-${Date.now()}`,
    name: "",
    description: "",
    imageUrl: "",
    basePriceUSD: 0,
    basePriceJMD: 0,
    components: [],
    optionalAdditions: [],
    isAvailable: true,
    isFeatured: false,
  });
  const [draft, setDraft] = useState<MenuPackage>(
    editingPackage ?? createDraft()
  );

  const availableItems = menu;
  const [showErrors, setShowErrors] = useState(false);
  const [imageError, setImageError] = useState<string | null>(null);

  useEffect(() => {
    setDraft(editingPackage ?? createDraft());
    setShowErrors(false);
    setImageError(null);
  }, [editingPackage]);

  const validation = useMemo(() => {
    const errs: Record<string, string> = {};
    if (!draft.name || draft.name.trim().length < 2) {
      errs.name = "Package name must be at least 2 characters.";
    }
    if (!draft.description || draft.description.trim().length < 5) {
      errs.description = "Description must be at least 5 characters.";
    }
    const choiceGroups = draft.choiceGroups || [];
    if (draft.components.length === 0 && choiceGroups.every((group) => group.options.length === 0)) {
      errs.components = "Add an included item or a customer choice group.";
    }
    if (draft.basePriceUSD < 0 || draft.basePriceJMD < 0) {
      errs.basePriceUSD = "Package price cannot be negative.";
    }
    choiceGroups.forEach((group, index) => {
      if (!group.title.trim()) errs[`choiceGroup${index}`] = "Give each choice group a name.";
      if (group.options.length === 0) errs[`choiceGroup${index}`] = "Add at least one item to each choice group.";
      if (group.min < 0 || group.max < 1 || group.min > group.max || group.max > group.options.length) {
        errs[`choiceGroup${index}`] = "Choice limits must fit the number of available options.";
      }
    });
    return errs;
  }, [draft]);

  const updateField = <K extends keyof MenuPackage>(
    key: K,
    value: MenuPackage[K]
  ) => {
    setDraft((prev) => ({ ...prev, [key]: value }));
  };

  const addComponent = useCallback(() => {
    const item = availableItems[0];
    if (!item) return;
    const next: MenuPackageComponent = {
      menuItemId: item.id,
      menuItemName: item.name,
      quantity: 1,
      isRequired: true,
      isSubstitutable: false,
    };
    setDraft((prev) => ({
      ...prev,
      components: [...prev.components, next],
    }));
  }, [availableItems]);

  const updateComponent = (
    index: number,
    patch: Partial<MenuPackageComponent>
  ) => {
    setDraft((prev) => {
      const comps = [...prev.components];
      comps[index] = { ...comps[index], ...patch };
      return { ...prev, components: comps };
    });
  };

  const removeComponent = (index: number) => {
    setDraft((prev) => {
      const comps = prev.components.filter((_, i) => i !== index);
      return { ...prev, components: comps };
    });
  };

  const addChoiceGroup = () => {
    const item = availableItems[0];
    if (!item) return;
    const group: MenuPackageChoiceGroup = {
      id: `choice-${Date.now()}`,
      title: "Choose an item",
      min: 1,
      max: 1,
      options: [{
        menuItemId: item.id,
        menuItemName: item.name,
        upgradePriceUSD: 0,
        upgradePriceJMD: 0,
      }],
    };
    setDraft((prev) => ({ ...prev, choiceGroups: [...(prev.choiceGroups || []), group] }));
  };

  const updateChoiceGroup = (index: number, patch: Partial<MenuPackageChoiceGroup>) => {
    setDraft((prev) => {
      const groups = [...(prev.choiceGroups || [])];
      groups[index] = { ...groups[index], ...patch };
      return { ...prev, choiceGroups: groups };
    });
  };

  const removeChoiceGroup = (index: number) => {
    setDraft((prev) => ({
      ...prev,
      choiceGroups: (prev.choiceGroups || []).filter((_, groupIndex) => groupIndex !== index),
    }));
  };

  const addChoiceOption = (index: number) => {
    const item = availableItems[0];
    if (!item) return;
    setDraft((prev) => {
      const groups = [...(prev.choiceGroups || [])];
      groups[index] = {
        ...groups[index],
        options: [...groups[index].options, {
          menuItemId: item.id,
          menuItemName: item.name,
          upgradePriceUSD: 0,
          upgradePriceJMD: 0,
        }],
      };
      return { ...prev, choiceGroups: groups };
    });
  };

  const updateChoiceOption = (groupIndex: number, optionIndex: number, patch: Partial<MenuPackageChoice>) => {
    setDraft((prev) => {
      const groups = [...(prev.choiceGroups || [])];
      const options = [...groups[groupIndex].options];
      options[optionIndex] = { ...options[optionIndex], ...patch };
      groups[groupIndex] = { ...groups[groupIndex], options };
      return { ...prev, choiceGroups: groups };
    });
  };

  const removeChoiceOption = (groupIndex: number, optionIndex: number) => {
    setDraft((prev) => {
      const groups = [...(prev.choiceGroups || [])];
      groups[groupIndex] = {
        ...groups[groupIndex],
        options: groups[groupIndex].options.filter((_, option) => option !== optionIndex),
        max: Math.min(groups[groupIndex].max, Math.max(1, groups[groupIndex].options.length - 1)),
        min: Math.min(groups[groupIndex].min, Math.max(0, groups[groupIndex].options.length - 1)),
      };
      return { ...prev, choiceGroups: groups };
    });
  };

  const addAddition = useCallback(() => {
    const item = availableItems[0];
    if (!item) return;
    const next: MenuPackageAddition = {
      menuItemId: item.id,
      menuItemName: item.name,
      priceUSD: currency === "JMD" ? 0 : item.priceUSD,
      priceJMD: currency === "JMD" ? item.priceJMD : 0,
    };
    setDraft((prev) => ({
      ...prev,
      optionalAdditions: [...(prev.optionalAdditions || []), next],
    }));
  }, [availableItems, currency]);

  const updateAddition = (index: number, patch: Partial<MenuPackageAddition>) => {
    setDraft((prev) => {
      const adds = [...(prev.optionalAdditions || [])];
      adds[index] = { ...adds[index], ...patch };
      return { ...prev, optionalAdditions: adds };
    });
  };

  const removeAddition = (index: number) => {
    setDraft((prev) => {
      const adds = (prev.optionalAdditions || []).filter((_, i) => i !== index);
      return { ...prev, optionalAdditions: adds };
    });
  };

  const handleSave = () => {
    setShowErrors(true);
    if (Object.keys(validation).length > 0) return;
    onSave(draft);
  };

  const hasErrors = Object.keys(validation).length > 0;
  const handleImageChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type.toLowerCase())) {
      setImageError("Only JPG, PNG, and WebP images are supported.");
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      setImageError("Image must be 5 MB or smaller.");
      return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      if (typeof reader.result !== "string") {
        setImageError("The selected image could not be read.");
        return;
      }
      setImageError(null);
      setDraft((prev) => ({ ...prev, imageUrl: reader.result as string }));
    };
    reader.onerror = () => setImageError("The selected image could not be read.");
    reader.readAsDataURL(file);
  };

  return (
    <section
      id="wizard-package-builder"
      aria-label="Meal package builder"
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
        <Utensils size={14} color="#FBBF24" />
        <span
          style={{
            fontSize: "11px",
            fontWeight: 800,
            color: "#E2E8F0",
            textTransform: "uppercase",
            letterSpacing: "0.6px",
          }}
        >
          Meal Package Builder
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
          <label htmlFor="pb-pkg-name" style={labelStyle}>
            Package Name *
          </label>
          <input
            id="pb-pkg-name"
            type="text"
            value={draft.name}
            disabled={disabled}
            onChange={(e) => updateField("name", e.target.value)}
            placeholder="Jamaican Sunday Dinner"
            style={inputStyle}
          />
          {fieldErrorText(showErrors && validation.name)}
        </div>
        <div>
          <label htmlFor="pb-pkg-price-usd" style={labelStyle}>
            Package Base Price (USD)
          </label>
          <input
            id="pb-pkg-price-usd"
            type="number"
            min={0}
            step={0.5}
            value={currency === "JMD" ? 0 : draft.basePriceUSD}
            disabled={disabled}
            onChange={(e) =>
              updateField("basePriceUSD", Number(e.target.value) || 0)
            }
            style={inputStyle}
          />
          {fieldErrorText(showErrors && validation.basePriceUSD)}
        </div>
        <div>
          <label htmlFor="pb-pkg-price-jmd" style={labelStyle}>
            Package Base Price (JMD)
          </label>
          <input
            id="pb-pkg-price-jmd"
            type="number"
            min={0}
            step={50}
            value={currency === "JMD" ? draft.basePriceJMD : 0}
            disabled={disabled}
            onChange={(e) =>
              updateField("basePriceJMD", Math.round(Number(e.target.value) || 0))
            }
            style={inputStyle}
          />
        </div>
        <div>
          <label htmlFor="pb-pkg-max" style={labelStyle}>
            Max Package Quantity
          </label>
          <input
            id="pb-pkg-max"
            type="number"
            min={1}
            step={1}
            value={draft.maxQty ?? ""}
            disabled={disabled}
            onChange={(e) =>
              updateField("maxQty", e.target.value ? Number(e.target.value) : undefined)
            }
            placeholder="Unlimited"
            style={inputStyle}
          />
        </div>
        <div>
          <label htmlFor="pb-pkg-limit" style={labelStyle}>
            Max per Customer
          </label>
          <input
            id="pb-pkg-limit"
            type="number"
            min={1}
            step={1}
            value={draft.maxQtyPerOrder ?? ""}
            disabled={disabled}
            onChange={(e) => updateField("maxQtyPerOrder", e.target.value ? Number(e.target.value) : undefined)}
            placeholder="Unlimited"
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
            Featured Package
          </label>
          <label style={{ ...labelStyle, display: "flex", alignItems: "center", gap: "6px", marginTop: 8 }}>
            <input
              type="checkbox"
              checked={draft.isAvailable !== false}
              disabled={disabled}
              onChange={(e) => updateField("isAvailable", e.target.checked)}
            />
            Available to order
          </label>
        </div>
      </div>

      <div>
        <label htmlFor="pb-pkg-desc" style={labelStyle}>
          Description *
        </label>
        <textarea
          id="pb-pkg-desc"
          rows={3}
          value={draft.description}
          disabled={disabled}
          onChange={(e) => updateField("description", e.target.value)}
          placeholder="Jerk Chicken, Rice and Peas, Steamed Vegetables, Festival, Optional Plantain…"
          style={{ ...inputStyle, height: "auto", padding: "10px 12px", resize: "vertical" }}
        />
        {fieldErrorText(showErrors && validation.description)}
      </div>

      <div>
        <label style={labelStyle}>Package Photograph</label>
        <div style={{ display: "flex", alignItems: "center", gap: "12px", flexWrap: "wrap" }}>
          {draft.imageUrl ? (
            <img
              src={draft.imageUrl}
              alt={`${draft.name || "Meal package"} preview`}
              style={{ width: 120, height: 90, objectFit: "cover", borderRadius: 8 }}
            />
          ) : (
            <div style={{ width: 120, height: 90, display: "grid", placeItems: "center", border: "1px dashed #64748B", borderRadius: 8, color: "#94A3B8" }}>
              <ImageIcon size={24} />
            </div>
          )}
          <label style={{ ...labelStyle, cursor: disabled ? "not-allowed" : "pointer" }}>
            {draft.imageUrl ? "Replace image" : "Upload image"}
            <input
              type="file"
              accept="image/jpeg,image/png,image/webp"
              disabled={disabled}
              onChange={handleImageChange}
              style={{ display: "block", marginTop: 6 }}
            />
          </label>
          {draft.imageUrl && (
            <button type="button" disabled={disabled} onClick={() => updateField("imageUrl", "")}>
              Remove image
            </button>
          )}
        </div>
        <p style={{ fontSize: 10, color: "#94A3B8", margin: "4px 0 0" }}>JPG, PNG or WebP up to 5 MB. Use an actual package photo or leave this empty.</p>
        {imageError && fieldErrorText(imageError)}
      </div>

      {/* Included components */}
      <div>
        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: "8px", marginBottom: "8px" }}>
          <label style={labelStyle}>Included Items (Built from Menu)</label>
          <button
            type="button"
            disabled={disabled || availableItems.length === 0}
            onClick={addComponent}
            style={{
              padding: "5px 12px",
              borderRadius: "8px",
              background: "rgba(16, 185, 129, 0.18)",
              color: "#34D399",
              border: "1px solid rgba(52, 211, 153, 0.4)",
              fontSize: "11px",
              fontWeight: 700,
              cursor: (disabled || availableItems.length === 0) ? "not-allowed" : "pointer",
            }}
          >
            <Plus size={13} /> Add Item
          </button>
        </div>
        {draft.components.length === 0 && (
          <p style={{ fontSize: "11px", color: "#94A3B8", margin: "0 0 8px" }}>
            Select existing menu items to include. The package price is calculated to avoid duplicate charging.
          </p>
        )}
        <div style={{ display: "flex", flexDirection: "column", gap: "6px" }}>
          {draft.components.map((comp, idx) => {
            const item = availableItems.find((i) => i.id === comp.menuItemId);
            const unitPrice = item
              ? currency === "JMD"
                ? item.priceJMD
                : item.priceUSD
              : 0;
            const lineTotal = unitPrice * comp.quantity;
            return (
              <React.Fragment key={comp.menuItemId + idx}>
                <div
                  style={{
                    display: "grid",
                    gridTemplateColumns: "1fr 90px 90px 110px 80px",
                    gap: "8px",
                    alignItems: "center",
                    padding: "6px 10px",
                    background: "rgba(30, 41, 59, 0.6)",
                    border: "1px solid rgba(148, 163, 184, 0.2)",
                    borderRadius: "8px",
                  }}
                >
                <div style={{ minWidth: 0 }}>
                  <label style={{ ...labelStyle, marginBottom: "2px", fontSize: "10px" }}>Item</label>
                  <select
                    value={comp.menuItemId}
                    disabled={disabled}
                    onChange={(e) => {
                      const selected = availableItems.find((i) => i.id === e.target.value);
                      updateComponent(idx, {
                        menuItemId: selected?.id || comp.menuItemId,
                        menuItemName: selected?.name || comp.menuItemName,
                      });
                    }}
                    style={inputStyle}
                  >
                    {availableItems.map((i) => (
                      <option key={i.id} value={i.id}>
                        {i.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label style={{ ...labelStyle, marginBottom: "2px", fontSize: "10px" }}>Qty</label>
                  <input
                    type="number"
                    min={1}
                    step={1}
                    value={comp.quantity}
                    disabled={disabled}
                    onChange={(e) =>
                      updateComponent(idx, { quantity: Math.max(1, Number(e.target.value) || 1) })
                    }
                    style={inputStyle}
                  />
                </div>
                <div style={{ fontSize: "11px", color: "#CBD5E1", textAlign: "right" }}>
                  {formatCurrencyAmount(unitPrice, currency)} each
                </div>
                <div style={{ fontSize: "11px", fontWeight: 700, color: "#38BDF8", textAlign: "right" }}>
                  {formatCurrencyAmount(lineTotal, currency)}
                </div>
                <div>
                  <label style={{ ...labelStyle, marginBottom: "2px", fontSize: "10px", display: "flex", alignItems: "center", gap: "4px" }}>
                    <input
                      type="checkbox"
                      checked={comp.isRequired}
                      disabled={disabled}
                      onChange={() =>
                        updateComponent(idx, { isRequired: !comp.isRequired })
                      }
                      style={{ margin: 0 }}
                    />
                    {comp.isRequired ? "Required" : "Optional"}
                  </label>
                </div>
                <button
                  type="button"
                  disabled={disabled}
                  onClick={() => removeComponent(idx)}
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
                <div style={{ padding: "0 10px 8px", background: "rgba(30, 41, 59, 0.35)", borderRadius: "0 0 8px 8px" }}>
                  <label style={{ ...labelStyle, display: "inline-flex", alignItems: "center", gap: 6 }}>
                    <input
                      type="checkbox"
                      checked={comp.isSubstitutable}
                      disabled={disabled}
                      onChange={(e) => updateComponent(idx, {
                        isSubstitutable: e.target.checked,
                        substituteMenuItemIds: e.target.checked ? comp.substituteMenuItemIds || [] : [],
                      })}
                    />
                    Allow substitutions
                  </label>
                  {comp.isSubstitutable && (
                    <div className="eb-stack" style={{ gap: 6 }}>
                      <div style={{ display: "flex", flexWrap: "wrap", gap: 8 }}>
                        {availableItems.filter((candidate) => candidate.id !== comp.menuItemId).map((candidate) => {
                          const selected = (comp.substituteMenuItemIds || []).includes(candidate.id);
                          return (
                            <label key={candidate.id} style={{ ...labelStyle, display: "inline-flex", alignItems: "center", gap: 4 }}>
                              <input
                                type="checkbox"
                                checked={selected}
                                disabled={disabled}
                                onChange={() => updateComponent(idx, {
                                  substituteMenuItemIds: selected
                                    ? (comp.substituteMenuItemIds || []).filter((id) => id !== candidate.id)
                                    : [...(comp.substituteMenuItemIds || []), candidate.id],
                                })}
                              />
                              {candidate.name}
                            </label>
                          );
                        })}
                      </div>
                      <label style={{ ...labelStyle, maxWidth: 220 }}>
                        Upgrade price for a substitution ({currency})
                        <input
                          type="number"
                          min={0}
                          step={currency === "JMD" ? 50 : 0.25}
                          value={currency === "JMD" ? comp.upgradePriceJMD || 0 : comp.upgradePriceUSD || 0}
                          disabled={disabled}
                          onChange={(e) => updateComponent(idx, currency === "JMD"
                            ? { upgradePriceJMD: Number(e.target.value) || 0 }
                            : { upgradePriceUSD: Number(e.target.value) || 0 })}
                          style={inputStyle}
                        />
                      </label>
                    </div>
                  )}
                </div>
              </React.Fragment>
            );
          })}
        </div>
        {fieldErrorText(showErrors && validation.components)}
      </div>

      <div>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 8 }}>
          <label style={labelStyle}>Required and Optional Choices</label>
          <button type="button" disabled={disabled || availableItems.length === 0} onClick={addChoiceGroup} className="eb-btn eb-btn-secondary eb-btn-sm">
            <Plus size={13} /> Add Choice Group
          </button>
        </div>
        <p style={{ fontSize: 11, color: "#94A3B8", margin: "0 0 8px" }}>
          Set how many items the attendee must choose, such as two mains, two sides, and four drinks.
        </p>
        <div className="eb-stack" style={{ gap: 8 }}>
          {(draft.choiceGroups || []).map((group, groupIndex) => (
            <fieldset key={group.id} style={{ border: "1px solid rgba(148, 163, 184, 0.25)", borderRadius: 8, padding: 10 }}>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 90px 90px auto", gap: 8, alignItems: "end" }}>
                <div>
                  <label style={labelStyle}>Selection name</label>
                  <input
                    value={group.title}
                    disabled={disabled}
                    onChange={(e) => updateChoiceGroup(groupIndex, { title: e.target.value })}
                    style={inputStyle}
                    placeholder="Choice of main dishes"
                  />
                </div>
                <div>
                  <label style={labelStyle}>Choose at least</label>
                  <input
                    type="number"
                    min={0}
                    max={group.options.length}
                    value={group.min}
                    disabled={disabled}
                    onChange={(e) => updateChoiceGroup(groupIndex, { min: Number(e.target.value) || 0 })}
                    style={inputStyle}
                  />
                </div>
                <div>
                  <label style={labelStyle}>Choose up to</label>
                  <input
                    type="number"
                    min={1}
                    max={group.options.length}
                    value={group.max}
                    disabled={disabled}
                    onChange={(e) => updateChoiceGroup(groupIndex, { max: Number(e.target.value) || 1 })}
                    style={inputStyle}
                  />
                </div>
                <button type="button" disabled={disabled} onClick={() => removeChoiceGroup(groupIndex)} className="eb-btn eb-btn-ghost eb-btn-sm">
                  Remove group
                </button>
              </div>
              <div className="eb-stack" style={{ gap: 6, marginTop: 8 }}>
                {group.options.map((option, optionIndex) => (
                  <div key={`${option.menuItemId}-${optionIndex}`} style={{ display: "grid", gridTemplateColumns: "1fr 110px auto", gap: 8, alignItems: "center" }}>
                    <select
                      value={option.menuItemId}
                      disabled={disabled}
                      onChange={(e) => {
                        const selected = availableItems.find((candidate) => candidate.id === e.target.value);
                        if (selected) updateChoiceOption(groupIndex, optionIndex, { menuItemId: selected.id, menuItemName: selected.name });
                      }}
                      style={inputStyle}
                    >
                      {availableItems.map((candidate) => <option key={candidate.id} value={candidate.id}>{candidate.name}</option>)}
                    </select>
                    <label style={labelStyle}>
                      Upgrade ({currency})
                      <input
                        type="number"
                        min={0}
                        step={currency === "JMD" ? 50 : 0.25}
                        value={currency === "JMD" ? option.upgradePriceJMD : option.upgradePriceUSD}
                        disabled={disabled}
                        onChange={(e) => updateChoiceOption(groupIndex, optionIndex, currency === "JMD"
                          ? { upgradePriceJMD: Number(e.target.value) || 0 }
                          : { upgradePriceUSD: Number(e.target.value) || 0 })}
                        style={inputStyle}
                      />
                    </label>
                    <button type="button" disabled={disabled} onClick={() => removeChoiceOption(groupIndex, optionIndex)} className="eb-btn eb-btn-ghost eb-btn-sm" aria-label="Remove choice option">
                      <Trash2 size={12} />
                    </button>
                  </div>
                ))}
                <button type="button" disabled={disabled || availableItems.length === 0} onClick={() => addChoiceOption(groupIndex)} className="eb-btn eb-btn-ghost eb-btn-sm" style={{ alignSelf: "flex-start" }}>
                  <Plus size={12} /> Add option
                </button>
              </div>
              {fieldErrorText(showErrors && validation[`choiceGroup${groupIndex}`])}
            </fieldset>
          ))}
        </div>
      </div>

      {/* Optional additions */}
      <div>
        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: "8px", marginBottom: "8px" }}>
          <label style={labelStyle}>Optional Additions</label>
          <button
            type="button"
            disabled={disabled || availableItems.length === 0}
            onClick={addAddition}
            style={{
              padding: "5px 12px",
              borderRadius: "8px",
              background: "rgba(99, 102, 241, 0.18)",
              color: "#A5B4FC",
              border: "1px solid rgba(129, 140, 248, 0.35)",
              fontSize: "11px",
              fontWeight: 700,
              cursor: (disabled || availableItems.length === 0) ? "not-allowed" : "pointer",
            }}
          >
            <Plus size={13} /> Add Addition
          </button>
        </div>
        <div style={{ display: "flex", flexDirection: "column", gap: "6px" }}>
          {(draft.optionalAdditions || []).map((add, idx) => (
            <div
              key={add.menuItemId + idx}
              style={{
                display: "grid",
                gridTemplateColumns: "1fr 110px 110px 80px",
                gap: "8px",
                alignItems: "center",
                padding: "6px 10px",
                background: "rgba(30, 41, 59, 0.6)",
                border: "1px solid rgba(148, 163, 184, 0.2)",
                borderRadius: "8px",
              }}
            >
              <div style={{ minWidth: 0 }}>
                <label style={{ ...labelStyle, marginBottom: "2px", fontSize: "10px" }}>Add-on Item</label>
                <select
                  value={add.menuItemId}
                  disabled={disabled}
                  onChange={(e) => {
                    const selected = availableItems.find((i) => i.id === e.target.value);
                    updateAddition(idx, {
                      menuItemId: selected?.id || add.menuItemId,
                      menuItemName: selected?.name || add.menuItemName,
                    });
                  }}
                  style={inputStyle}
                >
                  {availableItems.map((i) => (
                    <option key={i.id} value={i.id}>
                      {i.name}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label style={{ ...labelStyle, marginBottom: "2px", fontSize: "10px" }}>USD</label>
                <input
                  type="number"
                  min={0}
                  step={0.5}
                  value={currency === "JMD" ? 0 : add.priceUSD}
                  disabled={disabled}
                  onChange={(e) =>
                    updateAddition(idx, { priceUSD: Number(e.target.value) || 0 })
                  }
                  style={inputStyle}
                />
              </div>
              <div>
                <label style={{ ...labelStyle, marginBottom: "2px", fontSize: "10px" }}>JMD</label>
                <input
                  type="number"
                  min={0}
                  step={50}
                  value={currency === "JMD" ? add.priceJMD : 0}
                  disabled={disabled}
                  onChange={(e) =>
                    updateAddition(idx, { priceJMD: Math.round(Number(e.target.value) || 0) })
                  }
                  style={inputStyle}
                />
              </div>
              <button
                type="button"
                disabled={disabled}
                onClick={() => removeAddition(idx)}
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
        </div>
      </div>

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
          <span>Review the highlighted fields. Package pricing includes its included items; only selected upgrades and additions are charged extra.</span>
        ) : (
          <span>Package configuration is valid.</span>
        )}
      </div>

      <div style={{ display: "flex", gap: "10px", justifyContent: "flex-end", marginTop: "4px" }}>
        <button
          type="button"
          id="pb-delete-pkg"
          disabled={disabled || !editingPackage}
          onClick={() => onDelete(draft.id)}
          style={{
            padding: "8px 14px",
            borderRadius: "10px",
            background: "rgba(239, 68, 68, 0.16)",
            color: "#FCA5A5",
            border: "1px solid rgba(248, 113, 113, 0.35)",
            fontSize: "11px",
            fontWeight: 700,
            cursor: (disabled || !editingPackage) ? "not-allowed" : "pointer",
          }}
        >
          Delete Package
        </button>
        <button
          type="button"
          id="pb-save-pkg"
          disabled={disabled || hasErrors}
          onClick={handleSave}
          style={{
            padding: "8px 16px",
            borderRadius: "10px",
            background: "linear-gradient(135deg, #FBBF24 0%, #D97706 100%)",
            color: "#451A03",
            border: "none",
            fontSize: "11px",
            fontWeight: 800,
            cursor: (disabled || hasErrors) ? "not-allowed" : "pointer",
          }}
        >
          Save Package
        </button>
      </div>
    </section>
  );
}
