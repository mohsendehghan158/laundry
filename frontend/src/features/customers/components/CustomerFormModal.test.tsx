import { fireEvent, screen } from "@testing-library/react";
import { useState } from "react";
import { describe, expect, it } from "vitest";
import { makeUser, renderWithProviders } from "@/test/render";
import { CustomerFormModal } from "./CustomerFormModal";

function Parent() {
  const [ticks, setTicks] = useState(0);
  return (
    <>
      <button onClick={() => setTicks(ticks + 1)}>rerender</button>
      {/* A new `initial` object on every render, as a search box would pass. */}
      <CustomerFormModal open initial={{ mobile: "09121234567" }} onClose={() => {}} />
    </>
  );
}

describe("CustomerFormModal", () => {
  it("keeps what the user typed when the parent re-renders", async () => {
    renderWithProviders(<Parent />, { me: makeUser({ permissions: ["customers.manage"] }) });
    const name = await screen.findByLabelText("نام");
    expect(screen.getByLabelText("موبایل")).toHaveValue("09121234567");

    fireEvent.change(name, { target: { value: "نسرین" } });
    fireEvent.click(screen.getByText("rerender"));

    expect(screen.getByLabelText("نام")).toHaveValue("نسرین");
  });
});
