/**
 * Pickup and delivery API shapes: aliases of the generated contract
 * (shared/api/types.ts), plus the few UI-side helpers around them.
 */
import type { Slot, SlotTemplate, Zone, ZoneCheck } from "@/shared/api/types";

export type { Slot, SlotTemplate, Zone };

/** [latitude, longitude] */
export type LatLng = [number, number];

/**
 * What the zone form sends. The polygon is typed here because the
 * generated request type cannot describe a list of [lat, lng] pairs.
 */
export type ZoneInput = Partial<Omit<Zone, "id" | "branch_name" | "polygon">> & { polygon?: LatLng[] };

export type SlotTemplateInput = Partial<Omit<SlotTemplate, "id">>;

/** The zone a customer's point falls in, as the portal shows it. */
export type PublicZone = NonNullable<ZoneCheck["zone"]>;

/** Weekdays in the order of the Iranian week, Saturday first. */
export const WEEK = [6, 0, 1, 2, 3, 4, 5] as const;
