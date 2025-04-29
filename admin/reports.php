<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.html");
    exit();
}

// Koneksi ke database
$host = "localhost";
$user = "root";
$password = "";
$dbname = "tigajayamotor_inventory";
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Ambil data laporan stok
$query_stok = "SELECT p.id, p.nama, p.merek, k.nama AS kategori, p.harga, p.stok, p.min_stok 
               FROM tbl_produk p 
               LEFT JOIN tbl_kategori k ON p.kategori_id = k.id
               ORDER BY p.stok ASC";
$result_stok = $conn->query($query_stok);

// Hitung statistik
$query_stats = "SELECT 
                COUNT(*) as total_produk,
                SUM(stok) as total_stok,
                SUM(CASE WHEN stok <= min_stok THEN 1 ELSE 0 END) as stok_rendah,
                SUM(CASE WHEN stok = 0 THEN 1 ELSE 0 END) as stok_habis
                FROM tbl_produk";
$stats = $conn->query($query_stats)->fetch_assoc();

// Query untuk data grafik stok per kategori
$query_chart = "SELECT 
                k.nama AS kategori,
                SUM(p.stok) AS total_stok,
                COUNT(p.id) AS jumlah_produk
                FROM tbl_produk p
                LEFT JOIN tbl_kategori k ON p.kategori_id = k.id
                GROUP BY k.nama
                ORDER BY total_stok DESC";
$result_chart = $conn->query($query_chart);

// Siapkan data untuk Chart.js
$chart_labels = [];
$chart_data = [];
$chart_bg_color = [];
$chart_border_color = [];
$chart_jumlah_produk = [];

// Warna untuk chart
$colors = [
    'rgba(75, 192, 192, 0.7)',
    'rgba(54, 162, 235, 0.7)',
    'rgba(255, 206, 86, 0.7)',
    'rgba(153, 102, 255, 0.7)',
    'rgba(255, 99, 132, 0.7)',
    'rgba(199, 199, 199, 0.7)'
];

$color_index = 0;
while ($row = $result_chart->fetch_assoc()) {
    $chart_labels[] = $row['kategori'];
    $chart_data[] = $row['total_stok'];
    $chart_jumlah_produk[] = $row['jumlah_produk'];
    
    // Gunakan warna yang sudah ditentukan
    $chart_bg_color[] = $colors[$color_index % count($colors)];
    $chart_border_color[] = str_replace('0.7', '1', $colors[$color_index % count($colors)]);
    $color_index++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Stok - Inventory System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-report {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        .card-report:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .stock-low {
            background-color: #fff8f8;
        }
        .stock-out {
            background-color: #ffebee;
        }
        .search-box {
            position: relative;
        }
        .search-box .form-control {
            padding-left: 40px;
        }
        .search-box i {
            position: absolute;
            left: 15px;
            top: 12px;
            color: #6c757d;
        }
        .progress-thin {
            height: 5px;
        }
        .export-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .stat-card {
            color: white;
            padding: 15px;
            border-radius: 8px;
            height: 100%;
        }
        .stat-card i {
            font-size: 2rem;
            opacity: 0.8;
        }
    </style>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #2c3e50, #34495e);">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-clipboard-data"></i> Inventory System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link text-white">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['nama'] ?? 'Admin'); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <button class="btn btn-danger ms-2" onclick="logout()">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-clipboard-data"></i> Laporan Stok Produk</h2>
            <div class="text-muted">
                <?php echo date('l, d F Y'); ?>
            </div>
        </div>

        <!-- Statistik Ringkasan -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-primary">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title"><i class="bi bi-box-seam"></i> Total Produk</h5>
                            <h2 class="mb-0"><?php echo $stats['total_produk']; ?></h2>
                        </div>
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-success">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title"><i class="bi bi-boxes"></i> Total Stok</h5>
                            <h2 class="mb-0"><?php echo $stats['total_stok']; ?></h2>
                        </div>
                        <i class="bi bi-boxes"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-warning">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title"><i class="bi bi-exclamation-triangle"></i> Stok Rendah</h5>
                            <h2 class="mb-0"><?php echo $stats['stok_rendah']; ?></h2>
                        </div>
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card bg-danger">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title"><i class="bi bi-x-circle"></i> Stok Habis</h5>
                            <h2 class="mb-0"><?php echo $stats['stok_habis']; ?></h2>
                        </div>
                        <i class="bi bi-x-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grafik Stok -->
        <div class="card card-report mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Distribusi Stok per Kategori</h5>
            </div>
            <div class="card-body">
                <canvas id="stockChart" height="100"></canvas>
            </div>
        </div>

        <!-- Filter dan Pencarian -->
        <div class="card card-report mb-4">
            <div class="card-header bg-light">
                <div class="row">
                    <div class="col-md-6">
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" class="form-control" id="searchInput" placeholder="Cari produk...">
                        </div>
                        <div class="">

                        </div>
                    </div>
                    <div class="col-md-6 text-end export-buttons">
                        <button class="btn btn-outline-primary" onclick="printReport()">
                            <i class="bi bi-printer"></i> Cetak
                        </button>
                        <button class="btn btn-outline-success" onclick="exportToExcel()">
                            <i class="bi bi-file-excel"></i> Excel
                        </button>
                        <button class="btn btn-outline-danger" onclick="exportToPDF()">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Laporan -->
        <div class="card card-report">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-table"></i> Detail Stok Produk</h5>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="stockTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="5%">ID</th>
                                <th width="20%">Nama Produk</th>
                                <th width="15%">Merek</th>
                                <th width="15%">Kategori</th>
                                <th width="15%">Harga</th>
                                <th width="10%">Stok</th>
                                <th width="10%">Min. Stok</th>
                                <th width="10%">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $result_stok->data_seek(0); // Reset pointer hasil query
                            while ($row = $result_stok->fetch_assoc()): 
                                $stock_class = '';
                                $stock_status = 'Aman';
                                $percentage = ($row['stok'] / ($row['min_stok'] > 0 ? $row['min_stok'] : 1)) * 100;
                                
                                if ($row['stok'] <= 0) {
                                    $stock_class = 'stock-out';
                                    $stock_status = 'Habis';
                                } elseif ($row['stok'] <= $row['min_stok']) {
                                    $stock_class = 'stock-low';
                                    $stock_status = 'Rendah';
                                }
                            ?>
                                <tr class="<?php echo $stock_class; ?>">
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                    <td><?php echo htmlspecialchars($row['merek']); ?></td>
                                    <td><?php echo htmlspecialchars($row['kategori']); ?></td>
                                    <td>Rp<?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
                                    <td><?php echo $row['stok']; ?></td>
                                    <td><?php echo $row['min_stok']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if($stock_status == 'Aman'): ?>
                                                <span class="badge bg-success me-2"><?php echo $stock_status; ?></span>
                                            <?php elseif($stock_status == 'Rendah'): ?>
                                                <span class="badge bg-warning me-2"><?php echo $stock_status; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger me-2"><?php echo $stock_status; ?></span>
                                            <?php endif; ?>
                                            <div class="progress progress-thin w-100">
                                                <div class="progress-bar 
                                                    <?php echo $stock_status == 'Habis' ? 'bg-danger' : ($stock_status == 'Rendah' ? 'bg-warning' : 'bg-success'); ?>" 
                                                    role="progressbar" 
                                                    style="width: <?php echo min($percentage, 100); ?>%" 
                                                    aria-valuenow="<?php echo $percentage; ?>" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="100">
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi pencarian
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const input = this.value.toLowerCase();
            const rows = document.querySelectorAll('#stockTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        });

        // Fungsi logout
        function logout() {
            if (confirm('Apakah Anda yakin ingin logout?')) {
                sessionStorage.clear();
                localStorage.clear();
                window.location.href = "../index.html";
            }
        }

        // Fungsi ekspor
        function printReport() {
            window.print();
        }

        function exportToExcel() {
            // Implementasi ekspor ke Excel bisa menggunakan library seperti SheetJS
            alert('Fitur ekspor ke Excel akan diimplementasikan');
        }

        function exportToPDF() {
            // Implementasi ekspor ke PDF bisa menggunakan library seperti jsPDF
            alert('Fitur ekspor ke PDF akan diimplementasikan');
        }

        // Grafik Stok
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('stockChart').getContext('2d');
            
            const stockChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'Total Stok',
                        data: <?php echo json_encode($chart_data); ?>,
                        backgroundColor: <?php echo json_encode($chart_bg_color); ?>,
                        borderColor: <?php echo json_encode($chart_border_color); ?>,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Distribusi Stok per Kategori'
                        },
                        tooltip: {
                            callbacks: {
                                afterLabel: function(context) {
                                    const index = context.dataIndex;
                                    const jumlahProduk = <?php echo json_encode($chart_jumlah_produk); ?>;
                                    return 'Jumlah Produk: ' + jumlahProduk[index];
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Jumlah Stok'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Kategori Produk'
                            }
                        }
                    }
                }
            });
        });
        
        $(document).ready(function() {
    $('#stockTable').DataTable({
        "ordering": true,
        "paging": true,
        "info": true,
        "language": {
            "search": "Cari:",
            "lengthMenu": "Tampilkan _MENU_ data per halaman",
            "zeroRecords": "Tidak ada data ditemukan",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Berikutnya",
                "previous": "Sebelumnya"
            }
        }
    });
});

    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

</body>
</html>