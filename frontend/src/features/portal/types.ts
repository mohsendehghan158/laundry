/**
 * The customer portal's API shapes.
 *
 * Written by hand for now: the portal endpoints are newer than the last
 * export of docs/api/openapi.json. Once the contract is regenerated
 * (`php artisan scramble:export`, then `npm run api:types`), replace these
 * with aliases in shared/api/types.ts like every other resource.
 */
import type { LatLng, PublicZone } from "@/features/logistics/types";
import type { CurrencyUnit } from "@/shared/lib/money";

/** GET /portal/settings — shown before sign-in. */
export type PortalSettings = {
  organization_name: string;
  currency_unit: CurrencyUnit;
};

/** POST /customer/auth/otp */
export type OtpSent = {
  mobile: string;
  /** Seconds the code stays valid. */
  expires_in: number;
  /** Seconds before another code can be asked for. */
  resend_in: number;
};

/** GET /customer/me, POST /customer/auth/verify */
export type PortalCustomer = {
  id: number;
  name: string;
  mobile: string;
  /** First sign-in: the counter has no name for this number yet. */
  needs_name: boolean;
};

export type PortalOrderStatus =
  | "draft"
  | "pending_pickup"
  | "picked_up"
  | "received"
  | "in_process"
  | "partially_ready"
  | "ready"
  | "out_for_delivery"
  | "completed"
  | "cancelled";

/** GET /customer/orders (list) and /customer/orders/{public_id} (with the detail arrays). */
export type PortalOrder = {
  public_id: string;
  ticket_number: string;
  status: PortalOrderStatus;
  payment_status: "unpaid" | "partially_paid" | "paid" | "refunded";
  branch_name: string;
  received_at: string | null;
  due_at: string | null;
  ready_at: string | null;
  completed_at: string | null;
  total: number;
  paid_amount: number;
  balance: number;
  piece_count: number;
  subtotal: number;
  discount_amount: number;
  tax_amount: number;
  lines?: Array<{ item_name: string; service_name: string; quantity: string; unit: string; line_total: number }>;
  timeline?: Array<{ status: PortalOrderStatus; at: string }>;
  payments?: Array<{ method: "cash" | "card" | "transfer"; kind: "payment" | "refund"; amount: number; at: string }>;
};

/** GET /customer/addresses */
export type PortalAddress = {
  id: number;
  title: string | null;
  address: string;
  latitude: number | null;
  longitude: number | null;
  is_default: boolean;
  /** The service zone the point is in; null = no pickup or delivery there. */
  zone: PublicZone | null;
};

export type PortalAddressInput = {
  title?: string | null;
  address?: string;
  latitude?: number;
  longitude?: number;
  is_default?: boolean;
};

/** GET /public/zones/check */
export type ZoneCheck = { served: boolean; zone: PublicZone | null };

export type { LatLng };
