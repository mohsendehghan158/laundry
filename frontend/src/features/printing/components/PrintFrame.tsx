"use client";

import { PrinterOutlined } from "@ant-design/icons";
import { Button, Result, Spin } from "antd";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { useEffect, type ReactNode } from "react";

/**
 * Wraps a printable document: shows it on screen with a print button,
 * and opens the print dialog once it is ready unless ?auto=0.
 */
export function PrintFrame({ ready, error, pageCss, children }: { ready: boolean; error: boolean; pageCss: string; children: ReactNode }) {
  const t = useTranslations("printing");
  const auto = useSearchParams().get("auto") !== "0";

  useEffect(() => {
    if (ready && auto) {
      // Let fonts and SVGs settle before the dialog freezes the page.
      const timer = setTimeout(() => window.print(), 400);
      return () => clearTimeout(timer);
    }
  }, [ready, auto]);

  if (error) {
    return <Result status="error" title={t("failed")} />;
  }
  return (
    <>
      {!ready && (
        <div style={{ padding: 48, textAlign: "center" }}>
          <Spin />
        </div>
      )}
      <style>{`
        body { background: #e9ecef; }
        .print-toolbar { display: flex; justify-content: center; padding: 12px; }
        .print-sheet { background: #fff; color: #000; margin: 0 auto 24px; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
        @media print {
          body { background: #fff; }
          .print-toolbar { display: none; }
          .print-sheet { box-shadow: none; margin: 0; }
          ${pageCss}
        }
      `}</style>
      {ready && (
        <div className="print-toolbar">
          <Button type="primary" icon={<PrinterOutlined />} onClick={() => window.print()}>
            {t("print")}
          </Button>
        </div>
      )}
      {children}
    </>
  );
}
