import { resolveEventImage } from "../lib/imageResolver";

/**
 * Says what an event's picture is when it is not the event's own: "Illustrative photo" on a library photograph.
 * (Generated artwork carries its own "Event artwork · no flyer uploaded yet" line.) Place it inside the image's
 * positioned container.
 */
export function ImageLabel({ event, corner = "bottom-left" }: { event: any; corner?: "bottom-left" | "top-left" }) {
  const image = resolveEventImage(event);
  if (image.kind !== "library") {
    return null;
  }

  return (
    <span
      title={image.label ?? "Illustrative photo"}
      style={{
        position: "absolute",
        left: 8,
        ...(corner === "top-left" ? { top: 8 } : { bottom: 8 }),
        zIndex: 2,
        padding: "3px 8px",
        borderRadius: 999,
        background: "rgba(7, 17, 31, 0.78)",
        color: "#F8FAFC",
        fontSize: 11,
        fontWeight: 600,
        letterSpacing: "0.01em",
        pointerEvents: "none",
      }}
    >
      Illustrative photo
    </span>
  );
}
