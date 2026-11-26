"use client";

import { RightOutlined } from "@ant-design/icons";
import { Alert, App, Button, Card, Descriptions, Divider, Flex, Popconfirm, Result, Skeleton, Steps, Tag, Typography } from "antd";
import { Fragment, type ReactNode } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { isApiError } from "@/shared/api/client";
import { errorMessage } from "@/shared/api/formErrors";
import { dayjs, formatDateTime } from "@/shared/lib/date";
import { formatNumber } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";
import { useCancelOrder, useMyOrder, usePortalSettings } from "../api";
import { currentStep, statusColor, stepsFor } from "../status";
import { windowLabel } from "../onlineOrder";

function Row({ first, title, description, amount }: { first: boolean; title: string; description: string; amount: ReactNode }) {
  return (
    <Fragment>
      {!first && <Divider style={{ marginBlock: 8 }} />}
      <Flex justify="space-between" align="center" gap={12}>
        <Flex vertical style={{ minWidth: 0 }}>
          <Typography.Text>{title}</Typography.Text>
          <Typography.Text type="secondary" style={{ fontSize: 13 }}>
            {description}
          </Typography.Text>
        </Flex>
        <span style={{ flexShrink: 0 }}>{amount}</span>
      </Flex>
    </Fragment>
  );
}

/** "14:00" in the viewer's time zone. */
function formatTime(value: string): string {
  return dayjs(value).format("HH:mm");
}

/** One of the customer's orders: where it is, what is in it and what is owed. */
export function MyOrder() {
  const t = useTranslations("portal.order");
  const tm = useTranslations("tracking");
  const units = useTranslations("catalog.units");
  const methods = useTranslations("billing.methods");
  const kinds = useTranslations("billing.kinds");
  const publicId = useParams<{ publicId: string }>().publicId;
  const unit = usePortalSettings().data?.currency_unit ?? "toman";
  const order = useMyOrder(publicId);
  const cancel = useCancelOrder();
  const { message } = App.useApp();

  const back = (
    <Link href="/my">
      <RightOutlined aria-hidden /> {t("back")}
    </Link>
  );

  if (order.isLoading) {
    return <Skeleton active />;
  }
  if (order.isError || !order.data) {
    return <Result status="404" title={isApiError(order.error, 404) ? t("notFound") : tm("error")} extra={back} />;
  }

  const data = order.data;
  const money = (rial: number) => `${formatMoney(rial, unit)} ${tm(unit)}`;
  const cancelled = data.status === "cancelled";
  const steps = stepsFor(data.source);
  const step = currentStep(data.status, steps);
  const readyStep = steps.indexOf("ready");
  const at = (status: string) => data.timeline?.find((entry) => entry.status === status)?.at;

  return (
    <Flex vertical gap={16}>
      {back}
      <Card>
        <Flex justify="space-between" align="center" gap={12} style={{ marginBottom: 16 }}>
          <Typography.Text strong className="ltr" style={{ fontSize: 22 }}>
            {data.ticket_number}
          </Typography.Text>
          <Tag color={statusColor(data.status)} style={{ fontSize: 14 }}>
            {tm(`statuses.${data.status}`)}
          </Tag>
        </Flex>
        {cancelled ? (
          <Alert type="error" showIcon title={tm("cancelled")} />
        ) : (
          <Steps
            orientation="vertical"
            size="small"
            current={step}
            items={steps.map((status) => ({
              title: tm(`statuses.${status}`),
              content: at(status) ? formatDateTime(at(status)) : status === "ready" && step < readyStep && data.due_at ? `${tm("expected")} ${formatDateTime(data.due_at)}` : undefined,
            }))}
          />
        )}
        {data.status === "ready" && <Alert type="success" showIcon title={t("readyNotice")} style={{ marginTop: 16 }} />}
      </Card>

      {data.pickup && (
        <Card size="small" title={t("pickup")}>
          <Descriptions column={1} size="small">
            <Descriptions.Item label={t("pickupWindow")}>
              {formatDateTime(data.pickup.starts_at, false)} <span className="ltr">{windowLabel(formatTime(data.pickup.starts_at), formatTime(data.pickup.ends_at))}</span>
            </Descriptions.Item>
            <Descriptions.Item label={t("pickupAddress")}>{data.pickup.address}</Descriptions.Item>
          </Descriptions>
          {data.items_by_driver && data.lines?.length === 0 && <Alert type="info" showIcon title={t("byDriver")} style={{ marginTop: 8 }} />}
          {data.can_cancel && (
            <Popconfirm
              title={t("cancelConfirm")}
              okText={t("cancel")}
              okButtonProps={{ danger: true }}
              onConfirm={() =>
                cancel.mutateAsync(data.public_id).then(
                  () => message.success(t("cancelled")),
                  (error: unknown) => message.error(errorMessage(error, t("cancelFailed"))),
                )
              }
            >
              <Button danger block loading={cancel.isPending} style={{ marginTop: 12 }}>
                {t("cancel")}
              </Button>
            </Popconfirm>
          )}
        </Card>
      )}

      <Card size="small">
        <Descriptions column={1} size="small">
          <Descriptions.Item label={t("branch")}>{data.branch_name}</Descriptions.Item>
          {data.received_at && <Descriptions.Item label={t("receivedAt")}>{formatDateTime(data.received_at)}</Descriptions.Item>}
          {data.due_at && <Descriptions.Item label={t("dueAt")}>{formatDateTime(data.due_at)}</Descriptions.Item>}
        </Descriptions>
      </Card>

      {data.lines && data.lines.length > 0 && (
        <Card size="small" title={t("items")}>
          {data.lines.map((line, index) => (
            <Row
              key={index}
              first={index === 0}
              title={line.item_name}
              description={`${line.service_name}، ${formatNumber(Number(line.quantity))} ${units(line.unit as "piece")}`}
              amount={money(line.line_total)}
            />
          ))}
        </Card>
      )}

      <Card size="small">
        <Descriptions column={1} size="small">
          <Descriptions.Item label={t("subtotal")}>{money(data.subtotal)}</Descriptions.Item>
          {data.discount_amount > 0 && <Descriptions.Item label={t("discount")}>{money(data.discount_amount)}</Descriptions.Item>}
          {data.tax_amount > 0 && <Descriptions.Item label={t("tax")}>{money(data.tax_amount)}</Descriptions.Item>}
          {data.delivery_fee > 0 && <Descriptions.Item label={t("deliveryFee")}>{money(data.delivery_fee)}</Descriptions.Item>}
          <Descriptions.Item label={t("total")}>
            <Typography.Text strong>{money(data.total)}</Typography.Text>
          </Descriptions.Item>
          <Descriptions.Item label={t("paid")}>{money(data.paid_amount)}</Descriptions.Item>
          <Descriptions.Item label={t("balance")}>
            <Typography.Text strong type={data.balance > 0 ? "danger" : "success"}>
              {data.balance > 0 ? money(data.balance) : tm("settled")}
            </Typography.Text>
          </Descriptions.Item>
        </Descriptions>
      </Card>

      {data.payments && data.payments.length > 0 && (
        <Card size="small" title={t("payments")}>
          {data.payments.map((payment, index) => (
            <Row
              key={index}
              first={index === 0}
              title={`${kinds(payment.kind)}، ${methods(payment.method)}`}
              description={formatDateTime(payment.at)}
              amount={<Typography.Text type={payment.kind === "refund" ? "danger" : undefined}>{money(payment.amount)}</Typography.Text>}
            />
          ))}
        </Card>
      )}
    </Flex>
  );
}
