"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/shared/api/client";
import type { NotificationLog, NotificationTemplate, Paginated, Wrapped } from "@/shared/api/types";

export function useNotificationTemplates(enabled = true) {
  return useQuery({
    queryKey: ["notification-templates"],
    queryFn: () => api.get<{ data: NotificationTemplate[]; placeholders: string[] }>("/notification-templates"),
    enabled,
  });
}

export function useSaveTemplate() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ event, body, is_active }: { event: NotificationTemplate["event"]; body: string; is_active: boolean }) =>
      (await api.put<Wrapped<NotificationTemplate>>(`/notification-templates/${event}`, { body, is_active })).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["notification-templates"] }),
  });
}

export function useNotificationLogs(filters: { status?: string | null; order_id?: number | null; page?: number; per_page?: number }, enabled = true) {
  return useQuery({
    queryKey: ["notification-logs", filters],
    queryFn: () =>
      api.get<Paginated<NotificationLog>>("/notification-logs", {
        "filter[status]": filters.status,
        "filter[order_id]": filters.order_id,
        page: filters.page,
        per_page: filters.per_page,
      }),
    placeholderData: keepPreviousData,
    enabled,
  });
}

export function useRetryNotification() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => (await api.post<Wrapped<NotificationLog>>(`/notification-logs/${id}/retry`)).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["notification-logs"] }),
  });
}

/** Fills placeholders with sample values, to preview a message while editing it. */
export function previewMessage(body: string, sample: Record<string, string>): string {
  return body.replace(/\{(\w+)\}/g, (match, key: string) => sample[key] ?? match);
}
