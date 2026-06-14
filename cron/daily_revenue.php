<?php
/**
 * فایل Cron Job برای محاسبه درامد روزانه
 * این فایل باید هر روز در ساعت مشخص شده اجرا شود
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/TelegramAPI.php';

class DailyRevenueCron {
    private $db;
    private $api;
    private $yesterday;

    public function __construct() {
        $this->db = new Database();
        $this->api = new TelegramAPI(BOT_TOKEN);
        $this->yesterday = date('Y-m-d', strtotime('-1 day'));
    }

    /**
     * اجرای کرون جاب
     */
    public function run() {
        echo "[" . date('Y-m-d H:i:s') . "] شروع محاسبه درامد روزانه...\n";

        try {
            // دریافت تمام پروژه‌های فعال
            $projects = $this->db->select('projects', '*', ['status' => 'active']);

            foreach ($projects as $project) {
                $this->processProject($project);
            }

            echo "[" . date('Y-m-d H:i:s') . "] پایان محاسبه درامد روزانه.\n";
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] خطا: " . $e->getMessage() . "\n";
        }
    }

    /**
     * پردازش پروژه
     */
    private function processProject($project) {
        echo "[" . date('Y-m-d H:i:s') . "] پردازش پروژه: {$project['name']}...\n";

        try {
            // دریافت درامد از وب‌سرویس
            $revenue = $this->fetchRevenueFromAPI($project['web_service_url']);

            if ($revenue === false) {
                echo "[" . date('Y-m-d H:i:s') . "] خطا در دریافت درامد از {$project['web_service_url']}\n";
                return;
            }

            // ذخیره درامد روزانه
            $this->saveRevenue($project['id'], $revenue);

            // محاسبه درامد هر ادمین
            $this->calculateAdminRevenues($project['id'], $revenue);

        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] خطا در پردازش پروژه: " . $e->getMessage() . "\n";
        }
    }

    /**
     * دریافت درامد از وب‌سرویس
     */
    private function fetchRevenueFromAPI($url) {
        $url = rtrim($url, '/') . '/revenue?date=' . $this->yesterday;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, API_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            echo "[" . date('Y-m-d H:i:s') . "] HTTP Error: $httpCode\n";
            return false;
        }

        $data = json_decode($response, true);
        return $data['revenue'] ?? 0;
    }

    /**
     * ذخیره درامد روزانه
     */
    private function saveRevenue($projectId, $revenue) {
        // بررسی اینکه آیا درامد برای این روز وجود دارد
        $existing = $this->db->selectOne('daily_revenues', '*', [
            'project_id' => $projectId,
            'revenue_date' => $this->yesterday
        ]);

        if ($existing) {
            $this->db->update('daily_revenues', ['total_revenue' => $revenue], [
                'project_id' => $projectId,
                'revenue_date' => $this->yesterday
            ]);
        } else {
            $this->db->insert('daily_revenues', [
                'project_id' => $projectId,
                'revenue_date' => $this->yesterday,
                'total_revenue' => $revenue,
            ]);
        }

        echo "[" . date('Y-m-d H:i:s') . "] درامد ذخیره شد: {$revenue}\n";
    }

    /**
     * محاسبه درامد هر ادمین
     */
    private function calculateAdminRevenues($projectId, $totalRevenue) {
        // دریافت تمام ادمین‌های این پروژه
        $admins = $this->db->select('project_admins', '*', [
            'project_id' => $projectId,
            'is_active' => true
        ]);

        foreach ($admins as $admin) {
            $adminShare = ($totalRevenue * $admin['share_percentage']) / 100;

            // ذخیره محاسبه درامد
            $calculationId = $this->db->insert('admin_revenue_calculations', [
                'project_admin_id' => $admin['id'],
                'revenue_date' => $this->yesterday,
                'total_project_revenue' => $totalRevenue,
                'admin_share_percentage' => $admin['share_percentage'],
                'calculated_amount' => $adminShare,
                'status' => 'pending',
            ]);

            // ارسال پیام به مالک برای تأیید
            $this->sendApprovalMessage($projectId, $admin, $adminShare, $calculationId);
        }
    }

    /**
     * ارسال پیام تأیید به مالک
     */
    private function sendApprovalMessage($projectId, $admin, $amount, $calculationId) {
        $project = $this->db->selectOne('projects', '*', ['id' => $projectId]);
        $owner = $this->db->selectOne('users', '*', ['id' => $project['owner_id']]);

        // دریافت اطلاعات ادمین
        $adminUser = $this->db->selectOne('users', '*', ['id' => $admin['admin_id']]);

        // دریافت کارت بانکی اولیه ادمین
        $card = $this->db->selectOne('bank_cards', '*', [
            'admin_id' => $admin['admin_id'],
            'is_primary' => true
        ]);

        if (!$card) {
            echo "[" . date('Y-m-d H:i:s') . "] کارت بانکی برای ادمین {$admin['admin_name']} یافت نشد\n";
            return;
        }

        $text = "<b>📊 درخواست تأیید پرداخت</b>\n\n";
        $text .= "<b>پروژه:</b> {$project['name']}\n";
        $text .= "<b>ادمین:</b> {$admin['admin_name']}\n";
        $text .= "<b>تاریخ:</b> {$this->yesterday}\n";
        $text .= "<b>درصد سهم:</b> {$admin['share_percentage']}%\n";
        $text .= "<b>کل درامد پروژه:</b> " . number_format($project['total_revenue']) . " ریال\n";
        $text .= "<b>مبلغ قابل پرداخت:</b> <code>" . number_format($amount) . " ریال</code>\n";
        $text .= "<b>شماره کارت:</b> <code>{$card['card_number']}</code>\n\n";
        $text .= "آیا این مبلغ را پرداخت کنم؟";

        $keyboard = TelegramAPI::inlineKeyboard([
            [
                TelegramAPI::inlineButton('✅ تأیید', 'approve_payment:' . $calculationId),
                TelegramAPI::inlineButton('❌ رد', 'reject_payment:' . $calculationId)
            ]
        ]);

        // ارسال پیام به مالک
        $this->api->sendMessage($owner['telegram_id'], $text, $keyboard);

        echo "[" . date('Y-m-d H:i:s') . "] پیام تأیید برای {$admin['admin_name']} ارسال شد.\n";
    }
}

// اجرای کرون جاب
$cron = new DailyRevenueCron();
$cron->run();
