import { describe, expect, it } from "vitest";
import en from "./en.json";
import fa from "./fa.json";

function keys(value: unknown, prefix = ""): string[] {
  if (value && typeof value === "object" && !Array.isArray(value)) {
    return Object.entries(value).flatMap(([key, child]) => keys(child, prefix ? `${prefix}.${key}` : key));
  }
  return [prefix];
}

describe("message catalogs", () => {
  it("have the same keys in every language", () => {
    expect(keys(en).sort()).toEqual(keys(fa).sort());
  });

  it("label every permission and built-in role the backend defines", async () => {
    const { readFile } = await import("node:fs/promises");
    const { resolve } = await import("node:path");
    const registry = await readFile(resolve(process.cwd(), "../backend/app/Modules/Identity/Support/PermissionRegistry.php"), "utf8");
    const permissions = Array.from(registry.matchAll(/'([a-z_]+\.[a-z_]+)'/g), (match) => match[1]);
    expect(permissions.length).toBeGreaterThan(0);
    for (const permission of new Set(permissions)) {
      expect(keys(fa)).toContain(`roles.permissions.${permission}`);
    }
    const roles = Array.from(registry.matchAll(/^\s+'([a-z_]+)' => \[/gm), (match) => match[1]).filter((name) => !keys(fa.roles.groups).includes(name));
    for (const role of ["owner", ...roles]) {
      expect(keys(fa)).toContain(`roles.names.${role}`);
    }
  });
});
