import { describe, expect, it } from "vitest";
import { formatNumber, toLatinDigits, toPersianDigits } from "./digits";

describe("digits", () => {
  it("normalizes Persian and Arabic digits to Latin", () => {
    expect(toLatinDigits("۰۹۱۲۳۴۵۶۷۸۹")).toBe("0912345678" + "9");
    expect(toLatinDigits("٠١٢٣٤٥٦٧٨٩")).toBe("0123456789");
    expect(toLatinDigits("T۱-abc")).toBe("T1-abc");
  });

  it("converts Latin digits to Persian", () => {
    expect(toPersianDigits("1405/09/03")).toBe("۱۴۰۵/۰۹/۰۳");
    expect(toPersianDigits(120)).toBe("۱۲۰");
  });

  it("formats numbers with Persian digits and separators", () => {
    expect(formatNumber(1250000)).toBe("۱٬۲۵۰٬۰۰۰");
    expect(formatNumber(1250000, "en")).toBe("1,250,000");
    expect(formatNumber(null)).toBe("");
    expect(formatNumber(Number.NaN)).toBe("");
  });
});
