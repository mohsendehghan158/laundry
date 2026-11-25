import type { Metadata } from "next";
import { PieceLookupPage } from "@/features/production/components/PieceLookupPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "ردیابی قطعه" };

export default function Page() {
  return (
    <RequirePermission permission={["production.view", "orders.view"]}>
      <PieceLookupPage />
    </RequirePermission>
  );
}
