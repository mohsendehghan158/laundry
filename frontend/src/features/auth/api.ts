"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/shared/api/client";
import type { AuthUser, UpdatePasswordBody, Wrapped } from "@/shared/api/types";

export const meKey = ["me"] as const;

/** The signed-in user with permissions and branches; the root of everything the panel shows. */
export function useMe() {
  return useQuery({
    queryKey: meKey,
    queryFn: async ({ signal }) => (await api<Wrapped<AuthUser>>("/me", { signal })).data,
    staleTime: 5 * 60_000,
    retry: false,
  });
}

export function useLogin() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (body: { username: string; password: string; remember?: boolean }) =>
      (await api<Wrapped<AuthUser>>("/auth/login", { method: "POST", body })).data,
    onSuccess: (user) => {
      queryClient.clear();
      queryClient.setQueryData(meKey, user);
    },
  });
}

export function useLogout() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => api<void>("/auth/logout", { method: "POST" }),
    onSettled: () => queryClient.clear(),
  });
}

export function useChangePassword() {
  return useMutation({
    mutationFn: (body: UpdatePasswordBody) => api<void>("/me/password", { method: "PUT", body }),
  });
}
