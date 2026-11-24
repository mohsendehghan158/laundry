import type { Metadata } from "next";
import { OrderDetailPage } from "@/features/orders/components/OrderDetailPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "سفارش" };

export default function Page() {
  return (
    <RequirePermission permission="orders.view">
      <OrderDetailPage />
    </RequirePermission>
  );
}
