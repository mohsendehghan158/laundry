"use client";

import { theme, Tooltip, Typography } from "antd";
import { useColorScheme } from "@/shared/hooks/useColorScheme";
import { niceTop } from "@/features/reports/scale";

export type BarPoint = {
  key: string;
  /** Under the bar (a day of the month, say). */
  label: string;
  value: number;
  /** Tooltip and accessible name. */
  description: string;
  /** Emphasise this bar's label (today). */
  strong?: boolean;
};

const HEIGHT = 180;
const AXIS_WIDTH = 64;
/** The single series colour, checked for contrast on each surface. */
const SERIES = { light: "#1668dc", dark: "#3c89e8" } as const;

/**
 * Vertical bars of one measure, in reading order (right to left in RTL),
 * with a three-line value axis and a tooltip per bar. `top` fixes the
 * axis (100 for percentages); otherwise it is rounded up from the data.
 */
export function BarChart({ points, label, tick, top }: { points: BarPoint[]; label: string; tick: (value: number) => string; top?: number }) {
  const { token } = theme.useToken();
  const scheme = useColorScheme();
  const max = top ?? niceTop(Math.max(0, ...points.map((p) => p.value)));

  return (
    <figure style={{ margin: 0 }} aria-label={label}>
      <div style={{ display: "flex", gap: 8 }}>
        <div style={{ position: "relative", width: AXIS_WIDTH, height: HEIGHT, flex: "none" }} aria-hidden>
          {[max, max / 2, 0].map((v, i) => (
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
            {points.map((point) => (
              <Tooltip key={point.key} title={point.description} trigger={["hover", "focus"]}>
                <div
                  role="listitem"
                  tabIndex={0}
                  aria-label={point.description}
                  className="trend-chart-column"
                  style={{ flex: 1, display: "flex", alignItems: "flex-end", justifyContent: "center", height: "100%", borderRadius: 4, cursor: "default" }}
                >
                  <div
                    style={{
                      width: "60%",
                      maxWidth: 24,
                      height: `${Math.min(100, (point.value / max) * 100)}%`,
                      minHeight: point.value > 0 ? 2 : 0,
                      background: SERIES[scheme],
                      borderRadius: "4px 4px 0 0",
                    }}
                  />
                </div>
              </Tooltip>
            ))}
          </div>
        </div>
      </div>
      <div style={{ display: "flex", marginInlineStart: AXIS_WIDTH + 8 }} aria-hidden>
        {points.map((point) => (
          <Typography.Text key={point.key} type={point.strong ? undefined : "secondary"} strong={point.strong} style={{ flex: 1, textAlign: "center", fontSize: 12, paddingTop: 4 }}>
            {point.label}
          </Typography.Text>
        ))}
      </div>
    </figure>
  );
}
