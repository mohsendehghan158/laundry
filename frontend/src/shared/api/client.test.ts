import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { api, ApiError, buildQuery, onUnauthenticated } from "./client";

function json(status: number, body: unknown) {
  return new Response(status === 204 ? null : JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } });
}

describe("buildQuery", () => {
  it("skips empty values and joins arrays", () => {
    expect(buildQuery({ "filter[q]": "", page: 2, sort: null, "filter[role]": ["a", "b"], x: undefined })).toBe(
      "?page=2&filter%5Brole%5D=a%2Cb",
    );
    expect(buildQuery(undefined)).toBe("");
  });
});

describe("api client", () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    vi.stubGlobal("fetch", fetchMock);
    document.cookie = "XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT";
  });

  afterEach(() => {
    fetchMock.mockReset();
    vi.unstubAllGlobals();
    onUnauthenticated(null);
  });

  it("sends GET requests to the versioned API with JSON headers", async () => {
    fetchMock.mockResolvedValueOnce(json(200, { data: [] }));
    await api.get("/branches", { page: 1 });
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("/api/v1/branches?page=1");
    expect((init?.headers as Record<string, string>).Accept).toBe("application/json");
    expect(init?.credentials).toBe("same-origin");
  });

  it("fetches the CSRF cookie before the first write and sends it back", async () => {
    fetchMock.mockImplementationOnce(async () => {
      document.cookie = `XSRF-TOKEN=${encodeURIComponent("abc=123")}`;
      return new Response(null, { status: 204 });
    });
    fetchMock.mockResolvedValueOnce(json(201, { data: { id: 1 } }));

    await api.post("/branches", { name: "x" });

    expect(fetchMock.mock.calls[0][0]).toBe("/sanctum/csrf-cookie");
    const init = fetchMock.mock.calls[1][1];
    expect((init?.headers as Record<string, string>)["X-XSRF-TOKEN"]).toBe("abc=123");
    expect(init?.body).toBe(JSON.stringify({ name: "x" }));
  });

  it("renews the CSRF token once on 419 and retries", async () => {
    document.cookie = "XSRF-TOKEN=old";
    fetchMock
      .mockResolvedValueOnce(json(419, { message: "expired", code: "csrf_mismatch" }))
      .mockImplementationOnce(async () => {
        document.cookie = "XSRF-TOKEN=new";
        return new Response(null, { status: 204 });
      })
      .mockResolvedValueOnce(new Response(null, { status: 204 }));

    await api.delete("/branches/1");

    expect(fetchMock).toHaveBeenCalledTimes(3);
    expect((fetchMock.mock.calls[2][1]?.headers as Record<string, string>)["X-XSRF-TOKEN"]).toBe("new");
  });

  it("throws ApiError with the server's code and field errors", async () => {
    fetchMock.mockResolvedValueOnce(json(422, { message: "invalid", code: "validation_failed", errors: { code: ["taken"] } }));

    const error = await api.get("/branches").catch((e: unknown) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({ status: 422, code: "validation_failed", errors: { code: ["taken"] }, message: "invalid" });
  });

  it("reports 401 responses to the session handler", async () => {
    const handler = vi.fn();
    onUnauthenticated(handler);
    fetchMock.mockResolvedValueOnce(json(401, { message: "no", code: "unauthenticated" }));

    await expect(api.get("/me")).rejects.toBeInstanceOf(ApiError);
    expect(handler).toHaveBeenCalledOnce();
  });
});
