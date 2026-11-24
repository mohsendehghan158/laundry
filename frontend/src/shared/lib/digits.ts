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

/**
 * Formatter for antd InputNumber: shows what is being typed as is, and
 * otherwise the value with Persian digits and separators. An empty field
 * stays empty (antd passes "" for it, which must not become 0).
 */
export function inputNumberFormatter(value: number | string | undefined | null, info: { userTyping: boolean; input: string }): string {
  if (info.userTyping) {
    return info.input;
  }
  if (value === undefined || value === null || value === "") {
    return "";
  }
  return formatNumber(Number(value));
}
