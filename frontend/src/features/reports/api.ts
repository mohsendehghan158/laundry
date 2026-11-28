"use client";

import { keepPreviousData, useMutation, useQuery } from "@tanstack/react-query";
import { api, apiDownload } from "@/shared/api/client";
import type { DashboardReport, ProductionReport, ReceivablesReport, SalesGroup, SalesReport } from "@/shared/api/types";

export function useDashboardReport(branchId: number | null, enabled = true) {
  return useQuery({
    queryKey: ["reports", "dashboard", branchId],
    queryFn: async () => (await api.get<{ data: DashboardReport }>("/reports/dashboard", { branch_id: branchId })).data,
    enabled,
    // The counter keeps the dashboard open all day.
    refetchInterval: 60_000,
  });
}

/** Local dates as YYYY-MM-DD, both included. */
export type SalesFilters = { from: string; to: string; group: SalesGroup; branch_id: number | null };

export function useSalesReport(filters: SalesFilters) {
  return useQuery({
    queryKey: ["reports", "sales", filters],
    queryFn: async () => (await api.get<{ data: SalesReport }>("/reports/sales", filters)).data,
    placeholderData: keepPreviousData,
  });
}

export function useExportSales() {
  return useMutation({ mutationFn: (filters: SalesFilters) => apiDownload("/reports/sales/export", filters) });
}

export type ProductionFilters = { from: string; to: string; branch_id: number | null };

export function useProductionReport(filters: ProductionFilters) {
  return useQuery({
    queryKey: ["reports", "production", filters],
    queryFn: async () => (await api.get<{ data: ProductionReport }>("/reports/production", filters)).data,
    placeholderData: keepPreviousData,
  });
}

/** Balanced journal lines of the period, for accounting software. */
export function useExportJournal() {
  return useMutation({ mutationFn: ({ from, to, branch_id }: Omit<SalesFilters, "group">) => apiDownload("/reports/accounting/export", { from, to, branch_id }) });
}

export function useReceivables(branchId: number | null) {
  return useQuery({
    queryKey: ["reports", "receivables", branchId],
    queryFn: async () => (await api.get<{ data: ReceivablesReport }>("/reports/receivables", { branch_id: branchId })).data,
    placeholderData: keepPreviousData,
  });
}

export function useExportReceivables() {
  return useMutation({ mutationFn: (branchId: number | null) => apiDownload("/reports/receivables/export", { branch_id: branchId }) });
}
