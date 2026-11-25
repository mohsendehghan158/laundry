import type { Metadata } from "next";
import { ReceiptPrint } from "@/features/printing/components/ReceiptPrint";

export const metadata: Metadata = { title: "رسید" };

export default function Page() {
  return <ReceiptPrint />;
}
