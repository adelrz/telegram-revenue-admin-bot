<?php
/**
 * Polling برای دریافت پیام‌های تلگرام (برای تست محلی)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/TelegramAPI.php';
require_once __DIR__ . '/src/Bot.php';

// شروع Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class BotPoller {
    private $api;
    private $bot;
    private $lastUpdateId = 0;

    public function __construct() {
        $this->api = new TelegramAPI(BOT_TOKEN);
        $this->bot = new Bot();
    }

    /**
     * شروع polling
     */
    public function start() {
        echo "🤖 Telegram Bot Poller شروع شد...\n";
        echo "[" . date('Y-m-d H:i:s') . "] منتظر پیام‌ها...\n\n";

        while (true) {
            $this->getUpdates();
            sleep(2); // هر 2 ثانیه چک کن
        }
    }

    /**
     * دریافت آپدیت‌ها
     */
    private function getUpdates() {
        $ch = curl_init();
        $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getUpdates';
        $url .= '?offset=' . ($this->lastUpdateId + 1);
        $url .= '&timeout=30';

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 35);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            echo "[" . date('Y-m-d H:i:s') . "] HTTP Error: $httpCode\n";
            return;
        }

        $data = json_decode($response, true);

        if (!$data['ok'] || empty($data['result'])) {
            return;
        }

        foreach ($data['result'] as $update) {
            $this->handleUpdate($update);
            $this->lastUpdateId = $update['update_id'];
        }
    }

    /**
     * پردازش آپدیت
     */
    private function handleUpdate($update) {
        try {
            $this->bot->handleUpdate($update);
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
        }
    }
}

// چک کنید که از خط فرمان اجرا می‌شود
if (php_sapi_name() === 'cli') {
    $poller = new BotPoller();
    $poller->start();
} else {
    echo "This script must be run from command line.";
}
