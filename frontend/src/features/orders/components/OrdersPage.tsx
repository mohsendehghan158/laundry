"use client";

import { PlusOutlined } from "@ant-design/icons";
import { Button, Card, Flex, Input, Segmented, Select, Tag, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { parseAsArrayOf, parseAsInteger, parseAsStringLiteral } from "nuqs";
import type { Order, OrderStatus } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { useListParams } from "@/shared/hooks/useListParams";
import { usePermission } from "@/shared/hooks/usePermission";
import { dayjs, formatDateTime } from "@/shared/lib/date";
import { formatNumber } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";
import { DataTable } from "@/shared/ui/DataTable";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useOrders } from "../api";
import { OPEN_STATUSES, ORDER_STATUS_COLOR, PAYMENT_STATUS_COLOR } from "../status";

const STATUSES: OrderStatus[] = ["received", "in_process", "ready", "completed", "cancelled"];
const DUE = ["today", "overdue"] as const;

export function OrdersPage() {
  const t = useTranslations();
  const router = useRouter();
  const unit = useCurrencyUnit();
  const { can, me } = usePermission();
  const [params, setParams, setFilter] = useListParams({
    status: parseAsArrayOf(parseAsStringLiteral(STATUSES)),
    due: parseAsStringLiteral(DUE),
    branch: parseAsInteger,
  });

  const orders = useOrders({
    q: params.q,
    status: params.status,
    due: params.due,
    branch_id: params.branch,
    sort: params.sort,
    page: params.page,
    per_page: params.per_page,
  });

  const columns: ColumnsType<Order> = [
    {
      key: "ticket_number",
      dataIndex: "ticket_number",
      title: t("orders.columns.ticket"),
      sorter: true,
      render: (value: string, order) => (
        <Link href={`/orders/${order.id}`} className="ltr">
          {value}
        </Link>
      ),
    },
    {
      key: "customer",
      dataIndex: "customer",
      title: t("orders.columns.customer"),
      render: (customer: Order["customer"]) => (
        <Flex vertical>
          <span>{customer.name}</span>
          <Typography.Text type="secondary" className="ltr" style={{ fontSize: 12 }}>
            {customer.mobile}
          </Typography.Text>
        </Flex>
      ),
    },
    { key: "status", dataIndex: "status", title: t("orders.columns.status"), render: (s: OrderStatus) => <Tag color={ORDER_STATUS_COLOR[s]}>{t(`orders.statuses.${s}`)}</Tag> },
    {
      key: "payment",
      dataIndex: "payment_status",
      title: t("orders.columns.payment"),
      render: (s: Order["payment_status"]) => <Tag color={PAYMENT_STATUS_COLOR[s]}>{t(`orders.paymentStatuses.${s}`)}</Tag>,
    },
    { key: "pieces", dataIndex: "piece_count", title: t("orders.columns.pieces"), render: (v: number | undefined) => formatNumber(v ?? 0) },
    { key: "total", dataIndex: "total", title: t("orders.columns.total"), render: (v: number) => formatMoney(v, unit) },
    {
      key: "due_at",
      dataIndex: "due_at",
      title: t("orders.columns.due"),
      sorter: true,
      render: (v: string, order) => {
        const late = OPEN_STATUSES.includes(order.status) && dayjs(v).isBefore(dayjs());
        return <Typography.Text type={late ? "danger" : undefined}>{formatDateTime(v)}</Typography.Text>;
      },
    },
    { key: "created_at", dataIndex: "created_at", title: t("orders.columns.created"), sorter: true, render: (v: string | null) => formatDateTime(v) },
  ];

  if ((me?.branches.length ?? 0) > 1) {
    columns.splice(2, 0, { key: "branch", dataIndex: "branch", title: t("orders.columns.branch"), render: (b: Order["branch"]) => b.name });
  }

  return (
    <>
      <PageHeader
        title={t("orders.title")}
        extra={
          can("orders.create") && (
            <Link href="/pos">
              <Button type="primary" icon={<PlusOutlined />}>
                {t("pos.newOrder")}
              </Button>
            </Link>
          )
        }
      />
      <Card>
        <Flex gap={8} wrap style={{ marginBottom: 16 }}>
          <Input.Search
            allowClear
            defaultValue={params.q}
            placeholder={t("orders.searchPlaceholder")}
            onSearch={(q) => setFilter({ q: q || null })}
            style={{ width: 300 }}
          />
          <Select
            mode="multiple"
            allowClear
            placeholder={t("orders.filters.status")}
            value={params.status ?? []}
            onChange={(status: OrderStatus[]) => setFilter({ status: status.length ? status : null })}
            options={STATUSES.map((s) => ({ value: s, label: t(`orders.statuses.${s}`) }))}
            style={{ minWidth: 200 }}
          />
          <Segmented
            value={params.due ?? "all"}
            onChange={(value) => setFilter({ due: value === "all" ? null : (value as (typeof DUE)[number]) })}
            options={[
              { value: "all", label: t("common.all") },
              { value: "today", label: t("orders.filters.today") },
              { value: "overdue", label: t("orders.filters.overdue") },
            ]}
          />
          {(me?.branches.length ?? 0) > 1 && (
            <Select
              allowClear
              placeholder={t("orders.columns.branch")}
              value={params.branch}
              onChange={(branch) => setFilter({ branch: branch ?? null })}
              options={me?.branches.map((b) => ({ value: b.id, label: b.name }))}
              style={{ width: 180 }}
            />
          )}
        </Flex>
        <DataTable<Order>
          columns={columns}
          result={orders.data}
          loading={orders.isFetching}
          sort={params.sort ?? "-created_at"}
          onPageChange={(page, perPage) => setParams({ page, per_page: perPage })}
          onSortChange={(sort) => setFilter({ sort })}
          onRow={(order) => ({ onDoubleClick: () => router.push(`/orders/${order.id}`) })}
        />
      </Card>
    </>
  );
}
