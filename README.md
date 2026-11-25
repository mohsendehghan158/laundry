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

پنل روی <http://localhost:3000> باز می‌شود و سرور سوکت (Reverb، برای به‌روزرسانی زنده تابلوی تولید) روی پورت ۸۰۸۰. دستور آخر داده نمونه می‌سازد؛ برای راه‌اندازی واقعی به‌جای آن `docker compose exec api php artisan laundry:install` را اجرا کنید تا کسب‌وکار، شعبه اول و حساب مالک را بپرسد.

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

تابلوی تولید بدون سرور سوکت هر ۱۵ ثانیه تازه می‌شود. برای به‌روزرسانی زنده در `.env` مقدار `BROADCAST_CONNECTION=reverb` بگذارید و در کنار `serve` این‌ها را هم اجرا کنید (دومی هشدار عقب‌افتادگی را می‌فرستد):

```bash
php artisan reverb:start
```

```bash
php artisan schedule:work
```

فرانت (روی پورت ۳۰۰۰؛ درخواست‌های `/api` و `/sanctum` را به بک‌اند می‌فرستد):

```bash
cd frontend && npm ci && cp .env.example .env.local
```

```bash
npm run dev
```

پنل را همیشه از <http://localhost:3000> باز کنید، نه از پورت ۸۰۰۰؛ نشست ورود فقط برای نشانی‌های `SANCTUM_STATEFUL_DOMAINS` برقرار می‌شود.

### پرتال مشتری

پرتال مشتری روی <http://localhost:3000/my> است و ورودش با کد پیامکی است. برای توسعه محلی در `backend/.env` مقدار `OTP_FIXED_CODE=12345` بگذارید تا کد همیشه همین باشد (در محیط عملیاتی نادیده گرفته می‌شود). مشتریان نمونه همان شماره‌های `DemoCustomerSeeder` هستند، مثلاً `09121112233`. داده نمونه دو محدوده سرویس در تهران (ونک و اطراف برای شعبه مرکزی، غرب برای شعبه غرب) و برنامه هفتگی جمع‌آوری دارد.

نقشه‌ها از کاشی‌های OpenStreetMap استفاده می‌کنند؛ برای استفاده عملیاتی در پرتال مشتری، `NEXT_PUBLIC_MAP_TILE_URL` و `NEXT_PUBLIC_MAP_ATTRIBUTION` را به سرویس‌دهنده نقشه‌ای که با آن قرارداد دارید تغییر دهید.

### حساب‌های نمونه

رمز همه `password` است.

| نام کاربری | نقش | دسترسی |
|---|---|---|
| `admin` | مالک | همه چیز |
| `manager` | مدیر شعبه (شعبه مرکزی) | صندوق، سفارش‌ها، لغو و بازپرداخت، مشاهده کاتالوگ و پیامک‌ها، کارگاه (اسکن، مونتاژ، قفسه، بارنامه، برون‌سپاری)، خسارت و مفقودی، گزارش فروش و تولید، کاربران شعبه، محدوده‌های سرویس و بازه‌های جمع‌آوری شعبه |
| `accountant` | حسابدار | مشاهده سفارش‌ها و شیفت‌های همه شعبه‌ها، ردیابی قطعه، گزارش فروش و تولید |
| `cashier`، `cashier2` | پذیرش و صندوق | ثبت سفارش، دریافت پول، شیفت صندوق، تحویل، مونتاژ و قفسه، بارنامه به کارگاه |
| `operator` | اپراتور کارگاه (کارگاه مرکزی) | ایستگاه اسکن، تابلوی تولید، دریافت بارنامه، برون‌سپاری، گزارش تولید |
| `driver` | راننده | مشاهده مشتریان، محدوده‌ها و بازه‌های جمع‌آوری (اپ راننده در ادامه فاز ۴) |

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
