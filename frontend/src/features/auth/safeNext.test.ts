import { describe, expect, it } from "vitest";
import { safeNext } from "./safeNext";

describe("safeNext", () => {
  it("follows same-site paths", () => {
    expect(safeNext("/users?page=2")).toBe("/users?page=2");
  });

  it("refuses anything that could leave the site or loop", () => {
    for (const value of ["//evil.com", "/\\evil.com", "https://evil.com", "javascript:alert(1)", "/login", "", null, undefined]) {
      expect(safeNext(value)).toBe("/dashboard");
    }
  });
});
