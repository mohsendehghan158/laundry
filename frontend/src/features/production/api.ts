"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/shared/api/client";
import type { AssemblyState, PieceDetail, Rack, ScanResult, Station, StationActivity, StationKind, Wrapped } from "@/shared/api/types";

export const productionKeys = {
  stations: ["stations"] as const,
  activity: (stationId: number) => ["stations", stationId, "activity"] as const,
  piece: (tag: string) => ["pieces", tag] as const,
  racks: ["racks"] as const,
  assembly: (orderId: number) => ["assembly", orderId] as const,
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

export function useRacks(enabled = true) {
  return useQuery({
    queryKey: productionKeys.racks,
    queryFn: async () => (await api.get<Wrapped<Rack[]>>("/racks")).data,
    enabled,
  });
}

export type RackInput = { branch_id?: number; name: string; code?: string; slot_count: number; sort_order: number; is_active: boolean };

export function useSaveRack() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: RackInput }) =>
      (id ? await api.put<Wrapped<Rack>>(`/racks/${id}`, input) : await api.post<Wrapped<Rack>>("/racks", input)).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: productionKeys.racks }),
  });
}

export function useDeleteRack() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/racks/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: productionKeys.racks }),
  });
}

export function useAssembly(orderId: number | null) {
  return useQuery({
    queryKey: productionKeys.assembly(orderId ?? 0),
    queryFn: async () => (await api.get<Wrapped<AssemblyState>>(`/assembly/${orderId}`)).data,
    enabled: orderId !== null,
  });
}

export function useAssemblyScan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (tag: string) => api.post<{ data: AssemblyState; scanned_piece_id: number }>("/assembly/scan", { tag }),
    onSuccess: ({ data }) => queryClient.setQueryData(productionKeys.assembly(data.order.id), data),
  });
}

export type CompleteAssemblyInput = { piece_ids?: number[]; rack_slot_id?: number | null; rack_location?: string | null; partial?: boolean };

export function useCompleteAssembly() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ orderId, input }: { orderId: number; input: CompleteAssemblyInput }) =>
      (await api.post<Wrapped<AssemblyState>>(`/assembly/${orderId}/complete`, input)).data,
    onSuccess: (state) => {
      queryClient.setQueryData(productionKeys.assembly(state.order.id), state);
      queryClient.invalidateQueries({ queryKey: ["orders"] });
      queryClient.invalidateQueries({ queryKey: productionKeys.racks });
      queryClient.invalidateQueries({ queryKey: ["pieces"] });
    },
  });
}
