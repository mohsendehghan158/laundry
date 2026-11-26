import { screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { renderWithProviders } from "@/test/render";
import { portalKeys } from "../api";
import type { OnlineOrderQuote, PortalAddress } from "../types";
import { NewOrder } from "./NewOrder";

const push = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ push, replace: vi.fn() }) }));

const post = vi.fn();
const apiFetch = vi.fn();
vi.mock("@/shared/api/client", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/shared/api/client")>()),
  api: { get: vi.fn(), post: (...args: unknown[]) => post(...args), put: vi.fn(), delete: vi.fn() },
  apiFetch: (...args: unknown[]) => apiFetch(...args),
}));

const zone = { id: 1, name: "شمال و مرکز", branch_name: "شعبه مرکزی", delivery_fee: 300_000, free_delivery_over: 5_000_000, min_order: null };
const addresses: PortalAddress[] = [
  { id: 1, title: "خانه", address: "ونک، ملاصدرا", latitude: 35.76, longitude: 51.41, is_default: true, zone },
  { id: 2, title: "ویلا", address: "لواسان", latitude: 35.82, longitude: 51.63, is_default: false, zone: null },
];
const slots = {
  zone,
  slots: [
    { id: 11, date: "2026-12-05", starts_at: "14:00", ends_at: "16:00", remaining: 1 },
    { id: 12, date: "2026-12-06", starts_at: "16:00", ends_at: "19:00", remaining: 3 },
  ],
};
const catalog = {
  categories: [
    {
      id: 1,
      name: "پوشاک",
      items: [{ id: 10, name: "پیراهن", unit: "piece" as const, prices: [{ service_type_id: 20, service_name: "خشکشویی", price: 900_000 }] }],
    },
  ],
};

function quote(overrides: Partial<OnlineOrderQuote> = {}): OnlineOrderQuote {
  return {
    zone,
    items_by_driver: false,
    lines: [{ item_name: "پیراهن", service_name: "خشکشویی", quantity: "2.000", line_total: 1_800_000 }],
    subtotal: 1_800_000,
    discount_amount: 0,
    tax_amount: 180_000,
    items_total: 1_980_000,
    delivery_fee: 300_000,
    total: 2_280_000,
    min_order: null,
    below_minimum: false,
    ...overrides,
  };
}

function renderNewOrder() {
  const result = renderWithProviders(<NewOrder />);
  result.queryClient.setQueryData(portalKeys.settings, { organization_name: "خشکشویی نمونه", currency_unit: "toman" });
  result.queryClient.setQueryData(portalKeys.addresses, addresses);
  result.queryClient.setQueryData(portalKeys.slots(1), slots);
  result.queryClient.setQueryData(portalKeys.catalog, catalog);
  return result;
}

describe("NewOrder", () => {
  beforeEach(() => {
    post.mockReset();
    apiFetch.mockReset();
    push.mockReset();
  });

  it("orders a pickup of the items picked, in the chosen window", async () => {
    post.mockResolvedValue({ data: quote() });
    apiFetch.mockResolvedValue({ data: { public_id: "01JABC", ticket_number: "T1-00001" } });
    renderNewOrder();

    // Only served addresses can be picked; the default one is.
    expect(await screen.findByRole("radio", { name: /خانه/ })).toBeChecked();
    expect(screen.getByRole("radio", { name: /ویلا/ })).toBeDisabled();
    expect(screen.getByRole("group", { name: "شنبه ۱۴۰۵/۰۹/۱۴" })).toBeInTheDocument();

    await userEvent.type(screen.getByRole("spinbutton", { name: "پیراهن" }), "2");
    expect(await screen.findByText("۲۲۸٬۰۰۰ تومان")).toBeInTheDocument();
    expect(post).toHaveBeenLastCalledWith("/customer/orders/quote", {
      address_id: 1,
      items_by_driver: false,
      lines: [{ item_id: 10, service_type_id: 20, quantity: 2 }],
    });
    const submit = screen.getByRole("button", { name: "ثبت سفارش" });
    expect(submit).toBeDisabled();

    await userEvent.click(screen.getByRole("radio", { name: "یکشنبه ۱۶:۰۰–۱۹:۰۰" }).closest("label")!);
    await userEvent.type(screen.getByRole("textbox", { name: "توضیحات برای راننده" }), "زنگ دوم");
    await waitFor(() => expect(submit).toBeEnabled());
    await userEvent.click(submit);

    await waitFor(() => expect(push).toHaveBeenCalledWith("/my/orders/01JABC"));
    const [path, options] = apiFetch.mock.calls[0] as [string, { method: string; body: unknown; headers: Record<string, string> }];
    expect(path).toBe("/customer/orders");
    expect(options.body).toEqual({ address_id: 1, items_by_driver: false, lines: [{ item_id: 10, service_type_id: 20, quantity: 2 }], slot_id: 12, notes: "زنگ دوم" });
    expect(options.headers["Idempotency-Key"]).toBeTruthy();
  });

  it("leaves the items to the driver, priced with the delivery fee only", async () => {
    post.mockResolvedValue({ data: quote({ items_by_driver: true, lines: [], subtotal: 0, tax_amount: 0, items_total: 0, total: 300_000 }) });
    renderNewOrder();

    await userEvent.click(await screen.findByRole("checkbox", { name: "اقلام را راننده هنگام جمع‌آوری ثبت کند" }));

    expect(screen.queryByRole("spinbutton", { name: "پیراهن" })).not.toBeInTheDocument();
    expect(await screen.findByText("مبلغ اقلام پس از ثبت آن‌ها توسط راننده اضافه می‌شود.")).toBeInTheDocument();
    expect(post).toHaveBeenLastCalledWith("/customer/orders/quote", { address_id: 1, items_by_driver: true });
  });

  it("does not let an order under the zone minimum through", async () => {
    post.mockResolvedValue({ data: quote({ min_order: 3_000_000, below_minimum: true }) });
    renderNewOrder();

    await userEvent.type(await screen.findByRole("spinbutton", { name: "پیراهن" }), "2");
    await userEvent.click(screen.getByRole("radio", { name: "شنبه ۱۴:۰۰–۱۶:۰۰" }).closest("label")!);

    expect(await screen.findByText("حداقل سفارش آنلاین در این محدوده ۳۰۰٬۰۰۰ تومان است.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "ثبت سفارش" })).toBeDisabled();
  });

  it("sends a customer with no served address to add one", async () => {
    const result = renderWithProviders(<NewOrder />);
    result.queryClient.setQueryData(portalKeys.settings, { organization_name: "خشکشویی نمونه", currency_unit: "toman" });
    result.queryClient.setQueryData(portalKeys.addresses, [addresses[1]]);

    const empty = await screen.findByText("برای سفارش درب منزل، اول نشانی‌ای در محدوده سرویس ثبت کنید.");
    expect(within(empty.closest(".ant-empty")!).getByRole("link")).toHaveAttribute("href", "/my/addresses");
  });
});
