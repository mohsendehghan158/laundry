import type { Metadata } from "next";
import { NewOrder } from "@/features/portal/components/NewOrder";

export const metadata: Metadata = { title: "سفارش جدید" };

export default function Page() {
  return <NewOrder />;
}
