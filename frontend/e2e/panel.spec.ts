import { expect, test } from "@playwright/test";
import { authUser, branches, mockApi, withSessionCookie } from "./mock-api";

test("without a session the panel sends you to the login page", async ({ page }) => {
  await page.goto("/users?page=2");
  await expect(page).toHaveURL(/\/login\?next=%2Fusers%3Fpage%3D2$/);
  await expect(page.getByRole("button", { name: "ورود" })).toBeVisible();
});

test("signing in shows the field error, then the dashboard", async ({ page }) => {
  await mockApi(page, { me: null });
  await page.goto("/login");

  await page.getByLabel("نام کاربری").fill("admin");
  await page.getByLabel("رمز عبور").fill("wrong");
  await page.getByRole("button", { name: "ورود" }).click();
  await expect(page.getByText("نام کاربری یا رمز عبور نادرست است.")).toBeVisible();

  // The real API sets the session cookie on success; do the same here.
  await page.context().addCookies([{ name: "laundry_session", value: "test", url: page.url() }]);
  await page.getByLabel("رمز عبور").fill("password");
  await page.getByRole("button", { name: "ورود" }).click();

  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByRole("heading", { name: "مدیر سامانه عزیز، خوش آمدید" })).toBeVisible();
  await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
});

test("the menu follows permissions and forbidden pages say so", async ({ page, context, baseURL }) => {
  await withSessionCookie(context, baseURL!);
  await mockApi(page, { me: authUser({ name: "علی رضایی", roles: ["cashier"], permissions: ["branches.view"] }) });

  await page.goto("/dashboard");
  const isDesktop = (page.viewportSize()?.width ?? 0) >= 992;
  if (!isDesktop) await page.getByRole("button", { name: "menu" }).click();

  await expect(page.getByRole("menuitem", { name: "شعبه‌ها" })).toBeVisible();
  await expect(page.getByRole("menuitem", { name: "کاربران" })).toHaveCount(0);

  await page.goto("/users");
  await expect(page.getByText("به این بخش دسترسی ندارید.")).toBeVisible();
});

test("branches list and creating a branch", async ({ page, context, baseURL }) => {
  await withSessionCookie(context, baseURL!);
  let created: unknown = null;
  await mockApi(page, {
    onRequest: (method, path, body) => {
      if (method === "POST" && path === "/branches") {
        const branch = body as { code: string };
        if (branch.code === "T1") {
          return { status: 422, body: { message: "invalid", code: "validation_failed", errors: { code: ["این کد قبلاً ثبت شده است."] } } };
        }
        created = body;
        return { status: 201, body: { data: { ...branches[1], ...(body as object), id: 3 } } };
      }
    },
  });

  await page.goto("/branches");
  await expect(page.getByRole("cell", { name: "شعبه غرب" })).toBeVisible();

  await page.getByRole("button", { name: "شعبه جدید" }).click();
  const drawer = page.getByRole("dialog");
  await drawer.getByLabel("نام", { exact: true }).fill("شعبه شرق");
  await drawer.getByLabel("کد").fill("t1");
  await drawer.getByRole("button", { name: "ذخیره" }).click();
  await expect(drawer.getByText("این کد قبلاً ثبت شده است.")).toBeVisible();

  await drawer.getByLabel("کد").fill("t۳");
  await drawer.getByRole("button", { name: "ذخیره" }).click();
  await expect(page.getByText("ایجاد شد.")).toBeVisible();
  expect(created).toMatchObject({ name: "شعبه شرق", code: "T3", type: "store", is_active: true });
});

test("filters live in the address bar", async ({ page, context, baseURL }) => {
  await withSessionCookie(context, baseURL!);
  const requests: string[] = [];
  page.on("request", (request) => {
    if (request.url().includes("/api/v1/branches")) requests.push(decodeURIComponent(request.url()));
  });
  await mockApi(page);

  await page.goto("/branches?q=غرب");
  await expect(page.getByRole("cell", { name: "شعبه غرب" })).toBeVisible();
  expect(requests.some((url) => url.includes("filter[q]=غرب"))).toBe(true);
});
