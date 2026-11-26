import { describe, expect, it } from "vitest";
import { byDate, linesFrom, windowLabel } from "./onlineOrder";

describe("online order helpers", () => {
  it("turns the quantities typed in into order lines, skipping empty ones", () => {
    expect(linesFrom({ "10:20": 2, "11:20": 0, "12:21": 1.5 })).toEqual([
      { item_id: 10, service_type_id: 20, quantity: 2 },
      { item_id: 12, service_type_id: 21, quantity: 1.5 },
    ]);
  });

  it("groups windows by day, keeping their order", () => {
    const slot = (id: number, date: string) => ({ id, date, starts_at: "09:00", ends_at: "12:00", remaining: 1 });
    expect(byDate([slot(1, "2026-12-05"), slot(2, "2026-12-05"), slot(3, "2026-12-06")]).map((day) => [day.date, day.slots.map((s) => s.id)])).toEqual([
      ["2026-12-05", [1, 2]],
      ["2026-12-06", [3]],
    ]);
  });

  it("writes a window with Persian digits", () => {
    expect(windowLabel("14:00", "16:00")).toBe("۱۴:۰۰–۱۶:۰۰");
  });
});
