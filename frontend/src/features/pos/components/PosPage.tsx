"use client";

import { CheckCircleFilled, DollarOutlined, GiftOutlined, PercentageOutlined, PrinterOutlined, ShoppingCartOutlined, TagsOutlined } from "@ant-design/icons";
import { Alert, App, Button, Card, Col, Descriptions, Divider, Empty, Flex, Input, InputNumber, Modal, Popover, Radio, Result, Row, Segmented, Select, Skeleton, Space, Typography } from "antd";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useMemo, useReducer, useRef, useState } from "react";
import { PaymentModal } from "@/features/billing/components/PaymentModal";
import { useCounterCatalog } from "@/features/catalog/api";
import { useCreateOrder, usePricePreview } from "@/features/orders/api";
import { openPrint } from "@/features/printing/api";
import { errorMessage } from "@/shared/api/formErrors";
import { isApiError } from "@/shared/api/client";
import type { Order } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { useDebouncedValue } from "@/shared/hooks/useDebouncedValue";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatDateTime } from "@/shared/lib/date";
import { formatNumber, inputNumberFormatter, toPersianDigits } from "@/shared/lib/digits";
import { formatMoney, parseAmount } from "@/shared/lib/money";
import { MoneyInput } from "@/shared/ui/MoneyInput";
import { cartReducer, emptyCart, toPayload, type CartDiscount } from "../cart";
import { useCounterBranch } from "../useCounterBranch";
import { CartLineCard } from "./CartLineCard";
import { CustomerPicker } from "./CustomerPicker";
import { ItemGrid } from "./ItemGrid";

function newIdempotencyKey(): string {
  return typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
}

export function PosPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can } = usePermission();
  const unit = useCurrencyUnit();
  const catalog = useCounterCatalog();
  const [cart, dispatch] = useReducer(cartReducer, emptyCart);
  const [created, setCreated] = useState<Order | null>(null);
  const [paying, setPaying] = useState(false);
  const [promoDraft, setPromoDraft] = useState("");
  const idempotencyKey = useRef(newIdempotencyKey());
  const create = useCreateOrder();

  const { branches, branchId, choose: chooseBranch } = useCounterBranch();

  const payload = useMemo(() => (cart.lines.length > 0 ? toPayload(cart) : null), [cart]);
  const debouncedPayload = useDebouncedValue(payload, 250);
  const preview = usePricePreview(debouncedPayload);
  const quote = payload ? preview.data : undefined;

  if (catalog.isLoading) {
    return <Skeleton active />;
  }
  if (catalog.isError || !catalog.data) {
    return <Result status="error" title={t("common.error")} extra={<Button onClick={() => catalog.refetch()}>{t("common.retry")}</Button>} />;
  }
  if (branches.length === 0) {
    return <Result status="warning" title={t("pos.noBranch")} />;
  }

  const data = catalog.data;
  const tierId = cart.tierId ?? data.turnaround_tiers.find((tier) => tier.is_default)?.id ?? data.turnaround_tiers[0]?.id ?? null;
  const canSubmit = cart.customerId !== null && cart.lines.length > 0 && branchId !== null && !preview.isError;

  const submit = () => {
    if (!canSubmit || branchId === null || cart.customerId === null) {
      return;
    }
    create.mutate(
      {
        idempotencyKey: idempotencyKey.current,
        input: { ...toPayload({ ...cart, tierId }), branch_id: branchId, customer_id: cart.customerId, notes: cart.notes || null },
      },
      {
        onSuccess: (order) => {
          setCreated(order);
          setPromoDraft("");
          dispatch({ type: "reset" });
          idempotencyKey.current = newIdempotencyKey();
        },
        onError: (error) => {
          const fieldErrors = isApiError(error, 422) ? Object.values(error.errors).flat() : [];
          message.error(fieldErrors[0] ?? errorMessage(error, t("common.error")));
        },
      },
    );
  };

  const previewError = preview.isError && isApiError(preview.error, 422) ? Object.values(preview.error.errors).flat()[0] : null;

  return (
    <>
      <Row gutter={[16, 16]}>
        <Col xs={24} xl={10}>
          <Flex vertical gap={12}>
            {branches.length > 1 && (
              <Select
                value={branchId}
                onChange={chooseBranch}
                options={branches.map((branch) => ({ value: branch.id, label: `${branch.name} (${branch.code})` }))}
                aria-label={t("pos.branch")}
              />
            )}
            <CustomerPicker
              customerId={cart.customerId}
              onChange={(customerId) => dispatch({ type: "setCustomer", customerId })}
              departmentId={cart.departmentId ?? null}
              onDepartmentChange={(departmentId) => dispatch({ type: "setDepartment", departmentId })}
            />

            <Card size="small" title={<Space><ShoppingCartOutlined />{t("pos.cart")}</Space>} styles={{ body: { padding: 12 } }}>
              {cart.lines.length === 0 ? (
                <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={t("pos.cartEmpty")} />
              ) : (
                <Flex vertical gap={8}>
                  {cart.lines.map((line, index) => (
                    <CartLineCard key={line.key} line={line} catalog={data} total={quote?.lines[index]?.line_total} dispatch={dispatch} />
                  ))}
                </Flex>
              )}
            </Card>

            {data.turnaround_tiers.length > 0 && (
              <Segmented
                block
                size="large"
                value={tierId ?? undefined}
                onChange={(value) => dispatch({ type: "setTier", tierId: Number(value) })}
                options={data.turnaround_tiers.map((tier) => ({
                  value: tier.id,
                  label: tier.surcharge_percent ? `${tier.name} (+${formatNumber(tier.surcharge_percent)}٪)` : tier.name,
                }))}
              />
            )}

            <Input
              prefix={<GiftOutlined />}
              placeholder={t("pos.promoCode")}
              value={promoDraft}
              onChange={(e) => setPromoDraft(e.target.value)}
              onBlur={() => dispatch({ type: "setPromoCode", code: promoDraft })}
              onPressEnter={() => dispatch({ type: "setPromoCode", code: promoDraft })}
              allowClear
              dir="ltr"
              maxLength={30}
              aria-label={t("pos.promoCode")}
            />
            <Input.TextArea placeholder={t("pos.orderNotes")} value={cart.notes} onChange={(e) => dispatch({ type: "setNotes", notes: e.target.value })} autoSize={{ minRows: 1, maxRows: 3 }} maxLength={2000} />

            {previewError && <Alert type="error" showIcon title={previewError} />}

            <Card size="small" styles={{ body: { padding: 12 } }}>
              <Descriptions column={1} size="small" colon={false} styles={{ content: { justifyContent: "flex-end" } }}>
                <Descriptions.Item label={t("pos.subtotal")}>{quote ? formatMoney(quote.subtotal, unit) : "—"}</Descriptions.Item>
                {quote && quote.discount_amount > 0 && (
                  <Descriptions.Item label={t("pos.discount")}>
                    <Typography.Text type="danger">−{formatMoney(quote.discount_amount, unit)}</Typography.Text>
                  </Descriptions.Item>
                )}
                <Descriptions.Item label={t("pos.tax")}>{quote ? formatMoney(quote.tax_amount, unit) : "—"}</Descriptions.Item>
                <Descriptions.Item label={t("pos.due")}>{quote ? formatDateTime(quote.due_at) : "—"}</Descriptions.Item>
                <Descriptions.Item label={t("pos.pieceTotal")}>{quote ? formatNumber(quote.piece_count) : "—"}</Descriptions.Item>
              </Descriptions>
              <Divider style={{ margin: "8px 0" }} />
              <Flex justify="space-between" align="center">
                <Typography.Text strong style={{ fontSize: 16 }}>
                  {t("pos.total")}
                </Typography.Text>
                <Typography.Text strong style={{ fontSize: 24 }} data-testid="pos-total">
                  {quote ? `${formatMoney(quote.total, unit)} ${t(`money.${unit}`)}` : "—"}
                </Typography.Text>
              </Flex>
              <Flex gap={8} style={{ marginTop: 12 }}>
                {can("orders.discount") && <DiscountButton discount={cart.discount} onChange={(discount) => dispatch({ type: "setDiscount", discount })} />}
                <Button type="primary" size="large" block disabled={!canSubmit} loading={create.isPending} onClick={submit}>
                  {t("pos.submit")}
                </Button>
              </Flex>
            </Card>
          </Flex>
        </Col>
        <Col xs={24} xl={14}>
          <Card styles={{ body: { padding: 16 } }}>
            <ItemGrid catalog={data} onAdd={(item, service) => dispatch({ type: "add", item, serviceTypeId: service.id })} />
          </Card>
        </Col>
      </Row>

      <Modal open={created !== null} footer={null} closable={false} width={460}>
        {created && (
          <Result
            icon={<CheckCircleFilled style={{ color: "#52c41a" }} />}
            title={t("pos.created")}
            subTitle={
              <Flex vertical gap={4}>
                <Typography.Text style={{ fontSize: 28 }} strong className="ltr" data-testid="ticket-number">
                  {created.ticket_number}
                </Typography.Text>
                <span>
                  {t("pos.total")}: {formatMoney(created.total, unit)} {t(`money.${unit}`)}
                </span>
                <span>
                  {t("pos.due")}: {formatDateTime(created.due_at)} · {t("pos.pieces", { count: created.piece_count ?? 0 })}
                </span>
              </Flex>
            }
            extra={[
              <Flex key="print" gap={8} justify="center" style={{ marginBottom: 8 }}>
                <Button icon={<PrinterOutlined />} onClick={() => openPrint(created.id, "receipt")}>
                  {t("printing.receipt")}
                </Button>
                <Button icon={<TagsOutlined />} onClick={() => openPrint(created.id, "tags")}>
                  {t("printing.tags")}
                </Button>
              </Flex>,
              created.balance > 0 && can("payments.create") && (
                <Button key="pay" icon={<DollarOutlined />} onClick={() => setPaying(true)}>
                  {t("billing.pay")}
                </Button>
              ),
              <Link key="view" href={`/orders/${created.id}`}>
                <Button>{t("pos.viewOrder")}</Button>
              </Link>,
              <Button key="new" type="primary" autoFocus onClick={() => setCreated(null)}>
                {t("pos.newOrder")}
              </Button>,
            ]}
          />
        )}
      </Modal>
      {created && (
        <PaymentModal
          order={created}
          mode="pay"
          open={paying}
          onClose={() => setPaying(false)}
          onDone={() => setCreated(null)}
        />
      )}
    </>
  );
}

function DiscountButton({ discount, onChange }: { discount: CartDiscount | null; onChange: (discount: CartDiscount | null) => void }) {
  const t = useTranslations("pos");
  const unit = useCurrencyUnit();
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState<CartDiscount>(discount ?? { type: "percent", value: 0, reason: "" });

  return (
    <Popover
      open={open}
      onOpenChange={(next) => {
        setOpen(next);
        if (next) {
          setDraft(discount ?? { type: "percent", value: 0, reason: "" });
        }
      }}
      trigger="click"
      title={t("discount")}
      content={
        <Flex vertical gap={8} style={{ width: 260 }}>
          <Radio.Group
            optionType="button"
            value={draft.type}
            onChange={(e) => setDraft({ ...draft, type: e.target.value, value: 0 })}
            options={[
              { value: "percent", label: t("discountPercent") },
              { value: "amount", label: t("discountAmount") },
            ]}
          />
          {draft.type === "percent" ? (
            <InputNumber<number>
              min={0}
              max={100}
              value={draft.value}
              formatter={inputNumberFormatter}
              parser={(text) => parseAmount(text) ?? 0}
              onChange={(value) => setDraft({ ...draft, value: value ?? 0 })}
              suffix="٪"
              style={{ width: "100%" }}
            />
          ) : (
            <MoneyInput unit={unit} value={draft.value} onChange={(rial) => setDraft({ ...draft, value: rial ?? 0 })} />
          )}
          <Input placeholder={t("discountReason")} value={draft.reason} onChange={(e) => setDraft({ ...draft, reason: e.target.value })} maxLength={255} />
          <Flex gap={8} justify="end">
            <Button
              onClick={() => {
                onChange(null);
                setOpen(false);
              }}
            >
              {t("discountRemove")}
            </Button>
            <Button
              type="primary"
              onClick={() => {
                onChange(draft);
                setOpen(false);
              }}
            >
              {t("discountApply")}
            </Button>
          </Flex>
        </Flex>
      }
    >
      <Button size="large" icon={<PercentageOutlined />} type={discount ? "primary" : "default"} ghost={Boolean(discount)} aria-label={t("discount")}>
        {discount ? (discount.type === "percent" ? `${toPersianDigits(discount.value)}٪` : formatMoney(discount.value, unit)) : null}
      </Button>
    </Popover>
  );
}
