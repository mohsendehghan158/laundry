"use client";

import { InputNumber, type InputNumberProps } from "antd";
import { currencyLabel, fromRials, parseAmount, toRials, type CurrencyUnit } from "@/shared/lib/money";
import { formatNumber } from "@/shared/lib/digits";

export interface MoneyInputProps extends Omit<InputNumberProps<number>, "value" | "onChange" | "formatter" | "parser"> {
  /** Amount in rials, as stored and sent to the API. */
  value?: number | null;
  onChange?: (rials: number | null) => void;
  unit?: CurrencyUnit;
}

/**
 * Amount field: the person types in the display unit (toman by default)
 * with Persian or Latin digits; the form always receives rials.
 */
export function MoneyInput({ value, onChange, unit = "toman", ...rest }: MoneyInputProps) {
  return (
    <InputNumber<number>
      min={0}
      style={{ width: "100%" }}
      suffix={currencyLabel(unit)}
      {...rest}
      value={value == null ? null : fromRials(value, unit)}
      onChange={(amount) => onChange?.(amount == null ? null : toRials(amount, unit))}
      formatter={(amount) => (amount === undefined || String(amount) === "" ? "" : formatNumber(Number(amount)))}
      parser={(text) => parseAmount(text ?? "") ?? 0}
      inputMode="numeric"
    />
  );
}
