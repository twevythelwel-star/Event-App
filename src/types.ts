export type Currency = "USD" | "JMD";
export type BusinessLocation = "JM" | "US";
export type MethodId = "bank" | "zelle" | "cashapp" | "cash" | "card";
export type PaymentMethodKey = MethodId;
export type OrderStatus = PaymentStatus;
export type OrderFulfillmentStatus = FulfillmentStatus;
export type MenuPricingType = PricingType;

// 7 Payment Statuses per specification
export type PaymentStatus =
  | "awaiting_payment"      // Order created, but no payment confirmed
  | "payment_submitted"     // Customer says they sent transfer / provided ref
  | "under_verification"    // Organizer is checking transaction
  | "partially_paid"        // Only part of required amount received
  | "paid"                  // Full required amount verified
  | "rejected"              // Submitted payment could not be verified
  | "refunded"              // Refund recorded with supporting details
  | "pending_verification"; // Backward-compatible alias for payment_submitted

// Separate Order Fulfillment / Kitchen Status
export type FulfillmentStatus = "unprepared" | "accepted" | "preparing" | "ready_for_pickup" | "collected";

export type HandoverStatus = "pending" | "collected";

export type PaymentMethodSettings = {
  enabled: boolean;
  eligible: boolean;
  details: string;
};

export type Settings = {
  businessLocation: BusinessLocation;
  currency: Currency;
  ticketPriceUSD: number;
  ticketPriceJMD: number;
  methods: Record<MethodId, PaymentMethodSettings>;
};

export type MenuCategory =
  | "Main Dishes"
  | "Seafood"
  | "Poultry"
  | "Meat"
  | "Vegetarian"
  | "Vegan"
  | "Rice and Grains"
  | "Sides"
  | "Desserts"
  | "Drinks"
  | "Snacks"
  | "Cultural Specialties"
  | "Meal Packages"
  | "Sauces and Condiments"
  | "Extras"
  | "Community Donations"
  | (string & {});

export type PricingType = "fixed" | "customizable" | "custom_amount";

export type CustomOptionChoice = {
  label: string;
  extraPriceUSD: number;
  extraPriceJMD: number;
};

export type CustomOptionGroup = {
  id: string;
  title: string; // e.g. "Fish Size", "Choice of Side", "Beverage Pairing", "Extras & Sauces"
  required: boolean;
  choices: CustomOptionChoice[];
};

export type CustomAmountConfig = {
  minUSD: number;
  maxUSD: number;
  minJMD: number;
  maxJMD: number;
  suggestedAmountsUSD: number[];
  suggestedAmountsJMD: number[];
};

export type MenuItem = {
  id: string;
  organizerId?: string;
  name: string;
  category: MenuCategory;
  description: string;
  pricingType: PricingType;
  priceUSD: number;
  priceJMD: number;
  imageUrl: string;
  imageIsUploaded?: boolean;
  // Country of origin / cuisine association (e.g. "Jamaica", "Caribbean")
  countryOfOrigin?: string;
  cuisineAssociation?: string;
  // Estimated preparation time in minutes (where relevant)
  prepTimeMinutes?: number;
  // Availability window (ISO strings). When set, item is only orderable within this window.
  availabilityStartIso?: string;
  availabilityEndIso?: string;
  // Dietary classification
  dietaryClassification?:
    | "none"
    | "vegetarian"
    | "vegan"
    | "pescatarian"
    | "halal"
    | "kosher"
    | "glutenFree"
    | "dairyFree"
    | "nutFree";
  // Allergen information (e.g. ["gluten", "dairy", "nuts", "shellfish"])
  allergens?: string[];
  // Ingredient information
  ingredients?: string;
  // Maximum quantity a single order may include of this item
  maxQtyPerOrder?: number;
  // Featured item status (shown prominently in menus)
  isFeatured?: boolean;
  // Whether the item is currently available for ordering
  isAvailable?: boolean;
  // Service mode: preorder only, event-day only, or both
  serviceMode?: "preorder" | "eventDay" | "both";
  // Vendor operating under a multi-vendor event
  vendorId?: string;
  vendorName?: string;
  // Daily time-of-day availability window (HH:MM 24h, e.g. "07:00" to "11:00" for Breakfast)
  availableFromTime?: string;
  availableUntilTime?: string;
  // Item-specific preorder cutoff timestamp
  preorderCutoffIso?: string;
  // Multi-select dietary tags for filtering (e.g. ["vegetarian", "halal", "glutenFree"])
  dietaryTags?: (
    | "vegetarian"
    | "vegan"
    | "pescatarian"
    | "halal"
    | "kosher"
    | "glutenFree"
    | "dairyFree"
    | "nutFree"
  )[];
  // Portion size options with immediate price delta
  portionSizes?: {
    id: string;
    label: string;
    extraPriceUSD: number;
    extraPriceJMD: number;
  }[];
  customOptionGroups?: CustomOptionGroup[];
  customAmountConfig?: CustomAmountConfig;
  allowSpecialInstructions?: boolean;
  availableStock?: number;
};

/** Mode of food service offered at the event. */
export type FoodServiceMode = "disabled" | "preorder" | "eventDay" | "both";

/** A defined pickup window for food orders. */
export type PickupWindow = {
  id: string;
  label: string; // e.g. "12:00 PM – 1:30 PM"
  startTime: string; // HH:MM
  endTime: string; // HH:MM
  maxOrders?: number;
};

/** Host-configured food service settings (Section 7.1). */
export type FoodServiceConfig = {
  enabled: boolean;
  serviceMode: FoodServiceMode;
  fulfillmentMode: "pickup" | "pickup_and_delivery";
  deliveryInstructions?: string;
  foodServiceStartTime?: string; // HH:MM local
  foodServiceEndTime?: string; // HH:MM local
  orderingDeadlineIso?: string; // ISO deadline for preorders
  pickupWindows?: PickupWindow[];
  packagingFeeUSD?: number;
  packagingFeeJMD?: number;
  serviceFeeUSD?: number;
  serviceFeeJMD?: number;
  maxOrdersPerEvent?: number;
};

/** A single component within a combined meal package (Section 7.4). */
export type MenuPackageComponent = {
  menuItemId: string;
  menuItemName: string;
  quantity: number;
  isRequired: boolean;
  isSubstitutable: boolean;
  substituteMenuItemIds?: string[];
  upgradePriceUSD?: number;
  upgradePriceJMD?: number;
};

/** An optional add-on item that can be added to a package. */
export type MenuPackageAddition = {
  menuItemId: string;
  menuItemName: string;
  priceUSD: number;
  priceJMD: number;
};

export type MenuPackageChoice = {
  menuItemId: string;
  menuItemName: string;
  upgradePriceUSD: number;
  upgradePriceJMD: number;
};

export type MenuPackageChoiceGroup = {
  id: string;
  title: string;
  min: number;
  max: number;
  options: MenuPackageChoice[];
};

/** A combined meal package built from existing menu items (Section 7.4). */
export type MenuPackage = {
  id: string;
  name: string;
  description: string;
  imageUrl: string;
  imageIsUploaded?: boolean;
  basePriceUSD: number;
  basePriceJMD: number;
  components: MenuPackageComponent[];
  choiceGroups?: MenuPackageChoiceGroup[];
  optionalAdditions?: MenuPackageAddition[];
  maxQty?: number; // max packages available total
  maxQtyPerOrder?: number; // max per attendee order
  isAvailable?: boolean;
  isFeatured?: boolean;
};

/** Attendee-facing mix-and-match order method (Section 7.5). */
export type OrderMethod = "package" | "buildYourOwn";

export type OrderItem = {
  id?: string; // Server order-item id (present for orders loaded from the API)
  menuItemId: string;
  name: string;
  packageId?: string; // Reference to the MenuPackage this item belongs to (if any)
  packageName?: string; // Snapshot of package name
  pricingType: PricingType;
  basePrice: number;
  selectedOptionsSummary: string[];
  optionsTotalExtra: number;
  customDonationAmount?: number;
  finalItemPrice: number;
  currency: Currency;
  quantity: number;
  collectedQuantity?: number; // Tracks partial portions collected (e.g. 1 of 2 fish meals)
  fulfilledQuantity?: number; // Portions handed over to the customer
  remainingQuantity?: number; // Portions not yet handed over
  // Per-item statuses so items can be fulfilled independently (Section 7.7)
  paymentStatus?: PaymentStatus | "unpaid" | "cancelled" | "unknown";
  preparationStatus?: "pending" | "accepted" | "preparing" | "ready" | "collected";
  pickupStatus?: "pending" | "partlyCollected" | "collected";
  vendorName?: string;
  portionSizeLabel?: string;
  lineType?: "item" | "ticket" | "package" | "component";
  packageLineId?: string;
  category?: MenuCategory;
  specialInstructions?: string;
  handoverStatus: HandoverStatus;
  handedOverAt?: string;
  handedOverBy?: string;
  stationHandedOver?: string;
};

export type StaffStation = "all" | "mains" | "sides" | "drinks" | "desserts";

export type HandoverAuditEntry = {
  id: string;
  organizerId?: string;
  orderId: string;
  orderNumber: string;
  ticketCode: string;
  customerName: string;
  itemName: string;
  itemCategory?: MenuCategory;
  quantityCollected: number;
  remainingQuantity: number;
  timestamp: string;
  staffName: string;
  station: string;
  actionType: "collection" | "reversal" | "supervisor_override";
  overrideReason?: string;
  supervisorName?: string;
};

export type TicketOrder = {
  id: string;
  organizerId?: string;
  ticketCode: string;
  orderNumber: string;
  customerName: string;
  customerPhone: string;
  email?: string;
  method: MethodId;
  subtotal: number;
  packagingFee: number;
  totalAmount: number;
  amountPaid?: number; // Tracks actual amount verified (critical for partially_paid)
  currency: Currency;
  status: PaymentStatus;
  fulfillmentStatus?: FulfillmentStatus; // Separate from payment status
  pickupNumber?: string; // Queue number for food pickup (e.g. "PU-104")
  pickupWindow?: string; // Scheduled collection window (e.g. "12:00 PM – 12:30 PM")
  readyNotificationSentAt?: string; // Timestamp when attendee was notified order is ready
  paymentReference?: string; // Reference number or transaction ID provided by customer/staff
  senderName?: string; // Name on sender's Zelle/Cash App/bank account
  verifiedAt?: string;
  verifiedBy?: string;
  verificationNotes?: string;
  rejectionReason?: string;
  refundDetails?: {
    amount: number;
    reason: string;
    refundedAt: string;
    refundedBy: string;
  };
  authorizedUnpaid?: boolean; // Organizer authorized meal prep/reservation prior to payment
  createdAt: string;
  items: OrderItem[];
  allHandedOver: boolean;
  closedAt?: string;
  notes?: string;
  secureTicketToken: string;
  eventId?: string;
  isReversed?: boolean;
  eventTitle?: string;
  venueName?: string;
  venueLocation?: string;
  eventDateStr?: string;
  eventTimeStr?: string;
  eventThumbnail?: string;
  isTransferred?: boolean;
  transferredTo?: string;
  transferredAt?: string;
  // Present on orders loaded for host/staff operations: what the signed-in user may do with this order
  capabilities?: { verify: boolean; collect: boolean; scan: boolean; cash: boolean; reverse: boolean; refund: boolean };
  refunds?: { amount: number; reason: string; refundedAt: string; refundedBy: string }[];
};

export type CashTransactionType = "receipt" | "payment" | "adjustment" | "reversal";

export type CashPaymentRecord = {
  id: string;
  organizerId?: string;
  transactionType: CashTransactionType;
  receiptNumber: string;
  customerName: string;
  customerPhone: string;
  eventName?: string;
  orderId?: string;
  orderNumber?: string;
  ticketCode?: string;
  amount: number; // positive for receipt/payment, negative for reversal/adjustment
  currency: Currency;
  receivedAt: string;
  receivedBy: string; // Person who received the cash (e.g. "Pastor Brown", "Treasurer Davis")
  notes?: string;
  originalRecordId?: string; // For audit trail linking reversals to original receipt
  reversalOfId?: string;
  reversalReason?: string;
  reversalAuthorizedBy?: string;
  createdAt?: string;
  eventId?: string;
  isReversed?: boolean;
};

export type EventStatus = "draft" | "published" | "unpublished" | "cancelled";

export type EventWizardStepId =
  | "basic_details"
  | "date_location"
  | "tickets"
  | "food"
  | "review";

export type EventTicketTierCategory =
  | "General"
  | "General Admission"
  | "VIP"
  | "Premium VIP"
  | "Early Bird"
  | "Group"
  | "Family Pass"
  | "Group Pass"
  | "Student Ticket"
  | "Child Ticket"
  | "Complimentary Ticket"
  | "Vendor Pass"
  | "Staff Pass"
  | "Custom Ticket Type"
  | "Community";

export type EventTicketTier = {
  id: string;
  name: string;
  category: EventTicketTierCategory;
  currency?: Currency;
  priceUSD: number;
  priceJMD: number;
  availableStock: number;
  maxPerCustomer: number;
  minPerOrder?: number;
  admissionCount?: number;
  saleStartIso?: string;
  saleEndIso?: string;
  description?: string;
  benefits?: string[];
  admissionPrivileges?: string;
  includedFoodAndDrinks?: string;
  ageRestriction?: string;
  refundEligible?: boolean;
  visibility?: "public" | "hidden";
  designStyle?: "premium_minimal" | "vip_dark" | "festival_color" | "modern_digital" | "elegant_gold" | "concert_pass";
  accentColor?: "indigo" | "emerald" | "coral" | "cyan" | "violet" | "gold";
  backgroundStyle?: "solid" | "gradient" | "glow";
  isActive?: boolean;
};

export type EventOperationStationType =
  | "ticket_checking"
  | "food_station"
  | "payment_desk"
  | "supervisor"
  | "custom";

export type EventOperationStation = {
  id: string;
  stationName: string; // e.g., "Entrance A", "VIP Entrance", "Food Station 1", "Food Station 2", "Payment Desk", "Supervisor"
  stationType: EventOperationStationType;
  assignedResponsibility: string; // e.g., "Ticket verification", "VIP ticket verification", "Main meals", "Drinks and desserts", "Authorized cash/payment verification", "Event-wide oversight"
  assignedStaffIds?: string[];
  assignedStaffNames?: string[];
  operatingHours?: string;
  notes?: string;
};

export type EventWorkerRoleKey =
  | "payment_clerk"
  | "ticket_checker"
  | "food_station_worker"
  | "supervisor"
  | "custom_restricted_worker";

export type StaffCheckInStatus =
  | "not_checked_in"
  | "checked_in"
  | "on_break"
  | "checked_out";

export type EventStaffMemberAssignment = {
  id: string;
  userId: string;
  name: string;
  email: string;
  phone?: string;
  claimCode?: string;
  roleOnEvent: EventWorkerRoleKey;
  stationId: string;
  stationName: string;
  assignedResponsibility: string;
  individualPermissions: string[];
  shiftStartsAt: string; // ISO or datetime-local
  shiftExpiresAt: string; // ISO or datetime-local
  timezone: string;
  status: "active" | "scheduled" | "pending_acceptance" | "expired" | "revoked";
  checkInStatus: StaffCheckInStatus;
  checkedInAt?: string;
  checkedOutAt?: string;
  assignedBy?: string;
};

export type EventStaffShiftSchedule = {
  id: string;
  shiftName: string; // e.g., "Shift 1 — Gate Setup & Early Admission"
  startTime: string; // HH:MM
  endTime: string; // HH:MM
  stationName: string;
  assignedStaffName: string;
  roleOnEvent: EventWorkerRoleKey;
  responsibility: string;
};

export type EventStaffActivityEntry = {
  id: string;
  timestamp: string;
  staffId: string;
  staffName: string;
  roleOnEvent: EventWorkerRoleKey;
  stationName: string;
  actionType:
    | "staff_invited"
    | "staff_check_in"
    | "staff_check_out"
    | "station_assigned"
    | "ticket_verified"
    | "food_collected"
    | "payment_verified"
    | "escalation_logged"
    | "access_revoked";
  summary: string;
};

export type EventEmergencyContact = {
  id: string;
  label: string; // e.g., "On-Site Medical / First Aid Lead", "Venue Security Dispatch"
  contactName: string;
  phone: string;
  locationOrPost: string;
  notes?: string;
};

export type EventEscalationContact = {
  id: string;
  tierLevel: 1 | 2 | 3;
  roleTitle: string; // e.g., "Tier 1 — Event Supervisor", "Tier 2 — Box Office & Payment Lead", "Tier 3 — Event Host / Owner"
  contactName: string;
  phone: string;
  email?: string;
  triggerScenarios: string; // e.g., "Duplicate QR scan disputes, VIP gate overrides"
};

export type EventOperationsConfig = {
  enabled: boolean;
  stations: EventOperationStation[];
  staffAssignments: EventStaffMemberAssignment[];
  schedules: EventStaffShiftSchedule[];
  activityLogs: EventStaffActivityEntry[];
  emergencyContacts: EventEmergencyContact[];
  escalationContacts: EventEscalationContact[];
  assemblyPoint?: string;
  medicalPostLocation?: string;
};

export type EventFaqItem = {
  id: string;
  question: string;
  answer: string;
  category?: "general" | "tickets" | "food" | "parking" | "accessibility";
};

export type AttendeeRegistrationQuestionType =
  | "text"
  | "single_select"
  | "multi_select"
  | "yes_no";

export type AttendeeRegistrationQuestionCategory =
  | "dietary"
  | "group"
  | "accessibility"
  | "general";

export type AttendeeRegistrationQuestion = {
  id: string;
  prompt: string;
  fieldType: AttendeeRegistrationQuestionType;
  required: boolean;
  category: AttendeeRegistrationQuestionCategory;
  options?: string[];
  placeholder?: string;
  privacyPurpose: string;
};

export type AttendeeQuestionResponse = {
  questionId: string;
  prompt: string;
  value: string | string[];
};

export type AttendeePrivacyRetentionPolicy = {
  retentionDaysAfterEvent: number;
  allowAttendeeDeletionRequest: boolean;
  restrictStaffVisibilityToRole: boolean;
  purposeSummary: string;
  encryptedAtRest: boolean;
};

export type EventGroupRegistrationConfig = {
  enabled: boolean;
  minGroupSize: number;
  maxGroupSize: number;
  groupDiscountPercent?: number;
  allowSharedCheckInPass: boolean;
};

export type EventReminderChannel = "sms" | "email" | "whatsapp";

export type EventReminderConfig = {
  enabled: boolean;
  channels: EventReminderChannel[];
  scheduleOffsets: string[];
  customReminderMessage?: string;
};

export type EventAnnouncementItem = {
  id: string;
  title: string;
  message: string;
  publishedAt: string;
  priority: "info" | "important" | "urgent";
  authorName?: string;
};

export type EventFeedbackSubmission = {
  id: string;
  attendeeName: string;
  overallRating: number;
  foodRating: number;
  operationsRating: number;
  comment: string;
  submittedAt: string;
  verifiedAttendee?: boolean;
};

export type EventAttendeeExperienceConfig = {
  faqs: EventFaqItem[];
  guestInstructions: string;
  parkingAndTransitInfo: string;
  accessibilityInfo: string;
  eventRules: string[];
  dressCode: string;
  ageRequirement: string;
  registrationQuestions: AttendeeRegistrationQuestion[];
  dietaryPreferenceOptions: string[];
  enableDietaryPreferencePrompt: boolean;
  privacyRetentionPolicy: AttendeePrivacyRetentionPolicy;
  groupRegistration: EventGroupRegistrationConfig;
  reminders: EventReminderConfig;
  calendarIntegrationEnabled: boolean;
  announcements: EventAnnouncementItem[];
  feedbackEnabled: boolean;
  feedbackPrompt: string;
  feedbackSubmissions: EventFeedbackSubmission[];
};

export type EventMediaAsset = {
  id: string;
  mediaType: "gallery" | "organization_logo" | "sponsor_logo";
  imageUrl: string;
  thumbnailUrl?: string | null;
  altText: string;
  width: number;
  height: number;
  positionX: number;
  positionY: number;
};

export type EventDetails = {
  id?: string;
  organizerId?: string;
  slug?: string;
  title: string;
  tagline: string;
  dateStr: string;
  timeStr: string;
  timezone?: string; // e.g. "America/Jamaica" or "America/New_York"
  venueName: string;
  venueBuildingName?: string;
  streetAddress?: string;
  city?: string;
  stateCode?: string;
  stateName?: string;
  zipCode?: string;
  countryCode?: string;
  venueType?: string; // e.g. "Park", "Concert Hall", "Restaurant", "Community Center", "Outdoor"
  venueInstructions?: string; // host-provided venue instructions (load-in, entrances, etc.)
  parkingInformation?: string; // parking / transit details for attendees
  locationVerified?: boolean; // true only after a configured mapping service confirms the location
  locationSource?: string; // provider that verified the location
  locationToken?: string; // short-lived server-signed token from address lookup
  clearLocationPin?: boolean;
  locationPreviewUrl?: string;
  latitude?: number | null;
  longitude?: number | null;
  targetMarketId?: string | null;
  venueAddress: string;
  coordinates: string;
  description: string;
  currency?: Currency; // "JMD" | "USD"
  organizerName: string;
  organizerPhone: string;
  organizerWhatsApp: string;
  organizerEmail: string;
  advanceOrderDeadline: string;
  isOrderingOpen: boolean;
  status?: EventStatus;
  pickupInstructions: string;
  packagingFeeUSD: number;
  packagingFeeJMD: number;
  category?: string;
  summary?: string;
  eventTypes?: string[];
  eventFormat?: "in_person" | "virtual" | "hybrid";
  expectedAttendance?: number | null;
  ageRestriction?: string;
  dressCode?: string;
  accessibilityInformation?: string;
  languages?: string[];
  visibility?: "public" | "unlisted" | "private";
  isFeatured?: boolean;
  virtualUrl?: string;
  virtualAccessInstructions?: string;
  culturalCategoryIds?: string[];
  countryIds?: string[];
  dishIds?: string[];
  customDishes?: {
    name: string;
    countryId: string;
    alternativeSpellings?: string[];
    isFusion?: boolean;
    dietaryType?: "any" | "vegetarian" | "vegan";
  }[];
  eventDateIso?: string;
  endDateIso?: string; // ISO date (YYYY-MM-DD) when the event ends — required for multi-day / past-midnight events
  endDateStr?: string; // Human-readable end date label
  startTime?: string;
  endTime?: string;
  doorsOpenTime?: string; // HH:MM when doors/gates open
  foodServiceStartTime?: string; // HH:MM when food service begins
  foodServiceEndTime?: string; // HH:MM when food service ends
  setupTime?: string; // HH:MM when venue setup / load-in begins (where relevant)
  earlyBirdSaleStartIso?: string; // event-local datetime-local value
  earlyBirdSaleEndIso?: string; // event-local datetime-local value
  ticketSalesStartIso?: string; // event-local datetime-local value
  ticketSalesEndIso?: string; // event-local datetime-local value
  supportsFoodOrdering?: boolean;
  foodServiceConfig?: FoodServiceConfig;
  operationsConfig?: EventOperationsConfig;
  attendeeExperienceConfig?: EventAttendeeExperienceConfig;
  packages?: MenuPackage[];
  ticketTiers?: EventTicketTier[];
  refundPolicyDeadlineHours?: number;
  refundPolicyNotes?: string;
  refundPolicyMode?: "manual_full_only" | "manual_full_and_partial" | "non_refundable";
  hostCancellationPolicy?: "contact_organizer" | "manual_refund_if_supported";
  postponementPolicy?: "tickets_valid_for_new_date" | "contact_organizer";
  weatherCancellationPolicy?: "contact_organizer" | "reschedule_tickets_valid" | "manual_refund_if_supported";
  foodOrderCancellationDeadlineHours?: number;
  foodOrderModificationDeadlineHours?: number;
  nonRefundableTicketCategories?: string[];
  eventMedia?: EventMediaAsset[];
  flyerUrl?: string;
  flyerThumbnailUrl?: string;
  flyerAltText?: string;
  flyerPositionX?: number;
  flyerPositionY?: number;
  flyerStoragePath?: string;
  flyerMimeType?: string;
  flyerSizeBytes?: number;
  flyerDimensions?: { width: number; height: number };
  isFlyerPublic?: boolean;
  shareableUrl?: string;
  socialSharingMeta?: {
    ogTitle: string;
    ogDescription: string;
    ogImage?: string;
  };
};

export type NotificationTrigger =
  // 9 Explicit Lifecycle Templates
  | "order_received"
  | "payment_instructions"
  | "transfer_submitted"
  | "payment_verified_rejected"
  | "cash_receipt_recorded"
  | "ticket_issued_updated"
  | "event_reminders"
  | "pickup_instructions"
  | "order_collection_completed"
  // Backward-compatible trigger aliases
  | "order_created"
  | "payment_verified"
  | "cash_received"
  | "order_updated"
  | "event_approaching"
  | "order_ready"
  | "items_collected";

export type NotificationChannel = "sms" | "whatsapp" | "email" | "link";

export type NotificationDeliveryStatus = "queued" | "sent" | "delivered" | "failed";

export type NotificationAttempt = {
  timestamp: string;
  status: NotificationDeliveryStatus;
  note: string;
  workerId?: string;
};

export type NotificationLogEntry = {
  id: string;
  organizerId?: string;
  orderId?: string;
  orderNumber?: string;
  customerName: string;
  recipient: string; // phone or email
  channel: NotificationChannel;
  trigger: NotificationTrigger;
  title: string;
  message: string;
  emailSubject?: string;
  emailHtml?: string;
  status: NotificationDeliveryStatus;
  provider: "Twilio SMS" | "WhatsApp Direct" | "Island Mail" | "Mailgun" | "Postmark" | "Shareable Link" | string;
  sentAt: string;
  createdAt?: string;
  deliveredAt?: string;
  failureReason?: string;
  retryCount?: number;
  idempotencyKey?: string;
  providerMessageId?: string;
  retrySuppressed?: boolean;
  attempts?: NotificationAttempt[];
};

export type NotificationTemplateDefinition = {
  id: string;
  trigger: NotificationTrigger;
  name: string;
  stageNumber: number;
  description: string;
  defaultEmailSubject: string;
  defaultEmailHtml: string;
  defaultSms: string;
  supportedChannels: NotificationChannel[];
  availableVariables: string[];
  sampleVariables: Record<string, string>;
  laravelNotificationClass: string;
  providerGuidelines: {
    senderIdRule: string;
    coverage: string;
    pricingEstimate: string;
    deliveryCallbackRequired: boolean;
  };
};

// 4 Tenant Roles per enterprise architecture specification
export type TenantRole =
  | "platform_admin"      // Platform infrastructure, tenant onboarding, global audits
  | "event_owner"         // Owns events, branding, menu/prices, payment settings, staff, reports
  | "payment_verifier"    // Audit bank references, verify transfers, record cash receipts
  | "food_collector";     // Ticket QR scanner, mark portions collected, kitchen prep sheets

export type OrganizerProfile = {
  id: string; // e.g. "org-kingston-youth"
  name: string; // e.g. "Kingston Youth Foundation"
  slug: string; // e.g. "kingstonyouth"
  status: "active" | "suspended" | "pending_onboarding";
  defaultCurrency: Currency;
  businessLocation: BusinessLocation;
  logoUrl?: string;
  flyerStoragePrefix: string;
  contactEmail: string;
  contactPhone: string;
  contactWhatsApp: string;
  tagline: string;
  createdAt: string;
};

export type TenantUserMember = {
  id: string;
  organizerId: string;
  name: string;
  email: string;
  role: TenantRole;
  stationAssignment: StaffStation;
  isActive: boolean;
  lastActiveAt: string;
};

export type TenantBreachSimulationResult = {
  id: string;
  simulatedAt: string;
  targetEntity: "orders" | "tickets" | "cash_payments" | "customers" | "reports" | "event_settings";
  targetRecordId: string;
  authenticatedTenantId: string;
  authenticatedTenantName: string;
  targetTenantId: string;
  targetTenantName: string;
  attemptedAction: string;
  policyTriggered: string;
  httpStatus: 403 | 200;
  statusText: string;
  accessGranted: boolean;
  securityMessage: string;
};

export type SavedState = {
  eventDetails: EventDetails;
  menu: MenuItem[];
  settings: Settings;
  orders: TicketOrder[];
  cashLedger: CashPaymentRecord[];
  notificationLogs: NotificationLogEntry[];
  handoverAuditLogs?: HandoverAuditEntry[];
  organizers?: OrganizerProfile[];
  activeOrganizerId?: string;
  activeRolePerspective?: TenantRole;
  tenantUsers?: TenantUserMember[];
  // Account types & responsibilities state
  activeAccountType?: AccountType;
  platformOverviewStats?: PlatformOverviewStats;
  systemHealthComponents?: SystemComponentHealth[];
  notificationProviders?: NotificationProviderStatus[];
  platformFeeSettings?: PlatformFeeSettings;
  securityAuditLogs?: SecurityAuditLog[];
  adminScopes?: AdminAssignedScope[];
  ticketDisputes?: TicketDispute[];
  attendeeProfile?: AttendeeProfile;
};

// 4 Official Enterprise Account Types
export type AccountType = "system_admin" | "admin" | "event_host" | "attendee";

// System Administrator Dashboard Models
export type SystemComponentHealth = {
  name: string;
  status: "healthy" | "degraded" | "failing";
  metrics: string;
  lastChecked: string;
};

export type PlatformOverviewStats = {
  totalGrossMerchandiseValueUSD: number;
  totalGrossMerchandiseValueJMD: number;
  totalPlatformFeesCollectedUSD: number;
  totalPlatformFeesCollectedJMD: number;
  totalRegisteredHosts: number;
  totalActiveEvents: number;
  totalTicketsIssued: number;
  systemUptimePercentage: number;
};

export type NotificationProviderStatus = {
  id: string;
  name: string;
  channel: "sms" | "email" | "whatsapp";
  status: "operational" | "degraded" | "disconnected";
  maskedApiKey: string; // "sk_live_••••••••1892" (never plaintext)
  maskedAccountSid?: string;
  endpointUrl: string;
  deliverySuccessRate: number;
  lastPingAt: string;
};

export type PlatformFeeSettings = {
  enabled: boolean;
  takeRatePercent: number; // e.g. 2.5%
  fixedFeeUSD: number; // e.g. 0.30
  fixedFeeJMD: number; // e.g. 50
  nonProfitDiscountRatePercent: number; // e.g. 1.0%
  supportedCurrencies: Currency[];
  supportedGateways: { id: string; name: string; enabled: boolean; region: BusinessLocation | "BOTH" }[];
  dataRetentionYears: number;
  automatedDailyBackups: boolean;
  lastBackupVerifiedAt: string;
};

export type SecurityAuditLog = {
  id: string;
  timestamp: string;
  actorId: string;
  actorName: string;
  actorRole: AccountType;
  action: string;
  targetEntity: string;
  targetId: string;
  ipAddress: string;
  severity: "info" | "warning" | "critical";
  details: string;
  isPiiAccess?: boolean;
};

// Delegated Administrator Dashboard Models
export type AdminAssignedScope = {
  id: string;
  adminUserId: string;
  adminName: string;
  assignedRegion: BusinessLocation | "ALL";
  canManageEvents: boolean;
  canAssistHostPublishing: boolean;
  canResolveDisputes: boolean;
  canViewScopedReports: boolean;
  canAssistAccountRecovery: boolean;
  canReviewPaymentAudit: boolean;
  canModifyGlobalFees: false; // Explicitly forbidden for delegated admins
  canViewUnmaskedCredentials: false; // Explicitly forbidden for delegated admins
};

export type DisputeStatus = "open" | "under_review" | "resolved" | "rejected";
export type DisputeType = "ticket_refund" | "lost_qr_code" | "payment_mismatch" | "customer_complaint";

export type TicketDispute = {
  id: string;
  disputeNumber: string;
  orderNumber: string;
  ticketCode: string;
  customerName: string;
  customerPhone: string;
  organizerId: string;
  organizerName: string;
  eventName: string;
  disputeType: DisputeType;
  status: DisputeStatus;
  amount: number;
  currency: Currency;
  reason: string;
  createdAt: string;
  updatedAt: string;
  assignedAdminName?: string;
  resolutionNotes?: string;
};

// Attendee Dashboard Models
export type SavedEvent = {
  eventId: string;
  organizerId: string;
  title: string;
  dateStr: string;
  venueName: string;
  flyerUrl: string;
  currency: Currency;
  savedAt: string;
};

export type AttendeeNotificationPreferences = {
  smsReminders: boolean;
  emailReceipts: boolean;
  whatsAppTickets: boolean;
  marketingUpdates: boolean;
};

export type AttendeeProfile = {
  id: string;
  name: string;
  phone: string;
  email: string;
  avatarUrl?: string;
  bio?: string;
  membershipStatus?: string;
  memberSince?: string;
  savedEvents: SavedEvent[];
  notificationPreferences: AttendeeNotificationPreferences;
  isGuest: boolean;
};

// ==========================================
// Account Settings & Responsibilities Models
// ==========================================
export type AccountSettingKey =
  | "personal_profile"
  | "change_password"
  | "two_factor_auth"
  | "notification_preferences"
  | "manage_platform_users"
  | "manage_platform_roles"
  | "platform_configuration"
  | "event_management"
  | "flyer_menu_management"
  | "event_payment_instructions"
  | "record_verify_payments"
  | "manage_event_staff"
  | "manage_tickets_collection"
  | "sales_financial_reports"
  | "currency_regional_preferences"
  | "privacy_data_controls"
  | "audit_security_logs";

export type AccountSettingScope =
  | "all"
  | "limited"
  | "assigned_permissions_only"
  | "all_subject_to_policy"
  | "oversight"
  | "authorized_scope"
  | "platform_defaults"
  | "authorized_oversight"
  | "assigned_permissions"
  | "assigned_scope"
  | "own_and_assigned_events"
  | "own_events"
  | "own_tickets"
  | "own_transactions"
  | "event_specific_settings"
  | "personal_preference"
  | "platform_policies"
  | "own_account_lawful_event_data"
  | "own_account_personal_data"
  | "authorized_platform_wide_access"
  | "scoped_access"
  | "own_event_activity"
  | "own_account_activity";

export type AccountSettingDefinition = {
  key: AccountSettingKey;
  title: string;
  category: "identity_security" | "platform_ops" | "event_ops" | "finance" | "compliance";
  description: string;
  scopes: Record<AccountType, AccountSettingScope | null>;
};

// ==========================================
// Specialized Settings Area Models
// ==========================================
export type HostEventDefaults = {
  defaultCurrency: Currency;
  defaultTimezone: string;
  defaultLocation: string;
  defaultOrderDeadlineHours: number;
  defaultPickupInstructions: string;
  defaultFlyerAspectRatio: "4:5" | "1:1" | "16:9";
  defaultBrandTheme: "eventbite_navy" | "caribbean_emerald" | "sunset_gold" | "reggae_fusion" | "coastal_blue";
};

export type HostPaymentPolicySettings = {
  enableCash: boolean;
  zelleInstructions: string;
  cashAppInstructions: string;
  bankTransferInstructions: string;
  requireTransferReference: boolean;
  requireSenderName: boolean;
  requireReceiptSnapshot: boolean;
  partialPaymentsEnabled: boolean;
  depositPercentage: number;
  autoEmailReceipt: boolean;
  autoSmsReceipt: boolean;
  refundPolicyDeadlineHours: number;
  refundPolicyNotes: string;
  refundPolicyMode: "manual_full_only" | "manual_full_and_partial" | "non_refundable";
  hostCancellationPolicy: "contact_organizer" | "manual_refund_if_supported";
  postponementPolicy: "tickets_valid_for_new_date" | "contact_organizer";
  weatherCancellationPolicy: "contact_organizer" | "reschedule_tickets_valid" | "manual_refund_if_supported";
  foodOrderCancellationDeadlineHours: number;
  foodOrderModificationDeadlineHours: number;
};

export type HostCommunicationTemplates = {
  orderConfirmationTemplate: string;
  paymentConfirmationTemplate: string;
  eventReminderTemplate: string;
  collectionConfirmationTemplate: string;
};

export type HostOperationalRules = {
  stations: Array<{ id: string; name: string; staffAssigned: string }>;
  maxEventCapacity: number;
  maxPerItemPortions: number;
  enforceAntiDuplicateRedemption: boolean;
  allowPartialCollection: boolean;
  foodPrepBufferPercent: number; // e.g. 10%
};

export type SystemPlatformSettings = {
  platformName: string;
  logoUrl: string;
  brandTheme: string;
  supportedCurrencies: Currency[];
  supportedPaymentMethods: MethodId[];
  fileStorageStatus: "healthy" | "degraded" | "maintenance";
  storageBucket: string;
  storageProvider: "s3_compatible_r2" | "aws_s3" | "local_dev";
  notificationProviderStatus: "operational" | "degraded" | "down";
  mandatory2FaForAdmins: boolean;
  adminSessionTimeoutMinutes: number;
  attendeeSessionTimeoutDays: number;
  defaultTimezone: string;
  defaultLocale: string;
  auditRetentionDays: number;
  piiScrubDays: number;
  databasePoolMax: number;
  queueWorkersCount: number;
};

export type AttendeeAccountSettings = {
  fullName: string;
  phone: string;
  email: string;
  isPhoneVerified: boolean;
  isEmailVerified: boolean;
  twoFactorEnabled: boolean;
  preferredDisplayCurrency: Currency;
  activeSessionsCount: number;
};

export type USLaunchMarketSettings = {
  operatingCountry: "US";
  operatingCurrency: "USD";
  currencySymbol: "$";
  defaultTimezone: "America/New_York";
  supportedTimezones: string[];
  isUSOnlyLaunch: boolean;
};

export type OrderFinancialMetrics = {
  currency: string;
  subtotalCents: number;
  packagingFeeCents: number;
  totalAmountCents: number;
  amountReceivedCents: number;
  outstandingBalanceCents: number;
  refundedAmountCents: number;
  netReceiptsCents: number;
  isFullyPaid: boolean;
  isPartiallyPaid: boolean;
};

export type USPrivacyPreferences = {
  doNotSellOrShare: boolean;
  limitSensitiveDataUse: boolean;
  recordedAt: string;
  jurisdiction: "US_CCPA_CPRA";
};
