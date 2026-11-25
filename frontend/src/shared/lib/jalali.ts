/**
 * Gregorian ↔ Jalali (Persian calendar) conversion, a port of the widely
 * used jalaali algorithm (valid for Jalali years −61 to 3177). The server
 * uses the same arithmetic (backend/app/Support/Text/Jalali.php).
 *
 * We do not use the jalaliday package: its 3.1.1 release shifts every
 * 1 January – 29 February of a Gregorian leap year by one day.
 */

const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

const div = (a: number, b: number) => Math.trunc(a / b);
const mod = (a: number, b: number) => a - Math.trunc(a / b) * b;

/** Leap-year state and the March day of Farvardin 1 for a Jalali year. */
function jalCal(jy: number): { leap: number; gy: number; march: number } {
  const gy = jy + 621;
  let leapJ = -14;
  let jp = BREAKS[0];
  let jump = 0;

  for (let i = 1; i < BREAKS.length; i++) {
    const jm = BREAKS[i];
    jump = jm - jp;
    if (jy < jm) {
      break;
    }
    leapJ += div(jump, 33) * 8 + div(mod(jump, 33), 4);
    jp = jm;
  }

  let n = jy - jp;
  leapJ += div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
  if (mod(jump, 33) === 4 && jump - n === 4) {
    leapJ += 1;
  }

  const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
  const march = 20 + leapJ - leapG;

  if (jump - n < 6) {
    n = n - jump + div(jump + 4, 33) * 33;
  }
  let leap = mod(mod(n + 1, 33) - 1, 4);
  if (leap === -1) {
    leap = 4;
  }

  return { leap, gy, march };
}

/** Julian day number of a Gregorian date. */
function g2d(gy: number, gm: number, gd: number): number {
  const d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;
  return d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
}

/** Gregorian date of a Julian day number. */
function d2g(jdn: number): [number, number, number] {
  let j = 4 * jdn + 139361631;
  j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
  const i = div(mod(j, 1461), 4) * 5 + 308;
  const gd = div(mod(i, 153), 5) + 1;
  const gm = mod(div(i, 153), 12) + 1;
  const gy = div(j, 1461) - 100100 + div(8 - gm, 6);
  return [gy, gm, gd];
}

/** [year, month 1–12, day] on the Jalali calendar. */
export function toJalaliDate(gy: number, gm: number, gd: number): [number, number, number] {
  const jdn = g2d(gy, gm, gd);
  let jy = gy - 621;
  const { leap, march } = jalCal(jy);
  let k = jdn - g2d(gy, 3, march);

  if (k >= 0) {
    if (k <= 185) {
      return [jy, 1 + div(k, 31), mod(k, 31) + 1];
    }
    k -= 186;
  } else {
    jy -= 1;
    k += 179;
    if (leap === 1) {
      k += 1;
    }
  }

  return [jy, 7 + div(k, 30), mod(k, 30) + 1];
}

/** [year, month 1–12, day] on the Gregorian calendar. */
export function toGregorianDate(jy: number, jm: number, jd: number): [number, number, number] {
  const { gy, march } = jalCal(jy);
  return d2g(g2d(gy, 3, march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1);
}

export function isJalaliLeapYear(jy: number): boolean {
  return jalCal(jy).leap === 0;
}

/** 31 days for the first six months, 30 for the next five, 29 or 30 for Esfand. */
export function jalaliMonthLength(jy: number, jm: number): number {
  if (jm <= 6) {
    return 31;
  }
  if (jm <= 11) {
    return 30;
  }
  return isJalaliLeapYear(jy) ? 30 : 29;
}
