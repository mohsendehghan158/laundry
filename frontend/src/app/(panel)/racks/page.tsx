import type { Metadata } from "next";
import { RacksPage } from "@/features/production/components/RacksPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "قفسه‌ها" };

export default function Page() {
  return (
    <RequirePermission permission={["production.view", "orders.view"]}>
      <RacksPage />
    </RequirePermission>
  );
}
