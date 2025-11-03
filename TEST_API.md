# راهنمای تست API Backend

این راهنما به شما کمک می‌کند تا مطمئن شوید که بک‌اند به درستی کار می‌کند.

## 🚀 روش‌های تست

### 1. استفاده از اسکریپت خودکار

```bash
cd backend
./test-api.sh
```

این اسکریپت به صورت خودکار همه endpoint‌های مهم را تست می‌کند.

### 2. تست دستی با curl

#### تست Health Check
```bash
curl -X GET https://api.supersmash.ir/api/test/codes \
  -H "Accept: application/json"
```

#### تست CORS
```bash
curl -X OPTIONS https://api.supersmash.ir/api/test/codes \
  -H "Origin: https://supersmash.ir" \
  -H "Access-Control-Request-Method: GET" \
  -H "Access-Control-Request-Headers: Content-Type" \
  -v
```

#### تست Log Error
```bash
curl -X POST https://api.supersmash.ir/api/log-error \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "error": "تست لاگ",
    "type": "Test",
    "timestamp": "2024-01-01T00:00:00Z"
  }'
```

#### تست Register
```bash
curl -X POST https://api.supersmash.ir/api/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "username": "test_user_123",
    "name": "تست",
    "telegram_user_id": "123456789",
    "telegram_username": "test_user"
  }'
```

#### تست User Exists
```bash
curl -X GET "https://api.supersmash.ir/api/user/exists?username=test_user_123" \
  -H "Accept: application/json"
```

### 3. تست با Postman یا Insomnia

1. Import کردن collection (اگر دارید)
2. تنظیم Base URL: `https://api.supersmash.ir/api`
3. اجرای درخواست‌های مختلف

### 4. بررسی لاگ‌ها

```bash
# مشاهده لاگ‌های real-time
tail -f storage/logs/laravel.log

# مشاهده لاگ‌های خطای تلگرام
tail -f storage/logs/telegram-error.log

# جستجوی خطاهای خاص
grep -i "error\|exception" storage/logs/laravel.log | tail -20
```

### 5. بررسی وضعیت Laravel

```bash
# بررسی نسخه Laravel
php artisan --version

# لیست route‌ها
php artisan route:list

# پاک کردن cache
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Cache کردن برای production
php artisan config:cache
php artisan route:cache
```

## ✅ چک‌لیست

- [ ] سرور API در دسترس است (curl کار می‌کند)
- [ ] CORS headers به درستی تنظیم شده‌اند
- [ ] Endpoint `/api/test/codes` کار می‌کند
- [ ] Endpoint `/api/log-error` خطاها را ثبت می‌کند
- [ ] Endpoint `/api/register` کاربر جدید ثبت می‌کند
- [ ] Endpoint `/api/user/exists` بررسی کاربر را انجام می‌دهد
- [ ] فایل‌های لاگ ایجاد می‌شوند و قابل نوشتن هستند
- [ ] فایل `.env` به درستی تنظیم شده است
- [ ] `APP_URL` و `APP_ENV` درست هستند
- [ ] Database connection کار می‌کند

## 🔧 عیب‌یابی مشکلات رایج

### مشکل: درخواست‌ها fail می‌شوند

1. بررسی کنید که سرور در حال اجرا است
2. بررسی کنید که `APP_URL` در `.env` درست است
3. بررسی کنید که CORS به درستی تنظیم شده است
4. لاگ‌ها را بررسی کنید

### مشکل: CORS error

1. بررسی کنید که `config/cors.php` به درستی تنظیم شده است
2. بررسی کنید که `allowed_origins` شامل origin frontend است
3. Cache را پاک کنید: `php artisan config:clear`

### مشکل: لاگ‌ها نوشته نمی‌شوند

1. بررسی کنید که پوشه `storage/logs` قابل نوشتن است:
   ```bash
   chmod -R 755 storage/logs
   ```
2. بررسی کنید که owner درست است:
   ```bash
   chown -R www-data:www-data storage/logs
   ```

### مشکل: Database errors

1. بررسی کنید که `.env` به درستی تنظیم شده است
2. بررسی کنید که database موجود است
3. Migration‌ها را اجرا کنید:
   ```bash
   php artisan migrate
   ```

## 📝 نکات مهم

- همیشه قبل از deploy، تست‌ها را اجرا کنید
- لاگ‌ها را به صورت منظم بررسی کنید
- Cache‌ها را بعد از تغییر تنظیمات پاک کنید
- مطمئن شوید که فایل‌های لاگ قابل نوشتن هستند

