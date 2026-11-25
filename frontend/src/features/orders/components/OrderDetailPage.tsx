"use client";

import { CheckOutlined, CloseCircleOutlined, DollarOutlined, PlayCircleOutlined, RollbackOutlined, UndoOutlined } from "@ant-design/icons";
import { Alert, App, Button, Card, Col, Descriptions, Flex, Input, Modal, Result, Row, Skeleton, Table, Tag, Timeline, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import Link from "next/link";
import { useParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { isApiError } from "@/shared/api/client";
import { errorMessage } from "@/shared/api/formErrors";
import type { OrderLine, OrderStatus, Piece } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { usePermission } from "@/shared/hooks/usePermission";
import { dayjs, formatDateTime } from "@/shared/lib/date";
import { formatNumber } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";
import { PageHeader } from "@/shared/ui/PageHeader";
import { PaymentModal } from "@/features/billing/components/PaymentModal";
import { useOrder, useTransitionOrder } from "../api";
import { OPEN_STATUSES, ORDER_STATUS_COLOR, PAYMENT_STATUS_COLOR } from "../status";

type PieceRow = Piece & { item_name: string; service_name: string };

export function OrderDetailPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can } = usePermission();
  const unit = useCurrencyUnit();
  const id = Number(useParams<{ id: string }>().id);
  const order = useOrder(Number.isFinite(id) ? id : null);
  const transition = useTransitionOrder();
  const [asking, setAsking] = useState<"ready" | "cancelled" | null>(null);
  const [answer, setAnswer] = useState("");
  const [paying, setPaying] = useState<"pay" | "refund" | null>(null);

  if (order.isLoading) {
    return <Skeleton active />;
  }
  if (order.isError || !order.data) {
    const notFound = isApiError(order.error, 404) || isApiError(order.error, 403);
    return (
      <Result
        status={notFound ? "404" : "error"}
        title={notFound ? t("orders.notFound") : t("common.error")}
        extra={<Link href="/orders">{t("orders.backToList")}</Link>}
      />
    );
  }

  const data = order.data;
  const money = (value: number) => `${formatMoney(value, unit)} ${t(`money.${unit}`)}`;
  const overdue = OPEN_STATUSES.includes(data.status) && dayjs(data.due_at).isBefore(dayjs());

  const move = (to: OrderStatus, extra: { reason?: string; rack_location?: string | null } = {}) =>
    transition.mutate(
      { id: data.id, to, ...extra },
      {
        onSuccess: () => {
          message.success(t("common.saved"));
          setAsking(null);
          setAnswer("");
        },
        onError: (error) => message.error(errorMessage(error, t("common.error"))),
      },
    );

  const can_ = (to: OrderStatus) => data.available_transitions.includes(to) && can(to === "cancelled" ? "orders.cancel" : "orders.update");

  const lineColumns: ColumnsType<OrderLine> = [
    {
      key: "item",
      title: t("orders.detail.item"),
      render: (_v, line) => (
        <Flex vertical>
          <span>
            {line.item_name} <Tag>{line.service_name}</Tag>
          </span>
          {line.notes && <Typography.Text type="secondary">{line.notes}</Typography.Text>}
        </Flex>
      ),
    },
    { key: "quantity", title: t("orders.detail.quantity"), render: (_v, line) => `${formatNumber(line.quantity)} ${t(`catalog.units.${line.unit}`)}` },
    { key: "unit_price", dataIndex: "unit_price", title: t("orders.detail.unitPrice"), render: (v: number) => formatMoney(v, unit) },
    {
      key: "upcharges",
      title: t("orders.detail.upcharges"),
      render: (_v, line) => (line.upcharges.length === 0 ? "—" : line.upcharges.map((u) => <Tag key={u.name}>{u.name}</Tag>)),
    },
    { key: "surcharge", dataIndex: "surcharge_amount", title: t("orders.detail.surcharge"), render: (v: number) => (v ? formatMoney(v, unit) : "—") },
    { key: "total", dataIndex: "line_total", title: t("orders.detail.lineTotal"), render: (v: number) => <strong>{formatMoney(v, unit)}</strong> },
  ];

  const pieces: PieceRow[] = (data.lines ?? []).flatMap((line) => (line.pieces ?? []).map((piece) => ({ ...piece, item_name: line.item_name, service_name: line.service_name })));
  const pieceColumns: ColumnsType<PieceRow> = [
    { key: "tag", dataIndex: "tag_code", title: t("orders.detail.tag"), render: (v: string) => <Typography.Text code className="ltr">{v}</Typography.Text> },
    { key: "item", title: t("orders.detail.item"), render: (_v, p) => `${p.item_name} · ${p.service_name}` },
    { key: "status", dataIndex: "status", title: t("orders.columns.status"), render: (s: Piece["status"]) => <Tag>{t(`pieces.statuses.${s}`)}</Tag> },
    { key: "color", dataIndex: "color_name", title: t("orders.detail.color"), render: (v: string | null) => v ?? "—" },
    { key: "brand", dataIndex: "brand", title: t("orders.detail.brand"), render: (v: string | null) => v ?? "—" },
    {
      key: "defects",
      title: t("orders.detail.defects"),
      render: (_v, p) => (p.defects.length === 0 ? "—" : p.defects.map((d) => <Tag key={d.name} color={d.kind === "stain" ? "orange" : "red"}>{d.name}</Tag>)),
    },
    { key: "notes", dataIndex: "notes", title: t("orders.detail.notes"), render: (v: string | null) => v ?? "—" },
  ];

  return (
    <>
      <PageHeader
        title={
          <Flex align="center" gap={8} wrap>
            <span className="ltr">{data.ticket_number}</span>
            <Tag color={ORDER_STATUS_COLOR[data.status]}>{t(`orders.statuses.${data.status}`)}</Tag>
            <Tag color={PAYMENT_STATUS_COLOR[data.payment_status]}>{t(`orders.paymentStatuses.${data.payment_status}`)}</Tag>
          </Flex>
        }
        subtitle={
          <Link href={`/customers/${data.customer.id}`}>
            {data.customer.name} · <span className="ltr">{data.customer.mobile}</span>
          </Link>
        }
        extra={
          <>
            {can_("in_process") && (
              <Button icon={data.status === "ready" ? <RollbackOutlined /> : <PlayCircleOutlined />} loading={transition.isPending} onClick={() => move("in_process")}>
                {data.status === "ready" ? t("orders.actions.backToProcess") : t("orders.actions.in_process")}
              </Button>
            )}
            {can_("ready") && (
              <Button type="primary" icon={<CheckOutlined />} onClick={() => setAsking("ready")}>
                {t("orders.actions.ready")}
              </Button>
            )}
            {can_("cancelled") && (
              <Button danger icon={<CloseCircleOutlined />} onClick={() => setAsking("cancelled")}>
                {t("orders.actions.cancelled")}
              </Button>
            )}
          </>
        }
      />
      {data.customer.alert && <Alert type="warning" showIcon title={data.customer.alert} style={{ marginBottom: 16 }} />}
      {data.status === "cancelled" && data.cancel_reason && (
        <Alert type="error" showIcon title={`${t("orders.detail.cancelReason")}: ${data.cancel_reason}`} style={{ marginBottom: 16 }} />
      )}
      <Row gutter={[16, 16]}>
        <Col xs={24} lg={16}>
          <Flex vertical gap={16}>
            <Card title={t("orders.detail.lines")} styles={{ body: { padding: 0 } }}>
              <Table<OrderLine> rowKey="id" size="small" columns={lineColumns} dataSource={data.lines ?? []} pagination={false} scroll={{ x: "max-content" }} />
            </Card>
            <Card title={`${t("orders.detail.pieces")} (${formatNumber(pieces.length)})`} styles={{ body: { padding: 0 } }}>
              <Table<PieceRow> rowKey="id" size="small" columns={pieceColumns} dataSource={pieces} pagination={false} scroll={{ x: "max-content" }} />
            </Card>
          </Flex>
        </Col>
        <Col xs={24} lg={8}>
          <Flex vertical gap={16}>
            <Card title={t("orders.detail.summary")}>
              <Descriptions column={1} size="small">
                <Descriptions.Item label={t("orders.detail.subtotal")}>{money(data.subtotal)}</Descriptions.Item>
                {data.discount && (
                  <Descriptions.Item label={t("orders.detail.discount")}>
                    −{money(data.discount.amount)} {data.discount.reason && <Typography.Text type="secondary">({data.discount.reason})</Typography.Text>}
                  </Descriptions.Item>
                )}
                <Descriptions.Item label={t("orders.detail.tax")}>{money(data.tax_amount)}</Descriptions.Item>
                <Descriptions.Item label={t("orders.detail.total")}>
                  <strong>{money(data.total)}</strong>
                </Descriptions.Item>
                <Descriptions.Item label={t("orders.detail.paid")}>{money(data.paid_amount)}</Descriptions.Item>
                <Descriptions.Item label={t("orders.detail.balance")}>
                  <Typography.Text type={data.balance > 0 ? "danger" : "success"} strong>
                    {money(data.balance)}
                  </Typography.Text>
                </Descriptions.Item>
              </Descriptions>
            </Card>
            <Card
              title={t("billing.payments")}
              extra={
                <Flex gap={4}>
                  {data.balance > 0 && data.status !== "cancelled" && can("payments.create") && (
                    <Button type="primary" size="small" icon={<DollarOutlined />} onClick={() => setPaying("pay")}>
                      {t("billing.pay")}
                    </Button>
                  )}
                  {data.paid_amount > 0 && can("payments.refund") && (
                    <Button size="small" danger icon={<UndoOutlined />} onClick={() => setPaying("refund")}>
                      {t("billing.refund")}
                    </Button>
                  )}
                </Flex>
              }
            >
              {(data.payments ?? []).length === 0 ? (
                <Typography.Text type="secondary">{t("billing.noPayments")}</Typography.Text>
              ) : (
                <Flex vertical gap={6}>
                  {(data.payments ?? []).map((payment) => (
                    <Flex key={payment.id} justify="space-between" gap={8}>
                      <span>
                        <Tag color={payment.kind === "refund" ? "red" : "green"}>{t(`billing.methods.${payment.method}`)}</Tag>
                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                          {formatDateTime(payment.created_at)}
                          {payment.reference ? ` · ${payment.reference}` : ""}
                        </Typography.Text>
                      </span>
                      <Typography.Text type={payment.kind === "refund" ? "danger" : undefined}>
                        {payment.kind === "refund" ? "−" : ""}
                        {formatMoney(payment.amount, unit)}
                      </Typography.Text>
                    </Flex>
                  ))}
                </Flex>
              )}
            </Card>
            <Card>
              <Descriptions column={1} size="small">
                <Descriptions.Item label={t("orders.detail.received")}>{formatDateTime(data.received_at)}</Descriptions.Item>
                <Descriptions.Item label={t("orders.detail.due")}>
                  <Typography.Text type={overdue ? "danger" : undefined}>
                    {formatDateTime(data.due_at)} {overdue && <Tag color="red">{t("orders.detail.overdue")}</Tag>}
                  </Typography.Text>
                </Descriptions.Item>
                {data.turnaround.name && <Descriptions.Item label={t("orders.detail.turnaround")}>{data.turnaround.name}</Descriptions.Item>}
                {data.ready_at && <Descriptions.Item label={t("orders.detail.readyAt")}>{formatDateTime(data.ready_at)}</Descriptions.Item>}
                {data.rack_location && (
                  <Descriptions.Item label={t("orders.detail.rack")}>
                    <Tag color="green" className="ltr">
                      {data.rack_location}
                    </Tag>
                  </Descriptions.Item>
                )}
                <Descriptions.Item label={t("orders.columns.branch")}>{data.branch.name}</Descriptions.Item>
                {data.created_by && <Descriptions.Item label={t("orders.detail.createdBy")}>{data.created_by.name}</Descriptions.Item>}
                {data.notes && <Descriptions.Item label={t("orders.detail.notes")}>{data.notes}</Descriptions.Item>}
              </Descriptions>
            </Card>
            <Card title={t("orders.detail.history")}>
              <Timeline
                items={(data.history ?? []).map((change) => ({
                  color: ORDER_STATUS_COLOR[change.to_status] === "default" ? "gray" : ORDER_STATUS_COLOR[change.to_status],
                  content: (
                    <Flex vertical>
                      <span>{t(`orders.statuses.${change.to_status}`)}</span>
                      <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                        {formatDateTime(change.created_at)} · {change.user?.name ?? t("orders.detail.system")}
                      </Typography.Text>
                      {change.reason && <Typography.Text type="secondary">{change.reason}</Typography.Text>}
                    </Flex>
                  ),
                }))}
              />
            </Card>
          </Flex>
        </Col>
      </Row>
      <PaymentModal order={data} mode={paying ?? "pay"} open={paying !== null} onClose={() => setPaying(null)} />
      <Modal
        open={asking !== null}
        title={asking === "ready" ? t("orders.actions.ready") : t("orders.actions.cancelled")}
        okText={t("orders.actions.confirm")}
        cancelText={t("common.cancel")}
        okButtonProps={{ danger: asking === "cancelled", disabled: asking === "cancelled" && answer.trim() === "" }}
        confirmLoading={transition.isPending}
        onCancel={() => {
          setAsking(null);
          setAnswer("");
        }}
        onOk={() => (asking === "ready" ? move("ready", { rack_location: answer.trim() || null }) : move("cancelled", { reason: answer.trim() }))}
        destroyOnHidden
      >
        <Typography.Paragraph>{asking === "ready" ? t("orders.actions.rackPrompt") : t("orders.actions.cancelPrompt")}</Typography.Paragraph>
        <Input
          autoFocus
          value={answer}
          onChange={(e) => setAnswer(e.target.value)}
          placeholder={asking === "ready" ? t("orders.actions.rackPlaceholder") : t("orders.actions.cancelReason")}
          maxLength={asking === "ready" ? 50 : 500}
          aria-label={asking === "ready" ? t("orders.detail.rack") : t("orders.actions.cancelReason")}
        />
      </Modal>
    </>
  );
}
