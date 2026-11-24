"use client";

import type { ReactNode } from "react";
import type { Permission } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";

/** Renders its children only when the signed-in user holds the permission(s). */
export function Can({ permission, children, fallback = null }: { permission: Permission | Permission[]; children: ReactNode; fallback?: ReactNode }) {
  const { can } = usePermission();
  return <>{can(permission) ? children : fallback}</>;
}
