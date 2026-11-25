"use client";

import { useTranslations } from "next-intl";

/** Translated name of a role; custom roles added later show their raw name. */
export function useRoleLabel() {
  const t = useTranslations("roles.names");
  return (name: string) => (t.has(name) ? t(name) : name);
}

export function usePermissionLabel() {
  const t = useTranslations("roles");
  return {
    permission: (name: string) => (t.has(`perms.${name}`) ? t(`perms.${name}`) : name),
    group: (name: string) => (t.has(`groups.${name}`) ? t(`groups.${name}`) : name),
  };
}
