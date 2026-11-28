"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { orderKeys } from "@/features/orders/api";
import { api } from "@/shared/api/client";
import type { Invoice, InvoiceStatus, IssueInvoiceInput, Paginated, Wrapped } from "@/shared/api/types";

export const invoiceKeys = {
  list: (filters: object) => ["invoices", "list", filters] as const,
  detail: (id: number) => ["invoices", "detail", id] as const,
};

export type InvoiceFilters = {
  q?: string;
  status?: InvoiceStatus | null;
  branch_id?: number | null;
  customer_id?: number | null;
  from?: string | null;
  to?: string | null;
  page?: number;
  per_page?: number;
};

export function useInvoices(filters: InvoiceFilters, enabled = true) {
  return useQuery({
    queryKey: invoiceKeys.list(filters),
    queryFn: () =>
      api.get<Paginated<Invoice>>("/invoices", {
        "filter[q]": filters.q || undefined,
        "filter[status]": filters.status,
        "filter[branch_id]": filters.branch_id,
        "filter[customer_id]": filters.customer_id,
        "filter[from]": filters.from,
        "filter[to]": filters.to,
        page: filters.page,
        per_page: filters.per_page,
      }),
    placeholderData: keepPreviousData,
    enabled,
  });
}

export function useInvoice(id: number | null) {
  return useQuery({
    queryKey: invoiceKeys.detail(id ?? 0),
    queryFn: async () => (await api.get<Wrapped<Invoice>>(`/invoices/${id}`)).data,
    enabled: Boolean(id),
  });
}

function useInvoiceMutation<V>(fn: (vars: V) => Promise<Invoice>) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (invoice) => {
      queryClient.setQueryData(invoiceKeys.detail(invoice.id), invoice);
      queryClient.invalidateQueries({ queryKey: ["invoices", "list"] });
      for (const order of invoice.orders ?? []) {
        queryClient.invalidateQueries({ queryKey: orderKeys.detail(order.id) });
      }
    },
  });
}

export const useIssueInvoice = () => useInvoiceMutation(async (input: IssueInvoiceInput) => (await api.post<Wrapped<Invoice>>("/invoices", input)).data);

export const useVoidInvoice = () =>
  useInvoiceMutation(async ({ id, reason }: { id: number; reason: string }) => (await api.post<Wrapped<Invoice>>(`/invoices/${id}/void`, { reason })).data);

/** Opens the A4 invoice in a new window; it prints itself once loaded. */
export function openInvoicePrint(id: number) {
  window.open(`/print/invoices/${id}`, `print-invoice-${id}`, "width=1100,height=800");
}
