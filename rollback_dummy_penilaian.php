<?php
/**
 * rollback_dummy_penilaian.php — Hapus SEMUA data dummy yang dibuat
 * seed_dummy_penilaian.php (baris dengan is_dummy_seed=1 di
 * penilaian_assignment & penilaian_submission, plus detail-nya via FK).
 *
 * Jalankan via CLI SAJA: php rollback_dummy_penilaian.php
 *
 * Periode yang dibuat KHUSUS oleh seeder (dibuat_oleh = 'seed_dummy_penilaian.php')
 * juga ikut dihapus di sini, supaya kondisi kembali PERSIS seperti sebelum
 * seeding (tidak ada periode "aktif tapi kosong" tersisa). Periode yang
 * dibuat VIP asli TIDAK PERNAH disentuh.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("❌ Script ini HANYA boleh dijalankan lewat CLI, bukan via web browser.\n");
}
if (!file_exists(__DIR__ . '/dev.flag')) {
    fwrite(STDERR, "❌ GUARD: file dev.flag tidak ditemukan. Script ini HANYA boleh jalan di environment development.\n");
    exit(1);
}

$conn = @new mysqli('localhost', 'root', '', 'u173485424_hoki');
if (!$conn || $conn->connect_error) {
    fwrite(STDERR, "❌ Koneksi database gagal: " . ($conn ? $conn->connect_error : 'unknown') . "\n");
    exit(1);
}
$conn->set_charset('utf8mb4');

function line(string $s = ''): void { echo $s . "\n"; }
function hr(): void { line(str_repeat('─', 78)); }

const SEED_MARKER = 'seed_dummy_penilaian.php';

hr();
line("🧹 ROLLBACK DATA DUMMY PENILAIAN REKAN KERJA — " . date('Y-m-d H:i:s'));
hr();

// Pastikan kolomnya ada (kalau seeder belum pernah jalan sama sekali, kolom
// mungkin belum ada - aman, tidak error).
$hasCol = true;
foreach (['penilaian_assignment', 'penilaian_submission'] as $tbl) {
    $chk = $conn->query("SHOW COLUMNS FROM `$tbl` LIKE 'is_dummy_seed'");
    if (!$chk || $chk->num_rows === 0) { $hasCol = false; }
}
if (!$hasCol) {
    line("ℹ️  Kolom is_dummy_seed belum ada di salah satu tabel - berarti seeder belum pernah dijalankan. Tidak ada yang perlu dirollback.");
    exit(0);
}

// ── Hapus submission_detail milik submission dummy ──
$res = $conn->query("SELECT id FROM penilaian_submission WHERE is_dummy_seed = 1");
$dummySubIds = $res ? array_map('intval', array_column($res->fetch_all(MYSQLI_ASSOC), 'id')) : [];

$jumlahDetail = 0;
if ($dummySubIds) {
    $ph = implode(',', array_fill(0, count($dummySubIds), '?'));
    $stmt = $conn->prepare("DELETE FROM penilaian_submission_detail WHERE submission_id IN ($ph)");
    $stmt->bind_param(str_repeat('i', count($dummySubIds)), ...$dummySubIds);
    $stmt->execute();
    $jumlahDetail = $stmt->affected_rows;
}
line("🗑️  Hapus penilaian_submission_detail (milik submission dummy): $jumlahDetail baris");

// ── Hapus submission dummy ──
$conn->query("DELETE FROM penilaian_submission WHERE is_dummy_seed = 1");
$jumlahSub = $conn->affected_rows;
line("🗑️  Hapus penilaian_submission (is_dummy_seed=1): $jumlahSub baris");

// ── Hapus assignment dummy ──
$conn->query("DELETE FROM penilaian_assignment WHERE is_dummy_seed = 1");
$jumlahAssign = $conn->affected_rows;
line("🗑️  Hapus penilaian_assignment (is_dummy_seed=1): $jumlahAssign baris");

// ── Hapus periode yang dibuat KHUSUS oleh seeder (dibuat_oleh = marker) ──
$marker = SEED_MARKER;
$stmt = $conn->prepare("SELECT id FROM penilaian_periode WHERE dibuat_oleh = ?");
$stmt->bind_param('s', $marker);
$stmt->execute();
$seederPeriode = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$jumlahPeriode = 0;
foreach ($seederPeriode as $p) {
    $pid = (int)$p['id'];
    // Jaga-jaga: kalau ternyata masih ada submission/assignment NON-dummy yang
    // nempel ke periode ini (mestinya tidak mungkin, tapi defensif), jangan hapus.
    $chkSub = $conn->query("SELECT COUNT(*) c FROM penilaian_submission WHERE periode_id=$pid");
    $sisaSub = (int)$chkSub->fetch_assoc()['c'];
    if ($sisaSub > 0) {
        line("⚠️  Periode #$pid (dibuat seeder) masih punya $sisaSub submission tersisa (bukan dummy?) - TIDAK dihapus, cek manual.");
        continue;
    }
    $conn->query("DELETE FROM penilaian_periode WHERE id=$pid");
    $jumlahPeriode++;
    line("🗑️  Hapus periode #$pid (dibuat khusus oleh seeder, sudah kosong)");
}
if (!$seederPeriode) {
    line("ℹ️  Tidak ada periode yang dibuat khusus oleh seeder (berarti seeder tadinya memakai periode aktif yang sudah ada milik VIP asli - dibiarkan, tidak disentuh).");
}

hr();
line("✅ ROLLBACK SELESAI.");
line("   Total dihapus: $jumlahDetail detail, $jumlahSub submission, $jumlahAssign assignment, $jumlahPeriode periode.");
line("   Data asli (bukan dummy) TIDAK disentuh sama sekali.");
line("   Silakan cek ulang tab 'Penilaian Rekan Kerja' - harus kembali kosong seperti sebelum seeding.");
hr();
