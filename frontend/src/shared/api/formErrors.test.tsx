import { act, render, renderHook } from "@testing-library/react";
import { Form, Input, type FormInstance } from "antd";
import { describe, expect, it } from "vitest";
import { ApiError } from "./client";
import { applyFormErrors, errorMessage } from "./formErrors";

describe("applyFormErrors", () => {
  it("puts 422 field errors on the matching fields, folding array indexes", () => {
    let form!: FormInstance;
    function Fields() {
      [form] = Form.useForm();
      return (
        <Form form={form}>
          <Form.Item name="code"><Input /></Form.Item>
          <Form.Item name="branch_ids"><Input /></Form.Item>
          <Form.Item name={["tax", "vat_percent"]}><Input /></Form.Item>
        </Form>
      );
    }
    render(<Fields />);
    const error = new ApiError(422, {
      message: "invalid",
      errors: { code: ["کد تکراری است."], "branch_ids.0": ["شعبه نامعتبر است."], "tax.vat_percent": ["نامعتبر"] },
    });

    let applied = false;
    act(() => {
      applied = applyFormErrors(form, error);
    });

    expect(applied).toBe(true);
    expect(form.getFieldError("code")).toEqual(["کد تکراری است."]);
    expect(form.getFieldError("branch_ids")).toEqual(["شعبه نامعتبر است."]);
    expect(form.getFieldError(["tax", "vat_percent"])).toEqual(["نامعتبر"]);
  });

  it("ignores errors that are not validation errors", () => {
    const { result } = renderHook(() => Form.useForm()[0]);
    expect(applyFormErrors(result.current, new ApiError(409, { message: "conflict" }))).toBe(false);
    expect(applyFormErrors(result.current, new Error("boom"))).toBe(false);
  });
});

describe("errorMessage", () => {
  it("prefers the server's message", () => {
    expect(errorMessage(new ApiError(409, { message: "آخرین شعبه را نمی‌توان حذف کرد." }), "fallback")).toBe(
      "آخرین شعبه را نمی‌توان حذف کرد.",
    );
    expect(errorMessage(new Error("network"), "fallback")).toBe("fallback");
  });
});
