import type { Metadata } from "next";
import { StatementPrint } from "@/features/companies/components/StatementPrint";

export const metadata: Metadata = { title: "صورت‌حساب" };

export default function Page() {
  return <StatementPrint />;
}
