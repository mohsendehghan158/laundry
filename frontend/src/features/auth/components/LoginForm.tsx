"use client";

import { LockOutlined, UserOutlined } from "@ant-design/icons";
import { Alert, Button, Card, Checkbox, Form, Input, Typography } from "antd";
import { useRouter, useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";
import { isApiError } from "@/shared/api/client";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { LoginInput } from "@/shared/api/types";
import { toLatinDigits } from "@/shared/lib/digits";
import { useLogin, useMe } from "../api";
import { safeNext } from "../safeNext";

export function LoginForm() {
  const t = useTranslations();
  const router = useRouter();
  const next = safeNext(useSearchParams().get("next"));
  const [form] = Form.useForm<LoginInput>();
  const [formError, setFormError] = useState<string | null>(null);
  const me = useMe();
  const login = useLogin();

  // Already signed in (e.g. opened /login in a second tab).
  useEffect(() => {
    if (me.data) {
      router.replace(next);
    }
  }, [me.data, next, router]);

  const onFinish = (values: LoginInput) => {
    setFormError(null);
    login.mutate(
      { ...values, username: toLatinDigits(values.username.trim()) },
      {
        onSuccess: () => router.replace(next),
        onError: (error) => {
          // Wrong credentials and throttling come back as errors on "username";
          // show them above the form rather than under one field.
          if (isApiError(error, 422) || isApiError(error, 429)) {
            const messages = Object.values(isApiError(error) ? error.errors : {}).flat();
            if (messages.length > 0) {
              setFormError(messages[0]);
              return;
            }
          }
          if (!applyFormErrors(form, error)) {
            setFormError(errorMessage(error, t("common.error")));
          }
        },
      },
    );
  };

  return (
    <Card style={{ width: "100%", maxWidth: 400 }} styles={{ body: { padding: 32 } }}>
      <Typography.Title level={3} style={{ marginTop: 0 }}>
        {t("auth.title")}
      </Typography.Title>
      <Typography.Paragraph type="secondary">{t("auth.subtitle")}</Typography.Paragraph>

      {formError && <Alert type="error" title={formError} showIcon style={{ marginBottom: 16 }} role="alert" />}

      <Form<LoginInput> form={form} layout="vertical" requiredMark={false} onFinish={onFinish} initialValues={{ remember: true }} size="large">
        <Form.Item name="username" label={t("auth.username")} rules={[{ required: true, message: t("auth.usernameRequired") }]}>
          <Input prefix={<UserOutlined />} autoComplete="username" autoFocus dir="ltr" />
        </Form.Item>
        <Form.Item name="password" label={t("auth.password")} rules={[{ required: true, message: t("auth.passwordRequired") }]}>
          <Input.Password prefix={<LockOutlined />} autoComplete="current-password" dir="ltr" />
        </Form.Item>
        <Form.Item name="remember" valuePropName="checked">
          <Checkbox>{t("auth.remember")}</Checkbox>
        </Form.Item>
        <Button type="primary" htmlType="submit" block loading={login.isPending}>
          {t("auth.submit")}
        </Button>
      </Form>
    </Card>
  );
}
