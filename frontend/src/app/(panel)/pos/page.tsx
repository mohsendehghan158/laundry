import type { Metadata } from "next";
import { PosPage } from "@/features/pos/components/PosPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "صندوق" };

export default function Page() {
  return (
    <RequirePermission permission="orders.create">
      <PosPage />
    </RequirePermission>
  );
}
