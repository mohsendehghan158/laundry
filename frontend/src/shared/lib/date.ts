import dayjs, { type Dayjs } from "dayjs";
import customParseFormat from "dayjs/plugin/customParseFormat";
import { toPersianDigits } from "./digits";
import { toGregorianDate, toJalaliDate } from "./jalali";

dayjs.extend(customParseFormat);

export { dayjs };
export type { Dayjs };

export const JALALI_MONTHS = ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"] as const;

/** Sunday-first, like Date#getDay(). */
const WEEKDAYS = ["یک‌شنبه", "دوشنبه", "سه‌شنبه", "چهارشنبه", "پنج‌شنبه", "جمعه", "شنبه"] as const;
const WEEKDAYS_MIN = ["ی", "د", "س", "چ", "پ", "ج", "ش"] as const;

/** Jalali [year, month 1–12, day] of a moment, in the browser's time zone. */
export function jalaliParts(value: Dayjs): [number, number, number] {
  return toJalaliDate(value.year(), value.month() + 1, value.date());
}

/** The local moment at the given Jalali date, keeping the time of `time`. */
export function fromJalali(jy: number, jm: number, jd: number, time?: Dayjs): Dayjs {
  const [gy, gm, gd] = toGregorianDate(jy, jm, jd);
  const t = time ?? dayjs().startOf("day");
  return dayjs(new Date(gy, gm - 1, gd, t.hour(), t.minute(), t.second(), t.millisecond()));
}

const pad = (value: number, width = 2) => String(value).padStart(width, "0");

/**
 * Formats a moment on the Jalali calendar with dayjs-style tokens:
 * YYYY YY MMMM MMM MM M DD D dddd ddd dd d HH H hh h mm m ss s A a;
 * text in [brackets] is kept as is. Digits stay Latin; see formatDateTime
 * for display.
 */
export function formatJalali(value: Dayjs, format: string): string {
  const [jy, jm, jd] = jalaliParts(value);
  return format.replace(/\[([^\]]*)]|YYYY|YY|MMMM|MMM|MM|M|DD|D|dddd|ddd|dd|d|HH|H|hh|h|mm|m|ss|s|A|a/g, (token, literal: string | undefined) => {
    if (literal !== undefined) {
      return literal;
    }
    switch (token) {
      case "YYYY":
        return pad(jy, 4);
      case "YY":
        return pad(jy % 100);
      case "MMMM":
      case "MMM":
        return JALALI_MONTHS[jm - 1];
      case "MM":
        return pad(jm);
      case "M":
        return String(jm);
      case "DD":
        return pad(jd);
      case "D":
        return String(jd);
      case "dddd":
      case "ddd":
        return WEEKDAYS[value.day()];
      case "dd":
        return WEEKDAYS_MIN[value.day()];
      case "d":
        return String(value.day());
      case "A":
      case "a":
        return value.hour() < 12 ? "ق.ظ" : "ب.ظ";
      default:
        // Time tokens are the same on both calendars.
        return value.format(token);
    }
  });
}

/**
 * Formats an API timestamp for display: "۱۴۰۵/۰۹/۰۳ ۱۴:۳۰".
 * Returns an empty string for missing values.
 */
export function formatDateTime(value: string | null | undefined, withTime = true): string {
  if (!value) {
    return "";
  }
  const date = dayjs(value);
  if (!date.isValid()) {
    return "";
  }
  return toPersianDigits(formatJalali(date, withTime ? "YYYY/MM/DD HH:mm" : "YYYY/MM/DD"));
}

export function formatDate(value: string | null | undefined): string {
  return formatDateTime(value, false);
}
