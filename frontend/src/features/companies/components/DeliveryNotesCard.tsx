"use client";

import { CheckOutlined, DeleteOutlined, ExportOutlined, ImportOutlined, MinusCircleOutlined, PlusOutlined, PrinterOutlined } from "@ant-design/icons";
import { App, Button, Card, Col, Flex, Form, Input, InputNumber, Modal, Popconfirm, Row, Select, Space, Table, Tag, Tooltip, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useEffect, useMemo, useState } from "react";
import { useCounterCatalog } from "@/features/catalog/api";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { Company, DeliveryNote } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatDateTime } from "@/shared/lib/date";
import { formatNumber, inputNumberFormatter, toPersianDigits } from "@/shared/lib/digits";
import { parseAmount } from "@/shared/lib/money";
import { openDeliveryNotePrint, useConfirmDeliveryNote, useDeleteDeliveryNote, useDeliveryNotes, useOpenCompanyOrders, useSaveDeliveryNote } from "../api";

type InLine = { item_id?: number; service_type_id?: number; expected_quantity?: number; counted_quantity?: number };
type InValues = { branch_id: number; customer_id: number; company_department_id?: number | null; lines: InLine[]; notes?: string };

const quantityInput = { min: 0, max: 100000, formatter: inputNumberFormatter, parser: (text: string | undefined) => parseAmount(text) ?? 0, style: { width: "100%" } };

/** Items a company sent in: what it stated and what was counted here. */
function IncomingModal({ company, open, onClose }: { company: Company; open: boolean; onClose: () => void }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const { me } = usePermission();
  const [form] = Form.useForm<InValues>();
  const catalog = useCounterCatalog(open);
  const save = useSaveDeliveryNote();
  const lines = Form.useWatch("lines", form) ?? [];

  const items = useMemo(() => (catalog.data?.categories ?? []).flatMap((category) => category.items ?? []).filter((item) => item.is_active), [catalog.data]);
  const servicesFor = (itemId?: number) =>
    (catalog.data?.service_types ?? []).filter((service) => service.is_active && (catalog.data?.prices ?? []).some((p) => p.item_id === itemId && p.service_type_id === service.id));

  useEffect(() => {
    if (open) {
      form.resetFields();
      form.setFieldsValue({ branch_id: me?.branches[0]?.id, customer_id: company.members?.[0]?.id, lines: [{}] });
    }
  }, [open, company, me, form]);

  return (
    <Modal
      open={open}
      width={760}
      title={t("deliveryNotes.newIn")}
      okText={t("common.save")}
      cancelText={t("common.cancel")}
      onCancel={onClose}
      onOk={() => form.submit()}
      confirmLoading={save.isPending}
      forceRender
    >
      <Form<InValues>
        form={form}
        layout="vertical"
        name="delivery-in"
        onFinish={(values) =>
          save.mutate(
            { input: { ...values, type: "in", company_id: company.id, lines: values.lines.map((line) => ({ ...line, item_id: line.item_id!, service_type_id: line.service_type_id!, expected_quantity: line.expected_quantity ?? 0, counted_quantity: line.counted_quantity ?? 0 })) } },
            {
              onSuccess: () => {
                message.success(t("common.created"));
                onClose();
              },
              onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error"))),
            },
          )
        }
      >
        <Row gutter={16}>
          <Col xs={24} sm={8}>
            <Form.Item name="customer_id" label={t("deliveryNotes.customer")} rules={[{ required: true, message: t("common.required") }]}>
              <Select options={(company.members ?? []).map((m) => ({ value: m.id, label: m.name }))} notFoundContent={t("deliveryNotes.noMembers")} />
            </Form.Item>
          </Col>
          <Col xs={24} sm={8}>
            <Form.Item name="company_department_id" label={t("companies.fields.department")}>
              <Select allowClear options={(company.departments ?? []).filter((d) => d.is_active).map((d) => ({ value: d.id, label: d.name }))} />
            </Form.Item>
          </Col>
          <Col xs={24} sm={8}>
            <Form.Item name="branch_id" label={t("orders.columns.branch")} rules={[{ required: true, message: t("common.required") }]}>
              <Select options={(me?.branches ?? []).map((b) => ({ value: b.id, label: b.name }))} />
            </Form.Item>
          </Col>
        </Row>
        <Form.List name="lines">
          {(fields, { add, remove }) => (
            <Flex vertical gap={4}>
              {fields.map((field, index) => (
                <Row key={field.key} gutter={8} align="middle">
                  <Col xs={24} sm={8}>
                    <Form.Item name={[field.name, "item_id"]} label={index === 0 ? t("deliveryNotes.item") : undefined} rules={[{ required: true, message: t("common.required") }]}>
                      <Select showSearch optionFilterProp="label" aria-label={t("deliveryNotes.item")} options={items.map((item) => ({ value: item.id, label: item.name }))} onChange={() => form.setFieldValue(["lines", field.name, "service_type_id"], undefined)} />
                    </Form.Item>
                  </Col>
                  <Col xs={24} sm={6}>
                    <Form.Item name={[field.name, "service_type_id"]} label={index === 0 ? t("deliveryNotes.service") : undefined} rules={[{ required: true, message: t("common.required") }]}>
                      <Select aria-label={t("deliveryNotes.service")} options={servicesFor(lines[index]?.item_id).map((s) => ({ value: s.id, label: s.name }))} />
                    </Form.Item>
                  </Col>
                  <Col xs={10} sm={4}>
                    <Form.Item name={[field.name, "expected_quantity"]} label={index === 0 ? t("deliveryNotes.stated") : undefined} rules={[{ required: true, message: t("common.required") }]}>
                      <InputNumber<number> {...quantityInput} aria-label={t("deliveryNotes.stated")} />
                    </Form.Item>
                  </Col>
                  <Col xs={10} sm={4}>
                    <Form.Item name={[field.name, "counted_quantity"]} label={index === 0 ? t("deliveryNotes.counted") : undefined} rules={[{ required: true, message: t("common.required") }]}>
                      <InputNumber<number> {...quantityInput} aria-label={t("deliveryNotes.counted")} />
                    </Form.Item>
                  </Col>
                  <Col xs={4} sm={2}>
                    <Button type="text" danger icon={<MinusCircleOutlined />} aria-label={t("common.delete")} disabled={fields.length === 1} onClick={() => remove(field.name)} style={{ marginTop: index === 0 ? 30 : 0 }} />
                  </Col>
                </Row>
              ))}
              <Button type="dashed" icon={<PlusOutlined />} onClick={() => add({})}>
                {t("deliveryNotes.addLine")}
              </Button>
            </Flex>
          )}
        </Form.List>
        <Form.Item name="notes" label={t("deliveryNotes.notes")} style={{ marginTop: 12 }}>
          <Input maxLength={500} />
        </Form.Item>
      </Form>
    </Modal>
  );
}

/** Picks one of the company's open orders to send back. */
function OutgoingModal({ company, open, onClose }: { company: Company; open: boolean; onClose: () => void }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const orders = useOpenCompanyOrders(open ? company.id : null);
  const save = useSaveDeliveryNote();
  const [orderId, setOrderId] = useState<number | null>(null);

  return (
    <Modal
      open={open}
      title={t("deliveryNotes.newOut")}
      okText={t("common.save")}
      cancelText={t("common.cancel")}
      okButtonProps={{ disabled: orderId === null }}
      confirmLoading={save.isPending}
      onCancel={onClose}
      onOk={() =>
        orderId !== null &&
        save.mutate(
          { input: { type: "out", company_id: company.id, order_id: orderId } },
          {
            onSuccess: () => {
              message.success(t("common.created"));
              setOrderId(null);
              onClose();
            },
            onError: (error) => message.error(errorMessage(error, t("common.error"))),
          },
        )
      }
      destroyOnHidden
    >
      <Typography.Paragraph type="secondary">{t("deliveryNotes.outHelp")}</Typography.Paragraph>
      <Select
        style={{ width: "100%" }}
        aria-label={t("deliveryNotes.order")}
        placeholder={t("deliveryNotes.order")}
        loading={orders.isLoading}
        value={orderId}
        onChange={setOrderId}
        options={(orders.data?.data ?? []).map((o) => ({ value: o.id, label: `${o.ticket_number} · ${t(`orders.statuses.${o.status}`)}` }))}
      />
    </Modal>
  );
}

/** Closes a note with who signed for the company and, going out, its count. */
function ConfirmModal({ note, onClose }: { note: DeliveryNote | null; onClose: () => void }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const confirm = useConfirmDeliveryNote();
  const [name, setName] = useState("");
  const [received, setReceived] = useState<Record<number, number>>({});

  return (
    <Modal
      open={note !== null}
      title={t("deliveryNotes.confirmTitle", { number: toPersianDigits(note?.number ?? "") })}
      okText={t("deliveryNotes.confirm")}
      cancelText={t("common.cancel")}
      okButtonProps={{ disabled: name.trim() === "" }}
      confirmLoading={confirm.isPending}
      onCancel={onClose}
      onOk={() =>
        note &&
        confirm.mutate(
          { id: note.id, counterpart_name: name.trim(), received: Object.entries(received).map(([line, quantity]) => ({ line_id: Number(line), quantity })) },
          {
            onSuccess: () => {
              message.success(t("deliveryNotes.confirmed"));
              onClose();
            },
            onError: (error) => message.error(errorMessage(error, t("common.error"))),
          },
        )
      }
      destroyOnHidden
    >
      <Typography.Paragraph type="secondary">{note?.type === "in" ? t("deliveryNotes.confirmInHelp") : t("deliveryNotes.confirmOutHelp")}</Typography.Paragraph>
      {note?.type === "out" &&
        (note.lines ?? []).map((line) => (
          <Flex key={line.id} justify="space-between" align="center" gap={8} style={{ marginBottom: 8 }}>
            <span>
              {line.item_name} ({line.service_name}) — {t("deliveryNotes.sent")}: {formatNumber(line.expected_quantity)}
            </span>
            <InputNumber<number> {...quantityInput} style={{ width: 120 }} aria-label={`${t("deliveryNotes.received")} ${line.item_name}`} placeholder={formatNumber(line.expected_quantity)} value={received[line.id]} onChange={(v) => setReceived((r) => ({ ...r, [line.id]: v ?? 0 }))} />
          </Flex>
        ))}
      <Input autoFocus value={name} onChange={(e) => setName(e.target.value)} placeholder={t("deliveryNotes.counterpart")} aria-label={t("deliveryNotes.counterpart")} maxLength={100} />
    </Modal>
  );
}

export function DeliveryNotesCard({ company }: { company: Company }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can } = usePermission();
  const [page, setPage] = useState(1);
  const notes = useDeliveryNotes({ company_id: company.id, page });
  const remove = useDeleteDeliveryNote();
  const [creating, setCreating] = useState<"in" | "out" | null>(null);
  const [confirming, setConfirming] = useState<DeliveryNote | null>(null);
  const manage = can("companies.delivery");

  const columns: ColumnsType<DeliveryNote> = [
    { key: "number", title: t("deliveryNotes.number"), render: (_v, note) => <strong>{toPersianDigits(note.number)}</strong> },
    { key: "type", title: t("deliveryNotes.type"), render: (_v, note) => (note.type === "in" ? <Tag color="blue">{t("deliveryNotes.types.in")}</Tag> : <Tag color="purple">{t("deliveryNotes.types.out")}</Tag>) },
    { key: "created", title: t("deliveryNotes.date"), render: (_v, note) => formatDateTime(note.confirmed_at ?? note.created_at) },
    { key: "department", title: t("companies.fields.department"), render: (_v, note) => note.department?.name ?? "—" },
    { key: "order", title: t("deliveryNotes.order"), render: (_v, note) => (note.order ? <Link href={`/orders/${note.order.id}`} className="ltr">{note.order.ticket_number}</Link> : "—") },
    {
      key: "status",
      title: t("common.status"),
      render: (_v, note) => (
        <Space size={4}>
          {note.status === "confirmed" ? <Tag color="green">{t("deliveryNotes.statuses.confirmed")}</Tag> : <Tag>{t("deliveryNotes.statuses.draft")}</Tag>}
          {(note.differences ?? 0) > 0 && <Tag color="orange">{t("deliveryNotes.differences", { count: note.differences ?? 0 })}</Tag>}
        </Space>
      ),
    },
    {
      key: "actions",
      width: 130,
      render: (_v, note) => (
        <Space size={0}>
          <Tooltip title={t("printing.print")}>
            <Button type="text" icon={<PrinterOutlined />} aria-label={t("printing.print")} onClick={() => openDeliveryNotePrint(note.id)} />
          </Tooltip>
          {manage && note.status === "draft" && (
            <>
              <Tooltip title={t("deliveryNotes.confirm")}>
                <Button type="text" icon={<CheckOutlined />} aria-label={t("deliveryNotes.confirm")} onClick={() => setConfirming(note)} />
              </Tooltip>
              <Popconfirm title={t("deliveryNotes.deleteConfirm")} okText={t("common.delete")} cancelText={t("common.cancel")} okButtonProps={{ danger: true }} onConfirm={() => remove.mutateAsync(note.id).catch((error: unknown) => message.error(errorMessage(error, t("common.error"))))}>
                <Button type="text" danger icon={<DeleteOutlined />} aria-label={t("common.delete")} />
              </Popconfirm>
            </>
          )}
        </Space>
      ),
    },
  ];

  return (
    <Card
      title={t("deliveryNotes.title")}
      extra={
        manage && (
          <Space>
            <Button size="small" icon={<ImportOutlined />} onClick={() => setCreating("in")}>
              {t("deliveryNotes.newIn")}
            </Button>
            <Button size="small" icon={<ExportOutlined />} onClick={() => setCreating("out")}>
              {t("deliveryNotes.newOut")}
            </Button>
          </Space>
        )
      }
      styles={{ body: { padding: 0 } }}
    >
      <Table<DeliveryNote>
        rowKey="id"
        size="small"
        columns={columns}
        dataSource={notes.data?.data ?? []}
        loading={notes.isFetching}
        scroll={{ x: "max-content" }}
        locale={{ emptyText: t("deliveryNotes.none") }}
        pagination={{ current: page, pageSize: 10, total: notes.data?.meta.total ?? 0, onChange: setPage, hideOnSinglePage: true }}
      />
      <IncomingModal company={company} open={creating === "in"} onClose={() => setCreating(null)} />
      <OutgoingModal company={company} open={creating === "out"} onClose={() => setCreating(null)} />
      <ConfirmModal key={confirming?.id ?? 0} note={confirming} onClose={() => setConfirming(null)} />
    </Card>
  );
}
