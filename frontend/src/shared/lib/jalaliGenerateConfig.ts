import type generatePicker from "antd/es/date-picker/generatePicker";
import { dayjs, formatJalali, fromJalali, JALALI_MONTHS, jalaliParts, type Dayjs } from "./date";
import { toLatinDigits, toPersianDigits } from "./digits";
import { jalaliMonthLength } from "./jalali";

type GenerateConfig = Parameters<typeof generatePicker<Dayjs>>[0];

/** Saturday: the Iranian week starts on شنبه. */
const WEEK_FIRST_DAY = 6;
const SHORT_WEEK_DAYS = ["ی", "د", "س", "چ", "پ", "ج", "ش"];

/** Same time of day, another Jalali date; the day is kept within the month. */
function withJalali(value: Dayjs, jy: number, jm: number, jd: number): Dayjs {
  // Normalize a month outside 1–12 into the right year.
  const months = jy * 12 + (jm - 1);
  const year = Math.floor(months / 12);
  const month = (((months % 12) + 12) % 12) + 1;
  return fromJalali(year, month, Math.min(Math.max(jd, 1), jalaliMonthLength(year, month)), value);
}

/**
 * Reads Jalali text typed into the picker: "۱۴۰۵/۹/۳", "1405-09-03",
 * optionally followed by a time "14:30" or "14:30:15".
 */
export function parseJalali(text: string): Dayjs | null {
  const match = toLatinDigits(text)
    .trim()
    .match(/^(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/);
  if (!match) {
    return null;
  }
  const [, y, m, d, hh = "0", mm = "0", ss = "0"] = match;
  const [year, month, day] = [Number(y), Number(m), Number(d)];
  if (month < 1 || month > 12 || day < 1 || day > jalaliMonthLength(year, month)) {
    return null;
  }
  return fromJalali(year, month, day).hour(Number(hh)).minute(Number(mm)).second(Number(ss)).millisecond(0);
}

/**
 * Ant Design picker configuration on the Jalali calendar. Values are
 * ordinary dayjs objects (Gregorian underneath); only what the picker
 * reads and sets is translated, so values go to the API with
 * `.toISOString()` as usual.
 */
export const jalaliGenerateConfig: GenerateConfig = {
  getNow: () => dayjs(),
  getFixedDate: (fixed) => dayjs(fixed, ["YYYY-M-DD", "YYYY-MM-DD"]),
  getEndDate: (date) => {
    const [jy, jm] = jalaliParts(date);
    return withJalali(date, jy, jm, jalaliMonthLength(jy, jm));
  },
  getWeekDay: (date) => date.day(),
  getYear: (date) => jalaliParts(date)[0],
  getMonth: (date) => jalaliParts(date)[1] - 1,
  getDate: (date) => jalaliParts(date)[2],
  getHour: (date) => date.hour(),
  getMinute: (date) => date.minute(),
  getSecond: (date) => date.second(),
  getMillisecond: (date) => date.millisecond(),

  addYear: (date, diff) => {
    const [jy, jm, jd] = jalaliParts(date);
    return withJalali(date, jy + diff, jm, jd);
  },
  addMonth: (date, diff) => {
    const [jy, jm, jd] = jalaliParts(date);
    return withJalali(date, jy, jm + diff, jd);
  },
  addDate: (date, diff) => date.add(diff, "day"),
  setYear: (date, year) => {
    const [, jm, jd] = jalaliParts(date);
    return withJalali(date, year, jm, jd);
  },
  setMonth: (date, month) => {
    const [jy, , jd] = jalaliParts(date);
    return withJalali(date, jy, month + 1, jd);
  },
  setDate: (date, value) => {
    const [jy, jm] = jalaliParts(date);
    return withJalali(date, jy, jm, 1).add(value - 1, "day");
  },
  setHour: (date, hour) => date.hour(hour),
  setMinute: (date, minute) => date.minute(minute),
  setSecond: (date, second) => date.second(second),
  setMillisecond: (date, ms) => date.millisecond(ms),

  isAfter: (a, b) => a.isAfter(b),
  isValidate: (date) => date.isValid(),

  locale: {
    getWeekFirstDay: () => WEEK_FIRST_DAY,
    getWeekFirstDate: (_locale, date) => date.subtract((date.day() - WEEK_FIRST_DAY + 7) % 7, "day").startOf("day"),
    getWeek: (_locale, date) => {
      const [jy] = jalaliParts(date);
      const yearStart = fromJalali(jy, 1, 1);
      const offset = (yearStart.day() - WEEK_FIRST_DAY + 7) % 7;
      return Math.floor((date.startOf("day").diff(yearStart, "day") + offset) / 7) + 1;
    },
    getShortWeekDays: () => SHORT_WEEK_DAYS,
    getShortMonths: () => [...JALALI_MONTHS],
    format: (_locale, date, format) => toPersianDigits(formatJalali(date, format)),
    parse: (_locale, text) => parseJalali(text),
  },
};
