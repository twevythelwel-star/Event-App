/**
 * Mapping Service Integration
 *
 * Provides interactive map features (address lookup, map preview, location pin,
 * address confirmation) only when a supported mapping service has been configured.
 *
 * The application never invents coordinates and never displays a location pin for
 * an unverified address. Coordinates/pins are accepted only from:
 *  - a configured mapping service (geocoding / reverse geocoding), or
 *  - an explicit host confirmation after address lookup.
 *
 * Designed as a registry so a supported mapping service (Google Maps, Mapbox,
 * OpenStreetMap, etc.) can be wired in without changing this module.
 */

export type MapServiceProvider = "google" | "mapbox" | "openstreetmap" | "custom";

export interface MapServiceConfig {
  provider: MapServiceProvider;
  /** Display name shown to the host when configuring the service. */
  label: string;
  /** Whether the service is actually configured with credentials/endpoints. */
  isConfigured: boolean;
  /**
   * Geocode a free-text address into coordinates + a confirmed formatted address.
   * Returns null when the address cannot be resolved.
   */
  geocodeAddress?: (
    address: string
  ) => Promise<{
    latitude: number;
    longitude: number;
    formattedAddress: string;
  } | null>;
  /**
   * Reverse-geocode coordinates into a confirmed formatted address.
   */
  reverseGeocode?: (
    latitude: number,
    longitude: number
  ) => Promise<{ formattedAddress: string } | null>;
  /**
   * Build an embeddable map preview URL for the given coordinates/address.
   * Returns null when a preview cannot be generated.
   */
  mapPreviewUrl?: (
    latitude: number,
    longitude: number,
    address?: string
  ) => string | null;
}

let mapServiceRegistry: MapServiceConfig | null = null;

/**
 * Register a configured mapping service. Call this once during app bootstrap
 * when mapping credentials are available (e.g. from environment configuration).
 */
export function registerMapService(config: MapServiceConfig): void {
  mapServiceRegistry = config;
}

/**
 * Returns the currently registered mapping service, or null when none is
 * configured. When null, the UI should render a non-interactive placeholder.
 */
export function getMapService(): MapServiceConfig | null {
  return mapServiceRegistry || null;
}

/**
 * Whether a supported mapping service is currently configured.
 */
export function isMapServiceConfigured(): boolean {
  return getMapService() !== null;
}

/**
 * Formats a coordinates pair for display/storage. Never fabricates values.
 */
export function formatCoordinates(latitude: number, longitude: number): string {
  const latDir = latitude >= 0 ? "N" : "S";
  const lonDir = longitude >= 0 ? "E" : "W";
  return `${Math.abs(latitude).toFixed(4)}° ${latDir}, ${Math.abs(
    longitude
  ).toFixed(4)}° ${lonDir}`;
}

/**
 * Parses a "lat, lon" string into numeric coordinates. Returns null when invalid.
 */
export function parseCoordinates(raw: string): { latitude: number; longitude: number } | null {
  const match = raw.match(/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/);
  if (!match) return null;
  const latitude = Number(match[1]);
  const longitude = Number(match[2]);
  if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
    return null;
  }
  return { latitude, longitude };
}
