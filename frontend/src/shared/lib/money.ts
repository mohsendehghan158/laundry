import { formatNumber, toLatinDigits } from "./digits";

/** Amounts are always stored and sent in rials; the unit only changes display. */
export type CurrencyUnit = "toman" | "rial";

const UNIT_LABEL: Record<CurrencyUnit, string> = { toman: "تومان", rial: "ریال" };

/** Converts rials to the displayed unit. */
export function fromRials(rials: number, unit: CurrencyUnit): number {
  return unit === "toman" ? rials / 10 : rials;
}

/** Converts an amount in the displayed unit back to rials. */
export function toRials(amount: number, unit: CurrencyUnit): number {
  return Math.round(unit === "toman" ? amount * 10 : amount);
}

/** e.g. formatMoney(1250000, "toman") → "۱۲۵٬۰۰۰ تومان" */
export function formatMoney(rials: number, unit: CurrencyUnit, withUnit = true): string {
  const text = formatNumber(fromRials(rials, unit));
  return withUnit ? `${text} ${UNIT_LABEL[unit]}` : text;
}

export function currencyLabel(unit: CurrencyUnit): string {
  return UNIT_LABEL[unit];
}

/**
 * Reads what a person typed into an amount field: accepts Persian or Latin
 * digits and any thousands separators. Returns null when nothing usable is left.
 */
export function parseAmount(text: string): number | null {
  const cleaned = toLatinDigits(text).replace(/[^\d.]/g, "");
  if (cleaned === "" || cleaned === ".") return null;
  const value = Number(cleaned);
  return Number.isFinite(value) ? value : null;
}
