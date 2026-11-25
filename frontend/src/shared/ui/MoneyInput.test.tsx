import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { MoneyInput } from "./MoneyInput";

describe("MoneyInput", () => {
  it("shows rials in toman with Persian digits", () => {
    render(<MoneyInput value={1_250_000} />);
    expect(screen.getByRole("spinbutton")).toHaveValue("۱۲۵٬۰۰۰");
    expect(screen.getByText("تومان")).toBeInTheDocument();
  });

  it("reports what is typed as rials, accepting Persian digits", () => {
    const onChange = vi.fn();
    render(<MoneyInput value={null} onChange={onChange} />);
    fireEvent.change(screen.getByRole("spinbutton"), { target: { value: "۲۵۰۰" } });
    expect(onChange).toHaveBeenLastCalledWith(25_000);
  });
});
