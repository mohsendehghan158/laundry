import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "@/shared/api/client";
import { renderWithProviders } from "@/test/render";
import { portalKeys } from "../api";
import { PortalLogin } from "./PortalLogin";

const replace = vi.fn();
let next: string | null = null;
vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace, push: vi.fn() }),
  useSearchParams: () => new URLSearchParams(next ? { next } : {}),
}));

const get = vi.fn();
const post = vi.fn();
vi.mock("@/shared/api/client", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/shared/api/client")>()),
  api: { get: (...args: unknown[]) => get(...args), post: (...args: unknown[]) => post(...args), put: vi.fn(), delete: vi.fn() },
}));

const customer = { id: 7, name: "", mobile: "09123456789", needs_name: true };

function renderLogin() {
  const result = renderWithProviders(<PortalLogin />);
  result.queryClient.setQueryData(portalKeys.customer, null);
  result.queryClient.setQueryData(portalKeys.settings, { organization_name: "خشکشویی نمونه", currency_unit: "toman" });
  return result;
}

async function typeCode(code: string) {
  const user = userEvent.setup();
  const boxes = await screen.findAllByRole("textbox");
  await user.click(boxes[0]);
  await user.keyboard(code);
}

beforeEach(() => {
  replace.mockReset();
  get.mockReset();
  post.mockReset();
  next = null;
});

describe("PortalLogin", () => {
  it("texts a code to the number as typed in Persian digits, then signs in with it", async () => {
    next = "/my/orders/01JD3ZKX";
    post.mockImplementation(async (path: string) =>
      path === "/customer/auth/otp" ? { data: { mobile: "09123456789", expires_in: 300, resend_in: 60 } } : { data: customer },
    );
    renderLogin();

    await userEvent.type(screen.getByLabelText("شماره موبایل"), "۰۹۱۲ ۳۴۵ ۶۷۸۹");
    await userEvent.click(screen.getByRole("button", { name: "دریافت کد ورود" }));

    expect(await screen.findByText("کد ۵ رقمی به شماره ۰۹۱۲۳۴۵۶۷۸۹ پیامک شد.")).toBeInTheDocument();
    expect(post).toHaveBeenCalledWith("/customer/auth/otp", { mobile: "09123456789" });
    expect(screen.getByText(/ارسال دوباره تا/)).toBeInTheDocument();

    // The fifth digit sends the code without pressing the button.
    await typeCode("۱۲۳۴۵");
    await waitFor(() => expect(post).toHaveBeenCalledWith("/customer/auth/verify", { mobile: "09123456789", code: "12345" }));
    await waitFor(() => expect(replace).toHaveBeenCalledWith("/my/orders/01JD3ZKX"));
  });

  it("refuses a malformed number without asking the server", async () => {
    renderLogin();
    await userEvent.type(screen.getByLabelText("شماره موبایل"), "12345");
    await userEvent.click(screen.getByRole("button", { name: "دریافت کد ورود" }));

    expect(await screen.findByText("شماره موبایل را مثل ۰۹۱۲۱۲۳۴۵۶۷ وارد کنید.")).toBeInTheDocument();
    expect(post).not.toHaveBeenCalled();
  });

  it("shows why a code was refused and stays on the code step", async () => {
    post.mockImplementation(async (path: string) => {
      if (path === "/customer/auth/otp") {
        return { data: { mobile: "09123456789", expires_in: 300, resend_in: 60 } };
      }
      throw new ApiError(422, { code: "otp_wrong", message: "کد نادرست است. 4 بار دیگر می‌توانید امتحان کنید." });
    });
    renderLogin();

    await userEvent.type(screen.getByLabelText("شماره موبایل"), "09123456789");
    await userEvent.click(screen.getByRole("button", { name: "دریافت کد ورود" }));
    await typeCode("11111");

    expect(await screen.findByRole("alert")).toHaveTextContent("کد نادرست است. 4 بار دیگر می‌توانید امتحان کنید.");
    expect(screen.getByRole("button", { name: "تغییر شماره" })).toBeInTheDocument();
    // The boxes are emptied for another try.
    await waitFor(() => expect(screen.getAllByRole("textbox").map((box) => (box as HTMLInputElement).value).join("")).toBe(""));
    expect(replace).not.toHaveBeenCalled();
  });

  it("explains a refused code request, e.g. asking again too soon", async () => {
    post.mockRejectedValue(new ApiError(429, { code: "otp_too_soon", message: "کد قبلی همین الان فرستاده شد." }));
    renderLogin();

    await userEvent.type(screen.getByLabelText("شماره موبایل"), "09123456789");
    await userEvent.click(screen.getByRole("button", { name: "دریافت کد ورود" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("کد قبلی همین الان فرستاده شد.");
  });
});
