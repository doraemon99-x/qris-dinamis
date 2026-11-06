<?php
/**
 * QR Code Image Generator Endpoint
 * Menghasilkan gambar PNG QR Code langsung dari string atau nominal dinamis
 * Contoh pemakaian:
 *   qr.php?text=000201010212...
 *   qr.php?amount=50000
 *   qr.php?amount=50000&unique=1
 */

error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/includes/QrisHelper.php';
require_once __DIR__ . '/includes/qrcode.php';

$config = require __DIR__ . '/config.php';

// Ambil parameter
$text = isset($_GET['text']) ? trim($_GET['text']) : '';
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;
$size = isset($_GET['size']) ? max(3, min(16, (int)$_GET['size'])) : 8;
$margin = isset($_GET['margin']) ? max(1, min(10, (int)$_GET['margin'])) : 4;
$download = isset($_GET['download']) && $_GET['download'] === '1';

// Jika yang dikirim adalah amount, ubah QRIS statis jadi dinamis terlebih dahulu
if (empty($text) && $amount > 0) {
    $feeType = $_GET['fee_type'] ?? null;
    $feeValue = isset($_GET['fee_val']) ? (float)$_GET['fee_val'] : 0;

    // Tambah kode unik jika diminta
    if (isset($_GET['unique']) && $_GET['unique'] === '1' && !empty($config['unique_code']['enabled'])) {
        $min = $config['unique_code']['min'] ?? 1;
        $max = $config['unique_code']['max'] ?? 499;
        $amount += rand($min, $max);
    }

    $dyn = QrisHelper::toDynamic($config['qris_base'], $amount, $feeType, $feeValue);
    $text = $dyn['qris_string'];
}

if (empty($text)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Error: parameter "text" atau "amount" wajib diisi.';
    exit;
}

try {
    // Generate QR Code menggunakan pure PHP QRCode library
    $qr = QRCode::getMinimumQRCode($text, QR_ERROR_CORRECT_LEVEL_M);
    $im = $qr->createImage($size, $margin);

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400'); // Cache 1 hari

    if ($download) {
        $filename = 'QRIS_AXLINESID_' . ($amount > 0 ? (int)$amount : time()) . '.png';
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    }

    imagepng($im);
    imagedestroy($im);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Error generating QR Code: ' . $e->getMessage();
    exit;
}
