import type { Metadata } from "next";
import { InvoicePrint } from "@/features/invoices/components/InvoicePrint";

export const metadata: Metadata = { title: "فاکتور" };

export default function Page() {
  return <InvoicePrint />;
}
