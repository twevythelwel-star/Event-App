import React from "react";
import { Plus, Sparkles } from "lucide-react";
import {
  Currency,
  EventDetails,
  EventWizardStepId,
  MenuItem,
  OrganizerProfile,
} from "../../types";
import { FishFryEventItem } from "../../data/fishFryEvents";
import {
  HostNavToolId,
  HostRolePermissions,
  HostUpcomingEventCardData,
} from "./types";
import { HostUpcomingEventsSection } from "./HostUpcomingEventsSection";
import { EventCreationWizard, PreSaveFlyerState } from "./EventCreationWizard";
import { getDefaultEventTicketTiers } from "./wizardValidation";

type Props = {
  eventDetails: EventDetails;
  menu?: MenuItem[];
  activeOrganizer: OrganizerProfile;
  permissions: HostRolePermissions;
  upcomingEvents: HostUpcomingEventCardData[];
  flyerWarningToast: string | null;
  preSaveConfirmFlyer: PreSaveFlyerState | null;
  shareCopied: boolean;
  initialWizardStep?: EventWizardStepId;
  onManageEvent: (event: EventDetails | FishFryEventItem) => void;
  onNavigateTool: (tool: HostNavToolId) => void;
  onUpdateEventDetails: (details: EventDetails) => void;
  onUpdateMenu?: (menu: MenuItem[]) => void;
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

export function HostEventStudioView({
  eventDetails,
  menu = [],
  activeOrganizer,
  permissions,
  upcomingEvents,
  flyerWarningToast,
  preSaveConfirmFlyer,
  shareCopied,
  initialWizardStep = "basic_details",
  onManageEvent,
  onNavigateTool,
  onUpdateEventDetails,
  onUpdateMenu = () => {},
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
  formatMoney,
}: Props) {
  const handleStartNewDraftEvent = () => {
    if (!permissions.canManageEventsAndMenus) return;
    const draftId = `evt-draft-${Date.now().toString(36)}`;
    const draftEvent: EventDetails = {
      id: draftId,
      organizerId: activeOrganizer.id,
      slug: `island-event-${Date.now().toString(36)}`,
      title: "New Island Culinary & Music Showcase",
      tagline: "Fresh Caribbean Flavors, Live Riddims & Waterfront Vibes",
      category: "Seafood & Fish Fry",
      dateStr: "Saturday, December 12, 2026",
      eventDateIso: "2026-12-12",
      timeStr: "12:00 PM – 9:00 PM",
      startTime: "12:00",
      endTime: "21:00",
      timezone: eventDetails.timezone || "America/Jamaica",
      venueName: "Kingston Waterfront Pier",
      venueAddress: "Ocean Boulevard, Downtown Kingston, Jamaica",
      coordinates: "17.9647° N, 76.7936° W",
      description:
        "Join us for an authentic Caribbean culinary showcase featuring fresh escovitch snapper, roast breadfruit, festival, and live acoustic performances.",
      currency: eventDetails.currency || "JMD",
      organizerName: activeOrganizer.name,
      organizerPhone: activeOrganizer.contactPhone || "+1 (876) 926-3849",
      organizerWhatsApp: activeOrganizer.contactWhatsApp || "+1 (876) 555-4321",
      organizerEmail: activeOrganizer.contactEmail || "events@islandevents.example",
      advanceOrderDeadline: "2026-12-10T20:00:00-05:00",
      isOrderingOpen: false,
      status: "draft",
      pickupInstructions:
        "Present your verified QR code at Express Collection Tent A.",
      packagingFeeUSD: 1.0,
      packagingFeeJMD: 150,
      supportsFoodOrdering: true,
      ticketTiers: getDefaultEventTicketTiers(),
      flyerUrl: undefined,
      isFlyerPublic: false,
      shareableUrl: `https://islandevents.app/e/${draftId}`,
    };

    onUpdateEventDetails(draftEvent);
    onSaveToast(
      "Started a new Draft Event in the 5-step Guided Creation Wizard."
    );
  };

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "18px" }}>
      {/* Top Action Bar for Launching a Fresh Event Draft */}
      <div
        style={{
          padding: "14px 18px",
          borderRadius: "14px",
          background: "#0B1120",
          border: "1px solid rgba(148, 163, 184, 0.18)",
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          flexWrap: "wrap",
          gap: "12px",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
          <Sparkles size={16} color="#34D399" />
          <div>
            <strong style={{ fontSize: "13px", color: "#F8FAFC", display: "block" }}>
              Host Event Portfolio & Guided Event-Creation Studio
            </strong>
            <span style={{ fontSize: "11px", color: "#94A3B8" }}>
              Select any upcoming event below to configure it, or launch a new
              draft event in the 5-step creation wizard.
            </span>
          </div>
        </div>

        <button
          type="button"
          id="launch-new-event-wizard-btn"
          disabled={!permissions.canManageEventsAndMenus}
          onClick={handleStartNewDraftEvent}
          style={{
            padding: "8px 14px",
            borderRadius: "10px",
            background:
              "linear-gradient(135deg, #6366F1 0%, #4F46E5 50%, #10B981 100%)",
            color: "#FFFFFF",
            border: "none",
            fontSize: "11px",
            fontWeight: 800,
            display: "inline-flex",
            alignItems: "center",
            gap: "6px",
            cursor: permissions.canManageEventsAndMenus
              ? "pointer"
              : "not-allowed",
          }}
        >
          <Plus size={14} />
          Create New Draft Event
        </button>
      </div>

      {/* Upcoming Host Events Portfolio */}
      <HostUpcomingEventsSection
        upcomingEvents={upcomingEvents}
        onManageEvent={onManageEvent}
        onNavigateTool={onNavigateTool}
      />

      {/* 5-Stage Guided Event-Creation & Configuration Wizard */}
      <EventCreationWizard
        eventDetails={eventDetails}
        menu={menu}
        activeOrganizer={activeOrganizer}
        permissions={permissions}
        flyerWarningToast={flyerWarningToast}
        preSaveConfirmFlyer={preSaveConfirmFlyer}
        shareCopied={shareCopied}
        initialStep={initialWizardStep}
        onUpdateEventDetails={onUpdateEventDetails}
        onUpdateMenu={onUpdateMenu}
        onOpenUnpublishModal={onOpenUnpublishModal}
        onOpenCancelEventModal={onOpenCancelEventModal}
        onFlyerUpload={onFlyerUpload}
        onConfirmPreSavedFlyer={onConfirmPreSavedFlyer}
        onCancelPreSavedFlyer={onCancelPreSavedFlyer}
        onRemoveFlyer={onRemoveFlyer}
        onCopyShareUrl={onCopyShareUrl}
        onSaveToast={onSaveToast}
        onOpenAddDishModal={onOpenAddDishModal}
        onOpenEditDishModal={onOpenEditDishModal}
        onDeleteDish={onDeleteDish}
        formatMoney={formatMoney}
      />
    </div>
  );
}
