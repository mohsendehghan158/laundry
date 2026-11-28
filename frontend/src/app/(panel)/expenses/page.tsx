import type { Metadata } from "next";
import { ExpensesPage } from "@/features/expenses/components/ExpensesPage";
import { RequirePermission } from "@/shared/ui/RequirePermission";

export const metadata: Metadata = { title: "هزینه‌ها" };

export default function Page() {
  return (
    <RequirePermission permission={["expenses.view", "expenses.manage"]}>
      <ExpensesPage />
    </RequirePermission>
  );
}
