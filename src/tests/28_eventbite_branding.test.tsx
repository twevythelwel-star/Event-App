import { createElement } from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { BrandLogo } from "../components/BrandLogo";

describe("EventBite brand logo", () => {
  it("renders the official wordmark with stable dimensions and alt text", () => {
    const markup = renderToStaticMarkup(createElement(BrandLogo));

    expect(markup).toContain('src="/brand/eventbite-wordmark.webp"');
    expect(markup).toContain('alt="EventBite"');
    expect(markup).toContain('width="180"');
    expect(markup).toContain('height="63"');
  });

  it("uses the derived square mark for decorative compact icons", () => {
    const markup = renderToStaticMarkup(
      createElement(BrandLogo, { variant: "icon", decorative: true })
    );

    expect(markup).toContain('src="/icons/eventbite-48.png"');
    expect(markup).toContain('alt=""');
    expect(markup).toContain('width="40"');
    expect(markup).toContain('height="40"');
  });
});
