"use client";

import { DeleteOutlined, PlusOutlined } from "@ant-design/icons";
import { App, Button, Card, Checkbox, Col, Flex, Form, Input, Modal, Row } from "antd";
import { useTranslations } from "next-intl";
import { useEffect } from "react";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { Customer, SaveCustomerInput } from "@/shared/api/types";
import { toLatinDigits } from "@/shared/lib/digits";
import { useSaveCustomer } from "../api";

type Values = {
  name: string;
  mobile: string;
  phone?: string | null;
  notes?: string | null;
  alert?: string | null;
  addresses: { id?: number; title?: string | null; address: string; is_default?: boolean }[];
};

type Props = {
  open: boolean;
  customer?: Customer;
  /** Prefills a new customer, e.g. with what was typed in a search box. */
  initial?: Partial<Pick<Values, "name" | "mobile">>;
  onClose: () => void;
  onSaved?: (customer: Customer) => void;
};

export function CustomerFormModal({ open, customer, initial, onClose, onSaved }: Props) {
  const t = useTranslations();
  const { message } = App.useApp();
  const [form] = Form.useForm<Values>();
  const save = useSaveCustomer();

  useEffect(() => {
    if (!open) {
      return;
    }
    form.resetFields();
    form.setFieldsValue(
      customer
        ? {
            name: customer.name,
            mobile: customer.mobile,
            phone: customer.phone,
            notes: customer.notes,
            alert: customer.alert,
            addresses: (customer.addresses ?? []).map((a) => ({ id: a.id, title: a.title, address: a.address, is_default: a.is_default })),
          }
        : { name: initial?.name ?? "", mobile: initial?.mobile ?? "", addresses: [] },
    );
    // Fill the form when it opens or switches to another customer only;
    // re-running on every new `initial`/`customer` object would wipe what
    // the user is typing whenever the parent re-renders.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, customer?.id, form]);

  const onFinish = (values: Values) => {
    const input: Partial<SaveCustomerInput> = {
      ...values,
      mobile: toLatinDigits(values.mobile),
      phone: values.phone || null,
      notes: values.notes || null,
      alert: values.alert || null,
      addresses: (values.addresses ?? []).map((a) => ({ ...a, title: a.title || null })),
    };
    save.mutate(
      { id: customer?.id, input },
      {
        onSuccess: (saved) => {
          message.success(customer ? t("common.saved") : t("common.created"));
          onSaved?.(saved);
          onClose();
        },
        onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error"))),
      },
    );
  };

  return (
    <Modal
      open={open}
      width={640}
      title={customer ? t("customers.edit") : t("customers.new")}
      okText={t("common.save")}
      cancelText={t("common.cancel")}
      onCancel={onClose}
      onOk={() => form.submit()}
      confirmLoading={save.isPending}
      forceRender
    >
      <Form<Values> form={form} layout="vertical" onFinish={onFinish} name="customer">
        <Row gutter={16}>
          <Col xs={24} sm={12}>
            <Form.Item name="name" label={t("customers.fields.name")} rules={[{ required: true, message: t("common.required") }, { max: 100 }]}>
              <Input autoFocus />
            </Form.Item>
          </Col>
          <Col xs={24} sm={12}>
            <Form.Item
              name="mobile"
              label={t("customers.fields.mobile")}
              rules={[
                { required: true, message: t("common.required") },
                {
                  validator: (_rule, value?: string) =>
                    !value || /^(?:\+98|0098|98|0)?9\d{9}$/.test(toLatinDigits(value).replace(/[\s-]/g, ""))
                      ? Promise.resolve()
                      : Promise.reject(new Error(t("users.mobileInvalid"))),
                },
              ]}
            >
              <Input dir="ltr" inputMode="tel" placeholder="09121234567" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={12}>
            <Form.Item name="phone" label={t("customers.fields.phone")}>
              <Input dir="ltr" inputMode="tel" />
            </Form.Item>
          </Col>
          <Col xs={24}>
            <Form.Item name="alert" label={t("customers.fields.alert")} extra={t("customers.fields.alertHelp")} rules={[{ max: 500 }]}>
              <Input />
            </Form.Item>
          </Col>
          <Col xs={24}>
            <Form.Item name="notes" label={t("customers.fields.notes")} rules={[{ max: 2000 }]}>
              <Input.TextArea rows={2} />
            </Form.Item>
          </Col>
        </Row>
        <Form.List name="addresses">
          {(fields, { add, remove }) => (
            <Flex vertical gap={8}>
              {fields.map((field) => (
                <Card key={field.key} size="small">
                  <Row gutter={8} align="bottom">
                    <Form.Item name={[field.name, "id"]} hidden>
                      <Input />
                    </Form.Item>
                    <Col xs={24} sm={6}>
                      <Form.Item name={[field.name, "title"]} label={t("customers.fields.addressTitle")} style={{ marginBottom: 8 }}>
                        <Input placeholder={t("customers.fields.addressTitlePlaceholder")} />
                      </Form.Item>
                    </Col>
                    <Col xs={24} sm={14}>
                      <Form.Item
                        name={[field.name, "address"]}
                        label={t("customers.fields.address")}
                        rules={[{ required: true, message: t("common.required") }, { max: 500 }]}
                        style={{ marginBottom: 8 }}
                      >
                        <Input />
                      </Form.Item>
                    </Col>
                    <Col xs={20} sm={3}>
                      <Form.Item name={[field.name, "is_default"]} valuePropName="checked" style={{ marginBottom: 8 }}>
                        <Checkbox>{t("customers.fields.default")}</Checkbox>
                      </Form.Item>
                    </Col>
                    <Col xs={4} sm={1}>
                      <Button type="text" danger icon={<DeleteOutlined />} aria-label={t("common.delete")} onClick={() => remove(field.name)} style={{ marginBottom: 8 }} />
                    </Col>
                  </Row>
                </Card>
              ))}
              {fields.length < 10 && (
                <Button type="dashed" icon={<PlusOutlined />} onClick={() => add({ address: "" })}>
                  {t("customers.addAddress")}
                </Button>
              )}
            </Flex>
          )}
        </Form.List>
      </Form>
    </Modal>
  );
}
