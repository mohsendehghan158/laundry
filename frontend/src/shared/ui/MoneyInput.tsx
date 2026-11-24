"use client";

import { InputNumber, type InputNumberProps } from "antd";
import { useTranslations } from "next-intl";
import { formatNumber } from "@/shared/lib/digits";
import { fromRial, parseAmount, toRial, type CurrencyUnit } from "@/shared/lib/money";

export type MoneyInputProps = Omit<InputNumberProps<number>, "value" | "onChange" | "formatter" | "parser"> & {
  /** Amount in rial, as the API stores it. */
  value?: number | null;
  onChange?: (rial: number | null) => void;
  /** Unit the person types in; defaults to toman. */
  unit?: CurrencyUnit;
};

/**
 * Amount field that shows Persian digits with thousands separators, accepts
 * Persian or Latin digits, and always reports whole rials.
 */
export function MoneyInput({ value, onChange, unit = "toman", ...rest }: MoneyInputProps) {
  const t = useTranslations("money");

  return (
    <InputNumber<number>
      min={0}
      style={{ width: "100%" }}
      suffix={t(unit)}
      {...rest}
      value={value === null || value === undefined ? null : fromRial(value, unit)}
      formatter={(v, info) => (info.userTyping ? info.input : v === undefined || v === null ? "" : formatNumber(Number(v)))}
      parser={(text) => parseAmount(text) ?? (null as unknown as number)}
      onChange={(amount) => onChange?.(amount === null ? null : toRial(amount, unit))}
    />
  );
}
