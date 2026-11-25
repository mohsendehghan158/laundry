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

export type ServiceType = Schemas["ServiceTypeResource"];
export type ItemCategory = Schemas["ItemCategoryResource"];
export type Item = Schemas["ItemResource"];
export type Upcharge = Schemas["UpchargeResource"];
export type TurnaroundTier = Schemas["TurnaroundTierResource"];
export type DefectType = Schemas["DefectTypeResource"];
export type Color = Schemas["ColorResource"];
export type PriceUnit = Schemas["PriceUnit"];
export type UpchargeType = Schemas["UpchargeType"];
export type DefectKind = Schemas["DefectKind"];

export type Customer = Schemas["CustomerResource"];
export type CustomerAddress = Schemas["CustomerAddressResource"];
export type SaveCustomerInput = Schemas["SaveCustomerRequest"];

export type Order = Schemas["OrderResource"];
export type OrderLine = Schemas["OrderLineResource"];
export type Piece = Schemas["PieceResource"];
export type OrderStatus = Schemas["OrderStatus"];
export type PaymentStatus = Schemas["PaymentStatus"];
export type StoreOrderInput = Schemas["StoreOrderRequest"];
export type OrderPayload = Schemas["PreviewOrderRequest"];

export type CashShift = Schemas["CashShiftResource"];
export type Payment = Schemas["PaymentResource"];
export type PaymentMethod = Schemas["PaymentMethod"];
export type PromoCode = Schemas["PromoCodeResource"];
export type SavePromoCodeInput = Schemas["SavePromoCodeRequest"];

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
export type CounterCatalog = Json<operations["catalog.catalog"]>["data"];
export type PriceCell = Json<operations["price.index"]>["data"][number];
export type PriceImport = Json<operations["price.import"]>["data"];
export type PricePreview = Json<operations["orders.orderPreview"]>["data"];
export type NotificationTemplate = Json<operations["notificationTemplate.index"]>["data"][number];
export type NotificationLog = Schemas["NotificationLogResource"];
export type PublicTracking = Json<operations["orders.publicTracking"]>["data"];
export type Station = Schemas["StationResource"];
export type StationKind = Schemas["StationKind"];
export type PieceDetail = Schemas["PieceDetailResource"];
export type PieceEventEntry = Schemas["PieceEventResource"];
export type PieceEvent = Schemas["PieceEvent"];
export type PieceFlag = Schemas["PieceFlag"];
export type PieceStatus = Schemas["PieceStatus"];
export type ScanResult = Json<operations["production.scan"]>["data"][number];
export type Rack = Schemas["RackResource"];
export type AssemblyState = Json<operations["assembly.show"]>["data"];
export type AssemblyPiece = AssemblyState["pieces"][number];
export type ProductionBoard = Json<operations["board.show"]>["data"];
export type BoardColumn = ProductionBoard["columns"][number];
export type BoardOrder = BoardColumn["orders"][number];
export type RealtimeConfig = Json<operations["production.realtime"]>["data"];
export type Transfer = Schemas["TransferResource"];
export type TransferStatus = Schemas["TransferStatus"];
export type Shipment = Schemas["ShipmentResource"];
export type Vendor = Schemas["VendorResource"];
export type Claim = Schemas["ClaimResource"];
export type ClaimKind = Schemas["ClaimKind"];
export type ClaimStatus = Schemas["ClaimStatus"];
export type TagResult = Json<operations["transfer.addPieces"]>["results"][number];
export type PiecePhoto = PieceDetail["photos"][number];
export type StationActivity = Json<operations["station.activity"]>["data"];
export type DashboardReport = Json<operations["report.dashboard"]>["data"];
export type SalesReport = Json<operations["report.sales"]>["data"];
export type SalesRow = SalesReport["rows"][number];
export type SalesGroup = operations["report.sales"]["parameters"]["query"]["group"];

/** Laravel's paginated resource collection. */
export type Paginated<T> = {
  data: T[];
  meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
};

/** `{ data: T }` wrapper used by single-resource responses. */
export type Wrapped<T> = { data: T };
