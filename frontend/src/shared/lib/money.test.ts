import { describe, expect, it } from "vitest";
import { formatMoney, fromRial, parseAmount, toRial } from "./money";

describe("money", () => {
  it("converts between rial and the display unit", () => {
    expect(fromRial(125000, "toman")).toBe(12500);
    expect(fromRial(125000, "rial")).toBe(125000);
    expect(toRial(12500, "toman")).toBe(125000);
    expect(toRial(12500.5, "toman")).toBe(125005);
    expect(toRial(99.6, "rial")).toBe(100);
  });

  it("formats stored rial amounts for display", () => {
    expect(formatMoney(1250000, "toman")).toBe("۱۲۵٬۰۰۰");
    expect(formatMoney(1250000, "rial")).toBe("۱٬۲۵۰٬۰۰۰");
    expect(formatMoney(null, "toman")).toBe("");
  });

  it("parses typed amounts in any digit set", () => {
    expect(parseAmount("۱٬۲۵۰٬۰۰۰")).toBe(1250000);
    expect(parseAmount("1,250,000")).toBe(1250000);
    expect(parseAmount("۱۲٫۵")).toBe(12.5);
    expect(parseAmount("")).toBeNull();
    expect(parseAmount("abc")).toBeNull();
    expect(parseAmount(undefined)).toBeNull();
  });
});
