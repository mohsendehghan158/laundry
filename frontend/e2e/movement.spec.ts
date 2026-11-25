import { expect, test } from "@playwright/test";
import { loginAndWait, shirtAtCounter, shirtsAtCounter } from "./helpers";

test.describe.configure({ mode: "serial" });

/** A 1×1 JPEG, enough for the upload's type check. */
const JPEG = Buffer.from("/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=", "base64");

test("the counter sends a transfer to the plant, which checks in what arrived", async ({ page }) => {
  const [first, second] = await shirtsAtCounter(page, 2);

  await loginAndWait(page, "cashier");
  await page.goto("/transfers");
  await page.getByRole("button", { name: "بارنامه جدید" }).click();
  const dialog = page.getByRole("dialog");
  await dialog.getByLabel("به شعبه").click();
  await page.locator(".ant-select-dropdown:visible").getByText("کارگاه مرکزی").click();
  await dialog.getByRole("button", { name: "شروع" }).click();
  await expect(page).toHaveURL(/\/transfers\/\d+$/);
  const transferUrl = page.url();
  const number = (await page.getByRole("heading", { level: 3 }).locator(".ltr").textContent())!;

  const scan = page.getByLabel("کد قطعه", { exact: true });
  await scan.fill(`${first} ${second} NOPE-1`);
  await scan.press("Enter");
  await expect(page.getByTestId("tag-results")).toContainText("قطعه‌ای با این کد پیدا نشد.");
  await expect(page.getByText("فهرست قطعه‌ها (۲)")).toBeVisible();
  await page.getByRole("button", { name: "ارسال" }).click();
  await expect(page.getByText("بارنامه فرستاده شد.")).toBeVisible();
  await expect(page.locator(".ant-tag").filter({ hasText: "در راه" })).toBeVisible();

  // The manifest that goes with the bag.
  await page.goto(`/print/transfers/${transferUrl.split("/").pop()}?auto=0`);
  await expect(page.getByText(`بارنامه ${number}`)).toBeVisible();
  await expect(page.getByText(second, { exact: true })).toBeVisible();
  await page.context().clearCookies();

  // At the plant, one shirt comes out of the bag; the other is missing.
  await loginAndWait(page, "operator");
  await page.goto("/transfers");
  await page.getByRole("link", { name: number }).click();
  await page.getByLabel("کد قطعه", { exact: true }).fill(first);
  await page.getByLabel("کد قطعه", { exact: true }).press("Enter");
  await expect(page.getByTestId("tag-results")).toContainText(first);
  await page.getByRole("button", { name: "بستن بارنامه" }).click();
  const closing = page.getByRole("dialog");
  await expect(closing.getByText("۱ قطعه اسکن نشده است و «در راه» می‌ماند.")).toBeVisible();
  await closing.getByLabel("کیسه‌های شمرده‌شده:").fill("1");
  await closing.getByRole("button", { name: "تأیید دریافت" }).click();
  await expect(page.getByText("بارنامه بسته شد.")).toBeVisible();
  await expect(page.getByText("۱ قطعه نرسیده است")).toBeVisible();

  await page.goto(`/pieces?tag=${second}`);
  await expect(page.getByText(`در بارنامه ${number} به مقصد «کارگاه مرکزی» در راه است.`)).toBeVisible();
  await page.goto(`/pieces?tag=${first}`);
  await expect(page.getByText("در «کارگاه مرکزی»، هنوز در ایستگاهی اسکن نشده است.")).toBeVisible();
});

test("a manager sends a rug to a vendor and checks it back in", async ({ page }) => {
  const tag = await shirtAtCounter(page);

  await loginAndWait(page, "manager");
  await page.goto("/outsourcing");
  await page.getByRole("button", { name: "ارسال به پیمانکار" }).click();
  const dialog = page.getByRole("dialog");
  await dialog.getByLabel("پیمانکار").click();
  await page.locator(".ant-select-dropdown:visible").getByText("قالیشویی نگین").click();
  await dialog.getByLabel("کد قطعه", { exact: true }).fill(tag);
  await dialog.getByLabel("کد قطعه", { exact: true }).press("Enter");
  await dialog.getByRole("button", { name: "ارسال ۱ قطعه" }).click();
  await expect(page.getByText(/^OS-\d{5} فرستاده شد\.$/)).toBeVisible();

  const row = page.getByRole("row", { name: /قالیشویی نگین/ });
  await expect(row).toContainText("۰ / ۱");
  await row.click();
  await page.getByLabel("اسکن قطعه‌های برگشتی").first().fill(tag);
  await page.getByLabel("اسکن قطعه‌های برگشتی").first().press("Enter");
  await page.getByRole("tab", { name: "برگشته" }).click();
  await expect(page.getByRole("tabpanel", { name: "برگشته" }).getByRole("row", { name: /قالیشویی نگین/ })).toContainText("۱ / ۱");
});

test("a lost piece is reported with a photo, then found", async ({ page }) => {
  const tag = await shirtAtCounter(page);

  await loginAndWait(page, "manager");
  await page.goto(`/pieces?tag=${tag}`);
  await page.locator('input[type="file"]').setInputFiles({ name: "piece.jpg", mimeType: "image/jpeg", buffer: JPEG });
  await expect(page.getByText("عکس ذخیره شد.")).toBeVisible();
  await expect(page.getByRole("img", { name: new RegExp(tag) })).toBeVisible();

  await page.getByRole("button", { name: "ثبت خسارت یا مفقودی" }).click();
  const dialog = page.getByRole("dialog", { name: "پرونده جدید" });
  await dialog.locator(".ant-radio-button-wrapper").filter({ hasText: "مفقودی" }).click();
  await dialog.getByLabel("شرح").fill("در قفسه پیدا نشد");
  await dialog.getByRole("button", { name: "ثبت پرونده" }).click();
  await expect(page.getByText(/^پرونده CL-\d{5} ثبت شد\.$/)).toBeVisible();
  await expect(page.getByText("مفقود اعلام شده است.")).toBeVisible();

  await page.goto("/claims");
  const row = page.getByRole("row", { name: new RegExp(tag) });
  await row.getByRole("button", { name: "پیدا شد" }).click();
  await page.getByLabel("نحوه رسیدگی").fill("پشت دستگاه اتو پیدا شد");
  await page.getByRole("button", { name: "تأیید" }).click();
  await page.locator(".ant-segmented-item").filter({ hasText: "ردشده" }).click();
  await expect(page.getByRole("row", { name: new RegExp(tag) })).toContainText("پشت دستگاه اتو پیدا شد");

  await page.goto(`/pieces?tag=${tag}`);
  await expect(page.locator(".ant-timeline-item").first()).toContainText("پیدا شد");
});
