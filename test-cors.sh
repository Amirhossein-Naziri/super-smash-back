#!/bin/bash

echo "🔍 تست CORS Configuration..."
echo ""

API_URL="https://api.supersmash.ir"

echo "1. تست OPTIONS request (Preflight)..."
response=$(curl -s -X OPTIONS "$API_URL/api/test/codes" \
  -H "Origin: https://supersmash.ir" \
  -H "Access-Control-Request-Method: GET" \
  -H "Access-Control-Request-Headers: Content-Type" \
  -v 2>&1)

echo "$response" | grep -i "access-control" || echo "   ⚠️  CORS headers موجود نیست"

echo ""
echo "2. تست GET request با Origin..."
response=$(curl -s -X GET "$API_URL/api/test/codes" \
  -H "Origin: https://supersmash.ir" \
  -H "Accept: application/json" \
  -v 2>&1)

echo "$response" | grep -i "access-control" || echo "   ⚠️  CORS headers موجود نیست"

echo ""
echo "✅ تست CORS تمام شد"
echo ""
echo "اگر CORS headers را نمی‌بینید، مطمئن شوید که:"
echo "1. HandleCors middleware به Kernel اضافه شده است"
echo "2. Cache پاک شده است"
echo "3. config/cors.php به درستی تنظیم شده است"

