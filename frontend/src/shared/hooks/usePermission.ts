"use client";

import { useMe } from "@/features/auth/api";

/**
 * Whether the signed-in user holds a permission. Only hides menus and
 * buttons; the API stays the final authority.
 */
export function usePermission(permission: string | string[]): boolean {
  const { data: me } = useMe();
  return hasPermission(me?.permissions, permission);
}

/** True when any of the given permissions is held. */
export function hasPermission(held: string[] | undefined, permission: string | string[]): boolean {
  if (!held) return false;
  const wanted = Array.isArray(permission) ? permission : [permission];
  return wanted.some((name) => held.includes(name));
}
