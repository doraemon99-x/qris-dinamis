<?php
/**
 * Logout Endpoint - QRIS Dinamis
 * Menghapus sesi login admin dan mengalihkan ke halaman login
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

logout_user();
set_flash('success', 'Anda telah berhasil keluar dari akun admin.');
header('Location: login.php');
exit;
