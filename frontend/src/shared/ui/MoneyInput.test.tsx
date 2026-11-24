import { fireEvent, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { renderWithProviders } from "@/test/render";
import { MoneyInput } from "./MoneyInput";

describe("MoneyInput", () => {
  it("shows a rial value in toman with Persian digits", () => {
    renderWithProviders(<MoneyInput value={1250000} />);
    expect(screen.getByRole("spinbutton")).toHaveValue("۱۲۵٬۰۰۰");
    expect(screen.getByText("تومان")).toBeInTheDocument();
  });

  it("accepts Persian digits and reports whole rials", () => {
    const onChange = vi.fn();
    renderWithProviders(<MoneyInput onChange={onChange} />);
    fireEvent.change(screen.getByRole("spinbutton"), { target: { value: "۱۲۵۰۰" } });
    expect(onChange).toHaveBeenLastCalledWith(125000);
  });

  it("works in rial when configured so", () => {
    const onChange = vi.fn();
    renderWithProviders(<MoneyInput unit="rial" onChange={onChange} />);
    fireEvent.change(screen.getByRole("spinbutton"), { target: { value: "5000" } });
    expect(onChange).toHaveBeenLastCalledWith(5000);
    expect(screen.getByText("ریال")).toBeInTheDocument();
  });
});

describe("MoneyInput when empty", () => {
  it("stays empty instead of showing zero", () => {
    renderWithProviders(<MoneyInput value={null} />);
    expect(screen.getByRole("spinbutton")).toHaveValue("");
  });
});
