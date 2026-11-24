import type { Metadata } from "next";
import { AccountPage } from "@/features/account/components/AccountPage";

export const metadata: Metadata = { title: "حساب من" };

export default function Page() {
  return <AccountPage />;
}
