import { expect, test } from "@playwright/test";
import { shirtAtCounter } from "./helpers";

/** OTP_FIXED_CODE in playwright.config.ts: every sign-in code is this. */
const CODE = "12345";

test("a customer signs in with a texted code and follows the order the counter took", async ({ page }) => {
  // The counter takes a shirt for محمد حسینی (09121112233), then signs out.
  await shirtAtCounter(page);

  // The portal has its own sign-in; the staff panel's does not apply.
  await page.goto("/my");
  await expect(page).toHaveURL(/\/my\/login$/);
  await page.getByLabel("شماره موبایل").fill("۰۹۱۲ ۱۱۱ ۲۲۳۳");
  await page.getByRole("button", { name: "دریافت کد ورود" }).click();
  await expect(page.getByText("کد ۵ رقمی به شماره ۰۹۱۲۱۱۱۲۲۳۳ پیامک شد.")).toBeVisible();

  // A wrong code is refused and the boxes empty for another try.
  await page.getByRole("textbox").first().click();
  await page.keyboard.type("54321");
  await expect(page.getByRole("alert").filter({ hasText: /./ })).toContainText("کد نادرست است");

  await page.getByRole("textbox").first().click();
  await page.keyboard.type(CODE);
  await expect(page).toHaveURL(/\/my$/);

  // A customer the counter already knows is not asked for a name.
  await expect(page.getByRole("heading", { name: "سفارش‌های من" })).toBeVisible();
  await expect(page.getByText("محمد حسینی")).toBeVisible();
  const newest = page.locator('a[href^="/my/orders/"]:not([href="/my/orders/new"])').first();
  await expect(newest).toContainText("پذیرش شد");
  await newest.click();

  await expect(page.getByText("پیراهن")).toBeVisible();
  await expect(page.getByText("اقلام", { exact: true })).toBeVisible();

  // Signing out of the portal leads back to its sign-in, and the staff panel stays closed.
  await page.getByRole("button", { name: "خروج" }).click();
  await expect(page).toHaveURL(/\/my\/login$/);
  await page.goto("/dashboard");
  await expect(page).toHaveURL(/\/login/);
});
