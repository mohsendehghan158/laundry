import type { Metadata } from "next";
import { DashboardPage } from "@/features/dashboard/components/DashboardPage";

export const metadata: Metadata = { title: "داشبورد" };

export default function Page() {
  return <DashboardPage />;
}
