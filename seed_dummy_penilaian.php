<?php
/**
 * seed_dummy_penilaian.php — Seeder QA untuk fitur Peer Review.
 *
 * Jalankan via CLI SAJA: php seed_dummy_penilaian.php [--allow-small]
 *
 * TUJUAN: bikin data assignment + submission dummy yang realistis di
 * environment DEVELOPMENT, supaya perhitungan skor peer review (rata-rata
 * ÷ 100 x 45%, pembagi = jumlah yang BENAR-BENAR submit) bisa diverifikasi
 * manual sebelum dipakai staff sungguhan.
 *
 * GUARD: TIDAK BISA jalan via web (harus CLI) & WAJIB ada file dev.flag di
 * root project (penanda dev, pola yang sama dipakai api.php/api-penilaian.php
 * untuk deteksi environment - tapi di CLI tidak ada HTTP_HOST jadi dev.flag
 * yang jadi sinyal utama di sini).
 *
 * --allow-small : override guard "minimal 5 staff" (dipakai kalau data
 * staff di dev DB memang belum sebanyak itu, sudah dikonfirmasi user).
 * Tanpa flag ini, skrip BERHENTI kalau staff target (Staff/Senior
 * Staff/SPV) kurang dari 5 orang - TIDAK PERNAH generate user fiktif.
 */

// ── GUARD ENVIRONMENT ────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("❌ Script ini HANYA boleh dijalankan lewat CLI (`php seed_dummy_penilaian.php`), bukan via web browser.\n");
}
if (!file_exists(__DIR__ . '/dev.flag')) {
    fwrite(STDERR, "❌ GUARD: file dev.flag tidak ditemukan di root project.\n");
    fwrite(STDERR, "   Script ini HANYA boleh jalan di environment development.\n");
    fwrite(STDERR, "   Kalau ini memang environment dev, buat file kosong 'dev.flag' di root project dulu.\n");
    exit(1);
}
define('APP_ENV', 'development');

$allowSmall = in_array('--allow-small', $argv, true);

// ── KONEKSI DB (dev only - hardcode root/'' konsisten dengan pola isDev di api.php) ──
$conn = @new mysqli('localhost', 'root', '', 'u173485424_hoki');
if (!$conn || $conn->connect_error) {
    fwrite(STDERR, "❌ Koneksi database gagal: " . ($conn ? $conn->connect_error : 'unknown') . "\n");
    exit(1);
}
$conn->set_charset('utf8mb4');

function line(string $s = ''): void { echo $s . "\n"; }
function hr(): void { line(str_repeat('─', 78)); }

// ── PASTIKAN KOLOM is_dummy_seed ADA (idempotent, pola SHOW COLUMNS spt api.php) ──
foreach (['penilaian_assignment', 'penilaian_submission'] as $tbl) {
    $chk = $conn->query("SHOW COLUMNS FROM `$tbl` LIKE 'is_dummy_seed'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE `$tbl` ADD COLUMN is_dummy_seed TINYINT(1) NOT NULL DEFAULT 0");
        line("🔧 Kolom is_dummy_seed ditambahkan ke $tbl");
    }
}

const SEED_MARKER = 'seed_dummy_penilaian.php';

hr();
line("🌱 SEED DUMMY PENILAIAN REKAN KERJA (QA) — " . date('Y-m-d H:i:s'));
hr();

// ── 1. AMBIL STAFF TARGET (Staff/Senior Staff/SPV) — TIDAK BUAT USER BARU ──
// (Dicek DULUAN, SEBELUM ada tulisan apapun ke DB - supaya kalau guard "minimal
// 5 staff" berhenti, skrip benar-benar belum menulis apa-apa sama sekali,
// termasuk belum bikin periode baru.)
$targetRoles = ['Staff', 'Senior Staff', 'SPV'];
$ph = implode(',', array_fill(0, count($targetRoles), '?'));
$stmt = $conn->prepare("SELECT id, username, fullName, role, cabang FROM users WHERE role IN ($ph) ORDER BY fullName ASC");
$stmt->bind_param(str_repeat('s', count($targetRoles)), ...$targetRoles);
$stmt->execute();
$staffPool = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

line("👥 Staff target ditemukan: " . count($staffPool) . " orang (" . implode(', ', array_column($staffPool, 'fullName')) . ")");

if (count($staffPool) < 5 && !$allowSmall) {
    hr();
    line("🛑 BERHENTI: staff target (Staff/Senior Staff/SPV) cuma " . count($staffPool) . " orang, kurang dari minimal 5.");
    line("   Sesuai instruksi: skrip TIDAK boleh generate user/staff fiktif. BELUM ADA data ditulis ke DB.");
    line("   Opsi: (a) tambah staff asli dulu di dev DB, atau");
    line("         (b) jalankan ulang dengan flag: php seed_dummy_penilaian.php --allow-small");
    line("             (variasi assignment otomatis diperkecil menyesuaikan jumlah staff yang ada)");
    hr();
    exit(1);
}
if (count($staffPool) < 2) {
    line("🛑 BERHENTI: minimal butuh 2 staff target untuk simulasi assignment peer-to-peer. Cuma ada " . count($staffPool) . ". BELUM ADA data ditulis ke DB.");
    exit(1);
}
if ($allowSmall && count($staffPool) < 5) {
    line("⚠️  Jalan dengan --allow-small: skala assignment diperkecil (1-2 target/penilai, bukan 2-4) karena cuma " . count($staffPool) . " staff.");
}

// ── 2. PASTIKAN ADA PERIODE AKTIF (baru mulai menulis ke DB dari sini) ──
$periode = null;
$res = $conn->query("SELECT * FROM penilaian_periode WHERE status='aktif' ORDER BY id DESC LIMIT 1");
if ($res && $res->num_rows > 0) {
    $periode = $res->fetch_assoc();
    line("📅 Memakai periode AKTIF yang sudah ada (bukan dummy, tidak disentuh): #{$periode['id']} ({$periode['tanggal_mulai']} – {$periode['tanggal_selesai']})");
} else {
    $mulai   = date('Y-m-01');
    $selesai = date('Y-m-t');
    $stmt = $conn->prepare("INSERT INTO penilaian_periode (tanggal_mulai, tanggal_selesai, status, dibuat_oleh) VALUES (?,?,'aktif',?)");
    $stmt->bind_param('sss', $mulai, $selesai, SEED_MARKER);
    $stmt->execute();
    $periode = ['id' => $conn->insert_id, 'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai];
    line("📅 Tidak ada periode aktif — dibuat periode baru KHUSUS SEEDER: #{$periode['id']} ({$mulai} – {$selesai})");
    line("   (ditandai dibuat_oleh='" . SEED_MARKER . "' - rollback_dummy_penilaian.php akan otomatis hapus periode ini juga, karena bukan dibuat VIP asli)");
}
$periodeId = (int)$periode['id'];
line("");

// ── 3. AMBIL OWNER/VIP (AUTO-RATE-ALL) — tidak perlu baris assignment ──
$res = $conn->query("SELECT id, username, fullName, role FROM users WHERE role IN ('Owner','VIP') ORDER BY role, fullName");
$autoRaters = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
line("👑 Auto-rater (Owner/VIP, tidak butuh baris assignment): " . count($autoRaters) . " orang (" . implode(', ', array_column($autoRaters, 'fullName')) . ")");
hr();

// ── HELPER: template yang berlaku untuk sebuah role SAAT INI ──────────
function get_template_for_role(mysqli $conn, string $role): ?array
{
    $stmt = $conn->prepare("SELECT t.id, t.kode, t.nama FROM penilaian_role_template_map m
                             JOIN penilaian_template t ON t.id = m.template_id
                             WHERE m.role = ? AND m.aktif = 1 LIMIT 1");
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
}

function get_pertanyaan(mysqli $conn, int $templateId): array
{
    $stmt = $conn->prepare("SELECT id FROM penilaian_pertanyaan WHERE template_id = ?");
    $stmt->bind_param('i', $templateId);
    $stmt->execute();
    return array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
}

/** Random skor 1-5 dengan bobot (persen), bisa digeser per target buat variasi bias. */
function skor_acak(array $bobot): int
{
    $roll = mt_rand(1, 100);
    $kumulatif = 0;
    foreach ($bobot as $skor => $persen) {
        $kumulatif += $persen;
        if ($roll <= $kumulatif) return (int)$skor;
    }
    return 3;
}

const BOBOT_NORMAL = [1 => 5, 2 => 10, 3 => 25, 4 => 35, 5 => 25];
const BOBOT_TINGGI = [1 => 2, 2 => 5, 3 => 15, 4 => 38, 5 => 40]; // bias skor lebih tinggi
const BOBOT_RENDAH = [1 => 10, 2 => 20, 3 => 35, 4 => 25, 5 => 10]; // bias skor lebih rendah

/** Buat 1 baris assignment dummy kalau belum ada (skip kalau sudah ada apapun - jangan overwrite). */
function pastikan_assignment(mysqli $conn, int $penilaiId, int $dinilaiId): string
{
    $chk = $conn->prepare("SELECT id FROM penilaian_assignment WHERE penilai_user_id=? AND dinilai_user_id=?");
    $chk->bind_param('ii', $penilaiId, $dinilaiId);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) return 'sudah_ada';

    $stmt = $conn->prepare("INSERT INTO penilaian_assignment (penilai_user_id, dinilai_user_id, aktif, is_dummy_seed) VALUES (?,?,1,1)");
    $stmt->bind_param('ii', $penilaiId, $dinilaiId);
    $stmt->execute();
    return 'dibuat';
}

/** Buat submission dummy + detail kalau belum ada. Return total_skor kalau dibuat, null kalau di-skip. */
function buat_submission(mysqli $conn, int $periodeId, int $penilaiId, int $dinilaiId, string $dinilaiRole, array $bobot): ?int
{
    $chk = $conn->prepare("SELECT id FROM penilaian_submission WHERE periode_id=? AND penilai_user_id=? AND dinilai_user_id=?");
    $chk->bind_param('iii', $periodeId, $penilaiId, $dinilaiId);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) return null; // sudah ada (asli atau dummy lama) - jangan overwrite

    $template = get_template_for_role($conn, $dinilaiRole);
    if (!$template) return null;
    $pertanyaanIds = get_pertanyaan($conn, (int)$template['id']);
    if (!$pertanyaanIds) return null;

    $totalSkor = 0;
    $jawaban = [];
    foreach ($pertanyaanIds as $pid) {
        $s = skor_acak($bobot);
        $jawaban[$pid] = $s;
        $totalSkor += $s;
    }

    $conn->begin_transaction();
    $ins = $conn->prepare("INSERT INTO penilaian_submission (periode_id, penilai_user_id, dinilai_user_id, template_id_snapshot, total_skor, locked, is_dummy_seed) VALUES (?,?,?,?,?,1,1)");
    $ins->bind_param('iiiii', $periodeId, $penilaiId, $dinilaiId, $template['id'], $totalSkor);
    $ins->execute();
    $subId = $conn->insert_id;

    $insD = $conn->prepare("INSERT INTO penilaian_submission_detail (submission_id, pertanyaan_id, skor) VALUES (?,?,?)");
    foreach ($jawaban as $pid => $skor) {
        $insD->bind_param('iii', $subId, $pid, $skor);
        $insD->execute();
    }
    $conn->commit();

    return $totalSkor;
}

$byId = [];
foreach ($staffPool as $s) $byId[$s['id']] = $s;

// Tentukan 3 skenario khusus dari staffPool LEBIH DULU (sebelum bikin assignment),
// supaya skenario "partial" (target #2) bisa DIKECUALIKAN dari pool target random
// di bawah - kalau tidak, jumlah peer yang kebetulan menilai dia jadi acak & tidak
// bisa dipastikan hasilnya "4 direncanakan, 3 submit" (soalnya dia bakal murni
// dikontrol dari 4 auto-rater yang dipilih manual, bukan campur assignment acak).
$skenarioNolSubmission = $staffPool[0]['id'] ?? null; // target #1: 0 submission
$skenarioPartial       = $staffPool[1]['id'] ?? null; // target #2: assigned direncanakan 4, submit 3
$skenarioNormal        = $staffPool[2]['id'] ?? null; // target #3 dst: normal, bias tinggi

line("🎯 Skenario QA yang sengaja dibuat:");
if ($skenarioNolSubmission) line("   - " . $byId[$skenarioNolSubmission]['fullName'] . " → 0 submission sama sekali (tes 'Belum ada data')");
if ($skenarioPartial)       line("   - " . $byId[$skenarioPartial]['fullName']       . " → 4 penilai direncanakan (murni dari auto-rater Owner/VIP), cuma 3 yang submit (tes pembagi dinamis)");
if ($skenarioNormal)        line("   - " . $byId[$skenarioNormal]['fullName']        . " → submission lengkap, bias skor TINGGI");
line("");

// ── 4. ASSIGNMENT DUMMY ANTAR-STAFF (skip kalau penilai sudah punya assignment) ──
line("🔗 ASSIGNMENT DUMMY (staff biasa → staff biasa)");
hr();

// Cari pasangan penilai->target per staff, variasi jumlah target per penilai.
// Skala kecil (--allow-small, pool cuma 3): sandivr dapat 2 target (maksimum
// yang mungkin), ibnusub & fatihmun masing2 1 target - supaya tetap ada variasi
// walau pool terbatas. Target skenario "partial" DIKECUALIKAN dari pool acak ini
// (lihat catatan di atas) supaya jumlah penilainya murni terkontrol dari auto-rater.
$assignmentPlan = []; // [penilaiId => [targetId, ...]]
foreach ($staffPool as $i => $penilai) {
    $lainnya = array_values(array_filter($staffPool, fn($s) => $s['id'] !== $penilai['id'] && $s['id'] !== $skenarioPartial));
    if (!$lainnya) continue;

    // Cek dulu apakah penilai ini SUDAH punya assignment (real, bukan dummy) - kalau
    // sudah ada assignment apapun untuknya, jangan tambah dummy (sesuai instruksi).
    $chkExisting = $conn->prepare("SELECT COUNT(*) c FROM penilaian_assignment WHERE penilai_user_id=?");
    $chkExisting->bind_param('i', $penilai['id']);
    $chkExisting->execute();
    $sudahPunya = (int)$chkExisting->get_result()->fetch_assoc()['c'];
    if ($sudahPunya > 0) {
        line("⏭️  {$penilai['fullName']} sudah punya assignment sebelumnya ({$sudahPunya} baris) — dilewati, tidak ditambah dummy.");
        continue;
    }

    // Variasi jumlah target: kalau pool kecil, batasi ke jumlah yang tersedia.
    $maxTarget = count($lainnya);
    $jumlahTarget = $allowSmall
        ? ($i === 0 ? min(2, $maxTarget) : min(1, $maxTarget)) // staff pertama dapat 2 (kalau ada), sisanya 1
        : min($maxTarget, [2, 3, 4][array_rand([2, 3, 4])]);
    shuffle($lainnya);
    $dipilih = array_slice($lainnya, 0, $jumlahTarget);
    $assignmentPlan[$penilai['id']] = array_column($dipilih, 'id');

    foreach ($dipilih as $target) {
        $hasil = pastikan_assignment($conn, (int)$penilai['id'], (int)$target['id']);
        line(sprintf("   %s -> menilai %s [%s]", $penilai['fullName'], $target['fullName'], $hasil));
    }
}

hr();
line("");
line("📝 SUBMISSION DUMMY");
hr();

// Untuk pelaporan manual per target di langkah akhir.
$laporan = []; // [targetId => ['nama'=>, 'role'=>, 'template'=>, 'submisi'=>[skor,...], 'catatan'=>...]]
foreach ($staffPool as $t) {
    $laporan[$t['id']] = ['nama' => $t['fullName'], 'role' => $t['role'], 'template' => null, 'submisi' => [], 'catatan' => ''];
}

// 4a. Submission dari assignment PEER (skip kalau targetnya = skenarioNolSubmission).
foreach ($assignmentPlan as $penilaiId => $targetIds) {
    foreach ($targetIds as $targetId) {
        if ($targetId === $skenarioNolSubmission) {
            line(sprintf("   ⏭️  SKIP sengaja: %s -> %s (target skenario 0-submission)", $byId[$penilaiId]['fullName'], $byId[$targetId]['fullName']));
            continue;
        }
        $bobot = ($targetId === $skenarioNormal) ? BOBOT_TINGGI : (($targetId === $skenarioPartial) ? BOBOT_RENDAH : BOBOT_NORMAL);
        $skor = buat_submission($conn, $periodeId, $penilaiId, $targetId, $byId[$targetId]['role'], $bobot);
        if ($skor !== null) {
            $laporan[$targetId]['submisi'][] = ['dari' => $byId[$penilaiId]['fullName'], 'skor' => $skor];
            line(sprintf("   ✅ %s -> %s : total_skor=%d", $byId[$penilaiId]['fullName'], $byId[$targetId]['fullName'], $skor));
        }
    }
}

// 4b. Submission dari AUTO-RATER (Owner/VIP) ke semua target, KECUALI:
//     - skenarioNolSubmission: skip semua
//     - skenarioPartial: DIKECUALIKAN dari assignment peer (lihat di atas), jadi 4 "direncanakan"
//       di sini murni dari 4 auto-rater Owner/VIP, 1 di antaranya sengaja dibatalkan -> submit 3.
$autoRatersUntukPartial = array_slice($autoRaters, 0, 4); // 4 auto-rater "direncanakan" utk skenario partial
$autoRaterSkipped = null;
foreach ($autoRaters as $rater) {
    foreach ($staffPool as $target) {
        $targetId = $target['id'];
        if ($targetId === $skenarioNolSubmission) continue; // skip total, tidak usah di-log satu2 (banyak)

        if ($targetId === $skenarioPartial) {
            $termasukRencana = in_array($rater, $autoRatersUntukPartial, true);
            if (!$termasukRencana) continue; // bukan bagian dari 4 yang direncanakan
        }

        $bobot = ($targetId === $skenarioNormal) ? BOBOT_TINGGI : (($targetId === $skenarioPartial) ? BOBOT_RENDAH : BOBOT_NORMAL);
        $skor = buat_submission($conn, $periodeId, (int)$rater['id'], (int)$targetId, $target['role'], $bobot);
        if ($skor !== null) {
            $laporan[$targetId]['submisi'][] = ['dari' => $rater['fullName'], 'skor' => $skor];
        }
    }
}

// Untuk skenario partial: sengaja SKIP 1 dari 4 auto-rater yang direncanakan,
// supaya "direncanakan 4, submit 3".
if ($skenarioPartial && count($autoRatersUntukPartial) >= 4) {
    $yangDiskip = $autoRatersUntukPartial[3]; // yang ke-4 sengaja tidak jadi disubmit
    // Hapus submission yang sudah kadung dibuat utk auto-rater ke-2 ini (supaya jadi "direncanakan tapi tidak submit").
    $del = $conn->prepare("SELECT id FROM penilaian_submission WHERE periode_id=? AND penilai_user_id=? AND dinilai_user_id=? AND is_dummy_seed=1");
    $del->bind_param('iii', $periodeId, $yangDiskip['id'], $skenarioPartial);
    $del->execute();
    $row = $del->get_result()->fetch_assoc();
    if ($row) {
        $subId = (int)$row['id'];
        $conn->query("DELETE FROM penilaian_submission_detail WHERE submission_id=$subId");
        $conn->query("DELETE FROM penilaian_submission WHERE id=$subId");
        // Buang juga dari laporan.
        $laporan[$skenarioPartial]['submisi'] = array_values(array_filter(
            $laporan[$skenarioPartial]['submisi'],
            fn($s) => $s['dari'] !== $yangDiskip['fullName']
        ));
        line(sprintf("   🗑️  Sengaja DIBATALKAN (direncanakan tapi tidak submit): %s -> %s", $yangDiskip['fullName'], $byId[$skenarioPartial]['fullName']));
    }
}

hr();
line("");
line("📊 HASIL PERHITUNGAN MANUAL (pembanding untuk verifikasi UI)");
hr();

foreach ($staffPool as $t) {
    $tid = $t['id'];
    $template = get_template_for_role($conn, $t['role']);
    $submisi = $laporan[$tid]['submisi'];
    $jumlah = count($submisi);

    line("");
    line("▶ {$t['fullName']}  (role: {$t['role']}, template: " . ($template ? "{$template['kode']} - {$template['nama']}" : 'BELUM DIPETAKAN') . ")");

    if ($tid === $skenarioPartial) {
        line("   Skenario: 4 penilai DIRENCANAKAN, 1 sengaja tidak submit -> harus tersisa 3 submission aktual.");
    }
    if ($tid === $skenarioNolSubmission) {
        line("   Skenario: SEMUA penilai sengaja di-skip -> harus 0 submission, expect 'Belum ada data' di UI.");
    }
    if ($tid === $skenarioNormal) {
        line("   Skenario: submission lengkap, bias skor TINGGI (leaderboard pembanding).");
    }

    if ($jumlah === 0) {
        line("   Jumlah submission: 0");
        line("   >> HASIL: Belum ada data (bukan 0%)");
        continue;
    }

    $skorList = array_column($submisi, 'skor');
    foreach ($submisi as $s) {
        line(sprintf("     - dari %-28s total_skor = %d", $s['dari'], $s['skor']));
    }
    $rata = array_sum($skorList) / $jumlah;
    $bagi100 = $rata / 100;
    $kali45 = $bagi100 * 45;

    line(sprintf("   Jumlah yang BENAR-BENAR submit : %d", $jumlah));
    line(sprintf("   Rata-rata total_skor           : %.4f  (%s / %d)", $rata, implode('+', $skorList), $jumlah));
    line(sprintf("   Rata-rata ÷ 100                : %.4f", $bagi100));
    line(sprintf("   Hasil akhir (x 45%%)            : %.2f", $kali45));
}

hr();
line("");
line("✅ SELESAI. Ringkasan:");
line("   - Periode aktif dipakai: #$periodeId");
line("   - Total assignment dummy dibuat: cek tabel penilaian_assignment WHERE is_dummy_seed=1");
line("   - Total submission dummy dibuat: cek tabel penilaian_submission WHERE is_dummy_seed=1");
line("");
line("📋 CARA VERIFIKASI:");
line("   1. Login sebagai VIP -> buka manajemen_penilaian.html -> lihat tabel Monitoring,");
line("      cocokkan 'Jumlah Penilai' & nama-nama penilai per staff dengan tabel di atas.");
line("   2. Login sebagai masing-masing staff target -> performa.html -> tab 'Penilaian");
line("      Rekan Kerja' -> bandingkan angka 'Skor Peer Review Anda' dengan kolom");
line("      'Hasil akhir (x 45%)' di atas, per staff.");
line("   3. Pastikan staff dengan 0 submission tampil 'Belum ada data', BUKAN 0% atau error.");
line("   4. Kalau semua cocok, jalankan: php rollback_dummy_penilaian.php");
hr();
