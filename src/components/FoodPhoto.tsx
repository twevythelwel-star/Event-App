import { useState, type CSSProperties } from "react";
import { Image as ImageIcon } from "lucide-react";

type Props = {
  src?: string | null;
  alt: string;
  style?: CSSProperties;
  loading?: "eager" | "lazy";
  unavailableLabel?: string;
  showUnavailableText?: boolean;
};

export function FoodPhoto({
  src,
  alt,
  style,
  loading = "lazy",
  unavailableLabel = "Photo unavailable",
  showUnavailableText = true,
}: Props) {
  const [failedSource, setFailedSource] = useState<string | null>(null);
  const hasImage = Boolean(src?.trim()) && failedSource !== src;

  if (hasImage) {
    return (
      <img
        src={src || undefined}
        alt={alt}
        loading={loading}
        onError={() => setFailedSource(src || null)}
        style={{ width: "100%", height: "100%", objectFit: "cover", display: "block", ...style }}
      />
    );
  }

  return (
    <div
      role="img"
      aria-label={`${alt}: ${unavailableLabel}`}
      title={`${alt}: ${unavailableLabel}`}
      style={{
        width: "100%",
        height: "100%",
        minHeight: 40,
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center",
        gap: 5,
        overflow: "hidden",
        textAlign: "center",
        background: "linear-gradient(145deg, #1B2631, #111923)",
        color: "#A8B5C2",
        fontSize: 10,
        ...style,
      }}
    >
      <ImageIcon size={16} aria-hidden="true" />
      {showUnavailableText && <span>{unavailableLabel}</span>}
    </div>
  );
}