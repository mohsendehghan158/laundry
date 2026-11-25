import type { Metadata } from "next";
import { TrackingPage } from "@/features/tracking/components/TrackingPage";

export const metadata: Metadata = { title: "پیگیری سفارش" };

export default function Page() {
  return <TrackingPage />;
}
