"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { meKey } from "@/features/auth/api";
import { api } from "@/shared/api/client";
import type { Branch, BranchType, Paginated, StoreBranchInput, UpdateBranchInput, Wrapped } from "@/shared/api/types";

export type BranchFilters = {
  q?: string;
  type?: BranchType | null;
  is_active?: boolean | null;
  sort?: string | null;
  page?: number;
  per_page?: number;
};

export const branchKeys = {
  all: ["branches"] as const,
  list: (filters: BranchFilters) => ["branches", "list", filters] as const,
};

export function useBranches(filters: BranchFilters, enabled = true) {
  return useQuery({
    queryKey: branchKeys.list(filters),
    queryFn: ({ signal }) =>
      api.get<Paginated<Branch>>(
        "/branches",
        {
          "filter[q]": filters.q,
          "filter[type]": filters.type,
          "filter[is_active]": filters.is_active === null || filters.is_active === undefined ? undefined : String(filters.is_active),
          sort: filters.sort,
          page: filters.page,
          per_page: filters.per_page,
        },
        signal,
      ),
    placeholderData: keepPreviousData,
    enabled,
  });
}

/** Every branch the user can see, for pickers (at most 100). */
export function useBranchOptions(enabled = true) {
  return useBranches({ per_page: 100, sort: "name" }, enabled);
}

function useInvalidateBranches() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: branchKeys.all });
    // The signed-in user's branch list comes from /me.
    queryClient.invalidateQueries({ queryKey: meKey });
  };
}

export function useSaveBranch() {
  const invalidate = useInvalidateBranches();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: StoreBranchInput | UpdateBranchInput }) =>
      id
        ? (await api.put<Wrapped<Branch>>(`/branches/${id}`, input)).data
        : (await api.post<Wrapped<Branch>>("/branches", input)).data,
    onSuccess: invalidate,
  });
}

export function useDeleteBranch() {
  const invalidate = useInvalidateBranches();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/branches/${id}`),
    onSuccess: invalidate,
  });
}
