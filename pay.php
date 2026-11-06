<?php
/**
 * Halaman Pembayaran Pelanggan (Customer Checkout Page)
 * QRIS Dinamis Standar Bank Indonesia
 *
 * Mendukung parameter:
 *   - amount   : nominal tagihan (contoh: 25000)
 *   - note     : nomor invoice / catatan transaksi (contoh: INV-202610-001)
 *   - qris     : string QRIS siap pakai (opsional jika sudah di-generate sebelumnya)
 *   - unique   : tambahkan kode unik jika belum (1 = aktif)
 *   - fee_type : 'fixed' atau 'percentage'
 *   - fee_val  : nominal / persentase fee
 */

error_reporting(0);
ini_set('display_errors', '0');

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
} catch (Throwable $e) {
    // Fallback config.php
}

// Ambil input
$rawQris = isset($_GET['qris']) ? trim($_GET['qris']) : '';
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;
$note = isset($_GET['note']) ? trim($_GET['note']) : (isset($_GET['order_id']) ? trim($_GET['order_id']) : '');
$customer = isset($_GET['customer']) ? trim($_GET['customer']) : '';

$uniqueCode = 0;
$feeAmount = 0;

if (!empty($rawQris)) {
    // Jika string QRIS langsung diberikan
    $tags = QrisHelper::parse($rawQris);
    $merchantName = $tags['59'] ?? $config['merchant_name'];
    $merchantCity = $tags['60'] ?? $config['merchant_city'];
    $tag54 = $tags['54'] ?? '0';
    $totalAmount = (int)$tag54;
    $qrisString = $rawQris;
    $nmid = '';
    if (isset($tags['51'])) {
        $sub51 = QrisHelper::parse($tags['51']);
        $nmid = $sub51['02'] ?? $config['nmid'];
    }
} else {
    // Generate QRIS dinamis dari amount
    if ($amount <= 0) {
        $amount = 10000; // Default demo jika dibuka tanpa parameter
    }

    $feeType = $_GET['fee_type'] ?? null;
    $feeVal = isset($_GET['fee_val']) ? (float)$_GET['fee_val'] : 0;

    if ($feeType === 'fixed' && $feeVal > 0) {
        $feeAmount = (int)$feeVal;
    } elseif ($feeType === 'percentage' && $feeVal > 0) {
        $feeAmount = (int)round(($amount * $feeVal) / 100);
    } else {
        $feeType = null;
    }

    if (isset($_GET['unique']) && $_GET['unique'] === '1' && !empty($config['unique_code']['enabled'])) {
        $min = $config['unique_code']['min'] ?? 1;
        $max = $config['unique_code']['max'] ?? 499;
        $uniqueCode = rand($min, $max);
    }

    $totalAmount = (int)round($amount + $uniqueCode + $feeAmount);
    $dyn = QrisHelper::toDynamic($config['qris_base'], $totalAmount, $feeType, $feeVal);

    $merchantName = $dyn['merchant_name'];
    $merchantCity = $dyn['merchant_city'];
    $nmid = $dyn['nmid'];
    $qrisString = $dyn['qris_string'];
}

$expiryMinutes = (int)($config['expiry_minutes'] ?? 15);
$adminWa = preg_replace('/[^0-9]/', '', (string)($config['admin_whatsapp'] ?? ''));
$adminTg = trim((string)($config['admin_telegram'] ?? ''));

// Format pesan konfirmasi WA / Telegram
$msgText = "Halo Admin " . $merchantName . ",\n\n"
    . "Saya sudah melakukan pembayaran via QRIS:\n"
    . "• Total Bayar: Rp " . number_format($totalAmount, 0, ',', '.') . "\n"
    . ($note ? "• No. Pesanan / Catatan: " . $note . "\n" : "")
    . ($customer ? "• Atas Nama: " . $customer . "\n" : "")
    . "• Waktu: " . date('d M Y, H:i') . " WIB\n\n"
    . "Mohon untuk diverifikasi dan diproses. Terima kasih!";

$waUrl = "https://wa.me/" . $adminWa . "?text=" . urlencode($msgText);
$tgUrl = $adminTg ? "https://t.me/" . ltrim($adminTg, '@') . "?text=" . urlencode($msgText) : "";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pembayaran QRIS - <?= htmlspecialchars($merchantName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="qrcode.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    },
                    colors: {
                        qris: {
                            red: '#ED1C24',
                            dark: '#1e293b',
                            gray: '#64748b'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
            min-height: 100vh;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
        }
        .qris-frame {
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            position: relative;
        }
        .pulse-subtle {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body class="font-sans antialiased text-slate-800 p-3 sm:p-6 flex items-center justify-center">

    <div class="w-full max-w-md mx-auto my-auto">
        <!-- Main Card -->
        <div class="glass-card rounded-3xl overflow-hidden shadow-2xl transition-all duration-300">

            <!-- Header QRIS Resmi -->
            <div class="bg-gradient-to-r from-red-600 via-rose-600 to-red-700 text-white p-5 text-center relative overflow-hidden">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-white/10 rounded-full blur-xl pointer-events-none"></div>
                <div class="absolute -left-6 -bottom-6 w-24 h-24 bg-black/10 rounded-full blur-xl pointer-events-none"></div>

                <div class="flex items-center justify-between mb-3 px-1">
                    <!-- QRIS Badge -->
                    <div class="flex items-center space-x-1.5 bg-white px-2.5 py-1 rounded-lg shadow-sm">
                        <span class="text-red-600 font-extrabold tracking-wider text-sm">QRIS</span>
                    </div>
                    <!-- GPN Logo Text -->
                    <div class="flex items-center space-x-1 bg-white/15 backdrop-blur px-2.5 py-1 rounded-lg border border-white/20">
                        <span class="text-[11px] font-bold tracking-widest uppercase">GPN</span>
                        <div class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></div>
                    </div>
                </div>

                <h1 class="text-lg sm:text-xl font-bold tracking-tight text-white line-clamp-1">
                    <?= htmlspecialchars($merchantName) ?>
                </h1>
                <div class="flex items-center justify-center space-x-2 text-red-100 text-xs mt-1">
                    <span>NMID: <?= htmlspecialchars($nmid ?: $config['nmid']) ?></span>
                    <span>•</span>
                    <span><?= htmlspecialchars($merchantCity ?: $config['merchant_city']) ?></span>
                </div>
            </div>

            <!-- Countdown Timer & Alert Bar -->
            <div id="timerContainer" class="bg-amber-50 border-b border-amber-200/80 px-4 py-2.5 flex items-center justify-between text-xs font-medium text-amber-800">
                <div class="flex items-center space-x-1.5">
                    <svg class="w-4 h-4 text-amber-600 animate-spin" style="animation-duration: 3s;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Selesaikan dalam</span>
                </div>
                <div id="countdown" class="font-mono font-bold text-amber-900 bg-amber-200/70 px-2 py-0.5 rounded text-sm">
                    15:00
                </div>
            </div>

            <div class="p-5 sm:p-6 space-y-5">

                <!-- Nominal Card -->
                <div class="bg-slate-50 border border-slate-200/90 rounded-2xl p-4 text-center">
                    <p class="text-xs uppercase tracking-wider font-semibold text-slate-500 mb-1">Total Tagihan</p>
                    <div class="flex items-baseline justify-center space-x-1">
                        <span class="text-sm font-bold text-slate-700">Rp</span>
                        <span class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-mono">
                            <?= number_format($totalAmount, 0, ',', '.') ?>
                        </span>
                    </div>

                    <?php if ($uniqueCode > 0 || $note): ?>
                        <div class="mt-3 pt-3 border-t border-dashed border-slate-200 text-xs text-slate-600 space-y-1">
                            <?php if ($note): ?>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Catatan/No. Order:</span>
                                    <span class="font-semibold text-slate-800 font-mono"><?= htmlspecialchars($note) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($uniqueCode > 0): ?>
                                <div class="flex justify-between items-center bg-amber-50 px-2 py-1 rounded text-amber-900">
                                    <span class="text-[11px]">Termasuk Kode Unik:</span>
                                    <span class="font-mono font-bold text-red-600">+ Rp <?= number_format($uniqueCode, 0, ',', '.') ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($uniqueCode > 0): ?>
                        <div class="mt-2 text-[11px] text-amber-700 font-medium bg-amber-100/60 p-2 rounded-xl flex items-start space-x-1.5 text-left">
                            <svg class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Mohon bayar <b>TEPAT</b> sesuai nominal di atas agar sistem mendeteksi pembayaran Anda.</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- QR Code Display Box -->
                <div class="relative flex flex-col items-center">
                    <div id="expiredOverlay" class="hidden absolute inset-0 bg-white/95 backdrop-blur-sm z-20 rounded-2xl flex flex-col items-center justify-center p-6 text-center">
                        <div class="w-14 h-14 bg-red-100 text-red-600 rounded-full flex items-center justify-center mb-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-800">QRIS Telah Kadaluarsa</h3>
                        <p class="text-xs text-slate-500 mt-1 mb-4">Sesi transaksi 15 menit telah habis. Silakan buat QRIS baru.</p>
                        <button onclick="window.location.reload()" class="px-4 py-2 bg-red-600 text-white rounded-xl text-xs font-semibold hover:bg-red-700 transition">
                            Muat Ulang Halaman
                        </button>
                    </div>

                    <div class="p-4 bg-white rounded-2xl shadow-inner border-2 border-slate-100 flex items-center justify-center relative">
                        <!-- QR Target Container -->
                        <div id="qrcode" class="w-[230px] h-[230px] flex items-center justify-center"></div>
                    </div>

                    <!-- E-wallet Compatibility Logos List -->
                    <div class="mt-3 text-center">
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Menerima Pembayaran Dari</p>
                        <div class="flex flex-wrap items-center justify-center gap-2 text-[10px] font-bold text-slate-600">
                            <span class="px-2 py-0.5 bg-slate-100 rounded border border-slate-200">BCA</span>
                            <span class="px-2 py-0.5 bg-slate-100 rounded border border-slate-200">Mandiri</span>
                            <span class="px-2 py-0.5 bg-slate-100 rounded border border-slate-200">BRI</span>
                            <span class="px-2 py-0.5 bg-slate-100 rounded border border-slate-200">BNI</span>
                            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded border border-blue-200">DANA</span>
                            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded border border-emerald-200">GoPay</span>
                            <span class="px-2 py-0.5 bg-purple-50 text-purple-700 rounded border border-purple-200">OVO</span>
                            <span class="px-2 py-0.5 bg-orange-50 text-orange-700 rounded border border-orange-200">ShopeePay</span>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-2.5 pt-1">
                    <button id="btnDownload" onclick="downloadQrImage()" class="w-full flex items-center justify-center space-x-2 py-3 px-4 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-semibold text-xs transition shadow-md active:scale-[0.98]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        <span>Simpan Gambar QRIS</span>
                    </button>

                    <?php if ($adminWa): ?>
                        <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" class="w-full flex items-center justify-center space-x-2 py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold text-xs transition shadow-md active:scale-[0.98]">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                            </svg>
                            <span>Konfirmasi via WhatsApp</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($adminTg): ?>
                        <a href="<?= htmlspecialchars($tgUrl) ?>" target="_blank" onclick="copyTgMessage(event)" class="w-full flex items-center justify-center space-x-2 py-3 px-4 bg-sky-500 hover:bg-sky-600 text-white rounded-xl font-semibold text-xs transition shadow-md active:scale-[0.98]">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.12.03-1.99 1.27-5.62 3.73-.53.36-1.01.54-1.44.53-.47-.02-1.37-.26-2.03-.48-.82-.27-1.47-.42-1.42-.88.03-.25.38-.51 1.08-.78 4.23-1.84 7.05-3.05 8.45-3.64 4.02-1.69 4.85-1.98 5.39-1.99.12 0 .39.03.56.17.14.12.18.28.2.45-.02.07-.02.13-.04.2z"/>
                            </svg>
                            <span>Konfirmasi via Telegram</span>
                        </a>
                        <script>
                            const tgMsgText = <?= json_encode($msgText) ?>;
                            function copyTgMessage(e) {
                                navigator.clipboard.writeText(tgMsgText).catch(err => {});
                            }
                        </script>
                    <?php endif; ?>

                    <button onclick="copyQrisString()" class="w-full flex items-center justify-center space-x-1.5 py-2.5 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium text-xs transition">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                        <span id="copyBtnText">Salin Teks String QRIS</span>
                    </button>
                </div>

                <!-- Cara Pembayaran Accordion -->
                <div class="pt-2 border-t border-slate-100">
                    <button onclick="toggleTutorial()" class="w-full flex items-center justify-between text-xs font-semibold text-slate-600 hover:text-slate-900 py-1">
                        <span>Petunjuk Cara Pembayaran</span>
                        <svg id="tutorialChevron" class="w-4 h-4 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="tutorialContent" class="hidden mt-3 space-y-2 text-xs text-slate-600 bg-slate-50 p-3.5 rounded-xl border border-slate-200/70">
                        <div class="flex items-start space-x-2">
                            <span class="w-4 h-4 rounded-full bg-red-100 text-red-600 font-bold flex items-center justify-center text-[10px] flex-shrink-0 mt-0.5">1</span>
                            <span>Buka aplikasi m-Banking (BCA, Mandiri, BRI, BNI) atau E-Wallet (DANA, GoPay, OVO, ShopeePay).</span>
                        </div>
                        <div class="flex items-start space-x-2">
                            <span class="w-4 h-4 rounded-full bg-red-100 text-red-600 font-bold flex items-center justify-center text-[10px] flex-shrink-0 mt-0.5">2</span>
                            <span>Pilih menu <b>Bayar / Scan QRIS</b>.</span>
                        </div>
                        <div class="flex items-start space-x-2">
                            <span class="w-4 h-4 rounded-full bg-red-100 text-red-600 font-bold flex items-center justify-center text-[10px] flex-shrink-0 mt-0.5">3</span>
                            <span>Arahkan kamera ke QR di layar ini, atau upload gambar QR jika Anda menyimpannya ke galeri.</span>
                        </div>
                        <div class="flex items-start space-x-2">
                            <span class="w-4 h-4 rounded-full bg-red-100 text-red-600 font-bold flex items-center justify-center text-[10px] flex-shrink-0 mt-0.5">4</span>
                            <span>Nominal <b>Rp <?= number_format($totalAmount, 0, ',', '.') ?></b> akan otomatis terisi. Periksa nama merchant: <b><?= htmlspecialchars($merchantName) ?></b>.</span>
                        </div>
                        <div class="flex items-start space-x-2">
                            <span class="w-4 h-4 rounded-full bg-red-100 text-red-600 font-bold flex items-center justify-center text-[10px] flex-shrink-0 mt-0.5">5</span>
                            <span>Masukkan PIN Anda dan selesaikan pembayaran.</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div class="bg-slate-100 border-t border-slate-200/80 px-5 py-3 text-center text-[11px] text-slate-500 flex items-center justify-between">
                <span>© <?= date('Y') ?> <?= htmlspecialchars($merchantName) ?></span>
                <span class="font-mono text-[10px] bg-slate-200/80 px-1.5 py-0.5 rounded text-slate-600">ID: <?= htmlspecialchars($nmid ?: 'QRIS-BI') ?></span>
            </div>

        </div>
    </div>

    <!-- Script QR Code & Countdown Timer -->
    <script>
        const qrisData = <?= json_encode($qrisString) ?>;
        const totalAmount = <?= json_encode($totalAmount) ?>;
        const expiryMinutes = <?= json_encode($expiryMinutes) ?>;

        // 1. Render QR Code
        const qrContainer = document.getElementById('qrcode');
        let qrObj = null;

        try {
            qrObj = new QRCode(qrContainer, {
                text: qrisData,
                width: 230,
                height: 230,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        } catch (e) {
            console.error('JS QRCode error, fallback ke qr.php:', e);
            qrContainer.innerHTML = '<img src="qr.php?text=' + encodeURIComponent(qrisData) + '" width="230" height="230" class="rounded-lg shadow-sm" alt="QRIS Dinamis" />';
        }

        // 2. Countdown Timer
        const storageKey = 'qris_expiry_' + totalAmount + '_' + qrisData.slice(-10);
        let expiryTimestamp = localStorage.getItem(storageKey);

        if (!expiryTimestamp) {
            expiryTimestamp = Date.now() + (expiryMinutes * 60 * 1000);
            localStorage.setItem(storageKey, expiryTimestamp);
        } else {
            expiryTimestamp = parseInt(expiryTimestamp, 10);
        }

        const countdownEl = document.getElementById('countdown');
        const expiredOverlay = document.getElementById('expiredOverlay');

        function updateTimer() {
            const now = Date.now();
            const diff = expiryTimestamp - now;

            if (diff <= 0) {
                countdownEl.innerText = '00:00';
                expiredOverlay.classList.remove('hidden');
                localStorage.removeItem(storageKey);
                return;
            }

            const minutes = Math.floor(diff / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);

            countdownEl.innerText =
                (minutes < 10 ? '0' : '') + minutes + ':' +
                (seconds < 10 ? '0' : '') + seconds;

            setTimeout(updateTimer, 1000);
        }
        updateTimer();

        // 3. Download QR Image
        function downloadQrImage() {
            // Coba ambil dari canvas / img di dalam qrcode container
            const img = qrContainer.querySelector('img');
            const canvas = qrContainer.querySelector('canvas');
            let dataUrl = '';

            if (canvas) {
                dataUrl = canvas.toDataURL('image/png');
            } else if (img && img.src && img.src.startsWith('data:')) {
                dataUrl = img.src;
            } else {
                // Fallback direct download via qr.php
                window.location.href = 'qr.php?text=' + encodeURIComponent(qrisData) + '&download=1&amount=' + totalAmount;
                return;
            }

            const a = document.createElement('a');
            a.href = dataUrl;
            a.download = 'QRIS_Bayar_' + totalAmount + '.png';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // 4. Salin String QRIS
        function copyQrisString() {
            navigator.clipboard.writeText(qrisData).then(() => {
                const btn = document.getElementById('copyBtnText');
                const orig = btn.innerText;
                btn.innerText = 'Tersalin ke Clipboard!';
                setTimeout(() => {
                    btn.innerText = orig;
                }, 2000);
            }).catch(err => {
                alert('Gagal menyalin: ' + err);
            });
        }

        // 5. Toggle Tutorial Accordion
        function toggleTutorial() {
            const content = document.getElementById('tutorialContent');
            const chevron = document.getElementById('tutorialChevron');
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                chevron.classList.add('rotate-180');
            } else {
                content.classList.add('hidden');
                chevron.classList.remove('rotate-180');
            }
        }
    </script>
</body>
</html>
