import { toLatinDigits } from "@/shared/lib/digits";

/** One code as stored: Latin digits, capitals, no spaces. */
export function normalizeTag(code: string): string {
  return toLatinDigits(code).replace(/\s+/g, "").toUpperCase();
}

/**
 * The tags in what was typed, pasted or scanned: separated by spaces,
 * new lines, commas or semicolons; duplicates dropped, order kept.
 */
export function splitTags(text: string): string[] {
  return [...new Set(toLatinDigits(text).split(/[\s,،;؛]+/).map(normalizeTag).filter(Boolean))];
}
