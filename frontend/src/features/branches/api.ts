"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, type Query } from "@/shared/api/client";
import type { Branch, Paginated, StoreBranchBody, UpdateBranchBody, Wrapped } from "@/shared/api/types";
import { meKey } from "@/features/auth/api";

export const branchKeys = {
  all: ["branches"] as const,
  list: (query: Query) => ["branches", "list", query] as const,
};

export function useBranches(query: Query, enabled = true) {
  return useQuery({
    queryKey: branchKeys.list(query),
    queryFn: ({ signal }) => api<Paginated<Branch>>("/branches", { query, signal }),
    placeholderData: keepPreviousData,
    enabled,
  });
}

/** Every branch the user can see, for pickers (assignments, filters). */
export function useBranchOptions(enabled = true) {
  return useBranches({ per_page: 100, sort: "name" }, enabled);
}

function useInvalidate() {
  const queryClient = useQueryClient();
  return () => {
    void queryClient.invalidateQueries({ queryKey: branchKeys.all });
    // The branch switcher and permissions come from /me.
    void queryClient.invalidateQueries({ queryKey: meKey });
  };
}

export function useSaveBranch() {
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: async ({ id, body }: { id?: number; body: StoreBranchBody | UpdateBranchBody }) =>
      (
        await api<Wrapped<Branch>>(id ? `/branches/${id}` : "/branches", {
          method: id ? "PUT" : "POST",
          body,
        })
      ).data,
    onSuccess: invalidate,
  });
}

export function useDeleteBranch() {
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (id: number) => api<void>(`/branches/${id}`, { method: "DELETE" }),
    onSuccess: invalidate,
  });
}
