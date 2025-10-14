# سیستم دعوت (Referral System)

این سیستم امکان دعوت کاربران جدید و دریافت پاداش تصاعدی را فراهم می‌کند.

## ویژگی‌های سیستم

- هر کاربر یک کد دعوت منحصر به فرد دریافت می‌کند
- پاداش تصاعدی: کاربر اول ۱۰۰ امتیاز، دومین ۲۰۰ امتیاز، سومین ۳۰۰ امتیاز و...
- ردیابی کامل دعوت‌ها و آمارها
- جدول رتبه‌بندی دعوت‌کنندگان

## API Endpoints

### 1. ثبت‌نام با کد دعوت
```
POST /api/register
```

**Body:**
```json
{
    "username": "test_user",
    "name": "نام کاربر",
    "phone": "09123456789",
    "city": "تهران",
    "referral_code": "ABC12345"  // اختیاری
}
```

**Response:**
```json
{
    "success": true,
    "user": {...},
    "referral_code": "XYZ67890",
    "message": "ثبت‌نام با موفقیت انجام شد",
    "referral_result": {
        "success": true,
        "message": "کد دعوت با موفقیت اعمال شد",
        "reward_amount": 100
    }
}
```

### 2. اعتبارسنجی کد دعوت
```
POST /api/referral/validate
```

**Body:**
```json
{
    "referral_code": "ABC12345"
}
```

**Response:**
```json
{
    "valid": true,
    "message": "کد دعوت معتبر است",
    "referrer_name": "نام دعوت‌کننده"
}
```

### 3. دریافت آمار دعوت‌ها (نیاز به احراز هویت)
```
GET /api/referral/stats
```

**Response:**
```json
{
    "success": true,
    "stats": {
        "total_referrals": 5,
        "total_rewards": 1500,
        "referral_code": "XYZ67890",
        "referrals": [...]
    }
}
```

### 4. دریافت کد دعوت کاربر (نیاز به احراز هویت)
```
GET /api/referral/code
```

**Response:**
```json
{
    "success": true,
    "referral_code": "XYZ67890",
    "message": "کد دعوت شما"
}
```

### 5. دریافت لیست کاربران دعوت شده (نیاز به احراز هویت)
```
GET /api/referral/referred-users
```

**Response:**
```json
{
    "success": true,
    "referrals": [
        {
            "id": 1,
            "referred_user_name": "کاربر جدید",
            "referred_user_id": 123,
            "reward_amount": 100,
            "referral_order": 1,
            "created_at": "2024-01-01 12:00:00",
            "is_active": true
        }
    ],
    "total_count": 1,
    "total_rewards": 100
}
```

### 6. جدول رتبه‌بندی دعوت‌کنندگان
```
GET /api/referral/leaderboard?limit=10
```

**Response:**
```json
{
    "success": true,
    "leaderboard": [
        {
            "rank": 1,
            "user_id": 1,
            "name": "کاربر برتر",
            "referral_count": 10,
            "referral_rewards": 5500
        }
    ]
}
```

## ساختار دیتابیس

### جدول users (فیلدهای جدید)
- `referral_code`: کد دعوت منحصر به فرد کاربر
- `referred_by`: شناسه کاربری که این کاربر را دعوت کرده
- `referral_count`: تعداد دعوت‌های موفق
- `referral_rewards`: مجموع پاداش‌های دریافتی از دعوت

### جدول referrals
- `referrer_id`: شناسه دعوت‌کننده
- `referred_id`: شناسه دعوت‌شده
- `reward_amount`: مبلغ پاداش
- `referral_order`: ترتیب دعوت (اولین، دومین، ...)
- `is_active`: وضعیت فعال بودن دعوت

## نحوه کارکرد سیستم

1. **ثبت‌نام کاربر جدید:**
   - کاربر کد دعوت را وارد می‌کند (اختیاری)
   - سیستم کد دعوت منحصر به فرد برای کاربر جدید ایجاد می‌کند
   - اگر کد دعوت معتبر باشد، دعوت‌کننده پاداش دریافت می‌کند

2. **محاسبه پاداش:**
   - اولین دعوت: ۱۰۰ امتیاز
   - دومین دعوت: ۲۰۰ امتیاز
   - سومین دعوت: ۳۰۰ امتیاز
   - و به همین ترتیب...

3. **ردیابی:**
   - تمام دعوت‌ها در جدول `referrals` ثبت می‌شوند
   - آمار کاربران به‌روزرسانی می‌شود
   - امکان مشاهده تاریخچه دعوت‌ها وجود دارد

## نکات مهم

- هر کاربر نمی‌تواند خودش را دعوت کند
- هر کد دعوت فقط یک بار قابل استفاده است
- سیستم از تراکنش‌های دیتابیس استفاده می‌کند تا از یکپارچگی داده‌ها اطمینان حاصل شود
- تمام عملیات لاگ می‌شوند
