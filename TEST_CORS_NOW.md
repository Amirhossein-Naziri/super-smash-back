# تست سریع CORS - دستورات مستقیم

برای تست CORS بدون نیاز به اسکریپت، این دستورات را اجرا کنید:

## 1. تست OPTIONS (Preflight)

```bash
curl -X OPTIONS https://api.supersmash.ir/api/test/codes \
  -H "Origin: https://supersmash.ir" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type" \
  -i | grep -i "access-control"
```

**انتظار می‌رود:** باید headers زیر را ببینید:
- `Access-Control-Allow-Origin: *`
- `Access-Control-Allow-Methods: ...`
- `Access-Control-Allow-Headers: ...`

## 2. تست POST request

```bash
curl -X POST https://api.supersmash.ir/api/log-error \
  -H "Content-Type: application/json" \
  -H "Origin: https://supersmash.ir" \
  -H "Accept: application/json" \
  -d '{"error":"test","type":"CORS Test"}' \
  -i | grep -i "access-control"
```

**انتظار می‌رود:** باید CORS headers را در response ببینید.

## 3. تست Register endpoint

```bash
curl -X POST https://api.supersmash.ir/api/register \
  -H "Content-Type: application/json" \
  -H "Origin: https://supersmash.ir" \
  -H "Accept: application/json" \
  -d '{
    "username": "test_user_'$(date +%s)'",
    "telegram_user_id": "999999999",
    "telegram_username": "test_user"
  }' \
  -i | head -20
```

**انتظار می‌رود:** باید response با CORS headers دریافت کنید.

## اگر CORS headers را نمی‌بینید:

1. **مطمئن شوید سرور restart شده است:**
   ```bash
   sudo systemctl restart php8.3-fpm
   sudo systemctl restart apache2
   ```

2. **Cache را پاک کنید:**
   ```bash
   cd /var/www/supersmash/super-smash-back
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   ```

3. **بررسی کنید که middleware فعال است:**
   ```bash
   cat app/Http/Kernel.php | grep EnableCors
   ```
   باید `\App\Http\Middleware\EnableCors::class,` را ببینید.

## نتیجه:

✅ **اگر CORS headers را می‌بینید:** CORS کار می‌کند! مشکل از جای دیگری است.

❌ **اگر CORS headers را نمی‌بینید:** مشکل از middleware است. لاگ‌ها را بررسی کنید:
   ```bash
   tail -f storage/logs/laravel.log
   ```

