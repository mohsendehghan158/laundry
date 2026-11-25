# سامانه مدیریت خشکشویی

پذیرش، کارگاه، تحویل و مدیریت چندشعبه‌ای خشکشویی؛ فارسی، راست‌به‌چپ و تقویم جلالی.
مسیر کامل پروژه، معماری و فازبندی در [`ROADMAP.md`](ROADMAP.md) است.

| پوشه | محتوا |
|---|---|
| `backend/` | API با Laravel 13 (مونولیت ماژولار در `app/Modules`) |
| `frontend/` | پنل با Next.js 16، React 19 و Ant Design 6 |
| `docs/api/openapi.json` | قرارداد API که از کد بک‌اند تولید می‌شود؛ نوع‌های TypeScript فرانت از همین فایل ساخته می‌شوند |
| `compose.yaml` | اجرای کل سامانه با Docker |

## اجرا با Docker

```bash
cp .env.example .env
```

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
```

```bash
docker compose up --build -d
```

```bash
docker compose exec api php artisan db:seed --force
```

پنل روی <http://localhost:3000> باز می‌شود. دستور آخر داده نمونه می‌سازد؛ برای راه‌اندازی واقعی به‌جای آن `docker compose exec api php artisan laundry:install` را اجرا کنید تا کسب‌وکار، شعبه اول و حساب مالک را بپرسد.

## اجرای محلی برای توسعه

پیش‌نیازها: PHP 8.4 با افزونه‌های `pdo_pgsql` و `redis`، Composer، Node.js 22، PostgreSQL 17 و Redis 7.

بک‌اند (روی پورت ۸۰۰۰):

```bash
cd backend && composer install && cp .env.example .env && php artisan key:generate
```

```bash
php artisan migrate --seed
```

```bash
php artisan serve
```

فرانت (روی پورت ۳۰۰۰؛ درخواست‌های `/api` و `/sanctum` را به بک‌اند می‌فرستد):

```bash
cd frontend && npm ci && cp .env.example .env.local
```

```bash
npm run dev
```

پنل را همیشه از <http://localhost:3000> باز کنید، نه از پورت ۸۰۰۰؛ نشست ورود فقط برای نشانی‌های `SANCTUM_STATEFUL_DOMAINS` برقرار می‌شود.

### حساب‌های نمونه

رمز همه `password` است.

| نام کاربری | نقش | دسترسی |
|---|---|---|
| `admin` | مالک | همه چیز |
| `manager` | مدیر شعبه (شعبه مرکزی) | صندوق، سفارش‌ها، لغو و بازپرداخت، مشاهده کاتالوگ و پیامک‌ها، داشبورد و گزارش فروش، کاربران شعبه |
| `accountant` | حسابدار | مشاهده سفارش‌ها و شیفت‌های همه شعبه‌ها، داشبورد و گزارش فروش |
| `cashier`، `cashier2` | پذیرش و صندوق | ثبت سفارش، دریافت پول، شیفت صندوق، تحویل |
| `operator` | اپراتور کارگاه | مشاهده و به‌روزرسانی وضعیت سفارش‌ها |
| `driver` | راننده | فعلاً فقط مشاهده مشتریان (لجستیک در فاز ۴) |

## تست و کیفیت

بک‌اند:

```bash
cd backend && vendor/bin/pint --test && vendor/bin/phpstan analyse && vendor/bin/pest
```

فرانت:

```bash
cd frontend && npm run typecheck && npm run lint && npm test
```

تست سرتاسری (Playwright) هر دو سرور را خودش بالا می‌آورد و پایگاه داده `laundry_e2e` را هر بار پاک و با داده نمونه پر می‌کند:

```bash
cd frontend && npx playwright test
```

## تغییر API

1. کد بک‌اند را تغییر دهید.
2. قرارداد را دوباره بسازید: `php artisan scramble:export` در `backend/` (پایگاه داده باید مهاجرت‌شده باشد، چون نوع ستون‌ها از آن خوانده می‌شود).
3. نوع‌های فرانت را به‌روز کنید: `npm run api:types` در `frontend/`.

خط لوله CI (`.github/workflows/ci.yml`) اگر یکی از این دو فایل با کد هم‌خوان نباشد خطا می‌دهد.
