import type { Metadata } from "next";
import { RolesPage } from "@/features/roles/components/RolesPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "نقش‌ها و مجوزها" };

export default function Page() {
  return (
    <RequirePermission permission="roles.view">
      <RolesPage />
    </RequirePermission>
  );
}
