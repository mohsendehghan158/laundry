"use client";

import { DeleteOutlined, EditOutlined, PlusOutlined } from "@ant-design/icons";
import { App, Button, Flex, Form, Input, Popconfirm, Select, Space, Switch, Tag } from "antd";
import type { ColumnsType } from "antd/es/table";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { applyServerErrors } from "@/shared/api/form-errors";
import { errorMessage } from "@/shared/api/errors";
import type { StoreUserBody, User } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { useTableState } from "@/shared/hooks/useTableState";
import { formatDateTime } from "@/shared/lib/date";
import { toLatinDigits } from "@/shared/lib/digits";
import { DataTable } from "@/shared/ui/DataTable";
import { FormDrawer } from "@/shared/ui/FormDrawer";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useMe } from "@/features/auth/api";
import { useBranchOptions } from "@/features/branches/api";
import { useRoleLabel } from "@/features/roles/labels";
import { useRoles } from "@/features/roles/api";
import { useDeleteUser, useSaveUser, useUsers } from "./api";

type UserForm = StoreUserBody & { password?: string };

export function UsersPage() {
  const t = useTranslations("users");
  const tc = useTranslations("common");
  const roleLabel = useRoleLabel();
  const { message } = App.useApp();
  const table = useTableState(["q", "role", "branch_id", "is_active"], "name");
  const users = useUsers(table.query);
  const me = useMe();
  const canCreate = usePermission("users.create");
  const canUpdate = usePermission("users.update");
  const canDelete = usePermission("users.delete");
  const canViewRoles = usePermission("roles.view");
  const canViewBranches = usePermission("branches.view");
  const roles = useRoles(canViewRoles);
  const branches = useBranchOptions(canViewBranches);
  const save = useSaveUser();
  const remove = useDeleteUser();

  const [form] = Form.useForm<UserForm>();
  const [editing, setEditing] = useState<User | "new" | null>(null);

  const isOwner = me.data?.roles.includes("owner") ?? false;
  // Only an owner can hand out the owner role (the API enforces the same).
  const roleOptions = (roles.data ?? [])
    .filter((role) => isOwner || role.name !== "owner")
    .map((role) => ({ value: role.name, label: roleLabel(role.name) }));
  // Branch choices fall back to the user's own branches when the full list is not visible.
  const branchOptions = (branches.data?.data ?? me.data?.branches ?? []).map((branch) => ({ value: branch.id, label: branch.name }));

  const submit = (values: UserForm) => {
    const body = {
      ...values,
      username: toLatinDigits(values.username).trim().toLowerCase(),
      mobile: values.mobile ? toLatinDigits(values.mobile).trim() : null,
      email: values.email?.trim() || null,
      // An empty password on edit keeps the current one.
      password: values.password || null,
      branch_ids: values.branch_ids ?? [],
    } as StoreUserBody;
    save.mutate(
      { id: editing === "new" ? undefined : editing?.id, body },
      {
        onSuccess: () => {
          message.success(editing === "new" ? tc("created") : tc("saved"));
          setEditing(null);
        },
        onError: (error) => {
          if (!applyServerErrors(form, error)) message.error(errorMessage(error));
        },
      },
    );
  };

  const columns: ColumnsType<User> = [
    { title: t("name"), dataIndex: "name", key: "name", sorter: true },
    { title: t("username"), dataIndex: "username", key: "username", sorter: true, render: (v: string) => <span className="ltr">{v}</span> },
    { title: t("mobile"), dataIndex: "mobile", key: "mobile", render: (v: string | null) => (v ? <span className="ltr">{v}</span> : "—") },
    {
      title: t("roles"),
      dataIndex: "roles",
      key: "roles",
      render: (names: string[]) => (
        <Space size={4} wrap>
          {names.map((name) => (
            <Tag key={name} color={name === "owner" ? "gold" : undefined}>{roleLabel(name)}</Tag>
          ))}
        </Space>
      ),
    },
    {
      title: t("branches"),
      dataIndex: "branches",
      key: "branches",
      render: (list: User["branches"]) => (list.length ? list.map((b) => b.name).join("، ") : "—"),
    },
    { title: t("lastLogin"), dataIndex: "last_login_at", key: "last_login_at", sorter: true, render: (v: string | null) => formatDateTime(v) },
    {
      title: tc("status"),
      dataIndex: "is_active",
      key: "is_active",
      render: (active: boolean) => <Tag color={active ? "green" : "default"}>{active ? tc("active") : tc("inactive")}</Tag>,
    },
    {
      title: tc("actions"),
      key: "actions",
      width: 110,
      render: (_, user) => (
        <Flex gap={4}>
          {canUpdate ? <Button type="text" icon={<EditOutlined />} aria-label={tc("edit")} onClick={() => setEditing(user)} /> : null}
          {canDelete && user.id !== me.data?.id ? (
            <Popconfirm
              title={tc("confirmDelete", { name: user.name })}
              okText={tc("delete")}
              cancelText={tc("cancel")}
              okButtonProps={{ danger: true }}
              onConfirm={() =>
                remove.mutateAsync(user.id).then(
                  () => message.success(tc("deleted")),
                  (error) => message.error(errorMessage(error)),
                )
              }
            >
              <Button type="text" danger icon={<DeleteOutlined />} aria-label={tc("delete")} />
            </Popconfirm>
          ) : null}
        </Flex>
      ),
    },
  ];

  const isNew = editing === "new";

  return (
    <>
      <PageHeader
        title={t("title")}
        extra={canCreate ? <Button type="primary" icon={<PlusOutlined />} onClick={() => setEditing("new")}>{t("new")}</Button> : null}
      />
      <DataTable<User>
        state={table}
        result={users.data}
        loading={users.isFetching}
        columns={columns}
        toolbar={
          <Flex gap={8} wrap>
            <Input.Search
              allowClear
              placeholder={t("searchPlaceholder")}
              defaultValue={table.filters.q ?? ""}
              onSearch={(value) => table.setFilter("q", value ? toLatinDigits(value) : null)}
              style={{ maxWidth: 260 }}
            />
            {canViewRoles ? (
              <Select
                allowClear
                placeholder={t("roles")}
                value={table.filters.role ?? undefined}
                onChange={(value) => table.setFilter("role", value ?? null)}
                options={(roles.data ?? []).map((role) => ({ value: role.name, label: roleLabel(role.name) }))}
                style={{ minWidth: 160 }}
              />
            ) : null}
            <Select
              allowClear
              placeholder={t("branches")}
              value={table.filters.branch_id ? Number(table.filters.branch_id) : undefined}
              onChange={(value) => table.setFilter("branch_id", value ? String(value) : null)}
              options={branchOptions}
              style={{ minWidth: 160 }}
            />
            <Select
              allowClear
              placeholder={tc("status")}
              value={table.filters.is_active ?? undefined}
              onChange={(value) => table.setFilter("is_active", value ?? null)}
              options={[{ value: "1", label: tc("active") }, { value: "0", label: tc("inactive") }]}
              style={{ minWidth: 140 }}
            />
          </Flex>
        }
      />
      <FormDrawer<UserForm>
        open={editing !== null}
        title={isNew ? t("new") : t("editTitle")}
        form={form}
        submitting={save.isPending}
        onClose={() => setEditing(null)}
        onSubmit={submit}
        initialValues={
          editing && editing !== "new"
            ? {
                name: editing.name,
                username: editing.username,
                email: editing.email,
                mobile: editing.mobile,
                roles: editing.roles,
                branch_ids: editing.branches.map((b) => b.id),
                is_active: editing.is_active,
                locale: editing.locale as "fa" | "en",
              }
            : { is_active: true, locale: "fa", roles: [], branch_ids: [] }
        }
      >
        <Form.Item name="name" label={t("name")} rules={[{ required: true, max: 100 }]}>
          <Input />
        </Form.Item>
        <Form.Item name="username" label={t("username")} extra={t("usernameHelp")} rules={[{ required: true }]}>
          <Input dir="ltr" autoComplete="off" />
        </Form.Item>
        <Form.Item
          name="password"
          label={t("password")}
          extra={isNew ? undefined : t("passwordKeep")}
          rules={[{ required: isNew }, { min: 8 }]}
        >
          <Input.Password dir="ltr" autoComplete="new-password" />
        </Form.Item>
        <Form.Item name="mobile" label={t("mobile")}>
          <Input dir="ltr" inputMode="tel" placeholder="09121234567" />
        </Form.Item>
        <Form.Item name="email" label={t("email")} rules={[{ type: "email" }]}>
          <Input dir="ltr" />
        </Form.Item>
        <Form.Item name="roles" label={t("roles")} rules={[{ required: true, type: "array", min: 1 }]}>
          <Select mode="multiple" options={roleOptions} loading={roles.isLoading} />
        </Form.Item>
        <Form.Item name="branch_ids" label={t("branches")} extra={t("branchesHelp")}>
          <Select mode="multiple" options={branchOptions} loading={branches.isLoading} optionFilterProp="label" />
        </Form.Item>
        <Form.Item name="locale" label={t("language")}>
          <Select options={[{ value: "fa", label: "فارسی" }, { value: "en", label: "English" }]} />
        </Form.Item>
        <Form.Item name="is_active" label={tc("active")} valuePropName="checked">
          <Switch />
        </Form.Item>
      </FormDrawer>
    </>
  );
}
