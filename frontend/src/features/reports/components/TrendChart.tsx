"use client";

import { theme, Tooltip, Typography } from "antd";
import { useTranslations } from "next-intl";
import type { DashboardReport } from "@/shared/api/types";
import { useColorScheme } from "@/shared/hooks/useColorScheme";
import { dayjs, formatJalali } from "@/shared/lib/date";
import { formatNumber, toPersianDigits } from "@/shared/lib/digits";
import { formatMoney, fromRial, type CurrencyUnit } from "@/shared/lib/money";
import { compactNumber, niceTop } from "../scale";

export type TrendMeasure = "sales" | "orders";
type Point = DashboardReport["trend"][number];

const HEIGHT = 180;
const AXIS_WIDTH = 64;
/** The single series colour, checked for contrast on each surface. */
const SERIES = { light: "#1668dc", dark: "#3c89e8" } as const;

/**
 * Daily bars for the last days, newest at the reading end (left in RTL).
 * One measure at a time; the tooltip shows both.
 */
export function TrendChart({ points, measure, unit }: { points: Point[]; measure: TrendMeasure; unit: CurrencyUnit }) {
  const t = useTranslations();
  const { token } = theme.useToken();
  const scheme = useColorScheme();
  const today = dayjs().format("YYYY-MM-DD");

  const value = (point: Point) => (measure === "sales" ? fromRial(point.sales, unit) : point.orders);
  const top = niceTop(Math.max(0, ...points.map(value)));
  const tick = (v: number) => (measure === "sales" ? compactNumber(v) : formatNumber(v));
  const describe = (point: Point) =>
    `${toPersianDigits(formatJalali(dayjs(point.date), "dddd D MMMM"))}: ${formatMoney(point.sales, unit)} ${t(`money.${unit}`)}، ${t("dashboard.trend.ordersCount", { count: point.orders })}`;

  return (
    <figure style={{ margin: 0 }} aria-label={t("dashboard.trend.aria", { measure: t(`dashboard.trend.${measure}`) })}>
      <div style={{ display: "flex", gap: 8 }}>
        <div style={{ position: "relative", width: AXIS_WIDTH, height: HEIGHT, flex: "none" }} aria-hidden>
          {[top, top / 2, 0].map((v, i) => (
            <Typography.Text
              key={v}
              type="secondary"
              style={{ position: "absolute", insetInlineEnd: 0, top: `${i * 50}%`, transform: "translateY(-50%)", fontSize: 12, whiteSpace: "nowrap" }}
            >
              {tick(v)}
            </Typography.Text>
          ))}
        </div>
        <div style={{ position: "relative", flex: 1, height: HEIGHT, borderBottom: `1px solid ${token.colorBorder}` }}>
          {[0, 50].map((offset) => (
            <div key={offset} aria-hidden style={{ position: "absolute", insetInline: 0, top: `${offset}%`, borderTop: `1px solid ${token.colorBorderSecondary}` }} />
          ))}
          <div role="list" style={{ position: "relative", display: "flex", height: "100%" }}>
            {points.map((point) => {
              const label = describe(point);
              return (
                <Tooltip key={point.date} title={label} trigger={["hover", "focus"]}>
                  <div
                    role="listitem"
                    tabIndex={0}
                    aria-label={label}
                    className="trend-chart-column"
                    style={{ flex: 1, display: "flex", alignItems: "flex-end", justifyContent: "center", height: "100%", borderRadius: 4, cursor: "default" }}
                  >
                    <div
                      style={{
                        width: "60%",
                        maxWidth: 24,
                        height: `${(value(point) / top) * 100}%`,
                        minHeight: value(point) > 0 ? 2 : 0,
                        background: SERIES[scheme],
                        borderRadius: "4px 4px 0 0",
                      }}
                    />
                  </div>
                </Tooltip>
              );
            })}
          </div>
        </div>
      </div>
      <div style={{ display: "flex", marginInlineStart: AXIS_WIDTH + 8 }} aria-hidden>
        {points.map((point) => (
          <Typography.Text
            key={point.date}
            type={point.date === today ? undefined : "secondary"}
            strong={point.date === today}
            style={{ flex: 1, textAlign: "center", fontSize: 12, paddingTop: 4 }}
          >
            {toPersianDigits(formatJalali(dayjs(point.date), "D"))}
          </Typography.Text>
        ))}
      </div>
    </figure>
  );
}
