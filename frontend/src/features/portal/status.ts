import type { PortalOrderStatus } from "./types";

/** The steps a counter order goes through, as the customer sees them. */
export const ORDER_STEPS = ["received", "in_process", "ready", "completed"] as const;

/** An online order starts with its pickup. */
export const ONLINE_ORDER_STEPS = ["pending_pickup", ...ORDER_STEPS] as const;

export function stepsFor(source: "counter" | "online"): readonly PortalOrderStatus[] {
  return source === "online" ? ONLINE_ORDER_STEPS : ORDER_STEPS;
}

export function statusColor(status: PortalOrderStatus): string {
  switch (status) {
    case "cancelled":
      return "red";
    case "ready":
    case "partially_ready":
      return "green";
    case "completed":
      return "default";
    default:
      return "blue";
  }
}

/** Index of the step reached so far; partly ready counts as still in process, picked up as on the way in. */
export function currentStep(status: PortalOrderStatus, steps: readonly PortalOrderStatus[] = ORDER_STEPS): number {
  const shown = status === "partially_ready" ? "in_process" : status === "picked_up" ? "pending_pickup" : status;
  return Math.max(0, steps.indexOf(shown));
}
