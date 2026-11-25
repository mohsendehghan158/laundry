"use client";

import { App as AntApp, ConfigProvider } from "antd";
import faIR from "antd/locale/fa_IR";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { NextIntlClientProvider } from "next-intl";
import { NuqsAdapter } from "nuqs/adapters/next/app";
import { useState, type ReactNode } from "react";
import messages from "@/shared/i18n/fa.json";
import { isApiError } from "@/shared/api/errors";
import { jalaliPickerLocale } from "@/shared/ui/JalaliDatePicker";
import { darkTheme, lightTheme } from "@/theme/tokens";
import { ThemeModeProvider, useThemeMode } from "@/theme/ThemeModeProvider";

function makeQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        refetchOnWindowFocus: false,
        // Client errors (403, 404, 422 …) will not fix themselves on retry.
        retry: (failureCount, error) =>
          !(isApiError(error) && error.status >= 400 && error.status < 500) && failureCount < 2,
      },
    },
  });
}

function ThemedApp({ children }: { children: ReactNode }) {
  const { mode } = useThemeMode();
  return (
    <ConfigProvider
      direction="rtl"
      locale={{ ...faIR, DatePicker: jalaliPickerLocale }}
      theme={mode === "dark" ? darkTheme : lightTheme}
    >
      <AntApp>{children}</AntApp>
    </ConfigProvider>
  );
}

export function Providers({ children }: { children: ReactNode }) {
  const [queryClient] = useState(makeQueryClient);

  return (
    <NextIntlClientProvider locale="fa" messages={messages} timeZone="Asia/Tehran">
      <QueryClientProvider client={queryClient}>
        <NuqsAdapter>
          <ThemeModeProvider>
            <ThemedApp>{children}</ThemedApp>
          </ThemeModeProvider>
        </NuqsAdapter>
      </QueryClientProvider>
    </NextIntlClientProvider>
  );
}
