<?php
/**
 * Konfigurasi QRIS Dinamis
 * Bisa diubah sesuai kebutuhan merchant Anda
 */

date_default_timezone_set('Asia/Jakarta');

return [
    // String QRIS Statis dasar (didapat dari scan qris.jpg Anda)
    'qris_base' => '00020101021126570011ID.DANA.WWW011893600915309822381902090982238190303UKE51440014ID.CO.QRIS.WWW0215ID10200583144330303UKE5204481453033605802ID5914AXLINESID Shop6015Kab. Rokan Hili6105289536304BF92',

    // Informasi Merchant (akan dioverride jika otomatis terbaca dari QRIS)
    'merchant_name' => 'AXLINESID Shop',
    'merchant_city' => 'Kab. Rokan Hili',
    'nmid'          => 'ID1020058314433',

    // Pengaturan Kode Unik Acak (Membantu mencocokkan mutasi manual di aplikasi DANA)
    'unique_code' => [
        'enabled' => true,
        'min'     => 1,
        'max'     => 499, // Tambahan nominal acak Rp 1 s/d Rp 499
    ],

    // Masa berlaku QRIS Dinamis (dalam menit untuk countdown display)
    'expiry_minutes' => 15,

    // Nomor WhatsApp Admin untuk konfirmasi pembayaran dari customer
    // Format internasional tanpa tanda + (contoh: 6281234567890)
    'admin_whatsapp' => '6281234567890',

    // Username Telegram Admin (tanpa @) atau Bot Telegram untuk konfirmasi
    'admin_telegram' => '',
    'telegram_bot_token' => '',
    'telegram_chat_id' => '',

    // API Key (opsional, jika ingin mengamankan endpoint /api.php dari luar)
    // Kosongkan '' jika ingin bebas diakses tanpa auth
    'api_key' => '',
];
