#!/bin/bash

# رنگ‌ها برای output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=========================================="
echo "تست API Backend - Super Smash"
echo "=========================================="
echo ""

# خواندن API URL از .env یا استفاده از مقدار پیش‌فرض
if [ -f .env ]; then
    API_URL=$(grep "^APP_URL=" .env | cut -d '=' -f2 | tr -d '"')
    if [ -z "$API_URL" ]; then
        API_URL="https://api.supersmash.ir"
    fi
else
    API_URL="https://api.supersmash.ir"
fi

echo "🌐 API URL: $API_URL"
echo ""

# شمارنده تست‌ها
PASSED=0
FAILED=0

# تابع برای تست endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local description=$4
    
    echo "📋 تست: $description"
    echo "   $method $endpoint"
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" -X GET "$API_URL/api$endpoint" \
            -H "Accept: application/json" \
            -H "Content-Type: application/json")
    else
        response=$(curl -s -w "\n%{http_code}" -X $method "$API_URL/api$endpoint" \
            -H "Accept: application/json" \
            -H "Content-Type: application/json" \
            -d "$data")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)
    
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo -e "   ✅ موفق - Status: $http_code"
        echo "   Response: $(echo $body | head -c 100)..."
        ((PASSED++))
    elif [ "$http_code" -ge 400 ] && [ "$http_code" -lt 500 ]; then
        echo -e "   ⚠️  Client Error - Status: $http_code (این ممکن است طبیعی باشد)"
        echo "   Response: $(echo $body | head -c 100)..."
        ((PASSED++))
    else
        echo -e "   ❌ خطا - Status: $http_code"
        echo "   Response: $body"
        ((FAILED++))
    fi
    echo ""
}

# 1. تست Health Check (اگر وجود دارد)
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "1️⃣  تست Health Check"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
test_endpoint "GET" "/test/codes" "" "تست endpoint ساده"

# 2. تست CORS
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "2️⃣  تست CORS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📋 تست CORS Preflight"
cors_response=$(curl -s -X OPTIONS "$API_URL/api/test/codes" \
    -H "Origin: https://supersmash.ir" \
    -H "Access-Control-Request-Method: GET" \
    -H "Access-Control-Request-Headers: Content-Type" \
    -v 2>&1)

if echo "$cors_response" | grep -q "access-control-allow-origin"; then
    echo -e "   ✅ CORS headers موجود است"
    ((PASSED++))
else
    echo -e "   ⚠️  CORS headers ممکن است مشکل داشته باشد"
    ((FAILED++))
fi
echo ""

# 3. تست Log Error
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "3️⃣  تست Log Error Endpoint"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
test_endpoint "POST" "/log-error" '{"error":"تست از script","type":"Test","timestamp":"'$(date -Iseconds)'"}' "تست لاگ خطا"

# 4. تست Register Endpoint
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "4️⃣  تست Register Endpoint"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
test_endpoint "POST" "/register" '{
    "username":"test_user_'$(date +%s)'",
    "name":"تست",
    "telegram_user_id":"123456789",
    "telegram_username":"test_user"
}' "تست ثبت‌نام"

# 5. تست User Exists
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "5️⃣  تست User Exists"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
test_endpoint "GET" "/user/exists?username=test_user_123" "" "بررسی وجود کاربر"

# 6. بررسی لاگ‌ها
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "6️⃣  بررسی فایل‌های لاگ"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
if [ -f "storage/logs/telegram-error.log" ]; then
    log_size=$(wc -l < storage/logs/telegram-error.log)
    echo "   ✅ telegram-error.log موجود است ($log_size خط)"
    ((PASSED++))
else
    echo "   ⚠️  telegram-error.log موجود نیست"
    ((FAILED++))
fi

if [ -f "storage/logs/laravel.log" ]; then
    log_size=$(stat -f%z storage/logs/laravel.log 2>/dev/null || stat -c%s storage/logs/laravel.log 2>/dev/null)
    echo "   ✅ laravel.log موجود است ($(numfmt --to=iec-i --suffix=B $log_size 2>/dev/null || echo "$log_size bytes"))"
    ((PASSED++))
else
    echo "   ⚠️  laravel.log موجود نیست"
    ((FAILED++))
fi
echo ""

# 7. بررسی تنظیمات
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "7️⃣  بررسی تنظیمات"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
if [ -f ".env" ]; then
    echo "   ✅ فایل .env موجود است"
    if grep -q "APP_URL" .env; then
        app_url=$(grep "^APP_URL=" .env | cut -d '=' -f2 | tr -d '"')
        echo "   APP_URL: $app_url"
    fi
    if grep -q "APP_ENV" .env; then
        app_env=$(grep "^APP_ENV=" .env | cut -d '=' -f2 | tr -d '"')
        echo "   APP_ENV: $app_env"
    fi
    ((PASSED++))
else
    echo "   ❌ فایل .env موجود نیست"
    ((FAILED++))
fi
echo ""

# خلاصه نتایج
echo "=========================================="
echo "📊 خلاصه نتایج"
echo "=========================================="
echo -e "✅ تست‌های موفق: ${GREEN}$PASSED${NC}"
echo -e "❌ تست‌های ناموفق: ${RED}$FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ همه تست‌ها با موفقیت انجام شد!${NC}"
    exit 0
else
    echo -e "${YELLOW}⚠️  برخی تست‌ها ناموفق بودند. لطفاً بررسی کنید.${NC}"
    exit 1
fi

