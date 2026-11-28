"use client";

import { App, Col, Form, Input, Modal, Radio, Row, Typography } from "antd";
import { useTranslations } from "next-intl";
import { useEffect } from "react";
import { useCustomer } from "@/features/customers/api";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { BuyerType, Invoice } from "@/shared/api/types";
import { toLatinDigits, toPersianDigits } from "@/shared/lib/digits";
import { openInvoicePrint, useIssueInvoice } from "../api";

type Values = {
  buyer_type: BuyerType;
  buyer_name: string;
  buyer_national_id?: string | null;
  buyer_economic_code?: string | null;
  buyer_registration_number?: string | null;
  buyer_postal_code?: string | null;
  buyer_address?: string | null;
  buyer_phone?: string | null;
  notes?: string | null;
};

const digits = (value: string | undefined) => toLatinDigits(value ?? "").replace(/\D/g, "");

/**
 * Issues an official invoice for orders of one customer. The buyer
 * details start from the customer's last invoice.
 */
export function IssueInvoiceModal({ orderIds, customerId, open, onClose, onIssued }: { orderIds: number[]; customerId: number; open: boolean; onClose: () => void; onIssued?: (invoice: Invoice) => void }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const [form] = Form.useForm<Values>();
  const customer = useCustomer(open ? customerId : null);
  const issue = useIssueInvoice();
  const type = Form.useWatch("buyer_type", form);

  useEffect(() => {
    if (!open || !customer.data) {
      return;
    }
    const last = customer.data.invoice_details;
    form.resetFields();
    form.setFieldsValue(
      last
        ? Object.fromEntries(Object.entries(last).map(([key, value]) => [`buyer_${key}`, value]))
        : { buyer_type: "individual", buyer_name: customer.data.name, buyer_phone: customer.data.mobile },
    );
  }, [open, customer.data, form]);

  const onFinish = (values: Values) =>
    issue.mutate(
      { ...values, order_ids: orderIds },
      {
        onSuccess: (invoice) => {
          message.success(t("invoices.issued", { number: toPersianDigits(invoice.number) }));
          onClose();
          onIssued?.(invoice);
          openInvoicePrint(invoice.id);
        },
        onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error"))),
      },
    );

  const legal = type === "legal";

  return (
    <Modal
      open={open}
      width={680}
      title={t("invoices.issueTitle")}
      okText={t("invoices.issue")}
      cancelText={t("common.cancel")}
      onCancel={onClose}
      onOk={() => form.submit()}
      confirmLoading={issue.isPending}
      forceRender
    >
      <Typography.Paragraph type="secondary">{t("invoices.issueHelp")}</Typography.Paragraph>
      <Form<Values> form={form} layout="vertical" onFinish={onFinish} name="invoice">
        <Form.Item name="buyer_type" label={t("invoices.fields.buyerType")}>
          <Radio.Group
            optionType="button"
            options={[
              { value: "individual", label: t("invoices.buyerTypes.individual") },
              { value: "legal", label: t("invoices.buyerTypes.legal") },
            ]}
          />
        </Form.Item>
        <Row gutter={16}>
          <Col xs={24} sm={12}>
            <Form.Item name="buyer_name" label={legal ? t("invoices.fields.companyName") : t("invoices.fields.buyerName")} rules={[{ required: true, message: t("common.required") }]}>
              <Input maxLength={150} />
            </Form.Item>
          </Col>
          <Col xs={24} sm={12}>
            <Form.Item
              name="buyer_national_id"
              label={legal ? t("invoices.fields.legalId") : t("invoices.fields.personCode")}
              normalize={digits}
              rules={[
                { required: legal, message: t("common.required") },
                { pattern: legal ? /^\d{11}$/ : /^\d{10}$/, message: legal ? t("invoices.legalIdLength") : t("invoices.personCodeLength") },
              ]}
            >
              <Input dir="ltr" inputMode="numeric" maxLength={11} />
            </Form.Item>
          </Col>
          <Col xs={24} sm={12}>
            <Form.Item name="buyer_economic_code" label={t("invoices.fields.economicCode")} normalize={digits} rules={[{ pattern: /^\d{10,14}$/, message: t("invoices.economicCodeLength") }]}>
              <Input dir="ltr" inputMode="numeric" maxLength={14} />
            </Form.Item>
          </Col>
          {legal && (
            <Col xs={24} sm={12}>
              <Form.Item name="buyer_registration_number" label={t("invoices.fields.registrationNumber")} normalize={digits}>
                <Input dir="ltr" inputMode="numeric" maxLength={20} />
              </Form.Item>
            </Col>
          )}
          <Col xs={24} sm={12}>
            <Form.Item name="buyer_postal_code" label={t("invoices.fields.postalCode")} normalize={digits} rules={[{ pattern: /^\d{10}$/, message: t("invoices.postalCodeLength") }]}>
              <Input dir="ltr" inputMode="numeric" maxLength={10} />
            </Form.Item>
          </Col>
          <Col xs={24} sm={12}>
            <Form.Item name="buyer_phone" label={t("invoices.fields.phone")} normalize={(v: string) => toLatinDigits(v ?? "")}>
              <Input dir="ltr" maxLength={20} />
            </Form.Item>
          </Col>
          <Col xs={24}>
            <Form.Item name="buyer_address" label={t("invoices.fields.address")}>
              <Input.TextArea rows={2} maxLength={500} />
            </Form.Item>
          </Col>
          <Col xs={24}>
            <Form.Item name="notes" label={t("invoices.fields.notes")} extra={t("invoices.notesHelp")}>
              <Input maxLength={500} />
            </Form.Item>
          </Col>
        </Row>
      </Form>
    </Modal>
  );
}
