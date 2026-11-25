"use client";

import { useParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { useLayoutEffect, useRef, useState } from "react";
import { useOrder } from "@/features/orders/api";
import { formatDateTime } from "@/shared/lib/date";
import { formatNumber } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";
import { Barcode } from "@/shared/ui/Barcode";
import { trackingUrl, useReceiptSettings } from "../api";
import { PrintFrame } from "./PrintFrame";

/** The customer's 80 mm receipt: what was taken in, the amounts, and how to track it. */
export function ReceiptPrint() {
  const t = useTranslations();
  const id = Number(useParams<{ id: string }>().id);
  const order = useOrder(id);
  const settings = useReceiptSettings();
  const data = order.data;
  const s = settings.data;
  const unit = s?.currency_unit ?? "toman";
  const m = (v: number) => formatMoney(v, unit);

  // Receipt rolls have no fixed length and browsers ignore "size: 80mm auto",
  // so the page is made exactly as tall as the rendered receipt.
  const sheet = useRef<HTMLDivElement>(null);
  const [heightMm, setHeightMm] = useState<number | null>(null);
  useLayoutEffect(() => {
    if (sheet.current) {
      setHeightMm(Math.ceil((sheet.current.getBoundingClientRect().height * 25.4) / 96) + 2);
    }
  }, [data, s]);

  return (
    <PrintFrame
      ready={Boolean(data && s && heightMm)}
      error={order.isError || settings.isError}
      pageCss={`@page { size: 80mm ${heightMm ?? 297}mm; margin: 0; } .print-sheet { width: 80mm; }`}
    >
      {data && s && (
        <div ref={sheet} className="print-sheet receipt" style={{ width: "80mm", padding: "4mm", fontSize: 12, lineHeight: 1.6 }}>
          <style>{`
            .receipt h1 { font-size: 16px; text-align: center; margin: 0 0 2mm; }
            .receipt .center { text-align: center; }
            .receipt .row { display: flex; justify-content: space-between; gap: 2mm; }
            .receipt hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
            .receipt table { width: 100%; border-collapse: collapse; }
            .receipt td { vertical-align: top; padding: 0.5mm 0; }
            .receipt .num { text-align: left; white-space: nowrap; }
            .receipt .big { font-size: 15px; font-weight: 700; }
            .receipt .ticket { font-size: 20px; font-weight: 700; direction: ltr; text-align: center; letter-spacing: 1px; }
          `}</style>
          <h1>{s.organization_name}</h1>
          <div className="center">{data.branch.name}</div>
          {s.header_text && <div className="center" style={{ whiteSpace: "pre-line" }}>{s.header_text}</div>}
          <hr />
          <div className="ticket">{data.ticket_number}</div>
          <Barcode value={data.ticket_number} height={9} />
          <div className="row">
            <span>{t("printing.customer")}</span>
            <span>{data.customer.name}</span>
          </div>
          <div className="row">
            <span>{t("printing.received")}</span>
            <span>{formatDateTime(data.received_at)}</span>
          </div>
          <div className="row big">
            <span>{t("printing.due")}</span>
            <span>{formatDateTime(data.due_at)}</span>
          </div>
          <hr />
          <table>
            <tbody>
              {(data.lines ?? []).map((line) => (
                <tr key={line.id}>
                  <td>
                    {line.item_name} ({line.service_name}) × {formatNumber(line.quantity)}
                    {line.upcharges.length > 0 && <div style={{ fontSize: 11 }}>+ {line.upcharges.map((u) => u.name).join("، ")}</div>}
                  </td>
                  <td className="num">{m(line.line_total)}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <hr />
          <div className="row">
            <span>{t("printing.subtotal")}</span>
            <span>{m(data.subtotal)}</span>
          </div>
          {data.discount && (
            <div className="row">
              <span>{t("printing.discount")}</span>
              <span>−{m(data.discount.amount)}</span>
            </div>
          )}
          {data.tax_amount > 0 && (
            <div className="row">
              <span>{s.prices_include_vat ? t("printing.taxIncluded") : t("printing.tax")}</span>
              <span>{m(data.tax_amount)}</span>
            </div>
          )}
          <div className="row big">
            <span>{t("printing.total")}</span>
            <span>
              {m(data.total)} {t(`money.${unit}`)}
            </span>
          </div>
          <div className="row">
            <span>{t("printing.paid")}</span>
            <span>{m(data.paid_amount)}</span>
          </div>
          <div className="row big">
            <span>{t("printing.balance")}</span>
            <span>{m(data.balance)}</span>
          </div>
          <div className="row">
            <span>{t("printing.pieces")}</span>
            <span>{formatNumber(data.piece_count ?? 0)}</span>
          </div>
          <hr />
          <div className="center">{t("printing.track")}</div>
          <div style={{ width: "28mm", margin: "1mm auto" }}>
            <Barcode value={trackingUrl(data.public_id)} type="qrcode" />
          </div>
          {s.footer_text && <div className="center" style={{ whiteSpace: "pre-line" }}>{s.footer_text}</div>}
        </div>
      )}
    </PrintFrame>
  );
}
