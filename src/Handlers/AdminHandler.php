<?php
/**
 * مدیریت درخواست‌های ادمین
 */

class AdminHandler {
    private $db;
    private $api;
    private $userId;
    private $chatId;

    public function __construct($db, $api, $userId, $chatId) {
        $this->db = $db;
        $this->api = $api;
        $this->userId = $userId;
        $this->chatId = $chatId;
    }

    /**
     * مدیریت درخواست‌های ورودی متنی
     */
    public function handleInput($text, $step = null) {
        switch ($step) {
            case 'add_card':
                return $this->handleAddCard($text);
            case 'edit_card':
                return $this->handleEditCard($text);
            default:
                return false;
        }
    }

    /**
     * دریافت شماره کارت و ذخیره
     */
    private function handleAddCard($text) {
        $text = str_replace(' ', '', $text);
        $text = str_replace('-', '', $text);

        if (!$this->isValidCardNumber($text)) {
            $this->api->sendMessage($this->chatId, "❌ شماره کارت یا شبا نامعتبر است.\n\nفرمت صحیح:\n• کارت: 16 رقم\n• شبا: 24 رقم\n\nدوباره تلاش کنید:");
            return false;
        }

        // حذف کارت‌های قبلی و اضافه کردن کارت جدید
        $this->db->update('bank_cards', ['is_primary' => false], ['admin_id' => $this->userId]);

        $result = $this->db->insert('bank_cards', [
            'admin_id' => $this->userId,
            'card_number' => $text,
            'is_primary' => true,
            'status' => 'active'
        ]);

        unset($_SESSION['step']);

        if ($result) {
            $this->api->sendMessage($this->chatId, "✅ شماره کارت با موفقیت ثبت شد!\n\n" .
                "شماره: <code>" . $this->maskCardNumber($text) . "</code>");
            return true;
        } else {
            $this->api->sendMessage($this->chatId, "❌ خطا در ثبت کارت. لطفاً دوباره تلاش کنید.");
            return false;
        }
    }

    /**
     * ویرایش شماره کارت
     */
    private function handleEditCard($text) {
        $text = str_replace(' ', '', $text);
        $text = str_replace('-', '', $text);

        if (!$this->isValidCardNumber($text)) {
            $this->api->sendMessage($this->chatId, "❌ شماره کارت یا شبا نامعتبر است. دوباره تلاش کنید:");
            return false;
        }

        $cardId = $_SESSION['edit_card_id'];
        $this->db->update('bank_cards', ['card_number' => $text], ['id' => $cardId]);

        unset($_SESSION['edit_card_id'], $_SESSION['step']);

        $this->api->sendMessage($this->chatId, "✅ شماره کارت بروزرسانی شد!\n\n" .
            "شماره: <code>" . $this->maskCardNumber($text) . "</code>");
        return true;
    }

    /**
     * اعتبارسنجی شماره کارت
     */
    private function isValidCardNumber($card) {
        $card = str_replace(' ', '', $card);
        $card = str_replace('-', '', $card);
        return (strlen($card) == 16 || strlen($card) == 24) && ctype_digit($card);
    }

    /**
     * مخفی کردن شماره کارت
     */
    private function maskCardNumber($card) {
        $length = strlen($card);
        if ($length == 16) {
            return substr($card, 0, 4) . '-' . substr($card, 4, 4) . '-' . substr($card, 8, 4) . '-' . substr($card, 12, 4);
        } else {
            return substr($card, 0, 4) . '-' . substr($card, 4, 8) . '-' . substr($card, 12, 8);
        }
    }

    /**
     * نمایش کارت‌های بانکی
     */
    public function showBankCards() {
        $cards = $this->db->select('bank_cards', '*', ['admin_id' => $this->userId, 'status' => 'active']);

        if (empty($cards)) {
            $keyboard = TelegramAPI::replyKeyboard([
                [TelegramAPI::replyButton('💳 اضافه کردن کارت')],
                [TelegramAPI::replyButton('⬅️ بازگشت')],
            ]);
            $this->api->sendMessage($this->chatId, "❌ هیچ کارت ثبت‌شده‌ای وجود ندارد.", $keyboard);
            return;
        }

        $text = "<b>💳 کارت‌های بانکی شما</b>\n\n";

        $buttons = [];
        foreach ($cards as $card) {
            $maskedCard = $this->maskCardNumber($card['card_number']);
            $primaryBadge = $card['is_primary'] ? ' ⭐' : '';
            
            $text .= "<b>$maskedCard</b>$primaryBadge\n";
            $buttons[] = [TelegramAPI::inlineButton('🔧 مدیریت', 'manage_card:' . $card['id'])];
        }

        $buttons[] = [TelegramAPI::inlineButton('➕ افزودن کارت جدید', 'add_new_card')];
        $buttons[] = [TelegramAPI::inlineButton('⬅️ بازگشت', 'back_to_admin_menu')];

        $keyboard = TelegramAPI::inlineKeyboard($buttons);
        $this->api->sendMessage($this->chatId, $text, $keyboard);
    }

    /**
     * مدیریت کارت
     */
    public function showManageCardOptions($cardId) {
        $card = $this->db->selectOne('bank_cards', '*', ['id' => $cardId, 'admin_id' => $this->userId]);

        if (!$card) {
            $this->api->sendMessage($this->chatId, "❌ کارت یافت نشد.");
            return;
        }

        $buttons = [
            [TelegramAPI::inlineButton('✏️ ویرایش', 'edit_card_number:' . $cardId)],
            [TelegramAPI::inlineButton('🗑️ حذف', 'delete_card:' . $cardId)],
            [TelegramAPI::inlineButton('⬅️ بازگشت', 'back_to_cards')],
        ];

        $keyboard = TelegramAPI::inlineKeyboard($buttons);

        $text = "<b>💳 مدیریت کارت</b>\n\n";
        $text .= "<b>شماره:</b> <code>" . $this->maskCardNumber($card['card_number']) . "</code>\n";
        $text .= "<b>وضعیت:</b> " . ($card['status'] === 'active' ? '✅ فعال' : '❌ غیرفعال') . "\n";
        $text .= "<b>اصلی:</b> " . ($card['is_primary'] ? '⭐ بله' : '❌ خیر') . "\n";

        $this->api->sendMessage($this->chatId, $text, $keyboard);
    }

    /**
     * شروع ویرایش کارت
     */
    public function startEditCard($cardId) {
        $_SESSION['edit_card_id'] = $cardId;
        $_SESSION['step'] = 'edit_card';
        $this->api->sendMessage($this->chatId, "💳 شماره کارت جدید را وارد کنید:");
    }

    /**
     * حذف کارت
     */
    public function deleteCard($cardId) {
        $card = $this->db->selectOne('bank_cards', '*', ['id' => $cardId, 'admin_id' => $this->userId]);

        if (!$card) {
            $this->api->sendMessage($this->chatId, "❌ کارت یافت نشد.");
            return;
        }

        if ($card['is_primary']) {
            $this->api->sendMessage($this->chatId, "⚠️ نمی‌توانید کارت اصلی را حذف کنید. ابتدا کارت دیگری را به عنوان اصلی تنظیم کنید.");
            return;
        }

        $keyboard = TelegramAPI::inlineKeyboard([
            [
                TelegramAPI::inlineButton('✅ تأیید', 'confirm_delete_card:' . $cardId),
                TelegramAPI::inlineButton('❌ لغو', 'cancel_delete')
            ]
        ]);

        $this->api->sendMessage($this->chatId, "⚠️ آیا مطمئن هستید که می‌خواهید این کارت را حذف کنید؟", $keyboard);
    }

    /**
     * تأیید حذف کارت
     */
    public function confirmDeleteCard($cardId) {
        $this->db->update('bank_cards', ['status' => 'inactive'], ['id' => $cardId]);
        $this->api->sendMessage($this->chatId, "✅ کارت حذف شد!");
    }

    /**
     * شروع اضافه کردن کارت جدید
     */
    public function startAddCard() {
        $_SESSION['step'] = 'add_card';
        $this->api->sendMessage($this->chatId, "💳 شماره کارت یا شبا را وارد کنید:\n\n" .
            "<b>فرمت:</b>\n" .
            "• کارت: 16 رقم\n" .
            "• شبا: 24 رقم (شروع با IR)");
    }

    /**
     * نمایش پروژه‌های ادمین
     */
    public function showMyProjects() {
        $projectAdmins = $this->db->select('project_admins', '*', ['admin_id' => $this->userId, 'is_active' => true]);

        if (empty($projectAdmins)) {
            $this->api->sendMessage($this->chatId, "❌ هیچ پروژه‌ای برای شما تعیین نشده است.");
            return;
        }

        $text = "<b>📁 پروژه‌های من</b>\n\n";

        foreach ($projectAdmins as $projectAdmin) {
            $project = $this->db->selectOne('projects', '*', ['id' => $projectAdmin['project_id']]);

            if ($project) {
                $text .= "<b>🏢 " . $project['name'] . "</b>\n";
                $text .= "📝 <i>" . $project['description'] . "</i>\n";
                $text .= "💼 شغل: " . $projectAdmin['job_title'] . "\n";
                $text .= "📊 سهم: " . $projectAdmin['share_percentage'] . "%\n";
                $text .= "─────────────────\n\n";
            }
        }

        $this->api->sendMessage($this->chatId, $text);
    }

    /**
     * نمایش تراکنش‌های ادمین
     */
    public function showTransactions() {
        $transactions = $this->db->select('transactions', '*', ['admin_id' => $this->userId]);

        if (empty($transactions)) {
            $this->api->sendMessage($this->chatId, "❌ هیچ تراکنشی وجود ندارد.");
            return;
        }

        // نمایش خلاصه
        $totalAmount = 0;
        $completedCount = 0;

        $text = "<b>💰 تراکنش‌های شما</b>\n\n";

        foreach ($transactions as $transaction) {
            $project = $this->db->selectOne('projects', '*', ['id' => $transaction['project_id']]);
            $statusEmoji = $transaction['status'] === 'completed' ? '✅' : '⏳';

            $text .= "$statusEmoji <b>" . $project['name'] . "</b>\n";
            $text .= "💵 مبلغ: " . number_format($transaction['amount']) . " ریال\n";
            $text .= "📅 تاریخ: " . $transaction['payment_date'] . "\n";
            $text .= "🕐 ساعت: " . $transaction['payment_time'] . "\n";
            $text .= "─────────────────\n\n";

            if ($transaction['status'] === 'completed') {
                $totalAmount += $transaction['amount'];
                $completedCount++;
            }
        }

        $text .= "\n<b>📊 خلاصه:</b>\n";
        $text .= "✅ تراکنش‌های تکمیل‌شده: $completedCount\n";
        $text .= "💰 کل مبلغ: " . number_format($totalAmount) . " ریال\n\n";

        $buttons = [
            [TelegramAPI::inlineButton('📥 دانلود Excel', 'download_excel')],
            [TelegramAPI::inlineButton('⬅️ بازگشت', 'back_to_admin_menu')],
        ];

        $keyboard = TelegramAPI::inlineKeyboard($buttons);
        $this->api->sendMessage($this->chatId, $text, $keyboard);
    }

    /**
     * تولید و ارسال فایل Excel
     */
    public function generateExcelFile() {
        require_once __DIR__ . '/../ExcelGenerator.php';

        $transactions = $this->db->select('transactions', '*', ['admin_id' => $this->userId]);

        if (empty($transactions)) {
            $this->api->sendMessage($this->chatId, "❌ هیچ تراکنشی برای دانلود وجود ندارد.");
            return;
        }

        $generator = new ExcelGenerator();
        $filePath = $generator->generateTransactionReport($transactions, $this->userId);

        if ($filePath && file_exists($filePath)) {
            $this->api->sendExcelFile($this->chatId, $filePath, "📊 تراکنش‌های شما");
            
            // حذف فایل بعد از ارسال
            @unlink($filePath);
        } else {
            $this->api->sendMessage($this->chatId, "❌ خطا در تولید فایل Excel.");
        }
    }

    /**
     * نمایش درامد امروز (درصورت وجود)
     */
    public function showTodayRevenue() {
        $today = date('Y-m-d');
        $projectAdmins = $this->db->select('project_admins', '*', ['admin_id' => $this->userId, 'is_active' => true]);

        if (empty($projectAdmins)) {
            $this->api->sendMessage($this->chatId, "❌ هیچ پروژه‌ای برای شما تعیین نشده است.");
            return;
        }

        $text = "<b>📊 درامد امروز</b>\n";
        $text .= "تاریخ: " . jdate('Y/m/d', strtotime($today)) . "\n\n";

        $totalRevenue = 0;

        foreach ($projectAdmins as $projectAdmin) {
            $calculation = $this->db->selectOne('admin_revenue_calculations', '*', [
                'project_admin_id' => $projectAdmin['id'],
                'revenue_date' => $today
            ]);

            if ($calculation) {
                $project = $this->db->selectOne('projects', '*', ['id' => $projectAdmin['project_id']]);

                $text .= "<b>🏢 " . $project['name'] . "</b>\n";
                $text .= "📊 درامد کل: " . number_format($calculation['total_project_revenue']) . " ریال\n";
                $text .= "📈 سهم شما: " . $calculation['admin_share_percentage'] . "%\n";
                $text .= "💰 مبلغ: " . number_format($calculation['calculated_amount']) . " ریال\n";
                $text .= "⏳ وضعیت: ";

                switch ($calculation['status']) {
                    case 'pending':
                        $text .= "⏳ در انتظار تأیید\n";
                        break;
                    case 'approved':
                        $text .= "✅ تأیید شده\n";
                        break;
                    case 'paid':
                        $text .= "✅ پرداخت‌شده\n";
                        break;
                    case 'rejected':
                        $text .= "❌ رد شده\n";
                        break;
                }

                $text .= "─────────────────\n\n";
                $totalRevenue += $calculation['calculated_amount'];
            }
        }

        if ($totalRevenue > 0) {
            $text .= "<b>💵 کل درامد امروز:</b> " . number_format($totalRevenue) . " ریال";
        } else {
            $text .= "❌ هنوز درامدی برای امروز ثبت نشده است.";
        }

        $this->api->sendMessage($this->chatId, $text);
    }
}
