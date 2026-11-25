"use client";

import { DatePicker } from "antd";
import faIR from "antd/locale/fa_IR";
import type { PickerLocale } from "antd/es/date-picker/generatePicker";
import type { Dayjs } from "@/shared/lib/date";
import { jalaliGenerateConfig } from "@/shared/lib/jalali-generate-config";

/**
 * Date pickers in the Jalali calendar. Values are ordinary Dayjs instants
 * (send them with `.toISOString()`); only display and navigation are Jalali.
 */
const JalaliPicker = DatePicker.generatePicker<Dayjs>(jalaliGenerateConfig);

export const JalaliDatePicker = JalaliPicker;
export const JalaliRangePicker = JalaliPicker.RangePicker;

/** Persian texts with formats that read naturally in the Jalali calendar. */
export const jalaliPickerLocale: PickerLocale = {
  ...faIR.DatePicker!,
  lang: {
    ...faIR.DatePicker!.lang,
    locale: "fa",
    yearFormat: "YYYY",
    monthFormat: "MMMM",
    dayFormat: "D",
    fieldDateFormat: "YYYY/MM/DD",
    fieldDateTimeFormat: "YYYY/MM/DD HH:mm",
    cellDateFormat: "D",
    monthBeforeYear: true,
  },
};
