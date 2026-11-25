import { describe, expect, it } from "vitest";
import { formatNumber, toLatinDigits, toPersianDigits } from "./digits";

describe("digits", () => {
  it("normalizes Persian and Arabic digits to Latin", () => {
    expect(toLatinDigits("۰۹۱۲۳۴۵۶۷۸۹")).toBe("09123456789");
    expect(toLatinDigits("٠١٢٣")).toBe("0123");
    expect(toLatinDigits("T۱ abc")).toBe("T1 abc");
  });

  it("shows Persian digits", () => {
    expect(toPersianDigits(1405)).toBe("۱۴۰۵");
  });

  it("formats with separators", () => {
    expect(formatNumber(1250000)).toBe("۱٬۲۵۰٬۰۰۰");
    expect(formatNumber(1250000, "en")).toBe("1,250,000");
  });
});
