import { expect, test, type Page } from "@playwright/test";
import { loginAndWait, markReady } from "./helpers";

async function addItem(page: Page, category: string, item: string, service = "خشکشویی") {
  await page.locator(".ant-segmented-item").filter({ hasText: category }).click();
  await page.getByRole("button", { name: item, exact: true }).click();
  // Items priced for several services ask which one.
  const chooser = page.getByRole("dialog", { name: item });
  await chooser.getByRole("button", { name: new RegExp(service) }).click();
  await expect(chooser).toBeHidden();
}

test.describe.configure({ mode: "serial" });

test("a cashier takes an order for a new customer at the counter", async ({ page }) => {
  await loginAndWait(page, "cashier");
  await page.goto("/pos");

  // Register the customer straight from the search box.
  const search = page.getByLabel("جست‌وجوی مشتری با نام یا موبایل");
  await search.fill("۰۹۱۹۸۸۸۷۷۶۶");
  await page.getByRole("button", { name: /ثبت مشتری جدید با/ }).click();
  const form = page.getByRole("dialog");
  await expect(form.getByLabel("موبایل")).toHaveValue("09198887766");
  await form.getByLabel("نام").fill("نسرین شریفی");
  await form.getByRole("button", { name: "ذخیره" }).click();
  await expect(page.getByText("نسرین شریفی")).toBeVisible();

  await addItem(page, "لباس روزمره", "پیراهن");
  await addItem(page, "لباس روزمره", "پیراهن");
  await addItem(page, "کت و رسمی", "کت‌وشلوار");

  // Silk on the shirts, and describe the first shirt.
  const shirtLine = page.getByTestId("cart-line").filter({ hasText: "پیراهن" });
  await shirtLine.getByText(/ابریشم/).click();
  await shirtLine.getByRole("button", { name: /۲ قطعه/ }).click();
  const drawer = page.locator(".ant-drawer");
  await drawer.getByLabel("رنگ").first().click();
  await page.locator(".ant-select-dropdown:visible").getByText("آبی", { exact: true }).click();
  await drawer.getByText("پارگی").first().click();
  await drawer.getByRole("button", { name: "بستن" }).click();

  await page.locator(".ant-segmented-item").filter({ hasText: "فوری" }).click();

  // Shirts 2 × 90,000 + 30% silk = 234,000; suit 320,000; +50% express = 831,000; +10% VAT.
  await expect(page.getByTestId("pos-total")).toHaveText("۹۱۴٬۱۰۰ تومان");

  await page.getByRole("button", { name: "ثبت سفارش" }).click();
  await expect(page.getByTestId("ticket-number")).toHaveText(/^T1-\d{5}$/);
  const ticket = (await page.getByTestId("ticket-number").textContent())!;

  await page.getByRole("button", { name: "مشاهده سفارش" }).click();
  await expect(page.getByRole("heading", { name: new RegExp(ticket) })).toBeVisible();
  await expect(page.getByRole("row", { name: new RegExp(`${ticket}-01`) })).toContainText("آبی");
  await expect(page.getByRole("row", { name: new RegExp(`${ticket}-01`) })).toContainText("پارگی");
  await expect(page.getByRole("row", { name: new RegExp(`${ticket}-04`) })).toContainText("کت‌وشلوار");

  // The workshop finishes it.
  await markReady(page, "B-07");
  await expect(page.locator(".ant-tag").filter({ hasText: "آماده تحویل" })).toBeVisible();
  await expect(page.locator(".ant-descriptions").getByText("B-07")).toBeVisible();

  await page.goto("/orders");
  await page.getByPlaceholder("شماره قبض، نام یا موبایل مشتری").fill("نسرین");
  await page.getByPlaceholder("شماره قبض، نام یا موبایل مشتری").press("Enter");
  await expect(page.getByRole("row", { name: new RegExp(ticket) })).toContainText("آماده تحویل");
});

test("the counter refuses a discount above the cashier's limit", async ({ page }) => {
  await loginAndWait(page, "cashier");
  await page.goto("/pos");
  await page.getByLabel("جست‌وجوی مشتری با نام یا موبایل").fill("فاطمه");
  await page.locator(".ant-select-dropdown:visible").getByText("فاطمه رحیمی").click();
  await addItem(page, "لباس روزمره", "پیراهن");

  await page.getByRole("button", { name: "تخفیف" }).click();
  await page.locator(".ant-popover").getByRole("spinbutton").fill("۲۰");
  await page.locator(".ant-popover").getByRole("button", { name: "اعمال" }).click();

  await page.getByRole("button", { name: "ثبت سفارش" }).click();
  await expect(page.getByText("تخفیف بیشتر از سقف مجاز (10 درصد) است.")).toBeVisible();

  // Within the limit it goes through: 90,000 − 10% = 81,000, +10% VAT.
  await page.getByRole("button", { name: "تخفیف" }).click();
  await page.locator(".ant-popover").getByRole("spinbutton").fill("10");
  await page.locator(".ant-popover").getByRole("button", { name: "اعمال" }).click();
  await expect(page.getByTestId("pos-total")).toHaveText("۸۹٬۱۰۰ تومان");
  await page.getByRole("button", { name: "ثبت سفارش" }).click();
  await expect(page.getByTestId("ticket-number")).toBeVisible();
});

test("a branch manager cancels an order with a reason", async ({ page }) => {
  await loginAndWait(page, "manager");
  await page.goto("/orders");
  await page.getByRole("row").nth(1).getByRole("link").click();
  await page.getByRole("button", { name: "لغو سفارش" }).click();
  await expect(page.getByRole("button", { name: "تأیید" })).toBeDisabled();
  await page.getByLabel("دلیل لغو").fill("مشتری منصرف شد");
  await page.getByRole("button", { name: "تأیید" }).click();
  await expect(page.getByText("دلیل لغو: مشتری منصرف شد")).toBeVisible();
});
