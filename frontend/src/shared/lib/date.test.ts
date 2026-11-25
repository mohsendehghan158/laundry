import { describe, expect, it } from "vitest";
import { dayjs, formatDate, formatDateTime, formatJalali, jalaliMonthRange } from "./date";
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
    expect(formatJalali(parseJalali("1405-09-03 14:30")!, "YYYY-MM-DD HH:mm")).toBe("1405-09-03 14:30");
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
  const azar3 = dayjs("2026-11-24T10:30:00Z");

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

  it("formats with Jalali tokens and keeps bracketed text", () => {
    expect(formatJalali(azar3, "dddd D MMMM YYYY [ساعت] HH:mm")).toBe("سه‌شنبه 3 آذر 1405 ساعت 14:00");
  });

  it("handles the days jalaliday 3.1.1 got wrong", () => {
    expect(formatDate("2028-01-01T12:00:00Z")).toBe("۱۴۰۶/۱۰/۱۱");
    expect(config.getDate(dayjs("2024-02-29T12:00:00"))).toBe(10);
  });

  it("keeps the day within the month when moving between months", () => {
    // 31 Shahrivar + 1 month → 30 Mehr (Mehr has 30 days).
    const shahrivar31 = dayjs("2026-09-22T09:00:00");
    const next = config.addMonth(shahrivar31, 1);
    expect(config.locale.format("fa", next, "YYYY/MM/DD HH:mm")).toBe("۱۴۰۵/۰۷/۳۰ ۰۹:۰۰");
    expect(config.locale.format("fa", config.addMonth(shahrivar31, -7), "YYYY/MM/DD")).toBe("۱۴۰۴/۱۱/۳۰");
  });

  it("parses what it formats", () => {
    const text = config.locale.format("fa", azar3, "YYYY/MM/DD");
    const parsed = config.locale.parse("fa", text, ["YYYY/MM/DD"]);
    expect(parsed && config.getDate(parsed)).toBe(3);
  });
});

describe("jalaliMonthRange", () => {
  const today = dayjs(new Date(2026, 10, 25, 15, 0)); // ۱۴۰۵/۰۹/۰۴

  it("spans the current Jalali month", () => {
    const [from, to] = jalaliMonthRange(0, today);
    expect(formatJalali(from, "YYYY/MM/DD HH:mm")).toBe("1405/09/01 00:00");
    expect(formatJalali(to, "YYYY/MM/DD")).toBe("1405/09/30");
  });

  it("goes back across the new year, Esfand included", () => {
    const [from, to] = jalaliMonthRange(-9, today);
    expect(formatJalali(from, "YYYY/MM/DD")).toBe("1404/12/01");
    // 1404 is not a leap year: Esfand has 29 days.
    expect(formatJalali(to, "YYYY/MM/DD")).toBe("1404/12/29");
    expect(formatJalali(jalaliMonthRange(-8, today)[0], "YYYY/MM/DD")).toBe("1405/01/01");
  });
});
