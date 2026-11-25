import { expect, test } from "@playwright/test";
import { loginAndWait } from "./helpers";

test("a cashier prints an order, then hands it over by scanning a tag, in two parts", async ({ page }) => {
  await loginAndWait(page, "cashier");

  // Take an order of two shirts.
  await page.goto("/pos");
  await page.getByLabel("جست‌وجوی مشتری با نام یا موبایل").fill("زینب");
  await page.locator(".ant-select-dropdown:visible").getByText("زینب کاظمی").click();
  for (let i = 0; i < 2; i++) {
    await page.getByRole("button", { name: "پیراهن", exact: true }).click();
    await page.getByRole("dialog", { name: "پیراهن" }).getByRole("button", { name: /خشکشویی/ }).click();
  }
  await page.getByRole("button", { name: "ثبت سفارش" }).click();
  const ticket = (await page.getByTestId("ticket-number").textContent())!.trim();
  await page.getByRole("button", { name: "مشاهده سفارش" }).click();
  await expect(page).toHaveURL(/\/orders\/\d+$/);
  const orderId = page.url().split("/").pop();

  // Receipt and tags, without opening the print dialog.
  await page.goto(`/print/orders/${orderId}/receipt?auto=0`);
  await expect(page.getByText(ticket, { exact: true })).toBeVisible();
  await expect(page.getByRole("img", { name: ticket })).toBeVisible();
  await page.goto(`/print/orders/${orderId}/tags?auto=0`);
  await expect(page.getByTestId("tag")).toHaveCount(2);
  await expect(page.getByRole("img", { name: `${ticket}-02` })).toBeVisible();

  // The workshop finishes it and puts it on rack C-3.
  await page.goto(`/orders/${orderId}`);
  await page.getByRole("button", { name: "آماده شد" }).click();
  await page.getByRole("dialog").getByLabel("محل قفسه").fill("c-3");
  await page.getByRole("button", { name: "تأیید" }).click();
  await expect(page.locator(".ant-descriptions").getByText("C-3")).toBeVisible();

  // The customer comes back; the clerk scans the second shirt's tag.
  await page.goto("/handover");
  const scan = page.getByLabel("اسکن یا ورود شماره قبض / تگ");
  await scan.fill(`${ticket.toLowerCase()}-02`);
  await scan.press("Enter");
  await expect(page.getByText("C-3", { exact: true })).toBeVisible();
  await expect(page.getByText("مانده سفارش باید پیش از تحویل پرداخت شود.")).toBeVisible();
  await expect(page.getByRole("button", { name: "تحویل همه" })).toBeDisabled();

  await page.getByRole("button", { name: "دریافت وجه" }).click();
  const payment = page.getByRole("dialog").filter({ hasText: "روش" });
  await payment.locator(".ant-segmented-item").filter({ hasText: "کارت‌خوان" }).click();
  await payment.getByRole("button", { name: "ثبت دریافت" }).click();
  await expect(page.getByText("پرداخت ثبت شد.")).toBeVisible();

  // Only one shirt today.
  await page.getByRole("checkbox", { name: `${ticket}-01` }).uncheck();
  await page.getByRole("button", { name: "تحویل ۱ قطعه" }).click();
  await expect(page.getByText("قطعه‌های انتخاب‌شده تحویل شد؛ بقیه روی قفسه می‌ماند.")).toBeVisible();

  // The other one tomorrow, found by the ticket number this time.
  await scan.fill(ticket);
  await scan.press("Enter");
  await expect(page.getByRole("checkbox", { name: `${ticket}-02` })).toBeDisabled();
  await page.getByRole("button", { name: "تحویل همه" }).click();
  await expect(page.getByText("سفارش تحویل و بسته شد.")).toBeVisible();

  await page.goto(`/orders/${orderId}`);
  await expect(page.getByRole("heading", { level: 3 }).getByText("تحویل‌شده")).toBeVisible();
});
