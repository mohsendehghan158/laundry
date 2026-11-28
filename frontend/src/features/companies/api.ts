"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { customerKeys } from "@/features/customers/api";
import { api } from "@/shared/api/client";
import type { Company, CompanyStatement, DeliveryNote, Order, Paginated, SaveCompanyInput, SaveDeliveryNoteInput, Wrapped } from "@/shared/api/types";

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

export function useDeliveryNotes(filters: { company_id?: number; page?: number }) {
  return useQuery({
    queryKey: ["delivery-notes", "list", filters],
    queryFn: () => api.get<Paginated<DeliveryNote>>("/delivery-notes", { "filter[company_id]": filters.company_id, page: filters.page, per_page: 10 }),
    placeholderData: keepPreviousData,
  });
}

export function useDeliveryNote(id: number | null) {
  return useQuery({
    queryKey: ["delivery-notes", "detail", id ?? 0],
    queryFn: async () => (await api.get<Wrapped<DeliveryNote>>(`/delivery-notes/${id}`)).data,
    enabled: Boolean(id),
  });
}

/** The company's orders still to go back (for outgoing notes). */
export function useOpenCompanyOrders(companyId: number | null) {
  return useQuery({
    queryKey: ["orders", "list", { company: companyId, open: true }],
    queryFn: () =>
      api.get<Paginated<Order>>("/orders", {
        "filter[company_id]": companyId ?? undefined,
        "filter[status]": "received,in_process,partially_ready,ready",
        per_page: 50,
      }),
    enabled: companyId !== null,
  });
}

function useDeliveryNoteMutation<V>(fn: (vars: V) => Promise<DeliveryNote>) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (note) => {
      queryClient.setQueryData(["delivery-notes", "detail", note.id], note);
      queryClient.invalidateQueries({ queryKey: ["delivery-notes", "list"] });
      queryClient.invalidateQueries({ queryKey: ["orders"] });
      queryClient.invalidateQueries({ queryKey: ["companies"] });
    },
  });
}

/** In-note lines carry item, service and both counts; out-note lines an order line and what is sent. */
export type DeliveryNoteInput = Omit<Partial<SaveDeliveryNoteInput>, "lines" | "company_department_id"> & {
  company_department_id?: number | null;
  lines?: ({ item_id: number; service_type_id: number; expected_quantity: number; counted_quantity: number } | { order_line_id: number; counted_quantity: number })[];
};

export const useSaveDeliveryNote = () =>
  useDeliveryNoteMutation(async ({ id, input }: { id?: number; input: DeliveryNoteInput }) =>
    (id ? await api.put<Wrapped<DeliveryNote>>(`/delivery-notes/${id}`, input) : await api.post<Wrapped<DeliveryNote>>("/delivery-notes", input)).data,
  );

export const useConfirmDeliveryNote = () =>
  useDeliveryNoteMutation(async ({ id, counterpart_name, received }: { id: number; counterpart_name: string; received?: { line_id: number; quantity: number }[] }) =>
    (await api.post<Wrapped<DeliveryNote>>(`/delivery-notes/${id}/confirm`, { counterpart_name, received })).data,
  );

export function useDeleteDeliveryNote() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/delivery-notes/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["delivery-notes"] }),
  });
}

/** Opens a delivery note for printing; it prints itself once loaded. */
export function openDeliveryNotePrint(id: number) {
  window.open(`/print/delivery-notes/${id}`, `print-delivery-${id}`, "width=900,height=800");
}

export function useCompanyStatement(companyId: number | null, from: string, to: string) {
  return useQuery({
    queryKey: ["companies", "statement", companyId ?? 0, from, to],
    queryFn: async () => (await api.get<Wrapped<CompanyStatement>>(`/companies/${companyId}/statement`, { from, to })).data,
    enabled: companyId !== null,
    placeholderData: keepPreviousData,
  });
}

export function useInvoiceStatement() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ companyId, from, to }: { companyId: number; from: string; to: string }) =>
      (await api.post<Wrapped<{ id: number; number: number; total: number }>>(`/companies/${companyId}/statement/invoice`, { from, to })).data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["companies", "statement"] });
      queryClient.invalidateQueries({ queryKey: ["invoices"] });
      queryClient.invalidateQueries({ queryKey: ["orders"] });
    },
  });
}

export function openStatementPrint(companyId: number, from: string, to: string) {
  window.open(`/print/companies/${companyId}/statement?from=${from}&to=${to}`, `print-statement-${companyId}`, "width=1100,height=800");
}
