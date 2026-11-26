"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, apiFetch, isApiError } from "@/shared/api/client";
import type { Wrapped } from "@/shared/api/types";
import { toLatinDigits } from "@/shared/lib/digits";
import type {
  CustomerSlots,
  LatLng,
  OnlineOrderInput,
  OnlineOrderQuote,
  OtpSent,
  PortalAddress,
  PortalAddressInput,
  PortalCatalog,
  PortalCustomer,
  PortalOrder,
  PortalSettings,
  ZoneCheck,
} from "./types";

export const portalKeys = {
  settings: ["portal", "settings"] as const,
  customer: ["portal", "customer"] as const,
  orders: ["portal", "orders"] as const,
  order: (publicId: string) => ["portal", "orders", publicId] as const,
  addresses: ["portal", "addresses"] as const,
  zoneCheck: (point: LatLng | null) => ["portal", "zone-check", point?.join(",") ?? ""] as const,
  catalog: ["portal", "catalog"] as const,
  slots: (addressId: number | null) => ["portal", "slots", addressId] as const,
  quote: (input: Omit<OnlineOrderInput, "slot_id">) => ["portal", "quote", input] as const,
};

/** What belongs to one customer must not be shown to the next one on this device. */
function forgetCustomerData(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.removeQueries({ queryKey: portalKeys.orders });
  queryClient.removeQueries({ queryKey: portalKeys.addresses });
  queryClient.removeQueries({ queryKey: ["portal", "slots"] });
  queryClient.removeQueries({ queryKey: ["portal", "quote"] });
}

/** The business's name and display unit; public, needed before sign-in. */
export function usePortalSettings() {
  return useQuery({
    queryKey: portalKeys.settings,
    queryFn: async () => (await api.get<Wrapped<PortalSettings>>("/portal/settings")).data,
    staleTime: 60 * 60_000,
  });
}

/**
 * The signed-in customer. `data` is undefined while loading and `null`
 * once the server says no customer is signed in. Staff sessions do not
 * count: the portal has its own sign-in.
 */
export function useCustomer() {
  return useQuery({
    queryKey: portalKeys.customer,
    queryFn: async (): Promise<PortalCustomer | null> => {
      try {
        return (await api.get<Wrapped<PortalCustomer>>("/customer/me")).data;
      } catch (error) {
        if (isApiError(error, 401)) {
          return null;
        }
        throw error;
      }
    },
    staleTime: 5 * 60_000,
  });
}

/** Reduces whatever was typed ("۰۹۱۲ ۳۴۵ ۶۷۸۹", "+98 912…") to 09xxxxxxxxx where possible. */
export function normalizeMobile(value: string): string {
  const digits = toLatinDigits(value).replace(/\D/g, "");
  if (digits.startsWith("98") && digits.length === 12) {
    return `0${digits.slice(2)}`;
  }
  if (digits.length === 10 && digits.startsWith("9")) {
    return `0${digits}`;
  }
  return digits;
}

export function useSendOtp() {
  return useMutation({
    mutationFn: async (mobile: string) => (await api.post<Wrapped<OtpSent>>("/customer/auth/otp", { mobile: normalizeMobile(mobile) })).data,
  });
}

export function useVerifyOtp() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: { mobile: string; code: string }) =>
      (await api.post<Wrapped<PortalCustomer>>("/customer/auth/verify", { mobile: input.mobile, code: toLatinDigits(input.code) })).data,
    onSuccess: (customer) => {
      forgetCustomerData(queryClient);
      queryClient.setQueryData(portalKeys.customer, customer);
    },
  });
}

export function useUpdateName() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (name: string) => (await api.put<Wrapped<PortalCustomer>>("/customer/me", { name })).data,
    onSuccess: (customer) => queryClient.setQueryData(portalKeys.customer, customer),
  });
}

export function useCustomerLogout() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => api.post<void>("/customer/auth/logout"),
    onSettled: () => {
      forgetCustomerData(queryClient);
      queryClient.setQueryData(portalKeys.customer, null);
    },
  });
}

export function useMyOrders() {
  return useQuery({
    queryKey: portalKeys.orders,
    queryFn: async () => (await api.get<Wrapped<PortalOrder[]>>("/customer/orders")).data,
    refetchInterval: 60_000,
  });
}

export function useMyOrder(publicId: string) {
  return useQuery({
    queryKey: portalKeys.order(publicId),
    queryFn: async () => (await api.get<Wrapped<PortalOrder>>(`/customer/orders/${publicId}`)).data,
    refetchInterval: 60_000,
  });
}

export function useAddresses() {
  return useQuery({
    queryKey: portalKeys.addresses,
    queryFn: async () => (await api.get<Wrapped<PortalAddress[]>>("/customer/addresses")).data,
  });
}

export function useSaveAddress() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: PortalAddressInput }) =>
      (id === undefined
        ? await api.post<Wrapped<PortalAddress>>("/customer/addresses", input)
        : await api.put<Wrapped<PortalAddress>>(`/customer/addresses/${id}`, input)
      ).data,
    // Making one address the default changes the others too.
    onSuccess: () => queryClient.invalidateQueries({ queryKey: portalKeys.addresses }),
  });
}

export function useDeleteAddress() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/customer/addresses/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: portalKeys.addresses }),
  });
}

/** Whether a point on the map is served, as the customer moves the pin. */
export function useZoneCheck(point: LatLng | null) {
  return useQuery({
    queryKey: portalKeys.zoneCheck(point),
    queryFn: async () => (await api.get<Wrapped<ZoneCheck>>("/public/zones/check", { latitude: point![0], longitude: point![1] })).data,
    enabled: point !== null,
    staleTime: 10 * 60_000,
    placeholderData: keepPreviousData,
  });
}

/** What can be ordered online, with prices. */
export function usePortalCatalog() {
  return useQuery({
    queryKey: portalKeys.catalog,
    queryFn: async () => (await api.get<Wrapped<PortalCatalog>>("/customer/catalog")).data,
    staleTime: 10 * 60_000,
  });
}

/** The pickup windows that can still be booked for an address. */
export function useCustomerSlots(addressId: number | null) {
  return useQuery({
    queryKey: portalKeys.slots(addressId),
    queryFn: async () => (await api.get<Wrapped<CustomerSlots>>("/customer/slots", { address_id: addressId! })).data,
    enabled: addressId !== null,
    refetchInterval: 60_000,
  });
}

/** The price of an order being put together; null input = nothing to price yet. */
export function useOrderQuote(input: Omit<OnlineOrderInput, "slot_id"> | null) {
  return useQuery({
    queryKey: portalKeys.quote(input ?? { address_id: 0 }),
    queryFn: async () => (await api.post<Wrapped<OnlineOrderQuote>>("/customer/orders/quote", input)).data,
    enabled: input !== null,
    placeholderData: keepPreviousData,
  });
}

export function usePlaceOrder() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ input, key }: { input: OnlineOrderInput; key: string }) =>
      (await apiFetch<Wrapped<PortalOrder>>("/customer/orders", { method: "POST", body: input, headers: { "Idempotency-Key": key } })).data,
    onSuccess: (order) => {
      queryClient.setQueryData(portalKeys.order(order.public_id), order);
      void queryClient.invalidateQueries({ queryKey: portalKeys.orders });
      void queryClient.invalidateQueries({ queryKey: ["portal", "slots"] });
    },
  });
}

export function useCancelOrder() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (publicId: string) => (await api.post<Wrapped<PortalOrder>>(`/customer/orders/${publicId}/cancel`)).data,
    onSuccess: (order) => {
      queryClient.setQueryData(portalKeys.order(order.public_id), order);
      void queryClient.invalidateQueries({ queryKey: portalKeys.orders });
      void queryClient.invalidateQueries({ queryKey: ["portal", "slots"] });
    },
  });
}
