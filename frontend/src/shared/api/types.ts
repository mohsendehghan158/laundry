/**
 * Friendly names for the types generated from the backend's OpenAPI
 * document (`npm run api:types`). Never edit schema.d.ts by hand: change
 * the backend, regenerate docs/api/openapi.json, then regenerate.
 */
import type { components, operations } from "./schema";

type Schemas = components["schemas"];

export type AuthUser = Schemas["AuthUserResource"];
export type Branch = Schemas["BranchResource"];
export type BranchType = Schemas["BranchType"];
export type User = Schemas["UserResource"];
export type Role = Schemas["RoleResource"];
export type Activity = Schemas["ActivityResource"];
export type Organization = Schemas["OrganizationResource"];

export type LoginInput = Schemas["LoginRequest"];
export type StoreBranchInput = Schemas["StoreBranchRequest"];
export type UpdateBranchInput = Schemas["UpdateBranchRequest"];
export type StoreUserInput = Schemas["StoreUserRequest"];
export type UpdateUserInput = Schemas["UpdateUserRequest"];
export type UpdatePasswordInput = Schemas["UpdatePasswordRequest"];
export type UpdateSettingsInput = Schemas["UpdateSettingsRequest"];
export type Permission = Schemas["UpdateRoleRequest"]["permissions"][number];

type Json<O, S extends number = 200> = O extends { responses: Record<S, { content: { "application/json": infer B } }> }
  ? B
  : never;

export type Settings = Json<operations["setting.show"]>["data"];
export type PermissionGroup = Json<operations["role.permissions"]>["data"][number];

/** Laravel's paginated resource collection. */
export type Paginated<T> = {
  data: T[];
  meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
};

/** `{ data: T }` wrapper used by single-resource responses. */
export type Wrapped<T> = { data: T };
