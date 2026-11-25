"use client";

import { useTranslations } from "next-intl";
import type { DashboardReport } from "@/shared/api/types";
import { dayjs, formatJalali } from "@/shared/lib/date";
import { formatNumber, toPersianDigits } from "@/shared/lib/digits";
import { formatMoney, fromRial, type CurrencyUnit } from "@/shared/lib/money";
import { BarChart } from "@/shared/ui/BarChart";
import { compactNumber } from "../scale";

export type TrendMeasure = "sales" | "orders";
type Point = DashboardReport["trend"][number];

/** Daily bars for the last days, newest at the reading end; one measure at a time, the tooltip shows both. */
export function TrendChart({ points, measure, unit }: { points: Point[]; measure: TrendMeasure; unit: CurrencyUnit }) {
  const t = useTranslations();
  const today = dayjs().format("YYYY-MM-DD");

  return (
    <BarChart
      label={t("dashboard.trend.aria", { measure: t(`dashboard.trend.${measure}`) })}
      tick={(v) => (measure === "sales" ? compactNumber(v) : formatNumber(v))}
      points={points.map((point) => ({
        key: point.date,
        label: toPersianDigits(formatJalali(dayjs(point.date), "D")),
        value: measure === "sales" ? fromRial(point.sales, unit) : point.orders,
        strong: point.date === today,
        description: `${toPersianDigits(formatJalali(dayjs(point.date), "dddd D MMMM"))}: ${formatMoney(point.sales, unit)} ${t(`money.${unit}`)}، ${t("dashboard.trend.ordersCount", { count: point.orders })}`,
      }))}
    />
  );
}
