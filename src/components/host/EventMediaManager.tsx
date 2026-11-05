import React, { useEffect, useState } from "react";
import { ImagePlus, LoaderCircle, Trash2 } from "lucide-react";
import type { EventDetails, EventMediaAsset } from "../../types";
import { removeHostEventMedia, uploadHostEventMedia } from "../../lib/api";

type MediaType = EventMediaAsset["mediaType"];

const MEDIA_TYPES: { id: MediaType; label: string }[] = [
  { id: "gallery", label: "Event gallery" },
  { id: "organization_logo", label: "Host / organization logo" },
  { id: "sponsor_logo", label: "Sponsor logo" },
];

type Props = {
  event: EventDetails;
  disabled?: boolean;
  onChange: (event: EventDetails) => void;
};

export function EventMediaManager({ event, disabled = false, onChange }: Props) {
  const [mediaType, setMediaType] = useState<MediaType>("gallery");
  const [altText, setAltText] = useState("");
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const media = event.eventMedia || [];

  useEffect(() => () => {
    if (previewUrl) URL.revokeObjectURL(previewUrl);
  }, [previewUrl]);

  async function handleSelect(file?: File) {
    setError(null);
    if (!file) return;
    if (!event.id || event.id.startsWith("evt-draft-")) {
      setError("Save the event draft before adding gallery or logo images.");
      return;
    }
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type.toLowerCase())) {
      setError("Choose a JPG, PNG, or WebP image.");
      return;
    }
    if (file.size < 1 || file.size > 5 * 1024 * 1024) {
      setError("Image files must be 5 MB or smaller.");
      return;
    }
    const objectUrl = URL.createObjectURL(file);
    setPreviewUrl(objectUrl);
    const image = new Image();
    image.onload = async () => {
      if (image.width < 200 || image.height < 200 || image.width > 4500 || image.height > 4500) {
        setError("Images must be between 200×200 and 4500×4500 pixels.");
        URL.revokeObjectURL(objectUrl);
        setPreviewUrl(null);
        return;
      }
      const description = altText.trim();
      if (description.length < 2) {
        setError("Add alt text describing this image before uploading.");
        return;
      }
      setBusy(true);
      try {
        const saved = await uploadHostEventMedia(event.id!, file, mediaType, description, 50, 50, file.name);
        onChange({ ...event, eventMedia: saved.eventMedia || [] });
        setAltText("");
        setPreviewUrl(null);
        URL.revokeObjectURL(objectUrl);
      } catch (reason) {
        setError(reason instanceof Error ? reason.message : "The image could not be uploaded.");
      } finally {
        setBusy(false);
      }
    };
    image.onerror = () => {
      setError("The selected file is not a readable image.");
      URL.revokeObjectURL(objectUrl);
      setPreviewUrl(null);
    };
    image.src = objectUrl;
  }

  async function handleRemove(item: EventMediaAsset) {
    if (!event.id || disabled) return;
    setBusy(true);
    setError(null);
    try {
      const saved = await removeHostEventMedia(event.id, item.id);
      onChange({ ...event, eventMedia: saved.eventMedia || [] });
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "The image could not be removed.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <section aria-labelledby="event-media-manager-title" style={{ display: "grid", gap: 12, marginTop: 14, paddingTop: 14, borderTop: "1px solid rgba(148,163,184,.18)" }}>
      <div>
        <h4 id="event-media-manager-title" style={{ margin: 0, color: "#E2E8F0", fontSize: 12 }}>Gallery and brand images</h4>
        <p style={{ margin: "4px 0 0", color: "#94A3B8", fontSize: 10 }}>JPG, PNG, WebP · 5 MB max · 200–4500 px · Add descriptive alt text. Originals remain unchanged; optimized WebP previews are stored separately.</p>
      </div>
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(170px, 1fr))", gap: 8 }}>
        <label style={{ display: "grid", gap: 5, color: "#CBD5E1", fontSize: 10, fontWeight: 700 }}>Image type
          <select value={mediaType} disabled={disabled || busy} onChange={(e) => setMediaType(e.target.value as MediaType)} style={controlStyle}>
            {MEDIA_TYPES.map((type) => <option key={type.id} value={type.id}>{type.label}</option>)}
          </select>
        </label>
        <label style={{ display: "grid", gap: 5, color: "#CBD5E1", fontSize: 10, fontWeight: 700 }}>Alt text
          <input value={altText} disabled={disabled || busy} maxLength={250} onChange={(e) => setAltText(e.target.value)} placeholder="Describe the image" style={controlStyle} />
        </label>
        <label style={{ display: "grid", gap: 5, color: "#CBD5E1", fontSize: 10, fontWeight: 700 }}>Upload image
          <span style={{ display: "inline-flex", alignItems: "center", gap: 6, minHeight: 40, padding: "0 10px", borderRadius: 8, border: "1px solid rgba(56,189,248,.4)", color: "#7DD3FC", cursor: disabled || busy ? "not-allowed" : "pointer" }}>
            {busy ? <LoaderCircle size={14} /> : <ImagePlus size={14} />}{busy ? "Saving…" : "Choose image"}
            <input type="file" accept="image/jpeg,image/png,image/webp" disabled={disabled || busy} onChange={(e) => { void handleSelect(e.target.files?.[0]); e.currentTarget.value = ""; }} style={{ display: "none" }} />
          </span>
        </label>
      </div>
      {previewUrl && <div style={{ display: "flex", alignItems: "center", gap: 10, color: "#CBD5E1", fontSize: 10 }}><img src={previewUrl} alt="Selected image preview" style={{ width: 96, height: 64, objectFit: "cover", borderRadius: 6 }} />Image preview</div>}
      {error && <p role="alert" style={{ margin: 0, color: "#FCA5A5", fontSize: 11 }}>{error}</p>}
      {media.length > 0 && <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(150px, 1fr))", gap: 8 }}>
        {media.map((item) => (
          <article key={item.id} style={{ overflow: "hidden", border: "1px solid rgba(148,163,184,.2)", borderRadius: 8, background: "#111827" }}>
            <img src={item.thumbnailUrl || item.imageUrl} alt={item.altText} style={{ width: "100%", aspectRatio: "4/3", objectFit: "cover", objectPosition: `${item.positionX}% ${item.positionY}%`, display: "block" }} />
            <div style={{ padding: 8, display: "flex", justifyContent: "space-between", gap: 6, alignItems: "center" }}>
              <span style={{ color: "#CBD5E1", fontSize: 10, overflowWrap: "anywhere" }}>{MEDIA_TYPES.find((type) => type.id === item.mediaType)?.label}: {item.altText}</span>
              <button type="button" title="Remove image" aria-label={`Remove ${item.altText}`} disabled={disabled || busy} onClick={() => void handleRemove(item)} style={{ border: 0, background: "transparent", color: "#FCA5A5", cursor: "pointer" }}><Trash2 size={14} /></button>
            </div>
          </article>
        ))}
      </div>}
    </section>
  );
}

const controlStyle: React.CSSProperties = {
  minHeight: 40,
  width: "100%",
  borderRadius: 8,
  border: "1px solid rgba(148,163,184,.28)",
  background: "#0B1120",
  color: "#F8FAFC",
  padding: "0 10px",
  fontSize: 11,
};