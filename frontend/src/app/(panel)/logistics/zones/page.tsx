import type { Metadata } from "next";
import { ZonesPage } from "@/features/logistics/components/ZonesPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "محدوده‌های سرویس" };

export default function Page() {
  return (
    <RequirePermission permission="logistics.view">
      <ZonesPage />
    </RequirePermission>
  );
}
