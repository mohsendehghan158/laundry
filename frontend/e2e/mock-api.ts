import type { BrowserContext, Page, Route } from "@playwright/test";

export const OWNER_PERMISSIONS = [
  "organization.update",
  "branches.view", "branches.create", "branches.update", "branches.delete", "branches.access_all",
  "users.view", "users.create", "users.update", "users.delete",
  "roles.view", "roles.update",
  "settings.view", "settings.update",
  "activity.view",
];

export function authUser(overrides: Partial<{ name: string; roles: string[]; permissions: string[] }> = {}) {
  return {
    id: 1,
    name: "مدیر سامانه",
    username: "admin",
    email: null,
    mobile: null,
    locale: "fa",
    organization: { id: 1, name: "خشکشویی نمونه" },
    roles: ["owner"],
    permissions: OWNER_PERMISSIONS,
    branches: [{ id: 1, name: "شعبه مرکزی", code: "T1", type: "hybrid" }],
    ...overrides,
  };
}

const page1 = <T,>(data: T[]) => ({
  data,
  meta: { current_page: 1, last_page: 1, per_page: 25, total: data.length, from: data.length ? 1 : null, to: data.length },
});

export const branches = [
  { id: 1, name: "شعبه مرکزی", code: "T1", type: "hybrid", phone: "021-88001122", address: null, is_active: true, users_count: 3, created_at: "2026-11-20T08:00:00Z", updated_at: "2026-11-20T08:00:00Z" },
  { id: 2, name: "شعبه غرب", code: "T2", type: "store", phone: null, address: null, is_active: true, users_count: 1, created_at: "2026-11-20T08:00:00Z", updated_at: "2026-11-20T08:00:00Z" },
];

/** Signs the browser in as far as the proxy is concerned (the cookie only has to exist). */
export async function withSessionCookie(context: BrowserContext, baseURL: string) {
  await context.addCookies([{ name: "laundry_session", value: "test", url: baseURL }]);
}

interface MockOptions {
  me?: ReturnType<typeof authUser> | null;
  onRequest?: (method: string, path: string, body: unknown) => { status: number; body?: unknown } | undefined;
}

/** Answers every API call the panel makes with fixed data. */
export async function mockApi(page: Page, options: MockOptions = {}) {
  let me = options.me === undefined ? authUser() : options.me;
  const fulfil = (route: Route, status: number, body?: unknown) =>
    route.fulfill({ status, contentType: "application/json", body: body === undefined ? "" : JSON.stringify(body) });

  await page.route("**/sanctum/csrf-cookie", (route) =>
    route.fulfill({ status: 204, headers: { "Set-Cookie": "XSRF-TOKEN=test; Path=/" } }),
  );

  await page.route("**/api/v1/**", async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    const path = url.pathname.replace("/api/v1", "");
    const method = request.method();
    const body = request.postDataJSON?.() ?? null;

    const custom = options.onRequest?.(method, path, body);
    if (custom) return fulfil(route, custom.status, custom.body);

    if (path === "/auth/login" && method === "POST") {
      const credentials = body as { username: string; password: string };
      if (credentials.password !== "password") {
        return fulfil(route, 422, { message: "invalid", code: "validation_failed", errors: { username: ["نام کاربری یا رمز عبور نادرست است."] } });
      }
      me = authUser();
      return fulfil(route, 200, { data: me });
    }
    if (path === "/auth/logout") {
      me = null;
      return fulfil(route, 204);
    }
    if (path === "/me") return me ? fulfil(route, 200, { data: me }) : fulfil(route, 401, { message: "login", code: "unauthenticated" });
    if (path === "/branches" && method === "GET") return fulfil(route, 200, page1(branches));
    if (path === "/users" && method === "GET") return fulfil(route, 200, page1([]));
    if (path === "/roles") return fulfil(route, 200, { data: [] });
    if (path === "/permissions") return fulfil(route, 200, { data: [] });
    if (path === "/activity-log") return fulfil(route, 200, page1([]));
    if (path === "/settings") {
      return fulfil(route, 200, {
        data: { general: { currency_unit: "toman", timezone: "Asia/Tehran" }, tax: { vat_percent: 10, prices_include_vat: false }, receipt: { header_text: null, footer_text: null } },
      });
    }
    return fulfil(route, 404, { message: "not found", code: "not_found" });
  });
}
