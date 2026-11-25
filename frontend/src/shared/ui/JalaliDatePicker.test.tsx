import { fireEvent, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { dayjs, formatJalali } from "@/shared/lib/date";
import { renderWithProviders } from "@/test/render";
import { JalaliDatePicker } from "./JalaliDatePicker";

describe("JalaliDatePicker", () => {
  it("displays the value as a Jalali date", () => {
    renderWithProviders(<JalaliDatePicker value={dayjs("2026-11-24T10:30:00Z")} format="YYYY/MM/DD" />);
    expect(screen.getByRole("textbox")).toHaveValue("۱۴۰۵/۰۹/۰۳");
  });

  it("opens on the Jalali month with Saturday first", () => {
    renderWithProviders(<JalaliDatePicker open value={dayjs("2026-11-24T10:30:00Z")} format="YYYY/MM/DD" />);
    expect(document.querySelector(".ant-picker-month-btn")).toHaveTextContent("آذر");
    expect(document.querySelector(".ant-picker-year-btn")).toHaveTextContent("۱۴۰۵");
    const headers = Array.from(document.querySelectorAll(".ant-picker-content thead th")).map((th) => th.textContent);
    expect(headers[0]).toBe("ش");
  });

  it("reports the picked day as the right moment", () => {
    const onChange = vi.fn();
    renderWithProviders(<JalaliDatePicker open value={dayjs("2026-11-24T10:30:00Z")} onChange={onChange} format="YYYY/MM/DD" />);
    fireEvent.click(document.querySelector('td[title="۱۴۰۵-۰۹-۱۰"] .ant-picker-cell-inner')!);
    const picked = onChange.mock.calls[0][0];
    expect(formatJalali(picked, "YYYY-MM-DD")).toBe("1405-09-10");
    expect(picked.format("YYYY-MM-DD")).toBe("2026-12-01");
  });
});
