"use client";

import { App, Button, Card, Col, Form, Input, InputNumber, Row, Select, Switch } from "antd";
import { useTranslations } from "next-intl";
import { applyServerErrors } from "@/shared/api/form-errors";
import { errorMessage } from "@/shared/api/errors";
import type { Settings } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { PageHeader } from "@/shared/ui/PageHeader";
import { QueryState } from "@/shared/ui/QueryState";
import { useMe } from "@/features/auth/api";
import { useSettings, useUpdateOrganization, useUpdateSettings } from "./api";

const TIMEZONES = ["Asia/Tehran", "Asia/Dubai", "Asia/Kabul", "Europe/Istanbul", "UTC"];

export function SettingsPage() {
  const t = useTranslations("settings");
  const tc = useTranslations("common");
  const { message } = App.useApp();
  const me = useMe();
  const canViewSettings = usePermission("settings.view");
  const canUpdateSettings = usePermission("settings.update");
  const canUpdateOrganization = usePermission("organization.update");
  const settings = useSettings(canViewSettings);
  const updateSettings = useUpdateSettings();
  const updateOrganization = useUpdateOrganization();
  const [orgForm] = Form.useForm<{ name: string }>();
  const [settingsForm] = Form.useForm<Settings>();

  return (
    <>
      <PageHeader title={t("title")} />
      <Row gutter={[16, 16]}>
        {canUpdateOrganization ? (
          <Col xs={24}>
            <Card title={t("organization")}>
              <Form
                form={orgForm}
                layout="vertical"
                initialValues={{ name: me.data?.organization.name }}
                onFinish={(values) =>
                  updateOrganization.mutate(values, {
                    onSuccess: () => message.success(tc("saved")),
                    onError: (error) => {
                      if (!applyServerErrors(orgForm, error)) message.error(errorMessage(error));
                    },
                  })
                }
              >
                <Form.Item name="name" label={t("orgName")} rules={[{ required: true, max: 100 }]} style={{ maxWidth: 420 }}>
                  <Input />
                </Form.Item>
                <Button type="primary" htmlType="submit" loading={updateOrganization.isPending}>
                  {tc("save")}
                </Button>
              </Form>
            </Card>
          </Col>
        ) : null}
        {canViewSettings ? (
          <Col xs={24}>
            <QueryState isLoading={settings.isLoading} error={settings.error} onRetry={() => settings.refetch()}>
              {settings.data ? (
                <Form<Settings>
                  form={settingsForm}
                  layout="vertical"
                  disabled={!canUpdateSettings}
                  initialValues={settings.data}
                  onFinish={(values) =>
                    updateSettings.mutate(values, {
                      onSuccess: () => message.success(tc("saved")),
                      onError: (error) => {
                        if (!applyServerErrors(settingsForm, error)) message.error(errorMessage(error));
                      },
                    })
                  }
                >
                  <Row gutter={[16, 16]}>
                    <Col xs={24} lg={12}>
                      <Card title={t("general")} style={{ height: "100%" }}>
                        <Form.Item name={["general", "currency_unit"]} label={t("currencyUnit")} extra={t("currencyHelp")}>
                          <Select options={[{ value: "toman", label: t("toman") }, { value: "rial", label: t("rial") }]} />
                        </Form.Item>
                        <Form.Item name={["general", "timezone"]} label={t("timezone")}>
                          <Select options={TIMEZONES.map((zone) => ({ value: zone, label: <span className="ltr">{zone}</span> }))} />
                        </Form.Item>
                      </Card>
                    </Col>
                    <Col xs={24} lg={12}>
                      <Card title={t("tax")} style={{ height: "100%" }}>
                        <Form.Item name={["tax", "vat_percent"]} label={t("vat")} rules={[{ type: "number", min: 0, max: 100 }]}>
                          <InputNumber min={0} max={100} step={0.5} suffix="٪" style={{ width: "100%" }} />
                        </Form.Item>
                        <Form.Item name={["tax", "prices_include_vat"]} label={t("pricesIncludeVat")} valuePropName="checked">
                          <Switch />
                        </Form.Item>
                      </Card>
                    </Col>
                    <Col xs={24}>
                      <Card title={t("receipt")}>
                        <Row gutter={16}>
                          <Col xs={24} md={12}>
                            <Form.Item name={["receipt", "header_text"]} label={t("header")} rules={[{ max: 500 }]}>
                              <Input.TextArea rows={3} />
                            </Form.Item>
                          </Col>
                          <Col xs={24} md={12}>
                            <Form.Item name={["receipt", "footer_text"]} label={t("footer")} rules={[{ max: 500 }]}>
                              <Input.TextArea rows={3} />
                            </Form.Item>
                          </Col>
                        </Row>
                      </Card>
                    </Col>
                  </Row>
                  {canUpdateSettings ? (
                    <Button type="primary" htmlType="submit" loading={updateSettings.isPending} style={{ marginTop: 16 }}>
                      {tc("save")}
                    </Button>
                  ) : null}
                </Form>
              ) : null}
            </QueryState>
          </Col>
        ) : null}
      </Row>
    </>
  );
}
