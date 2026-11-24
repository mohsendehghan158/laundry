import type { Metadata } from "next";
import { OrdersPage } from "@/features/orders/components/OrdersPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "سفارش‌ها" };

export default function Page() {
  return (
    <RequirePermission permission="orders.view">
      <OrdersPage />
    </RequirePermission>
  );
}
