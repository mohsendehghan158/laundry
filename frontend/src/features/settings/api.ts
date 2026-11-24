"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { meKey } from "@/features/auth/api";
import { api } from "@/shared/api/client";
import type { Organization, Settings, UpdateSettingsInput, Wrapped } from "@/shared/api/types";

export const settingsKey = ["settings"] as const;
export const organizationKey = ["organization"] as const;

export function useSettings(enabled = true) {
  return useQuery({
    queryKey: settingsKey,
    queryFn: async () => (await api.get<Wrapped<Settings>>("/settings")).data,
    enabled,
    staleTime: 5 * 60_000,
  });
}

export function useUpdateSettings() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: UpdateSettingsInput) => (await api.put<Wrapped<Settings>>("/settings", input)).data,
    onSuccess: (settings) => {
      queryClient.setQueryData(settingsKey, settings);
      // The currency unit reaches every screen through /me.
      queryClient.invalidateQueries({ queryKey: meKey });
    },
  });
}

export function useOrganization() {
  return useQuery({
    queryKey: organizationKey,
    queryFn: async () => (await api.get<Wrapped<Organization>>("/organization")).data,
  });
}

export function useUpdateOrganization() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: { name: string }) => (await api.put<Wrapped<Organization>>("/organization", input)).data,
    onSuccess: (organization) => {
      queryClient.setQueryData(organizationKey, organization);
      queryClient.invalidateQueries({ queryKey: meKey });
    },
  });
}
