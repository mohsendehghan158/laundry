"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/shared/api/client";
import type { Wrapped } from "@/shared/api/types";
import type { Slot, SlotTemplate, SlotTemplateInput, Zone, ZoneInput } from "./types";

export const logisticsKeys = {
  zones: ["logistics", "zones"] as const,
  templates: ["logistics", "templates"] as const,
  slots: (branchId: number) => ["logistics", "slots", branchId] as const,
};

export function useZones() {
  return useQuery({
    queryKey: logisticsKeys.zones,
    queryFn: async () => (await api.get<Wrapped<Zone[]>>("/zones")).data,
  });
}

export function useSaveZone() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: ZoneInput }) =>
      (id === undefined ? await api.post<Wrapped<Zone>>("/zones", input) : await api.put<Wrapped<Zone>>(`/zones/${id}`, input)).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: logisticsKeys.zones }),
  });
}

export function useDeleteZone() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/zones/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: logisticsKeys.zones }),
  });
}

export function useSlotTemplates() {
  return useQuery({
    queryKey: logisticsKeys.templates,
    queryFn: async () => (await api.get<Wrapped<SlotTemplate[]>>("/slot-templates")).data,
  });
}

/** Templates and dated slots move together, so both are refreshed. */
function useRefreshSlots() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: logisticsKeys.templates });
    queryClient.invalidateQueries({ queryKey: ["logistics", "slots"] });
  };
}

export function useSaveSlotTemplate() {
  const refresh = useRefreshSlots();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: SlotTemplateInput }) =>
      (id === undefined
        ? await api.post<Wrapped<SlotTemplate>>("/slot-templates", input)
        : await api.put<Wrapped<SlotTemplate>>(`/slot-templates/${id}`, input)
      ).data,
    onSuccess: refresh,
  });
}

export function useDeleteSlotTemplate() {
  const refresh = useRefreshSlots();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/slot-templates/${id}`),
    onSuccess: refresh,
  });
}

export function useSlots(branchId: number | null) {
  return useQuery({
    queryKey: logisticsKeys.slots(branchId ?? 0),
    queryFn: async () => (await api.get<Wrapped<Slot[]>>("/slots", { branch_id: branchId })).data,
    enabled: branchId !== null,
    placeholderData: keepPreviousData,
  });
}

export function useUpdateSlot() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, input }: { id: number; input: Partial<Pick<Slot, "capacity" | "is_closed">> }) =>
      (await api.put<Wrapped<Slot>>(`/slots/${id}`, input)).data,
    onSuccess: (slot) => {
      queryClient.setQueryData<Slot[]>(logisticsKeys.slots(slot.branch_id), (slots) => slots?.map((s) => (s.id === slot.id ? slot : s)));
    },
  });
}
