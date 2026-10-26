/**
 * Market Configuration & Regional Settings
 *
 * Implements the United States-Only Configuration for Island Event's initial launch:
 * - Default operating market: United States (US)
 * - Default currency: USD ($)
 * - Address schema: US Standard (Street, City, 2-letter State, 5-digit ZIP)
 * - Default IANA timezones: US Timezones (America/New_York, America/Chicago, etc.)
 * - Financial calculation: Exact integer minor units (cents), zero floating point math
 * - Modular architecture: Extensible registry for future international expansion
 */

export interface USAddress {
  street: string;
  city: string;
  state: string; // 2-letter US state code
  zip: string;   // 5-digit ZIP or ZIP+4
  country: "US";
}

export interface ValidatedAddress extends USAddress {
  stateName: string;
  formatted: string;
}

export const US_STATES: Record<string, string> = {
  AL: "Alabama", AK: "Alaska", AZ: "Arizona", AR: "Arkansas",
  CA: "California", CO: "Colorado", CT: "Connecticut", DE: "Delaware",
  DC: "District of Columbia", FL: "Florida", GA: "Georgia", HI: "Hawaii",
  ID: "Idaho", IL: "Illinois", IN: "Indiana", IA: "Iowa",
  KS: "Kansas", KY: "Kentucky", LA: "Louisiana", ME: "Maine",
  MD: "Maryland", MA: "Massachusetts", MI: "Michigan", MN: "Minnesota",
  MS: "Mississippi", MO: "Missouri", MT: "Montana", NE: "Nebraska",
  NV: "Nevada", NH: "New Hampshire", NJ: "New Jersey", NM: "New Mexico",
  NY: "New York", NC: "North Carolina", ND: "North Dakota", OH: "Ohio",
  OK: "Oklahoma", OR: "Oregon", PA: "Pennsylvania", RI: "Rhode Island",
  SC: "South Carolina", SD: "South Dakota", TN: "Tennessee", TX: "Texas",
  UT: "Utah", VT: "Vermont", VA: "Virginia", WA: "Washington",
  WV: "West Virginia", WI: "Wisconsin", WY: "Wyoming",
};

export const US_TIMEZONES: Record<string, string> = {
  "America/New_York": "Eastern Time (ET)",
  "America/Chicago": "Central Time (CT)",
  "America/Denver": "Mountain Time (MT)",
  "America/Los_Angeles": "Pacific Time (PT)",
  "America/Phoenix": "Arizona (MST - No DST)",
  "America/Anchorage": "Alaska Time (AKT)",
  "Pacific/Honolulu": "Hawaii Time (HST - No DST)",
};

export const US_MARKET_CONFIG = {
  countryCode: "US" as const,
  countryName: "United States",
  defaultCurrency: "USD" as const,
  currencySymbol: "$",
  defaultTimezone: "America/New_York",
  phonePrefix: "+1",
  allowedCountries: ["US"],
  allowedCurrencies: ["USD"],
  isUSOnlyLaunch: true,
};

/**
 * Validate a US address.
 */
export function validateUSAddress(address: Partial<USAddress>): ValidatedAddress {
  const country = (address.country || "US").toUpperCase();
  if (country !== "US") {
    throw new Error(`Market restricted to United States (US). Provided: ${country}`);
  }

  const street = (address.street || "").trim();
  if (street.length < 3) {
    throw new Error("Street address is required (minimum 3 characters).");
  }

  const city = (address.city || "").trim();
  if (city.length < 2) {
    throw new Error("City is required (minimum 2 characters).");
  }

  const state = (address.state || "").trim().toUpperCase();
  if (!US_STATES[state]) {
    throw new Error(`Invalid US state code: "${state}". Must be a valid 2-letter US state code.`);
  }

  const zip = (address.zip || "").trim();
  if (!/^\d{5}(-\d{4})?$/.test(zip)) {
    throw new Error(`Invalid US ZIP code format: "${zip}". Must be 5 digits (e.g. 10001) or ZIP+4.`);
  }

  return {
    street,
    city,
    state,
    stateName: US_STATES[state],
    zip,
    country: "US",
    formatted: `${street}, ${city}, ${state} ${zip}, United States`,
  };
}

/**
 * Validate that an IANA timezone identifier is a supported US timezone.
 */
export function isSupportedUSTimezone(timezone: string): boolean {
  return Boolean(US_TIMEZONES[timezone]);
}

/**
 * Accurate financial calculations in integer minor units (cents).
 * Distinguishes order totals, amounts received, outstanding balances, refunds, and net receipts.
 */
export function calculateOrderFinancials(
  subtotalCents: number,
  packagingFeeCents: number,
  amountReceivedCents: number,
  refundedCents: number = 0,
  currency: string = "USD"
): {
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
  formattedTotal: string;
  formattedReceived: string;
  formattedBalance: string;
  formattedRefunded: string;
  formattedNet: string;
} {
  const totalAmountCents = subtotalCents + packagingFeeCents;
  const outstandingBalanceCents = Math.max(0, totalAmountCents - amountReceivedCents);
  const netReceiptsCents = amountReceivedCents - refundedCents;

  const isFullyPaid = amountReceivedCents >= totalAmountCents && totalAmountCents > 0;
  const isPartiallyPaid = amountReceivedCents > 0 && amountReceivedCents < totalAmountCents;

  const fmt = (cents: number) => `$${(cents / 100).toFixed(2)}`;

  return {
    currency,
    subtotalCents,
    packagingFeeCents,
    totalAmountCents,
    amountReceivedCents,
    outstandingBalanceCents,
    refundedAmountCents: refundedCents,
    netReceiptsCents,
    isFullyPaid,
    isPartiallyPaid,
    formattedTotal: fmt(totalAmountCents),
    formattedReceived: fmt(amountReceivedCents),
    formattedBalance: fmt(outstandingBalanceCents),
    formattedRefunded: fmt(refundedCents),
    formattedNet: fmt(netReceiptsCents),
  };
}

/**
 * Locks the authoritative currency to the event's configured currency.
 * Prevent attendees or browser requests from changing the authoritative currency.
 */
export function lockAuthoritativeCurrency(
  eventCurrency: string = "USD",
  _requestedCurrency?: string
): string {
  return eventCurrency.toUpperCase().trim() || "USD";
}

/**
 * Format a US phone number to standard formats.
 */
export function formatUSPhoneNumber(rawPhone: string): { e164: string; national: string; digits: string } {
  let digits = rawPhone.replace(/\D/g, "");
  if (digits.startsWith("1") && digits.length === 11) {
    digits = digits.substring(1);
  }
  if (digits.length !== 10) {
    throw new Error(`Invalid US telephone number: ${rawPhone}. Must have 10 digits.`);
  }

  const area = digits.substring(0, 3);
  const prefix = digits.substring(3, 6);
  const line = digits.substring(6, 10);

  return {
    e164: `+1${digits}`,
    national: `(${area}) ${prefix}-${line}`,
    digits,
  };
}

/**
 * Modular International Expansion Registry.
 * Preserves extensibility for future countries without breaking the initial US-only release.
 */
const internationalRegistry: Record<string, unknown> = {
  US: US_MARKET_CONFIG,
};

export function registerInternationalMarket(countryCode: string, config: unknown): void {
  internationalRegistry[countryCode.toUpperCase().trim()] = config;
}

export function getRegisteredMarkets(): string[] {
  return Object.keys(internationalRegistry);
}
