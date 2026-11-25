"use client";

import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { api, type Query } from "@/shared/api/client";
import type { Activity, Paginated } from "@/shared/api/types";

export function useActivity(query: Query, enabled = true) {
  return useQuery({
    queryKey: ["activity", query],
    queryFn: ({ signal }) => api<Paginated<Activity>>("/activity-log", { query, signal }),
    placeholderData: keepPreviousData,
    enabled,
  });
}
