import type { Metadata } from "next";

/** The customer portal; installable on a phone's home screen. */
export const metadata: Metadata = {
  title: { default: "پرتال مشتری", template: "%s | پرتال مشتری" },
  manifest: "/portal.webmanifest",
  appleWebApp: { capable: true, title: "خشکشویی", statusBarStyle: "default" },
  icons: { apple: "/icons/portal-192.png" },
};

export default function PortalLayout({ children }: LayoutProps<"/my">) {
  return children;
}
