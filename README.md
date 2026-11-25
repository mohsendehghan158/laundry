# سامانه جامع مدیریت خشکشویی

پذیرش در پیشخوان، ردیابی قطعه در کارگاه، جمع‌آوری و تحویل، مالی و مشتریان سازمانی — فارسی، راست‌به‌چپ، تقویم جلالی.

مسیر کامل پروژه، مدل داده و فازبندی در [ROADMAP.md](ROADMAP.md) و تصمیم‌های معماری در [docs/adr](docs/adr/README.md) است.

## ساختار مخزن

| پوشه | محتوا |
|---|---|
| `backend/` | Laravel 13، فقط API (`/api/v1`)، یکپارچه ماژولار در `app/Modules` |
| `frontend/` | Next.js 16 + React 19 + Ant Design 6، پنل کارکنان |
| `docs/api/openapi.json` | قرارداد API که از کد تولید می‌شود |
| `docs/adr/` | تصمیم‌های معماری |
| `infra/` | Dockerfileها، پراکسی Caddy، استقرار تولید |
| `compose.yaml` | محیط توسعه محلی |

## اجرای محلی با Docker

```bash
cp backend/.env.example backend/.env
docker compose up -d
docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --seed
```

- پنل: <http://localhost:3000> — ورود با `admin` / `password` (کاربران نمونه دیگر: `manager`، `cashier`، `operator`، `driver`، `accountant` با همین رمز)
- مستندات API: <http://localhost:3000/docs/api>
- ایمیل‌های آزمایشی: <http://localhost:8025>

## اجرای محلی بدون Docker

پیش‌نیاز: PHP 8.4 (با `pdo_pgsql`، `redis`، `intl`)، Composer، Node.js 22، PostgreSQL 17، Redis 7.

```bash
# بک‌اند — http://localhost:8000
cd backend
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve

# فرانت — http://localhost:3000 (درخواست‌های /api را به بک‌اند می‌فرستد)
cd frontend
npm ci
npm run dev
```

برای راه‌اندازی تولید بدون داده نمونه به‌جای `--seed` از `php artisan laundry:install` استفاده کنید.

## تست و کیفیت

| | فرمان |
|---|---|
| تست بک‌اند (Pest، با پایگاه داده `laundry_test`) | `cd backend && php artisan test` |
| سبک کد و تحلیل ایستا | `vendor/bin/pint --test` · `vendor/bin/phpstan analyse` |
| به‌روزرسانی قرارداد API | `php artisan scramble:export` سپس در `frontend`: `npm run api:types` |
| فرانت | `npm run lint` · `npm run typecheck` · `npm test` · `npm run build` |
| تست مرورگر (بدون نیاز به بک‌اند) | `npx playwright install chromium` و سپس `npm run e2e` |

همه این‌ها در CI (`.github/workflows/ci.yml`) روی هر push اجرا می‌شوند و در پایان imageهای Docker ساخته می‌شوند.

## استقرار

```bash
cp infra/.env.prod.example infra/.env.prod   # رمزها و دامنه را پر کنید
docker compose -f infra/compose.prod.yaml --env-file infra/.env.prod up -d --build
```

Caddy با HTTPS خودکار جلوی هر دو سرویس می‌نشیند؛ مهاجرت پایگاه داده هنگام شروع کانتینر بک‌اند اجرا می‌شود. جزئیات در [ADR ۰۰۰۸](docs/adr/0008-deployment-docker.md).
