<?php
/**
 * مدیریت درخواست‌های مالک
 */

class OwnerHandler {
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
            case 'project_name':
                return $this->handleProjectName($text);
            case 'project_description':
                return $this->handleProjectDescription($text);
            case 'project_url':
                return $this->handleProjectUrl($text);
            case 'admin_name':
                return $this->handleAdminName($text);
            case 'admin_user_id':
                return $this->handleAdminUserId($text);
            case 'admin_share':
                return $this->handleAdminShare($text);
            case 'admin_job':
                return $this->handleAdminJob($text);
            case 'edit_project_name':
                return $this->handleEditProjectName($text);
            case 'edit_project_description':
                return $this->handleEditProjectDescription($text);
            case 'edit_project_url':
                return $this->handleEditProjectUrl($text);
            case 'edit_admin_share':
                return $this->handleEditAdminShare($text);
            case 'edit_admin_job':
                return $this->handleEditAdminJob($text);
            default:
                return false;
        }
    }

    /**
     * دریافت نام پروژه
     */
    private function handleProjectName($text) {
        $_SESSION['project_data'] = ['name' => $text];
        $_SESSION['step'] = 'project_description';
        $this->api->sendMessage($this->chatId, "📝 لطفاً توضیحات پروژه را وارد کنید:");
        return true;
    }

    /**
     * دریافت توضیحات پروژه
     */
    private function handleProjectDescription($text) {
        $_SESSION['project_data']['description'] = $text;
        $_SESSION['step'] = 'project_url';
        $this->api->sendMessage($this->chatId, "🌐 لطفاً آدرس وب‌سرویس را وارد کنید (مثال: https://api.example.com):");
        return true;
    }

    /**
     * دریافت URL وب‌سرویس و ذخیره پروژه
     */
    private function handleProjectUrl($text) {
        $projectId = $this->db->insert('projects', [
            'owner_id' => $this->userId,
            'name' => $_SESSION['project_data']['name'],
            'description' => $_SESSION['project_data']['description'],
            'web_service_url' => $text,
            'status' => 'active'
        ]);

        unset($_SESSION['project_data'], $_SESSION['step']);

        if ($projectId) {
            $this->api->sendMessage($this->chatId, "✅ پروژه با موفقیت اضافه شد!");\n            return true;
        } else {
            $this->api->sendMessage($this->chatId, "❌ خطا در اضافه کردن پروژه. لطفاً دوباره تلاش کنید.");
            return false;
        }
    }

    /**
     * دریافت نام ادمین
     */
    private function handleAdminName($text) {
        $_SESSION['admin_data'] = ['name' => $text];
        $_SESSION['step'] = 'admin_user_id';
        $this->api->sendMessage($this->chatId, "👤 لطفاً Telegram ID ادمین را وارد کنید:\n(آن را از @userinfobot دریافت کنید)");
        return true;
    }

    /**
     * دریافت Telegram ID ادمین
     */
    private function handleAdminUserId($text) {
        if (!is_numeric($text)) {
            $this->api->sendMessage($this->chatId, "❌ Telegram ID باید عدد باشد. دوباره تلاش کنید:");
            return false;
        }

        $user = $this->db->selectOne('users', '*', ['telegram_id' => (int)$text]);
        if (!$user) {
            $newUserId = $this->db->insert('users', [
                'telegram_id' => (int)$text,
                'is_admin' => true
            ]);
            $_SESSION['admin_data']['user_id'] = $newUserId;
        } else {
            $_SESSION['admin_data']['user_id'] = $user['id'];
            $this->db->update('users', ['is_admin' => true], ['id' => $user['id']]);
        }

        $_SESSION['step'] = 'admin_share';
        $this->api->sendMessage($this->chatId, "📊 لطفاً درصد سهم ادمین را وارد کنید (مثال: 10 برای 10%):");
        return true;
    }

    /**
     * دریافت درصد سهم ادمین
     */
    private function handleAdminShare($text) {
        $share = (float)str_replace(',', '.', $text);
        
        if ($share <= 0 || $share > 100) {
            $this->api->sendMessage($this->chatId, "❌ درصد باید بین 0 و 100 باشد. دوباره تلاش کنید:");
            return false;
        }

        $_SESSION['admin_data']['share'] = $share;
        $_SESSION['step'] = 'admin_job';
        $this->api->sendMessage($this->chatId, "💼 لطفاً شغل ادمین در این پروژه را وارد کنید (مثال: توسعه‌دهنده، طراح و...):");
        return true;
    }

    /**
     * دریافت شغل ادمین و ذخیره
     */
    private function handleAdminJob($text) {
        $projectId = $_SESSION['admin_data']['project_id'];
        $adminId = $_SESSION['admin_data']['user_id'];

        $existing = $this->db->selectOne('project_admins', '*', [
            'project_id' => $projectId,
            'admin_id' => $adminId
        ]);

        if ($existing) {
            $this->api->sendMessage($this->chatId, "⚠️ این ادمین قبلاً به این پروژه اضافه شده است.");
            unset($_SESSION['admin_data'], $_SESSION['step']);
            return false;
        }

        $result = $this->db->insert('project_admins', [
            'project_id' => $projectId,
            'admin_id' => $adminId,
            'admin_name' => $_SESSION['admin_data']['name'],
            'share_percentage' => $_SESSION['admin_data']['share'],
            'job_title' => $text,
            'is_active' => true
        ]);

        unset($_SESSION['admin_data'], $_SESSION['step']);

        if ($result) {
            $this->api->sendMessage($this->chatId, "✅ ادمین با موفقیت اضافه شد!");
            
            // ارسال پیام به ادمین
            $adminTelegram = $this->db->selectOne('users', '*', ['id' => $adminId]);
            if ($adminTelegram) {
                $project = $this->db->selectOne('projects', '*', ['id' => $projectId]);
                $message = "🎉 شما به عنوان ادمین به پروژه <b>{$project['name']}</b> اضافه شدید!\n\n";
                $message .= "📊 سهم شما: {$_SESSION['admin_data']['share']}%\n";
                $message .= "💼 شغل: $text\n\n";
                $message .= "با دستور /start شروع کنید.";
                $this->api->sendMessage($adminTelegram['telegram_id'], $message);
            }
            return true;
        } else {
            $this->api->sendMessage($this->chatId, "❌ خطا در اضافه کردن ادمین.");
            return false;
        }
    }

    /**
     * ویرایش نام پروژه
     */
    private function handleEditProjectName($text) {
        $projectId = $_SESSION['edit_project_id'];
        $this->db->update('projects', ['name' => $text], ['id' => $projectId]);
        unset($_SESSION['edit_project_id'], $_SESSION['step']);
        $this->api->sendMessage($this->chatId, "✅ نام پروژه بروزرسانی شد!");
        return true;
    }

    /**
     * ویرایش توضیحات پروژه
     */
    private function handleEditProjectDescription($text) {
        $projectId = $_SESSION['edit_project_id'];
        $this->db->update('projects', ['description' => $text], ['id' => $projectId]);
        unset($_SESSION['edit_project_id'], $_SESSION['step']);
        $this->api->sendMessage($this->chatId, "✅ توضیحات پروژه بروزرسانی شدند!");
        return true;
    }

    /**
     * ویرایش URL وب‌سرویس
     */
    private function handleEditProjectUrl($text) {
        $projectId = $_SESSION['edit_project_id'];
        $this->db->update('projects', ['web_service_url' => $text], ['id' => $projectId]);
        unset($_SESSION['edit_project_id'], $_SESSION['step']);
        $this->api->sendMessage($this->chatId, "✅ آدرس وب‌سرویس بروزرسانی شد!");
        return true;
    }

    /**
     * ویرایش درصد سهم ادمین
     */
    private function handleEditAdminShare($text) {
        $share = (float)str_replace(',', '.', $text);
        
        if ($share <= 0 || $share > 100) {
            $this->api->sendMessage($this->chatId, "❌ درصد باید بین 0 و 100 باشد. دوباره تلاش کنید:");
            return false;
        }

        $adminId = $_SESSION['edit_admin_id'];
        $this->db->update('project_admins', ['share_percentage' => $share], ['id' => $adminId]);
        unset($_SESSION['edit_admin_id'], $_SESSION['step']);
        $this->api->sendMessage($this->chatId, "✅ درصد سهم بروزرسانی شد!");
        return true;
    }

    /**
     * ویرایش شغل ادمین
     */
    private function handleEditAdminJob($text) {
        $adminId = $_SESSION['edit_admin_id'];
        $this->db->update('project_admins', ['job_title' => $text], ['id' => $adminId]);
        unset($_SESSION['edit_admin_id'], $_SESSION['step']);
        $this->api->sendMessage($this->chatId, "✅ شغل بروزرسانی شد!");
        return true;
    }

    /**
     * ویرایش پروژه
     */
    public function showEditProjectOptions($projectId) {
        $project = $this->db->selectOne('projects', '*', ['id' => $projectId]);
        
        if (!$project || $project['owner_id'] != $this->userId) {
            $this->api->sendMessage($this->chatId, "❌ پروژه یافت نشد.");
            return;
        }

        $buttons = [
            [TelegramAPI::inlineButton('✏️ ویرایش نام', 'edit_proj_name:' . $projectId)],
            [TelegramAPI::inlineButton('📝 ویرایش توضیحات', 'edit_proj_desc:' . $projectId)],
            [TelegramAPI::inlineButton('🌐 ویرایش URL', 'edit_proj_url:' . $projectId)],
            [TelegramAPI::inlineButton('🔄 تغییر وضعیت', 'toggle_proj_status:' . $projectId)],
            [TelegramAPI::inlineButton('🗑️ حذف پروژه', 'delete_proj:' . $projectId)],
            [TelegramAPI::inlineButton('⬅️ بازگشت', 'back_to_projects')],
        ];

        $keyboard = TelegramAPI::inlineKeyboard($buttons);

        $text = "<b>📋 مدیریت پروژه</b>\n\n";
        $text .= "<b>نام:</b> {$project['name']}\n";
        $text .= "<b>توضیحات:</b> {$project['description']}\n";
        $text .= "<b>URL:</b> <code>{$project['web_service_url']}</code>\n";
        $text .= "<b>وضعیت:</b> " . ($project['status'] === 'active' ? '✅ فعال' : '❌ غیرفعال') . "\n";

        $this->api->sendMessage($this->chatId, $text, $keyboard);
    }

    /**
     * شروع ویرایش نام پروژه
     */
    public function startEditProjectName($projectId) {
        $_SESSION['edit_project_id'] = $projectId;
        $_SESSION['step'] = 'edit_project_name';
        $this->api->sendMessage($this->chatId, "📝 نام جدید پروژه را وارد کنید:");
    }

    /**
     * شروع ویرایش توضیحات پروژه
     */
    public function startEditProjectDescription($projectId) {
        $_SESSION['edit_project_id'] = $projectId;
        $_SESSION['step'] = 'edit_project_description';
        $this->api->sendMessage($this->chatId, "📝 توضیحات جدید پروژه را وارد کنید:");
    }

    /**
     * شروع ویرایش URL
     */
    public function startEditProjectUrl($projectId) {
        $_SESSION['edit_project_id'] = $projectId;
        $_SESSION['step'] = 'edit_project_url';
        $this->api->sendMessage($this->chatId, "🌐 آدرس وب‌سرویس جدید را وارد کنید:");
    }

    /**
     * تغییر وضعیت پروژه
     */
    public function toggleProjectStatus($projectId) {
        $project = $this->db->selectOne('projects', '*', ['id' => $projectId]);
        $newStatus = $project['status'] === 'active' ? 'inactive' : 'active';
        $this->db->update('projects', ['status' => $newStatus], ['id' => $projectId]);
        $this->api->sendMessage($this->chatId, "✅ وضعیت پروژه تغییر کرد!");
    }

    /**
     * حذف پروژه
     */
    public function deleteProject($projectId) {
        $project = $this->db->selectOne('projects', '*', ['id' => $projectId]);
        
        if (!$project || $project['owner_id'] != $this->userId) {
            $this->api->sendMessage($this->chatId, "❌ پروژه یافت نشد.");
            return;
        }

        $keyboard = TelegramAPI::inlineKeyboard([
            [
                TelegramAPI::inlineButton('✅ تأیید', 'confirm_delete_proj:' . $projectId),
                TelegramAPI::inlineButton('❌ لغو', 'cancel_delete')
            ]
        ]);

        $this->api->sendMessage($this->chatId, "⚠️ آیا مطمئن هستید که می‌خواهید این پروژه را حذف کنید؟", $keyboard);
    }

    /**
     * تأیید حذف پروژه
     */
    public function confirmDeleteProject($projectId) {
        $this->db->delete('projects', ['id' => $projectId]);
        $this->api->sendMessage($this->chatId, "✅ پروژه حذف شد!");
    }

    /**
     * ویرایش ادمین
     */
    public function showEditAdminOptions($adminId) {
        $admin = $this->db->selectOne('project_admins', '*', ['id' => $adminId]);
        
        if (!$admin) {
            $this->api->sendMessage($this->chatId, "❌ ادمین یافت نشد.");
            return;
        }

        $project = $this->db->selectOne('projects', '*', ['id' => $admin['project_id']]);
        
        if ($project['owner_id'] != $this->userId) {
            $this->api->sendMessage($this->chatId, "❌ شما اجازه دسترسی ندارید.");
            return;
        }

        $buttons = [
            [TelegramAPI::inlineButton('📊 ویرایش درصد سهم', 'edit_admin_share:' . $adminId)],
            [TelegramAPI::inlineButton('💼 ویرایش شغل', 'edit_admin_job:' . $adminId)],
            [TelegramAPI::inlineButton('🔄 تغییر وضعیت', 'toggle_admin_status:' . $adminId)],
            [TelegramAPI::inlineButton('🗑️ حذف', 'delete_admin:' . $adminId)],
            [TelegramAPI::inlineButton('⬅️ بازگشت', 'back_to_admins')],
        ];

        $keyboard = TelegramAPI::inlineKeyboard($buttons);

        $text = "<b>👤 مدیریت ادمین</b>\n\n";
        $text .= "<b>نام:</b> {$admin['admin_name']}\n";
        $text .= "<b>پروژه:</b> {$project['name']}\n";
        $text .= "<b>سهم:</b> {$admin['share_percentage']}%\n";
        $text .= "<b>شغل:</b> {$admin['job_title']}\n";
        $text .= "<b>وضعیت:</b> " . ($admin['is_active'] ? '✅ فعال' : '❌ غیرفعال') . "\n";

        $this->api->sendMessage($this->chatId, $text, $keyboard);
    }

    /**
     * شروع ویرایش درصد سهم ادمین
     */
    public function startEditAdminShare($adminId) {
        $_SESSION['edit_admin_id'] = $adminId;
        $_SESSION['step'] = 'edit_admin_share';
        $this->api->sendMessage($this->chatId, "📊 درصد سهم جدید را وارد کنید:");
    }

    /**
     * شروع ویرایش شغل ادمین
     */
    public function startEditAdminJob($adminId) {
        $_SESSION['edit_admin_id'] = $adminId;
        $_SESSION['step'] = 'edit_admin_job';
        $this->api->sendMessage($this->chatId, "💼 شغل جدید را وارد کنید:");
    }

    /**
     * تغییر وضعیت ادمین
     */
    public function toggleAdminStatus($adminId) {
        $admin = $this->db->selectOne('project_admins', '*', ['id' => $adminId]);
        $newStatus = !$admin['is_active'];
        $this->db->update('project_admins', ['is_active' => $newStatus], ['id' => $adminId]);
        $this->api->sendMessage($this->chatId, "✅ وضعیت ادمین تغییر کرد!");
    }

    /**
     * حذف ادمین
     */
    public function deleteAdmin($adminId) {
        $keyboard = TelegramAPI::inlineKeyboard([
            [
                TelegramAPI::inlineButton('✅ تأیید', 'confirm_delete_admin:' . $adminId),
                TelegramAPI::inlineButton('❌ لغو', 'cancel_delete')
            ]
        ]);

        $this->api->sendMessage($this->chatId, "⚠️ آیا مطمئن هستید که می‌خواهید این ادمین را حذف کنید؟", $keyboard);
    }

    /**
     * تأیید حذف ادمین
     */
    public function confirmDeleteAdmin($adminId) {
        $this->db->delete('project_admins', ['id' => $adminId]);
        $this->api->sendMessage($this->chatId, "✅ ادمین حذف شد!");
    }

    /**
     * تأیید پرداخت
     */
    public function approvePayment($calculationId) {
        $calculation = $this->db->selectOne('admin_revenue_calculations', '*', ['id' => $calculationId]);
        
        if (!$calculation) {
            $this->api->sendMessage($this->chatId, "❌ محاسبه یافت نشد.");
            return;
        }

        $projectAdmin = $this->db->selectOne('project_admins', '*', ['id' => $calculation['project_admin_id']]);
        $adminUser = $this->db->selectOne('users', '*', ['id' => $projectAdmin['admin_id']]);
        $card = $this->db->selectOne('bank_cards', '*', ['admin_id' => $projectAdmin['admin_id'], 'is_primary' => true]);

        // بروزرسانی وضعیت محاسبه
        $this->db->update('admin_revenue_calculations', ['status' => 'approved'], ['id' => $calculationId]);

        // ایجاد تراکنش
        $transactionId = $this->db->insert('transactions', [
            'admin_id' => $projectAdmin['admin_id'],
            'project_id' => $projectAdmin['project_id'],
            'amount' => $calculation['calculated_amount'],
            'card_id' => $card['id'],
            'payment_date' => date('Y-m-d'),
            'payment_time' => date('H:i:s'),
            'revenue_calculation_id' => $calculationId,
            'status' => 'completed'
        ]);

        // ارسال پیام تأیید به ادمین
        $project = $this->db->selectOne('projects', '*', ['id' => $projectAdmin['project_id']]);
        $message = "✅ <b>پرداخت تأیید شد</b>\n\n";
        $message .= "<b>پروژه:</b> {$project['name']}\n";
        $message .= "<b>مبلغ:</b> " . number_format($calculation['calculated_amount']) . " ریال\n";
        $message .= "<b>تاریخ:</b> " . date('Y-m-d') . "\n";
        $message .= "<b>شماره کارت:</b> <code>{$card['card_number']}</code>\n\n";
        $message .= "مبلغ به حساب شما واریز خواهد شد.";

        $this->api->sendMessage($adminUser['telegram_id'], $message);
        
        $this->api->sendMessage($this->chatId, "✅ پرداخت تأیید و ثبت شد!");
    }

    /**
     * رد پرداخت
     */
    public function rejectPayment($calculationId) {
        $calculation = $this->db->selectOne('admin_revenue_calculations', '*', ['id' => $calculationId]);
        
        if (!$calculation) {
            $this->api->sendMessage($this->chatId, "❌ محاسبه یافت نشد.");
            return;
        }

        $projectAdmin = $this->db->selectOne('project_admins', '*', ['id' => $calculation['project_admin_id']]);
        $adminUser = $this->db->selectOne('users', '*', ['id' => $projectAdmin['admin_id']]);

        // بروزرسانی وضعیت محاسبه
        $this->db->update('admin_revenue_calculations', ['status' => 'rejected'], ['id' => $calculationId]);

        // ارسال پیام رد به ادمین
        $project = $this->db->selectOne('projects', '*', ['id' => $projectAdmin['project_id']]);
        $message = "❌ <b>درخواست پرداخت رد شد</b>\n\n";
        $message .= "<b>پروژه:</b> {$project['name']}\n";
        $message .= "<b>مبلغ:</b> " . number_format($calculation['calculated_amount']) . " ریال\n";
        $message .= "<b>تاریخ:</b> " . $calculation['revenue_date'] . "\n\n";
        $message .= "لطفاً با مالک تماس بگیرید.";

        $this->api->sendMessage($adminUser['telegram_id'], $message);
        
        $this->api->sendMessage($this->chatId, "✅ درخواست رد شد!");
    }
}
