import { describe, expect, it } from "vitest";
import { dayjs, formatDate, formatLongDate } from "./date";
import { jalaliGenerateConfig as config } from "./jalali-generate-config";

// 2026-11-25 is 4 Azar 1405, a Wednesday.
const day = () => dayjs("2026-11-25T08:30:00Z");

describe("jalali generate config", () => {
  it("reads Jalali year, month and day", () => {
    expect(config.getYear(day())).toBe(1405);
    expect(config.getMonth(day())).toBe(8); // zero-based: Azar
    expect(config.getDate(day())).toBe(4);
  });

  it("moves by Jalali months and knows their length", () => {
    expect(config.getDate(config.getEndDate(day()))).toBe(30);
    const esfand = config.setMonth(config.setDate(day(), 1), 11);
    expect(config.getDate(config.getEndDate(esfand))).toBe(29); // 1405 is not a leap year
    const next = config.addMonth(day(), 1);
    expect([config.getYear(next), config.getMonth(next), config.getDate(next)]).toEqual([1405, 9, 4]);
  });

  it("starts the week on Saturday", () => {
    expect(config.locale.getWeekFirstDay("fa")).toBe(6);
    const first = config.locale.getWeekFirstDate("fa", day());
    expect(first.day()).toBe(6);
    expect(first.toDate().toISOString().slice(0, 10)).toBe("2026-11-21");
  });

  it("formats and parses Jalali text, accepting Persian digits", () => {
    expect(config.locale.format("fa", day(), "YYYY/MM/DD")).toBe("۱۴۰۵/۰۹/۰۴");
    expect(config.locale.parse("fa", config.locale.format("fa", day(), "YYYY/MM/DD"), ["YYYY/MM/DD"])?.date()).toBe(4);
    const parsed = config.locale.parse("fa", "۱۴۰۵/۰۹/۰۴", ["YYYY/MM/DD"]);
    expect(parsed?.format("YYYY-MM-DD")).toBe("1405-09-04");
    expect(dayjs(parsed).calendar("gregory").format("YYYY-MM-DD")).toBe("2026-11-25");
  });

  it("rejects impossible dates", () => {
    expect(config.locale.parse("fa", "1405/12/30", ["YYYY/MM/DD"])).toBeNull();
    expect(config.locale.parse("fa", "1405/13/01", ["YYYY/MM/DD"])).toBeNull();
    expect(config.locale.parse("fa", "abc", ["YYYY/MM/DD"])).toBeNull();
  });

  it("gives Persian month and weekday names", () => {
    expect(config.locale.getShortMonths?.("fa")[0]).toBe("فروردین");
    expect(config.locale.getShortWeekDays?.("fa")[0]).toBe("ش");
  });

  it("display helpers use the Jalali calendar and Persian digits", () => {
    expect(formatDate("2026-11-25T08:30:00Z")).toBe("۱۴۰۵/۰۹/۰۴");
    expect(formatLongDate("2026-11-25T08:30:00Z")).toBe("۴ آذر ۱۴۰۵");
    expect(formatDate(null)).toBe("—");
  });
});
