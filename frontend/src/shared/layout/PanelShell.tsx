"use client";

import { LogoutOutlined, MenuOutlined, UserOutlined } from "@ant-design/icons";
import { useQueryClient } from "@tanstack/react-query";
import { App, Avatar, Button, Dropdown, Grid, Layout, Menu, Result, Space, Spin, Typography, theme, type MenuProps } from "antd";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useEffect, useMemo, useState, type ReactNode } from "react";
import { meKey, useLogout, useMe } from "@/features/auth/api";
import { onUnauthenticated } from "@/shared/api/client";
import { usePermission } from "@/shared/hooks/usePermission";
import { activeNavItem, visibleNav } from "./nav";

const { Header, Sider, Content } = Layout;

type MenuItem = NonNullable<MenuProps["items"]>[number];

function FullPage({ children }: { children: ReactNode }) {
  return <div style={{ minHeight: "100vh", display: "grid", placeItems: "center" }}>{children}</div>;
}

export function PanelShell({ children }: { children: ReactNode }) {
  const t = useTranslations();
  const router = useRouter();
  const pathname = usePathname();
  const queryClient = useQueryClient();
  const { message } = App.useApp();
  const { token } = theme.useToken();
  const screens = Grid.useBreakpoint();
  const isDesktop = screens.lg ?? true;
  const [collapsed, setCollapsed] = useState(false);

  const me = useMe();
  const logout = useLogout();
  const { can } = usePermission();

  // Any 401 from the API means the session is gone: drop the cached user,
  // which sends the effect below to the login page.
  useEffect(() => {
    onUnauthenticated(() => queryClient.setQueryData(meKey, null));
    return () => onUnauthenticated(null);
  }, [queryClient]);

  useEffect(() => {
    if (me.data === null) {
      const next = pathname && pathname !== "/dashboard" ? `?next=${encodeURIComponent(pathname)}` : "";
      router.replace(`/login${next}`);
    }
  }, [me.data, pathname, router]);

  const menuItems = useMemo(
    () =>
      visibleNav(can).flatMap((group, index): MenuItem[] => {
        const items = group.items.map((item) => ({
          key: item.href,
          icon: item.icon,
          label: <Link href={item.href}>{t(`nav.${item.label}`)}</Link>,
        }));
        return group.label ? [{ type: "group" as const, key: `group-${index}`, label: t(`nav.${group.label}`), children: items }] : items;
      }),
    [can, t],
  );

  if (me.isError) {
    return (
      <FullPage>
        <Result
          status="error"
          title={t("common.error")}
          extra={<Button onClick={() => me.refetch()}>{t("common.retry")}</Button>}
        />
      </FullPage>
    );
  }

  if (!me.data) {
    return (
      <FullPage>
        <Spin size="large" />
      </FullPage>
    );
  }

  const user = me.data;
  const selected = activeNavItem(pathname ?? "")?.href;

  const handleLogout = () =>
    logout.mutate(undefined, {
      onSuccess: () => router.replace("/login"),
      onError: () => message.error(t("common.error")),
    });

  return (
    <Layout style={{ minHeight: "100vh" }}>
      <Sider
        width={240}
        breakpoint="lg"
        collapsedWidth={0}
        collapsed={isDesktop ? false : collapsed}
        onCollapse={setCollapsed}
        trigger={null}
        style={{ borderInlineStart: `1px solid ${token.colorBorderSecondary}`, ...(isDesktop ? {} : { position: "fixed", insetBlock: 0, insetInlineStart: 0, zIndex: 20 }) }}
      >
        <div style={{ height: 64, display: "flex", alignItems: "center", paddingInline: 24 }}>
          <Typography.Text strong style={{ fontSize: 16 }}>
            {t("app.shortName")}
          </Typography.Text>
        </div>
        <Menu
          mode="inline"
          selectedKeys={selected ? [selected] : []}
          items={menuItems}
          style={{ borderInlineEnd: 0 }}
          onClick={() => !isDesktop && setCollapsed(true)}
        />
      </Sider>
      <Layout>
        <Header
          style={{
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            gap: 16,
            height: 64,
            lineHeight: "normal",
            paddingInline: isDesktop ? 24 : 16,
            borderBottom: `1px solid ${token.colorBorderSecondary}`,
          }}
        >
          <Space>
            {!isDesktop && (
              <Button type="text" icon={<MenuOutlined />} aria-label={t("nav.openMenu")} onClick={() => setCollapsed((value) => !value)} />
            )}
            <Typography.Text type="secondary" ellipsis>
              {user.organization.name}
            </Typography.Text>
          </Space>
          <Dropdown
            trigger={["click"]}
            menu={{
              items: [
                { key: "account", icon: <UserOutlined />, label: <Link href="/account">{t("nav.account")}</Link> },
                { type: "divider" },
                { key: "logout", icon: <LogoutOutlined />, danger: true, label: t("auth.logout"), onClick: handleLogout },
              ],
            }}
          >
            <Button type="text" style={{ height: 48 }} data-testid="user-menu">
              <Space>
                <Avatar size="small" icon={<UserOutlined />} />
                {isDesktop && <span>{user.name}</span>}
              </Space>
            </Button>
          </Dropdown>
        </Header>
        <Content style={{ padding: isDesktop ? 24 : 16 }}>{children}</Content>
      </Layout>
      {!isDesktop && !collapsed && (
        <div
          aria-hidden
          onClick={() => setCollapsed(true)}
          style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.35)", zIndex: 10 }}
        />
      )}
    </Layout>
  );
}
