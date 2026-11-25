import { describe, expect, it } from "vitest";
import { visibleNavItems } from "@/features/shell/navigation";
import { safeNext } from "@/features/auth/LoginForm";
import { hasPermission } from "./usePermission";

describe("permissions", () => {
  it("matches any of the wanted permissions", () => {
    expect(hasPermission(["users.view"], "users.view")).toBe(true);
    expect(hasPermission(["users.view"], ["roles.view", "users.view"])).toBe(true);
    expect(hasPermission(["users.view"], "users.delete")).toBe(false);
    expect(hasPermission(undefined, "users.view")).toBe(false);
  });

  it("builds the menu from permissions", () => {
    expect(visibleNavItems([]).map((item) => item.href)).toEqual(["/dashboard"]);
    expect(visibleNavItems(["branches.view", "settings.view"]).map((item) => item.href)).toEqual([
      "/dashboard",
      "/branches",
      "/settings",
    ]);
  });
});

describe("safeNext", () => {
  it("only follows same-site paths", () => {
    expect(safeNext("/users?page=2")).toBe("/users?page=2");
    expect(safeNext("//evil.example")).toBe("/dashboard");
    expect(safeNext("https://evil.example")).toBe("/dashboard");
    expect(safeNext("/login")).toBe("/dashboard");
    expect(safeNext(null)).toBe("/dashboard");
  });
});
