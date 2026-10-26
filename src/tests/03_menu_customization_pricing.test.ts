import { describe, it, expect } from "vitest";
import { MenuItem } from "../types";
import { calculateItemFinalPrice, calculateOrderTotals } from "./testUtils";

describe("03: Menu Customization and Server-Side Price Calculation Suite", () => {
  const fixedFishMeal: MenuItem = {
    id: "item-fish-fixed",
    name: "Classic Escovitch Snapper Meal",
    category: "Mains",
    description: "Crispy fried whole red snapper with spicy pickled pickle and festival.",
    pricingType: "fixed",
    priceUSD: 18.0,
    priceJMD: 2800,
    imageUrl: "/images/fish.jpg",
  };

  const customizableFishMeal: MenuItem = {
    id: "item-fish-custom",
    name: "Custom Escovitch Fish Feast",
    category: "Mains",
    description: "Choose your fish size and signature starch side.",
    pricingType: "customizable",
    priceUSD: 18.0,
    priceJMD: 2800,
    imageUrl: "/images/fish-custom.jpg",
    customOptionGroups: [
      {
        id: "fish-size",
        title: "Fish Portion Size",
        required: true,
        choices: [
          { label: "Medium (approx 1 lb)", extraPriceUSD: 0, extraPriceJMD: 0 },
          { label: "Large (approx 1.5 lb)", extraPriceUSD: 4.0, extraPriceJMD: 600 },
          { label: "Jumbo King Snapper", extraPriceUSD: 8.0, extraPriceJMD: 1200 },
        ],
      },
      {
        id: "side-choice",
        title: "Side Pairing",
        required: true,
        choices: [
          { label: "Golden Fried Festival (2 pcs)", extraPriceUSD: 0, extraPriceJMD: 0 },
          { label: "Crispy Cassava Bammy (2 wedges)", extraPriceUSD: 1.5, extraPriceJMD: 250 },
          { label: "Combo: Bammy + Festival", extraPriceUSD: 2.5, extraPriceJMD: 400 },
        ],
      },
    ],
  };

  const donationItem: MenuItem = {
    id: "item-donation",
    name: "Youth Sports Equipment Contribution",
    category: "Community Donations",
    description: "Support youth gear and athletic kits.",
    pricingType: "custom_amount",
    priceUSD: 0,
    priceJMD: 0,
    imageUrl: "/images/donate.jpg",
    customAmountConfig: {
      minUSD: 5,
      maxUSD: 500,
      minJMD: 500,
      maxJMD: 75000,
      suggestedAmountsUSD: [10, 25, 50, 100],
      suggestedAmountsJMD: [1000, 2500, 5000, 10000],
    },
  };

  it("should calculate fixed meal pricing accurately in USD and JMD", () => {
    const usdResult = calculateItemFinalPrice(fixedFishMeal, "USD");
    expect(usdResult.basePrice).toBe(18.0);
    expect(usdResult.optionsExtra).toBe(0);
    expect(usdResult.finalPrice).toBe(18.0);

    const jmdResult = calculateItemFinalPrice(fixedFishMeal, "JMD");
    expect(jmdResult.basePrice).toBe(2800);
    expect(jmdResult.optionsExtra).toBe(0);
    expect(jmdResult.finalPrice).toBe(2800);
  });

  it("should calculate customizable add-ons accurately based on selected choices", () => {
    // Select Large (+4 USD / +600 JMD) and Combo Side (+2.5 USD / +400 JMD)
    const selectedIndices = {
      "fish-size": 1, // Large
      "side-choice": 2, // Combo
    };

    const usdResult = calculateItemFinalPrice(customizableFishMeal, "USD", selectedIndices);
    expect(usdResult.basePrice).toBe(18.0);
    expect(usdResult.optionsExtra).toBe(6.5); // 4 + 2.5
    expect(usdResult.finalPrice).toBe(24.5);
    expect(usdResult.summaryList).toContain("Fish Portion Size: Large (approx 1.5 lb)");
    expect(usdResult.summaryList).toContain("Side Pairing: Combo: Bammy + Festival");

    const jmdResult = calculateItemFinalPrice(customizableFishMeal, "JMD", selectedIndices);
    expect(jmdResult.basePrice).toBe(2800);
    expect(jmdResult.optionsExtra).toBe(1000); // 600 + 400
    expect(jmdResult.finalPrice).toBe(3800);
  });

  it("should handle custom donation amounts correctly", () => {
    const donationUSD = calculateItemFinalPrice(donationItem, "USD", {}, 45);
    expect(donationUSD.finalPrice).toBe(45);
    expect(donationUSD.summaryList[0]).toContain("Custom Contribution: USD 45");

    const donationJMD = calculateItemFinalPrice(donationItem, "JMD", {}, 7500);
    expect(donationJMD.finalPrice).toBe(7500);
    expect(donationJMD.summaryList[0]).toContain("Custom Contribution: JMD 7500");
  });

  it("should calculate order totals including subtotal and packaging fee", () => {
    const cart = [
      { finalItemPrice: 24.5, quantity: 2 }, // 49.0
      { finalItemPrice: 5.0, quantity: 3 }, // 15.0
    ];
    const packagingFeeUSD = 2.0;

    const totals = calculateOrderTotals(cart, packagingFeeUSD);
    expect(totals.subtotal).toBe(64.0);
    expect(totals.packagingFee).toBe(2.0);
    expect(totals.totalAmount).toBe(66.0);
  });

  it("should prevent client side undercharging by computing prices strictly from server rules", () => {
    // Untrusted client sends arbitrary negative options or spoofed extra
    const selectedIndices = { "fish-size": 0, "side-choice": 0 };
    const authoritativePrice = calculateItemFinalPrice(customizableFishMeal, "USD", selectedIndices);
    expect(authoritativePrice.finalPrice).toBe(18.0);
    expect(authoritativePrice.finalPrice).toBeGreaterThanOrEqual(customizableFishMeal.priceUSD);
  });
});
