"use client";

import { Card, Grid, Statistic } from "antd";
import type { ReactNode } from "react";

/**
 * A headline figure in a card. Values arrive formatted (Persian digits);
 * the unit is set smaller so amounts fit on one line on a phone.
 */
export function StatTile({ title, value, suffix, loading }: { title: ReactNode; value: string; suffix?: string; loading?: boolean }) {
  const screens = Grid.useBreakpoint();
  const compact = !screens.sm;
  return (
    <Card size={compact ? "small" : "default"}>
      <Statistic
        title={title}
        value={value}
        suffix={suffix}
        loading={loading}
        styles={{ content: { fontSize: compact ? 20 : 24 }, suffix: { fontSize: 14 } }}
      />
    </Card>
  );
}
