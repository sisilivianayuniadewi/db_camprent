<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_status'] !== 2) {
    header("Location: ../index.php");
    exit();
}

$pesan = "";
$cari = trim($_GET['cari'] ?? '');

// SIMPAN ATAU UPDATE TARIF DENDA DI SESSION
if (isset($_POST['ubah_tarif'])) {
    $input_tarif = (float)($_POST['tarif_denda_baru'] ?? 5000);
    if ($input_tarif >= 0) {
        $_SESSION['tarif_denda_custom'] = $input_tarif;
    }
}

// Gunakan tarif dari session, jika belum ada pakai default 5000
$tarif_denda = $_SESSION['tarif_denda_custom'] ?? 5000;

/* SIMPAN PENGEMBALIAN */
if (isset($_POST['simpan_pengembalian'])) {
    $peminjaman_id = (int)($_POST['peminjaman_id'] ?? 0);
    $kondisi = $_POST['kondisi'] ?? [];
    $biaya = $_POST['biaya_kerusakan'] ?? [];
    $keterangan = $_POST['keterangan_kerusakan'] ?? [];

    mysqli_begin_transaction($koneksi);

    try {
        $q_pinjam = mysqli_query($koneksi, "
            SELECT *
            FROM t_peminjaman
            WHERE peminjaman_id = $peminjaman_id
            FOR UPDATE
        ");

        if (!$q_pinjam) {
            throw new Exception("Gagal mengambil data peminjaman.");
        }

        $pinjam = mysqli_fetch_assoc($q_pinjam);

        if (!$pinjam || $pinjam['status'] !== 'Dipinjam') {
            throw new Exception("Peminjaman tidak ditemukan atau sudah dikembalikan.");
        }

        $tanggal_sekarang = date('Y-m-d');
        $tanggal_rencana = $pinjam['tanggal_rencana_kembali'];

        $terlambat = 0;
        if ($tanggal_sekarang > $tanggal_rencana) {
            $terlambat = (int)(
                (strtotime($tanggal_sekarang) - strtotime($tanggal_rencana)) / 86400
            );
        }

        $denda_telat = $terlambat * $tarif_denda;
        $total_kerusakan = 0;
        $ada_kerusakan = false;
        $details = [];
        $rincian_kerusakan = [];

        $q_detail = mysqli_query($koneksi, "
            SELECT d.*, a.nama_alat
            FROM t_detail_peminjaman d
            JOIN t_alat a ON d.alat_id = a.alat_id
            WHERE d.peminjaman_id = $peminjaman_id
            FOR UPDATE
        ");

        if (!$q_detail || mysqli_num_rows($q_detail) == 0) {
            throw new Exception("Detail alat peminjaman tidak ditemukan.");
        }

        while ($d = mysqli_fetch_assoc($q_detail)) {
            $id = (int)$d['detail_id'];
            $pilihan = $kondisi[$id] ?? 'baik';
            $nominal = (float)($biaya[$id] ?? 0);
            $ket = trim($keterangan[$id] ?? '');

            if (!in_array($pilihan, ['baik', 'rusak ringan', 'rusak berat'], true)) {
                throw new Exception("Kondisi alat tidak valid.");
            }

            if ($nominal < 0) {
                throw new Exception("Biaya kerusakan tidak boleh negatif.");
            }

            if ($pilihan === 'baik') {
                $nominal = 0;
                $ket = '';
            } else {
                if ($ket === '') {
                    throw new Exception("Keterangan kerusakan wajib diisi untuk alat yang rusak.");
                }
                $ada_kerusakan = true;
                $rincian_kerusakan[] = $d['nama_alat'] . ': ' . $ket . ' (Rp' . number_format($nominal, 0, ',', '.') . ')';
            }

            $total_kerusakan += $nominal;

            $details[] = [
                'detail_id' => $id,
                'alat_id' => (int)$d['alat_id'],
                'jumlah' => (int)$d['jumlah'],
                'kondisi' => $pilihan,
                'biaya' => $nominal,
                'keterangan' => $ket
            ];
        }

        foreach ($details as $d) {
            $stmt = mysqli_prepare($koneksi, "
                UPDATE t_detail_peminjaman
                SET kondisi_kembali = ?,
                    biaya_kerusakan = ?,
                    keterangan_kerusakan = ?
                WHERE detail_id = ?
            ");

            if (!$stmt) {
                throw new Exception("Gagal menyiapkan kondisi alat.");
            }

            mysqli_stmt_bind_param($stmt, "sdsi", $d['kondisi'], $d['biaya'], $d['keterangan'], $d['detail_id']);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Gagal menyimpan kondisi alat.");
            }

            if ($d['kondisi'] === 'baik') {
                $stmt_stok = mysqli_prepare($koneksi, "
                    UPDATE t_alat
                    SET stok = stok + ?
                    WHERE alat_id = ?
                ");

                if (!$stmt_stok) {
                    throw new Exception("Gagal menyiapkan pembaruan stok.");
                }

                mysqli_stmt_bind_param($stmt_stok, "ii", $d['jumlah'], $d['alat_id']);

                if (!mysqli_stmt_execute($stmt_stok)) {
                    throw new Exception("Gagal memperbarui stok.");
                }
            }
        }

        $total_denda = $denda_telat + $total_kerusakan;
        $petugas_id = (int)$_SESSION['user_id'];
        $kondisi_db = $ada_kerusakan ? 'Rusak' : 'Baik';

        $catatan = "Terlambat $terlambat hari. " .
            "Denda keterlambatan Rp" . number_format($denda_telat, 0, ',', '.') . ". " .
            "Biaya kerusakan Rp" . number_format($total_kerusakan, 0, ',', '.') . ". " .
            "Total denda Rp" . number_format($total_denda, 0, ',', '.') . ".";

        if ($rincian_kerusakan) {
            $catatan .= " Rincian: " . implode('; ', $rincian_kerusakan);
        }

        $stmt = mysqli_prepare($koneksi, "
            INSERT INTO t_pengembalian
            (peminjaman_id, petugas_id, tanggal_kembali, kondisi_kembali, catatan, terlambat_hari, tarif_denda_per_hari, total_denda)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new Exception("Gagal menyiapkan data pengembalian.");
        }

        mysqli_stmt_bind_param($stmt, "iisssidd", $peminjaman_id, $petugas_id, $tanggal_sekarang, $kondisi_db, $catatan, $terlambat, $tarif_denda, $total_denda);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Gagal menyimpan pengembalian.");
        }

        $stmt_status = mysqli_prepare($koneksi, "
            UPDATE t_peminjaman
            SET status = 'Selesai'
            WHERE peminjaman_id = ?
        ");

        mysqli_stmt_bind_param($stmt_status, "i", $peminjaman_id);
        mysqli_stmt_execute($stmt_status);

        mysqli_commit($koneksi);
        header("Location: pengembalian.php?berhasil=1");
        exit();

    } catch (Throwable $e) {
        mysqli_rollback($koneksi);
        $pesan = $e->getMessage();
    }
}

/* QUERY PENCARIAN DATA PEMINJAMAN AKTIF */
$where_cari = "WHERE p.status = 'Dipinjam'";
if ($cari !== '') {
    $cari_db = mysqli_real_escape_string($koneksi, $cari);
    $where_cari .= " AND (p.kode_peminjaman LIKE '%$cari_db%' OR pm.nama_lengkap LIKE '%$cari_db%')";
}

$q_pinjam = mysqli_query($koneksi, "
    SELECT p.*, pm.nama_lengkap
    FROM t_peminjaman p
    LEFT JOIN t_peminjam pm ON p.peminjam_id = pm.peminjam_id
    $where_cari
    ORDER BY p.tanggal_rencana_kembali ASC
");

$total_aktif = $q_pinjam ? mysqli_num_rows($q_pinjam) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pengembalian Alat - CampRent</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root {
    --navy: #102a43;
    --teal: #159eaa;
    --teal-light: #e7f7f7;
    --muted: #718096;
    --bg: #f1f5f9;
}

* { box-sizing: border-box; }

body {
    margin: 0;
    background: var(--bg);
    color: var(--navy);
    font-family: Arial, sans-serif;
}

.sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    width: 235px;
    padding: 24px 16px;
    background: linear-gradient(180deg, #102a43, #176b78);
    color: white;
    z-index: 100;
    overflow-y: auto;
}

.brand {
    text-align: center;
    padding: 5px 0 24px;
    border-bottom: 1px solid #ffffff30;
    margin-bottom: 22px;
}

.brand-icon {
    width: 50px; height: 50px;
    margin: 0 auto 10px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    background: #20b8b1; font-size: 24px;
}

.brand h4 { font-weight: 800; margin: 0; font-size: 20px; }
.brand small { color: #c2e3e9; font-size: 10px; }

.menu-label {
    color: #a9dce2; font-size: 10px; font-weight: bold;
    letter-spacing: 1px; margin: 22px 10px 10px;
}

.sidebar a {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 12px; margin-bottom: 6px;
    color: #e1edf5; text-decoration: none; border-radius: 9px; font-size: 13px;
}

.sidebar a:hover, .sidebar a.active { background: #ffffff22; color: white; }
.sidebar a.active { border-left: 3px solid #43ddd0; }

.main { margin-left: 235px; padding: 28px; }

.topbar {
    background: white; padding: 18px 22px; border-radius: 14px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 15px; margin-bottom: 25px; box-shadow: 0 4px 18px #102a4308;
}

.topbar h5 { margin: 0 0 3px; font-weight: 800; }
.subheading { color: var(--muted); font-size: 12px; }

.page-title { font-size: 22px; font-weight: 800; margin-bottom: 4px; }

.stat-card {
    background: white; border-radius: 14px; padding: 18px;
    box-shadow: 0 4px 18px #102a4308; display: flex; align-items: center; gap: 15px;
}
.stat-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; font-size: 20px;
}
.stat-icon.teal { background: #e2f7f6; color: #11969d; }
.stat-icon.orange { background: #fff2dd; color: #d18a18; }
.stat-label { font-size: 11px; color: var(--muted); margin-bottom: 4px; }
.stat-value { font-size: 20px; font-weight: 800; }

.card-list-box {
    background: white; border-radius: 14px; padding: 20px;
    box-shadow: 0 4px 18px #102a4308;
}

.table thead th {
    background: #edf4f8; color: #3a5369; font-size: 11px; padding: 12px;
}
.table tbody td { font-size: 12px; padding: 12px; vertical-align: middle; }

@media (max-width: 900px) {
    .sidebar { position: static; width: 100%; height: auto; }
    .main { margin-left: 0; padding: 15px; }
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon"><i class="bi bi-box-seam"></i></div>
        <h4>CampRent</h4>
        <small>SISTEM PEMINJAMAN ALAT</small>
    </div>

    <div class="menu-label">MENU UTAMA</div>
    <a href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    <a href="peminjaman.php"><i class="bi bi-clipboard-check"></i> Peminjaman</a>
    <a href="pengembalian.php" class="active"><i class="bi bi-box-arrow-in-left"></i> Pengembalian</a>
    <a href="laporan.php"><i class="bi bi-file-earmark-bar-graph"></i> Laporan</a>

    <div class="menu-label">AKUN</div>
    <a href="logout.php" onclick="return confirm('Yakin ingin logout?')"><i class="bi bi-box-arrow-right"></i> Logout</a>
</aside>

<main class="main">

    <div class="topbar">
        <div>
            <h5>Pengelolaan Pengembalian</h5>
            <div class="subheading">Panel petugas / Pengembalian alat</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-light text-teal" style="width:38px;height:38px;">
                <i class="bi bi-person-badge fs-5"></i>
            </div>
            <div>
                <div class="fw-bold small"><?= htmlspecialchars($_SESSION['username'] ?? 'Petugas') ?></div>
                <div class="subheading">Petugas</div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <div class="page-title">Pengembalian Alat</div>
            <div class="subheading">Cari kode peminjaman atau nama peminjam untuk memproses pengembalian.</div>
        </div>

        <form method="GET" class="d-flex gap-2">
            <div class="input-group">
                <input type="text" name="cari" class="form-control form-control-sm" placeholder="Cari Kode / Nama..." value="<?= htmlspecialchars($cari) ?>">
                <button type="submit" class="btn btn-dark btn-sm px-3"><i class="bi bi-search"></i></button>
                <?php if ($cari !== ''): ?>
                    <a href="pengembalian.php" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (isset($_GET['berhasil'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
            <i class="bi bi-check-circle-fill me-2"></i> Pengembalian berhasil disimpan dan stok alat telah diperbarui.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($pesan !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm">
            <i class="bi bi-exclamation-circle-fill me-2"></i> <?= htmlspecialchars($pesan) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Kartu Statistik & Ubah Tarif Denda -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-icon teal"><i class="bi bi-box-seam"></i></div>
                <div>
                    <div class="stat-label">Peminjaman Aktif (Belum Kembali)</div>
                    <div class="stat-value"><?= $total_aktif ?> Data</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon orange"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="stat-label">Tarif Denda Keterlambatan</div>
                        <div class="stat-value" style="font-size:18px">Rp<?= number_format($tarif_denda, 0, ',', '.') ?> <span class="text-secondary fs-6">/hari</span></div>
                    </div>
                </div>
                <!-- Tombol kecil untuk ubah tarif -->
                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalAturTarif">
                    <i class="bi bi-gear"></i> Ubah Tarif
                </button>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar Pengajuan Dipinjam -->
    <div class="card-list-box">
        <h6 class="fw-bold mb-3"><i class="bi bi-list-task me-2 text-info"></i>Daftar Pengajuan Yang Sedang Dipinjam</h6>

        <?php if ($total_aktif === 0): ?>
            <div class="text-center py-5 text-secondary">
                <i class="bi bi-clipboard-check fs-1 text-muted"></i>
                <p class="mt-2 mb-0">Tidak ada data peminjaman aktif yang ditemukan.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Kode Peminjaman</th>
                            <th>Nama Peminjam</th>
                            <th>Rencana Kembali</th>
                            <th>Status Keterlambatan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $no = 1; 
                    while ($p = mysqli_fetch_assoc($q_pinjam)): 
                        $pid = (int)$p['peminjaman_id'];
                        $batas = $p['tanggal_rencana_kembali'];
                        $hari_ini = date('Y-m-d');
                        $hari_telat = 0;

                        if ($hari_ini > $batas) {
                            $hari_telat = (int)((strtotime($hari_ini) - strtotime($batas)) / 86400);
                        }
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($p['kode_peminjaman']) ?></td>
                            <td><?= htmlspecialchars($p['nama_lengkap'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($batas) ?></td>
                            <td>
                                <?php if ($hari_telat > 0): ?>
                                    <span class="badge bg-danger">Terlambat <?= $hari_telat ?> hari (Rp<?= number_format($hari_telat * $tarif_denda, 0, ',', '.') ?>)</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Tepat Waktu</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalKembali<?= $pid ?>">
                                    <i class="bi bi-box-arrow-in-left me-1"></i> Proses Pengembalian
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>

<!-- MODAL ATUR TARIF DENDA -->
<div class="modal fade" id="modalAturTarif" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Ubah Tarif Denda</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <label class="form-label small">Tarif per Hari (Rp)</label>
                    <input type="number" name="tarif_denda_baru" class="form-control form-control-sm" min="0" step="500" value="<?= $tarif_denda ?>" required>
                </div>
                <div class="modal-footer py-2">
                    <button type="submit" name="ubah_tarif" class="btn btn-success btn-sm w-100">Simpan Tarif</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL PEMERIKSAAN KONDISI ALAT -->
<?php 
mysqli_data_seek($q_pinjam, 0);
while ($p = mysqli_fetch_assoc($q_pinjam)): 
    $pid = (int)$p['peminjaman_id'];
    $q_alat = mysqli_query($koneksi, "
        SELECT d.*, a.nama_alat
        FROM t_detail_peminjaman d
        JOIN t_alat a ON d.alat_id = a.alat_id
        WHERE d.peminjaman_id = $pid
    ");
?>
<div class="modal fade" id="modalKembali<?= $pid ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-left me-2 text-info"></i>Proses Pengembalian: <?= htmlspecialchars($p['kode_peminjaman']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            
            <form method="POST" onsubmit="return confirm('Pastikan kondisi alat dan biaya kerusakan sudah benar. Simpan pengembalian sekarang?')">
                <input type="hidden" name="peminjaman_id" value="<?= $pid ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="small text-secondary">Nama Peminjam: <strong><?= htmlspecialchars($p['nama_lengkap'] ?? '-') ?></strong></div>
                        <div class="small text-secondary">Rencana Kembali: <strong><?= htmlspecialchars($p['tanggal_rencana_kembali']) ?></strong></div>
                    </div>

                    <h6 class="fw-bold mb-2">Pemeriksaan Kondisi Alat</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama Alat</th>
                                    <th>Jumlah</th>
                                    <th>Kondisi Kembali</th>
                                    <th>Biaya Kerusakan</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($a = mysqli_fetch_assoc($q_alat)): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($a['nama_alat']) ?></td>
                                    <td><?= (int)$a['jumlah'] ?> unit</td>
                                    <td>
                                        <select name="kondisi[<?= (int)$a['detail_id'] ?>]" class="form-select form-select-sm kondisi" required>
                                            <option value="baik">Baik</option>
                                            <option value="rusak ringan">Rusak ringan</option>
                                            <option value="rusak berat">Rusak berat</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="biaya_kerusakan[<?= (int)$a['detail_id'] ?>]" class="form-control form-control-sm biaya" min="0" step="1000" value="0">
                                    </td>
                                    <td>
                                        <input type="text" name="keterangan_kerusakan[<?= (int)$a['detail_id'] ?>]" class="form-control form-control-sm keterangan" placeholder="Keterangan jika rusak">
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-info py-2 px-3 small mb-0">
                        <i class="bi bi-info-circle me-1"></i> Denda keterlambatan dihitung otomatis berdasarkan tarif aktif (Rp<?= number_format($tarif_denda, 0, ',', '.') ?>/hari). Alat dengan kondisi <strong>Baik</strong> otomatis kembali ke stok.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="simpan_pengembalian" class="btn btn-primary btn-sm px-4">Simpan Pengembalian</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endwhile; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.kondisi').forEach(select => {
    function aturKolom() {
        const row = select.closest('tr');
        const biaya = row.querySelector('.biaya');
        const keterangan = row.querySelector('.keterangan');
        const rusak = select.value !== 'baik';

        biaya.disabled = !rusak;
        keterangan.disabled = !rusak;
        keterangan.required = rusak;

        if (!rusak) {
            biaya.value = 0;
            keterangan.value = '';
        }
    }

    select.addEventListener('change', aturKolom);
    aturKolom();
});
</script>

</body>
</html>