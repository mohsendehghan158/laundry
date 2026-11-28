import type { DiscountType, Item, OrderPayload } from "./types";

export type CartPiece = {
  color_id: number | null;
  brand: string;
  notes: string;
  defect_type_ids: number[];
};

export type CartLine = {
  /** Stable key for React lists; lines have no id until the order is saved. */
  key: string;
  item_id: number;
  service_type_id: number;
  unit: Item["unit"];
  /** Physical pieces one unit makes (a suit is 2). */
  pieces_per_unit: number;
  quantity: number;
  upcharge_ids: number[];
  notes: string;
  /** Descriptions of the line's pieces, in tag order; may be shorter than the piece count. */
  pieces: CartPiece[];
};

export type CartDiscount = { type: DiscountType; value: number; reason: string };

export type Cart = {
  customerId: number | null;
  lines: CartLine[];
  tierId: number | null;
  discount: CartDiscount | null;
  /** A promo code replaces any manual discount. */
  promoCode: string;
  notes: string;
  /** The company department the order is charged to, for customers of a company. */
  departmentId?: number | null;
};

export type CartAction =
  | { type: "setCustomer"; customerId: number | null }
  | { type: "add"; item: Pick<Item, "id" | "unit" | "pieces_per_unit">; serviceTypeId: number }
  | { type: "setQuantity"; key: string; quantity: number }
  | { type: "remove"; key: string }
  | { type: "toggleUpcharge"; key: string; upchargeId: number }
  | { type: "setLineNotes"; key: string; notes: string }
  | { type: "setPieces"; key: string; pieces: CartPiece[] }
  | { type: "setTier"; tierId: number | null }
  | { type: "setDiscount"; discount: CartDiscount | null }
  | { type: "setPromoCode"; code: string }
  | { type: "setNotes"; notes: string }
  | { type: "setDepartment"; departmentId: number | null }
  | { type: "reset" };

export const emptyCart: Cart = { customerId: null, lines: [], tierId: null, discount: null, promoCode: "", notes: "" };

export const emptyPiece = (): CartPiece => ({ color_id: null, brand: "", notes: "", defect_type_ids: [] });

let counter = 0;
const newKey = () => `line-${Date.now().toString(36)}-${(counter++).toString(36)}`;

/** Number of tagged pieces a line will produce. */
export function pieceCount(line: Pick<CartLine, "unit" | "quantity" | "pieces_per_unit">): number {
  return line.unit === "piece" ? Math.max(0, Math.floor(line.quantity)) * line.pieces_per_unit : 1;
}

function isPlain(line: CartLine): boolean {
  return line.upcharge_ids.length === 0 && line.notes === "" && line.pieces.length === 0;
}

export function cartReducer(cart: Cart, action: CartAction): Cart {
  const update = (key: string, change: (line: CartLine) => CartLine): Cart => ({
    ...cart,
    lines: cart.lines.map((line) => (line.key === key ? change(line) : line)),
  });

  switch (action.type) {
    case "setCustomer":
      return { ...cart, customerId: action.customerId, departmentId: null };

    case "setDepartment":
      return { ...cart, departmentId: action.departmentId };

    case "add": {
      // Tapping the same counted item again adds one more, unless that line
      // has been customised (then a separate line keeps the details apart).
      const existing = cart.lines.find(
        (line) => line.item_id === action.item.id && line.service_type_id === action.serviceTypeId && line.unit === "piece" && isPlain(line),
      );
      if (existing) {
        return update(existing.key, (line) => ({ ...line, quantity: line.quantity + 1 }));
      }
      return {
        ...cart,
        lines: [
          ...cart.lines,
          {
            key: newKey(),
            item_id: action.item.id,
            service_type_id: action.serviceTypeId,
            unit: action.item.unit,
            pieces_per_unit: action.item.pieces_per_unit,
            quantity: 1,
            upcharge_ids: [],
            notes: "",
            pieces: [],
          },
        ],
      };
    }

    case "setQuantity":
      return update(action.key, (line) => {
        const quantity = line.unit === "piece" ? Math.max(1, Math.round(action.quantity)) : Math.max(0.1, Math.round(action.quantity * 1000) / 1000);
        // Drop descriptions of pieces that no longer exist.
        return { ...line, quantity, pieces: line.pieces.slice(0, pieceCount({ ...line, quantity })) };
      });

    case "remove":
      return { ...cart, lines: cart.lines.filter((line) => line.key !== action.key) };

    case "toggleUpcharge":
      return update(action.key, (line) => ({
        ...line,
        upcharge_ids: line.upcharge_ids.includes(action.upchargeId)
          ? line.upcharge_ids.filter((id) => id !== action.upchargeId)
          : [...line.upcharge_ids, action.upchargeId],
      }));

    case "setLineNotes":
      return update(action.key, (line) => ({ ...line, notes: action.notes }));

    case "setPieces":
      return update(action.key, (line) => ({ ...line, pieces: action.pieces.slice(0, pieceCount(line)) }));

    case "setTier":
      return { ...cart, tierId: action.tierId };

    case "setDiscount":
      return { ...cart, discount: action.discount && action.discount.value > 0 ? action.discount : null, promoCode: action.discount ? "" : cart.promoCode };

    case "setPromoCode":
      return { ...cart, promoCode: action.code.trim().toUpperCase(), discount: action.code.trim() ? null : cart.discount };

    case "setNotes":
      return { ...cart, notes: action.notes };

    case "reset":
      return emptyCart;
  }
}

/** The lines, tier and discount in the shape the API prices and stores. */
export function toPayload(cart: Cart): OrderPayload {
  return {
    lines: cart.lines.map((line) => ({
      item_id: line.item_id,
      service_type_id: line.service_type_id,
      quantity: line.quantity,
      upcharge_ids: line.upcharge_ids,
      notes: line.notes || null,
      pieces: trimPieces(line.pieces).map((piece) => ({
        color_id: piece.color_id,
        brand: piece.brand || null,
        notes: piece.notes || null,
        defect_type_ids: piece.defect_type_ids,
      })),
    })),
    turnaround_tier_id: cart.tierId,
    customer_id: cart.customerId,
    ...(cart.departmentId ? { company_department_id: cart.departmentId } : {}),
    ...(cart.promoCode
      ? { promo_code: cart.promoCode }
      : { discount: cart.discount ? { type: cart.discount.type, value: cart.discount.value, reason: cart.discount.reason || null } : null }),
  };
}

/** Drops empty descriptions at the end, so untouched pieces are not sent. */
function trimPieces(pieces: CartPiece[]): CartPiece[] {
  let end = pieces.length;
  while (end > 0) {
    const piece = pieces[end - 1];
    if (piece.color_id !== null || piece.brand !== "" || piece.notes !== "" || piece.defect_type_ids.length > 0) {
      break;
    }
    end--;
  }
  return pieces.slice(0, end);
}
