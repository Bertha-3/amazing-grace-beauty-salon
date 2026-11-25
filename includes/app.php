<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function is_hosted_deployment(): bool
{
    return getenv('RENDER') === 'true'
        || (getenv('RAILWAY_PROJECT_ID') !== false && getenv('RAILWAY_PROJECT_ID') !== '');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $renderUrl = getenv('RENDER_EXTERNAL_URL');
    $renderHttps = getenv('RENDER') === 'true'
        && $renderUrl !== false
        && strtolower((string)parse_url($renderUrl, PHP_URL_SCHEME)) === 'https';
    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 2)[0]));
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $renderHttps || $forwardedProto === 'https',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $sslCa = getenv('SALON_DB_SSL_CA');
        if ($sslCa !== false && $sslCa !== '') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money($amount): string
{
    return APP_CURRENCY . number_format((float)$amount, 2);
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page, and try again.');
    }
}

function customer(): ?array
{
    return $_SESSION['customer'] ?? null;
}

function admin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function require_customer(): void
{
    if (!customer()) {
        flash('error', 'Please sign in to continue.');
        redirect('index.php?page=login');
    }
}

function require_admin(): void
{
    if (!admin()) {
        flash('error', 'Administrator sign-in is required.');
        redirect('index.php?page=admin-login');
    }
}

function setting(string $key, string $default = ''): string
{
    static $settings;
    if ($settings === null) {
        $settings = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings[$key] ?? $default;
}

function cart_count(): int
{
    return array_sum(array_map('intval', $_SESSION['cart'] ?? []));
}

function cart_items(): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        return [];
    }
    $ids = array_values(array_filter(array_map('intval', array_keys($cart))));
    if (!$ids) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT id, name, price, stock, image FROM products WHERE is_active = 1 AND id IN ($marks)");
    $stmt->execute($ids);
    $items = [];
    foreach ($stmt->fetchAll() as $product) {
        $quantity = min((int)$cart[$product['id']], (int)$product['stock']);
        if ($quantity > 0) {
            $product['quantity'] = $quantity;
            $product['line_total'] = $quantity * (float)$product['price'];
            $items[] = $product;
        }
    }
    return $items;
}

function cart_total(array $items): float
{
    return array_sum(array_column($items, 'line_total'));
}

function image_url(?string $image, string $fallback): string
{
    if ($image) {
        $filename = basename($image);
        $directory = strpos($filename, 'seed-') === 0 ? 'assets/images/' : 'uploads/';
        return $directory . rawurlencode($filename);
    }
    return $fallback;
}

function upload_image(string $field, ?string $current = null): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return $current;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Choose an image smaller than 5 MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Images must be JPG, PNG, or WebP.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../uploads/' . $name)) {
        throw new RuntimeException('The image could not be saved. Check the uploads folder permissions.');
    }
    return $name;
}

function base_url(string $path = ''): string
{
    if (APP_BASE_URL !== '') {
        return APP_BASE_URL . '/' . ltrim($path, '/');
    }
    $renderUrl = getenv('RENDER_EXTERNAL_URL');
    if ($renderUrl !== false && $renderUrl !== '') {
        return rtrim($renderUrl, '/') . '/' . ltrim($path, '/');
    }
    $railwayDomain = getenv('RAILWAY_PUBLIC_DOMAIN');
    if ($railwayDomain !== false && $railwayDomain !== '') {
        return 'https://' . rtrim($railwayDomain, '/') . '/' . ltrim($path, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/\A[a-z0-9.\-\[\]:]+\z/i', $host)) {
        $host = 'localhost';
    }
    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $directory = dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $segments = array_filter(explode('/', trim($directory, '/')), 'strlen');
    $basePath = $segments ? '/' . implode('/', array_map('rawurlencode', $segments)) : '';
    return $scheme . '://' . $host . $basePath . '/' . ltrim($path, '/');
}
