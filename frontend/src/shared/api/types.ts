import type { components } from "./schema";

/**
 * Names for the generated API types (see `npm run api:types`). Anything that
 * comes from the server is typed from the OpenAPI document, so a breaking
 * change in the backend fails the frontend type check.
 */
type Schemas = components["schemas"];

export type AuthUser = Schemas["AuthUserResource"];
export type User = Schemas["UserResource"];
export type Branch = Schemas["BranchResource"];
export type BranchType = Schemas["BranchType"];
export type Role = Schemas["RoleResource"];
export type Activity = Schemas["ActivityResource"];
export type Organization = Schemas["OrganizationResource"];

export type StoreUserBody = Schemas["StoreUserRequest"];
export type UpdateUserBody = Schemas["UpdateUserRequest"];
export type StoreBranchBody = Schemas["StoreBranchRequest"];
export type UpdateBranchBody = Schemas["UpdateBranchRequest"];
export type UpdatePasswordBody = Schemas["UpdatePasswordRequest"];
export type UpdateSettingsBody = Schemas["UpdateSettingsRequest"];

/** Laravel's paginated resource collection. */
export interface Paginated<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
}

export interface Wrapped<T> {
  data: T;
}

export interface Settings {
  general: { currency_unit: "toman" | "rial"; timezone: string };
  tax: { vat_percent: number; prices_include_vat: boolean };
  receipt: { header_text: string | null; footer_text: string | null };
}

export interface PermissionGroup {
  group: string;
  permissions: string[];
}
