import type { Metadata } from "next";
import { OutsourcingPage } from "@/features/production/components/OutsourcingPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "برون‌سپاری" };

export default function Page() {
  return (
    <RequirePermission permission="production.outsource">
      <OutsourcingPage />
    </RequirePermission>
  );
}
