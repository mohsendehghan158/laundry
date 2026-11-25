import { describe, expect, it } from "vitest";
import { isJalaliLeapYear, jalaliMonthLength, toGregorianDate, toJalaliDate } from "./jalali";

/** The JavaScript engine's own Persian calendar (ICU) as the reference. */
const icu = new Intl.DateTimeFormat("en-u-ca-persian-nu-latn", { year: "numeric", month: "numeric", day: "numeric", timeZone: "UTC" });
function reference(date: Date): [number, number, number] {
  const parts = Object.fromEntries(icu.formatToParts(date).map((p) => [p.type, p.value]));
  return [Number(parseInt(parts.year, 10)), Number(parts.month), Number(parts.day)];
}

describe("Jalali conversion", () => {
  it("matches the engine's Persian calendar for every day from 1980 to 2060, both ways", () => {
    const mismatches: string[] = [];
    for (let t = Date.UTC(1980, 0, 1, 12); t < Date.UTC(2060, 0, 1); t += 86_400_000) {
      const date = new Date(t);
      const g: [number, number, number] = [date.getUTCFullYear(), date.getUTCMonth() + 1, date.getUTCDate()];
      const j = toJalaliDate(...g);
      if (j.join("-") !== reference(date).join("-") || toGregorianDate(...j).join("-") !== g.join("-")) {
        mismatches.push(`${g.join("-")} → ${j.join("-")} (expected ${reference(date).join("-")})`);
      }
    }
    expect(mismatches).toEqual([]);
  });

  it("gets the days jalaliday 3.1.1 got wrong", () => {
    // 1 Jan – 29 Feb of Gregorian leap years.
    expect(toJalaliDate(2028, 1, 1)).toEqual([1406, 10, 11]);
    expect(toJalaliDate(2000, 1, 12)).toEqual([1378, 10, 22]);
    expect(toJalaliDate(2024, 2, 29)).toEqual([1402, 12, 10]);
  });

  it("knows month lengths and leap years", () => {
    expect(isJalaliLeapYear(1403)).toBe(true);
    expect(isJalaliLeapYear(1404)).toBe(false);
    expect(jalaliMonthLength(1403, 12)).toBe(30);
    expect(jalaliMonthLength(1405, 12)).toBe(29);
    expect(jalaliMonthLength(1405, 6)).toBe(31);
    expect(jalaliMonthLength(1405, 7)).toBe(30);
  });
});
