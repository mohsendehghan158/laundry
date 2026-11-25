"use client";

import { App, Button, Card, Col, Descriptions, Form, Input, Row, Space, Tag } from "antd";
import { useTranslations } from "next-intl";
import { applyServerErrors } from "@/shared/api/form-errors";
import { errorMessage } from "@/shared/api/errors";
import type { UpdatePasswordBody } from "@/shared/api/types";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useRoleLabel } from "@/features/roles/labels";
import { useChangePassword, useMe } from "./api";

export function ProfilePage() {
  const t = useTranslations("profile");
  const tu = useTranslations("users");
  const roleLabel = useRoleLabel();
  const { message } = App.useApp();
  const { data: me } = useMe();
  const change = useChangePassword();
  const [form] = Form.useForm<UpdatePasswordBody>();

  if (!me) return null;

  return (
    <>
      <PageHeader title={t("title")} />
      <Row gutter={[16, 16]}>
        <Col xs={24} lg={12}>
          <Card title={t("info")}>
            <Descriptions
              column={1}
              items={[
                { key: "name", label: tu("name"), children: me.name },
                { key: "username", label: tu("username"), children: <span className="ltr">{me.username}</span> },
                { key: "mobile", label: tu("mobile"), children: me.mobile ? <span className="ltr">{me.mobile}</span> : "—" },
                { key: "email", label: tu("email"), children: me.email ? <span className="ltr">{me.email}</span> : "—" },
                {
                  key: "roles",
                  label: tu("roles"),
                  children: (
                    <Space size={4} wrap>
                      {me.roles.map((role) => <Tag key={role}>{roleLabel(role)}</Tag>)}
                    </Space>
                  ),
                },
                { key: "branches", label: tu("branches"), children: me.branches.map((b) => b.name).join("، ") || "—" },
              ]}
            />
          </Card>
        </Col>
        <Col xs={24} lg={12}>
          <Card title={t("changePassword")}>
            <Form<UpdatePasswordBody>
              form={form}
              layout="vertical"
              onFinish={(values) =>
                change.mutate(values, {
                  onSuccess: () => {
                    message.success(t("changed"));
                    form.resetFields();
                  },
                  onError: (error) => {
                    if (!applyServerErrors(form, error)) message.error(errorMessage(error));
                  },
                })
              }
            >
              <Form.Item name="current_password" label={t("currentPassword")} rules={[{ required: true }]}>
                <Input.Password dir="ltr" autoComplete="current-password" />
              </Form.Item>
              <Form.Item name="password" label={t("newPassword")} rules={[{ required: true }, { min: 8, message: t("minLength") }]}>
                <Input.Password dir="ltr" autoComplete="new-password" />
              </Form.Item>
              <Form.Item
                name="password_confirmation"
                label={t("confirmPassword")}
                dependencies={["password"]}
                rules={[
                  { required: true },
                  ({ getFieldValue }) => ({
                    validator: (_, value) =>
                      !value || getFieldValue("password") === value ? Promise.resolve() : Promise.reject(new Error(t("mismatch"))),
                  }),
                ]}
              >
                <Input.Password dir="ltr" autoComplete="new-password" />
              </Form.Item>
              <Button type="primary" htmlType="submit" loading={change.isPending}>
                {t("changePassword")}
              </Button>
            </Form>
          </Card>
        </Col>
      </Row>
    </>
  );
}
