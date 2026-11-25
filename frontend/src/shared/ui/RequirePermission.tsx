"use client";

import { Button, Result } from "antd";
import Link from "next/link";
import { useTranslations } from "next-intl";
import type { ReactNode } from "react";
import type { Permission } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";

/**
 * Page-level guard: a 403 screen instead of a page the server would refuse
 * anyway. With a list, any one of the permissions is enough.
 */
export function RequirePermission({ permission, children }: { permission: Permission | Permission[]; children: ReactNode }) {
  const t = useTranslations("common");
  const { can } = usePermission();
  const allowed = Array.isArray(permission) ? permission.some((p) => can(p)) : can(permission);

  if (!allowed) {
    return (
      <Result
        status="403"
        title={t("forbiddenTitle")}
        subTitle={t("forbiddenText")}
        extra={
          <Link href="/dashboard">
            <Button type="primary">{t("backHome")}</Button>
          </Link>
        }
      />
    );
  }

  return <>{children}</>;
}
