import type { Metadata } from "next";
import { RegisterPage } from "@/features/billing/components/RegisterPage";

export const metadata: Metadata = { title: "صندوق و شیفت" };

export default function Page() {
  return <RegisterPage />;
}
