/**
 * The customer portal's API shapes: aliases of the generated contract
 * (shared/api/types.ts).
 */
import type { LatLng } from "@/features/logistics/types";
import type { PortalOrder } from "@/shared/api/types";

export type {
  CustomerSlots,
  OtpSent,
  PortalAddress,
  PortalAddressInput,
  PortalCustomer,
  PortalOrder,
  PortalSettings,
  ZoneCheck,
} from "@/shared/api/types";

export type PortalOrderStatus = PortalOrder["status"];

export type { LatLng };
