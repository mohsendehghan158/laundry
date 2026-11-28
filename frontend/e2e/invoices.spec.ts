import { expect, test } from "@playwright/test";
import { API_HEADERS, loginAndWait } from "./helpers";

test.describe.configure({ mode: "serial" });

test("the owner sets the seller printed on invoices", async ({ page }) => {
  await loginAndWait(page, "admin");
  await page.goto("/settings");
  await page.getByRole("tab", { name: "فاکتور رسمی" }).click();
  await page.getByLabel("شناسه ملی / کد ملی").fill("۱۰۱۰۱۲۳۴۵۶۵");
  await page.getByLabel("کد اقتصادی").fill("411234567890");
  await page.getByLabel("نشانی").fill("تهران، ونک");
  await page.getByRole("button", { name: "ذخیره" }).click();
  await expect(page.getByText("ذخیره شد")).toBeVisible();
});

test("a branch manager issues an invoice for an order and an accountant voids it", async ({ page }) => {
  await loginAndWait(page, "manager");
  await page.goto("/pos");
  await page.getByLabel("جست‌وجوی مشتری با نام یا موبایل").fill("محمد");
  await page.locator(".ant-select-dropdown:visible").getByText("محمد حسینی").click();
  await page.getByRole("button", { name: "پیراهن", exact: true }).click();
  await page.getByRole("dialog", { name: "پیراهن" }).getByRole("button", { name: /خشکشویی/ }).click();
  await page.getByRole("button", { name: "ثبت سفارش" }).click();
  const ticket = (await page.getByTestId("ticket-number").textContent())!;
  const order = (await (await page.request.get(`/api/v1/orders/lookup?code=${ticket}`, { headers: API_HEADERS })).json()).data;

  await page.goto(`/orders/${order.id}`);
  await page.getByRole("button", { name: "صدور فاکتور" }).click();
  const dialog = page.getByRole("dialog", { name: "صدور فاکتور رسمی" });
  await expect(dialog.getByLabel("نام و نام خانوادگی")).toHaveValue("محمد حسینی");
  await dialog.getByText("شخص حقوقی").click();
  await dialog.getByLabel("نام شرکت / سازمان").fill("هتل آزادی");
  await dialog.getByLabel("شناسه ملی").fill("10101234566");
  const popup = page.waitForEvent("popup");
  await dialog.getByRole("button", { name: "صدور فاکتور" }).click();
  await expect(dialog.getByText("شناسه ملی معتبر نیست.")).toBeVisible();
  await dialog.getByLabel("شناسه ملی").fill("۱۰۱۰۱۲۳۴۵۶۵");
  await dialog.getByRole("button", { name: "صدور فاکتور" }).click();
  await (await popup).close();
  await expect(page.getByText(/فاکتور شماره .+ صادر شد/)).toBeVisible();
  const invoiceButton = page.getByRole("button", { name: /فاکتور [۰-۹]+$/ });
  await expect(invoiceButton).toBeVisible();
  await expect(page.getByRole("button", { name: "صدور فاکتور" })).toHaveCount(0);

  const invoice = (await (await page.request.get(`/api/v1/orders/${order.id}`, { headers: API_HEADERS })).json()).data.invoice;
  await page.goto(`/print/invoices/${invoice.id}?auto=0`);
  await expect(page.getByRole("heading", { name: "صورتحساب فروش کالا و خدمات" })).toBeVisible();
  await expect(page.getByText("هتل آزادی")).toBeVisible();
  await expect(page.getByText("۴۱۱۲۳۴۵۶۷۸۹۰")).toBeVisible();
  // One shirt, 900,000 rials + 90,000 VAT.
  await expect(page.getByText("نهصد و نود هزار ریال")).toBeVisible();
  await page.screenshot({ path: "test-results/invoice.png", fullPage: true });

  await page.context().clearCookies();
  await loginAndWait(page, "accountant");
  await page.goto("/invoices");
  const row = page.getByRole("row").filter({ hasText: "هتل آزادی" });
  await expect(row.getByText("صادرشده")).toBeVisible();
  await row.getByRole("button", { name: "ابطال" }).click();
  const voiding = page.getByRole("dialog");
  await voiding.getByLabel("دلیل ابطال").fill("نام خریدار اشتباه بود");
  await voiding.getByRole("button", { name: "ابطال" }).click();
  await expect(page.getByText("فاکتور باطل شد.")).toBeVisible();
  await expect(row.getByText("باطل", { exact: true })).toBeVisible();
});
