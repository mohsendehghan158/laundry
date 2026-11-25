import { PortalShell } from "@/features/portal/components/PortalShell";

export default function AccountLayout({ children }: LayoutProps<"/my">) {
  return <PortalShell>{children}</PortalShell>;
}
