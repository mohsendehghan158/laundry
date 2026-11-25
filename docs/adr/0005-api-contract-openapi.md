# ۰۰۰۵. قرارداد API از روی کد و نوع‌های تولیدی فرانت

- وضعیت: پذیرفته
- تاریخ: 2026-11-24

## زمینه

بک‌اند و فرانت جدا توسعه می‌یابند؛ تغییر نام یک فیلد در Resource نباید بی‌صدا فرانت را بشکند. نوشتن دستی سند OpenAPI هم زود کهنه می‌شود.

## تصمیم

- سند OpenAPI با Scramble از خود کد (FormRequestها، Resourceها، توضیحات PHPDoc) تولید و در `docs/api/openapi.json` نگه داشته می‌شود (`php artisan scramble:export`).
- فرانت نوع‌های TypeScript را از همان سند می‌سازد (`npm run api:types` ← `src/shared/api/schema.d.ts`) و نام‌های کوتاه را در `src/shared/api/types.ts` تعریف می‌کند.
- CI دو بررسی دارد: سند صادرشده با کد یکی باشد، و نوع‌های فرانت با سند یکی باشند. تغییر شکننده قرارداد = خطای type check فرانت.
- قالب خطا در کل API یکی است: `{ message, code, errors? }`؛ `code` ماشینی و ثابت است و `message` به زبان `Accept-Language`.

## پیامدها

- هر تغییر در API همراه با `docs/api/openapi.json` و `schema.d.ts` به‌روزشده commit می‌شود.
