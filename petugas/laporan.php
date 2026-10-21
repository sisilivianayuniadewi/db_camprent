<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['user_id']) || (int)($_SESSION['user_status'] ?? 0) !== 2) {
    header("Location: ../index.php");
    exit();
}

$tanggal_awal = $_GET['tanggal_awal'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? '';
$status = $_GET['status'] ?? '';

$where = [];

if ($tanggal_awal !== '') {
    $tanggal_awal_db = mysqli_real_escape_string($koneksi, $tanggal_awal);
    $where[] = "p.tanggal_pinjam >= '$tanggal_awal_db'";
}

if ($tanggal_akhir !== '') {
    $tanggal_akhir_db = mysqli_real_escape_string($koneksi, $tanggal_akhir);
    $where[] = "p.tanggal_pinjam <= '$tanggal_akhir_db'";
}

$daftar_status = [
    'Menunggu Persetujuan',
    'Dipinjam',
    'Selesai',
    'Ditolak'
];

if (in_array($status, $daftar_status, true)) {
    $status_db = mysqli_real_escape_string($koneksi, $status);
    $where[] = "p.status = '$status_db'";
}

$where_sql = count($where) > 0
    ? 'WHERE ' . implode(' AND ', $where)
    : '';

$query = mysqli_query($koneksi, "
    SELECT
        p.peminjaman_id,
        p.kode_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        p.status,
        COALESCE(p.total_biaya, 0) AS total_biaya,
        COALESCE(p.status_pembayaran, 'Belum Dibayar') AS status_pembayaran,
        pm.nama_lengkap,
        pg.tanggal_kembali,
        pg.kondisi_kembali,
        pg.catatan,
        COALESCE(pg.terlambat_hari, 0) AS terlambat_hari,
        COALESCE(pg.tarif_denda_per_hari, 5000) AS tarif_denda_per_hari,
        COALESCE(pg.total_denda, 0) AS total_denda,
        COALESCE(d.kerusakan, 0) AS total_kerusakan
    FROM t_peminjaman p
    LEFT JOIN t_peminjam pm
        ON p.peminjam_id = pm.peminjam_id
    LEFT JOIN t_pengembalian pg
        ON p.peminjaman_id = pg.peminjaman_id
    LEFT JOIN (
        SELECT
            peminjaman_id,
            SUM(COALESCE(biaya_kerusakan, 0)) AS kerusakan
        FROM t_detail_peminjaman
        GROUP BY peminjaman_id
    ) d ON p.peminjaman_id = d.peminjaman_id
    $where_sql
    ORDER BY p.peminjaman_id DESC
");

$total_data = 0;
$total_selesai = 0;
$total_sewa = 0;
$total_denda = 0;
$total_kerusakan = 0;
$total_tagihan = 0;

if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        $total_data++;

        $total_sewa += (float)$row['total_biaya'];
        $total_denda += (float)$row['total_denda'];
        $total_kerusakan += (float)$row['total_kerusakan'];

        if ($row['status'] === 'Selesai') {
            $total_selesai++;
        }
    }

    $total_tagihan = $total_denda + $total_kerusakan;

    mysqli_data_seek($query, 0);
}

function rupiah($angka)
{
    return 'Rp' . number_format((float)$angka, 0, ',', '.');
}

function tanggalIndonesia($tanggal)
{
    if (empty($tanggal) || $tanggal === '0000-00-00') {
        return '-';
    }

    return date('d-m-Y', strtotime($tanggal));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Laporan Petugas - CampRent</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root {
    --navy: #102a43;
    --teal: #159eaa;
    --bg: #f1f5f9;
    --muted: #718096;
}

body {
    margin: 0;
    background: var(--bg);
    color: var(--navy);
    font-family: Arial, sans-serif;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 235px;
    height: 100vh;
    padding: 24px 16px;
    color: white;
    background: linear-gradient(180deg, #102a43, #176b78);
    overflow-y: auto;
}

.brand {
    text-align: center;
    padding-bottom: 24px;
    border-bottom: 1px solid #ffffff30;
    margin-bottom: 22px;
}

.brand-icon {
    width: 54px;
    height: 54px;
    border-radius: 16px;
    background: #20b8b1;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-size: 26px;
}

.brand h4 {
    font-weight: 800;
    margin: 0;
}

.brand small {
    color: #c2e3e9;
    font-size: 10px;
}

.menu-label {
    color: #a9dce2;
    font-size: 10px;
    font-weight: bold;
    margin: 22px 10px 10px;
}

.sidebar a {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 12px;
    margin-bottom: 6px;
    border-radius: 9px;
    color: #e1edf5;
    text-decoration: none;
    font-size: 13px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #ffffff22;
    color: white;
}

.sidebar a.active {
    border-left: 3px solid #43ddd0;
}

.main {
    margin-left: 235px;
    padding: 28px;
}

.topbar,
.panel,
.stat-card {
    background: white;
    border-radius: 14px;
    box-shadow: 0 4px 18px #102a4308;
}

.topbar {
    padding: 18px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 25px;
}

.topbar h5 {
    font-weight: 800;
    margin-bottom: 5px;
}

.muted {
    color: var(--muted);
    font-size: 12px;
}

.page-title {
    font-size: 23px;
    font-weight: 800;
    margin-bottom: 5px;
}

.stat-card {
    padding: 20px;
    height: 100%;
    display: flex;
    align-items: center;
    gap: 14px;
}

.stat-icon {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
    background: #e5f7f6;
    color: var(--teal);
}

.stat-label {
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 6px;
}

.stat-value {
    font-size: 19px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.panel {
    padding: 22px;
    margin-bottom: 22px;
}

.panel-title {
    font-size: 16px;
    font-weight: 800;
    margin-bottom: 18px;
}

.form-label {
    font-size: 12px;
    font-weight: bold;
}

.form-control,
.form-select {
    border-color: #dce5ed;
    border-radius: 8px;
    font-size: 13px;
    padding: 10px;
}

.btn-filter {
    background: var(--teal);
    color: white;
    border: 0;
    border-radius: 8px;
    padding: 10px 15px;
}

.btn-filter:hover {
    background: #117d87;
    color: white;
}

.table thead th {
    background: #edf4f8;
    color: #3a5369;
    font-size: 11px;
    white-space: nowrap;
    padding: 14px 12px;
}

.table tbody td {
    font-size: 12px;
    padding: 13px 12px;
    vertical-align: middle;
}

.badge {
    font-weight: 600;
}

.table-responsive {
    border-radius: 9px;
}

@media print {
    .sidebar,
    .topbar,
    .filter-panel,
    .no-print {
        display: none !important;
    }

    .main {
        margin: 0;
        padding: 0;
    }

    body {
        background: white;
    }

    .panel,
    .stat-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}

@media(max-width: 900px) {
    .sidebar {
        position: static;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
        padding: 16px;
    }
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon">
            <i class="bi bi-box-seam"></i>
        </div>
        <h4>CampRent</h4>
        <small>SISTEM PEMINJAMAN ALAT RPL</small>
    </div>

    <div class="menu-label">MENU UTAMA</div>

    <a href="dashboard.php">
        <i class="bi bi-grid-1x2"></i> Dashboard
    </a>

    <a href="peminjaman.php">
        <i class="bi bi-clipboard-check"></i> Peminjaman
    </a>

    <a href="pengembalian.php">
        <i class="bi bi-box-arrow-in-left"></i> Pengembalian
    </a>

    <a href="laporan.php" class="active">
        <i class="bi bi-file-earmark-bar-graph"></i> Laporan
    </a>

    <div class="menu-label">AKUN</div>

    <a href="logout.php" onclick="return confirm('Yakin ingin logout?')">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</aside>

<main class="main">

    <div class="topbar">
        <div>
            <h5>Laporan Peminjaman</h5>
            <div class="muted">Petugas / Laporan</div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center"
                 style="width:40px;height:40px;background:#e5f7f6;color:#159eaa">
                <i class="bi bi-person-badge fs-5"></i>
            </div>

            <div>
                <div class="fw-bold small">
                    <?= htmlspecialchars($_SESSION['username'] ?? 'Petugas') ?>
                </div>
                <div class="muted">Petugas</div>
            </div>
        </div>
    </div>

    <div class="mb-4">
        <div class="page-title">Laporan Peminjaman & Pengembalian</div>
        <div class="muted">
            Pantau riwayat peminjaman, status pembayaran, keterlambatan, dan biaya kerusakan alat.
        </div>
    </div>

    <?php if (!$query): ?>
        <div class="alert alert-danger">
            Gagal mengambil laporan:
            <?= htmlspecialchars(mysqli_error($koneksi)) ?>
        </div>
    <?php endif; ?>

    <!-- Statistik Ringkasan -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div>
                    <div class="stat-label">Total Pendapatan Sewa</div>
                    <div class="stat-value text-success"><?= rupiah($total_sewa) ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#e8f8ec;color:#23964f">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <div class="stat-label">Peminjaman Selesai</div>
                    <div class="stat-value"><?= $total_selesai ?> / <?= $total_data ?> Data</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff2dd;color:#d18a18">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div>
                    <div class="stat-label">Total Denda Terlambat</div>
                    <div class="stat-value text-danger"><?= rupiah($total_denda) ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fdeaea;color:#c0392b">
                    <i class="bi bi-tools"></i>
                </div>
                <div>
                    <div class="stat-label">Total Biaya Kerusakan</div>
                    <div class="stat-value text-danger"><?= rupiah($total_kerusakan) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Laporan -->
    <div class="panel filter-panel">
        <div class="panel-title">
            <i class="bi bi-funnel me-2" style="color:#159eaa"></i>
            Filter Laporan
        </div>

        <form method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Tanggal Pinjam Awal</label>
                    <input type="date" name="tanggal_awal"
                           class="form-control"
                           value="<?= htmlspecialchars($tanggal_awal) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Tanggal Pinjam Akhir</label>
                    <input type="date" name="tanggal_akhir"
                           class="form-control"
                           value="<?= htmlspecialchars($tanggal_akhir) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Status Peminjaman</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <?php foreach ($daftar_status as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>"
                                <?= $status === $s ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-filter flex-grow-1">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>

                    <a href="laporan.php"
                       class="btn btn-outline-secondary"
                       title="Reset filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabel Riwayat -->
    <div class="panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="panel-title mb-0">Riwayat Transaksi & Tagihan</div>

            <button onclick="window.print()"
                    class="btn btn-outline-primary btn-sm no-print">
                <i class="bi bi-printer me-1"></i> Cetak Laporan
            </button>
        </div>

        <div class="muted mb-3">
            Akumulasi keseluruhan denda keterlambatan dan kerusakan:
            <strong><?= rupiah($total_tagihan) ?></strong>
        </div>

        <?php if ($query && $total_data === 0): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-secondary"></i>
                <h6 class="mt-3 fw-bold">Data tidak ditemukan</h6>
                <div class="muted">
                    Belum ada laporan yang sesuai dengan filter.
                </div>
            </div>

        <?php elseif ($query): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode</th>
                        <th>Nama Peminjam</th>
                        <th>Tgl Pinjam</th>
                        <th>Batas Kembali</th>
                        <th>Status</th>
                        <th>Pembayaran</th>
                        <th>Kondisi</th>
                        <th>Terlambat</th>
                        <th>Biaya Sewa</th>
                        <th>Denda Telat</th>
                        <th>Kerusakan</th>
                        <th>Total Tagihan</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php $no = 1; ?>
                    <?php while ($r = mysqli_fetch_assoc($query)): ?>
                        <?php
                        $biaya_sewa = (float)$r['total_biaya'];
                        $denda = (float)$r['total_denda'];
                        $kerusakan = (float)$r['total_kerusakan'];
                        $tagihan = $denda + $kerusakan;
                        $pembayaran = $r['status_pembayaran'] ?? 'Belum Dibayar';

                        $warna = [
                            'Menunggu Persetujuan' => 'warning',
                            'Dipinjam' => 'primary',
                            'Selesai' => 'success',
                            'Ditolak' => 'danger'
                        ];
                        $badge = $warna[$r['status']] ?? 'secondary';
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td class="fw-bold">
                                <?= htmlspecialchars($r['kode_peminjaman']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($r['nama_lengkap'] ?? '-') ?>
                            </td>

                            <td>
                                <?= tanggalIndonesia($r['tanggal_pinjam']) ?>
                            </td>

                            <td>
                                <?= tanggalIndonesia($r['tanggal_rencana_kembali']) ?>
                            </td>

                            <td>
                                <span class="badge text-bg-<?= $badge ?>">
                                    <?= htmlspecialchars($r['status']) ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($pembayaran === 'Lunas'): ?>
                                    <span class="badge bg-success">Lunas</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Belum</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($r['kondisi_kembali'] ?? '-') ?>
                            </td>

                            <td>
                                <?= (int)$r['terlambat_hari'] ?> hari
                            </td>

                            <td class="fw-bold text-success">
                                <?= rupiah($biaya_sewa) ?>
                            </td>

                            <td class="fw-bold text-danger">
                                <?= rupiah($denda) ?>
                            </td>

                            <td class="fw-bold">
                                <?= rupiah($kerusakan) ?>
                            </td>

                            <td class="fw-bold text-danger">
                                <?= rupiah($tagihan) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="muted text-center mt-4">
        CampRent &copy; <?= date('Y') ?> · Sistem Peminjaman Alat RPL
    </div>

</main>
</body>
</html>