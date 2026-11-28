const ONES = ["", "یک", "دو", "سه", "چهار", "پنج", "شش", "هفت", "هشت", "نه"];
const TEENS = ["ده", "یازده", "دوازده", "سیزده", "چهارده", "پانزده", "شانزده", "هفده", "هجده", "نوزده"];
const TENS = ["", "", "بیست", "سی", "چهل", "پنجاه", "شصت", "هفتاد", "هشتاد", "نود"];
const HUNDREDS = ["", "صد", "دویست", "سیصد", "چهارصد", "پانصد", "ششصد", "هفتصد", "هشتصد", "نهصد"];
const SCALES = ["", "هزار", "میلیون", "میلیارد", "هزار میلیارد"];

function belowThousand(n: number): string[] {
  const parts: string[] = [];
  const hundreds = Math.floor(n / 100);
  const rest = n % 100;
  if (hundreds) {
    parts.push(HUNDREDS[hundreds]);
  }
  if (rest >= 10 && rest < 20) {
    parts.push(TEENS[rest - 10]);
  } else {
    if (rest >= 20) {
      parts.push(TENS[Math.floor(rest / 10)]);
    }
    if (rest % 10) {
      parts.push(ONES[rest % 10]);
    }
  }
  return parts;
}

/**
 * A whole number in Persian words, as written on invoices and cheques:
 * 8_800_000 → "هشت میلیون و هشتصد هزار".
 */
export function toPersianWords(value: number): string {
  const n = Math.trunc(Math.abs(value));
  if (n === 0) {
    return "صفر";
  }
  const groups: string[] = [];
  let rest = n;
  for (let scale = 0; rest > 0 && scale < SCALES.length; scale++) {
    const chunk = rest % 1000;
    rest = Math.floor(rest / 1000);
    if (chunk) {
      const words = belowThousand(chunk).join(" و ");
      groups.unshift(SCALES[scale] ? `${words} ${SCALES[scale]}` : words);
    }
  }
  return (value < 0 ? "منفی " : "") + groups.join(" و ");
}
