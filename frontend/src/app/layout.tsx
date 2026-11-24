import type { Metadata, Viewport } from "next";
import { AntdRegistry } from "@ant-design/nextjs-registry";
import { Suspense } from "react";
import "vazirmatn/Vazirmatn-Variable-font-face.css";
import { AppProviders } from "@/shared/providers/AppProviders";
import "./globals.css";

export const metadata: Metadata = {
  title: { default: "سامانه مدیریت خشکشویی", template: "%s | خشکشویی" },
  description: "پذیرش، کارگاه، تحویل و مدیریت خشکشویی",
  robots: { index: false, follow: false },
};

export const viewport: Viewport = {
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#ffffff" },
    { media: "(prefers-color-scheme: dark)", color: "#141414" },
  ],
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="fa" dir="rtl">
      <body>
        {/* Ant Design's style cache picks a random id when created, which
            cannot be part of a prerendered shell; the panel renders per request. */}
        <Suspense>
          <AntdRegistry>
            <AppProviders>{children}</AppProviders>
          </AntdRegistry>
        </Suspense>
      </body>
    </html>
  );
}
