import { describe, expect, it } from "vitest";
import { safePortalNext } from "./safePortalNext";

describe("safePortalNext", () => {
  it("follows paths inside the portal", () => {
    expect(safePortalNext("/my/orders/01JD3")).toBe("/my/orders/01JD3");
    expect(safePortalNext("/my")).toBe("/my");
  });

  it("never sends a customer to the staff panel, off the site or back to sign-in", () => {
    for (const value of ["/dashboard", "/mystery", "//evil.com", "https://evil.com", "/my/login", "/my/login?next=/my", null]) {
      expect(safePortalNext(value)).toBe("/my");
    }
  });
});
