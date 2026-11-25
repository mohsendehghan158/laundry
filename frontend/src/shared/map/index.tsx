"use client";

import { Skeleton } from "antd";
import dynamic from "next/dynamic";

/*
 * Leaflet touches `window` as soon as it loads, so the maps are only ever
 * rendered in the browser, in their own bundle.
 */
const loading = () => <Skeleton.Node active style={{ width: "100%", height: 320 }} />;

export const ZoneMap = dynamic(() => import("./ZoneMap"), { ssr: false, loading });
export const PointMap = dynamic(() => import("./PointMap"), { ssr: false, loading });
export type { MapZone, ZoneMapProps } from "./ZoneMap";
export type { PointMapProps } from "./PointMap";
export { zoneColor } from "./config";
