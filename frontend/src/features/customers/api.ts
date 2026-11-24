"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/shared/api/client";
import type { Customer, Paginated, SaveCustomerInput, Wrapped } from "@/shared/api/types";

export type CustomerFilters = { q?: string; sort?: string | null; page?: number; per_page?: number };

export const customerKeys = {
  all: ["customers"] as const,
  list: (filters: CustomerFilters) => ["customers", "list", filters] as const,
  detail: (id: number) => ["customers", "detail", id] as const,
};

export function useCustomers(filters: CustomerFilters, enabled = true) {
  return useQuery({
    queryKey: customerKeys.list(filters),
    queryFn: ({ signal }) =>
      api.get<Paginated<Customer>>(
        "/customers",
        { "filter[q]": filters.q, sort: filters.sort, page: filters.page, per_page: filters.per_page },
        signal,
      ),
    placeholderData: keepPreviousData,
    enabled,
  });
}

export function useCustomer(id: number | null | undefined) {
  return useQuery({
    queryKey: customerKeys.detail(id ?? 0),
    queryFn: async () => (await api.get<Wrapped<Customer>>(`/customers/${id}`)).data,
    enabled: Boolean(id),
  });
}

export function useSaveCustomer() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: Partial<SaveCustomerInput> }) =>
      id
        ? (await api.put<Wrapped<Customer>>(`/customers/${id}`, input)).data
        : (await api.post<Wrapped<Customer>>("/customers", input)).data,
    onSuccess: (customer) => {
      queryClient.setQueryData(customerKeys.detail(customer.id), customer);
      queryClient.invalidateQueries({ queryKey: ["customers", "list"] });
    },
  });
}

export function useDeleteCustomer() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/customers/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: customerKeys.all }),
  });
}

/** True when the text looks like (part of) a phone number rather than a name. */
export function looksLikePhone(text: string): boolean {
  return /^[\d۰-۹٠-٩+\s-]{3,}$/.test(text.trim());
}
