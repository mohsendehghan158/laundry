import type { Metadata } from "next";
import { ActivityPage } from "@/features/activity/ActivityPage";

export const metadata: Metadata = { title: "گزارش فعالیت" };

export default function Page() {
  return <ActivityPage />;
}
