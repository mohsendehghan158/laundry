import { NextResponse, type NextRequest } from "next/server";

/** Laravel's session cookie (SESSION_COOKIE in backend/.env). */
const SESSION_COOKIE = process.env.SESSION_COOKIE ?? "laundry_session";

/**
 * Cheap first gate: a browser with no session cookie at all cannot be
 * signed in, so send it straight to the login page instead of rendering
 * the panel shell first. Whether the session is still valid is decided by
 * the API (GET /me) on the client.
 */
export function proxy(request: NextRequest) {
  if (request.cookies.has(SESSION_COOKIE)) {
    return NextResponse.next();
  }

  const url = request.nextUrl.clone();
  const target = `${request.nextUrl.pathname}${request.nextUrl.search}`;
  url.pathname = "/login";
  url.search = target === "/" ? "" : `?next=${encodeURIComponent(target)}`;
  return NextResponse.redirect(url);
}

export const config = {
  // Everything except the login page, public tracking, the customer portal
  // (it has its own sign-in), API/Sanctum rewrites, Next internals and static files.
  matcher: ["/((?!login|track|my(?:/|$)|api|sanctum|_next|favicon.ico|.*\\.[a-zA-Z0-9]+$).*)"],
};
