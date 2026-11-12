import {
  AttendeePrivacyRetentionPolicy,
  AttendeeRegistrationQuestion,
  EventAnnouncementItem,
  EventAttendeeExperienceConfig,
  EventDetails,
  EventFaqItem,
  EventFeedbackSubmission,
  EventGroupRegistrationConfig,
  EventReminderConfig,
  EventWorkerRoleKey,
} from "../types";

export const DEFAULT_ATTENDEE_REGISTRATION_QUESTIONS: AttendeeRegistrationQuestion[] = [
  {
    id: "q-dietary-restrictions",
    prompt: "Do you have any dietary restrictions?",
    fieldType: "multi_select",
    required: false,
    category: "dietary",
    options: [
      "None",
      "Vegetarian",
      "Vegan",
      "Halal",
      "Gluten-Free",
      "Nut Allergy",
      "Shellfish Allergy",
      "Dairy-Free",
    ],
    placeholder: "Select any dietary restrictions or allergies",
    privacyPurpose:
      "Shared only with assigned kitchen & food station staff to prevent allergen cross-contact.",
  },
  {
    id: "q-attending-with-group",
    prompt: "Are you attending with a group?",
    fieldType: "yes_no",
    required: false,
    category: "group",
    options: ["Yes", "No"],
    placeholder: "Let us know if you are arriving with a group",
    privacyPurpose:
      "Used by entrance and seating coordinators to streamline group check-in and table allocation.",
  },
  {
    id: "q-accessibility-assistance",
    prompt: "Do you require accessibility assistance?",
    fieldType: "single_select",
    required: false,
    category: "accessibility",
    options: [
      "No assistance needed",
      "Wheelchair / step-free entry & seating",
      "Accessible parking near entrance",
      "ASL / visual interpretation support",
      "Sensory-friendly quiet zone access",
    ],
    placeholder: "Select any accessibility accommodations needed",
    privacyPurpose:
      "Shared strictly with designated guest accessibility leads and supervisors prior to doors opening.",
  },
];

export const DEFAULT_EVENT_FAQS: EventFaqItem[] = [
  {
    id: "faq-arrival-checkin",
    question: "What do I need to bring for gate check-in and food pickup?",
    answer:
      "Bring your digital QR pass (on your phone or printed) along with a valid government-issued photo ID matching the reservation holder. Separate food pickup tokens are automatically linked to your order.",
    category: "tickets",
  },
  {
    id: "faq-food-preorders",
    question: "Can I customize my meal or order from multiple food stations?",
    answer:
      "Yes. You can pre-order from any participating event kitchen, choose proteins, sides, portion sizes, and dietary modifications, and select a scheduled pickup window so your meal is hot when you arrive.",
    category: "food",
  },
  {
    id: "faq-parking-shuttle",
    question: "Is there on-site parking or a rideshare drop-off zone?",
    answer:
      "Dedicated attendee parking is available in Lot A & Lot B with step-free pathways to Entrance A. Rideshare and taxi drop-offs are located directly at the North Gate turnaround.",
    category: "parking",
  },
  {
    id: "faq-weather-refunds",
    question: "What happens if weather affects the schedule?",
    answer:
      "This is a rain-or-shine cultural experience with covered dining pavilions. If severe weather requires a schedule adjustment, live announcements and SMS/email updates will be sent immediately.",
    category: "general",
  },
];

export const DEFAULT_EVENT_RULES: string[] = [
  "Valid digital QR ticket and photo ID required at Entrance A or VIP Entrance.",
  "Outside alcoholic beverages, glass bottles, and unauthorized coolers are not permitted.",
  "Small personal bags and clear totes are welcome; all bags are subject to rapid security screening.",
  "Respect fellow guests, culinary vendors, performers, and event staff at all times.",
  "Re-entry is permitted only with a scanned exit wristband from Entrance A.",
];

export const DEFAULT_DIETARY_PREFERENCE_OPTIONS: string[] = [
  "No Preference",
  "Vegetarian",
  "Vegan",
  "Halal Certified",
  "Ital / Plant-Based",
  "Gluten-Friendly",
  "Pescatarian",
  "Nut-Free Kitchen Prep",
];

export const DEFAULT_PRIVACY_RETENTION_POLICY: AttendeePrivacyRetentionPolicy = {
  retentionDaysAfterEvent: 30,
  allowAttendeeDeletionRequest: true,
  restrictStaffVisibilityToRole: true,
  purposeSummary:
    "Registration answers (dietary, accessibility, and group preferences) are used solely for event-day hospitality and safety, restricted by staff role, and automatically purged after the retention window.",
  encryptedAtRest: true,
};

export const DEFAULT_GROUP_REGISTRATION_CONFIG: EventGroupRegistrationConfig = {
  enabled: true,
  minGroupSize: 2,
  maxGroupSize: 15,
  groupDiscountPercent: 10,
  allowSharedCheckInPass: true,
};

export const DEFAULT_REMINDER_CONFIG: EventReminderConfig = {
  enabled: true,
  channels: ["email", "sms", "whatsapp"],
  scheduleOffsets: ["24 hours before doors open", "2 hours before doors open"],
  customReminderMessage:
    "Have your QR pass ready at Entrance A and check your scheduled food pickup window!",
};

export const DEFAULT_ANNOUNCEMENTS: EventAnnouncementItem[] = [
  {
    id: "ann-gates-food",
    title: "Gates & Express Food Pre-Order Pickup Ready",
    message:
      "Express QR lanes at Entrance A and VIP Entrance open 30 minutes prior to main stage sets. Pre-order food pickup numbers will be displayed live on the order board.",
    publishedAt: "2026-08-01T14:00:00Z",
    priority: "important",
    authorName: "Event Operations Team",
  },
  {
    id: "ann-parking-lot",
    title: "North Gate Rideshare & Accessible Drop-Off Pin Active",
    message:
      "Use the North Gate pin for Uber/Lyft drop-offs and step-free wheelchair access directly to the main pavilion.",
    publishedAt: "2026-08-01T16:30:00Z",
    priority: "info",
    authorName: "Venue Logistics",
  },
];

export const DEFAULT_FEEDBACK_SUBMISSIONS: EventFeedbackSubmission[] = [
  {
    id: "fb-1",
    attendeeName: "Marlon R.",
    overallRating: 5,
    foodRating: 5,
    operationsRating: 5,
    comment:
      "The pre-order pickup queue saved us 30 minutes in line! Jerk pork and festival were piping hot right at our 6:30 PM window.",
    submittedAt: "2026-07-28T22:15:00Z",
    verifiedAttendee: true,
  },
  {
    id: "fb-2",
    attendeeName: "Brianna K.",
    overallRating: 5,
    foodRating: 5,
    operationsRating: 4,
    comment:
      "Super clear parking and gate instructions. Staff at Entrance A had our group passes scanned in seconds.",
    submittedAt: "2026-07-29T01:10:00Z",
    verifiedAttendee: true,
  },
];

export function getDefaultAttendeeExperienceConfig(
  eventDetails?: Partial<EventDetails>
): EventAttendeeExperienceConfig {
  const existing = eventDetails?.attendeeExperienceConfig;

  const guestInstructions =
    existing?.guestInstructions ||
    eventDetails?.venueInstructions ||
    "Arrive 20–30 minutes before your scheduled entry or food pickup window. Follow signage to Entrance A (General Admission) or VIP Entrance. Present your QR code for ticket verification before proceeding to Food Station 1 or Food Station 2.";

  const parkingAndTransitInfo =
    existing?.parkingAndTransitInfo ||
    eventDetails?.parkingInformation ||
    "On-site parking is available in Lot A (General) and Lot B (VIP & Accessible) starting 1 hour before doors open. Rideshare drop-off is located at the North Gate turnaround, and public transit stops within a 4-minute walk of the main entrance.";

  const accessibilityInfo =
    existing?.accessibilityInfo ||
    eventDetails?.accessibilityInformation ||
    "Step-free wheelchair access is available at all gates, food stations, and restrooms. Reserved ADA seating and sensory-quiet zones are supported—indicate any accommodation needs in the registration questions so our supervisor team can assist upon arrival.";

  const dressCode =
    existing?.dressCode ||
    eventDetails?.dressCode ||
    "Island Chic / Smart Festival Casual (comfortable footwear recommended)";

  const ageRequirement =
    existing?.ageRequirement ||
    eventDetails?.ageRestriction ||
    "All Ages Welcome (21+ with valid government photo ID for bar wristbands)";

  return {
    faqs:
      existing?.faqs && existing.faqs.length > 0
        ? existing.faqs
        : DEFAULT_EVENT_FAQS.map((item) => ({ ...item })),
    guestInstructions,
    parkingAndTransitInfo,
    accessibilityInfo,
    eventRules:
      existing?.eventRules && existing.eventRules.length > 0
        ? [...existing.eventRules]
        : [...DEFAULT_EVENT_RULES],
    dressCode,
    ageRequirement,
    registrationQuestions:
      existing?.registrationQuestions && existing.registrationQuestions.length > 0
        ? existing.registrationQuestions.map((q) => ({
            ...q,
            options: q.options ? [...q.options] : undefined,
          }))
        : DEFAULT_ATTENDEE_REGISTRATION_QUESTIONS.map((q) => ({
            ...q,
            options: q.options ? [...q.options] : undefined,
          })),
    dietaryPreferenceOptions:
      existing?.dietaryPreferenceOptions &&
      existing.dietaryPreferenceOptions.length > 0
        ? [...existing.dietaryPreferenceOptions]
        : [...DEFAULT_DIETARY_PREFERENCE_OPTIONS],
    enableDietaryPreferencePrompt:
      existing?.enableDietaryPreferencePrompt ?? true,
    privacyRetentionPolicy: existing?.privacyRetentionPolicy
      ? { ...existing.privacyRetentionPolicy }
      : { ...DEFAULT_PRIVACY_RETENTION_POLICY },
    groupRegistration: existing?.groupRegistration
      ? { ...existing.groupRegistration }
      : { ...DEFAULT_GROUP_REGISTRATION_CONFIG },
    reminders: existing?.reminders
      ? {
          ...existing.reminders,
          channels: [...existing.reminders.channels],
          scheduleOffsets: [...existing.reminders.scheduleOffsets],
        }
      : {
          ...DEFAULT_REMINDER_CONFIG,
          channels: [...DEFAULT_REMINDER_CONFIG.channels],
          scheduleOffsets: [...DEFAULT_REMINDER_CONFIG.scheduleOffsets],
        },
    calendarIntegrationEnabled: existing?.calendarIntegrationEnabled ?? true,
    announcements:
      existing?.announcements && existing.announcements.length > 0
        ? existing.announcements.map((a) => ({ ...a }))
        : DEFAULT_ANNOUNCEMENTS.map((a) => ({ ...a })),
    feedbackEnabled: existing?.feedbackEnabled ?? true,
    feedbackPrompt:
      existing?.feedbackPrompt ||
      "How was the food, music, and check-in experience? Your feedback helps the host and culinary vendors improve future events.",
    feedbackSubmissions:
      existing?.feedbackSubmissions && existing.feedbackSubmissions.length > 0
        ? existing.feedbackSubmissions.map((f) => ({ ...f }))
        : DEFAULT_FEEDBACK_SUBMISSIONS.map((f) => ({ ...f })),
  };
}

export interface RegistrationValidationResult {
  valid: boolean;
  missingQuestionIds: string[];
  errors: Record<string, string>;
}

export function validateRegistrationQuestionResponses(
  questions: AttendeeRegistrationQuestion[],
  responses: Record<string, string | string[]>,
  groupRegistrationOptions?: {
    isGroupBooking?: boolean;
    groupSize?: number;
    groupName?: string;
    groupConfig?: EventGroupRegistrationConfig;
  }
): RegistrationValidationResult {
  const missingQuestionIds: string[] = [];
  const errors: Record<string, string> = {};

  for (const q of questions) {
    if (!q.required) continue;
    const raw = responses[q.id];
    const isEmpty =
      raw === undefined ||
      raw === null ||
      (typeof raw === "string" && raw.trim().length === 0) ||
      (Array.isArray(raw) && raw.filter((v) => v.trim().length > 0).length === 0);

    if (isEmpty) {
      missingQuestionIds.push(q.id);
      errors[q.id] = `Please answer required question: "${q.prompt}"`;
    }
  }

  if (
    groupRegistrationOptions?.isGroupBooking &&
    groupRegistrationOptions.groupConfig?.enabled
  ) {
    const cfg = groupRegistrationOptions.groupConfig;
    const size = groupRegistrationOptions.groupSize ?? 1;
    if (size < cfg.minGroupSize || size > cfg.maxGroupSize) {
      errors["groupSize"] = `Group size must be between ${cfg.minGroupSize} and ${cfg.maxGroupSize} guests.`;
    }
    if (
      !groupRegistrationOptions.groupName ||
      groupRegistrationOptions.groupName.trim().length === 0
    ) {
      errors["groupName"] = "Please provide a group name for group check-in.";
    }
  }

  return {
    valid: missingQuestionIds.length === 0 && Object.keys(errors).length === 0,
    missingQuestionIds,
    errors,
  };
}

export function filterQuestionsVisibleToWorkerRole(
  questions: AttendeeRegistrationQuestion[],
  workerRole:
    | EventWorkerRoleKey
    | "kitchen"
    | "gate"
    | "cashier"
    | "host",
  restrictByRole: boolean
): AttendeeRegistrationQuestion[] {
  if (!restrictByRole || workerRole === "host" || workerRole === "supervisor") {
    return questions;
  }
  if (workerRole === "kitchen" || workerRole === "food_worker") {
    return questions.filter((q) => q.category === "dietary");
  }
  if (workerRole === "gate" || workerRole === "ticket_checker") {
    return questions.filter(
      (q) => q.category === "group" || q.category === "accessibility"
    );
  }
  // Cashier / payment_clerk does not need medical/accessibility or dietary details
  return [];
}

export function formatPrivacyRetentionDisclosure(
  policy: AttendeePrivacyRetentionPolicy
): string {
  const retentionLabel =
    policy.retentionDaysAfterEvent <= 0
      ? "immediately after the event ends"
      : `${policy.retentionDaysAfterEvent} days after the event concludes`;
  const roleScope = policy.restrictStaffVisibilityToRole
    ? "Role-scoped access enforced (only relevant stations see dietary or accessibility notes)."
    : "Visible to authorized event staff.";
  const deletionNote = policy.allowAttendeeDeletionRequest
    ? "You may request immediate deletion of your registration answers at any time."
    : "";

  return `Privacy & Data Retention: Responses are encrypted at rest and automatically purged ${retentionLabel}. ${roleScope} ${deletionNote}`.trim();
}

function toCompactUtcCalendarTimestamp(
  dateIso?: string,
  timeStr?: string,
  fallbackHoursOffset = 0
): string {
  const baseDate =
    dateIso && /^\d{4}-\d{2}-\d{2}$/.test(dateIso)
      ? dateIso
      : "2026-08-15";
  const cleanTime =
    timeStr && /^\d{2}:\d{2}$/.test(timeStr) ? timeStr : "16:00";

  const [year, month, day] = baseDate.split("-").map(Number);
  const [hour, minute] = cleanTime.split(":").map(Number);
  const dt = new Date(
    Date.UTC(year, (month || 1) - 1, day || 1, (hour || 16) + fallbackHoursOffset, minute || 0, 0)
  );
  return dt.toISOString().replace(/[-:]/g, "").replace(/\.\d{3}Z$/, "Z");
}

export function buildGoogleCalendarUrl(event: Partial<EventDetails>): string {
  const startUtc = toCompactUtcCalendarTimestamp(
    event.eventDateIso,
    event.startTime,
    0
  );
  const endUtc = toCompactUtcCalendarTimestamp(
    event.endDateIso || event.eventDateIso,
    event.endTime || event.startTime,
    event.endTime ? 0 : 4
  );
  const title = encodeURIComponent(event.title || "EventBite Cultural Experience");
  const location = encodeURIComponent(
    [event.venueName, event.venueAddress].filter(Boolean).join(", ") ||
      "Event Venue"
  );
  const details = encodeURIComponent(
    [
      event.tagline || event.description || "",
      event.attendeeExperienceConfig?.guestInstructions
        ? `Guest Instructions: ${event.attendeeExperienceConfig.guestInstructions}`
        : "",
    ]
      .filter(Boolean)
      .join("\n\n")
  );

  return `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${title}&dates=${startUtc}/${endUtc}&location=${location}&details=${details}`;
}

export function buildIcsCalendarContent(event: Partial<EventDetails>): string {
  const startUtc = toCompactUtcCalendarTimestamp(
    event.eventDateIso,
    event.startTime,
    0
  );
  const endUtc = toCompactUtcCalendarTimestamp(
    event.endDateIso || event.eventDateIso,
    event.endTime || event.startTime,
    event.endTime ? 0 : 4
  );
  const uid = `${event.id || "eventbite-event"}@eventbite.app`;
  const summary = (event.title || "EventBite Event").replace(/\r?\n/g, " ");
  const location = (
    [event.venueName, event.venueAddress].filter(Boolean).join(", ") ||
    "Event Venue"
  ).replace(/\r?\n/g, " ");
  const description = (
    event.attendeeExperienceConfig?.guestInstructions ||
    event.description ||
    "Bring your digital QR pass for gate check-in and food pickup."
  ).replace(/\r?\n/g, "\\n");

  return [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//EventBite//Attendee Experience//EN",
    "CALSCALE:GREGORIAN",
    "METHOD:PUBLISH",
    "BEGIN:VEVENT",
    `UID:${uid}`,
    `DTSTAMP:${startUtc}`,
    `DTSTART:${startUtc}`,
    `DTEND:${endUtc}`,
    `SUMMARY:${summary}`,
    `LOCATION:${location}`,
    `DESCRIPTION:${description}`,
    "END:VEVENT",
    "END:VCALENDAR",
  ].join("\r\n");
}

export function computeFeedbackAggregate(
  submissions: EventFeedbackSubmission[]
): {
  count: number;
  averageOverall: number;
  averageFood: number;
  averageOperations: number;
} {
  if (!submissions || submissions.length === 0) {
    return {
      count: 0,
      averageOverall: 0,
      averageFood: 0,
      averageOperations: 0,
    };
  }
  const count = submissions.length;
  const sumOverall = submissions.reduce((acc, s) => acc + s.overallRating, 0);
  const sumFood = submissions.reduce((acc, s) => acc + s.foodRating, 0);
  const sumOps = submissions.reduce((acc, s) => acc + s.operationsRating, 0);

  return {
    count,
    averageOverall: Number((sumOverall / count).toFixed(1)),
    averageFood: Number((sumFood / count).toFixed(1)),
    averageOperations: Number((sumOps / count).toFixed(1)),
  };
}
