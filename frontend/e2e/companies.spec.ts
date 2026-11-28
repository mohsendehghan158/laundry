import { expect, test } from "@playwright/test";
import { loginAndWait } from "./helpers";

test("the owner sets up a hotel contract and its orders get contract prices and a department", async ({ page }) => {
  await loginAndWait(page, "admin");

  // A contract price list at 10% off the default prices.
  await page.goto("/catalog");
  await page.getByRole("tab", { name: "ماتریس قیمت" }).click();
  await page.getByRole("button", { name: "لیست قیمت قراردادی جدید" }).click();
  const listDialog = page.getByRole("dialog", { name: "لیست قیمت قراردادی جدید" });
  await listDialog.getByLabel("نام لیست").fill("قرارداد هتل‌ها");
  await listDialog.getByLabel("درصد تغییر نسبت به قیمت پیش‌فرض").fill("-10");
  await listDialog.getByRole("button", { name: "ذخیره" }).click();
  await expect(listDialog).toBeHidden();
  await expect(page.getByText(/قرارداد هتل‌ها \(.+ قیمت اختصاصی\)/)).toBeVisible();

  await page.goto("/companies");
  await page.getByRole("button", { name: "سازمان جدید" }).click();
  const form = page.getByRole("dialog", { name: "سازمان جدید" });
  await form.getByLabel("نام سازمان").fill("هتل آزادی");
  await form.getByLabel("شناسه ملی").fill("10101234565");
  await form.getByLabel("لیست قیمت قرارداد").click();
  await page.locator(".ant-select-dropdown:visible").getByText("قرارداد هتل‌ها").click();
  await form.getByLabel("سقف اعتبار").fill("1000000");
  await form.getByRole("button", { name: "ذخیره" }).click();
  await expect(page).toHaveURL(/\/companies\/\d+$/);
  await expect(page.getByRole("heading", { name: /هتل آزادی/ })).toBeVisible();

  await page.getByLabel("نام واحد جدید").fill("خانه‌داری");
  await page.getByRole("button", { name: "افزودن واحد" }).click();
  await expect(page.getByRole("cell", { name: "خانه‌داری" })).toBeVisible();
  await page.getByLabel("افزودن مشتری").click();
  await page.getByLabel("افزودن مشتری").fill("آرمان");
  await page.locator(".ant-select-dropdown:visible").getByText(/شرکت آرمان/).click();
  await expect(page.getByRole("link", { name: "شرکت آرمان" })).toBeVisible();

  // At the counter: the company, its credit and department; the shirt at 81,000 + VAT.
  await page.goto("/pos");
  await page.getByLabel("جست‌وجوی مشتری با نام یا موبایل").fill("آرمان");
  await page.locator(".ant-select-dropdown:visible").getByText("شرکت آرمان").click();
  await expect(page.getByText("هتل آزادی")).toBeVisible();
  await expect(page.getByText("اعتبار باقی‌مانده: ۱٬۰۰۰٬۰۰۰ تومان")).toBeVisible();
  await page.getByLabel("واحد سازمان").click();
  await page.locator(".ant-select-dropdown:visible").getByText("خانه‌داری").click();
  await page.getByRole("button", { name: "پیراهن", exact: true }).click();
  await page.getByRole("dialog", { name: "پیراهن" }).getByRole("button", { name: /خشکشویی/ }).click();
  await expect(page.getByTestId("pos-total")).toHaveText("۸۹٬۱۰۰ تومان");
  await page.getByRole("button", { name: "ثبت سفارش" }).click();
  await expect(page.getByTestId("ticket-number")).toBeVisible();
});
