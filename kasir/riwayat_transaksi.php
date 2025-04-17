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

// Ambil riwayat transaksi dengan filter tanggal
$query = "SELECT t.id, p.nama AS produk, t.jumlah, t.harga_total, t.diskon, t.pembayaran, t.kembalian, t.metode_pembayaran, t.tanggal 
          FROM tbl_transaksi t
          JOIN tbl_produk p ON t.produk_id = p.id";

// Filter berdasarkan tanggal
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $conn->real_escape_string($_GET['start_date']);
    $end_date = $conn->real_escape_string($_GET['end_date']);
    $query .= " WHERE t.tanggal BETWEEN '$start_date' AND '$end_date 23:59:59'";
}

$query .= " ORDER BY t.tanggal DESC";
$result = $conn->query($query);

// Hitung total pendapatan
$total_pendapatan = 0;
$total_transaksi = 0;
if ($result->num_rows > 0) {
    $total_transaksi = $result->num_rows;
    $result->data_seek(0); // Reset pointer untuk iterasi kedua
    while ($row = $result->fetch_assoc()) {
        $total_pendapatan += $row['harga_total'] - $row['diskon'];
    }
    $result->data_seek(0); // Reset pointer lagi untuk tampilan
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir - Riwayat Transaksi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .kasir-nav {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        .card-kasir {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: none;
        }
        
        .card-kasir:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
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
        
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .table thead th {
            position: sticky;
            top: 0;
            background-color: var(--primary-color);
            color: white;
            z-index: 10;
        }
        
        .badge-cash {
            background-color: var(--success-color);
        }
        
        .badge-transfer {
            background-color: var(--accent-color);
        }
        
        .badge-qris {
            background-color: #9b59b6;
        }
        
        .badge-card {
            background-color: #f39c12;
        }
        
        .print-area {
            display: none;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            .print-area, .print-area * {
                visibility: visible;
            }
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
        
        .btn-print {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-print:hover {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .card-summary {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark kasir-nav">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-cash-stack"></i> Kasir Tiga Jaya Motor
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link text-white">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['nama'] ?? 'Kasir'); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="riwayat_transaksi.php">
                            <i class="bi bi-clock-history"></i> Riwayat
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
            <h2><i class="bi bi-clock-history me-2"></i>Riwayat Transaksi</h2>
            <div class="no-print">
                <button class="btn btn-print" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Cetak Laporan
                </button>
            </div>
        </div>

        <!-- Card Summary -->
        <div class="row mb-4 no-print">
            <div class="col-md-4">
                <div class="card card-summary h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-receipt me-2"></i>Total Transaksi</h5>
                        <p class="card-text display-6"><?php echo $total_transaksi; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-summary h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-currency-exchange me-2"></i>Total Pendapatan</h5>
                        <p class="card-text display-6">Rp<?php echo number_format($total_pendapatan, 0, ',', '.'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-summary h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-calendar-range me-2"></i>Periode</h5>
                        <p class="card-text">
                            <?php if (isset($_GET['start_date']) && isset($_GET['end_date'])): ?>
                                <?php echo date('d M Y', strtotime($_GET['start_date'])); ?> - <?php echo date('d M Y', strtotime($_GET['end_date'])); ?>
                            <?php else: ?>
                                Semua Transaksi
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Filter Tanggal -->
        <div class="card mb-4 no-print">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-funnel me-1"></i>Filter Transaksi
            </div>
            <div class="card-body">
                <form method="GET">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Selesai</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-filter me-1"></i>Filter
                            </button>
                            <?php if (isset($_GET['start_date'])): ?>
                                <a href="riwayat_transaksi.php" class="btn btn-outline-danger ms-2">
                                    <i class="bi bi-x-circle me-1"></i>Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Riwayat -->
        <div class="print-area">
            <div class="text-center mb-4">
                <h3>LAPORAN TRANSAKSI</h3>
                <h5>TIGA JAYA MOTOR</h5>
                <p>Jl. Erlangga No.6, Katang, Sukorejo, Kec. Ngasem, Kabupaten Kediri</p>
                <p>
                    <?php if (isset($_GET['start_date']) && isset($_GET['end_date'])): ?>
                        Periode: <?php echo date('d F Y', strtotime($_GET['start_date'])); ?> - <?php echo date('d F Y', strtotime($_GET['end_date'])); ?>
                    <?php else: ?>
                        Semua Riwayat Transaksi
                    <?php endif; ?>
                </p>
                <hr>
                <div class="d-flex justify-content-between">
                    <p class="text-start"><strong>Total Transaksi:</strong> <?php echo $total_transaksi; ?></p>
                    <p class="text-end"><strong>Total Pendapatan:</strong> Rp<?php echo number_format($total_pendapatan, 0, ',', '.'); ?></p>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Produk</th>
                            <th class="text-end">Jumlah</th>
                            <th class="text-end">Harga</th>
                            <th class="text-end">Diskon</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Bayar</th>
                            <th class="text-end">Kembali</th>
                            <th>Metode</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while ($row = $result->fetch_assoc()): 
                            // Tentukan class badge berdasarkan metode pembayaran
                            $metode_class = '';
                            if (strtolower($row['metode_pembayaran']) == 'cash') {
                                $metode_class = 'badge-cash';
                            } elseif (strtolower($row['metode_pembayaran']) == 'transfer bank') {
                                $metode_class = 'badge-transfer';
                            } elseif (strtolower($row['metode_pembayaran']) == 'qris') {
                                $metode_class = 'badge-qris';
                            } elseif (strtolower($row['metode_pembayaran']) == 'debit/credit card') {
                                $metode_class = 'badge-card';
                            } else {
                                $metode_class = 'badge-secondary';
                            }
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($row['tanggal'])); ?></td>
                                <td><?php echo htmlspecialchars($row['produk']); ?></td>
                                <td class="text-end"><?php echo $row['jumlah']; ?></td>
                                <td class="text-end">Rp<?php echo number_format($row['harga_total'] / $row['jumlah'], 0, ',', '.'); ?></td>
                                <td class="text-end">Rp<?php echo number_format($row['diskon'], 0, ',', '.'); ?></td>
                                <td class="text-end">Rp<?php echo number_format($row['harga_total'], 0, ',', '.'); ?></td>
                                <td class="text-end">Rp<?php echo number_format($row['pembayaran'], 0, ',', '.'); ?></td>
                                <td class="text-end">Rp<?php echo number_format($row['kembalian'], 0, ',', '.'); ?></td>
                                <td class="text-center"><span class="badge rounded-pill <?php echo $metode_class; ?>"><?php echo $row['metode_pembayaran']; ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="table-active">
                        <tr>
                            <td colspan="6" class="text-end"><strong>Total Keseluruhan:</strong></td>
                            <td class="text-end"><strong>Rp<?php echo number_format($total_pendapatan, 0, ',', '.'); ?></strong></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="mt-4 text-end">
                <p>Dicetak pada: <?php echo date('d F Y H:i:s'); ?></p>
                <div class="d-flex justify-content-end mt-5">
                    <div class="text-center">
                        <p>Mengetahui,</p>
                        <br><br><br>
                        <p>_________________________</p>
                        <p>Manager</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
       function logout() {
            if (confirm('Apakah Anda yakin ingin logout?')) {
                sessionStorage.clear();
                localStorage.clear();
                window.location.href = "../index.html";
            }
        }
    </script>
</body>
</html>