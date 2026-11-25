import type { Metadata } from "next";
import { ProfilePage } from "@/features/auth/ProfilePage";

export const metadata: Metadata = { title: "حساب من" };

export default function Page() {
  return <ProfilePage />;
}
