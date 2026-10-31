import type { ImgHTMLAttributes } from "react";

export type BrandLogoVariant = "wordmark" | "compact" | "icon";

export interface BrandLogoProps extends Omit<ImgHTMLAttributes<HTMLImageElement>, "alt" | "src"> {
  variant?: BrandLogoVariant;
  decorative?: boolean;
}

const logoAssets: Record<BrandLogoVariant, { src: string; width: number; height: number }> = {
  wordmark: { src: "/brand/eventbite-wordmark.webp", width: 180, height: 63 },
  compact: { src: "/brand/eventbite-wordmark.webp", width: 144, height: 51 },
  icon: { src: "/icons/eventbite-48.png", width: 40, height: 40 },
};

export function BrandLogo({
  variant = "wordmark",
  decorative = false,
  className = "",
  ...props
}: BrandLogoProps) {
  const asset = logoAssets[variant];

  return (
    <img
      {...props}
      className={`brand-logo brand-logo--${variant}${className ? ` ${className}` : ""}`}
      src={asset.src}
      width={asset.width}
      height={asset.height}
      alt={decorative ? "" : "EventBite"}
      decoding="async"
    />
  );
}
