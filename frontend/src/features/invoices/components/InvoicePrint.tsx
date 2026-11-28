"use client";

import { useParams } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import type { Invoice } from "@/shared/api/types";
import { formatDate } from "@/shared/lib/date";
import { formatNumber, toPersianDigits } from "@/shared/lib/digits";
import { toPersianWords } from "@/shared/lib/words";
import { PrintFrame } from "@/features/printing/components/PrintFrame";
import { useInvoice } from "../api";

type Party = Invoice["seller"] & { type?: Invoice["buyer"]["type"] };

/** Amounts on official invoices are always in rials. */
const rial = (value: number) => formatNumber(value);

function PartyBox({ title, party }: { title: string; party: Party }) {
  const t = useTranslations("invoices");
  const legal = party.type !== "individual";
  const field = (label: string, value: string | null) => (
    <span>
      {label}: <b className="ltr">{value ? toPersianDigits(value) : "—"}</b>
    </span>
  );
  return (
    <div className="party">
      <div className="party-title">{title}</div>
      <div className="party-grid">
        <span>
          {legal ? t("fields.companyName") : t("fields.buyerName")}: <b>{party.name}</b>
        </span>
        {field(legal ? t("fields.legalId") : t("fields.personCode"), party.national_id)}
        {field(t("fields.economicCode"), party.economic_code)}
        {legal && field(t("fields.registrationNumber"), party.registration_number)}
        {field(t("fields.postalCode"), party.postal_code)}
        {field(t("fields.phone"), party.phone)}
        <span className="wide">
          {t("fields.address")}: <b>{party.address ?? "—"}</b>
        </span>
      </div>
    </div>
  );
}

/** The official A4 sales invoice (صورتحساب فروش کالا و خدمات), printed by the browser. */
export function InvoicePrint() {
  const t = useTranslations();
  const locale = useLocale();
  const id = Number(useParams<{ id: string }>().id);
  const invoice = useInvoice(Number.isFinite(id) ? id : null);
  const data = invoice.data;

  return (
    <PrintFrame ready={Boolean(data)} error={invoice.isError} pageCss="@page { size: A4 landscape; margin: 8mm; } .print-sheet { width: auto; }">
      {data && (
        <div className="print-sheet invoice" style={{ width: "281mm", padding: "6mm", fontSize: 11, lineHeight: 1.7 }}>
          <style>{`
            .invoice h1 { font-size: 16px; text-align: center; margin: 0; }
            .invoice .head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 3mm; }
            .invoice .meta { border: 1px solid #000; padding: 1mm 3mm; min-width: 45mm; }
            .invoice .party { border: 1px solid #000; margin-bottom: 2mm; }
            .invoice .party-title { background: #eee; border-bottom: 1px solid #000; padding: 0.5mm 3mm; font-weight: 700; text-align: center; }
            .invoice .party-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0 6mm; padding: 1mm 3mm; }
            .invoice .party-grid .wide { grid-column: 1 / -1; }
            .invoice table { width: 100%; border-collapse: collapse; }
            .invoice th, .invoice td { border: 1px solid #000; padding: 1mm 1.5mm; vertical-align: middle; }
            .invoice th { background: #eee; font-weight: 700; text-align: center; }
            .invoice td.num { text-align: left; white-space: nowrap; direction: ltr; }
            .invoice td.c { text-align: center; }
            .invoice tfoot td { font-weight: 700; }
            .invoice .void { color: #c00; border: 2px solid #c00; padding: 1mm 3mm; text-align: center; font-weight: 700; margin-bottom: 2mm; }
            .invoice .foot { display: flex; justify-content: space-between; margin-top: 3mm; }
            .invoice .sign { width: 45%; border-top: 1px dashed #000; padding-top: 1mm; text-align: center; height: 18mm; }
          `}</style>
          <div className="head">
            <div style={{ width: "45mm" }} />
            <h1>{t("invoices.printTitle")}</h1>
            <div className="meta">
              <div>
                {t("invoices.columns.number")}: <b>{toPersianDigits(data.number)}</b>
              </div>
              <div>
                {t("invoices.columns.issuedAt")}: <b>{formatDate(data.issued_at)}</b>
              </div>
              {data.orders && data.orders.length > 0 && (
                <div>
                  {t("invoices.orders")}: <b className="ltr">{data.orders.map((o) => o.ticket_number).join("، ")}</b>
                </div>
              )}
            </div>
          </div>
          {data.status === "void" && (
            <div className="void">
              {t("invoices.voidStamp")} — {data.void?.reason}
            </div>
          )}
          <PartyBox title={t("invoices.seller")} party={{ ...data.seller, type: data.seller.national_id?.length === 10 ? "individual" : "legal" }} />
          <PartyBox title={t("invoices.buyer")} party={data.buyer} />
          <table>
            <thead>
              <tr>
                <th style={{ width: "8mm" }}>{t("invoices.line.position")}</th>
                <th>{t("invoices.line.description")}</th>
                <th>{t("invoices.line.quantity")}</th>
                <th>{t("invoices.line.unitPrice")}</th>
                <th>{t("invoices.line.amount")}</th>
                <th>{t("invoices.line.discount")}</th>
                <th>{t("invoices.line.afterDiscount")}</th>
                <th>{t("invoices.line.tax", { percent: formatNumber(data.vat_percent) })}</th>
                <th>{t("invoices.line.total")}</th>
              </tr>
            </thead>
            <tbody>
              {(data.lines ?? []).map((line) => (
                <tr key={line.position}>
                  <td className="c">{formatNumber(line.position)}</td>
                  <td>{line.description}</td>
                  <td className="c">
                    {formatNumber(line.quantity)} {line.unit ? t(`catalog.units.${line.unit as "piece" | "kg" | "sqm"}`) : ""}
                  </td>
                  <td className="num">{rial(line.unit_price)}</td>
                  <td className="num">{rial(line.amount)}</td>
                  <td className="num">{rial(line.discount)}</td>
                  <td className="num">{rial(line.amount - line.discount)}</td>
                  <td className="num">{rial(line.tax)}</td>
                  <td className="num">{rial(line.total)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan={4}>{t("invoices.totals")}</td>
                <td className="num">{rial(data.subtotal)}</td>
                <td className="num">{rial(data.discount_amount)}</td>
                <td className="num">{rial(data.subtotal - data.discount_amount)}</td>
                <td className="num">{rial(data.tax_amount)}</td>
                <td className="num">{rial(data.total)}</td>
              </tr>
              <tr>
                <td colSpan={9}>
                  {t("invoices.payable")}: {rial(data.total)} {t("money.rial")}
                  {locale === "fa" && ` (${toPersianWords(data.total)} ${t("money.rial")})`}
                </td>
              </tr>
            </tfoot>
          </table>
          {data.notes && <div style={{ marginTop: "2mm", whiteSpace: "pre-line" }}>{data.notes}</div>}
          <div className="foot">
            <div className="sign">{t("invoices.sellerSignature")}</div>
            <div className="sign">{t("invoices.buyerSignature")}</div>
          </div>
        </div>
      )}
    </PrintFrame>
  );
}
