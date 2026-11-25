import type { Metadata } from "next";
import { DashboardPage } from "@/features/shell/DashboardPage";

export const metadata: Metadata = { title: "پیشخوان" };

export default function Page() {
  return <DashboardPage />;
}
