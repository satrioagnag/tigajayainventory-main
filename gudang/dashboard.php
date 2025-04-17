<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Gudang') {
    header("Location: ../index.html");
    exit();
}

// Koneksi database
$host = "localhost";
$user = "root";
$password = "";
$dbname = "tigajayamotor_inventory";
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Query data untuk dashboard
$query_stok_total = "SELECT SUM(stok) as total FROM tbl_produk";
$result_stok_total = $conn->query($query_stok_total);
$stok_total = $result_stok_total->fetch_assoc()['total'];

$query_barang_masuk = "SELECT COUNT(*) as total FROM tbl_riwayat_masuk WHERE DATE(tanggal) = CURDATE()";
$result_barang_masuk = $conn->query($query_barang_masuk);
$barang_masuk = $result_barang_masuk->fetch_assoc()['total'];

$query_barang_keluar = "SELECT COUNT(*) as total FROM tbl_riwayat_keluar WHERE DATE(tanggal) = CURDATE()";
$result_barang_keluar = $conn->query($query_barang_keluar);
$barang_keluar = $result_barang_keluar->fetch_assoc()['total'];

$query_stok_minimal = "SELECT COUNT(*) as total FROM tbl_produk WHERE stok <= min_stok";
$result_stok_minimal = $conn->query($query_stok_minimal);
$stok_minimal = $result_stok_minimal->fetch_assoc()['total'];

$query_riwayat_terakhir = "SELECT 
    p.nama as produk, 
    rm.jumlah, 
    rm.tanggal,
    'Masuk' as tipe
    FROM tbl_riwayat_masuk rm
    JOIN tbl_produk p ON rm.produk_id = p.id
    ORDER BY rm.tanggal DESC LIMIT 5";
$riwayat_terakhir = $conn->query($query_riwayat_terakhir);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Gudang</title>
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-box-seam"></i> Inventory System - Gudang
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="kelola_stok.php">
                            <i class="bi bi-boxes"></i> Kelola Stok
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="riwayat_masuk.php">
                            <i class="bi bi-box-arrow-in-down"></i> Riwayat Masuk
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="riwayat_keluar.php">
                            <i class="bi bi-box-arrow-up"></i> Riwayat Keluar
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
            <h2><i class="bi bi-speedometer2"></i> Dashboard Gudang</h2>
            <div class="text-muted">
                <?php echo date('l, d F Y'); ?>
            </div>
        </div>
        
        <p class="mb-4">Selamat datang, <strong><?php echo $_SESSION['username'] ?? 'Pengguna'; ?></strong>! di sistem manajemen stok gudang.</p>

        <!-- Ringkasan Statistik -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card-counter primary">
                    <i class="bi bi-box-seam"></i>
                    <span class="count-numbers"><?php echo $stok_total; ?></span>
                    <span class="count-name">Total Stok</span>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card-counter success">
                    <i class="bi bi-box-arrow-in-down"></i>
                    <span class="count-numbers"><?php echo $barang_masuk; ?></span>
                    <span class="count-name">Barang Masuk Hari Ini</span>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card-counter warning">
                    <i class="bi bi-box-arrow-up"></i>
                    <span class="count-numbers"><?php echo $barang_keluar; ?></span>
                    <span class="count-name">Barang Keluar Hari Ini</span>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card-counter danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span class="count-numbers"><?php echo $stok_minimal; ?></span>
                    <span class="count-name">Stok Perlu Restock</span>
                </div>
            </div>
        </div>

        <!-- Dua Kolom Konten -->
        <div class="row">
            <!-- Kolom Pertama -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-clock-history"></i> Riwayat Terakhir
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Jumlah</th>
                                        <th>Tanggal</th>
                                        <th>Tipe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $riwayat_terakhir->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['produk']; ?></td>
                                        <td><?php echo $row['jumlah']; ?></td>
                                        <td><?php echo $row['tanggal']; ?></td>
                                        <td>
                                            <?php if($row['tipe'] == 'Masuk'): ?>
                                                <span class="badge bg-success"><?php echo $row['tipe']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-warning"><?php echo $row['tipe']; ?></span>
                                            <?php endif; ?>
                                        </td>
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
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-exclamation-triangle"></i> Stok Perlu Perhatian
                    </div>
                    <div class="card-body">
                        <?php 
                        $query_stok_perhatian = "SELECT p.nama, p.stok, p.min_stok 
                                               FROM tbl_produk p 
                                               WHERE p.stok <= p.min_stok 
                                               ORDER BY p.stok ASC 
                                               LIMIT 5";
                        $stok_perhatian = $conn->query($query_stok_perhatian);
                        
                        if($stok_perhatian->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while($item = $stok_perhatian->fetch_assoc()): ?>
                                <a href="kelola_stok.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo $item['nama']; ?></h6>
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
                                <a href="kelola_stok.php?action=add" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-circle"></i> Tambah Stok
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="riwayat_masuk.php" class="btn btn-success w-100">
                                    <i class="bi bi-list-check"></i> Lihat Riwayat
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
            sessionStorage.clear();
            localStorage.clear();
            window.location.href = "../index.html";
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>