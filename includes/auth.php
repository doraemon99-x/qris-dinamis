<?php
/**
 * Auth & Session Helper
 * Mengelola sesi admin, verifikasi login, proteksi CSRF, dan flash message.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Konfigurasi Cookie Sesi Aman
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('QRIS_ADMIN_SESS');
    session_start();
}

/**
 * Mengecek apakah admin sedang login
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Memastikan user sudah login, jika belum redirect ke login.php
 */
function require_login(): void
{
    if (!is_logged_in()) {
        $loginUrl = 'login.php';
        header("Location: {$loginUrl}");
        exit;
    }
}

/**
 * Verifikasi proses login admin
 */
function login_user(string $username, string $password): bool
{
    $savedUser = get_setting('admin_username', 'admin');
    $savedHash = get_setting('admin_password_hash', '');

    if ($username === $savedUser && password_verify($password, (string)$savedHash)) {
        // Regenerasi ID sesi untuk mencegah Session Fixation
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['last_activity'] = time();
        return true;
    }

    return false;
}

/**
 * Logout admin dan bersihkan sesi
 */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Dapatkan atau buat CSRF token untuk form admin
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifikasi validitas CSRF token
 */
function verify_csrf(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set pesan notifikasi flash (success, error, warning, info)
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Ambil dan hapus pesan notifikasi flash
 */
function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
