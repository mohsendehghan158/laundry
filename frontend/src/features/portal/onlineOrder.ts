/** Helpers for putting an online order together in the portal. */
import { toPersianDigits } from "@/shared/lib/digits";
import type { CustomerSlots, OnlineOrderInput, PortalAddress } from "./types";

type Slot = CustomerSlots["slots"][number];

/** Quantities typed in, keyed "item:service". */
export type Quantities = Record<string, number>;

export function newIdempotencyKey(): string {
  return typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
}

export function isServed(address: PortalAddress): boolean {
  return address.zone !== null && address.latitude !== null && address.longitude !== null;
}

/** "۱۴:۰۰–۱۶:۰۰" */
export function windowLabel(from: string, to: string): string {
  return toPersianDigits(`${from}–${to}`);
}

/** Windows grouped by their local date, in the order they come. */
export function byDate(slots: Slot[]): Array<{ date: string; slots: Slot[] }> {
  const days: Array<{ date: string; slots: Slot[] }> = [];
  for (const slot of slots) {
    const day = days.at(-1);
    if (day?.date === slot.date) {
      day.slots.push(slot);
    } else {
      days.push({ date: slot.date, slots: [slot] });
    }
  }
  return days;
}

/** The lines of the order from the quantities typed in, keyed "item:service". */
export function linesFrom(quantities: Quantities): NonNullable<OnlineOrderInput["lines"]> {
  return Object.entries(quantities)
    .filter(([, quantity]) => quantity > 0)
    .map(([key, quantity]) => {
      const [item, service] = key.split(":").map(Number);
      return { item_id: item, service_type_id: service, quantity };
    });
}
