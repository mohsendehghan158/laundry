"use client";

import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { api } from "@/shared/api/client";
import type { Activity, Paginated } from "@/shared/api/types";

export type ActivityFilters = {
  log_name?: string | null;
  event?: string | null;
  causer_id?: number | null;
  /** ISO timestamps. */
  from?: string | null;
  to?: string | null;
  page?: number;
  per_page?: number;
};

export const LOG_NAMES = ["auth", "user", "branch", "role", "settings", "organization"] as const;
export const EVENTS = ["created", "updated", "deleted", "login", "logout", "roles_updated"] as const;

export function useActivityLog(filters: ActivityFilters) {
  return useQuery({
    queryKey: ["activity", filters],
    queryFn: ({ signal }) =>
      api.get<Paginated<Activity>>(
        "/activity-log",
        {
          "filter[log_name]": filters.log_name,
          "filter[event]": filters.event,
          "filter[causer_id]": filters.causer_id,
          "filter[from]": filters.from,
          "filter[to]": filters.to,
          page: filters.page,
          per_page: filters.per_page,
        },
        signal,
      ),
    placeholderData: keepPreviousData,
  });
}
