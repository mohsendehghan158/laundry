"use client";

import { LeftOutlined } from "@ant-design/icons";
import { Alert, Button, Card, Empty, Flex, Skeleton, Tag, Typography } from "antd";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { formatDateTime } from "@/shared/lib/date";
import { formatMoney } from "@/shared/lib/money";
import { useMyOrders, usePortalSettings } from "../api";
import { statusColor } from "../status";

/** The customer's orders, newest first, each a tappable card. */
export function MyOrders() {
  const t = useTranslations("portal");
  const tc = useTranslations("common");
  const tm = useTranslations("tracking");
  const unit = usePortalSettings().data?.currency_unit ?? "toman";
  const orders = useMyOrders();

  if (orders.isLoading) {
    return <Skeleton active />;
  }
  if (orders.isError) {
    return (
      <Alert
        type="error"
        showIcon
        title={t("orders.error")}
        action={
          <Button size="small" onClick={() => orders.refetch()}>
            {tc("retry")}
          </Button>
        }
      />
    );
  }

  return (
    <Flex vertical gap={12}>
      <Typography.Title level={4} style={{ margin: 0 }}>
        {t("nav.orders")}
      </Typography.Title>
      {orders.data?.length === 0 && <Empty description={t("orders.empty")}>{t("orders.emptyHint")}</Empty>}
      {orders.data?.map((order) => (
        <Link key={order.public_id} href={`/my/orders/${order.public_id}`} aria-label={order.ticket_number}>
          <Card size="small" hoverable>
            <Flex justify="space-between" align="center" gap={12}>
              <Flex vertical gap={4} style={{ minWidth: 0 }}>
                <Flex align="center" gap={8} wrap>
                  <Typography.Text strong className="ltr">
                    {order.ticket_number}
                  </Typography.Text>
                  <Tag color={statusColor(order.status)}>{tm(`statuses.${order.status}`)}</Tag>
                </Flex>
                <Typography.Text type="secondary" style={{ fontSize: 13 }}>
                  {order.branch_name}، {t("orders.pieces", { count: order.piece_count })}
                </Typography.Text>
                {order.due_at && order.status !== "completed" && order.status !== "cancelled" && (
                  <Typography.Text type="secondary" style={{ fontSize: 13 }}>
                    {t("orders.due")}: {formatDateTime(order.due_at)}
                  </Typography.Text>
                )}
              </Flex>
              <Flex align="center" gap={8} style={{ flexShrink: 0 }}>
                <Typography.Text type={order.balance > 0 ? "danger" : "secondary"} style={{ fontSize: 13 }}>
                  {order.balance > 0 ? `${t("orders.balance")} ${formatMoney(order.balance, unit)} ${tm(unit)}` : t("orders.settled")}
                </Typography.Text>
                <LeftOutlined aria-hidden />
              </Flex>
            </Flex>
          </Card>
        </Link>
      ))}
    </Flex>
  );
}
