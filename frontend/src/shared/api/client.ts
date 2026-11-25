import { ApiError, type ApiErrorBody } from "./errors";

/**
 * Thin fetch wrapper for the Laravel API. The browser talks to the same
 * origin (Next.js rewrites /api and /sanctum to the backend), so Sanctum
 * cookie sessions and CSRF protection work without CORS.
 */
export const API_PREFIX = "/api/v1";

type QueryValue = string | number | boolean | null | undefined;
export type Query = Record<string, QueryValue | QueryValue[]>;

export interface RequestOptions {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  body?: unknown;
  query?: Query;
  signal?: AbortSignal;
}

const UNSAFE = new Set(["POST", "PUT", "PATCH", "DELETE"]);

/** Called when the API reports the session is gone, so the app can go to the login page. */
let onUnauthenticated: (() => void) | null = null;
export function setUnauthenticatedHandler(handler: (() => void) | null): void {
  onUnauthenticated = handler;
}

export function buildQuery(query?: Query): string {
  if (!query) return "";
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    const values = Array.isArray(value) ? value : [value];
    const present = values.filter((v) => v !== undefined && v !== null && v !== "");
    if (present.length === 0) continue;
    params.set(key, present.map((v) => (typeof v === "boolean" ? (v ? "1" : "0") : String(v))).join(","));
  }
  const text = params.toString();
  return text ? `?${text}` : "";
}

function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.split("; ").find((row) => row.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}

/** Fetches the XSRF-TOKEN cookie that every state-changing request must echo back. */
export async function ensureCsrfCookie(force = false): Promise<void> {
  if (!force && readCookie("XSRF-TOKEN")) return;
  await fetch("/sanctum/csrf-cookie", { credentials: "include", headers: { Accept: "application/json" } });
}

export async function api<T>(path: string, options: RequestOptions = {}, retried = false): Promise<T> {
  const method = options.method ?? "GET";
  if (UNSAFE.has(method)) await ensureCsrfCookie();

  const headers: Record<string, string> = {
    Accept: "application/json",
    "Accept-Language": "fa",
    "X-Requested-With": "XMLHttpRequest",
  };
  const xsrf = readCookie("XSRF-TOKEN");
  if (xsrf) headers["X-XSRF-TOKEN"] = xsrf;
  if (options.body !== undefined) headers["Content-Type"] = "application/json";

  let response: Response;
  try {
    response = await fetch(`${API_PREFIX}${path}${buildQuery(options.query)}`, {
      method,
      headers,
      credentials: "include",
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
      signal: options.signal,
    });
  } catch (error) {
    if ((error as Error).name === "AbortError") throw error;
    throw new ApiError(0, {});
  }

  if (response.status === 204) return undefined as T;

  const data: unknown = await response.json().catch(() => ({}));

  if (!response.ok) {
    // The CSRF token expires with the session; get a fresh one and try once more.
    if (response.status === 419 && !retried) {
      await ensureCsrfCookie(true);
      return api<T>(path, options, true);
    }
    if (response.status === 401 && path !== "/auth/login") onUnauthenticated?.();
    throw new ApiError(response.status, data as Partial<ApiErrorBody>);
  }

  return data as T;
}
