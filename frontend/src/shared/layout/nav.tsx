import {
  ApartmentOutlined,
  ContactsOutlined,
  FileTextOutlined,
  GiftOutlined,
  LineChartOutlined,
  MessageOutlined,
  ExportOutlined,
  WalletOutlined,
  ShopOutlined,
  DashboardOutlined,
  HistoryOutlined,
  SafetyCertificateOutlined,
  SettingOutlined,
  TagsOutlined,
  TeamOutlined,
} from "@ant-design/icons";
import type { ReactNode } from "react";
import type { Permission } from "@/shared/api/types";

export type NavItem = {
  href: string;
  /** Key under the "nav" messages. */
  label: "dashboard" | "reports" | "pos" | "handover" | "orders" | "register" | "promotions" | "notifications" | "customers" | "catalog" | "branches" | "users" | "roles" | "settings" | "activity";
  icon: ReactNode;
  /** Shown only to users holding this permission (or any one of a list). */
  permission?: Permission | Permission[];
};

export type NavGroup = { label?: "management"; items: NavItem[] };

export const NAV: NavGroup[] = [
  {
    items: [
      { href: "/dashboard", label: "dashboard", icon: <DashboardOutlined /> },
      { href: "/pos", label: "pos", icon: <ShopOutlined />, permission: "orders.create" },
      { href: "/handover", label: "handover", icon: <ExportOutlined />, permission: "orders.handover" },
      { href: "/orders", label: "orders", icon: <FileTextOutlined />, permission: "orders.view" },
      { href: "/register", label: "register", icon: <WalletOutlined />, permission: ["shifts.operate", "shifts.view"] },
      { href: "/customers", label: "customers", icon: <ContactsOutlined />, permission: "customers.view" },
    ],
  },
  {
    label: "management",
    items: [
      { href: "/reports", label: "reports", icon: <LineChartOutlined />, permission: "reports.view" },
      { href: "/catalog", label: "catalog", icon: <TagsOutlined />, permission: "catalog.view" },
      { href: "/promotions", label: "promotions", icon: <GiftOutlined />, permission: "promotions.manage" },
      { href: "/notifications", label: "notifications", icon: <MessageOutlined />, permission: ["notifications.view", "notifications.manage"] },
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
  const allowed = (permission: NavItem["permission"]) => (Array.isArray(permission) ? permission.some((p) => can(p)) : can(permission));
  return NAV.map((group) => ({ ...group, items: group.items.filter((item) => allowed(item.permission)) })).filter(
    (group) => group.items.length > 0,
  );
}

/** The nav entry that owns a pathname, e.g. "/users/5" → users. */
export function activeNavItem(pathname: string): NavItem | undefined {
  return NAV.flatMap((group) => group.items).find(
    (item) => pathname === item.href || pathname.startsWith(`${item.href}/`),
  );
}
