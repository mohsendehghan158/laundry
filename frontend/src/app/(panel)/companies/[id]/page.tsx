import type { Metadata } from "next";
import { CompanyDetailPage } from "@/features/companies/components/CompanyDetailPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "مشتری سازمانی" };

export default function Page() {
  return (
    <RequirePermission permission="companies.view">
      <CompanyDetailPage />
    </RequirePermission>
  );
}
