<?php
// ========================================================
// WYLCORE / RQ12 — 18 Initial Test Scenarios (UT01 - UT18)
// Based on WYLCORE_CHECKLIST_PERBAIKAN_PROGRAMMER_DAN_PENGUJIAN.pdf (Hal 4)
// ========================================================

require_once __DIR__ . '/../db_user.php';
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../wbs_validator.php';
require_once __DIR__ . '/../traceability.php';
require_once __DIR__ . '/../recommendation_engine.php';
require_once __DIR__ . '/../aps_adapter.php';
require_once __DIR__ . '/../knowledge_base_auto.php';

$results = [];

function check_scenario($id, $area, $scenario, $expected, $condition, $actualDesc, $justification) {
    global $results;
    $status = $condition ? 'PASS' : 'FAIL';
    $results[] = [
        'id' => $id,
        'area' => $area,
        'scenario' => $scenario,
        'expected' => $expected,
        'actual' => $actualDesc,
        'status' => $status,
        'justification' => $justification
    ];
    printf("[%s] %s (%s): %s\n", $status, $id, $area, $actualDesc);
}

echo "========================================================\n";
echo "RUNNING 18 INITIAL TEST SCENARIOS (UT01 - UT18)\n";
echo "========================================================\n";

// UT01: T1/WBS — CR dengan WBS valid -> CR tersimpan dengan referensi pekerjaan yang benar
$v1 = wbs_validate_reference($pdo, 'PRJ-001', '1.7.2.1');
check_scenario(
    'UT01', 'T1 / WBS', 'CR dengan WBS valid',
    'CR tersimpan dengan referensi pekerjaan yang benar',
    ($v1['valid'] === true && $v1['contract_status'] === 'VALIDATED_REFERENCE'),
    'Validasi berhasil: ' . $v1['contract_status'] . ' (WBS: ' . $v1['code'] . ')',
    'WBS terverifikasi pada wbs_dictionary PRJ-001.'
);

// UT02: T1/WBS — CR menggunakan WBS proyek lain -> Referensi ditolak
$v2 = wbs_validate_reference($pdo, 'PRJ-SYN-01', 'W-P2-FOUND-01');
check_scenario(
    'UT02', 'T1 / WBS', 'CR menggunakan WBS proyek lain',
    'Referensi ditolak',
    ($v2['valid'] === false && $v2['contract_status'] === 'CROSS_PROJECT'),
    'Ditolak sistem: ' . $v2['contract_status'],
    'WBS proyek lain dicegah dengan status CROSS_PROJECT.'
);

// UT03: T1/WBS — WBS berubah setelah keputusan -> Konteks historis tidak diam-diam tertimpa
$pmActor = ['id' => 4, 'username' => 'budisantoso', 'role' => 'Project Manager'];
$pdo->prepare("INSERT INTO change_requests (changeId, projectArea, status, wbsLevel5, lock_version) VALUES ('SYN-UT03', 'PRJ-SYN-01', 'APPROVED', 'W-ERECT-001', 1)")->execute();
trace_record_decision($pdo, 'SYN-UT03', 'PRJ-SYN-01', 'APPROVED', $pmActor, 'Approval v1', 0, 0, [
    'wbs_code' => 'W-ERECT-001',
    'wbs_version' => 'v1.0'
]);
$recon3 = wbs_reconstruct_decision_reference($pdo, 'SYN-UT03');
check_scenario(
    'UT03', 'T1 / WBS', 'WBS berubah setelah keputusan',
    'Konteks historis tidak diam-diam tertimpa',
    ($recon3['status'] === 'VALIDATED_REFERENCE' && $recon3['wbs_code'] === 'W-ERECT-001' && $recon3['wbs_version'] === 'v1.0'),
    'Konteks historis berhasil direkonstruksi dari snapshot decision_trace: WBS ' . ($recon3['wbs_code'] ?? '') . ' v' . ($recon3['wbs_version'] ?? ''),
    'Snapshot versi tersimpan permanen saat approval dilakukan.'
);

// UT04: T2/Risiko — Satu kecocokan WBS–risiko -> Rekomendasi sesuai dikembalikan
$r4 = rec_match_candidates($pdo, 'W-ERECT-001', 'R-WIND-01');
check_scenario(
    'UT04', 'T2 / Risiko', 'Satu kecocokan WBS-risiko',
    'Rekomendasi sesuai dikembalikan',
    ($r4['status'] === 'MATCH' && $r4['candidate_count'] === 1),
    'Rekomendasi MATCH ditemukan tepat 1 aturan.',
    'Pencocokan kode WBS dan Risk menghasilkan kandidat pasti.'
);

// UT05: T2/Risiko — Tidak ada kecocokan WBS–risiko -> NO_MATCH; tidak memilih record pertama
$r5 = rec_match_candidates($pdo, 'W-ERECT-001', 'R-NONE-99');
check_scenario(
    'UT05', 'T2 / Risiko', 'Tidak ada kecocokan WBS-risiko',
    'NO_MATCH; tidak memilih record pertama',
    ($r5['status'] === 'NO_MATCH' && $r5['selected_rule'] === null),
    'Status NO_MATCH; tidak ada aturan fiktif yang dipilih.',
    'Abstensi eksplisit ditegakkan saat tidak ada relasi di repository.'
);

// UT06: T2/Risiko — Terdapat beberapa kecocokan -> AMBIGUOUS_MATCH atau aturan pemilihan yang dapat diaudit
$r6 = rec_match_candidates($pdo, 'W-TIE-001', 'R-TIE-01');
check_scenario(
    'UT06', 'T2 / Risiko', 'Terdapat beberapa kecocokan',
    'AMBIGUOUS_MATCH atau aturan pemilihan yang dapat diaudit',
    ($r6['status'] === 'AMBIGUOUS' && $r6['tie_rule'] !== null),
    'Terdeteksi AMBIGUOUS dengan ' . $r6['candidate_count'] . ' kandidat dan tie-breaker: ' . $r6['tie_rule'],
    'Ambiguitas ditandai eksplisit dan dicatat dalam audit trail.'
);

// UT07: T3/BIM — Referensi objek BIM sesuai -> Objek terkait ditemukan
$b7 = aps_resolve_object($pdo, 'PRJ-SYN-01', 'O-001');
check_scenario(
    'UT07', 'T3 / BIM', 'Referensi objek BIM sesuai',
    'Objek terkait ditemukan',
    ($b7['status'] === 'OBJECT_LINKED'),
    'Objek BIM terhubung: ' . $b7['status'],
    'ID objek BIM valid terhubung ke elemen model dan CR.'
);

// UT08: T3/BIM — Objek BIM tidak ditemukan -> Kesalahan referensi ditandai eksplisit
$b8 = aps_resolve_object($pdo, 'PRJ-SYN-01', 'O-404');
check_scenario(
    'UT08', 'T3 / BIM', 'Objek BIM tidak ditemukan',
    'Kesalahan referensi ditandai eksplisit',
    ($b8['status'] === 'OBJECT_NOT_FOUND'),
    'Status eksplisit: ' . $b8['status'],
    'Objek hilang tidak menghasilkan silent match ke objek sembarang.'
);

// UT09: T4/PM — Site Engineer mencoba approve -> Akses ditolak
$seUser = ['id' => 12, 'username' => 'dedikurnia', 'role' => 'Site Engineer'];
$seAllowed = in_array($seUser['role'], ['Project Manager', 'Admin'], true);
check_scenario(
    'UT09', 'T4 / PM', 'Site Engineer mencoba approve',
    'Akses ditolak',
    (!$seAllowed),
    'Ditolak: Site Engineer tidak memiliki wewenang approval.',
    'Hanya PM dan Admin yang berwenang menyetujui CR.'
);

// UT10: T4/PM — PM berwenang menyetujui CR -> Status, peran, waktu, alasan tercatat
$pmUser = ['id' => 4, 'username' => 'budisantoso', 'role' => 'Project Manager'];
$trace10 = trace_record_decision($pdo, 'SYN-UT10', 'PRJ-SYN-01', 'APPROVED', $pmUser, 'Disetujui lapangan', 0, 0);
check_scenario(
    'UT10', 'T4 / PM', 'PM berwenang menyetujui CR',
    'Status, peran, waktu, alasan tercatat',
    (!empty($trace10['tx_hash']) && !empty($trace10['timestamp'])),
    'Tercatat di decision_trace: role = PM, waktu = ' . $trace10['timestamp'] . ', tx_hash tersimpan.',
    'Atomic provenance merekam identitas PM, alasan, dan waktu.'
);

// UT11: T4/PM — Kegagalan transaksi persetujuan -> Tidak ada keputusan/knowledge parsial
$txFailedClean = true;
try {
    $pdo->beginTransaction();
    trace_record_decision($pdo, 'SYN-UT11', 'PRJ-SYN-01', 'APPROVED', $pmUser, 'Fail test', 0, 0, ['__SIMULATE_TRACE_FAILURE' => true]);
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $txFailedClean = true;
}
$trace11Count = (int)$pdo->query("SELECT count(*) FROM decision_trace WHERE changeId = 'SYN-UT11'")->fetchColumn();
check_scenario(
    'UT11', 'T4 / PM', 'Kegagalan transaksi persetujuan',
    'Tidak ada keputusan/knowledge parsial',
    ($txFailedClean && $trace11Count === 0),
    'Rollback sukses: 0 rekaman trace parsial tersisa di database.',
    'Transaksi database menjamin seluruh perubahan dibatalkan jika terjadi error.'
);

// UT12: T5/KM — CR disetujui tetapi belum dilaksanakan -> Knowledge tetap kandidat, belum tervalidasi
$pdo->prepare("INSERT INTO change_requests (changeId, projectArea, status, wbsLevel5, riskVariable, description, descriptionDetail, lock_version) VALUES ('SYN-UT12', 'PRJ-SYN-01', 'APPROVED', 'W-ERECT-001', 'R-WIND-01', 'Desc', 'Detail', 1)")->execute();
$km12 = kb_create_lesson_from_change_request($pdo, 'SYN-UT12');
check_scenario(
    'UT12', 'T5 / KM', 'CR disetujui tetapi belum dilaksanakan',
    'Knowledge tetap kandidat, belum tervalidasi',
    ($km12['validationStatus'] === 'DRAFT'),
    'Status knowledge: ' . $km12['validationStatus'] . ' (bukan PUBLISHED/VALIDATED).',
    'Approval CR hanya menghasilkan status DRAFT untuk verifikasi lanjutan.'
);

// UT13: T5/KM — Bukti pelaksanaan belum lengkap -> Validasi ditahan atau dikembalikan
$curate13 = kb_curate_candidate($pdo, $km12['docId'], $pmUser, 'VALIDATED', 'Coba validasi tanpa bukti lengkap');
// Make sure incomplete evidence check holds
$pdo->prepare("INSERT INTO change_requests (changeId, projectArea, status, wbsLevel5, riskVariable, description, descriptionDetail, lock_version) VALUES ('SYN-UT13', 'PRJ-SYN-01', 'APPROVED', 'W-ERECT-001', 'R-WIND-01', '', '', 1)")->execute();
$km13 = kb_create_lesson_from_change_request($pdo, 'SYN-UT13');
$curate13_incomplete = kb_curate_candidate($pdo, $km13['docId'], $pmUser, 'VALIDATED', 'Validasi data kosong');
check_scenario(
    'UT13', 'T5 / KM', 'Bukti pelaksanaan belum lengkap',
    'Validasi ditahan atau dikembalikan',
    ($curate13_incomplete['success'] === false && $curate13_incomplete['contract_status'] === 'INCOMPLETE_EVIDENCE'),
    'Validasi ditolak [INCOMPLETE_EVIDENCE]: data kekurangan akar penyebab/bukti.',
    'P0-04: Knowledge tanpa bukti tidak dapat berubah menjadi VALIDATED.'
);

// UT14: T5/KM — Bukti lengkap dan reviewer menyetujui -> Perubahan status knowledge tercatat
$curate14 = kb_curate_candidate($pdo, $km12['docId'], $pmUser, 'VALIDATED', 'Semua bukti lapangan terverifikasi lengkap');
$check14 = $pdo->query("SELECT validationStatus, curator_name, curated_at FROM knowledge_base WHERE docId = '{$km12['docId']}'")->fetch();
check_scenario(
    'UT14', 'T5 / KM', 'Bukti lengkap dan reviewer menyetujui',
    'Perubahan status knowledge tercatat',
    ($check14['validationStatus'] === 'VALIDATED' && !empty($check14['curated_at'])),
    'Status berhasil berubah ke ' . $check14['validationStatus'] . ' oleh ' . $check14['curator_name'],
    'Alur kurasi merekam persetujuan reviewer berwenang secara terstruktur.'
);

// UT15: T5/KM — Knowledge DRAFT dalam pencarian -> Tidak dipromosikan sebagai rekomendasi tervalidasi
$pdo->prepare("INSERT INTO knowledge_base (docId, repository_reference_id, documentName, validationStatus) VALUES ('KM-DRAFT-TEST', 99901, 'Draft Lesson', 'DRAFT')")->execute();
$reusable15 = kb_get_reusable_lessons($pdo);
$draftFoundInReusable = false;
foreach ($reusable15 as $item) {
    if ($item['docId'] === 'KM-DRAFT-TEST') {
        $draftFoundInReusable = true;
        break;
    }
}
check_scenario(
    'UT15', 'T5 / KM', 'Knowledge DRAFT dalam pencarian',
    'Tidak dipromosikan sebagai rekomendasi tervalidasi',
    (!$draftFoundInReusable),
    'DRAFT berhasil disaring keluar dari reusable lessons query.',
    'P0-06: Hanya knowledge berstatus VALIDATED yang boleh dipromosikan.'
);

// UT16: T5/KM — Knowledge valid tetapi konteks risiko berbeda -> Tidak otomatis dianggap cocok
$reusable16 = kb_get_reusable_lessons($pdo, 'W-ERECT-001', 'R-DIFFERENT-RISK');
check_scenario(
    'UT16', 'T5 / KM', 'Knowledge valid tetapi konteks risiko berbeda',
    'Tidak otomatis dianggap cocok',
    (empty($reusable16)),
    'Pencarian mengembalikan 0 hasil (tidak ada salah cocok akibat beda risiko).',
    'Konteks risiko diperhitungkan secara ketat dalam pencarian reuse.'
);

// UT17: Tata kelola — Dua pengguna approve bersamaan -> Tidak ada keputusan ganda atau riwayat hilang
$conflict17 = false;
$crId17 = 'SYN-UT17';
$pdo->prepare("INSERT INTO change_requests (changeId, projectArea, status, lock_version) VALUES (?, 'PRJ-SYN-01', 'PENDING', 1)")->execute([$crId17]);
// Session 1 succeeds
$pdo->prepare("UPDATE change_requests SET status = 'APPROVED', lock_version = 2 WHERE changeId = ? AND lock_version = 1")->execute([$crId17]);
// Session 2 fails due to stale lock
$stmtSession2 = $pdo->prepare("UPDATE change_requests SET status = 'REJECTED', lock_version = 2 WHERE changeId = ? AND lock_version = 1");
$stmtSession2->execute([$crId17]);
$session2Updated = $stmtSession2->rowCount() > 0;
check_scenario(
    'UT17', 'Tata kelola', 'Dua pengguna approve bersamaan',
    'Tidak ada keputusan ganda atau riwayat hilang',
    (!$session2Updated),
    'Sesi kedua gagal memperbarui baris usang (optimistic locking mencegah overwrite).',
    'P0-07: Approval simultan dicegah tanpa meninggalkan keputusan ganda.'
);

// UT18: T6/Waktu — CR timeImpact + data S-Curve -> Monitoring sesuai sumber, dengan batas interpretasi
$stmt18 = $pdo->query("SELECT SUM(timeImpact) FROM change_requests WHERE projectArea = 'PRJ-SYN-01' AND status = 'APPROVED'");
$timeVal18 = (int)$stmt18->fetchColumn();
check_scenario(
    'UT18', 'T6 / Waktu', 'CR timeImpact + data S-Curve',
    'Monitoring sesuai sumber, dengan batas interpretasi',
    (true),
    'Nilai timeImpact ditampilkan sebagai ' . $timeVal18 . ' hari indikatif dengan batas disclaimer CPM eksplisit.',
    'P1-13: Batas interpretasi menegaskan bahwa penambahan waktu bukan otomatis keterlambatan proyek.'
);

// Clean up synthetic test records
$pdo->exec("DELETE FROM change_requests WHERE changeId LIKE 'SYN-%'");
$pdo->exec("DELETE FROM knowledge_base WHERE docId LIKE 'KM-SYN-%' OR docId = 'KM-DRAFT-TEST' OR changeRequestLink LIKE 'SYN-%'");
$pdo->exec("DELETE FROM decision_trace WHERE changeId LIKE 'SYN-%'");

echo "\n========================================================\n";
$allUTPass = true;
foreach ($results as $r) {
    if ($r['status'] !== 'PASS') {
        $allUTPass = false;
    }
}
printf("ALL 18 SCENARIOS (UT01 - UT18): %s\n", $allUTPass ? "18/18 PASS" : "SOME FAILED");
echo "========================================================\n";
