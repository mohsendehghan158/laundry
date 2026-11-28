import type { Metadata } from "next";
import { InvoicesPage } from "@/features/invoices/components/InvoicesPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "فاکتورها" };

export default function Page() {
  return (
    <RequirePermission permission="invoices.view">
      <InvoicesPage />
    </RequirePermission>
  );
}
