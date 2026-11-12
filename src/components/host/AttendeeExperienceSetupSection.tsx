import React, { useState } from "react";
import {
  Bell,
  Calendar,
  CheckCircle2,
  FileQuestion,
  HelpCircle,
  Lock,
  Megaphone,
  MessageSquare,
  Plus,
  ShieldCheck,
  Sparkles,
  Star,
  Trash2,
  Users,
  Utensils,
} from "lucide-react";
import {
  AttendeeRegistrationQuestion,
  AttendeeRegistrationQuestionCategory,
  AttendeeRegistrationQuestionType,
  EventAnnouncementItem,
  EventAttendeeExperienceConfig,
  EventDetails,
  EventFaqItem,
  EventReminderChannel,
} from "../../types";
import {
  computeFeedbackAggregate,
  DEFAULT_ATTENDEE_REGISTRATION_QUESTIONS,
  formatPrivacyRetentionDisclosure,
  getDefaultAttendeeExperienceConfig,
} from "../../lib/attendeeExperience";

interface AttendeeExperienceSetupSectionProps {
  eventDetails: EventDetails;
  onChange: (updatedConfig: EventAttendeeExperienceConfig) => void;
  onSyncLegacyFields?: (fields: {
    venueInstructions?: string;
    parkingInformation?: string;
    accessibilityInformation?: string;
    dressCode?: string;
    ageRestriction?: string;
  }) => void;
}

export const AttendeeExperienceSetupSection: React.FC<
  AttendeeExperienceSetupSectionProps
> = ({ eventDetails, onChange, onSyncLegacyFields }) => {
  const config = getDefaultAttendeeExperienceConfig(eventDetails);

  // New Question state
  const [newQuestionPrompt, setNewQuestionPrompt] = useState("");
  const [newQuestionType, setNewQuestionType] =
    useState<AttendeeRegistrationQuestionType>("text");
  const [newQuestionCategory, setNewQuestionCategory] =
    useState<AttendeeRegistrationQuestionCategory>("general");
  const [newQuestionRequired, setNewQuestionRequired] = useState(false);
  const [newQuestionOptions, setNewQuestionOptions] = useState("");
  const [newQuestionPurpose, setNewQuestionPurpose] = useState(
    "Used only for guest hospitality during this event and automatically purged after the retention period."
  );

  // New FAQ state
  const [newFaqQuestion, setNewFaqQuestion] = useState("");
  const [newFaqAnswer, setNewFaqAnswer] = useState("");

  // New Rule state
  const [newRuleText, setNewRuleText] = useState("");

  // New Announcement state
  const [newAnnouncementTitle, setNewAnnouncementTitle] = useState("");
  const [newAnnouncementMessage, setNewAnnouncementMessage] = useState("");
  const [newAnnouncementPriority, setNewAnnouncementPriority] = useState<
    "info" | "important" | "urgent"
  >("info");

  const updateConfig = (partial: Partial<EventAttendeeExperienceConfig>) => {
    const next = {
      ...config,
      ...partial,
    };
    onChange(next);
  };

  const handleToggleQuestionRequired = (questionId: string) => {
    const nextQuestions = config.registrationQuestions.map((q) =>
      q.id === questionId ? { ...q, required: !q.required } : q
    );
    updateConfig({ registrationQuestions: nextQuestions });
  };

  const handleDeleteQuestion = (questionId: string) => {
    updateConfig({
      registrationQuestions: config.registrationQuestions.filter(
        (q) => q.id !== questionId
      ),
    });
  };

  const handleAddCustomQuestion = () => {
    if (!newQuestionPrompt.trim()) return;
    const parsedOptions =
      newQuestionType === "single_select" || newQuestionType === "multi_select"
        ? newQuestionOptions
            .split(",")
            .map((s) => s.trim())
            .filter(Boolean)
        : newQuestionType === "yes_no"
        ? ["Yes", "No"]
        : undefined;

    const created: AttendeeRegistrationQuestion = {
      id: `q-custom-${Date.now()}`,
      prompt: newQuestionPrompt.trim(),
      fieldType: newQuestionType,
      required: newQuestionRequired,
      category: newQuestionCategory,
      options:
        parsedOptions && parsedOptions.length > 0
          ? parsedOptions
          : newQuestionType === "single_select" ||
            newQuestionType === "multi_select"
          ? ["Option A", "Option B"]
          : undefined,
      privacyPurpose:
        newQuestionPurpose.trim() ||
        "Used strictly for event-day guest coordination and purged post-event.",
    };

    updateConfig({
      registrationQuestions: [...config.registrationQuestions, created],
    });
    setNewQuestionPrompt("");
    setNewQuestionOptions("");
    setNewQuestionRequired(false);
  };

  const handleResetDefaultQuestions = () => {
    updateConfig({
      registrationQuestions: DEFAULT_ATTENDEE_REGISTRATION_QUESTIONS.map(
        (q) => ({
          ...q,
          options: q.options ? [...q.options] : undefined,
        })
      ),
    });
  };

  const handleAddFaq = () => {
    if (!newFaqQuestion.trim() || !newFaqAnswer.trim()) return;
    const nextFaq: EventFaqItem = {
      id: `faq-${Date.now()}`,
      question: newFaqQuestion.trim(),
      answer: newFaqAnswer.trim(),
      category: "general",
    };
    updateConfig({ faqs: [...config.faqs, nextFaq] });
    setNewFaqQuestion("");
    setNewFaqAnswer("");
  };

  const handleRemoveFaq = (id: string) => {
    updateConfig({ faqs: config.faqs.filter((f) => f.id !== id) });
  };

  const handleAddRule = () => {
    if (!newRuleText.trim()) return;
    updateConfig({
      eventRules: [...config.eventRules, newRuleText.trim()],
    });
    setNewRuleText("");
  };

  const handleRemoveRule = (index: number) => {
    updateConfig({
      eventRules: config.eventRules.filter((_, i) => i !== index),
    });
  };

  const handleAddAnnouncement = () => {
    if (!newAnnouncementTitle.trim() || !newAnnouncementMessage.trim()) return;
    const item: EventAnnouncementItem = {
      id: `ann-${Date.now()}`,
      title: newAnnouncementTitle.trim(),
      message: newAnnouncementMessage.trim(),
      publishedAt: new Date().toISOString(),
      priority: newAnnouncementPriority,
      authorName: eventDetails.organizerName || "Event Host",
    };
    updateConfig({
      announcements: [item, ...config.announcements],
    });
    setNewAnnouncementTitle("");
    setNewAnnouncementMessage("");
  };

  const handleRemoveAnnouncement = (id: string) => {
    updateConfig({
      announcements: config.announcements.filter((a) => a.id !== id),
    });
  };

  const handleToggleReminderChannel = (channel: EventReminderChannel) => {
    const current = config.reminders.channels;
    const nextChannels = current.includes(channel)
      ? current.filter((c) => c !== channel)
      : [...current, channel];
    updateConfig({
      reminders: {
        ...config.reminders,
        channels: nextChannels,
      },
    });
  };

  const feedbackStats = computeFeedbackAggregate(config.feedbackSubmissions);

  return (
    <section
      id="attendee-experience-setup-section"
      data-testid="attendee-experience-setup-section"
      className="mt-6 rounded-2xl border border-purple-500/30 bg-[#121216] p-6 text-white shadow-xl"
    >
      {/* Header */}
      <div className="flex flex-wrap items-start justify-between gap-4 border-b border-white/10 pb-5">
        <div>
          <div className="inline-flex items-center gap-2 rounded-full border border-purple-400/30 bg-purple-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-purple-300">
            <Sparkles className="h-3.5 w-3.5 text-lime-400" />
            Attendee Experience & Guest Hospitality Setup
          </div>
          <h3 className="mt-2 text-xl font-extrabold tracking-tight text-white">
            Configure Guest Instructions, Registration Questions, FAQs & Post-Event Feedback
          </h3>
          <p className="mt-1 text-sm text-zinc-400">
            Give attendees everything they need before arrival—custom registration questions with privacy & data-retention controls, group booking, dietary preferences, calendar sync, reminders, live updates, and ratings.
          </p>
        </div>

        <div className="flex items-center gap-2 rounded-xl border border-lime-400/30 bg-lime-400/10 px-3.5 py-2 text-xs font-semibold text-lime-300">
          <ShieldCheck className="h-4 w-4 shrink-0" />
          <span>
            Auto-Purge Retention: {config.privacyRetentionPolicy.retentionDaysAfterEvent} Days Post-Event
          </span>
        </div>
      </div>

      {/* 1. Arrival, Parking, Accessibility, Dress Code & Age Requirements */}
      <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div className="rounded-xl border border-white/10 bg-black/30 p-4">
          <label
            htmlFor="attendee-guest-instructions"
            className="block text-xs font-bold uppercase tracking-wider text-lime-400"
          >
            Guest Instructions (Arrival & Gate Check-In)
          </label>
          <textarea
            id="attendee-guest-instructions"
            rows={3}
            value={config.guestInstructions}
            onChange={(e) => {
              updateConfig({ guestInstructions: e.target.value });
              onSyncLegacyFields?.({ venueInstructions: e.target.value });
            }}
            className="mt-2 w-full rounded-lg border border-white/15 bg-zinc-900/90 px-3 py-2 text-sm text-white focus:border-lime-400 focus:outline-none"
            placeholder="Gate check-in instructions, QR ticket readiness, and food pickup guidance..."
          />
        </div>

        <div className="rounded-xl border border-white/10 bg-black/30 p-4">
          <label
            htmlFor="attendee-parking-transit"
            className="block text-xs font-bold uppercase tracking-wider text-lime-400"
          >
            Parking & Transportation Information
          </label>
          <textarea
            id="attendee-parking-transit"
            rows={3}
            value={config.parkingAndTransitInfo}
            onChange={(e) => {
              updateConfig({ parkingAndTransitInfo: e.target.value });
              onSyncLegacyFields?.({ parkingInformation: e.target.value });
            }}
            className="mt-2 w-full rounded-lg border border-white/15 bg-zinc-900/90 px-3 py-2 text-sm text-white focus:border-lime-400 focus:outline-none"
            placeholder="Parking lots, shuttle schedule, rideshare drop-off pin, public transit..."
          />
        </div>

        <div className="rounded-xl border border-white/10 bg-black/30 p-4">
          <label
            htmlFor="attendee-accessibility-info"
            className="block text-xs font-bold uppercase tracking-wider text-lime-400"
          >
            Accessibility Information
          </label>
          <textarea
            id="attendee-accessibility-info"
            rows={3}
            value={config.accessibilityInfo}
            onChange={(e) => {
              updateConfig({ accessibilityInfo: e.target.value });
              onSyncLegacyFields?.({ accessibilityInformation: e.target.value });
            }}
            className="mt-2 w-full rounded-lg border border-white/15 bg-zinc-900/90 px-3 py-2 text-sm text-white focus:border-lime-400 focus:outline-none"
            placeholder="Step-free entrances, ADA seating, accessible restrooms, sensory accommodations..."
          />
        </div>

        <div className="grid grid-cols-1 gap-3 rounded-xl border border-white/10 bg-black/30 p-4 sm:grid-cols-2">
          <div>
            <label
              htmlFor="attendee-dress-code"
              className="block text-xs font-bold uppercase tracking-wider text-purple-300"
            >
              Dress Code
            </label>
            <input
              id="attendee-dress-code"
              type="text"
              value={config.dressCode}
              onChange={(e) => {
                updateConfig({ dressCode: e.target.value });
                onSyncLegacyFields?.({ dressCode: e.target.value });
              }}
              className="mt-2 w-full rounded-lg border border-white/15 bg-zinc-900/90 px-3 py-2 text-sm text-white focus:border-lime-400 focus:outline-none"
              placeholder="e.g., Island Chic, Smart Casual"
            />
          </div>

          <div>
            <label
              htmlFor="attendee-age-requirement"
              className="block text-xs font-bold uppercase tracking-wider text-purple-300"
            >
              Age Requirements
            </label>
            <input
              id="attendee-age-requirement"
              type="text"
              value={config.ageRequirement}
              onChange={(e) => {
                updateConfig({ ageRequirement: e.target.value });
                onSyncLegacyFields?.({ ageRestriction: e.target.value });
              }}
              className="mt-2 w-full rounded-lg border border-white/15 bg-zinc-900/90 px-3 py-2 text-sm text-white focus:border-lime-400 focus:outline-none"
              placeholder="e.g., All Ages, 18+, 21+ with Valid ID"
            />
          </div>
        </div>
      </div>

      {/* 2. Custom Attendee Registration Questions + Privacy & Data-Retention Controls */}
      <div
        id="host-registration-questions-panel"
        className="mt-6 rounded-xl border border-lime-400/25 bg-black/40 p-5"
      >
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h4 className="flex items-center gap-2 text-base font-bold text-white">
              <FileQuestion className="h-4 w-4 text-lime-400" />
              Custom Attendee Registration Questions & Dietary Prompts
            </h4>
            <p className="text-xs text-zinc-400">
              Ask attendees structured questions during ticket/food registration. Each question can be Optional or Required and respects role-scoped privacy controls.
            </p>
          </div>
          <button
            type="button"
            onClick={handleResetDefaultQuestions}
            className="rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-semibold text-zinc-300 hover:bg-white/10"
          >
            Restore Recommended Questions
          </button>
        </div>

        {/* Question List */}
        <div className="mt-4 space-y-3">
          {config.registrationQuestions.map((q) => (
            <div
              key={q.id}
              data-testid={`registration-question-card-${q.id}`}
              className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/10 bg-zinc-900/80 p-3.5"
            >
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="font-semibold text-white">{q.prompt}</span>
                  <span
                    className={`rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase ${
                      q.required
                        ? "border border-amber-400/40 bg-amber-500/20 text-amber-300"
                        : "border border-zinc-600 bg-zinc-800 text-zinc-300"
                    }`}
                  >
                    {q.required ? "Required" : "Optional"}
                  </span>
                  <span className="rounded-full border border-purple-400/30 bg-purple-500/10 px-2.5 py-0.5 text-[11px] font-semibold uppercase text-purple-300">
                    {q.category} • {q.fieldType.replace("_", " ")}
                  </span>
                </div>
                {q.options && q.options.length > 0 && (
                  <p className="mt-1 text-xs text-zinc-400">
                    Options: {q.options.join(" · ")}
                  </p>
                )}
                <p className="mt-1 flex items-center gap-1.5 text-[11px] text-emerald-300/90">
                  <Lock className="h-3 w-3 shrink-0" />
                  {q.privacyPurpose}
                </p>
              </div>

              <div className="flex items-center gap-2">
                <button
                  type="button"
                  id={`btn-toggle-required-${q.id}`}
                  onClick={() => handleToggleQuestionRequired(q.id)}
                  className={`rounded-lg px-3 py-1.5 text-xs font-bold transition ${
                    q.required
                      ? "bg-amber-400 text-black hover:bg-amber-300"
                      : "border border-white/15 bg-white/5 text-zinc-200 hover:bg-white/10"
                  }`}
                >
                  {q.required ? "Mark Optional" : "Mark Required"}
                </button>
                <button
                  type="button"
                  aria-label={`Delete question ${q.prompt}`}
                  onClick={() => handleDeleteQuestion(q.id)}
                  className="rounded-lg border border-red-500/30 bg-red-500/10 p-1.5 text-red-300 hover:bg-red-500/20"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            </div>
          ))}
        </div>

        {/* Add New Custom Question */}
        <div className="mt-4 rounded-xl border border-dashed border-white/15 bg-zinc-950/60 p-4">
          <div className="text-xs font-bold uppercase tracking-wider text-zinc-300">
            Add Custom Registration Question
          </div>
          <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-4">
            <div className="md:col-span-2">
              <input
                id="new-reg-question-prompt"
                type="text"
                value={newQuestionPrompt}
                onChange={(e) => setNewQuestionPrompt(e.target.value)}
                placeholder="e.g., Do you need a high-chair or stroller space?"
                className="w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-2 text-sm text-white"
              />
            </div>
            <div>
              <select
                id="new-reg-question-type"
                aria-label="Question field type"
                value={newQuestionType}
                onChange={(e) =>
                  setNewQuestionType(
                    e.target.value as AttendeeRegistrationQuestionType
                  )
                }
                className="w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-2 text-sm text-white"
              >
                <option value="text">Free Text</option>
                <option value="yes_no">Yes / No</option>
                <option value="single_select">Single Choice</option>
                <option value="multi_select">Multiple Choice</option>
              </select>
            </div>
            <div>
              <select
                id="new-reg-question-category"
                aria-label="Question category"
                value={newQuestionCategory}
                onChange={(e) =>
                  setNewQuestionCategory(
                    e.target.value as AttendeeRegistrationQuestionCategory
                  )
                }
                className="w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-2 text-sm text-white"
              >
                <option value="dietary">Dietary</option>
                <option value="group">Group</option>
                <option value="accessibility">Accessibility</option>
                <option value="general">General</option>
              </select>
            </div>
          </div>

          {(newQuestionType === "single_select" ||
            newQuestionType === "multi_select") && (
            <div className="mt-3">
              <input
                id="new-reg-question-options"
                type="text"
                value={newQuestionOptions}
                onChange={(e) => setNewQuestionOptions(e.target.value)}
                placeholder="Comma-separated options (e.g., Vegan, Halal, Gluten-Free)"
                className="w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-2 text-sm text-white"
              />
            </div>
          )}

          <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
            <label className="inline-flex items-center gap-2 text-xs text-zinc-300">
              <input
                id="new-reg-question-required"
                type="checkbox"
                checked={newQuestionRequired}
                onChange={(e) => setNewQuestionRequired(e.target.checked)}
                className="rounded border-white/20 bg-zinc-900 text-lime-400"
              />
              Require attendee response before checkout completes
            </label>

            <button
              type="button"
              id="btn-add-custom-reg-question"
              onClick={handleAddCustomQuestion}
              className="inline-flex items-center gap-1.5 rounded-lg bg-lime-400 px-3.5 py-2 text-xs font-extrabold text-black hover:bg-lime-300"
            >
              <Plus className="h-3.5 w-3.5" />
              Add Question
            </button>
          </div>
        </div>

        {/* Privacy & Data-Retention Controls */}
        <div
          id="privacy-retention-controls-card"
          className="mt-4 rounded-xl border border-emerald-500/30 bg-emerald-950/20 p-4"
        >
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-300">
              <Lock className="h-4 w-4" />
              Privacy & Data-Retention Controls for Attendee Responses
            </div>
            <div className="flex items-center gap-2">
              <label
                htmlFor="retention-days-select"
                className="text-xs text-zinc-300"
              >
                Auto-Purge Window:
              </label>
              <select
                id="retention-days-select"
                value={config.privacyRetentionPolicy.retentionDaysAfterEvent}
                onChange={(e) =>
                  updateConfig({
                    privacyRetentionPolicy: {
                      ...config.privacyRetentionPolicy,
                      retentionDaysAfterEvent: Number(e.target.value),
                    },
                  })
                }
                className="rounded-lg border border-emerald-400/30 bg-zinc-900 px-2.5 py-1 text-xs font-semibold text-white"
              >
                <option value={7}>7 days post-event</option>
                <option value={14}>14 days post-event</option>
                <option value={30}>30 days post-event</option>
                <option value={60}>60 days post-event</option>
              </select>
            </div>
          </div>

          <div className="mt-3 flex flex-wrap gap-5 text-xs text-zinc-300">
            <label className="inline-flex items-center gap-2">
              <input
                type="checkbox"
                checked={
                  config.privacyRetentionPolicy.restrictStaffVisibilityToRole
                }
                onChange={(e) =>
                  updateConfig({
                    privacyRetentionPolicy: {
                      ...config.privacyRetentionPolicy,
                      restrictStaffVisibilityToRole: e.target.checked,
                    },
                  })
                }
              />
              Role-scoped visibility (kitchen sees only dietary; gate sees only group/accessibility)
            </label>

            <label className="inline-flex items-center gap-2">
              <input
                type="checkbox"
                checked={
                  config.privacyRetentionPolicy.allowAttendeeDeletionRequest
                }
                onChange={(e) =>
                  updateConfig({
                    privacyRetentionPolicy: {
                      ...config.privacyRetentionPolicy,
                      allowAttendeeDeletionRequest: e.target.checked,
                    },
                  })
                }
              />
              Allow one-click attendee data deletion request
            </label>

            <label className="inline-flex items-center gap-2">
              <input
                type="checkbox"
                checked={config.enableDietaryPreferencePrompt}
                onChange={(e) =>
                  updateConfig({
                    enableDietaryPreferencePrompt: e.target.checked,
                  })
                }
              />
              Show quick dietary preference tags during food & ticket checkout
            </label>
          </div>

          <p className="mt-2 text-[11px] text-emerald-200/80">
            {formatPrivacyRetentionDisclosure(config.privacyRetentionPolicy)}
          </p>
        </div>
      </div>

      {/* 3. Group Registration, Event Reminders & Calendar Integration */}
      <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {/* Group Registration */}
        <div className="rounded-xl border border-white/10 bg-black/30 p-4">
          <div className="flex items-center justify-between">
            <span className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-lime-400">
              <Users className="h-4 w-4" />
              Group Registration
            </span>
            <input
              id="toggle-group-registration"
              aria-label="Enable Group Registration"
              type="checkbox"
              checked={config.groupRegistration.enabled}
              onChange={(e) =>
                updateConfig({
                  groupRegistration: {
                    ...config.groupRegistration,
                    enabled: e.target.checked,
                  },
                })
              }
            />
          </div>
          <p className="mt-1 text-xs text-zinc-400">
            Allow attendees to register multiple guests together with group check-in passes.
          </p>
          <div className="mt-3 grid grid-cols-3 gap-2">
            <div>
              <label className="block text-[11px] text-zinc-400">Min Size</label>
              <input
                type="number"
                min={2}
                max={50}
                value={config.groupRegistration.minGroupSize}
                onChange={(e) =>
                  updateConfig({
                    groupRegistration: {
                      ...config.groupRegistration,
                      minGroupSize: Math.max(2, Number(e.target.value) || 2),
                    },
                  })
                }
                className="mt-1 w-full rounded border border-white/15 bg-zinc-900 px-2 py-1 text-xs text-white"
              />
            </div>
            <div>
              <label className="block text-[11px] text-zinc-400">Max Size</label>
              <input
                type="number"
                min={2}
                max={100}
                value={config.groupRegistration.maxGroupSize}
                onChange={(e) =>
                  updateConfig({
                    groupRegistration: {
                      ...config.groupRegistration,
                      maxGroupSize: Math.max(2, Number(e.target.value) || 10),
                    },
                  })
                }
                className="mt-1 w-full rounded border border-white/15 bg-zinc-900 px-2 py-1 text-xs text-white"
              />
            </div>
            <div>
              <label className="block text-[11px] text-zinc-400">
                Perk %
              </label>
              <input
                type="number"
                min={0}
                max={50}
                value={config.groupRegistration.groupDiscountPercent ?? 0}
                onChange={(e) =>
                  updateConfig({
                    groupRegistration: {
                      ...config.groupRegistration,
                      groupDiscountPercent: Math.max(
                        0,
                        Number(e.target.value) || 0
                      ),
                    },
                  })
                }
                className="mt-1 w-full rounded border border-white/15 bg-zinc-900 px-2 py-1 text-xs text-white"
              />
            </div>
          </div>
        </div>

        {/* Event Reminders */}
        <div className="rounded-xl border border-white/10 bg-black/30 p-4">
          <div className="flex items-center justify-between">
            <span className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-lime-400">
              <Bell className="h-4 w-4" />
              Event Reminders
            </span>
            <input
              id="toggle-event-reminders"
              aria-label="Enable Event Reminders"
              type="checkbox"
              checked={config.reminders.enabled}
              onChange={(e) =>
                updateConfig({
                  reminders: {
                    ...config.reminders,
                    enabled: e.target.checked,
                  },
                })
              }
            />
          </div>
          <p className="mt-1 text-xs text-zinc-400">
            Automated pre-event reminders (24h & 2h before doors open).
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            {(["email", "sms", "whatsapp"] as EventReminderChannel[]).map(
              (ch) => {
                const active = config.reminders.channels.includes(ch);
                return (
                  <button
                    key={ch}
                    type="button"
                    onClick={() => handleToggleReminderChannel(ch)}
                    className={`rounded-lg px-2.5 py-1 text-xs font-bold uppercase ${
                      active
                        ? "bg-lime-400 text-black"
                        : "border border-white/15 bg-zinc-900 text-zinc-400"
                    }`}
                  >
                    {ch}
                  </button>
                );
              }
            )}
          </div>
        </div>

        {/* Calendar Integration & Post-Event Feedback Toggle */}
        <div className="rounded-xl border border-white/10 bg-black/30 p-4">
          <div className="flex items-center justify-between">
            <span className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-lime-400">
              <Calendar className="h-4 w-4" />
              Calendar & Feedback
            </span>
            <CheckCircle2 className="h-4 w-4 text-lime-400" />
          </div>
          <div className="mt-3 space-y-2.5 text-xs text-zinc-300">
            <label className="flex items-center justify-between gap-2">
              <span>1-Click Google Calendar & .ICS Export</span>
              <input
                id="toggle-calendar-integration"
                type="checkbox"
                checked={config.calendarIntegrationEnabled}
                onChange={(e) =>
                  updateConfig({
                    calendarIntegrationEnabled: e.target.checked,
                  })
                }
              />
            </label>
            <label className="flex items-center justify-between gap-2">
              <span>Post-Event Ratings & Guest Reviews</span>
              <input
                id="toggle-post-event-feedback"
                type="checkbox"
                checked={config.feedbackEnabled}
                onChange={(e) =>
                  updateConfig({
                    feedbackEnabled: e.target.checked,
                  })
                }
              />
            </label>
            <div className="flex items-center gap-2 pt-1 text-[11px] text-amber-300">
              <Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
              <span>
                Current Rating: {feedbackStats.averageOverall || "5.0"} / 5.0 ({feedbackStats.count} verified reviews)
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* 4. Event FAQs & Event Rules */}
      <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        {/* FAQs */}
        <div className="rounded-xl border border-white/10 bg-black/30 p-4">
          <h4 className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-lime-400">
            <HelpCircle className="h-4 w-4" />
            Event FAQs ({config.faqs.length})
          </h4>
          <div className="mt-3 max-h-52 space-y-2 overflow-y-auto pr-1">
            {config.faqs.map((faq) => (
              <div
                key={faq.id}
                className="flex items-start justify-between gap-2 rounded-lg border border-white/10 bg-zinc-900/70 p-2.5 text-xs"
              >
                <div>
                  <div className="font-bold text-white">{faq.question}</div>
                  <div className="mt-0.5 text-zinc-400">{faq.answer}</div>
                </div>
                <button
                  type="button"
                  aria-label={`Remove FAQ ${faq.question}`}
                  onClick={() => handleRemoveFaq(faq.id)}
                  className="text-zinc-400 hover:text-red-400"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </div>
            ))}
          </div>
          <div className="mt-3 space-y-2">
            <input
              id="new-faq-question"
              type="text"
              value={newFaqQuestion}
              onChange={(e) => setNewFaqQuestion(e.target.value)}
              placeholder="FAQ Question (e.g., Is outside food allowed?)"
              className="w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-1.5 text-xs text-white"
            />
            <div className="flex gap-2">
              <input
                id="new-faq-answer"
                type="text"
                value={newFaqAnswer}
                onChange={(e) => setNewFaqAnswer(e.target.value)}
                placeholder="Clear answer for attendees..."
                className="flex-1 rounded-lg border border-white/15 bg-zinc-900 px-3 py-1.5 text-xs text-white"
              />
              <button
                type="button"
                id="btn-add-faq"
                onClick={handleAddFaq}
                className="rounded-lg bg-lime-400 px-3 py-1.5 text-xs font-bold text-black hover:bg-lime-300"
              >
                Add FAQ
              </button>
            </div>
          </div>
        </div>

        {/* Event Rules */}
        <div className="rounded-xl border border-white/10 bg-black/30 p-4">
          <h4 className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-lime-400">
            <Utensils className="h-4 w-4" />
            Event Rules & Venue Policies ({config.eventRules.length})
          </h4>
          <ul className="mt-3 max-h-52 space-y-2 overflow-y-auto pr-1 text-xs">
            {config.eventRules.map((rule, idx) => (
              <li
                key={`${idx}-${rule.slice(0, 16)}`}
                className="flex items-center justify-between gap-2 rounded-lg border border-white/10 bg-zinc-900/70 p-2.5 text-zinc-200"
              >
                <span>• {rule}</span>
                <button
                  type="button"
                  aria-label={`Remove rule ${idx + 1}`}
                  onClick={() => handleRemoveRule(idx)}
                  className="text-zinc-400 hover:text-red-400"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </li>
            ))}
          </ul>
          <div className="mt-3 flex gap-2">
            <input
              id="new-event-rule"
              type="text"
              value={newRuleText}
              onChange={(e) => setNewRuleText(e.target.value)}
              placeholder="Add an event rule or bag policy..."
              className="flex-1 rounded-lg border border-white/15 bg-zinc-900 px-3 py-1.5 text-xs text-white"
            />
            <button
              type="button"
              id="btn-add-event-rule"
              onClick={handleAddRule}
              className="rounded-lg bg-lime-400 px-3 py-1.5 text-xs font-bold text-black hover:bg-lime-300"
            >
              Add Rule
            </button>
          </div>
        </div>
      </div>

      {/* 5. Event Updates & Live Announcements */}
      <div className="mt-6 rounded-xl border border-white/10 bg-black/30 p-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h4 className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-lime-400">
            <Megaphone className="h-4 w-4" />
            Event Updates & Live Attendee Announcements ({config.announcements.length})
          </h4>
          <span className="text-[11px] text-zinc-400">
            Displayed on the event page & attendee ticket screen
          </span>
        </div>

        <div className="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2">
          {config.announcements.map((ann) => (
            <div
              key={ann.id}
              className="flex items-start justify-between gap-2 rounded-lg border border-white/10 bg-zinc-900/80 p-3 text-xs"
            >
              <div>
                <div className="flex items-center gap-2">
                  <span
                    className={`rounded px-1.5 py-0.5 text-[10px] font-bold uppercase ${
                      ann.priority === "urgent"
                        ? "bg-red-500/20 text-red-300"
                        : ann.priority === "important"
                        ? "bg-amber-500/20 text-amber-300"
                        : "bg-purple-500/20 text-purple-300"
                    }`}
                  >
                    {ann.priority}
                  </span>
                  <span className="font-bold text-white">{ann.title}</span>
                </div>
                <p className="mt-1 text-zinc-300">{ann.message}</p>
              </div>
              <button
                type="button"
                aria-label={`Remove announcement ${ann.title}`}
                onClick={() => handleRemoveAnnouncement(ann.id)}
                className="text-zinc-400 hover:text-red-400"
              >
                <Trash2 className="h-3.5 w-3.5" />
              </button>
            </div>
          ))}
        </div>

        <div className="mt-3 grid grid-cols-1 gap-2 md:grid-cols-4">
          <input
            id="new-announcement-title"
            type="text"
            value={newAnnouncementTitle}
            onChange={(e) => setNewAnnouncementTitle(e.target.value)}
            placeholder="Announcement headline (e.g., VIP Gate Opens Early)"
            className="rounded-lg border border-white/15 bg-zinc-900 px-3 py-1.5 text-xs text-white"
          />
          <input
            id="new-announcement-message"
            type="text"
            value={newAnnouncementMessage}
            onChange={(e) => setNewAnnouncementMessage(e.target.value)}
            placeholder="Details for attendees..."
            className="rounded-lg border border-white/15 bg-zinc-900 px-3 py-1.5 text-xs text-white md:col-span-2"
          />
          <div className="flex gap-2">
            <select
              id="new-announcement-priority"
              aria-label="Announcement priority"
              value={newAnnouncementPriority}
              onChange={(e) =>
                setNewAnnouncementPriority(
                  e.target.value as "info" | "important" | "urgent"
                )
              }
              className="rounded-lg border border-white/15 bg-zinc-900 px-2 py-1.5 text-xs text-white"
            >
              <option value="info">Info</option>
              <option value="important">Important</option>
              <option value="urgent">Urgent</option>
            </select>
            <button
              type="button"
              id="btn-publish-announcement"
              onClick={handleAddAnnouncement}
              className="inline-flex items-center gap-1 rounded-lg bg-lime-400 px-3 py-1.5 text-xs font-bold text-black hover:bg-lime-300"
            >
              <MessageSquare className="h-3.5 w-3.5" />
              Post
            </button>
          </div>
        </div>
      </div>
    </section>
  );
};

