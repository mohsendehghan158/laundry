import type { Metadata, Viewport } from "next";
import type { ReactNode } from "react";
import { AntdRegistry } from "@ant-design/nextjs-registry";
import "vazirmatn/Vazirmatn-font-face.css";
import "./globals.css";
import { Providers } from "./providers";

export const metadata: Metadata = {
  title: { default: "سامانه خشکشویی", template: "%s | سامانه خشکشویی" },
  description: "مدیریت پذیرش، کارگاه، تحویل و مالی خشکشویی",
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#ffffff" },
    { media: "(prefers-color-scheme: dark)", color: "#141a21" },
  ],
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="fa" dir="rtl">
      <body>
        <AntdRegistry>
          <Providers>{children}</Providers>
        </AntdRegistry>
      </body>
    </html>
  );
}
