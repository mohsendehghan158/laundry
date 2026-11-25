"use client";

import { BarcodeOutlined, CheckOutlined, DollarOutlined, WarningFilled } from "@ant-design/icons";
import { Alert, App, Button, Card, Checkbox, Col, Empty, Flex, Input, Result, Row, Skeleton, Statistic, Table, Tag, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { useEffect, useMemo, useRef, useState } from "react";
import { PaymentModal } from "@/features/billing/components/PaymentModal";
import { isApiError } from "@/shared/api/client";
import { errorMessage } from "@/shared/api/formErrors";
import type { Order, Piece } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatDateTime } from "@/shared/lib/date";
import { formatNumber, toLatinDigits } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useHandOver, useOrderLookup } from "../api";
import { ORDER_STATUS_COLOR } from "../status";

type PieceRow = Piece & { item_name: string; service_name: string };

/**
 * The counter's handover screen. Scanning a tag or receipt (a barcode
 * reader types it and presses Enter) brings up the order with its rack
 * location and balance; the clerk takes payment if needed and hands over
 * all ready pieces, or only some.
 */
export function HandoverPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can } = usePermission();
  const unit = useCurrencyUnit();
  const initial = useSearchParams().get("code");
  const [input, setInput] = useState(initial ?? "");
  const [code, setCode] = useState<string | null>(initial);
  const lookup = useOrderLookup(code);
  const handOver = useHandOver();
  const [paying, setPaying] = useState(false);
  const [unchecked, setUnchecked] = useState<Set<number>>(new Set());
  const inputRef = useRef<{ focus: () => void; select: () => void } | null>(null);

  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  const order = lookup.data?.order;
  const rows: PieceRow[] = useMemo(
    () => (order?.lines ?? []).flatMap((line) => (line.pieces ?? []).map((piece) => ({ ...piece, item_name: line.item_name, service_name: line.service_name }))),
    [order],
  );
  const ready = rows.filter((piece) => piece.status === "ready");
  const selected = ready.filter((piece) => !unchecked.has(piece.id));

  const scan = () => {
    const value = toLatinDigits(input.trim()).toUpperCase();
    if (value) {
      setUnchecked(new Set());
      setCode(value);
    }
    inputRef.current?.select();
  };

  const blockedByBalance = order !== undefined && order.balance > 0 && !can("orders.handover_unpaid");
  const canHandOver = order !== undefined && ["ready", "partially_ready"].includes(order.status) && selected.length > 0 && !blockedByBalance && can("orders.handover");

  const submit = (target: Order) =>
    handOver.mutate(
      { id: target.id, pieceIds: selected.map((piece) => piece.id) },
      {
        onSuccess: (updated) => {
          message.success(updated.status === "completed" ? t("handover.completed") : t("handover.partial"));
          setInput("");
          setCode(null);
          inputRef.current?.focus();
        },
        onError: (error) => message.error(errorMessage(error, t("common.error"))),
      },
    );

  const columns: ColumnsType<PieceRow> = [
    {
      key: "pick",
      width: 48,
      render: (_v, piece) => (
        <Checkbox
          checked={piece.status === "ready" && !unchecked.has(piece.id)}
          disabled={piece.status !== "ready"}
          onChange={(e) =>
            setUnchecked((current) => {
              const next = new Set(current);
              if (e.target.checked) {
                next.delete(piece.id);
              } else {
                next.add(piece.id);
              }
              return next;
            })
          }
          aria-label={piece.tag_code}
        />
      ),
    },
    { key: "tag", dataIndex: "tag_code", title: t("orders.detail.tag"), render: (v: string) => <Typography.Text code className="ltr">{v}</Typography.Text> },
    { key: "item", title: t("orders.detail.item"), render: (_v, p) => `${p.item_name} · ${p.service_name}` },
    { key: "color", dataIndex: "color_name", title: t("orders.detail.color"), render: (v: string | null) => v ?? "—" },
    {
      key: "status",
      dataIndex: "status",
      title: t("orders.columns.status"),
      render: (s: Piece["status"]) => <Tag color={s === "ready" ? "green" : s === "handed_over" ? "default" : "gold"}>{t(`pieces.statuses.${s}`)}</Tag>,
    },
  ];

  return (
    <>
      <PageHeader title={t("handover.title")} subtitle={t("handover.subtitle")} />
      <Input
        ref={inputRef as never}
        size="large"
        prefix={<BarcodeOutlined />}
        placeholder={t("handover.scanPlaceholder")}
        value={input}
        onChange={(e) => setInput(e.target.value)}
        onPressEnter={scan}
        dir="ltr"
        style={{ maxWidth: 480, marginBottom: 16 }}
        aria-label={t("handover.scanPlaceholder")}
        autoComplete="off"
      />

      {!code ? (
        <Card>
          <Empty description={t("handover.waiting")} />
        </Card>
      ) : lookup.isLoading ? (
        <Skeleton active />
      ) : lookup.isError || !order ? (
        <Result status="warning" title={isApiError(lookup.error, 404) ? t("handover.notFound", { code }) : t("common.error")} />
      ) : (
        <Row gutter={[16, 16]}>
          <Col xs={24} lg={8}>
            <Card>
              <Flex vertical gap={12}>
                <Flex justify="space-between" align="center">
                  <Link href={`/orders/${order.id}`} className="ltr" style={{ fontSize: 22, fontWeight: 700 }}>
                    {order.ticket_number}
                  </Link>
                  <Tag color={ORDER_STATUS_COLOR[order.status]}>{t(`orders.statuses.${order.status}`)}</Tag>
                </Flex>
                <div>
                  <Typography.Text strong>{order.customer.name}</Typography.Text>{" "}
                  <Typography.Text type="secondary" className="ltr">
                    {order.customer.mobile}
                  </Typography.Text>
                </div>
                {order.customer.alert && <Alert type="warning" showIcon icon={<WarningFilled />} title={order.customer.alert} />}
                <Statistic title={t("handover.rack")} value={order.rack_location ?? "—"} valueStyle={{ fontSize: 36, direction: "ltr" }} />
                <Statistic
                  title={t("orders.detail.balance")}
                  value={`${formatMoney(order.balance, unit)} ${t(`money.${unit}`)}`}
                  valueStyle={{ color: order.balance > 0 ? "#cf1322" : "#389e0d" }}
                />
                {order.balance > 0 && can("payments.create") && order.status !== "cancelled" && (
                  <Button size="large" icon={<DollarOutlined />} onClick={() => setPaying(true)}>
                    {t("billing.pay")}
                  </Button>
                )}
                <Typography.Text type="secondary">
                  {t("orders.detail.due")}: {formatDateTime(order.due_at)}
                </Typography.Text>
              </Flex>
            </Card>
          </Col>
          <Col xs={24} lg={16}>
            <Card title={t("handover.pieces", { ready: formatNumber(ready.length), total: formatNumber(rows.length) })} styles={{ body: { padding: 0 } }}>
              <Table<PieceRow>
                rowKey="id"
                size="small"
                columns={columns}
                dataSource={rows}
                pagination={false}
                rowClassName={(piece) => (piece.id === lookup.data?.matchedPieceId ? "handover-matched" : "")}
                scroll={{ x: "max-content" }}
              />
            </Card>
            {!["ready", "partially_ready"].includes(order.status) && (
              <Alert type="info" showIcon style={{ marginTop: 12 }} title={order.status === "completed" ? t("handover.alreadyDone") : t("handover.notReady")} />
            )}
            {blockedByBalance && <Alert type="error" showIcon style={{ marginTop: 12 }} title={t("handover.payFirst")} />}
            <Flex justify="end" style={{ marginTop: 12 }}>
              <Button type="primary" size="large" icon={<CheckOutlined />} disabled={!canHandOver} loading={handOver.isPending} onClick={() => submit(order)}>
                {selected.length === ready.length ? t("handover.giveAll") : t("handover.giveSome", { count: selected.length })}
              </Button>
            </Flex>
          </Col>
          <PaymentModal order={order} mode="pay" open={paying} onClose={() => setPaying(false)} onDone={() => lookup.refetch()} />
        </Row>
      )}
    </>
  );
}
