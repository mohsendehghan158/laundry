"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { orderKeys } from "@/features/orders/api";
import { api } from "@/shared/api/client";
import type { CashShift, Paginated, Payment, PaymentMethod, PromoCode, SavePromoCodeInput, Wrapped } from "@/shared/api/types";

export const billingKeys = {
  current: (branchId: number) => ["cash-shifts", "current", branchId] as const,
  shifts: (filters: object) => ["cash-shifts", "list", filters] as const,
  shift: (id: number) => ["cash-shifts", "detail", id] as const,
  promoCodes: ["promo-codes"] as const,
};

/** The open shift of a branch, or null while its register is closed. */
export function useCurrentShift(branchId: number | null, enabled = true) {
  return useQuery({
    queryKey: billingKeys.current(branchId ?? 0),
    queryFn: async () => (await api.get<{ data: CashShift | null }>("/cash-shifts/current", { branch_id: branchId })).data,
    enabled: enabled && branchId !== null,
  });
}

export function useShifts(filters: { branch_id?: number | null; page?: number; per_page?: number }, enabled = true) {
  return useQuery({
    queryKey: billingKeys.shifts(filters),
    queryFn: () => api.get<Paginated<CashShift>>("/cash-shifts", { "filter[branch_id]": filters.branch_id, page: filters.page, per_page: filters.per_page }),
    placeholderData: keepPreviousData,
    enabled,
  });
}

export function useShift(id: number | null) {
  return useQuery({
    queryKey: billingKeys.shift(id ?? 0),
    queryFn: async () => (await api.get<Wrapped<CashShift>>(`/cash-shifts/${id}`)).data,
    enabled: id !== null,
  });
}

function useShiftMutation<V>(fn: (vars: V) => Promise<CashShift>) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (shift) => {
      queryClient.setQueryData(billingKeys.shift(shift.id), shift);
      queryClient.invalidateQueries({ queryKey: ["cash-shifts"] });
    },
  });
}

export const useOpenShift = () =>
  useShiftMutation(async (input: { branch_id: number; opening_float: number; notes?: string | null }) => (await api.post<Wrapped<CashShift>>("/cash-shifts", input)).data);

export const useCloseShift = () =>
  useShiftMutation(async ({ id, ...input }: { id: number; counted_cash: number; notes?: string | null }) => (await api.post<Wrapped<CashShift>>(`/cash-shifts/${id}/close`, input)).data);

export const useCashMovement = () =>
  useShiftMutation(async ({ id, ...input }: { id: number; type: "in" | "out"; amount: number; reason: string }) => (await api.post<Wrapped<CashShift>>(`/cash-shifts/${id}/movements`, input)).data);

function usePaymentMutation<V extends { orderId: number }>(path: "payments" | "refunds") {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ orderId, ...input }: V) => (await api.post<Wrapped<Payment>>(`/orders/${orderId}/${path}`, input)).data,
    onSuccess: (_payment, { orderId }) => {
      queryClient.invalidateQueries({ queryKey: orderKeys.detail(orderId) });
      queryClient.invalidateQueries({ queryKey: ["orders", "list"] });
      queryClient.invalidateQueries({ queryKey: ["cash-shifts"] });
    },
  });
}

export const usePay = () =>
  usePaymentMutation<{ orderId: number; method: PaymentMethod; amount: number; reference?: string | null; notes?: string | null }>("payments");

export const useRefund = () => usePaymentMutation<{ orderId: number; method: PaymentMethod; amount: number; reason: string }>("refunds");

export function usePromoCodes(enabled = true) {
  return useQuery({
    queryKey: billingKeys.promoCodes,
    queryFn: async () => (await api.get<Wrapped<PromoCode[]>>("/promo-codes")).data,
    enabled,
  });
}

export function useSavePromoCode() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: Partial<SavePromoCodeInput> }) =>
      id ? (await api.put<Wrapped<PromoCode>>(`/promo-codes/${id}`, input)).data : (await api.post<Wrapped<PromoCode>>("/promo-codes", input)).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: billingKeys.promoCodes }),
  });
}

export function useDeletePromoCode() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/promo-codes/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: billingKeys.promoCodes }),
  });
}
