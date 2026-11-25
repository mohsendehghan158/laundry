import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { api, buildQuery, setUnauthenticatedHandler } from "./client";
import { ApiError } from "./errors";

const json = (status: number, body: unknown) =>
  new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } });

describe("buildQuery", () => {
  it("drops empty values and joins arrays", () => {
    expect(buildQuery({ "filter[q]": "", page: 2, sort: null, "filter[role]": ["a", "b"], "filter[is_active]": true })).toBe(
      "?page=2&filter%5Brole%5D=a%2Cb&filter%5Bis_active%5D=1",
    );
    expect(buildQuery({})).toBe("");
  });
});

describe("api", () => {
  const fetchMock = vi.fn();

  beforeEach(() => {
    vi.stubGlobal("fetch", fetchMock);
    document.cookie = "XSRF-TOKEN=token%3D1; path=/";
  });

  afterEach(() => {
    fetchMock.mockReset();
    vi.unstubAllGlobals();
    setUnauthenticatedHandler(null);
  });

  it("sends JSON with the CSRF header and same-origin credentials", async () => {
    fetchMock.mockResolvedValueOnce(json(200, { data: { id: 1 } }));
    const result = await api<{ data: { id: number } }>("/branches", { method: "POST", body: { name: "x" } });

    expect(result.data.id).toBe(1);
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("/api/v1/branches");
    expect(init.credentials).toBe("include");
    expect(init.headers["X-XSRF-TOKEN"]).toBe("token=1");
    expect(init.headers["Accept-Language"]).toBe("fa");
    expect(init.body).toBe('{"name":"x"}');
  });

  it("turns the error shape into an ApiError with field errors", async () => {
    fetchMock.mockResolvedValueOnce(
      json(422, { message: "invalid", code: "validation_failed", errors: { code: ["taken"] } }),
    );
    const error = (await api("/branches", { method: "POST", body: {} }).catch((e: unknown) => e)) as ApiError;

    expect(error).toBeInstanceOf(ApiError);
    expect(error.status).toBe(422);
    expect(error.code).toBe("validation_failed");
    expect(error.errors).toEqual({ code: ["taken"] });
  });

  it("refreshes the CSRF cookie once on 419", async () => {
    fetchMock
      .mockResolvedValueOnce(json(419, { message: "expired", code: "csrf_mismatch" }))
      .mockResolvedValueOnce(new Response(null, { status: 204 })) // /sanctum/csrf-cookie
      .mockResolvedValueOnce(new Response(null, { status: 204 }));

    await expect(api("/auth/logout", { method: "POST" })).resolves.toBeUndefined();
    expect(fetchMock.mock.calls[1][0]).toBe("/sanctum/csrf-cookie");
  });

  it("reports an expired session", async () => {
    const handler = vi.fn();
    setUnauthenticatedHandler(handler);
    fetchMock.mockResolvedValueOnce(json(401, { message: "login", code: "unauthenticated" }));

    await expect(api("/me")).rejects.toMatchObject({ status: 401 });
    expect(handler).toHaveBeenCalledOnce();
  });

  it("reports network failures as status 0", async () => {
    fetchMock.mockRejectedValueOnce(new TypeError("Failed to fetch"));
    await expect(api("/me")).rejects.toMatchObject({ status: 0, code: "network_error" });
  });
});
