import { expect, test } from "@playwright/test";
import { loginAndWait } from "./helpers";
import { xlsx } from "./xlsx";

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

test("the owner updates prices from an Excel file after reviewing the changes", async ({ page }) => {
  await loginAndWait(page, "admin");
  await page.goto("/catalog");

  const download = page.waitForEvent("download");
  await page.getByRole("button", { name: "دانلود اکسل" }).click();
  expect((await download).suggestedFilename()).toBe("price-list.xlsx");

  const items: Array<{ id: number; name: string }> = (
    await (await page.request.get("/api/v1/items", { headers: { Accept: "application/json", Referer: "http://localhost:3000/" } })).json()
  ).data;
  const id = (name: string) => items.find((item) => item.name === name)!.id;
  const sheet = xlsx([
    ["شناسه", "قلم", "خشکشویی [DC]", "فقط اتو [PR]"],
    // T-shirts: dry cleaning 70,000 → 75,000 (typed in Persian), ironing unchanged.
    [id("تی‌شرت"), "تی‌شرت", "۷۵٬۰۰۰", 30000],
    // Knitwear: dry cleaning unchanged, ironing newly offered.
    [id("ژاکت و بافت"), "ژاکت و بافت", 150000, 60000],
  ]);
  await page.locator('input[type="file"]').setInputFiles({ name: "prices.xlsx", mimeType: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", buffer: sheet });

  const dialog = page.getByRole("dialog", { name: /بررسی تغییرات/ });
  await expect(dialog.getByRole("row", { name: /تی‌شرت/ })).toContainText("۷۰٬۰۰۰");
  await expect(dialog.getByRole("row", { name: /تی‌شرت/ })).toContainText("۷۵٬۰۰۰");
  await expect(dialog.getByRole("row", { name: /ژاکت و بافت/ })).toContainText("تازه");
  await dialog.getByRole("button", { name: "اعمال ۲ تغییر" }).click();
  await expect(page.getByText("۲ قیمت به‌روزرسانی شد.")).toBeVisible();

  await expect(page.getByRole("spinbutton", { name: "تی‌شرت – خشکشویی" })).toHaveValue("۷۵٬۰۰۰");
  await expect(page.getByRole("spinbutton", { name: "ژاکت و بافت – فقط اتو" })).toHaveValue("۶۰٬۰۰۰");
});

test("a cashier can read prices but not change them", async ({ page }) => {
  await loginAndWait(page, "cashier");
  await page.goto("/catalog");
  await expect(page.getByText("شما فقط اجازه مشاهده کاتالوگ را دارید.")).toBeVisible();
  await expect(page.getByRole("row", { name: /پیراهن/ })).toContainText("۹۰٬۰۰۰");
  await expect(page.getByRole("spinbutton")).toHaveCount(0);
});
