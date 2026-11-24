import { expect, test } from "@playwright/test";
import { loginAndWait } from "./helpers";

test("the owner prices a service in the matrix and it is kept", async ({ page }) => {
  await loginAndWait(page, "admin");
  await page.goto("/catalog");

  // Skirts have no washing price in the demo catalog.
  const cell = page.getByRole("spinbutton", { name: "دامن – شست‌وشو" });
  await expect(cell).toHaveValue("");
  await cell.fill("۸۵۰۰۰");
  await expect(page.getByText("۱ تغییر ذخیره‌نشده")).toBeVisible();
  await page.getByRole("button", { name: "ذخیره" }).click();
  await expect(page.getByText("تغییرات ذخیره شد.")).toBeVisible();

  await page.reload();
  await expect(page.getByRole("spinbutton", { name: "دامن – شست‌وشو" })).toHaveValue("۸۵٬۰۰۰");

  await page.goto("/activity?area=catalog");
  await expect(page.getByRole("row", { name: /تغییر قیمت/ }).first()).toBeVisible();
});

test("the owner adds an item that then appears in the matrix", async ({ page }) => {
  await loginAndWait(page, "admin");
  await page.goto("/catalog?tab=items");
  await page.getByRole("button", { name: "قلم جدید" }).click();

  const dialog = page.getByRole("dialog");
  await dialog.getByLabel("نام").fill("شال");
  await dialog.getByRole("button", { name: "ذخیره" }).click();
  await expect(page.getByText("با موفقیت افزوده شد.")).toBeVisible();

  await page.getByRole("tab", { name: "ماتریس قیمت" }).click();
  await expect(page.getByRole("spinbutton", { name: "شال – خشکشویی" })).toBeVisible();
});

test("a cashier can read prices but not change them", async ({ page }) => {
  await loginAndWait(page, "cashier");
  await page.goto("/catalog");
  await expect(page.getByText("شما فقط اجازه مشاهده کاتالوگ را دارید.")).toBeVisible();
  await expect(page.getByRole("row", { name: /پیراهن/ })).toContainText("۹۰٬۰۰۰");
  await expect(page.getByRole("spinbutton")).toHaveCount(0);
});
