"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/shared/api/client";
import type { Organization, Settings, Wrapped } from "@/shared/api/types";
import { meKey } from "@/features/auth/api";

export function useSettings(enabled = true) {
  return useQuery({
    queryKey: ["settings"],
    queryFn: async ({ signal }) => (await api<Wrapped<Settings>>("/settings", { signal })).data,
    enabled,
  });
}

export function useUpdateSettings() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (body: Partial<{ [K in keyof Settings]: Partial<Settings[K]> }>) =>
      (await api<Wrapped<Settings>>("/settings", { method: "PUT", body })).data,
    onSuccess: (settings) => queryClient.setQueryData(["settings"], settings),
  });
}

export function useUpdateOrganization() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (body: { name: string }) =>
      (await api<Wrapped<Organization>>("/organization", { method: "PUT", body })).data,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: meKey }),
  });
}
