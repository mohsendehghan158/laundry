import type { Metadata } from "next";
import { NotificationsPage } from "@/features/notifications/components/NotificationsPage";

export const metadata: Metadata = { title: "پیامک‌ها" };

export default function Page() {
  return <NotificationsPage />;
}
