"use client";

import { LockOutlined, UserOutlined } from "@ant-design/icons";
import { Alert, Button, Card, Checkbox, Flex, Form, Input, Typography } from "antd";
import { useTranslations } from "next-intl";
import { useRouter, useSearchParams } from "next/navigation";
import { applyServerErrors } from "@/shared/api/form-errors";
import { errorMessage, isApiError } from "@/shared/api/errors";
import { toLatinDigits } from "@/shared/lib/digits";
import { useLogin } from "./api";

interface LoginValues {
  username: string;
  password: string;
  remember: boolean;
}

/** Only same-site paths are followed after login, never another origin. */
export function safeNext(next: string | null): string {
  if (!next || !next.startsWith("/") || next.startsWith("//") || next.startsWith("/login")) return "/dashboard";
  return next;
}

export function LoginForm() {
  const t = useTranslations("auth");
  const tApp = useTranslations("app");
  const [form] = Form.useForm<LoginValues>();
  const login = useLogin();
  const router = useRouter();
  const searchParams = useSearchParams();

  const onFinish = (values: LoginValues) => {
    login.mutate(
      { username: toLatinDigits(values.username.trim()).toLowerCase(), password: values.password, remember: values.remember },
      {
        onSuccess: () => router.replace(safeNext(searchParams.get("next"))),
        onError: (error) => applyServerErrors(form, error),
      },
    );
  };

  // Wrong credentials and lockouts arrive as field errors and are shown on the fields.
  const hasFieldErrors = isApiError(login.error) && Object.keys(login.error.errors).length > 0;
  const generalError = login.error && !hasFieldErrors ? errorMessage(login.error) : null;

  return (
    <Flex align="center" justify="center" style={{ minHeight: "100vh", padding: 16 }}>
      <Card style={{ width: "100%", maxWidth: 400 }}>
        <Flex vertical gap={4} style={{ marginBottom: 24 }}>
          <Typography.Title level={3} style={{ margin: 0 }}>
            {tApp("name")}
          </Typography.Title>
          <Typography.Text type="secondary">{t("subtitle")}</Typography.Text>
        </Flex>
        {generalError ? <Alert type="error" title={generalError} showIcon style={{ marginBottom: 16 }} /> : null}
        <Form<LoginValues> form={form} layout="vertical" onFinish={onFinish} initialValues={{ remember: true }} requiredMark={false}>
          <Form.Item name="username" label={t("username")} rules={[{ required: true, message: t("required") }]}>
            <Input prefix={<UserOutlined />} autoComplete="username" autoFocus dir="ltr" size="large" />
          </Form.Item>
          <Form.Item name="password" label={t("password")} rules={[{ required: true, message: t("required") }]}>
            <Input.Password prefix={<LockOutlined />} autoComplete="current-password" dir="ltr" size="large" />
          </Form.Item>
          <Form.Item name="remember" valuePropName="checked">
            <Checkbox>{t("remember")}</Checkbox>
          </Form.Item>
          <Button type="primary" htmlType="submit" block size="large" loading={login.isPending}>
            {t("submit")}
          </Button>
        </Form>
      </Card>
    </Flex>
  );
}
