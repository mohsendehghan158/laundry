import {
  ApartmentOutlined,
  AuditOutlined,
  DashboardOutlined,
  SafetyCertificateOutlined,
  SettingOutlined,
  TeamOutlined,
} from "@ant-design/icons";
import type { ReactNode } from "react";

export interface NavItem {
  href: string;
  /** Key under "nav" in the translation file. */
  label: string;
  icon: ReactNode;
  /** Shown when the user holds any of these; empty means everyone. */
  permissions: string[];
  group?: "management";
}

/** The panel menu. Each phase adds its entries here (POS, orders, production …). */
export const NAV_ITEMS: NavItem[] = [
  { href: "/dashboard", label: "dashboard", icon: <DashboardOutlined />, permissions: [] },
  { href: "/branches", label: "branches", icon: <ApartmentOutlined />, permissions: ["branches.view"], group: "management" },
  { href: "/users", label: "users", icon: <TeamOutlined />, permissions: ["users.view"], group: "management" },
  { href: "/roles", label: "roles", icon: <SafetyCertificateOutlined />, permissions: ["roles.view"], group: "management" },
  { href: "/activity", label: "activity", icon: <AuditOutlined />, permissions: ["activity.view"], group: "management" },
  { href: "/settings", label: "settings", icon: <SettingOutlined />, permissions: ["settings.view", "organization.update"], group: "management" },
];

export function visibleNavItems(permissions: string[]): NavItem[] {
  return NAV_ITEMS.filter((item) => item.permissions.length === 0 || item.permissions.some((p) => permissions.includes(p)));
}

/** The menu entry a path belongs to, for highlighting and access checks. */
export function navItemFor(pathname: string): NavItem | undefined {
  return NAV_ITEMS.find((item) => pathname === item.href || pathname.startsWith(`${item.href}/`));
}
