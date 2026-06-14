<?php
/**
 * تولید فایل Excel برای تراکنش‌ها
 */

class ExcelGenerator {
    
    /**
     * تولید گزارش تراکنش‌ها
     */
    public function generateTransactionReport($transactions, $adminId) {
        // استفاده از یک کتابخانه ساده برای ایجاد CSV (همان‌طور که Excel می‌تواند باز کند)
        $fileName = 'transactions_' . $adminId . '_' . time() . '.csv';
        $filePath = __DIR__ . '/../temp/' . $fileName;

        // ایجاد دایرکتوری اگر وجود ندارد
        if (!is_dir(__DIR__ . '/../temp/')) {
            mkdir(__DIR__ . '/../temp/', 0755, true);
        }

        $file = fopen($filePath, 'w');

        // تنظیم UTF-8 BOM برای Excel
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

        // هدر‌های جدول
        $headers = ['تاریخ پرداخت', 'ساعت پرداخت', 'پروژه', 'مبلغ (ریال)', 'شماره کارت', 'وضعیت'];
        fputcsv($file, $headers, ',');

        // ردیف‌های داده
        foreach ($transactions as $transaction) {
            $project = $this->getProject($transaction['project_id']);
            $card = $this->getCard($transaction['card_id']);

            $status = $this->getStatusText($transaction['status']);

            $row = [
                $transaction['payment_date'],
                $transaction['payment_time'],
                $project['name'] ?? 'نامشخص',
                number_format($transaction['amount']),
                $this->maskCardNumber($card['card_number'] ?? ''),
                $status
            ];

            fputcsv($file, $row, ',');
        }

        // جمع کل
        $totalAmount = 0;
        foreach ($transactions as $transaction) {
            if ($transaction['status'] === 'completed') {
                $totalAmount += $transaction['amount'];
            }
        }

        // خط خالی
        fputcsv($file, [], ',');

        // خط کل
        $totalRow = ['', '', 'کل کل:', number_format($totalAmount), '', ''];
        fputcsv($file, $totalRow, ',');

        fclose($file);

        return $filePath;
    }

    /**
     * دریافت اطلاعات پروژه
     */
    private function getProject($projectId) {
        global $db;
        if (!$db) {
            $db = new Database();
        }
        return $db->selectOne('projects', '*', ['id' => $projectId]);
    }

    /**
     * دریافت اطلاعات کارت
     */
    private function getCard($cardId) {
        global $db;
        if (!$db) {
            $db = new Database();
        }
        return $db->selectOne('bank_cards', '*', ['id' => $cardId]);
    }

    /**
     * متن وضعیت
     */
    private function getStatusText($status) {
        $statusMap = [
            'pending' => 'در انتظار',
            'confirmed' => 'تأیید شده',
            'completed' => 'تکمیل شده',
            'failed' => 'ناموفق'
        ];
        return $statusMap[$status] ?? 'نامشخص';
    }

    /**
     * مخفی کردن شماره کارت
     */
    private function maskCardNumber($card) {
        if (empty($card)) return '';
        $length = strlen($card);
        if ($length == 16) {
            return substr($card, 0, 4) . '-' . substr($card, 4, 4) . '-' . substr($card, 8, 4) . '-' . substr($card, 12, 4);
        } else {
            return substr($card, 0, 4) . '-' . substr($card, 4, 8) . '-' . substr($card, 12, 8);
        }
    }
}
