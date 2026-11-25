"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, apiFetch } from "@/shared/api/client";
import type { Order, OrderPayload, OrderStatus, Paginated, PricePreview, StoreOrderInput, Wrapped } from "@/shared/api/types";

export type OrderFilters = {
  q?: string;
  status?: OrderStatus[] | null;
  branch_id?: number | null;
  customer_id?: number | null;
  due?: "today" | "overdue" | null;
  sort?: string | null;
  page?: number;
  per_page?: number;
};

export const orderKeys = {
  all: ["orders"] as const,
  list: (filters: OrderFilters) => ["orders", "list", filters] as const,
  detail: (id: number) => ["orders", "detail", id] as const,
};

export function useOrders(filters: OrderFilters, enabled = true) {
  return useQuery({
    queryKey: orderKeys.list(filters),
    queryFn: ({ signal }) =>
      api.get<Paginated<Order>>(
        "/orders",
        {
          "filter[q]": filters.q,
          "filter[status]": filters.status ?? undefined,
          "filter[branch_id]": filters.branch_id,
          "filter[customer_id]": filters.customer_id,
          "filter[due]": filters.due,
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

export function useOrder(id: number | null | undefined) {
  return useQuery({
    queryKey: orderKeys.detail(id ?? 0),
    queryFn: async () => (await api.get<Wrapped<Order>>(`/orders/${id}`)).data,
    enabled: Boolean(id),
  });
}

/**
 * Takes an order in. The idempotency key makes a retry after a network
 * error return the same order instead of creating a second one.
 */
export function useCreateOrder() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ input, idempotencyKey }: { input: StoreOrderInput; idempotencyKey: string }) =>
      (await apiFetch<Wrapped<Order>>("/orders", { method: "POST", body: input, headers: { "Idempotency-Key": idempotencyKey } })).data,
    onSuccess: (order) => {
      queryClient.setQueryData(orderKeys.detail(order.id), order);
      queryClient.invalidateQueries({ queryKey: ["orders", "list"] });
    },
  });
}

export function useTransitionOrder() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, to, reason }: { id: number; to: OrderStatus; reason?: string }) => (await api.post<Wrapped<Order>>(`/orders/${id}/transitions`, { to, reason })).data,
    onSuccess: (order) => {
      queryClient.setQueryData(orderKeys.detail(order.id), order);
      queryClient.invalidateQueries({ queryKey: ["orders", "list"] });
    },
  });
}

/** Server-side price of an order being put together; refreshed as the cart changes. */
export function usePricePreview(payload: OrderPayload | null) {
  return useQuery({
    queryKey: ["orders", "preview", payload],
    queryFn: async ({ signal }) => (await apiFetch<Wrapped<PricePreview>>("/orders/price-preview", { method: "POST", body: payload, signal })).data,
    enabled: payload !== null && payload.lines.length > 0,
    placeholderData: keepPreviousData,
    staleTime: 60_000,
    retry: false,
  });
}

/** Finds an order by a scanned ticket number or piece tag. */
export function useOrderLookup(code: string | null) {
  return useQuery({
    queryKey: ["orders", "lookup", code],
    queryFn: async () => {
      const result = await api.get<{ data: Order; matched_piece_id: number | null }>("/orders/lookup", { code });
      return { order: result.data, matchedPieceId: result.matched_piece_id };
    },
    enabled: Boolean(code),
    retry: false,
  });
}

export function useHandOver() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, pieceIds }: { id: number; pieceIds: number[] }) =>
      (await api.post<Wrapped<Order>>(`/orders/${id}/handover`, { piece_ids: pieceIds })).data,
    onSuccess: (order) => {
      queryClient.setQueryData(orderKeys.detail(order.id), order);
      queryClient.invalidateQueries({ queryKey: ["orders"] });
    },
  });
}

export function useUpdateRack() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, rack }: { id: number; rack: string | null }) => (await api.put<Wrapped<Order>>(`/orders/${id}/rack`, { rack_location: rack })).data,
    onSuccess: (order) => {
      queryClient.setQueryData(orderKeys.detail(order.id), order);
      queryClient.invalidateQueries({ queryKey: ["orders"] });
    },
  });
}
