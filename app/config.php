<?php

return [
    'app_name' => 'LibraryHub',
    'timezone' => 'Asia/Kolkata',
    'debug' => filter_var(getenv('APP_DEBUG') ?: '1', FILTER_VALIDATE_BOOL),
    'base_url' => rtrim(getenv('APP_BASE_URL') ?: '', '/'),
    'db' => (function() {
        $dbUrl = getenv('MYSQL_URL') ?: (getenv('DATABASE_URL') ?: '');
        $dbHost = trim(getenv('DB_HOST') ?: '127.0.0.1');
        $dbPort = trim(getenv('DB_PORT') ?: '3306');
        $dbName = trim(getenv('DB_NAME') ?: 'libraryhub');
        $dbUser = trim(getenv('DB_USER') ?: 'root');
        $dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
        $dbSsl = filter_var(getenv('DB_SSL') ?: '0', FILTER_VALIDATE_BOOL);

        if ($dbUrl !== '') {
            $parsed = parse_url($dbUrl);
            if ($parsed) {
                if (!empty($parsed['host'])) $dbHost = $parsed['host'];
                if (!empty($parsed['port'])) $dbPort = (string)$parsed['port'];
                if (!empty($parsed['user'])) $dbUser = urldecode($parsed['user']);
                if (isset($parsed['pass'])) $dbPass = urldecode($parsed['pass']);
                if (!empty($parsed['path'])) $dbName = ltrim($parsed['path'], '/');
            }
        }

        // Clean up $dbHost if scheme was included (e.g. mysql://host)
        if (preg_match('/^([a-z0-9+.-]+):\/\/(.*)/i', $dbHost, $matches)) {
            $dbHost = $matches[2];
        }

        // If host contains port (e.g. host.aivencloud.com:25348)
        if (str_contains($dbHost, ':')) {
            $parts = explode(':', $dbHost, 2);
            $dbHost = $parts[0];
            if ($dbPort === '3306' || $dbPort === '') {
                $dbPort = $parts[1];
            }
        }

        // Auto-enable SSL for cloud databases (like Aiven)
        if (str_contains($dbHost, 'aivencloud.com') || str_contains($dbHost, 'cleardb') || str_contains($dbHost, 'neon')) {
            $dbSsl = true;
        }

        return [
            'host' => $dbHost,
            'port' => $dbPort,
            'name' => $dbName,
            'user' => $dbUser,
            'pass' => $dbPass,
            'ssl' => $dbSsl,
            'charset' => 'utf8mb4',
        ];
    })(),
    'librarian' => [
        'email' => getenv('LIBRARIAN_EMAIL') ?: 'admin.lms@gmail.com',
        'password' => getenv('LIBRARIAN_PASSWORD') ?: 'admin@#$123',
    ],
    'razorpay' => [
        'key_id' => getenv('RAZORPAY_KEY_ID') ?: '',
        'key_secret' => getenv('RAZORPAY_KEY_SECRET') ?: '',
    ],
    'twilio' => [
        'account_sid' => getenv('TWILIO_ACCOUNT_SID') ?: '',
        'auth_token' => getenv('TWILIO_AUTH_TOKEN') ?: '',
        'whatsapp_number' => getenv('TWILIO_WHATSAPP_NUMBER') ?: 'whatsapp:+14155238886',
    ],
    'cloudinary' => [
        'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME') ?: '',
        'api_key' => getenv('CLOUDINARY_API_KEY') ?: '',
        'api_secret' => getenv('CLOUDINARY_API_SECRET') ?: '',
        'upload_preset' => getenv('CLOUDINARY_UPLOAD_PRESET') ?: '',
    ],
    'business' => [
        'rent_percent' => 0.10,
        'late_fine_percent' => 0.05,
        'loan_period_days' => 14,
        'max_borrowed_books' => 3,
        're_request_hours' => 24,
        'initial_behaviour_score' => 100,
        'score_max' => 100,
        'score_min' => 0,
        'score_returned_on_time' => 2,
        'score_returned_early' => 3,
        'score_returned_late' => -5,
        'score_lost_book' => -25,
        'score_damaged_book' => -20,
        'score_damage_fine_added' => -5,
        'score_cancelled_approved' => -3,
        'score_paid_fine_immediately' => 2,
        'status_good_min' => 80,
        'status_average_min' => 50,
    ],
];
