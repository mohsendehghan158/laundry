"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/shared/api/client";
import type { PieceDetail, ScanResult, Station, StationActivity, StationKind, Wrapped } from "@/shared/api/types";

export const productionKeys = {
  stations: ["stations"] as const,
  activity: (stationId: number) => ["stations", stationId, "activity"] as const,
  piece: (tag: string) => ["pieces", tag] as const,
};

export function useStations(enabled = true) {
  return useQuery({
    queryKey: productionKeys.stations,
    queryFn: async () => (await api.get<Wrapped<Station[]>>("/stations")).data,
    enabled,
  });
}

export type StationInput = { branch_id?: number; name: string; code: string; kind: StationKind; sort_order: number; is_active: boolean };

export function useSaveStation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: StationInput }) =>
      (id ? await api.put<Wrapped<Station>>(`/stations/${id}`, input) : await api.post<Wrapped<Station>>("/stations", input)).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: productionKeys.stations }),
  });
}

export function useDeleteStation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/stations/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: productionKeys.stations }),
  });
}

export type ScanInput = { station_id: number; tags: string[]; passed?: boolean; reason?: string | null; defect_type_ids?: number[] };

export function useScan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: ScanInput) => (await api.post<Wrapped<ScanResult[]>>("/scans", input)).data,
    onSuccess: (_results, input) => {
      queryClient.invalidateQueries({ queryKey: productionKeys.activity(input.station_id) });
      queryClient.invalidateQueries({ queryKey: ["pieces"] });
      queryClient.invalidateQueries({ queryKey: ["production"] });
    },
  });
}

export function useStationActivity(stationId: number | null) {
  return useQuery({
    queryKey: productionKeys.activity(stationId ?? 0),
    queryFn: async () => (await api.get<Wrapped<StationActivity>>(`/stations/${stationId}/activity`)).data,
    enabled: stationId !== null,
    placeholderData: keepPreviousData,
  });
}

export function usePiece(tag: string | null) {
  return useQuery({
    queryKey: productionKeys.piece(tag ?? ""),
    queryFn: async () => (await api.get<Wrapped<PieceDetail>>(`/pieces/${encodeURIComponent(tag ?? "")}`)).data,
    enabled: Boolean(tag),
  });
}

export function usePieceHold() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, reason }: { id: number; reason: string | null }) =>
      (reason === null ? await api.post<Wrapped<PieceDetail>>(`/pieces/${id}/release`) : await api.post<Wrapped<PieceDetail>>(`/pieces/${id}/hold`, { reason })).data,
    onSuccess: (piece) => queryClient.setQueryData(productionKeys.piece(piece.tag_code), piece),
  });
}
