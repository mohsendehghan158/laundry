import { expect, type Page } from "@playwright/test";

/** Every seeded demo account uses this password (DatabaseSeeder::PASSWORD). */
export const PASSWORD = "password";

export async function login(page: Page, username: string, password = PASSWORD) {
  await page.goto("/login");
  await page.getByLabel("نام کاربری").fill(username);
  await page.getByLabel("رمز عبور").fill(password);
  await page.getByRole("button", { name: "ورود" }).click();
}

export async function loginAndWait(page: Page, username: string) {
  await login(page, username);
  await expect(page).toHaveURL(/\/dashboard$/);
}

export function sidebar(page: Page) {
  return page.locator(".ant-layout-sider");
}
