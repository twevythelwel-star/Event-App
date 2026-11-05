import React, { useCallback, useMemo, useState } from "react";
import { EventDetails } from "../../types";
import { lookupHostEventLocation } from "../../lib/api";
import { MapPin, AlertCircle, CheckCircle2, Search, RefreshCw, Globe } from "lucide-react";

type LocationPatch = Partial<Pick<
  EventDetails,
  "streetAddress" | "city" | "stateCode" | "zipCode" | "countryCode" |
  "venueAddress" | "latitude" | "longitude" | "coordinates" |
  "locationVerified" | "locationSource" | "locationToken" | "clearLocationPin" |
  "locationPreviewUrl"
>>;

type Props = {
  eventDetails: Pick<
    EventDetails,
    | "streetAddress"
    | "city"
    | "stateCode"
    | "zipCode"
    | "countryCode"
    | "venueName"
    | "venueAddress"
    | "latitude"
    | "longitude"
    | "coordinates"
    | "timezone"
    | "locationVerified"
    | "locationSource"
    | "locationPreviewUrl"
  >;
  onUpdateLocation: (patch: LocationPatch) => void;
  disabled?: boolean;
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

const labelStyle: React.CSSProperties = {
  display: "block",
  fontSize: "11px",
  fontWeight: 700,
  color: "#CBD5E1",
  marginBottom: "5px",
};

export function VenueMapLocator({ eventDetails, onUpdateLocation, disabled = false }: Props) {
  const [lookupAddress, setLookupAddress] = useState("");
  const [lookupStatus, setLookupStatus] = useState<"idle" | "searching" | "found" | "error">("idle");
  const [lookupError, setLookupError] = useState<string | null>(null);
  const [lookupResults, setLookupResults] = useState<{
    label: string;
    latitude: number;
    longitude: number;
    token: string;
    preview: string | null;
  }[]>([]);

  const verified = Boolean(eventDetails.locationVerified);
  const currentCoords = useMemo(
    () => ({
      latitude: eventDetails.latitude,
      longitude: eventDetails.longitude,
    }),
    [eventDetails.latitude, eventDetails.longitude]
  );

  const mapPreviewUrl = useMemo(() => {
    return verified ? eventDetails.locationPreviewUrl ?? null : null;
  }, [verified, eventDetails.locationPreviewUrl]);

  const handleLookupAddress = useCallback(async () => {
    const address = lookupAddress.trim() || eventDetails.venueAddress;
    if (!address) {
      setLookupError("Enter a venue address to look up.");
      setLookupStatus("error");
      return;
    }
    setLookupStatus("searching");
    setLookupError(null);
    setLookupResults([]);
    try {
      const result = await lookupHostEventLocation(address, eventDetails.countryCode || "US");
      if (!result.enabled || result.results.length === 0) {
        setLookupError(result.message || "No matching place was found. Check the address and try again.");
        setLookupStatus("error");
        return;
      }
      setLookupResults(result.results);
      setLookupStatus("found");
    } catch (error) {
      setLookupError(error instanceof Error ? error.message : "Address lookup failed.");
      setLookupStatus("error");
    }
  }, [lookupAddress, eventDetails.venueAddress, eventDetails.countryCode]);

  const handleConfirmLocation = useCallback((result: (typeof lookupResults)[number]) => {
    onUpdateLocation({
      latitude: result.latitude,
      longitude: result.longitude,
      coordinates: `${result.latitude.toFixed(6)}, ${result.longitude.toFixed(6)}`,
      locationVerified: true,
      locationSource: "mapbox",
      locationToken: result.token,
      clearLocationPin: false,
      locationPreviewUrl: result.preview ?? undefined,
    });
    setLookupResults([]);
    setLookupStatus("idle");
  }, [lookupResults, onUpdateLocation]);

  const handleCorrectLocation = useCallback(() => {
    setLookupResults([]);
    setLookupStatus("idle");
    setLookupError(null);
    setLookupAddress("");
  }, []);

  const showPin = verified && currentCoords.latitude != null && currentCoords.longitude != null;

  return (
    <section
      id="wizard-venue-map-locator"
      aria-label="Venue location and map"
      style={{
        padding: "16px",
        borderRadius: "12px",
        background: "#0B1120",
        border: `1px solid ${
          verified
            ? "rgba(52, 211, 153, 0.35)"
            : "rgba(148, 163, 184, 0.22)"
        }`,
        display: "flex",
        flexDirection: "column",
        gap: "12px",
      }}
    >
      <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
        <MapPin size={14} color={verified ? "#34D399" : "#94A3B8"} />
        <span
          style={{
            fontSize: "11px",
            fontWeight: 800,
            color: "#E2E8F0",
            textTransform: "uppercase",
            letterSpacing: "0.6px",
          }}
        >
          Venue Location &amp; Map
        </span>
      </div>

      <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
            <label style={labelStyle}>Venue Address Lookup</label>
            <div style={{ display: "flex", gap: "8px" }}>
              <input
                id="map-lookup-address"
                type="text"
                value={lookupAddress}
                disabled={disabled}
                placeholder={eventDetails.venueAddress || "Street address, city, region, postal code"}
                onChange={(e) => setLookupAddress(e.target.value)}
                style={{ ...inputStyle, flex: 1 }}
              />
              <button
                type="button"
                id="map-lookup-btn"
                disabled={disabled || lookupStatus === "searching"}
                onClick={handleLookupAddress}
                style={{
                  padding: "0 14px",
                  borderRadius: "10px",
                  background: "#38BDF8",
                  color: "#020617",
                  fontWeight: 800,
                  border: "none",
                  cursor: lookupStatus === "searching" ? "not-allowed" : "pointer",
                }}
              >
                {lookupStatus === "searching" ? (
                  "Looking up…"
                ) : (
                  <>
                    <Search size={14} /> Look up
                  </>
                )}
              </button>
            </div>
            {lookupError && (
              <p role="alert" style={{ margin: 0, fontSize: "11px", color: "#FCA5A5", fontWeight: 600, display: "flex", alignItems: "center", gap: "6px" }}>
                <AlertCircle size={12} />
                {lookupError}
              </p>
            )}
            <p style={{ margin: 0, fontSize: "10px", color: "#94A3B8" }}>
              Lookup availability depends on the mapping service configured for this site. No pin is shown until you confirm a matching result.
            </p>
      </div>

          {lookupStatus === "found" && lookupResults.length > 0 && (
            <div
              style={{
                padding: "12px",
                borderRadius: "10px",
                background: "rgba(56, 189, 248, 0.12)",
                border: "1px solid rgba(56, 189, 248, 0.35)",
                display: "flex",
                flexDirection: "column",
                gap: "8px",
              }}
            >
              <div style={{ fontSize: "11px", color: "#94A3B8", textTransform: "uppercase", letterSpacing: "0.6px" }}>
                Choose the matching result (not yet confirmed)
              </div>
              {lookupResults.map((result, index) => (
                <div key={`${result.token}-${index}`} style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: "10px", padding: "8px", borderRadius: "8px", background: "rgba(15, 23, 42, 0.7)" }}>
                  <div>
                    <div style={{ fontSize: "12px", color: "#E2E8F0", wordBreak: "break-word" }}>{result.label}</div>
                    <div style={{ fontSize: "10px", color: "#CBD5E1", fontFamily: "ui-monospace, SFMono-Regular, monospace" }}>
                      {result.latitude.toFixed(6)}, {result.longitude.toFixed(6)}
                    </div>
                  </div>
                  <button
                    type="button"
                    id={index === 0 ? "map-confirm-location-btn" : undefined}
                    disabled={disabled}
                    onClick={() => handleConfirmLocation(result)}
                    style={{ flexShrink: 0, padding: "6px 10px", borderRadius: "8px", background: "#10B981", color: "#052E16", border: "none", fontSize: "11px", fontWeight: 800, cursor: "pointer" }}
                  >
                    <CheckCircle2 size={13} /> Confirm
                  </button>
                </div>
              ))}
              <button
                type="button"
                id="map-correct-location-btn"
                disabled={disabled}
                onClick={handleCorrectLocation}
                style={{ alignSelf: "flex-start", padding: "6px 12px", borderRadius: "8px", background: "rgba(148, 163, 184, 0.16)", color: "#CBD5E1", border: "1px solid rgba(148, 163, 184, 0.25)", fontSize: "11px", fontWeight: 700, cursor: "pointer" }}
              >
                <RefreshCw size={12} /> Search again
              </button>
            </div>
          )}

          {/* Map preview + pin (only after verification) */}
          <div
            id="map-preview-pane"
            style={{
              height: "220px",
              borderRadius: "10px",
              border: `1px solid ${
                showPin ? "rgba(52, 211, 153, 0.45)" : "rgba(148, 163, 184, 0.22)"
              }`,
              background: "#020617",
              overflow: "hidden",
              position: "relative",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            {mapPreviewUrl && showPin ? (
              <>
                <img
                  src={mapPreviewUrl}
                  alt="Confirmed venue location map preview"
                  style={{ width: "100%", height: "100%", objectFit: "cover", display: "block" }}
                />
              </>
            ) : (
              <div style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: "8px", color: "#64748B", fontSize: "12px" }}>
                {currentCoords.latitude == null || currentCoords.longitude == null ? (
                  <>
                    <Globe size={24} />
                    <div>Confirm an address lookup to view a map preview</div>
                  </>
                ) : (
                  <>
                    <Globe size={24} />
                    <div>Mapping service does not provide a preview URL for this location.</div>
                  </>
                )}
              </div>
            )}
            {!showPin && currentCoords.latitude != null && currentCoords.longitude != null && !verified && (
              <div
                style={{
                  position: "absolute",
                  bottom: "10px",
                  left: "10px",
                  fontSize: "11px",
                  color: "#FBBF24",
                  background: "rgba(2, 6, 23, 0.75)",
                  padding: "4px 8px",
                  borderRadius: "6px",
                  border: "1px solid rgba(251, 191, 36, 0.35)",
                }}
              >
                Pin not shown: location not yet confirmed
              </div>
            )}
          </div>

          {verified && (
            <p style={{ margin: 0, fontSize: "11px", color: "#34D399", fontWeight: 700, display: "flex", alignItems: "center", gap: "6px" }}>
              <CheckCircle2 size={13} />
              Venue location confirmed via mapping service.
            </p>
          )}
          {verified && (
            <button
              type="button"
              id="map-remove-location-pin-btn"
              disabled={disabled}
              onClick={() => onUpdateLocation({
                latitude: null,
                longitude: null,
                coordinates: "",
                locationVerified: false,
                locationSource: undefined,
                locationToken: undefined,
                locationPreviewUrl: undefined,
                clearLocationPin: true,
              })}
              style={{ alignSelf: "flex-start", background: "none", border: "none", color: "#38BDF8", fontSize: "11px", fontWeight: 700, cursor: "pointer", textDecoration: "underline" }}
            >
              Remove confirmed map pin
            </button>
          )}
    </section>
  );
}
