/** Only same-site paths are followed after login, never "//evil.com" or "https://…". */
export function safeNext(next: string | null | undefined, fallback = "/dashboard"): string {
  if (!next || !next.startsWith("/") || next.startsWith("//") || next.startsWith("/\\") || next.startsWith("/login")) {
    return fallback;
  }
  return next;
}
