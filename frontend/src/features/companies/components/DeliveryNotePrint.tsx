"use client";

import { useParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { useReceiptSettings } from "@/features/printing/api";
import { PrintFrame } from "@/features/printing/components/PrintFrame";
import { formatDateTime } from "@/shared/lib/date";
import { formatNumber, toPersianDigits } from "@/shared/lib/digits";
import { useDeliveryNote } from "../api";

/** An A5 delivery note with both counts and room for both signatures. */
export function DeliveryNotePrint() {
  const t = useTranslations();
  const id = Number(useParams<{ id: string }>().id);
  const note = useDeliveryNote(Number.isFinite(id) ? id : null);
  const settings = useReceiptSettings();
  const data = note.data;
  const incoming = data?.type === "in";

  return (
    <PrintFrame ready={Boolean(data && settings.data)} error={note.isError || settings.isError} pageCss="@page { size: A5 landscape; margin: 8mm; }">
      {data && settings.data && (
        <div className="print-sheet delivery" style={{ width: "194mm", padding: "5mm", fontSize: 11, lineHeight: 1.7 }}>
          <style>{`
            .delivery h1 { font-size: 15px; text-align: center; margin: 0 0 2mm; }
            .delivery .head { display: flex; justify-content: space-between; gap: 4mm; margin-bottom: 2mm; }
            .delivery table { width: 100%; border-collapse: collapse; }
            .delivery th, .delivery td { border: 1px solid #000; padding: 1mm 1.5mm; text-align: center; }
            .delivery th { background: #eee; }
            .delivery td.l { text-align: right; }
            .delivery .diff { font-weight: 700; }
            .delivery .foot { display: flex; justify-content: space-between; margin-top: 4mm; }
            .delivery .sign { width: 45%; border-top: 1px dashed #000; padding-top: 1mm; text-align: center; height: 14mm; }
          `}</style>
          <h1>
            {incoming ? t("deliveryNotes.printIn") : t("deliveryNotes.printOut")} — {settings.data.organization_name}
          </h1>
          <div className="head">
            <div>
              <div>
                {t("deliveryNotes.number")}: <b>{toPersianDigits(data.number)}</b>
              </div>
              <div>
                {t("deliveryNotes.date")}: <b>{formatDateTime(data.confirmed_at ?? data.created_at)}</b>
              </div>
              {data.order && (
                <div>
                  {t("deliveryNotes.order")}: <b className="ltr">{data.order.ticket_number}</b>
                </div>
              )}
            </div>
            <div>
              <div>
                {t("orders.detail.company")}: <b>{data.company.name}</b>
              </div>
              {data.department && (
                <div>
                  {t("companies.fields.department")}: <b>{data.department.name}</b> {data.department.code && <span className="ltr">({data.department.code})</span>}
                </div>
              )}
              <div>
                {t("orders.columns.branch")}: <b>{data.branch.name}</b>
              </div>
            </div>
          </div>
          <table>
            <thead>
              <tr>
                <th>{t("invoices.line.position")}</th>
                <th>{t("deliveryNotes.item")}</th>
                <th>{incoming ? t("deliveryNotes.stated") : t("deliveryNotes.sent")}</th>
                <th>{incoming ? t("deliveryNotes.counted") : t("deliveryNotes.received")}</th>
                <th>{t("deliveryNotes.difference")}</th>
              </tr>
            </thead>
            <tbody>
              {(data.lines ?? []).map((line, index) => (
                <tr key={line.id}>
                  <td>{formatNumber(index + 1)}</td>
                  <td className="l">
                    {line.item_name} {line.service_name && `(${line.service_name})`}
                  </td>
                  <td>{formatNumber(line.expected_quantity)}</td>
                  <td>{formatNumber(line.counted_quantity)}</td>
                  <td className={line.difference !== 0 ? "diff" : undefined}>{line.difference === 0 ? "—" : formatNumber(line.difference)}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {data.notes && <div style={{ marginTop: "2mm" }}>{data.notes}</div>}
          <div className="foot">
            <div className="sign">
              {t("deliveryNotes.shopSignature")}
              {data.created_by && <div>{data.created_by.name}</div>}
            </div>
            <div className="sign">
              {t("deliveryNotes.companySignature")}
              {data.counterpart_name && <div>{data.counterpart_name}</div>}
            </div>
          </div>
        </div>
      )}
    </PrintFrame>
  );
}
