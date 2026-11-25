import type { Metadata } from "next";
import { StationsPage } from "@/features/production/components/StationsPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "ایستگاه‌های کارگاه" };

export default function Page() {
  return (
    <RequirePermission permission="production.manage">
      <StationsPage />
    </RequirePermission>
  );
}
