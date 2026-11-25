import type { Metadata } from "next";
import { ProductionReportPage } from "@/features/reports/components/ProductionReportPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "گزارش تولید" };

export default function Page() {
  return (
    <RequirePermission permission="reports.production">
      <ProductionReportPage />
    </RequirePermission>
  );
}
