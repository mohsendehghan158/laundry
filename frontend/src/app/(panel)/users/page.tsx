import type { Metadata } from "next";
import { UsersPage } from "@/features/users/components/UsersPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "کاربران" };

export default function Page() {
  return (
    <RequirePermission permission="users.view">
      <UsersPage />
    </RequirePermission>
  );
}
