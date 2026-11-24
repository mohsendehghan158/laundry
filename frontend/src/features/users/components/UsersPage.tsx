"use client";

import { DeleteOutlined, EditOutlined, PlusOutlined } from "@ant-design/icons";
import { App, Button, Card, Flex, Input, Popconfirm, Select, Space, Tag, Tooltip, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import { useTranslations } from "next-intl";
import { parseAsInteger, parseAsString, parseAsStringLiteral } from "nuqs";
import { useState } from "react";
import { useBranchOptions } from "@/features/branches/api";
import { useRoles } from "@/features/roles/api";
import { useRoleLabel } from "@/features/roles/useRoleLabel";
import { errorMessage } from "@/shared/api/formErrors";
import type { User } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { useListParams } from "@/shared/hooks/useListParams";
import { formatDateTime } from "@/shared/lib/date";
import { DataTable } from "@/shared/ui/DataTable";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useDeleteUser, useUsers } from "../api";
import { UserFormModal } from "./UserFormModal";

const ACTIVE = ["active", "inactive"] as const;

export function UsersPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can, me } = usePermission();
  const roleLabel = useRoleLabel();
  const [params, setParams, setFilter] = useListParams({
    role: parseAsString,
    branch: parseAsInteger,
    status: parseAsStringLiteral(ACTIVE),
  });
  const [editing, setEditing] = useState<User | "new" | null>(null);

  const users = useUsers({
    q: params.q,
    role: params.role,
    branch_id: params.branch,
    is_active: params.status === null ? null : params.status === "active",
    sort: params.sort,
    page: params.page,
    per_page: params.per_page,
  });
  const roles = useRoles(can("roles.view"));
  const branches = useBranchOptions(can("branches.view"));
  const remove = useDeleteUser();

  const columns: ColumnsType<User> = [
    {
      key: "name",
      dataIndex: "name",
      title: t("users.fields.name"),
      sorter: true,
      render: (name: string, user) => (
        <Space orientation="vertical" size={0}>
          <span>{name}</span>
          {user.mobile && (
            <Typography.Text type="secondary" className="ltr" style={{ fontSize: 12 }}>
              {user.mobile}
            </Typography.Text>
          )}
        </Space>
      ),
    },
    { key: "username", dataIndex: "username", title: t("users.fields.username"), sorter: true, render: (v: string) => <span className="ltr">{v}</span> },
    {
      key: "roles",
      dataIndex: "roles",
      title: t("users.fields.roles"),
      render: (names: string[]) => names.map((name) => <Tag key={name}>{roleLabel(name)}</Tag>),
    },
    {
      key: "branches",
      dataIndex: "branches",
      title: t("users.fields.branches"),
      render: (list: User["branches"]) =>
        list.length === 0 ? <Typography.Text type="secondary">—</Typography.Text> : list.map((branch) => <Tag key={branch.id}>{branch.name}</Tag>),
    },
    {
      key: "last_login_at",
      dataIndex: "last_login_at",
      title: t("users.fields.lastLogin"),
      sorter: true,
      render: (value: string | null) => (value ? formatDateTime(value) : <Typography.Text type="secondary">{t("common.never")}</Typography.Text>),
    },
    {
      key: "is_active",
      dataIndex: "is_active",
      title: t("common.status"),
      render: (active: boolean) => (active ? <Tag color="green">{t("common.active")}</Tag> : <Tag>{t("common.inactive")}</Tag>),
    },
  ];

  if (can("users.update") || can("users.delete")) {
    columns.push({
      key: "actions",
      title: t("common.actions"),
      width: 110,
      render: (_value, user) => (
        <Space>
          {can("users.update") && (
            <Tooltip title={t("common.edit")}>
              <Button type="text" icon={<EditOutlined />} aria-label={t("common.edit")} onClick={() => setEditing(user)} />
            </Tooltip>
          )}
          {can("users.delete") && user.id !== me?.id && (
            <Popconfirm
              title={t("users.deleteConfirm", { name: user.name })}
              okText={t("common.delete")}
              cancelText={t("common.cancel")}
              okButtonProps={{ danger: true }}
              onConfirm={() =>
                remove.mutateAsync(user.id).then(
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
        title={t("users.title")}
        extra={
          can("users.create") && (
            <Button type="primary" icon={<PlusOutlined />} onClick={() => setEditing("new")}>
              {t("users.new")}
            </Button>
          )
        }
      />
      <Card>
        <Flex gap={8} wrap style={{ marginBottom: 16 }}>
          <Input.Search
            allowClear
            defaultValue={params.q}
            placeholder={t("users.searchPlaceholder")}
            onSearch={(q) => setFilter({ q: q || null })}
            style={{ width: 260 }}
          />
          {can("roles.view") && (
            <Select
              allowClear
              placeholder={t("users.fields.roles")}
              value={params.role}
              onChange={(role) => setFilter({ role: role ?? null })}
              options={roles.data?.map((role) => ({ value: role.name, label: roleLabel(role.name) }))}
              style={{ width: 180 }}
            />
          )}
          {can("branches.view") && (
            <Select
              allowClear
              placeholder={t("users.fields.branches")}
              value={params.branch}
              onChange={(branch) => setFilter({ branch: branch ?? null })}
              options={branches.data?.data.map((branch) => ({ value: branch.id, label: branch.name }))}
              style={{ width: 180 }}
            />
          )}
          <Select
            allowClear
            placeholder={t("common.status")}
            value={params.status}
            onChange={(status) => setFilter({ status: status ?? null })}
            options={ACTIVE.map((status) => ({ value: status, label: t(`common.${status}`) }))}
            style={{ width: 140 }}
          />
        </Flex>
        <DataTable<User>
          columns={columns}
          result={users.data}
          loading={users.isFetching}
          sort={params.sort}
          onPageChange={(page, perPage) => setParams({ page, per_page: perPage })}
          onSortChange={(sort) => setFilter({ sort })}
        />
      </Card>
      <UserFormModal open={editing !== null} user={editing === "new" || editing === null ? undefined : editing} onClose={() => setEditing(null)} />
    </>
  );
}
