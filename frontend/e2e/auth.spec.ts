import { expect, test } from "@playwright/test";
import { login, loginAndWait, sidebar } from "./helpers";

test("rejects a wrong password with a Persian message", async ({ page }) => {
  await login(page, "admin", "wrong-password");
  await expect(page.getByRole("alert").filter({ hasText: /./ })).toContainText("نام کاربری یا رمز عبور نادرست است.");
  await expect(page).toHaveURL(/\/login/);
});

test("sends a signed-out visitor to login and back to the page they asked for", async ({ page }) => {
  await page.goto("/users");
  await expect(page).toHaveURL(/\/login\?next=%2Fusers$/);
  await page.getByLabel("نام کاربری").fill("admin");
  await page.getByLabel("رمز عبور").fill("password");
  await page.getByRole("button", { name: "ورود" }).click();
  await expect(page).toHaveURL(/\/users$/);
  await expect(page.getByRole("heading", { name: "کاربران" })).toBeVisible();
});

test("the owner sees every management page in the menu", async ({ page }) => {
  await loginAndWait(page, "admin");
  await expect(page.getByRole("heading", { name: "سلام مدیر سامانه" })).toBeVisible();
  for (const name of ["داشبورد", "کاتالوگ و قیمت", "شعبه‌ها", "کاربران", "نقش‌ها و مجوزها", "تنظیمات", "لاگ فعالیت"]) {
    await expect(sidebar(page).getByRole("link", { name })).toBeVisible();
  }
});

test("a cashier sees the dashboard and catalog but cannot open management pages", async ({ page }) => {
  await loginAndWait(page, "cashier");
  await expect(sidebar(page).getByRole("link", { name: "داشبورد" })).toBeVisible();
  await expect(sidebar(page).getByRole("link", { name: "کاتالوگ و قیمت" })).toBeVisible();
  await expect(sidebar(page).getByRole("link", { name: "کاربران" })).toHaveCount(0);
  await expect(sidebar(page).getByRole("link", { name: "شعبه‌ها" })).toHaveCount(0);

  await page.goto("/users");
  await expect(page.getByText("دسترسی ندارید")).toBeVisible();
});

test("a branch manager sees users and roles but not branch editing", async ({ page }) => {
  await loginAndWait(page, "manager");
  await expect(sidebar(page).getByRole("link", { name: "کاربران" })).toBeVisible();
  await page.goto("/branches");
  await expect(page.getByRole("heading", { name: "شعبه‌ها" })).toBeVisible();
  await expect(page.getByRole("button", { name: "شعبه جدید" })).toHaveCount(0);
});

test("signing out ends the session", async ({ page }) => {
  await loginAndWait(page, "admin");
  await page.getByTestId("user-menu").click();
  await page.getByRole("menuitem", { name: "خروج" }).click();
  await expect(page).toHaveURL(/\/login$/);
  await page.goto("/dashboard");
  await expect(page).toHaveURL(/\/login/);
});
