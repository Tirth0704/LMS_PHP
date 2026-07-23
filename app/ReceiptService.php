<?php

class ReceiptService
{
    private LibraryDatabase $db;

    public function __construct(LibraryDatabase $db)
    {
        $this->db = $db;
    }

    public function renderReceiptHtml(int $paymentId): ?string
    {
        $payment = $this->db->fetch(
            "SELECT p.*, s.full_name AS student_name, s.enrollment_number, s.department, s.email, f.fine_type, f.description AS fine_description
             FROM payments p
             JOIN students s ON s.id = p.student_id
             LEFT JOIN fines f ON f.id = p.fine_id
             WHERE p.id = :id AND p.status = 'Completed' LIMIT 1",
            ['id' => $paymentId]
        );

        if (!$payment) {
            return null;
        }

        $receiptNo = sprintf("RCP-%06d", $payment['id']);
        $completedAt = date('d M Y H:i', strtotime($payment['completed_at'] ?? $payment['created_at']));
        $method = ucfirst($payment['payment_method']);
        $fineType = ucwords(str_replace('_', ' ', $payment['fine_type'] ?? 'General Dues'));
        $description = $payment['fine_description'] ?: 'Library Fine / Rent Payment';
        $amount = number_format((float)$payment['amount'], 2);

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - <?= esc($receiptNo) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 40px 20px;
            color: #1a1a2e;
        }
        .action-bar {
            max-width: 650px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .receipt-card {
            max-width: 650px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            padding: 40px;
            border-top: 6px solid #4f46e5;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .brand {
            font-size: 26px;
            font-weight: 800;
            color: #4f46e5;
            letter-spacing: -0.5px;
        }
        .title {
            font-size: 14px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .details-table th, .details-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }
        .details-table th {
            background-color: #f9fafb;
            color: #4b5563;
            font-weight: 600;
            width: 35%;
        }
        .details-table td {
            color: #111827;
            font-weight: 500;
        }
        .status-badge {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
        }
        .amount-highlight {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
        }
        .footer {
            border-top: 1px solid #f0f0f0;
            padding-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
        }
        .btn-action:hover {
            background: #4338ca;
            color: white;
            transform: translateY(-1px);
        }
        .btn-secondary-action {
            background: #e0e7ff;
            color: #4338ca;
        }
        .btn-secondary-action:hover {
            background: #c7d2fe;
            color: #3730a3;
        }
        @media print {
            body { background: white; padding: 0; }
            .action-bar { display: none !important; }
            .receipt-card { box-shadow: none; border: none; padding: 20px; }
            .btn-action { display: none !important; }
        }
    </style>
</head>
<body>

<div class="action-bar">
    <a href="javascript:history.back()" class="btn-action btn-secondary-action"><i class="bi bi-arrow-left"></i> Back</a>
    <div style="display: flex; gap: 10px;">
        <button onclick="window.print()" class="btn-action"><i class="bi bi-file-earmark-pdf-fill"></i> Save / Download PDF</button>
        <button onclick="window.print()" class="btn-action btn-secondary-action"><i class="bi bi-printer"></i> Print</button>
    </div>
</div>

<div class="receipt-card">
    <div class="header">
        <div>
            <div class="brand">LibraryHub</div>
            <div style="font-size: 12px; color: #6b7280;">Smart Library Resource & Student Platform</div>
        </div>
        <div style="text-align: right;">
            <div class="title">Official Receipt</div>
            <div style="font-size: 14px; font-weight: 700; color: #111827; margin-top: 4px;"><?= esc($receiptNo) ?></div>
        </div>
    </div>

    <table class="details-table">
        <tr>
            <th>Date & Time</th>
            <td><?= esc($completedAt) ?></td>
        </tr>
        <tr>
            <th>Payment Method</th>
            <td><?= esc($method) ?></td>
        </tr>
        <tr>
            <th>Student Name</th>
            <td><?= esc($payment['student_name']) ?></td>
        </tr>
        <tr>
            <th>Enrollment No.</th>
            <td><?= esc($payment['enrollment_number']) ?></td>
        </tr>
        <tr>
            <th>Department</th>
            <td><?= esc($payment['department']) ?></td>
        </tr>
        <tr>
            <th>Fine / Due Type</th>
            <td><?= esc($fineType) ?></td>
        </tr>
        <tr>
            <th>Description</th>
            <td><?= esc($description) ?></td>
        </tr>
        <tr>
            <th>Amount Paid</th>
            <td class="amount-highlight">₹ <?= esc($amount) ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><span class="status-badge">PAID ✓</span></td>
        </tr>
        <?php if (!empty($payment['razorpay_payment_id'])): ?>
        <tr>
            <th>Razorpay Payment ID</th>
            <td style="font-family: monospace; font-size: 13px;"><?= esc($payment['razorpay_payment_id']) ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="footer">
        <p>This is an automated digital payment receipt issued by LibraryHub.</p>
        <p>Generated on <?= date('d M Y H:i') ?> UTC | LibraryHub Systems</p>
    </div>

    <div style="margin-top: 30px; text-align: center;">
        <button onclick="window.print()" class="btn-action"><i class="bi bi-download me-1"></i> Save as PDF / Print Receipt</button>
    </div>
</div>

</body>
</html>
        <?php
        return ob_get_clean();
    }
}
