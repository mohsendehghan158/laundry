"use client";

import { useEffect } from "react";
import { useMap } from "react-leaflet";

/**
 * Leaflet measures its container once. A map inside a drawer or modal is
 * measured mid-animation and then misses tiles and centres wrongly, so
 * the size is taken again whenever the container changes.
 */
export function AutoResize() {
  const map = useMap();
  useEffect(() => {
    const container = map.getContainer();
    const observer = new ResizeObserver(() => map.invalidateSize());
    observer.observe(container);
    return () => observer.disconnect();
  }, [map]);
  return null;
}
