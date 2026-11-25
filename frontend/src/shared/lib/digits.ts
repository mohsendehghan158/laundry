const PERSIAN = "۰۱۲۳۴۵۶۷۸۹";
const ARABIC = "٠١٢٣٤٥٦٧٨٩";

/** Replaces Persian and Arabic-Indic digits with Latin ones. */
export function toLatinDigits(value: string): string {
  return value.replace(/[۰-۹٠-٩]/g, (char) => {
    const persian = PERSIAN.indexOf(char);
    return String(persian >= 0 ? persian : ARABIC.indexOf(char));
  });
}

/** Replaces Latin digits with Persian ones, for display. */
export function toPersianDigits(value: string | number): string {
  return String(value).replace(/\d/g, (digit) => PERSIAN[Number(digit)]);
}

/** Formats an integer with Persian digits and thousands separators. */
export function formatNumber(value: number, locale: "fa" | "en" = "fa"): string {
  return new Intl.NumberFormat(locale === "fa" ? "fa-IR" : "en-US", {
    maximumFractionDigits: 2,
  }).format(value);
}
