import en from "./en.json";
import fa from "./fa.json";

export const LOCALES = ["fa", "en"] as const;
export type Locale = (typeof LOCALES)[number];

export const DEFAULT_LOCALE: Locale = "fa";
export const TIME_ZONE = "Asia/Tehran";

export const messages: Record<Locale, typeof fa> = { fa, en };
