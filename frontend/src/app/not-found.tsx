"use client";

import { Button, Result } from "antd";
import Link from "next/link";
import { useTranslations } from "next-intl";

export default function NotFound() {
  const t = useTranslations("common");
  return (
    <main style={{ minHeight: "100vh", display: "grid", placeItems: "center" }}>
      <Result
        status="404"
        title={t("notFoundTitle")}
        subTitle={t("notFoundText")}
        extra={
          <Link href="/dashboard">
            <Button type="primary">{t("backHome")}</Button>
          </Link>
        }
      />
    </main>
  );
}
