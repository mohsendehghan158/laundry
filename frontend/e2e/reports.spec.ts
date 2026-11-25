import { expect, test } from "@playwright/test";
import { API_HEADERS, loginAndWait, markReady, shirtAtCounter, sidebar } from "./helpers";

test.describe.configure({ mode: "serial" });

const API = { Accept: "application/json", Referer: "http://localhost:3000/" };
const fa = (n: number) => new Intl.NumberFormat("fa-IR").format(n);
/** Rial amounts shown in toman, the demo organization's display unit. */
const toman = (rial: number) => fa(rial / 10);

test("the manager follows today's sales on the dashboard and exports the sales report", async ({ page }) => {
  // The counter takes a one-shirt order (99,000 toman with VAT).
  await loginAndWait(page, "cashier");
  await page.goto("/pos");
  await page.getByLabel("جست‌وجوی مشتری با نام یا موبایل").fill("محمد");
  await page.locator(".ant-select-dropdown:visible").getByText("محمد حسینی").click();
  await page.getByRole("button", { name: "پیراهن", exact: true }).click();
  await page.getByRole("dialog", { name: "پیراهن" }).getByRole("button", { name: /خشکشویی/ }).click();
  await page.getByRole("button", { name: "ثبت سفارش" }).click();
  await expect(page.getByTestId("ticket-number")).toBeVisible();
  await page.context().clearCookies();

  await loginAndWait(page, "manager");
  const dashboard = (await (await page.request.get("/api/v1/reports/dashboard", { headers: API })).json()).data;
  expect(dashboard.today.orders).toBeGreaterThan(0);
  const tile = (title: string) => page.locator(".ant-statistic:visible").filter({ has: page.getByText(title, { exact: true }) });
  await expect(tile("سفارش امروز")).toContainText(fa(dashboard.today.orders));
  await expect(tile("فروش امروز")).toContainText(`${toman(dashboard.today.sales)}تومان`);
  await expect(page.getByRole("link", { name: `در جریان: ${fa(dashboard.workload.in_process)}` })).toBeVisible();

  // One bar a day; today's carries its sales and orders.
  const chart = page.getByRole("figure", { name: /نمودار ستونی فروش/ });
  await expect(chart.getByRole("listitem")).toHaveCount(14);
  await expect(chart.getByRole("listitem").last()).toHaveAccessibleName(new RegExp(`${toman(dashboard.today.sales)} تومان، ${fa(dashboard.today.orders)} سفارش`));
  await page.getByTitle("جدول").click();
  await expect(page.locator(".ant-table-tbody .ant-table-row:visible").first()).toContainText(toman(dashboard.today.sales));

  // The sales report, this Jalali month by day.
  await sidebar(page).getByRole("link", { name: "گزارش فروش" }).click();
  await expect(page.getByRole("heading", { name: "گزارش فروش" })).toBeVisible();
  await expect(page.getByRole("row", { name: /جمع کل/ })).toBeVisible();
  await page.locator(".ant-picker-range").click();
  await page.locator(".ant-picker-presets").getByText("امروز", { exact: true }).click();
  await expect(tile("فروش")).toContainText(toman(dashboard.today.sales));
  await expect(page.locator(".ant-table-tbody .ant-table-row:visible")).toHaveCount(1);

  // By item: the shirt is there, and the note explains what line amounts are.
  await page.locator(".ant-segmented-item").filter({ hasText: "کالا" }).click();
  await expect(page).toHaveURL(/group=item/);
  await expect(page.getByRole("row", { name: /پیراهن/ })).toBeVisible();
  await expect(page.getByText(/پیش از تخفیف و مالیات/)).toBeVisible();

  const download = page.waitForEvent("download");
  await page.getByRole("button", { name: "خروجی اکسل" }).click();
  expect((await download).suggestedFilename()).toMatch(/^sales-item-(\d{4}-\d{2}-\d{2})-\1\.xlsx$/);
});

test("a cashier sees no sales figures and cannot open the report", async ({ page }) => {
  await loginAndWait(page, "cashier");
  await expect(page.getByRole("heading", { name: "سلام علی رضایی" })).toBeVisible();
  await expect(page.getByText("فروش امروز")).toBeHidden();
  await expect(sidebar(page).getByRole("link", { name: "گزارش فروش" })).toBeHidden();
  await page.goto("/reports");
  await expect(page.getByText("دسترسی ندارید")).toBeVisible();
});

test("the manager reads how the workshop did: cycle time and on-time delivery", async ({ page }) => {
  const tag = await shirtAtCounter(page);
  const ticket = tag.slice(0, tag.lastIndexOf("-"));

  // The manager marks the shirt ready at the counter.
  await loginAndWait(page, "manager");
  const order = (await (await page.request.get(`/api/v1/orders/lookup?code=${ticket}`, { headers: API_HEADERS })).json()).data;
  await page.goto(`/orders/${order.id}`);
  await markReady(page);

  await sidebar(page).getByRole("link", { name: "گزارش تولید" }).click();
  await expect(page.getByRole("heading", { name: "گزارش تولید" })).toBeVisible();
  const today = new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Tehran" });
  const report = (await (await page.request.get(`/api/v1/reports/production?from=${today}&to=${today}`, { headers: API_HEADERS })).json()).data;
  await page.locator(".ant-picker-range").click();
  await page.locator(".ant-picker-presets").getByText("امروز", { exact: true }).click();
  const tile = (title: string) => page.locator(".ant-statistic:visible").filter({ has: page.getByText(title, { exact: true }) });
  await expect(tile("سفارش آماده‌شده")).toContainText(new Intl.NumberFormat("fa-IR").format(report.summary.ready));
  await expect(tile("آماده به‌موقع")).toContainText("٪");
  await expect(page.getByRole("figure", { name: /درصد تحویل به‌موقع/ }).getByRole("listitem")).toHaveCount(1); // today only
});
