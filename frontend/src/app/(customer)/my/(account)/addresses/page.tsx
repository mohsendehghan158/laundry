import type { Metadata } from "next";
import { MyAddresses } from "@/features/portal/components/MyAddresses";

export const metadata: Metadata = { title: "نشانی‌های من" };

export default function Page() {
  return <MyAddresses />;
}
