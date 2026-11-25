"use client";

import { Alert, App, Button, Flex, Form, Input, Modal, Segmented, Typography } from "antd";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { errorMessage } from "@/shared/api/formErrors";
import type { Order, PaymentMethod } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { formatMoney } from "@/shared/lib/money";
import { MoneyInput } from "@/shared/ui/MoneyInput";
import { useCurrentShift, usePay, useRefund } from "../api";

const METHODS: PaymentMethod[] = ["cash", "card", "transfer"];

type Props = {
  order: Pick<Order, "id" | "balance" | "paid_amount" | "ticket_number"> & { branch: { id: number } };
  mode: "pay" | "refund";
  open: boolean;
  onClose: () => void;
  onDone?: () => void;
};

/**
 * Takes money for an order (cash, card or transfer, any part of the
 * balance) or gives it back. For cash it shows the change to hand back.
 */
export function PaymentModal({ order, mode, open, onClose, onDone }: Props) {
  const t = useTranslations();
  return (
    <Modal open={open} title={`${mode === "pay" ? t("billing.pay") : t("billing.refund")} · ${order.ticket_number}`} footer={null} onCancel={onClose} destroyOnHidden>
      {/* Mounted afresh on every open, so its fields start from the order's current amounts. */}
      <PaymentForm order={order} mode={mode} onClose={onClose} onDone={onDone} />
    </Modal>
  );
}

function PaymentForm({ order, mode, onClose, onDone }: Omit<Props, "open">) {
  const t = useTranslations();
  const { message } = App.useApp();
  const unit = useCurrencyUnit();
  const pay = usePay();
  const refund = useRefund();
  const shift = useCurrentShift(order.branch.id);
  const limit = mode === "pay" ? order.balance : order.paid_amount;

  const [method, setMethod] = useState<PaymentMethod>(mode === "pay" ? "cash" : "card");
  const [amount, setAmount] = useState<number | null>(limit);
  const [tendered, setTendered] = useState<number | null>(null);
  const [reference, setReference] = useState("");
  const [reason, setReason] = useState("");

  const registerClosed = method === "cash" && shift.isSuccess && shift.data === null;
  const change = method === "cash" && tendered !== null && amount !== null ? tendered - amount : null;
  const valid = amount !== null && amount > 0 && amount <= limit && !registerClosed && (mode === "pay" || reason.trim() !== "");
  const pending = pay.isPending || refund.isPending;

  const submit = () => {
    if (!valid || amount === null) {
      return;
    }
    const callbacks = {
      onSuccess: () => {
        message.success(mode === "pay" ? t("billing.paid") : t("billing.refunded"));
        onDone?.();
        onClose();
      },
      onError: (error: unknown) => message.error(errorMessage(error, t("common.error"))),
    };
    if (mode === "pay") {
      pay.mutate({ orderId: order.id, method, amount, reference: reference.trim() || null }, callbacks);
    } else {
      refund.mutate({ orderId: order.id, method, amount, reason: reason.trim() }, callbacks);
    }
  };

  return (
    <Form layout="vertical" onFinish={submit}>
      <Typography.Paragraph>
        {mode === "pay" ? t("billing.balance") : t("billing.paidSoFar")}: <strong>{formatMoney(limit, unit)} {t(`money.${unit}`)}</strong>
      </Typography.Paragraph>
      <Form.Item label={t("billing.method")}>
        <Segmented block size="large" value={method} onChange={(value) => setMethod(value as PaymentMethod)} options={METHODS.map((m) => ({ value: m, label: t(`billing.methods.${m}`) }))} />
      </Form.Item>
      {registerClosed && (
        <Alert type="warning" showIcon style={{ marginBottom: 16 }} title={t("billing.registerClosed")} action={<Link href="/register">{t("billing.openRegister")}</Link>} />
      )}
      <Form.Item label={t("billing.amount")} validateStatus={amount !== null && amount > limit ? "error" : undefined} help={amount !== null && amount > limit ? t("billing.overLimit") : undefined}>
        <MoneyInput unit={unit} size="large" value={amount} onChange={setAmount} autoFocus aria-label={t("billing.amount")} />
      </Form.Item>
      {mode === "pay" && method === "cash" && (
        <Form.Item label={t("billing.tendered")}>
          <MoneyInput unit={unit} value={tendered} onChange={setTendered} aria-label={t("billing.tendered")} />
        </Form.Item>
      )}
      {change !== null && change >= 0 && <Alert type="info" title={`${t("billing.change")}: ${formatMoney(change, unit)} ${t(`money.${unit}`)}`} style={{ marginBottom: 16 }} />}
      {mode === "pay" && method !== "cash" && (
        <Form.Item label={t("billing.reference")}>
          <Input dir="ltr" inputMode="numeric" value={reference} onChange={(e) => setReference(e.target.value)} maxLength={100} />
        </Form.Item>
      )}
      {mode === "refund" && (
        <Form.Item label={t("billing.reason")} required>
          <Input value={reason} onChange={(e) => setReason(e.target.value)} maxLength={500} aria-label={t("billing.reason")} />
        </Form.Item>
      )}
      <Flex justify="end" gap={8}>
        <Button onClick={onClose}>{t("common.cancel")}</Button>
        <Button type="primary" danger={mode === "refund"} htmlType="submit" disabled={!valid} loading={pending}>
          {mode === "pay" ? t("billing.confirmPay") : t("billing.confirmRefund")}
        </Button>
      </Flex>
    </Form>
  );
}
