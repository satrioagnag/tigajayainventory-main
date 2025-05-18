<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Kasir') {
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

// Inisialisasi variabel
$username = $_SESSION['username'] ?? 'Kasir';
$jumlah_transaksi = 0;
$total_pendapatan = 0;
$produk_terlaris = 'Belum ada transaksi';
$labels = [];
$data = [];

// Query untuk Ringkasan Transaksi Hari Ini
$query_jumlah_transaksi = "SELECT COUNT(*) AS jumlah FROM tbl_transaksi WHERE DATE(tanggal) = CURDATE()";
$result_jumlah_transaksi = $conn->query($query_jumlah_transaksi);
if ($result_jumlah_transaksi) {
    $jumlah_transaksi = $result_jumlah_transaksi->fetch_assoc()['jumlah'] ?? 0;
}

$query_total_pendapatan = "SELECT SUM(harga_total) AS total FROM tbl_transaksi WHERE DATE(tanggal) = CURDATE()";
$result_total_pendapatan = $conn->query($query_total_pendapatan);
if ($result_total_pendapatan) {
    $total_pendapatan = $result_total_pendapatan->fetch_assoc()['total'] ?? 0;
}

$query_produk_terlaris = "SELECT p.nama, SUM(t.jumlah) AS total_terjual 
                          FROM tbl_transaksi t 
                          JOIN tbl_produk p ON t.produk_id = p.id 
                          WHERE DATE(t.tanggal) = CURDATE() 
                          GROUP BY t.produk_id 
                          ORDER BY total_terjual DESC 
                          LIMIT 1";
$result_produk_terlaris = $conn->query($query_produk_terlaris);
if ($result_produk_terlaris && $result_produk_terlaris->num_rows > 0) {
    $produk_terlaris = $result_produk_terlaris->fetch_assoc()['nama'];
}

// Query untuk Grafik Statistik Penjualan (7 Hari Terakhir)
$query_grafik = "SELECT DATE(tanggal) AS tanggal, SUM(harga_total) AS total 
                 FROM tbl_transaksi 
                 WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                 GROUP BY DATE(tanggal) 
                 ORDER BY tanggal ASC";
$result_grafik = $conn->query($query_grafik);

if ($result_grafik) {
    while ($row = $result_grafik->fetch_assoc()) {
        $labels[] = date('d M', strtotime($row['tanggal']));
        $data[] = $row['total'] ?? 0;
    }
}

// Query untuk Transaksi Terakhir
$query_transaksi_terakhir = "SELECT t.id, p.nama as produk, t.jumlah, t.harga_total, t.tanggal 
                            FROM tbl_transaksi t
                            JOIN tbl_produk p ON t.produk_id = p.id
                            ORDER BY t.tanggal DESC LIMIT 5";
$transaksi_terakhir = $conn->query($query_transaksi_terakhir);

// Produk dengan stok rendah
$query_stok_rendah = "SELECT nama, stok, min_stok FROM tbl_produk WHERE stok <= min_stok ORDER BY stok ASC LIMIT 5";
$stok_rendah = $conn->query($query_stok_rendah);

$data_stok_rendah = array();
if ($stok_rendah->num_rows > 0) {
    while ($row = $stok_rendah->fetch_assoc()) {
        $data_stok_rendah[] = $row;
    }
}

// Konversikan data ke format JSON
$json_stok_rendah = json_encode($data_stok_rendah);
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kasir</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .kasir-nav {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .dashboard-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-card {
            color: white;
            padding: 20px;
            border-radius: 8px;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .stat-card i {
            font-size: 2.5rem;
            opacity: 0.2;
            position: absolute;
            right: 20px;
            top: 20px;
        }

        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .card-header {
            font-weight: 600;
            background-color: var(--primary-color);
            color: white;
        }

        .transaction-item {
            border-left: 3px solid var(--accent-color);
            transition: all 0.2s ease;
            margin-bottom: 8px;
        }

        .transaction-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }

        .quick-action-btn {
            transition: all 0.2s ease;
        }

        .quick-action-btn:hover {
            transform: translateY(-3px);
        }

        .date-display {
            background-color: white;
            padding: 5px 15px;
            border-radius: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            font-weight: 500;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark kasir-nav">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-cash-stack me-2"></i> Kasir Tiga Jaya Motor
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transaksi.php">
                            <i class="bi bi-cart-plus me-1"></i> Transaksi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="riwayat_transaksi.php">
                            <i class="bi bi-receipt me-1"></i> Riwayat
                        </a>
                    </li>
                    <li class="nav-item">
                        <button class="btn btn-outline-light ms-2" onclick="logout()">
                            <i class="bi bi-box-arrow-right me-1"></i> Logout
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0"><i class="bi bi-speedometer2 me-2"></i> Dashboard Kasir</h2>
                <p class="mb-0 text-muted">Selamat datang, <strong><?php echo htmlspecialchars($username); ?></strong>
                </p>
            </div>
            <div class="date-display">
                <i class="bi bi-calendar3 me-2"></i><?php echo date('l, d F Y'); ?>
            </div>
        </div>

        <!-- Statistik Ringkasan -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stat-card" style="background: linear-gradient(135deg, var(--accent-color), #2980b9);">
                    <i class="bi bi-cart-check"></i>
                    <div class="stat-value"><?php echo $jumlah_transaksi; ?></div>
                    <div class="stat-label">Transaksi Hari Ini</div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="stat-card" style="background: linear-gradient(135deg, var(--success-color), #2ecc71);">
                    <i class="bi bi-currency-dollar"></i>
                    <div class="stat-value">Rp<?php echo number_format($total_pendapatan, 0, ',', '.'); ?></div>
                    <div class="stat-label">Pendapatan Hari Ini</div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="stat-card" style="background: linear-gradient(135deg, var(--warning-color), #f1c40f);">
                    <i class="bi bi-trophy"></i>
                    <div class="stat-value">
                        <?php echo htmlspecialchars(mb_strimwidth($produk_terlaris, 0, 15, '...')); ?></div>
                    <div class="stat-label">Produk Terlaris</div>
                </div>
            </div>
        </div>

        <!-- Dua Kolom Konten -->
        <div class="row">
            <!-- Kolom Pertama - Grafik -->
            <div class="col-lg-8 mb-4">
                <div class="dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-graph-up me-2"></i> Statistik Penjualan 7 Hari Terakhir</span>
                        <span class="badge bg-light text-dark"><?php echo date('d M Y'); ?></span>
                    </div>
                    <div class="card-body">
                        <canvas id="penjualanChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Kolom Kedua - Transaksi Terakhir -->
            <div class="col-lg-4 mb-4">
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="bi bi-clock-history me-2"></i> Transaksi Terakhir
                    </div>
                    <div class="card-body">
                        <?php if ($transaksi_terakhir && $transaksi_terakhir->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while ($row = $transaksi_terakhir->fetch_assoc()):
                                    $harga_satuan = ($row['jumlah'] > 0) ? $row['harga_total'] / $row['jumlah'] : 0;
                                    ?>
                                    <div class="transaction-item p-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($row['produk']); ?></h6>
                                                <small class="text-muted"><?php echo $row['jumlah']; ?> x
                                                    Rp<?php echo number_format($harga_satuan, 0, ',', '.'); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <small
                                                    class="text-muted"><?php echo date('H:i', strtotime($row['tanggal'])); ?></small>
                                                <div class="fw-bold text-success">
                                                    Rp<?php echo number_format($row['harga_total'], 0, ',', '.'); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-cart-x text-muted" style="font-size: 3rem;"></i>
                                <p class="mt-2 text-muted">Belum ada transaksi</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="bi bi-lightning me-2"></i> Akses Cepat
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <a href="transaksi.php" class="btn btn-primary w-100 quick-action-btn">
                                    <i class="bi bi-cart-plus me-1"></i> Transaksi Baru
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="riwayat_transaksi.php" class="btn btn-success w-100 quick-action-btn">
                                    <i class="bi bi-receipt me-1"></i> Riwayat
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

        const stokRendahData = <?php echo $json_stok_rendah; ?>;

         function showAlertStokRendah() {
            if (stokRendahData.length > 0) {
                let pesan = "Peringatan! Beberapa produk memiliki stok rendah:\n";
                stokRendahData.forEach(produk => {
                    pesan += `- ${produk.nama} (Stok: ${produk.stok}, Minimal: ${produk.min_stok})\n`;
                });
                alert(pesan);
            } else {
                console.log("Tidak ada produk dengan stok rendah.");
            }
        }

                window.addEventListener('load', showAlertStokRendah);

        // Grafik Statistik Penjualan
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('penjualanChart').getContext('2d');
            const penjualanChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        label: 'Pendapatan Harian',
                        data: <?php echo json_encode($data); ?>,
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: 'rgba(52, 152, 219, 1)',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return 'Rp' + context.raw.toLocaleString('id-ID');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return 'Rp' + value.toLocaleString('id-ID');
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>