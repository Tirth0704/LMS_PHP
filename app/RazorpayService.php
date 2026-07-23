<?php

class RazorpayService
{
    private LibraryDatabase $db;
    private array $config;
    private WhatsAppService $whatsapp;

    public function __construct(LibraryDatabase $db, array $config, WhatsAppService $whatsapp)
    {
        $this->db = $db;
        $this->config = $config['razorpay'] ?? [];
        $this->whatsapp = $whatsapp;
    }

    public function createOrder(float $amount, int $fineId, int $studentId): array
    {
        $keyId = $this->config['key_id'] ?? '';
        $keySecret = $this->config['key_secret'] ?? '';

        $amountPaise = (int) round($amount * 100);

        if ($keyId === '' || $keySecret === '') {
            // Mock mode for development if keys are not provided
            $mockOrderId = 'order_mock_' . uniqid() . '_' . $fineId;
            return [
                'ok' => true,
                'id' => $mockOrderId,
                'amount' => $amountPaise,
                'currency' => 'INR',
                'mock' => true,
            ];
        }

        $url = "https://api.razorpay.com/v1/orders";
        $data = [
            'amount' => $amountPaise,
            'currency' => 'INR',
            'receipt' => "fine_{$fineId}_student_{$studentId}",
            'notes' => [
                'fine_id' => (string) $fineId,
                'student_id' => (string) $studentId,
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$keyId}:{$keySecret}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['ok' => false, 'message' => 'Razorpay connection error: ' . $curlError];
        }

        $json = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && isset($json['id'])) {
            return [
                'ok' => true,
                'id' => $json['id'],
                'amount' => $json['amount'],
                'currency' => $json['currency'],
                'mock' => false,
            ];
        }

        return [
            'ok' => false,
            'message' => $json['error']['description'] ?? "Razorpay API error (HTTP {$httpCode})"
        ];
    }

    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        $keySecret = $this->config['key_secret'] ?? '';
        if ($keySecret === '') {
            // Mock mode signature verification
            return strpos($orderId, 'order_mock_') === 0 || $signature === 'mock_signature';
        }

        $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $keySecret);
        return hash_equals($expectedSignature, $signature);
    }

    public function completePayment(int $fineId, int $studentId, string $orderId, string $paymentId, string $signature): array
    {
        if (!$this->verifySignature($orderId, $paymentId, $signature)) {
            $student = $this->db->fetch('SELECT * FROM students WHERE id = :id', ['id' => $studentId]);
            $fine = $this->db->fetch('SELECT * FROM fines WHERE id = :id', ['id' => $fineId]);
            if ($student && $fine) {
                $this->whatsapp->sendPaymentDeclined($student, (float) $fine['amount'], 'Signature verification failed.');
            }
            return ['ok' => false, 'message' => 'Payment signature verification failed.'];
        }

        $fine = $this->db->fetch('SELECT * FROM fines WHERE id = :id LIMIT 1', ['id' => $fineId]);
        if (!$fine || $fine['status'] === 'Paid') {
            return ['ok' => false, 'message' => 'Fine not found or already paid.'];
        }

        $student = $this->db->fetch('SELECT * FROM students WHERE id = :id LIMIT 1', ['id' => $studentId]);
        if (!$student) {
            return ['ok' => false, 'message' => 'Student record not found.'];
        }

        $this->db->begin();
        try {
            // Insert Payment
            $this->db->execute(
                "INSERT INTO payments (student_id, fine_id, amount, payment_method, status, razorpay_order_id, razorpay_payment_id, razorpay_signature, receipt_path, completed_at, created_at)
                 VALUES (:student_id, :fine_id, :amount, 'razorpay', 'Completed', :order_id, :payment_id, :signature, 'dynamic', NOW(), NOW())",
                [
                    'student_id' => $studentId,
                    'fine_id' => $fineId,
                    'amount' => $fine['amount'],
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'signature' => $signature,
                ]
            );
            $paymentDbId = (int) $this->db->lastInsertId();

            // Mark Fine Paid
            $this->db->execute(
                "UPDATE fines SET status = 'Paid', paid_at = NOW() WHERE id = :id",
                ['id' => $fineId]
            );

            // Behaviour Score +2 for paying fine immediately
            $scoreChange = app_config('business.score_paid_fine_immediately', 2);
            $newScore = min(100, max(0, (int)$student['behaviour_score'] + $scoreChange));
            $accountStatus = account_status_for_score($newScore);

            $this->db->execute(
                'UPDATE students SET behaviour_score = :score, account_status = :status WHERE id = :id',
                ['score' => $newScore, 'status' => $accountStatus, 'id' => $studentId]
            );

            $this->db->execute(
                "INSERT INTO behaviour_logs (student_id, score_before, score_change, score_after, event_type, description, created_at)
                 VALUES (:student_id, :score_before, :score_change, :score_after, 'paid_fine_immediately', 'Paid fine via Razorpay', NOW())",
                [
                    'student_id' => $studentId,
                    'score_before' => $student['behaviour_score'],
                    'score_change' => $scoreChange,
                    'score_after' => $newScore,
                ]
            );

            // In-app Notification
            $this->db->execute(
                "INSERT INTO notifications (student_id, notification_type, title, message, is_read, created_at)
                 VALUES (:student_id, 'fine_paid', 'Fine Paid Successfully', :message, 0, NOW())",
                [
                    'student_id' => $studentId,
                    'message' => "Your fine of ₹" . number_format((float)$fine['amount'], 2) . " has been paid via Razorpay.",
                ]
            );

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'message' => 'Database error recording payment: ' . $e->getMessage()];
        }

        // Send WhatsApp Notification asynchronously (outside transaction)
        try {
            $this->whatsapp->sendFinePaymentConfirmed($student, (float) $fine['amount'], 'razorpay', $paymentDbId);
        } catch (Throwable $e) {
            // Non-blocking
        }

        return ['ok' => true, 'payment_id' => $paymentDbId, 'message' => 'Payment completed successfully.'];
    }
}
