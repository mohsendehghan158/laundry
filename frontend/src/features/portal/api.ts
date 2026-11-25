"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, isApiError } from "@/shared/api/client";
import type { Wrapped } from "@/shared/api/types";
import { toLatinDigits } from "@/shared/lib/digits";
import type { OtpSent, PortalCustomer, PortalOrder, PortalSettings } from "./types";

export const portalKeys = {
  settings: ["portal", "settings"] as const,
  customer: ["portal", "customer"] as const,
  orders: ["portal", "orders"] as const,
  order: (publicId: string) => ["portal", "orders", publicId] as const,
};

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
      queryClient.removeQueries({ queryKey: ["portal", "orders"] });
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
      queryClient.removeQueries({ queryKey: ["portal", "orders"] });
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
