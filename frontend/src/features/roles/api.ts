"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { meKey } from "@/features/auth/api";
import { api } from "@/shared/api/client";
import type { Permission, PermissionGroup, Role, Wrapped } from "@/shared/api/types";

export const OWNER_ROLE = "owner";

export const roleKeys = {
  all: ["roles"] as const,
  permissions: ["permissions"] as const,
};

export function useRoles(enabled = true) {
  return useQuery({
    queryKey: roleKeys.all,
    queryFn: async () => (await api.get<Wrapped<Role[]>>("/roles")).data,
    enabled,
    staleTime: 5 * 60_000,
  });
}

export function usePermissionGroups(enabled = true) {
  return useQuery({
    queryKey: roleKeys.permissions,
    queryFn: async () => (await api.get<Wrapped<PermissionGroup[]>>("/permissions")).data,
    enabled,
    staleTime: Infinity,
  });
}

export function useUpdateRole() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, permissions }: { id: number; permissions: Permission[] }) =>
      (await api.put<Wrapped<Role>>(`/roles/${id}`, { permissions })).data,
    onSuccess: (role) => {
      queryClient.setQueryData<Role[]>(roleKeys.all, (roles) => roles?.map((r) => (r.id === role.id ? role : r)));
      // The signed-in user may hold the role that just changed.
      queryClient.invalidateQueries({ queryKey: meKey });
    },
  });
}
