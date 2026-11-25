import type { Metadata } from "next";
import { TransferManifestPrint } from "@/features/production/components/TransferManifestPrint";

export const metadata: Metadata = { title: "بارنامه" };

export default function Page() {
  return <TransferManifestPrint />;
}
