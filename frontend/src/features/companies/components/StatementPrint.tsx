"use client";

import { useParams, useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { useReceiptSettings } from "@/features/printing/api";
import { PrintFrame } from "@/features/printing/components/PrintFrame";
import { formatDate } from "@/shared/lib/date";
import { formatNumber, toPersianDigits } from "@/shared/lib/digits";
import { useCompanyStatement } from "../api";

/** The company's statement on A4, in rials, to send with the group invoice. */
export function StatementPrint() {
  const t = useTranslations();
  const id = Number(useParams<{ id: string }>().id);
  const search = useSearchParams();
  const from = search.get("from") ?? "";
  const to = search.get("to") ?? "";
  const statement = useCompanyStatement(Number.isFinite(id) && from && to ? id : null, from, to);
  const settings = useReceiptSettings();
  const data = statement.data;
  const rial = (v: number) => formatNumber(v);

  return (
    <PrintFrame ready={Boolean(data && settings.data)} error={statement.isError || settings.isError} pageCss="@page { size: A4; margin: 10mm; }">
      {data && settings.data && (
        <div className="print-sheet statement" style={{ width: "190mm", padding: "6mm", fontSize: 11, lineHeight: 1.7 }}>
          <style>{`
            .statement h1 { font-size: 16px; text-align: center; margin: 0; }
            .statement .sub { text-align: center; margin-bottom: 3mm; }
            .statement .head { display: flex; justify-content: space-between; margin-bottom: 3mm; }
            .statement table { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
            .statement th, .statement td { border: 1px solid #000; padding: 1mm 1.5mm; }
            .statement th { background: #eee; }
            .statement td.num { text-align: left; direction: ltr; white-space: nowrap; }
            .statement tfoot td { font-weight: 700; }
            .statement .sign { margin-top: 6mm; display: flex; justify-content: space-between; }
            .statement .sign div { width: 45%; border-top: 1px dashed #000; text-align: center; padding-top: 1mm; height: 14mm; }
          `}</style>
          <h1>{t("statements.printTitle")}</h1>
          <div className="sub">{settings.data.organization_name}</div>
          <div className="head">
            <div>
              <div>
                {t("orders.detail.company")}: <b>{data.company.name}</b>
              </div>
              {data.company.economic_code && (
                <div>
                  {t("invoices.fields.economicCode")}: <b>{toPersianDigits(data.company.economic_code)}</b>
                </div>
              )}
            </div>
            <div>
              <div>
                {t("statements.period")}: <b>{formatDate(data.from)} – {formatDate(data.to)}</b>
              </div>
              <div>
                {t("companies.fields.paymentTerms")}: <b>{formatNumber(data.company.payment_terms_days)}</b>
              </div>
            </div>
          </div>
          <table>
            <thead>
              <tr>
                <th>{t("invoices.line.position")}</th>
                <th>{t("orders.columns.ticket")}</th>
                <th>{t("deliveryNotes.date")}</th>
                <th>{t("companies.fields.department")}</th>
                <th>{t("statements.notes")}</th>
                <th>{t("statements.rial.total")}</th>
                <th>{t("statements.rial.paid")}</th>
                <th>{t("statements.rial.balance")}</th>
              </tr>
            </thead>
            <tbody>
              {data.orders.map((row, index) => (
                <tr key={row.id}>
                  <td>{formatNumber(index + 1)}</td>
                  <td className="ltr">{row.ticket_number}</td>
                  <td>{formatDate(row.received_at)}</td>
                  <td>{row.department ?? "—"}</td>
                  <td>{row.delivery_notes.length ? toPersianDigits(row.delivery_notes.join("، ")) : "—"}</td>
                  <td className="num">{rial(row.total)}</td>
                  <td className="num">{rial(row.paid)}</td>
                  <td className="num">{rial(row.balance)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan={5}>{t("statements.periodTotal")}</td>
                <td className="num">{rial(data.totals.total)}</td>
                <td className="num">{rial(data.totals.paid)}</td>
                <td className="num">{rial(data.totals.balance)}</td>
              </tr>
              <tr>
                <td colSpan={7}>{t("statements.previous")}</td>
                <td className="num">{rial(data.previous_balance)}</td>
              </tr>
              <tr>
                <td colSpan={7}>{t("statements.due")}</td>
                <td className="num">{rial(data.previous_balance + data.totals.balance)}</td>
              </tr>
            </tfoot>
          </table>
          {data.departments.length > 1 && (
            <table>
              <thead>
                <tr>
                  <th>{t("companies.fields.department")}</th>
                  <th>{t("statements.orders")}</th>
                  <th>{t("statements.rial.total")}</th>
                </tr>
              </thead>
              <tbody>
                {data.departments.map((d) => (
                  <tr key={d.name ?? "-"}>
                    <td>{d.name ?? t("statements.noDepartment")}</td>
                    <td>{formatNumber(d.orders)}</td>
                    <td className="num">{rial(d.total)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          <div className="sign">
            <div>{t("invoices.sellerSignature")}</div>
            <div>{t("invoices.buyerSignature")}</div>
          </div>
        </div>
      )}
    </PrintFrame>
  );
}
