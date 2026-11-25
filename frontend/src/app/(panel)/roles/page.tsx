import type { Metadata } from "next";
import { RolesPage } from "@/features/roles/RolesPage";

export const metadata: Metadata = { title: "نقش‌ها و مجوزها" };

export default function Page() {
  return <RolesPage />;
}
