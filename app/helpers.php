<?php

function app_config(?string $key = null, $default = null)
{
    $config = $GLOBALS['config'] ?? [];
    if ($key === null) {
        return $config;
    }

    $segments = explode('.', $key);
    $value = $config;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function db(): LibraryDatabase
{
    return $GLOBALS['database'];
}

function library(): LibraryService
{
    return $GLOBALS['library'];
}

function esc(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
    return '₹ ' . number_format((float) $value, 2);
}

function format_date(?string $value): string
{
    if (!$value) {
        return 'N/A';
    }
    return date('d M Y', strtotime($value));
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return 'N/A';
    }
    return date('d M Y H:i', strtotime($value));
}

function app_base_path(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $base = rtrim(str_replace('/index.php', '', dirname($script)), '/');
    return $base === '/' ? '' : $base;
}

function route_url(string $route, array $params = []): string
{
    $query = array_merge(['route' => $route], $params);
    $basePath = app_base_path();
    $prefix = $basePath === '' ? '' : $basePath;

    return $prefix . '/index.php?' . http_build_query($query);
}

function asset_url(string $path): string
{
    $basePath = app_base_path();
    $prefix = $basePath === '' ? '' : $basePath;

    return $prefix . '/' . ltrim($path, '/');
}

function current_path(): string
{
    $route = $_GET['route'] ?? '';
    if ($route !== '') {
        return trim($route, '/');
    }

    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $uriPath = str_replace('\\', '/', $uriPath);
    $basePath = app_base_path();
    if ($basePath !== '' && str_starts_with($uriPath, $basePath)) {
        $uriPath = substr($uriPath, strlen($basePath));
    }

    $uriPath = trim($uriPath, '/');
    if ($uriPath === '' || $uriPath === 'index.php') {
        return 'home';
    }

    return $uriPath;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function flash(string $type, ?string $message = null): ?array
{
    if ($message === null) {
        $messages = $_SESSION['_flash'][$type] ?? [];
        unset($_SESSION['_flash'][$type]);
        return $messages;
    }

    $_SESSION['_flash'][$type][] = $message;
    return null;
}

function flash_messages(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return [
        'info' => $messages['info'] ?? [],
        'success' => $messages['success'] ?? [],
        'warning' => $messages['warning'] ?? [],
        'danger' => $messages['danger'] ?? [],
    ];
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . esc(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!is_post()) {
        return;
    }

    $token = $_POST['_csrf'] ?? '';
    if (empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], (string) $token)) {
        flash('danger', 'Security session expired or token mismatch. Please try again.');
        $fallback = route_url('home');
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        header('Location: ' . $referer);
        exit;
    }
}

function redirect_to(string $route, array $params = []): never
{
    header('Location: ' . route_url($route, $params));
    exit;
}

function current_student(): ?array
{
    return $_SESSION['student'] ?? null;
}

function is_student_logged_in(): bool
{
    return !empty($_SESSION['student']['id']);
}

function is_librarian_logged_in(): bool
{
    return !empty($_SESSION['librarian_logged_in']);
}

function require_student(): void
{
    if (!is_student_logged_in() || is_librarian_logged_in()) {
        flash('danger', 'Please log in as a student to continue.');
        redirect_to('login');
    }
}

function require_librarian(): void
{
    if (!is_librarian_logged_in()) {
        flash('danger', 'Librarian access required.');
        redirect_to('login');
    }
}

function clamp_int(int $value, int $min, int $max): int
{
    return max($min, min($max, $value));
}

function clamp_score(int $score): int
{
    return clamp_int($score, app_config('business.score_min', 0), app_config('business.score_max', 100));
}

function account_status_for_score(int $score): string
{
    if ($score >= app_config('business.status_good_min', 80)) {
        return 'Good';
    }

    if ($score >= app_config('business.status_average_min', 50)) {
        return 'Average';
    }

    return 'Bad';
}

function rent_for_price($price): float
{
    return round(((float) $price) * app_config('business.rent_percent', 0.10), 2);
}

function late_fine_for_price($price, int $overdueDays): float
{
    if ($overdueDays <= 0) {
        return 0.0;
    }

    $rent = rent_for_price($price);
    return round($rent * app_config('business.late_fine_percent', 0.05) * $overdueDays, 2);
}

function lost_amount_for_price($price): float
{
    return round((float) $price, 2);
}

function days_between(string $from, string $to): int
{
    return (int) floor((strtotime($to) - strtotime($from)) / 86400);
}

function status_badge(string $status): string
{
    $map = [
        'Good' => 'success',
        'Average' => 'warning',
        'Bad' => 'danger',
        'Pending' => 'secondary',
        'Approved' => 'success',
        'Rejected' => 'danger',
        'Hold' => 'warning',
        'Cancelled' => 'dark',
        'Issued' => 'primary',
        'Returned' => 'success',
        'Lost' => 'danger',
        'Unpaid' => 'warning',
        'Paid' => 'success',
        'Completed' => 'success',
        'Failed' => 'danger',
        'online' => 'primary',
        'offline' => 'secondary',
        'cash' => 'secondary',
    ];

    $class = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $class . '">' . esc($status) . '</span>';
}

function nav_active(string $route, string $current): string
{
    return str_starts_with($current, $route) ? 'active' : '';
}

function render(string $title, string $body, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $appTitle = app_config('app_name', 'LibraryHub');
    $messages = flash_messages();

    ob_start();
    include __DIR__ . '/../templates/layout.php';
    echo ob_get_clean();
}


