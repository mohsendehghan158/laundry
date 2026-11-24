"use client";

import { DeleteOutlined, EditOutlined, PlusOutlined } from "@ant-design/icons";
import { App, Button, Flex, Form, InputNumber, Modal, Popconfirm, Space, Switch, Table, Tooltip } from "antd";
import type { ColumnsType } from "antd/es/table";
import { useTranslations } from "next-intl";
import { useEffect, useState, type ReactNode } from "react";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import { formatNumber } from "@/shared/lib/digits";
import { useCatalogList, useDeleteCatalogEntry, useSaveCatalogEntry, type CatalogResource, type CatalogResources } from "../api";

type Values = Record<string, unknown>;

type Props<R extends CatalogResource> = {
  resource: R;
  /** Columns describing the record; order, active and actions are added. */
  columns: ColumnsType<CatalogResources[R]>;
  /** Form items for the record's own fields. */
  fields: ReactNode;
  /** Initial values for a new record. */
  defaults?: Values;
  /** Turns a record into form values (defaults to the record itself). */
  toValues?: (entry: CatalogResources[R]) => Values;
  /** Turns form values into the request body (defaults to the values). */
  toInput?: (values: Values) => Values;
  /** Narrows the visible rows, e.g. items of one category. */
  filter?: (entry: CatalogResources[R]) => boolean;
  toolbar?: ReactNode;
  editable: boolean;
  /** Why a record cannot be added yet (e.g. no category exists); disables the add button. */
  addBlockedReason?: string | null;
};

/**
 * List, create, edit, reorder, enable/disable and delete one kind of
 * catalog record. Each tab of the catalog page is a configuration of this.
 */
export function CatalogCrudTab<R extends CatalogResource>({
  resource,
  columns,
  fields,
  defaults = {},
  toValues = (entry) => ({ ...entry }),
  toInput = (values) => values,
  filter,
  toolbar,
  editable,
  addBlockedReason = null,
}: Props<R>) {
  const t = useTranslations();
  const { message } = App.useApp();
  const [form] = Form.useForm<Values>();
  const [editing, setEditing] = useState<CatalogResources[R] | "new" | null>(null);
  const list = useCatalogList(resource);
  const save = useSaveCatalogEntry(resource);
  const remove = useDeleteCatalogEntry(resource);

  useEffect(() => {
    if (editing === null) {
      return;
    }
    form.resetFields();
    form.setFieldsValue(editing === "new" ? { sort_order: nextSortOrder(list.data), is_active: true, ...defaults } : toValues(editing));
    // Only when the dialog opens; defaults and toValues are recreated each render.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editing, form]);

  const submit = (values: Values) =>
    save.mutate(
      { id: editing === "new" || editing === null ? undefined : editing.id, input: toInput(values) },
      {
        onSuccess: () => {
          message.success(editing === "new" ? t("common.created") : t("common.saved"));
          setEditing(null);
        },
        onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error"))),
      },
    );

  const toggleActive = (entry: CatalogResources[R], isActive: boolean) =>
    save.mutate(
      { id: entry.id, input: { is_active: isActive } },
      { onError: (error) => message.error(errorMessage(error, t("common.error"))) },
    );

  const allColumns: ColumnsType<CatalogResources[R]> = [
    ...columns,
    {
      key: "sort_order",
      dataIndex: "sort_order",
      title: t("catalog.fields.sortOrder"),
      width: 90,
      render: (value: number) => formatNumber(value),
    },
    {
      key: "is_active",
      dataIndex: "is_active",
      title: t("catalog.fields.isActive"),
      width: 90,
      render: (value: boolean, entry) => (
        <Switch size="small" checked={value} disabled={!editable} onChange={(checked) => toggleActive(entry, checked)} aria-label={t("catalog.fields.isActive")} />
      ),
    },
  ];

  if (editable) {
    allColumns.push({
      key: "actions",
      title: t("common.actions"),
      width: 110,
      render: (_value, entry) => (
        <Space>
          <Tooltip title={t("common.edit")}>
            <Button type="text" icon={<EditOutlined />} aria-label={t("common.edit")} onClick={() => setEditing(entry)} />
          </Tooltip>
          <Popconfirm
            title={t("catalog.deleteConfirm", { name: entry.name })}
            okText={t("common.delete")}
            cancelText={t("common.cancel")}
            okButtonProps={{ danger: true }}
            onConfirm={() =>
              remove.mutateAsync(entry.id).then(
                () => message.success(t("common.deleted")),
                (error: unknown) => message.error(errorMessage(error, t("common.error"))),
              )
            }
          >
            <Button type="text" danger icon={<DeleteOutlined />} aria-label={t("common.delete")} />
          </Popconfirm>
        </Space>
      ),
    });
  }

  const rows = filter ? list.data?.filter(filter) : list.data;
  const addLabel = t(`catalog.add.${resource as CatalogResource}`);

  return (
    <>
      <Flex justify="space-between" align="center" gap={8} wrap style={{ marginBottom: 16 }}>
        <div>{toolbar}</div>
        {editable && (
          <Tooltip title={addBlockedReason}>
            <Button type="primary" icon={<PlusOutlined />} disabled={addBlockedReason !== null} onClick={() => setEditing("new")}>
              {addLabel}
            </Button>
          </Tooltip>
        )}
      </Flex>
      <Table<CatalogResources[R]>
        rowKey="id"
        size="middle"
        scroll={{ x: "max-content" }}
        loading={list.isLoading}
        columns={allColumns}
        dataSource={rows ?? []}
        pagination={false}
        rowClassName={(entry) => (entry.is_active ? "" : "catalog-row-inactive")}
      />
      <Modal
        open={editing !== null}
        title={editing === "new" ? addLabel : t("catalog.edit")}
        okText={t("common.save")}
        cancelText={t("common.cancel")}
        onCancel={() => setEditing(null)}
        onOk={() => form.submit()}
        confirmLoading={save.isPending}
        forceRender
      >
        <Form<Values> form={form} layout="vertical" onFinish={submit} name={`catalog-${resource}`}>
          {fields}
          <Flex gap={24}>
            <Form.Item name="sort_order" label={t("catalog.fields.sortOrder")}>
              <InputNumber<number> min={0} max={100000} />
            </Form.Item>
            <Form.Item name="is_active" label={t("catalog.fields.isActive")} valuePropName="checked">
              <Switch />
            </Form.Item>
          </Flex>
        </Form>
      </Modal>
    </>
  );
}

function nextSortOrder(rows: { sort_order: number }[] | undefined): number {
  return rows && rows.length > 0 ? Math.max(...rows.map((row) => row.sort_order)) + 1 : 0;
}
