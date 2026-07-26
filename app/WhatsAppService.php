<?php

class WhatsAppService
{
    private LibraryDatabase $db;
    private array $config;

    public function __construct(LibraryDatabase $db, array $config)
    {
        $this->db = $db;
        $this->config = $config['twilio'] ?? [];
    }

    private function formatWhatsAppNumber(string $phone): string
    {
        $phone = str_replace(' ', '', trim($phone));
        if ($phone === '') {
            return '';
        }
        if ($phone[0] !== '+') {
            $phone = '+91' . $phone;
        }
        if (strpos($phone, 'whatsapp:') !== 0) {
            $phone = 'whatsapp:' . $phone;
        }
        return $phone;
    }

    public function sendWhatsApp(?int $studentId, string $phoneNumber, string $eventType, string $messageBody, ?string $mediaUrl = null): array
    {
        $to = $this->formatWhatsAppNumber($phoneNumber);
        $from = $this->config['whatsapp_number'] ?? 'whatsapp:+14155238886';
        $accountSid = $this->config['account_sid'] ?? '';
        $authToken = $this->config['auth_token'] ?? '';

        $status = 'failed';
        $twilioSid = null;
        $errorMessage = null;

        if ($accountSid !== '' && $authToken !== '' && $to !== '') {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
            
            $postData = [
                'To' => $to,
                'From' => $from,
                'Body' => $messageBody,
            ];
            if ($mediaUrl) {
                $postData['MediaUrl'] = $mediaUrl;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, "{$accountSid}:{$authToken}");
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $errorMessage = "cURL Error: " . $curlError;
            } else {
                $json = json_decode($response, true);
                if ($httpCode >= 200 && $httpCode < 300 && isset($json['sid'])) {
                    $status = 'sent';
                    $twilioSid = $json['sid'];
                } else {
                    $errorMessage = $json['message'] ?? "Twilio HTTP Code: {$httpCode}";
                }
            }
        } else {
            $errorMessage = "Twilio credentials not configured or phone number empty.";
        }

        // Log to database
        try {
            $this->db->execute(
                'INSERT INTO whatsapp_logs (student_id, event_type, to_number, message_body, status, twilio_sid, error_message, created_at)
                 VALUES (:student_id, :event_type, :to_number, :message_body, :status, :twilio_sid, :error_message, NOW())',
                [
                    'student_id' => $studentId,
                    'event_type' => $eventType,
                    'to_number' => $to,
                    'message_body' => $messageBody,
                    'status' => $status,
                    'twilio_sid' => $twilioSid,
                    'error_message' => $errorMessage,
                ]
            );
        } catch (Throwable $e) {
            // Non-blocking log failure
        }

        return [
            'ok' => $status === 'sent',
            'status' => $status,
            'twilio_sid' => $twilioSid,
            'error' => $errorMessage,
        ];
    }

    public function sendRequestApproved(array $student, string $bookTitle, string $dueDate): array
    {
        $name = esc($student['full_name'] ?? 'Student');
        $msg = "Hello {$name},\n\n"
             . "Your request for *{$bookTitle}* has been approved! Please collect the book from the library desk.\n"
             . "Due Date: *{$dueDate}*\n\n"
             . "— LibraryHub";
        return $this->sendWhatsApp((int)$student['id'], $student['phone_number'], 'request_approved', $msg);
    }

    public function sendRequestRejected(array $student, string $bookTitle, string $reason): array
    {
        $name = esc($student['full_name'] ?? 'Student');
        $msg = "Hello {$name},\n\n"
             . "Your request for *{$bookTitle}* has been rejected.\n"
             . "Reason: {$reason}\n\n"
             . "You may re-request after 24 hours.\n\n"
             . "— LibraryHub";
        return $this->sendWhatsApp((int)$student['id'], $student['phone_number'], 'request_rejected', $msg);
    }

    public function sendDueReminder(array $student, string $bookTitle, string $dueDate): array
    {
        $name = esc($student['full_name'] ?? 'Student');
        $msg = "Hello {$name},\n\n"
             . "This is a reminder that *{$bookTitle}* is due *tomorrow* ({$dueDate}).\n"
             . "Please return it on time to avoid late fines.\n\n"
             . "— LibraryHub";
        return $this->sendWhatsApp((int)$student['id'], $student['phone_number'], 'due_reminder', $msg);
    }

    public function sendOverdueNotice(array $student, string $bookTitle, string $dueDate): array
    {
        $name = esc($student['full_name'] ?? 'Student');
        $msg = "Hello {$name},\n\n"
             . "*{$bookTitle}* was due on {$dueDate} and is now *OVERDUE*.\n"
             . "Late fines are being added daily. Please return it immediately.\n\n"
             . "— LibraryHub";
        return $this->sendWhatsApp((int)$student['id'], $student['phone_number'], 'overdue_notice', $msg);
    }

    public function sendFinePaymentConfirmed(array $student, float $amount, string $paymentMethod, ?int $paymentId = null): array
    {
        $name = esc($student['full_name'] ?? 'Student');
        $formattedAmount = number_format($amount, 2);
        $method = ucfirst($paymentMethod);

        $mediaUrl = null;

        if ($paymentId) {
            try {
                if (function_exists('cloudinary') && cloudinary()->isConfigured()) {
                    $mediaUrl = receipt_service()->ensurePdfFile($paymentId);
                } else {
                    $baseUrl = rtrim(getenv('APP_BASE_URL') ?: '', '/');
                    if ($baseUrl === '' || preg_match('/localhost|127\.0\.0\.1|0\.0\.0\.0/', $baseUrl)) {
                        $baseUrl = 'https://lms-php-qani.onrender.com';
                    }
                    $mediaUrl = "{$baseUrl}/receipt-pdf/{$paymentId}.pdf";
                }
            } catch (Throwable $e) {
                // Never crash payment on WhatsApp URL failure
            }
        }

        $msg = "Hello {$name},\n\n"
             . "✅ Your payment of *₹{$formattedAmount}* has been confirmed via {$method}.\n";

        if ($mediaUrl) {
            $msg .= "Your digital payment receipt is attached below.\n\n";
        } else {
            $msg .= "You can download your receipt from the LibraryHub dashboard.\n\n";
        }

        $msg .= "— LibraryHub";

        return $this->sendWhatsApp((int)$student['id'], $student['phone_number'], 'fine_paid', $msg, $mediaUrl);
    }

    public function sendBookReturned(array $student, string $bookTitle, string $condition, float $totalDue): array
    {
        $name = esc($student['full_name'] ?? 'Student');
        $msg = "Hello {$name},\n\n"
             . "The return of *{$bookTitle}* has been successfully processed.\n"
             . "Condition: *{$condition}*\n";
        if ($totalDue > 0) {
            $formatted = number_format($totalDue, 2);
            $msg .= "Total amount due (rent/fines): *₹{$formatted}*\n";
        } else {
            $msg .= "No pending dues for this book.\n";
        }
        $msg .= "\nThank you for returning it!\n\n— LibraryHub";
        return $this->sendWhatsApp((int)$student['id'], $student['phone_number'], 'book_returned', $msg);
    }

    public function sendPaymentDeclined(array $student, float $amount, ?string $reason = null): array
    {
        $name = esc($student['full_name'] ?? 'Student');
        $formattedAmount = number_format($amount, 2);
        $msg = "Hello {$name},\n\n"
             . "⚠️ Your payment of *₹{$formattedAmount}* could not be processed.\n";
        if ($reason) {
            $msg .= "Reason: {$reason}\n";
        }
        $msg .= "\nPlease try again from your fines page or contact the library desk for assistance.\n\n— LibraryHub";
        return $this->sendWhatsApp((int)$student['id'], $student['phone_number'], 'payment_declined', $msg);
    }
}
