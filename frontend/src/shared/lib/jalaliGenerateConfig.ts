import type generatePicker from "antd/es/date-picker/generatePicker";
import { dayjs, JALALI_MONTHS, toJalali, type Dayjs } from "./date";
import { toLatinDigits, toPersianDigits } from "./digits";

type GenerateConfig = Parameters<typeof generatePicker<Dayjs>>[0];

/** Saturday: the Iranian week starts on شنبه. */
const WEEK_FIRST_DAY = 6;
const SHORT_WEEK_DAYS = ["ی", "د", "س", "چ", "پ", "ج", "ش"];

const j = (value: Dayjs): Dayjs => (value.calendar("jalali") as Dayjs).locale("fa");

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
  const month = Number(m);
  const day = Number(d);
  if (month < 1 || month > 12 || day < 1) {
    return null;
  }
  const first = dayjs(`${y}-${m.padStart(2, "0")}-01`, { jalali: true });
  const parsed = toJalali(first);
  if (!parsed.isValid() || day > parsed.endOf("month").date()) {
    return null;
  }
  return parsed
    .date(day)
    .hour(Number(hh))
    .minute(Number(mm))
    .second(Number(ss))
    .millisecond(0);
}

/**
 * Ant Design picker configuration that works on the Jalali calendar.
 * Every value it produces is a Jalali dayjs object, so year/month/date
 * getters and setters, adding months and end-of-month all follow the
 * Persian calendar. The moment in time is unchanged, so values can be
 * sent to the API with `.toISOString()` as usual.
 */
export const jalaliGenerateConfig: GenerateConfig = {
  getNow: () => toJalali(dayjs()),
  getFixedDate: (fixed) => toJalali(dayjs(fixed, ["YYYY-M-DD", "YYYY-MM-DD"])),
  getEndDate: (date) => j(date).endOf("month"),
  getWeekDay: (date) => j(date).day(),
  getYear: (date) => j(date).year(),
  getMonth: (date) => j(date).month(),
  getDate: (date) => j(date).date(),
  getHour: (date) => date.hour(),
  getMinute: (date) => date.minute(),
  getSecond: (date) => date.second(),
  getMillisecond: (date) => date.millisecond(),

  addYear: (date, diff) => j(date).add(diff, "year"),
  addMonth: (date, diff) => j(date).add(diff, "month"),
  addDate: (date, diff) => j(date).add(diff, "day"),
  setYear: (date, year) => j(date).year(year),
  setMonth: (date, month) => j(date).month(month),
  setDate: (date, value) => j(date).date(value),
  setHour: (date, hour) => j(date).hour(hour),
  setMinute: (date, minute) => j(date).minute(minute),
  setSecond: (date, second) => j(date).second(second),
  setMillisecond: (date, ms) => j(date).millisecond(ms),

  isAfter: (a, b) => a.isAfter(b),
  isValidate: (date) => date.isValid(),

  locale: {
    getWeekFirstDay: () => WEEK_FIRST_DAY,
    getWeekFirstDate: (_locale, date) => {
      const value = j(date);
      return value.subtract((value.day() - WEEK_FIRST_DAY + 7) % 7, "day").startOf("day");
    },
    getWeek: (_locale, date) => {
      const value = j(date);
      const yearStart = value.startOf("year");
      const offset = (yearStart.day() - WEEK_FIRST_DAY + 7) % 7;
      return Math.floor((value.diff(yearStart, "day") + offset) / 7) + 1;
    },
    getShortWeekDays: () => SHORT_WEEK_DAYS,
    getShortMonths: () => [...JALALI_MONTHS],
    format: (_locale, date, format) => toPersianDigits(j(date).format(format)),
    parse: (_locale, text) => parseJalali(text),
  },
};
