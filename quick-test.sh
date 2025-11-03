#!/bin/bash

# تست سریع API
echo "🔍 تست سریع API Backend..."
echo ""

API_URL=${APP_URL:-"https://api.supersmash.ir"}

echo "1. تست Health Check..."
response=$(curl -s -o /dev/null -w "%{http_code}" "$API_URL/api/test/codes")
if [ "$response" = "200" ]; then
    echo "   ✅ Health Check موفق"
else
    echo "   ❌ Health Check ناموفق (Status: $response)"
fi

echo ""
echo "2. تست Log Error..."
response=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_URL/api/log-error" \
  -H "Content-Type: application/json" \
  -d '{"error":"test","type":"QuickTest","timestamp":"'$(date -Iseconds)'"}')
if [ "$response" = "200" ]; then
    echo "   ✅ Log Error موفق"
else
    echo "   ❌ Log Error ناموفق (Status: $response)"
fi

echo ""
echo "✅ تست سریع تمام شد!"

