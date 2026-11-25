import type { Metadata } from "next";
import { SlotsPage } from "@/features/logistics/components/SlotsPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "بازه‌های جمع‌آوری و تحویل" };

export default function Page() {
  return (
    <RequirePermission permission="logistics.view">
      <SlotsPage />
    </RequirePermission>
  );
}
