import { safeNext } from "@/features/auth/safeNext";

/** After a customer signs in: only back into the portal, never into the staff panel. */
export function safePortalNext(next: string | null | undefined): string {
  const path = safeNext(next, "/my");
  return (path === "/my" || path.startsWith("/my/") || path.startsWith("/my?")) && !path.startsWith("/my/login") ? path : "/my";
}
