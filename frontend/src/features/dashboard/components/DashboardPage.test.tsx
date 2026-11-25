import { fireEvent, screen, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import type { DashboardReport } from "@/shared/api/types";
import { makeUser, renderWithProviders } from "@/test/render";
import { DashboardPage } from "./DashboardPage";

function report(): DashboardReport {
  const trend = Array.from({ length: 14 }, (_, i) => ({
    date: `2026-11-${String(12 + i).padStart(2, "0")}`,
    orders: i === 13 ? 3 : i % 2,
    sales: i === 13 ? 3_520_000 : (i % 2) * 900_000,
  }));
  return {
    today: { orders: 3, pieces: 7, sales: 3_520_000, collected: 990_000 },
    workload: { due_today: 2, overdue: 1, in_process: 5, ready: 4 },
    trend,
  };
}

function serve(data: DashboardReport) {
  const fetch = vi.fn(async () => new Response(JSON.stringify({ data }), { status: 200, headers: { "Content-Type": "application/json" } }));
  vi.stubGlobal("fetch", fetch);
  return fetch;
}

afterEach(() => vi.unstubAllGlobals());

describe("DashboardPage", () => {
  it("shows today's figures in the display unit and links the workload to the orders list", async () => {
    serve(report());
    renderWithProviders(<DashboardPage />, { me: makeUser({ permissions: ["reports.view", "orders.view"] }) });

    expect(await screen.findByText("۳۵۲٬۰۰۰")).toBeInTheDocument();
    expect(screen.getByText("سفارش امروز").closest(".ant-card")).toHaveTextContent("۳");
    expect(screen.getByText("دریافتی امروز").closest(".ant-card")).toHaveTextContent("۹۹٬۰۰۰تومان");
    expect(screen.getByRole("link", { name: "دیرکرد: ۱" })).toHaveAttribute("href", "/orders?due=overdue");
    expect(screen.getByRole("link", { name: "آماده تحویل: ۴" })).toHaveAttribute("href", "/orders?status=ready");
  });

  it("draws one bar a day and offers the same figures as a table", async () => {
    serve(report());
    renderWithProviders(<DashboardPage />, { me: makeUser({ permissions: ["reports.view"] }) });

    const chart = await screen.findByRole("figure", { name: "نمودار ستونی فروش در ۱۴ روز اخیر" });
    const bars = within(chart).getAllByRole("listitem");
    expect(bars).toHaveLength(14);
    expect(bars[13]).toHaveAccessibleName(/۳۵۲٬۰۰۰ تومان، ۳ سفارش/);

    fireEvent.click(screen.getByTitle("جدول"));
    const rows = document.querySelectorAll(".ant-table-tbody .ant-table-row");
    expect(rows).toHaveLength(14);
    // Newest day first.
    expect(rows[0]).toHaveTextContent("۳۵۲٬۰۰۰");
  });

  it("asks nothing of the report API for users who may not see reports", () => {
    const fetch = serve(report());
    renderWithProviders(<DashboardPage />, { me: makeUser({ permissions: [] }) });

    expect(screen.getByRole("heading", { name: "سلام سارا احمدی" })).toBeInTheDocument();
    expect(screen.queryByText("فروش امروز")).not.toBeInTheDocument();
    expect(fetch).not.toHaveBeenCalled();
  });
});
