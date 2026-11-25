"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, type Query } from "@/shared/api/client";
import type { Paginated, StoreUserBody, UpdateUserBody, User, Wrapped } from "@/shared/api/types";
import { meKey } from "@/features/auth/api";

export const userKeys = {
  all: ["users"] as const,
  list: (query: Query) => ["users", "list", query] as const,
};

export function useUsers(query: Query) {
  return useQuery({
    queryKey: userKeys.list(query),
    queryFn: ({ signal }) => api<Paginated<User>>("/users", { query, signal }),
    placeholderData: keepPreviousData,
  });
}

function useInvalidate() {
  const queryClient = useQueryClient();
  return () => {
    void queryClient.invalidateQueries({ queryKey: userKeys.all });
    void queryClient.invalidateQueries({ queryKey: ["roles"] });
    void queryClient.invalidateQueries({ queryKey: meKey });
  };
}

export function useSaveUser() {
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: async ({ id, body }: { id?: number; body: StoreUserBody | UpdateUserBody }) =>
      (await api<Wrapped<User>>(id ? `/users/${id}` : "/users", { method: id ? "PUT" : "POST", body })).data,
    onSuccess: invalidate,
  });
}

export function useDeleteUser() {
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (id: number) => api<void>(`/users/${id}`, { method: "DELETE" }),
    onSuccess: invalidate,
  });
}
