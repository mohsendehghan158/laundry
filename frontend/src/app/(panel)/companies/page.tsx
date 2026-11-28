import type { Metadata } from "next";
import { CompaniesPage } from "@/features/companies/components/CompaniesPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "مشتریان سازمانی" };

export default function Page() {
  return (
    <RequirePermission permission="companies.view">
      <CompaniesPage />
    </RequirePermission>
  );
}
