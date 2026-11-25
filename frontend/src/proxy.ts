import { NextResponse, type NextRequest } from "next/server";

/**
 * First redirect only: without a session cookie there is no point rendering
 * the panel, so go straight to the login page. Whether the session is still
 * valid and what the user may see is always decided by the API (/me).
 */
const SESSION_COOKIE = process.env.SESSION_COOKIE ?? "laundry_session";

export function proxy(request: NextRequest) {
  if (request.cookies.has(SESSION_COOKIE)) return NextResponse.next();

  const url = request.nextUrl.clone();
  const next = request.nextUrl.pathname + request.nextUrl.search;
  url.pathname = "/login";
  url.search = next === "/" ? "" : `?next=${encodeURIComponent(next)}`;
  return NextResponse.redirect(url);
}

export const config = {
  // Everything except the login page, the API and static assets.
  matcher: ["/((?!login|api|sanctum|docs|_next|favicon.ico|.*\\.[a-z0-9]+$).*)"],
};
