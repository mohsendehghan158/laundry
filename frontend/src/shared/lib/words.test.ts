import { describe, expect, it } from "vitest";
import { toPersianWords } from "./words";

describe("toPersianWords", () => {
  it("writes numbers the way invoices do", () => {
    expect(toPersianWords(0)).toBe("صفر");
    expect(toPersianWords(7)).toBe("هفت");
    expect(toPersianWords(15)).toBe("پانزده");
    expect(toPersianWords(40)).toBe("چهل");
    expect(toPersianWords(321)).toBe("سیصد و بیست و یک");
    expect(toPersianWords(1001)).toBe("یک هزار و یک");
    expect(toPersianWords(8_800_000)).toBe("هشت میلیون و هشتصد هزار");
    expect(toPersianWords(2_000_050_000)).toBe("دو میلیارد و پنجاه هزار");
    expect(toPersianWords(-12)).toBe("منفی دوازده");
  });
});
