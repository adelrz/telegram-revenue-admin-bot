<?php
/**
 * کلاس اصلی ربات تلگرام
 */

class Bot {
    private $db;
    private $api;
    private $update;
    private $userId;
    private $chatId;
    private $messageId;
    private $callbackQueryId;
    private $callbackData;
    private $userMessage;
    private $isCallbackQuery = false;

    public function __construct() {
        $this->db = new Database();
        $this->api = new TelegramAPI(BOT_TOKEN);
    }

    /**
     * پردازش آپدیت دریافتی از تلگرام
     */
    public function handleUpdate($update) {
        $this->update = $update;

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }
    }

    /**
     * پردازش پیام معمولی
     */
    private function handleMessage($message) {
        $this->userId = $message['from']['id'];
        $this->chatId = $message['chat']['id'];
        $this->messageId = $message['message_id'];
        $this->userMessage = $message['text'] ?? '';

        // ثبت یا بهروزرسانی کاربر
        $this->registerUser($message['from']);

        // بررسی نوع کاربر و هدایت به منوی مناسب
        if ($this->isOwner($this->userId)) {
            $this->handleOwnerMessage($this->userMessage);
        } elseif ($this->isAdmin($this->userId)) {
            $this->handleAdminMessage($this->userMessage);
        } else {
            $this->handleUnauthorizedUser();
        }
    }

    /**
     * پردازش callback query
     */
    private function handleCallbackQuery($callbackQuery) {
        $this->userId = $callbackQuery['from']['id'];
        $this->chatId = $callbackQuery['message']['chat']['id'];
        $this->messageId = $callbackQuery['message']['message_id'];
        $this->callbackQueryId = $callbackQuery['id'];
        $this->callbackData = $callbackQuery['data'];
        $this->isCallbackQuery = true;

        // پاسخ به callback query
        $this->api->answerCallbackQuery($this->callbackQueryId);

        // بررسی نوع کاربر و هدایت
        if ($this->isOwner($this->userId)) {
            $this->handleOwnerCallback($this->callbackData);
        } elseif ($this->isAdmin($this->userId)) {
            $this->handleAdminCallback($this->callbackData);
        }
    }

    /**
     * مدیریت پیام مالک
     */
    private function handleOwnerMessage($text) {
        switch ($text) {
            case '/start':
                $this->showOwnerMenu();
                break;
            case '➕ افزودن ادمین':
                $this->showAddAdminMenu();
                break;
            case '👥 مدیریت ادمین‌ها':
                $this->showManageAdminsMenu();
                break;
            case '➕ افزودن پروژه':
                $this->showAddProjectMenu();
                break;
            case '📊 مدیریت پروژه‌ها':
                $this->showManageProjectsMenu();
                break;
            default:
                $this->handleOwnerInput($text);
        }
    }

    /**
     * مدیریت callback مالک
     */
    private function handleOwnerCallback($data) {
        $parts = explode(':', $data);
        $action = $parts[0];
        $param = $parts[1] ?? null;

        switch ($action) {
            case 'add_admin_project':
                $this->showAdminNameInput($param);
                break;
            case 'manage_project':
                $this->showEditProjectMenu($param);
                break;
            case 'manage_admin':
                $this->showEditAdminMenu($param);
                break;
            case 'approve_payment':
                $this->handlePaymentApproval($param);
                break;
            case 'reject_payment':
                $this->handlePaymentRejection($param);
                break;
        }
    }

    /**
     * مدیریت پیام ادمین
     */
    private function handleAdminMessage($text) {
        switch ($text) {
            case '/start':
                $this->showAdminMenu();
                break;
            case '💳 افزودن شماره کارت':
                $this->showAddCardInput();
                break;
            case '📁 پروژه‌های من':
                $this->showAdminProjects();
                break;
            case '💰 تراکنش‌ها':
                $this->showAdminTransactions();
                break;
            default:
                $this->handleAdminInput($text);
        }
    }

    /**
     * مدیریت callback ادمین
     */
    private function handleAdminCallback($data) {
        $parts = explode(':', $data);
        $action = $parts[0];
        $param = $parts[1] ?? null;

        switch ($action) {
            case 'edit_card':
                $this->showEditCardMenu($param);
                break;
            case 'delete_card':
                $this->handleDeleteCard($param);
                break;
            case 'download_transactions':
                $this->generateTransactionExcel();
                break;
        }
    }

    /**
     * نمایش منوی مالک
     */
    private function showOwnerMenu() {
        $keyboard = TelegramAPI::replyKeyboard([
            [TelegramAPI::replyButton('➕ افزودن ادمین')],
            [TelegramAPI::replyButton('👥 مدیریت ادمین‌ها')],
            [TelegramAPI::replyButton('➕ افزودن پروژه')],
            [TelegramAPI::replyButton('📊 مدیریت پروژه‌ها')],
        ]);

        $text = "👋 سلام مالک گرامی!\n\n" .
                "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";

        $this->api->sendMessage($this->chatId, $text, $keyboard);
    }

    /**
     * نمایش منوی ادمین
     */
    private function showAdminMenu() {
        $keyboard = TelegramAPI::replyKeyboard([
            [TelegramAPI::replyButton('💳 افزودن شماره کارت')],
            [TelegramAPI::replyButton('📁 پروژه‌های من')],
            [TelegramAPI::replyButton('💰 تراکنش‌ها')],
        ]);

        $text = "👋 سلام ادمین!\n\n" .
                "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";

        $this->api->sendMessage($this->chatId, $text, $keyboard);
    }

    /**
     * نمایش منوی افزودن ادمین
     */
    private function showAddAdminMenu() {
        $projects = $this->db->select('projects', '*', ['owner_id' => $this->userId, 'status' => 'active']);

        if (empty($projects)) {
            $this->api->sendMessage($this->chatId, '❌ هیچ پروژه‌ای وجود ندارد. ابتدا یک پروژه اضافه کنید.');
            return;
        }

        $buttons = [];
        foreach ($projects as $project) {
            $buttons[] = [TelegramAPI::inlineButton(
                '🔹 ' . $project['name'],
                'add_admin_project:' . $project['id']
            )];
        }

        $keyboard = TelegramAPI::inlineKeyboard($buttons);

        $text = "📋 لطفاً پروژه‌ای را برای افزودن ادمین انتخاب کنید:";
        $this->api->sendMessage($this->chatId, $text, $keyboard);
    }

    /**
     * درخواست نام ادمین
     */
    private function showAdminNameInput($projectId) {
        $_SESSION['add_admin_project_id'] = $projectId;
        $_SESSION['step'] = 'admin_name';

        $text = "📝 لطفاً نام ادمین را وارد کنید:";
        $this->api->sendMessage($this->chatId, $text);
    }

    /**
     * نمایش منوی افزودن پروژه
     */
    private function showAddProjectMenu() {
        $_SESSION['step'] = 'project_name';
        $text = "📝 لطفاً نام پروژه را وارد کنید:";
        $this->api->sendMessage($this->chatId, $text);
    }

    /**
     * نمایش منوی مدیریت پروژه‌ها
     */
    private function showManageProjectsMenu() {
        $projects = $this->db->select('projects', '*', ['owner_id' => $this->userId]);

        if (empty($projects)) {
            $this->api->sendMessage($this->chatId, '❌ هیچ پروژه‌ای وجود ندارد.');
            return;
        }

        $buttons = [];
        foreach ($projects as $project) {
            $buttons[] = [TelegramAPI::inlineButton(
                '🔹 ' . $project['name'],
                'manage_project:' . $project['id']
            )];
        }

        $keyboard = TelegramAPI::inlineKeyboard($buttons);

        $text = "📊 پروژه‌های شما:";
        $this->api->sendMessage($this->chatId, $text, $keyboard);
    }

    /**
     * نمایش منوی مدیریت ادمین‌ها
     */
    private function showManageAdminsMenu() {
        $admins = $this->db->select('project_admins', '*', []);
        $projectAdmins = [];
        
        foreach ($admins as $admin) {
            $project = $this->db->selectOne('projects', '*', ['id' => $admin['project_id']]);
            if ($project && $project['owner_id'] == $this->userId) {
                $projectAdmins[] = $admin;
            }
        }

        if (empty($projectAdmins)) {
            $this->api->sendMessage($this->chatId, '❌ هیچ ادمینی وجود ندارد.');
            return;
        }

        $buttons = [];
        foreach ($projectAdmins as $admin) {
            $buttons[] = [TelegramAPI::inlineButton(
                '👤 ' . $admin['admin_name'],
                'manage_admin:' . $admin['id']
            )];
        }

        $keyboard = TelegramAPI::inlineKeyboard($buttons);

        $text = "👥 ادمین‌های شما:";
        $this->api->sendMessage($this->chatId, $text, $keyboard);
    }

    /**
     * نمایش پروژه‌های ادمین
     */
    private function showAdminProjects() {
        $projects = $this->db->select(
            'project_admins',
            '*',
            ['admin_id' => $this->userId, 'is_active' => true]
        );

        if (empty($projects)) {
            $this->api->sendMessage($this->chatId, '❌ هیچ پروژه‌ای برای شما تعیین نشده‌است.');
            return;
        }

        $text = "📁 <b>پروژه‌های شما:</b>\n\n";

        foreach ($projects as $project) {
            $projectData = $this->db->selectOne('projects', '*', ['id' => $project['project_id']]);
            $text .= "<b>🔹 {$projectData['name']}</b>\n";
            $text .= "📝 توضیحات: {$projectData['description']}\n";
            $text .= "💼 شغل: {$project['job_title']}\n";
            $text .= "📊 سهم: {$project['share_percentage']}%\n\n";
        }

        $this->api->sendMessage($this->chatId, $text);
    }

    /**
     * نمایش تراکنش‌های ادمین
     */
    private function showAdminTransactions() {
        $transactions = $this->db->select(
            'transactions',
            '*',
            ['admin_id' => $this->userId]
        );

        if (empty($transactions)) {
            $this->api->sendMessage($this->chatId, '❌ هیچ تراکنشی وجود ندارد.');
            return;
        }

        $this->generateTransactionExcel($transactions);
    }

    /**
     * درخواست ورود شماره کارت
     */
    private function showAddCardInput() {
        $_SESSION['step'] = 'add_card';
        $text = "💳 لطفاً شماره کارت یا شماره شبا را وارد کنید:";
        $this->api->sendMessage($this->chatId, $text);
    }

    /**
     * بررسی اینکه آیا کاربر مالک است
     */
    private function isOwner($userId) {
        $user = $this->db->selectOne('users', '*', ['telegram_id' => $userId]);
        return $user && $user['is_owner'];
    }

    /**
     * بررسی اینکه آیا کاربر ادمین است
     */
    private function isAdmin($userId) {
        $user = $this->db->selectOne('users', '*', ['telegram_id' => $userId]);
        return $user && $user['is_admin'];
    }

    /**
     * ثبت یا بهروزرسانی کاربر
     */
    private function registerUser($userInfo) {
        $telegramId = $userInfo['id'];
        $existing = $this->db->selectOne('users', '*', ['telegram_id' => $telegramId]);

        if (!$existing) {
            $this->db->insert('users', [
                'telegram_id' => $telegramId,
                'username' => $userInfo['username'] ?? '',
                'first_name' => $userInfo['first_name'] ?? '',
                'last_name' => $userInfo['last_name'] ?? '',
            ]);
        }
    }

    /**
     * مدیریت درخواست‌های مالک
     */
    private function handleOwnerInput($text) {
        if (($_SESSION['step'] ?? null) === 'project_name') {
            $_SESSION['project_name'] = $text;
            $_SESSION['step'] = 'project_description';
            $this->api->sendMessage($this->chatId, "📝 لطفاً توضیحات پروژه را وارد کنید:");
        } elseif (($_SESSION['step'] ?? null) === 'project_description') {
            $_SESSION['project_description'] = $text;
            $_SESSION['step'] = 'project_url';
            $this->api->sendMessage($this->chatId, "🌐 لطفاً آدرس وب‌سرویس را وارد کنید:");
        } elseif (($_SESSION['step'] ?? null) === 'project_url') {
            $projectId = $this->db->insert('projects', [
                'owner_id' => $this->userId,
                'name' => $_SESSION['project_name'],
                'description' => $_SESSION['project_description'],
                'web_service_url' => $text,
            ]);

            unset($_SESSION['project_name'], $_SESSION['project_description'], $_SESSION['step']);

            if ($projectId) {
                $this->api->sendMessage($this->chatId, "✅ پروژه با موفقیت اضافه شد!");
                $this->showOwnerMenu();
            } else {
                $this->api->sendMessage($this->chatId, "❌ خطا در اضافه کردن پروژه.");
            }
        }
    }

    /**
     * مدیریت درخواست‌های ادمین
     */
    private function handleAdminInput($text) {
        if (($_SESSION['step'] ?? null) === 'add_card') {
            if ($this->isValidCardNumber($text)) {
                $this->db->insert('bank_cards', [
                    'admin_id' => $this->userId,
                    'card_number' => $text,
                    'is_primary' => true,
                ]);

                unset($_SESSION['step']);
                $this->api->sendMessage($this->chatId, "✅ شماره کارت با موفقیت ثبت شد!");
                $this->showAdminMenu();
            } else {
                $this->api->sendMessage($this->chatId, "❌ شماره کارت نامعتبر است. لطفاً دوباره تلاش کنید.");
            }
        }
    }

    /**
     * اعتبار سنجی شماره کارت
     */
    private function isValidCardNumber($card) {
        $card = str_replace(' ', '', $card);
        return (strlen($card) == 16 || strlen($card) == 24) && ctype_digit($card);
    }

    /**
     * مدیریت پیام‌های غیر مجاز
     */
    private function handleUnauthorizedUser() {
        $this->api->sendMessage($this->chatId, "❌ شما اجازه دسترسی به این ربات را ندارید.");
    }

    /**
     * سایر توابع...
     */
    private function handlePaymentApproval($calculationId) {
        // مدیریت تأیید پرداخت
    }

    private function handlePaymentRejection($calculationId) {
        // مدیریت رد پرداخت
    }

    private function showEditProjectMenu($projectId) {
        // نمایش منوی ویرایش پروژه
    }

    private function showEditAdminMenu($adminId) {
        // نمایش منوی ویرایش ادمین
    }

    private function showEditCardMenu($cardId) {
        // نمایش منوی ویرایش کارت
    }

    private function handleDeleteCard($cardId) {
        // حذف کارت
    }

    private function generateTransactionExcel($transactions = null) {
        // تولید فایل اکسل
    }
}
