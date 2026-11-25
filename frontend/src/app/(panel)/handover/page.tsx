import type { Metadata } from "next";
import { HandoverPage } from "@/features/orders/components/HandoverPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "تحویل سفارش" };

export default function Page() {
  return (
    <RequirePermission permission="orders.handover">
      <HandoverPage />
    </RequirePermission>
  );
}
