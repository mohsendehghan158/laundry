import { theme, type ThemeConfig } from "antd";

/** Every color, radius and font decision of the interface lives here. */
const FONT = "Vazirmatn, Tahoma, system-ui, sans-serif";

const shared: ThemeConfig = {
  token: {
    fontFamily: FONT,
    colorPrimary: "#0e7490",
    colorInfo: "#0e7490",
    colorSuccess: "#15803d",
    colorWarning: "#b45309",
    colorError: "#b91c1c",
    borderRadius: 8,
    fontSize: 14,
  },
  components: {
    Layout: { headerHeight: 56, headerPadding: "0 16px" },
    Menu: { itemBorderRadius: 8 },
  },
};

export const lightTheme: ThemeConfig = {
  ...shared,
  algorithm: theme.defaultAlgorithm,
  token: { ...shared.token, colorBgLayout: "#f4f6f8", colorPrimaryBg: "#e3f3f7", colorInfoBg: "#e3f3f7", colorInfoBorder: "#a9d9e5" },
  components: {
    ...shared.components,
    Layout: { ...shared.components?.Layout, siderBg: "#ffffff", headerBg: "#ffffff" },
    Menu: { ...shared.components?.Menu, itemSelectedBg: "#e3f3f7" },
  },
};

export const darkTheme: ThemeConfig = {
  ...shared,
  algorithm: theme.darkAlgorithm,
  token: { ...shared.token, colorPrimary: "#22a3c4", colorInfo: "#22a3c4", colorBgLayout: "#0b0f14" },
  components: { ...shared.components, Layout: { ...shared.components?.Layout, siderBg: "#141a21", headerBg: "#141a21" } },
};

/** Point of sale and kiosk screens: larger type and touch targets of at least 48px. */
export const touchTheme: ThemeConfig = {
  token: { fontSize: 16, controlHeight: 48, controlHeightLG: 56 },
};
