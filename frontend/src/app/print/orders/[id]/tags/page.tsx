import type { Metadata } from "next";
import { TagsPrint } from "@/features/printing/components/TagsPrint";

export const metadata: Metadata = { title: "تگ قطعه‌ها" };

export default function Page() {
  return <TagsPrint />;
}
