import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";
import en from "./en.json";
import fa from "./fa.json";

function keys(messages: object, prefix = ""): string[] {
  return Object.entries(messages).flatMap(([key, value]) =>
    typeof value === "object" && value !== null ? keys(value, `${prefix}${key}.`) : [`${prefix}${key}`],
  );
}

describe("translations", () => {
  it("Persian and English have the same keys", () => {
    expect(keys(en).sort()).toEqual(keys(fa).sort());
  });

  it("every permission and role of the backend registry has a label", () => {
    // The registry is the single list of permissions (docs/adr/0004).
    const registry = readFileSync(
      resolve(process.cwd(), "../backend/app/Modules/Identity/Support/PermissionRegistry.php"),
      "utf8",
    );
    const permissions = [...new Set(registry.match(/'[a-z_]+\.[a-z_]+'/g)?.map((quoted) => quoted.slice(1, -1)))];
    const groups = [...new Set(permissions.map((name) => name.split(".")[0]))];
    const roles = [...registry.matchAll(/'([a-z_]+)' => \[/g)].map((match) => match[1]).filter((name) => !groups.includes(name));

    expect(permissions.length).toBeGreaterThan(10);
    expect(Object.keys(fa.roles.perms).sort()).toEqual(permissions.sort());
    groups.forEach((group) => expect(fa.roles.groups).toHaveProperty(group));
    [...roles, "owner"].forEach((role) => expect(fa.roles.names).toHaveProperty(role));
  });
});
