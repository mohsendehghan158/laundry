"use client";

import { App, Col, Form, Input, InputNumber, Modal, Row, Select, Switch } from "antd";
import { useTranslations } from "next-intl";
import { useEffect } from "react";
import { usePriceLists } from "@/features/catalog/api";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { Company } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { dayjs, type Dayjs } from "@/shared/lib/date";
import { inputNumberFormatter, toLatinDigits } from "@/shared/lib/digits";
import { parseAmount } from "@/shared/lib/money";
import { JalaliRangePicker } from "@/shared/ui/JalaliDatePicker";
import { MoneyInput } from "@/shared/ui/MoneyInput";
import { useSaveCompany } from "../api";

type Values = {
  name: string;
  national_id?: string | null;
  economic_code?: string | null;
  registration_number?: string | null;
  postal_code?: string | null;
  phone?: string | null;
  address?: string | null;
  contact_name?: string | null;
  contact_mobile?: string | null;
  contract_number?: string | null;
  contract?: [Dayjs | null, Dayjs | null] | null;
  price_list_id?: number | null;
  on_account: boolean;
  credit_limit?: number | null;
  payment_terms_days?: number | null;
  notes?: string | null;
  is_active: boolean;
};

const digits = (value: string | undefined) => toLatinDigits(value ?? "").replace(/\D/g, "") || null;
const DATE = "YYYY-MM-DD";

/** Creates or edits a company and its contract. */
export function CompanyFormModal({ company, open, onClose, onSaved }: { company: Company | null; open: boolean; onClose: () => void; onSaved?: (company: Company) => void }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const unit = useCurrencyUnit();
  const [form] = Form.useForm<Values>();
  const save = useSaveCompany();
  const lists = usePriceLists(open);
  const onAccount = Form.useWatch("on_account", form);

  useEffect(() => {
    if (!open) {
      return;
    }
    form.resetFields();
    form.setFieldsValue(
      company
        ? {
            ...company,
            price_list_id: company.price_list?.id ?? null,
            on_account: company.credit_limit !== null,
            contract: company.contract_starts_on || company.contract_ends_on ? [company.contract_starts_on ? dayjs(company.contract_starts_on) : null, company.contract_ends_on ? dayjs(company.contract_ends_on) : null] : null,
          }
        : { on_account: true, payment_terms_days: 30, is_active: true },
    );
  }, [open, company, form]);

  const onFinish = ({ contract, on_account, ...values }: Values) =>
    save.mutate(
      {
        id: company?.id,
        input: {
          ...values,
          credit_limit: on_account ? (values.credit_limit ?? 0) : null,
          payment_terms_days: values.payment_terms_days ?? 0,
          contract_starts_on: contract?.[0] ? contract[0].format(DATE) : null,
          contract_ends_on: contract?.[1] ? contract[1].format(DATE) : null,
        },
      },
      {
        onSuccess: (saved) => {
          message.success(company ? t("common.saved") : t("common.created"));
          onClose();
          onSaved?.(saved);
        },
        onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error"))),
      },
    );

  return (
    <Modal
      open={open}
      width={760}
      title={company ? t("companies.edit") : t("companies.new")}
      okText={t("common.save")}
      cancelText={t("common.cancel")}
      onCancel={onClose}
      onOk={() => form.submit()}
      confirmLoading={save.isPending}
      forceRender
    >
      <Form<Values> form={form} layout="vertical" onFinish={onFinish} name="company">
        <Row gutter={16}>
          <Col xs={24} sm={12}>
            <Form.Item name="name" label={t("companies.fields.name")} rules={[{ required: true, message: t("common.required") }]}>
              <Input maxLength={150} />
            </Form.Item>
          </Col>
          <Col xs={12} sm={6}>
            <Form.Item name="national_id" label={t("invoices.fields.legalId")} normalize={digits} rules={[{ pattern: /^\d{11}$/, message: t("invoices.legalIdLength") }]}>
              <Input dir="ltr" inputMode="numeric" maxLength={11} />
            </Form.Item>
          </Col>
          <Col xs={12} sm={6}>
            <Form.Item name="economic_code" label={t("invoices.fields.economicCode")} normalize={digits} rules={[{ pattern: /^\d{10,14}$/, message: t("invoices.economicCodeLength") }]}>
              <Input dir="ltr" inputMode="numeric" maxLength={14} />
            </Form.Item>
          </Col>
          <Col xs={12} sm={6}>
            <Form.Item name="registration_number" label={t("invoices.fields.registrationNumber")} normalize={digits}>
              <Input dir="ltr" inputMode="numeric" maxLength={20} />
            </Form.Item>
          </Col>
          <Col xs={12} sm={6}>
            <Form.Item name="postal_code" label={t("invoices.fields.postalCode")} normalize={digits} rules={[{ pattern: /^\d{10}$/, message: t("invoices.postalCodeLength") }]}>
              <Input dir="ltr" inputMode="numeric" maxLength={10} />
            </Form.Item>
          </Col>
          <Col xs={12} sm={6}>
            <Form.Item name="phone" label={t("invoices.fields.phone")} normalize={(v: string) => toLatinDigits(v ?? "") || null}>
              <Input dir="ltr" maxLength={20} />
            </Form.Item>
          </Col>
          <Col xs={24}>
            <Form.Item name="address" label={t("invoices.fields.address")}>
              <Input maxLength={500} />
            </Form.Item>
          </Col>
          <Col xs={12}>
            <Form.Item name="contact_name" label={t("companies.fields.contactName")}>
              <Input maxLength={100} />
            </Form.Item>
          </Col>
          <Col xs={12}>
            <Form.Item name="contact_mobile" label={t("companies.fields.contactMobile")} normalize={digits} rules={[{ pattern: /^09\d{9}$/, message: t("companies.mobileInvalid") }]}>
              <Input dir="ltr" inputMode="numeric" maxLength={11} />
            </Form.Item>
          </Col>
          <Col xs={24}>
            <h4 style={{ margin: "4px 0 12px" }}>{t("companies.contract")}</h4>
          </Col>
          <Col xs={12} sm={8}>
            <Form.Item name="contract_number" label={t("companies.fields.contractNumber")}>
              <Input maxLength={50} />
            </Form.Item>
          </Col>
          <Col xs={12} sm={16}>
            <Form.Item name="contract" label={t("companies.fields.contractPeriod")}>
              <JalaliRangePicker allowEmpty={[true, true]} format="YYYY/MM/DD" style={{ width: "100%" }} />
            </Form.Item>
          </Col>
          <Col xs={24} sm={12}>
            <Form.Item name="price_list_id" label={t("companies.fields.priceList")} extra={t("companies.priceListHelp")}>
              <Select
                allowClear
                placeholder={t("catalog.priceLists.default")}
                options={(lists.data ?? []).filter((list) => !list.is_default).map((list) => ({ value: list.id, label: list.name }))}
              />
            </Form.Item>
          </Col>
          <Col xs={12} sm={6}>
            <Form.Item name="on_account" label={t("companies.fields.onAccount")} valuePropName="checked">
              <Switch />
            </Form.Item>
          </Col>
          <Col xs={12} sm={6}>
            <Form.Item name="payment_terms_days" label={t("companies.fields.paymentTerms")}>
              <InputNumber<number> min={0} max={365} formatter={inputNumberFormatter} parser={(text) => parseAmount(text) ?? 0} style={{ width: "100%" }} />
            </Form.Item>
          </Col>
          {onAccount && (
            <Col xs={24} sm={12}>
              <Form.Item name="credit_limit" label={t("companies.fields.creditLimit")} extra={t("companies.creditHelp")} rules={[{ required: true, message: t("common.required") }]}>
                <MoneyInput unit={unit} />
              </Form.Item>
            </Col>
          )}
          <Col xs={24}>
            <Form.Item name="notes" label={t("companies.fields.notes")}>
              <Input.TextArea rows={2} maxLength={2000} />
            </Form.Item>
          </Col>
          <Col xs={24}>
            <Form.Item name="is_active" label={t("common.active")} valuePropName="checked">
              <Switch />
            </Form.Item>
          </Col>
        </Row>
      </Form>
    </Modal>
  );
}
