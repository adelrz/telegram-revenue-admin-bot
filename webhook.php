<?php
/**
 * Webhook برای دریافت پیام‌های تلگرام
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/TelegramAPI.php';
require_once __DIR__ . '/src/Bot.php';

// شروع Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// دریافت دیتای JSON از تلگرام
$content = file_get_contents('php://input');
$update = json_decode($content, true);

if (!$update) {
    http_response_code(400);
    exit('Invalid request');
}

try {
    $bot = new Bot();
    $bot->handleUpdate($update);
    http_response_code(200);
} catch (Exception $e) {
    error_log('Bot Error: ' . $e->getMessage());
    http_response_code(500);
}
