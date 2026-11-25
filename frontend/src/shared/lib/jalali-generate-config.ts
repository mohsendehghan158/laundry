import type { GenerateConfig } from "@rc-component/picker/lib/generate";
import { dayjs, type Dayjs } from "./date";
import { toLatinDigits, toPersianDigits } from "./digits";

/**
 * Date logic for Ant Design pickers in the Jalali calendar. antd-jalali only
 * supports antd 5, so this is our own, built on dayjs + jalaliday: every value
 * is kept in the Jalali calendar, so year/month/day arithmetic follows it.
 * Values still compare and serialize as ordinary instants.
 */
const JALALI_MONTHS = [
  "فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور",
  "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند",
];
const WEEKDAYS_MIN = ["ش", "ی", "د", "س", "چ", "پ", "ج"]; // from Saturday

const j = (value: Dayjs): Dayjs => dayjs(value).calendar("jalali").locale("fa");

/** Supported tokens when parsing typed text: YYYY, MM/M, DD/D, HH, mm, ss. */
function parseWithFormat(text: string, format: string): Dayjs | null {
  const tokens = format.match(/YYYY|MM|M|DD|D|HH|H|mm|ss/g) ?? [];
  const numbers = toLatinDigits(text).match(/\d+/g) ?? [];
  if (tokens.length === 0 || numbers.length !== tokens.length) return null;

  const parts: Record<string, number> = { Y: 0, M: 1, D: 1, H: 0, m: 0, s: 0 };
  tokens.forEach((token, index) => {
    parts[token[0]] = Number(numbers[index]);
  });

  const { Y, M, D, H, m, s } = parts;
  if (Y < 1000 || M < 1 || M > 12 || D < 1 || D > 31 || H > 23 || m > 59 || s > 59) return null;

  const date = dayjs(`${Y}-${pad(M)}-${pad(D)}`, { jalali: true } as never)
    .calendar("jalali")
    .hour(H)
    .minute(m)
    .second(s);
  // Reject overflow such as 1405/12/30 in a common year.
  if (!date.isValid() || date.month() + 1 !== M || date.date() !== D) return null;
  return date.locale("fa");
}

const pad = (n: number) => String(n).padStart(2, "0");

export const jalaliGenerateConfig: GenerateConfig<Dayjs> = {
  getNow: () => j(dayjs()),
  getFixedDate: (fixed) => {
    const [y, m, d] = fixed.split("-").map(Number);
    return j(dayjs(`${y}-${pad(m)}-${pad(d)}`, { jalali: true } as never));
  },
  getEndDate: (date) => j(date).endOf("month"),
  getWeekDay: (date) => dayjs(date).day(),
  getYear: (date) => j(date).year(),
  getMonth: (date) => j(date).month(),
  getDate: (date) => j(date).date(),
  getHour: (date) => j(date).hour(),
  getMinute: (date) => j(date).minute(),
  getSecond: (date) => j(date).second(),
  getMillisecond: (date) => j(date).millisecond(),

  addYear: (date, diff) => j(date).add(diff, "year"),
  addMonth: (date, diff) => j(date).add(diff, "month"),
  addDate: (date, diff) => j(date).add(diff, "day"),
  setYear: (date, year) => j(date).year(year),
  setMonth: (date, month) => j(date).month(month),
  setDate: (date, num) => j(date).date(num),
  setHour: (date, hour) => j(date).hour(hour),
  setMinute: (date, minute) => j(date).minute(minute),
  setSecond: (date, second) => j(date).second(second),
  setMillisecond: (date, ms) => j(date).millisecond(ms),

  isAfter: (a, b) => dayjs(a).isAfter(dayjs(b)),
  isValidate: (date) => dayjs(date).isValid(),

  locale: {
    // The Iranian week starts on Saturday.
    getWeekFirstDay: () => 6,
    getWeekFirstDate: (_locale, date) => {
      const value = j(date);
      return value.subtract((value.day() + 1) % 7, "day").startOf("day");
    },
    getWeek: (_locale, date) => {
      const value = j(date);
      const firstOfYear = value.startOf("year");
      const offset = (firstOfYear.day() + 1) % 7;
      return Math.floor((value.diff(firstOfYear, "day") + offset) / 7) + 1;
    },
    getShortWeekDays: () => WEEKDAYS_MIN,
    getShortMonths: () => JALALI_MONTHS,
    // Persian digits on screen; parse() accepts either digit set back.
    format: (_locale, date, format) => toPersianDigits(j(date).format(format)),
    parse: (_locale, text, formats) => {
      for (const format of formats) {
        const date = parseWithFormat(text, format);
        if (date) return date;
      }
      return null;
    },
  },
};

export { JALALI_MONTHS };
