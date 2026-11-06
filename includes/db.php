<?php
/**
 * Database Helper - SQLite PDO
 * Mengelola koneksi basis data lokal, auto-migrasi skema, dan helper pengaturan.
 */

declare(strict_types=1);

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dbDir = dirname(__DIR__) . '/data';
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $dbPath = $dbDir . '/database.sqlite';
        $isNew = !file_exists($dbPath);

        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ]);

        // Aktifkan WAL (Write-Ahead Logging) untuk performa & konkurensi optimal
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');

        // Inisialisasi Skema Tabel jika baru / belum ada
        init_db_schema($pdo);
    }

    return $pdo;
}

/**
 * Membuat tabel transaksi dan pengaturan secara otomatis
 */
function init_db_schema(PDO $pdo): void
{
    // Tabel Transaksi
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id TEXT UNIQUE NOT NULL,
            customer_name TEXT,
            customer_phone TEXT,
            base_amount INTEGER NOT NULL,
            unique_code INTEGER DEFAULT 0,
            fee_amount INTEGER DEFAULT 0,
            total_amount INTEGER NOT NULL,
            qris_string TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            note TEXT,
            paid_at DATETIME,
            expired_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_order_id ON transactions(order_id);
        CREATE INDEX IF NOT EXISTS idx_status ON transactions(status);
        CREATE INDEX IF NOT EXISTS idx_created_at ON transactions(created_at);

        CREATE TABLE IF NOT EXISTS items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            price INTEGER NOT NULL,
            stock INTEGER DEFAULT 999,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Pastikan kolom stock ada pada tabel items (migrasi otomatis)
    try {
        $pdo->exec("ALTER TABLE items ADD COLUMN stock INTEGER DEFAULT 999;");
    } catch (Throwable $e) {}

    // Seed default item jika tabel items masih kosong
    $stmtItem = $pdo->query("SELECT COUNT(*) FROM items");
    if ((int)$stmtItem->fetchColumn() === 0) {
        $insItem = $pdo->prepare("INSERT INTO items (name, price, description) VALUES (:name, :price, :desc)");
        $insItem->execute([
            ':name'  => 'ButterScout',
            ':price' => 28000,
            ':desc'  => 'Menu / Produk pilihan'
        ]);
    }

    // Tabel Pengaturan Sistem (Key-Value)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        );
    ");

    // Inisialisasi nilai awal settings dari config.php jika tabel settings masih kosong
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    if ((int)$stmt->fetchColumn() === 0) {
        $configFile = dirname(__DIR__) . '/config.php';
        $fallback = file_exists($configFile) ? (require $configFile) : [];

        $defaults = [
            'qris_base'           => $fallback['qris_base'] ?? '',
            'merchant_name'       => $fallback['merchant_name'] ?? 'AXLINESID Shop',
            'merchant_city'       => $fallback['merchant_city'] ?? 'Kab. Rokan Hili',
            'nmid'                => $fallback['nmid'] ?? '',
            'unique_enabled'      => !empty($fallback['unique_code']['enabled']) ? '1' : '1',
            'unique_min'          => (string)($fallback['unique_code']['min'] ?? 1),
            'unique_max'          => (string)($fallback['unique_code']['max'] ?? 499),
            'expiry_minutes'      => (string)($fallback['expiry_minutes'] ?? 15),
            'admin_whatsapp'      => (string)($fallback['admin_whatsapp'] ?? '6281234567890'),
            'admin_telegram'      => (string)($fallback['admin_telegram'] ?? ''),
            'telegram_bot_token'  => (string)($fallback['telegram_bot_token'] ?? ''),
            'telegram_chat_id'    => (string)($fallback['telegram_chat_id'] ?? ''),
            'api_key'             => (string)($fallback['api_key'] ?? ''),
            'admin_username'      => 'admin',
            'admin_password_hash' => password_hash('admin123', PASSWORD_BCRYPT), // Default awal
        ];

        $ins = $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :val)");
        foreach ($defaults as $k => $v) {
            $ins->execute([':key' => $k, ':val' => (string)$v]);
        }
    }
}

/**
 * Mengambil satu nilai pengaturan
 */
function get_setting(string $key, mixed $default = null): mixed
{
    $db = get_db();
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = :k LIMIT 1");
    $stmt->execute([':k' => $key]);
    $val = $stmt->fetchColumn();

    return $val !== false ? $val : $default;
}

/**
 * Menyimpan / memperbarui satu nilai pengaturan
 */
function set_setting(string $key, mixed $value): bool
{
    $db = get_db();
    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (:k, :v)");
    return $stmt->execute([':k' => $key, ':v' => (string)$value]);
}

/**
 * Mengambil seluruh pengaturan dalam bentuk associative array
 */
function get_all_settings(): array
{
    $db = get_db();
    $stmt = $db->query("SELECT key, value FROM settings");
    $all = [];
    while ($row = $stmt->fetch()) {
        $all[$row['key']] = $row['value'];
    }
    return $all;
}
