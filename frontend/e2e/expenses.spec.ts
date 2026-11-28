import { expect, test } from "@playwright/test";
import { loginAndWait } from "./helpers";

test("a branch manager records an expense and sees it next to sales", async ({ page }) => {
  await loginAndWait(page, "manager");
  await page.goto("/expenses");
  await page.getByRole("button", { name: "ثبت هزینه" }).click();
  const dialog = page.getByRole("dialog", { name: "ثبت هزینه" });
  await dialog.getByLabel("دسته").click();
  await page.locator(".ant-select-dropdown:visible").getByText("مواد شوینده و مصرفی").click();
  await dialog.getByLabel("مبلغ").fill("۲۵۰۰۰۰");
  await dialog.getByText("کارت‌به‌کارت").click();
  await expect(dialog.getByText("از صندوق پرداخت شد")).toHaveCount(0);
  await dialog.getByLabel("شرح").fill("پودر لباسشویی");
  await dialog.getByRole("button", { name: "ذخیره" }).click();
  await expect(page.getByText("با موفقیت افزوده شد.")).toBeVisible();

  await expect(page.getByRole("row").filter({ hasText: "پودر لباسشویی" })).toContainText("۲۵۰٬۰۰۰");
  await expect(page.getByText("مواد شوینده و مصرفی: ۲۵۰٬۰۰۰ تومان")).toBeVisible();

  await page.goto("/reports");
  await expect(page.getByText("هزینه‌ها به‌تفکیک دسته")).toBeVisible();
  await expect(page.getByRole("columnheader", { name: "هزینه‌ها (تومان)" })).toBeVisible();
});
