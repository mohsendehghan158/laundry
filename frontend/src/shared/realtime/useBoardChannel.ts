"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useRef, useSyncExternalStore } from "react";
import { api } from "@/shared/api/client";
import type { RealtimeConfig, Wrapped } from "@/shared/api/types";
import { getEcho, isConnected, onConnectionChange } from "./echo";

export function useRealtimeConfig() {
  return useQuery({
    queryKey: ["realtime"],
    queryFn: async () => (await api.get<Wrapped<RealtimeConfig>>("/realtime")).data,
    staleTime: Infinity,
  });
}

type Handlers = {
  /** Something on the board changed: reload it. */
  onChange: () => void;
  /** Orders just passed their due time. */
  onOverdue?: (tickets: string[]) => void;
};

/**
 * Listens to a branch's production board channel. Returns whether the
 * socket is live; when it is not, callers keep refreshing on a timer.
 */
export function useBoardChannel(branchId: number | null, handlers: Handlers): boolean {
  const config = useRealtimeConfig();
  const echo = getEcho(config.data);
  const latest = useRef(handlers);

  useEffect(() => {
    latest.current = handlers;
  });

  useEffect(() => {
    if (!echo || branchId === null) {
      return;
    }
    const name = `plant.${branchId}.board`;
    echo
      .private(name)
      .listen(".board.changed", () => latest.current.onChange())
      .listen(".orders.overdue", (event: { tickets: string[] }) => latest.current.onOverdue?.(event.tickets));
    return () => echo.leave(name);
  }, [echo, branchId]);

  return useSyncExternalStore(
    (callback) => onConnectionChange(echo, callback),
    () => isConnected(echo),
    () => false,
  );
}
