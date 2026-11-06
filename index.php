<?php
/**
 * Beranda / Katalog Produk & Top-up Store Modern (index.php)
 * Tampilan ala website top-up game & digital store profesional dengan info stok.
 */

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Ambil pengaturan terbaru dari database jika ada
try {
    $dbSettings = get_all_settings();
    if (!empty($dbSettings)) {
        if (!empty($dbSettings['merchant_name'])) $config['merchant_name'] = $dbSettings['merchant_name'];
        if (!empty($dbSettings['merchant_city'])) $config['merchant_city'] = $dbSettings['merchant_city'];
        if (!empty($dbSettings['nmid'])) $config['nmid'] = $dbSettings['nmid'];
    }
} catch (Throwable $e) {}

$itemsList = [];
try {
    $db = get_db();
    $itemsList = $db->query("SELECT * FROM items ORDER BY id DESC")->fetchAll();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($config['merchant_name']) ?> — Digital Store</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    },
                    colors: {
                        brand: {
                            50: '#fff1f2',
                            100: '#ffe4e6',
                            500: '#f43f5e',
                            600: '#e11d48',
                            700: '#be123c',
                            900: '#881337',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #0b0f19;
            color: #f8fafc;
            min-height: 100vh;
        }
        .glass-nav {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .store-card {
            background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid rgba(255, 255, 255, 0.07);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .store-card:hover {
            transform: translateY(-4px);
            border-color: rgba(244, 63, 94, 0.5);
            box-shadow: 0 20px 40px -15px rgba(244, 63, 94, 0.25);
        }
        .hero-banner {
            background: radial-gradient(circle at top right, rgba(225, 29, 72, 0.25), transparent 50%),
                        radial-gradient(circle at bottom left, rgba(79, 70, 229, 0.2), transparent 50%),
                        #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .modal-glass {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
    </style>
</head>
<body class="font-sans antialiased">

    <!-- Top Navbar -->
    <header class="sticky top-0 z-40 glass-nav">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-brand-600 to-rose-500 flex items-center justify-center shadow-lg shadow-brand-600/30 text-white font-extrabold text-lg">
                    ⚡
                </div>
                <div>
                    <span class="font-extrabold text-base tracking-tight text-white block leading-none"><?= htmlspecialchars($config['merchant_name']) ?></span>
                    <span class="text-[10px] font-semibold text-rose-400 tracking-wider uppercase">Official Digital Store</span>
                </div>
            </div>

            <div class="flex items-center space-x-2 sm:space-x-3">
                <?php if (is_logged_in()): ?>
                    <a href="generator.php" class="hidden sm:inline-flex items-center space-x-1.5 text-xs font-bold text-amber-300 bg-amber-500/10 hover:bg-amber-500/20 px-3.5 py-2 rounded-xl transition border border-amber-500/20">
                        <span>⚡ Generator</span>
                    </a>
                    <a href="admin.php" class="inline-flex items-center space-x-1.5 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 px-4 py-2 rounded-xl shadow-md shadow-brand-600/30 transition">
                        <span>⚙️ Dashboard Admin</span>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="inline-flex items-center space-x-1.5 text-xs font-bold text-slate-300 hover:text-white bg-slate-800/80 hover:bg-slate-700 px-4 py-2 rounded-xl border border-slate-700 transition">
                        <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <span>Login Admin</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-8">

        <!-- Hero Banner -->
        <div class="hero-banner rounded-3xl p-6 sm:p-10 relative overflow-hidden shadow-2xl">
            <div class="absolute right-0 top-0 w-96 h-96 bg-brand-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="relative z-10 max-w-2xl space-y-4">
                <div class="inline-flex items-center space-x-2 bg-brand-500/20 border border-brand-500/30 px-3.5 py-1.5 rounded-full text-xs font-bold text-rose-300">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span>Layanan Otomatis 24 Jam • QRIS Instan</span>
                </div>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white leading-tight">
                    Beli Produk Digital <span class="text-transparent bg-clip-text bg-gradient-to-r from-rose-400 to-amber-400">Instan & Aman</span>
                </h1>
                <p class="text-sm sm:text-base text-slate-300 leading-relaxed">
                    Selamat datang di <?= htmlspecialchars($config['merchant_name']) ?>. Pilih produk atau layanan yang Anda butuhkan, bayar menggunakan QRIS, dan transaksi diproses secara instan.
                </p>

                <!-- Search Bar -->
                <div class="pt-2">
                    <div class="relative max-w-md">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input type="text" id="searchInput" oninput="filterProducts()" placeholder="Cari produk atau layanan..."
                            class="w-full pl-11 pr-4 py-3 bg-slate-900/80 border border-slate-700/80 rounded-2xl text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition">
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Daftar Produk -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-2 h-5 bg-brand-500 rounded-full"></div>
                    <h2 class="text-lg sm:text-xl font-extrabold text-white tracking-tight">Katalog Produk & Layanan</h2>
                </div>
                <span class="text-xs font-semibold text-slate-400 font-mono" id="itemCountBadge"><?= count($itemsList) ?> Produk Tersedia</span>
            </div>

            <?php if (empty($itemsList)): ?>
                <div class="store-card rounded-3xl p-16 text-center space-y-3">
                    <div class="text-4xl">🛒</div>
                    <h3 class="text-base font-bold text-white">Belum Ada Produk</h3>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto">Admin belum menambahkan produk apapun ke katalog store.</p>
                </div>
            <?php else: ?>
                <div id="productGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                    <?php foreach ($itemsList as $item): ?>
                        <?php $stock = (int)($item['stock'] ?? 999); ?>
                        <div class="store-card rounded-3xl p-5 flex flex-col justify-between space-y-4 product-item" data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>">
                            <div class="space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="w-10 h-10 rounded-2xl bg-brand-500/10 border border-brand-500/20 flex items-center justify-center text-rose-400 font-bold text-base">
                                        🛍️
                                    </div>
                                    <?php if ($stock <= 0): ?>
                                        <span class="px-2.5 py-1 bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl text-[10px] font-extrabold font-mono tracking-wider">STOK HABIS</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl text-[10px] font-extrabold font-mono tracking-wider">STOK: <?= $stock ?></span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="font-bold text-white text-base leading-snug line-clamp-1"><?= htmlspecialchars($item['name']) ?></h3>
                                <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed"><?= htmlspecialchars($item['description'] ?? 'Layanan instan berkualitas tinggi, proses cepat & otomatis.') ?></p>
                            </div>

                            <div class="pt-4 border-t border-slate-800 flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-slate-500 block">Harga</span>
                                    <span class="font-mono font-extrabold text-rose-400 text-sm sm:text-base">Rp <?= number_format($item['price'], 0, ',', '.') ?></span>
                                </div>
                                <?php if ($stock <= 0): ?>
                                    <button type="button" disabled class="px-4 py-2.5 bg-slate-800 text-slate-500 rounded-xl font-bold text-xs cursor-not-allowed">
                                        Habis
                                    </button>
                                <?php else: ?>
                                    <button type="button"
                                        onclick="openCheckoutModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name']), ENT_QUOTES) ?>', <?= $item['price'] ?>, <?= $stock ?>)"
                                        class="px-4 py-2.5 bg-gradient-to-r from-brand-600 to-rose-600 hover:from-brand-700 hover:to-rose-700 text-white rounded-xl font-extrabold text-xs shadow-lg shadow-brand-600/25 transition active:scale-95 flex items-center space-x-1.5">
                                        <span>Beli Sekarang</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <footer class="border-t border-slate-800 pt-6 pb-10 text-center space-y-2 text-xs text-slate-500">
            <p>© <?= date('Y') ?> <?= htmlspecialchars($config['merchant_name']) ?>. All rights reserved.</p>
            <p>Didukung oleh Standar QRIS Nasional Indonesia • Pembayaran Aman & Real-time</p>
        </footer>
    </main>

    <!-- Checkout Modal -->
    <div id="checkoutModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center hidden p-4">
        <div class="modal-glass rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-7 space-y-6 animate-in fade-in zoom-in duration-200">

            <div class="flex justify-between items-start border-b border-slate-800 pb-4">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-rose-400 block mb-1">Konfirmasi Pesanan</span>
                    <h3 class="text-lg font-extrabold text-white" id="modalItemName">Nama Produk</h3>
                    <p class="text-xs font-mono text-slate-400 mt-0.5">Harga Satuan: <span id="modalItemPrice" class="text-rose-400 font-bold">Rp 0</span> | Sisa Stok: <span id="modalItemStock" class="text-emerald-400 font-bold">0</span></p>
                </div>
                <button type="button" onclick="closeCheckoutModal()" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center font-bold transition">✕</button>
            </div>

            <form id="checkoutForm" onsubmit="submitCheckout(event)" class="space-y-4">
                <input type="hidden" id="modalItemId" name="item_id" value="">
                <input type="hidden" id="modalUnitPrice" name="unit_price" value="0">
                <input type="hidden" id="modalMaxStock" name="max_stock" value="999">

                <!-- Quantity -->
                <div>
                    <label for="modalQty" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Jumlah Pembelian (Quantity)</label>
                    <div class="flex items-center space-x-2">
                        <button type="button" onclick="adjustQty(-1)" class="w-12 h-12 bg-slate-800 hover:bg-slate-700 text-white rounded-2xl font-bold text-lg transition flex items-center justify-center border border-slate-700">−</button>
                        <input type="number" id="modalQty" name="qty" value="1" min="1" max="999" required oninput="calculateTotal()"
                            class="w-full text-center py-3 bg-slate-900 border border-slate-700 rounded-2xl text-base font-extrabold font-mono text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <button type="button" onclick="adjustQty(1)" class="w-12 h-12 bg-slate-800 hover:bg-slate-700 text-white rounded-2xl font-bold text-lg transition flex items-center justify-center border border-slate-700">+</button>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="modalCustomer" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Nama Pembeli</label>
                        <input type="text" id="modalCustomer" name="customer" required placeholder="Nama Anda"
                            class="w-full px-3.5 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-xs font-medium text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label for="modalPhone" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">No. WhatsApp / HP</label>
                        <input type="text" id="modalPhone" name="phone" placeholder="08123456789"
                            class="w-full px-3.5 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-xs font-medium text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                </div>

                <div>
                    <label for="modalNote" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Catatan / Keterangan (Opsional)</label>
                    <input type="text" id="modalNote" name="note" placeholder="ID Game / Email / Nickname / Keterangan"
                        class="w-full px-3.5 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-xs font-medium text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>

                <!-- Total Summary Box -->
                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 text-center space-y-1">
                    <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-400 block">Total Pembayaran (QRIS Instan)</span>
                    <span id="modalTotalDisplay" class="text-2xl font-extrabold text-rose-400 font-mono">Rp 0</span>
                </div>

                <button type="submit" id="btnModalSubmit" class="w-full py-4 px-4 bg-gradient-to-r from-brand-600 via-rose-600 to-brand-700 hover:from-brand-700 hover:to-rose-800 text-white rounded-2xl font-extrabold text-sm shadow-xl shadow-brand-600/30 transition duration-200 flex items-center justify-center space-x-2">
                    <span>Lanjut ke Pembayaran QRIS ⚡</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        function filterProducts() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.product-item');
            let count = 0;
            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                if (name.includes(query)) {
                    card.style.display = 'flex';
                    count++;
                } else {
                    card.style.display = 'none';
                }
            });
            document.getElementById('itemCountBadge').innerText = count + ' Produk Ditemukan';
        }

        function openCheckoutModal(id, name, price, stock) {
            document.getElementById('modalItemId').value = id;
            document.getElementById('modalItemName').innerText = name;
            document.getElementById('modalUnitPrice').value = price;
            document.getElementById('modalMaxStock').value = stock;
            document.getElementById('modalItemPrice').innerText = 'Rp ' + parseInt(price).toLocaleString('id-ID');
            document.getElementById('modalItemStock').innerText = stock;
            document.getElementById('modalQty').value = 1;
            document.getElementById('modalQty').max = stock;
            document.getElementById('modalNote').value = name;

            calculateTotal();
            document.getElementById('checkoutModal').classList.remove('hidden');
        }

        function closeCheckoutModal() {
            document.getElementById('checkoutModal').classList.add('hidden');
        }

        function adjustQty(change) {
            const qtyInput = document.getElementById('modalQty');
            const maxStock = parseInt(document.getElementById('modalMaxStock').value) || 999;
            let current = parseInt(qtyInput.value) || 1;
            current += change;
            if (current < 1) current = 1;
            if (current > maxStock) current = maxStock;
            qtyInput.value = current;
            calculateTotal();
        }

        function calculateTotal() {
            const unitPrice = parseFloat(document.getElementById('modalUnitPrice').value) || 0;
            const qtyInput = document.getElementById('modalQty');
            const maxStock = parseInt(document.getElementById('modalMaxStock').value) || 999;
            let qty = parseInt(qtyInput.value) || 1;
            if (qty > maxStock) {
                qty = maxStock;
                qtyInput.value = maxStock;
            }
            if (qty < 1) {
                qty = 1;
                qtyInput.value = 1;
            }
            const total = unitPrice * qty;
            document.getElementById('modalTotalDisplay').innerText = 'Rp ' + total.toLocaleString('id-ID');
        }

        async function submitCheckout(e) {
            e.preventDefault();
            const btn = document.getElementById('btnModalSubmit');
            btn.disabled = true;
            btn.innerText = 'Memproses Pesanan...';

            const unitPrice = parseFloat(document.getElementById('modalUnitPrice').value) || 0;
            const qty = parseInt(document.getElementById('modalQty').value) || 1;
            const totalAmount = unitPrice * qty;
            const itemName = document.getElementById('modalItemName').innerText;
            const customer = document.getElementById('modalCustomer').value;
            const phone = document.getElementById('modalPhone').value;
            const customNote = document.getElementById('modalNote').value;
            const fullNote = customNote ? `${itemName} (x${qty}) - ${customNote}` : `${itemName} (x${qty})`;

            try {
                const formData = new FormData();
                formData.append('action', 'generate');
                formData.append('amount', totalAmount);
                formData.append('note', fullNote);
                formData.append('customer', customer);
                formData.append('phone', phone);
                formData.append('unique', '1');
                formData.append('item_id', document.getElementById('modalItemId').value);

                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const json = await response.json();

                if (!json.success) {
                    alert('Gagal membuat pesanan: ' + (json.message || 'Unknown error'));
                    btn.disabled = false;
                    btn.innerText = 'Lanjut ke Pembayaran QRIS ⚡';
                    return;
                }

                window.location.href = json.data.pay_url;

            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan koneksi.');
                btn.disabled = false;
                btn.innerText = 'Lanjut ke Pembayaran QRIS ⚡';
            }
        }
    </script>
</body>
</html>
