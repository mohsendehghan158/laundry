import { screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { renderWithProviders } from "@/test/render";
import { portalKeys } from "../api";
import type { PortalAddress } from "../types";
import { MyAddresses } from "./MyAddresses";

vi.mock("@/shared/map", () => ({ PointMap: () => null }));

const zone = { id: 1, name: "شمال و مرکز", branch_name: "شعبه مرکزی", delivery_fee: 300_000, free_delivery_over: 5_000_000, min_order: null };

function renderAddresses(addresses: PortalAddress[]) {
  const result = renderWithProviders(<MyAddresses />);
  result.queryClient.setQueryData(portalKeys.settings, { organization_name: "خشکشویی نمونه", currency_unit: "toman" });
  result.queryClient.setQueryData(portalKeys.addresses, addresses);
  return result;
}

describe("MyAddresses", () => {
  it("says for each address whether it is served and what delivery costs", async () => {
    renderAddresses([
      { id: 1, title: "خانه", address: "ونک، ملاصدرا", latitude: 35.76, longitude: 51.41, is_default: true, zone },
      { id: 2, title: "ویلا", address: "لواسان", latitude: 35.82, longitude: 51.63, is_default: false, zone: null },
    ]);

    expect(await screen.findByText("در محدوده سرویس «شمال و مرکز»")).toBeInTheDocument();
    expect(screen.getByText("هزینه ارسال: ۳۰٬۰۰۰ تومان، رایگان برای سفارش‌های از ۵۰۰٬۰۰۰ تومان")).toBeInTheDocument();
    expect(screen.getByText(/این نقطه خارج از محدوده جمع‌آوری و تحویل است/)).toBeInTheDocument();
    expect(screen.getByText("پیش‌فرض")).toBeInTheDocument();
    // Only the address that is not the default offers to become it.
    expect(screen.getAllByRole("button", { name: "پیش‌فرض شود" })).toHaveLength(1);
  });

  it("invites a customer with no addresses to add one", async () => {
    renderAddresses([]);
    expect(await screen.findByText("هنوز نشانی‌ای ثبت نکرده‌اید.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /افزودن نشانی/ })).toBeInTheDocument();
  });
});
