/**
 * The top of a bar chart's value axis: the smallest of 1, 2, 4, 6, 8 or 10 times
 * a power of ten that holds the largest value, so the middle gridline
 * (half of it) is a round number too.
 */
export function niceTop(max: number): number {
  if (!(max > 0)) {
    return 2;
  }
  const power = 10 ** Math.floor(Math.log10(max));
  const step = [1, 2, 4, 6, 8, 10].find((s) => max <= s * power) ?? 10;
  return Math.max(2, step * power);
}

const COMPACT = new Intl.NumberFormat("fa-IR", { notation: "compact", maximumFractionDigits: 1 });

/** "۳۵۲ هزار", "۱٫۲ میلیون": short axis labels. */
export function compactNumber(value: number): string {
  return COMPACT.format(value);
}
