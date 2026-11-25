"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/shared/api/client";

export type ReceiptSettings = {
  organization_name: string;
  header_text: string | null;
  footer_text: string | null;
  currency_unit: "toman" | "rial";
  prices_include_vat: boolean;
  vat_percent: number;
};

export function useReceiptSettings() {
  return useQuery({
    queryKey: ["receipt-settings"],
    queryFn: async () => (await api.get<{ data: ReceiptSettings }>("/receipt-settings")).data,
    staleTime: 10 * 60_000,
  });
}

/** Opens a print page in a new window; it prints itself once loaded. */
export function openPrint(orderId: number, what: "receipt" | "tags") {
  window.open(`/print/orders/${orderId}/${what}`, `print-${what}-${orderId}`, "width=480,height=720");
}

/** The public tracking address printed as a QR code on receipts. */
export function trackingUrl(publicId: string): string {
  return `${window.location.origin}/track/${publicId}`;
}
