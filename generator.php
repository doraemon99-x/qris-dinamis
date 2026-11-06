<?php
/**
 * Generator QRIS Dinamis Custom Nominal (generator.php)
 * Hanya dapat diakses oleh Admin yang sudah login.
 */

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Proteksi: Hanya admin yang boleh mengakses halaman generator custom ini
require_login();

// Ambil pengaturan terbaru dari database jika ada
try {
    $dbSettings = get_all_settings();
    if (!empty($dbSettings)) {
        if (!empty($dbSettings['merchant_name'])) $config['merchant_name'] = $dbSettings['merchant_name'];
        if (!empty($dbSettings['merchant_city'])) $config['merchant_city'] = $dbSettings['merchant_city'];
        if (!empty($dbSettings['nmid'])) $config['nmid'] = $dbSettings['nmid'];
        if (isset($dbSettings['unique_enabled'])) $config['unique_code']['enabled'] = ($dbSettings['unique_enabled'] === '1');
        if (isset($dbSettings['unique_min'])) $config['unique_code']['min'] = (int)$dbSettings['unique_min'];
        if (isset($dbSettings['unique_max'])) $config['unique_code']['max'] = (int)$dbSettings['unique_max'];
        if (isset($dbSettings['expiry_minutes'])) $config['expiry_minutes'] = (int)$dbSettings['expiry_minutes'];
        if (isset($dbSettings['admin_whatsapp'])) $config['admin_whatsapp'] = $dbSettings['admin_whatsapp'];
    }
} catch (Throwable $e) {}

$itemsList = [];
try {
    $db = get_db();
    $itemsList = $db->query("SELECT * FROM items ORDER BY id DESC")->fetchAll();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Generator QRIS Custom - <?= htmlspecialchars($config['merchant_name']) ?></title>
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
    </style>
</head>
<body class="font-sans antialiased text-slate-800 p-3 sm:p-6 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-lg mx-auto my-auto">
        <!-- Top bar -->
        <div class="flex items-center justify-between mb-4 px-2">
            <div class="flex items-center space-x-2">
                <div class="w-3 h-3 rounded-full bg-amber-500 animate-pulse"></div>
                <span class="text-xs font-bold text-white tracking-wider uppercase">QRIS Custom Generator (Admin)</span>
            </div>
            <div class="flex items-center space-x-2">
                <a href="index.php" class="inline-flex items-center space-x-1.5 text-xs font-semibold text-slate-300 hover:text-white bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-full transition border border-white/10">
                    <span>🛒 Katalog Store</span>
                </a>
                <a href="admin.php" class="inline-flex items-center space-x-1.5 text-xs font-semibold text-slate-300 hover:text-white bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-full transition border border-white/10">
                    <span>⚙️ Dashboard Admin</span>
                </a>
            </div>
        </div>

        <!-- Main Card -->
        <div class="glass-card rounded-3xl overflow-hidden shadow-2xl">
            <!-- Header -->
            <div class="bg-gradient-to-r from-amber-600 via-orange-600 to-amber-700 text-white p-5 text-center relative overflow-hidden">
                <h2 class="text-xl font-extrabold tracking-tight"><?= htmlspecialchars($config['merchant_name']) ?></h2>
                <p class="text-xs text-amber-100 mt-0.5">Generator QRIS Dinamis Custom Nominal</p>
            </div>

            <!-- Form Body -->
            <div class="p-6 space-y-5">
                <form id="qrisForm" onsubmit="generateQris(event)" class="space-y-4">

                    <?php if (!empty($itemsList)): ?>
                    <div>
                        <label for="itemSelect" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilih dari Produk / Item (Opsional)</label>
                        <select id="itemSelect" onchange="onSelectItem(this)" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <option value="">-- Buat Nominal Bebas --</option>
                            <?php foreach ($itemsList as $it): ?>
                                <option value="<?= $it['price'] ?>" data-name="<?= htmlspecialchars($it['name']) ?>"><?= htmlspecialchars($it['name']) ?> - Rp <?= number_format($it['price'], 0, ',', '.') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label for="amount" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nominal Pembayaran (Rp)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center font-bold text-slate-400 text-sm">Rp</span>
                            <input type="number" id="amount" name="amount" min="1" required placeholder="Contoh: 15000"
                                class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-lg font-bold font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500 transition">
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Tanpa minimum (asal &gt; Rp 0).</p>
                    </div>

                    <!-- Quick Amount Buttons -->
                    <div class="grid grid-cols-4 gap-2">
                        <button type="button" onclick="setAmount(10000)" class="py-2 bg-slate-100 hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200 border border-slate-200 rounded-xl text-xs font-bold font-mono transition">10rb</button>
                        <button type="button" onclick="setAmount(20000)" class="py-2 bg-slate-100 hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200 border border-slate-200 rounded-xl text-xs font-bold font-mono transition">20rb</button>
                        <button type="button" onclick="setAmount(50000)" class="py-2 bg-slate-100 hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200 border border-slate-200 rounded-xl text-xs font-bold font-mono transition">50rb</button>
                        <button type="button" onclick="setAmount(100000)" class="py-2 bg-slate-100 hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200 border border-slate-200 rounded-xl text-xs font-bold font-mono transition">100rb</button>
                    </div>

                    <div>
                        <label for="note" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Catatan / Keterangan (Opsional)</label>
                        <input type="text" id="note" name="note" placeholder="Contoh: Pembayaran Layanan X"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500 transition">
                    </div>

                    <?php if ($config['unique_code']['enabled']): ?>
                    <div class="flex items-center justify-between p-3 bg-slate-50 border border-slate-200 rounded-2xl">
                        <div>
                            <span class="text-xs font-bold text-slate-800 block">Kode Unik Otomatis</span>
                            <span class="text-[11px] text-slate-500">Menambahkan 1–99 untuk deteksi mutasi otomatis</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="unique" name="unique" value="1" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                        </label>
                    </div>
                    <?php endif; ?>

                    <button type="submit" id="btnSubmit" class="w-full py-3.5 px-4 bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700 text-white rounded-2xl font-extrabold text-sm shadow-lg shadow-amber-500/30 transition duration-200 flex items-center justify-center space-x-2">
                        <span>Buat QRIS Pembayaran</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="text-center text-xs text-slate-400 py-4">
            © <?= date('Y') ?> <?= htmlspecialchars($config['merchant_name']) ?> • Powered by QRIS Dinamis
        </div>
    </div>

    <script>
        function setAmount(val) {
            document.getElementById('amount').value = val;
            const sel = document.getElementById('itemSelect');
            if (sel) sel.value = '';
        }

        function onSelectItem(sel) {
            const opt = sel.options[sel.selectedIndex];
            if (opt.value) {
                document.getElementById('amount').value = opt.value;
                document.getElementById('note').value = opt.getAttribute('data-name');
            }
        }

        async function generateQris(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerText = 'Memproses...';

            const amount = document.getElementById('amount').value;
            const note = document.getElementById('note').value;
            const uniqueEl = document.getElementById('unique');
            const unique = uniqueEl && uniqueEl.checked ? '1' : '0';

            try {
                const formData = new FormData();
                formData.append('action', 'generate');
                formData.append('amount', amount);
                formData.append('note', note);
                formData.append('unique', unique);

                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const json = await response.json();

                if (!json.success) {
                    alert('Gagal: ' + (json.message || 'Unknown error'));
                    btn.disabled = false;
                    btn.innerText = 'Buat QRIS Pembayaran';
                    return;
                }

                window.location.href = json.data.pay_url;

            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan koneksi.');
                btn.disabled = false;
                btn.innerText = 'Buat QRIS Pembayaran';
            }
        }
    </script>
</body>
</html>
