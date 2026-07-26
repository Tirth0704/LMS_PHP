<?php

function load_env_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        [$key, $value] = $parts;
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}

load_env_file(dirname(__DIR__) . '/.env');

$GLOBALS['config'] = require __DIR__ . '/config.php';
date_default_timezone_set($GLOBALS['config']['timezone']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/CloudinaryService.php';
require_once __DIR__ . '/WhatsAppService.php';
require_once __DIR__ . '/RazorpayService.php';
require_once __DIR__ . '/fpdf.php';
require_once __DIR__ . '/ReceiptService.php';
require_once __DIR__ . '/LibraryService.php';

$GLOBALS['database'] = new LibraryDatabase($GLOBALS['config']['db']);

// Auto-migrate missing columns/tables in existing MySQL databases
try {
    $dbPdo = $GLOBALS['database']->pdo();

    // Check if books table exists
    $tables = $dbPdo->query("SHOW TABLES LIKE 'books'")->fetchAll();
    if (empty($tables)) {
        // Run full schema creation if database is fresh/empty
        $schemaPath = __DIR__ . '/../database/schema.sql';
        if (file_exists($schemaPath)) {
            $rawSql = file_get_contents($schemaPath);
            $rawSql = preg_replace('/CREATE DATABASE[^;]*;/i', '', $rawSql);
            $rawSql = preg_replace('/USE [^;]*;/i', '', $rawSql);
            $statements = array_filter(array_map('trim', explode(';', $rawSql)));
            foreach ($statements as $stmt) {
                if ($stmt !== '') {
                    try {
                        $dbPdo->exec($stmt);
                    } catch (Throwable $e) {}
                }
            }
        }
    } else {
        // Ensure books table columns exist
        $neededBooksCols = [
            'is_archived' => "ALTER TABLE books ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0",
        ];
        foreach ($neededBooksCols as $colName => $alterSql) {
            $colExists = $dbPdo->query("SHOW COLUMNS FROM books LIKE '{$colName}'")->fetchAll();
            if (empty($colExists)) {
                try { $dbPdo->exec($alterSql); } catch (Throwable $e) {}
            }
        }

        // Ensure payments table columns exist
        $neededPayCols = [
            'reference_no'        => "ALTER TABLE payments ADD COLUMN reference_no VARCHAR(100) DEFAULT NULL",
            'razorpay_order_id'   => "ALTER TABLE payments ADD COLUMN razorpay_order_id VARCHAR(100) DEFAULT NULL",
            'razorpay_payment_id' => "ALTER TABLE payments ADD COLUMN razorpay_payment_id VARCHAR(100) DEFAULT NULL",
            'razorpay_signature'  => "ALTER TABLE payments ADD COLUMN razorpay_signature VARCHAR(255) DEFAULT NULL",
            'receipt_path'        => "ALTER TABLE payments ADD COLUMN receipt_path VARCHAR(255) DEFAULT NULL",
        ];
        foreach ($neededPayCols as $colName => $alterSql) {
            $colExists = $dbPdo->query("SHOW COLUMNS FROM payments LIKE '{$colName}'")->fetchAll();
            if (empty($colExists)) {
                try { $dbPdo->exec($alterSql); } catch (Throwable $e) {}
            }
        }

        // Ensure book_returns table exists and has all required columns
        $tablesRet = $dbPdo->query("SHOW TABLES LIKE 'book_returns'")->fetchAll();
        if (empty($tablesRet)) {
            $dbPdo->exec("CREATE TABLE IF NOT EXISTS book_returns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                issue_id INT NOT NULL UNIQUE,
                student_id INT NOT NULL,
                book_id INT NOT NULL,
                return_date DATE NOT NULL,
                return_condition VARCHAR(20) NOT NULL DEFAULT 'Good',
                rent_charged DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                late_fine DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                damage_fine DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                lost_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                total_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                payment_mode VARCHAR(20) NOT NULL DEFAULT 'offline',
                librarian_notes TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            $neededRetCols = [
                'return_condition' => "ALTER TABLE book_returns ADD COLUMN return_condition VARCHAR(20) NOT NULL DEFAULT 'Good'",
                'rent_charged'     => "ALTER TABLE book_returns ADD COLUMN rent_charged DECIMAL(10,2) NOT NULL DEFAULT 0.00",
                'late_fine'        => "ALTER TABLE book_returns ADD COLUMN late_fine DECIMAL(10,2) NOT NULL DEFAULT 0.00",
                'damage_fine'      => "ALTER TABLE book_returns ADD COLUMN damage_fine DECIMAL(10,2) NOT NULL DEFAULT 0.00",
                'lost_amount'      => "ALTER TABLE book_returns ADD COLUMN lost_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
                'total_due'        => "ALTER TABLE book_returns ADD COLUMN total_due DECIMAL(10,2) NOT NULL DEFAULT 0.00",
                'payment_mode'     => "ALTER TABLE book_returns ADD COLUMN payment_mode VARCHAR(20) NOT NULL DEFAULT 'offline'",
                'librarian_notes'  => "ALTER TABLE book_returns ADD COLUMN librarian_notes TEXT DEFAULT NULL",
            ];
            foreach ($neededRetCols as $colName => $alterSql) {
                $colExists = $dbPdo->query("SHOW COLUMNS FROM book_returns LIKE '{$colName}'")->fetchAll();
                if (empty($colExists)) {
                    try { $dbPdo->exec($alterSql); } catch (Throwable $e) {}
                }
            }

            // Ensure legacy 'condition' column does not block INSERTs if present on existing databases
            $colsOldCond = $dbPdo->query("SHOW COLUMNS FROM book_returns LIKE 'condition'")->fetchAll();
            if (!empty($colsOldCond)) {
                try { $dbPdo->exec("ALTER TABLE book_returns MODIFY COLUMN `condition` VARCHAR(20) DEFAULT NULL"); } catch (Throwable $e) {}
            }
        }
    }

    $dbPdo->exec("CREATE TABLE IF NOT EXISTS whatsapp_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT DEFAULT NULL,
        event_type VARCHAR(50) NOT NULL,
        to_number VARCHAR(30) NOT NULL,
        message_body TEXT NOT NULL,
        media_url TEXT DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'sent',
        twilio_sid VARCHAR(100) DEFAULT NULL,
        error_message TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_whatsapp_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $whatsappCols = [
        'media_url' => "ALTER TABLE whatsapp_logs ADD COLUMN media_url TEXT DEFAULT NULL",
    ];
    foreach ($whatsappCols as $colName => $alterSql) {
        $colExists = $dbPdo->query("SHOW COLUMNS FROM whatsapp_logs LIKE '{$colName}'")->fetchAll();
        if (empty($colExists)) {
            try { $dbPdo->exec($alterSql); } catch (Throwable $e) {}
        }
    }
} catch (Throwable $e) {
    // Gracefully handle auto-migration exceptions
}

$GLOBALS['cloudinary'] = new CloudinaryService($GLOBALS['config']);
$GLOBALS['whatsapp'] = new WhatsAppService($GLOBALS['database'], $GLOBALS['config']);
$GLOBALS['razorpay'] = new RazorpayService($GLOBALS['database'], $GLOBALS['config'], $GLOBALS['whatsapp']);
$GLOBALS['receipt_service'] = new ReceiptService($GLOBALS['database']);
$GLOBALS['library'] = new LibraryService($GLOBALS['database'], $GLOBALS['config'], $GLOBALS['whatsapp'], $GLOBALS['razorpay'], $GLOBALS['receipt_service']);

function whatsapp(): WhatsAppService {
    return $GLOBALS['whatsapp'];
}

function razorpay(): RazorpayService {
    return $GLOBALS['razorpay'];
}

function receipt_service(): ReceiptService {
    return $GLOBALS['receipt_service'];
}

function cloudinary(): CloudinaryService {
    return $GLOBALS['cloudinary'];
}

