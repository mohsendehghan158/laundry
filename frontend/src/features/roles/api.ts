"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/shared/api/client";
import type { PermissionGroup, Role, Wrapped } from "@/shared/api/types";
import { meKey } from "@/features/auth/api";

export function useRoles(enabled = true) {
  return useQuery({
    queryKey: ["roles"],
    queryFn: async ({ signal }) => (await api<Wrapped<Role[]>>("/roles", { signal })).data,
    staleTime: 5 * 60_000,
    enabled,
  });
}

export function usePermissionGroups() {
  return useQuery({
    queryKey: ["permissions"],
    queryFn: async ({ signal }) => (await api<Wrapped<PermissionGroup[]>>("/permissions", { signal })).data,
    staleTime: Infinity,
  });
}

export function useUpdateRole() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, permissions }: { id: number; permissions: string[] }) =>
      (await api<Wrapped<Role>>(`/roles/${id}`, { method: "PUT", body: { permissions } })).data,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["roles"] });
      void queryClient.invalidateQueries({ queryKey: meKey });
    },
  });
}
