import { describe, expect, it } from "vitest";
import { cartReducer, emptyCart, emptyPiece, pieceCount, toPayload, type Cart } from "./cart";

const shirt = { id: 1, unit: "piece" as const, pieces_per_unit: 1 };
const suit = { id: 2, unit: "piece" as const, pieces_per_unit: 2 };
const laundry = { id: 3, unit: "kg" as const, pieces_per_unit: 1 };

function cartWith(...actions: Parameters<typeof cartReducer>[1][]): Cart {
  return actions.reduce(cartReducer, emptyCart);
}

describe("cart", () => {
  it("adds one more when the same plain item is tapped again", () => {
    const cart = cartWith({ type: "add", item: shirt, serviceTypeId: 10 }, { type: "add", item: shirt, serviceTypeId: 10 });
    expect(cart.lines).toHaveLength(1);
    expect(cart.lines[0].quantity).toBe(2);
  });

  it("keeps different services, weighed items and customised lines apart", () => {
    let cart = cartWith({ type: "add", item: shirt, serviceTypeId: 10 }, { type: "add", item: shirt, serviceTypeId: 11 });
    expect(cart.lines).toHaveLength(2);

    cart = cartWith({ type: "add", item: laundry, serviceTypeId: 12 }, { type: "add", item: laundry, serviceTypeId: 12 });
    expect(cart.lines).toHaveLength(2);

    cart = cartWith({ type: "add", item: shirt, serviceTypeId: 10 });
    cart = cartReducer(cart, { type: "toggleUpcharge", key: cart.lines[0].key, upchargeId: 5 });
    cart = cartReducer(cart, { type: "add", item: shirt, serviceTypeId: 10 });
    expect(cart.lines.map((line) => line.quantity)).toEqual([1, 1]);
  });

  it("counts pieces from quantity and pieces per unit", () => {
    expect(pieceCount({ unit: "piece", quantity: 3, pieces_per_unit: 2 })).toBe(6);
    expect(pieceCount({ unit: "kg", quantity: 2.5, pieces_per_unit: 1 })).toBe(1);
  });

  it("keeps counted quantities whole and at least one, and weights positive", () => {
    let cart = cartWith({ type: "add", item: suit, serviceTypeId: 10 }, { type: "add", item: laundry, serviceTypeId: 12 });
    const [suitLine, laundryLine] = cart.lines;
    cart = cartReducer(cart, { type: "setQuantity", key: suitLine.key, quantity: 2.6 });
    cart = cartReducer(cart, { type: "setQuantity", key: laundryLine.key, quantity: 2.5 });
    expect(cart.lines.map((line) => line.quantity)).toEqual([3, 2.5]);
    cart = cartReducer(cart, { type: "setQuantity", key: suitLine.key, quantity: 0 });
    expect(cart.lines[0].quantity).toBe(1);
  });

  it("drops piece descriptions when the quantity shrinks", () => {
    let cart = cartWith({ type: "add", item: suit, serviceTypeId: 10 });
    const key = cart.lines[0].key;
    cart = cartReducer(cart, { type: "setQuantity", key, quantity: 2 });
    cart = cartReducer(cart, { type: "setPieces", key, pieces: [1, 2, 3, 4, 5].map(() => ({ ...emptyPiece(), brand: "x" })) });
    expect(cart.lines[0].pieces).toHaveLength(4);
    cart = cartReducer(cart, { type: "setQuantity", key, quantity: 1 });
    expect(cart.lines[0].pieces).toHaveLength(2);
  });

  it("builds the API payload, leaving out untouched trailing pieces and empty notes", () => {
    let cart = cartWith({ type: "add", item: suit, serviceTypeId: 10 }, { type: "setTier", tierId: 7 });
    const key = cart.lines[0].key;
    cart = cartReducer(cart, { type: "setPieces", key, pieces: [{ ...emptyPiece(), color_id: 4, defect_type_ids: [9] }, emptyPiece()] });
    cart = cartReducer(cart, { type: "setDiscount", discount: { type: "percent", value: 5, reason: "" } });

    expect(toPayload(cart)).toEqual({
      lines: [{ item_id: 2, service_type_id: 10, quantity: 1, upcharge_ids: [], notes: null, pieces: [{ color_id: 4, brand: null, notes: null, defect_type_ids: [9] }] }],
      turnaround_tier_id: 7,
      discount: { type: "percent", value: 5, reason: null },
    });
  });

  it("treats a zero discount as none", () => {
    const cart = cartWith({ type: "setDiscount", discount: { type: "amount", value: 0, reason: "" } });
    expect(cart.discount).toBeNull();
  });
});
