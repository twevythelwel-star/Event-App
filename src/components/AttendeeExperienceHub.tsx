import React, { useState } from "react";
import {
  Accessibility,
  AlertCircle,
  Bell,
  Calendar,
  Car,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  Download,
  ExternalLink,
  HelpCircle,
  Info,
  Lock,
  Megaphone,
  ShieldCheck,
  Sparkles,
  Star,
  Trash2,
  Users,
  Utensils,
} from "lucide-react";
import {
  EventDetails,
  EventFeedbackSubmission,
  EventReminderChannel,
} from "../types";
import {
  buildGoogleCalendarUrl,
  buildIcsCalendarContent,
  computeFeedbackAggregate,
  formatPrivacyRetentionDisclosure,
  getDefaultAttendeeExperienceConfig,
  validateRegistrationQuestionResponses,
} from "../lib/attendeeExperience";

interface AttendeeExperienceHubProps {
  event: EventDetails;
  onRegistrationValidated?: (payload: {
    valid: boolean;
    responses: Record<string, string | string[]>;
    isGroupBooking: boolean;
    groupSize: number;
    groupName: string;
  }) => void;
}

export const AttendeeExperienceHub: React.FC<AttendeeExperienceHubProps> = ({
  event,
  onRegistrationValidated,
}) => {
  const config = getDefaultAttendeeExperienceConfig(event);

  // FAQ accordion state
  const [openFaqId, setOpenFaqId] = useState<string | null>(
    config.faqs[0]?.id ?? null
  );

  // Registration question responses
  const [questionResponses, setQuestionResponses] = useState<
    Record<string, string | string[]>
  >({});
  const [selectedDietaryPrefs, setSelectedDietaryPrefs] = useState<string[]>(
    []
  );
  const [isGroupBooking, setIsGroupBooking] = useState(false);
  const [groupSize, setGroupSize] = useState(
    config.groupRegistration.minGroupSize || 2
  );
  const [groupName, setGroupName] = useState("");
  const [registrationSavedMessage, setRegistrationSavedMessage] = useState<
    string | null
  >(null);
  const [validationErrors, setValidationErrors] = useState<
    Record<string, string>
  >({});

  // Reminder opt-in state
  const [optedReminderChannels, setOptedReminderChannels] = useState<
    EventReminderChannel[]
  >(["email"]);
  const [reminderSaved, setReminderSaved] = useState(false);
  const [icsDownloaded, setIcsDownloaded] = useState(false);

  // Post-event feedback state
  const [submissions, setSubmissions] = useState<EventFeedbackSubmission[]>(
    config.feedbackSubmissions
  );
  const [reviewerName, setReviewerName] = useState("");
  const [overallRating, setOverallRating] = useState(5);
  const [foodRating, setFoodRating] = useState(5);
  const [operationsRating, setOperationsRating] = useState(5);
  const [reviewComment, setReviewComment] = useState("");
  const [feedbackSuccess, setFeedbackSuccess] = useState(false);

  const googleCalendarUrl = buildGoogleCalendarUrl(event);
  const feedbackStats = computeFeedbackAggregate(submissions);

  const handleDownloadIcs = () => {
    const icsText = buildIcsCalendarContent(event);
    if (typeof window !== "undefined" && typeof Blob !== "undefined") {
      try {
        const blob = new Blob([icsText], {
          type: "text/calendar;charset=utf-8",
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = `${(event.slug || event.id || "eventbite-event")
          .toString()
          .replace(/[^a-z0-9-_]/gi, "-")}.ics`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
      } catch {
        // Fallback in non-browser test environments
      }
    }
    setIcsDownloaded(true);
  };

  const handleToggleReminderChannel = (channel: EventReminderChannel) => {
    setOptedReminderChannels((prev) =>
      prev.includes(channel)
        ? prev.filter((c) => c !== channel)
        : [...prev, channel]
    );
    setReminderSaved(true);
  };

  const handleMultiSelectChange = (questionId: string, option: string) => {
    const existing = questionResponses[questionId];
    const currentArr = Array.isArray(existing) ? existing : [];
    const nextArr = currentArr.includes(option)
      ? currentArr.filter((o) => o !== option)
      : [...currentArr, option];
    setQuestionResponses((prev) => ({
      ...prev,
      [questionId]: nextArr,
    }));
  };

  const handleSaveRegistrationPreferences = () => {
    const result = validateRegistrationQuestionResponses(
      config.registrationQuestions,
      questionResponses,
      {
        isGroupBooking,
        groupSize,
        groupName,
        groupConfig: config.groupRegistration,
      }
    );
    setValidationErrors(result.errors);
    if (result.valid) {
      setRegistrationSavedMessage(
        "Your registration preferences have been saved and encrypted for event-day check-in."
      );
      onRegistrationValidated?.({
        valid: true,
        responses: questionResponses,
        isGroupBooking,
        groupSize,
        groupName,
      });
    } else {
      setRegistrationSavedMessage(null);
      onRegistrationValidated?.({
        valid: false,
        responses: questionResponses,
        isGroupBooking,
        groupSize,
        groupName,
      });
    }
  };

  const handlePurgeMyResponses = () => {
    setQuestionResponses({});
    setSelectedDietaryPrefs([]);
    setIsGroupBooking(false);
    setGroupName("");
    setValidationErrors({});
    setRegistrationSavedMessage(
      "Your registration responses have been immediately deleted in accordance with our privacy policy."
    );
  };

  const handleSubmitFeedback = (e: React.FormEvent) => {
    e.preventDefault();
    if (!reviewComment.trim()) return;
    const newEntry: EventFeedbackSubmission = {
      id: `fb-${Date.now()}`,
      attendeeName: reviewerName.trim() || "Verified Guest",
      overallRating,
      foodRating,
      operationsRating,
      comment: reviewComment.trim(),
      submittedAt: new Date().toISOString(),
      verifiedAttendee: true,
    };
    setSubmissions((prev) => [newEntry, ...prev]);
    setReviewerName("");
    setReviewComment("");
    setFeedbackSuccess(true);
  };

  return (
    <div
      id="attendee-experience-hub"
      data-testid="attendee-experience-hub"
      className="mt-8 space-y-6"
    >
      {/* 1. Live Event Updates & Announcements */}
      {config.announcements.length > 0 && (
        <section
          id="attendee-announcements-banner"
          aria-label="Live Event Updates and Announcements"
          className="rounded-2xl border border-lime-400/30 bg-gradient-to-r from-lime-500/10 via-zinc-900/95 to-purple-500/10 p-5"
        >
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-lime-300">
              <Megaphone className="h-4 w-4 text-lime-400" />
              Event Updates & Live Announcements
            </div>
            <span className="rounded-full border border-lime-400/30 bg-lime-400/10 px-2.5 py-0.5 text-[11px] font-semibold text-lime-300">
              {config.announcements.length} Active Updates
            </span>
          </div>
          <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
            {config.announcements.map((ann) => (
              <div
                key={ann.id}
                className="rounded-xl border border-white/10 bg-black/40 p-3.5"
              >
                <div className="flex items-center gap-2">
                  <span
                    className={`rounded px-2 py-0.5 text-[10px] font-bold uppercase ${
                      ann.priority === "urgent"
                        ? "bg-red-500/20 text-red-300"
                        : ann.priority === "important"
                        ? "bg-amber-500/20 text-amber-300"
                        : "bg-purple-500/20 text-purple-300"
                    }`}
                  >
                    {ann.priority}
                  </span>
                  <h4 className="text-sm font-bold text-white">{ann.title}</h4>
                </div>
                <p className="mt-1.5 text-xs leading-relaxed text-zinc-300">
                  {ann.message}
                </p>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* 2. Calendar Integration & Event Reminders Bar */}
      <section
        id="attendee-calendar-reminders-bar"
        className="grid grid-cols-1 gap-4 rounded-2xl border border-white/10 bg-zinc-900/80 p-5 lg:grid-cols-2"
      >
        {config.calendarIntegrationEnabled && (
          <div className="flex flex-col justify-between gap-3 rounded-xl border border-white/10 bg-black/30 p-4">
            <div>
              <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-lime-400">
                <Calendar className="h-4 w-4" />
                Calendar Integration
              </div>
              <p className="mt-1 text-xs text-zinc-300">
                Add this event, doors-open time, and gate instructions directly to your calendar.
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-2.5">
              <a
                id="btn-add-google-calendar"
                href={googleCalendarUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 rounded-xl bg-lime-400 px-3.5 py-2 text-xs font-extrabold text-black transition hover:bg-lime-300"
              >
                <ExternalLink className="h-3.5 w-3.5" />
                Add to Google Calendar
              </a>
              <button
                type="button"
                id="btn-download-ics-calendar"
                onClick={handleDownloadIcs}
                className="inline-flex items-center gap-1.5 rounded-xl border border-white/15 bg-white/5 px-3.5 py-2 text-xs font-bold text-white transition hover:bg-white/10"
              >
                <Download className="h-3.5 w-3.5 text-lime-400" />
                {icsDownloaded
                  ? "Apple / Outlook .ICS Downloaded"
                  : "Download .ICS Calendar"}
              </button>
            </div>
          </div>
        )}

        {config.reminders.enabled && (
          <div className="flex flex-col justify-between gap-3 rounded-xl border border-white/10 bg-black/30 p-4">
            <div>
              <div className="flex items-center justify-between">
                <span className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-purple-300">
                  <Bell className="h-4 w-4 text-purple-400" />
                  Event Reminders & Pickup Alerts
                </span>
                {reminderSaved && (
                  <span className="text-[11px] font-semibold text-lime-300">
                    ✓ Reminder preferences active
                  </span>
                )}
              </div>
              <p className="mt-1 text-xs text-zinc-300">
                Receive reminders {config.reminders.scheduleOffsets.join(" & ")} plus live kitchen pickup ready alerts:
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              {config.reminders.channels.map((channel) => {
                const isSelected = optedReminderChannels.includes(channel);
                return (
                  <button
                    key={channel}
                    type="button"
                    id={`btn-reminder-optin-${channel}`}
                    onClick={() => handleToggleReminderChannel(channel)}
                    className={`rounded-xl px-3 py-1.5 text-xs font-bold uppercase transition ${
                      isSelected
                        ? "border border-purple-400/50 bg-purple-500/25 text-purple-200"
                        : "border border-white/10 bg-zinc-900 text-zinc-400 hover:text-white"
                    }`}
                  >
                    {isSelected ? `✓ ${channel} Alerts` : `+ ${channel} Alerts`}
                  </button>
                );
              })}
            </div>
          </div>
        )}
      </section>

      {/* 3. Essential Guest Guide: Instructions, Parking, Accessibility, Dress Code, Age & Rules */}
      <section
        id="attendee-essential-info-grid"
        className="rounded-2xl border border-white/10 bg-zinc-900/80 p-5"
      >
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-4">
          <div>
            <h3 className="text-base font-extrabold text-white">
              Guest Instructions, Venue Logistics & Event Rules
            </h3>
            <p className="text-xs text-zinc-400">
              Everything you need to know for smooth entry, parking, accessibility, and dress code.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <span
              data-testid="attendee-dress-code-badge"
              className="rounded-full border border-purple-400/30 bg-purple-500/15 px-3 py-1 text-xs font-bold text-purple-200"
            >
              Dress Code: {config.dressCode}
            </span>
            <span
              data-testid="attendee-age-requirement-badge"
              className="rounded-full border border-amber-400/30 bg-amber-500/15 px-3 py-1 text-xs font-bold text-amber-200"
            >
              Age Policy: {config.ageRequirement}
            </span>
          </div>
        </div>

        <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
          <div className="rounded-xl border border-white/10 bg-black/30 p-4">
            <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-lime-400">
              <Info className="h-4 w-4" />
              Guest Instructions
            </div>
            <p className="mt-2 text-xs leading-relaxed text-zinc-300">
              {config.guestInstructions}
            </p>
          </div>

          <div className="rounded-xl border border-white/10 bg-black/30 p-4">
            <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-lime-400">
              <Car className="h-4 w-4" />
              Parking & Transportation
            </div>
            <p className="mt-2 text-xs leading-relaxed text-zinc-300">
              {config.parkingAndTransitInfo}
            </p>
          </div>

          <div className="rounded-xl border border-white/10 bg-black/30 p-4">
            <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-lime-400">
              <Accessibility className="h-4 w-4" />
              Accessibility Information
            </div>
            <p className="mt-2 text-xs leading-relaxed text-zinc-300">
              {config.accessibilityInfo}
            </p>
          </div>
        </div>

        {/* Event Rules */}
        {config.eventRules.length > 0 && (
          <div className="mt-4 rounded-xl border border-white/10 bg-black/30 p-4">
            <div className="text-xs font-bold uppercase tracking-wider text-zinc-300">
              Event Rules & Venue Conduct
            </div>
            <ul className="mt-2 grid grid-cols-1 gap-2 text-xs text-zinc-300 md:grid-cols-2">
              {config.eventRules.map((rule, index) => (
                <li key={index} className="flex items-start gap-2">
                  <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-lime-400" />
                  <span>{rule}</span>
                </li>
              ))}
            </ul>
          </div>
        )}
      </section>

      {/* 4. Group Registration & Custom Attendee Registration Questions */}
      <section
        id="attendee-registration-questions-card"
        data-testid="attendee-registration-questions-card"
        className="rounded-2xl border border-lime-400/30 bg-zinc-900/90 p-5"
      >
        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-white/10 pb-4">
          <div>
            <div className="inline-flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-lime-400">
              <Sparkles className="h-4 w-4" />
              Guest Registration Questions, Dietary Preferences & Group Booking
            </div>
            <h3 className="mt-1 text-base font-extrabold text-white">
              Personalize Your Arrival, Dietary Needs & Group Check-In
            </h3>
            <p className="text-xs text-zinc-400">
              Help the event host, kitchen stations, and gate team prepare for your arrival.
            </p>
          </div>

          <div className="flex items-center gap-1.5 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-3 py-1 text-[11px] font-semibold text-emerald-300">
            <ShieldCheck className="h-3.5 w-3.5" />
            Encrypted & Auto-Purged ({config.privacyRetentionPolicy.retentionDaysAfterEvent}d Post-Event)
          </div>
        </div>

        {/* Group Registration Toggle */}
        {config.groupRegistration.enabled && (
          <div className="mt-4 rounded-xl border border-white/10 bg-black/35 p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-2">
                <Users className="h-4 w-4 text-lime-400" />
                <span className="text-sm font-bold text-white">
                  Group Registration ({config.groupRegistration.minGroupSize}–
                  {config.groupRegistration.maxGroupSize} Guests)
                </span>
                {config.groupRegistration.groupDiscountPercent ? (
                  <span className="rounded-full bg-lime-400/20 px-2.5 py-0.5 text-[11px] font-bold text-lime-300">
                    Save {config.groupRegistration.groupDiscountPercent}% on Group Packages
                  </span>
                ) : null}
              </div>

              <label className="inline-flex cursor-pointer items-center gap-2 text-xs font-semibold text-zinc-200">
                <input
                  id="attendee-toggle-group-booking"
                  type="checkbox"
                  checked={isGroupBooking}
                  onChange={(e) => setIsGroupBooking(e.target.checked)}
                />
                Registering a Group
              </label>
            </div>

            {isGroupBooking && (
              <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label
                    htmlFor="attendee-group-name"
                    className="block text-xs font-semibold text-zinc-300"
                  >
                    Group / Party Name *
                  </label>
                  <input
                    id="attendee-group-name"
                    type="text"
                    value={groupName}
                    onChange={(e) => setGroupName(e.target.value)}
                    placeholder="e.g., Kingston Link-Up Crew"
                    className="mt-1 w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-2 text-xs text-white"
                  />
                  {validationErrors["groupName"] && (
                    <p className="mt-1 text-xs text-red-400">
                      {validationErrors["groupName"]}
                    </p>
                  )}
                </div>
                <div>
                  <label
                    htmlFor="attendee-group-size"
                    className="block text-xs font-semibold text-zinc-300"
                  >
                    Number of Guests in Group
                  </label>
                  <input
                    id="attendee-group-size"
                    type="number"
                    min={config.groupRegistration.minGroupSize}
                    max={config.groupRegistration.maxGroupSize}
                    value={groupSize}
                    onChange={(e) => setGroupSize(Number(e.target.value) || 2)}
                    className="mt-1 w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-2 text-xs text-white"
                  />
                  {validationErrors["groupSize"] && (
                    <p className="mt-1 text-xs text-red-400">
                      {validationErrors["groupSize"]}
                    </p>
                  )}
                </div>
              </div>
            )}
          </div>
        )}

        {/* Optional Dietary Preference Tags */}
        {config.enableDietaryPreferencePrompt && (
          <div className="mt-4 rounded-xl border border-white/10 bg-black/35 p-4">
            <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-lime-400">
              <Utensils className="h-3.5 w-3.5" />
              Optional Dietary Preference Tags (Shared with Event Kitchens)
            </div>
            <div className="mt-2.5 flex flex-wrap gap-2">
              {config.dietaryPreferenceOptions.map((pref) => {
                const active = selectedDietaryPrefs.includes(pref);
                return (
                  <button
                    key={pref}
                    type="button"
                    onClick={() =>
                      setSelectedDietaryPrefs((prev) =>
                        prev.includes(pref)
                          ? prev.filter((p) => p !== pref)
                          : [...prev, pref]
                      )
                    }
                    className={`rounded-full px-3 py-1 text-xs font-semibold transition ${
                      active
                        ? "bg-lime-400 text-black"
                        : "border border-white/15 bg-zinc-900 text-zinc-300 hover:border-lime-400/50"
                    }`}
                  >
                    {active ? `✓ ${pref}` : pref}
                  </button>
                );
              })}
            </div>
          </div>
        )}

        {/* Custom Attendee Registration Questions */}
        <div className="mt-4 space-y-4">
          {config.registrationQuestions.map((question) => {
            const currentValue = questionResponses[question.id];
            const errorMsg = validationErrors[question.id];

            return (
              <div
                key={question.id}
                data-testid={`attendee-question-field-${question.id}`}
                className="rounded-xl border border-white/10 bg-black/35 p-4"
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <label
                    htmlFor={`input-${question.id}`}
                    className="text-sm font-bold text-white"
                  >
                    {question.prompt}
                  </label>
                  <span
                    className={`rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase ${
                      question.required
                        ? "border border-amber-400/40 bg-amber-500/20 text-amber-300"
                        : "border border-zinc-700 bg-zinc-800 text-zinc-300"
                    }`}
                  >
                    {question.required ? "Required" : "Optional"}
                  </span>
                </div>

                {/* Field input by type */}
                <div className="mt-2.5">
                  {question.fieldType === "text" && (
                    <input
                      id={`input-${question.id}`}
                      type="text"
                      value={typeof currentValue === "string" ? currentValue : ""}
                      onChange={(e) =>
                        setQuestionResponses((prev) => ({
                          ...prev,
                          [question.id]: e.target.value,
                        }))
                      }
                      placeholder={
                        question.placeholder || "Enter your response..."
                      }
                      className="w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-2 text-xs text-white"
                    />
                  )}

                  {(question.fieldType === "yes_no" ||
                    question.fieldType === "single_select") && (
                    <div className="flex flex-wrap gap-2">
                      {(question.options || ["Yes", "No"]).map((opt) => {
                        const selected = currentValue === opt;
                        return (
                          <button
                            key={opt}
                            type="button"
                            onClick={() =>
                              setQuestionResponses((prev) => ({
                                ...prev,
                                [question.id]: opt,
                              }))
                            }
                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                              selected
                                ? "bg-lime-400 text-black"
                                : "border border-white/15 bg-zinc-900 text-zinc-300 hover:border-lime-400/50"
                            }`}
                          >
                            {selected ? `● ${opt}` : opt}
                          </button>
                        );
                      })}
                    </div>
                  )}

                  {question.fieldType === "multi_select" && (
                    <div className="flex flex-wrap gap-2">
                      {(question.options || []).map((opt) => {
                        const arr = Array.isArray(currentValue)
                          ? currentValue
                          : [];
                        const selected = arr.includes(opt);
                        return (
                          <button
                            key={opt}
                            type="button"
                            onClick={() =>
                              handleMultiSelectChange(question.id, opt)
                            }
                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                              selected
                                ? "bg-lime-400 text-black"
                                : "border border-white/15 bg-zinc-900 text-zinc-300 hover:border-lime-400/50"
                            }`}
                          >
                            {selected ? `✓ ${opt}` : opt}
                          </button>
                        );
                      })}
                    </div>
                  )}
                </div>

                <p className="mt-2 flex items-center gap-1.5 text-[11px] text-zinc-400">
                  <Lock className="h-3 w-3 shrink-0 text-emerald-400" />
                  {question.privacyPurpose}
                </p>

                {errorMsg && (
                  <p
                    role="alert"
                    className="mt-1.5 flex items-center gap-1.5 text-xs font-semibold text-red-400"
                  >
                    <AlertCircle className="h-3.5 w-3.5" />
                    {errorMsg}
                  </p>
                )}
              </div>
            );
          })}
        </div>

        {/* Privacy & Data-Retention Footer + Save / Delete Controls */}
        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-500/25 bg-emerald-950/20 p-3.5">
          <p className="max-w-2xl text-[11px] leading-relaxed text-emerald-200/90">
            {formatPrivacyRetentionDisclosure(config.privacyRetentionPolicy)}
          </p>
          <div className="flex items-center gap-2">
            {config.privacyRetentionPolicy.allowAttendeeDeletionRequest && (
              <button
                type="button"
                id="btn-purge-registration-responses"
                onClick={handlePurgeMyResponses}
                className="inline-flex items-center gap-1 rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-semibold text-zinc-300 hover:bg-white/10"
              >
                <Trash2 className="h-3.5 w-3.5" />
                Delete My Answers
              </button>
            )}
            <button
              type="button"
              id="btn-save-registration-responses"
              onClick={handleSaveRegistrationPreferences}
              className="rounded-lg bg-lime-400 px-4 py-1.5 text-xs font-extrabold text-black hover:bg-lime-300"
            >
              Save Registration Answers
            </button>
          </div>
        </div>

        {registrationSavedMessage && (
          <div
            role="status"
            className="mt-3 rounded-xl border border-lime-400/30 bg-lime-400/10 px-3.5 py-2 text-xs font-semibold text-lime-300"
          >
            {registrationSavedMessage}
          </div>
        )}
      </section>

      {/* 5. Event FAQs Accordion */}
      {config.faqs.length > 0 && (
        <section
          id="attendee-faqs-section"
          className="rounded-2xl border border-white/10 bg-zinc-900/80 p-5"
        >
          <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-lime-400">
            <HelpCircle className="h-4 w-4" />
            Frequently Asked Questions ({config.faqs.length})
          </div>
          <div className="mt-3 space-y-2.5">
            {config.faqs.map((faq) => {
              const isOpen = openFaqId === faq.id;
              return (
                <div
                  key={faq.id}
                  className="rounded-xl border border-white/10 bg-black/30"
                >
                  <button
                    type="button"
                    onClick={() => setOpenFaqId(isOpen ? null : faq.id)}
                    aria-expanded={isOpen}
                    className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-bold text-white"
                  >
                    <span>{faq.question}</span>
                    {isOpen ? (
                      <ChevronUp className="h-4 w-4 shrink-0 text-lime-400" />
                    ) : (
                      <ChevronDown className="h-4 w-4 shrink-0 text-zinc-400" />
                    )}
                  </button>
                  {isOpen && (
                    <div className="border-t border-white/10 px-4 py-3 text-xs leading-relaxed text-zinc-300">
                      {faq.answer}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </section>
      )}

      {/* 6. Post-Event Feedback & Ratings */}
      {config.feedbackEnabled && (
        <section
          id="attendee-post-event-feedback-section"
          className="rounded-2xl border border-white/10 bg-zinc-900/80 p-5"
        >
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-4">
            <div>
              <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-400">
                <Star className="h-4 w-4 fill-amber-400 text-amber-400" />
                Post-Event Feedback & Verified Attendee Ratings
              </div>
              <p className="mt-1 text-xs text-zinc-400">
                {config.feedbackPrompt}
              </p>
            </div>

            <div className="flex items-center gap-4 rounded-xl border border-amber-400/30 bg-amber-500/10 px-4 py-2 text-xs">
              <div>
                <div className="text-lg font-extrabold text-amber-300">
                  {feedbackStats.averageOverall || "5.0"} ★
                </div>
                <div className="text-[10px] text-zinc-300">
                  {feedbackStats.count} Reviews
                </div>
              </div>
              <div className="border-l border-white/10 pl-3 text-[11px] text-zinc-300">
                <div>Food: {feedbackStats.averageFood || "5.0"} / 5</div>
                <div>Check-In: {feedbackStats.averageOperations || "5.0"} / 5</div>
              </div>
            </div>
          </div>

          <div className="mt-4 grid grid-cols-1 gap-5 lg:grid-cols-2">
            {/* Submit feedback form */}
            <form
              onSubmit={handleSubmitFeedback}
              className="space-y-3 rounded-xl border border-white/10 bg-black/30 p-4"
            >
              <div className="text-xs font-bold uppercase tracking-wider text-white">
                Share Your Event & Food Experience
              </div>
              <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <input
                  id="feedback-attendee-name"
                  type="text"
                  value={reviewerName}
                  onChange={(e) => setReviewerName(e.target.value)}
                  placeholder="Your Name (or First Name & Initial)"
                  className="rounded-lg border border-white/15 bg-zinc-900 px-3 py-1.5 text-xs text-white"
                />
                <div className="flex items-center justify-between rounded-lg border border-white/15 bg-zinc-900 px-3 py-1.5 text-xs">
                  <span className="text-zinc-300">Overall Rating:</span>
                  <div className="flex items-center gap-1">
                    {[1, 2, 3, 4, 5].map((star) => (
                      <button
                        key={star}
                        type="button"
                        aria-label={`Rate ${star} stars`}
                        onClick={() => setOverallRating(star)}
                        className={`text-sm ${
                          star <= overallRating
                            ? "text-amber-400"
                            : "text-zinc-600"
                        }`}
                      >
                        ★
                      </button>
                    ))}
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-2 text-xs">
                <label className="flex items-center justify-between rounded-lg border border-white/10 bg-zinc-900/60 px-3 py-1.5">
                  <span className="text-zinc-400">Food Quality:</span>
                  <select
                    aria-label="Food quality rating"
                    value={foodRating}
                    onChange={(e) => setFoodRating(Number(e.target.value))}
                    className="bg-transparent font-bold text-amber-300"
                  >
                    {[5, 4, 3, 2, 1].map((n) => (
                      <option key={n} value={n} className="bg-zinc-900">
                        {n} / 5
                      </option>
                    ))}
                  </select>
                </label>

                <label className="flex items-center justify-between rounded-lg border border-white/10 bg-zinc-900/60 px-3 py-1.5">
                  <span className="text-zinc-400">Gate & Pickup:</span>
                  <select
                    aria-label="Gate and pickup rating"
                    value={operationsRating}
                    onChange={(e) => setOperationsRating(Number(e.target.value))}
                    className="bg-transparent font-bold text-amber-300"
                  >
                    {[5, 4, 3, 2, 1].map((n) => (
                      <option key={n} value={n} className="bg-zinc-900">
                        {n} / 5
                      </option>
                    ))}
                  </select>
                </label>
              </div>

              <textarea
                id="feedback-comment-input"
                rows={2}
                value={reviewComment}
                onChange={(e) => setReviewComment(e.target.value)}
                placeholder="How was the food pickup speed, music, and check-in experience?"
                className="w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-2 text-xs text-white"
              />

              <div className="flex items-center justify-between">
                {feedbackSuccess ? (
                  <span className="text-xs font-semibold text-lime-300">
                    ✓ Thank you! Your feedback has been shared with the host.
                  </span>
                ) : (
                  <span className="text-[11px] text-zinc-400">
                    Verified attendees help improve future events
                  </span>
                )}
                <button
                  type="submit"
                  id="btn-submit-post-event-feedback"
                  className="rounded-lg bg-lime-400 px-3.5 py-1.5 text-xs font-extrabold text-black hover:bg-lime-300"
                >
                  Submit Rating
                </button>
              </div>
            </form>

            {/* Recent verified reviews */}
            <div className="space-y-2.5">
              {submissions.slice(0, 3).map((sub) => (
                <div
                  key={sub.id}
                  className="rounded-xl border border-white/10 bg-black/30 p-3.5 text-xs"
                >
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-white">
                      {sub.attendeeName}
                    </span>
                    <span className="font-bold text-amber-400">
                      {"★".repeat(sub.overallRating)} ({sub.overallRating}/5)
                    </span>
                  </div>
                  <p className="mt-1 text-zinc-300">{sub.comment}</p>
                </div>
              ))}
            </div>
          </div>
        </section>
      )}
    </div>
  );
};

