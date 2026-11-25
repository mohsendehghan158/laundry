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

/**
 * On an order's page: "ready" ticks off every piece and racks the order,
 * in the given slot (typed into the slot picker) or the suggested one.
 */
export async function markReady(page: Page, slot?: string) {
  await page.getByRole("button", { name: "آماده شد" }).click();
  const dialog = page.getByRole("dialog", { name: "مونتاژ و آماده‌سازی سفارش" });
  await dialog.getByRole("button", { name: "همه حاضرند" }).click();
  if (slot) {
    await dialog.getByLabel("خانه قفسه").click();
    await dialog.getByLabel("خانه قفسه").fill(slot);
    await page.locator(".ant-select-dropdown:visible").getByText(slot, { exact: false }).first().click();
  }
  await dialog.getByRole("button", { name: "تکمیل مونتاژ و آماده" }).click();
  await expect(dialog).toBeHidden();
}
