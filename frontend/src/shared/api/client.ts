/**
 * Thin fetch wrapper for the Laravel API. The API is served from the same
 * origin (see the rewrites in next.config.ts), so the Sanctum session
 * cookie travels automatically; state-changing requests also carry the
 * XSRF token Laravel hands out at /sanctum/csrf-cookie.
 */

export const API_BASE = "/api/v1";
const CSRF_URL = "/sanctum/csrf-cookie";
const XSRF_COOKIE = "XSRF-TOKEN";

/** Shape of every API error body (see ApiExceptionRenderer on the backend). */
export type ApiErrorBody = {
  message: string;
  code?: string;
  errors?: Record<string, string[]>;
};

export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly errors: Record<string, string[]>;

  constructor(status: number, body: Partial<ApiErrorBody>) {
    super(body.message || `HTTP ${status}`);
    this.name = "ApiError";
    this.status = status;
    this.code = body.code ?? "http_error";
    this.errors = body.errors ?? {};
  }
}

export function isApiError(error: unknown, status?: number): error is ApiError {
  return error instanceof ApiError && (status === undefined || error.status === status);
}

type Query = Record<string, string | number | boolean | null | undefined | Array<string | number>>;

export type RequestOptions = {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  query?: Query;
  body?: unknown;
  signal?: AbortSignal;
};

let unauthenticatedHandler: (() => void) | null = null;

/** Called once whenever a request comes back 401 (session expired). */
export function onUnauthenticated(handler: (() => void) | null): void {
  unauthenticatedHandler = handler;
}

function readCookie(name: string): string | null {
  if (typeof document === "undefined") {
    return null;
  }
  const match = document.cookie.split("; ").find((part) => part.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}

async function ensureCsrfCookie(force = false): Promise<string | null> {
  if (force || !readCookie(XSRF_COOKIE)) {
    await fetch(CSRF_URL, { credentials: "same-origin", headers: { Accept: "application/json" } });
  }
  return readCookie(XSRF_COOKIE);
}

/** Builds "?filter[q]=x&page=2", skipping empty values. Arrays are comma-joined. */
export function buildQuery(query: Query | undefined): string {
  if (!query) {
    return "";
  }
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === "") {
      continue;
    }
    if (Array.isArray(value)) {
      if (value.length > 0) {
        params.set(key, value.join(","));
      }
      continue;
    }
    params.set(key, String(value));
  }
  const text = params.toString();
  return text ? `?${text}` : "";
}

async function send(path: string, options: RequestOptions, csrfRetry: boolean): Promise<Response> {
  const method = options.method ?? "GET";
  const headers: Record<string, string> = {
    Accept: "application/json",
    "Accept-Language": (typeof document !== "undefined" && document.documentElement.lang) || "fa",
  };

  if (method !== "GET") {
    const token = await ensureCsrfCookie();
    if (token) {
      headers["X-XSRF-TOKEN"] = token;
    }
  }
  if (options.body !== undefined) {
    headers["Content-Type"] = "application/json";
  }

  const response = await fetch(`${API_BASE}${path}${buildQuery(options.query)}`, {
    method,
    headers,
    credentials: "same-origin",
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
    signal: options.signal,
  });

  // 419: the CSRF token went stale (e.g. the session was renewed). Fetch a
  // fresh one and try once more.
  if (response.status === 419 && csrfRetry) {
    await ensureCsrfCookie(true);
    return send(path, options, false);
  }

  return response;
}

export async function apiFetch<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const response = await send(path, options, true);

  if (response.status === 204) {
    return undefined as T;
  }

  const body: unknown = await response.json().catch(() => ({}));

  if (!response.ok) {
    if (response.status === 401 && unauthenticatedHandler) {
      unauthenticatedHandler();
    }
    throw new ApiError(response.status, (body ?? {}) as Partial<ApiErrorBody>);
  }

  return body as T;
}

export const api = {
  get: <T>(path: string, query?: Query, signal?: AbortSignal) => apiFetch<T>(path, { query, signal }),
  post: <T>(path: string, body?: unknown) => apiFetch<T>(path, { method: "POST", body }),
  put: <T>(path: string, body?: unknown) => apiFetch<T>(path, { method: "PUT", body }),
  delete: <T = void>(path: string) => apiFetch<T>(path, { method: "DELETE" }),
};
