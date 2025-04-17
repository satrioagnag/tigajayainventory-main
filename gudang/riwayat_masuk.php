<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Gudang') {
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

// Ambil data riwayat barang masuk
$query = "SELECT r.id, p.nama AS produk, r.jumlah, r.tanggal 
          FROM tbl_riwayat_masuk r
          JOIN tbl_produk p ON r.produk_id = p.id";

// Filter berdasarkan tanggal
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $conn->real_escape_string($_GET['start_date']);
    $end_date = $conn->real_escape_string($_GET['end_date']);
    $query .= " WHERE r.tanggal BETWEEN '$start_date' AND '$end_date'";
}

$query .= " ORDER BY r.tanggal DESC";
$result = $conn->query($query);

// Export ke CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="riwayat_masuk_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID', 'Produk', 'Jumlah', 'Tanggal'));
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Barang Masuk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .history-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .history-card .card-header {
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }
        .history-table th {
            background-color: #2c3e50;
            color: white;
        }
        .history-table tr:hover {
            background-color: #f8f9fa;
        }
        .filter-card {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .badge-date {
            font-size: 0.9em;
            padding: 5px 10px;
            background-color: #6c757d;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
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
                        <a class="nav-link active" href="riwayat_masuk.php">
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
            <h2><i class="bi bi-box-arrow-in-down"></i> Riwayat Barang Masuk</h2>
            <span class="badge bg-primary">
                Total Data: <?php echo $result->num_rows; ?>
            </span>
        </div>

        <!-- Form Filter Tanggal -->
        <div class="card filter-card">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="riwayat_masuk.php" class="btn btn-secondary w-100">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="riwayat_masuk.php?export=1<?php echo isset($_GET['start_date']) ? '&start_date='.$_GET['start_date'].'&end_date='.$_GET['end_date'] : ''; ?>" 
                           class="btn btn-success w-100">
                            <i class="bi bi-file-earmark-excel"></i> Export CSV
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Riwayat -->
        <div class="card history-card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-list-check"></i> Daftar Riwayat Barang Masuk
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover history-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Produk</th>
                                <th>Jumlah</th>
                                <th>Tanggal Masuk</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo $row['produk']; ?></td>
                                    <td>
                                        <span class="badge bg-success">
                                            <?php echo $row['jumlah']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark">
                                            <?php echo date('d M Y H:i', strtotime($row['tanggal'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
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
        
        // Set default dates for filter
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('start_date') && !urlParams.has('end_date')) {
                const today = new Date().toISOString().split('T')[0];
                document.querySelector('input[name="start_date"]').value = today;
                document.querySelector('input[name="end_date"]').value = today;
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>