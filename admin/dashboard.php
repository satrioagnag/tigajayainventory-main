<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['isAuthenticated']) || $_SESSION['isAuthenticated'] !== true) {
    header("Location: ../index.html");
    exit();
}

if ($_SESSION['role'] !== 'Admin') {
    header("Location: ../index.html");
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$dbname = "tigajayamotor_inventory";
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Query untuk Admin Dashboard
$query_total_produk = "SELECT COUNT(*) as total FROM tbl_produk";
$result_total_produk = $conn->query($query_total_produk);
$total_produk = $result_total_produk->fetch_assoc()['total'];

$query_total_kategori = "SELECT COUNT(*) as total FROM tbl_kategori";
$result_total_kategori = $conn->query($query_total_kategori);
$total_kategori = $result_total_kategori->fetch_assoc()['total'];

$query_total_transaksi = "SELECT COUNT(*) as total FROM (
                          SELECT id FROM tbl_riwayat_masuk WHERE DATE(tanggal) = CURDATE()
                          UNION ALL
                          SELECT id FROM tbl_riwayat_keluar WHERE DATE(tanggal) = CURDATE()
                        ) as combined";
$result_total_transaksi = $conn->query($query_total_transaksi);
$total_transaksi = $result_total_transaksi->fetch_assoc()['total'];

// Riwayat aktivitas terakhir
$query_riwayat = "SELECT 
                 'Masuk' as tipe, p.nama as produk, rm.jumlah, rm.tanggal
                 FROM tbl_riwayat_masuk rm
                 JOIN tbl_produk p ON rm.produk_id = p.id
                 UNION ALL
                 SELECT 
                 'Keluar' as tipe, p.nama as produk, rk.jumlah, rk.tanggal
                 FROM tbl_riwayat_keluar rk
                 JOIN tbl_produk p ON rk.produk_id = p.id
                 ORDER BY tanggal DESC LIMIT 5";
$riwayat_terakhir = $conn->query($query_riwayat);

// Produk dengan stok rendah
$query_stok_rendah = "SELECT nama, stok, min_stok FROM tbl_produk WHERE stok <= min_stok ORDER BY stok ASC LIMIT 5";
$stok_rendah = $conn->query($query_stok_rendah);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Inventory System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card-counter {
            box-shadow: 2px 2px 10px #DADADA;
            margin: 5px;
            padding: 20px 10px;
            border-radius: 5px;
            transition: .3s linear all;
        }
        .card-counter:hover {
            box-shadow: 4px 4px 20px #DADADA;
            transition: .3s linear all;
        }
        .card-counter.primary {
            background-color: #007bff;
            color: #FFF;
        }
        .card-counter.success {
            background-color: #28a745;
            color: #FFF;
        }
        .card-counter.warning {
            background-color: #ffc107;
            color: #FFF;
        }
        .card-counter.danger {
            background-color: #dc3545;
            color: #FFF;
        }
        .card-counter i {
            font-size: 2.5em;
            opacity: 0.3;
        }
        .card-counter .count-numbers {
            font-size: 1.8em;
            display: block;
        }
        .card-counter .count-name {
            font-style: italic;
            opacity: 0.8;
            display: block;
            font-size: 1em;
        }
        .admin-nav {
            background: linear-gradient(135deg, #2c3e50, #34495e);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark admin-nav">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-shield-lock"></i> Inventory System - Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="manage_produk.php">
                            <i class="bi bi-box-seam"></i> Produk
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_member.php">
                            <i class="bi bi-people"></i> Membership
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="bi bi-graph-up"></i> Laporan
                        </a>
                    </li>
                    <li class="nav-item">
                        <button class="btn btn-danger" onclick="logout()">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-speedometer2"></i> Dashboard Admin</h2>
            <div class="text-muted">
                <?php echo date('l, d F Y'); ?>
            </div>
        </div>
        
        <p class="mb-4">Selamat datang, <strong><?php echo $_SESSION['username'] ?? 'Admin'; ?></strong>! Anda login sebagai Administrator Sistem.</p>

        <!-- Ringkasan Statistik -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card-counter primary">
                    <i class="bi bi-box-seam"></i>
                    <span class="count-numbers"><?php echo $total_produk; ?></span>
                    <span class="count-name">Total Produk</span>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card-counter success">
                    <i class="bi bi-tags"></i>
                    <span class="count-numbers"><?php echo $total_kategori; ?></span>
                    <span class="count-name">Kategori Produk</span>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card-counter warning">
                    <i class="bi bi-arrow-left-right"></i>
                    <span class="count-numbers"><?php echo $total_transaksi; ?></span>
                    <span class="count-name">Transaksi Hari Ini</span>
                </div>
            </div>
        </div>

        <!-- Dua Kolom Konten -->
        <div class="row">
            <!-- Kolom Pertama -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-clock-history"></i> Aktivitas Terakhir
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Tipe</th>
                                        <th>Jumlah</th>
                                        <th>Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $riwayat_terakhir->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['produk']); ?></td>
                                        <td>
                                            <?php if($row['tipe'] == 'Masuk'): ?>
                                                <span class="badge bg-success"><?php echo $row['tipe']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-warning"><?php echo $row['tipe']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $row['jumlah']; ?></td>
                                        <td><?php echo date('H:i', strtotime($row['tanggal'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Kolom Kedua -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <i class="bi bi-exclamation-triangle"></i> Stok Perlu Perhatian
                    </div>
                    <div class="card-body">
                        <?php if($stok_rendah->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while($item = $stok_rendah->fetch_assoc()): ?>
                                <a href="manage_produk.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['nama']); ?></h6>
                                        <small class="text-danger"><?php echo $item['stok']; ?> / <?php echo $item['min_stok']; ?></small>
                                    </div>
                                    <div class="progress mt-2">
                                        <?php 
                                        $percentage = ($item['stok'] / $item['min_stok']) * 100;
                                        if($percentage > 100) $percentage = 100;
                                        ?>
                                        <div class="progress-bar bg-danger" 
                                             role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%" 
                                             aria-valuenow="<?php echo $percentage; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100"></div>
                                    </div>
                                </a>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> Semua stok dalam kondisi aman
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-lightning"></i> Akses Cepat
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <a href="manage_produk.php" class="btn btn-primary w-100">
                                    <i class="bi bi-box-seam"></i> Kelola Produk
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="reports.php" class="btn btn-success w-100">
                                    <i class="bi bi-graph-up"></i> Lihat Laporan
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function logout() {
            if (confirm('Apakah Anda yakin ingin logout?')) {
                sessionStorage.clear();
                localStorage.clear();
                window.location.href = "../index.html";
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>