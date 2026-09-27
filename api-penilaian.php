<?php
/**
 * api-penilaian.php — Backend Peer Review / Penilaian Rekan Kerja.
 * File terpisah dari api.php (mengikuti pola api-cek-order-baru.php,
 * api-pelamar-kerja.php dkk).
 *
 * Role yang bisa jadi TARGET (dinilai) cuma 3: Staff, Senior Staff, SPV.
 * Owner & VIP = auto-rate-all (otomatis jadi penilai utk SEMUA target di
 * atas, tanpa baris assignment) - mereka sendiri TIDAK PERNAH jadi target.
 * Endpoint admin_* HANYA untuk role VIP (bukan Owner), sesuai halaman
 * manajemen_penilaian.html yang VIP-only.
 *
 * Template pertanyaan (A/B/C) ditentukan MURNI dari role TARGET yang
 * dinilai (bukan role penilai), lewat mapping data-driven di tabel
 * penilaian_role_template_map (bukan hardcode if/else). Saat submit,
 * template yang berlaku SAAT ITU disimpan sebagai template_id_snapshot -
 * kalau role staff berubah belakangan, submission lama tidak ikut berubah.
 */

header('Content-Type: application/json');

// ── Koneksi database utama (sama seperti api.php) ──
$host  = $_SERVER['HTTP_HOST'] ?? '';
$isDev = (
    $host === 'localhost' ||
    $host === '127.0.0.1' ||
    substr($host, 0, 8) === '192.168.' ||
    strpos($host, 'localhost') !== false ||
    strpos($host, ':') !== false ||
    file_exists(__DIR__ . '/dev.flag')
);

if ($isDev) {
    $conn = @new mysqli("localhost", "root", "", "u173485424_hoki");
} else {
    $conn = @new mysqli("localhost", "u173485424_kurniarp", "Alpukat19#", "u173485424_hoki");
}

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.']);
    exit;
}
$conn->set_charset('utf8mb4');

// Role yang bisa jadi TARGET penilaian - SPV disiapkan di sistem walau
// belum ada orangnya sekarang.
const TARGET_ROLES = ['Staff', 'Senior Staff', 'SPV'];

function respond(array $data): void { echo json_encode($data); exit; }

/** Verifikasi identitas & role ke DB via session_token - tidak pernah percaya role dari client. */
function verify_actor(mysqli $conn, string $user, string $token): ?array
{
    if ($user === '' || $token === '') return null;
    $stmt = $conn->prepare("SELECT id, username, fullName, role FROM users WHERE LOWER(username) = LOWER(?) AND session_token = ? AND session_token != ''");
    $stmt->bind_param('ss', $user, $token);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
}

function require_actor(mysqli $conn): array
{
    global $input;
    $u = trim($_GET['user'] ?? $_POST['user'] ?? $input['user'] ?? '');
    $t = trim($_GET['token'] ?? $_POST['token'] ?? $input['token'] ?? '');
    $actor = verify_actor($conn, $u, $t);
    if (!$actor) {
        http_response_code(401);
        respond(['status' => 'error', 'message' => 'Sesi tidak valid atau sudah berakhir. Silakan login ulang.']);
    }
    return $actor;
}

function require_vip(mysqli $conn): array
{
    $actor = require_actor($conn);
    if ($actor['role'] !== 'VIP') {
        http_response_code(403);
        respond(['status' => 'error', 'message' => 'Akses ditolak. Fitur ini khusus VIP.']);
    }
    return $actor;
}

function is_admin_role(string $role): bool
{
    return in_array($role, ['Owner', 'VIP'], true);
}

/** Daftar staff yang BOLEH jadi target penilaian = role Staff/Senior Staff/SPV saja. */
function get_staff_pool(mysqli $conn): array
{
    $roles = TARGET_ROLES;
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $stmt = $conn->prepare("SELECT id, username, fullName, role FROM users
                             WHERE role IN ($placeholders)
                             ORDER BY fullName ASC");
    $stmt->bind_param(str_repeat('s', count($roles)), ...$roles);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function get_periode_aktif(mysqli $conn): ?array
{
    $res = $conn->query("SELECT * FROM penilaian_periode WHERE status='aktif' ORDER BY id DESC LIMIT 1");
    return ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
}

/**
 * Template yang berlaku untuk sebuah role SAAT INI (data-driven, dari
 * penilaian_role_template_map - bukan hardcode). Null kalau role itu belum
 * dipetakan ke template manapun.
 */
function get_template_for_role(mysqli $conn, string $role): ?array
{
    $stmt = $conn->prepare("SELECT t.id, t.kode, t.nama, t.deskripsi
                             FROM penilaian_role_template_map m
                             JOIN penilaian_template t ON t.id = m.template_id
                             WHERE m.role = ? AND m.aktif = 1
                             LIMIT 1");
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
}

/** Target yang HARUS dinilai oleh $penilai (auto-all kalau Owner/VIP, else dari assignment),
 *  masing-masing dilengkapi info template yang berlaku SAAT INI berdasarkan role-nya. */
function get_targets_for_penilai(mysqli $conn, int $penilaiId, string $penilaiRole): array
{
    if (is_admin_role($penilaiRole)) {
        $targets = get_staff_pool($conn);
    } else {
        $stmt = $conn->prepare("SELECT u.id, u.username, u.fullName, u.role
                                 FROM penilaian_assignment a
                                 JOIN users u ON u.id = a.dinilai_user_id
                                 WHERE a.penilai_user_id = ? AND a.aktif = 1
                                 ORDER BY u.fullName ASC");
        $stmt->bind_param('i', $penilaiId);
        $stmt->execute();
        $res = $stmt->get_result();
        $targets = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    $templateCache = [];
    foreach ($targets as &$t) {
        if (!array_key_exists($t['role'], $templateCache)) {
            $templateCache[$t['role']] = get_template_for_role($conn, $t['role']);
        }
        $t['template'] = $templateCache[$t['role']];
    }
    unset($t);

    return $targets;
}

/** Skor peer (null kalau 0 submission) = (rata-rata total_skor / 100) x 45. */
function hitung_skor_peer(mysqli $conn, int $periodeId, int $userId): ?array
{
    $stmt = $conn->prepare("SELECT AVG(total_skor) AS rata, COUNT(*) AS jumlah
                             FROM penilaian_submission
                             WHERE periode_id = ? AND dinilai_user_id = ?");
    $stmt->bind_param('ii', $periodeId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $jumlah = (int)($row['jumlah'] ?? 0);
    if ($jumlah === 0) return null;
    $rata = (float)$row['rata'];
    return [
        'jumlah_penilai' => $jumlah,
        'rata_rata_100'  => round($rata, 2),
        'skor_45'        => round(($rata / 100) * 45, 2),
    ];
}

/**
 * Hari-kerja & omset per staff dalam rentang tanggal [tanggal_mulai, tanggal_selesai]
 * sebuah periode - query & pencocokan petugas IDENTIK dengan yang dipakai action
 * get_performa di api.php (tabel laporan_settlement, cocokkan username/fullName
 * di kolom petugas), cuma di sini di-index by user id & selalu di-scope ke rentang
 * tanggal periode_aktif (bukan filter Mingguan/Bulanan/Custom yang dipakai tab
 * pertama performa.html).
 */
function hitung_absensi_omset_periode(mysqli $conn, array $periode, array $usersForAgg): array
{
    $start = $conn->real_escape_string($periode['tanggal_mulai']);
    $end   = $conn->real_escape_string($periode['tanggal_selesai']);
    $resRows = $conn->query("SELECT waktu, petugas, grand_total FROM laporan_settlement WHERE waktu >= '$start 00:00:00' AND waktu <= '$end 23:59:59'");
    $rows = $resRows ? $resRows->fetch_all(MYSQLI_ASSOC) : [];

    $result = [];
    foreach ($usersForAgg as $u) {
        $result[(int)$u['id']] = ['omset' => 0, 'tanggal' => []];
    }

    foreach ($rows as $row) {
        $tgl = date('Y-m-d', strtotime($row['waktu']));
        $petugasNames = array_map('trim', preg_split('/[,&]/', $row['petugas']));
        $petugasNamesLower = array_map('strtolower', $petugasNames);
        foreach ($usersForAgg as $u) {
            $uid = (int)$u['id'];
            if (in_array(strtolower($u['username']), $petugasNamesLower) || in_array(strtolower($u['fullName']), $petugasNamesLower)) {
                $result[$uid]['omset'] += (int)$row['grand_total'];
                $result[$uid]['tanggal'][$tgl] = true;
            }
        }
    }

    $out = [];
    foreach ($result as $uid => $v) {
        $out[$uid] = ['omset' => $v['omset'], 'hari_kerja' => count($v['tanggal'])];
    }
    return $out;
}

/**
 * Skor Total 1 staff = Absensi (30%) + Omset (25%) + Peer Review (45%).
 * Absensi selalu lengkap (data kehadiran selalu ada sebagai angka nyata,
 * minimal 0). Omset butuh target_nominal > 0 di penilaian_target_omset
 * utk periode ini, kalau belum diset -> status 'target_belum_diset'. Peer
 * butuh minimal 1 submission, kalau belum -> 'belum_ada_data'. Skor Total
 * HANYA dijumlah kalau Omset & Peer keduanya lengkap - tidak ada penjumlahan
 * parsial (sesuai spec), tapi komponen yang SUDAH ada tetap ditampilkan.
 */
function hitung_skor_total_staff(mysqli $conn, array $periode, array $staff, array $absensiOmsetMap): array
{
    $uid = (int)$staff['id'];
    $hariKalender = ((strtotime($periode['tanggal_selesai']) - strtotime($periode['tanggal_mulai'])) / 86400) + 1;
    $hariKerja    = $absensiOmsetMap[$uid]['hari_kerja'] ?? 0;
    $omsetAktual  = $absensiOmsetMap[$uid]['omset'] ?? 0;

    $skorAbsensi = $hariKalender > 0 ? round(min(30, ($hariKerja / $hariKalender) * 30), 2) : 0;

    $stmtT = $conn->prepare("SELECT target_nominal FROM penilaian_target_omset WHERE staff_id=? AND periode_id=?");
    $stmtT->bind_param('ii', $uid, $periode['id']);
    $stmtT->execute();
    $rowT = $stmtT->get_result()->fetch_assoc();
    $targetNominal = $rowT ? (float)$rowT['target_nominal'] : 0.0;

    if ($targetNominal <= 0) {
        $skorOmsetStatus = 'target_belum_diset';
        $skorOmset = null;
    } else {
        $skorOmsetStatus = 'ok';
        $skorOmset = round(min(25, ($omsetAktual / $targetNominal) * 25), 2);
    }

    $skorPeerData   = hitung_skor_peer($conn, (int)$periode['id'], $uid);
    $skorPeerStatus = $skorPeerData ? 'ok' : 'belum_ada_data';
    $skorPeer       = $skorPeerData['skor_45'] ?? null;

    $lengkap   = ($skorOmsetStatus === 'ok') && ($skorPeerStatus === 'ok');
    $skorTotal = $lengkap ? round($skorAbsensi + $skorOmset + $skorPeer, 2) : null;

    return [
        'user_id'               => $uid,
        'username'              => $staff['username'],
        'fullName'              => $staff['fullName'],
        'role'                  => $staff['role'],
        'skor_absensi'          => $skorAbsensi,
        'hari_kerja_aktual'     => $hariKerja,
        'hari_kalender_periode' => (int)$hariKalender,
        'skor_omset'            => $skorOmset,
        'skor_omset_status'     => $skorOmsetStatus,
        'omset_aktual'          => $omsetAktual,
        'target_nominal'        => $targetNominal,
        'skor_peer'             => $skorPeer,
        'skor_peer_status'      => $skorPeerStatus,
        'jumlah_penilai'        => $skorPeerData['jumlah_penilai'] ?? 0,
        'skor_total'            => $skorTotal,
        'status'                => $lengkap ? 'lengkap' : 'data_belum_lengkap',
    ];
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$input  = json_decode(file_get_contents('php://input'), true) ?: [];

switch ($action) {

    // ═══ REFERENSI (butuh login, tidak perlu role khusus) ═══════════
    case 'get_pertanyaan': {
        require_actor($conn);
        $templateId = (int)($_GET['template_id'] ?? 0);
        if ($templateId <= 0) respond(['status' => 'error', 'message' => 'template_id wajib diisi.']);
        $stmt = $conn->prepare("SELECT id, template_id, kategori, urutan, teks FROM penilaian_pertanyaan WHERE template_id = ? ORDER BY urutan ASC");
        $stmt->bind_param('i', $templateId);
        $stmt->execute();
        $res = $stmt->get_result();
        respond(['status' => 'success', 'data' => $res ? $res->fetch_all(MYSQLI_ASSOC) : []]);
    }

    case 'get_template_list': {
        require_actor($conn);
        $res = $conn->query("SELECT id, kode, nama, deskripsi FROM penilaian_template ORDER BY kode ASC");
        respond(['status' => 'success', 'data' => $res ? $res->fetch_all(MYSQLI_ASSOC) : []]);
    }

    case 'get_periode_aktif': {
        require_actor($conn);
        respond(['status' => 'success', 'data' => get_periode_aktif($conn)]);
    }

    // ═══ STAFF: target penilaian & submit ═══════════════════════════
    case 'get_target_penilaian': {
        $actor    = require_actor($conn);
        $periode  = get_periode_aktif($conn);
        $targets  = $periode ? get_targets_for_penilai($conn, (int)$actor['id'], $actor['role']) : [];

        if ($periode) {
            $ids = array_column($targets, 'id');
            $sudahMap = [];
            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $types = str_repeat('i', count($ids) + 2);
                $stmt = $conn->prepare("SELECT id, dinilai_user_id FROM penilaian_submission
                                        WHERE periode_id = ? AND penilai_user_id = ? AND dinilai_user_id IN ($placeholders)");
                $params = array_merge([$periode['id'], $actor['id']], $ids);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                    $sudahMap[(int)$row['dinilai_user_id']] = (int)$row['id'];
                }
            }
            foreach ($targets as &$t) {
                $tid = (int)$t['id'];
                $t['sudah_dinilai']  = isset($sudahMap[$tid]);
                $t['submission_id']  = $sudahMap[$tid] ?? null;
            }
            unset($t);
        }

        respond(['status' => 'success', 'periode_aktif' => $periode, 'targets' => $targets]);
    }

    case 'submit_penilaian': {
        $actor = require_actor($conn);
        $dinilaiId = (int)($input['dinilai_user_id'] ?? 0);
        $jawaban   = $input['jawaban'] ?? []; // [{pertanyaan_id, skor}]

        if ($dinilaiId <= 0) respond(['status' => 'error', 'message' => 'Target penilaian tidak valid.']);
        if ($dinilaiId === (int)$actor['id']) respond(['status' => 'error', 'message' => 'Tidak bisa menilai diri sendiri.']);

        $periode = get_periode_aktif($conn);
        if (!$periode) respond(['status' => 'error', 'message' => 'Tidak ada periode penilaian yang sedang aktif.']);

        $today = date('Y-m-d');
        if ($today < $periode['tanggal_mulai'] || $today > $periode['tanggal_selesai']) {
            respond(['status' => 'error', 'message' => 'Periode penilaian ini sudah di luar rentang tanggal (belum mulai / sudah lewat).']);
        }

        $targets = get_targets_for_penilai($conn, (int)$actor['id'], $actor['role']);
        $targetRow = null;
        foreach ($targets as $t) { if ((int)$t['id'] === $dinilaiId) { $targetRow = $t; break; } }
        if (!$targetRow) respond(['status' => 'error', 'message' => 'Anda tidak ditugaskan untuk menilai staff ini.']);

        // Template ditentukan MURNI dari role TARGET saat ini (bukan role penilai),
        // dan nilai ini yang disimpan sebagai snapshot - lihat dokumentasi di atas.
        $template = $targetRow['template'];
        if (!$template) {
            respond(['status' => 'error', 'message' => 'Role staff ini belum dipetakan ke template pertanyaan manapun. Hubungi VIP untuk mengatur mapping-nya.']);
        }
        $templateId = (int)$template['id'];

        // Cek belum pernah submit utk kombinasi ini.
        $chk = $conn->prepare("SELECT id FROM penilaian_submission WHERE periode_id=? AND penilai_user_id=? AND dinilai_user_id=?");
        $chk->bind_param('iii', $periode['id'], $actor['id'], $dinilaiId);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            respond(['status' => 'error', 'message' => 'Anda sudah pernah menilai staff ini di periode ini. Penilaian tidak bisa diubah.']);
        }

        // Validasi jawaban: harus mencakup SEMUA pertanyaan di TEMPLATE target ini, skor 1-5.
        $stmtQ = $conn->prepare("SELECT id FROM penilaian_pertanyaan WHERE template_id = ?");
        $stmtQ->bind_param('i', $templateId);
        $stmtQ->execute();
        $allQIds = array_map('intval', array_column($stmtQ->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));

        $jawabanMap = [];
        foreach ($jawaban as $j) {
            $pid = (int)($j['pertanyaan_id'] ?? 0);
            $skor = (int)($j['skor'] ?? 0);
            if ($skor < 1 || $skor > 5) respond(['status' => 'error', 'message' => 'Skor harus antara 1-5.']);
            if (!in_array($pid, $allQIds, true)) respond(['status' => 'error', 'message' => 'Ada pertanyaan yang tidak sesuai dengan template staff ini.']);
            $jawabanMap[$pid] = $skor;
        }
        foreach ($allQIds as $pid) {
            if (!isset($jawabanMap[$pid])) {
                respond(['status' => 'error', 'message' => 'Semua pertanyaan wajib diisi.']);
            }
        }

        $totalSkor = array_sum($jawabanMap);

        $conn->begin_transaction();
        try {
            $ins = $conn->prepare("INSERT INTO penilaian_submission (periode_id, penilai_user_id, dinilai_user_id, template_id_snapshot, total_skor, locked) VALUES (?,?,?,?,?,1)");
            $ins->bind_param('iiiii', $periode['id'], $actor['id'], $dinilaiId, $templateId, $totalSkor);
            $ins->execute();
            $submissionId = $conn->insert_id;

            $insDetail = $conn->prepare("INSERT INTO penilaian_submission_detail (submission_id, pertanyaan_id, skor) VALUES (?,?,?)");
            foreach ($jawabanMap as $pid => $skor) {
                $insDetail->bind_param('iii', $submissionId, $pid, $skor);
                $insDetail->execute();
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            respond(['status' => 'error', 'message' => 'Gagal menyimpan penilaian: ' . $e->getMessage()]);
        }

        respond(['status' => 'success', 'message' => 'Penilaian berhasil disimpan.']);
    }

    // Read-only jawaban SATU submission - HANYA boleh oleh penilai itu sendiri
    // (self-view), atau Owner/VIP (admin visibility). Staff yang DINILAI tidak
    // boleh lihat ini sama sekali (anonimitas ke penilai).
    case 'get_submission_detail': {
        $actor = require_actor($conn);
        $submissionId = (int)($_GET['submission_id'] ?? 0);

        $stmt = $conn->prepare("SELECT s.*, up.fullName AS penilai_nama, ud.fullName AS dinilai_nama
                                 FROM penilaian_submission s
                                 JOIN users up ON up.id = s.penilai_user_id
                                 JOIN users ud ON ud.id = s.dinilai_user_id
                                 WHERE s.id = ?");
        $stmt->bind_param('i', $submissionId);
        $stmt->execute();
        $sub = $stmt->get_result()->fetch_assoc();
        if (!$sub) respond(['status' => 'error', 'message' => 'Data tidak ditemukan.']);

        $isOwnSubmission = ((int)$sub['penilai_user_id'] === (int)$actor['id']);
        if (!$isOwnSubmission && !is_admin_role($actor['role'])) {
            http_response_code(403);
            respond(['status' => 'error', 'message' => 'Anda tidak berhak melihat detail penilaian ini.']);
        }

        $stmtD = $conn->prepare("SELECT d.pertanyaan_id, d.skor, p.kategori, p.urutan, p.teks
                                  FROM penilaian_submission_detail d
                                  JOIN penilaian_pertanyaan p ON p.id = d.pertanyaan_id
                                  WHERE d.submission_id = ?
                                  ORDER BY p.kategori ASC, p.urutan ASC");
        $stmtD->bind_param('i', $submissionId);
        $stmtD->execute();
        $detail = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);

        respond(['status' => 'success', 'submission' => $sub, 'detail' => $detail]);
    }

    // Skor peer milik user login sendiri (periode aktif) + histori periode tutup.
    case 'get_skor_saya': {
        $actor = require_actor($conn);
        $periodeAktif = get_periode_aktif($conn);
        $skorAktif = $periodeAktif ? hitung_skor_peer($conn, (int)$periodeAktif['id'], (int)$actor['id']) : null;

        $resHist = $conn->query("SELECT * FROM penilaian_periode WHERE status='tutup' ORDER BY tanggal_selesai DESC");
        $histori = [];
        foreach (($resHist ? $resHist->fetch_all(MYSQLI_ASSOC) : []) as $p) {
            $skor = hitung_skor_peer($conn, (int)$p['id'], (int)$actor['id']);
            $histori[] = [
                'periode' => $p,
                'skor'    => $skor,
            ];
        }

        respond(['status' => 'success', 'periode_aktif' => $periodeAktif, 'skor_aktif' => $skorAktif, 'histori' => $histori]);
    }

    // ═══ ADMIN (VIP only) ════════════════════════════════════════════
    case 'admin_get_staff': {
        require_vip($conn);
        respond(['status' => 'success', 'data' => get_staff_pool($conn)]);
    }

    // Mapping role -> template, data-driven (bukan hardcode). Dipakai
    // UI manajemen_penilaian.html supaya VIP bisa ubah/tambah mapping
    // tanpa perlu ubah kode.
    case 'admin_get_role_template_map': {
        require_vip($conn);
        $res = $conn->query("SELECT m.id, m.role, m.template_id, m.aktif, t.kode, t.nama
                              FROM penilaian_role_template_map m
                              JOIN penilaian_template t ON t.id = m.template_id
                              ORDER BY m.role ASC");
        respond(['status' => 'success', 'data' => $res ? $res->fetch_all(MYSQLI_ASSOC) : []]);
    }

    case 'admin_save_role_template_map': {
        require_vip($conn);
        $role       = trim($input['role'] ?? '');
        $templateId = (int)($input['template_id'] ?? 0);

        if ($role === '' || !in_array($role, TARGET_ROLES, true)) {
            respond(['status' => 'error', 'message' => 'Role tidak valid.']);
        }
        $chkT = $conn->prepare("SELECT id FROM penilaian_template WHERE id = ?");
        $chkT->bind_param('i', $templateId);
        $chkT->execute();
        if ($chkT->get_result()->num_rows === 0) {
            respond(['status' => 'error', 'message' => 'Template tidak ditemukan.']);
        }

        $stmt = $conn->prepare("INSERT INTO penilaian_role_template_map (role, template_id, aktif) VALUES (?,?,1)
                                 ON DUPLICATE KEY UPDATE template_id = VALUES(template_id), aktif = 1");
        $stmt->bind_param('si', $role, $templateId);
        $stmt->execute();
        respond(['status' => 'success', 'message' => 'Mapping role -> template disimpan.']);
    }

    case 'admin_get_periode_list': {
        require_vip($conn);
        $res = $conn->query("SELECT * FROM penilaian_periode ORDER BY id DESC");
        respond(['status' => 'success', 'data' => $res ? $res->fetch_all(MYSQLI_ASSOC) : []]);
    }

    case 'admin_create_periode': {
        $actor = require_vip($conn);
        $mulai   = trim($input['tanggal_mulai'] ?? '');
        $selesai = trim($input['tanggal_selesai'] ?? '');

        if (!$mulai || !$selesai) respond(['status' => 'error', 'message' => 'Tanggal mulai & selesai wajib diisi.']);
        if ($selesai < $mulai) respond(['status' => 'error', 'message' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']);

        if (get_periode_aktif($conn)) {
            respond(['status' => 'error', 'message' => 'Masih ada periode yang aktif. Tutup dulu periode itu sebelum buat yang baru.']);
        }

        $stmt = $conn->prepare("INSERT INTO penilaian_periode (tanggal_mulai, tanggal_selesai, status, dibuat_oleh) VALUES (?,?,'aktif',?)");
        $stmt->bind_param('sss', $mulai, $selesai, $actor['username']);
        $stmt->execute();
        respond(['status' => 'success', 'message' => 'Periode baru dibuat.', 'id' => $conn->insert_id]);
    }

    case 'admin_close_periode': {
        require_vip($conn);
        $id = (int)($input['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE penilaian_periode SET status='tutup' WHERE id=? AND status='aktif'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($conn->affected_rows === 0) {
            respond(['status' => 'error', 'message' => 'Periode tidak ditemukan atau sudah tertutup.']);
        }
        respond(['status' => 'success', 'message' => 'Periode ditutup.']);
    }

    // Ambil assignment lengkap (dengan nama) - buat tabel manajemen.
    case 'admin_get_assignment': {
        require_vip($conn);
        $res = $conn->query("SELECT a.id, a.penilai_user_id, a.dinilai_user_id, a.aktif,
                                     up.fullName AS penilai_nama, up.username AS penilai_username, up.role AS penilai_role,
                                     ud.fullName AS dinilai_nama, ud.username AS dinilai_username, ud.role AS dinilai_role
                              FROM penilaian_assignment a
                              JOIN users up ON up.id = a.penilai_user_id
                              JOIN users ud ON ud.id = a.dinilai_user_id
                              WHERE a.aktif = 1
                              ORDER BY up.fullName ASC, ud.fullName ASC");
        respond(['status' => 'success', 'data' => $res ? $res->fetch_all(MYSQLI_ASSOC) : []]);
    }

    // Bulk save: utk 1 penilai, set target-nya PERSIS sama dengan daftar yg dikirim
    // (yang tidak ada di daftar baru otomatis di-nonaktifkan / "dihapus").
    case 'admin_save_assignment': {
        require_vip($conn);
        $penilaiId = (int)($input['penilai_user_id'] ?? 0);
        $targetIds = array_map('intval', $input['dinilai_user_ids'] ?? []);
        $targetIds = array_values(array_unique(array_filter($targetIds, fn($v) => $v > 0 && $v !== $penilaiId)));

        if ($penilaiId <= 0) respond(['status' => 'error', 'message' => 'Penilai tidak valid.']);

        $conn->begin_transaction();
        try {
            if ($targetIds) {
                $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
                $types = 'i' . str_repeat('i', count($targetIds));
                $stmtOff = $conn->prepare("UPDATE penilaian_assignment SET aktif=0 WHERE penilai_user_id=? AND dinilai_user_id NOT IN ($placeholders)");
                $params = array_merge([$penilaiId], $targetIds);
                $stmtOff->bind_param($types, ...$params);
                $stmtOff->execute();

                $stmtOn = $conn->prepare("INSERT INTO penilaian_assignment (penilai_user_id, dinilai_user_id, aktif) VALUES (?,?,1)
                                           ON DUPLICATE KEY UPDATE aktif=1");
                foreach ($targetIds as $tid) {
                    $stmtOn->bind_param('ii', $penilaiId, $tid);
                    $stmtOn->execute();
                }
            } else {
                // Tidak ada target dipilih sama sekali -> nonaktifkan semua assignment penilai ini.
                $stmtOffAll = $conn->prepare("UPDATE penilaian_assignment SET aktif=0 WHERE penilai_user_id=?");
                $stmtOffAll->bind_param('i', $penilaiId);
                $stmtOffAll->execute();
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            respond(['status' => 'error', 'message' => 'Gagal menyimpan assignment: ' . $e->getMessage()]);
        }

        respond(['status' => 'success', 'message' => 'Assignment disimpan.']);
    }

    // Monitoring: semua staff + status submission diterima di periode tertentu
    // (default periode aktif), LENGKAP dengan nama penilai (halaman ini VIP-only,
    // bukan pelanggaran anonimitas).
    case 'admin_get_monitoring': {
        require_vip($conn);
        $periodeId = (int)($_GET['periode_id'] ?? 0);
        if ($periodeId <= 0) {
            $p = get_periode_aktif($conn);
            $periodeId = $p ? (int)$p['id'] : 0;
        }
        if ($periodeId <= 0) respond(['status' => 'success', 'periode_id' => null, 'data' => []]);

        $staff = get_staff_pool($conn);
        $stmt = $conn->prepare("SELECT s.dinilai_user_id, s.total_skor, up.fullName AS penilai_nama, up.username AS penilai_username
                                 FROM penilaian_submission s
                                 JOIN users up ON up.id = s.penilai_user_id
                                 WHERE s.periode_id = ?");
        $stmt->bind_param('i', $periodeId);
        $stmt->execute();
        $subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $byTarget = [];
        foreach ($subs as $s) {
            $byTarget[(int)$s['dinilai_user_id']][] = $s;
        }

        $data = [];
        foreach ($staff as $u) {
            $uid = (int)$u['id'];
            $penilaiList = $byTarget[$uid] ?? [];
            $data[] = [
                'user_id'        => $uid,
                'username'       => $u['username'],
                'fullName'       => $u['fullName'],
                'role'           => $u['role'],
                'jumlah_penilai' => count($penilaiList),
                'penilai'        => array_map(fn($s) => ['nama' => $s['penilai_nama'], 'username' => $s['penilai_username'], 'skor' => (int)$s['total_skor']], $penilaiList),
                'belum_dinilai'  => count($penilaiList) === 0,
            ];
        }

        respond(['status' => 'success', 'periode_id' => $periodeId, 'data' => $data]);
    }

    // Koreksi submission (typo/salah input) - HANYA VIP, staff tidak pernah
    // bisa akses jalur ini.
    case 'admin_edit_submission': {
        require_vip($conn);
        $submissionId = (int)($input['submission_id'] ?? 0);
        $jawaban = $input['jawaban'] ?? [];

        $chk = $conn->query("SELECT id FROM penilaian_submission WHERE id=" . $submissionId);
        if (!$chk || $chk->num_rows === 0) respond(['status' => 'error', 'message' => 'Submission tidak ditemukan.']);

        $totalSkor = 0;
        $conn->begin_transaction();
        try {
            $del = $conn->prepare("DELETE FROM penilaian_submission_detail WHERE submission_id=?");
            $del->bind_param('i', $submissionId);
            $del->execute();

            $ins = $conn->prepare("INSERT INTO penilaian_submission_detail (submission_id, pertanyaan_id, skor) VALUES (?,?,?)");
            foreach ($jawaban as $j) {
                $pid  = (int)($j['pertanyaan_id'] ?? 0);
                $skor = max(1, min(5, (int)($j['skor'] ?? 1)));
                $totalSkor += $skor;
                $ins->bind_param('iii', $submissionId, $pid, $skor);
                $ins->execute();
            }

            $upd = $conn->prepare("UPDATE penilaian_submission SET total_skor=? WHERE id=?");
            $upd->bind_param('ii', $totalSkor, $submissionId);
            $upd->execute();
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            respond(['status' => 'error', 'message' => 'Gagal mengoreksi: ' . $e->getMessage()]);
        }
        respond(['status' => 'success', 'message' => 'Submission dikoreksi.']);
    }

    case 'admin_delete_submission': {
        require_vip($conn);
        $submissionId = (int)($input['submission_id'] ?? 0);
        $conn->query("DELETE FROM penilaian_submission_detail WHERE submission_id=" . $submissionId);
        $conn->query("DELETE FROM penilaian_submission WHERE id=" . $submissionId);
        respond(['status' => 'success', 'message' => 'Submission dihapus.']);
    }

    // Target omset per staff per periode (dasar hitung Skor Omset) - VIP only.
    case 'admin_get_target_omset': {
        require_vip($conn);
        $periodeId = (int)($_GET['periode_id'] ?? 0);
        if ($periodeId <= 0) {
            $p = get_periode_aktif($conn);
            $periodeId = $p ? (int)$p['id'] : 0;
        }
        if ($periodeId <= 0) respond(['status' => 'success', 'periode_id' => null, 'data' => []]);

        $staff = get_staff_pool($conn);
        $stmt = $conn->prepare("SELECT staff_id, target_nominal FROM penilaian_target_omset WHERE periode_id=?");
        $stmt->bind_param('i', $periodeId);
        $stmt->execute();
        $map = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $map[(int)$r['staff_id']] = (float)$r['target_nominal'];
        }

        $data = [];
        foreach ($staff as $u) {
            $uid = (int)$u['id'];
            $data[] = [
                'user_id'        => $uid,
                'username'       => $u['username'],
                'fullName'       => $u['fullName'],
                'role'           => $u['role'],
                'target_nominal' => $map[$uid] ?? 0,
            ];
        }
        respond(['status' => 'success', 'periode_id' => $periodeId, 'data' => $data]);
    }

    case 'admin_save_target_omset': {
        require_vip($conn);
        $staffId       = (int)($input['staff_id'] ?? 0);
        $periodeId     = (int)($input['periode_id'] ?? 0);
        $targetNominal = (float)($input['target_nominal'] ?? 0);

        if ($staffId <= 0) respond(['status' => 'error', 'message' => 'Staff tidak valid.']);
        if ($periodeId <= 0) {
            $p = get_periode_aktif($conn);
            $periodeId = $p ? (int)$p['id'] : 0;
        }
        if ($periodeId <= 0) respond(['status' => 'error', 'message' => 'Tidak ada periode aktif.']);
        if ($targetNominal <= 0) respond(['status' => 'error', 'message' => 'Target nominal harus lebih dari 0.']);

        $chk = $conn->prepare("SELECT id FROM users WHERE id=? AND role IN ('Staff','Senior Staff','SPV')");
        $chk->bind_param('i', $staffId);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) respond(['status' => 'error', 'message' => 'Staff tidak ditemukan / bukan target penilaian.']);

        $stmt = $conn->prepare("INSERT INTO penilaian_target_omset (staff_id, periode_id, target_nominal) VALUES (?,?,?)
                                 ON DUPLICATE KEY UPDATE target_nominal = VALUES(target_nominal)");
        $stmt->bind_param('iid', $staffId, $periodeId, $targetNominal);
        $stmt->execute();
        respond(['status' => 'success', 'message' => 'Target omset disimpan.']);
    }

    // Skor Total = Absensi (30%) + Omset (25%) + Peer Review (45%), periode_aktif saja.
    // Owner/VIP lihat SEMUA staff (sorted desc, incomplete di bawah). Role lain HANYA
    // lihat milik sendiri - user_id diambil dari actor session, TIDAK PERNAH dari
    // parameter client, supaya tidak bisa lihat skor orang lain.
    case 'get_skor_total': {
        $actor   = require_actor($conn);
        $periode = get_periode_aktif($conn);
        if (!$periode) respond(['status' => 'success', 'periode_aktif' => null, 'data' => []]);

        $isAdmin = is_admin_role($actor['role']);
        $staffList = $isAdmin ? get_staff_pool($conn) : [$actor];

        $absOmsetMap = hitung_absensi_omset_periode($conn, $periode, $staffList);

        $data = [];
        foreach ($staffList as $s) {
            $data[] = hitung_skor_total_staff($conn, $periode, $s, $absOmsetMap);
        }

        if ($isAdmin) {
            usort($data, function ($a, $b) {
                if ($a['skor_total'] === null && $b['skor_total'] === null) return 0;
                if ($a['skor_total'] === null) return 1;
                if ($b['skor_total'] === null) return -1;
                return $b['skor_total'] <=> $a['skor_total'];
            });
        }

        respond(['status' => 'success', 'periode_aktif' => $periode, 'scope' => $isAdmin ? 'all' : 'self', 'data' => $data]);
    }

    default:
        respond(['status' => 'error', 'message' => "Action '$action' tidak dikenali."]);
}
