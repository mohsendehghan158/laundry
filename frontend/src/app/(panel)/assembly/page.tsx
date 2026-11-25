import type { Metadata } from "next";
import { AssemblyPage } from "@/features/production/components/AssemblyPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "مونتاژ سفارش" };

export default function Page() {
  return (
    <RequirePermission permission={["production.scan", "orders.update"]}>
      <AssemblyPage />
    </RequirePermission>
  );
}
