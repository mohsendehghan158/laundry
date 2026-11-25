"use client";

import { DeleteOutlined, EditOutlined, PlusOutlined } from "@ant-design/icons";
import { App, Button, Card, Col, Form, Input, InputNumber, Modal, Popconfirm, Radio, Row, Select, Space, Switch, Table, Tag, Tooltip, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import { useTranslations } from "next-intl";
import { useEffect, useMemo, useState } from "react";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { Station } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatNumber, inputNumberFormatter, toLatinDigits } from "@/shared/lib/digits";
import { parseAmount } from "@/shared/lib/money";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useDeleteStation, useSaveStation, useStations, type StationInput } from "../api";

type Values = StationInput;

/** The workshop steps of each branch, in the order pieces go through them. */
export function StationsPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { me } = usePermission();
  const [form] = Form.useForm<Values>();
  const [editing, setEditing] = useState<Station | "new" | null>(null);
  const [branchId, setBranchId] = useState<number | null>(null);
  const stations = useStations();
  const save = useSaveStation();
  const remove = useDeleteStation();
  const branches = useMemo(() => me?.branches ?? [], [me]);
  const branchName = useMemo(() => new Map(branches.map((b) => [b.id, b.name])), [branches]);

  useEffect(() => {
    if (editing === null) {
      return;
    }
    form.resetFields();
    form.setFieldsValue(
      editing === "new"
        ? { branch_id: branchId ?? branches[0]?.id, kind: "process", sort_order: (stations.data?.length ?? 0) + 1, is_active: true }
        : { ...editing },
    );
  }, [editing, form, branchId, branches, stations.data?.length]);

  const onFinish = (values: Values) => {
    const input = { ...values, code: toLatinDigits(values.code).toUpperCase() };
    if (editing !== "new") {
      delete input.branch_id;
    }
    save.mutate(
      { id: editing === "new" || editing === null ? undefined : editing.id, input },
      {
        onSuccess: () => {
          message.success(editing === "new" ? t("common.created") : t("common.saved"));
          setEditing(null);
        },
        onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error"))),
      },
    );
  };

  const rows = (stations.data ?? []).filter((station) => branchId === null || station.branch_id === branchId);
  const columns: ColumnsType<Station> = [
    ...(branches.length > 1 ? [{ key: "branch", title: t("stations.fields.branch"), render: (_v: unknown, s: Station) => branchName.get(s.branch_id) ?? "—" }] : []),
    { key: "order", dataIndex: "sort_order", title: t("stations.fields.order"), width: 80, render: (v: number) => formatNumber(v) },
    {
      key: "name",
      dataIndex: "name",
      title: t("stations.fields.name"),
      render: (name: string, station) => <Typography.Text type={station.is_active ? undefined : "secondary"}>{name}</Typography.Text>,
    },
    { key: "code", dataIndex: "code", title: t("stations.fields.code"), render: (code: string) => <Tag className="ltr">{code}</Tag> },
    {
      key: "kind",
      dataIndex: "kind",
      title: t("stations.fields.kind"),
      render: (kind: Station["kind"]) => <Tag color={kind === "qc" ? "purple" : "blue"}>{t(`stations.kinds.${kind}`)}</Tag>,
    },
    {
      key: "active",
      dataIndex: "is_active",
      title: t("common.status"),
      render: (active: boolean) => (active ? <Tag color="green">{t("common.active")}</Tag> : <Tag>{t("common.inactive")}</Tag>),
    },
    {
      key: "actions",
      title: t("common.actions"),
      width: 110,
      render: (_v, station) => (
        <Space>
          <Tooltip title={t("common.edit")}>
            <Button type="text" icon={<EditOutlined />} aria-label={`${t("common.edit")} ${station.name}`} onClick={() => setEditing(station)} />
          </Tooltip>
          <Popconfirm
            title={t("stations.deleteConfirm", { name: station.name })}
            okText={t("common.delete")}
            cancelText={t("common.cancel")}
            okButtonProps={{ danger: true }}
            onConfirm={() =>
              remove.mutateAsync(station.id).then(
                () => message.success(t("common.deleted")),
                (error: unknown) => message.error(errorMessage(error, t("common.error"))),
              )
            }
          >
            <Button type="text" danger icon={<DeleteOutlined />} aria-label={`${t("common.delete")} ${station.name}`} />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <>
      <PageHeader
        title={t("stations.title")}
        subtitle={t("stations.subtitle")}
        extra={
          <>
            {branches.length > 1 && (
              <Select
                allowClear
                aria-label={t("stations.fields.branch")}
                placeholder={t("reports.allBranches")}
                value={branchId}
                onChange={(value?: number) => setBranchId(value ?? null)}
                options={branches.map((b) => ({ value: b.id, label: b.name }))}
                style={{ width: 180 }}
              />
            )}
            <Button type="primary" icon={<PlusOutlined />} onClick={() => setEditing("new")}>
              {t("stations.new")}
            </Button>
          </>
        }
      />
      <Card styles={{ body: { padding: 0 } }}>
        <Table<Station>
          rowKey="id"
          columns={columns}
          dataSource={rows}
          loading={stations.isLoading}
          pagination={false}
          scroll={{ x: "max-content" }}
          locale={{ emptyText: t("stations.empty") }}
        />
      </Card>
      <Modal
        open={editing !== null}
        title={editing === "new" ? t("stations.new") : t("stations.edit")}
        okText={t("common.save")}
        cancelText={t("common.cancel")}
        onCancel={() => setEditing(null)}
        onOk={() => form.submit()}
        confirmLoading={save.isPending}
        forceRender
      >
        <Form<Values> form={form} layout="vertical" onFinish={onFinish} name="station">
          <Row gutter={16}>
            {editing === "new" && (
              <Col xs={24}>
                <Form.Item name="branch_id" label={t("stations.fields.branch")} rules={[{ required: true, message: t("common.required") }]}>
                  <Select options={branches.map((b) => ({ value: b.id, label: b.name }))} />
                </Form.Item>
              </Col>
            )}
            <Col xs={24} sm={14}>
              <Form.Item name="name" label={t("stations.fields.name")} rules={[{ required: true, message: t("common.required") }]}>
                <Input maxLength={100} />
              </Form.Item>
            </Col>
            <Col xs={24} sm={10}>
              <Form.Item
                name="code"
                label={t("stations.fields.code")}
                normalize={(v: string) => toLatinDigits(v ?? "").toUpperCase()}
                rules={[{ required: true, message: t("common.required") }, { pattern: /^[A-Z0-9-]{1,10}$/, message: t("catalog.codeInvalid") }]}
              >
                <Input dir="ltr" maxLength={10} />
              </Form.Item>
            </Col>
            <Col xs={24}>
              <Form.Item name="kind" label={t("stations.fields.kind")} extra={t("stations.kindHelp")}>
                <Radio.Group optionType="button" options={(["process", "qc"] as const).map((kind) => ({ value: kind, label: t(`stations.kinds.${kind}`) }))} />
              </Form.Item>
            </Col>
            <Col xs={12}>
              <Form.Item name="sort_order" label={t("stations.fields.order")}>
                <InputNumber<number> min={0} formatter={inputNumberFormatter} parser={(text) => parseAmount(text) ?? 0} style={{ width: "100%" }} />
              </Form.Item>
            </Col>
            <Col xs={12}>
              <Form.Item name="is_active" label={t("common.active")} valuePropName="checked">
                <Switch />
              </Form.Item>
            </Col>
          </Row>
        </Form>
      </Modal>
    </>
  );
}
