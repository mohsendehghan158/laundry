const PERSIAN = "۰۱۲۳۴۵۶۷۸۹";
const ARABIC = "٠١٢٣٤٥٦٧٨٩";

/** Replaces Persian and Arabic-Indic digits with ASCII digits. */
export function toLatinDigits(value: string): string {
  return value.replace(/[۰-۹٠-٩]/g, (d) => {
    const persian = PERSIAN.indexOf(d);
    return String(persian >= 0 ? persian : ARABIC.indexOf(d));
  });
}

/** Replaces ASCII digits with Persian digits. */
export function toPersianDigits(value: string | number): string {
  return String(value).replace(/[0-9]/g, (d) => PERSIAN[Number(d)]);
}

/** Formats an integer with Persian digits and thousands separators. */
export function formatNumber(value: number | null | undefined, locale = "fa"): string {
  if (value === null || value === undefined || Number.isNaN(value)) {
    return "";
  }
  return new Intl.NumberFormat(locale === "fa" ? "fa-IR" : "en-US").format(value);
}
