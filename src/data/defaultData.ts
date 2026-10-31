import {
  AccountSettingDefinition,
  AdminAssignedScope,
  AttendeeAccountSettings,
  AttendeeProfile,
  CashPaymentRecord,
  EventDetails,
  HandoverAuditEntry,
  HostCommunicationTemplates,
  HostEventDefaults,
  HostOperationalRules,
  HostPaymentPolicySettings,
  MenuItem,
  NotificationLogEntry,
  NotificationProviderStatus,
  NotificationTemplateDefinition,
  OrganizerProfile,
  PlatformFeeSettings,
  PlatformOverviewStats,
  SavedState,
  SecurityAuditLog,
  Settings,
  SystemComponentHealth,
  SystemPlatformSettings,
  TenantUserMember,
  TicketDispute,
  TicketOrder,
} from "../types";
import { TEN_FISH_FRY_EVENTS } from "./fishFryEvents";
import {
  US_DEMO_CASH_LEDGER,
  US_DEMO_NOTIFICATION_LOGS,
  US_DEMO_ORDERS,
} from "./usDemoTransactions";

export const LEGACY_DEFAULT_EVENT_DETAILS: EventDetails = {
  id: "evt-us-miami-festival-2026",
  organizerId: "org-us-caribbean-heritage",
  slug: "miami-waterfront-seafood-fest-2026",
  title: "Miami Waterfront Seafood & Cultural Cookout",
  tagline: "Authentic Caribbean snapper, live music, and multicultural food experiences by the bay",
  dateStr: "Saturday, November 28, 2026",
  timeStr: "1:00 PM – 9:00 PM EST",
  timezone: "America/New_York",
  venueName: "Biscayne Bay Waterfront Park Amphitheater",
  venueAddress: "301 Biscayne Blvd, Miami, FL 33132, United States",
  coordinates: "25.7743° N, 80.1878° W",
  description:
    "Celebrate a U.S.-wide multicultural food and culture experience in downtown Miami. Every ticket supports local community programming, neighborhood vendors, and cultural education initiatives across South Florida.",
  currency: "USD",
  organizerName: "Caribbean Heritage Cultural Foundation of South Florida",
  organizerPhone: "+1 (305) 555-0199",
  organizerWhatsApp: "+1 (305) 555-0199",
  organizerEmail: "info@caribbeanheritage-mia.example",
  advanceOrderDeadline: "Thursday, Nov 26, 2026 at 8:00 PM EST",
  isOrderingOpen: true,
  status: "published",
  pickupInstructions:
    "Pickup Tent Alpha, North Promenade, Biscayne Bay Waterfront Park. Show your digital ticket QR pass or reference code upon arrival.",
  packagingFeeUSD: 1.5,
  packagingFeeJMD: 0,
  flyerUrl: "https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=1200&q=80",
  flyerStoragePath: "tenants/org-us-caribbean-heritage/flyers/miami_cookout_hero.jpg",
  flyerMimeType: "image/jpeg",
  flyerSizeBytes: 1024 * 650,
  flyerDimensions: { width: 1200, height: 800 },
  isFlyerPublic: true,
  shareableUrl: "https://islandevents.app/e/miami-waterfront-seafood-fest-2026",
  socialSharingMeta: {
    ogTitle: "Miami Waterfront Seafood & Cultural Cookout | EventBite",
    ogDescription: "Multicultural food, music, and community culture in Miami, FL.",
    ogImage: "https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=1200&q=80",
  },
};

export const DEFAULT_EVENT_DETAILS: EventDetails = TEN_FISH_FRY_EVENTS[0];

export const LEGACY_DEFAULT_MENU: MenuItem[] = [
  {
    id: "item-escovitch-fish",
    name: "Escovitch Fish Meal",
    category: "Mains",
    pricingType: "customizable",
    description:
      "Crispy whole fried Jamaican snapper seasoned with sea salt and black pepper, dressed with spicy pickled scotch bonnet peppers, carrots, pimento, and onions.",
    priceUSD: 10,
    priceJMD: 1500,
    imageUrl: "/images/escovitch_fish.jpg",
    allowSpecialInstructions: true,
    availableStock: 180,
    customOptionGroups: [
      {
        id: "fish-size",
        title: "Fish Size",
        required: true,
        choices: [
          { label: "Standard / Medium Catch", extraPriceUSD: 0, extraPriceJMD: 0 },
          { label: "Large Snapper Catch (+J$300 / $2.00)", extraPriceUSD: 2, extraPriceJMD: 300 },
          { label: "Jumbo Snapper Catch (+J$600 / $4.00)", extraPriceUSD: 4, extraPriceJMD: 600 },
        ],
      },
      {
        id: "side-dish",
        title: "Choice of Side",
        required: true,
        choices: [
          { label: "2 Golden Festivals", extraPriceUSD: 0, extraPriceJMD: 0 },
          { label: "Fried Cassava Bammy (+J$100 / $0.75)", extraPriceUSD: 0.75, extraPriceJMD: 100 },
          {
            label: "Both (Festival & Bammy Combo) (+J$250 / $1.75)",
            extraPriceUSD: 1.75,
            extraPriceJMD: 250,
          },
        ],
      },
      {
        id: "drink-pairing",
        title: "Drink Pairing",
        required: true,
        choices: [
          { label: "Chilled Spring Water", extraPriceUSD: 0, extraPriceJMD: 0 },
          { label: "Ting / Jamaican Soda (+J$150 / $1.00)", extraPriceUSD: 1, extraPriceJMD: 150 },
          {
            label: "Fresh Sorrel Ginger / Natural Juice (+J$250 / $1.75)",
            extraPriceUSD: 1.75,
            extraPriceJMD: 250,
          },
        ],
      },
      {
        id: "extras",
        title: "Extras & Sauces",
        required: false,
        choices: [
          { label: "Standard escovitch pickle sauce", extraPriceUSD: 0, extraPriceJMD: 0 },
          {
            label: "Extra Hot Scotch Bonnet Pickle Jar (+J$100 / $0.75)",
            extraPriceUSD: 0.75,
            extraPriceJMD: 100,
          },
        ],
      },
    ],
  },
  {
    id: "item-fried-chicken",
    name: "Fried Chicken Meal",
    category: "Mains",
    pricingType: "fixed",
    description:
      "Deep golden Jamaican spiced fried chicken (2 pieces) served with 2 crispy festivals and fresh island pepper slaw. Fixed-price favorite.",
    priceUSD: 8,
    priceJMD: 1200,
    imageUrl: "/images/fried_chicken.jpg",
    allowSpecialInstructions: true,
    availableStock: 150,
  },
  {
    id: "item-festival-2pcs",
    name: "Festival (2 pieces)",
    category: "Sides & Extras",
    pricingType: "fixed",
    description:
      "Sweet, crunchy traditional Jamaican fried cornmeal dumplings. The ideal companion for fried fish and chicken.",
    priceUSD: 1.5,
    priceJMD: 200,
    imageUrl: "/images/escovitch_fish.jpg",
    availableStock: 400,
  },
  {
    id: "item-bammy",
    name: "Fried Bammy",
    category: "Sides & Extras",
    pricingType: "fixed",
    description:
      "Traditional Jamaican cassava flatbread wedges fried golden crisp on the exterior and tender inside.",
    priceUSD: 1.75,
    priceJMD: 250,
    imageUrl: "/images/bammy.jpg",
    availableStock: 250,
  },
  {
    id: "item-festival-drink",
    name: "Festival Drink / Island Rum Punch",
    category: "Beverages",
    pricingType: "fixed",
    description:
      "Refreshing chilled tropical drink pairing (choice of Ting, Kola Champagne, fresh Sorrel or Island Rum Punch).",
    priceUSD: 2,
    priceJMD: 300,
    imageUrl: "/images/rum_punch.jpg",
    availableStock: 500,
  },
  {
    id: "item-curry-goat",
    name: "Authentic Jamaican Curry Goat",
    category: "Food",
    pricingType: "customizable",
    description:
      "Tender goat meat slow-simmered in fragrant Jamaican turmeric curry, fresh thyme, garlic, scallion, and scotch bonnet peppers.",
    priceUSD: 14,
    priceJMD: 2100,
    imageUrl: "/images/caribbean_curry_goat.jpg",
    allowSpecialInstructions: true,
    availableStock: 95,
    customOptionGroups: [
      {
        id: "portion-choice",
        title: "Portion Choices",
        required: true,
        choices: [
          { label: "Standard Regular Bowl", extraPriceUSD: 0, extraPriceJMD: 0 },
          { label: "Big Yard Jumbo Portion (+J$450 / $3.00)", extraPriceUSD: 3, extraPriceJMD: 450 },
        ],
      },
      {
        id: "side-pairing",
        title: "Choice of Side",
        required: true,
        choices: [
          { label: "Traditional Rice & Peas", extraPriceUSD: 0, extraPriceJMD: 0 },
          { label: "Steamed White Jasmine Rice", extraPriceUSD: 0, extraPriceJMD: 0 },
          { label: "Fried Cassava Bammy (+J$150 / $1.00)", extraPriceUSD: 1, extraPriceJMD: 150 },
        ],
      },
      {
        id: "substitutions-addons",
        title: "Substitutions & Add-ons",
        required: false,
        choices: [
          { label: "Steamed Island Cabbage with Bell Peppers", extraPriceUSD: 0, extraPriceJMD: 0 },
          { label: "Extra Savory Curry Gravy (+J$100 / $0.75)", extraPriceUSD: 0.75, extraPriceJMD: 100 },
          { label: "Sweet Caramelized Fried Plantains (+J$200 / $1.50)", extraPriceUSD: 1.5, extraPriceJMD: 200 },
        ],
      },
    ],
  },
  {
    id: "item-jerk-chicken-platter",
    name: "Pimento Wood Jerk Chicken Platter",
    category: "Food",
    pricingType: "customizable",
    description:
      "Authentic pimento-smoked jerk chicken quarter infused with fiery island spices, served with sweet plantains and aromatic rice & peas.",
    priceUSD: 12,
    priceJMD: 1800,
    imageUrl: "/images/jerk_chicken.jpg",
    allowSpecialInstructions: true,
    availableStock: 120,
    customOptionGroups: [
      {
        id: "chicken-cut",
        title: "Portion Choices",
        required: true,
        choices: [
          { label: "Quarter Dark Meat (Leg & Thigh)", extraPriceUSD: 0, extraPriceJMD: 0 },
          { label: "Quarter White Meat (Breast & Wing) (+J$200 / $1.50)", extraPriceUSD: 1.5, extraPriceJMD: 200 },
          { label: "Half Chicken Feast (+J$650 / $4.50)", extraPriceUSD: 4.5, extraPriceJMD: 650 },
        ],
      },
      {
        id: "sides",
        title: "Choice of Sides",
        required: true,
        choices: [
          { label: "Rice and Peas & Sweet Festival", extraPriceUSD: 0, extraPriceJMD: 0 },
          { label: "Fried Bammy & Island Slaw (+J$150 / $1.00)", extraPriceUSD: 1, extraPriceJMD: 150 },
        ],
      },
      {
        id: "spice-level",
        title: "Jerk Pepper Level",
        required: false,
        choices: [
          { label: "Classic Mild Island Jerk", extraPriceUSD: 0, extraPriceJMD: 0 },
          { label: "Fiery Scotch Bonnet Jerk Glaze (+J$50 / $0.50)", extraPriceUSD: 0.5, extraPriceJMD: 50 },
        ],
      },
    ],
  },
  {
    id: "item-crispy-wings",
    name: "Spicy Honey Jerk Wings (8 pcs)",
    category: "Snacks",
    pricingType: "fixed",
    description:
      "Crispy double-cooked smoked chicken wings tossed in spicy honey scotch bonnet glaze, garnished with chopped scallions.",
    priceUSD: 8.5,
    priceJMD: 1300,
    imageUrl: "/images/crispy_island_wings.jpg",
    allowSpecialInstructions: true,
    availableStock: 80,
  },
  {
    id: "item-plantains-rice-bowl",
    name: "Sweet Fried Plantains & Rice Bowl",
    category: "Sides",
    pricingType: "fixed",
    description:
      "Golden caramelized sweet fried ripe plantains served with traditional coconut-infused rice and peas and steamed island cabbage.",
    priceUSD: 5.5,
    priceJMD: 850,
    imageUrl: "/images/fried_plantains_sides.jpg",
    availableStock: 140,
  },
  {
    id: "item-tropical-smoothie",
    name: "Fresh Mango Passionfruit Smoothie",
    category: "Drinks",
    pricingType: "fixed",
    description:
      "Freshly blended ripe Jamaican mango, passionfruit, ginger, and lime, served icy cold with fresh mint.",
    priceUSD: 4.5,
    priceJMD: 700,
    imageUrl: "/images/tropical_island_smoothie.jpg",
    availableStock: 190,
  },
  {
    id: "item-rum-cake",
    name: "Island Spiced Dark Rum Cake",
    category: "Desserts",
    pricingType: "fixed",
    description:
      "Rich and moist authentic Caribbean dark rum cake soaked with aged rum, island spices, molasses, and vanilla glaze.",
    priceUSD: 5,
    priceJMD: 750,
    imageUrl: "/images/escovitch_fish.jpg",
    availableStock: 65,
  },
  {
    id: "item-community-donation",
    name: "Kingston Youth Center Fundraiser Donation",
    category: "Community Donations",
    pricingType: "custom_amount",
    description:
      "Give directly to support after-school computer labs, sports equipment, and school supplies for downtown Kingston youth. Enter any amount within range.",
    priceUSD: 10,
    priceJMD: 1500,
    imageUrl: "/images/oxtail.jpg",
    customAmountConfig: {
      minUSD: 5,
      maxUSD: 350,
      minJMD: 500,
      maxJMD: 50000,
      suggestedAmountsUSD: [5, 10, 25, 50, 100],
      suggestedAmountsJMD: [500, 1000, 2500, 5000, 10000],
    },
  },
];

export const DEFAULT_MENU: MenuItem[] = TEN_FISH_FRY_EVENTS[0].menu;

export const DEFAULT_SETTINGS: Settings = {
  businessLocation: "US",
  currency: "USD",
  ticketPriceUSD: 15,
  ticketPriceJMD: 0,
  methods: {
    bank: {
      enabled: true,
      eligible: true,
      details:
        "DEMO ONLY: Example U.S. ACH instructions are not a real payment account. Configure verified payout details before accepting transfers.",
    },
    zelle: {
      enabled: true,
      eligible: true,
      details: "DEMO ONLY: Configure a verified U.S. Zelle recipient before accepting transfers.",
    },
    cashapp: {
      enabled: true,
      eligible: true,
      details: "DEMO ONLY: Configure a verified Cash App recipient before accepting transfers.",
    },
    cash: {
      enabled: true,
      eligible: true,
      details:
        "On-site Box Office & Food Pickup Tent. Cash receipts issued immediately upon authorized collection.",
    },
    card: {
      enabled: false,
      eligible: true,
      details:
        "Direct credit/debit card payment (Visa, Mastercard, Amex). US payment processor ready.",
    },
  },
};

export const LEGACY_DEFAULT_CASH_LEDGER: CashPaymentRecord[] = [
  {
    id: "cash-rec-1",
    transactionType: "receipt",
    receiptNumber: "CSH-2026-1024",
    customerName: "Chronixx Jam",
    customerPhone: "+1 (876) 555-0812",
    orderNumber: "ORD-1024",
    ticketCode: "SFF-2026-1024",
    amount: 3000,
    currency: "JMD",
    receivedAt: new Date(Date.now() - 3600000 * 24).toISOString(),
    receivedBy: "Treasurer Davis",
    notes: "Walk-in cash payment at Youth Center office. Issued physical receipt copy.",
  },
  {
    id: "cash-rec-2",
    transactionType: "receipt",
    receiptNumber: "CSH-2026-1025",
    customerName: "Beris Hammond",
    customerPhone: "+1 (876) 555-0941",
    orderNumber: "ORD-9912",
    ticketCode: "SFF-2026-9912",
    amount: 1500,
    currency: "JMD",
    receivedAt: new Date(Date.now() - 3600000 * 12).toISOString(),
    receivedBy: "Pastor Brown",
    notes: "Cash payment for 1 Escovitch meal in advance.",
  },
];

export const DEFAULT_CASH_LEDGER: CashPaymentRecord[] = US_DEMO_CASH_LEDGER;

export const LEGACY_SEED_ORDERS: TicketOrder[] = [
  {
    id: "ord-fishfry-1",
    ticketCode: "SFF-2026-8492",
    orderNumber: "ORD-8492",
    customerName: "Damian Marley",
    customerPhone: "+1 (876) 555-0142",
    email: "damian@reggae.example",
    method: "bank",
    subtotal: 3500,
    packagingFee: 100,
    totalAmount: 3600,
    amountPaid: 3600,
    currency: "JMD",
    status: "paid",
    fulfillmentStatus: "ready_for_pickup",
    paymentReference: "NCB-TRANSFER-88492",
    senderName: "Damian Marley",
    verifiedAt: new Date(Date.now() - 3600000 * 3).toISOString(),
    verifiedBy: "Treasurer Davis",
    verificationNotes: "Matched on NCB Online Banking statement batch #4812",
    createdAt: new Date(Date.now() - 3600000 * 4).toISOString(),
    secureTicketToken: "tok-sff-8492",
    allHandedOver: false,
    items: [
      {
        menuItemId: "item-escovitch-fish",
        name: "Escovitch Fish Meal",
        pricingType: "customizable",
        basePrice: 1500,
        selectedOptionsSummary: [
          "Fish Size: Large Snapper Catch (+J$300)",
          "Choice of Side: Both (Festival & Bammy Combo) (+J$250)",
          "Drink Pairing: Ting / Jamaican Soda (+J$150)",
        ],
        optionsTotalExtra: 700,
        finalItemPrice: 2200,
        currency: "JMD",
        quantity: 1,
        specialInstructions: "Extra crispy fish, lime on the side please",
        handoverStatus: "collected",
        handedOverAt: new Date(Date.now() - 1800000).toISOString(),
      },
      {
        menuItemId: "item-fried-chicken",
        name: "Fried Chicken Meal",
        pricingType: "fixed",
        basePrice: 1200,
        selectedOptionsSummary: [],
        optionsTotalExtra: 0,
        finalItemPrice: 1200,
        currency: "JMD",
        quantity: 1,
        handoverStatus: "pending",
      },
      {
        menuItemId: "item-festival-drink",
        name: "Festival Drink",
        pricingType: "fixed",
        basePrice: 300,
        selectedOptionsSummary: [],
        optionsTotalExtra: 0,
        finalItemPrice: 300,
        currency: "JMD",
        quantity: 1,
        handoverStatus: "pending",
      },
    ],
  },
  {
    id: "ord-fishfry-2",
    ticketCode: "SFF-2026-3921",
    orderNumber: "ORD-3921",
    customerName: "Shelly-Ann Fraser",
    customerPhone: "+1 (876) 555-0199",
    email: "pocketrocket@speed.example",
    method: "bank",
    subtotal: 4800,
    packagingFee: 100,
    totalAmount: 4900,
    currency: "JMD",
    status: "payment_submitted",
    fulfillmentStatus: "unprepared",
    paymentReference: "NCB-MOBILE-92144",
    senderName: "Shelly-Ann Fraser-Pryce",
    createdAt: new Date(Date.now() - 3600000 * 2).toISOString(),
    secureTicketToken: "tok-sff-3921",
    allHandedOver: false,
    items: [
      {
        menuItemId: "item-escovitch-fish",
        name: "Escovitch Fish Meal",
        pricingType: "customizable",
        basePrice: 1500,
        selectedOptionsSummary: [
          "Fish Size: Standard / Medium Catch (+J$0)",
          "Choice of Side: 2 Golden Festivals (+J$0)",
          "Drink Pairing: Fresh Sorrel Ginger / Natural Juice (+J$250)",
        ],
        optionsTotalExtra: 250,
        finalItemPrice: 1750,
        currency: "JMD",
        quantity: 2,
        handoverStatus: "pending",
      },
      {
        menuItemId: "item-fried-chicken",
        name: "Fried Chicken Meal",
        pricingType: "fixed",
        basePrice: 1200,
        selectedOptionsSummary: [],
        optionsTotalExtra: 0,
        finalItemPrice: 1200,
        currency: "JMD",
        quantity: 1,
        handoverStatus: "pending",
      },
    ],
  },
  {
    id: "ord-fishfry-3",
    ticketCode: "SFF-2026-5512",
    orderNumber: "ORD-5512",
    customerName: "Buju Banton",
    customerPhone: "+1 (876) 555-0348",
    email: "gargamel@music.example",
    method: "bank",
    subtotal: 3900,
    packagingFee: 100,
    totalAmount: 4000,
    amountPaid: 2000,
    currency: "JMD",
    status: "partially_paid",
    fulfillmentStatus: "preparing",
    paymentReference: "NCB-TX-5512",
    senderName: "Mark Myrie",
    verifiedAt: new Date(Date.now() - 3600000 * 5).toISOString(),
    verifiedBy: "Treasurer Davis",
    verificationNotes: "Partial deposit of J$2,000 received. Balance of J$2,000 due in cash at pickup.",
    createdAt: new Date(Date.now() - 3600000 * 6).toISOString(),
    secureTicketToken: "tok-sff-5512",
    allHandedOver: false,
    items: [
      {
        menuItemId: "item-escovitch-fish",
        name: "Escovitch Fish Meal",
        pricingType: "customizable",
        basePrice: 1500,
        selectedOptionsSummary: [
          "Fish Size: Jumbo Snapper Catch (+J$600)",
          "Choice of Side: Both (Festival & Bammy Combo) (+J$250)",
          "Drink Pairing: Chilled Spring Water (+J$0)",
        ],
        optionsTotalExtra: 850,
        finalItemPrice: 2350,
        currency: "JMD",
        quantity: 1,
        handoverStatus: "pending",
      },
      {
        menuItemId: "item-fried-bammy",
        name: "Fried Bammy (3 slices)",
        pricingType: "fixed",
        basePrice: 250,
        selectedOptionsSummary: [],
        optionsTotalExtra: 0,
        finalItemPrice: 250,
        currency: "JMD",
        quantity: 1,
        handoverStatus: "pending",
      },
      {
        menuItemId: "item-fried-chicken",
        name: "Fried Chicken Meal",
        pricingType: "fixed",
        basePrice: 1200,
        selectedOptionsSummary: [],
        optionsTotalExtra: 0,
        finalItemPrice: 1200,
        currency: "JMD",
        quantity: 1,
        handoverStatus: "pending",
      },
    ],
  },
  {
    id: "ord-fishfry-4",
    ticketCode: "SFF-2026-7781",
    orderNumber: "ORD-7781",
    customerName: "Koffee Jam",
    customerPhone: "+1 (876) 555-0622",
    email: "toast@grammy.example",
    method: "cash",
    subtotal: 1500,
    packagingFee: 100,
    totalAmount: 1600,
    amountPaid: 0,
    currency: "JMD",
    status: "awaiting_payment",
    fulfillmentStatus: "unprepared",
    authorizedUnpaid: true,
    notes: "Ordered via WhatsApp with Organizer. Authorized to reserve meals; paying cash in person at Collection Tent B.",
    createdAt: new Date(Date.now() - 3600000 * 1).toISOString(),
    secureTicketToken: "tok-sff-7781",
    allHandedOver: false,
    items: [
      {
        menuItemId: "item-escovitch-fish",
        name: "Escovitch Fish Meal",
        pricingType: "customizable",
        basePrice: 1500,
        selectedOptionsSummary: [
          "Fish Size: Standard / Medium Catch (+J$0)",
          "Choice of Side: 2 Golden Festivals (+J$0)",
          "Drink Pairing: Chilled Spring Water (+J$0)",
        ],
        optionsTotalExtra: 0,
        finalItemPrice: 1500,
        currency: "JMD",
        quantity: 1,
        handoverStatus: "pending",
      },
    ],
  },
  {
    id: "ord-fishfry-prompt-1024",
    ticketCode: "FF-1024",
    orderNumber: "FF-1024",
    customerName: "Miss Lou Bennett",
    customerPhone: "+1 (876) 555-0120",
    email: "misslou@heritage.example",
    method: "bank",
    subtotal: 4400,
    packagingFee: 100,
    totalAmount: 4500,
    amountPaid: 4500,
    currency: "JMD",
    status: "paid",
    fulfillmentStatus: "preparing",
    paymentReference: "NCB-MOBILE-1024",
    senderName: "Louise Bennett-Coverley",
    verifiedAt: new Date(Date.now() - 3600000 * 3).toISOString(),
    verifiedBy: "Treasurer Davis",
    verificationNotes: "Paid in full via NCB Mobile Banking. Matched statement #1024.",
    createdAt: new Date(Date.now() - 3600000 * 4).toISOString(),
    secureTicketToken: "tok-ff-1024",
    allHandedOver: false,
    items: [
      {
        menuItemId: "item-escovitch-fish",
        name: "Escovitch fish meal",
        category: "Mains",
        pricingType: "customizable",
        basePrice: 1500,
        selectedOptionsSummary: [
          "Fish Size: Standard Snapper (+J$0)",
          "Choice of Side: Golden Festival (+J$0)",
        ],
        optionsTotalExtra: 0,
        finalItemPrice: 1500,
        currency: "JMD",
        quantity: 2,
        collectedQuantity: 0, // 2 ordered, 0 collected, 2 remaining
        handoverStatus: "pending",
      },
      {
        menuItemId: "item-festival",
        name: "Festival",
        category: "Sides",
        pricingType: "fixed",
        basePrice: 200,
        selectedOptionsSummary: ["2 pieces"],
        optionsTotalExtra: 0,
        finalItemPrice: 200,
        currency: "JMD",
        quantity: 4,
        collectedQuantity: 2, // 4 ordered, 2 collected, 2 remaining
        handoverStatus: "pending",
        handedOverAt: new Date(Date.now() - 1800000).toISOString(),
        handedOverBy: "Sister Paulette",
        stationHandedOver: "Sides Station",
      },
      {
        menuItemId: "item-soda",
        name: "Soda",
        category: "Beverages",
        pricingType: "fixed",
        basePrice: 300,
        selectedOptionsSummary: ["Chilled Ting bottle"],
        optionsTotalExtra: 0,
        finalItemPrice: 300,
        currency: "JMD",
        quantity: 2,
        collectedQuantity: 0, // 2 ordered, 0 collected, 2 remaining
        handoverStatus: "pending",
      },
    ],
  },
  {
    id: "ord-fishfry-redeemed",
    ticketCode: "SFF-2026-9999",
    orderNumber: "ORD-9999",
    customerName: "Sean Paul",
    customerPhone: "+1 (876) 555-0999",
    email: "duttyrock@music.example",
    method: "bank",
    subtotal: 3000,
    packagingFee: 100,
    totalAmount: 3100,
    amountPaid: 3100,
    currency: "JMD",
    status: "paid",
    fulfillmentStatus: "collected",
    paymentReference: "SCOTIA-9999",
    verifiedAt: new Date(Date.now() - 3600000 * 5).toISOString(),
    verifiedBy: "Treasurer Davis",
    createdAt: new Date(Date.now() - 3600000 * 6).toISOString(),
    closedAt: new Date(Date.now() - 3600000 * 1).toISOString(),
    secureTicketToken: "tok-sff-9999",
    allHandedOver: true,
    items: [
      {
        menuItemId: "item-escovitch-fish",
        name: "Escovitch Fish Meal",
        category: "Mains",
        pricingType: "fixed",
        basePrice: 1500,
        selectedOptionsSummary: [],
        optionsTotalExtra: 0,
        finalItemPrice: 1500,
        currency: "JMD",
        quantity: 2,
        collectedQuantity: 2,
        handoverStatus: "collected",
        handedOverAt: new Date(Date.now() - 3600000 * 1).toISOString(),
        handedOverBy: "Worker Jerome",
        stationHandedOver: "Fish & Chicken Station",
      },
    ],
  },
  {
    id: "ord-fishfry-cancelled",
    ticketCode: "SFF-2026-0044",
    orderNumber: "ORD-0044",
    customerName: "Ziggy Marley",
    customerPhone: "+1 (876) 555-0044",
    email: "ziggy@melody.example",
    method: "bank",
    subtotal: 1500,
    packagingFee: 100,
    totalAmount: 1600,
    currency: "JMD",
    status: "refunded",
    fulfillmentStatus: "unprepared",
    refundDetails: {
      amount: 1600,
      reason: "Customer travel conflict — refund authorized by supervisor",
      refundedAt: new Date(Date.now() - 3600000 * 3).toISOString(),
      refundedBy: "Supervisor Davis",
    },
    createdAt: new Date(Date.now() - 3600000 * 8).toISOString(),
    secureTicketToken: "tok-sff-0044",
    allHandedOver: false,
    items: [
      {
        menuItemId: "item-escovitch-fish",
        name: "Escovitch Fish Meal",
        category: "Mains",
        pricingType: "fixed",
        basePrice: 1500,
        selectedOptionsSummary: [],
        optionsTotalExtra: 0,
        finalItemPrice: 1500,
        currency: "JMD",
        quantity: 1,
        collectedQuantity: 0,
        handoverStatus: "pending",
      },
    ],
  },
  {
    id: "ord-akeem-1",
    ticketCode: "SFF-2026-9011",
    orderNumber: "ORD-2026-9011",
    customerName: "Akeem Campbell",
    customerPhone: "+1 (876) 555-9011",
    email: "akeem.c@islandevents.example",
    method: "card",
    subtotal: 90,
    packagingFee: 3,
    totalAmount: 93,
    amountPaid: 93,
    currency: "USD",
    status: "paid",
    fulfillmentStatus: "ready_for_pickup",
    paymentReference: "STRIPE-CH-9011",
    verifiedAt: new Date(Date.now() - 3600000 * 24 * 2).toISOString(),
    verifiedBy: "EventBite Gateway",
    createdAt: new Date(Date.now() - 3600000 * 24 * 3).toISOString(),
    secureTicketToken: "tok-secure-akeem-9011",
    allHandedOver: false,
    eventId: "evt-ocho-rios-reggae",
    eventTitle: "Island Reggae & Sound Festival 2026",
    venueName: "Ocho Rios Oceanfront Ampitheater",
    venueLocation: "Main Street, Ocho Rios, St. Ann, Jamaica",
    eventDateStr: "Saturday, May 16, 2026",
    eventTimeStr: "4:00 PM – 11:30 PM EDT",
    eventThumbnail: "/images/island_music_reggae_concert.jpg",
    items: [
      {
        menuItemId: "tier-vip",
        name: "VIP Oceanfront Lounge Pass",
        pricingType: "fixed",
        basePrice: 45,
        selectedOptionsSummary: [
          "Express VIP entrance lane",
          "Elevated oceanfront viewing lounge",
          "Complimentary island rum punch voucher",
        ],
        optionsTotalExtra: 0,
        finalItemPrice: 45,
        currency: "USD",
        quantity: 2,
        handoverStatus: "pending",
      },
    ],
  },
  {
    id: "ord-akeem-2",
    ticketCode: "SFF-2026-9012",
    orderNumber: "ORD-2026-9012",
    customerName: "Akeem Campbell",
    customerPhone: "+1 (876) 555-9011",
    email: "akeem.c@islandevents.example",
    method: "zelle",
    subtotal: 45,
    packagingFee: 1.5,
    totalAmount: 46.5,
    currency: "USD",
    status: "payment_submitted",
    fulfillmentStatus: "unprepared",
    paymentReference: "ZELLE-CONF-9012",
    senderName: "Akeem Campbell",
    createdAt: new Date(Date.now() - 3600000 * 4).toISOString(),
    secureTicketToken: "tok-secure-akeem-9012",
    allHandedOver: false,
    eventId: "evt-hellshire-sunset-fry",
    eventTitle: "Hellshire Beach Sunset Fish Fry & Lobster Bake",
    venueName: "Fisherman's Corner, Hellshire Beach",
    venueLocation: "Hellshire Beach Road, Portmore, St. Catherine",
    eventDateStr: "Saturday, November 28, 2026",
    eventTimeStr: "1:00 PM – 9:00 PM",
    eventThumbnail: "/images/island_festival_sunset.jpg",
    items: [
      {
        menuItemId: "tier-ga",
        name: "General Admission Pass",
        pricingType: "fixed",
        basePrice: 45,
        selectedOptionsSummary: ["Beachside boardwalk access", "Live acoustic reggae set"],
        optionsTotalExtra: 0,
        finalItemPrice: 45,
        currency: "USD",
        quantity: 1,
        handoverStatus: "pending",
      },
    ],
  },
  {
    id: "ord-akeem-3",
    ticketCode: "SFF-2026-8802",
    orderNumber: "ORD-2026-8802",
    customerName: "Akeem Campbell",
    customerPhone: "+1 (876) 555-9011",
    email: "akeem.c@islandevents.example",
    method: "card",
    subtotal: 30,
    packagingFee: 1.5,
    totalAmount: 31.5,
    amountPaid: 31.5,
    currency: "USD",
    status: "paid",
    fulfillmentStatus: "collected",
    paymentReference: "CARD-AUTH-8802",
    verifiedAt: "2026-04-12T16:00:00Z",
    verifiedBy: "EventBite Terminal",
    createdAt: "2026-04-10T11:00:00Z",
    closedAt: "2026-04-12T23:00:00Z",
    secureTicketToken: "tok-secure-akeem-8802",
    allHandedOver: true,
    eventId: "evt-port-royal-heritage-fry",
    eventTitle: "Port Royal Heritage Seafood & Conch Feast",
    venueName: "Historic Naval Dockyard & Fort Charles",
    venueLocation: "Queen Street, Port Royal, Kingston Harbour",
    eventDateStr: "Sunday, October 11, 2026",
    eventTimeStr: "11:30 AM – 7:30 PM",
    eventThumbnail: "/images/island_culture_carnival.jpg",
    items: [
      {
        menuItemId: "tier-ga",
        name: "General Admission Pass",
        pricingType: "fixed",
        basePrice: 15,
        selectedOptionsSummary: ["Historic naval dockyard access", "Conch soup tasting pass"],
        optionsTotalExtra: 0,
        finalItemPrice: 15,
        currency: "USD",
        quantity: 2,
        collectedQuantity: 2,
        handoverStatus: "collected",
        handedOverAt: "2026-04-12T18:30:00Z",
        handedOverBy: "Staff Member",
        stationHandedOver: "Main Gate Gatehouse",
      },
    ],
  },
  {
    id: "ord-akeem-4",
    ticketCode: "SFF-2026-9014",
    orderNumber: "ORD-2026-9014",
    customerName: "Akeem Campbell",
    customerPhone: "+1 (876) 555-9011",
    email: "akeem.c@islandevents.example",
    method: "card",
    subtotal: 45,
    packagingFee: 1.5,
    totalAmount: 46.5,
    amountPaid: 46.5,
    currency: "USD",
    status: "paid",
    fulfillmentStatus: "ready_for_pickup",
    paymentReference: "CARD-AUTH-9014",
    verifiedAt: "2026-05-02T11:00:00Z",
    verifiedBy: "EventBite Gateway",
    createdAt: "2026-05-01T10:00:00Z",
    secureTicketToken: "tok-secure-akeem-9014",
    allHandedOver: false,
    isTransferred: true,
    transferredTo: "Marcus Campbell (marcus.c@islandevents.example)",
    transferredAt: "2026-05-04T15:20:00Z",
    notes: "Official ticket transfer completed. QR access badge regenerated for Marcus Campbell.",
    eventId: "evt-kingston-harbour-fry",
    eventTitle: "Kingston Harbour Waterfront Sunday Fish Fry",
    venueName: "Kingston Waterfront Pier (Victoria Pier)",
    venueLocation: "Ocean Boulevard, Downtown Kingston, Jamaica",
    eventDateStr: "Sunday, November 22, 2026",
    eventTimeStr: "12:00 PM – 8:00 PM",
    eventThumbnail: "/images/escovitch_fish.jpg",
    items: [
      {
        menuItemId: "tier-ga",
        name: "General Admission Pass",
        pricingType: "fixed",
        basePrice: 45,
        selectedOptionsSummary: ["Waterfront pier access", "Digital QR entry pass"],
        optionsTotalExtra: 0,
        finalItemPrice: 45,
        currency: "USD",
        quantity: 1,
        handoverStatus: "pending",
      },
    ],
  },
];

export const NOTIFICATION_TEMPLATES: NotificationTemplateDefinition[] = [
  {
    id: "tmpl-order-received",
    trigger: "order_received",
    name: "Order Received",
    stageNumber: 1,
    description: "Dispatched immediately upon checkout. Confirms order items, calculated total, and reserves portions without marking payment complete.",
    defaultEmailSubject: "Order Received: #{orderNumber} - {eventName}",
    defaultEmailHtml: `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif; background: #0c1220; color: #f1f5f9; margin: 0; padding: 24px; }
    .email-card { max-width: 580px; margin: 0 auto; background: #131d33; border: 1px solid #1e2e4f; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .email-header { background: linear-gradient(135deg, #0d7a57 0%, #064e3b 100%); padding: 24px; text-align: center; border-bottom: 3px solid #f59e0b; }
    .email-header h1 { margin: 0 0 6px 0; font-size: 20px; color: #ffffff; letter-spacing: 0.5px; }
    .email-header p { margin: 0; font-size: 13px; color: #a7f3d0; font-weight: 500; }
    .email-body { padding: 28px 24px; font-size: 14px; line-height: 1.6; color: #cbd5e1; }
    .order-box { background: #1a2744; border: 1px solid #273b64; border-radius: 10px; padding: 18px; margin: 20px 0; }
    .order-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
    .order-row strong { color: #f8fafc; }
    .total-row { border-top: 1px dashed #334e80; padding-top: 10px; margin-top: 10px; font-size: 16px; font-weight: 700; color: #10b981; }
    .status-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); }
    .cta-button { display: block; text-align: center; background: #10b981; color: #042f2e; text-decoration: none; font-weight: 700; padding: 14px 24px; border-radius: 8px; margin: 24px 0 16px 0; }
    .email-footer { background: #0e172a; padding: 18px 24px; font-size: 11px; color: #64748b; text-align: center; border-top: 1px solid #1e293b; }
  </style>
</head>
<body>
  <div class="email-card">
    <div class="email-header">
      <h1>{eventName}</h1>
      <p>Order Placed Successfully</p>
    </div>
    <div class="email-body">
      <p>Hello <strong>{customerName}</strong>,</p>
      <p>We have successfully received your order for <strong>{eventName}</strong>. Your meal portions have been held in reserve.</p>
      
      <div class="order-box">
        <div class="order-row"><span>Order Number:</span><strong>#{orderNumber}</strong></div>
        <div class="order-row"><span>Date Placed:</span><strong>{orderDate}</strong></div>
        <div class="order-row"><span>Payment Method:</span><strong>{paymentMethod}</strong></div>
        <div class="order-row"><span>Payment Status:</span><span class="status-badge">{paymentStatus}</span></div>
        <div class="order-row total-row"><span>Total Amount:</span><strong>{totalAmount}</strong></div>
      </div>

      <p style="font-size: 12px; color: #94a3b8;">
        <strong>Next Steps:</strong> If you selected a manual transfer (Zelle, Cash App, or Jamaican Bank Transfer), please view the payment instructions to complete and verify your transaction.
      </p>

      <a href="{trackingUrl}" class="cta-button">View Order & Payment Instructions &rarr;</a>
    </div>
    <div class="email-footer">
      Sent by {organizerName} via EventBite Notification Engine.<br>
      Automated system message. Invariant: Order receipt is not proof of payment.
    </div>
  </div>
</body>
</html>`,
    defaultSms: "Order #{orderNumber} received for {eventName}! Total: {totalAmount}. View order details & payment steps: {trackingUrl}",
    supportedChannels: ["email", "sms", "whatsapp"],
    availableVariables: ["{customerName}", "{orderNumber}", "{eventName}", "{totalAmount}", "{paymentMethod}", "{paymentStatus}", "{orderDate}", "{trackingUrl}", "{organizerName}"],
    sampleVariables: {
      customerName: "Damian Marley",
      orderNumber: "ORD-8492",
      eventName: "Sunday Fish Fry — Community Fundraiser",
      totalAmount: "J$3,600",
      paymentMethod: "Bank Transfer (NCB)",
      paymentStatus: "Awaiting Transfer",
      orderDate: "October 25, 2026",
      trackingUrl: "https://islandevents.app/orders/ORD-8492",
      organizerName: "Kingston Youth Foundation",
    },
    laravelNotificationClass: "App\\Notifications\\OrderReceivedNotification",
    providerGuidelines: {
      senderIdRule: "Jamaica: Alphanumeric 'ISLANDEVNT' registered via Digicel & Flow. US: TCR 10DLC campaign registration required.",
      coverage: "Direct SMS delivery to Jamaica (+1-876 Digicel & Flow), US (+1), UK (+44), CA (+1). Email universally available.",
      pricingEstimate: "Email: $0.001/message. SMS Jamaica: ~$0.058 - $0.075/segment. SMS US: ~$0.0079/segment.",
      deliveryCallbackRequired: true,
    },
  },
  {
    id: "tmpl-payment-instructions",
    trigger: "payment_instructions",
    name: "Payment Instructions",
    stageNumber: 2,
    description: "Sends precise organizer transfer accounts (Zelle, Cash App, NCB/Scotiabank) with a strict reminder that transfer submission is not verified payment.",
    defaultEmailSubject: "Payment Instructions: Order #{orderNumber} ({paymentMethod})",
    defaultEmailHtml: `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif; background: #0c1220; color: #f1f5f9; margin: 0; padding: 24px; }
    .email-card { max-width: 580px; margin: 0 auto; background: #131d33; border: 1px solid #1e2e4f; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .email-header { background: linear-gradient(135deg, #1e3a8a 0%, #172554 100%); padding: 24px; text-align: center; border-bottom: 3px solid #38bdf8; }
    .email-header h1 { margin: 0 0 6px 0; font-size: 20px; color: #ffffff; }
    .email-body { padding: 28px 24px; font-size: 14px; line-height: 1.6; color: #cbd5e1; }
    .instructions-box { background: #1e293b; border-left: 4px solid #38bdf8; padding: 16px; border-radius: 6px; margin: 18px 0; }
    .account-details { font-family: monospace; font-size: 13px; color: #38bdf8; background: #0f172a; padding: 12px; border-radius: 6px; margin-top: 10px; border: 1px solid #1e293b; }
    .security-notice { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 14px; margin: 18px 0; font-size: 12px; color: #fca5a5; }
    .cta-button { display: block; text-align: center; background: #38bdf8; color: #082f49; text-decoration: none; font-weight: 700; padding: 14px 24px; border-radius: 8px; margin: 24px 0 16px 0; }
    .email-footer { background: #0e172a; padding: 18px 24px; font-size: 11px; color: #64748b; text-align: center; border-top: 1px solid #1e293b; }
  </style>
</head>
<body>
  <div class="email-card">
    <div class="email-header">
      <h1>Payment Instructions</h1>
      <p style="margin:0; color:#93c5fd; font-size:13px;">Order #{orderNumber} &bull; {paymentMethod}</p>
    </div>
    <div class="email-body">
      <p>Hello <strong>{customerName}</strong>,</p>
      <p>Please follow the organizer payment instructions below to complete your order for <strong>{eventName}</strong>:</p>
      
      <div class="instructions-box">
        <strong>Amount to Send:</strong> <span style="font-size:16px; color:#38bdf8; font-weight:700;">{totalAmount}</span><br>
        <strong>Selected Method:</strong> {paymentMethod}<br>
        <div class="account-details">{accountDetails}</div>
      </div>

      <div class="security-notice">
        <strong>Important Rule:</strong> Recording a transfer submission does NOT mean payment has succeeded. Your digital ticket QR code will only be activated after the organizer verifies receipt on their bank or wallet statement.
      </div>

      <a href="{submissionUrl}" class="cta-button">Submit Transfer Reference or Screenshot &rarr;</a>
    </div>
    <div class="email-footer">
      Questions? Contact organizer at {organizerPhone} &bull; EventBite Verification Hub
    </div>
  </div>
</body>
</html>`,
    defaultSms: "Payment instructions for Order #{orderNumber}: Send {totalAmount} via {paymentMethod} to {accountHandle}. Submit reference to activate ticket: {submissionUrl}",
    supportedChannels: ["email", "sms", "whatsapp"],
    availableVariables: ["{customerName}", "{orderNumber}", "{eventName}", "{totalAmount}", "{paymentMethod}", "{accountDetails}", "{accountHandle}", "{submissionUrl}", "{organizerPhone}"],
    sampleVariables: {
      customerName: "Shelly-Ann Fraser",
      orderNumber: "ORD-3921",
      eventName: "Sunday Fish Fry — Community Fundraiser",
      totalAmount: "J$5,400",
      paymentMethod: "National Commercial Bank (NCB)",
      accountDetails: "NCB Current A/C #351-928-114 | Branch: Oxford Road | Name: Kingston Youth Foundation",
      accountHandle: "NCB A/C 351-928-114",
      submissionUrl: "https://islandevents.app/orders/ORD-3921/submit-transfer",
      organizerPhone: "+1 (876) 926-3849",
    },
    laravelNotificationClass: "App\\Notifications\\PaymentInstructionsNotification",
    providerGuidelines: {
      senderIdRule: "Requires verified sender profile so bank account details are not filtered by carrier anti-phishing algorithms.",
      coverage: "Email recommended for full bank/routing numbers; SMS provides short handle and direct link.",
      pricingEstimate: "Standard SMS segment. Email zero cost overhead.",
      deliveryCallbackRequired: true,
    },
  },
  {
    id: "tmpl-transfer-submitted",
    trigger: "transfer_submitted",
    name: "Transfer Submitted",
    stageNumber: 3,
    description: "Confirms payment details were received, explicitly warning the customer that payment has NOT yet succeeded until verified.",
    defaultEmailSubject: "Transfer Submitted: Order #{orderNumber} (Pending Verification)",
    defaultEmailHtml: `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif; background: #0c1220; color: #f1f5f9; margin: 0; padding: 24px; }
    .email-card { max-width: 580px; margin: 0 auto; background: #131d33; border: 1px solid #1e2e4f; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .email-header { background: linear-gradient(135deg, #b45309 0%, #78350f 100%); padding: 24px; text-align: center; border-bottom: 3px solid #f59e0b; }
    .email-header h1 { margin: 0 0 6px 0; font-size: 20px; color: #ffffff; }
    .email-body { padding: 28px 24px; font-size: 14px; line-height: 1.6; color: #cbd5e1; }
    .warning-banner { background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 8px; padding: 16px; margin: 20px 0; color: #fde68a; font-size: 13px; }
    .ref-box { background: #1a2744; border: 1px solid #273b64; border-radius: 8px; padding: 14px; margin: 16px 0; font-family: monospace; color: #93c5fd; }
    .cta-button { display: block; text-align: center; background: #f59e0b; color: #451a03; text-decoration: none; font-weight: 700; padding: 14px 24px; border-radius: 8px; margin: 24px 0 16px 0; }
    .email-footer { background: #0e172a; padding: 18px 24px; font-size: 11px; color: #64748b; text-align: center; border-top: 1px solid #1e293b; }
  </style>
</head>
<body>
  <div class="email-card">
    <div class="email-header">
      <h1>Transfer Details Received</h1>
      <p style="margin:0; color:#fde68a; font-size:13px;">Pending Organizer Verification</p>
    </div>
    <div class="email-body">
      <p>Hello <strong>{customerName}</strong>,</p>
      <p>We have logged your payment submission for <strong>Order #{orderNumber}</strong>.</p>
      
      <div class="ref-box">
        <strong>Submitted Reference:</strong> {paymentReference}<br>
        <strong>Sender Account:</strong> {senderName}<br>
        <strong>Amount Claimed:</strong> {totalAmount}
      </div>

      <div class="warning-banner">
        <strong>Verification In Progress:</strong> This confirmation proves your details were received into our review queue, but <strong>does not mean payment has succeeded</strong>. The event organizer will cross-reference their bank statement before issuing your active ticket.
      </div>

      <a href="{trackingUrl}" class="cta-button">Check Live Verification Status &rarr;</a>
    </div>
    <div class="email-footer">
      EventBite &bull; Anti-Fraud Ledger Protocol &bull; Zero Unauthorized Admissions
    </div>
  </div>
</body>
</html>`,
    defaultSms: "Order #{orderNumber}: Payment ref {paymentReference} submitted. Note: Funds are NOT yet verified. Ticket activates once organizer confirms receipt: {trackingUrl}",
    supportedChannels: ["email", "sms", "whatsapp"],
    availableVariables: ["{customerName}", "{orderNumber}", "{paymentReference}", "{senderName}", "{totalAmount}", "{trackingUrl}"],
    sampleVariables: {
      customerName: "Shelly-Ann Fraser",
      orderNumber: "ORD-3921",
      paymentReference: "NCB-TXN-88291",
      senderName: "Shelly-Ann Fraser-Pryce",
      totalAmount: "J$5,400",
      trackingUrl: "https://islandevents.app/orders/ORD-3921",
    },
    laravelNotificationClass: "App\\Notifications\\TransferSubmittedNotification",
    providerGuidelines: {
      senderIdRule: "Ensures customer has immediate proof of reference entry, reducing anxiety and duplicate phone calls to organizer.",
      coverage: "Available on SMS (+1-876, +1) and Email.",
      pricingEstimate: "1 SMS segment.",
      deliveryCallbackRequired: true,
    },
  },
  {
    id: "tmpl-payment-verified-rejected",
    trigger: "payment_verified_rejected",
    name: "Payment Verified or Rejected",
    stageNumber: 4,
    description: "Notifies customer immediately when organizer verifies bank statement (issuing active QR ticket) or rejects payment with reason for resubmission.",
    defaultEmailSubject: "Payment Update: Order #{orderNumber} ({verificationStatus})",
    defaultEmailHtml: `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif; background: #0c1220; color: #f1f5f9; margin: 0; padding: 24px; }
    .email-card { max-width: 580px; margin: 0 auto; background: #131d33; border: 1px solid #1e2e4f; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .email-header.verified { background: linear-gradient(135deg, #059669 0%, #064e3b 100%); padding: 24px; text-align: center; border-bottom: 3px solid #10b981; }
    .email-header.rejected { background: linear-gradient(135deg, #dc2626 0%, #7f1d1d 100%); padding: 24px; text-align: center; border-bottom: 3px solid #ef4444; }
    .email-header h1 { margin: 0 0 6px 0; font-size: 20px; color: #ffffff; }
    .email-body { padding: 28px 24px; font-size: 14px; line-height: 1.6; color: #cbd5e1; }
    .ticket-box { background: #1a2744; border: 2px dashed #10b981; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; }
    .ticket-code { font-size: 24px; font-weight: 800; letter-spacing: 2px; color: #10b981; margin: 8px 0; font-family: monospace; }
    .reject-box { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 8px; padding: 16px; margin: 20px 0; color: #fca5a5; }
    .cta-button { display: block; text-align: center; background: #10b981; color: #042f2e; text-decoration: none; font-weight: 700; padding: 14px 24px; border-radius: 8px; margin: 24px 0 16px 0; }
    .cta-button.resubmit { background: #ef4444; color: #ffffff; }
    .email-footer { background: #0e172a; padding: 18px 24px; font-size: 11px; color: #64748b; text-align: center; border-top: 1px solid #1e293b; }
  </style>
</head>
<body>
  <div class="email-card">
    <div class="email-header verified">
      <h1>Payment Verified & Approved</h1>
      <p style="margin:0; color:#a7f3d0; font-size:13px;">Digital Ticket is Now ACTIVE</p>
    </div>
    <div class="email-body">
      <p>Hello <strong>{customerName}</strong>,</p>
      <p>Great news! The organizer has verified your payment of <strong>{totalAmount}</strong> for <strong>{eventName}</strong>.</p>
      
      <div class="ticket-box">
        <span style="font-size:11px; color:#94a3b8; text-transform:uppercase; font-weight:700;">Official Ticket Code</span>
        <div class="ticket-code">#{ticketCode}</div>
        <span style="display:inline-block; padding:4px 10px; background:rgba(16,185,129,0.2); color:#10b981; font-weight:700; border-radius:6px; font-size:11px;">PAID & ACTIVATED</span>
      </div>

      <p style="font-size:13px; color:#94a3b8;">
        Present this digital QR ticket at the kitchen counter on event day. Staff will scan the barcode to release your meals.
      </p>

      <a href="{ticketUrl}" class="cta-button">Open Digital Ticket & QR Code &rarr;</a>
    </div>
    <div class="email-footer">
      Verified by {verifierName} &bull; EventBite Digital Box Office
    </div>
  </div>
</body>
</html>`,
    defaultSms: "Payment VERIFIED for Order #{orderNumber}! Ticket #{ticketCode} is now active. Present your QR code at the gate: {ticketUrl}",
    supportedChannels: ["email", "sms", "whatsapp"],
    availableVariables: ["{customerName}", "{orderNumber}", "{verificationStatus}", "{totalAmount}", "{eventName}", "{ticketCode}", "{ticketUrl}", "{verifierName}", "{rejectionReason}"],
    sampleVariables: {
      customerName: "Damian Marley",
      orderNumber: "ORD-8492",
      verificationStatus: "Verified & Approved",
      totalAmount: "J$3,600",
      eventName: "Sunday Fish Fry — Community Fundraiser",
      ticketCode: "SFF-2026-8492",
      ticketUrl: "https://islandevents.app/tickets/SFF-2026-8492",
      verifierName: "Treasurer Davis",
      rejectionReason: "Transaction reference not found on NCB bank statement",
    },
    laravelNotificationClass: "App\\Notifications\\PaymentVerifiedOrRejectedNotification",
    providerGuidelines: {
      senderIdRule: "Critical delivery: This is the customer's proof of payment. Must have high priority queue routing and delivery webhook logging.",
      coverage: "Dual dispatch (Email + SMS) strongly recommended.",
      pricingEstimate: "Email-first saves SMS fees; SMS guarantees immediate gate access on phones.",
      deliveryCallbackRequired: true,
    },
  },
  {
    id: "tmpl-cash-receipt-recorded",
    trigger: "cash_receipt_recorded",
    name: "Cash Receipt Recorded",
    stageNumber: 5,
    description: "Official immutable receipt for physical cash collected by organizer or authorized sellers with timestamp, collector identity, and receipt number.",
    defaultEmailSubject: "Official Cash Receipt #{receiptNumber} - Order #{orderNumber}",
    defaultEmailHtml: `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif; background: #0c1220; color: #f1f5f9; margin: 0; padding: 24px; }
    .email-card { max-width: 580px; margin: 0 auto; background: #131d33; border: 1px solid #1e2e4f; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .email-header { background: linear-gradient(135deg, #047857 0%, #064e3b 100%); padding: 24px; text-align: center; border-bottom: 3px solid #34d399; }
    .email-header h1 { margin: 0 0 6px 0; font-size: 20px; color: #ffffff; }
    .email-body { padding: 28px 24px; font-size: 14px; line-height: 1.6; color: #cbd5e1; }
    .receipt-box { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 18px; margin: 20px 0; }
    .receipt-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
    .receipt-row strong { color: #f8fafc; }
    .receipt-total { border-top: 1px dashed #475569; padding-top: 10px; margin-top: 10px; font-size: 16px; font-weight: 700; color: #34d399; }
    .cta-button { display: block; text-align: center; background: #10b981; color: #042f2e; text-decoration: none; font-weight: 700; padding: 14px 24px; border-radius: 8px; margin: 24px 0 16px 0; }
    .email-footer { background: #0e172a; padding: 18px 24px; font-size: 11px; color: #64748b; text-align: center; border-top: 1px solid #1e293b; }
  </style>
</head>
<body>
  <div class="email-card">
    <div class="email-header">
      <h1>Official Cash Receipt</h1>
      <p style="margin:0; color:#a7f3d0; font-size:13px;">Receipt #{receiptNumber}</p>
    </div>
    <div class="email-body">
      <p>Hello <strong>{customerName}</strong>,</p>
      <p>We have recorded your in-person cash payment for <strong>{eventName}</strong>:</p>
      
      <div class="receipt-box">
        <div class="receipt-row"><span>Receipt Number:</span><strong style="font-family:monospace;">{receiptNumber}</strong></div>
        <div class="receipt-row"><span>Order Number:</span><strong>#{orderNumber}</strong></div>
        <div class="receipt-row"><span>Date & Time Received:</span><strong>{receivedAt}</strong></div>
        <div class="receipt-row"><span>Collector / Officer:</span><strong>{receivedBy}</strong></div>
        <div class="receipt-row receipt-total"><span>Amount Received:</span><strong>{amountReceived}</strong></div>
      </div>

      <p style="font-size:13px; color:#94a3b8;">
        Your digital redemption ticket has been updated to <strong>PAID</strong>. Keep this receipt for your records.
      </p>

      <a href="{ticketUrl}" class="cta-button">View Active Digital Ticket &rarr;</a>
    </div>
    <div class="email-footer">
      Authorized Event Box Office &bull; Immutable Cash Audit Record
    </div>
  </div>
</body>
</html>`,
    defaultSms: "Cash receipt recorded: {amountReceived} received for Order #{orderNumber} (Rcpt: {receiptNumber}). Your digital ticket is activated: {ticketUrl}",
    supportedChannels: ["email", "sms", "whatsapp"],
    availableVariables: ["{customerName}", "{orderNumber}", "{receiptNumber}", "{eventName}", "{amountReceived}", "{receivedBy}", "{receivedAt}", "{ticketUrl}"],
    sampleVariables: {
      customerName: "Miss Lou Bennett",
      orderNumber: "FF-1024",
      receiptNumber: "RCP-2026-1024",
      eventName: "Sunday Fish Fry — Community Fundraiser",
      amountReceived: "J$3,000",
      receivedBy: "Sister Paulette (Authorized Agent)",
      receivedAt: "October 24, 2026 at 2:30 PM",
      ticketUrl: "https://islandevents.app/tickets/FF-1024",
    },
    laravelNotificationClass: "App\\Notifications\\CashReceiptRecordedNotification",
    providerGuidelines: {
      senderIdRule: "Audit trail: Dispatches automatically whenever cashier records a physical cash receipt in the cash ledger.",
      coverage: "Direct SMS and Email.",
      pricingEstimate: "1 SMS segment.",
      deliveryCallbackRequired: true,
    },
  },
  {
    id: "tmpl-ticket-issued-updated",
    trigger: "ticket_issued_updated",
    name: "Ticket Issued or Updated",
    stageNumber: 6,
    description: "Issues the customer's digital barcode ticket or sends an updated version if the order portion counts or menu choices were revised.",
    defaultEmailSubject: "Digital Ticket Issued: #{ticketCode} - {eventName}",
    defaultEmailHtml: `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif; background: #0c1220; color: #f1f5f9; margin: 0; padding: 24px; }
    .email-card { max-width: 580px; margin: 0 auto; background: #131d33; border: 1px solid #1e2e4f; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .email-header { background: linear-gradient(135deg, #1e40af 0%, #1e1b4b 100%); padding: 24px; text-align: center; border-bottom: 3px solid #60a5fa; }
    .email-header h1 { margin: 0 0 6px 0; font-size: 20px; color: #ffffff; }
    .email-body { padding: 28px 24px; font-size: 14px; line-height: 1.6; color: #cbd5e1; }
    .ticket-display { background: #1e293b; border: 1px solid #3b82f6; border-radius: 12px; padding: 20px; text-align: center; margin: 20px 0; }
    .ticket-code-text { font-size: 26px; font-weight: 800; color: #60a5fa; letter-spacing: 2px; font-family: monospace; }
    .items-list { background: #0f172a; padding: 12px 16px; border-radius: 8px; margin-top: 14px; text-align: left; font-size: 13px; color: #cbd5e1; }
    .cta-button { display: block; text-align: center; background: #3b82f6; color: #ffffff; text-decoration: none; font-weight: 700; padding: 14px 24px; border-radius: 8px; margin: 24px 0 16px 0; }
    .email-footer { background: #0e172a; padding: 18px 24px; font-size: 11px; color: #64748b; text-align: center; border-top: 1px solid #1e293b; }
  </style>
</head>
<body>
  <div class="email-card">
    <div class="email-header">
      <h1>Your Digital Ticket is Ready</h1>
      <p style="margin:0; color:#bfdbfe; font-size:13px;">{eventName}</p>
    </div>
    <div class="email-body">
      <p>Hello <strong>{customerName}</strong>,</p>
      <p>Here is your digital ticket for <strong>{eventName}</strong> on <strong>{eventDate}</strong>.</p>
      
      <div class="ticket-display">
        <span style="font-size:11px; color:#94a3b8; text-transform:uppercase; font-weight:700;">Ticket Access Code</span>
        <div class="ticket-code-text">#{ticketCode}</div>
        <div class="items-list">
          <strong>Order Items / Portions:</strong><br>
          {itemsSummary}
        </div>
      </div>

      <p style="font-size:13px; color:#94a3b8;">
        Save this email or bookmark your ticket link. Present the QR code on your phone when arriving at the collection tent.
      </p>

      <a href="{ticketUrl}" class="cta-button">Open Live QR Barcode &rarr;</a>
    </div>
    <div class="email-footer">
      Organized by {organizerName} &bull; EventBite Ticketing Service
    </div>
  </div>
</body>
</html>`,
    defaultSms: "Your ticket #{ticketCode} for {eventName} is ready! Show this QR code at food pickup: {ticketUrl}",
    supportedChannels: ["email", "sms", "whatsapp"],
    availableVariables: ["{customerName}", "{ticketCode}", "{eventName}", "{eventDate}", "{itemsSummary}", "{ticketUrl}", "{organizerName}"],
    sampleVariables: {
      customerName: "Damian Marley",
      ticketCode: "SFF-2026-8492",
      eventName: "Sunday Fish Fry — Community Fundraiser",
      eventDate: "Sunday, November 22, 2026",
      itemsSummary: "1x Escovitch Fish Meal (Jumbo Catch, Combo Side), 1x Fried Bammy, 1x Fried Chicken Meal",
      ticketUrl: "https://islandevents.app/tickets/SFF-2026-8492",
      organizerName: "Kingston Youth Foundation",
    },
    laravelNotificationClass: "App\\Notifications\\TicketIssuedOrUpdatedNotification",
    providerGuidelines: {
      senderIdRule: "Contains deep-link to customer's offline-capable ticket wallet pass.",
      coverage: "Available on all SMS routes and responsive email.",
      pricingEstimate: "1 SMS segment (under 160 characters).",
      deliveryCallbackRequired: true,
    },
  },
  {
    id: "tmpl-event-reminders",
    trigger: "event_reminders",
    name: "Event Reminders",
    stageNumber: 7,
    description: "Broadcast countdown reminder (e.g. 24 hours prior) with venue directions, serving hours, parking information, and pickup lane instructions.",
    defaultEmailSubject: "Event Reminder: {eventName} is taking place soon!",
    defaultEmailHtml: `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif; background: #0c1220; color: #f1f5f9; margin: 0; padding: 24px; }
    .email-card { max-width: 580px; margin: 0 auto; background: #131d33; border: 1px solid #1e2e4f; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .email-header { background: linear-gradient(135deg, #d97706 0%, #b45309 100%); padding: 24px; text-align: center; border-bottom: 3px solid #fbbf24; }
    .email-header h1 { margin: 0 0 6px 0; font-size: 20px; color: #ffffff; }
    .email-body { padding: 28px 24px; font-size: 14px; line-height: 1.6; color: #cbd5e1; }
    .event-info-box { background: #1e293b; border-radius: 8px; padding: 18px; margin: 20px 0; }
    .info-item { margin-bottom: 10px; font-size: 13px; }
    .info-item strong { color: #f8fafc; }
    .cta-button { display: block; text-align: center; background: #f59e0b; color: #451a03; text-decoration: none; font-weight: 700; padding: 14px 24px; border-radius: 8px; margin: 24px 0 16px 0; }
    .email-footer { background: #0e172a; padding: 18px 24px; font-size: 11px; color: #64748b; text-align: center; border-top: 1px solid #1e293b; }
  </style>
</head>
<body>
  <div class="email-card">
    <div class="email-header">
      <h1>Event Countdown Reminder</h1>
      <p style="margin:0; color:#fef3c7; font-size:13px;">{eventName} &bull; {eventDate}</p>
    </div>
    <div class="email-body">
      <p>Hello <strong>{customerName}</strong>,</p>
      <p>We are excited to see you! Here are key details for tomorrow's event:</p>
      
      <div class="event-info-box">
        <div class="info-item"><strong>Date & Time:</strong> {eventDate} ({pickupHours})</div>
        <div class="info-item"><strong>Venue Location:</strong> {venueLocation}</div>
        <div class="info-item"><strong>Your Ticket:</strong> #{ticketCode}</div>
        <div class="info-item"><strong>Pickup Instructions:</strong> {pickupInstructions}</div>
      </div>

      <a href="{ticketUrl}" class="cta-button">View My Ticket & Directions &rarr;</a>
    </div>
    <div class="email-footer">
      Event hotline: {organizerPhone} &bull; Kingston Waterfront Pier
    </div>
  </div>
</body>
</html>`,
    defaultSms: "Reminder: {eventName} is {eventDate}! Pickup hours: {pickupHours}. Have ticket #{ticketCode} ready on your phone: {ticketUrl}",
    supportedChannels: ["email", "sms", "whatsapp"],
    availableVariables: ["{customerName}", "{eventName}", "{eventDate}", "{venueLocation}", "{pickupHours}", "{ticketCode}", "{pickupInstructions}", "{ticketUrl}", "{organizerPhone}"],
    sampleVariables: {
      customerName: "Damian Marley",
      eventName: "Sunday Fish Fry — Community Fundraiser",
      eventDate: "Sunday, November 22, 2026",
      venueLocation: "Kingston Waterfront Pier, Ocean Boulevard",
      pickupHours: "12:00 PM – 8:00 PM",
      ticketCode: "SFF-2026-8492",
      pickupInstructions: "Proceed to Food Collection Tent B. Walk-up and drive-thru lanes available.",
      ticketUrl: "https://islandevents.app/tickets/SFF-2026-8492",
      organizerPhone: "+1 (876) 926-3849",
    },
    laravelNotificationClass: "App\\Notifications\\EventReminderNotification",
    providerGuidelines: {
      senderIdRule: "Mass broadcast optimization: Queue scheduled with rate limiting to avoid carrier throttling (e.g. 30 SMS/second limit).",
      coverage: "Email handles rich maps and directions; SMS provides high open-rate reminder.",
      pricingEstimate: "Segment calculated per recipient phone country code.",
      deliveryCallbackRequired: true,
    },
  },
  {
    id: "tmpl-pickup-instructions",
    trigger: "pickup_instructions",
    name: "Pickup Instructions",
    stageNumber: 8,
    description: "Real-time kitchen notification alerting the customer that their order is fresh and assigning them to a specific serving station/counter.",
    defaultEmailSubject: "Pickup Ready: Order #{orderNumber} is Ready for Collection!",
    defaultEmailHtml: `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif; background: #0c1220; color: #f1f5f9; margin: 0; padding: 24px; }
    .email-card { max-width: 580px; margin: 0 auto; background: #131d33; border: 1px solid #1e2e4f; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .email-header { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); padding: 24px; text-align: center; border-bottom: 3px solid #38bdf8; }
    .email-header h1 { margin: 0 0 6px 0; font-size: 20px; color: #ffffff; }
    .email-body { padding: 28px 24px; font-size: 14px; line-height: 1.6; color: #cbd5e1; }
    .station-box { background: #0f172a; border: 2px solid #38bdf8; border-radius: 10px; padding: 18px; text-align: center; margin: 20px 0; }
    .station-name { font-size: 22px; font-weight: 800; color: #38bdf8; margin: 6px 0; }
    .cta-button { display: block; text-align: center; background: #38bdf8; color: #082f49; text-decoration: none; font-weight: 700; padding: 14px 24px; border-radius: 8px; margin: 24px 0 16px 0; }
    .email-footer { background: #0e172a; padding: 18px 24px; font-size: 11px; color: #64748b; text-align: center; border-top: 1px solid #1e293b; }
  </style>
</head>
<body>
  <div class="email-card">
    <div class="email-header">
      <h1>Your Food is Ready!</h1>
      <p style="margin:0; color:#e0f2fe; font-size:13px;">Order #{orderNumber} Fresh from the Grill</p>
    </div>
    <div class="email-body">
      <p>Hello <strong>{customerName}</strong>,</p>
      <p>Your meal order <strong>#{orderNumber}</strong> has been prepared and packaged.</p>
      
      <div class="station-box">
        <span style="font-size:11px; color:#94a3b8; text-transform:uppercase; font-weight:700;">Please Proceed To</span>
        <div class="station-name">{stationName}</div>
        <span style="font-size:13px; color:#cbd5e1;">Present Ticket #{ticketCode}</span>
      </div>

      <p style="font-size:13px; color:#94a3b8;">
        Show your QR code to staff at the counter. Items will be handed over portion-by-portion.
      </p>

      <a href="{ticketUrl}" class="cta-button">Open Pickup QR Ticket &rarr;</a>
    </div>
    <div class="email-footer">
      Kitchen Operations &bull; Fresh & Hot Handover Guarantee
    </div>
  </div>
</body>
</html>`,
    defaultSms: "Order #{orderNumber} is READY! Proceed to {stationName}. Present your QR ticket to collect: {ticketUrl}",
    supportedChannels: ["sms", "whatsapp", "email"],
    availableVariables: ["{customerName}", "{orderNumber}", "{stationName}", "{ticketCode}", "{ticketUrl}"],
    sampleVariables: {
      customerName: "Damian Marley",
      orderNumber: "ORD-8492",
      stationName: "Station A - Hot Fish & Sides",
      ticketCode: "SFF-2026-8492",
      ticketUrl: "https://islandevents.app/tickets/SFF-2026-8492",
    },
    laravelNotificationClass: "App\\Notifications\\PickupInstructionsNotification",
    providerGuidelines: {
      senderIdRule: "Time-critical: Sent via SMS as customer is physically on site waiting for their food.",
      coverage: "Direct SMS delivery prioritized for speed (sub-5 second delivery latency).",
      pricingEstimate: "1 SMS segment.",
      deliveryCallbackRequired: true,
    },
  },
  {
    id: "tmpl-order-collection-completed",
    trigger: "order_collection_completed",
    name: "Order Collection Completed",
    stageNumber: 9,
    description: "Final fulfillment receipt confirming all ordered items and portions have been scanned and handed over with zero items remaining.",
    defaultEmailSubject: "Order Completed: Order #{orderNumber} Collected - Thank You!",
    defaultEmailHtml: `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif; background: #0c1220; color: #f1f5f9; margin: 0; padding: 24px; }
    .email-card { max-width: 580px; margin: 0 auto; background: #131d33; border: 1px solid #1e2e4f; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .email-header { background: linear-gradient(135deg, #059669 0%, #064e3b 100%); padding: 24px; text-align: center; border-bottom: 3px solid #10b981; }
    .email-header h1 { margin: 0 0 6px 0; font-size: 20px; color: #ffffff; }
    .email-body { padding: 28px 24px; font-size: 14px; line-height: 1.6; color: #cbd5e1; }
    .audit-summary { background: #1e293b; border-radius: 8px; padding: 16px; margin: 20px 0; font-size: 13px; }
    .audit-row { display: flex; justify-content: space-between; margin-bottom: 6px; }
    .audit-row strong { color: #f8fafc; }
    .cta-button { display: block; text-align: center; background: #10b981; color: #042f2e; text-decoration: none; font-weight: 700; padding: 14px 24px; border-radius: 8px; margin: 24px 0 16px 0; }
    .email-footer { background: #0e172a; padding: 18px 24px; font-size: 11px; color: #64748b; text-align: center; border-top: 1px solid #1e293b; }
  </style>
</head>
<body>
  <div class="email-card">
    <div class="email-header">
      <h1>Order Handover Complete</h1>
      <p style="margin:0; color:#a7f3d0; font-size:13px;">Thank You for Supporting Our Community!</p>
    </div>
    <div class="email-body">
      <p>Hello <strong>{customerName}</strong>,</p>
      <p>All items for <strong>Order #{orderNumber}</strong> have been successfully collected and audited.</p>
      
      <div class="audit-summary">
        <div class="audit-row"><span>Completed At:</span><strong>{completedAt}</strong></div>
        <div class="audit-row"><span>Handed Over By:</span><strong>{handedOverBy}</strong></div>
        <div class="audit-row"><span>Station:</span><strong>{stationName}</strong></div>
        <div class="audit-row"><span>Remaining Items:</span><strong style="color:#10b981;">0 portions (100% Fulfilled)</strong></div>
      </div>

      <p style="font-size:13px; color:#cbd5e1;">
        We hope you enjoy your meal! Your support helps fund youth mentorship and sports programs in Downtown Kingston.
      </p>

      <a href="{trackingUrl}" class="cta-button">View Order Fulfillment Audit Log &rarr;</a>
    </div>
    <div class="email-footer">
      Kingston Youth Foundation &bull; EventBite Fulfillment Audit Protocol
    </div>
  </div>
</body>
</html>`,
    defaultSms: "Order #{orderNumber} collection completed! All meal items have been handed over. Enjoy your meal and thank you for supporting the event!",
    supportedChannels: ["sms", "email", "whatsapp"],
    availableVariables: ["{customerName}", "{orderNumber}", "{completedAt}", "{handedOverBy}", "{stationName}", "{trackingUrl}"],
    sampleVariables: {
      customerName: "Damian Marley",
      orderNumber: "ORD-8492",
      completedAt: "November 22, 2026 at 1:45 PM",
      handedOverBy: "Chef Marcus",
      stationName: "Fish & Chicken Station",
      trackingUrl: "https://islandevents.app/orders/ORD-8492",
    },
    laravelNotificationClass: "App\\Notifications\\OrderCollectionCompletedNotification",
    providerGuidelines: {
      senderIdRule: "Fulfillment confirmation: Closes customer journey and creates final verifiable record.",
      coverage: "Email and SMS.",
      pricingEstimate: "1 SMS segment.",
      deliveryCallbackRequired: true,
    },
  },
];

export const SEED_ORDERS = LEGACY_SEED_ORDERS;

export const LEGACY_DEFAULT_NOTIFICATION_LOGS: NotificationLogEntry[] = [
  {
    id: "notif-1",
    orderId: "ord-fishfry-1",
    orderNumber: "ORD-8492",
    customerName: "Damian Marley",
    recipient: "+1 (876) 555-0142",
    channel: "sms",
    trigger: "order_received",
    title: "Order Received — Details & Reserve",
    message: "Order #ORD-8492 received for Sunday Fish Fry! Total: J$3,600. View order details & payment steps: https://islandevents.app/orders/ORD-8492",
    status: "delivered",
    provider: "Twilio SMS",
    idempotencyKey: "idemp_order_received_ORD-8492",
    providerMessageId: "SM67a8b9c1d2e3f4a5b6c7d8e9f0a1b2c3",
    sentAt: new Date(Date.now() - 3600000 * 4).toISOString(),
    deliveredAt: new Date(Date.now() - 3600000 * 4 + 4200).toISOString(),
    attempts: [
      { timestamp: new Date(Date.now() - 3600000 * 4).toISOString(), status: "queued", note: "Pushed to Laravel 'notifications' queue (Worker #1)", workerId: "worker-1" },
      { timestamp: new Date(Date.now() - 3600000 * 4 + 1000).toISOString(), status: "sent", note: "Accepted by Twilio SMS Gateway (Alphanumeric: ISLANDEVNT)", workerId: "worker-1" },
      { timestamp: new Date(Date.now() - 3600000 * 4 + 4200).toISOString(), status: "delivered", note: "Handset DSN confirmed via Digicel Jamaica (Cell Tower 876-04)", workerId: "webhook" },
    ],
  },
  {
    id: "notif-2",
    orderId: "ord-fishfry-2",
    orderNumber: "ORD-3921",
    customerName: "Shelly-Ann Fraser",
    recipient: "shelly@rocket.example",
    channel: "email",
    trigger: "payment_instructions",
    title: "Payment Instructions — NCB Transfer",
    message: "Payment instructions for Order #ORD-3921: Send J$5,400 via NCB Current A/C #351-928-114. Submit reference to activate ticket.",
    emailSubject: "Payment Instructions: Order #ORD-3921 (National Commercial Bank)",
    status: "delivered",
    provider: "Postmark",
    idempotencyKey: "idemp_pay_instructions_ORD-3921",
    providerMessageId: "msg_postmark_88291a9b",
    sentAt: new Date(Date.now() - 3600000 * 3.5).toISOString(),
    deliveredAt: new Date(Date.now() - 3600000 * 3.5 + 1800).toISOString(),
    attempts: [
      { timestamp: new Date(Date.now() - 3600000 * 3.5).toISOString(), status: "queued", note: "Enqueued in Redis queue 'notifications'", workerId: "worker-2" },
      { timestamp: new Date(Date.now() - 3600000 * 3.5 + 800).toISOString(), status: "sent", note: "Transmitted to Postmark SMTP API", workerId: "worker-2" },
      { timestamp: new Date(Date.now() - 3600000 * 3.5 + 1800).toISOString(), status: "delivered", note: "Delivered to recipient inbox with TLS 1.3", workerId: "webhook" },
    ],
  },
  {
    id: "notif-3",
    orderId: "ord-fishfry-2",
    orderNumber: "ORD-3921",
    customerName: "Shelly-Ann Fraser",
    recipient: "+1 (876) 555-0199",
    channel: "sms",
    trigger: "transfer_submitted",
    title: "Transfer Submitted — Verification Pending",
    message: "Order #ORD-3921: Payment ref NCB-TXN-88291 submitted. Note: Funds are NOT yet verified. Ticket activates once organizer confirms receipt.",
    status: "delivered",
    provider: "Twilio SMS",
    idempotencyKey: "idemp_transfer_sub_ORD-3921",
    providerMessageId: "SM99a8b7c6d5e4f3a2b1c0d9e8f7a6b5c4",
    sentAt: new Date(Date.now() - 3600000 * 2.8).toISOString(),
    deliveredAt: new Date(Date.now() - 3600000 * 2.8 + 3100).toISOString(),
    attempts: [
      { timestamp: new Date(Date.now() - 3600000 * 2.8).toISOString(), status: "queued", note: "Enqueued upon customer reference submission", workerId: "worker-1" },
      { timestamp: new Date(Date.now() - 3600000 * 2.8 + 1200).toISOString(), status: "sent", note: "Dispatched to Flow Jamaica network", workerId: "worker-1" },
      { timestamp: new Date(Date.now() - 3600000 * 2.8 + 3100).toISOString(), status: "delivered", note: "Carrier acknowledged delivery receipt", workerId: "webhook" },
    ],
  },
  {
    id: "notif-4",
    orderId: "ord-fishfry-1",
    orderNumber: "ORD-8492",
    customerName: "Damian Marley",
    recipient: "+1 (876) 555-0142",
    channel: "sms",
    trigger: "payment_verified_rejected",
    title: "Payment Verified — Ticket Active",
    message: "Payment VERIFIED for Order #ORD-8492! Ticket #SFF-2026-8492 is now active. Present your QR code at the gate: https://islandevents.app/t/SFF-2026-8492",
    status: "delivered",
    provider: "Twilio SMS",
    idempotencyKey: "idemp_pay_verified_ORD-8492",
    providerMessageId: "SM11223344556677889900aabbccddeeff",
    retrySuppressed: true,
    sentAt: new Date(Date.now() - 3600000 * 2.5).toISOString(),
    deliveredAt: new Date(Date.now() - 3600000 * 2.5 + 2400).toISOString(),
    attempts: [
      { timestamp: new Date(Date.now() - 3600000 * 2.5).toISOString(), status: "queued", note: "Triggered on bank ledger match approval", workerId: "worker-3" },
      { timestamp: new Date(Date.now() - 3600000 * 2.5 + 900).toISOString(), status: "sent", note: "Accepted by carrier gateway", workerId: "worker-3" },
      { timestamp: new Date(Date.now() - 3600000 * 2.5 + 2400).toISOString(), status: "delivered", note: "Delivered to Digicel handset", workerId: "webhook" },
      { timestamp: new Date(Date.now() - 3600000 * 2.0).toISOString(), status: "delivered", note: "Job retried by queue supervisor. IDEMPOTENCY CHECK PASSED: Duplicate receipt suppressed! Customer not spammed.", workerId: "worker-1" },
    ],
  },
  {
    id: "notif-5",
    orderId: "ord-fishfry-cash-1",
    orderNumber: "FF-1024",
    customerName: "Miss Lou Bennett",
    recipient: "+1 (876) 555-0120",
    channel: "sms",
    trigger: "cash_receipt_recorded",
    title: "Cash Receipt Recorded — Official Receipt",
    message: "Cash receipt recorded: J$3,000 received for Order #FF-1024 (Rcpt: RCP-2026-1024). Your digital ticket is activated: https://islandevents.app/tickets/FF-1024",
    status: "delivered",
    provider: "Twilio SMS",
    idempotencyKey: "idemp_cash_rcpt_RCP-2026-1024",
    providerMessageId: "SM44556677889900112233aabbccddeeff",
    sentAt: new Date(Date.now() - 3600000 * 2.2).toISOString(),
    deliveredAt: new Date(Date.now() - 3600000 * 2.2 + 3300).toISOString(),
    attempts: [
      { timestamp: new Date(Date.now() - 3600000 * 2.2).toISOString(), status: "queued", note: "Logged by Sister Paulette at Cash Desk B", workerId: "worker-2" },
      { timestamp: new Date(Date.now() - 3600000 * 2.2 + 1100).toISOString(), status: "sent", note: "Dispatched to Digicel SMS gateway", workerId: "worker-2" },
      { timestamp: new Date(Date.now() - 3600000 * 2.2 + 3300).toISOString(), status: "delivered", note: "Handset delivery receipt confirmed", workerId: "webhook" },
    ],
  },
  {
    id: "notif-6",
    orderId: "ord-fishfry-3",
    orderNumber: "ORD-5512",
    customerName: "Usain Bolt",
    recipient: "usain@track.example",
    channel: "email",
    trigger: "ticket_issued_updated",
    title: "Ticket Issued — QR Access Pass",
    message: "Your ticket #SFF-2026-5512 for Sunday Fish Fry is ready! Show your QR code at food pickup.",
    emailSubject: "Digital Ticket Issued: #SFF-2026-5512 - Sunday Fish Fry",
    status: "delivered",
    provider: "Postmark",
    idempotencyKey: "idemp_tck_issued_SFF-2026-5512",
    providerMessageId: "msg_postmark_771822c1",
    sentAt: new Date(Date.now() - 3600000 * 1.8).toISOString(),
    deliveredAt: new Date(Date.now() - 3600000 * 1.8 + 1500).toISOString(),
    attempts: [
      { timestamp: new Date(Date.now() - 3600000 * 1.8).toISOString(), status: "queued", note: "Enqueued upon deposit confirmation", workerId: "worker-1" },
      { timestamp: new Date(Date.now() - 3600000 * 1.8 + 600).toISOString(), status: "sent", note: "Transmitted to Postmark API", workerId: "worker-1" },
      { timestamp: new Date(Date.now() - 3600000 * 1.8 + 1500).toISOString(), status: "delivered", note: "Delivered to Gmail client", workerId: "webhook" },
    ],
  },
  {
    id: "notif-7",
    orderId: "ord-fishfry-1",
    orderNumber: "ORD-8492",
    customerName: "Damian Marley",
    recipient: "+1 (876) 555-0142",
    channel: "sms",
    trigger: "event_reminders",
    title: "Event Reminder — 24h Countdown",
    message: "Reminder: Sunday Fish Fry is Sunday, Nov 22, 2026! Pickup hours: 12PM-8PM. Have ticket #SFF-2026-8492 ready on your phone: https://islandevents.app/t/SFF-2026-8492",
    status: "delivered",
    provider: "Twilio SMS",
    idempotencyKey: "idemp_ev_remind_24h_ORD-8492",
    providerMessageId: "SM88990011223344556677aabbccddeeff",
    sentAt: new Date(Date.now() - 3600000 * 1.2).toISOString(),
    deliveredAt: new Date(Date.now() - 3600000 * 1.2 + 2800).toISOString(),
    attempts: [
      { timestamp: new Date(Date.now() - 3600000 * 1.2).toISOString(), status: "queued", note: "Scheduled event countdown broadcast", workerId: "worker-4" },
      { timestamp: new Date(Date.now() - 3600000 * 1.2 + 1000).toISOString(), status: "sent", note: "Carrier network received message", workerId: "worker-4" },
      { timestamp: new Date(Date.now() - 3600000 * 1.2 + 2800).toISOString(), status: "delivered", note: "Delivered to handset", workerId: "webhook" },
    ],
  },
  {
    id: "notif-8",
    orderId: "ord-fishfry-prompt-1024",
    orderNumber: "FF-1024",
    customerName: "Miss Lou Bennett",
    recipient: "+1 (876) 555-0120",
    channel: "sms",
    trigger: "pickup_instructions",
    title: "Pickup Instructions — Station Assignment",
    message: "Order #FF-1024 is READY! Proceed to Sides Station & Fish Counter. Present your QR ticket to collect: https://islandevents.app/tickets/FF-1024",
    status: "sent",
    provider: "Twilio SMS",
    idempotencyKey: "idemp_pickup_instr_FF-1024",
    providerMessageId: "SM33445566778899001122aabbccddeeff",
    sentAt: new Date(Date.now() - 3600000 * 0.4).toISOString(),
    attempts: [
      { timestamp: new Date(Date.now() - 3600000 * 0.4).toISOString(), status: "queued", note: "Enqueued when kitchen marked portions cooked", workerId: "worker-1" },
      { timestamp: new Date(Date.now() - 3600000 * 0.4 + 900).toISOString(), status: "sent", note: "Dispatched to cellular provider. INVARIANT ENFORCED: Status is 'sent', NOT marked delivered until carrier webhook confirms handset receipt.", workerId: "worker-1" },
    ],
  },
  {
    id: "notif-9",
    orderId: "ord-fishfry-redeemed",
    orderNumber: "ORD-9999",
    customerName: "Sean Paul",
    recipient: "+1 (876) 555-0133",
    channel: "sms",
    trigger: "order_collection_completed",
    title: "Order Collection Completed — Fulfillment Done",
    message: "Order #ORD-9999 collection completed! All meal items have been handed over. Enjoy your meal and thank you for supporting the event!",
    status: "delivered",
    provider: "Twilio SMS",
    idempotencyKey: "idemp_collection_done_ORD-9999",
    providerMessageId: "SM55667788990011223344aabbccddeeff",
    sentAt: new Date(Date.now() - 3600000 * 0.1).toISOString(),
    deliveredAt: new Date(Date.now() - 3600000 * 0.1 + 1900).toISOString(),
    attempts: [
      { timestamp: new Date(Date.now() - 3600000 * 0.1).toISOString(), status: "queued", note: "Dispatched upon zero uncollected portions audit", workerId: "worker-2" },
      { timestamp: new Date(Date.now() - 3600000 * 0.1 + 800).toISOString(), status: "sent", note: "Transmitted to mobile carrier", workerId: "worker-2" },
      { timestamp: new Date(Date.now() - 3600000 * 0.1 + 1900).toISOString(), status: "delivered", note: "Handset delivery confirmed", workerId: "webhook" },
    ],
  },
  {
    id: "notif-10",
    orderId: "ord-fishfry-4",
    orderNumber: "ORD-7781",
    customerName: "Beres Hammond",
    recipient: "+1 (876) 555-0177",
    channel: "sms",
    trigger: "order_received",
    title: "Order Received — Failed Network Delivery",
    message: "Order #ORD-7781 received for Sunday Fish Fry! Total: J$1,600. Cash payment reserved for Tent B pickup.",
    status: "failed",
    provider: "Twilio SMS",
    idempotencyKey: "idemp_order_received_ORD-7781",
    providerMessageId: "SM00112233445566778899aabbccddeeff",
    failureReason: "Carrier delivery timeout (Flow Jamaica local cell tower unreachable). Note: Payment and ticket reservation remain 100% valid and unaffected.",
    sentAt: new Date(Date.now() - 3600000 * 0.9).toISOString(),
    retryCount: 1,
    attempts: [
      { timestamp: new Date(Date.now() - 3600000 * 0.9).toISOString(), status: "queued", note: "Enqueued in notifications queue", workerId: "worker-1" },
      { timestamp: new Date(Date.now() - 3600000 * 0.9 + 1000).toISOString(), status: "sent", note: "Transmitted to carrier gateway", workerId: "worker-1" },
      { timestamp: new Date(Date.now() - 3600000 * 0.9 + 60000).toISOString(), status: "failed", note: "Carrier reported handset unreachable (Error 30008: Unknown error / unreachable tower). Exponential backoff scheduled.", workerId: "webhook" },
    ],
  },
];

export const DEFAULT_NOTIFICATION_LOGS: NotificationLogEntry[] = US_DEMO_NOTIFICATION_LOGS;

export const LEGACY_DEFAULT_HANDOVER_AUDIT_LOGS: HandoverAuditEntry[] = [
  {
    id: "audit-1",
    orderId: "ord-fishfry-prompt-1024",
    orderNumber: "FF-1024",
    ticketCode: "FF-1024",
    customerName: "Miss Lou Bennett",
    itemName: "Festival",
    itemCategory: "Sides",
    quantityCollected: 2,
    remainingQuantity: 2,
    timestamp: new Date(Date.now() - 1800000).toISOString(),
    staffName: "Sister Paulette",
    station: "Sides Station",
    actionType: "collection",
  },
  {
    id: "audit-2",
    orderId: "ord-fishfry-redeemed",
    orderNumber: "ORD-9999",
    ticketCode: "SFF-2026-9999",
    customerName: "Sean Paul",
    itemName: "Escovitch Fish Meal",
    itemCategory: "Mains",
    quantityCollected: 2,
    remainingQuantity: 0,
    timestamp: new Date(Date.now() - 3600000 * 1).toISOString(),
    staffName: "Worker Jerome",
    station: "Fish & Chicken Station",
    actionType: "collection",
  },
  {
    id: "audit-3",
    orderId: "ord-fishfry-1",
    orderNumber: "ORD-8492",
    ticketCode: "SFF-2026-8492",
    customerName: "Damian Marley",
    itemName: "Escovitch Fish Meal",
    itemCategory: "Mains",
    quantityCollected: 1,
    remainingQuantity: 0,
    timestamp: new Date(Date.now() - 3600000 * 2).toISOString(),
    staffName: "Chef Marcus",
    station: "Fish & Chicken Station",
    actionType: "collection",
  },
];

export const DEFAULT_HANDOVER_AUDIT_LOGS: HandoverAuditEntry[] = [];

export const DEFAULT_ORGANIZERS: OrganizerProfile[] = [
  {
    id: "org-kingston-youth",
    name: "South Florida Cultural Foundation",
    slug: "southfloridaculture",
    status: "active",
    defaultCurrency: "USD",
    businessLocation: "US",
    flyerStoragePrefix: "/tenants/org-kingston-youth/flyers/",
    contactEmail: "events@southfloridaculture.example",
    contactPhone: "+1 (305) 555-0199",
    contactWhatsApp: "+1 (305) 555-0199",
    tagline: "Connecting South Florida communities through food, music, and culture",
    createdAt: "2026-01-15T10:00:00.000Z",
  },
  {
    id: "org-hellshire-fishers",
    name: "New York Neighborhood Arts Collective",
    slug: "nyartscollective",
    status: "active",
    defaultCurrency: "USD",
    businessLocation: "US",
    flyerStoragePrefix: "/tenants/org-hellshire-fishers/flyers/",
    contactEmail: "market@nyartscollective.example",
    contactPhone: "+1 (212) 555-0148",
    contactWhatsApp: "+1 (212) 555-0148",
    tagline: "Supporting neighborhood arts, food vendors, and community events",
    createdAt: "2026-03-20T14:30:00.000Z",
  },
  {
    id: "org-portroyal-heritage",
    name: "Chicago Diaspora Arts & Culture Council",
    slug: "chicagodiaspora",
    status: "active",
    defaultCurrency: "USD",
    businessLocation: "US",
    flyerStoragePrefix: "/tenants/org-portroyal-heritage/flyers/",
    contactEmail: "hello@chicagodiaspora.example",
    contactPhone: "+1 (312) 555-0182",
    contactWhatsApp: "+1 (312) 555-0182",
    tagline: "Celebrating African and African diaspora cultures",
    createdAt: "2026-06-01T09:00:00.000Z",
  },
  {
    id: "org-ochorios-coop",
    name: "Historic Filipinotown Community Association",
    slug: "historicfilipinotown",
    status: "active",
    defaultCurrency: "USD",
    businessLocation: "US",
    flyerStoragePrefix: "/tenants/org-ochorios-coop/flyers/",
    contactEmail: "market@historicfilipinotown.example",
    contactPhone: "+1 (213) 555-0166",
    contactWhatsApp: "+1 (213) 555-0166",
    tagline: "Supporting Filipino American artists, businesses, and community",
    createdAt: "2026-06-01T09:00:00.000Z",
  },
  {
    id: "org-negril-sunset",
    name: "Houston Neighborhood Food & Music Alliance",
    slug: "houstonfoodmusic",
    status: "active",
    defaultCurrency: "USD",
    businessLocation: "US",
    flyerStoragePrefix: "/tenants/org-negril-sunset/flyers/",
    contactEmail: "hello@houstonfoodmusic.example",
    contactPhone: "+1 (713) 555-0127",
    contactWhatsApp: "+1 (713) 555-0127",
    tagline: "Bringing neighbors together through regional food and music",
    createdAt: "2026-06-01T09:00:00.000Z",
  },
  {
    id: "org-mobay-marine",
    name: "Greater Boston Latino Community Network",
    slug: "bostonlatinonetwork",
    status: "active",
    defaultCurrency: "USD",
    businessLocation: "US",
    flyerStoragePrefix: "/tenants/org-mobay-marine/flyers/",
    contactEmail: "events@bostonlatinonetwork.example",
    contactPhone: "+1 (617) 555-0173",
    contactWhatsApp: "+1 (617) 555-0173",
    tagline: "Connecting Greater Boston's Latino communities",
    createdAt: "2026-06-01T09:00:00.000Z",
  },
  {
    id: "org-southcoast-fishers",
    name: "Atlanta Latin Community Arts Association",
    slug: "atlantalatinarts",
    status: "active",
    defaultCurrency: "USD",
    businessLocation: "US",
    flyerStoragePrefix: "/tenants/org-southcoast-fishers/flyers/",
    contactEmail: "events@atlantalatinarts.example",
    contactPhone: "+1 (404) 555-0134",
    contactWhatsApp: "+1 (404) 555-0134",
    tagline: "Celebrating Mexican and Central American food, music, and arts",
    createdAt: "2026-06-01T09:00:00.000Z",
  },
  {
    id: "org-portland-cookout",
    name: "Bay Area Mediterranean Community Council",
    slug: "bayareamediterranean",
    status: "active",
    defaultCurrency: "USD",
    businessLocation: "US",
    flyerStoragePrefix: "/tenants/org-portland-cookout/flyers/",
    contactEmail: "hello@bayareamediterranean.example",
    contactPhone: "+1 (415) 555-0142",
    contactWhatsApp: "+1 (415) 555-0142",
    tagline: "Connecting Bay Area communities through Mediterranean heritage and food",
    createdAt: "2026-06-01T09:00:00.000Z",
  },
  {
    id: "org-whitehouse-fishers",
    name: "Nashville Black Arts & Community Alliance",
    slug: "nashvilleblackarts",
    status: "active",
    defaultCurrency: "USD",
    businessLocation: "US",
    flyerStoragePrefix: "/tenants/org-whitehouse-fishers/flyers/",
    contactEmail: "events@nashvilleblackarts.example",
    contactPhone: "+1 (615) 555-0161",
    contactWhatsApp: "+1 (615) 555-0161",
    tagline: "Celebrating soul food, blues, and Black community arts",
    createdAt: "2026-06-01T09:00:00.000Z",
  },
  {
    id: "org-runaway-events",
    name: "Phoenix Native Community Arts Partnership",
    slug: "phoenixnativearts",
    status: "active",
    defaultCurrency: "USD",
    businessLocation: "US",
    flyerStoragePrefix: "/tenants/org-runaway-events/flyers/",
    contactEmail: "hello@phoenixnativearts.example",
    contactPhone: "+1 (602) 555-0188",
    contactWhatsApp: "+1 (602) 555-0188",
    tagline: "Supporting Native-led arts, food, and community programming",
    createdAt: "2026-06-01T09:00:00.000Z",
  },
];

export const DEFAULT_TENANT_USERS: TenantUserMember[] = [
  {
    id: "user-south-florida-director",
    organizerId: "org-kingston-youth",
    name: "Ariana Campbell",
    email: "ariana.campbell@southfloridaculture.example",
    role: "event_owner",
    stationAssignment: "all",
    isActive: true,
    lastActiveAt: new Date(Date.now() - 1000 * 60 * 12).toISOString(),
  },
  {
    id: "user-us-finance-verifier",
    organizerId: "org-kingston-youth",
    name: "Jordan Alvarez",
    email: "jordan.alvarez@southfloridaculture.example",
    role: "payment_verifier",
    stationAssignment: "all",
    isActive: true,
    lastActiveAt: new Date(Date.now() - 1000 * 60 * 35).toISOString(),
  },
  {
    id: "user-miami-kitchen-lead",
    organizerId: "org-kingston-youth",
    name: "Chef Marcus Johnson",
    email: "marcus.johnson@southfloridaculture.example",
    role: "food_collector",
    stationAssignment: "mains",
    isActive: true,
    lastActiveAt: new Date(Date.now() - 1000 * 60 * 5).toISOString(),
  },
  {
    id: "user-miami-side-lead",
    organizerId: "org-kingston-youth",
    name: "Evelyn Reed",
    email: "evelyn.reed@southfloridaculture.example",
    role: "food_collector",
    stationAssignment: "sides",
    isActive: true,
    lastActiveAt: new Date(Date.now() - 1000 * 60 * 18).toISOString(),
  },
  {
    id: "user-new-york-host",
    organizerId: "org-hellshire-fishers",
    name: "Chef Mei Chen",
    email: "mei.chen@nyartscollective.example",
    role: "event_owner",
    stationAssignment: "all",
    isActive: true,
    lastActiveAt: new Date(Date.now() - 1000 * 60 * 120).toISOString(),
  },
  {
    id: "user-new-york-verifier",
    organizerId: "org-hellshire-fishers",
    name: "Andre Watson",
    email: "andre.watson@nyartscollective.example",
    role: "payment_verifier",
    stationAssignment: "all",
    isActive: true,
    lastActiveAt: new Date(Date.now() - 1000 * 60 * 95).toISOString(),
  },
  {
    id: "user-platform-superadmin",
    organizerId: "system-platform",
    name: "Platform Security & DevOps Admin",
    email: "superadmin@islandevents.example",
    role: "platform_admin",
    stationAssignment: "all",
    isActive: true,
    lastActiveAt: new Date(Date.now() - 1000 * 60 * 2).toISOString(),
  },
];

export const CROSS_TENANT_DEMO_RECORDS = {
  portmoreOrder: {
    id: "ord-portmore-9011",
    organizerId: "org-hellshire-fishers",
    orderNumber: "ORD-NY-9011",
    ticketCode: "EB-NY-2026-9011",
    customerName: "Akeem Campbell",
    customerPhone: "+1 (212) 555-9011",
    totalAmount: 37,
    currency: "USD",
    status: "paid",
    eventName: "New York Lunar New Year Food & Arts Market",
    details: "Vegetable dumplings, chicken adobo, and ginger citrus tea",
  },
  portmoreCashReceipt: {
    id: "csh-portmore-101",
    organizerId: "org-hellshire-fishers",
    receiptNumber: "CR-NY-101",
    customerName: "Charmaine Bailey",
    amount: 24,
    currency: "USD",
    receivedBy: "Andre Watson",
  },
  mobayOrder: {
    id: "ord-mobay-4402",
    organizerId: "org-mobay-marine",
    orderNumber: "ORD-BOS-4402",
    ticketCode: "EB-BOS-2026-4402",
    customerName: "Rohan Sterling",
    customerPhone: "+1 (617) 555-4402",
    totalAmount: 45,
    currency: "USD",
    status: "paid",
    eventName: "Boston Latino Harvest & Community Celebration",
  },
};

export const DEFAULT_PLATFORM_STATS: PlatformOverviewStats = {
  totalGrossMerchandiseValueUSD: 128450,
  totalGrossMerchandiseValueJMD: 0,
  totalPlatformFeesCollectedUSD: 3211.25,
  totalPlatformFeesCollectedJMD: 0,
  totalRegisteredHosts: 42,
  totalActiveEvents: 18,
  totalTicketsIssued: 5420,
  systemUptimePercentage: 99.98,
};

export const DEFAULT_SYSTEM_HEALTH: SystemComponentHealth[] = [
  {
    name: "PostgreSQL Database Connection Pool",
    status: "healthy",
    metrics: "42/100 pool utilized · 4ms ping · 0 replication lag",
    lastChecked: "Just now",
  },
  {
    name: "Redis Queue Workers (Ticket & Notification Delivery)",
    status: "healthy",
    metrics: "12 active daemon workers · 0 failed jobs · 2s heartbeat",
    lastChecked: "Just now",
  },
  {
    name: "Cloud Storage (Flyers, Receipts & S3/R2 Buckets)",
    status: "healthy",
    metrics: "18.4 GB stored · 28ms read latency · SSL/TLS enforced",
    lastChecked: "1 min ago",
  },
  {
    name: "Task Scheduler (Laravel Cron / Queue Heartbeat)",
    status: "healthy",
    metrics: "Heartbeat active · 14 scheduled tasks · next run in 25s",
    lastChecked: "30s ago",
  },
];

export const DEFAULT_NOTIFICATION_PROVIDERS: NotificationProviderStatus[] = [
  {
    id: "prov-twilio",
    name: "Twilio SMS Gateway",
    channel: "sms",
    status: "operational",
    maskedApiKey: "sk_live_••••••••1892",
    maskedAccountSid: "AC••••••••9841",
    endpointUrl: "https://api.twilio.com/2010-04-01",
    deliverySuccessRate: 99.4,
    lastPingAt: "Just now",
  },
  {
    id: "prov-postmark",
    name: "Postmark Transactional Email",
    channel: "email",
    status: "operational",
    maskedApiKey: "pm_live_••••••••3821",
    endpointUrl: "https://api.postmarkapp.com/email",
    deliverySuccessRate: 99.9,
    lastPingAt: "Just now",
  },
  {
    id: "prov-ses",
    name: "Amazon SES Backup Relay",
    channel: "email",
    status: "operational",
    maskedApiKey: "AKIA••••••••4912",
    endpointUrl: "https://email.us-east-1.amazonaws.com",
    deliverySuccessRate: 99.8,
    lastPingAt: "2 mins ago",
  },
  {
    id: "prov-whatsapp",
    name: "WhatsApp Cloud API",
    channel: "whatsapp",
    status: "operational",
    maskedApiKey: "EAAQ••••••••72b1",
    endpointUrl: "https://graph.facebook.com/v19.0",
    deliverySuccessRate: 99.2,
    lastPingAt: "Just now",
  },
];

export const DEFAULT_PLATFORM_FEE_SETTINGS: PlatformFeeSettings = {
  enabled: true,
  takeRatePercent: 2.5,
  fixedFeeUSD: 0.30,
  fixedFeeJMD: 0,
  nonProfitDiscountRatePercent: 1.0,
  supportedCurrencies: ["USD"],
  supportedGateways: [
    { id: "stripe", name: "Stripe", enabled: true, region: "US" },
    { id: "square", name: "Square", enabled: true, region: "US" },
  ],
  dataRetentionYears: 7,
  automatedDailyBackups: true,
  lastBackupVerifiedAt: "2026-10-25T04:00:00Z",
};

export const DEFAULT_SECURITY_AUDIT_LOGS: SecurityAuditLog[] = [
  {
    id: "sec-99120",
    timestamp: "2026-10-25T14:15:00Z",
    actorId: "user-platform-superadmin",
    actorName: "Platform Security & DevOps Admin",
    actorRole: "system_admin",
    action: "credential_masking_verification",
    targetEntity: "notification_providers",
    targetId: "prov-twilio",
    ipAddress: "192.168.1.10",
    severity: "info",
    details: "Universal enterprise credential protection verified. Plaintext secrets suppressed.",
    isPiiAccess: false,
  },
  {
    id: "sec-99119",
    timestamp: "2026-10-25T13:40:00Z",
    actorId: "user-platform-superadmin",
    actorName: "Platform Security & DevOps Admin",
    actorRole: "system_admin",
    action: "assign_administrator_scope",
    targetEntity: "admin_scope",
    targetId: "adm-scope-us",
    ipAddress: "192.168.1.10",
    severity: "info",
    details: "Assigned U.S. regional operational scope to Administrator Jordan Alvarez.",
    isPiiAccess: false,
  },
  {
    id: "sec-99118",
    timestamp: "2026-10-25T12:05:00Z",
    actorId: "user-admin-marcus",
    actorName: "Marcus Sterling",
    actorRole: "admin",
    action: "order_dispute_review",
    targetEntity: "ticket_disputes",
    targetId: "DSP-2026-001",
    ipAddress: "172.16.4.12",
    severity: "info",
    details: "Reviewed a customer payment mismatch for a U.S. event. PII access audited.",
    isPiiAccess: true,
  },
];

export const DEFAULT_ADMIN_SCOPES: AdminAssignedScope[] = [
  {
    id: "adm-scope-miami",
    adminUserId: "user-admin-beverly",
    adminName: "Beverly Campbell (South Florida Operations)",
    assignedRegion: "US",
    canManageEvents: true,
    canAssistHostPublishing: true,
    canResolveDisputes: true,
    canViewScopedReports: true,
    canAssistAccountRecovery: true,
    canReviewPaymentAudit: true,
    canModifyGlobalFees: false,
    canViewUnmaskedCredentials: false,
  },
  {
    id: "adm-scope-national",
    adminUserId: "user-admin-marcus",
    adminName: "Marcus Sterling (U.S. Operations)",
    assignedRegion: "US",
    canManageEvents: true,
    canAssistHostPublishing: true,
    canResolveDisputes: true,
    canViewScopedReports: true,
    canAssistAccountRecovery: true,
    canReviewPaymentAudit: true,
    canModifyGlobalFees: false,
    canViewUnmaskedCredentials: false,
  },
];

export const DEFAULT_TICKET_DISPUTES: TicketDispute[] = [
  {
    id: "DSP-2026-001",
    disputeNumber: "DSP-2026-001",
    orderNumber: "ORD-MIA-9011",
    ticketCode: "EB-MIA-2026-9011",
    customerName: "Akeem Campbell",
    customerPhone: "+1 (305) 555-9011",
    organizerId: "org-kingston-youth",
    organizerName: "South Florida Cultural Foundation",
    eventName: "Miami Waterfront Cultural Food Festival",
    disputeType: "payment_mismatch",
    status: "open",
    amount: 45,
    currency: "USD",
    reason: "Customer submitted a transfer reference and is waiting for host verification.",
    createdAt: "2026-10-25T11:20:00Z",
    updatedAt: "2026-10-25T11:20:00Z",
    assignedAdminName: "Marcus Sterling",
  },
  {
    id: "DSP-2026-002",
    disputeNumber: "DSP-2026-002",
    orderNumber: "ORD-NY-4402",
    ticketCode: "EB-NY-2026-4402",
    customerName: "Rohan Sterling (Miami)",
    customerPhone: "+1 (212) 555-4402",
    organizerId: "org-hellshire-fishers",
    organizerName: "New York Neighborhood Arts Collective",
    eventName: "New York Lunar New Year Food & Arts Market",
    disputeType: "payment_mismatch",
    status: "resolved",
    amount: 45,
    currency: "USD",
    reason: "Transfer sender name differed from attendee registration; host verified the payment.",
    createdAt: "2026-10-24T16:10:00Z",
    updatedAt: "2026-10-25T09:30:00Z",
    assignedAdminName: "Marcus Sterling (US Diaspora Operations)",
    resolutionNotes: "Verified the transfer record against the host's bank statement. Ticket status validated.",
  },
];

export const DEFAULT_ATTENDEE_PROFILE: AttendeeProfile = {
  id: "att-marcus-bailey",
  name: "Marcus Bailey",
  phone: "+1 (305) 555-4321",
  email: "marcus.bailey@islandevents.example",
  savedEvents: [
    {
      eventId: "evt-kingston-harbour-fry",
      organizerId: "org-kingston-youth",
      title: "Miami Waterfront Cultural Food Festival",
      dateStr: "Saturday, November 28, 2026",
      venueName: "Biscayne Bay Waterfront Park Amphitheater",
      flyerUrl: "/images/island_festival_sunset.jpg",
      currency: "USD",
      savedAt: "2026-10-24T18:00:00Z",
    },
  ],
  notificationPreferences: {
    smsReminders: true,
    emailReceipts: true,
    whatsAppTickets: true,
    marketingUpdates: false,
  },
  isGuest: false,
};

export const INITIAL_SAVED_STATE: SavedState = {
  eventDetails: DEFAULT_EVENT_DETAILS,
  menu: DEFAULT_MENU,
  settings: DEFAULT_SETTINGS,
  orders: US_DEMO_ORDERS,
  cashLedger: DEFAULT_CASH_LEDGER,
  notificationLogs: DEFAULT_NOTIFICATION_LOGS,
  handoverAuditLogs: DEFAULT_HANDOVER_AUDIT_LOGS,
  organizers: DEFAULT_ORGANIZERS,
  activeOrganizerId: "org-kingston-youth",
  activeRolePerspective: "event_owner",
  tenantUsers: DEFAULT_TENANT_USERS,
  activeAccountType: "event_host",
  platformOverviewStats: DEFAULT_PLATFORM_STATS,
  systemHealthComponents: DEFAULT_SYSTEM_HEALTH,
  notificationProviders: DEFAULT_NOTIFICATION_PROVIDERS,
  platformFeeSettings: DEFAULT_PLATFORM_FEE_SETTINGS,
  securityAuditLogs: DEFAULT_SECURITY_AUDIT_LOGS,
  adminScopes: DEFAULT_ADMIN_SCOPES,
  ticketDisputes: DEFAULT_TICKET_DISPUTES,
  attendeeProfile: DEFAULT_ATTENDEE_PROFILE,
};

export const MASTER_ACCOUNT_SETTINGS: AccountSettingDefinition[] = [
  {
    key: "personal_profile",
    title: "Personal profile",
    category: "identity_security",
    description: "Manage personal contact details, display name, phone number, and public profile info.",
    scopes: {
      system_admin: "all",
      admin: "all",
      event_host: "all",
      attendee: "all",
    },
  },
  {
    key: "change_password",
    title: "Change password",
    category: "identity_security",
    description: "Update authentication credentials with entropy checks and brute-force protection.",
    scopes: {
      system_admin: "all",
      admin: "all",
      event_host: "all",
      attendee: "all",
    },
  },
  {
    key: "two_factor_auth",
    title: "Two-factor authentication",
    category: "identity_security",
    description: "Multi-factor authentication via Authenticator app (TOTP), SMS OTP, or WebAuthn hardware passkeys.",
    scopes: {
      system_admin: "all",
      admin: "all",
      event_host: "all",
      attendee: "all",
    },
  },
  {
    key: "notification_preferences",
    title: "Notification preferences",
    category: "identity_security",
    description: "Configure delivery channels across SMS, WhatsApp, and email for orders and system alerts.",
    scopes: {
      system_admin: "all",
      admin: "all",
      event_host: "all",
      attendee: "all",
    },
  },
  {
    key: "manage_platform_users",
    title: "Manage platform users",
    category: "platform_ops",
    description: "User directory, account creation, activation, suspension, and emergency account recovery.",
    scopes: {
      system_admin: "all",
      admin: "limited",
      event_host: null,
      attendee: null,
    },
  },
  {
    key: "manage_platform_roles",
    title: "Manage platform roles",
    category: "platform_ops",
    description: "Role and permission governance, regional scope delegation, and administrative access controls.",
    scopes: {
      system_admin: "all",
      admin: "assigned_permissions_only",
      event_host: null,
      attendee: null,
    },
  },
  {
    key: "platform_configuration",
    title: "Platform configuration",
    category: "platform_ops",
    description: "Platform-wide system configuration, queue workers, storage buckets, database pools, and global fees.",
    scopes: {
      system_admin: "all",
      admin: null,
      event_host: null,
      attendee: null,
    },
  },
  {
    key: "event_management",
    title: "Event management",
    category: "event_ops",
    description: "Event lifecycle controls, publishing, deadline cutoffs, venue details, and status overrides.",
    scopes: {
      system_admin: "all_subject_to_policy",
      admin: "authorized_scope",
      event_host: "own_and_assigned_events",
      attendee: null,
    },
  },
  {
    key: "flyer_menu_management",
    title: "Flyer and menu management",
    category: "event_ops",
    description: "Promotional flyer uploads, menu categories, dish descriptions, pricing, and stock limits.",
    scopes: {
      system_admin: "oversight",
      admin: "authorized_scope",
      event_host: "own_and_assigned_events",
      attendee: null,
    },
  },
  {
    key: "event_payment_instructions",
    title: "Event payment instructions",
    category: "finance",
    description: "Manual transfer instructions for Jamaican banks (NCB, Scotia, Lynk) and US channels (Zelle, Cash App).",
    scopes: {
      system_admin: "platform_defaults",
      admin: "authorized_scope",
      event_host: "own_events",
      attendee: null,
    },
  },
  {
    key: "record_verify_payments",
    title: "Record and verify payments",
    category: "finance",
    description: "Bank transfer verification queue, reference matching, and cash on pickup ledger logging.",
    scopes: {
      system_admin: "authorized_oversight",
      admin: "assigned_permissions",
      event_host: "own_events",
      attendee: null,
    },
  },
  {
    key: "manage_event_staff",
    title: "Manage event staff",
    category: "event_ops",
    description: "Staff invitation, role assignments (Payment Verifier, Food Collector), and station permissions.",
    scopes: {
      system_admin: "oversight",
      admin: "authorized_scope",
      event_host: "own_events",
      attendee: null,
    },
  },
  {
    key: "manage_tickets_collection",
    title: "Manage tickets and collection",
    category: "event_ops",
    description: "Digital ticket issuance, QR validation, meal portion handovers, and anti-duplicate redemption.",
    scopes: {
      system_admin: "oversight",
      admin: "authorized_scope",
      event_host: "own_events",
      attendee: "own_tickets",
    },
  },
  {
    key: "sales_financial_reports",
    title: "Sales and financial reports",
    category: "finance",
    description: "Operational revenue statistics, GMV, payment method breakdowns, and settlement reports.",
    scopes: {
      system_admin: "authorized_oversight",
      admin: "assigned_scope",
      event_host: "own_events",
      attendee: "own_transactions",
    },
  },
  {
    key: "currency_regional_preferences",
    title: "Currency and regional preferences",
    category: "platform_ops",
    description: "Supported event currencies (JMD / USD), regional tax rules, and local timezone settings.",
    scopes: {
      system_admin: "platform_defaults",
      admin: "assigned_scope",
      event_host: "event_specific_settings",
      attendee: "personal_preference",
    },
  },
  {
    key: "privacy_data_controls",
    title: "Privacy and data controls",
    category: "compliance",
    description: "Data retention, GDPR / Data Protection Act compliance, customer manifest export, and erasure requests.",
    scopes: {
      system_admin: "platform_policies",
      admin: "assigned_permissions",
      event_host: "own_account_lawful_event_data",
      attendee: "own_account_personal_data",
    },
  },
  {
    key: "audit_security_logs",
    title: "Audit and security logs",
    category: "compliance",
    description: "Immutable security event stream, privileged PII access audits, and authentication trails.",
    scopes: {
      system_admin: "authorized_platform_wide_access",
      admin: "scoped_access",
      event_host: "own_event_activity",
      attendee: "own_account_activity",
    },
  },
];

export const DEFAULT_HOST_EVENT_DEFAULTS: HostEventDefaults = {
  defaultCurrency: "USD",
  defaultTimezone: "America/New_York",
  defaultLocation: "Biscayne Bay Waterfront Park, 301 Biscayne Blvd, Miami, FL 33132",
  defaultOrderDeadlineHours: 24,
  defaultPickupInstructions: "Show digital ticket QR pass at Kitchen Pickup Station A or B. Pre-orders held until 8:00 PM.",
  defaultFlyerAspectRatio: "4:5",
  defaultBrandTheme: "eventbite_navy",
};

export const DEFAULT_HOST_PAYMENT_POLICIES: HostPaymentPolicySettings = {
  enableCash: true,
  zelleInstructions: "DEMO ONLY: Configure a verified U.S. Zelle recipient before accepting transfers.",
  cashAppInstructions: "DEMO ONLY: Configure a verified Cash App recipient before accepting transfers.",
  bankTransferInstructions: "DEMO ONLY: Configure verified U.S. ACH details before accepting transfers.",
  requireTransferReference: true,
  requireSenderName: true,
  requireReceiptSnapshot: true,
  partialPaymentsEnabled: false,
  depositPercentage: 50,
  autoEmailReceipt: true,
  autoSmsReceipt: true,
  refundPolicyDeadlineHours: 48,
  refundPolicyNotes: "Full refund available up to 48 hours before order deadline. Cancellations after cutoff subject to prep costs.",
};

export const DEFAULT_HOST_COMMUNICATION_TEMPLATES: HostCommunicationTemplates = {
  orderConfirmationTemplate: "Hi {customer_name}! Your EventBite order {order_number} has been received. Ticket pass: {ticket_code}. Total: {total_amount}.",
  paymentConfirmationTemplate: "Payment of {total_amount} verified for order {order_number}. Your food portions are guaranteed for pickup!",
  eventReminderTemplate: "Reminder: {event_name} starts today at {venue_name}! Present your digital QR pass at the collection station.",
  collectionConfirmationTemplate: "Your meal portions for order {order_number} have been collected at Station {station_id}. Enjoy your meal!",
};

export const DEFAULT_HOST_OPERATIONAL_RULES: HostOperationalRules = {
  stations: [
    { id: "station-a", name: "Station A — Main courses", staffAssigned: "Chef Marcus Johnson" },
    { id: "station-b", name: "Station B — Sides & drinks", staffAssigned: "Evelyn Reed" },
  ],
  maxEventCapacity: 500,
  maxPerItemPortions: 10,
  enforceAntiDuplicateRedemption: true,
  allowPartialCollection: true,
  foodPrepBufferPercent: 10,
};

export const DEFAULT_SYSTEM_PLATFORM_SETTINGS: SystemPlatformSettings = {
  platformName: "EventBite",
  logoUrl: "/brand/eventbite-wordmark.webp",
  brandTheme: "eventbite_navy",
  supportedCurrencies: ["USD"],
  supportedPaymentMethods: ["cash", "bank", "zelle", "cashapp", "card"],
  fileStorageStatus: "healthy",
  storageBucket: "eventbite-flyers-prod",
  storageProvider: "s3_compatible_r2",
  notificationProviderStatus: "operational",
  mandatory2FaForAdmins: true,
  adminSessionTimeoutMinutes: 30,
  attendeeSessionTimeoutDays: 30,
  defaultTimezone: "America/New_York",
  defaultLocale: "en-US",
  auditRetentionDays: 365,
  piiScrubDays: 90,
  databasePoolMax: 100,
  queueWorkersCount: 4,
};

export const DEFAULT_ATTENDEE_ACCOUNT_SETTINGS: AttendeeAccountSettings = {
  fullName: "Marcus Bailey",
  phone: "+1 (305) 555-4321",
  email: "marcus.bailey@islandevents.example",
  isPhoneVerified: true,
  isEmailVerified: true,
  twoFactorEnabled: true,
  preferredDisplayCurrency: "USD",
  activeSessionsCount: 2,
};

export { TEN_FISH_FRY_EVENTS, type FishFryEventItem } from "./fishFryEvents";

export const DEFAULT_US_LAUNCH_EVENT_DETAILS: EventDetails = TEN_FISH_FRY_EVENTS[0];

export const DEFAULT_US_HOST_EVENT_DEFAULTS: HostEventDefaults = {
  defaultCurrency: "USD",
  defaultTimezone: "America/New_York",
  defaultLocation: "Biscayne Bay Waterfront Park, 301 Biscayne Blvd, Miami, FL 33132",
  defaultOrderDeadlineHours: 24,
  defaultPickupInstructions: "Show digital ticket QR pass at Kitchen Pickup Station A or B. Pre-orders held until 8:00 PM.",
  defaultFlyerAspectRatio: "4:5",
  defaultBrandTheme: "eventbite_navy",
};

export const DEFAULT_US_SETTINGS: Settings = {
  businessLocation: "US",
  currency: "USD",
  ticketPriceUSD: 15.00,
  ticketPriceJMD: 0,
  methods: {
    bank: {
      enabled: true,
      eligible: true,
      details:
        "DEMO ONLY: Example U.S. ACH instructions are not a real payment account. Configure verified payout details before accepting transfers.",
    },
    zelle: {
      enabled: true,
      eligible: true,
      details: "DEMO ONLY: Configure a verified U.S. Zelle recipient before accepting transfers.",
    },
    cashapp: {
      enabled: true,
      eligible: true,
      details: "DEMO ONLY: Configure a verified Cash App recipient before accepting transfers.",
    },
    cash: {
      enabled: true,
      eligible: true,
      details:
        "On-site Box Office & Food Pickup Tent. Cash receipts issued immediately upon authorized collection.",
    },
    card: {
      enabled: false,
      eligible: true,
      details: "Direct credit/debit card payment (Visa, Mastercard, Amex). US payment processor ready.",
    },
  },
};

export const DEFAULT_US_ATTENDEE_ACCOUNT_SETTINGS: AttendeeAccountSettings = {
  fullName: "Marcus Bailey",
  phone: "+1 (305) 555-4321",
  email: "marcus.bailey@islandevents.example",
  isPhoneVerified: true,
  isEmailVerified: true,
  twoFactorEnabled: true,
  preferredDisplayCurrency: "USD",
  activeSessionsCount: 2,
};

export const DEFAULT_US_SYSTEM_PLATFORM_SETTINGS: SystemPlatformSettings = {
  ...DEFAULT_SYSTEM_PLATFORM_SETTINGS,
  supportedCurrencies: ["USD"],
  defaultTimezone: "America/New_York",
  defaultLocale: "en-US",
};
