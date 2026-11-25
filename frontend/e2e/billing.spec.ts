import { expect, test, type Page } from "@playwright/test";
import { loginAndWait } from "./helpers";

test.describe.configure({ mode: "serial" });

/** Takes a one-shirt order (90,000 + 10% VAT = 99,000 toman) for a demo customer. */
async function takeShirtOrder(page: Page, extra?: (page: Page) => Promise<void>) {
  await page.goto("/pos");
  await page.getByLabel("جست‌وجوی مشتری با نام یا موبایل").fill("محمد");
  await page.locator(".ant-select-dropdown:visible").getByText("محمد حسینی").click();
  await page.getByRole("button", { name: "پیراهن", exact: true }).click();
  await page.getByRole("dialog", { name: "پیراهن" }).getByRole("button", { name: /خشکشویی/ }).click();
  await extra?.(page);
  await page.getByRole("button", { name: "ثبت سفارش" }).click();
  await expect(page.getByTestId("ticket-number")).toBeVisible();
}

test("a cashier opens the register, takes cash with change, and closes with a count", async ({ page }) => {
  await loginAndWait(page, "cashier");

  await page.goto("/register");
  await expect(page.getByText("صندوق بسته است")).toBeVisible();
  await page.getByLabel("موجودی اول صندوق").fill("۵۰۰۰۰");
  await page.getByRole("button", { name: "باز کردن صندوق" }).click();
  await expect(page.getByText("شیفت جاری")).toBeVisible();

  await takeShirtOrder(page);
  await page.getByRole("button", { name: "دریافت وجه" }).click();
  const dialog = page.getByRole("dialog").filter({ hasText: "روش" });
  await expect(dialog.getByLabel("مبلغ")).toHaveValue("۹۹٬۰۰۰");
  await dialog.getByLabel("دریافتی از مشتری").fill("100000");
  await expect(dialog.getByText("باقی‌مانده برای پس دادن: ۱٬۰۰۰ تومان")).toBeVisible();
  await dialog.getByRole("button", { name: "ثبت دریافت" }).click();
  await expect(page.getByText("پرداخت ثبت شد.")).toBeVisible();

  await page.goto("/register");
  // 50,000 float + 99,000 cash.
  await expect(page.getByText("۱۴۹٬۰۰۰").first()).toBeVisible();
  await page.getByRole("button", { name: "بستن صندوق" }).click();
  const closing = page.getByRole("dialog");
  await closing.getByLabel("نقد شمارش‌شده").fill("148000");
  await expect(closing.getByText("کسری: ۱٬۰۰۰ تومان")).toBeVisible();
  await closing.getByRole("button", { name: "بستن صندوق" }).click();
  await expect(page.getByText("صندوق بسته شد.")).toBeVisible();
  await expect(page.getByText("صندوق بسته است")).toBeVisible();
});

test("a card payment needs no open register, and a manager refunds it", async ({ page }) => {
  await loginAndWait(page, "cashier");
  await takeShirtOrder(page);
  await page.getByRole("button", { name: "دریافت وجه" }).click();
  const dialog = page.getByRole("dialog").filter({ hasText: "روش" });
  await dialog.getByText("نقد").click();
  await expect(dialog.getByText("صندوق این شعبه بسته است؛ برای دریافت نقدی اول آن را باز کنید.")).toBeVisible();
  await dialog.locator(".ant-segmented-item").filter({ hasText: "کارت‌خوان" }).click();
  await dialog.getByLabel("مبلغ").fill("50000");
  await dialog.getByRole("textbox").last().fill("۷۷۸۸۹۹");
  await dialog.getByRole("button", { name: "ثبت دریافت" }).click();
  await expect(page.getByText("پرداخت ثبت شد.")).toBeVisible();

  await page.goto("/orders");
  await page.getByRole("row").nth(1).getByRole("link").click();
  await expect(page).toHaveURL(/\/orders\/\d+$/);
  await expect(page.getByRole("heading", { level: 3 }).locator(".ant-tag").filter({ hasText: "بخشی پرداخت‌شده" })).toBeVisible();
  await expect(page.getByRole("button", { name: "بازپرداخت" })).toHaveCount(0);
  const url = page.url();

  await page.context().clearCookies();
  await loginAndWait(page, "manager");
  await page.goto(url);
  await page.getByRole("button", { name: "بازپرداخت" }).click();
  const refund = page.getByRole("dialog").filter({ hasText: "روش" });
  await refund.getByLabel("دلیل").fill("مشتری سفارش را لغو کرد");
  await refund.getByRole("button", { name: "ثبت بازپرداخت" }).click();
  await expect(page.getByRole("heading", { level: 3 }).locator(".ant-tag").filter({ hasText: "بازپرداخت‌شده" })).toBeVisible();

  await page.getByRole("button", { name: "لغو سفارش" }).click();
  await page.getByLabel("دلیل لغو").fill("لغو پس از بازپرداخت");
  await page.getByRole("button", { name: "تأیید" }).click();
  await expect(page.getByText("دلیل لغو: لغو پس از بازپرداخت")).toBeVisible();
});

test("the owner creates a promo code and the counter applies it", async ({ page }) => {
  await loginAndWait(page, "admin");
  await page.goto("/promotions");
  await page.getByRole("button", { name: "کد تخفیف جدید" }).click();
  const form = page.getByRole("dialog");
  await form.getByLabel("کد").fill("nowruz");
  await form.getByLabel("درصد تخفیف").fill("20");
  await form.getByRole("button", { name: "ذخیره" }).click();
  await expect(page.getByRole("row", { name: /NOWRUZ/ })).toContainText("۲۰٪");

  await page.context().clearCookies();
  await loginAndWait(page, "cashier");
  await takeShirtOrder(page, async (counter) => {
    await counter.getByLabel("کد تخفیف").fill("nowruz");
    await counter.getByLabel("کد تخفیف").press("Enter");
    // 90,000 − 20% = 72,000, +10% VAT.
    await expect(counter.getByTestId("pos-total")).toHaveText("۷۹٬۲۰۰ تومان");
  });

  await page.getByRole("button", { name: "سفارش جدید" }).click();
  await page.getByLabel("کد تخفیف").fill("WRONG");
  await page.getByLabel("کد تخفیف").press("Enter");
  await page.getByLabel("جست‌وجوی مشتری با نام یا موبایل").fill("محمد");
  await page.locator(".ant-select-dropdown:visible").getByText("محمد حسینی").click();
  await page.getByRole("button", { name: "پیراهن", exact: true }).click();
  await page.getByRole("dialog", { name: "پیراهن" }).getByRole("button", { name: /خشکشویی/ }).click();
  await expect(page.getByText("این کد تخفیف معتبر نیست.")).toBeVisible();
});
