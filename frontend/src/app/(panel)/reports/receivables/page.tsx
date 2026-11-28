import type { Metadata } from "next";
import { ReceivablesPage } from "@/features/reports/components/ReceivablesPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "سن مطالبات" };

export default function Page() {
  return (
    <RequirePermission permission="reports.view">
      <ReceivablesPage />
    </RequirePermission>
  );
}
