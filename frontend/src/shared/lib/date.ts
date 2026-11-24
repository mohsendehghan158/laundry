import dayjs, { type Dayjs } from "dayjs";
import "dayjs/locale/fa";
import customParseFormat from "dayjs/plugin/customParseFormat";
import localeData from "dayjs/plugin/localeData";
import weekday from "dayjs/plugin/weekday";
import jalaliday from "jalaliday/dayjs";
import { toPersianDigits } from "./digits";

dayjs.extend(customParseFormat);
dayjs.extend(localeData);
dayjs.extend(weekday);
dayjs.extend(jalaliday);

export { dayjs };
export type { Dayjs };

export const JALALI_MONTHS = [
  "فروردین",
  "اردیبهشت",
  "خرداد",
  "تیر",
  "مرداد",
  "شهریور",
  "مهر",
  "آبان",
  "آذر",
  "دی",
  "بهمن",
  "اسفند",
] as const;

/** The same moment as a Jalali, Persian-locale dayjs object. */
export function toJalali(value: Dayjs | Date | string | number): Dayjs {
  return dayjs(value).calendar("jalali").locale("fa");
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
  return toPersianDigits(toJalali(date).format(withTime ? "YYYY/MM/DD HH:mm" : "YYYY/MM/DD"));
}

export function formatDate(value: string | null | undefined): string {
  return formatDateTime(value, false);
}
