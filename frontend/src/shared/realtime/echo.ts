"use client";

import Echo from "laravel-echo";
import Pusher from "pusher-js";
import { api } from "@/shared/api/client";
import type { RealtimeConfig } from "@/shared/api/types";

let instance: Echo<"reverb"> | null = null;

/**
 * The page's single socket connection to Reverb, made on first use from
 * the settings the API hands out; null when live updates are off.
 * Private channels are authorised with the session, like any API call.
 */
export function getEcho(config: RealtimeConfig | undefined): Echo<"reverb"> | null {
  if (typeof window === "undefined" || !config?.enabled || !config.key) {
    return null;
  }
  if (instance) {
    return instance;
  }

  const tls = config.scheme === "https";
  instance = new Echo({
    broadcaster: "reverb",
    Pusher,
    key: config.key,
    wsHost: config.host || window.location.hostname,
    wsPort: config.port,
    wssPort: config.port,
    forceTLS: tls,
    enabledTransports: tls ? ["wss"] : ["ws"],
    channelAuthorization: {
      endpoint: "/api/v1/broadcasting/auth",
      transport: "ajax",
      customHandler: ({ socketId, channelName }: { socketId: string; channelName: string }, callback: (error: Error | null, data: { auth: string } | null) => void) => {
        api.post<{ auth: string }>("/broadcasting/auth", { socket_id: socketId, channel_name: channelName }).then(
          (data) => callback(null, data),
          (error: Error) => callback(error, null),
        );
      },
    },
  });
  return instance;
}

/** Whether the socket is connected right now. */
export function isConnected(echo: Echo<"reverb"> | null): boolean {
  const connector = echo?.connector as { pusher?: { connection?: { state?: string } } } | undefined;
  return connector?.pusher?.connection?.state === "connected";
}

/** Calls back whenever the socket connects or drops; returns an unsubscribe. */
export function onConnectionChange(echo: Echo<"reverb"> | null, callback: () => void): () => void {
  const connection = (echo?.connector as { pusher?: { connection?: { bind: (event: string, cb: () => void) => void; unbind: (event: string, cb: () => void) => void } } } | undefined)?.pusher?.connection;
  if (!connection) {
    return () => {};
  }
  connection.bind("state_change", callback);
  return () => connection.unbind("state_change", callback);
}
