import type { Metadata } from "next";
import { Suspense } from "react";
import { PortalLogin } from "@/features/portal/components/PortalLogin";

export const metadata: Metadata = { title: "ورود" };

export default function Page() {
  return (
    <main style={{ minHeight: "100vh", display: "grid", placeItems: "center", padding: 16 }}>
      <Suspense>
        <PortalLogin />
      </Suspense>
    </main>
  );
}
