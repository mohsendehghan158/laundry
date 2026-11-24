"use client";

import { useCallback } from "react";
import { useMe } from "@/features/auth/api";
import type { Permission } from "@/shared/api/types";

/**
 * Permission checks for hiding menus, buttons and pages. The server still
 * enforces every permission; this only keeps the interface honest.
 */
export function usePermission() {
  const { data: me } = useMe();

  const can = useCallback(
    (permission: Permission | Permission[] | undefined): boolean => {
      if (!permission) {
        return true;
      }
      if (!me) {
        return false;
      }
      const required = Array.isArray(permission) ? permission : [permission];
      return required.every((name) => me.permissions.includes(name));
    },
    [me],
  );

  const canAny = useCallback(
    (permissions: Permission[]): boolean => permissions.some((name) => me?.permissions.includes(name) ?? false),
    [me],
  );

  return { can, canAny, me };
}
