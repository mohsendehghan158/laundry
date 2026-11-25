import { describe, expect, it } from "vitest";
import { compactNumber, niceTop } from "./scale";

describe("niceTop", () => {
  it.each([
    [0, 2],
    [1, 2],
    [3, 4],
    [7, 8],
    [10, 10],
    [12, 20],
    [11, 20],
    [45, 60],
    [352_000, 400_000],
    [3_520_000, 4_000_000],
    [9_100_000, 10_000_000],
  ])("rounds %d up to %d", (max, top) => {
    expect(niceTop(max)).toBe(top);
  });

  it("keeps the middle gridline a whole number", () => {
    for (let max = 1; max < 500; max++) {
      expect(Number.isInteger(niceTop(max) / 2)).toBe(true);
    }
  });
});

describe("compactNumber", () => {
  it("abbreviates large amounts in Persian", () => {
    const plain = (value: number) => compactNumber(value).replace(/\s/g, " ");
    expect(plain(352_000)).toBe("۳۵۲ هزار");
    expect(plain(1_200_000)).toBe("۱٫۲ میلیون");
    expect(compactNumber(0)).toBe("۰");
  });
});
