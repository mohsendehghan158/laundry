import { screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { makeUser, renderWithProviders } from "@/test/render";
import { RequirePermission } from "./RequirePermission";

describe("RequirePermission", () => {
  it("renders the page for a user holding the permission", () => {
    renderWithProviders(<RequirePermission permission="users.view">users</RequirePermission>, {
      me: makeUser({ permissions: ["users.view"] }),
    });
    expect(screen.getByText("users")).toBeInTheDocument();
  });

  it("shows a 403 screen otherwise", () => {
    renderWithProviders(<RequirePermission permission="users.view">users</RequirePermission>, { me: makeUser() });
    expect(screen.queryByText("users")).not.toBeInTheDocument();
    expect(screen.getByText("دسترسی ندارید")).toBeInTheDocument();
  });
});
