"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/shared/api/client";
import type {
  Color,
  CounterCatalog,
  DefectType,
  Item,
  ItemCategory,
  PriceCell,
  ServiceType,
  TurnaroundTier,
  Upcharge,
  Wrapped,
} from "@/shared/api/types";

/** Catalog collections that share the same list/create/update/delete endpoints. */
export type CatalogResources = {
  "service-types": ServiceType;
  "item-categories": ItemCategory;
  items: Item;
  upcharges: Upcharge;
  "turnaround-tiers": TurnaroundTier;
  "defect-types": DefectType;
  colors: Color;
};
export type CatalogResource = keyof CatalogResources;

export const catalogKeys = {
  all: ["catalog"] as const,
  list: (resource: CatalogResource) => ["catalog", resource] as const,
  prices: ["catalog", "prices"] as const,
  counter: ["catalog", "counter"] as const,
};

export function useCatalogList<R extends CatalogResource>(resource: R, enabled = true) {
  return useQuery({
    queryKey: catalogKeys.list(resource),
    queryFn: async () => (await api.get<Wrapped<CatalogResources[R][]>>(`/${resource}`)).data,
    enabled,
  });
}

export function useSaveCatalogEntry<R extends CatalogResource>(resource: R) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: Record<string, unknown> }) =>
      id
        ? (await api.put<Wrapped<CatalogResources[R]>>(`/${resource}/${id}`, input)).data
        : (await api.post<Wrapped<CatalogResources[R]>>(`/${resource}`, input)).data,
    // A change anywhere can alter the counter catalog and the price matrix.
    onSuccess: () => queryClient.invalidateQueries({ queryKey: catalogKeys.all }),
  });
}

export function useDeleteCatalogEntry(resource: CatalogResource) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/${resource}/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: catalogKeys.all }),
  });
}

export function usePrices() {
  return useQuery({
    queryKey: catalogKeys.prices,
    queryFn: async () => (await api.get<Wrapped<PriceCell[]>>("/prices")).data,
  });
}

export type PriceChange = { item_id: number; service_type_id: number; price: number | null };

export function useUpdatePrices() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (prices: PriceChange[]) => (await api.put<Wrapped<PriceCell[]>>("/prices", { prices })).data,
    onSuccess: (prices) => {
      queryClient.setQueryData(catalogKeys.prices, prices);
      queryClient.invalidateQueries({ queryKey: catalogKeys.counter });
    },
  });
}

/** Everything the counter needs, cached for the session so intake stays fast. */
export function useCounterCatalog(enabled = true) {
  return useQuery({
    queryKey: catalogKeys.counter,
    queryFn: async () => (await api.get<Wrapped<CounterCatalog>>("/catalog")).data,
    staleTime: 10 * 60_000,
    enabled,
  });
}

/** "itemId:serviceId" → price in rials. */
export function priceKey(itemId: number, serviceTypeId: number): string {
  return `${itemId}:${serviceTypeId}`;
}

export function indexPrices(cells: PriceCell[] | undefined): Map<string, number> {
  return new Map((cells ?? []).map((cell) => [priceKey(cell.item_id, cell.service_type_id), cell.price]));
}
