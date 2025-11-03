#!/bin/bash

echo "🔍 تست سریع CORS..."
echo ""

API_URL="https://api.supersmash.ir"

echo "1. تست OPTIONS (Preflight)..."
curl -s -X OPTIONS "$API_URL/api/test/codes" \
  -H "Origin: https://supersmash.ir" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type" \
  -i 2>&1 | grep -i "access-control" | head -5

echo ""
echo "2. تست POST request..."
response=$(curl -s -X POST "$API_URL/api/log-error" \
  -H "Content-Type: application/json" \
  -H "Origin: https://supersmash.ir" \
  -H "Accept: application/json" \
  -d '{"error":"test","type":"CORS Test"}' \
  -i 2>&1)

echo "$response" | grep -i "access-control" || echo "   ⚠️  CORS headers موجود نیست"

echo ""
echo "✅ تست تمام شد"

