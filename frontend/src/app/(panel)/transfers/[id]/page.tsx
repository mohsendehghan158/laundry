import type { Metadata } from "next";
import { TransferDetailPage } from "@/features/production/components/TransferDetailPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "بارنامه" };

export default function Page() {
  return (
    <RequirePermission permission="production.transfer">
      <TransferDetailPage />
    </RequirePermission>
  );
}
