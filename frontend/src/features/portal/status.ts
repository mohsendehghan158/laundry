import type { PortalOrderStatus } from "./types";

/** The steps a counter order goes through, as the customer sees them. */
export const ORDER_STEPS = ["received", "in_process", "ready", "completed"] as const;

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

/** Index of the step reached so far; partly ready counts as still in process. */
export function currentStep(status: PortalOrderStatus): number {
  if (status === "partially_ready") {
    return 1;
  }
  return Math.max(0, ORDER_STEPS.indexOf(status as (typeof ORDER_STEPS)[number]));
}
