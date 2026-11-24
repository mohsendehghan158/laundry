"use client";

import { useMe } from "@/features/auth/api";
import type { CurrencyUnit } from "@/shared/lib/money";

/** The organization's display unit for amounts (toman unless set to rial). */
export function useCurrencyUnit(): CurrencyUnit {
  const { data } = useMe();
  return data?.organization.currency_unit ?? "toman";
}
