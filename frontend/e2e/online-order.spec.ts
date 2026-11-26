import { expect, test } from "@playwright/test";
import { loginAndWait } from "./helpers";

// Map tiles are not part of what is tested; the maps work without them.
test.beforeEach(async ({ page }) => {
  await page.route(/tile\.openstreetmap\.org/, (route) => route.fulfill({ status: 204 }));
});

// Vanak, inside the demo zone "شمال و مرکز" of the central branch.
test.use({ geolocation: { latitude: 35.7575, longitude: 51.41 }, permissions: ["geolocation"] });

test("a customer orders a pickup online, the branch sees it, and the customer cancels it", async ({ page, browser }) => {
  // فاطمه رحیمی, a demo customer without a point on the map yet.
  await page.goto("/my/login");
  await page.getByLabel("شماره موبایل").fill("09122223344");
  await page.getByRole("button", { name: "دریافت کد ورود" }).click();
  await expect(page.getByText("کد ۵ رقمی به شماره ۰۹۱۲۲۲۲۳۳۴۴ پیامک شد.")).toBeVisible();
  await page.getByRole("textbox").first().click();
  await page.keyboard.type("12345");
  await expect(page).toHaveURL(/\/my$/);

  // Without a served address the order page asks for one first.
  await page.getByRole("button", { name: "سفارش جدید" }).click();
  await expect(page.getByText("برای سفارش درب منزل، اول نشانی‌ای در محدوده سرویس ثبت کنید.")).toBeVisible();
  await page.getByRole("button", { name: "افزودن نشانی" }).click();
  await expect(page).toHaveURL(/\/my\/addresses$/);
  await page.getByRole("button", { name: /افزودن نشانی/ }).click();
  await page.getByRole("button", { name: /موقعیت فعلی من/ }).click();
  await expect(page.getByText("در محدوده سرویس «شمال و مرکز»")).toBeVisible();
  await page.getByLabel("عنوان").fill("خانه");
  await page.getByLabel("نشانی کامل").fill("ونک، خیابان ملاصدرا، پلاک ۱۲");
  await page.getByRole("button", { name: "ذخیره" }).click();
  await expect(page.locator(".ant-card", { hasText: "ونک، خیابان ملاصدرا، پلاک ۱۲" })).toBeVisible();

  await page.goto("/my/orders/new");
  // The demo address has no point on the map, so the new one is picked.
  await expect(page.getByRole("radio", { name: /ونک، خیابان ملاصدرا/ })).toBeChecked();
  // The first window offered, whichever day that is.
  await page.locator(".ant-radio-button-wrapper").first().click();
  await page.getByRole("spinbutton", { name: /^پیراهن — / }).first().fill("3");
  await expect(page.locator(".ant-descriptions-item", { hasText: "هزینه ارسال" })).toContainText("۳۰٬۰۰۰ تومان");
  await page.getByRole("textbox", { name: "توضیحات برای راننده" }).fill("زنگ دوم");
  await page.getByRole("button", { name: "ثبت سفارش" }).click();

  await expect(page).toHaveURL(/\/my\/orders\/[0-9a-z]{26}$/i);
  // The order form stays mounted, hidden, after navigating away from it.
  const pickup = page.locator(".ant-card", { hasText: "زمان جمع‌آوری" }).filter({ visible: true });
  await expect(pickup).toContainText("ونک، خیابان ملاصدرا، پلاک ۱۲");
  await expect(page.getByText("منتظر جمع‌آوری").first()).toBeVisible();
  const ticket = (await page.locator(".ltr").filter({ hasText: /^T\d-\d{5}$/ }).first().textContent())!.trim();

  // The central branch's manager, in another browser, sees it waiting for pickup.
  const panel = await (await browser.newContext({ baseURL: "http://localhost:3000", locale: "fa-IR", timezoneId: "Asia/Tehran" })).newPage();
  await loginAndWait(panel, "manager");
  await panel.goto("/orders");
  const row = panel.getByRole("row", { name: new RegExp(ticket) });
  await expect(row).toContainText("آنلاین");
  await expect(row).toContainText("منتظر جمع‌آوری");
  await row.getByRole("link", { name: ticket }).click();
  await expect(panel.locator(".ant-card", { hasText: "جمع‌آوری از درب منزل" })).toContainText("ونک، خیابان ملاصدرا، پلاک ۱۲");
  await expect(panel.getByText("زنگ دوم")).toBeVisible();
  await panel.context().close();

  // Back in the portal, the customer calls it off.
  await page.goto("/my");
  await page.getByRole("link", { name: ticket }).click();
  await page.getByRole("button", { name: "لغو سفارش" }).click();
  await page.getByRole("tooltip").getByRole("button", { name: "لغو سفارش" }).click();
  await expect(page.getByText("سفارش لغو شد.")).toBeVisible();
  await expect(page.getByText("این سفارش لغو شده است.")).toBeVisible();
  await expect(page.getByRole("button", { name: "لغو سفارش" })).toHaveCount(0);
});
