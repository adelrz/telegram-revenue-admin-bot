<?php
/**
 * کلاس API تلگرام
 */

class TelegramAPI {
    private $token;
    private $apiUrl = 'https://api.telegram.org/bot';

    public function __construct($token) {
        $this->token = $token;
    }

    /**
     * ارسال پیام متنی
     */
    public function sendMessage($chatId, $text, $keyboard = null, $parseMode = 'HTML') {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];

        if ($keyboard) {
            $data['reply_markup'] = $keyboard;
        }

        return $this->makeRequest('sendMessage', $data);
    }

    /**
     * ویرایش پیام
     */
    public function editMessageText($chatId, $messageId, $text, $keyboard = null, $parseMode = 'HTML') {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];

        if ($keyboard) {
            $data['reply_markup'] = $keyboard;
        }

        return $this->makeRequest('editMessageText', $data);
    }

    /**
     * حذف پیام
     */
    public function deleteMessage($chatId, $messageId) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];

        return $this->makeRequest('deleteMessage', $data);
    }

    /**
     * پاسخ callback query
     */
    public function answerCallbackQuery($callbackQueryId, $text = '', $showAlert = false) {
        $data = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert ? 'true' : 'false',
        ];

        return $this->makeRequest('answerCallbackQuery', $data);
    }

    /**
     * ارسال فایل
     */
    public function sendDocument($chatId, $filePath, $caption = '') {
        $data = [
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ];

        return $this->makeRequest('sendDocument', $data, $filePath);
    }

    /**
     * کیبورد inline
     */
    public static function inlineKeyboard($buttons) {
        return json_encode([
            'inline_keyboard' => $buttons
        ]);
    }

    /**
     * دکمه inline
     */
    public static function inlineButton($text, $callbackData = null, $url = null) {
        $button = ['text' => $text];

        if ($callbackData) {
            $button['callback_data'] = $callbackData;
        } elseif ($url) {
            $button['url'] = $url;
        }

        return $button;
    }

    /**
     * کیبورد معمولی
     */
    public static function replyKeyboard($buttons, $oneTime = true, $resize = true) {
        return json_encode([
            'keyboard' => $buttons,
            'one_time_keyboard' => $oneTime,
            'resize_keyboard' => $resize,
            'is_persistent' => false
        ]);
    }

    /**
     * دکمه معمولی
     */
    public static function replyButton($text) {
        return ['text' => $text];
    }

    /**
     * درخواست فایل اکسل
     */
    public function sendExcelFile($chatId, $filePath, $caption = 'تراکنش‌های شما') {
        return $this->sendDocument($chatId, $filePath, $caption);
    }

    /**
     * ارسال درخواست تأیید
     */
    public function sendApprovalMessage($chatId, $text, $approveCallback, $rejectCallback) {
        $keyboard = TelegramAPI::inlineKeyboard([
            [
                TelegramAPI::inlineButton('✅ تأیید', $approveCallback),
                TelegramAPI::inlineButton('❌ رد', $rejectCallback)
            ]
        ]);

        return $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * ارسال درخواست انتخاب
     */
    public function sendSelectionMessage($chatId, $text, $options) {
        $buttons = [];
        foreach ($options as $label => $callback) {
            $buttons[] = [TelegramAPI::inlineButton($label, $callback)];
        }

        $keyboard = TelegramAPI::inlineKeyboard($buttons);
        return $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * درخواست HTTP
     */
    private function makeRequest($method, $data, $filePath = null) {
        $url = $this->apiUrl . $this->token . '/' . $method;

        if ($filePath && file_exists($filePath)) {
            $data['document'] = new CURLFile($filePath);
            return $this->curlRequest($url, $data, true);
        }

        return $this->curlRequest($url, json_encode($data));
    }

    /**
     * اجرای درخواست curl
     */
    private function curlRequest($url, $data, $isMultipart = false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, API_TIMEOUT);

        if ($isMultipart) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('Telegram API Error: HTTP ' . $httpCode . ' - ' . $response);
            return false;
        }

        return json_decode($response, true);
    }
}
