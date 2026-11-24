import { describe, expect, it } from "vitest";
import { formatDate, formatDateTime, toJalali } from "./date";
import { jalaliGenerateConfig as config, parseJalali } from "./jalaliGenerateConfig";

describe("date formatting", () => {
  it("shows API timestamps on the Persian calendar with Persian digits", () => {
    // 2026-11-24 10:30 UTC = 1405/09/03 14:00 in Tehran (UTC+3:30).
    expect(formatDateTime("2026-11-24T10:30:00Z")).toBe("۱۴۰۵/۰۹/۰۳ ۱۴:۰۰");
    expect(formatDate("2026-11-24T10:30:00Z")).toBe("۱۴۰۵/۰۹/۰۳");
  });

  it("returns an empty string for missing or invalid values", () => {
    expect(formatDateTime(null)).toBe("");
    expect(formatDateTime("not a date")).toBe("");
  });
});

describe("parseJalali", () => {
  it("reads Jalali dates typed with any digits and separators", () => {
    const date = parseJalali("۱۴۰۵/۹/۳");
    expect(date?.toISOString()).toBe("2026-11-23T20:30:00.000Z");
    expect(parseJalali("1405-09-03 14:30")?.format("YYYY-MM-DD HH:mm")).toBe("1405-09-03 14:30");
  });

  it("rejects impossible dates", () => {
    expect(parseJalali("1405/13/01")).toBeNull();
    expect(parseJalali("1405/07/31")).toBeNull(); // Mehr has 30 days
    expect(parseJalali("1405/12/30")).toBeNull(); // 1405 is not a leap year
    expect(parseJalali("1403/12/30")).not.toBeNull(); // 1403 is
    expect(parseJalali("yesterday")).toBeNull();
  });
});

describe("jalaliGenerateConfig", () => {
  const azar3 = toJalali("2026-11-24T10:30:00Z");

  it("reads Jalali year, month and day", () => {
    expect(config.getYear(azar3)).toBe(1405);
    expect(config.getMonth(azar3)).toBe(8); // zero-based: Azar
    expect(config.getDate(azar3)).toBe(3);
    expect(config.getWeekDay(azar3)).toBe(2); // Tuesday
  });

  it("moves through months on the Persian calendar", () => {
    expect(config.getDate(config.getEndDate(azar3))).toBe(30);
    const esfand = config.setMonth(azar3, 11);
    expect(config.getDate(config.getEndDate(esfand))).toBe(29);
    expect(config.locale.format("fa", config.addMonth(azar3, 4), "YYYY/MM/DD")).toBe("۱۴۰۶/۰۱/۰۳");
  });

  it("starts weeks on Saturday", () => {
    expect(config.locale.getWeekFirstDay("fa")).toBe(6);
    const saturday = config.locale.getWeekFirstDate("fa", azar3);
    expect(saturday.day()).toBe(6);
    expect(config.locale.format("fa", saturday, "YYYY/MM/DD")).toBe("۱۴۰۵/۰۸/۳۰"); // 1 Azar is a Sunday
  });

  it("uses Jalali month names", () => {
    expect(config.locale.getShortMonths?.("fa")[8]).toBe("آذر");
    expect(config.locale.format("fa", azar3, "MMMM")).toBe("آذر");
  });

  it("parses what it formats", () => {
    const text = config.locale.format("fa", azar3, "YYYY/MM/DD");
    const parsed = config.locale.parse("fa", text, ["YYYY/MM/DD"]);
    expect(parsed && config.getDate(parsed)).toBe(3);
  });
});
