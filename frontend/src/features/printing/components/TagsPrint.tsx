"use client";

import { useParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { useOrder } from "@/features/orders/api";
import { formatDate } from "@/shared/lib/date";
import { toPersianDigits } from "@/shared/lib/digits";
import { Barcode } from "@/shared/ui/Barcode";
import { PrintFrame } from "./PrintFrame";

/**
 * One 50 × 30 mm label per piece: its barcode (scanned at every station
 * and at handover), ticket and piece number, item, customer and due date.
 */
export function TagsPrint() {
  const t = useTranslations("printing");
  const id = Number(useParams<{ id: string }>().id);
  const order = useOrder(id);
  const data = order.data;
  const total = data?.piece_count ?? 0;

  return (
    <PrintFrame ready={Boolean(data)} error={order.isError} pageCss="@page { size: 50mm 30mm; margin: 0; } .tag { margin: 0 !important; break-after: page; }">
      {data && (
        <div>
          <style>{`
            .tag { width: 50mm; height: 30mm; padding: 1.5mm 2mm; box-sizing: border-box; overflow: hidden; font-size: 9px; line-height: 1.35; display: flex; flex-direction: column; }
            .tag .top { display: flex; justify-content: space-between; font-weight: 700; font-size: 10px; }
            .tag .code { direction: ltr; text-align: center; font-family: monospace; font-size: 9px; letter-spacing: .5px; }
            .tag .muted { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
          `}</style>
          {(data.lines ?? []).flatMap((line) =>
            (line.pieces ?? []).map((piece) => (
              <div key={piece.id} className="print-sheet tag" style={{ marginBottom: "4mm" }} data-testid="tag">
                <div className="top">
                  <span style={{ direction: "ltr" }}>{data.ticket_number}</span>
                  <span>{toPersianDigits(`${piece.number}/${total}`)}</span>
                </div>
                <Barcode value={piece.tag_code} height={11} />
                <div className="code">{piece.tag_code}</div>
                <div className="muted">
                  {line.item_name} · {line.service_code}
                  {piece.color_name ? ` · ${piece.color_name}` : ""}
                  {piece.defects.length > 0 ? ` · ${piece.defects.map((d) => d.name).join("، ")}` : ""}
                </div>
                <div className="muted">
                  {data.customer.name} · {t("dueShort")} {formatDate(data.due_at)}
                </div>
              </div>
            )),
          )}
        </div>
      )}
    </PrintFrame>
  );
}
