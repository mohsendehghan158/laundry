import { expect, test } from "@playwright/test";
import { loginAndWait } from "./helpers";

test.describe.configure({ mode: "serial" });

test("the owner creates a branch", async ({ page }) => {
  await loginAndWait(page, "admin");
  await page.goto("/branches");
  await page.getByRole("button", { name: "شعبه جدید" }).click();

  const dialog = page.getByRole("dialog");
  await dialog.getByLabel("نام").fill("شعبه شرق");
  // Persian digits and lower case are normalized to "E1".
  await dialog.getByLabel("کد").fill("e۱");
  await dialog.getByLabel("تلفن").fill("۰۲۱-۷۷۰۰۱۱۲۲");
  await dialog.getByRole("button", { name: "ذخیره" }).click();

  await expect(page.getByText("با موفقیت افزوده شد.")).toBeVisible();
  const row = page.getByRole("row", { name: /شعبه شرق/ });
  await expect(row).toContainText("E1");
  await expect(row).toContainText("021-77001122");
});

test("a duplicate branch code shows the server's error on the field", async ({ page }) => {
  await loginAndWait(page, "admin");
  await page.goto("/branches");
  await page.getByRole("button", { name: "شعبه جدید" }).click();
  const dialog = page.getByRole("dialog");
  await dialog.getByLabel("نام").fill("شعبه تکراری");
  await dialog.getByLabel("کد").fill("T1");
  await dialog.getByRole("button", { name: "ذخیره" }).click();
  await expect(dialog.locator(".ant-form-item-explain-error")).toBeVisible();
  await expect(dialog).toBeVisible();
});

test("the owner creates a user who can then sign in", async ({ page, browser }) => {
  await loginAndWait(page, "admin");
  await page.goto("/users");
  await page.getByRole("button", { name: "کاربر جدید" }).click();

  const dialog = page.getByRole("dialog");
  await dialog.getByLabel("نام و نام خانوادگی").fill("زهرا محمدی");
  await dialog.getByLabel("نام کاربری").fill("zahra");
  await dialog.getByLabel("موبایل").fill("۰۹۱۲۳۴۵۶۷۸۹");
  await dialog.getByLabel("رمز عبور").fill("Secret-pass-123");

  // Multiple selects stay open after a pick; clicking the title closes them.
  const closeDropdown = () => dialog.locator(".ant-modal-title").click();
  await dialog.getByLabel("نقش‌ها").click();
  await page.locator(".ant-select-dropdown:visible").getByText("پذیرش و صندوق", { exact: true }).click();
  await closeDropdown();
  await dialog.getByLabel("شعبه‌ها").click();
  await page.locator(".ant-select-dropdown:visible").getByText("شعبه شرق (E1)").click();
  await closeDropdown();
  await dialog.getByRole("button", { name: "ذخیره" }).click();

  await expect(page.getByText("با موفقیت افزوده شد.")).toBeVisible();
  const row = page.getByRole("row", { name: /زهرا محمدی/ });
  await expect(row).toContainText("پذیرش و صندوق");
  await expect(row).toContainText("شعبه شرق");

  const other = await browser.newContext();
  const second = await other.newPage();
  await second.goto("/login");
  await second.getByLabel("نام کاربری").fill("zahra");
  await second.getByLabel("رمز عبور").fill("Secret-pass-123");
  await second.getByRole("button", { name: "ورود" }).click();
  await expect(second.getByRole("heading", { name: "سلام زهرا محمدی" })).toBeVisible();
  await expect(second.getByText("شعبه شرق")).toBeVisible();
  await other.close();
});

test("the activity log records what happened", async ({ page }) => {
  await loginAndWait(page, "admin");
  await page.goto("/activity");
  await expect(page.getByRole("heading", { name: "لاگ فعالیت" })).toBeVisible();
  await expect(page.getByRole("row", { name: /شعبه شرق/ }).first()).toContainText("ایجاد");
  await expect(page.getByRole("row", { name: /زهرا محمدی/ }).first()).toBeVisible();
});

test("settings can be changed and are kept", async ({ page }) => {
  await loginAndWait(page, "admin");
  await page.goto("/settings");
  await page.getByRole("tab", { name: "مالیات" }).click();
  const vat = page.getByLabel("نرخ مالیات بر ارزش افزوده (درصد)");
  await vat.fill("9");
  await page.getByRole("tabpanel", { name: "مالیات" }).getByRole("button", { name: "ذخیره" }).click();
  await expect(page.getByText("تغییرات ذخیره شد.")).toBeVisible();

  await page.reload();
  await page.getByRole("tab", { name: "مالیات" }).click();
  await expect(page.getByLabel("نرخ مالیات بر ارزش افزوده (درصد)")).toHaveValue("۹");
});

test("the panel works on a phone @mobile", async ({ page }) => {
  await loginAndWait(page, "admin");
  await page.getByRole("button", { name: "باز کردن منو" }).click();
  await page.locator(".ant-layout-sider").getByRole("link", { name: "شعبه‌ها" }).click();
  await expect(page).toHaveURL(/\/branches$/);
  await expect(page.getByRole("heading", { name: "شعبه‌ها" })).toBeVisible();
});
