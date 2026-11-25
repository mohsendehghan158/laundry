import { describe, expect, it } from "vitest";
import { previewMessage } from "./api";

describe("previewMessage", () => {
  it("fills known placeholders and leaves unknown ones visible", () => {
    expect(previewMessage("قبض {ticket} برای {customer} — {nope}", { ticket: "T1-00042", customer: "سارا" })).toBe("قبض T1-00042 برای سارا — {nope}");
  });
});
