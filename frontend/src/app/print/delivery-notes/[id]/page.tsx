import type { Metadata } from "next";
import { DeliveryNotePrint } from "@/features/companies/components/DeliveryNotePrint";

export const metadata: Metadata = { title: "حواله" };

export default function Page() {
  return <DeliveryNotePrint />;
}
