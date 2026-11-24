import type { Metadata } from "next";
import { CatalogPage } from "@/features/catalog/components/CatalogPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "کاتالوگ و قیمت" };

export default function Page() {
  return (
    <RequirePermission permission="catalog.view">
      <CatalogPage />
    </RequirePermission>
  );
}
