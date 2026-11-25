"use client";

import "leaflet/dist/leaflet.css";
import L from "leaflet";
import { useEffect, useMemo } from "react";
import { MapContainer, Marker, Polygon, Polyline, TileLayer, Tooltip, useMap, useMapEvents } from "react-leaflet";
import type { LatLng } from "@/features/logistics/types";
import { AutoResize } from "./AutoResize";
import { DEFAULT_CENTER, DEFAULT_ZOOM, TILE_ATTRIBUTION, TILE_URL } from "./config";

export type MapZone = { id: number; name: string; polygon: LatLng[]; color: string; muted?: boolean };

export type ZoneMapProps = {
  zones: MapZone[];
  /** The polygon being drawn; clicks on the map add corners to it. */
  editing?: LatLng[] | null;
  editingColor?: string;
  onEditingChange?: (polygon: LatLng[]) => void;
  onZoneClick?: (id: number) => void;
  height?: number | string;
  /** Labels for the corner markers' tooltip. */
  cornerHint?: string;
};

const vertexIcon = L.divIcon({ className: "map-vertex", iconSize: [16, 16] });

function FitTo({ points }: { points: LatLng[] }) {
  const map = useMap();
  const key = JSON.stringify(points.length > 0 ? [points[0], points.length] : null);
  useEffect(() => {
    if (points.length >= 2) {
      map.fitBounds(L.latLngBounds(points), { padding: [24, 24], maxZoom: 15 });
    }
    // Only when the set of shapes changes, not on every drag.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key, map]);
  return null;
}

function AddCorners({ onAdd }: { onAdd: (point: LatLng) => void }) {
  useMapEvents({ click: (event) => onAdd([round(event.latlng.lat), round(event.latlng.lng)]) });
  return null;
}

function round(value: number): number {
  return Math.round(value * 1e6) / 1e6;
}

/**
 * Service zones on the map. With `editing`, one polygon is drawn by
 * clicking corners onto the map; corners can be dragged, and a click on a
 * corner removes it.
 */
export default function ZoneMap({ zones, editing, editingColor = "#1668dc", onEditingChange, onZoneClick, height = 480, cornerHint }: ZoneMapProps) {
  const all = useMemo(() => [...zones.flatMap((zone) => zone.polygon), ...(editing ?? [])], [zones, editing]);

  const move = (index: number, point: LatLng) => editing && onEditingChange?.(editing.map((corner, i) => (i === index ? point : corner)));
  const remove = (index: number) => editing && onEditingChange?.(editing.filter((_, i) => i !== index));

  return (
    <div dir="ltr" style={{ height, borderRadius: 8, overflow: "hidden" }}>
      <MapContainer center={DEFAULT_CENTER} zoom={DEFAULT_ZOOM} style={{ height: "100%", width: "100%" }} doubleClickZoom={!editing}>
        <TileLayer url={TILE_URL} attribution={TILE_ATTRIBUTION} />
        <AutoResize />
        <FitTo points={editing && editing.length >= 3 ? editing : all} />
        {zones.map((zone) => (
          <Polygon
            key={zone.id}
            positions={zone.polygon}
            pathOptions={{ color: zone.color, weight: 2, fillOpacity: zone.muted ? 0.05 : 0.2, opacity: zone.muted ? 0.4 : 1, dashArray: zone.muted ? "4 4" : undefined }}
            eventHandlers={onZoneClick && !editing ? { click: () => onZoneClick(zone.id) } : undefined}
          >
            <Tooltip sticky>{zone.name}</Tooltip>
          </Polygon>
        ))}
        {editing && (
          <>
            <AddCorners onAdd={(point) => onEditingChange?.([...editing, point])} />
            {editing.length >= 3 ? (
              <Polygon positions={editing} pathOptions={{ color: editingColor, weight: 3, fillOpacity: 0.25 }} interactive={false} />
            ) : (
              editing.length === 2 && <Polyline positions={editing} pathOptions={{ color: editingColor, weight: 3 }} interactive={false} />
            )}
            {editing.map((corner, index) => (
              <Marker
                key={index}
                position={corner}
                icon={vertexIcon}
                draggable
                eventHandlers={{
                  dragend: (event) => {
                    const { lat, lng } = (event.target as L.Marker).getLatLng();
                    move(index, [round(lat), round(lng)]);
                  },
                  click: () => remove(index),
                }}
              >
                {cornerHint && <Tooltip direction="top">{cornerHint}</Tooltip>}
              </Marker>
            ))}
          </>
        )}
      </MapContainer>
    </div>
  );
}
