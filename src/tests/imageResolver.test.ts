import { describe, it, expect } from "vitest";
import { ARTWORK_LABEL, EVENT_PLACEHOLDER, MENU_PLACEHOLDER, resolveEventImage, resolveMenuItemImage } from "../lib/imageResolver";
import { resolveEventThumbnail } from "../components/host/financialCalculations";

describe("Image selection and accuracy", () => {
  it("always uses the host's uploaded flyer, never replacing it with other photography", () => {
    const image = resolveEventImage({ id: "evt-1", title: "Harbour Fish Fry", flyerUrl: "/api/v1/media/events/evt-1/flyer?v=abc" });
    expect(image).toMatchObject({ src: "/api/v1/media/events/evt-1/flyer?v=abc", kind: "flyer", label: null });
    expect(resolveEventThumbnail({ id: "evt-1", flyerThumbnailUrl: "/api/v1/media/events/evt-1/flyer?variant=thumbnail" }))
      .toBe("/api/v1/media/events/evt-1/flyer?variant=thumbnail");
  });

  it("uses the server's cover description when it is given", () => {
    const image = resolveEventImage({ id: "evt-2", title: "Picnic", coverImage: { kind: "artwork", url: "/api/v1/media/events/evt-2/artwork.svg",
      alt: "Artwork for Picnic: the host has not uploaded a flyer yet", label: ARTWORK_LABEL } });
    expect(image).toMatchObject({ kind: "artwork", label: ARTWORK_LABEL });
  });

  it("never shows a concert photo for a family picnic, whatever the title says", () => {
    for (const title of ["Family Picnic with Live DJ", "Community Celebration Picnic", "Friday Night Family Fish Fry"]) {
      const image = resolveEventImage({ id: "evt-3", title, category: "Music" });
      expect(image.src).toBe("/api/v1/media/events/evt-3/artwork.svg");
      expect(image.kind).toBe("artwork");
      expect(image.label).toBe(ARTWORK_LABEL);
      expect(image.alt).toContain("has not uploaded a flyer");
    }
  });

  it("never treats a bundled stock photo as an event's flyer", () => {
    for (const stock of ["/images/island_music_reggae_concert.jpg", "/images/event_food_festival.jpg", "/images/escovitch_fish.jpg"]) {
      const image = resolveEventImage({ id: "evt-4", title: "Harbour Fish Fry", flyerUrl: stock });
      expect(image.src).not.toContain("/images/");
      expect(image.kind).toBe("artwork");
    }
    expect(resolveEventThumbnail({ id: "evt-negril-7mile-catch" })).toBe("/api/v1/media/events/evt-negril-7mile-catch/artwork.svg");
  });

  it("falls back to a clearly identified placeholder when there is no event to draw artwork for", () => {
    expect(resolveEventImage({ title: "Untitled" })).toMatchObject({ src: EVENT_PLACEHOLDER, kind: "artwork", label: ARTWORK_LABEL });
    expect(decodeURIComponent(EVENT_PLACEHOLDER)).toContain("No flyer uploaded yet");
  });

  it("shows a menu item's own photo, a labelled matching sample, or a 'No photo yet' placeholder; never another dish", () => {
    expect(resolveMenuItemImage({ name: "Jerk Chicken", imageUrl: "/api/v1/media/menu/9/photo" })).toMatchObject({ isPlaceholder: false, label: null });
    expect(resolveMenuItemImage({ name: "Jerk Chicken", imageUrl: "/images/jerk_chicken.jpg", imageIsIllustrative: true }))
      .toMatchObject({ src: "/images/jerk_chicken.jpg", label: "Illustrative photo" });
    // A bundled photo the server did not mark as a matching sample is never shown, e.g. jerk chicken on a sushi roll.
    const sushi = resolveMenuItemImage({ name: "Salmon Sushi Roll", imageUrl: "/images/jerk_chicken.jpg" });
    expect(sushi).toMatchObject({ src: MENU_PLACEHOLDER, isPlaceholder: true, label: "No photo yet" });
    expect(decodeURIComponent(MENU_PLACEHOLDER)).toContain("No photo yet");
  });
});
