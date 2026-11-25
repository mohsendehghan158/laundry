"use client";

import { DeleteOutlined, EditOutlined, PlusOutlined } from "@ant-design/icons";
import { App, Button, Card, Col, Empty, Flex, Form, Input, InputNumber, Modal, Popconfirm, Progress, Row, Select, Switch, Table, Tag, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import dayjs from "dayjs";
import { useTranslations } from "next-intl";
import { useEffect, useMemo, useState } from "react";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatJalali } from "@/shared/lib/date";
import { formatNumber, inputNumberFormatter, toLatinDigits, toPersianDigits } from "@/shared/lib/digits";
import { parseAmount } from "@/shared/lib/money";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useDeleteSlotTemplate, useSaveSlotTemplate, useSlots, useSlotTemplates, useUpdateSlot } from "../api";
import { WEEK, type Slot, type SlotTemplate } from "../types";

const WEEKDAY_KEYS = ["sun", "mon", "tue", "wed", "thu", "fri", "sat"] as const;
const TIME = /^([01]\d|2[0-3]):[0-5]\d$/;

type Values = { weekday: number; starts_at: string; ends_at: string; capacity: number; is_active: boolean };

/** "09:00–12:00", in Persian digits. */
export function timeRange(from: string, to: string): string {
  return toPersianDigits(`${from}–${to}`);
}

/**
 * Pickup and delivery windows of a branch: the weekly pattern, and the
 * dated slots it makes for the days customers can book.
 */
export function SlotsPage() {
  const t = useTranslations("logistics");
  const tc = useTranslations("common");
  const { message } = App.useApp();
  const { me, can } = usePermission();
  const manage = can("logistics.manage");
  const branches = useMemo(() => me?.branches ?? [], [me]);
  const [branchId, setBranchId] = useState<number | null>(null);
  const branch = branchId ?? branches[0]?.id ?? null;
  const templates = useSlotTemplates();
  const slots = useSlots(branch);
  const saveTemplate = useSaveSlotTemplate();
  const deleteTemplate = useDeleteSlotTemplate();
  const updateSlot = useUpdateSlot();
  const [form] = Form.useForm<Values>();
  const [editing, setEditing] = useState<SlotTemplate | { weekday: number } | null>(null);

  const weekly = (templates.data ?? []).filter((template) => template.branch_id === branch);
  const weekday = (n: number) => t(`weekdays.${WEEKDAY_KEYS[n]}`);

  useEffect(() => {
    if (editing === null) {
      return;
    }
    form.resetFields();
    form.setFieldsValue("id" in editing ? editing : { weekday: editing.weekday, starts_at: "09:00", ends_at: "12:00", capacity: 5, is_active: true });
  }, [editing, form]);

  const onFinish = (values: Values) => {
    const input = { ...values, starts_at: toLatinDigits(values.starts_at), ends_at: toLatinDigits(values.ends_at) };
    const id = editing && "id" in editing ? editing.id : undefined;
    saveTemplate.mutate(
      { id, input: id === undefined ? { ...input, branch_id: branch ?? undefined } : input },
      {
        onSuccess: () => {
          message.success(id === undefined ? tc("created") : tc("saved"));
          setEditing(null);
        },
        onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, tc("error"))),
      },
    );
  };

  const changeSlot = (slot: Slot, input: Partial<Pick<Slot, "capacity" | "is_closed">>) =>
    updateSlot.mutate({ id: slot.id, input }, { onError: (error) => message.error(errorMessage(error, tc("error"))) });

  const columns: ColumnsType<Slot> = [
    {
      key: "date",
      title: t("slots.date"),
      render: (_v, slot) => toPersianDigits(formatJalali(dayjs(slot.date), "dddd D MMMM")),
    },
    { key: "time", title: t("slots.time"), render: (_v, slot) => <span className="ltr">{timeRange(slot.starts_at, slot.ends_at)}</span> },
    {
      key: "booked",
      title: t("slots.booked"),
      width: 200,
      render: (_v, slot) => (
        <Flex align="center" gap={8}>
          <Progress percent={Math.round((slot.booked / Math.max(1, slot.capacity)) * 100)} showInfo={false} size="small" style={{ width: 80, margin: 0 }} status={slot.is_closed ? "exception" : undefined} />
          <Typography.Text>
            {formatNumber(slot.booked)} / {formatNumber(slot.capacity)}
          </Typography.Text>
        </Flex>
      ),
    },
    {
      key: "capacity",
      title: t("slots.capacity"),
      render: (_v, slot) =>
        manage ? (
          <InputNumber<number>
            size="small"
            min={Math.max(1, slot.booked)}
            max={1000}
            defaultValue={slot.capacity}
            key={`${slot.id}-${slot.capacity}`}
            formatter={inputNumberFormatter}
            parser={(text) => parseAmount(text) ?? 0}
            aria-label={`${t("slots.capacity")} ${slot.date} ${slot.starts_at}`}
            onBlur={(event) => {
              const value = parseAmount(event.target.value);
              if (value !== null && value !== slot.capacity && value >= Math.max(1, slot.booked)) {
                changeSlot(slot, { capacity: value });
              }
            }}
            style={{ width: 80 }}
          />
        ) : (
          formatNumber(slot.capacity)
        ),
    },
    {
      key: "open",
      title: t("slots.open"),
      render: (_v, slot) =>
        manage ? (
          <Switch size="small" checked={!slot.is_closed} aria-label={`${t("slots.open")} ${slot.date} ${slot.starts_at}`} onChange={(open) => changeSlot(slot, { is_closed: !open })} />
        ) : slot.is_closed ? (
          <Tag color="red">{t("slots.closed")}</Tag>
        ) : (
          <Tag color="green">{t("slots.open")}</Tag>
        ),
    },
  ];

  return (
    <>
      <PageHeader
        title={t("slots.title")}
        subtitle={t("slots.subtitle")}
        extra={
          branches.length > 1 && (
            <Select aria-label={t("zones.fields.branch")} value={branch} onChange={setBranchId} options={branches.map((b) => ({ value: b.id, label: b.name }))} style={{ width: 200 }} />
          )
        }
      />

      <Typography.Title level={5}>{t("slots.weekly")}</Typography.Title>
      <Row gutter={[12, 12]} style={{ marginBottom: 24 }}>
        {WEEK.map((day) => {
          const windows = weekly.filter((template) => template.weekday === day);
          return (
            <Col key={day} xs={24} sm={12} md={8} xl={6}>
              <Card
                size="small"
                title={weekday(day)}
                extra={
                  manage && (
                    <Button type="text" size="small" icon={<PlusOutlined />} aria-label={`${t("slots.addWindow")} ${weekday(day)}`} onClick={() => setEditing({ weekday: day })} />
                  )
                }
              >
                {windows.length === 0 ? (
                  <Typography.Text type="secondary">{t("slots.noWindows")}</Typography.Text>
                ) : (
                  <Flex vertical gap={6}>
                    {windows.map((template) => (
                      <Flex key={template.id} justify="space-between" align="center" gap={4}>
                        <Flex vertical>
                          <Typography.Text className="ltr" type={template.is_active ? undefined : "secondary"} style={{ textAlign: "start" }}>
                            {timeRange(template.starts_at, template.ends_at)}
                          </Typography.Text>
                          <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                            {t("slots.places", { count: template.capacity })}
                            {!template.is_active && ` — ${tc("inactive")}`}
                          </Typography.Text>
                        </Flex>
                        {manage && (
                          <Flex>
                            <Button type="text" size="small" icon={<EditOutlined />} aria-label={`${tc("edit")} ${weekday(day)} ${template.starts_at}`} onClick={() => setEditing(template)} />
                            <Popconfirm
                              title={t("slots.deleteConfirm")}
                              description={t("slots.deleteHelp")}
                              okText={tc("delete")}
                              cancelText={tc("cancel")}
                              okButtonProps={{ danger: true }}
                              onConfirm={() =>
                                deleteTemplate.mutateAsync(template.id).then(
                                  () => message.success(tc("deleted")),
                                  (error: unknown) => message.error(errorMessage(error, tc("error"))),
                                )
                              }
                            >
                              <Button type="text" size="small" danger icon={<DeleteOutlined />} aria-label={`${tc("delete")} ${weekday(day)} ${template.starts_at}`} />
                            </Popconfirm>
                          </Flex>
                        )}
                      </Flex>
                    ))}
                  </Flex>
                )}
              </Card>
            </Col>
          );
        })}
      </Row>

      <Typography.Title level={5}>{t("slots.upcoming")}</Typography.Title>
      <Card styles={{ body: { padding: 0 } }}>
        <Table<Slot>
          rowKey="id"
          columns={columns}
          dataSource={slots.data ?? []}
          loading={slots.isLoading}
          pagination={false}
          scroll={{ x: "max-content" }}
          locale={{ emptyText: <Empty description={t("slots.empty")} /> }}
        />
      </Card>

      <Modal
        open={editing !== null}
        title={editing && "id" in editing ? t("slots.editWindow") : t("slots.addWindow")}
        okText={tc("save")}
        cancelText={tc("cancel")}
        onOk={() => form.submit()}
        onCancel={() => setEditing(null)}
        confirmLoading={saveTemplate.isPending}
        destroyOnHidden
      >
        <Form<Values> form={form} layout="vertical" requiredMark={false} onFinish={onFinish}>
          <Form.Item name="weekday" label={t("slots.weekday")}>
            <Select options={WEEK.map((day) => ({ value: day, label: weekday(day) }))} />
          </Form.Item>
          <Flex gap={12}>
            {(["starts_at", "ends_at"] as const).map((field) => (
              <Form.Item
                key={field}
                name={field}
                label={t(`slots.${field === "starts_at" ? "from" : "to"}`)}
                style={{ flex: 1 }}
                rules={[{ validator: async (_, value?: string) => (TIME.test(toLatinDigits(value ?? "")) ? undefined : Promise.reject(new Error(t("slots.timeFormat")))) }]}
              >
                <Input dir="ltr" placeholder="09:00" inputMode="numeric" />
              </Form.Item>
            ))}
          </Flex>
          <Form.Item name="capacity" label={t("slots.capacity")} extra={t("slots.capacityHelp")} rules={[{ required: true, message: tc("required") }]}>
            <InputNumber<number> min={1} max={1000} formatter={inputNumberFormatter} parser={(text) => parseAmount(text) ?? 0} style={{ width: "100%" }} />
          </Form.Item>
          <Form.Item name="is_active" label={tc("active")} valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>
    </>
  );
}
