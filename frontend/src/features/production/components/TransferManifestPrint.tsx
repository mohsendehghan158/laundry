"use client";

import { useParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { PrintFrame } from "@/features/printing/components/PrintFrame";
import { formatDateTime } from "@/shared/lib/date";
import { formatNumber, toPersianDigits } from "@/shared/lib/digits";
import { Barcode } from "@/shared/ui/Barcode";
import { useTransfer } from "../movementApi";

/** The A4 manifest that travels with the bags: every piece by order, with signatures. */
export function TransferManifestPrint() {
  const t = useTranslations("transfers");
  const id = Number(useParams<{ id: string }>().id);
  const transfer = useTransfer(Number.isFinite(id) ? id : null);
  const data = transfer.data;
  const byOrder = new Map<string, NonNullable<typeof data>["pieces"]>();
  for (const piece of data?.pieces ?? []) {
    byOrder.set(piece.ticket_number, [...(byOrder.get(piece.ticket_number) ?? []), piece]);
  }

  return (
    <PrintFrame ready={Boolean(data)} error={transfer.isError} pageCss="@page { size: A4; margin: 12mm; }">
      {data && (
        <div className="print-sheet manifest" style={{ fontSize: 12, lineHeight: 1.7 }}>
          <style>{`
            .manifest h1 { font-size: 18px; margin: 0; }
            .manifest table { width: 100%; border-collapse: collapse; margin-top: 4mm; }
            .manifest th, .manifest td { border: 1px solid #999; padding: 1.5mm 2mm; text-align: start; }
            .manifest .ltr { direction: ltr; unicode-bidi: plaintext; }
            .manifest .sign { display: flex; gap: 12mm; margin-top: 10mm; }
            .manifest .sign div { flex: 1; border-top: 1px solid #000; padding-top: 2mm; }
          `}</style>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
            <div>
              <h1>{t("manifestHeading", { number: data.number })}</h1>
              <div>{t("route", { from: data.from_branch.name, to: data.to_branch.name })}</div>
              <div>
                {t("printedAt", { date: formatDateTime(data.sent_at ?? data.created_at) })} · {t("fields.bags")}: {data.bags_sent === null ? "—" : formatNumber(data.bags_sent)} ·{" "}
                {t("fields.pieces")}: {formatNumber(data.piece_count)}
              </div>
              {data.note && <div>{data.note}</div>}
            </div>
            <div style={{ width: "60mm" }}>
              <Barcode value={data.number} height={10} />
            </div>
          </div>
          <table>
            <thead>
              <tr>
                <th style={{ width: "8mm" }}>#</th>
                <th>{t("printTicket")}</th>
                <th>{t("printTag")}</th>
                <th>{t("printItem")}</th>
                <th style={{ width: "16mm" }}>{t("printChecked")}</th>
              </tr>
            </thead>
            <tbody>
              {[...byOrder.entries()].flatMap(([ticket, pieces], groupIndex) =>
                (pieces ?? []).map((piece, index) => (
                  <tr key={piece.id}>
                    <td>{index === 0 ? toPersianDigits(groupIndex + 1) : ""}</td>
                    <td className="ltr">{index === 0 ? ticket : ""}</td>
                    <td className="ltr">{piece.tag_code}</td>
                    <td>{[piece.item_name, piece.color_name].filter(Boolean).join(" · ")}</td>
                    <td />
                  </tr>
                )),
              )}
            </tbody>
          </table>
          <div className="sign">
            <div>{t("printSender", { name: data.sender_name ?? data.creator_name ?? "" })}</div>
            <div>{t("printCarrier")}</div>
            <div>{t("printReceiver")}</div>
          </div>
        </div>
      )}
    </PrintFrame>
  );
}
