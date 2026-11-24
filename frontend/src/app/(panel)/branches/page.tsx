import type { Metadata } from "next";
import { BranchesPage } from "@/features/branches/components/BranchesPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "شعبه‌ها" };

export default function Page() {
  return (
    <RequirePermission permission="branches.view">
      <BranchesPage />
    </RequirePermission>
  );
}
