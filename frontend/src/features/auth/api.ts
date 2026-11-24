"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, isApiError } from "@/shared/api/client";
import type { AuthUser, LoginInput, UpdatePasswordInput, Wrapped } from "@/shared/api/types";

export const meKey = ["me"] as const;

/**
 * The signed-in user with permissions and branches. `data` is undefined
 * while loading and `null` once the server says nobody is signed in.
 */
export function useMe() {
  return useQuery({
    queryKey: meKey,
    queryFn: async (): Promise<AuthUser | null> => {
      try {
        return (await api.get<Wrapped<AuthUser>>("/me")).data;
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

export function useLogin() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: LoginInput) => (await api.post<Wrapped<AuthUser>>("/auth/login", input)).data,
    onSuccess: (user) => {
      queryClient.clear();
      queryClient.setQueryData(meKey, user);
    },
  });
}

export function useLogout() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => api.post<void>("/auth/logout"),
    onSettled: () => {
      queryClient.clear();
      queryClient.setQueryData(meKey, null);
    },
  });
}

export function useChangePassword() {
  return useMutation({
    mutationFn: (input: UpdatePasswordInput) => api.put<void>("/me/password", input),
  });
}
