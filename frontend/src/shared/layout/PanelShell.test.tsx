import { screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { makeUser, renderWithProviders } from "@/test/render";
import { PanelShell } from "./PanelShell";

const replace = vi.fn();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace, push: vi.fn() }),
  usePathname: () => "/users",
}));

beforeEach(() => replace.mockReset());

describe("PanelShell", () => {
  it("shows only the menu entries the user is allowed to open", () => {
    renderWithProviders(<PanelShell>content</PanelShell>, {
      me: makeUser({ permissions: ["branches.view", "users.view"] }),
    });

    expect(screen.getByRole("link", { name: "داشبورد" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "شعبه‌ها" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "کاربران" })).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "نقش‌ها و مجوزها" })).not.toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "تنظیمات" })).not.toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "لاگ فعالیت" })).not.toBeInTheDocument();
    expect(screen.getByText("content")).toBeInTheDocument();
    expect(screen.getByText("خشکشویی نمونه")).toBeInTheDocument();
  });

  it("drops the management group for a user without management permissions", () => {
    renderWithProviders(<PanelShell>content</PanelShell>, { me: makeUser({ roles: ["cashier"], permissions: [] }) });
    expect(screen.getByRole("link", { name: "داشبورد" })).toBeInTheDocument();
    expect(screen.queryByText("مدیریت")).not.toBeInTheDocument();
  });

  it("sends a signed-out visitor to the login page, remembering where they were", async () => {
    renderWithProviders(<PanelShell>content</PanelShell>, { me: null });
    await waitFor(() => expect(replace).toHaveBeenCalledWith("/login?next=%2Fusers"));
    expect(screen.queryByText("content")).not.toBeInTheDocument();
  });
});
