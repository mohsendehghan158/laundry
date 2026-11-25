import type { Metadata } from "next";
import { MyOrders } from "@/features/portal/components/MyOrders";

export const metadata: Metadata = { title: "سفارش‌های من" };

export default function Page() {
  return <MyOrders />;
}
