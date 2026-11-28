"use client";

import { DeleteOutlined, EditOutlined, PlusOutlined, SettingOutlined } from "@ant-design/icons";
import { App, Button, Card, Checkbox, Col, Flex, Form, Input, Modal, Popconfirm, Row, Segmented, Select, Space, Switch, Table, Tag, Tooltip, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import { useTranslations } from "next-intl";
import { parseAsInteger, parseAsString } from "nuqs";
import { useEffect, useState } from "react";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { Expense, ExpenseCategory, PaymentMethod } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { useListParams } from "@/shared/hooks/useListParams";
import { usePermission } from "@/shared/hooks/usePermission";
import { useRememberedId } from "@/shared/hooks/useRememberedId";
import { dayjs, formatDate, type Dayjs } from "@/shared/lib/date";
import { formatMoney } from "@/shared/lib/money";
import { DataTable } from "@/shared/ui/DataTable";
import { JalaliDatePicker, JalaliRangePicker } from "@/shared/ui/JalaliDatePicker";
import { MoneyInput } from "@/shared/ui/MoneyInput";
import { PageHeader } from "@/shared/ui/PageHeader";
import { StatTile } from "@/shared/ui/StatTile";
import { useDeleteExpense, useDeleteExpenseCategory, useExpenseCategories, useExpenses, useSaveExpense, useSaveExpenseCategory } from "../api";

const DATE = "YYYY-MM-DD";
const METHODS: PaymentMethod[] = ["cash", "card", "transfer"];

type Values = {
  branch_id: number;
  expense_category_id: number;
  spent_on: Dayjs;
  amount: number | null;
  method: PaymentMethod;
  description: string;
  reference?: string | null;
  from_register?: boolean;
};

function CategoriesModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const categories = useExpenseCategories(open);
  const save = useSaveExpenseCategory();
  const remove = useDeleteExpenseCategory();
  const [name, setName] = useState("");
  const fail = (error: unknown) => message.error(errorMessage(error, t("common.error")));

  const columns: ColumnsType<ExpenseCategory> = [
    {
      key: "name",
      dataIndex: "name",
      title: t("expenses.fields.category"),
      render: (value: string, category) => (
        <Typography.Text editable={{ tooltip: t("common.edit"), onChange: (next) => next.trim() && next !== value && save.mutate({ id: category.id, input: { name: next.trim() } }, { onError: fail }) }}>
          {value}
        </Typography.Text>
      ),
    },
    {
      key: "active",
      dataIndex: "is_active",
      title: t("common.active"),
      width: 80,
      render: (active: boolean, category) => (
        <Switch size="small" checked={active} aria-label={t("common.active")} onChange={(checked) => save.mutate({ id: category.id, input: { is_active: checked } }, { onError: fail })} />
      ),
    },
    {
      key: "actions",
      width: 60,
      render: (_v, category) => (
        <Popconfirm title={t("expenses.deleteCategoryConfirm", { name: category.name })} okText={t("common.delete")} cancelText={t("common.cancel")} okButtonProps={{ danger: true }} onConfirm={() => remove.mutateAsync(category.id).catch(fail)}>
          <Button type="text" danger icon={<DeleteOutlined />} aria-label={t("common.delete")} />
        </Popconfirm>
      ),
    },
  ];

  return (
    <Modal open={open} title={t("expenses.categories")} onCancel={onClose} footer={null} destroyOnHidden>
      <Space.Compact style={{ width: "100%", marginBottom: 12 }}>
        <Input value={name} onChange={(e) => setName(e.target.value)} placeholder={t("expenses.newCategory")} maxLength={100} aria-label={t("expenses.newCategory")} />
        <Button
          type="primary"
          icon={<PlusOutlined />}
          disabled={name.trim() === ""}
          loading={save.isPending}
          onClick={() => save.mutate({ input: { name: name.trim() } }, { onSuccess: () => setName(""), onError: fail })}
        >
          {t("expenses.addCategory")}
        </Button>
      </Space.Compact>
      <Table<ExpenseCategory> rowKey="id" size="small" columns={columns} dataSource={categories.data ?? []} loading={categories.isLoading} pagination={false} />
    </Modal>
  );
}

export function ExpensesPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can, me } = usePermission();
  const unit = useCurrencyUnit();
  const [form] = Form.useForm<Values>();
  const [editing, setEditing] = useState<Expense | "new" | null>(null);
  const [managingCategories, setManagingCategories] = useState(false);
  const [rememberedBranch, rememberBranch] = useRememberedId("expenses-branch");
  const [params, setParams, setFilter] = useListParams({
    branch: parseAsInteger,
    category: parseAsInteger,
    from: parseAsString,
    to: parseAsString,
  });
  const expenses = useExpenses({ q: params.q, branch_id: params.branch, category_id: params.category, from: params.from, to: params.to, page: params.page, per_page: params.per_page });
  const categories = useExpenseCategories();
  const save = useSaveExpense();
  const remove = useDeleteExpense();
  const method = Form.useWatch("method", form);
  const branches = me?.branches ?? [];
  const manage = can("expenses.manage");
  const money = (v: number) => `${formatMoney(v, unit)} ${t(`money.${unit}`)}`;

  useEffect(() => {
    if (editing === null) {
      return;
    }
    form.resetFields();
    form.setFieldsValue(
      editing === "new"
        ? { branch_id: rememberedBranch ?? branches[0]?.id, spent_on: dayjs(), method: "cash", from_register: true }
        : { ...editing, branch_id: editing.branch.id, expense_category_id: editing.category.id, spent_on: dayjs(editing.spent_on) },
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editing, form]);

  const onFinish = (values: Values) => {
    const input = {
      ...values,
      amount: values.amount ?? 0,
      spent_on: values.spent_on.format(DATE),
      reference: values.reference || null,
      from_register: editing === "new" && values.method === "cash" && Boolean(values.from_register),
    };
    rememberBranch(values.branch_id);
    save.mutate(
      { id: editing === "new" || editing === null ? undefined : editing.id, input: editing === "new" ? input : { ...input, from_register: undefined } },
      {
        onSuccess: () => {
          message.success(editing === "new" ? t("common.created") : t("common.saved"));
          setEditing(null);
        },
        onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error"))),
      },
    );
  };

  const columns: ColumnsType<Expense> = [
    { key: "spent_on", dataIndex: "spent_on", title: t("expenses.fields.spentOn"), render: (v: string) => formatDate(v) },
    { key: "category", dataIndex: "category", title: t("expenses.fields.category"), render: (c: Expense["category"]) => <Tag>{c.name}</Tag> },
    {
      key: "description",
      title: t("expenses.fields.description"),
      render: (_v, expense) => (
        <Flex vertical>
          <span>{expense.description}</span>
          {expense.reference && (
            <Typography.Text type="secondary" className="ltr" style={{ fontSize: 12 }}>
              {expense.reference}
            </Typography.Text>
          )}
        </Flex>
      ),
    },
    {
      key: "method",
      title: t("billing.method"),
      render: (_v, expense) => (
        <Space size={4}>
          <Tag>{t(`billing.methods.${expense.method}`)}</Tag>
          {expense.from_register && <Tag color="blue">{t("expenses.fromRegisterTag")}</Tag>}
        </Space>
      ),
    },
    { key: "amount", dataIndex: "amount", title: `${t("billing.amount")} (${t(`money.${unit}`)})`, align: "end", render: (v: number) => <strong>{formatMoney(v, unit)}</strong> },
    ...(manage
      ? [
          {
            key: "actions",
            title: t("common.actions"),
            width: 110,
            render: (_v: unknown, expense: Expense) => (
              <Space>
                <Tooltip title={t("common.edit")}>
                  <Button type="text" icon={<EditOutlined />} aria-label={t("common.edit")} onClick={() => setEditing(expense)} />
                </Tooltip>
                <Popconfirm
                  title={expense.from_register ? t("expenses.deleteFromRegisterConfirm") : t("expenses.deleteConfirm")}
                  okText={t("common.delete")}
                  cancelText={t("common.cancel")}
                  okButtonProps={{ danger: true }}
                  onConfirm={() =>
                    remove.mutateAsync(expense.id).then(
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
        ]
      : []),
  ];

  if (branches.length > 1) {
    columns.splice(1, 0, { key: "branch", dataIndex: "branch", title: t("orders.columns.branch"), render: (b: Expense["branch"]) => b.name });
  }

  const summary = expenses.data?.summary;
  const lockedMoney = editing !== null && editing !== "new" && editing.from_register;

  return (
    <>
      <PageHeader
        title={t("expenses.title")}
        subtitle={t("expenses.subtitle")}
        extra={
          <>
            {can("expenses.categories") && (
              <Button icon={<SettingOutlined />} onClick={() => setManagingCategories(true)}>
                {t("expenses.categories")}
              </Button>
            )}
            {manage && (
              <Button type="primary" icon={<PlusOutlined />} onClick={() => setEditing("new")}>
                {t("expenses.new")}
              </Button>
            )}
          </>
        }
      />
      <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
        <Col xs={24} md={8}>
          <StatTile title={t("expenses.total")} value={formatMoney(summary?.total ?? 0, unit)} suffix={t(`money.${unit}`)} loading={expenses.isLoading} />
        </Col>
        <Col xs={24} md={16}>
          <Card size="small" title={t("expenses.byCategory")} style={{ height: "100%" }}>
            {(summary?.by_category ?? []).length === 0 ? (
              <Typography.Text type="secondary">{t("expenses.none")}</Typography.Text>
            ) : (
              <Flex wrap gap={8}>
                {summary?.by_category.map((c) => (
                  <Tag key={c.id} style={{ cursor: "pointer" }} color={params.category === c.id ? "blue" : undefined} onClick={() => setFilter({ category: params.category === c.id ? null : c.id })}>
                    {c.name}: {money(c.total)}
                  </Tag>
                ))}
              </Flex>
            )}
          </Card>
        </Col>
      </Row>
      <Card>
        <Flex gap={8} wrap style={{ marginBottom: 16 }}>
          <Input.Search allowClear defaultValue={params.q} placeholder={t("expenses.searchPlaceholder")} onSearch={(q) => setFilter({ q: q || null })} style={{ width: 260 }} />
          <Select
            allowClear
            placeholder={t("expenses.fields.category")}
            value={params.category}
            onChange={(category) => setFilter({ category: category ?? null })}
            options={(categories.data ?? []).map((c) => ({ value: c.id, label: c.name }))}
            style={{ width: 180 }}
          />
          <JalaliRangePicker
            allowEmpty={[true, true]}
            placeholder={[t("activity.filters.from"), t("activity.filters.to")]}
            value={[params.from ? dayjs(params.from) : null, params.to ? dayjs(params.to) : null]}
            onChange={(range) => setFilter({ from: range?.[0] ? range[0].format(DATE) : null, to: range?.[1] ? range[1].format(DATE) : null })}
            format="YYYY/MM/DD"
          />
          {branches.length > 1 && (
            <Select
              allowClear
              placeholder={t("orders.columns.branch")}
              value={params.branch}
              onChange={(branch) => setFilter({ branch: branch ?? null })}
              options={branches.map((b) => ({ value: b.id, label: b.name }))}
              style={{ width: 180 }}
            />
          )}
        </Flex>
        <DataTable<Expense> columns={columns} result={expenses.data} loading={expenses.isFetching} onPageChange={(page, perPage) => setParams({ page, per_page: perPage })} />
      </Card>
      <Modal
        open={editing !== null}
        width={620}
        title={editing === "new" ? t("expenses.new") : t("expenses.edit")}
        okText={t("common.save")}
        cancelText={t("common.cancel")}
        onCancel={() => setEditing(null)}
        onOk={() => form.submit()}
        confirmLoading={save.isPending}
        forceRender
      >
        <Form<Values> form={form} layout="vertical" onFinish={onFinish} name="expense">
          {lockedMoney && <Typography.Paragraph type="secondary">{t("expenses.lockedHelp")}</Typography.Paragraph>}
          <Row gutter={16}>
            <Col xs={24} sm={12}>
              <Form.Item name="branch_id" label={t("orders.columns.branch")} rules={[{ required: true, message: t("common.required") }]}>
                <Select disabled={lockedMoney} options={branches.map((b) => ({ value: b.id, label: b.name }))} />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12}>
              <Form.Item name="expense_category_id" label={t("expenses.fields.category")} rules={[{ required: true, message: t("common.required") }]}>
                <Select showSearch optionFilterProp="label" options={(categories.data ?? []).filter((c) => c.is_active || (editing !== "new" && editing?.category.id === c.id)).map((c) => ({ value: c.id, label: c.name }))} />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12}>
              <Form.Item name="amount" label={t("billing.amount")} rules={[{ required: true, message: t("common.required") }]}>
                <MoneyInput unit={unit} disabled={lockedMoney} />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12}>
              <Form.Item name="spent_on" label={t("expenses.fields.spentOn")} rules={[{ required: true, message: t("common.required") }]}>
                <JalaliDatePicker format="YYYY/MM/DD" style={{ width: "100%" }} allowClear={false} />
              </Form.Item>
            </Col>
            <Col xs={24}>
              <Form.Item name="method" label={t("billing.method")}>
                <Segmented disabled={lockedMoney} options={METHODS.map((m) => ({ value: m, label: t(`billing.methods.${m}`) }))} />
              </Form.Item>
            </Col>
            {editing === "new" && method === "cash" && (
              <Col xs={24}>
                <Form.Item name="from_register" valuePropName="checked" extra={t("expenses.fromRegisterHelp")}>
                  <Checkbox>{t("expenses.fromRegister")}</Checkbox>
                </Form.Item>
              </Col>
            )}
            <Col xs={24}>
              <Form.Item name="description" label={t("expenses.fields.description")} rules={[{ required: true, message: t("common.required") }]}>
                <Input maxLength={255} />
              </Form.Item>
            </Col>
            <Col xs={24}>
              <Form.Item name="reference" label={t("expenses.fields.reference")}>
                <Input maxLength={100} />
              </Form.Item>
            </Col>
          </Row>
        </Form>
      </Modal>
      <CategoriesModal open={managingCategories} onClose={() => setManagingCategories(false)} />
    </>
  );
}
