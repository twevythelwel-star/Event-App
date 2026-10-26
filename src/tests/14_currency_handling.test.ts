import { describe, it, expect } from "vitest";
import { formatCurrencyAmount } from "./testUtils";

describe("14: Currency Handling Suite", () => {
  it("should format Jamaican Dollars (JMD) as whole integer amounts with J$ prefix", () => {
    expect(formatCurrencyAmount(2800, "JMD")).toBe("J$2,800");
    expect(formatCurrencyAmount(300, "JMD")).toBe("J$300");
    expect(formatCurrencyAmount(125000, "JMD")).toBe("J$125,000");

    // Decimals are rounded for JMD Caribbean event cash handling
    expect(formatCurrencyAmount(2800.75, "JMD")).toBe("J$2,801");
  });

  it("should format United States Dollars (USD) with standard two decimal places and $ prefix", () => {
    expect(formatCurrencyAmount(18, "USD")).toBe("$18.00");
    expect(formatCurrencyAmount(2.5, "USD")).toBe("$2.50");
    expect(formatCurrencyAmount(99.95, "USD")).toBe("$99.95");
  });

  it("should prevent cross-currency contamination in cart price calculations", () => {
    function computeCartWithCurrencyCheck(
      items: { price: number; currency: "USD" | "JMD" }[],
      packagingFee: { fee: number; currency: "USD" | "JMD" }
    ): { subtotal: number; total: number; currency: "USD" | "JMD" } {
      const orderCurrency = items[0]?.currency || packagingFee.currency;

      for (const itm of items) {
        if (itm.currency !== orderCurrency) {
          throw new Error(`Currency Mismatch: Cannot combine ${itm.currency} item with ${orderCurrency} order`);
        }
      }

      if (packagingFee.currency !== orderCurrency) {
        throw new Error(`Packaging Fee Currency Mismatch: Fee is in ${packagingFee.currency} but order is in ${orderCurrency}`);
      }

      const subtotal = items.reduce((sum, i) => sum + i.price, 0);
      return {
        subtotal,
        total: subtotal + packagingFee.fee,
        currency: orderCurrency,
      };
    }

    // Valid pure JMD
    const jmdCart = computeCartWithCurrencyCheck(
      [{ price: 2800, currency: "JMD" }, { price: 800, currency: "JMD" }],
      { fee: 300, currency: "JMD" }
    );
    expect(jmdCart.total).toBe(3900);
    expect(jmdCart.currency).toBe("JMD");

    // Invalid mixed currency cart throws
    expect(() =>
      computeCartWithCurrencyCheck(
        [{ price: 2800, currency: "JMD" }],
        { fee: 2.0, currency: "USD" } // Mismatched packaging fee
      )
    ).toThrow("Packaging Fee Currency Mismatch");
  });
});
