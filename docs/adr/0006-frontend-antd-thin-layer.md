# ۰۰۰۶. Ant Design 6 با لایه نازک اختصاصی و تقویم جلالی خودمان

- وضعیت: پذیرفته
- تاریخ: 2026-11-25

## زمینه

ProComponents هنوز antd 6 را فقط در شاخه بتا پشتیبانی می‌کند و `antd-jalali` فقط با antd 5 و React 18 کار می‌کند و از ۱۴۰۳ به‌روز نشده (بخش ۷ـ۳ نقشه‌راه).

## تصمیم

- وابستگی مستقیم فقط به antd 6؛ یک لایه نازک در `frontend/src/shared/ui`: `DataTable` (صفحه‌بندی و مرتب‌سازی سمت سرور)، `PageHeader`، `FormDrawer`، `QueryState`، `MoneyInput`، `JalaliDatePicker`.
- تقویم جلالی: `generateConfig` اختصاصی روی dayjs + jalaliday (`shared/lib/jalali-generate-config.ts`) و `DatePicker.generatePicker`. شروع هفته شنبه، نام ماه‌های شمسی، ارقام فارسی در نمایش، پذیرش هر دو نوع رقم در ورودی. مقدارها همان لحظه‌های عادی dayjs هستند و با `toISOString()` به API می‌روند.
- داده سرور فقط با TanStack Query؛ فیلتر، صفحه و مرتب‌سازی جدول‌ها در نشانی صفحه با nuqs (`useTableState`).
- فرم‌ها با `Form` خود antd؛ خطاهای ۴۲۲ با `applyServerErrors` مستقیم روی فیلدها می‌نشینند.
- راست‌به‌چپ و فارسی در `ConfigProvider`؛ توکن‌های رنگ و اندازه در `src/theme/tokens.ts` با حالت روشن و تیره؛ فونت وزیرمتن از بسته محلی (بدون CDN).
- متن‌ها در `src/shared/i18n/fa.json` و `en.json` با next-intl؛ آزمونی کلیدهای دو فایل را هم‌سان نگه می‌دارد.
- Cache Components در Next.js خاموش است: رجیستری CSS-in-JS antd هنگام رندر شناسه تصادفی می‌سازد که پیش‌رندر Cache Components نمی‌پذیرد، و پنل به‌هرحال از داده API سمت کاربر رندر می‌شود.

## پیامدها

- اگر ProComponents نسخه پایدار antd 6 داد، می‌توان `DataTable` را جایگزین کرد بی‌آنکه صفحه‌ها عوض شوند.
