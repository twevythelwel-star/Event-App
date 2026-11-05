/**
 * Market Configuration & Regional Settings
 *
 * Implements the United States-Only Configuration for EventBite's initial launch:
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

export const US_TERRITORIES: Record<string, string> = {
  PR: "Puerto Rico",
  VI: "U.S. Virgin Islands",
  GU: "Guam",
  AS: "American Samoa",
  MP: "Northern Mariana Islands",
};

/**
 * Combined default location catalog for the United States launch market:
 * all 50 states, the District of Columbia, and the U.S. territories.
 */
export const US_LOCATION_CATALOG = {
  countryCode: "US",
  countryName: "United States",
  states: US_STATES,
  territories: US_TERRITORIES,
};

/**
 * A modular location catalog for a country. Kept in a registry so the
 * application is not hardcoded to US-only geography and can be extended
 * to additional countries without breaking the initial US launch.
 */
export interface LocationCatalog {
  countryCode: string;
  countryName: string;
  states: Record<string, string>;
  territories?: Record<string, string>;
  defaultTimezone?: string;
  addressFormat?: string[];
}

const locationCatalogRegistry: Record<string, LocationCatalog> = {
  US: US_LOCATION_CATALOG,
};

/**
 * Register (or override) a location catalog for a country.
 * Used to add future international markets without touching the US launch config.
 */
export function registerLocationCatalog(countryCode: string, catalog: LocationCatalog): void {
  locationCatalogRegistry[countryCode.toUpperCase().trim()] = {
    ...catalog,
    countryCode: countryCode.toUpperCase().trim(),
  };
}

/**
 * Returns the location catalog for a country, falling back to the US catalog
 * when no catalog is registered for the requested country.
 */
export function getLocationCatalog(countryCode: string): LocationCatalog {
  const code = countryCode.toUpperCase().trim();
  return locationCatalogRegistry[code] || {
    countryCode: code,
    countryName: code,
    states: {},
    territories: {},
    addressFormat: ["street", "city"],
  };
}

/**
 * Returns every country code that has a registered location catalog.
 */
export function getRegisteredLocationCatalogs(): string[] {
  return Object.keys(locationCatalogRegistry);
}

/**
 * Returns the full set of state/territory options for a country's location catalog.
 */
export function getLocationOptions(countryCode: string): Record<string, string> {
  const catalog = getLocationCatalog(countryCode);
  return { ...catalog.states, ...(catalog.territories || {}) };
}

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
