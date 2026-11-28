import { expect, test } from "@playwright/test";
import { loginAndWait } from "./helpers";

test("the owner sets up a hotel contract and its orders get contract prices and a department", async ({ page }) => {
  await loginAndWait(page, "admin");

  // A contract price list at 10% off the default prices.
  await page.goto("/catalog");
  await page.getByRole("tab", { name: "ماتریس قیمت" }).click();
  await page.getByRole("button", { name: "لیست قیمت قراردادی جدید" }).click();
  const listDialog = page.getByRole("dialog", { name: "لیست قیمت قراردادی جدید" });
  await listDialog.getByLabel("نام لیست").fill("قرارداد هتل‌ها");
  await listDialog.getByLabel("درصد تغییر نسبت به قیمت پیش‌فرض").fill("-10");
  await listDialog.getByRole("button", { name: "ذخیره" }).click();
  await expect(listDialog).toBeHidden();
  await expect(page.getByText(/قرارداد هتل‌ها \(.+ قیمت اختصاصی\)/)).toBeVisible();

  await page.goto("/companies");
  await page.getByRole("button", { name: "سازمان جدید" }).click();
  const form = page.getByRole("dialog", { name: "سازمان جدید" });
  await form.getByLabel("نام سازمان").fill("هتل آزادی");
  await form.getByLabel("شناسه ملی").fill("10101234565");
  await form.getByLabel("لیست قیمت قرارداد").click();
  await page.locator(".ant-select-dropdown:visible").getByText("قرارداد هتل‌ها").click();
  await form.getByLabel("سقف اعتبار").fill("1000000");
  await form.getByRole("button", { name: "ذخیره" }).click();
  await expect(page).toHaveURL(/\/companies\/\d+$/);
  await expect(page.getByRole("heading", { name: /هتل آزادی/ })).toBeVisible();

  await page.getByLabel("نام واحد جدید").fill("خانه‌داری");
  await page.getByRole("button", { name: "افزودن واحد" }).click();
  await expect(page.getByRole("cell", { name: "خانه‌داری" })).toBeVisible();
  await page.getByLabel("افزودن مشتری").click();
  await page.getByLabel("افزودن مشتری").fill("آرمان");
  await page.locator(".ant-select-dropdown:visible").getByText(/شرکت آرمان/).click();
  await expect(page.getByRole("link", { name: "شرکت آرمان" })).toBeVisible();

  // At the counter: the company, its credit and department; the shirt at 81,000 + VAT.
  await page.goto("/pos");
  await page.getByLabel("جست‌وجوی مشتری با نام یا موبایل").fill("آرمان");
  await page.locator(".ant-select-dropdown:visible").getByText("شرکت آرمان").click();
  await expect(page.getByText("هتل آزادی")).toBeVisible();
  await expect(page.getByText("اعتبار باقی‌مانده: ۱٬۰۰۰٬۰۰۰ تومان")).toBeVisible();
  await page.getByLabel("واحد سازمان").click();
  await page.locator(".ant-select-dropdown:visible").getByText("خانه‌داری").click();
  await page.getByRole("button", { name: "پیراهن", exact: true }).click();
  await page.getByRole("dialog", { name: "پیراهن" }).getByRole("button", { name: /خشکشویی/ }).click();
  await expect(page.getByTestId("pos-total")).toHaveText("۸۹٬۱۰۰ تومان");
  await page.getByRole("button", { name: "ثبت سفارش" }).click();
  await expect(page.getByTestId("ticket-number")).toBeVisible();
});

test("the hotel's goods come in on a delivery note and the month is invoiced in one click", async ({ page }) => {
  await loginAndWait(page, "admin");
  await page.goto("/companies");
  await page.getByRole("link", { name: "هتل آزادی" }).click();

  await page.getByRole("button", { name: "حواله ورود" }).click();
  const note = page.getByRole("dialog", { name: "حواله ورود" });
  await note.getByLabel("قلم").click();
  await page.locator(".ant-select-dropdown:visible").getByText("پیراهن", { exact: true }).click();
  await note.getByLabel("خدمت").click();
  await page.locator(".ant-select-dropdown:visible").getByText("خشکشویی").click();
  await note.getByLabel("اعلام سازمان").fill("۵");
  await note.getByLabel("شمارش ما").fill("۴");
  await note.getByRole("button", { name: "ذخیره" }).click();
  await expect(page.getByText("۱ مغایرت")).toBeVisible();

  await page.getByRole("button", { name: "تأیید و امضا" }).click();
  const confirm = page.getByRole("dialog", { name: /تأیید حواله/ });
  await confirm.getByLabel("نام تحویل‌گیرنده/تحویل‌دهنده سازمان").fill("خانم احمدی");
  await confirm.getByRole("button", { name: "تأیید و امضا" }).click();
  await expect(page.getByText("حواله تأیید شد.")).toBeVisible();

  // The statement has the counter order and the one from the note.
  const statement = page.locator(".ant-card").filter({ hasText: "صورت‌حساب دوره‌ای" });
  await expect(statement.getByRole("row")).toHaveCount(3);
  await statement.getByRole("button", { name: "صدور فاکتور گروهی" }).click();
  const popup = page.waitForEvent("popup");
  await page.locator(".ant-popconfirm").getByRole("button", { name: "صدور فاکتور" }).click();
  const invoice = await popup;
  await expect(invoice.getByRole("heading", { name: "صورتحساب فروش کالا و خدمات" })).toBeVisible();
  await expect(invoice.getByText("هتل آزادی")).toBeVisible();
  await invoice.close();
  await expect(statement.getByRole("button", { name: "صدور فاکتور گروهی" })).toBeDisabled();
});
