"use client";

import { LogoutOutlined, MenuOutlined, MoonOutlined, SunOutlined, UserOutlined } from "@ant-design/icons";
import { Avatar, Button, Drawer, Dropdown, Flex, Grid, Layout, Menu, Result, Spin, Typography, theme } from "antd";
import type { MenuProps } from "antd";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useMemo, useState, type ReactNode } from "react";
import { setUnauthenticatedHandler } from "@/shared/api/client";
import { isApiError } from "@/shared/api/errors";
import { useThemeMode } from "@/theme/ThemeModeProvider";
import { useLogout, useMe } from "@/features/auth/api";
import { navItemFor, visibleNavItems } from "./navigation";

const SIDER_WIDTH = 232;

export function PanelShell({ children }: { children: ReactNode }) {
  const t = useTranslations("nav");
  const tApp = useTranslations("app");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const pathname = usePathname();
  const screens = Grid.useBreakpoint();
  const { token } = theme.useToken();
  const { mode, toggle } = useThemeMode();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const me = useMe();
  const logout = useLogout();

  const toLogin = useMemo(
    () => () => router.replace(`/login?next=${encodeURIComponent(window.location.pathname + window.location.search)}`),
    [router],
  );

  // Any 401 from the API (expired session) sends the person back to the login page.
  useEffect(() => {
    setUnauthenticatedHandler(toLogin);
    return () => setUnauthenticatedHandler(null);
  }, [toLogin]);

  useEffect(() => {
    if (isApiError(me.error) && me.error.status === 401) toLogin();
  }, [me.error, toLogin]);

  const items = useMemo(() => visibleNavItems(me.data?.permissions ?? []), [me.data?.permissions]);

  if (me.isLoading || (isApiError(me.error) && me.error.status === 401)) {
    return (
      <Flex align="center" justify="center" style={{ minHeight: "100vh" }}>
        <Spin size="large" description={tApp("loading")} />
      </Flex>
    );
  }

  if (me.error || !me.data) {
    return (
      <Result
        status="error"
        title={tCommon("error")}
        extra={<Button onClick={() => me.refetch()}>{tCommon("retry")}</Button>}
      />
    );
  }

  const current = navItemFor(pathname);
  const allowed = !current || items.includes(current);

  const menuItems: MenuProps["items"] = [
    ...items.filter((item) => !item.group).map((item) => ({ key: item.href, icon: item.icon, label: <Link href={item.href}>{t(item.label)}</Link> })),
    ...(items.some((item) => item.group === "management")
      ? [
          {
            type: "group" as const,
            key: "management",
            label: t("management"),
            children: items
              .filter((item) => item.group === "management")
              .map((item) => ({ key: item.href, icon: item.icon, label: <Link href={item.href}>{t(item.label)}</Link> })),
          },
        ]
      : []),
  ];

  const menu = (
    <Menu
      mode="inline"
      selectedKeys={current ? [current.href] : []}
      items={menuItems}
      onClick={() => setDrawerOpen(false)}
      style={{ borderInlineEnd: "none" }}
    />
  );

  const userMenu: MenuProps["items"] = [
    { key: "profile", icon: <UserOutlined />, label: <Link href="/profile">{t("profile")}</Link> },
    { type: "divider" },
    {
      key: "logout",
      icon: <LogoutOutlined />,
      danger: true,
      label: t("logout"),
      onClick: () => logout.mutate(undefined, { onSettled: () => router.replace("/login") }),
    },
  ];

  const isDesktop = screens.lg ?? true;
  const brand = (
    <Typography.Text strong style={{ fontSize: 16, display: "block", padding: "16px 20px" }}>
      {me.data.organization.name}
    </Typography.Text>
  );

  return (
    <Layout style={{ minHeight: "100vh" }}>
      {isDesktop ? (
        <Layout.Sider width={SIDER_WIDTH} style={{ borderInlineEnd: `1px solid ${token.colorBorderSecondary}` }} className="no-print">
          <div style={{ position: "sticky", top: 0 }}>
            {brand}
            {menu}
          </div>
        </Layout.Sider>
      ) : (
        <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} placement="right" size={SIDER_WIDTH} styles={{ body: { padding: 0 } }} title={me.data.organization.name}>
          {menu}
        </Drawer>
      )}
      <Layout>
        <Layout.Header
          className="no-print"
          style={{ display: "flex", alignItems: "center", gap: 8, borderBottom: `1px solid ${token.colorBorderSecondary}`, position: "sticky", top: 0, zIndex: 10 }}
        >
          {!isDesktop ? <Button type="text" icon={<MenuOutlined />} aria-label="menu" onClick={() => setDrawerOpen(true)} /> : null}
          <Typography.Text type="secondary" style={{ flex: 1 }} ellipsis>
            {!isDesktop ? me.data.organization.name : null}
          </Typography.Text>
          <Button
            type="text"
            icon={mode === "dark" ? <SunOutlined /> : <MoonOutlined />}
            onClick={toggle}
            aria-label={t("theme")}
            title={t("theme")}
          />
          <Dropdown menu={{ items: userMenu }} trigger={["click"]} placement="bottomLeft">
            <Button type="text" style={{ height: 40 }}>
              <Avatar size="small" icon={<UserOutlined />} style={{ backgroundColor: token.colorPrimary }} />
              <span>{me.data.name}</span>
            </Button>
          </Dropdown>
        </Layout.Header>
        <Layout.Content style={{ padding: isDesktop ? 24 : 16, maxWidth: 1400, width: "100%", margin: "0 auto" }}>
          {allowed ? children : <Result status="403" title={tCommon("noAccess")} extra={<Link href="/dashboard"><Button type="primary">{tCommon("home")}</Button></Link>} />}
        </Layout.Content>
      </Layout>
    </Layout>
  );
}
