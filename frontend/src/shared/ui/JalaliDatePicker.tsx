"use client";

import generatePicker from "antd/es/date-picker/generatePicker";
import { jalaliGenerateConfig } from "@/shared/lib/jalaliGenerateConfig";
import type { Dayjs } from "@/shared/lib/date";

/**
 * Ant Design date picker on the Persian calendar. Values are dayjs
 * objects; read them with `.toISOString()` or `.format()` as usual.
 */
export const JalaliDatePicker = generatePicker<Dayjs>(jalaliGenerateConfig);

export const JalaliRangePicker = JalaliDatePicker.RangePicker;
