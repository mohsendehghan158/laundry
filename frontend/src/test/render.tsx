import { App as AntdApp, ConfigProvider } from "antd";
import faIR from "antd/locale/fa_IR";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render } from "@testing-library/react";
import { NextIntlClientProvider } from "next-intl";
import type { ReactElement } from "react";
import { meKey } from "@/features/auth/api";
import type { AuthUser } from "@/shared/api/types";
import { messages, TIME_ZONE } from "@/shared/i18n";

export function makeUser(overrides: Partial<AuthUser> = {}): AuthUser {
  return {
    id: 1,
    name: "سارا احمدی",
    username: "manager",
    email: null,
    mobile: null,
    locale: "fa",
    organization: { id: 1, name: "خشکشویی نمونه", currency_unit: "toman" },
    roles: ["branch_manager"],
    permissions: [],
    branches: [{ id: 1, name: "شعبه مرکزی", code: "T1", type: "hybrid" }],
    ...overrides,
  };
}

/** Renders with the same providers as the app; `me` seeds the signed-in user. */
export function renderWithProviders(ui: ReactElement, { me }: { me?: AuthUser | null } = {}) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity } } });
  if (me !== undefined) {
    queryClient.setQueryData(meKey, me);
  }
  const result = render(
    <NextIntlClientProvider locale="fa" messages={messages.fa} timeZone={TIME_ZONE}>
      <ConfigProvider direction="rtl" locale={faIR}>
        <AntdApp>
          <QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>
        </AntdApp>
      </ConfigProvider>
    </NextIntlClientProvider>,
  );
  return { ...result, queryClient };
}
