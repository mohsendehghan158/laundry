"use client";

import { DeleteOutlined, EditOutlined, PlusOutlined } from "@ant-design/icons";
import { App, Button, Card, Col, Form, Input, InputNumber, Modal, Popconfirm, Radio, Row, Space, Switch, Table, Tag, Tooltip } from "antd";
import type { ColumnsType } from "antd/es/table";
import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { PromoCode } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { dayjs, formatDate, type Dayjs } from "@/shared/lib/date";
import { formatNumber, inputNumberFormatter, toLatinDigits } from "@/shared/lib/digits";
import { formatMoney, parseAmount } from "@/shared/lib/money";
import { JalaliRangePicker } from "@/shared/ui/JalaliDatePicker";
import { MoneyInput } from "@/shared/ui/MoneyInput";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useDeletePromoCode, usePromoCodes, useSavePromoCode } from "../api";

type Values = {
  code: string;
  description?: string | null;
  type: "amount" | "percent";
  value: number | null;
  max_discount?: number | null;
  min_order?: number | null;
  period?: [Dayjs | null, Dayjs | null] | null;
  usage_limit?: number | null;
  per_customer_limit?: number | null;
  first_order_only: boolean;
  is_active: boolean;
};

export function PromotionsPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const unit = useCurrencyUnit();
  const [form] = Form.useForm<Values>();
  const [editing, setEditing] = useState<PromoCode | "new" | null>(null);
  const codes = usePromoCodes();
  const save = useSavePromoCode();
  const remove = useDeletePromoCode();

  useEffect(() => {
    if (editing === null) {
      return;
    }
    form.resetFields();
    form.setFieldsValue(
      editing === "new"
        ? { type: "percent", first_order_only: false, is_active: true, min_order: 0 }
        : {
            ...editing,
            period: editing.starts_at || editing.ends_at ? [editing.starts_at ? dayjs(editing.starts_at) : null, editing.ends_at ? dayjs(editing.ends_at) : null] : null,
          },
    );
  }, [editing, form]);

  const onFinish = (values: Values) => {
    const { period, ...rest } = values;
    const input = {
      ...rest,
      code: toLatinDigits(values.code).toUpperCase(),
      description: values.description || null,
      value: values.value ?? 0,
      max_discount: values.type === "percent" ? (values.max_discount ?? null) : null,
      min_order: values.min_order ?? 0,
      starts_at: period?.[0] ? period[0].startOf("day").toISOString() : null,
      ends_at: period?.[1] ? period[1].endOf("day").toISOString() : null,
    };
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

  const describeValue = (code: PromoCode) =>
    code.type === "percent"
      ? `${formatNumber(code.value)}٪${code.max_discount ? ` (${t("promotions.upTo")} ${formatMoney(code.max_discount, unit)})` : ""}`
      : `${formatMoney(code.value, unit)} ${t(`money.${unit}`)}`;

  const columns: ColumnsType<PromoCode> = [
    { key: "code", dataIndex: "code", title: t("promotions.fields.code"), render: (v: string) => <Tag className="ltr">{v}</Tag> },
    { key: "value", title: t("promotions.fields.value"), render: (_v, code) => describeValue(code) },
    {
      key: "rules",
      title: t("promotions.rules"),
      render: (_v, code) => (
        <Space size={4} wrap>
          {code.min_order > 0 && <Tag>{t("promotions.minOrder", { amount: formatMoney(code.min_order, unit) })}</Tag>}
          {code.first_order_only && <Tag color="purple">{t("promotions.fields.firstOrderOnly")}</Tag>}
          {code.per_customer_limit && <Tag>{t("promotions.perCustomer", { count: code.per_customer_limit })}</Tag>}
          {code.ends_at && <Tag>{t("promotions.until", { date: formatDate(code.ends_at) })}</Tag>}
        </Space>
      ),
    },
    {
      key: "usage",
      title: t("promotions.usage"),
      render: (_v, code) => (code.usage_limit ? `${formatNumber(code.used_count ?? 0)} / ${formatNumber(code.usage_limit)}` : formatNumber(code.used_count ?? 0)),
    },
    {
      key: "status",
      title: t("common.status"),
      render: (_v, code) => {
        const expired = code.ends_at !== null && dayjs(code.ends_at).isBefore(dayjs());
        return !code.is_active ? <Tag>{t("common.inactive")}</Tag> : expired ? <Tag color="orange">{t("promotions.expired")}</Tag> : <Tag color="green">{t("common.active")}</Tag>;
      },
    },
    {
      key: "actions",
      title: t("common.actions"),
      width: 110,
      render: (_v, code) => (
        <Space>
          <Tooltip title={t("common.edit")}>
            <Button type="text" icon={<EditOutlined />} aria-label={t("common.edit")} onClick={() => setEditing(code)} />
          </Tooltip>
          <Popconfirm
            title={t("promotions.deleteConfirm", { code: code.code })}
            okText={t("common.delete")}
            cancelText={t("common.cancel")}
            okButtonProps={{ danger: true }}
            onConfirm={() =>
              remove.mutateAsync(code.id).then(
                () => message.success(t("common.deleted")),
                (error: unknown) => message.error(errorMessage(error, t("common.error"))),
              )
            }
          >
            <Button type="text" danger icon={<DeleteOutlined />} aria-label={t("common.delete")} />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <>
      <PageHeader
        title={t("promotions.title")}
        subtitle={t("promotions.subtitle")}
        extra={
          <Button type="primary" icon={<PlusOutlined />} onClick={() => setEditing("new")}>
            {t("promotions.new")}
          </Button>
        }
      />
      <Card styles={{ body: { padding: 0 } }}>
        <Table<PromoCode> rowKey="id" columns={columns} dataSource={codes.data ?? []} loading={codes.isLoading} pagination={false} scroll={{ x: "max-content" }} />
      </Card>
      <Modal
        open={editing !== null}
        width={620}
        title={editing === "new" ? t("promotions.new") : t("promotions.edit")}
        okText={t("common.save")}
        cancelText={t("common.cancel")}
        onCancel={() => setEditing(null)}
        onOk={() => form.submit()}
        confirmLoading={save.isPending}
        forceRender
      >
        <Form<Values> form={form} layout="vertical" onFinish={onFinish} name="promo">
          <Row gutter={16}>
            <Col xs={24} sm={12}>
              <Form.Item
                name="code"
                label={t("promotions.fields.code")}
                normalize={(v: string) => toLatinDigits(v ?? "").toUpperCase()}
                rules={[{ required: true, message: t("common.required") }, { pattern: /^[A-Z0-9_-]{1,30}$/, message: t("promotions.codeInvalid") }]}
              >
                <Input dir="ltr" maxLength={30} />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12}>
              <Form.Item name="type" label={t("promotions.fields.type")}>
                <Radio.Group optionType="button" options={[{ value: "percent", label: t("pos.discountPercent") }, { value: "amount", label: t("pos.discountAmount") }]} />
              </Form.Item>
            </Col>
            <Form.Item noStyle dependencies={["type"]}>
              {({ getFieldValue }) =>
                getFieldValue("type") === "percent" ? (
                  <>
                    <Col xs={24} sm={12}>
                      <Form.Item name="value" label={t("promotions.fields.percent")} rules={[{ required: true, message: t("common.required") }]}>
                        <InputNumber<number> min={0.01} max={100} step={0.5} formatter={inputNumberFormatter} parser={(text) => parseAmount(text) ?? 0} suffix="٪" style={{ width: "100%" }} />
                      </Form.Item>
                    </Col>
                    <Col xs={24} sm={12}>
                      <Form.Item name="max_discount" label={t("promotions.fields.maxDiscount")}>
                        <MoneyInput unit={unit} />
                      </Form.Item>
                    </Col>
                  </>
                ) : (
                  <Col xs={24} sm={12}>
                    <Form.Item name="value" label={t("promotions.fields.amount")} rules={[{ required: true, message: t("common.required") }]}>
                      <MoneyInput unit={unit} />
                    </Form.Item>
                  </Col>
                )
              }
            </Form.Item>
            <Col xs={24} sm={12}>
              <Form.Item name="min_order" label={t("promotions.fields.minOrder")}>
                <MoneyInput unit={unit} />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12}>
              <Form.Item name="period" label={t("promotions.fields.period")}>
                <JalaliRangePicker allowEmpty={[true, true]} format="YYYY/MM/DD" style={{ width: "100%" }} />
              </Form.Item>
            </Col>
            <Col xs={12} sm={6}>
              <Form.Item name="usage_limit" label={t("promotions.fields.usageLimit")}>
                <InputNumber<number> min={1} formatter={inputNumberFormatter} parser={(text) => parseAmount(text) ?? 0} style={{ width: "100%" }} />
              </Form.Item>
            </Col>
            <Col xs={12} sm={6}>
              <Form.Item name="per_customer_limit" label={t("promotions.fields.perCustomerLimit")}>
                <InputNumber<number> min={1} formatter={inputNumberFormatter} parser={(text) => parseAmount(text) ?? 0} style={{ width: "100%" }} />
              </Form.Item>
            </Col>
            <Col xs={24}>
              <Form.Item name="description" label={t("promotions.fields.description")}>
                <Input maxLength={255} />
              </Form.Item>
            </Col>
            <Col xs={12}>
              <Form.Item name="first_order_only" label={t("promotions.fields.firstOrderOnly")} valuePropName="checked">
                <Switch />
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
