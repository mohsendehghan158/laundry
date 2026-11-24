"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { branchKeys } from "@/features/branches/api";
import { roleKeys } from "@/features/roles/api";
import { api } from "@/shared/api/client";
import type { Paginated, StoreUserInput, UpdateUserInput, User, Wrapped } from "@/shared/api/types";

export type UserFilters = {
  q?: string;
  role?: string | null;
  branch_id?: number | null;
  is_active?: boolean | null;
  sort?: string | null;
  page?: number;
  per_page?: number;
};

export const userKeys = {
  all: ["users"] as const,
  list: (filters: UserFilters) => ["users", "list", filters] as const,
};

export function useUsers(filters: UserFilters) {
  return useQuery({
    queryKey: userKeys.list(filters),
    queryFn: ({ signal }) =>
      api.get<Paginated<User>>(
        "/users",
        {
          "filter[q]": filters.q,
          "filter[role]": filters.role,
          "filter[branch_id]": filters.branch_id,
          "filter[is_active]": filters.is_active === null || filters.is_active === undefined ? undefined : String(filters.is_active),
          sort: filters.sort,
          page: filters.page,
          per_page: filters.per_page,
        },
        signal,
      ),
    placeholderData: keepPreviousData,
  });
}

function useInvalidateUsers() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: userKeys.all });
    // Staff counts on branches and roles change with users.
    queryClient.invalidateQueries({ queryKey: branchKeys.all });
    queryClient.invalidateQueries({ queryKey: roleKeys.all });
  };
}

export function useSaveUser() {
  const invalidate = useInvalidateUsers();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: StoreUserInput | UpdateUserInput }) =>
      id ? (await api.put<Wrapped<User>>(`/users/${id}`, input)).data : (await api.post<Wrapped<User>>("/users", input)).data,
    onSuccess: invalidate,
  });
}

export function useDeleteUser() {
  const invalidate = useInvalidateUsers();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/users/${id}`),
    onSuccess: invalidate,
  });
}
