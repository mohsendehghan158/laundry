import type { PieceFlag, PieceStatus } from "@/shared/api/types";

export const PIECE_STATUS_COLOR: Record<PieceStatus, string> = {
  received: "blue",
  in_process: "gold",
  assembled: "cyan",
  ready: "green",
  handed_over: "default",
};

export const PIECE_FLAG_COLOR: Record<PieceFlag, string> = {
  rework: "orange",
  on_hold: "magenta",
  outsourced: "purple",
  in_transit: "geekblue",
  damaged: "volcano",
  lost: "red",
};
