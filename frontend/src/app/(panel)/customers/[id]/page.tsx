import type { Metadata } from "next";
import { CustomerDetailPage } from "@/features/customers/components/CustomerDetailPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "مشتری" };

export default function Page() {
  return (
    <RequirePermission permission="customers.view">
      <CustomerDetailPage />
    </RequirePermission>
  );
}
