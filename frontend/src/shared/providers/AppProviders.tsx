"use client";

import { App as AntdApp, ConfigProvider } from "antd";
import faIR from "antd/locale/fa_IR";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { NextIntlClientProvider } from "next-intl";
import { NuqsAdapter } from "nuqs/adapters/next/app";
import { useState, type ReactNode } from "react";
import { isApiError } from "@/shared/api/client";
import { messages, DEFAULT_LOCALE, TIME_ZONE } from "@/shared/i18n";
import { useColorScheme } from "@/shared/hooks/useColorScheme";
import { darkTheme, lightTheme } from "@/theme";

function makeQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        refetchOnWindowFocus: false,
        // Client errors (403, 404, 422 …) will not fix themselves.
        retry: (failureCount, error) => !(isApiError(error) && error.status < 500) && failureCount < 2,
      },
    },
  });
}

export function AppProviders({ children }: { children: ReactNode }) {
  const [queryClient] = useState(makeQueryClient);
  const scheme = useColorScheme();

  return (
    <NextIntlClientProvider locale={DEFAULT_LOCALE} messages={messages[DEFAULT_LOCALE]} timeZone={TIME_ZONE}>
      <ConfigProvider direction="rtl" locale={faIR} theme={scheme === "dark" ? darkTheme : lightTheme}>
        <AntdApp>
          <QueryClientProvider client={queryClient}>
            <NuqsAdapter>{children}</NuqsAdapter>
          </QueryClientProvider>
        </AntdApp>
      </ConfigProvider>
    </NextIntlClientProvider>
  );
}
