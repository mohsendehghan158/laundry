import type { Metadata } from "next";
import { SettingsPage } from "@/features/settings/components/SettingsPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "تنظیمات" };

export default function Page() {
  return (
    <RequirePermission permission="settings.view">
      <SettingsPage />
    </RequirePermission>
  );
}
