import type { Metadata } from "next";
import { SalesReportPage } from "@/features/reports/components/SalesReportPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "گزارش فروش" };

export default function Page() {
  return (
    <RequirePermission permission="reports.view">
      <SalesReportPage />
    </RequirePermission>
  );
}
