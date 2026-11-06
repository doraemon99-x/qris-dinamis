<?php
/**
 * REST API Endpoint - QRIS Dinamis
 * Dapat diakses via GET atau POST (JSON / Form Data)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/includes/QrisHelper.php';
require_once __DIR__ . '/includes/db.php';
$config = require __DIR__ . '/config.php';

// Gabungkan dengan pengaturan di SQLite jika ada
try {
    $dbSettings = get_all_settings();
    if (!empty($dbSettings)) {
        if (!empty($dbSettings['qris_base'])) $config['qris_base'] = $dbSettings['qris_base'];
        if (!empty($dbSettings['merchant_name'])) $config['merchant_name'] = $dbSettings['merchant_name'];
        if (!empty($dbSettings['merchant_city'])) $config['merchant_city'] = $dbSettings['merchant_city'];
        if (!empty($dbSettings['nmid'])) $config['nmid'] = $dbSettings['nmid'];
        if (isset($dbSettings['unique_enabled'])) $config['unique_code']['enabled'] = ($dbSettings['unique_enabled'] === '1');
        if (isset($dbSettings['unique_min'])) $config['unique_code']['min'] = (int)$dbSettings['unique_min'];
        if (isset($dbSettings['unique_max'])) $config['unique_code']['max'] = (int)$dbSettings['unique_max'];
        if (isset($dbSettings['expiry_minutes'])) $config['expiry_minutes'] = (int)$dbSettings['expiry_minutes'];
        if (isset($dbSettings['admin_whatsapp'])) $config['admin_whatsapp'] = $dbSettings['admin_whatsapp'];
        if (isset($dbSettings['admin_telegram'])) $config['admin_telegram'] = $dbSettings['admin_telegram'];
        if (isset($dbSettings['telegram_bot_token'])) $config['telegram_bot_token'] = $dbSettings['telegram_bot_token'];
        if (isset($dbSettings['telegram_chat_id'])) $config['telegram_chat_id'] = $dbSettings['telegram_chat_id'];
        if (isset($dbSettings['api_key'])) $config['api_key'] = $dbSettings['api_key'];
    }
} catch (Throwable $e) {}

$input = $_REQUEST;
if ($method === 'POST') {
    $rawBody = file_get_contents('php://input');
    if (!empty($rawBody)) {
        $json = json_decode($rawBody, true);
        if (is_array($json)) {
            $input = array_merge($input, $json);
        }
    }
}

// Cek autentikasi API Key jika dikonfigurasi
if (!empty($config['api_key'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $apiKey = $input['api_key'] ?? '';
    if (str_starts_with($authHeader, 'Bearer ')) {
        $apiKey = substr($authHeader, 7);
    }
    if ($apiKey !== $config['api_key']) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized: Invalid API Key'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$action = $input['action'] ?? 'generate';

if ($action === 'scan_qris') {
    if (!isset($_FILES['qris_image']) || $_FILES['qris_image']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File gambar QRIS tidak valid.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $tmpPath = $_FILES['qris_image']['tmp_name'];
    $targetPath = __DIR__ . '/data/scan_' . time() . '.jpg';
    @move_uploaded_file($tmpPath, $targetPath);

    $pythonPath = 'C:\\Users\\IRFAN TEX\\AppData\\Local\\Programs\\Python\\Python312\\python.exe';
    $escapedPath = addslashes($targetPath);
    $script = 'import cv2; from pyzbar.pyzbar import decode; img = cv2.imread("' . $escapedPath . '"); decoded = decode(img); print(decoded[0].data.decode("utf-8") if decoded else "")';

    $command = '"' . $pythonPath . '" -c "' . $script . '"';
    $output = trim(shell_exec($command));

    if (file_exists($targetPath)) {
        @unlink($targetPath);
    }

    if (!empty($output)) {
        $parsed = QrisHelper::parse($output);
        echo json_encode([
            'success' => true,
            'qris_string' => $output,
            'parsed' => $parsed
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mendeteksi QR Code pada gambar. Pastikan foto QRIS jelas.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

if ($action === 'config') {
    echo json_encode([
        'success' => true,
        'data' => [
            'merchant_name'   => $config['merchant_name'],
            'merchant_city'   => $config['merchant_city'],
            'nmid'            => $config['nmid'],
            'expiry_minutes'  => (int)($config['expiry_minutes'] ?? 15),
            'admin_whatsapp'  => (string)($config['admin_whatsapp'] ?? ''),
            'admin_telegram'  => (string)($config['admin_telegram'] ?? ''),
            'unique_code'     => $config['unique_code'] ?? ['enabled' => true, 'min' => 1, 'max' => 499],
            'has_api_key'     => !empty($config['api_key']),
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'inspect') {
    $qrisToInspect = trim($input['qris'] ?? $input['text'] ?? $config['qris_base']);
    if (empty($qrisToInspect)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Parameter "qris" atau "text" wajib diisi.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $details = QrisHelper::inspect($qrisToInspect);
    echo json_encode([
        'success' => true,
        'data' => $details
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'transactions') {
    $limit = max(1, min(100, (int)($input['limit'] ?? 15)));
    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM transactions ORDER BY id DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $txs = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $txs
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mengambil riwayat: ' . $e->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

if ($action === 'update_status') {
    $orderId = trim((string)($input['order_id'] ?? ''));
    $newStatus = trim((string)($input['status'] ?? 'paid'));

    if (empty($orderId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Parameter "order_id" wajib diisi.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    try {
        $db = get_db();
        $paidAt = ($newStatus === 'paid') ? date('Y-m-d H:i:s') : null;
        $stmt = $db->prepare("UPDATE transactions SET status = :st, paid_at = :paid WHERE order_id = :oid");
        $stmt->execute([
            ':st'   => $newStatus,
            ':paid' => $paidAt,
            ':oid'  => $orderId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Status transaksi berhasil diperbarui.',
            'data' => ['order_id' => $orderId, 'status' => $newStatus]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal memperbarui status: ' . $e->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

if ($action === 'items') {
    try {
        $db = get_db();
        $items = $db->query("SELECT * FROM items ORDER BY id DESC")->fetchAll();
        echo json_encode([
            'success' => true,
            'data' => $items
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mengambil item: ' . $e->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

// GENERATE QRIS
$amount = isset($input['amount']) ? (float)$input['amount'] : 0;
$itemId = isset($input['item_id']) ? (int)$input['item_id'] : 0;
$item = null;

if ($itemId > 0) {
    try {
        $db = get_db();
        $stmtItem = $db->prepare("SELECT * FROM items WHERE id = :id LIMIT 1");
        $stmtItem->execute([':id' => $itemId]);
        $item = $stmtItem->fetch();
        if ($item) {
            if (isset($item['stock']) && (int)$item['stock'] <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Maaf, stok produk "' . $item['name'] . '" sudah habis.'
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                exit;
            }
            if ($amount <= 0) {
                $amount = (float)$item['price'];
            }
        }
    } catch (Throwable $e) {}
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Parameter "amount" atau "item_id" wajib diisi dan harus > 0'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$useUnique = isset($input['unique']) && (in_array($input['unique'], [1, '1', true, 'true', 'yes'], true));
$uniqueCode = 0;
if ($useUnique && !empty($config['unique_code']['enabled'])) {
    $min = $config['unique_code']['min'] ?? 1;
    $max = $config['unique_code']['max'] ?? 499;
    $uniqueCode = rand($min, $max);
}

$feeType = $input['fee_type'] ?? null;
$feeVal = isset($input['fee_val']) ? (float)$input['fee_val'] : 0;
$feeAmount = 0;
if ($feeType === 'fixed' && $feeVal > 0) {
    $feeAmount = $feeVal;
} elseif ($feeType === 'percentage' && $feeVal > 0) {
    $feeAmount = round(($amount * $feeVal) / 100);
} else {
    $feeType = null;
    $feeVal = 0;
}

$totalAmount = (int) round($amount + $uniqueCode + $feeAmount);
$baseQris = !empty($input['base_qris']) ? trim($input['base_qris']) : $config['qris_base'];
$dyn = QrisHelper::toDynamic($baseQris, $totalAmount, $feeType, $feeVal);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseUrl = $protocol . $host . rtrim($scriptDir, '/');

$orderId = trim($input['order_id'] ?? '');
if (empty($orderId)) {
    $orderId = 'QRIS-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}
$note = trim($input['note'] ?? '');
$customer = trim($input['customer'] ?? '');
$customerPhone = trim($input['phone'] ?? $input['customer_phone'] ?? '');
$expiryMinutes = (int)($config['expiry_minutes'] ?? 15);
$now = time();
$expiredAt = $now + ($expiryMinutes * 60);

$shouldSave = !isset($input['save']) || in_array($input['save'], [1, '1', true, 'true', 'yes'], true);
$txId = null;
if ($shouldSave) {
    try {
        $db = get_db();
        $ins = $db->prepare("
            INSERT INTO transactions (
                order_id, customer_name, customer_phone, base_amount, unique_code, fee_amount,
                total_amount, qris_string, status, note, expired_at, created_at
            ) VALUES (
                :oid, :cname, :cphone, :base, :uniq, :fee,
                :total, :qstr, 'pending', :note, :exp, :created
            )
        ");
        $ins->execute([
            ':oid'     => $orderId,
            ':cname'   => $customer ?: null,
            ':cphone'  => $customerPhone ?: null,
            ':base'    => (int)$amount,
            ':uniq'    => (int)$uniqueCode,
            ':fee'     => (int)$feeAmount,
            ':total'   => (int)$totalAmount,
            ':qstr'    => $dyn['qris_string'],
            ':note'    => $note ?: null,
            ':exp'     => date('Y-m-d H:i:s', $expiredAt),
            ':created' => date('Y-m-d H:i:s', $now),
        ]);
        $txId = (int)$db->lastInsertId();

        // Kurangi stok produk jika dipesan via item_id
        if ($itemId > 0) {
            $db->prepare("UPDATE items SET stock = MAX(0, stock - 1) WHERE id = :id AND stock > 0")->execute([':id' => $itemId]);
        }
    } catch (Throwable $e) {}
}

$qrImageUrl = $baseUrl . '/qr.php?text=' . urlencode($dyn['qris_string']);
$payQuery = ['amount' => $totalAmount, 'order_id' => $orderId];
if ($note) $payQuery['note'] = $note;
if ($customer) $payQuery['customer'] = $customer;
$payUrl = $baseUrl . '/pay.php?' . http_build_query($payQuery);

echo json_encode([
    'success' => true,
    'data' => [
        'id'              => $txId,
        'order_id'        => $orderId,
        'merchant_name'   => $dyn['merchant_name'],
        'merchant_city'   => $dyn['merchant_city'],
        'nmid'            => $dyn['nmid'],
        'base_amount'     => (int)$amount,
        'unique_code'     => $uniqueCode,
        'fee_amount'      => (int)$feeAmount,
        'total_amount'    => $totalAmount,
        'formatted_total' => 'Rp ' . number_format($totalAmount, 0, ',', '.'),
        'note'            => $note,
        'customer'        => $customer,
        'qris_string'     => $dyn['qris_string'],
        'qr_image_url'    => $qrImageUrl,
        'pay_url'         => $payUrl,
        'expired_at'      => date('Y-m-d H:i:s', $expiredAt),
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
