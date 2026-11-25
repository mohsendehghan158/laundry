import type { Metadata } from "next";
import { UsersPage } from "@/features/users/UsersPage";

export const metadata: Metadata = { title: "کاربران" };

export default function Page() {
  return <UsersPage />;
}
