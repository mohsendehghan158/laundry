"use client";

import { LogoutOutlined } from "@ant-design/icons";
import { useQueryClient } from "@tanstack/react-query";
import { Button, Card, Flex, Form, Input, Layout, Spin, theme, Typography } from "antd";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useEffect, type ReactNode } from "react";
import { onUnauthenticated } from "@/shared/api/client";
import { applyFormErrors } from "@/shared/api/formErrors";
import { portalKeys, useCustomer, useCustomerLogout, usePortalSettings, useUpdateName } from "../api";

const WIDTH = 640;

function NameStep() {
  const t = useTranslations("portal.name");
  const [form] = Form.useForm<{ name: string }>();
  const update = useUpdateName();

  return (
    <Card style={{ maxWidth: 400, margin: "32px auto" }} styles={{ body: { padding: 32 } }}>
      <Typography.Title level={3} style={{ marginTop: 0 }}>
        {t("title")}
      </Typography.Title>
      <Typography.Paragraph type="secondary">{t("subtitle")}</Typography.Paragraph>
      <Form
        form={form}
        layout="vertical"
        requiredMark={false}
        size="large"
        onFinish={({ name }) => update.mutate(name.trim(), { onError: (error) => applyFormErrors(form, error) })}
      >
        <Form.Item name="name" label={t("label")} rules={[{ required: true, whitespace: true, max: 100 }]}>
          <Input autoComplete="name" autoFocus />
        </Form.Item>
        <Button type="primary" htmlType="submit" block loading={update.isPending}>
          {t("submit")}
        </Button>
      </Form>
    </Card>
  );
}

/**
 * Frame of the signed-in customer portal: a phone-first single column
 * with the business's name on top. Sends visitors who are not signed in
 * to the portal's own sign-in page, and asks first-time customers for
 * their name before anything else.
 */
export function PortalShell({ children }: { children: ReactNode }) {
  const t = useTranslations("portal");
  const router = useRouter();
  const pathname = usePathname();
  const queryClient = useQueryClient();
  const settings = usePortalSettings();
  const customer = useCustomer();
  const logout = useCustomerLogout();
  const { token } = theme.useToken();

  // A session that expires while the portal is open lands back on sign-in.
  useEffect(() => {
    onUnauthenticated(() => queryClient.setQueryData(portalKeys.customer, null));
    return () => onUnauthenticated(null);
  }, [queryClient]);

  // Signing out navigates by itself, without remembering the page.
  const signingOut = !logout.isIdle;
  useEffect(() => {
    if (customer.data === null && !signingOut) {
      router.replace(pathname === "/my" ? "/my/login" : `/my/login?next=${encodeURIComponent(pathname)}`);
    }
  }, [customer.data, pathname, router, signingOut]);

  if (!customer.data) {
    return (
      <Flex justify="center" align="center" style={{ minHeight: "100vh" }}>
        <Spin size="large" />
      </Flex>
    );
  }

  return (
    <Layout style={{ minHeight: "100vh" }}>
      <Layout.Header style={{ paddingInline: 16, paddingBlock: 12, height: "auto", lineHeight: "normal", borderBottom: `1px solid ${token.colorBorderSecondary}` }}>
        <Flex justify="space-between" align="center" gap={12} style={{ maxWidth: WIDTH, margin: "0 auto" }}>
          <Link href="/my" style={{ color: "inherit", minWidth: 0 }}>
            <Typography.Text strong ellipsis style={{ fontSize: 16, display: "block" }}>
              {settings.data?.organization_name ?? t("title")}
            </Typography.Text>
            {customer.data.name && (
              <Typography.Text type="secondary" ellipsis style={{ fontSize: 13, display: "block" }}>
                {customer.data.name}
              </Typography.Text>
            )}
          </Link>
          <Button
            icon={<LogoutOutlined />}
            loading={logout.isPending}
            onClick={() => logout.mutate(undefined, { onSettled: () => router.replace("/my/login") })}
          >
            {t("nav.logout")}
          </Button>
        </Flex>
      </Layout.Header>
      <Layout.Content style={{ padding: 16 }}>
        <div style={{ maxWidth: WIDTH, margin: "0 auto" }}>{customer.data.needs_name ? <NameStep /> : children}</div>
      </Layout.Content>
    </Layout>
  );
}
