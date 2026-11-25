"use client";

import { useTranslations } from "next-intl";
import { dayjs, jalaliMonthRange, type Dayjs } from "@/shared/lib/date";

/** Quick periods for report date pickers, on the Jalali calendar. */
export function useRangePresets(): { label: string; value: [Dayjs, Dayjs] }[] {
  const t = useTranslations("reports.presets");
  const today = dayjs().startOf("day");
  return [
    { label: t("today"), value: [today, today] },
    { label: t("yesterday"), value: [today.subtract(1, "day"), today.subtract(1, "day")] },
    { label: t("week"), value: [today.subtract(6, "day"), today] },
    { label: t("days30"), value: [today.subtract(29, "day"), today] },
    { label: t("month"), value: [jalaliMonthRange(0)[0], today] },
    { label: t("lastMonth"), value: jalaliMonthRange(-1) },
  ];
}
