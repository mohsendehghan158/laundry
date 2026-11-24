"use client";

import { App, Button, Card, Col, Descriptions, Form, Input, Row, Tag } from "antd";
import { useTranslations } from "next-intl";
import { useChangePassword } from "@/features/auth/api";
import { useRoleLabel } from "@/features/roles/useRoleLabel";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { UpdatePasswordInput } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { PageHeader } from "@/shared/ui/PageHeader";

export function AccountPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { me } = usePermission();
  const roleLabel = useRoleLabel();
  const [form] = Form.useForm<UpdatePasswordInput>();
  const change = useChangePassword();

  if (!me) {
    return null;
  }

  const onFinish = (values: UpdatePasswordInput) =>
    change.mutate(values, {
      onSuccess: () => {
        form.resetFields();
        message.success(t("account.changed"));
      },
      onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error"))),
    });

  return (
    <>
      <PageHeader title={t("account.title")} />
      <Row gutter={[16, 16]}>
        <Col xs={24} lg={12}>
          <Card title={t("account.profile")}>
            <Descriptions column={1} size="small">
              <Descriptions.Item label={t("users.fields.name")}>{me.name}</Descriptions.Item>
              <Descriptions.Item label={t("users.fields.username")}>
                <span className="ltr">{me.username}</span>
              </Descriptions.Item>
              {me.mobile && (
                <Descriptions.Item label={t("users.fields.mobile")}>
                  <span className="ltr">{me.mobile}</span>
                </Descriptions.Item>
              )}
              {me.email && (
                <Descriptions.Item label={t("users.fields.email")}>
                  <span className="ltr">{me.email}</span>
                </Descriptions.Item>
              )}
              <Descriptions.Item label={t("users.fields.roles")}>
                {me.roles.map((role) => (
                  <Tag key={role}>{roleLabel(role)}</Tag>
                ))}
              </Descriptions.Item>
            </Descriptions>
          </Card>
        </Col>
        <Col xs={24} lg={12}>
          <Card title={t("account.changePassword")}>
            <Form<UpdatePasswordInput> form={form} layout="vertical" onFinish={onFinish} name="password">
              <Form.Item name="current_password" label={t("account.currentPassword")} rules={[{ required: true, message: t("common.required") }]}>
                <Input.Password dir="ltr" autoComplete="current-password" />
              </Form.Item>
              <Form.Item name="password" label={t("account.newPassword")} rules={[{ required: true, message: t("common.required") }]}>
                <Input.Password dir="ltr" autoComplete="new-password" />
              </Form.Item>
              <Form.Item
                name="password_confirmation"
                label={t("account.confirmPassword")}
                dependencies={["password"]}
                rules={[
                  { required: true, message: t("common.required") },
                  ({ getFieldValue }) => ({
                    validator: (_rule, value) =>
                      !value || getFieldValue("password") === value ? Promise.resolve() : Promise.reject(new Error(t("account.mismatch"))),
                  }),
                ]}
              >
                <Input.Password dir="ltr" autoComplete="new-password" />
              </Form.Item>
              <Button type="primary" htmlType="submit" loading={change.isPending}>
                {t("account.changePassword")}
              </Button>
            </Form>
          </Card>
        </Col>
      </Row>
    </>
  );
}
