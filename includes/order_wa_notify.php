<?php
/**
 * Bangun pesan + link WA "order selesai" siap kirim ke customer - terpusat, dipanggil dari
 * 2 titik: api-update-status-order.php (Hoki POS) dan order/admin/pesanan.php (admin order
 * site), begitu status order diubah jadi 'completed'. Jangan duplikat logic ini di sisi lain.
 *
 * Belum ada integrasi WA API pihak ketiga (Fonnte/Wablas/dll) di project ini, jadi fungsi ini
 * cuma menyiapkan pesan + link wa.me siap kirim (staf tinggal klik) - sama seperti pola WA
 * lain yang sudah ada di seluruh project ini (wa_link() di order/includes/functions.php,
 * waLink() di kelola-order.html, dll), bukan auto-send lewat API.
 */

function build_order_completed_wa_notif(PDO $pdo, int $orderId): ?array
{
    $stmt = $pdo->prepare('SELECT o.*, b.nama AS branch_nama FROM orders o JOIN branches b ON b.id = o.branch_id WHERE o.id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order || empty($order['no_wa'])) {
        return null;
    }

    $pesan = "Halo {$order['nama_customer']}! 🥟\n"
        . "Pesanan kamu dengan kode *{$order['order_code']}* di {$order['branch_nama']} sudah *selesai*.\n"
        . "Terima kasih sudah order di Hoki Dimsum, sampai jumpa lagi! 🙏";

    $nomor = preg_replace('/[^0-9]/', '', $order['no_wa']);
    if (substr($nomor, 0, 1) === '0') {
        $nomor = '62' . substr($nomor, 1);
    }

    return [
        'order_code' => $order['order_code'],
        'no_wa'      => $order['no_wa'],
        'pesan'      => $pesan,
        'wa_link'    => 'https://wa.me/' . $nomor . '?text=' . rawurlencode($pesan),
    ];
}
