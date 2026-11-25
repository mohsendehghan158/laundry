"use client";

import "leaflet/dist/leaflet.css";
import L from "leaflet";
import { useEffect } from "react";
import { MapContainer, Marker, Polygon, TileLayer, useMap, useMapEvents } from "react-leaflet";
import type { LatLng } from "@/features/logistics/types";
import { AutoResize } from "./AutoResize";
import { DEFAULT_CENTER, DEFAULT_ZOOM, TILE_ATTRIBUTION, TILE_URL } from "./config";

export type PointMapProps = {
  value: LatLng | null;
  onChange: (point: LatLng) => void;
  /** Areas to show faintly behind the pin, e.g. the zones a business serves. */
  areas?: LatLng[][];
  height?: number | string;
};

// Leaflet places the marker with `transform`, so the pin shape is an inner element.
const pinIcon = L.divIcon({ className: "map-pin", html: "<span></span>", iconSize: [28, 28], iconAnchor: [14, 28] });

function round(value: number): number {
  return Math.round(value * 1e6) / 1e6;
}

function Picker({ onPick }: { onPick: (point: LatLng) => void }) {
  useMapEvents({ click: (event) => onPick([round(event.latlng.lat), round(event.latlng.lng)]) });
  return null;
}

/**
 * Brings the pin close up once it is placed (a tap on a city-wide view,
 * or "my location"), so it can be dragged onto the right door; keeps it
 * in view after that.
 */
function Follow({ value }: { value: LatLng | null }) {
  const map = useMap();
  useEffect(() => {
    if (value && (map.getZoom() < 15 || !map.getBounds().pad(-0.1).contains(value))) {
      map.setView(value, Math.max(map.getZoom(), 16));
    }
  }, [value, map]);
  return null;
}

/** A map to put one point on: tap to place the pin, drag it to adjust. */
export default function PointMap({ value, onChange, areas = [], height = 320 }: PointMapProps) {
  return (
    <div dir="ltr" style={{ height, borderRadius: 8, overflow: "hidden" }}>
      <MapContainer center={value ?? DEFAULT_CENTER} zoom={value ? 16 : DEFAULT_ZOOM} style={{ height: "100%", width: "100%" }}>
        <TileLayer url={TILE_URL} attribution={TILE_ATTRIBUTION} />
        <AutoResize />
        {areas.map((area, index) => (
          <Polygon key={index} positions={area} pathOptions={{ color: "#1668dc", weight: 1, fillOpacity: 0.06, dashArray: "4 4" }} interactive={false} />
        ))}
        <Picker onPick={onChange} />
        <Follow value={value} />
        {value && (
          <Marker
            position={value}
            icon={pinIcon}
            draggable
            eventHandlers={{
              dragend: (event) => {
                const { lat, lng } = (event.target as L.Marker).getLatLng();
                onChange([round(lat), round(lng)]);
              },
            }}
          />
        )}
      </MapContainer>
    </div>
  );
}
