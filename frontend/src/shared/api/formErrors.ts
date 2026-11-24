import type { FormInstance } from "antd";
import { isApiError } from "./client";

/**
 * Copies a 422 response's field errors onto an antd form. Nested keys such
 * as "branch_ids.0" or "tax.vat_percent" become name paths. Returns true
 * when at least one field error was placed on the form, so the caller can
 * skip a generic error toast.
 */
export function applyFormErrors(form: FormInstance, error: unknown): boolean {
  if (!isApiError(error, 422) || Object.keys(error.errors).length === 0) {
    return false;
  }

  const known = new Set(
    form
      .getFieldsError()
      .map((field) => JSON.stringify(field.name)),
  );

  const fields = Object.entries(error.errors).map(([key, messages]) => {
    const path = key.split(".");
    // "branch_ids.0" belongs to the "branch_ids" field.
    while (path.length > 1 && !known.has(JSON.stringify(path)) && /^\d+$/.test(path[path.length - 1])) {
      path.pop();
    }
    return { name: path, errors: messages };
  });

  form.setFields(fields);
  return true;
}

/** The message to show for an error that has no better place. */
export function errorMessage(error: unknown, fallback: string): string {
  if (isApiError(error) && error.message) {
    return error.message;
  }
  return fallback;
}
