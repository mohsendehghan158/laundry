import type { LatLng } from "@/features/logistics/types";

/**
 * Map tiles. OpenStreetMap's own servers are fine for a handful of staff
 * screens; for the customer portal at scale set NEXT_PUBLIC_MAP_TILE_URL
 * to a provider you have an agreement with (and its attribution).
 */
export const TILE_URL = process.env.NEXT_PUBLIC_MAP_TILE_URL ?? "https://tile.openstreetmap.org/{z}/{x}/{y}.png";
export const TILE_ATTRIBUTION =
  process.env.NEXT_PUBLIC_MAP_ATTRIBUTION ?? '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

/** Where a map with nothing on it opens: central Tehran. */
export const DEFAULT_CENTER: LatLng = [35.7, 51.4];
export const DEFAULT_ZOOM = 11;

/** Zone colours, by position in the list. */
export const ZONE_COLORS = ["#1668dc", "#389e0d", "#d46b08", "#722ed1", "#c41d7f", "#08979c", "#d4380d", "#7cb305"];

export function zoneColor(index: number): string {
  return ZONE_COLORS[index % ZONE_COLORS.length];
}
