import { formatNumber, toLatinDigits } from "./digits";

/** Display unit chosen in settings; amounts are always stored in rial. */
export type CurrencyUnit = "toman" | "rial";

const RIAL_PER_TOMAN = 10;

/** Converts a stored rial amount into the display unit. */
export function fromRial(rial: number, unit: CurrencyUnit): number {
  return unit === "toman" ? rial / RIAL_PER_TOMAN : rial;
}

/** Converts an amount typed in the display unit back into whole rials. */
export function toRial(amount: number, unit: CurrencyUnit): number {
  return Math.round(unit === "toman" ? amount * RIAL_PER_TOMAN : amount);
}

/** "۱۲۵٬۰۰۰" — the amount in the display unit, without the unit name. */
export function formatMoney(rial: number | null | undefined, unit: CurrencyUnit, locale = "fa"): string {
  if (rial === null || rial === undefined) {
    return "";
  }
  return formatNumber(fromRial(rial, unit), locale);
}

/**
 * Reads what a person typed into a money field: accepts Persian, Arabic and
 * ASCII digits and any thousands separator. Returns null for empty input.
 */
export function parseAmount(text: string | null | undefined): number | null {
  if (!text) {
    return null;
  }
  const normalized = toLatinDigits(text)
    .replace(/[٫]/g, ".")
    .replace(/[^0-9.-]/g, "");
  if (normalized === "" || normalized === "-" || normalized === ".") {
    return null;
  }
  const value = Number(normalized);
  return Number.isFinite(value) ? value : null;
}
