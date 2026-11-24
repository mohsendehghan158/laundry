import type { Metadata } from "next";
import { ActivityPage } from "@/features/activity/components/ActivityPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "لاگ فعالیت" };

export default function Page() {
  return (
    <RequirePermission permission="activity.view">
      <ActivityPage />
    </RequirePermission>
  );
}
