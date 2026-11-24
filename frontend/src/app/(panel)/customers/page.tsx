import type { Metadata } from "next";
import { CustomersPage } from "@/features/customers/components/CustomersPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "مشتریان" };

export default function Page() {
  return (
    <RequirePermission permission="customers.view">
      <CustomersPage />
    </RequirePermission>
  );
}
