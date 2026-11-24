"use client";

import { useTranslations } from "next-intl";
import { useCallback } from "react";

/** Built-in roles have Persian names; custom roles show their own name. */
export function useRoleLabel() {
  const t = useTranslations("roles.names");
  return useCallback((name: string) => (t.has(name as never) ? t(name as never) : name), [t]);
}
