"use client";

import { PlusOutlined, WarningOutlined } from "@ant-design/icons";
import { Button, Card, Input, Space, Tooltip, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useState } from "react";
import type { Customer } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { useListParams } from "@/shared/hooks/useListParams";
import { formatDate } from "@/shared/lib/date";
import { DataTable } from "@/shared/ui/DataTable";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useCustomers } from "../api";
import { CustomerFormModal } from "./CustomerFormModal";

export function CustomersPage() {
  const t = useTranslations();
  const router = useRouter();
  const { can } = usePermission();
  const [params, setParams, setFilter] = useListParams();
  const [creating, setCreating] = useState(false);
  const customers = useCustomers({ q: params.q, sort: params.sort, page: params.page, per_page: params.per_page });

  const columns: ColumnsType<Customer> = [
    {
      key: "name",
      dataIndex: "name",
      title: t("customers.fields.name"),
      sorter: true,
      render: (name: string, customer) => (
        <Space>
          <Link href={`/customers/${customer.id}`}>{name}</Link>
          {customer.alert && (
            <Tooltip title={customer.alert}>
              <WarningOutlined style={{ color: "#faad14" }} aria-label={t("customers.fields.alert")} />
            </Tooltip>
          )}
        </Space>
      ),
    },
    { key: "mobile", dataIndex: "mobile", title: t("customers.fields.mobile"), render: (v: string) => <span className="ltr">{v}</span> },
    { key: "phone", dataIndex: "phone", title: t("customers.fields.phone"), render: (v: string | null) => v && <span className="ltr">{v}</span> },
    { key: "created_at", dataIndex: "created_at", title: t("customers.fields.createdAt"), sorter: true, render: (v: string | null) => formatDate(v) },
  ];

  return (
    <>
      <PageHeader
        title={t("customers.title")}
        extra={
          can("customers.manage") && (
            <Button type="primary" icon={<PlusOutlined />} onClick={() => setCreating(true)}>
              {t("customers.new")}
            </Button>
          )
        }
      />
      <Card>
        <Input.Search
          allowClear
          size="large"
          defaultValue={params.q}
          placeholder={t("customers.searchPlaceholder")}
          onSearch={(q) => setFilter({ q: q || null })}
          style={{ maxWidth: 420, marginBottom: 16 }}
        />
        <DataTable<Customer>
          columns={columns}
          result={customers.data}
          loading={customers.isFetching}
          sort={params.sort ?? "-created_at"}
          onPageChange={(page, perPage) => setParams({ page, per_page: perPage })}
          onSortChange={(sort) => setFilter({ sort })}
          onRow={(customer) => ({ onDoubleClick: () => router.push(`/customers/${customer.id}`) })}
        />
        {customers.data?.meta.total === 0 && params.q && (
          <Typography.Text type="secondary">{t("customers.noMatch")}</Typography.Text>
        )}
      </Card>
      <CustomerFormModal
        open={creating}
        onClose={() => setCreating(false)}
        onSaved={(customer) => router.push(`/customers/${customer.id}`)}
      />
    </>
  );
}
