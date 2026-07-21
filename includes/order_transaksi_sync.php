<?php
/**
 * Bridge satu arah: order Order Online (paid) -> histori transaksi POS.
 * Dipakai dari 2 titik: api-update-status-order.php (langsung, sama proses)
 * dan api.php action=sync_order_transaksi (dipanggil via curl dari order/admin/pesanan.php).
 */

function sync_order_online_transaksi(mysqli $conn, PDO $pdo, int $orderId): array
{
    $stmt = $pdo->prepare('SELECT o.*, b.nama AS branch_nama FROM orders o JOIN branches b ON b.id = o.branch_id WHERE o.id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        return ['status' => 'error', 'message' => 'Order tidak ditemukan'];
    }
    if ((int)($order['synced_to_pos'] ?? 0) === 1) {
        return ['status' => 'skipped', 'message' => 'Order sudah tersinkron sebelumnya'];
    }

    $itemStmt = $pdo->prepare('SELECT oi.*, p.pos_sku FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
    $itemStmt->execute([$orderId]);
    $itemRows = $itemStmt->fetchAll();

    $items = [];
    foreach ($itemRows as $it) {
        $items[] = [
            'sku'   => $it['pos_sku'] ?? '',
            'nama'  => $it['nama_produk_snapshot'],
            'harga' => (float)$it['harga_snapshot'],
            'qty'   => (int)$it['qty'],
        ];
    }

    $cabang = $conn->real_escape_string($order['branch_nama']);
    $petugas = $conn->real_escape_string('Order Online');
    $total = (int)$order['total_bayar'];
    $metode = 'QR_ONLINE';
    $itemsJson = $conn->real_escape_string(json_encode($items));
    $pelangganNama = $conn->real_escape_string($order['nama_customer']);
    $pelangganTelepon = $conn->real_escape_string($order['no_wa']);

    $sql = "INSERT INTO transaksi (waktu, cabang, petugas, total, metode, items_json, pelanggan_nama, pelanggan_telepon)
        VALUES (NOW(), '$cabang', '$petugas', $total, '$metode', '$itemsJson', '$pelangganNama', '$pelangganTelepon')";
    $ok = $conn->query($sql);

    if (!$ok) {
        return ['status' => 'error', 'message' => 'Gagal menyimpan ke transaksi POS: ' . $conn->error];
    }

    $pdo->prepare('UPDATE orders SET synced_to_pos = 1 WHERE id = ?')->execute([$orderId]);

    return ['status' => 'success', 'transaksi_id' => $conn->insert_id];
}
