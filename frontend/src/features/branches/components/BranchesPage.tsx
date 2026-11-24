"use client";

import { DeleteOutlined, EditOutlined, PlusOutlined } from "@ant-design/icons";
import { App, Button, Card, Flex, Input, Popconfirm, Select, Space, Tag, Tooltip } from "antd";
import type { ColumnsType } from "antd/es/table";
import { useTranslations } from "next-intl";
import { parseAsStringLiteral } from "nuqs";
import { useState } from "react";
import { errorMessage } from "@/shared/api/formErrors";
import type { Branch } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { useListParams } from "@/shared/hooks/useListParams";
import { formatNumber } from "@/shared/lib/digits";
import { DataTable } from "@/shared/ui/DataTable";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useBranches, useDeleteBranch } from "../api";
import { BRANCH_TYPES, BranchFormModal } from "./BranchFormModal";

const ACTIVE = ["active", "inactive"] as const;

export function BranchesPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can } = usePermission();
  const [params, setParams, setFilter] = useListParams({
    type: parseAsStringLiteral(BRANCH_TYPES),
    status: parseAsStringLiteral(ACTIVE),
  });
  const [editing, setEditing] = useState<Branch | "new" | null>(null);

  const branches = useBranches({
    q: params.q,
    type: params.type,
    is_active: params.status === null ? null : params.status === "active",
    sort: params.sort,
    page: params.page,
    per_page: params.per_page,
  });
  const remove = useDeleteBranch();

  const columns: ColumnsType<Branch> = [
    { key: "name", dataIndex: "name", title: t("branches.fields.name"), sorter: true },
    {
      key: "code",
      dataIndex: "code",
      title: t("branches.fields.code"),
      sorter: true,
      render: (code: string) => <Tag className="ltr">{code}</Tag>,
    },
    { key: "type", dataIndex: "type", title: t("branches.fields.type"), render: (type: Branch["type"]) => t(`branches.types.${type}`) },
    { key: "phone", dataIndex: "phone", title: t("branches.fields.phone"), render: (phone: string | null) => phone && <span className="ltr">{phone}</span> },
    { key: "users_count", dataIndex: "users_count", title: t("branches.fields.usersCount"), render: (count?: number) => formatNumber(count ?? 0) },
    {
      key: "is_active",
      dataIndex: "is_active",
      title: t("common.status"),
      render: (active: boolean) => (active ? <Tag color="green">{t("common.active")}</Tag> : <Tag>{t("common.inactive")}</Tag>),
    },
  ];

  if (can("branches.update") || can("branches.delete")) {
    columns.push({
      key: "actions",
      title: t("common.actions"),
      width: 110,
      render: (_value, branch) => (
        <Space>
          {can("branches.update") && (
            <Tooltip title={t("common.edit")}>
              <Button type="text" icon={<EditOutlined />} aria-label={t("common.edit")} onClick={() => setEditing(branch)} />
            </Tooltip>
          )}
          {can("branches.delete") && (
            <Popconfirm
              title={t("branches.deleteConfirm", { name: branch.name })}
              okText={t("common.delete")}
              cancelText={t("common.cancel")}
              okButtonProps={{ danger: true }}
              onConfirm={() =>
                remove.mutateAsync(branch.id).then(
                  () => message.success(t("common.deleted")),
                  (error: unknown) => message.error(errorMessage(error, t("common.error"))),
                )
              }
            >
              <Button type="text" danger icon={<DeleteOutlined />} aria-label={t("common.delete")} />
            </Popconfirm>
          )}
        </Space>
      ),
    });
  }

  return (
    <>
      <PageHeader
        title={t("branches.title")}
        extra={
          can("branches.create") && (
            <Button type="primary" icon={<PlusOutlined />} onClick={() => setEditing("new")}>
              {t("branches.new")}
            </Button>
          )
        }
      />
      <Card>
        <Flex gap={8} wrap style={{ marginBottom: 16 }}>
          <Input.Search
            allowClear
            defaultValue={params.q}
            placeholder={t("branches.searchPlaceholder")}
            onSearch={(q) => setFilter({ q: q || null })}
            style={{ width: 260 }}
          />
          <Select
            allowClear
            placeholder={t("branches.fields.type")}
            value={params.type}
            onChange={(type) => setFilter({ type: type ?? null })}
            options={BRANCH_TYPES.map((type) => ({ value: type, label: t(`branches.types.${type}`) }))}
            style={{ width: 180 }}
          />
          <Select
            allowClear
            placeholder={t("common.status")}
            value={params.status}
            onChange={(status) => setFilter({ status: status ?? null })}
            options={ACTIVE.map((status) => ({ value: status, label: t(`common.${status}`) }))}
            style={{ width: 140 }}
          />
        </Flex>
        <DataTable<Branch>
          columns={columns}
          result={branches.data}
          loading={branches.isFetching}
          sort={params.sort}
          onPageChange={(page, perPage) => setParams({ page, per_page: perPage })}
          onSortChange={(sort) => setFilter({ sort })}
        />
      </Card>
      <BranchFormModal
        open={editing !== null}
        branch={editing === "new" || editing === null ? undefined : editing}
        onClose={() => setEditing(null)}
      />
    </>
  );
}
