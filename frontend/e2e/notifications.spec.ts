import { expect, test } from "@playwright/test";
import { loginAndWait, markReady } from "./helpers";

test.describe.configure({ mode: "serial" });

test("the customer is texted with a tracking link that opens without signing in", async ({ page, browser }) => {
  await loginAndWait(page, "cashier");
  await page.goto("/pos");
  await page.getByLabel("جست‌وجوی مشتری با نام یا موبایل").fill("امیر");
  await page.locator(".ant-select-dropdown:visible").getByText("امیر جعفری").click();
  await page.getByRole("button", { name: "پیراهن", exact: true }).click();
  await page.getByRole("dialog", { name: "پیراهن" }).getByRole("button", { name: /خشکشویی/ }).click();
  await page.getByRole("button", { name: "ثبت سفارش" }).click();
  const ticket = (await page.getByTestId("ticket-number").textContent())!.trim();
  await page.getByRole("button", { name: "مشاهده سفارش" }).click();
  await expect(page).toHaveURL(/\/orders\/\d+$/);
  const orderId = page.url().split("/").pop();
  // Sanctum treats requests with the site as referer as the signed-in browser.
  const order = await (await page.request.get(`/api/v1/orders/${orderId}`, { headers: { Accept: "application/json", Referer: "http://localhost:3000/" } })).json();

  await markReady(page);
  await expect(page.getByRole("heading", { level: 3 }).getByText("آماده تحویل")).toBeVisible();

  // The branch manager sees both messages in the log.
  await page.context().clearCookies();
  await loginAndWait(page, "manager");
  await page.goto("/notifications");
  const rows = page.getByRole("row").filter({ hasText: ticket });
  await expect(rows).toHaveCount(2);
  await rows.filter({ hasText: "ثبت سفارش" }).getByText("بیشتر").click();
  await expect(rows.filter({ hasText: "ثبت سفارش" })).toContainText(`/track/${order.data.public_id}`);
  await expect(rows.filter({ hasText: "آماده تحویل" }).locator(".ant-tag")).toHaveText("ارسال‌شده");

  // Anyone with the link sees the status, without signing in and without personal details.
  const guest = await browser.newContext({ locale: "fa-IR", timezoneId: "Asia/Tehran" });
  const tracking = await guest.newPage();
  await tracking.goto(`/track/${order.data.public_id}`);
  await expect(tracking.getByText(ticket)).toBeVisible();
  await expect(tracking.getByText("سفارش شما آماده است؛ برای دریافت آن به شعبه مراجعه کنید.")).toBeVisible();
  await expect(tracking.getByText("امیر جعفری")).toHaveCount(0);
  await tracking.goto("/track/01JZZZZZZZZZZZZZZZZZZZZZZZ");
  await expect(tracking.getByText("سفارشی با این نشانی پیدا نشد.")).toBeVisible();
  await guest.close();
});

test("the owner rewords a message and switches one off", async ({ page }) => {
  await loginAndWait(page, "admin");
  await page.goto("/notifications?tab=templates");
  const received = page.locator(".ant-card").filter({ has: page.getByText("ثبت سفارش", { exact: true }) }).last();
  const text = received.getByRole("textbox");
  await text.fill("سفارش {ticket} ثبت شد. ");
  await received.getByText("{link}").click();
  await expect(received.getByText("سفارش T1-00042 ثبت شد. https://example.com/track/…")).toBeVisible();
  await received.getByRole("button", { name: "ذخیره" }).click();
  await expect(page.getByText("تغییرات ذخیره شد.")).toBeVisible();

  const cancelled = page.locator(".ant-card").filter({ has: page.getByText("لغو سفارش", { exact: true }) }).last();
  await cancelled.getByRole("switch").click();
  await cancelled.getByRole("button", { name: "ذخیره" }).click();
  await expect(page.getByText("تغییرات ذخیره شد.").last()).toBeVisible();

  await page.reload();
  await expect(page.locator(".ant-card").filter({ has: page.getByText("ثبت سفارش", { exact: true }) }).last().getByRole("textbox")).toHaveValue("سفارش {ticket} ثبت شد. {link}");
  await expect(page.locator(".ant-card").filter({ has: page.getByText("لغو سفارش", { exact: true }) }).last().getByRole("switch")).not.toBeChecked();
});
