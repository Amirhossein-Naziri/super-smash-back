<?php

/**
 * تست سیستم دعوت
 * این فایل برای تست عملکرد سیستم دعوت ایجاد شده است
 */

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Models\Referral;
use App\Services\ReferralService;

// مثال استفاده از سیستم دعوت

echo "=== تست سیستم دعوت ===\n\n";

// 1. ایجاد کاربر اول (دعوت‌کننده)
echo "1. ایجاد کاربر اول (دعوت‌کننده):\n";
$user1 = new User();
$user1->name = 'کاربر اول';
$user1->telegram_username = 'user1';
$user1->score = 0;
$user1->referral_count = 0;
$user1->referral_rewards = 0;
$user1->save();

// ایجاد کد دعوت برای کاربر اول
$referralCode1 = $user1->generateReferralCode();
echo "کد دعوت کاربر اول: $referralCode1\n\n";

// 2. ایجاد کاربر دوم با کد دعوت کاربر اول
echo "2. ایجاد کاربر دوم با کد دعوت:\n";
$user2 = new User();
$user2->name = 'کاربر دوم';
$user2->telegram_username = 'user2';
$user2->score = 0;
$user2->referral_count = 0;
$user2->referral_rewards = 0;
$user2->save();

// ایجاد کد دعوت برای کاربر دوم
$referralCode2 = $user2->generateReferralCode();
echo "کد دعوت کاربر دوم: $referralCode2\n";

// پردازش دعوت
$referralService = new ReferralService();
$result = $referralService->processReferral($referralCode1, $user2->id);

if ($result['success']) {
    echo "دعوت موفق! پاداش: {$result['reward_amount']} امتیاز\n";
} else {
    echo "خطا در دعوت: {$result['message']}\n";
}

// 3. ایجاد کاربر سوم با کد دعوت کاربر اول
echo "\n3. ایجاد کاربر سوم با کد دعوت:\n";
$user3 = new User();
$user3->name = 'کاربر سوم';
$user3->telegram_username = 'user3';
$user3->score = 0;
$user3->referral_count = 0;
$user3->referral_rewards = 0;
$user3->save();

// ایجاد کد دعوت برای کاربر سوم
$referralCode3 = $user3->generateReferralCode();
echo "کد دعوت کاربر سوم: $referralCode3\n";

// پردازش دعوت دوم
$result = $referralService->processReferral($referralCode1, $user3->id);

if ($result['success']) {
    echo "دعوت موفق! پاداش: {$result['reward_amount']} امتیاز\n";
} else {
    echo "خطا در دعوت: {$result['message']}\n";
}

// 4. نمایش آمار نهایی
echo "\n4. آمار نهایی:\n";
$user1->refresh();
echo "کاربر اول:\n";
echo "- تعداد دعوت‌ها: {$user1->referral_count}\n";
echo "- مجموع پاداش‌ها: {$user1->referral_rewards}\n";
echo "- امتیاز کل: {$user1->score}\n";

$user2->refresh();
echo "\nکاربر دوم:\n";
echo "- دعوت شده توسط: {$user2->referred_by}\n";
echo "- امتیاز کل: {$user2->score}\n";

$user3->refresh();
echo "\nکاربر سوم:\n";
echo "- دعوت شده توسط: {$user3->referred_by}\n";
echo "- امتیاز کل: {$user3->score}\n";

// 5. نمایش لیست دعوت‌ها
echo "\n5. لیست دعوت‌های کاربر اول:\n";
$referrals = $user1->referralRecords()->with('referred')->get();
foreach ($referrals as $referral) {
    echo "- {$referral->referred->name} (پاداش: {$referral->reward_amount})\n";
}

echo "\n=== پایان تست ===\n";

/**
 * مثال API calls برای تست
 */

echo "\n=== مثال API Calls ===\n";

// مثال درخواست ثبت‌نام با کد دعوت
echo "1. درخواست ثبت‌نام با کد دعوت:\n";
echo "POST /api/register\n";
echo "Body: {\n";
echo "    \"username\": \"new_user\",\n";
echo "    \"name\": \"کاربر جدید\",\n";
echo "    \"referral_code\": \"$referralCode1\"\n";
echo "}\n\n";

// مثال درخواست اعتبارسنجی کد دعوت
echo "2. درخواست اعتبارسنجی کد دعوت:\n";
echo "POST /api/referral/validate\n";
echo "Body: {\n";
echo "    \"referral_code\": \"$referralCode1\"\n";
echo "}\n\n";

// مثال درخواست آمار دعوت‌ها
echo "3. درخواست آمار دعوت‌ها:\n";
echo "GET /api/referral/stats\n";
echo "Headers: Authorization: Bearer {token}\n\n";

// مثال درخواست کد دعوت کاربر
echo "4. درخواست کد دعوت کاربر:\n";
echo "GET /api/referral/code\n";
echo "Headers: Authorization: Bearer {token}\n\n";

// مثال درخواست جدول رتبه‌بندی
echo "5. درخواست جدول رتبه‌بندی:\n";
echo "GET /api/referral/leaderboard?limit=10\n\n";

echo "=== پایان مثال API Calls ===\n";
