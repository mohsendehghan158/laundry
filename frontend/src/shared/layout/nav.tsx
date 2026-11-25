import {
  ApartmentOutlined,
  BarChartOutlined,
  ClusterOutlined,
  InboxOutlined,
  AppstoreOutlined,
  FundProjectionScreenOutlined,
  SwapOutlined,
  ShareAltOutlined,
  WarningOutlined,
  ScanOutlined,
  SearchOutlined,
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
  label: "dashboard" | "reports" | "productionReport" | "board" | "station" | "assembly" | "racks" | "pieces" | "transfers" | "outsourcing" | "claims" | "stations" | "pos" | "handover" | "orders" | "register" | "promotions" | "notifications" | "customers" | "catalog" | "branches" | "users" | "roles" | "settings" | "activity";
  icon: ReactNode;
  /** Shown only to users holding this permission (or any one of a list). */
  permission?: Permission | Permission[];
};

export type NavGroup = { label?: "management" | "workshop"; items: NavItem[] };

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
    label: "workshop",
    items: [
      { href: "/production", label: "board", icon: <FundProjectionScreenOutlined />, permission: "production.view" },
      { href: "/station", label: "station", icon: <ScanOutlined />, permission: "production.scan" },
      { href: "/assembly", label: "assembly", icon: <InboxOutlined />, permission: ["production.scan", "orders.update"] },
      { href: "/racks", label: "racks", icon: <AppstoreOutlined />, permission: ["production.view", "orders.view"] },
      { href: "/pieces", label: "pieces", icon: <SearchOutlined />, permission: ["production.view", "orders.view"] },
      { href: "/transfers", label: "transfers", icon: <SwapOutlined />, permission: "production.transfer" },
      { href: "/outsourcing", label: "outsourcing", icon: <ShareAltOutlined />, permission: "production.outsource" },
      { href: "/claims", label: "claims", icon: <WarningOutlined />, permission: "claims.view" },
    ],
  },
  {
    label: "management",
    items: [
      { href: "/reports", label: "reports", icon: <LineChartOutlined />, permission: "reports.view" },
      { href: "/reports/production", label: "productionReport", icon: <BarChartOutlined />, permission: "reports.production" },
      { href: "/stations", label: "stations", icon: <ClusterOutlined />, permission: "production.manage" },
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
  // The most specific entry wins: /reports/production over /reports.
  return NAV.flatMap((group) => group.items)
    .filter((item) => pathname === item.href || pathname.startsWith(`${item.href}/`))
    .sort((a, b) => b.href.length - a.href.length)[0];
}
