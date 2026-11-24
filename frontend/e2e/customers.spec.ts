import { expect, test } from "@playwright/test";
import { loginAndWait } from "./helpers";

test("a cashier registers a customer and finds them again by part of the mobile", async ({ page }) => {
  await loginAndWait(page, "cashier");
  await page.goto("/customers");
  await page.getByRole("button", { name: "مشتری جدید" }).click();

  const dialog = page.getByRole("dialog");
  await dialog.getByLabel("نام").fill("سعید نیک‌نام");
  await dialog.getByLabel("موبایل").fill("۰۹۱۲ ۷۷۷ ۸۸۹۹");
  await dialog.getByLabel("هشدار").fill("فقط با هماهنگی تلفنی تحویل شود.");
  await dialog.getByRole("button", { name: "افزودن نشانی" }).click();
  await dialog.getByLabel("عنوان").fill("خانه");
  await dialog.getByLabel("نشانی", { exact: true }).fill("تهران، شهرک غرب، خیابان ایران‌زمین");
  await dialog.getByRole("button", { name: "ذخیره" }).click();

  // Saving opens the new customer's page.
  await expect(page.getByRole("heading", { name: "سعید نیک‌نام" })).toBeVisible();
  await expect(page.getByText("09127778899").first()).toBeVisible();
  await expect(page.getByText("فقط با هماهنگی تلفنی تحویل شود.")).toBeVisible();
  await expect(page.getByText("تهران، شهرک غرب، خیابان ایران‌زمین")).toBeVisible();

  await page.goto("/customers");
  await page.getByPlaceholder("نام یا شماره موبایل").fill("۷۷۷۸۸");
  await page.getByPlaceholder("نام یا شماره موبایل").press("Enter");
  await expect(page.getByRole("row", { name: /سعید نیک‌نام/ })).toBeVisible();
  await expect(page.getByRole("row")).toHaveCount(2); // header + one match
});

test("a duplicate mobile is refused on the field", async ({ page }) => {
  await loginAndWait(page, "cashier");
  await page.goto("/customers");
  await page.getByRole("button", { name: "مشتری جدید" }).click();
  const dialog = page.getByRole("dialog");
  await dialog.getByLabel("نام").fill("تکراری");
  await dialog.getByLabel("موبایل").fill("09121112233");
  await dialog.getByRole("button", { name: "ذخیره" }).click();
  await expect(dialog.locator(".ant-form-item-explain-error")).toContainText("موبایل");
});
