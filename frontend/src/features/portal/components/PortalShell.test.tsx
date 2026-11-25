import { screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { renderWithProviders } from "@/test/render";
import { portalKeys } from "../api";
import type { PortalCustomer } from "../types";
import { PortalShell } from "./PortalShell";

const replace = vi.fn();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace, push: vi.fn() }),
  usePathname: () => "/my/orders/01JD3ZKX",
}));

function renderShell(customer: PortalCustomer | null) {
  const result = renderWithProviders(<PortalShell>orders</PortalShell>);
  result.queryClient.setQueryData(portalKeys.settings, { organization_name: "خشکشویی نمونه", currency_unit: "toman" });
  result.queryClient.setQueryData(portalKeys.customer, customer);
  return result;
}

beforeEach(() => replace.mockReset());

describe("PortalShell", () => {
  it("shows the signed-in customer their pages under the business's name", async () => {
    renderShell({ id: 7, name: "سمیرا نادری", mobile: "09123456789", needs_name: false });
    expect(await screen.findByText("orders")).toBeInTheDocument();
    expect(screen.getByText("خشکشویی نمونه")).toBeInTheDocument();
    expect(screen.getByText("سمیرا نادری")).toBeInTheDocument();
  });

  it("asks a first-time customer for their name before anything else", async () => {
    renderShell({ id: 7, name: "", mobile: "09123456789", needs_name: true });
    expect(await screen.findByLabelText("نام و نام خانوادگی")).toBeInTheDocument();
    expect(screen.queryByText("orders")).not.toBeInTheDocument();
  });

  it("sends a visitor who is not signed in to the portal's sign-in, remembering the page", async () => {
    renderShell(null);
    await waitFor(() => expect(replace).toHaveBeenCalledWith("/my/login?next=%2Fmy%2Forders%2F01JD3ZKX"));
    expect(screen.queryByText("orders")).not.toBeInTheDocument();
  });
});
