<?php
require_once __DIR__ . '/../app/bootstrap.php';

$today = date('Y-m-d');
$issues = db()->fetchAll(
    'SELECT bi.*, b.title AS book_title, s.id AS student_id, s.full_name, s.phone_number
     FROM book_issues bi
     JOIN books b ON b.id = bi.book_id
     JOIN students s ON s.id = bi.student_id
     WHERE bi.is_returned = 0 AND bi.is_lost = 0 AND bi.due_date < :today',
    ['today' => $today]
);

$count = 0;
foreach ($issues as $issue) {
    // 1. Create in-app notification
    library()->createNotification(
        (int) $issue['student_id'],
        'overdue_notice',
        'Book Overdue',
        $issue['book_title'] . ' was due on ' . format_date($issue['due_date']) . ' and is now OVERDUE.'
    );

    // 2. Send Twilio WhatsApp message
    try {
        whatsapp()->sendOverdueNotice($issue, $issue['book_title'], format_date($issue['due_date']));
    } catch (Throwable $e) {
        error_log("WhatsApp overdue notice error: " . $e->getMessage());
    }

    $count++;
}

echo "Created and sent {$count} overdue notices.\n";
