"use client";

import { DeleteOutlined, EditOutlined, PlusOutlined, SendOutlined } from "@ant-design/icons";
import { App, Button, Card, Col, Flex, Form, Input, Modal, Popconfirm, Row, Select, Space, Switch, Table, Tabs, Tag, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { Shipment, TagResult, Vendor } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { useFeedbackSound } from "@/shared/hooks/useFeedbackSound";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatDate, formatDateTime, type Dayjs } from "@/shared/lib/date";
import { formatNumber } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";
import { JalaliDatePicker } from "@/shared/ui/JalaliDatePicker";
import { MoneyInput } from "@/shared/ui/MoneyInput";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useCreateShipment, useDeleteVendor, useReceiveShipment, useSaveVendor, useShipments, useVendors, type VendorInput } from "../movementApi";
import { ScanField } from "./ScanField";
import { TagResults } from "./TagResults";

function ShipmentPieces({ shipment }: { shipment: Shipment }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const beep = useFeedbackSound();
  const receive = useReceiveShipment();
  const [results, setResults] = useState<TagResult[]>([]);

  return (
    <Flex vertical gap={12}>
      {shipment.status === "sent" && (
        <>
          <ScanField
            label={t("outsourcing.scanBack")}
            placeholder={t("outsourcing.scanBack")}
            disabled={receive.isPending}
            onTags={(tags) =>
              receive.mutate(
                { id: shipment.id, tags },
                {
                  onSuccess: ({ results }) => {
                    setResults(results);
                    beep(results.every((r) => r.ok) ? "ok" : "error");
                  },
                  onError: (error) => message.error(errorMessage(error, t("common.error"))),
                },
              )
            }
          />
          <TagResults results={results} />
        </>
      )}
      <Flex wrap gap={6}>
        {shipment.pieces.map((piece) => (
          <Tag key={piece.id} color={piece.returned ? "green" : "purple"} className="ltr">
            {piece.tag_code} · {piece.item_name}
          </Tag>
        ))}
      </Flex>
      {shipment.note && <Typography.Text type="secondary">{shipment.note}</Typography.Text>}
    </Flex>
  );
}

function SendDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const { me } = usePermission();
  const unit = useCurrencyUnit();
  const vendors = useVendors(open);
  const create = useCreateShipment();
  const [form] = Form.useForm<{ vendor_id: number; branch_id: number; expected_at?: Dayjs | null; cost?: number | null; note?: string }>();
  const [tags, setTags] = useState<string[]>([]);

  return (
    <Modal
      open={open}
      title={t("outsourcing.send")}
      okText={t("outsourcing.sendConfirm", { count: tags.length })}
      okButtonProps={{ disabled: tags.length === 0 }}
      cancelText={t("common.cancel")}
      onCancel={onClose}
      onOk={() => form.submit()}
      confirmLoading={create.isPending}
      width={620}
      destroyOnHidden
      afterClose={() => setTags([])}
    >
      <Form
        form={form}
        layout="vertical"
        preserve={false}
        initialValues={{ branch_id: me?.branches[0]?.id }}
        onFinish={(values) =>
          create.mutate(
            { ...values, tags, expected_at: values.expected_at ? values.expected_at.endOf("day").toISOString() : null },
            {
              onSuccess: (shipment) => {
                message.success(t("outsourcing.sent", { number: shipment.number }));
                onClose();
              },
              onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error")), 8),
            },
          )
        }
      >
        <Row gutter={12}>
          <Col xs={24} sm={12}>
            <Form.Item name="vendor_id" label={t("outsourcing.fields.vendor")} rules={[{ required: true, message: t("common.required") }]}>
              <Select loading={vendors.isLoading} options={(vendors.data ?? []).filter((v) => v.is_active).map((v) => ({ value: v.id, label: v.name }))} />
            </Form.Item>
          </Col>
          <Col xs={24} sm={12}>
            <Form.Item name="branch_id" label={t("outsourcing.fields.branch")} rules={[{ required: true, message: t("common.required") }]}>
              <Select options={(me?.branches ?? []).map((b) => ({ value: b.id, label: b.name }))} />
            </Form.Item>
          </Col>
        </Row>
        <Form.Item label={t("outsourcing.fields.pieces")} required>
          <ScanField label={t("station.scanLabel")} placeholder={t("station.scanPlaceholder")} onTags={(scanned) => setTags((current) => [...new Set([...current, ...scanned])])} />
          <Flex wrap gap={6} style={{ marginTop: 8 }}>
            {tags.map((tag) => (
              <Tag key={tag} closable onClose={() => setTags((current) => current.filter((x) => x !== tag))} className="ltr">
                {tag}
              </Tag>
            ))}
          </Flex>
        </Form.Item>
        <Row gutter={12}>
          <Col xs={24} sm={12}>
            <Form.Item name="expected_at" label={t("outsourcing.fields.expected")}>
              <JalaliDatePicker format="YYYY/MM/DD" style={{ width: "100%" }} />
            </Form.Item>
          </Col>
          <Col xs={24} sm={12}>
            <Form.Item name="cost" label={t("outsourcing.fields.cost")}>
              <MoneyInput unit={unit} />
            </Form.Item>
          </Col>
        </Row>
        <Form.Item name="note" label={t("transfers.fields.note")}>
          <Input maxLength={500} />
        </Form.Item>
      </Form>
    </Modal>
  );
}

function VendorsTab() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can } = usePermission();
  const vendors = useVendors();
  const save = useSaveVendor();
  const remove = useDeleteVendor();
  const [editing, setEditing] = useState<Vendor | "new" | null>(null);
  const [form] = Form.useForm<VendorInput>();
  const manage = can("production.manage");

  useEffect(() => {
    if (editing === null) return;
    form.resetFields();
    form.setFieldsValue(editing === "new" ? { is_active: true } : editing);
  }, [editing, form]);

  return (
    <>
      {manage && (
        <Flex justify="end" style={{ marginBottom: 12 }}>
          <Button icon={<PlusOutlined />} onClick={() => setEditing("new")}>
            {t("outsourcing.newVendor")}
          </Button>
        </Flex>
      )}
      <Table<Vendor>
        rowKey="id"
        dataSource={vendors.data ?? []}
        loading={vendors.isLoading}
        pagination={false}
        columns={[
          { key: "name", dataIndex: "name", title: t("outsourcing.fields.vendor") },
          { key: "phone", dataIndex: "phone", title: t("outsourcing.fields.phone"), render: (v: string | null) => (v ? <span className="ltr">{v}</span> : "—") },
          { key: "services", dataIndex: "services", title: t("outsourcing.fields.services"), render: (v: string | null) => v ?? "—" },
          { key: "active", dataIndex: "is_active", title: t("common.status"), render: (v: boolean) => (v ? <Tag color="green">{t("common.active")}</Tag> : <Tag>{t("common.inactive")}</Tag>) },
          ...(manage
            ? [{
                key: "actions",
                title: t("common.actions"),
                width: 110,
                render: (_v: unknown, vendor: Vendor) => (
                  <Space>
                    <Button type="text" icon={<EditOutlined />} aria-label={`${t("common.edit")} ${vendor.name}`} onClick={() => setEditing(vendor)} />
                    <Popconfirm
                      title={t("outsourcing.deleteVendor", { name: vendor.name })}
                      okText={t("common.delete")}
                      cancelText={t("common.cancel")}
                      okButtonProps={{ danger: true }}
                      onConfirm={() => remove.mutateAsync(vendor.id).then(() => message.success(t("common.deleted")))}
                    >
                      <Button type="text" danger icon={<DeleteOutlined />} aria-label={`${t("common.delete")} ${vendor.name}`} />
                    </Popconfirm>
                  </Space>
                ),
              }]
            : []),
        ]}
      />
      <Modal
        open={editing !== null}
        title={editing === "new" ? t("outsourcing.newVendor") : t("outsourcing.editVendor")}
        okText={t("common.save")}
        cancelText={t("common.cancel")}
        onCancel={() => setEditing(null)}
        onOk={() => form.submit()}
        confirmLoading={save.isPending}
        forceRender
      >
        <Form<VendorInput>
          form={form}
          layout="vertical"
          name="vendor"
          onFinish={(values) =>
            save.mutate(
              { id: editing === "new" || editing === null ? undefined : editing.id, input: values },
              {
                onSuccess: () => {
                  message.success(t("common.saved"));
                  setEditing(null);
                },
                onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error"))),
              },
            )
          }
        >
          <Form.Item name="name" label={t("outsourcing.fields.vendor")} rules={[{ required: true, message: t("common.required") }]}>
            <Input maxLength={100} />
          </Form.Item>
          <Form.Item name="phone" label={t("outsourcing.fields.phone")}>
            <Input dir="ltr" maxLength={30} />
          </Form.Item>
          <Form.Item name="services" label={t("outsourcing.fields.services")}>
            <Input maxLength={255} />
          </Form.Item>
          <Form.Item name="is_active" label={t("common.active")} valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>
    </>
  );
}

function ShipmentsTab({ status }: { status: "sent" | "closed" }) {
  const t = useTranslations();
  const unit = useCurrencyUnit();
  const shipments = useShipments(status);
  const columns: ColumnsType<Shipment> = [
    { key: "number", dataIndex: "number", title: t("transfers.fields.number"), render: (v: string) => <span className="ltr">{v}</span> },
    { key: "vendor", title: t("outsourcing.fields.vendor"), render: (_v, s) => s.vendor.name },
    { key: "branch", title: t("outsourcing.fields.branch"), render: (_v, s) => s.branch.name },
    { key: "pieces", title: t("outsourcing.fields.back"), render: (_v, s) => `${formatNumber(s.returned_count)} / ${formatNumber(s.piece_count)}` },
    {
      key: "expected",
      title: t("outsourcing.fields.expected"),
      render: (_v, s) => (s.expected_at ? <Tag color={s.is_late ? "red" : undefined}>{formatDate(s.expected_at)}</Tag> : "—"),
    },
    { key: "cost", title: t("outsourcing.fields.cost"), render: (_v, s) => (s.cost === null ? "—" : `${formatMoney(s.cost, unit)} ${t(`money.${unit}`)}`) },
    { key: "sent", title: t("transfers.fields.sentAt"), render: (_v, s) => formatDateTime(s.sent_at) },
  ];

  return (
    <Table<Shipment>
      rowKey="id"
      columns={columns}
      dataSource={shipments.data ?? []}
      loading={shipments.isLoading}
      pagination={false}
      scroll={{ x: "max-content" }}
      expandable={{ expandedRowRender: (shipment) => <ShipmentPieces shipment={shipment} />, expandRowByClick: true }}
      locale={{ emptyText: t("outsourcing.empty") }}
    />
  );
}

/** Work at outside vendors (rugs, leather, tailoring) until every piece is back. */
export function OutsourcingPage() {
  const t = useTranslations();
  const [sending, setSending] = useState(false);

  return (
    <>
      <PageHeader
        title={t("outsourcing.title")}
        subtitle={t("outsourcing.subtitle")}
        extra={
          <Button type="primary" icon={<SendOutlined />} onClick={() => setSending(true)}>
            {t("outsourcing.send")}
          </Button>
        }
      />
      <Card>
        <Tabs
          items={[
            { key: "sent", label: t("outsourcing.tabs.sent"), children: <ShipmentsTab status="sent" /> },
            { key: "closed", label: t("outsourcing.tabs.closed"), children: <ShipmentsTab status="closed" /> },
            { key: "vendors", label: t("outsourcing.tabs.vendors"), children: <VendorsTab /> },
          ]}
        />
      </Card>
      <SendDialog open={sending} onClose={() => setSending(false)} />
    </>
  );
}
