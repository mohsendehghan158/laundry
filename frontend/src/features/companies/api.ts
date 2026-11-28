"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { customerKeys } from "@/features/customers/api";
import { api } from "@/shared/api/client";
import type { Company, Paginated, SaveCompanyInput, Wrapped } from "@/shared/api/types";

export const companyKeys = {
  list: (filters: object) => ["companies", "list", filters] as const,
  detail: (id: number) => ["companies", "detail", id] as const,
};

export function useCompanies(filters: { q?: string; page?: number; per_page?: number }, enabled = true) {
  return useQuery({
    queryKey: companyKeys.list(filters),
    queryFn: () => api.get<Paginated<Company>>("/companies", { "filter[q]": filters.q || undefined, page: filters.page, per_page: filters.per_page }),
    placeholderData: keepPreviousData,
    enabled,
  });
}

export function useCompany(id: number | null) {
  return useQuery({
    queryKey: companyKeys.detail(id ?? 0),
    queryFn: async () => (await api.get<Wrapped<Company>>(`/companies/${id}`)).data,
    enabled: Boolean(id),
  });
}

/** Every change on a company page returns the whole company. */
function useCompanyMutation<V>(fn: (vars: V) => Promise<Company>) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (company) => {
      queryClient.setQueryData(companyKeys.detail(company.id), company);
      queryClient.invalidateQueries({ queryKey: ["companies", "list"] });
      queryClient.invalidateQueries({ queryKey: customerKeys.all });
    },
  });
}

export const useSaveCompany = () =>
  useCompanyMutation(async ({ id, input }: { id?: number; input: Partial<SaveCompanyInput> }) =>
    (id ? await api.put<Wrapped<Company>>(`/companies/${id}`, input) : await api.post<Wrapped<Company>>("/companies", input)).data,
  );

export const useSaveDepartment = () =>
  useCompanyMutation(async ({ companyId, id, input }: { companyId: number; id?: number; input: { name?: string; code?: string | null; is_active?: boolean } }) =>
    (id ? await api.put<Wrapped<Company>>(`/companies/${companyId}/departments/${id}`, input) : await api.post<Wrapped<Company>>(`/companies/${companyId}/departments`, input)).data,
  );

export const useDeleteDepartment = () =>
  useCompanyMutation(async ({ companyId, id }: { companyId: number; id: number }) => (await api.delete<Wrapped<Company>>(`/companies/${companyId}/departments/${id}`)).data);

export const useAddMember = () =>
  useCompanyMutation(async ({ companyId, customerId }: { companyId: number; customerId: number }) => (await api.post<Wrapped<Company>>(`/companies/${companyId}/members`, { customer_id: customerId })).data);

export const useRemoveMember = () =>
  useCompanyMutation(async ({ companyId, customerId }: { companyId: number; customerId: number }) => (await api.delete<Wrapped<Company>>(`/companies/${companyId}/members/${customerId}`)).data);

export function useDeleteCompany() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/companies/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["companies"] }),
  });
}
