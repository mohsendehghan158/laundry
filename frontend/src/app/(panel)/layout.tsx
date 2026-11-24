import { Suspense } from "react";
import { PanelShell } from "@/shared/layout/PanelShell";

export default function PanelLayout({ children }: LayoutProps<"/">) {
  return (
    <Suspense>
      <PanelShell>{children}</PanelShell>
    </Suspense>
  );
}
