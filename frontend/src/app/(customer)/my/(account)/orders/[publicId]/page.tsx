import type { Metadata } from "next";
import { MyOrder } from "@/features/portal/components/MyOrder";

export const metadata: Metadata = { title: "سفارش" };

export default function Page() {
  return <MyOrder />;
}
