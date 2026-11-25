# پنل خشکشویی (Next.js)

پنل کارکنان: Next.js 16 (App Router)، React 19، Ant Design 6 راست‌به‌چپ، TanStack Query، next-intl، nuqs و dayjs با تقویم جلالی.

```bash
npm ci
npm run dev          # http://localhost:3000 — /api و /sanctum به BACKEND_URL (پیش‌فرض http://localhost:8000) می‌روند
```

## ساختار

```text
src/
├── app/
│   ├── (auth)/login/        # ورود کارکنان
│   ├── (panel)/             # پیشخوان، شعبه‌ها، کاربران، نقش‌ها، تنظیمات، گزارش فعالیت، حساب من
│   ├── layout.tsx           # فونت، AntdRegistry
│   └── providers.tsx        # ConfigProvider (rtl، fa_IR، تم)، QueryClient، next-intl، nuqs
├── features/<domain>/       # api.ts (هوک‌های Query) + صفحه‌ها و اجزای همان دامنه
├── shared/
│   ├── api/                 # client.ts، errors.ts، form-errors.ts، schema.d.ts (تولیدی)، types.ts
│   ├── hooks/               # usePermission، useTableState
│   ├── lib/                 # digits، money، date، jalali-generate-config
│   ├── ui/                  # DataTable، PageHeader، FormDrawer، QueryState، MoneyInput، JalaliDatePicker
│   └── i18n/                # fa.json، en.json
├── theme/                   # توکن‌ها، حالت روشن و تیره
└── proxy.ts                 # هدایت به /login در نبود کوکی نشست
```

## قواعد

- داده سرور فقط با TanStack Query و از راه `shared/api/client.ts`؛ نوع‌ها از `shared/api/types.ts` که از OpenAPI بک‌اند تولید می‌شود (`npm run api:types`).
- فیلتر و صفحه جدول‌ها در نشانی صفحه (`useTableState`).
- منو و دکمه‌ها با `usePermission`؛ فهرست منو در `features/shell/navigation.tsx`.
- همه متن‌ها در `shared/i18n/fa.json` (و همتای انگلیسی در `en.json`).
- مبالغ همیشه ریال؛ `MoneyInput` و `formatMoney` تبدیل به واحد نمایش را انجام می‌دهند.

## تست

```bash
npm test             # Vitest: ابزارهای پول، تاریخ جلالی، کلاینت API، MoneyInput
npm run build && npm run e2e   # Playwright با API شبیه‌سازی‌شده
```
