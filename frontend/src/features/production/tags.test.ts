import { describe, expect, it } from "vitest";
import { normalizeTag, splitTags } from "./tags";

describe("tags", () => {
  it("normalizes what a scanner or keyboard sends", () => {
    expect(normalizeTag(" t۱-۰۰۰۴۲-۰۳ ")).toBe("T1-00042-03");
  });

  it("splits a pasted batch and drops repeats", () => {
    expect(splitTags("T1-00042-01\nt1-00042-02, T1-00042-01 ؛ ")).toEqual(["T1-00042-01", "T1-00042-02"]);
    expect(splitTags("a;b،c")).toEqual(["A", "B", "C"]);
    expect(splitTags("   ")).toEqual([]);
  });
});
