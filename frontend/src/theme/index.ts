import { theme as antdTheme, type ThemeConfig } from "antd";

/** Brand tokens shared by every theme. Change colours here, nowhere else. */
const base: ThemeConfig["token"] = {
  colorPrimary: "#1668dc",
  colorInfo: "#1668dc",
  borderRadius: 8,
  fontFamily: "Vazirmatn, Tahoma, sans-serif",
  fontSize: 14,
};

export const lightTheme: ThemeConfig = {
  algorithm: antdTheme.defaultAlgorithm,
  token: { ...base, colorBgLayout: "#f5f7fa" },
  components: {
    Layout: { siderBg: "#ffffff", headerBg: "#ffffff" },
    Menu: { itemBorderRadius: 8 },
  },
};

export const darkTheme: ThemeConfig = {
  algorithm: antdTheme.darkAlgorithm,
  token: base,
  components: {
    Layout: { siderBg: "#141414", headerBg: "#141414" },
    Menu: { itemBorderRadius: 8 },
  },
};

/**
 * Large touch targets (48px+) for the counter and workshop stations.
 * Used from phase 2 onward by the POS and kiosk screens.
 */
export const touchTheme: ThemeConfig = {
  ...lightTheme,
  token: { ...lightTheme.token, controlHeight: 48, fontSize: 16 },
};
