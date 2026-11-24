import {
  ApartmentOutlined,
  DashboardOutlined,
  HistoryOutlined,
  SafetyCertificateOutlined,
  SettingOutlined,
  TeamOutlined,
} from "@ant-design/icons";
import type { ReactNode } from "react";
import type { Permission } from "@/shared/api/types";

export type NavItem = {
  href: string;
  /** Key under the "nav" messages. */
  label: "dashboard" | "branches" | "users" | "roles" | "settings" | "activity";
  icon: ReactNode;
  /** Shown only to users holding this permission. */
  permission?: Permission;
};

export type NavGroup = { label?: "management"; items: NavItem[] };

export const NAV: NavGroup[] = [
  { items: [{ href: "/dashboard", label: "dashboard", icon: <DashboardOutlined /> }] },
  {
    label: "management",
    items: [
      { href: "/branches", label: "branches", icon: <ApartmentOutlined />, permission: "branches.view" },
      { href: "/users", label: "users", icon: <TeamOutlined />, permission: "users.view" },
      { href: "/roles", label: "roles", icon: <SafetyCertificateOutlined />, permission: "roles.view" },
      { href: "/settings", label: "settings", icon: <SettingOutlined />, permission: "settings.view" },
      { href: "/activity", label: "activity", icon: <HistoryOutlined />, permission: "activity.view" },
    ],
  },
];

/** The navigation entries the user may open, groups without entries dropped. */
export function visibleNav(can: (permission?: Permission) => boolean): NavGroup[] {
  return NAV.map((group) => ({ ...group, items: group.items.filter((item) => can(item.permission)) })).filter(
    (group) => group.items.length > 0,
  );
}

/** The nav entry that owns a pathname, e.g. "/users/5" → users. */
export function activeNavItem(pathname: string): NavItem | undefined {
  return NAV.flatMap((group) => group.items).find(
    (item) => pathname === item.href || pathname.startsWith(`${item.href}/`),
  );
}
