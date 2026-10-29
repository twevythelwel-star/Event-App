import React, { useMemo, useState } from "react";
import {
  AlertCircle,
  Calendar,
  Check,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Clock,
  DollarSign,
  Eye,
  FileText,
  Globe,
  Image as ImageIcon,
  Lock,
  MapPin,
  Plus,
  Save,
  Sparkles,
  Ticket,
  Trash2,
  Upload,
  Utensils,
} from "lucide-react";
import {
  Currency,
  CustomOptionGroup,
  EventDetails,
  EventTicketTier,
  EventTicketTierCategory,
  EventWizardStepId,
  MenuCategory,
  MenuItem,
  MenuPricingType,
  OrganizerProfile,
} from "../../types";
import { HostRolePermissions } from "./types";
import { resolveEventThumbnail } from "./financialCalculations";
import {
  EVENT_WIZARD_STEPS,
  SUPPORTED_EVENT_CATEGORIES,
  SUPPORTED_EVENT_TIMEZONES,
  evaluateWizardPublishReadiness,
  getDefaultEventTicketTiers,
  validateWizardBasicDetails,
  validateWizardDateLocation,
  validateWizardFood,
  validateWizardImageUpload,
  validateWizardTickets,
} from "./wizardValidation";

export type PreSaveFlyerState = {
  url: string;
  mimeType: string;
  sizeBytes: number;
  width: number;
  height: number;
  storagePath: string;
  fileName: string;
};

type Props = {
  eventDetails: EventDetails;
  menu: MenuItem[];
  activeOrganizer: OrganizerProfile;
  permissions: HostRolePermissions;
  flyerWarningToast: string | null;
  preSaveConfirmFlyer: PreSaveFlyerState | null;
  shareCopied: boolean;
  initialStep?: EventWizardStepId;
  onUpdateEventDetails: (details: EventDetails) => void;
  onUpdateMenu: (menu: MenuItem[]) => void;
  onOpenUnpublishModal: () => void;
  onOpenCancelEventModal: () => void;
  onFlyerUpload: (e: React.ChangeEvent<HTMLInputElement>) => void;
  onConfirmPreSavedFlyer: () => void;
  onCancelPreSavedFlyer: () => void;
  onRemoveFlyer: () => void;
  onCopyShareUrl: (text: string) => void;
  onSaveToast: (msg: string) => void;
  onOpenAddDishModal?: () => void;
  onOpenEditDishModal?: (item: MenuItem) => void;
  onDeleteDish?: (itemId: string) => void;
  formatMoney?: (amount: number, currency: Currency) => string;
};

export function EventCreationWizard({
  eventDetails,
  menu,
  activeOrganizer,
  permissions,
  flyerWarningToast,
  preSaveConfirmFlyer,
  shareCopied,
  initialStep = "basic_details",
  onUpdateEventDetails,
  onUpdateMenu,
  onOpenUnpublishModal,
  onOpenCancelEventModal,
  onFlyerUpload,
  onConfirmPreSavedFlyer,
  onCancelPreSavedFlyer,
  onRemoveFlyer,
  onCopyShareUrl,
  onSaveToast,
  onOpenAddDishModal,
  onOpenEditDishModal,
  onDeleteDish,
}: Props) {
  const [currentStep, setCurrentStep] = useState<EventWizardStepId>(initialStep);
  const [showAttemptErrors, setShowAttemptErrors] = useState(false);
  const [isPreviewModalOpen, setIsPreviewModalOpen] = useState(false);
  const [wizardBannerError, setWizardBannerError] = useState<string | null>(null);

  // Inline Quick Dish Creator state inside Step 4 (Food)
  const [showInlineDishForm, setShowInlineDishForm] = useState(false);
  const [newDishName, setNewDishName] = useState("");
  const [newDishCategory, setNewDishCategory] = useState<MenuCategory>("Mains");
  const [newDishPricingType, setNewDishPricingType] = useState<MenuPricingType>("fixed");
  const [newDishPriceUSD, setNewDishPriceUSD] = useState("16");
  const [newDishPriceJMD, setNewDishPriceJMD] = useState("2500");
  const [newDishStock, setNewDishStock] = useState("85");
  const [newDishDescription] = useState(
    "Freshly prepared island specialty with traditional sides."
  );
  const [newDishImageUrl, setNewDishImageUrl] = useState("/images/escovitch_fish.jpg");
  const [newDishAddOnTitle, setNewDishAddOnTitle] = useState("Choice of Side");
  const [newDishAddOnChoices, setNewDishAddOnChoices] = useState(
    "Festival (+J$0), Bammy (+J$200), Rice & Peas (+J$250)"
  );
  const [dishUploadError, setDishUploadError] = useState<string | null>(null);

  const ticketTiers: EventTicketTier[] = useMemo(
    () => getDefaultEventTicketTiers(eventDetails),
    [eventDetails]
  );

  const supportsFoodOrdering = eventDetails.supportsFoodOrdering !== false;

  const basicValidation = useMemo(
    () => validateWizardBasicDetails(eventDetails),
    [eventDetails]
  );

  const dateLocationValidation = useMemo(
    () => validateWizardDateLocation(eventDetails),
    [eventDetails]
  );

  const ticketsValidation = useMemo(
    () =>
      validateWizardTickets(
        ticketTiers,
        eventDetails.packagingFeeUSD,
        eventDetails.packagingFeeJMD
      ),
    [ticketTiers, eventDetails.packagingFeeUSD, eventDetails.packagingFeeJMD]
  );

  const foodValidation = useMemo(
    () => validateWizardFood(supportsFoodOrdering, menu),
    [supportsFoodOrdering, menu]
  );

  const readiness = useMemo(
    () => evaluateWizardPublishReadiness(eventDetails, ticketTiers, menu),
    [eventDetails, ticketTiers, menu]
  );

  const currentStepIndex = EVENT_WIZARD_STEPS.findIndex(
    (s) => s.id === currentStep
  );

  const activeFlyerDisplayUrl = eventDetails.flyerUrl
    ? resolveEventThumbnail(eventDetails)
    : undefined;

  const updateField = <K extends keyof EventDetails>(
    key: K,
    value: EventDetails[K]
  ) => {
    setWizardBannerError(null);
    onUpdateEventDetails({
      ...eventDetails,
      [key]: value,
    });
  };

  const handleUpdateTier = (
    tierId: string,
    patch: Partial<EventTicketTier>
  ) => {
    const updated = ticketTiers.map((t) =>
      t.id === tierId ? { ...t, ...patch } : t
    );
    updateField("ticketTiers", updated);
  };

  const handleAddTicketTier = () => {
    const nextTier: EventTicketTier = {
      id: `tier-${Date.now()}`,
      name: `Custom Pass Tier #${ticketTiers.length + 1}`,
      category: "General",
      priceUSD: 20,
      priceJMD: 3100,
      availableStock: 100,
      maxPerCustomer: 6,
      saleStartIso: "2026-10-15T00:00",
      saleEndIso: "2026-11-22T18:00",
      description: "Admission & event reservation tier.",
      benefits: ["QR Gate Entry"],
      isActive: true,
    };
    updateField("ticketTiers", [...ticketTiers, nextTier]);
    onSaveToast(`Added ticket tier "${nextTier.name}"`);
  };

  const handleRemoveTicketTier = (tierId: string) => {
    if (ticketTiers.length <= 1) {
      setWizardBannerError(
        "At least one ticket tier must remain configured for the event."
      );
      return;
    }
    const updated = ticketTiers.filter((t) => t.id !== tierId);
    updateField("ticketTiers", updated);
    onSaveToast("Ticket tier removed.");
  };

  const handleStepSelect = (targetStep: EventWizardStepId) => {
    setWizardBannerError(null);
    setCurrentStep(targetStep);
  };

  const handlePreviousStep = () => {
    if (currentStepIndex > 0) {
      setWizardBannerError(null);
      setCurrentStep(EVENT_WIZARD_STEPS[currentStepIndex - 1].id);
    }
  };

  const handleNextStep = () => {
    setShowAttemptErrors(true);
    setWizardBannerError(null);
    if (currentStepIndex < EVENT_WIZARD_STEPS.length - 1) {
      setCurrentStep(EVENT_WIZARD_STEPS[currentStepIndex + 1].id);
    }
  };

  const handleSaveDraft = () => {
    if (!permissions.canManageEventsAndMenus) {
      setWizardBannerError(
        "Authorization required: Only Event Owners and Platform Admins can save draft changes."
      );
      return;
    }
    onUpdateEventDetails({
      ...eventDetails,
      ticketTiers,
      supportsFoodOrdering,
      status: eventDetails.status === "published" ? "published" : "draft",
      isOrderingOpen: eventDetails.status === "published",
    });
    onSaveToast("Draft saved! All step progress and configurations are preserved.");
  };

  const handlePublishEvent = () => {
    setShowAttemptErrors(true);
    if (!permissions.canManageEventsAndMenus) {
      setWizardBannerError(
        "Authorization required: Your role does not permit publishing events."
      );
      return;
    }

    if (!readiness.canPublish) {
      setWizardBannerError(
        `Cannot publish incomplete event: ${readiness.blockingReasons[0]}`
      );
      return;
    }

    setWizardBannerError(null);
    onUpdateEventDetails({
      ...eventDetails,
      ticketTiers,
      supportsFoodOrdering,
      status: "published",
      isOrderingOpen: true,
      isFlyerPublic: Boolean(eventDetails.flyerUrl),
    });
    onSaveToast("Event is now PUBLISHED and live for ticket & food orders!");
  };

  const handleInlineDishPhotoUpload = (
    e: React.ChangeEvent<HTMLInputElement>
  ) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const check = validateWizardImageUpload(
      {
        name: file.name,
        sizeBytes: file.size,
        mimeType: file.type,
      },
      activeOrganizer.id,
      permissions.canManageEventsAndMenus,
      "menu"
    );
    if (!check.valid) {
      setDishUploadError(check.error || "Invalid dish photo.");
      return;
    }
    setDishUploadError(null);
    const reader = new FileReader();
    reader.onload = () => {
      if (typeof reader.result === "string") {
        setNewDishImageUrl(reader.result);
      }
    };
    reader.readAsDataURL(file);
  };

  const handleAddInlineDish = () => {
    if (!permissions.canManageEventsAndMenus) return;
    if (newDishName.trim().length < 2) {
      setDishUploadError("Dish name must be at least 2 characters.");
      return;
    }
    const priceUSD = Math.max(0, Number(newDishPriceUSD) || 0);
    const priceJMD = Math.max(0, Math.round(Number(newDishPriceJMD) || 0));
    const stock = Math.max(1, Math.round(Number(newDishStock) || 50));

    let optionGroups: CustomOptionGroup[] | undefined;
    if (newDishPricingType === "customizable") {
      const parsedChoices = newDishAddOnChoices
        .split(",")
        .map((s) => s.trim())
        .filter(Boolean)
        .map((raw) => {
          const match = raw.match(/^(.*?)\s*\(\+?J?\$?(\d+)\)$/);
          if (match) {
            const extraJmd = Number(match[2]) || 0;
            return {
              label: match[1].trim(),
              extraPriceJMD: extraJmd,
              extraPriceUSD: Number((extraJmd / 155).toFixed(2)),
            };
          }
          return {
            label: raw,
            extraPriceUSD: 0,
            extraPriceJMD: 0,
          };
        });
      optionGroups = [
        {
          id: `grp-${Date.now()}`,
          title: newDishAddOnTitle.trim() || "Meal Customizations",
          required: true,
          choices:
            parsedChoices.length > 0
              ? parsedChoices
              : [{ label: "Standard Preparation", extraPriceUSD: 0, extraPriceJMD: 0 }],
        },
      ];
    }

    const createdItem: MenuItem = {
      id: `dish-${Date.now()}`,
      organizerId: activeOrganizer.id,
      name: newDishName.trim(),
      category: newDishCategory,
      description: newDishDescription.trim(),
      pricingType: newDishPricingType,
      priceUSD,
      priceJMD,
      imageUrl: newDishImageUrl || "/images/escovitch_fish.jpg",
      availableStock: stock,
      customOptionGroups: optionGroups,
      allowSpecialInstructions: true,
    };

    onUpdateMenu([...menu, createdItem]);
    setNewDishName("");
    setShowInlineDishForm(false);
    setDishUploadError(null);
    onSaveToast(`Added "${createdItem.name}" to the event food menu!`);
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

  const labelStyle: React.CSSProperties = {
    display: "block",
    fontSize: "11px",
    fontWeight: 700,
    color: "#CBD5E1",
    marginBottom: "5px",
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

  return (
    <div
      id="event-studio-view"
      style={{
        background: "linear-gradient(180deg, #0B1120 0%, #090E1A 100%)",
        border: "1px solid rgba(148, 163, 184, 0.18)",
        borderRadius: "18px",
        padding: "22px",
        boxShadow: "0 16px 40px rgba(2, 6, 23, 0.45)",
        display: "flex",
        flexDirection: "column",
        gap: "20px",
      }}
    >
      {/* Role Access Control Banner */}
      {!permissions.canManageEventsAndMenus && (
        <div
          id="wizard-rbac-lock-banner"
          style={{
            padding: "14px 18px",
            borderRadius: "12px",
            background: "rgba(239, 68, 68, 0.14)",
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
            {permissions.roleLabel}) has read-only access to the Event Creation
            Wizard. Only Event Owners and Platform Admins can modify or publish
            events.
          </span>
        </div>
      )}

      {/* Top Header & Lifecycle Controls */}
      <div
        style={{
          padding: "16px 18px",
          borderRadius: "14px",
          background: "#0F172A",
          border: "1px solid rgba(148, 163, 184, 0.2)",
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          flexWrap: "wrap",
          gap: "14px",
        }}
      >
        <div
          style={{
            display: "flex",
            alignItems: "center",
            gap: "12px",
            flexWrap: "wrap",
          }}
        >
          <span
            id="event-status-badge"
            style={{
              padding: "5px 12px",
              borderRadius: "999px",
              fontSize: "11px",
              fontWeight: 800,
              letterSpacing: "0.6px",
              textTransform: "uppercase",
              background:
                eventDetails.status === "published"
                  ? "rgba(16, 185, 129, 0.2)"
                  : eventDetails.status === "cancelled"
                  ? "rgba(239, 68, 68, 0.2)"
                  : "rgba(245, 158, 11, 0.2)",
              color:
                eventDetails.status === "published"
                  ? "#34D399"
                  : eventDetails.status === "cancelled"
                  ? "#F87171"
                  : "#FBBF24",
              border:
                eventDetails.status === "published"
                  ? "1px solid rgba(52, 211, 153, 0.45)"
                  : "1px solid rgba(251, 191, 36, 0.45)",
            }}
          >
            {(eventDetails.status || "draft").toUpperCase()}
          </span>
          <div>
            <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
              <Sparkles size={15} color="#818CF8" />
              <strong style={{ fontSize: "15px", color: "#F8FAFC" }}>
                Guided Event-Creation & Configuration Wizard
              </strong>
            </div>
            <span style={{ fontSize: "11px", color: "#94A3B8" }}>
              Active Event: <strong>{eventDetails.title || "Untitled Event"}</strong>{" "}
              ({eventDetails.id || "draft"}) · Readiness:{" "}
              <strong style={{ color: readiness.canPublish ? "#34D399" : "#FBBF24" }}>
                {readiness.completedRequiredCount}/{readiness.totalRequiredCount} required checks complete
              </strong>
            </span>
          </div>
        </div>

        <div
          style={{
            display: "flex",
            alignItems: "center",
            gap: "8px",
            flexWrap: "wrap",
          }}
        >
          {eventDetails.status !== "published" && (
            <button
              type="button"
              id="publish-event-btn"
              disabled={!permissions.canManageEventsAndMenus || !readiness.canPublish}
              onClick={handlePublishEvent}
              title={
                !readiness.canPublish
                  ? `Complete required fields before publishing: ${readiness.blockingReasons.join(
                      " | "
                    )}`
                  : "Publish Event Live"
              }
              style={{
                padding: "8px 14px",
                borderRadius: "9px",
                background: readiness.canPublish
                  ? "linear-gradient(135deg, #10B981 0%, #059669 100%)"
                  : "rgba(148, 163, 184, 0.16)",
                color: readiness.canPublish ? "#052E16" : "#94A3B8",
                border: readiness.canPublish
                  ? "1px solid rgba(52, 211, 153, 0.5)"
                  : "1px solid rgba(148, 163, 184, 0.25)",
                fontSize: "11px",
                fontWeight: 800,
                cursor:
                  permissions.canManageEventsAndMenus && readiness.canPublish
                    ? "pointer"
                    : "not-allowed",
              }}
            >
              Publish Event Live
            </button>
          )}

          {eventDetails.status === "published" && (
            <button
              type="button"
              id="unpublish-event-btn"
              disabled={!permissions.canManageEventsAndMenus}
              onClick={onOpenUnpublishModal}
              style={{
                padding: "7px 13px",
                borderRadius: "8px",
                background: "rgba(245, 158, 11, 0.16)",
                color: "#FBBF24",
                border: "1px solid rgba(251, 191, 36, 0.4)",
                fontSize: "11px",
                fontWeight: 700,
              }}
            >
              Unpublish (Move to Draft)
            </button>
          )}

          {eventDetails.status !== "cancelled" && (
            <button
              type="button"
              id="cancel-event-btn"
              disabled={!permissions.canManageEventsAndMenus}
              onClick={onOpenCancelEventModal}
              style={{
                padding: "7px 13px",
                borderRadius: "8px",
                background: "rgba(239, 68, 68, 0.16)",
                color: "#FCA5A5",
                border: "1px solid rgba(248, 113, 113, 0.4)",
                fontSize: "11px",
                fontWeight: 700,
              }}
            >
              Cancel Event
            </button>
          )}

          <button
            type="button"
            id="save-event-btn"
            disabled={!permissions.canManageEventsAndMenus}
            onClick={handleSaveDraft}
            style={{
              padding: "8px 14px",
              borderRadius: "9px",
              background: "rgba(79, 70, 229, 0.22)",
              color: "#C7D2FE",
              border: "1px solid rgba(129, 140, 248, 0.45)",
              fontSize: "11px",
              fontWeight: 700,
              display: "inline-flex",
              alignItems: "center",
              gap: "6px",
            }}
          >
            <Save size={13} />
            Save Draft
          </button>
        </div>
      </div>

      {/* Multi-Step Progress Indicator */}
      <nav
        id="event-wizard-progress-bar"
        aria-label="Event Creation Steps"
        style={{
          padding: "14px 16px",
          borderRadius: "14px",
          background: "#0F172A",
          border: "1px solid rgba(148, 163, 184, 0.18)",
        }}
      >
        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(155px, 1fr))",
            gap: "10px",
          }}
        >
          {EVENT_WIZARD_STEPS.map((step, idx) => {
            const isCurrent = step.id === currentStep;
            const isCompleted = readiness.stepStatus[step.id] === "complete";
            const isPast = idx < currentStepIndex;

            return (
              <button
                key={step.id}
                type="button"
                id={`wizard-progress-step-${step.id}`}
                aria-current={isCurrent ? "step" : undefined}
                onClick={() => handleStepSelect(step.id)}
                style={{
                  display: "flex",
                  alignItems: "center",
                  gap: "10px",
                  padding: "10px 12px",
                  borderRadius: "11px",
                  textAlign: "left",
                  cursor: "pointer",
                  transition: "all 0.15s ease",
                  background: isCurrent
                    ? "linear-gradient(135deg, rgba(79, 70, 229, 0.28) 0%, rgba(16, 185, 129, 0.18) 100%)"
                    : isCompleted
                    ? "rgba(16, 185, 129, 0.08)"
                    : "rgba(15, 23, 42, 0.7)",
                  border: isCurrent
                    ? "1px solid #818CF8"
                    : isCompleted
                    ? "1px solid rgba(52, 211, 153, 0.35)"
                    : "1px solid rgba(148, 163, 184, 0.18)",
                }}
              >
                <div
                  style={{
                    width: "28px",
                    height: "28px",
                    borderRadius: "999px",
                    flexShrink: 0,
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    fontSize: "12px",
                    fontWeight: 800,
                    background: isCurrent
                      ? "linear-gradient(135deg, #6366F1 0%, #10B981 100%)"
                      : isCompleted
                      ? "#10B981"
                      : "rgba(148, 163, 184, 0.18)",
                    color: isCurrent || isCompleted ? "#020617" : "#CBD5E1",
                  }}
                >
                  {isCompleted && !isCurrent ? (
                    <Check size={14} strokeWidth={3} />
                  ) : (
                    step.stepNumber
                  )}
                </div>

                <div style={{ minWidth: 0, flex: 1 }}>
                  <div
                    style={{
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "space-between",
                      gap: "4px",
                    }}
                  >
                    <span
                      style={{
                        fontSize: "12px",
                        fontWeight: 800,
                        color: isCurrent
                          ? "#F8FAFC"
                          : isCompleted
                          ? "#A7F3D0"
                          : "#CBD5E1",
                        whiteSpace: "nowrap",
                        overflow: "hidden",
                        textOverflow: "ellipsis",
                      }}
                    >
                      {step.stepNumber}. {step.title}
                    </span>
                  </div>
                  <span
                    style={{
                      display: "block",
                      fontSize: "10px",
                      color: isCurrent
                        ? "#C7D2FE"
                        : isCompleted
                        ? "#34D399"
                        : isPast
                        ? "#FBBF24"
                        : "#64748B",
                      whiteSpace: "nowrap",
                      overflow: "hidden",
                      textOverflow: "ellipsis",
                    }}
                  >
                    {isCurrent
                      ? "Current Stage"
                      : isCompleted
                      ? "Completed"
                      : "Needs review"}
                  </span>
                </div>
              </button>
            );
          })}
        </div>
      </nav>

      {wizardBannerError && (
        <div
          id="wizard-validation-alert"
          role="alert"
          style={{
            padding: "12px 16px",
            borderRadius: "10px",
            background: "rgba(239, 68, 68, 0.15)",
            border: "1px solid rgba(248, 113, 113, 0.45)",
            color: "#FCA5A5",
            fontSize: "12px",
            display: "flex",
            alignItems: "center",
            gap: "10px",
          }}
        >
          <AlertCircle size={16} />
          <span>{wizardBannerError}</span>
        </div>
      )}

      {/* Main Responsive Two-Column Workspace: Left Form + Right Contextual Preview */}
      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(340px, 1fr))",
          gap: "20px",
          alignItems: "start",
        }}
      >
        {/* LEFT COLUMN: Active Step Form */}
        <form
          id="event-wizard-form"
          onSubmit={(e) => {
            e.preventDefault();
            handleSaveDraft();
          }}
          style={{
            background: "#0F172A",
            border: "1px solid rgba(148, 163, 184, 0.2)",
            borderRadius: "14px",
            padding: "20px",
            display: "flex",
            flexDirection: "column",
            gap: "16px",
          }}
        >
          {/* STEP 1: BASIC EVENT INFORMATION */}
          {currentStep === "basic_details" && (
            <div
              id="wizard-step-basic_details"
              style={{ display: "flex", flexDirection: "column", gap: "14px" }}
            >
              <div>
                <span
                  style={{
                    fontSize: "10px",
                    fontWeight: 800,
                    color: "#818CF8",
                    textTransform: "uppercase",
                    letterSpacing: "0.7px",
                  }}
                >
                  Step 1 of 5 · Basic Event Information
                </span>
                <h3
                  style={{
                    margin: "3px 0 4px",
                    fontSize: "17px",
                    fontWeight: 800,
                    color: "#F8FAFC",
                  }}
                >
                  Event Identity, Category, Artwork & Organizer Details
                </h3>
                <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
                  Configure the core public profile, promotional flyer, and
                  verified organizer contact channels for your event.
                </p>
              </div>

              <div
                style={{
                  display: "grid",
                  gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
                  gap: "12px",
                }}
              >
                <div>
                  <label htmlFor="wizard-event-title-input" style={labelStyle}>
                    Event Name *
                  </label>
                  <input
                    id="wizard-event-title-input"
                    type="text"
                    value={eventDetails.title}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) => updateField("title", e.target.value)}
                    placeholder="e.g., Kingston Harbour Sunday Fish Fry"
                    style={inputStyle}
                  />
                  {fieldErrorText(basicValidation.fieldErrors.title)}
                </div>

                <div>
                  <label
                    htmlFor="wizard-event-category-select"
                    style={labelStyle}
                  >
                    Event Category *
                  </label>
                  <select
                    id="wizard-event-category-select"
                    value={eventDetails.category || "Seafood & Fish Fry"}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) => updateField("category", e.target.value)}
                    style={inputStyle}
                  >
                    {SUPPORTED_EVENT_CATEGORIES.map((cat) => (
                      <option key={cat} value={cat}>
                        {cat}
                      </option>
                    ))}
                  </select>
                  {fieldErrorText(basicValidation.fieldErrors.category)}
                </div>
              </div>

              <div>
                <label htmlFor="wizard-event-tagline-input" style={labelStyle}>
                  Headline Tagline / Subtitle
                </label>
                <input
                  id="wizard-event-tagline-input"
                  type="text"
                  value={eventDetails.tagline}
                  disabled={!permissions.canManageEventsAndMenus}
                  onChange={(e) => updateField("tagline", e.target.value)}
                  placeholder="Authentic Escovitch, Roast Breadfruit & Live Sound System"
                  style={inputStyle}
                />
              </div>

              <div>
                <label
                  htmlFor="wizard-event-description-input"
                  style={labelStyle}
                >
                  Event Description *
                </label>
                <textarea
                  id="wizard-event-description-input"
                  rows={3}
                  value={eventDetails.description}
                  disabled={!permissions.canManageEventsAndMenus}
                  onChange={(e) => updateField("description", e.target.value)}
                  placeholder="Describe the experience, entertainment lineup, culinary highlights, and community cause..."
                  style={{
                    ...inputStyle,
                    height: "auto",
                    padding: "10px 12px",
                  }}
                />
                {fieldErrorText(basicValidation.fieldErrors.description)}
              </div>

              {/* Event Images / Promotional Flyer Upload */}
              <div
                style={{
                  padding: "16px",
                  borderRadius: "12px",
                  background: "#0B1120",
                  border: "1px solid rgba(148, 163, 184, 0.22)",
                }}
              >
                <div
                  style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    marginBottom: "10px",
                    flexWrap: "wrap",
                    gap: "8px",
                  }}
                >
                  <div>
                    <span
                      style={{
                        fontSize: "11px",
                        fontWeight: 800,
                        color: "#E2E8F0",
                        display: "flex",
                        alignItems: "center",
                        gap: "6px",
                      }}
                    >
                      <ImageIcon size={14} color="#818CF8" />
                      Event Promotional Flyer & Cover Image
                    </span>
                    <span style={{ fontSize: "10px", color: "#94A3B8" }}>
                      Server-validated · Safe UUID filenames · Permitted: JPG,
                      PNG, WebP · Max 5 MB · Min 300×200px
                    </span>
                  </div>
                </div>

                {flyerWarningToast && (
                  <div
                    role="alert"
                    style={{
                      marginBottom: "10px",
                      padding: "9px 12px",
                      borderRadius: "8px",
                      background: "rgba(245, 158, 11, 0.16)",
                      border: "1px solid rgba(251, 191, 36, 0.4)",
                      color: "#FDE68A",
                      fontSize: "11px",
                    }}
                  >
                    {flyerWarningToast}
                  </div>
                )}

                {preSaveConfirmFlyer && (
                  <div
                    id="flyer-presave-card"
                    style={{
                      marginBottom: "12px",
                      padding: "12px",
                      borderRadius: "10px",
                      background: "rgba(16, 185, 129, 0.12)",
                      border: "1px solid rgba(52, 211, 153, 0.4)",
                    }}
                  >
                    <strong
                      style={{
                        fontSize: "12px",
                        color: "#34D399",
                        display: "block",
                      }}
                    >
                      Confirm Flyer Upload ({preSaveConfirmFlyer.fileName})
                    </strong>
                    <span style={{ fontSize: "10px", color: "#CBD5E1" }}>
                      {preSaveConfirmFlyer.width}×{preSaveConfirmFlyer.height}px
                      · {Math.round(preSaveConfirmFlyer.sizeBytes / 1024)} KB ·
                      Storage: {preSaveConfirmFlyer.storagePath}
                    </span>
                    <div
                      style={{ display: "flex", gap: "8px", marginTop: "8px" }}
                    >
                      <button
                        type="button"
                        id="confirm-flyer-save-btn"
                        onClick={onConfirmPreSavedFlyer}
                        style={{
                          padding: "5px 12px",
                          borderRadius: "6px",
                          background: "#10B981",
                          color: "#052E16",
                          border: "none",
                          fontSize: "11px",
                          fontWeight: 800,
                        }}
                      >
                        Save Flyer to Event
                      </button>
                      <button
                        type="button"
                        onClick={onCancelPreSavedFlyer}
                        style={{
                          padding: "5px 10px",
                          borderRadius: "6px",
                          background: "#334155",
                          color: "#CBD5E1",
                          border: "none",
                          fontSize: "11px",
                        }}
                      >
                        Discard
                      </button>
                    </div>
                  </div>
                )}

                {activeFlyerDisplayUrl ? (
                  <div>
                    <div
                      style={{
                        borderRadius: "10px",
                        overflow: "hidden",
                        border: "1px solid rgba(148, 163, 184, 0.25)",
                        maxHeight: "200px",
                        background: "#020617",
                      }}
                    >
                      <img
                        id="active-flyer-img"
                        src={activeFlyerDisplayUrl}
                        alt={eventDetails.title}
                        onError={(e) => {
                          e.currentTarget.src = "/images/escovitch_fish.jpg";
                        }}
                        style={{
                          width: "100%",
                          height: "190px",
                          objectFit: "cover",
                          display: "block",
                        }}
                      />
                    </div>

                    <div
                      style={{
                        marginTop: "10px",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                        gap: "8px",
                        flexWrap: "wrap",
                      }}
                    >
                      <label
                        style={{
                          padding: "6px 12px",
                          borderRadius: "7px",
                          background: "rgba(99, 102, 241, 0.18)",
                          border: "1px solid rgba(129, 140, 248, 0.35)",
                          color: "#A5B4FC",
                          fontSize: "11px",
                          fontWeight: 700,
                          cursor: "pointer",
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "5px",
                        }}
                      >
                        <Upload size={12} />
                        Replace Flyer
                        <input
                          id="flyer-replace-input"
                          type="file"
                          accept="image/jpeg,image/png,image/webp"
                          onChange={onFlyerUpload}
                          style={{ display: "none" }}
                        />
                      </label>

                      <button
                        type="button"
                        id="remove-flyer-btn"
                        onClick={onRemoveFlyer}
                        style={{
                          padding: "6px 12px",
                          borderRadius: "7px",
                          background: "rgba(239, 68, 68, 0.16)",
                          border: "1px solid rgba(248, 113, 113, 0.35)",
                          color: "#FCA5A5",
                          fontSize: "11px",
                          fontWeight: 700,
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "5px",
                        }}
                      >
                        <Trash2 size={12} />
                        Remove Flyer
                      </button>
                    </div>
                  </div>
                ) : (
                  <label
                    style={{
                      padding: "22px 16px",
                      borderRadius: "10px",
                      border: "2px dashed rgba(148, 163, 184, 0.3)",
                      display: "flex",
                      flexDirection: "column",
                      alignItems: "center",
                      gap: "6px",
                      cursor: "pointer",
                      color: "#CBD5E1",
                      textAlign: "center",
                    }}
                  >
                    <Upload size={20} color="#818CF8" />
                    <strong style={{ fontSize: "12px" }}>
                      Upload Official Event Flyer
                    </strong>
                    <span style={{ fontSize: "10px", color: "#94A3B8" }}>
                      JPG, PNG or WebP up to 5MB (Min 300×200px)
                    </span>
                    <input
                      id="flyer-file-input"
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      onChange={onFlyerUpload}
                      style={{ display: "none" }}
                    />
                  </label>
                )}
              </div>

              {/* Organizer Information */}
              <div
                style={{
                  paddingTop: "8px",
                  borderTop: "1px solid rgba(148, 163, 184, 0.15)",
                }}
              >
                <span
                  style={{
                    fontSize: "11px",
                    fontWeight: 800,
                    color: "#34D399",
                    textTransform: "uppercase",
                    display: "block",
                    marginBottom: "10px",
                  }}
                >
                  Organizer Information & Attendee Support Contacts
                </span>

                <div
                  style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))",
                    gap: "10px",
                  }}
                >
                  <div>
                    <label
                      htmlFor="wizard-organizer-name-input"
                      style={labelStyle}
                    >
                      Host / Organization Name *
                    </label>
                    <input
                      id="wizard-organizer-name-input"
                      type="text"
                      value={eventDetails.organizerName}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) =>
                        updateField("organizerName", e.target.value)
                      }
                      style={inputStyle}
                    />
                    {fieldErrorText(basicValidation.fieldErrors.organizerName)}
                  </div>

                  <div>
                    <label
                      htmlFor="wizard-organizer-phone-input"
                      style={labelStyle}
                    >
                      Primary Contact Phone *
                    </label>
                    <input
                      id="wizard-organizer-phone-input"
                      type="text"
                      value={eventDetails.organizerPhone}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) =>
                        updateField("organizerPhone", e.target.value)
                      }
                      style={inputStyle}
                    />
                    {fieldErrorText(basicValidation.fieldErrors.organizerPhone)}
                  </div>

                  <div>
                    <label
                      htmlFor="wizard-organizer-whatsapp-input"
                      style={labelStyle}
                    >
                      WhatsApp Support Number
                    </label>
                    <input
                      id="wizard-organizer-whatsapp-input"
                      type="text"
                      value={eventDetails.organizerWhatsApp}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) =>
                        updateField("organizerWhatsApp", e.target.value)
                      }
                      style={inputStyle}
                    />
                  </div>

                  <div>
                    <label
                      htmlFor="wizard-organizer-email-input"
                      style={labelStyle}
                    >
                      Organizer Support Email
                    </label>
                    <input
                      id="wizard-organizer-email-input"
                      type="email"
                      value={eventDetails.organizerEmail}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) =>
                        updateField("organizerEmail", e.target.value)
                      }
                      style={inputStyle}
                    />
                    {fieldErrorText(basicValidation.fieldErrors.organizerEmail)}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* STEP 2: DATE AND LOCATION */}
          {currentStep === "date_location" && (
            <div
              id="wizard-step-date_location"
              style={{ display: "flex", flexDirection: "column", gap: "14px" }}
            >
              <div>
                <span
                  style={{
                    fontSize: "10px",
                    fontWeight: 800,
                    color: "#38BDF8",
                    textTransform: "uppercase",
                    letterSpacing: "0.7px",
                  }}
                >
                  Step 2 of 5 · Date, Timezone & Venue Location
                </span>
                <h3
                  style={{
                    margin: "3px 0 4px",
                    fontSize: "17px",
                    fontWeight: 800,
                    color: "#F8FAFC",
                  }}
                >
                  Schedule, IANA Timezone & Physical Venue Coordinates
                </h3>
                <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
                  All order cutoffs and ticket windows are anchored to the
                  event&apos;s IANA timezone and persisted in UTC for accurate
                  display across regions.
                </p>
              </div>

              <div
                style={{
                  display: "grid",
                  gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))",
                  gap: "12px",
                }}
              >
                <div>
                  <label htmlFor="wizard-event-date-input" style={labelStyle}>
                    Event Date Display Label *
                  </label>
                  <input
                    id="wizard-event-date-input"
                    type="text"
                    value={eventDetails.dateStr}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) => updateField("dateStr", e.target.value)}
                    placeholder="Sunday, November 22, 2026"
                    style={inputStyle}
                  />
                  {fieldErrorText(dateLocationValidation.fieldErrors.dateStr)}
                </div>

                <div>
                  <label
                    htmlFor="wizard-event-date-iso-input"
                    style={labelStyle}
                  >
                    Calendar Date (ISO YYYY-MM-DD)
                  </label>
                  <input
                    id="wizard-event-date-iso-input"
                    type="date"
                    value={eventDetails.eventDateIso || "2026-11-22"}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) => {
                      const iso = e.target.value;
                      const parsed = new Date(`${iso}T12:00:00`);
                      const formattedLabel = !Number.isNaN(parsed.getTime())
                        ? parsed.toLocaleDateString("en-US", {
                            weekday: "long",
                            month: "long",
                            day: "numeric",
                            year: "numeric",
                          })
                        : eventDetails.dateStr;
                      onUpdateEventDetails({
                        ...eventDetails,
                        eventDateIso: iso,
                        dateStr: formattedLabel,
                      });
                    }}
                    style={inputStyle}
                  />
                </div>
              </div>

              <div
                style={{
                  display: "grid",
                  gridTemplateColumns: "repeat(auto-fit, minmax(160px, 1fr))",
                  gap: "12px",
                }}
              >
                <div>
                  <label htmlFor="wizard-start-time-input" style={labelStyle}>
                    Start Time (Local)
                  </label>
                  <input
                    id="wizard-start-time-input"
                    type="time"
                    value={eventDetails.startTime || "12:00"}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) =>
                      updateField("startTime", e.target.value)
                    }
                    style={inputStyle}
                  />
                </div>

                <div>
                  <label htmlFor="wizard-end-time-input" style={labelStyle}>
                    End Time (Local)
                  </label>
                  <input
                    id="wizard-end-time-input"
                    type="time"
                    value={eventDetails.endTime || "20:00"}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) => updateField("endTime", e.target.value)}
                    style={inputStyle}
                  />
                  {fieldErrorText(dateLocationValidation.fieldErrors.endTime)}
                </div>

                <div>
                  <label htmlFor="wizard-time-str-input" style={labelStyle}>
                    Serving / Gate Hours Label *
                  </label>
                  <input
                    id="wizard-time-str-input"
                    type="text"
                    value={eventDetails.timeStr}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) => updateField("timeStr", e.target.value)}
                    placeholder="12:00 PM – 8:00 PM"
                    style={inputStyle}
                  />
                  {fieldErrorText(dateLocationValidation.fieldErrors.timeStr)}
                </div>
              </div>

              {/* IANA Timezone + Advance Order Cutoff */}
              <div
                style={{
                  padding: "14px",
                  borderRadius: "12px",
                  background: "#0B1120",
                  border: "1px solid rgba(56, 189, 248, 0.28)",
                  display: "flex",
                  flexDirection: "column",
                  gap: "10px",
                }}
              >
                <div
                  style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
                    gap: "12px",
                  }}
                >
                  <div>
                    <label htmlFor="wizard-timezone-select" style={labelStyle}>
                      Applicable Event Timezone (IANA) *
                    </label>
                    <select
                      id="wizard-timezone-select"
                      value={eventDetails.timezone || "America/Jamaica"}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) => updateField("timezone", e.target.value)}
                      style={inputStyle}
                    >
                      {SUPPORTED_EVENT_TIMEZONES.map((tz) => (
                        <option key={tz.id} value={tz.id}>
                          {tz.id} — {tz.label} ({tz.utcOffsetLabel})
                        </option>
                      ))}
                    </select>
                    {fieldErrorText(
                      dateLocationValidation.fieldErrors.timezone
                    )}
                  </div>

                  <div>
                    <label htmlFor="wizard-deadline-input" style={labelStyle}>
                      Advance Order Cutoff Timestamp *
                    </label>
                    <input
                      id="wizard-deadline-input"
                      type="text"
                      value={eventDetails.advanceOrderDeadline}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) =>
                        updateField("advanceOrderDeadline", e.target.value)
                      }
                      placeholder="2026-11-20T20:00:00-05:00"
                      style={inputStyle}
                    />
                    {fieldErrorText(
                      dateLocationValidation.fieldErrors.advanceOrderDeadline
                    )}
                  </div>
                </div>

                <div
                  id="wizard-timezone-preview-pill"
                  style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    flexWrap: "wrap",
                    gap: "8px",
                    fontSize: "11px",
                    color: "#BAE6FD",
                    background: "rgba(14, 165, 233, 0.1)",
                    padding: "8px 12px",
                    borderRadius: "8px",
                  }}
                >
                  <span>
                    <Globe
                      size={12}
                      style={{ verticalAlign: "middle", marginRight: "6px" }}
                    />
                    Local Event Display:{" "}
                    <strong>
                      {dateLocationValidation.localDeadlineDisplay}
                    </strong>{" "}
                    ({eventDetails.timezone || "America/Jamaica"})
                  </span>
                  {dateLocationValidation.utcDeadlineIso && (
                    <span style={{ color: "#94A3B8" }}>
                      UTC Stored:{" "}
                      <code>{dateLocationValidation.utcDeadlineIso}</code>
                    </span>
                  )}
                </div>
              </div>

              {/* Venue & Address */}
              <div
                style={{
                  display: "grid",
                  gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
                  gap: "12px",
                }}
              >
                <div>
                  <label htmlFor="wizard-venue-name-input" style={labelStyle}>
                    Venue Name *
                  </label>
                  <input
                    id="wizard-venue-name-input"
                    type="text"
                    value={eventDetails.venueName}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) => updateField("venueName", e.target.value)}
                    placeholder="Kingston Waterfront Pier"
                    style={inputStyle}
                  />
                  {fieldErrorText(dateLocationValidation.fieldErrors.venueName)}
                </div>

                <div>
                  <label htmlFor="wizard-coordinates-input" style={labelStyle}>
                    Geographic Coordinates
                  </label>
                  <input
                    id="wizard-coordinates-input"
                    type="text"
                    value={eventDetails.coordinates}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) => updateField("coordinates", e.target.value)}
                    placeholder="17.9647° N, 76.7936° W"
                    style={inputStyle}
                  />
                </div>
              </div>

              <div>
                <label htmlFor="wizard-venue-address-input" style={labelStyle}>
                  Full Street & Parish / City Address *
                </label>
                <input
                  id="wizard-venue-address-input"
                  type="text"
                  value={eventDetails.venueAddress}
                  disabled={!permissions.canManageEventsAndMenus}
                  onChange={(e) => updateField("venueAddress", e.target.value)}
                  placeholder="Ocean Boulevard, Downtown Kingston, Jamaica"
                  style={inputStyle}
                />
                {fieldErrorText(
                  dateLocationValidation.fieldErrors.venueAddress
                )}
              </div>

              <div>
                <label
                  htmlFor="wizard-pickup-instructions-input"
                  style={labelStyle}
                >
                  Pickup & Gate Check-In Instructions
                </label>
                <textarea
                  id="wizard-pickup-instructions-input"
                  rows={2}
                  value={eventDetails.pickupInstructions}
                  disabled={!permissions.canManageEventsAndMenus}
                  onChange={(e) =>
                    updateField("pickupInstructions", e.target.value)
                  }
                  placeholder="Present your QR ticket at Tent B for express meal handover..."
                  style={{
                    ...inputStyle,
                    height: "auto",
                    padding: "10px 12px",
                  }}
                />
              </div>
            </div>
          )}

          {/* STEP 3: TICKETS */}
          {currentStep === "tickets" && (
            <div
              id="wizard-step-tickets"
              style={{ display: "flex", flexDirection: "column", gap: "14px" }}
            >
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  flexWrap: "wrap",
                  gap: "10px",
                }}
              >
                <div>
                  <span
                    style={{
                      fontSize: "10px",
                      fontWeight: 800,
                      color: "#FBBF24",
                      textTransform: "uppercase",
                      letterSpacing: "0.7px",
                    }}
                  >
                    Step 3 of 5 · Tickets & Pricing Rules
                  </span>
                  <h3
                    style={{
                      margin: "3px 0 4px",
                      fontSize: "17px",
                      fontWeight: 800,
                      color: "#F8FAFC",
                    }}
                  >
                    Ticket Tiers, Prices, Quantity Limits & Sale Windows
                  </h3>
                  <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
                    Configure admission and package tiers. All pricing and
                    capacity rules are validated on both client and server.
                  </p>
                </div>

                <button
                  type="button"
                  id="wizard-add-ticket-tier-btn"
                  disabled={!permissions.canManageEventsAndMenus}
                  onClick={handleAddTicketTier}
                  style={{
                    padding: "8px 14px",
                    borderRadius: "9px",
                    background: "rgba(99, 102, 241, 0.22)",
                    border: "1px solid rgba(129, 140, 248, 0.45)",
                    color: "#C7D2FE",
                    fontSize: "11px",
                    fontWeight: 800,
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "6px",
                    cursor: "pointer",
                  }}
                >
                  <Plus size={14} />
                  Add Ticket Tier
                </button>
              </div>

              {fieldErrorText(ticketsValidation.fieldErrors.ticketTiers)}

              <div
                style={{
                  display: "flex",
                  flexDirection: "column",
                  gap: "12px",
                }}
              >
                {ticketTiers.map((tier, idx) => {
                  const tierErrs =
                    ticketsValidation.tierErrors[tier.id] || [];
                  return (
                    <div
                      key={tier.id}
                      id={`wizard-ticket-tier-card-${idx}`}
                      style={{
                        padding: "14px",
                        borderRadius: "12px",
                        background: "#0B1120",
                        border:
                          tierErrs.length > 0
                            ? "1px solid rgba(248, 113, 113, 0.5)"
                            : "1px solid rgba(148, 163, 184, 0.22)",
                        display: "flex",
                        flexDirection: "column",
                        gap: "10px",
                      }}
                    >
                      <div
                        style={{
                          display: "flex",
                          alignItems: "center",
                          justifyContent: "space-between",
                          gap: "10px",
                          flexWrap: "wrap",
                        }}
                      >
                        <div
                          style={{
                            display: "flex",
                            alignItems: "center",
                            gap: "8px",
                          }}
                        >
                          <Ticket size={14} color="#FBBF24" />
                          <strong
                            style={{ fontSize: "13px", color: "#F8FAFC" }}
                          >
                            Tier #{idx + 1}: {tier.name}
                          </strong>
                          <span
                            style={{
                              padding: "2px 8px",
                              borderRadius: "999px",
                              fontSize: "10px",
                              fontWeight: 700,
                              background: "rgba(99, 102, 241, 0.18)",
                              color: "#A5B4FC",
                            }}
                          >
                            {tier.category}
                          </span>
                        </div>

                        <div
                          style={{
                            display: "flex",
                            alignItems: "center",
                            gap: "10px",
                          }}
                        >
                          <label
                            style={{
                              display: "inline-flex",
                              alignItems: "center",
                              gap: "5px",
                              fontSize: "11px",
                              color: "#CBD5E1",
                              cursor: "pointer",
                            }}
                          >
                            <input
                              type="checkbox"
                              checked={tier.isActive !== false}
                              disabled={!permissions.canManageEventsAndMenus}
                              onChange={(e) =>
                                handleUpdateTier(tier.id, {
                                  isActive: e.target.checked,
                                })
                              }
                            />
                            Active
                          </label>

                          <button
                            type="button"
                            disabled={
                              !permissions.canManageEventsAndMenus ||
                              ticketTiers.length <= 1
                            }
                            onClick={() => handleRemoveTicketTier(tier.id)}
                            style={{
                              padding: "4px 8px",
                              borderRadius: "6px",
                              background: "rgba(239, 68, 68, 0.14)",
                              border: "1px solid rgba(248, 113, 113, 0.3)",
                              color: "#FCA5A5",
                              fontSize: "10px",
                              cursor: "pointer",
                            }}
                          >
                            Remove
                          </button>
                        </div>
                      </div>

                      <div
                        style={{
                          display: "grid",
                          gridTemplateColumns:
                            "repeat(auto-fit, minmax(150px, 1fr))",
                          gap: "10px",
                        }}
                      >
                        <div>
                          <label style={labelStyle}>Tier Name *</label>
                          <input
                            type="text"
                            value={tier.name}
                            disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) =>
                              handleUpdateTier(tier.id, {
                                name: e.target.value,
                              })
                            }
                            style={inputStyle}
                          />
                        </div>

                        <div>
                          <label style={labelStyle}>Category</label>
                          <select
                            value={tier.category}
                            disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) =>
                              handleUpdateTier(tier.id, {
                                category: e.target
                                  .value as EventTicketTierCategory,
                              })
                            }
                            style={inputStyle}
                          >
                            <option value="General">General</option>
                            <option value="VIP">VIP</option>
                            <option value="Early Bird">Early Bird</option>
                            <option value="Group">Group</option>
                            <option value="Community">Community</option>
                          </select>
                        </div>

                        <div>
                          <label style={labelStyle}>Price (USD) *</label>
                          <input
                            type="number"
                            min={0}
                            step="0.5"
                            value={tier.priceUSD}
                            disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) =>
                              handleUpdateTier(tier.id, {
                                priceUSD: Number(e.target.value),
                              })
                            }
                            style={inputStyle}
                          />
                        </div>

                        <div>
                          <label style={labelStyle}>Price (JMD) *</label>
                          <input
                            type="number"
                            min={0}
                            step="50"
                            value={tier.priceJMD}
                            disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) =>
                              handleUpdateTier(tier.id, {
                                priceJMD: Math.round(Number(e.target.value)),
                              })
                            }
                            style={inputStyle}
                          />
                        </div>
                      </div>

                      <div
                        style={{
                          display: "grid",
                          gridTemplateColumns:
                            "repeat(auto-fit, minmax(145px, 1fr))",
                          gap: "10px",
                        }}
                      >
                        <div>
                          <label style={labelStyle}>Quantity Limit *</label>
                          <input
                            type="number"
                            min={1}
                            step={1}
                            value={tier.availableStock}
                            disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) =>
                              handleUpdateTier(tier.id, {
                                availableStock: Math.round(
                                  Number(e.target.value)
                                ),
                              })
                            }
                            style={inputStyle}
                          />
                        </div>

                        <div>
                          <label style={labelStyle}>Max Per Customer *</label>
                          <input
                            type="number"
                            min={1}
                            step={1}
                            value={tier.maxPerCustomer}
                            disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) =>
                              handleUpdateTier(tier.id, {
                                maxPerCustomer: Math.round(
                                  Number(e.target.value)
                                ),
                              })
                            }
                            style={inputStyle}
                          />
                        </div>

                        <div>
                          <label style={labelStyle}>Sale Starts</label>
                          <input
                            type="datetime-local"
                            value={tier.saleStartIso || "2026-10-01T00:00"}
                            disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) =>
                              handleUpdateTier(tier.id, {
                                saleStartIso: e.target.value,
                              })
                            }
                            style={inputStyle}
                          />
                        </div>

                        <div>
                          <label style={labelStyle}>Sale Ends</label>
                          <input
                            type="datetime-local"
                            value={tier.saleEndIso || "2026-11-22T18:00"}
                            disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) =>
                              handleUpdateTier(tier.id, {
                                saleEndIso: e.target.value,
                              })
                            }
                            style={inputStyle}
                          />
                        </div>
                      </div>

                      {tierErrs.map((err, i) => (
                        <div key={i}>{fieldErrorText(err)}</div>
                      ))}
                    </div>
                  );
                })}
              </div>

              {/* Packaging & Takeout Fee Policy */}
              <div
                style={{
                  padding: "14px",
                  borderRadius: "12px",
                  background: "#0B1120",
                  border: "1px solid rgba(148, 163, 184, 0.2)",
                  display: "grid",
                  gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))",
                  gap: "12px",
                }}
              >
                <div>
                  <label htmlFor="wizard-packaging-usd-input" style={labelStyle}>
                    Order Packaging Fee (USD)
                  </label>
                  <input
                    id="wizard-packaging-usd-input"
                    type="number"
                    min={0}
                    step="0.25"
                    value={eventDetails.packagingFeeUSD}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) =>
                      updateField("packagingFeeUSD", Number(e.target.value))
                    }
                    style={inputStyle}
                  />
                  {fieldErrorText(
                    ticketsValidation.fieldErrors.packagingFeeUSD
                  )}
                </div>

                <div>
                  <label htmlFor="wizard-packaging-jmd-input" style={labelStyle}>
                    Order Packaging Fee (JMD)
                  </label>
                  <input
                    id="wizard-packaging-jmd-input"
                    type="number"
                    min={0}
                    step="25"
                    value={eventDetails.packagingFeeJMD}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) =>
                      updateField(
                        "packagingFeeJMD",
                        Math.round(Number(e.target.value))
                      )
                    }
                    style={inputStyle}
                  />
                  {fieldErrorText(
                    ticketsValidation.fieldErrors.packagingFeeJMD
                  )}
                </div>
              </div>
            </div>
          )}

          {/* STEP 4: FOOD MENU */}
          {currentStep === "food" && (
            <div
              id="wizard-step-food"
              style={{ display: "flex", flexDirection: "column", gap: "14px" }}
            >
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  flexWrap: "wrap",
                  gap: "10px",
                }}
              >
                <div>
                  <span
                    style={{
                      fontSize: "10px",
                      fontWeight: 800,
                      color: "#34D399",
                      textTransform: "uppercase",
                      letterSpacing: "0.7px",
                    }}
                  >
                    Step 4 of 5 · Food Menu & Meal Customizations
                  </span>
                  <h3
                    style={{
                      margin: "3px 0 4px",
                      fontSize: "17px",
                      fontWeight: 800,
                      color: "#F8FAFC",
                    }}
                  >
                    Meal Choices, Add-Ons, Item Prices, Stock & Dish Photos
                  </h3>
                  <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
                    Attach and customize the culinary menu for advance ordering
                    and kitchen prep tracking.
                  </p>
                </div>

                <label
                  style={{
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "8px",
                    padding: "8px 12px",
                    borderRadius: "10px",
                    background: supportsFoodOrdering
                      ? "rgba(16, 185, 129, 0.14)"
                      : "rgba(148, 163, 184, 0.12)",
                    border: supportsFoodOrdering
                      ? "1px solid rgba(52, 211, 153, 0.4)"
                      : "1px solid rgba(148, 163, 184, 0.25)",
                    fontSize: "11px",
                    fontWeight: 800,
                    color: supportsFoodOrdering ? "#34D399" : "#CBD5E1",
                    cursor: "pointer",
                  }}
                >
                  <input
                    id="wizard-supports-food-toggle"
                    type="checkbox"
                    checked={supportsFoodOrdering}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) =>
                      updateField("supportsFoodOrdering", e.target.checked)
                    }
                  />
                  <span>Event Supports Advance Food Ordering</span>
                </label>
              </div>

              {supportsFoodOrdering ? (
                <>
                  <div
                    style={{
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "space-between",
                      flexWrap: "wrap",
                      gap: "8px",
                    }}
                  >
                    <span style={{ fontSize: "12px", color: "#CBD5E1" }}>
                      Configured Menu Items: <strong>{menu.length}</strong>{" "}
                      dishes/items
                    </span>

                    <div style={{ display: "flex", gap: "8px" }}>
                      <button
                        type="button"
                        id="wizard-inline-add-dish-btn"
                        disabled={!permissions.canManageEventsAndMenus}
                        onClick={() =>
                          setShowInlineDishForm(!showInlineDishForm)
                        }
                        style={{
                          padding: "7px 12px",
                          borderRadius: "8px",
                          background: "#10B981",
                          color: "#052E16",
                          border: "none",
                          fontSize: "11px",
                          fontWeight: 800,
                          display: "inline-flex",
                          alignItems: "center",
                          gap: "5px",
                          cursor: "pointer",
                        }}
                      >
                        <Plus size={13} />
                        {showInlineDishForm
                          ? "Close Dish Form"
                          : "Add Menu Dish / Meal"}
                      </button>

                      {onOpenAddDishModal && (
                        <button
                          type="button"
                          disabled={!permissions.canManageEventsAndMenus}
                          onClick={onOpenAddDishModal}
                          style={{
                            padding: "7px 12px",
                            borderRadius: "8px",
                            background: "rgba(99, 102, 241, 0.2)",
                            border: "1px solid rgba(129, 140, 248, 0.4)",
                            color: "#C7D2FE",
                            fontSize: "11px",
                            fontWeight: 700,
                            cursor: "pointer",
                          }}
                        >
                          Advanced Option Builder
                        </button>
                      )}
                    </div>
                  </div>

                  {fieldErrorText(foodValidation.fieldErrors.menu)}

                  {showInlineDishForm && (
                    <div
                      id="wizard-inline-dish-creator"
                      style={{
                        padding: "14px",
                        borderRadius: "12px",
                        background: "#0B1120",
                        border: "1px solid rgba(52, 211, 153, 0.4)",
                        display: "flex",
                        flexDirection: "column",
                        gap: "10px",
                      }}
                    >
                      <strong style={{ fontSize: "13px", color: "#34D399" }}>
                        Add New Menu Dish, Meal Choice or Add-On
                      </strong>

                      <div
                        style={{
                          display: "grid",
                          gridTemplateColumns:
                            "repeat(auto-fit, minmax(160px, 1fr))",
                          gap: "10px",
                        }}
                      >
                        <div>
                          <label style={labelStyle}>Dish / Item Name *</label>
                          <input
                            id="wizard-new-dish-name"
                            type="text"
                            value={newDishName}
                            onChange={(e) => setNewDishName(e.target.value)}
                            placeholder="Whole Roast Snapper Platter"
                            style={inputStyle}
                          />
                        </div>

                        <div>
                          <label style={labelStyle}>Menu Category</label>
                          <select
                            value={newDishCategory}
                            onChange={(e) =>
                              setNewDishCategory(e.target.value as MenuCategory)
                            }
                            style={inputStyle}
                          >
                            <option value="Mains">Mains</option>
                            <option value="Sides">Sides</option>
                            <option value="Drinks">Drinks</option>
                            <option value="Desserts">Desserts</option>
                            <option value="Community Donations">
                              Community Donations
                            </option>
                          </select>
                        </div>

                        <div>
                          <label style={labelStyle}>Pricing Mode</label>
                          <select
                            value={newDishPricingType}
                            onChange={(e) =>
                              setNewDishPricingType(
                                e.target.value as MenuPricingType
                              )
                            }
                            style={inputStyle}
                          >
                            <option value="fixed">Fixed Price</option>
                            <option value="customizable">
                              Customizable Meal Choices & Add-Ons
                            </option>
                          </select>
                        </div>
                      </div>

                      <div
                        style={{
                          display: "grid",
                          gridTemplateColumns:
                            "repeat(auto-fit, minmax(130px, 1fr))",
                          gap: "10px",
                        }}
                      >
                        <div>
                          <label style={labelStyle}>Price (USD)</label>
                          <input
                            type="number"
                            min={0}
                            step="0.5"
                            value={newDishPriceUSD}
                            onChange={(e) => setNewDishPriceUSD(e.target.value)}
                            style={inputStyle}
                          />
                        </div>

                        <div>
                          <label style={labelStyle}>Price (JMD)</label>
                          <input
                            type="number"
                            min={0}
                            step="50"
                            value={newDishPriceJMD}
                            onChange={(e) => setNewDishPriceJMD(e.target.value)}
                            style={inputStyle}
                          />
                        </div>

                        <div>
                          <label style={labelStyle}>Available Portions</label>
                          <input
                            type="number"
                            min={1}
                            step={1}
                            value={newDishStock}
                            onChange={(e) => setNewDishStock(e.target.value)}
                            style={inputStyle}
                          />
                        </div>

                        <div>
                          <label style={labelStyle}>Dish Photo Upload</label>
                          <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={handleInlineDishPhotoUpload}
                            style={{
                              ...inputStyle,
                              padding: "6px 8px",
                              fontSize: "10px",
                            }}
                          />
                        </div>
                      </div>

                      {newDishPricingType === "customizable" && (
                        <div
                          style={{
                            display: "grid",
                            gridTemplateColumns: "1fr 2fr",
                            gap: "10px",
                          }}
                        >
                          <div>
                            <label style={labelStyle}>Option Group Title</label>
                            <input
                              type="text"
                              value={newDishAddOnTitle}
                              onChange={(e) =>
                                setNewDishAddOnTitle(e.target.value)
                              }
                              style={inputStyle}
                            />
                          </div>
                          <div>
                            <label style={labelStyle}>
                              Meal Choices / Add-Ons (comma-separated)
                            </label>
                            <input
                              type="text"
                              value={newDishAddOnChoices}
                              onChange={(e) =>
                                setNewDishAddOnChoices(e.target.value)
                              }
                              style={inputStyle}
                            />
                          </div>
                        </div>
                      )}

                      {dishUploadError && fieldErrorText(dishUploadError)}

                      <div
                        style={{
                          display: "flex",
                          justifyContent: "flex-end",
                          gap: "8px",
                        }}
                      >
                        <button
                          type="button"
                          id="wizard-confirm-add-dish-btn"
                          onClick={handleAddInlineDish}
                          style={{
                            padding: "7px 14px",
                            borderRadius: "8px",
                            background: "#10B981",
                            color: "#052E16",
                            border: "none",
                            fontSize: "11px",
                            fontWeight: 800,
                            cursor: "pointer",
                          }}
                        >
                          Save Dish to Event Menu
                        </button>
                      </div>
                    </div>
                  )}

                  {/* Existing Menu Items Grid */}
                  <div
                    style={{
                      display: "grid",
                      gridTemplateColumns:
                        "repeat(auto-fit, minmax(240px, 1fr))",
                      gap: "10px",
                      maxHeight: "360px",
                      overflowY: "auto",
                      paddingRight: "4px",
                    }}
                  >
                    {menu.map((item) => (
                      <div
                        key={item.id}
                        style={{
                          padding: "10px 12px",
                          borderRadius: "10px",
                          background: "#0B1120",
                          border: "1px solid rgba(148, 163, 184, 0.2)",
                          display: "flex",
                          gap: "10px",
                          alignItems: "center",
                        }}
                      >
                        <img
                          src={item.imageUrl || "/images/escovitch_fish.jpg"}
                          alt={item.name}
                          onError={(e) => {
                            e.currentTarget.src = "/images/escovitch_fish.jpg";
                          }}
                          style={{
                            width: "52px",
                            height: "52px",
                            borderRadius: "8px",
                            objectFit: "cover",
                            flexShrink: 0,
                          }}
                        />
                        <div style={{ minWidth: 0, flex: 1 }}>
                          <strong
                            style={{
                              fontSize: "12px",
                              color: "#F8FAFC",
                              display: "block",
                              whiteSpace: "nowrap",
                              overflow: "hidden",
                              textOverflow: "ellipsis",
                            }}
                          >
                            {item.name}
                          </strong>
                          <span
                            style={{
                              fontSize: "10px",
                              color: "#34D399",
                              fontWeight: 700,
                            }}
                          >
                            ${item.priceUSD.toFixed(2)} USD · J$
                            {item.priceJMD.toLocaleString()}
                          </span>
                          <span
                            style={{
                              display: "block",
                              fontSize: "10px",
                              color: "#94A3B8",
                            }}
                          >
                            {item.category} · Stock:{" "}
                            {item.availableStock ?? "Unlimited"}
                            {item.customOptionGroups &&
                            item.customOptionGroups.length > 0
                              ? ` · ${item.customOptionGroups.length} option group(s)`
                              : ""}
                          </span>
                        </div>
                        <div
                          style={{
                            display: "flex",
                            flexDirection: "column",
                            gap: "4px",
                          }}
                        >
                          {onOpenEditDishModal && (
                            <button
                              type="button"
                              disabled={!permissions.canManageEventsAndMenus}
                              onClick={() => onOpenEditDishModal(item)}
                              style={{
                                padding: "3px 8px",
                                borderRadius: "5px",
                                background: "rgba(99, 102, 241, 0.18)",
                                border: "1px solid rgba(129, 140, 248, 0.35)",
                                color: "#C7D2FE",
                                fontSize: "10px",
                                cursor: "pointer",
                              }}
                            >
                              Edit
                            </button>
                          )}
                          {onDeleteDish && (
                            <button
                              type="button"
                              disabled={!permissions.canManageEventsAndMenus}
                              onClick={() => onDeleteDish(item.id)}
                              style={{
                                padding: "3px 8px",
                                borderRadius: "5px",
                                background: "rgba(239, 68, 68, 0.14)",
                                border: "1px solid rgba(248, 113, 113, 0.3)",
                                color: "#FCA5A5",
                                fontSize: "10px",
                                cursor: "pointer",
                              }}
                            >
                              Remove
                            </button>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </>
              ) : (
                <div
                  style={{
                    padding: "18px",
                    borderRadius: "12px",
                    background: "rgba(15, 23, 42, 0.6)",
                    border: "1px dashed rgba(148, 163, 184, 0.3)",
                    color: "#94A3B8",
                    fontSize: "12px",
                  }}
                >
                  Food ordering is currently disabled for this event. Attendees
                  will purchase admission tickets only. Toggle{" "}
                  <strong>Event Supports Advance Food Ordering</strong> above to
                  attach a menu.
                </div>
              )}
            </div>
          )}

          {/* STEP 5: REVIEW & PUBLISH CHECKLIST */}
          {currentStep === "review" && (
            <div
              id="wizard-step-review"
              style={{ display: "flex", flexDirection: "column", gap: "14px" }}
            >
              <div>
                <span
                  style={{
                    fontSize: "10px",
                    fontWeight: 800,
                    color: "#A78BFA",
                    textTransform: "uppercase",
                    letterSpacing: "0.7px",
                  }}
                >
                  Step 5 of 5 · Review & Publish Readiness
                </span>
                <h3
                  style={{
                    margin: "3px 0 4px",
                    fontSize: "17px",
                    fontWeight: 800,
                    color: "#F8FAFC",
                  }}
                >
                  Pre-Publication Checklist, Policies & Box Office Link
                </h3>
                <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
                  Verify all required event details, timezone schedule, ticket
                  tiers, and menu items before publishing live.
                </p>
              </div>

              {/* Readiness Checklist */}
              <div
                id="wizard-readiness-checklist"
                style={{
                  display: "flex",
                  flexDirection: "column",
                  gap: "8px",
                }}
              >
                {readiness.checklist.map((item) => (
                  <div
                    key={item.id}
                    style={{
                      padding: "12px 14px",
                      borderRadius: "10px",
                      background: item.passed
                        ? "rgba(16, 185, 129, 0.08)"
                        : "rgba(245, 158, 11, 0.1)",
                      border: item.passed
                        ? "1px solid rgba(52, 211, 153, 0.35)"
                        : "1px solid rgba(251, 191, 36, 0.4)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "space-between",
                      gap: "12px",
                    }}
                  >
                    <div
                      style={{
                        display: "flex",
                        alignItems: "center",
                        gap: "10px",
                      }}
                    >
                      <CheckCircle2
                        size={16}
                        color={item.passed ? "#34D399" : "#FBBF24"}
                      />
                      <div>
                        <strong
                          style={{
                            fontSize: "12px",
                            color: "#F8FAFC",
                            display: "block",
                          }}
                        >
                          {item.label}
                        </strong>
                        <span style={{ fontSize: "11px", color: "#CBD5E1" }}>
                          {item.detail}
                        </span>
                      </div>
                    </div>

                    <button
                      type="button"
                      onClick={() => handleStepSelect(item.stepId)}
                      style={{
                        padding: "5px 10px",
                        borderRadius: "6px",
                        background: "rgba(148, 163, 184, 0.16)",
                        color: "#E2E8F0",
                        border: "none",
                        fontSize: "10px",
                        fontWeight: 700,
                        cursor: "pointer",
                        whiteSpace: "nowrap",
                      }}
                    >
                      Edit Step
                    </button>
                  </div>
                ))}
              </div>

              {/* Shareable URL & OpenGraph Card */}
              <div
                id="og-preview-card"
                style={{
                  padding: "16px",
                  borderRadius: "12px",
                  background: "#0B1120",
                  border: "1px solid rgba(148, 163, 184, 0.2)",
                }}
              >
                <span
                  style={{
                    fontSize: "10px",
                    fontWeight: 800,
                    color: "#34D399",
                    textTransform: "uppercase",
                  }}
                >
                  Public Box Office Link
                </span>
                <h4
                  style={{
                    margin: "2px 0 8px",
                    fontSize: "14px",
                    fontWeight: 800,
                    color: "#F8FAFC",
                  }}
                >
                  Shareable Event URL & Social OpenGraph Preview
                </h4>

                <div style={{ display: "flex", gap: "8px" }}>
                  <input
                    id="shareable-event-url-input"
                    type="text"
                    readOnly
                    value={
                      eventDetails.shareableUrl ||
                      `https://islandevents.app/e/${
                        eventDetails.slug || eventDetails.id
                      }`
                    }
                    style={inputStyle}
                  />
                  <button
                    type="button"
                    id="copy-shareable-url-btn"
                    onClick={() =>
                      onCopyShareUrl(
                        eventDetails.shareableUrl ||
                          `https://islandevents.app/e/${
                            eventDetails.slug || eventDetails.id
                          }`
                      )
                    }
                    style={{
                      padding: "0 14px",
                      borderRadius: "8px",
                      background: "#10B981",
                      color: "#052E16",
                      border: "none",
                      fontSize: "11px",
                      fontWeight: 800,
                      whiteSpace: "nowrap",
                      cursor: "pointer",
                    }}
                  >
                    {shareCopied ? "Copied!" : "Copy URL"}
                  </button>
                </div>
              </div>

              {/* Online Ordering Checkbox + Legacy Submit Button */}
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  paddingTop: "8px",
                  borderTop: "1px solid rgba(148, 163, 184, 0.14)",
                  flexWrap: "wrap",
                  gap: "10px",
                }}
              >
                <label
                  style={{
                    display: "flex",
                    alignItems: "center",
                    gap: "8px",
                    fontSize: "12px",
                    color: "#E2E8F0",
                    cursor: "pointer",
                  }}
                >
                  <input
                    type="checkbox"
                    checked={eventDetails.isOrderingOpen}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) =>
                      updateField("isOrderingOpen", e.target.checked)
                    }
                  />
                  <span>
                    Accepting Online Orders & Ticket Reservations When Published
                  </span>
                </label>

                <button
                  type="submit"
                  id="save-event-form-submit"
                  disabled={!permissions.canManageEventsAndMenus}
                  style={{
                    height: "36px",
                    padding: "0 14px",
                    borderRadius: "8px",
                    background: "#4F46E5",
                    color: "#FFFFFF",
                    border: "none",
                    fontSize: "11px",
                    fontWeight: 800,
                    cursor: "pointer",
                  }}
                >
                  Save Event Details
                </button>
              </div>
            </div>
          )}

          {/* Persistent Wizard Footer Controls: Save Draft, Previous, Next, Preview, Publish */}
          <div
            id="wizard-footer-controls"
            style={{
              marginTop: "6px",
              paddingTop: "14px",
              borderTop: "1px solid rgba(148, 163, 184, 0.18)",
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              flexWrap: "wrap",
              gap: "10px",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
              <button
                type="button"
                id="wizard-prev-step-btn"
                disabled={currentStepIndex === 0}
                onClick={handlePreviousStep}
                style={{
                  height: "38px",
                  padding: "0 14px",
                  borderRadius: "10px",
                  background: "rgba(148, 163, 184, 0.14)",
                  color: currentStepIndex === 0 ? "#64748B" : "#F8FAFC",
                  border: "1px solid rgba(148, 163, 184, 0.25)",
                  fontSize: "12px",
                  fontWeight: 700,
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "5px",
                  cursor: currentStepIndex === 0 ? "not-allowed" : "pointer",
                }}
              >
                <ChevronLeft size={14} />
                Previous
              </button>

              <button
                type="button"
                id="wizard-save-draft-btn"
                disabled={!permissions.canManageEventsAndMenus}
                onClick={handleSaveDraft}
                style={{
                  height: "38px",
                  padding: "0 14px",
                  borderRadius: "10px",
                  background: "rgba(79, 70, 229, 0.18)",
                  color: "#C7D2FE",
                  border: "1px solid rgba(129, 140, 248, 0.4)",
                  fontSize: "12px",
                  fontWeight: 700,
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "6px",
                  cursor: permissions.canManageEventsAndMenus
                    ? "pointer"
                    : "not-allowed",
                }}
              >
                <Save size={13} />
                Save Draft
              </button>
            </div>

            <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
              <button
                type="button"
                id="wizard-preview-btn"
                onClick={() => setIsPreviewModalOpen(!isPreviewModalOpen)}
                style={{
                  height: "38px",
                  padding: "0 14px",
                  borderRadius: "10px",
                  background: "rgba(56, 189, 248, 0.14)",
                  color: "#7DD3FC",
                  border: "1px solid rgba(56, 189, 248, 0.35)",
                  fontSize: "12px",
                  fontWeight: 700,
                  display: "inline-flex",
                  alignItems: "center",
                  gap: "6px",
                  cursor: "pointer",
                }}
              >
                <Eye size={14} />
                {isPreviewModalOpen ? "Hide Preview" : "Preview"}
              </button>

              {currentStepIndex < EVENT_WIZARD_STEPS.length - 1 ? (
                <button
                  type="button"
                  id="wizard-next-step-btn"
                  onClick={handleNextStep}
                  style={{
                    height: "38px",
                    padding: "0 18px",
                    borderRadius: "10px",
                    background:
                      "linear-gradient(135deg, #6366F1 0%, #4F46E5 50%, #10B981 100%)",
                    color: "#FFFFFF",
                    border: "none",
                    fontSize: "12px",
                    fontWeight: 800,
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "6px",
                    cursor: "pointer",
                    boxShadow: "0 6px 18px rgba(79, 70, 229, 0.35)",
                  }}
                >
                  Next Step
                  <ChevronRight size={14} />
                </button>
              ) : (
                <button
                  type="button"
                  id="wizard-publish-btn"
                  disabled={
                    !permissions.canManageEventsAndMenus ||
                    !readiness.canPublish
                  }
                  onClick={handlePublishEvent}
                  style={{
                    height: "38px",
                    padding: "0 18px",
                    borderRadius: "10px",
                    background: readiness.canPublish
                      ? "linear-gradient(135deg, #10B981 0%, #059669 55%, #4F46E5 100%)"
                      : "rgba(148, 163, 184, 0.2)",
                    color: readiness.canPublish ? "#FFFFFF" : "#94A3B8",
                    border: "none",
                    fontSize: "12px",
                    fontWeight: 800,
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "6px",
                    cursor:
                      permissions.canManageEventsAndMenus &&
                      readiness.canPublish
                        ? "pointer"
                        : "not-allowed",
                    boxShadow: readiness.canPublish
                      ? "0 6px 20px rgba(16, 185, 129, 0.35)"
                      : "none",
                  }}
                >
                  <Sparkles size={14} />
                  Publish Event
                </button>
              )}
            </div>
          </div>
        </form>

        {/* RIGHT COLUMN: Contextual Live Preview & Step Readiness Summary */}
        <aside
          id="event-wizard-contextual-preview"
          style={{
            background: "#0F172A",
            border: "1px solid rgba(148, 163, 184, 0.2)",
            borderRadius: "14px",
            overflow: "hidden",
            display: "flex",
            flexDirection: "column",
          }}
        >
          {/* Live Card Banner */}
          <div
            style={{
              position: "relative",
              height: "175px",
              background: "#020617",
              overflow: "hidden",
            }}
          >
            <img
              src={resolveEventThumbnail(eventDetails)}
              alt={eventDetails.title || "Event Preview"}
              onError={(e) => {
                e.currentTarget.src = "/images/escovitch_fish.jpg";
              }}
              style={{
                width: "100%",
                height: "100%",
                objectFit: "cover",
                display: "block",
                opacity: 0.88,
              }}
            />
            <div
              style={{
                position: "absolute",
                inset: 0,
                background:
                  "linear-gradient(180deg, rgba(2,6,23,0.15) 0%, rgba(2,6,23,0.88) 100%)",
              }}
            />
            <div
              style={{
                position: "absolute",
                top: "12px",
                left: "12px",
                right: "12px",
                display: "flex",
                justifyContent: "space-between",
                alignItems: "center",
              }}
            >
              <span
                style={{
                  padding: "4px 10px",
                  borderRadius: "999px",
                  fontSize: "10px",
                  fontWeight: 800,
                  background: "rgba(15, 23, 42, 0.85)",
                  color: "#38BDF8",
                  border: "1px solid rgba(56, 189, 248, 0.4)",
                }}
              >
                {eventDetails.category || "Seafood & Fish Fry"}
              </span>
              <span
                style={{
                  padding: "4px 10px",
                  borderRadius: "999px",
                  fontSize: "10px",
                  fontWeight: 800,
                  background: "rgba(15, 23, 42, 0.85)",
                  color:
                    eventDetails.status === "published" ? "#34D399" : "#FBBF24",
                  border: "1px solid rgba(148, 163, 184, 0.3)",
                }}
              >
                Live Preview · {(eventDetails.status || "draft").toUpperCase()}
              </span>
            </div>

            <div
              style={{
                position: "absolute",
                bottom: "12px",
                left: "14px",
                right: "14px",
              }}
            >
              <h4
                style={{
                  margin: 0,
                  fontSize: "16px",
                  fontWeight: 800,
                  color: "#F8FAFC",
                }}
              >
                {eventDetails.title || "Untitled Island Event"}
              </h4>
              <span style={{ fontSize: "11px", color: "#CBD5E1" }}>
                {eventDetails.tagline || "Island Culinary & Cultural Showcase"}
              </span>
            </div>
          </div>

          {/* Contextual Details Body */}
          <div
            style={{
              padding: "16px",
              display: "flex",
              flexDirection: "column",
              gap: "12px",
            }}
          >
            <div
              style={{
                display: "flex",
                flexDirection: "column",
                gap: "6px",
                fontSize: "11px",
                color: "#CBD5E1",
              }}
            >
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <Calendar size={13} color="#38BDF8" />
                <span>
                  {eventDetails.dateStr || "Date TBD"} ·{" "}
                  {eventDetails.timeStr || "Hours TBD"}
                </span>
              </div>
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <Clock size={13} color="#A78BFA" />
                <span>
                  Timezone:{" "}
                  <strong>{eventDetails.timezone || "America/Jamaica"}</strong>
                </span>
              </div>
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <MapPin size={13} color="#34D399" />
                <span>
                  {eventDetails.venueName || "Venue TBD"} —{" "}
                  {eventDetails.venueAddress || "Address TBD"}
                </span>
              </div>
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <Ticket size={13} color="#FBBF24" />
                <span>
                  {ticketsValidation.activeTiersCount} Active Ticket Tier(s) ·{" "}
                  {ticketsValidation.totalCapacity} Total Capacity
                </span>
              </div>
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <Utensils size={13} color="#F472B6" />
                <span>
                  {supportsFoodOrdering
                    ? `${menu.length} Menu Dishes Attached`
                    : "Admission Tickets Only (No Food Menu)"}
                </span>
              </div>
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <DollarSign size={13} color="#34D399" />
                <span>
                  Packaging Fee: ${eventDetails.packagingFeeUSD.toFixed(2)} USD
                  / J${eventDetails.packagingFeeJMD}
                </span>
              </div>
            </div>

            {/* Compact Readiness Checklist Summary */}
            <div
              style={{
                paddingTop: "10px",
                borderTop: "1px solid rgba(148, 163, 184, 0.16)",
                display: "flex",
                flexDirection: "column",
                gap: "6px",
              }}
            >
              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  alignItems: "center",
                  fontSize: "11px",
                }}
              >
                <strong style={{ color: "#E2E8F0" }}>
                  Publication Readiness Gate
                </strong>
                <span
                  style={{
                    color: readiness.canPublish ? "#34D399" : "#FBBF24",
                    fontWeight: 800,
                  }}
                >
                  {readiness.canPublish ? "Ready to Publish" : "Incomplete"}
                </span>
              </div>

              {readiness.checklist.map((chk) => (
                <div
                  key={chk.id}
                  style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    fontSize: "11px",
                    color: chk.passed ? "#A7F3D0" : "#FDE68A",
                  }}
                >
                  <span>
                    {chk.passed ? "✓" : "○"} {chk.label}
                  </span>
                  <span style={{ fontSize: "10px", opacity: 0.85 }}>
                    {chk.passed
                      ? "Pass"
                      : chk.requiredForPublish
                      ? "Required"
                      : "Optional"}
                  </span>
                </div>
              ))}
            </div>
          </div>
        </aside>
      </div>

      {/* Interactive Attendee Box-Office Preview Drawer when Preview button is clicked */}
      {isPreviewModalOpen && (
        <div
          id="wizard-attendee-preview-panel"
          style={{
            padding: "18px",
            borderRadius: "14px",
            background: "#0B1120",
            border: "1px solid rgba(56, 189, 248, 0.4)",
            display: "flex",
            flexDirection: "column",
            gap: "12px",
          }}
        >
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
              <FileText size={15} color="#38BDF8" />
              <strong style={{ fontSize: "14px", color: "#F8FAFC" }}>
                Attendee Box-Office Preview ({eventDetails.title})
              </strong>
            </div>
            <button
              type="button"
              onClick={() => setIsPreviewModalOpen(false)}
              style={{
                padding: "4px 10px",
                borderRadius: "6px",
                background: "rgba(148, 163, 184, 0.18)",
                color: "#CBD5E1",
                border: "none",
                fontSize: "11px",
                cursor: "pointer",
              }}
            >
              Close Preview
            </button>
          </div>

          <p style={{ margin: 0, fontSize: "12px", color: "#CBD5E1" }}>
            {eventDetails.description}
          </p>

          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
              gap: "10px",
            }}
          >
            {ticketTiers
              .filter((t) => t.isActive !== false)
              .map((tier) => (
                <div
                  key={tier.id}
                  style={{
                    padding: "10px 12px",
                    borderRadius: "10px",
                    background: "#0F172A",
                    border: "1px solid rgba(148, 163, 184, 0.2)",
                  }}
                >
                  <strong
                    style={{
                      fontSize: "12px",
                      color: "#F8FAFC",
                      display: "block",
                    }}
                  >
                    {tier.name} ({tier.category})
                  </strong>
                  <span
                    style={{
                      fontSize: "11px",
                      color: "#34D399",
                      fontWeight: 800,
                    }}
                  >
                    ${tier.priceUSD.toFixed(2)} USD · J$
                    {tier.priceJMD.toLocaleString()}
                  </span>
                  <span
                    style={{
                      display: "block",
                      fontSize: "10px",
                      color: "#94A3B8",
                    }}
                  >
                    {tier.availableStock} available · Max {tier.maxPerCustomer}{" "}
                    per order
                  </span>
                </div>
              ))}
          </div>

          {showAttemptErrors && !readiness.canPublish && (
            <div
              style={{
                fontSize: "11px",
                color: "#FBBF24",
              }}
            >
              Note: This event is currently in Preview Mode and cannot be
              published until all required readiness checks pass.
            </div>
          )}
        </div>
      )}
    </div>
  );
}
