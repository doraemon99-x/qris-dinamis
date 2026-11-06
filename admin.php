<?php
/**
 * Admin Dashboard - QRIS Dinamis
 * Mengelola pengaturan merchant, base QRIS, riwayat transaksi, dan manajemen produk/stok.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/QrisHelper.php';

require_login();

$db = get_db();
$activeTab = $_GET['tab'] ?? 'transactions';

// Proses aksi POST (PRG Pattern: Post-Redirect-Get)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('error', 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.');
        header("Location: admin.php?tab={$activeTab}");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Update Pengaturan
    if ($action === 'update_settings') {
        $qrisBase = trim((string)($_POST['qris_base'] ?? ''));
        $merchantName = trim((string)($_POST['merchant_name'] ?? ''));
        $merchantCity = trim((string)($_POST['merchant_city'] ?? ''));
        $nmid = trim((string)($_POST['nmid'] ?? ''));
        $uniqueEnabled = isset($_POST['unique_enabled']) ? '1' : '0';
        $uniqueMin = (int)($_POST['unique_min'] ?? 1);
        $uniqueMax = (int)($_POST['unique_max'] ?? 499);
        $expiryMinutes = (int)($_POST['expiry_minutes'] ?? 15);
        $adminWhatsapp = preg_replace('/[^0-9]/', '', (string)($_POST['admin_whatsapp'] ?? ''));
        $adminTelegram = trim((string)($_POST['admin_telegram'] ?? ''));
        $apiKey = trim((string)($_POST['api_key'] ?? ''));
        $adminUser = trim((string)($_POST['admin_username'] ?? 'admin'));
        $newPassword = $_POST['new_password'] ?? '';

        if (empty($qrisBase)) {
            set_flash('error', 'String QRIS Base wajib diisi.');
        } else {
            $parsedQris = QrisHelper::parse($qrisBase);
            if (!empty($parsedQris['59'])) $merchantName = trim($parsedQris['59']);
            if (!empty($parsedQris['60'])) $merchantCity = trim($parsedQris['60']);
            if (isset($parsedQris['51'])) {
                $sub51 = QrisHelper::parse($parsedQris['51']);
                if (!empty($sub51['02'])) $nmid = trim($sub51['02']);
            }

            set_setting('qris_base', $qrisBase);
            set_setting('merchant_name', $merchantName);
            set_setting('merchant_city', $merchantCity);
            set_setting('nmid', $nmid);
            set_setting('unique_enabled', $uniqueEnabled);
            set_setting('unique_min', (string)$uniqueMin);
            set_setting('unique_max', (string)$uniqueMax);
            set_setting('expiry_minutes', (string)$expiryMinutes);
            set_setting('admin_whatsapp', $adminWhatsapp);
            set_setting('admin_telegram', $adminTelegram);
            set_setting('api_key', $apiKey);
            set_setting('admin_username', $adminUser);

            if (!empty($newPassword)) {
                if (strlen($newPassword) < 5) {
                    set_flash('error', 'Password baru minimal 5 karakter.');
                    header("Location: admin.php?tab=settings");
                    exit;
                } else {
                    set_setting('admin_password_hash', password_hash($newPassword, PASSWORD_BCRYPT));
                }
            }

            set_flash('success', 'Pengaturan berhasil disimpan!');
        }
        header("Location: admin.php?tab=settings");
        exit;
    }

    // 2. Update Status Transaksi
    elseif ($action === 'update_tx_status') {
        $orderId = trim((string)($_POST['order_id'] ?? ''));
        $newStatus = trim((string)($_POST['status'] ?? 'paid'));

        $stmt = $db->prepare("UPDATE transactions SET status = :st, paid_at = :paid WHERE order_id = :oid");
        $stmt->execute([
            ':st'   => $newStatus,
            ':paid' => ($newStatus === 'paid' ? date('Y-m-d H:i:s') : null),
            ':oid'  => $orderId
        ]);
        set_flash('success', 'Status transaksi ' . $orderId . ' diperbarui menjadi ' . $newStatus);
        header("Location: admin.php?tab=transactions");
        exit;
    }

    // 3. Hapus Transaksi
    elseif ($action === 'delete_tx') {
        $orderId = trim((string)($_POST['order_id'] ?? ''));
        $stmt = $db->prepare("DELETE FROM transactions WHERE order_id = :oid");
        $stmt->execute([':oid' => $orderId]);
        set_flash('success', 'Transaksi ' . $orderId . ' berhasil dihapus.');
        header("Location: admin.php?tab=transactions");
        exit;
    }

    // 4. Tambah Item / Produk
    elseif ($action === 'add_item') {
        $itemName = trim((string)($_POST['item_name'] ?? ''));
        $itemPrice = (float)($_POST['item_price'] ?? 0);
        $itemStock = (int)($_POST['item_stock'] ?? 999);
        $itemDesc = trim((string)($_POST['item_desc'] ?? ''));

        if (empty($itemName) || $itemPrice <= 0) {
            set_flash('error', 'Nama item dan harga wajib diisi dan harus > 0.');
        } else {
            $ins = $db->prepare("INSERT INTO items (name, price, stock, description) VALUES (:name, :price, :stock, :desc)");
            $ins->execute([
                ':name'  => $itemName,
                ':price' => $itemPrice,
                ':stock' => $itemStock,
                ':desc'  => $itemDesc ?: null
            ]);
            set_flash('success', 'Produk "' . $itemName . '" berhasil ditambahkan!');
        }
        header("Location: admin.php?tab=items");
        exit;
    }

    // 5. Update Item / Produk
    elseif ($action === 'update_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $itemName = trim((string)($_POST['item_name'] ?? ''));
        $itemPrice = (float)($_POST['item_price'] ?? 0);
        $itemStock = (int)($_POST['item_stock'] ?? 999);
        $itemDesc = trim((string)($_POST['item_desc'] ?? ''));

        if ($itemId <= 0 || empty($itemName) || $itemPrice <= 0) {
            set_flash('error', 'Data item untuk update tidak valid.');
        } else {
            $upd = $db->prepare("UPDATE items SET name = :name, price = :price, stock = :stock, description = :desc WHERE id = :id");
            $upd->execute([
                ':name'  => $itemName,
                ':price' => $itemPrice,
                ':stock' => $itemStock,
                ':desc'  => $itemDesc ?: null,
                ':id'    => $itemId
            ]);
            set_flash('success', 'Produk "' . $itemName . '" berhasil diperbarui!');
        }
        header("Location: admin.php?tab=items");
        exit;
    }

    // 6. Hapus Item / Produk
    elseif ($action === 'delete_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $del = $db->prepare("DELETE FROM items WHERE id = :id");
        $del->execute([':id' => $itemId]);
        set_flash('success', 'Produk berhasil dihapus.');
        header("Location: admin.php?tab=items");
        exit;
    }
}

// Ambil Flash Messages
$flash = get_flash();
$message = ($flash && $flash['type'] === 'success') ? $flash['message'] : '';
$error = ($flash && $flash['type'] === 'error') ? $flash['message'] : '';

// Ambil data pengaturan
$settings = get_all_settings();
$itemsList = $db->query("SELECT * FROM items ORDER BY id DESC")->fetchAll();

// Ambil daftar transaksi
$filterStatus = $_GET['status'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM transactions WHERE 1=1";
$params = [];

if (!empty($filterStatus)) {
    $sql .= " AND status = :status";
    $params[':status'] = $filterStatus;
}
if (!empty($searchQuery)) {
    $sql .= " AND (order_id LIKE :q OR customer_name LIKE :q OR note LIKE :q)";
    $params[':q'] = "%{$searchQuery}%";
}

$sql .= " ORDER BY id DESC LIMIT 50";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$totalTx = $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$totalPaidAmount = $db->query("SELECT SUM(total_amount) FROM transactions WHERE status = 'paid'")->fetchColumn() ?: 0;
$totalPending = $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Dashboard - <?= htmlspecialchars($settings['merchant_name'] ?? 'QRIS Dinamis') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="jsqr.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    }
                }
            }
        }
    </script>
    <style>
        body { background: #f8fafc; min-height: 100vh; }
    </style>
</head>
<body class="font-sans antialiased text-slate-800">

    <!-- Top Navigation Bar -->
    <header class="bg-slate-900 text-white shadow-md sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-red-600 to-rose-500 flex items-center justify-center font-extrabold text-white shadow-md shadow-red-500/30">
                    Q
                </div>
                <div>
                    <span class="font-bold text-sm tracking-tight block"><?= htmlspecialchars($settings['merchant_name'] ?? 'Merchant') ?></span>
                    <span class="text-[10px] text-slate-400">Admin Panel • NMID: <?= htmlspecialchars($settings['nmid'] ?? '-') ?></span>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="index.php" target="_blank" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold transition border border-slate-700 flex items-center space-x-1.5">
                    <span>🛒 Buka Store Katalog</span>
                </a>
                <a href="generator.php" target="_blank" class="px-3 py-1.5 bg-amber-600/20 hover:bg-amber-600 text-amber-400 hover:text-white rounded-xl text-xs font-semibold transition border border-amber-500/30">
                    <span>⚡ Generator Custom</span>
                </a>
                <a href="logout.php" class="px-3 py-1.5 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded-xl text-xs font-semibold transition border border-red-500/30">
                    <span>Keluar</span>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <?php if (!empty($message)): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs sm:text-sm font-medium flex items-center space-x-2 shadow-sm">
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl text-xs sm:text-sm font-medium flex items-center space-x-2 shadow-sm">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Transaksi</p>
                    <h3 class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($totalTx, 0, ',', '.') ?></h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">📦</div>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Pendapatan (Paid)</p>
                    <h3 class="text-2xl font-extrabold text-emerald-600 mt-1 font-mono">Rp <?= number_format($totalPaidAmount, 0, ',', '.') ?></h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">💰</div>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Transaksi Pending</p>
                    <h3 class="text-2xl font-extrabold text-amber-600 mt-1"><?= number_format($totalPending, 0, ',', '.') ?></h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">⏳</div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="flex space-x-2 border-b border-slate-200">
            <button onclick="switchTab('transactions')" id="tabBtnTransactions" class="px-4 py-2.5 font-bold text-xs sm:text-sm border-b-2 border-transparent text-slate-500 hover:text-slate-900 transition">
                Riwayat Transaksi
            </button>
            <button onclick="switchTab('items')" id="tabBtnItems" class="px-4 py-2.5 font-bold text-xs sm:text-sm border-b-2 border-transparent text-slate-500 hover:text-slate-900 transition">
                Manajemen Produk (Item & Stok)
            </button>
            <button onclick="switchTab('settings')" id="tabBtnSettings" class="px-4 py-2.5 font-bold text-xs sm:text-sm border-b-2 border-transparent text-slate-500 hover:text-slate-900 transition">
                Pengaturan QRIS & Merchant
            </button>
        </div>

        <!-- TAB 1: TRANSACTIONS -->
        <div id="sectionTransactions" class="hidden space-y-4">
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-3">
                <form method="GET" action="admin.php" class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <input type="hidden" name="tab" value="transactions">
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Cari No. Order / Nama..."
                        class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium w-full sm:w-64 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <select name="status" class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="">Semua Status</option>
                        <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="paid" <?= $filterStatus === 'paid' ? 'selected' : '' ?>>Paid (Lunas)</option>
                        <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-semibold hover:bg-slate-800 transition">
                        Filter
                    </button>
                    <?php if (!empty($searchQuery) || !empty($filterStatus)): ?>
                        <a href="admin.php?tab=transactions" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-semibold">Reset</a>
                    <?php endif; ?>
                </form>
                <div class="text-xs text-slate-500">Menampilkan <?= count($transactions) ?> transaksi terakhir</div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-bold border-b border-slate-200">
                                <th class="p-3.5">No. Order / Waktu</th>
                                <th class="p-3.5">Pelanggan / Catatan</th>
                                <th class="p-3.5 text-right">Nominal (Rp)</th>
                                <th class="p-3.5 text-center">Status</th>
                                <th class="p-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-slate-400 italic">Belum ada data transaksi.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="p-3.5">
                                            <span class="font-mono font-bold text-slate-900 block"><?= htmlspecialchars($tx['order_id']) ?></span>
                                            <span class="text-[11px] text-slate-400"><?= htmlspecialchars($tx['created_at']) ?></span>
                                        </td>
                                        <td class="p-3.5">
                                            <span class="font-semibold text-slate-800 block"><?= htmlspecialchars($tx['customer_name'] ?? 'Tanpa Nama') ?></span>
                                            <?php if (!empty($tx['note'])): ?>
                                                <span class="text-[11px] text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded"><?= htmlspecialchars($tx['note']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold text-slate-900">
                                            Rp <?= number_format($tx['total_amount'], 0, ',', '.') ?>
                                            <?php if ($tx['unique_code'] > 0): ?>
                                                <span class="block text-[10px] text-amber-600 font-normal">+uniq <?= $tx['unique_code'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <?php if ($tx['status'] === 'paid'): ?>
                                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full text-[10px] font-bold">Paid</span>
                                            <?php elseif ($tx['status'] === 'cancelled'): ?>
                                                <span class="px-2.5 py-1 bg-red-100 text-red-800 rounded-full text-[10px] font-bold">Cancelled</span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 rounded-full text-[10px] font-bold">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3.5 text-center space-x-1">
                                            <a href="pay.php?amount=<?= $tx['total_amount'] ?>&order_id=<?= urlencode($tx['order_id']) ?>&note=<?= urlencode($tx['note'] ?? '') ?>" target="_blank"
                                                class="inline-flex items-center px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-semibold transition">
                                                QR
                                            </a>
                                            <form method="POST" action="admin.php?tab=transactions" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="update_tx_status">
                                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($tx['order_id']) ?>">
                                                <?php if ($tx['status'] !== 'paid'): ?>
                                                    <input type="hidden" name="status" value="paid">
                                                    <button type="submit" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[11px] font-semibold transition">Set Paid</button>
                                                <?php else: ?>
                                                    <input type="hidden" name="status" value="pending">
                                                    <button type="submit" class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-[11px] font-semibold transition">Set Pending</button>
                                                <?php endif; ?>
                                            </form>
                                            <form method="POST" action="admin.php?tab=transactions" class="inline" onsubmit="return confirm('Hapus transaksi ini?')">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="delete_tx">
                                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($tx['order_id']) ?>">
                                                <button type="submit" class="px-2 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg text-[11px] font-semibold transition">✕</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: ITEMS & STOCK -->
        <div id="sectionItems" class="hidden space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Form Tambah Item -->
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200/80 h-fit">
                    <h2 class="text-base font-bold text-slate-900 mb-1">Tambah Produk & Stok</h2>
                    <p class="text-xs text-slate-500 mb-4">Tambahkan produk beserta jumlah stok ketersediaannya.</p>
                    <form method="POST" action="admin.php?tab=items" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="add_item">

                        <div>
                            <label for="item_name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nama Produk</label>
                            <input type="text" id="item_name" name="item_name" required placeholder="Contoh: Voucher Game X"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label for="item_price" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Harga Satuan (Rp)</label>
                            <input type="number" id="item_price" name="item_price" required min="1" placeholder="28000"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label for="item_stock" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Jumlah Stok</label>
                            <input type="number" id="item_stock" name="item_stock" required min="0" value="100" placeholder="100"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label for="item_desc" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Deskripsi (Opsional)</label>
                            <input type="text" id="item_desc" name="item_desc" placeholder="Keterangan singkat"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <button type="submit" class="w-full py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-xs shadow-md transition">
                            Simpan Produk
                        </button>
                    </form>
                </div>

                <!-- Tabel Daftar Produk -->
                <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                        <h2 class="text-base font-bold text-slate-900">Daftar Produk & Stok</h2>
                        <span class="text-xs text-slate-500"><?= count($itemsList) ?> produk tersedia</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-bold border-b border-slate-200">
                                    <th class="p-3.5">Nama Produk</th>
                                    <th class="p-3.5">Harga</th>
                                    <th class="p-3.5 text-center">Stok</th>
                                    <th class="p-3.5">Deskripsi</th>
                                    <th class="p-3.5 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <?php if (empty($itemsList)): ?>
                                    <tr>
                                        <td colspan="5" class="p-8 text-center text-slate-400 italic">Belum ada produk.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($itemsList as $item): ?>
                                        <tr class="hover:bg-slate-50/80 transition">
                                            <td class="p-3.5 font-bold text-slate-900"><?= htmlspecialchars($item['name']) ?></td>
                                            <td class="p-3.5 font-mono font-bold text-red-600">Rp <?= number_format($item['price'], 0, ',', '.') ?></td>
                                            <td class="p-3.5 text-center">
                                                <?php $stk = (int)($item['stock'] ?? 999); ?>
                                                <?php if ($stk <= 0): ?>
                                                    <span class="px-2.5 py-1 bg-red-100 text-red-800 rounded-full font-bold text-[10px]">Habis (0)</span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full font-bold text-[10px]"><?= $stk ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-3.5 text-slate-600"><?= htmlspecialchars($item['description'] ?? '-') ?></td>
                                            <td class="p-3.5 text-center space-x-1">
                                                <button type="button" onclick="openEditItemModal('<?= $item['id'] ?>', '<?= htmlspecialchars(addslashes($item['name']), ENT_QUOTES) ?>', '<?= $item['price'] ?>', '<?= $stk ?>', '<?= htmlspecialchars(addslashes($item['description'] ?? ''), ENT_QUOTES) ?>')"
                                                    class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-semibold transition">
                                                    Edit
                                                </button>
                                                <form method="POST" action="admin.php?tab=items" class="inline" onsubmit="return confirm('Hapus produk ini?')">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg text-[11px] font-semibold transition">Hapus</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: SETTINGS -->
        <div id="sectionSettings" class="hidden space-y-6">
            <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80">
                <div class="mb-6">
                    <h2 class="text-lg font-bold text-slate-900">Pengaturan QRIS & Merchant</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Ubah string dasar QRIS Statis Anda, informasi toko, dan keamanan panel.</p>
                </div>

                <form method="POST" action="admin.php?tab=settings" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_settings">

                    <!-- Fitur Upload Gambar QRIS -->
                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Upload Foto / Screenshot QRIS Statis (Otomatis Ekstrak)</label>
                        <div class="flex flex-col sm:flex-row items-center gap-3">
                            <input type="file" id="qrisImageFile" accept="image/*" onchange="handleQrisImageUpload(event)"
                                class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-red-50 file:text-red-700 hover:file:bg-red-100 cursor-pointer">
                            <span id="scanStatus" class="text-xs text-slate-400 font-medium">Pilih gambar QRIS untuk mendeteksi string otomatis.</span>
                        </div>
                    </div>

                    <div>
                        <label for="qris_base" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">String QRIS Statis Dasar</label>
                        <textarea id="qris_base" name="qris_base" rows="3" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-mono text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500"><?= htmlspecialchars($settings['qris_base'] ?? '') ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="merchant_name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nama Merchant / Toko</label>
                            <input type="text" id="merchant_name" name="merchant_name" value="<?= htmlspecialchars($settings['merchant_name'] ?? '') ?>" required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label for="merchant_city" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Kota Merchant</label>
                            <input type="text" id="merchant_city" name="merchant_city" value="<?= htmlspecialchars($settings['merchant_city'] ?? '') ?>" required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label for="nmid" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">NMID QRIS</label>
                            <input type="text" id="nmid" name="nmid" value="<?= htmlspecialchars($settings['nmid'] ?? '') ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                        <div class="flex items-center space-x-2 pt-6">
                            <input type="checkbox" id="unique_enabled" name="unique_enabled" value="1" <?= ($settings['unique_enabled'] ?? '1') === '1' ? 'checked' : '' ?>
                                class="w-4 h-4 text-red-600 rounded border-slate-300 focus:ring-red-500">
                            <label for="unique_enabled" class="text-xs font-bold text-slate-700 cursor-pointer">Aktifkan Kode Unik Acak</label>
                        </div>
                        <div>
                            <label for="unique_min" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Kode Unik Min (Rp)</label>
                            <input type="number" id="unique_min" name="unique_min" value="<?= htmlspecialchars($settings['unique_min'] ?? '1') ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label for="unique_max" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Kode Unik Max (Rp)</label>
                            <input type="number" id="unique_max" name="unique_max" value="<?= htmlspecialchars($settings['unique_max'] ?? '499') ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
                        <div>
                            <label for="expiry_minutes" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Masa Berlaku (Menit)</label>
                            <input type="number" id="expiry_minutes" name="expiry_minutes" value="<?= htmlspecialchars($settings['expiry_minutes'] ?? '15') ?>" required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label for="admin_whatsapp" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">No. WhatsApp Admin</label>
                            <input type="text" id="admin_whatsapp" name="admin_whatsapp" value="<?= htmlspecialchars($settings['admin_whatsapp'] ?? '') ?>" placeholder="628123456789"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label for="admin_telegram" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Username Telegram</label>
                            <input type="text" id="admin_telegram" name="admin_telegram" value="<?= htmlspecialchars($settings['admin_telegram'] ?? '') ?>" placeholder="usernameadmin"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label for="api_key" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">API Key (Opsional)</label>
                            <input type="text" id="api_key" name="api_key" value="<?= htmlspecialchars($settings['api_key'] ?? '') ?>" placeholder="Bebas"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-5 mt-4">
                        <h3 class="text-sm font-bold text-slate-900 mb-3">Keamanan Admin</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="admin_username" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Username Admin</label>
                                <input type="text" id="admin_username" name="admin_username" value="<?= htmlspecialchars($settings['admin_username'] ?? 'admin') ?>" required
                                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                            </div>
                            <div>
                                <label for="new_password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Password Baru (Opsional)</label>
                                <input type="password" id="new_password" name="new_password" placeholder="Kosongkan jika tidak diubah"
                                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                            </div>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-xs sm:text-sm shadow-md transition">
                            Simpan Perubahan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <!-- Modal Edit Produk -->
    <div id="editItemModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4" onclick="if(event.target === this) closeEditItemModal()">
        <div class="bg-white rounded-3xl shadow-xl max-w-md w-full p-6 space-y-4 relative z-10">
            <div class="flex justify-between items-center">
                <h3 class="text-base font-bold text-slate-900">Edit Produk & Stok</h3>
                <button type="button" onclick="closeEditItemModal()" class="text-slate-400 hover:text-slate-600 font-bold text-lg p-1">✕</button>
            </div>
            <form method="POST" action="admin.php?tab=items" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_item">
                <input type="hidden" id="edit_item_id" name="item_id" value="">

                <div>
                    <label for="edit_item_name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nama Produk</label>
                    <input type="text" id="edit_item_name" name="item_name" required
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label for="edit_item_price" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Harga (Rp)</label>
                    <input type="number" id="edit_item_price" name="item_price" required min="1"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label for="edit_item_stock" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Jumlah Stok</label>
                    <input type="number" id="edit_item_stock" name="item_stock" required min="0"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label for="edit_item_desc" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Deskripsi (Opsional)</label>
                    <input type="text" id="edit_item_desc" name="item_desc"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div class="flex space-x-2 pt-2">
                    <button type="button" onclick="closeEditItemModal()" class="w-1/2 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold text-xs transition">
                        Batal
                    </button>
                    <button type="submit" class="w-1/2 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-xs shadow-md transition">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            const txSec = document.getElementById('sectionTransactions');
            const itemsSec = document.getElementById('sectionItems');
            const setSec = document.getElementById('sectionSettings');
            const txBtn = document.getElementById('tabBtnTransactions');
            const itemsBtn = document.getElementById('tabBtnItems');
            const setBtn = document.getElementById('tabBtnSettings');

            txSec.classList.add('hidden');
            itemsSec.classList.add('hidden');
            setSec.classList.add('hidden');

            txBtn.classList.remove('border-red-600', 'text-red-600');
            txBtn.classList.add('border-transparent', 'text-slate-500');
            itemsBtn.classList.remove('border-red-600', 'text-red-600');
            itemsBtn.classList.add('border-transparent', 'text-slate-500');
            setBtn.classList.remove('border-red-600', 'text-red-600');
            setBtn.classList.add('border-transparent', 'text-slate-500');

            if (tab === 'transactions') {
                txSec.classList.remove('hidden');
                txBtn.classList.add('border-red-600', 'text-red-600');
                txBtn.classList.remove('border-transparent', 'text-slate-500');
            } else if (tab === 'items') {
                itemsSec.classList.remove('hidden');
                itemsBtn.classList.add('border-red-600', 'text-red-600');
                itemsBtn.classList.remove('border-transparent', 'text-slate-500');
            } else if (tab === 'settings') {
                setSec.classList.remove('hidden');
                setBtn.classList.add('border-red-600', 'text-red-600');
                setBtn.classList.remove('border-transparent', 'text-slate-500');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            switchTab('<?= $activeTab ?>');
        });

        function openEditItemModal(id, name, price, stock, desc) {
            const modal = document.getElementById('editItemModal');
            if (!modal) return;
            document.getElementById('edit_item_id').value = id;
            document.getElementById('edit_item_name').value = name;
            document.getElementById('edit_item_price').value = price;
            document.getElementById('edit_item_stock').value = stock;
            document.getElementById('edit_item_desc').value = desc;
            modal.classList.remove('hidden');
        }

        function closeEditItemModal() {
            const modal = document.getElementById('editItemModal');
            if (modal) modal.classList.add('hidden');
        }

        function handleQrisImageUpload(event) {
            const file = event.target.files[0];
            const statusEl = document.getElementById('scanStatus');
            if (!file) return;

            statusEl.innerText = 'Memindai & mengekstrak QRIS...';
            statusEl.className = 'text-xs text-amber-600 font-medium';

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');

                    let width = img.width;
                    let height = img.height;
                    const maxWidth = 800;
                    if (width > maxWidth) {
                        height = Math.round((height * maxWidth) / width);
                        width = maxWidth;
                    }

                    canvas.width = width;
                    canvas.height = height;
                    ctx.drawImage(img, 0, 0, width, height);

                    let imageData = ctx.getImageData(0, 0, width, height);
                    let code = jsQR(imageData.data, imageData.width, imageData.height);

                    if (!code) {
                        const cropW = Math.round(width * 0.7);
                        const cropH = Math.round(height * 0.7);
                        const cropX = Math.round((width - cropW) / 2);
                        const cropY = Math.round((height - cropH) / 2);

                        const canvasCrop = document.createElement('canvas');
                        const ctxCrop = canvasCrop.getContext('2d');
                        canvasCrop.width = cropW;
                        canvasCrop.height = cropH;
                        ctxCrop.drawImage(img, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);

                        const imgDataCrop = ctxCrop.getImageData(0, 0, cropW, cropH);
                        code = jsQR(imgDataCrop.data, imgDataCrop.width, imgDataCrop.height);
                    }

                    if (code) {
                        document.getElementById('qris_base').value = code.data;
                        statusEl.innerText = 'Berhasil! String QRIS berhasil diekstrak.';
                        statusEl.className = 'text-xs text-emerald-600 font-bold';

                        try {
                            const data = code.data;
                            let idx = 0;
                            while (idx < data.length) {
                                let tag = data.substr(idx, 2);
                                let len = parseInt(data.substr(idx + 2, 2), 10);
                                let val = data.substr(idx + 4, len);
                                if (tag === '59') document.getElementById('merchant_name').value = val;
                                if (tag === '60') document.getElementById('merchant_city').value = val;
                                idx += 4 + len;
                            }
                        } catch (err) {}
                    } else {
                        statusEl.innerText = 'Gagal mendeteksi QR Code. Pastikan foto jelas dan terang.';
                        statusEl.className = 'text-xs text-red-600 font-bold';
                    }
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    </script>
</body>
</html>
