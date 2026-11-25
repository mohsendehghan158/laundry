import type { Metadata } from "next";
import { ClaimsPage } from "@/features/production/components/ClaimsPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "خسارت و مفقودی" };

export default function Page() {
  return (
    <RequirePermission permission="claims.view">
      <ClaimsPage />
    </RequirePermission>
  );
}
