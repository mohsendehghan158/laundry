/**
 * Pickup and delivery API shapes.
 *
 * Written by hand for now: these endpoints are newer than the last export
 * of docs/api/openapi.json. Once the contract is regenerated, replace them
 * with aliases in shared/api/types.ts like every other resource.
 */

/** [latitude, longitude] */
export type LatLng = [number, number];

export type Zone = {
  id: number;
  branch_id: number;
  branch_name: string | null;
  name: string;
  polygon: LatLng[];
  /** Rials. */
  delivery_fee: number;
  free_delivery_over: number | null;
  min_order: number | null;
  is_active: boolean;
};

export type ZoneInput = Partial<Omit<Zone, "id" | "branch_name">>;

export type SlotTemplate = {
  id: number;
  branch_id: number;
  /** 0 = Sunday … 6 = Saturday. */
  weekday: number;
  /** "09:00" */
  starts_at: string;
  ends_at: string;
  capacity: number;
  is_active: boolean;
};

export type SlotTemplateInput = Partial<Omit<SlotTemplate, "id">>;

export type Slot = {
  id: number;
  branch_id: number;
  /** Local date, "2026-12-05". */
  date: string;
  starts_at: string;
  ends_at: string;
  capacity: number;
  booked: number;
  remaining: number;
  is_closed: boolean;
  from_template: boolean;
};

/** The zone a customer's point falls in, as the portal shows it. */
export type PublicZone = {
  id: number;
  name: string;
  branch_name: string | null;
  delivery_fee: number;
  free_delivery_over: number | null;
  min_order: number | null;
};

/** Weekdays in the order of the Iranian week, Saturday first. */
export const WEEK = [6, 0, 1, 2, 3, 4, 5] as const;
