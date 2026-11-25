import { describe, expect, it } from "vitest";
import { formatMoney, fromRials, parseAmount, toRials } from "./money";

describe("money", () => {
  it("converts between rials and toman", () => {
    expect(fromRials(1_250_000, "toman")).toBe(125_000);
    expect(toRials(125_000, "toman")).toBe(1_250_000);
    expect(toRials(500, "rial")).toBe(500);
  });

  it("formats in the display unit", () => {
    expect(formatMoney(1_250_000, "toman")).toBe("۱۲۵٬۰۰۰ تومان");
    expect(formatMoney(1_250_000, "rial", false)).toBe("۱٬۲۵۰٬۰۰۰");
  });

  it("parses typed amounts in either digit set", () => {
    expect(parseAmount("۱۲۵٬۰۰۰")).toBe(125000);
    expect(parseAmount("12,500")).toBe(12500);
    expect(parseAmount("")).toBeNull();
    expect(parseAmount("abc")).toBeNull();
  });
});
