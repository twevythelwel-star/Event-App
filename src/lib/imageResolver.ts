/**
 * Single source of truth for which picture represents an event, a ticket's event, or a menu item.
 *
 * Accuracy rules (the same as the Laravel app's ImageSubjects / EventResource::coverImage):
 *  - A host's uploaded flyer is always used when present. It is never replaced by other photography.
 *  - Without a flyer, an approved library photo of the kind of event the host chose (sent by the API as
 *    coverImage kind "library", labelled "Illustrative photo"); never chosen here from the title.
 *  - Otherwise, the picture is artwork drawn from the event's own details, labelled
 *    "Event artwork · no flyer uploaded yet". It is never a stock photo of another event, so a concert photo
 *    can never stand in for a family picnic.
 *  - A menu item shows its own photo, or a "No photo yet" placeholder: never another dish's photo.
 *  - Nothing here is presented as a photograph of real customers, vendors, venues, attendance or partnerships.
 */
export type EventImageKind = "flyer" | "library" | "artwork";

export interface ResolvedEventImage {
  src: string;
  kind: EventImageKind;
  /** Says what the picture is when it is not the host's own flyer. */
  label: string | null;
  alt: string;
}

export const ARTWORK_LABEL = "Event artwork · no flyer uploaded yet";

/** Shown only when there is no event to draw artwork for. Clearly a placeholder, not a photograph. */
export const EVENT_PLACEHOLDER = svgPlaceholder("Event artwork", "No flyer uploaded yet");
export const MENU_PLACEHOLDER = svgPlaceholder("No photo yet", "The host has not added a photo");

function svgPlaceholder(title: string, subtitle: string): string {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 450"><rect width="800" height="450" fill="#10243A"/>`
    + `<text x="400" y="215" text-anchor="middle" font-family="sans-serif" font-size="34" font-weight="700" fill="#F8FAFC">${title}</text>`
    + `<text x="400" y="260" text-anchor="middle" font-family="sans-serif" font-size="20" fill="#A9B8CA">${subtitle}</text></svg>`;
  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
}

/** Only the host's own uploads count as a flyer: a bundled /images/ photo never does. */
function isHostImage(url: unknown): url is string {
  return typeof url === "string" && (url.startsWith("/api/") || url.startsWith("https://") || url.startsWith("data:image/") || url.startsWith("blob:"));
}

export function resolveEventImage(event: any): ResolvedEventImage {
  const title = typeof event?.title === "string" && event.title.trim() ? event.title.trim() : "Event";
  const cover = event?.coverImage;
  if (cover && isHostImage(cover.url)) {
    const kind: EventImageKind = cover.kind === "flyer" ? "flyer" : cover.kind === "library" ? "library" : "artwork";
    return { src: cover.url, kind, label: cover.label ?? null, alt: cover.alt || title };
  }
  const flyer = event?.flyerUrl ?? event?.eventThumbnail;
  if (isHostImage(flyer)) {
    return { src: flyer, kind: "flyer", label: null, alt: event?.flyerAltText || `${title} event flyer` };
  }
  const id = event?.eventId ?? event?.id;
  if (typeof id === "string" && id.trim()) {
    return { src: `/api/v1/media/events/${encodeURIComponent(id.trim())}/artwork.svg`, kind: "artwork", label: ARTWORK_LABEL,
      alt: `Artwork for ${title}: the host has not uploaded a flyer yet` };
  }
  return { src: EVENT_PLACEHOLDER, kind: "artwork", label: ARTWORK_LABEL, alt: `${title}: no flyer uploaded yet` };
}

/**
 * A menu item's own photo, or a labelled placeholder. Never a photo of a different dish. The API's imageUrl is
 * already checked server-side (ImageSubjects): a bundled sample photo only reaches an item whose name matches it,
 * and is marked imageIsIllustrative so it can be labelled "Illustrative photo".
 */
export function resolveMenuItemImage(item: any): { src: string; isPlaceholder: boolean; label: string | null; alt: string } {
  const name = typeof item?.name === "string" && item.name.trim() ? item.name.trim() : "Menu item";
  const photo = item?.imageUrl ?? item?.photoUrl;
  if (isHostImage(photo)) {
    return { src: photo, isPlaceholder: false, label: item?.imageIsIllustrative ? "Illustrative photo" : null, alt: name };
  }
  if (typeof photo === "string" && photo.startsWith("/images/") && item?.imageIsIllustrative) {
    return { src: photo, isPlaceholder: false, label: "Illustrative photo", alt: `${name} (illustrative photo)` };
  }
  return { src: MENU_PLACEHOLDER, isPlaceholder: true, label: "No photo yet", alt: `${name}: no photo yet` };
}
