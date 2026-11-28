"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, apiDownload } from "@/shared/api/client";
import type {
  Color,
  CounterCatalog,
  DefectType,
  Item,
  ItemCategory,
  PriceCell,
  PriceImport,
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

/** Prices of the default list, or of a contract list (only the pairs it overrides). */
export function usePrices(priceListId: number | null = null) {
  return useQuery({
    queryKey: [...catalogKeys.prices, priceListId ?? "default"],
    queryFn: async () => (await api.get<Wrapped<PriceCell[]>>("/prices", { price_list_id: priceListId ?? undefined })).data,
  });
}

export type PriceListSummary = { id: number; name: string; is_default: boolean; prices_count: number };

export function usePriceLists(enabled = true) {
  return useQuery({
    queryKey: ["catalog", "price-lists"],
    queryFn: async () => (await api.get<Wrapped<PriceListSummary[]>>("/price-lists")).data,
    enabled,
  });
}

export function useCreatePriceList() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: { name: string; percent: number | null }) => (await api.post<Wrapped<PriceListSummary>>("/price-lists", input)).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["catalog", "price-lists"] }),
  });
}

export function useDeletePriceList() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/price-lists/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["catalog", "price-lists"] }),
  });
}

export type PriceChange = { item_id: number; service_type_id: number; price: number | null };

export function useUpdatePrices(priceListId: number | null = null) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (prices: PriceChange[]) => (await api.put<Wrapped<PriceCell[]>>("/prices", { prices, price_list_id: priceListId })).data,
    onSuccess: (prices) => {
      queryClient.setQueryData([...catalogKeys.prices, priceListId ?? "default"], prices);
      queryClient.invalidateQueries({ queryKey: catalogKeys.counter });
      queryClient.invalidateQueries({ queryKey: ["catalog", "price-lists"] });
    },
  });
}

export function useExportPrices() {
  return useMutation({ mutationFn: () => apiDownload("/prices/export") });
}

/** Compares an edited price list workbook with the prices; with `apply`, saves it. */
export function useImportPrices() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ file, apply }: { file: File; apply: boolean }) => {
      const form = new FormData();
      form.append("file", file);
      form.append("apply", apply ? "1" : "0");
      return (await api.post<Wrapped<PriceImport>>("/prices/import", form)).data;
    },
    onSuccess: (result) => {
      if (result.applied) {
        queryClient.invalidateQueries({ queryKey: catalogKeys.prices });
        queryClient.invalidateQueries({ queryKey: catalogKeys.counter });
      }
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
