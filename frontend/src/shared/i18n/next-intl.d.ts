import type fa from "./fa.json";
import type { Locale } from "./index";

declare module "next-intl" {
  interface AppConfig {
    Locale: Locale;
    Messages: typeof fa;
  }
}
