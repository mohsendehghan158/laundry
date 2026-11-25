"use client";

import { DeleteOutlined, EditOutlined, PlusOutlined } from "@ant-design/icons";
import { App, Button, Flex, Form, Input, Popconfirm, Select, Switch, Tag } from "antd";
import type { ColumnsType } from "antd/es/table";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { applyServerErrors } from "@/shared/api/form-errors";
import { errorMessage } from "@/shared/api/errors";
import type { Branch, BranchType, StoreBranchBody } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { useTableState } from "@/shared/hooks/useTableState";
import { formatNumber, toLatinDigits } from "@/shared/lib/digits";
import { DataTable } from "@/shared/ui/DataTable";
import { FormDrawer } from "@/shared/ui/FormDrawer";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useBranches, useDeleteBranch, useSaveBranch } from "./api";

const TYPES: BranchType[] = ["store", "plant", "hybrid"];
const TYPE_COLORS: Record<BranchType, string> = { store: "blue", plant: "purple", hybrid: "cyan" };

export function BranchesPage() {
  const t = useTranslations("branches");
  const tc = useTranslations("common");
  const { message } = App.useApp();
  const table = useTableState(["q", "type", "is_active"], "name");
  const branches = useBranches(table.query);
  const save = useSaveBranch();
  const remove = useDeleteBranch();
  const canCreate = usePermission("branches.create");
  const canUpdate = usePermission("branches.update");
  const canDelete = usePermission("branches.delete");

  const [form] = Form.useForm<StoreBranchBody>();
  const [editing, setEditing] = useState<Branch | "new" | null>(null);

  const submit = (values: StoreBranchBody) => {
    const body = { ...values, code: toLatinDigits(values.code).toUpperCase(), phone: values.phone ? toLatinDigits(values.phone) : null };
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

  const columns: ColumnsType<Branch> = [
    { title: t("name"), dataIndex: "name", key: "name", sorter: true },
    { title: t("code"), dataIndex: "code", key: "code", sorter: true, render: (code: string) => <span className="ltr">{code}</span> },
    { title: t("type"), dataIndex: "type", key: "type", render: (type: BranchType) => <Tag color={TYPE_COLORS[type]}>{t(`types.${type}`)}</Tag> },
    { title: t("phone"), dataIndex: "phone", key: "phone", render: (phone: string | null) => (phone ? <span className="ltr">{phone}</span> : "—") },
    { title: t("users"), dataIndex: "users_count", key: "users_count", render: (count?: number) => formatNumber(count ?? 0) },
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
      render: (_, branch) => (
        <Flex gap={4}>
          {canUpdate ? <Button type="text" icon={<EditOutlined />} aria-label={tc("edit")} onClick={() => setEditing(branch)} /> : null}
          {canDelete ? (
            <Popconfirm
              title={tc("confirmDelete", { name: branch.name })}
              okText={tc("delete")}
              cancelText={tc("cancel")}
              okButtonProps={{ danger: true }}
              onConfirm={() =>
                remove.mutateAsync(branch.id).then(
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

  return (
    <>
      <PageHeader
        title={t("title")}
        extra={canCreate ? <Button type="primary" icon={<PlusOutlined />} onClick={() => setEditing("new")}>{t("new")}</Button> : null}
      />
      <DataTable<Branch>
        state={table}
        result={branches.data}
        loading={branches.isFetching}
        columns={columns}
        toolbar={
          <Flex gap={8} wrap>
            <Input.Search
              allowClear
              placeholder={tc("search")}
              defaultValue={table.filters.q ?? ""}
              onSearch={(value) => table.setFilter("q", value || null)}
              style={{ maxWidth: 260 }}
            />
            <Select
              allowClear
              placeholder={t("type")}
              value={table.filters.type ?? undefined}
              onChange={(value) => table.setFilter("type", value ?? null)}
              options={TYPES.map((type) => ({ value: type, label: t(`types.${type}`) }))}
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
      <FormDrawer<StoreBranchBody>
        open={editing !== null}
        title={editing === "new" ? t("new") : t("editTitle")}
        form={form}
        submitting={save.isPending}
        onClose={() => setEditing(null)}
        onSubmit={submit}
        initialValues={
          editing && editing !== "new"
            ? { name: editing.name, code: editing.code, type: editing.type, phone: editing.phone, address: editing.address, is_active: editing.is_active }
            : { type: "store", is_active: true }
        }
      >
        <Form.Item name="name" label={t("name")} rules={[{ required: true, max: 100 }]}>
          <Input />
        </Form.Item>
        <Form.Item name="code" label={t("code")} extra={t("codeHelp")} rules={[{ required: true, max: 10 }]}>
          <Input dir="ltr" style={{ textTransform: "uppercase" }} />
        </Form.Item>
        <Form.Item name="type" label={t("type")} rules={[{ required: true }]}>
          <Select options={TYPES.map((type) => ({ value: type, label: t(`types.${type}`) }))} />
        </Form.Item>
        <Form.Item name="phone" label={t("phone")}>
          <Input dir="ltr" inputMode="tel" />
        </Form.Item>
        <Form.Item name="address" label={t("address")} rules={[{ max: 500 }]}>
          <Input.TextArea rows={3} />
        </Form.Item>
        <Form.Item name="is_active" label={tc("active")} valuePropName="checked">
          <Switch />
        </Form.Item>
      </FormDrawer>
    </>
  );
}
