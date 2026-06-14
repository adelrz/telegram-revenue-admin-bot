<?php
/**
 * تنظیمات اصلی ربات تلگرام
 * این فایل را به config.php کپی کنید و مقادیر را تکمیل کنید
 */

// اطلاعات ربات
define('BOT_TOKEN', 'YOUR_TELEGRAM_BOT_TOKEN_HERE');
define('BOT_OWNER_ID', 123456789); // Telegram ID مالک
define('BOT_USERNAME', '@your_bot_username');

// تنظیمات دیتابیس
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'revenue_bot');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');

// تنظیمات Webhook
define('WEBHOOK_URL', 'https://yourdomain.com/webhook.php');
define('USE_WEBHOOK', false); // true برای webhook، false برای polling

// تنظیمات Cron
define('CRON_TIME', '01:00'); // ساعت اجرای cron (HH:MM)
define('CRON_TIMEZONE', 'Asia/Tehran'); // منطقه زمانی

// تنظیمات سایر
define('DEBUG', false);
define('LOG_FILE', __DIR__ . '/../logs/bot.log');
define('MAX_ADMIN_PER_PROJECT', 10);
define('MAX_PROJECTS_PER_OWNER', 50);

// API تنظیمات
define('API_TIMEOUT', 10);
define('API_RETRY', 3);

// Currency
define('CURRENCY', 'ریال');
