# پنل خشکشویی (Next.js)

راه‌اندازی و دستورها در [README اصلی](../README.md) آمده است. این‌جا فقط ساختار کد:

```text
src/
├── app/                 # مسیرها: (auth)/login و (panel)/… — صفحه‌ها فقط کامپوننت ویژگی را رندر می‌کنند
├── features/<دامنه>/    # api.ts (هوک‌های TanStack Query) و components/
├── shared/
│   ├── api/             # کلاینت fetch با CSRF، نوع‌های تولیدشده از OpenAPI، نگاشت خطای فرم
│   ├── ui/              # DataTable، PageHeader، JalaliDatePicker، MoneyInput، Can، RequirePermission
│   ├── hooks/           # usePermission، useListParams (فیلتر و صفحه در نشانی)، useColorScheme
│   ├── lib/             # ارقام، پول (ریال ↔ تومان)، تاریخ جلالی
│   ├── layout/          # پوسته پنل و منوی وابسته به مجوز
│   └── i18n/            # fa.json و en.json
├── theme/               # توکن‌های Ant Design: روشن، تیره، لمسی
└── proxy.ts             # بدون کوکی نشست ← صفحه ورود
```

قواعد:

- داده سرور فقط با TanStack Query؛ مجوزها از `/me` می‌آیند و منو و دکمه‌ها با `usePermission` پنهان می‌شوند (سرور مرجع نهایی است).
- همه متن‌ها در `src/shared/i18n/*.json`؛ تست، یکسان‌بودن کلیدهای فارسی و انگلیسی و برچسب‌داشتن همه مجوزهای بک‌اند را بررسی می‌کند.
- `src/shared/api/schema.d.ts` را دستی ویرایش نکنید؛ با `npm run api:types` از `docs/api/openapi.json` ساخته می‌شود.
- مبالغ همیشه ریال‌اند؛ `MoneyInput` و `formatMoney` واحد نمایش (تومان یا ریال) را می‌گیرند.
