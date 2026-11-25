import dayjs, { type Dayjs } from "dayjs";
import jalaliday from "jalaliday/dayjs";
import "dayjs/locale/fa";
import customParseFormat from "dayjs/plugin/customParseFormat";
import localeData from "dayjs/plugin/localeData";
import relativeTime from "dayjs/plugin/relativeTime";
import weekday from "dayjs/plugin/weekday";
import weekOfYear from "dayjs/plugin/weekOfYear";

dayjs.extend(customParseFormat);
dayjs.extend(localeData);
dayjs.extend(weekday);
dayjs.extend(weekOfYear);
dayjs.extend(relativeTime);
dayjs.extend(jalaliday);

export { dayjs };
export type { Dayjs };

/** A moment in the Jalali calendar and Persian locale. */
export function jalali(value?: string | number | Date | Dayjs | null): Dayjs {
  return dayjs(value ?? undefined).calendar("jalali").locale("fa");
}

/** e.g. "۱۴۰۵/۰۹/۰۴" */
export function formatDate(value: string | Date | Dayjs | null | undefined): string {
  if (!value) return "—";
  return toFa(jalali(value).format("YYYY/MM/DD"));
}

/** e.g. "۱۴۰۵/۰۹/۰۴ ۱۴:۳۰" */
export function formatDateTime(value: string | Date | Dayjs | null | undefined): string {
  if (!value) return "—";
  return toFa(jalali(value).format("YYYY/MM/DD HH:mm"));
}

/** e.g. "۴ آذر ۱۴۰۵" */
export function formatLongDate(value: string | Date | Dayjs): string {
  return toFa(jalali(value).format("D MMMM YYYY"));
}

function toFa(text: string): string {
  return text.replace(/\d/g, (digit) => "۰۱۲۳۴۵۶۷۸۹"[Number(digit)]);
}
