"use client";

import { Input, Select } from "antd";
import { useTranslations } from "next-intl";
import { useMemo } from "react";
import { formatNumber } from "@/shared/lib/digits";
import { useRacks } from "../api";

export type RackChoice = { slotId: number | null; location: string };

/**
 * Where to put a finished order: a slot of the branch's racks (the
 * suggested one first), or a free-text place when the branch has none.
 */
export function RackPicker({ branchId, suggestion, value, onChange }: { branchId: number; suggestion: { id: number; code: string } | null; value: RackChoice; onChange: (value: RackChoice) => void }) {
  const t = useTranslations("assembly");
  const racks = useRacks();
  const options = useMemo(
    () =>
      (racks.data ?? [])
        .filter((rack) => rack.branch_id === branchId && rack.is_active)
        .map((rack) => ({
          label: rack.name,
          options: rack.slots.map((slot) => ({
            value: slot.id,
            label: [slot.code, slot.id === suggestion?.id ? t("suggested") : null, slot.orders.length > 0 ? t("occupied", { count: formatNumber(slot.orders.length) }) : null]
              .filter(Boolean)
              .join(" · "),
          })),
        })),
    [racks.data, branchId, suggestion, t],
  );

  if (!racks.isLoading && options.length === 0) {
    return (
      <Input
        aria-label={t("rackLocation")}
        placeholder={t("rackPlaceholder")}
        value={value.location}
        onChange={(e) => onChange({ slotId: null, location: e.target.value })}
        maxLength={50}
      />
    );
  }

  return (
    <Select
      showSearch
      optionFilterProp="label"
      aria-label={t("rackSlot")}
      placeholder={t("rackSlot")}
      loading={racks.isLoading}
      value={value.slotId ?? undefined}
      onChange={(slotId: number) => onChange({ slotId, location: "" })}
      options={options}
      style={{ width: "100%" }}
    />
  );
}
