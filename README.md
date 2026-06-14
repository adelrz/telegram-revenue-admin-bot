# Telegram Revenue Admin Bot

ربات تلگرام برای مدیریت پروژه‌ها، ادمین‌ها و تراکنش‌های درامد

## ویژگی‌ها

### کیبورد مالک:
- افزودن ادمین
- مدیریت ادمین‌ها
- افزودن پروژه
- مدیریت پروژه‌ها

### کیبورد ادمین‌ها:
- افزودن شماره کارت
- پروژه‌های من
- تراکنش‌ها

## نصب و راه‌اندازی

### الزامات:
- PHP 7.4+
- MySQL/MariaDB
- cURL
- Telegram Bot Token

### مراحل نصب:

1. **کلون کردن پروژه:**
```bash
git clone https://github.com/adelrz/telegram-revenue-admin-bot.git
cd telegram-revenue-admin-bot
```

2. **تنظیمات دیتابیس:**
```bash
mysql -u root -p < database/schema.sql
```

3. **تنظیم فایل کانفیگ:**
```bash
cp config/config.example.php config/config.php
```

4. **ویرایش فایل `config/config.php` با اطلاعات خود:**

```php
define('BOT_TOKEN', 'YOUR_TELEGRAM_BOT_TOKEN');
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'revenue_bot');
define('BOT_OWNER_ID', YOUR_TELEGRAM_ID);
```

5. **تنظیم Webhook (اختیاری):**
```bash
curl -F "url=https://yourdomain.com/webhook.php" \
  https://api.telegram.org/botYOUR_TOKEN/setWebhook
```

6. **تنظیم Cron Job:**
```bash
# هر روز ساعت 1 صبح
0 1 * * * /usr/bin/php /path/to/bot/cron/daily_revenue.php
```

## ساختار پروژه

```
.
├── config/
│   ├── config.php           # تنظیمات اصلی
│   └── config.example.php   # نمونه تنظیمات
├── database/
│   └── schema.sql          # طرح دیتابیس
├── src/
│   ├── Bot.php             # کلاس اصلی ربات
│   ├── Database.php        # اتصال دیتابیس
│   ├── TelegramAPI.php     # API تلگرام
│   └── Handlers/           # مدیریت‌کننده‌ها
│       ├── OwnerHandler.php
│       └── AdminHandler.php
├── cron/
│   └── daily_revenue.php   # کرون جاب روزانه
├── webhook.php             # Webhook اصلی
├── poll.php                # Polling (برای تست)
└── README.md
```

## استفاده

### برای مالک:
1. `/start` را فرستاده
2. منوی مالک نمایش می‌یابد
3. پروژه و ادمین را اضافه کنید

### برای ادمین:
1. ادمین کاربر اضافه شود
2. `/start` را فرستاده
3. منوی ادمین نمایش می‌یابد

## توسعه‌دهندگان

اگر می‌خواهید پروژه را توسعه دهید:

1. یک branch جدید بسازید
2. تغییرات خود را انجام دهید
3. Pull Request ارسال کنید

## مجوز

MIT License
