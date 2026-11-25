"use client";

import { toSVG } from "bwip-js/browser";
import { useMemo } from "react";

type Props = {
  /** What the barcode encodes; our own codes only (ticket numbers, tag codes, links). */
  value: string;
  type?: "code128" | "qrcode";
  /** Bar height in millimetres (Code 128) or module scale (QR). */
  height?: number;
  width?: string;
  className?: string;
};

/**
 * A crisp vector barcode for screen and print: Code 128 for tags and
 * tickets (what counter scanners read), QR for links.
 */
export function Barcode({ value, type = "code128", height = 10, width = "100%", className }: Props) {
  const svg = useMemo(() => {
    try {
      return toSVG(type === "qrcode" ? { bcid: "qrcode", text: value, scale: 3 } : { bcid: "code128", text: value, height, includetext: false });
    } catch {
      return "";
    }
  }, [value, type, height]);

  return (
    <span
      className={className}
      role="img"
      aria-label={value}
      style={{ display: "block", width, lineHeight: 0 }}
      // The SVG is generated locally from our own short codes.
      dangerouslySetInnerHTML={{ __html: svg.replace("<svg ", '<svg style="width:100%;height:auto" ') }}
    />
  );
}
