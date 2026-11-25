import type { FormInstance } from "antd";
import { isApiError } from "./errors";

/**
 * Puts the 422 field errors of an API response onto the matching form
 * fields. Returns true when the error was a validation error and has been
 * shown, so the caller does not need a separate message.
 * Nested keys such as "general.timezone" or "roles.0" map to form paths.
 */
export function applyServerErrors(form: FormInstance, error: unknown, fieldMap: Record<string, string[]> = {}): boolean {
  if (!isApiError(error) || !error.isValidation) return false;

  const fields = Object.entries(error.errors).map(([key, errors]) => {
    const parts = key.split(".");
    // "roles.0" belongs to the "roles" field.
    const name = fieldMap[key] ?? (/^\d+$/.test(parts.at(-1) ?? "") ? parts.slice(0, -1) : parts);
    return { name, errors };
  });

  if (fields.length === 0) return false;
  form.setFields(fields);
  return true;
}
