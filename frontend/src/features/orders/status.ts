import type { OrderStatus, PaymentStatus } from "@/shared/api/types";

/** Tag colors for order and payment statuses. */
export const ORDER_STATUS_COLOR: Record<OrderStatus, string> = {
  draft: "default",
  pending_pickup: "purple",
  picked_up: "purple",
  received: "blue",
  in_process: "gold",
  partially_ready: "lime",
  ready: "green",
  out_for_delivery: "cyan",
  completed: "default",
  cancelled: "red",
};

export const PAYMENT_STATUS_COLOR: Record<PaymentStatus, string> = {
  unpaid: "red",
  partially_paid: "orange",
  paid: "green",
  refunded: "default",
};

export const OPEN_STATUSES: OrderStatus[] = ["received", "in_process", "partially_ready", "ready"];
