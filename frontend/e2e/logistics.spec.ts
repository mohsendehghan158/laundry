import { expect, test } from "@playwright/test";
import { loginAndWait } from "./helpers";

// Map tiles are not part of what is tested; the maps work without them.
test.beforeEach(async ({ page }) => {
  await page.route(/tile\.openstreetmap\.org/, (route) => route.fulfill({ status: 204 }));
});

test("a branch manager sees their service zone and sets up a weekly window", async ({ page }) => {
  await loginAndWait(page, "manager");
  await page.goto("/logistics/zones");
  // The demo zone of the manager's branch, not the west branch's.
  await expect(page.getByText("شمال و مرکز")).toBeVisible();
  await expect(page.getByText("غرب", { exact: true })).toHaveCount(0);
  await expect(page.locator(".leaflet-container")).toBeVisible();

  await page.goto("/logistics/slots");
  await expect(page.getByRole("heading", { name: "برنامه هفتگی" })).toBeVisible();
  // Friday has no demo windows; add one.
  await page.getByRole("button", { name: "افزودن بازه جمعه" }).click();
  const dialog = page.getByRole("dialog");
  await dialog.getByLabel("از ساعت").fill("۱۰:۰۰");
  await dialog.getByLabel("تا ساعت").fill("12:00");
  await dialog.getByLabel("ظرفیت").fill("3");
  await dialog.getByRole("button", { name: "ذخیره" }).click();
  await expect(dialog).toBeHidden();
  await expect(page.getByText("۱۰:۰۰–۱۲:۰۰")).toBeVisible();

  // Overlapping windows on one day are refused.
  await page.getByRole("button", { name: "افزودن بازه جمعه" }).click();
  await dialog.getByLabel("از ساعت").fill("11:00");
  await dialog.getByLabel("تا ساعت").fill("13:00");
  await dialog.getByRole("button", { name: "ذخیره" }).click();
  await expect(dialog.getByText("این بازه با بازه دیگری از همین روز هم‌پوشانی دارد.")).toBeVisible();
});

test.describe("customer addresses", () => {
  // Vanak, inside the demo zone "شمال و مرکز".
  test.use({ geolocation: { latitude: 35.7575, longitude: 51.41 }, permissions: ["geolocation"] });

  test("a customer saves an address from their location and sees that it is served", async ({ page }) => {
    await page.goto("/my/login");
    await page.getByLabel("شماره موبایل").fill("09194445566");
    await page.getByRole("button", { name: "دریافت کد ورود" }).click();
    await page.getByRole("textbox").first().click();
    await page.keyboard.type("12345");
    await expect(page).toHaveURL(/\/my$/);

    await page.getByText("نشانی‌ها").click();
    await expect(page).toHaveURL(/\/my\/addresses$/);
    await page.getByRole("button", { name: /افزودن نشانی/ }).click();
    await page.getByRole("button", { name: /موقعیت فعلی من/ }).click();
    await expect(page.getByText("در محدوده سرویس «شمال و مرکز»")).toBeVisible();
    await page.getByLabel("عنوان").fill("محل کار");
    await page.getByLabel("نشانی کامل").fill("ونک، خیابان ملاصدرا، پلاک ۲۰");
    await page.getByRole("button", { name: "ذخیره" }).click();

    const card = page.locator(".ant-card", { hasText: "محل کار" });
    await expect(card).toContainText("ونک، خیابان ملاصدرا، پلاک ۲۰");
    await expect(card).toContainText("در محدوده سرویس «شمال و مرکز»");
    await expect(card).toContainText("هزینه ارسال: ۳۰٬۰۰۰ تومان");
  });
});
