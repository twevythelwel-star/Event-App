import React, { useEffect, useMemo, useRef, useState } from "react";
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
  EventDetails,
  EventTicketTier,
  EventTicketTierCategory,
  EventWizardStepId,
  MenuItem,
  MenuPackage,
  OrganizerProfile,
} from "../../types";
import { HostRolePermissions } from "./types";
import { resolveEventThumbnail } from "./financialCalculations";
import { EVENT_PLACEHOLDER } from "../../lib/imageResolver";
import {
  CulturalCatalogCategory,
  fetchCulturalCatalog,
} from "../../lib/api";
import {
  getLocationCatalog,
  getLocationOptions,
  getRegisteredLocationCatalogs,
} from "../../services/marketConfiguration";
import {
  EVENT_WIZARD_STEPS,
  SUPPORTED_EVENT_CATEGORIES,
  SUPPORTED_EVENT_TIMEZONES,
  evaluateWizardPublishReadiness,
  getDefaultEventTicketTiers,
  validateWizardBasicDetails,
  validateWizardDateLocation,
  validateWizardTickets,
} from "./wizardValidation";
import { VenueMapLocator } from "./VenueMapLocator";
import { FoodServiceConfigSection } from "./FoodServiceConfigSection";
import { CancellationPolicyEditor } from "./CancellationPolicyEditor";
import { EventMediaManager } from "./EventMediaManager";
import { FoodPhoto } from "../FoodPhoto";
import { MenuItemBuilder } from "./MenuItemBuilder";
import { PackageBuilder } from "./PackageBuilder";
import { TicketPreview } from "./TicketPreview";

const EVENT_TYPE_OPTIONS = [
  "Cultural Festival", "Food Festival", "Concert", "Live Music", "Community Gathering",
  "Networking", "Fundraiser", "Religious or Spiritual Gathering", "Private Celebration",
  "Conference", "Food Pop-Up", "Market or Vendor Event", "Educational Event", "Family Event", "Other",
];

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
}: Props) {
  const [currentStep, setCurrentStep] = useState<EventWizardStepId>(initialStep);
  const [showAttemptErrors, setShowAttemptErrors] = useState(false);
  const [isPreviewModalOpen, setIsPreviewModalOpen] = useState(false);
  const [flyerPreviewDevice, setFlyerPreviewDevice] = useState<"desktop" | "tablet" | "mobile">("desktop");
  const [wizardBannerError, setWizardBannerError] = useState<string | null>(null);
  const [culturalCatalog, setCulturalCatalog] = useState<CulturalCatalogCategory[]>([]);
  const [culturalCatalogError, setCulturalCatalogError] = useState<string | null>(null);
  const [dishSearch, setDishSearch] = useState("");
  const [customDishName, setCustomDishName] = useState("");
  const [customDishCountryId, setCustomDishCountryId] = useState("");
  const [customDishSpellings, setCustomDishSpellings] = useState("");
  const [customDishDietaryType, setCustomDishDietaryType] = useState<"any" | "vegetarian" | "vegan">("any");
  const [customDishIsFusion, setCustomDishIsFusion] = useState(false);

  // Food menu upgrade (Section 7): builder state
  const [editingItem, setEditingItem] = useState<MenuItem | null>(null);
  const [editingPackage, setEditingPackage] = useState<MenuPackage | null>(null);
  const [activeFoodTab, setActiveFoodTab] = useState<string>("menu");
  const pendingTicketScrollId = useRef<string | null>(null);

  useEffect(() => {
    fetchCulturalCatalog()
      .then(setCulturalCatalog)
      .catch((error: unknown) => {
        setCulturalCatalogError(error instanceof Error ? error.message : "The cultural catalog could not be loaded.");
      });
  }, []);


  const ticketTiers: EventTicketTier[] = useMemo(
    () => getDefaultEventTicketTiers(eventDetails),
    [eventDetails]
  );

  const supportsFoodOrdering = eventDetails.supportsFoodOrdering !== false;

  useEffect(() => {
    const ticketId = pendingTicketScrollId.current;
    if (!ticketId) return;
    const card = document.getElementById(`wizard-ticket-tier-card-${ticketId}`);
    if (!card) return;
    card.scrollIntoView({ behavior: "smooth", block: "start" });
    pendingTicketScrollId.current = null;
  }, [ticketTiers]);

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
  const selectedCulturalCategoryIds = eventDetails.culturalCategoryIds || [];
  const selectedCountryIds = eventDetails.countryIds || [];
  const selectedDishIds = eventDetails.dishIds || [];
  const visibleCountries = useMemo(
    () => culturalCatalog.flatMap((category) =>
      selectedCulturalCategoryIds.includes(category.id)
        ? category.regions.flatMap((region) => region.countries.map((country) => ({
            ...country,
            regionName: region.name,
            categoryId: category.id,
          })))
        : []
    ).filter((country, index, all) =>
      all.findIndex((candidate) => candidate.id === country.id) === index
    ),
    [culturalCatalog, selectedCulturalCategoryIds]
  );
  const selectedCountries = visibleCountries.filter((country) => selectedCountryIds.includes(country.id));
  const availableDishes = selectedCountries.flatMap((country) =>
    country.cuisines.flatMap((cuisine) => cuisine.dishes.map((dish) => ({
      ...dish,
      cuisineName: cuisine.name,
      countryId: country.id,
      countryName: country.name,
    })))
  );
  const dishQuery = dishSearch.trim().toLowerCase();
  const filteredDishes = availableDishes.filter((dish) =>
    [dish.name, ...dish.alternativeSpellings].some((name) => name.toLowerCase().includes(dishQuery))
  );
  const selectedDishSummaries = availableDishes.filter((dish) => selectedDishIds.includes(dish.id));

  const toggleCatalogId = (key: "culturalCategoryIds" | "countryIds" | "dishIds", id: string) => {
    const current = eventDetails[key] || [];
    const next = current.includes(id) ? current.filter((value) => value !== id) : [...current, id];
    if (key === "culturalCategoryIds") {
      const countryIds = new Set(
        culturalCatalog.flatMap((category) => category.regions.flatMap((region) => region.countries))
          .filter((country) => next.some((categoryId) => culturalCatalog
            .find((category) => category.id === categoryId)?.regions
            .some((region) => region.countries.some((item) => item.id === country.id))))
          .map((country) => country.id)
      );
      onUpdateEventDetails({
        ...eventDetails,
        culturalCategoryIds: next,
        countryIds: (eventDetails.countryIds || []).filter((countryId) => countryIds.has(countryId)),
      });
      return;
    }
    if (key === "countryIds") {
      const retained = new Set(next);
      onUpdateEventDetails({
        ...eventDetails,
        countryIds: next,
        dishIds: (eventDetails.dishIds || []).filter((dishId) =>
          availableDishes.some((dish) => dish.id === dishId && retained.has(dish.countryId))
        ),
        customDishes: (eventDetails.customDishes || []).filter((dish) => retained.has(dish.countryId)),
      });
      return;
    }
    updateField(key, next);
  };

  const addCustomDish = () => {
    const name = customDishName.trim();
    const countryId = selectedCountryIds.includes(customDishCountryId)
      ? customDishCountryId
      : selectedCountryIds[0];
    if (name.length < 2 || !countryId) return;
    updateField("customDishes", [
      ...(eventDetails.customDishes || []),
      {
        name,
        countryId,
        alternativeSpellings: customDishSpellings.split(",").map((value) => value.trim()).filter(Boolean),
        isFusion: customDishIsFusion,
        dietaryType: customDishDietaryType,
      },
    ]);
    setCustomDishName("");
    setCustomDishSpellings("");
    setCustomDishIsFusion(false);
    setCustomDishDietaryType("any");
  };

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

  const updateAddressField = (
    key:
      | "streetAddress"
      | "venueBuildingName"
      | "city"
      | "stateCode"
      | "zipCode"
      | "countryCode",
    value: string,
    countryCode?: string
  ) => {
    const nextCountry = countryCode || eventDetails.countryCode || "US";
    const catalog = getLocationCatalog(nextCountry);
    const countryName = catalog.countryName || "United States";
    const updated = {
      ...eventDetails,
      [key]: value,
      locationVerified: false,
      locationSource: undefined,
      locationToken: undefined,
      locationPreviewUrl: undefined,
      latitude: null,
      longitude: null,
      coordinates: "",
      clearLocationPin: eventDetails.locationVerified || eventDetails.clearLocationPin,
      countryCode: nextCountry,
      ...(key === "city" || key === "stateCode" ? { timezone: "" } : {}),
    };
    const state = updated.stateCode?.trim().toUpperCase() || "";
    updated.venueAddress = [
      updated.streetAddress?.trim(),
      updated.venueBuildingName?.trim(),
      updated.city?.trim(),
      state,
      updated.zipCode?.trim(),
      countryName,
    ].filter(Boolean).join(", ");
    onUpdateEventDetails(updated);
  };

  const locationOptions = useMemo(
    () => getLocationOptions(eventDetails.countryCode || "US"),
    [eventDetails.countryCode]
  );

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
    if (ticketTiers.length >= 20) {
      setWizardBannerError("An event can have at most 20 ticket types.");
      return;
    }
    const nextTier: EventTicketTier = {
      id: `tier-${Date.now()}`,
      name: "General Admission",
      category: "General Admission",
      currency: eventDetails.currency || "USD",
      priceUSD: 20,
      priceJMD: 3100,
      availableStock: 100,
      maxPerCustomer: 6,
      minPerOrder: 1,
      admissionCount: 1,
      saleStartIso: eventDetails.ticketSalesStartIso || "",
      saleEndIso: eventDetails.ticketSalesEndIso || "",
      description: "Admission & event reservation tier.",
      benefits: ["QR Gate Entry"],
      admissionPrivileges: "",
      includedFoodAndDrinks: "",
      ageRestriction: "",
      refundEligible: true,
      visibility: "public",
      designStyle: "premium_minimal",
      accentColor: "indigo",
      backgroundStyle: "solid",
      isActive: true,
    };
    pendingTicketScrollId.current = nextTier.id;
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

  const buttonStyle: React.CSSProperties = {
    display: "inline-flex",
    alignItems: "center",
    gap: "6px",
    padding: "8px 11px",
    borderRadius: "8px",
    border: "1px solid rgba(148, 163, 184, 0.3)",
    background: "#1E293B",
    color: "#E2E8F0",
    fontSize: "11px",
    fontWeight: 700,
    cursor: "pointer",
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
                    placeholder="e.g., Miami Neighborhood Food & Arts Festival"
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
                    value={eventDetails.category || "Food & Drink"}
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

              <div
                style={{
                  padding: "16px",
                  borderRadius: "12px",
                  background: "#0B1120",
                  border: "1px solid rgba(148, 163, 184, 0.22)",
                  display: "flex",
                  flexDirection: "column",
                  gap: "12px",
                }}
              >
                <strong style={{ color: "#E2E8F0", fontSize: "13px" }}>Event overview</strong>
                <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: "12px" }}>
                  <div>
                    <label htmlFor="wizard-event-slug" style={labelStyle}>URL slug</label>
                    <input id="wizard-event-slug" value={eventDetails.slug || ""} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateField("slug", e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, "-"))} placeholder="my-community-event" style={inputStyle} />
                  </div>
                  <div>
                    <label htmlFor="wizard-event-format" style={labelStyle}>Event format</label>
                    <select id="wizard-event-format" value={eventDetails.eventFormat || "in_person"} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateField("eventFormat", e.target.value as EventDetails["eventFormat"])} style={inputStyle}>
                      <option value="in_person">In-person</option>
                      <option value="virtual">Virtual</option>
                      <option value="hybrid">Hybrid</option>
                    </select>
                  </div>
                  <div>
                    <label htmlFor="wizard-expected-attendance" style={labelStyle}>Expected attendance</label>
                    <input id="wizard-expected-attendance" type="number" min="1" value={eventDetails.expectedAttendance ?? ""} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateField("expectedAttendance", e.target.value ? Number(e.target.value) : null)} placeholder="e.g. 250" style={inputStyle} />
                  </div>
                  <div>
                    <label htmlFor="wizard-event-visibility" style={labelStyle}>Event visibility</label>
                    <select id="wizard-event-visibility" value={eventDetails.visibility || "public"} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateField("visibility", e.target.value as NonNullable<EventDetails["visibility"]>)} style={inputStyle}>
                      <option value="public">Public — discoverable</option>
                      <option value="unlisted">Unlisted — link only</option>
                      <option value="private">Private — invitation only</option>
                    </select>
                  </div>
                </div>
                <div>
                  <label htmlFor="wizard-event-summary" style={labelStyle}>Short event summary</label>
                  <textarea id="wizard-event-summary" rows={2} maxLength={500} value={eventDetails.summary || ""} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateField("summary", e.target.value)} placeholder="A concise introduction for event cards and search results." style={{ ...inputStyle, height: "auto", padding: "10px 12px" }} />
                </div>
                <div>
                  <span style={labelStyle}>Event types — select all that apply</span>
                  <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(190px, 1fr))", gap: "8px" }}>
                    {EVENT_TYPE_OPTIONS.map((type) => (
                      <label key={type} style={{ ...labelStyle, display: "flex", alignItems: "center", gap: "8px", fontWeight: 500, cursor: "pointer" }}>
                        <input type="checkbox" checked={(eventDetails.eventTypes || []).includes(type)} disabled={!permissions.canManageEventsAndMenus} onChange={() => {
                          const current = eventDetails.eventTypes || [];
                          updateField("eventTypes", current.includes(type) ? current.filter((value) => value !== type) : [...current, type]);
                        }} />
                        {type}
                      </label>
                    ))}
                  </div>
                </div>
                {(eventDetails.eventFormat === "virtual" || eventDetails.eventFormat === "hybrid") && (
                  <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: "12px" }}>
                    <div>
                      <label htmlFor="wizard-virtual-url" style={labelStyle}>Online attendance destination</label>
                      <input id="wizard-virtual-url" type="url" value={eventDetails.virtualUrl || ""} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateField("virtualUrl", e.target.value)} placeholder="https://…" style={inputStyle} />
                    </div>
                    <div>
                      <label htmlFor="wizard-virtual-instructions" style={labelStyle}>Online access instructions</label>
                      <textarea id="wizard-virtual-instructions" rows={2} value={eventDetails.virtualAccessInstructions || ""} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateField("virtualAccessInstructions", e.target.value)} placeholder="How attendees receive access details" style={{ ...inputStyle, height: "auto", padding: "10px 12px" }} />
                    </div>
                  </div>
                )}
                <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: "12px" }}>
                  <div>
                    <label htmlFor="wizard-age-restriction" style={labelStyle}>Age restrictions</label>
                    <input id="wizard-age-restriction" value={eventDetails.ageRestriction || ""} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateField("ageRestriction", e.target.value)} placeholder="All ages, 18+, 21+…" style={inputStyle} />
                  </div>
                  <div>
                    <label htmlFor="wizard-dress-code" style={labelStyle}>Dress code</label>
                    <input id="wizard-dress-code" value={eventDetails.dressCode || ""} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateField("dressCode", e.target.value)} placeholder="Casual, formal, cultural attire…" style={inputStyle} />
                  </div>
                  <div>
                    <label htmlFor="wizard-languages" style={labelStyle}>Languages spoken (comma-separated)</label>
                    <input id="wizard-languages" value={(eventDetails.languages || []).join(", ")} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateField("languages", e.target.value.split(",").map((language) => language.trim()).filter(Boolean))} placeholder="English, Spanish, Haitian Creole" style={inputStyle} />
                  </div>
                </div>
                <div>
                  <label htmlFor="wizard-accessibility" style={labelStyle}>Accessibility information</label>
                  <textarea id="wizard-accessibility" rows={2} value={eventDetails.accessibilityInformation || ""} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateField("accessibilityInformation", e.target.value)} placeholder="Share mobility access, sensory accommodations, or contact details for accessibility requests." style={{ ...inputStyle, height: "auto", padding: "10px 12px" }} />
                </div>
                {permissions.role === "platform_admin" && (
                  <label style={{ ...labelStyle, display: "flex", alignItems: "center", gap: "8px" }}>
                    <input type="checkbox" checked={Boolean(eventDetails.isFeatured)} onChange={(e) => updateField("isFeatured", e.target.checked)} />
                    Feature this event (System Administrator permission)
                  </label>
                )}
              </div>

              <section
                aria-labelledby="cultural-identity-heading"
                style={{ padding: "16px", borderRadius: "12px", background: "#0B1120", border: "1px solid rgba(148, 163, 184, 0.22)", display: "flex", flexDirection: "column", gap: "12px" }}
              >
                <div>
                  <h4 id="cultural-identity-heading" style={{ margin: "0 0 4px", color: "#E2E8F0", fontSize: "14px" }}>Cultural identities, regions & cuisines</h4>
                  <p style={{ margin: 0, color: "#94A3B8", fontSize: "11px" }}>Choose every identity represented. These selections help guests discover the event and do not imply that any culture or cuisine belongs to one group of people.</p>
                </div>
                {culturalCatalogError && <p role="alert" style={{ color: "#FCA5A5", fontSize: "12px" }}>Cultural catalog unavailable: {culturalCatalogError}</p>}
                {!culturalCatalog.length && !culturalCatalogError && <p style={{ color: "#94A3B8", fontSize: "12px" }}>Loading cultural catalog…</p>}
                <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: "8px" }}>
                  {culturalCatalog.map((culture) => (
                    <label key={culture.id} style={{ ...labelStyle, display: "flex", alignItems: "center", gap: "8px", fontWeight: 500, cursor: "pointer" }}>
                      <input type="checkbox" checked={selectedCulturalCategoryIds.includes(culture.id)} disabled={!permissions.canManageEventsAndMenus} onChange={() => toggleCatalogId("culturalCategoryIds", culture.id)} />
                      {culture.name}
                    </label>
                  ))}
                </div>
                {culturalCatalog.filter((culture) => selectedCulturalCategoryIds.includes(culture.id)).map((culture) => (
                  <div key={culture.id} style={{ borderTop: "1px solid rgba(148,163,184,.2)", paddingTop: "10px" }}>
                    <strong style={{ color: "#C7D2FE", fontSize: "12px" }}>{culture.name} regions</strong>
                    {culture.regions.map((region) => (
                      <div key={region.id} style={{ marginTop: "9px" }}>
                        <div style={{ color: "#CBD5E1", fontSize: "11px", marginBottom: "7px" }}>{region.name}</div>
                        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))", gap: "8px" }}>
                          {region.countries.map((country) => {
                            const selected = selectedCountryIds.includes(country.id);
                            const dishCount = selectedDishIds.filter((dishId) => country.cuisines.some((cuisine) => cuisine.dishes.some((dish) => dish.id === dishId))).length
                              + (eventDetails.customDishes || []).filter((dish) => dish.countryId === country.id).length;
                            return (
                              <button key={`${region.id}-${country.id}`} type="button" className="cultural-country-card" aria-pressed={selected} aria-expanded={selected} disabled={!permissions.canManageEventsAndMenus} onClick={() => toggleCatalogId("countryIds", country.id)} style={{ textAlign: "left", padding: "10px", borderRadius: "10px", color: "#E2E8F0", background: selected ? "rgba(99,102,241,.18)" : "#111827", border: selected ? "1px solid #818CF8" : "1px solid rgba(148,163,184,.2)", cursor: "pointer" }}>
                                <span style={{ display: "flex", alignItems: "center", gap: "9px" }}>
                                  {country.imageUrl ? <img src={country.imageUrl} alt="" width="42" height="32" style={{ objectFit: "cover", borderRadius: "5px" }} /> : <span aria-hidden="true" style={{ fontSize: "24px" }}>{country.flag || "🌍"}</span>}
                                  <span><strong style={{ display: "block", fontSize: "12px" }}>{country.name}</strong><small style={{ color: "#94A3B8" }}>{region.name} · {dishCount} dishes selected</small></span>
                                </span>
                              </button>
                            );
                          })}
                        </div>
                      </div>
                    ))}
                  </div>
                ))}
                {selectedCountries.length > 0 && (
                  <div className="cultural-selection-panel" style={{ borderTop: "1px solid rgba(148,163,184,.2)", paddingTop: "12px", display: "flex", flexDirection: "column", gap: "10px" }}>
                    <div style={{ display: "flex", flexWrap: "wrap", gap: "7px" }}>
                      {selectedCountries.map((country) => <span key={country.id} style={{ padding: "5px 8px", borderRadius: "999px", background: "rgba(52,211,153,.12)", color: "#A7F3D0", fontSize: "11px" }}>{country.flag || "🌍"} {country.name}</span>)}
                    </div>
                    {(selectedDishSummaries.length > 0 || (eventDetails.customDishes || []).length > 0) && (
                      <div className="cultural-served-summary" aria-label="Selected dishes being served">
                        <strong>Being served</strong>
                        {selectedDishSummaries.map((dish) => <span key={dish.id}>{dish.name} · {dish.countryName}</span>)}
                        {(eventDetails.customDishes || []).map((dish, index) => (
                          <span key={`${dish.countryId}-${dish.name}-${index}`}>{dish.name} · {selectedCountries.find((country) => country.id === dish.countryId)?.name}</span>
                        ))}
                      </div>
                    )}
                    <label htmlFor="cultural-dish-search" style={labelStyle}>Search dishes from selected countries</label>
                    <input id="cultural-dish-search" type="search" value={dishSearch} onChange={(e) => setDishSearch(e.target.value)} placeholder="Search dish names…" style={inputStyle} />
                    <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: "7px" }}>
                      {filteredDishes.map((dish) => (
                        <label key={dish.id} style={{ ...labelStyle, display: "flex", gap: "8px", alignItems: "center", fontWeight: 500 }}>
                          <input type="checkbox" checked={selectedDishIds.includes(dish.id)} disabled={!permissions.canManageEventsAndMenus} onChange={() => toggleCatalogId("dishIds", dish.id)} />
                          <span>{dish.name}<small style={{ display: "block", color: "#94A3B8" }}>{dish.countryName} · {dish.cuisineName}{dish.dietaryType !== "any" ? ` · ${dish.dietaryType}` : ""}{dish.isFusion ? " · fusion" : ""}</small>{dish.alternativeSpellings.length > 0 && <small style={{ display: "block", color: "#64748B" }}>Also known as: {dish.alternativeSpellings.join(", ")}</small>}</span>
                        </label>
                      ))}
                      {filteredDishes.length === 0 && <span style={{ color: "#94A3B8", fontSize: "11px" }}>No suggestions match. You can add a dish below.</span>}
                    </div>
                    <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: "8px" }}>
                      <div>
                        <label htmlFor="custom-dish-country" style={labelStyle}>Country for custom dish</label>
                        <select id="custom-dish-country" value={customDishCountryId || selectedCountryIds[0]} onChange={(e) => setCustomDishCountryId(e.target.value)} style={inputStyle}>
                          {selectedCountries.map((country) => <option key={country.id} value={country.id}>{country.name}</option>)}
                        </select>
                      </div>
                      <div>
                        <label htmlFor="custom-dish-name" style={labelStyle}>Add a dish being served</label>
                        <input id="custom-dish-name" value={customDishName} onChange={(e) => setCustomDishName(e.target.value)} placeholder="Dish name" style={inputStyle} />
                      </div>
                      <div>
                        <label htmlFor="custom-dish-spellings" style={labelStyle}>Alternative spellings</label>
                        <input id="custom-dish-spellings" value={customDishSpellings} onChange={(e) => setCustomDishSpellings(e.target.value)} placeholder="Comma-separated" style={inputStyle} />
                      </div>
                      <div>
                        <label htmlFor="custom-dish-dietary" style={labelStyle}>Dietary identity</label>
                        <select id="custom-dish-dietary" value={customDishDietaryType} onChange={(e) => setCustomDishDietaryType(e.target.value as typeof customDishDietaryType)} style={inputStyle}>
                          <option value="any">Not specified</option><option value="vegetarian">Vegetarian</option><option value="vegan">Vegan</option>
                        </select>
                      </div>
                    </div>
                    <label style={{ ...labelStyle, display: "flex", alignItems: "center", gap: "8px" }}>
                      <input type="checkbox" checked={customDishIsFusion} onChange={(e) => setCustomDishIsFusion(e.target.checked)} /> Fusion dish
                    </label>
                    <button type="button" disabled={!permissions.canManageEventsAndMenus || customDishName.trim().length < 2} onClick={addCustomDish} style={{ ...buttonStyle, alignSelf: "flex-start" }}>
                      <Plus size={14} /> Add custom dish
                    </button>
                    {(eventDetails.customDishes || []).map((dish, index) => (
                      <div key={`${dish.countryId}-${dish.name}-${index}`} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", color: "#CBD5E1", fontSize: "12px" }}>
                        <span>{dish.name} · {selectedCountries.find((country) => country.id === dish.countryId)?.name}{dish.dietaryType !== "any" ? ` · ${dish.dietaryType}` : ""}{dish.isFusion ? " · fusion" : ""}</span>
                        <button type="button" aria-label={`Remove ${dish.name}`} onClick={() => updateField("customDishes", (eventDetails.customDishes || []).filter((_, itemIndex) => itemIndex !== index))} style={{ ...buttonStyle, padding: "5px 8px" }}><Trash2 size={13} /></button>
                      </div>
                    ))}
                  </div>
                )}
              </section>

              <section aria-label="Live event identity preview" style={{ display: "grid", gridTemplateColumns: "minmax(0, 1fr) minmax(180px, 280px)", gap: "14px", padding: "14px", borderRadius: "12px", background: "#111827", border: "1px solid rgba(129,140,248,.35)" }}>
                <div>
                  <small style={{ color: "#A5B4FC", fontWeight: 800, letterSpacing: ".06em" }}>LIVE EVENT PREVIEW</small>
                  <h3 style={{ margin: "7px 0 3px", color: "#F8FAFC" }}>{eventDetails.title || "Event name"}</h3>
                  <p style={{ margin: "0 0 8px", color: "#C7D2FE", fontSize: "13px" }}>{eventDetails.tagline || "Your event tagline"}</p>
                  <small style={{ color: "#94A3B8" }}>{(eventDetails.eventTypes || []).join(" · ") || eventDetails.category || "Event category"} · {eventDetails.eventDateIso || eventDetails.dateStr || "Event date"}</small>
                  <p style={{ margin: "8px 0", color: "#CBD5E1", fontSize: "12px" }}>{eventDetails.eventFormat === "virtual" ? "Online event" : [eventDetails.venueName, eventDetails.city, eventDetails.stateCode].filter(Boolean).join(", ") || "Event location"}{eventDetails.eventFormat === "hybrid" ? " · Online attendance available" : ""}</p>
                  <p style={{ margin: 0, color: "#CBD5E1", fontSize: "12px", whiteSpace: "pre-wrap" }}>{eventDetails.description || "Event description will appear here."}</p>
                </div>
                <img src={activeFlyerDisplayUrl || "/images/event-placeholder.svg"} alt={eventDetails.flyerAltText || (eventDetails.title ? `${eventDetails.title} event image` : "Event image preview")} style={{ width: "100%", height: "160px", objectFit: "cover", objectPosition: `${eventDetails.flyerPositionX ?? 50}% ${eventDetails.flyerPositionY ?? 50}%`, borderRadius: "8px", background: "#0B1120" }} />
              </section>

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
                      JPG, PNG, WebP · Max 5 MB · 300–4500 px · separate optimized preview, original retained
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
                        alt={eventDetails.flyerAltText || `${eventDetails.title || "Event"} event flyer`}
                        onError={(e) => {
                          e.currentTarget.src = "/images/event-placeholder.svg";
                        }}
                        style={{
                          width: "100%",
                          height: "190px",
                          objectFit: "cover",
                          objectPosition: `${eventDetails.flyerPositionX ?? 50}% ${eventDetails.flyerPositionY ?? 50}%`,
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

                <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))", gap: 10, marginTop: 12 }}>
                  <label style={labelStyle}>Flyer alt text
                    <input value={eventDetails.flyerAltText || ""} disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) => updateField("flyerAltText", e.target.value)} maxLength={250}
                      placeholder="Describe the event artwork" style={inputStyle} />
                  </label>
                  <label style={labelStyle}>Crop focus horizontally: {eventDetails.flyerPositionX ?? 50}%
                    <input type="range" min={0} max={100} value={eventDetails.flyerPositionX ?? 50} disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) => updateField("flyerPositionX", Number(e.target.value))} aria-label="Flyer horizontal crop focus" />
                  </label>
                  <label style={labelStyle}>Crop focus vertically: {eventDetails.flyerPositionY ?? 50}%
                    <input type="range" min={0} max={100} value={eventDetails.flyerPositionY ?? 50} disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) => updateField("flyerPositionY", Number(e.target.value))} aria-label="Flyer vertical crop focus" />
                  </label>
                </div>

                <section aria-label="Interactive event cover preview" style={{ marginTop: 14, padding: 12, borderRadius: 10, background: "#080D16", border: "1px solid rgba(148,163,184,.2)" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 8, flexWrap: "wrap", marginBottom: 10 }}>
                    <strong style={{ color: "#E2E8F0", fontSize: 11 }}>Event page preview</strong>
                    <div role="group" aria-label="Event preview device" style={{ display: "flex", gap: 5 }}>
                      {(["desktop", "tablet", "mobile"] as const).map((device) => (
                        <button key={device} type="button" aria-pressed={flyerPreviewDevice === device} onClick={() => setFlyerPreviewDevice(device)}
                          style={{ padding: "5px 8px", borderRadius: 6, border: `1px solid ${flyerPreviewDevice === device ? "#38BDF8" : "rgba(148,163,184,.25)"}`, background: flyerPreviewDevice === device ? "rgba(56,189,248,.12)" : "#111827", color: "#E2E8F0", fontSize: 10, textTransform: "capitalize", transition: "all 180ms ease" }}>{device}</button>
                      ))}
                    </div>
                  </div>
                  <div style={{ width: flyerPreviewDevice === "desktop" ? "100%" : flyerPreviewDevice === "tablet" ? "min(100%, 560px)" : "min(100%, 320px)", margin: "0 auto", transition: "width 220ms ease" }}>
                    <div style={{ overflow: "hidden", borderRadius: 10, border: "1px solid rgba(148,163,184,.25)", background: "#111827" }}>
                      <div style={{ height: flyerPreviewDevice === "mobile" ? 180 : 240, position: "relative", transition: "height 220ms ease", overflow: "hidden", background: "#0B1120" }}>
                        <img src={activeFlyerDisplayUrl || "/images/event-placeholder.svg"} alt={eventDetails.flyerAltText || `${eventDetails.title || "Event"} event flyer`}
                          style={{ width: "100%", height: "100%", display: "block", objectFit: "cover", objectPosition: `${eventDetails.flyerPositionX ?? 50}% ${eventDetails.flyerPositionY ?? 50}%` }} />
                        <div style={{ position: "absolute", inset: "45% 0 0", padding: 12, display: "flex", flexDirection: "column", justifyContent: "end", background: "linear-gradient(transparent, rgba(0,0,0,.82))", color: "#FFF" }}>
                          <strong style={{ fontSize: flyerPreviewDevice === "mobile" ? 15 : 19, overflowWrap: "anywhere" }}>{eventDetails.title || "Event name"}</strong>
                          <span style={{ fontSize: 11 }}>{eventDetails.dateStr || eventDetails.eventDateIso || "Event date"} · {eventDetails.venueName || "Venue"}</span>
                        </div>
                      </div>
                      <div style={{ padding: 10, color: "#CBD5E1", fontSize: 10 }}>Discovery card: 1200×800 recommended · Event detail / social share: 1600×900 · Mobile cover: 1080×1350 portrait-safe</div>
                    </div>
                  </div>
                </section>

                <EventMediaManager event={eventDetails} disabled={!permissions.canManageEventsAndMenus} onChange={onUpdateEventDetails} />
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
                  display across regions. For an overnight event, leave the end
                  date blank; choose a later date for a multi-day event.
                </p>
              </div>

              {/* Event scheduling */}
              <section
                style={{
                  padding: "14px",
                  borderRadius: "12px",
                  background: "#0B1120",
                  border: "1px solid rgba(56, 189, 248, 0.28)",
                  display: "flex",
                  flexDirection: "column",
                  gap: "12px",
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
                  <Calendar size={13} color="#38BDF8" />
                  <span
                    style={{
                      fontSize: "11px",
                      fontWeight: 800,
                      color: "#E2E8F0",
                      textTransform: "uppercase",
                      letterSpacing: "0.6px",
                    }}
                  >
                    Event Schedule
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
                      Start Date (ISO YYYY-MM-DD)
                    </label>
                    <input
                      id="wizard-event-date-iso-input"
                      type="date"
                      value={eventDetails.eventDateIso || ""}
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
                    {fieldErrorText(dateLocationValidation.fieldErrors.eventDateIso)}
                  </div>

                  <div>
                    <label
                      htmlFor="wizard-event-end-date-iso-input"
                      style={labelStyle}
                    >
                      End Date (optional for overnight events)
                    </label>
                    <input
                      id="wizard-event-end-date-iso-input"
                      type="date"
                      value={eventDetails.endDateIso || ""}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) => {
                        const iso = e.target.value;
                        const parsed = iso
                          ? new Date(`${iso}T12:00:00`)
                          : null;
                        const formattedLabel = parsed && !Number.isNaN(parsed.getTime())
                          ? parsed.toLocaleDateString("en-US", {
                              weekday: "long",
                              month: "long",
                              day: "numeric",
                              year: "numeric",
                            })
                          : "";
                        onUpdateEventDetails({
                          ...eventDetails,
                          endDateIso: iso,
                          endDateStr: formattedLabel,
                        });
                      }}
                      placeholder="Leave blank if it ends the next morning"
                      style={inputStyle}
                    />
                    {fieldErrorText(
                      dateLocationValidation.fieldErrors.endDateIso
                    )}
                    {eventDetails.endDateIso && eventDetails.endDateStr && (
                      <span style={{ fontSize: "10px", color: "#94A3B8" }}>
                        Ends {eventDetails.endDateStr}
                      </span>
                    )}
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
                    {fieldErrorText(dateLocationValidation.fieldErrors.startTime)}
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
                    <label htmlFor="wizard-doors-open-input" style={labelStyle}>
                      Doors Open Time
                    </label>
                    <input
                      id="wizard-doors-open-input"
                      type="time"
                      value={eventDetails.doorsOpenTime || ""}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) =>
                        updateField("doorsOpenTime", e.target.value)
                      }
                      placeholder="e.g. 11:00"
                      style={inputStyle}
                    />
                    {fieldErrorText(
                      dateLocationValidation.fieldErrors.doorsOpenTime
                    )}
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
                    <label
                      htmlFor="wizard-food-service-start-input"
                      style={labelStyle}
                    >
                      Food Service Start
                    </label>
                    <input
                      id="wizard-food-service-start-input"
                      type="time"
                      value={eventDetails.foodServiceStartTime || ""}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) =>
                        updateField("foodServiceStartTime", e.target.value)
                      }
                      placeholder="e.g. 12:00"
                      style={inputStyle}
                    />
                    {fieldErrorText(
                      dateLocationValidation.fieldErrors.foodServiceStartTime
                    )}
                  </div>

                  <div>
                    <label
                      htmlFor="wizard-food-service-end-input"
                      style={labelStyle}
                    >
                      Food Service End
                    </label>
                    <input
                      id="wizard-food-service-end-input"
                      type="time"
                      value={eventDetails.foodServiceEndTime || ""}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) =>
                        updateField("foodServiceEndTime", e.target.value)
                      }
                      placeholder="e.g. 20:00"
                      style={inputStyle}
                    />
                    {fieldErrorText(
                      dateLocationValidation.fieldErrors.foodServiceEndTime
                    )}
                  </div>

                  <div>
                    <label htmlFor="wizard-setup-time-input" style={labelStyle}>
                      Setup / Load-In Time
                    </label>
                    <input
                      id="wizard-setup-time-input"
                      type="time"
                      value={eventDetails.setupTime || ""}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) =>
                        updateField("setupTime", e.target.value)
                      }
                      placeholder="e.g. 08:00"
                      style={inputStyle}
                    />
                    {fieldErrorText(dateLocationValidation.fieldErrors.setupTime)}
                  </div>
                </div>

                {/* Ticket sales windows */}
                <div
                  style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))",
                    gap: "12px",
                  }}
                >
                  <div>
                    <label
                      htmlFor="wizard-ticket-sales-start-input"
                      style={labelStyle}
                    >
                      Ticket Sales Start
                    </label>
                    <input
                      id="wizard-ticket-sales-start-input"
                      type="datetime-local"
                      value={eventDetails.ticketSalesStartIso || ""}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) =>
                        updateField("ticketSalesStartIso", e.target.value)
                      }
                      placeholder="When general admission tickets go on sale"
                      style={inputStyle}
                    />
                    {fieldErrorText(dateLocationValidation.fieldErrors.ticketSalesStartIso)}
                  </div>

                  <div>
                    <label
                      htmlFor="wizard-ticket-sales-end-input"
                      style={labelStyle}
                    >
                      Ticket Sales End
                    </label>
                    <input
                      id="wizard-ticket-sales-end-input"
                      type="datetime-local"
                      value={eventDetails.ticketSalesEndIso || ""}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) =>
                        updateField("ticketSalesEndIso", e.target.value)
                      }
                      placeholder="When ticket sales close"
                      style={inputStyle}
                    />
                    {fieldErrorText(dateLocationValidation.fieldErrors.ticketSalesEndIso)}
                  </div>
                </div>

                {/* Early-bird sales period */}
                <div
                  style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))",
                    gap: "12px",
                  }}
                >
                  <div>
                    <label
                      htmlFor="wizard-early-bird-start-input"
                      style={labelStyle}
                    >
                      Early-Bird Sales Start
                    </label>
                    <input
                      id="wizard-early-bird-start-input"
                      type="datetime-local"
                      value={eventDetails.earlyBirdSaleStartIso || ""}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) =>
                        updateField("earlyBirdSaleStartIso", e.target.value)
                      }
                      placeholder="Discounted early tickets begin"
                      style={inputStyle}
                    />
                    {fieldErrorText(dateLocationValidation.fieldErrors.earlyBirdSaleStartIso)}
                  </div>

                  <div>
                    <label
                      htmlFor="wizard-early-bird-end-input"
                      style={labelStyle}
                    >
                      Early-Bird Sales End
                    </label>
                    <input
                      id="wizard-early-bird-end-input"
                      type="datetime-local"
                      value={eventDetails.earlyBirdSaleEndIso || ""}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) =>
                        updateField("earlyBirdSaleEndIso", e.target.value)
                      }
                      placeholder="Early-bird pricing ends"
                      style={inputStyle}
                    />
                    {fieldErrorText(
                      dateLocationValidation.fieldErrors.earlyBirdSaleEndIso
                    )}
                  </div>
                </div>
              </section>

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
                      Event Timezone (IANA)
                    </label>
                    <input
                      id="wizard-timezone-select"
                      type="text"
                      list="wizard-timezone-options"
                      value={eventDetails.timezone || ""}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) => updateField("timezone", e.target.value)}
                      placeholder="America/New_York"
                      style={inputStyle}
                    />
                    <datalist id="wizard-timezone-options">
                      {SUPPORTED_EVENT_TIMEZONES.map((tz) => (
                        <option key={tz.id} value={tz.id} label={`${tz.label} (${tz.utcOffsetLabel})`} />
                      ))}
                    </datalist>
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
                    ({eventDetails.timezone || "America/New_York"})
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
              {eventDetails.eventFormat !== "virtual" ? (
                <>
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  gap: "8px",
                  marginBottom: "6px",
                }}
              >
                <MapPin size={13} color="#34D399" />
                <span
                  style={{
                    fontSize: "11px",
                    fontWeight: 800,
                    color: "#E2E8F0",
                    textTransform: "uppercase",
                    letterSpacing: "0.6px",
                  }}
                >
                  Venue Location
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
                  <label htmlFor="wizard-venue-name-input" style={labelStyle}>
                    Venue Name *
                  </label>
                  <input
                    id="wizard-venue-name-input"
                    type="text"
                    value={eventDetails.venueName}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) => updateField("venueName", e.target.value)}
                    placeholder="Biscayne Bay Waterfront Park Amphitheater"
                    style={inputStyle}
                  />
                  {fieldErrorText(dateLocationValidation.fieldErrors.venueName)}
                </div>
                <div>
                  <label htmlFor="wizard-venue-type-input" style={labelStyle}>
                    Venue Type
                  </label>
                  <select
                    id="wizard-venue-type-input"
                  value={eventDetails.venueType || ""}
                  disabled={!permissions.canManageEventsAndMenus}
                  onChange={(e) => updateField("venueType", e.target.value)}
                  style={inputStyle}
                  >
                  <option value="">Select a venue type</option>
                  <option value="park">Park or outdoor space</option>
                  <option value="hall">Hall or community center</option>
                  <option value="restaurant">Restaurant or bar</option>
                  <option value="church">Church or place of worship</option>
                  <option value="school">School or campus</option>
                  <option value="stadium">Stadium or arena</option>
                  <option value="beach">Beach or waterfront</option>
                  <option value="private">Private residence</option>
                  <option value="street">Street or block party</option>
                  <option value="other">Other</option>
                  </select>
                </div>
                <div>
                  <label htmlFor="wizard-country-input" style={labelStyle}>
                    Country *
                  </label>
                  <select
                    id="wizard-country-input"
                    value={eventDetails.countryCode || "US"}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) =>
                      updateAddressField("countryCode", e.target.value, e.target.value)
                    }
                    style={inputStyle}
                  >
                    {getRegisteredLocationCatalogs().map((code) => {
                      const catalog = getLocationCatalog(code);
                      return (
                        <option key={code} value={code}>
                          {catalog.countryName}
                        </option>
                      );
                    })}
                  </select>
                </div>
              </div>

              <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(190px, 1fr))", gap: "12px" }}>
                <div>
                  <label htmlFor="wizard-street-address" style={labelStyle}>Street address *</label>
                  <input id="wizard-street-address" type="text" value={eventDetails.streetAddress || ""} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateAddressField("streetAddress", e.target.value)} autoComplete="street-address" style={inputStyle} />
                  {fieldErrorText(dateLocationValidation.fieldErrors.streetAddress)}
                </div>
                <div>
                  <label htmlFor="wizard-building-name" style={labelStyle}>Building / room</label>
                  <input id="wizard-building-name" type="text" value={eventDetails.venueBuildingName || ""} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateAddressField("venueBuildingName", e.target.value)} style={inputStyle} />
                </div>
                <div>
                  <label htmlFor="wizard-city" style={labelStyle}>City or town *</label>
                  <input id="wizard-city" type="text" value={eventDetails.city || ""} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateAddressField("city", e.target.value)} autoComplete="address-level2" style={inputStyle} />
                  {fieldErrorText(dateLocationValidation.fieldErrors.city)}
                </div>
                <div>
                  <label htmlFor="wizard-state-code" style={labelStyle}>
                    {eventDetails.countryCode === "US" || !eventDetails.countryCode ? "State / territory *" : "State / province / region"}
                  </label>
                  {Object.keys(locationOptions).length > 0 ? (
                    <select
                      id="wizard-state-code"
                      value={eventDetails.stateCode || ""}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) => updateAddressField("stateCode", e.target.value)}
                      style={inputStyle}
                    >
                      <option value="">Select a state or territory</option>
                      {Object.entries(locationOptions).map(([code, name]) => (
                        <option key={code} value={code}>{name}</option>
                      ))}
                    </select>
                  ) : (
                    <input
                      id="wizard-state-code"
                      type="text"
                      value={eventDetails.stateCode || ""}
                      disabled={!permissions.canManageEventsAndMenus}
                      onChange={(e) => updateAddressField("stateCode", e.target.value)}
                      autoComplete="address-level1"
                      style={inputStyle}
                    />
                  )}
                  {fieldErrorText(dateLocationValidation.fieldErrors.stateCode)}
                </div>
                <div>
                  <label htmlFor="wizard-zip-code" style={labelStyle}>
                    {eventDetails.countryCode === "US" || !eventDetails.countryCode ? "ZIP code *" : "Postal code"}
                  </label>
                  <input id="wizard-zip-code" type="text" value={eventDetails.zipCode || ""} disabled={!permissions.canManageEventsAndMenus} onChange={(e) => updateAddressField("zipCode", e.target.value)} maxLength={32} autoComplete="postal-code" placeholder={eventDetails.countryCode === "US" || !eventDetails.countryCode ? "12345" : "Postal code"} style={inputStyle} />
                  {fieldErrorText(dateLocationValidation.fieldErrors.zipCode)}
                </div>
              </div>

              <div
                style={{
                  display: "grid",
                  gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
                  gap: "12px",
                }}
              >
                <div>
                  <label htmlFor="wizard-venue-instructions-input" style={labelStyle}>
                    Venue Instructions
                  </label>
                  <textarea
                    id="wizard-venue-instructions-input"
                    rows={2}
                    value={eventDetails.venueInstructions || ""}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) =>
                      updateField("venueInstructions", e.target.value)
                    }
                    placeholder="Load-in entrance, staging area, house rules…"
                    style={{ ...inputStyle, height: "auto", padding: "10px 12px" }}
                  />
                </div>
                <div>
                  <label htmlFor="wizard-parking-instructions-input" style={labelStyle}>
                    Parking Information
                  </label>
                  <textarea
                    id="wizard-parking-instructions-input"
                    rows={2}
                    value={eventDetails.parkingInformation || ""}
                    disabled={!permissions.canManageEventsAndMenus}
                    onChange={(e) =>
                      updateField("parkingInformation", e.target.value)
                    }
                    placeholder="Parking lot location, fees, accessible parking…"
                    style={{ ...inputStyle, height: "auto", padding: "10px 12px" }}
                  />
                </div>
              </div>

              {/* Interactive map */}
              <VenueMapLocator
                eventDetails={{
                  streetAddress: eventDetails.streetAddress,
                  city: eventDetails.city,
                  stateCode: eventDetails.stateCode,
                  zipCode: eventDetails.zipCode,
                  countryCode: eventDetails.countryCode,
                  venueName: eventDetails.venueName,
                  venueAddress: eventDetails.venueAddress,
                  latitude: eventDetails.latitude,
                  longitude: eventDetails.longitude,
                  coordinates: eventDetails.coordinates,
                  timezone: eventDetails.timezone,
                  locationVerified: eventDetails.locationVerified,
                  locationSource: eventDetails.locationSource,
                  locationPreviewUrl: eventDetails.locationPreviewUrl,
                }}
                onUpdateLocation={(patch) =>
                  onUpdateEventDetails({ ...eventDetails, ...patch })
                }
                disabled={!permissions.canManageEventsAndMenus}
              />

              <div>
                <label
                  htmlFor="wizard-accessibility-input"
                  style={labelStyle}
                >
                  Accessibility Details
                </label>
                <textarea
                  id="wizard-accessibility-input"
                  rows={2}
                  value={eventDetails.accessibilityInformation || ""}
                  disabled={!permissions.canManageEventsAndMenus}
                  onChange={(e) =>
                    updateField("accessibilityInformation", e.target.value)
                  }
                  placeholder="Share mobility access, sensory accommodations, or contact details for accessibility requests."
                  style={{ ...inputStyle, height: "auto", padding: "10px 12px" }}
                />
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
                </>
              ) : (
                <p style={{ margin: 0, color: "#94A3B8", fontSize: "12px" }}>
                  This event is online-only. Add the attendee destination and access instructions in Basic Details.
                </p>
              )}
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
                    Ticket Types, Prices, Benefits & Live Ticket Designer
                  </h3>
                  <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
                    Configure admission and package tiers. All pricing and
                    capacity rules are validated on both client and server.
                  </p>
                </div>

                <button
                  type="button"
                  id="wizard-add-ticket-tier-btn"
                  disabled={!permissions.canManageEventsAndMenus || ticketTiers.length >= 20}
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
                  Add Ticket
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
                      id={`wizard-ticket-tier-card-${tier.id}`}
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
                            <option value="General Admission">General Admission</option>
                            <option value="VIP">VIP</option>
                            <option value="Premium VIP">Premium VIP</option>
                            <option value="Early Bird">Early Bird</option>
                            <option value="Family Pass">Family Pass</option>
                            <option value="Group Pass">Group Pass</option>
                            <option value="Student Ticket">Student Ticket</option>
                            <option value="Child Ticket">Child Ticket</option>
                            <option value="Complimentary Ticket">Complimentary Ticket</option>
                            <option value="Vendor Pass">Vendor Pass</option>
                            <option value="Staff Pass">Staff Pass</option>
                            <option value="Custom Ticket Type">Custom Ticket Type</option>
                            <option value="General">Legacy: General</option>
                            <option value="Group">Legacy: Group</option>
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

                      <div>
                        <label style={labelStyle}>Ticket description</label>
                        <textarea
                          rows={2}
                          maxLength={300}
                          value={tier.description || ""}
                          disabled={!permissions.canManageEventsAndMenus}
                          onChange={(e) => handleUpdateTier(tier.id, { description: e.target.value })}
                          placeholder="Describe who this ticket is for and what it includes."
                          style={{ ...inputStyle, height: "auto", padding: 10, resize: "vertical" }}
                        />
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
                          <label style={labelStyle}>Sales Start</label>
                          <input
                            type="datetime-local"
                            value={tier.saleStartIso || ""}
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
                          <label style={labelStyle}>Sales End</label>
                          <input
                            type="datetime-local"
                            value={tier.saleEndIso || ""}
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

                      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(150px, 1fr))", gap: 10 }}>
                        <div>
                          <label style={labelStyle}>Minimum per order</label>
                          <input type="number" min={1} max={100} step={1} value={tier.minPerOrder ?? 1}
                            disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) => handleUpdateTier(tier.id, { minPerOrder: Math.max(1, Math.round(Number(e.target.value))) })}
                            style={inputStyle} />
                        </div>
                        <div>
                          <label style={labelStyle}>Admissions per ticket</label>
                          <input type="number" min={1} max={100} step={1} value={tier.admissionCount ?? 1}
                            disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) => handleUpdateTier(tier.id, { admissionCount: Math.max(1, Math.round(Number(e.target.value))) })}
                            style={inputStyle} />
                        </div>
                        <div>
                          <label style={labelStyle}>Age restriction</label>
                          <input type="text" maxLength={100} value={tier.ageRestriction || ""}
                            disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) => handleUpdateTier(tier.id, { ageRestriction: e.target.value })}
                            placeholder="All ages, 18+, 21+"
                            style={inputStyle} />
                        </div>
                        <div>
                          <label style={labelStyle}>Currency</label>
                          <input type="text" value={eventDetails.currency || "USD"} readOnly aria-readonly="true"
                            title="Ticket prices use the event's checkout currency."
                            style={{ ...inputStyle, opacity: 0.75 }} />
                          <span style={{ fontSize: 9, color: "#94A3B8" }}>Event checkout currency</span>
                        </div>
                      </div>

                      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 10 }}>
                        <div>
                          <label style={labelStyle}>Admission privileges</label>
                          <textarea rows={3} maxLength={1000} value={tier.admissionPrivileges || ""}
                            disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) => handleUpdateTier(tier.id, { admissionPrivileges: e.target.value })}
                            placeholder="Areas, entry lanes, seating access…"
                            style={{ ...inputStyle, height: "auto", padding: 10, resize: "vertical" }} />
                        </div>
                        <div>
                          <label style={labelStyle}>Included food or drinks</label>
                          <textarea rows={3} maxLength={1000} value={tier.includedFoodAndDrinks || ""}
                            disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) => handleUpdateTier(tier.id, { includedFoodAndDrinks: e.target.value })}
                            placeholder="What's included with this ticket?"
                            style={{ ...inputStyle, height: "auto", padding: 10, resize: "vertical" }} />
                        </div>
                        <div>
                          <label style={labelStyle}>Benefits (one per line)</label>
                          <textarea rows={3} maxLength={1000} value={(tier.benefits || []).join("\n")}
                            disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) => handleUpdateTier(tier.id, { benefits: e.target.value.split(/\r?\n/).map((value) => value.trim()).filter(Boolean) })}
                            placeholder="VIP lounge&#10;Welcome drink"
                            style={{ ...inputStyle, height: "auto", padding: 10, resize: "vertical" }} />
                        </div>
                      </div>

                      <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", gap: 14 }}>
                        <label style={{ display: "inline-flex", gap: 6, alignItems: "center", color: "#CBD5E1", fontSize: 11 }}>
                          <input type="checkbox" checked={tier.refundEligible !== false} disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) => handleUpdateTier(tier.id, { refundEligible: e.target.checked })} />
                          Eligible for refunds under event policy
                        </label>
                        <label style={{ display: "inline-flex", gap: 6, alignItems: "center", color: "#CBD5E1", fontSize: 11 }}>
                          Visibility
                          <select value={tier.visibility || "public"} disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) => handleUpdateTier(tier.id, { visibility: e.target.value as "public" | "hidden" })} style={inputStyle}>
                            <option value="public">Public</option>
                            <option value="hidden">Hidden (not listed)</option>
                          </select>
                        </label>
                        <label style={{ display: "inline-flex", gap: 6, alignItems: "center", color: "#CBD5E1", fontSize: 11 }}>
                          Status
                          <select value={tier.isActive === false ? "inactive" : "active"} disabled={!permissions.canManageEventsAndMenus}
                            onChange={(e) => handleUpdateTier(tier.id, { isActive: e.target.value === "active" })} style={inputStyle}>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                          </select>
                        </label>
                      </div>

                      <TicketPreview
                        event={eventDetails}
                        ticket={tier}
                        currency={eventDetails.currency || "USD"}
                        onDesignChange={(designStyle) => handleUpdateTier(tier.id, { designStyle })}
                        onAccentChange={(accentColor) => handleUpdateTier(tier.id, { accentColor })}
                        onBackgroundChange={(backgroundStyle) => handleUpdateTier(tier.id, { backgroundStyle })}
                      />

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

          {/* STEP 4: FOOD MENU (Sections 7.1-7.6) */}
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
                    Plan the Food Experience Before Publishing
                  </h3>
                  <p style={{ margin: 0, fontSize: "12px", color: "#94A3B8" }}>
                    Configure food service mode, build individual items and meal
                    packages, and preview the attendee ordering experience.
                  </p>
                </div>

              </div>

              {/* 7.1 Food Service Configuration */}
              <FoodServiceConfigSection
                allowOvernight={Boolean(
                  (eventDetails.eventDateIso && eventDetails.endDateIso && eventDetails.endDateIso > eventDetails.eventDateIso) ||
                  (!eventDetails.endDateIso && eventDetails.startTime && eventDetails.endTime && eventDetails.endTime <= eventDetails.startTime)
                )}
                config={
                  eventDetails.foodServiceConfig ?? {
                    enabled: supportsFoodOrdering,
                    serviceMode: supportsFoodOrdering ? "both" : "disabled",
                    fulfillmentMode: "pickup",
                    foodServiceStartTime: eventDetails.foodServiceStartTime,
                    foodServiceEndTime: eventDetails.foodServiceEndTime,
                    orderingDeadlineIso: eventDetails.advanceOrderDeadline,
                    packagingFeeUSD: eventDetails.packagingFeeUSD,
                    packagingFeeJMD: eventDetails.packagingFeeJMD,
                    serviceFeeUSD: undefined,
                    serviceFeeJMD: undefined,
                    maxOrdersPerEvent: undefined,
                    pickupWindows: [],
                  }
                }
                currency={eventDetails.currency || "USD"}
                disabled={!permissions.canManageEventsAndMenus}
                onChange={(next) =>
                  onUpdateEventDetails({
                    ...eventDetails,
                    foodServiceConfig: next,
                    supportsFoodOrdering: next.enabled,
                  })
                }
              />

              {/* 7.6 Menu presentation preview tabs */}
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  gap: "6px",
                  borderBottom: "1px solid rgba(148, 163, 184, 0.2)",
                  paddingBottom: "8px",
                  marginBottom: "10px",
                }}
              >
                {[
                  { id: "menu", label: "Menu Builder" },
                  { id: "package", label: "Package Builder" },
                  { id: "preview", label: "Attendee Preview" },
                ].map((tab) => {
                  const active = tab.id === activeFoodTab;
                  return (
                    <button
                      key={tab.id}
                      type="button"
                      onClick={() => setActiveFoodTab(tab.id)}
                      disabled={!permissions.canManageEventsAndMenus}
                      style={{
                        padding: "7px 14px",
                        borderRadius: "8px",
                        border: `1px solid ${
                          active
                            ? "rgba(52, 211, 153, 0.5)"
                            : "rgba(148, 163, 184, 0.25)"
                        }`,
                        background: active
                          ? "rgba(52, 211, 153, 0.12)"
                          : "rgba(30, 41, 59, 0.7)",
                        color: active ? "#34D399" : "#CBD5E1",
                        fontSize: "11px",
                        fontWeight: 700,
                        cursor:
                          !permissions.canManageEventsAndMenus
                            ? "not-allowed"
                            : "pointer",
                      }}
                    >
                      {tab.label}
                    </button>
                  );
                })}
              </div>

              {/* 7.2 Menu Item Builder */}
              {activeFoodTab === "menu" && (
                <MenuItemBuilder
                  item={editingItem}
                  timezone={eventDetails.timezone || "America/New_York"}
                  disabled={!permissions.canManageEventsAndMenus}
                  onSave={(savedItem) => {
                    const next = editingItem
                      ? menu.map((m) =>
                          m.id === savedItem.id ? savedItem : m
                        )
                      : [...menu, savedItem];
                    onUpdateMenu(next);
                    setEditingItem(null);
                  }}
                  onDelete={() => {
                    if (!editingItem) return;
                    onUpdateMenu(
                      menu.filter((m) => m.id !== editingItem!.id)
                    );
                    setEditingItem(null);
                  }}
                  onClose={() => setEditingItem(null)}
                />
              )}

              {activeFoodTab === "menu" && permissions.canManageEventsAndMenus && (
                <div
                  style={{
                    display: "flex",
                    gap: "8px",
                    marginTop: "8px",
                    flexWrap: "wrap",
                  }}
                >
                  <button
                    type="button"
                    id="wizard-add-menu-item-btn"
                    onClick={() =>
                      setEditingItem({
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
                      })
                    }
                    style={{
                      padding: "7px 14px",
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
                    Add Menu Item
                  </button>
                  {editingItem && (
                    <button
                      type="button"
                      id="wizard-cancel-item-btn"
                      onClick={() => setEditingItem(null)}
                      style={{
                        padding: "7px 14px",
                        borderRadius: "8px",
                        background: "rgba(148, 163, 184, 0.16)",
                        color: "#CBD5E1",
                        border: "1px solid rgba(148, 163, 184, 0.25)",
                        fontSize: "11px",
                        fontWeight: 700,
                        cursor: "pointer",
                      }}
                    >
                      Cancel
                    </button>
                  )}
                </div>
              )}

              {/* 7.4 Package Builder */}
              {activeFoodTab === "package" && (
                <PackageBuilder
                  menu={menu}
                  currency={eventDetails.currency || "USD"}
                  disabled={!permissions.canManageEventsAndMenus}
                  editingPackage={editingPackage}
                  onSave={(savedPkg) => {
                    const next = editingPackage
                      ? (eventDetails.packages || []).map((p) =>
                          p.id === savedPkg.id ? savedPkg : p
                        )
                      : [...(eventDetails.packages || []), savedPkg];
                    onUpdateEventDetails({ ...eventDetails, packages: next });
                    setEditingPackage(null);
                  }}
                  onDelete={(id) =>
                    onUpdateEventDetails({
                      ...eventDetails,
                      packages: (eventDetails.packages || []).filter(
                        (p) => p.id !== id
                      ),
                    })
                  }
                />
              )}

              {activeFoodTab === "package" && permissions.canManageEventsAndMenus && (
                <div
                  style={{
                    display: "flex",
                    gap: "8px",
                    marginTop: "8px",
                    flexWrap: "wrap",
                  }}
                >
                  <button
                    type="button"
                    id="wizard-add-package-btn"
                    onClick={() =>
                      setEditingPackage({
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
                      })
                    }
                    style={{
                      padding: "7px 14px",
                      borderRadius: "8px",
                      background: "#FBBF24",
                      color: "#451A03",
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
                    Add Package
                  </button>
                  {editingPackage && (
                    <button
                      type="button"
                      id="wizard-cancel-package-btn"
                      onClick={() => setEditingPackage(null)}
                      style={{
                        padding: "7px 14px",
                        borderRadius: "8px",
                        background: "rgba(148, 163, 184, 0.16)",
                        color: "#CBD5E1",
                        border: "1px solid rgba(148, 163, 184, 0.25)",
                        fontSize: "11px",
                        fontWeight: 700,
                        cursor: "pointer",
                      }}
                    >
                      Cancel
                    </button>
                  )}
                </div>
              )}

              {/* Attendee Preview (7.6) */}
              {activeFoodTab === "preview" && (
                <div
                  style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fill, minmax(260px, 1fr))",
                    gap: "16px",
                  }}
                >
                  <div
                    style={{
                      padding: "14px",
                      borderRadius: "10px",
                      background: "#0B1120",
                      border: "1px solid rgba(148, 163, 184, 0.2)",
                    }}
                  >
                    <span
                      style={{
                        fontSize: "10px",
                        fontWeight: 800,
                        color: "#38BDF8",
                        textTransform: "uppercase",
                      }}
                    >
                      Attendee View
                    </span>
                    <p style={{ fontSize: "11px", color: "#94A3B8", margin: "6px 0 0" }}>
                      This is how the menu appears to attendees. Items and
                      packages render as food cards with images, prices, and
                      customization options. Unavailable items are hidden.
                    </p>
                  </div>

                  {menu
                    .filter((m) => m.isAvailable !== false)
                    .map((m) => (
                      <div
                        key={m.id}
                        style={{
                          padding: "14px",
                          borderRadius: "10px",
                          background: "#0B1120",
                          border: "1px solid rgba(148, 163, 184, 0.2)",
                          display: "flex",
                          flexDirection: "column",
                          gap: "8px",
                        }}
                      >
                        <div
                          style={{
                            width: "100%",
                            height: "120px",
                            borderRadius: "8px",
                            background: "#020617",
                            overflow: "hidden",
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "center",
                          }}
                        >
                          <FoodPhoto src={m.imageUrl} alt={m.name} />
                        </div>
                        <div>
                          <div
                            style={{
                              display: "flex",
                              justifyContent: "space-between",
                              alignItems: "flex-start",
                              marginBottom: "4px",
                            }}
                          >
                            <h4
                              style={{
                                fontSize: "14px",
                                fontWeight: 800,
                                color: "#F8FAFC",
                                margin: 0,
                              }}
                            >
                              {m.name}
                            </h4>
                            <span
                              style={{
                                fontSize: "11px",
                                fontWeight: 800,
                                color: "#38BDF8",
                              }}
                            >
                              {eventDetails.currency === "JMD"
                                ? `J$${m.priceJMD}`
                                : `$${m.priceUSD.toFixed(2)}`}
                            </span>
                          </div>
                          <p
                            style={{
                              fontSize: "11px",
                              color: "#94A3B8",
                              margin: "0 0 8px",
                              lineHeight: 1.4,
                            }}
                          >
                            {m.description}
                          </p>
                          <span
                            style={{
                              fontSize: "10px",
                              color: "#CBD5E1",
                              textTransform: "uppercase",
                              letterSpacing: "0.5px",
                            }}
                          >
                            {m.category} · {m.serviceMode || "both"}
                          </span>
                        </div>
                      </div>
                    ))}
                  {(eventDetails.packages || []).map((p) => (
                    <div
                      key={p.id}
                      style={{
                        padding: "14px",
                        borderRadius: "10px",
                        background: "#0B1120",
                        border: "1px solid rgba(148, 163, 184, 0.2)",
                        display: "flex",
                        flexDirection: "column",
                        gap: "8px",
                      }}
                    >
                      <div
                        style={{
                          width: "100%",
                          height: "120px",
                          borderRadius: "8px",
                          background: "#020617",
                          overflow: "hidden",
                          display: "flex",
                          alignItems: "center",
                          justifyContent: "center",
                        }}
                      >
                        <FoodPhoto src={p.imageUrl} alt={p.name} unavailableLabel="Package photo unavailable" />
                      </div>
                      <div>
                        <div
                          style={{
                            display: "flex",
                            justifyContent: "space-between",
                            alignItems: "flex-start",
                            marginBottom: "4px",
                          }}
                        >
                          <h4
                            style={{
                              fontSize: "14px",
                              fontWeight: 800,
                              color: "#F8FAFC",
                              margin: 0,
                            }}
                          >
                            {p.name}
                          </h4>
                          <span
                            style={{
                              fontSize: "11px",
                              fontWeight: 800,
                              color: "#FBBF24",
                            }}
                          >
                            {eventDetails.currency === "JMD"
                              ? `J$${p.basePriceJMD}`
                              : `$${p.basePriceUSD.toFixed(2)}`}
                          </span>
                        </div>
                        <p
                          style={{
                            fontSize: "11px",
                            color: "#94A3B8",
                            margin: "0 0 8px",
                            lineHeight: 1.4,
                          }}
                        >
                          {p.description}
                        </p>
                        <span
                          style={{
                            fontSize: "10px",
                            color: "#CBD5E1",
                            textTransform: "uppercase",
                            letterSpacing: "0.5px",
                          }}
                        >
                          Meal Package · {p.components.length} item(s)
                        </span>
                      </div>
                    </div>
                  ))}
                  {(menu.filter((m) => m.isAvailable !== false).length === 0 &&
                    !(eventDetails.packages || []).length) && (
                    <p style={{ fontSize: "11px", color: "#94A3B8", gridColumn: "1 / -1", textAlign: "center", padding: "20px" }}>
                      No items or packages configured yet. Use the Menu Builder or Package Builder tabs to add them.
                    </p>
                  )}
                </div>
              )}
              {supportsFoodOrdering ? (
                <div
                  style={{
                    marginTop: "8px",
                    fontSize: "11px",
                    color: "#94A3B8",
                  }}
                >
                  {menu.length} menu item(s) configured ·{" "}
                  {(eventDetails.packages || []).length} package(s) configured
                </div>
              ) : (
                <div
                  style={{
                    padding: "12px",
                    borderRadius: "8px",
                    background: "rgba(15, 23, 42, 0.6)",
                    border: "1px dashed rgba(148, 163, 184, 0.3)",
                    color: "#94A3B8",
                    fontSize: "12px",
                  }}
                >
                  Food ordering is disabled for this event. Attendees will
                  purchase admission tickets only.
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

              <CancellationPolicyEditor
                event={eventDetails}
                ticketCategories={[...new Set(ticketTiers.map((tier) => tier.category))]}
                disabled={!permissions.canManageEventsAndMenus}
                onChange={onUpdateEventDetails}
              />

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
                      `${window.location.origin}/e/${
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
                          `${window.location.origin}/e/${
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
                e.currentTarget.src = EVENT_PLACEHOLDER;
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
                {eventDetails.category || "Food & Drink"}
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
                  <strong>{eventDetails.timezone || "America/New_York"}</strong>
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
