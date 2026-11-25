import { Suspense, type ReactNode } from "react";
import { PanelShell } from "@/features/shell/PanelShell";

export default function PanelLayout({ children }: { children: ReactNode }) {
  return (
    <Suspense>
      <PanelShell>{children}</PanelShell>
    </Suspense>
  );
}
