"use client";

import { Alert, App, Button, Card, Flex, Form, Input, InputNumber, Radio, Select, Skeleton, Switch, Tabs } from "antd";
import { useTranslations } from "next-intl";
import { useEffect, useMemo } from "react";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { Settings } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { parseAmount } from "@/shared/lib/money";
import { formatNumber } from "@/shared/lib/digits";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useOrganization, useSettings, useUpdateOrganization, useUpdateSettings } from "../api";

function timeZones(): string[] {
  try {
    return Intl.supportedValuesOf("timeZone");
  } catch {
    return ["Asia/Tehran", "UTC"];
  }
}

function OrganizationForm() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can } = usePermission();
  const [form] = Form.useForm<{ name: string }>();
  const organization = useOrganization();
  const update = useUpdateOrganization();
  const editable = can("organization.update");

  useEffect(() => {
    if (organization.data) {
      form.setFieldsValue({ name: organization.data.name });
    }
  }, [organization.data, form]);

  if (organization.isLoading) {
    return <Skeleton active />;
  }

  return (
    <Form
      form={form}
      layout="vertical"
      disabled={!editable}
      style={{ maxWidth: 480 }}
      onFinish={(values) =>
        update.mutate(values, {
          onSuccess: () => message.success(t("common.saved")),
          onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error"))),
        })
      }
    >
      <Form.Item name="name" label={t("settings.fields.organizationName")} rules={[{ required: true, message: t("common.required") }, { max: 100 }]}>
        <Input />
      </Form.Item>
      {editable && (
        <Button type="primary" htmlType="submit" loading={update.isPending}>
          {t("common.save")}
        </Button>
      )}
    </Form>
  );
}

function SettingsForm({ section }: { section: "general" | "tax" | "receipt" }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can } = usePermission();
  const [form] = Form.useForm<Settings>();
  const settings = useSettings();
  const update = useUpdateSettings();
  const editable = can("settings.update");
  const zones = useMemo(() => timeZones().map((zone) => ({ value: zone, label: zone })), []);

  useEffect(() => {
    if (settings.data) {
      form.setFieldsValue(settings.data);
    }
  }, [settings.data, form]);

  if (settings.isLoading) {
    return <Skeleton active />;
  }

  const onFinish = (values: Settings) =>
    // Only the visible section is sent; the API merges partial updates.
    update.mutate(
      { [section]: values[section] },
      {
        onSuccess: () => message.success(t("common.saved")),
        onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error"))),
      },
    );

  return (
    <Form<Settings> form={form} layout="vertical" disabled={!editable} style={{ maxWidth: 560 }} onFinish={onFinish}>
      {section === "general" && (
        <>
          <Form.Item name={["general", "currency_unit"]} label={t("settings.fields.currencyUnit")} extra={t("settings.fields.currencyUnitHelp")}>
            <Radio.Group
              optionType="button"
              options={[
                { value: "toman", label: t("money.toman") },
                { value: "rial", label: t("money.rial") },
              ]}
            />
          </Form.Item>
          <Form.Item name={["general", "timezone"]} label={t("settings.fields.timezone")} rules={[{ required: true, message: t("common.required") }]}>
            <Select showSearch options={zones} style={{ direction: "ltr" }} />
          </Form.Item>
        </>
      )}
      {section === "tax" && (
        <>
          <Form.Item
            name={["tax", "vat_percent"]}
            label={t("settings.fields.vatPercent")}
            rules={[{ required: true, message: t("common.required") }, { type: "number", min: 0, max: 100 }]}
          >
            <InputNumber<number>
              min={0}
              max={100}
              step={0.5}
              style={{ width: 160 }}
              formatter={(value, info) => (info.userTyping ? info.input : value === undefined || value === null ? "" : formatNumber(Number(value)))}
              parser={(text) => parseAmount(text) ?? 0}
            />
          </Form.Item>
          <Form.Item name={["tax", "prices_include_vat"]} label={t("settings.fields.pricesIncludeVat")} valuePropName="checked">
            <Switch />
          </Form.Item>
        </>
      )}
      {section === "receipt" && (
        <>
          <Form.Item name={["receipt", "header_text"]} label={t("settings.fields.headerText")} rules={[{ max: 500 }]}>
            <Input.TextArea rows={3} showCount maxLength={500} />
          </Form.Item>
          <Form.Item name={["receipt", "footer_text"]} label={t("settings.fields.footerText")} rules={[{ max: 500 }]}>
            <Input.TextArea rows={3} showCount maxLength={500} />
          </Form.Item>
        </>
      )}
      {editable && (
        <Flex>
          <Button type="primary" htmlType="submit" loading={update.isPending}>
            {t("common.save")}
          </Button>
        </Flex>
      )}
    </Form>
  );
}

export function SettingsPage() {
  const t = useTranslations();
  const { can } = usePermission();

  return (
    <>
      <PageHeader title={t("settings.title")} />
      {!can("settings.update") && <Alert type="info" showIcon title={t("settings.readOnly")} style={{ marginBottom: 16 }} />}
      <Card>
        <Tabs
          items={[
            { key: "organization", label: t("settings.tabs.organization"), children: <OrganizationForm /> },
            { key: "general", label: t("settings.tabs.general"), children: <SettingsForm section="general" /> },
            { key: "tax", label: t("settings.tabs.tax"), children: <SettingsForm section="tax" /> },
            { key: "receipt", label: t("settings.tabs.receipt"), children: <SettingsForm section="receipt" /> },
          ]}
        />
      </Card>
    </>
  );
}
