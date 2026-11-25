"use client";

import { Button, Result, Skeleton } from "antd";
import { useTranslations } from "next-intl";
import type { ReactNode } from "react";
import { errorMessage, isApiError } from "@/shared/api/errors";

/** Loading skeleton, a readable error with retry, or the content. */
export function QueryState({
  isLoading,
  error,
  onRetry,
  children,
}: {
  isLoading: boolean;
  error: unknown;
  onRetry?: () => void;
  children: ReactNode;
}) {
  const t = useTranslations("common");
  if (isLoading) return <Skeleton active paragraph={{ rows: 6 }} />;
  if (error) {
    const forbidden = isApiError(error) && error.status === 403;
    return (
      <Result
        status={forbidden ? "403" : "error"}
        title={forbidden ? t("noAccess") : t("error")}
        subTitle={forbidden ? undefined : errorMessage(error)}
        extra={onRetry && !forbidden ? <Button onClick={onRetry}>{t("retry")}</Button> : null}
      />
    );
  }
  return <>{children}</>;
}
