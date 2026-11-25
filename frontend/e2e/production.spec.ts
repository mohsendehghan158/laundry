import { expect, test, type Page } from "@playwright/test";
import { loginAndWait } from "./helpers";

test.describe.configure({ mode: "serial" });

const API = { Accept: "application/json", Referer: "http://localhost:3000/" };

/** The counter takes a one-shirt order; returns the shirt's tag code. */
async function shirtAtCounter(page: Page): Promise<string> {
  await loginAndWait(page, "cashier");
  await page.goto("/pos");
  await page.getByLabel("جست‌وجوی مشتری با نام یا موبایل").fill("محمد");
  await page.locator(".ant-select-dropdown:visible").getByText("محمد حسینی").click();
  await page.getByRole("button", { name: "پیراهن", exact: true }).click();
  await page.getByRole("dialog", { name: "پیراهن" }).getByRole("button", { name: /خشکشویی/ }).click();
  await page.getByRole("button", { name: "ثبت سفارش" }).click();
  const ticket = (await page.getByTestId("ticket-number").textContent())!;
  const order = (await (await page.request.get(`/api/v1/orders/lookup?code=${ticket}`, { headers: API })).json()).data;
  await page.context().clearCookies();
  return order.lines[0].pieces[0].tag_code;
}

async function chooseStation(page: Page, name: string) {
  await page.getByLabel("انتخاب ایستگاه").click();
  await page.locator(".ant-select-dropdown:visible").getByText(name).click();
}

test("the plant scans a piece through its stations and anyone can find it", async ({ page }) => {
  const tag = await shirtAtCounter(page);

  await loginAndWait(page, "operator");
  await page.goto("/station");
  await chooseStation(page, "شست‌وشو و خشکشویی · کارگاه مرکزی");
  const scan = page.getByLabel("کد قطعه", { exact: true });
  await scan.fill(tag.toLowerCase());
  await scan.press("Enter");
  const result = page.getByTestId("scan-result");
  await expect(result).toHaveAttribute("data-level", "warn");
  await expect(result).toContainText(tag);
  await expect(result).toContainText("پیراهن · خشکشویی");
  await expect(result).toContainText("قطعه بدون بارنامه از «شعبه مرکزی» به اینجا آمده است.");
  await expect(page.locator(".ant-statistic").filter({ hasText: "اسکن‌های امروز من" })).toContainText("۱");

  // The scanner fires twice: counted once.
  await scan.fill(tag);
  await scan.press("Enter");
  await expect(result).toContainText("همین الان در این ایستگاه ثبت شده بود");

  // Quality control fails it for rework, then passes it.
  await chooseStation(page, "کنترل کیفیت · کارگاه مرکزی");
  await page.locator(".ant-segmented-item").filter({ hasText: "رد" }).click();
  await scan.fill(tag);
  await scan.press("Enter");
  await expect(page.getByText("برای رد قطعه، اول دلیل یا عیب را مشخص کنید.")).toBeVisible();
  await page.getByLabel("دلیل رد").fill("لکه یقه مانده");
  await scan.fill(tag);
  await scan.press("Enter");
  await expect(result).toHaveAttribute("data-level", "ok");
  await expect(result).toContainText("دوباره‌کاری");
  await page.locator(".ant-segmented-item").filter({ hasText: "قبول" }).click();
  await scan.fill(tag);
  await scan.press("Enter");
  await expect(result).not.toContainText("دوباره‌کاری");

  // Batch: two codes, one unknown.
  await page.locator(".ant-segmented-item").filter({ hasText: "دسته‌ای" }).click();
  await scan.fill(`${tag} NOPE-404`);
  await scan.press("Enter");
  await page.getByRole("button", { name: "ثبت ۲ قطعه" }).click();
  await expect(result).toContainText("۱ قطعه ثبت شد، ۱ مورد ناموفق");
  await expect(result).toContainText("قطعه‌ای با این کد پیدا نشد.");
  await page.context().clearCookies();

  // At the counter, the quick search finds where the piece is.
  await loginAndWait(page, "cashier");
  await page.getByLabel("اسکن یا کد قطعه").fill(tag);
  await page.getByLabel("اسکن یا کد قطعه").press("Enter");
  await expect(page).toHaveURL(/\/pieces\?tag=/);
  await expect(page.getByText("در «کارگاه مرکزی»، آخرین بار در ایستگاه «کنترل کیفیت».")).toBeVisible();
  await expect(page.locator(".ant-timeline-item")).toHaveCount(4);
  await expect(page.locator(".ant-timeline-item").first()).toContainText("قبول در کنترل کیفیت");
  await expect(page.getByText("رد در کنترل کیفیت")).toBeVisible();
  await expect(page.getByText("لکه یقه مانده")).toBeVisible();
});

test("a manager adds a station and holds a piece", async ({ page }) => {
  const tag = await shirtAtCounter(page);

  await loginAndWait(page, "manager");
  await page.goto("/stations");
  await page.getByRole("button", { name: "ایستگاه جدید" }).click();
  const dialog = page.getByRole("dialog");
  await dialog.getByLabel("نام").fill("لکه‌گیری");
  await dialog.getByLabel("کد").fill("spot");
  await dialog.getByRole("button", { name: "ذخیره" }).click();
  await expect(page.getByRole("row", { name: /لکه‌گیری/ })).toContainText("SPOT");

  await page.goto(`/pieces?tag=${tag}`);
  await page.getByRole("button", { name: "نگه داشتن" }).click();
  await page.getByLabel("دلیل (مثلاً منتظر تأیید مشتری)").fill("منتظر تأیید مشتری برای لکه‌بری");
  await page.getByRole("dialog").getByRole("button", { name: "نگه داشتن" }).click();
  await expect(page.locator(".ant-card-head").getByText("نگه‌داشته")).toBeVisible();

  await page.goto("/station");
  await chooseStation(page, "لکه‌گیری · شعبه مرکزی");
  await page.getByLabel("کد قطعه", { exact: true }).fill(tag);
  await page.getByLabel("کد قطعه", { exact: true }).press("Enter");
  await expect(page.getByTestId("scan-result")).toHaveAttribute("data-level", "error");
  await expect(page.getByTestId("scan-result")).toContainText("این قطعه نگه داشته شده است: منتظر تأیید مشتری برای لکه‌بری");
});
