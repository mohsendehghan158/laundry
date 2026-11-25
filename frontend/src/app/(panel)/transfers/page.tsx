import type { Metadata } from "next";
import { TransfersPage } from "@/features/production/components/TransfersPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "بارنامه‌ها" };

export default function Page() {
  return (
    <RequirePermission permission="production.transfer">
      <TransfersPage />
    </RequirePermission>
  );
}
