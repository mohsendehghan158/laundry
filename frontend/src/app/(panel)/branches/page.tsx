import type { Metadata } from "next";
import { BranchesPage } from "@/features/branches/BranchesPage";

export const metadata: Metadata = { title: "شعبه‌ها" };

export default function Page() {
  return <BranchesPage />;
}
