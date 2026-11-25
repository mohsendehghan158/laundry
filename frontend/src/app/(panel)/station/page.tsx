import type { Metadata } from "next";
import { StationPage } from "@/features/production/components/StationPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "ایستگاه اسکن" };

export default function Page() {
  return (
    <RequirePermission permission="production.scan">
      <StationPage />
    </RequirePermission>
  );
}
