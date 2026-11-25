import type { Metadata } from "next";
import { PromotionsPage } from "@/features/billing/components/PromotionsPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "کدهای تخفیف" };

export default function Page() {
  return (
    <RequirePermission permission="promotions.manage">
      <PromotionsPage />
    </RequirePermission>
  );
}
