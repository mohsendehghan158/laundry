import type { Metadata } from "next";
import { BoardPage } from "@/features/production/components/BoardPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "تابلوی تولید" };

export default function Page() {
  return (
    <RequirePermission permission="production.view">
      <BoardPage />
    </RequirePermission>
  );
}
