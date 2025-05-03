<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Gudang')) {
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

// Ambil semua stok produk
$query = "SELECT p.id, p.nama, p.merek, k.nama AS kategori, p.harga, p.stok 
          FROM tbl_produk p 
          LEFT JOIN tbl_kategori k ON p.kategori_id = k.id";
$result = $conn->query($query);

// Tambah Stok
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_stock'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);

    if ($quantity <= 0) {
        $_SESSION['error'] = "Jumlah stok harus lebih dari 0.";
        header("Location: kelola_stok.php");
        exit();
    }

    $update_query = "UPDATE tbl_produk SET stok = stok + ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $quantity, $product_id);

    if ($stmt->execute()) {
        $insert_riwayat = "INSERT INTO tbl_riwayat_masuk (produk_id, jumlah) VALUES (?, ?)";
        $stmt_riwayat = $conn->prepare($insert_riwayat);
        $stmt_riwayat->bind_param("ii", $product_id, $quantity);
        $stmt_riwayat->execute();

        $_SESSION['success'] = "Stok berhasil ditambahkan.";
    } else {
        $_SESSION['error'] = "Gagal menambah stok.";
    }
    header("Location: kelola_stok.php");
    exit();
}

// Kurangi Stok
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reduce_stock'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);

    if ($quantity <= 0) {
        $_SESSION['error'] = "Jumlah stok harus lebih dari 0.";
        header("Location: kelola_stok.php");
        exit();
    }

    $update_query = "UPDATE tbl_produk SET stok = stok - ? WHERE id = ? AND stok >= ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("iii", $quantity, $product_id, $quantity);

    if ($stmt->execute()) {
        $insert_riwayat_keluar = "INSERT INTO tbl_riwayat_keluar (produk_id, jumlah) VALUES (?, ?)";
        $stmt_riwayat_keluar = $conn->prepare($insert_riwayat_keluar);
        $stmt_riwayat_keluar->bind_param("ii", $product_id, $quantity);
        $stmt_riwayat_keluar->execute();

        $_SESSION['success'] = "Stok berhasil dikurangi.";
    } else {
        $_SESSION['error'] = "Gagal mengurangi stok. Pastikan stok cukup.";
    }
    header("Location: kelola_stok.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Stok Gudang</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css">
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
        .stok-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stok-card .card-header {
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }
        .stok-table th {
            background-color: #2c3e50;
            color: white;
        }
        .stok-low {
            background-color: #fff3cd !important;
        }
        .stok-critical {
            background-color: #f8d7da !important;
        }
        .badge-stok {
            font-size: 0.9em;
            padding: 5px 8px;
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
                        <a class="nav-link active" href="kelola_stok.php">
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
            <h2><i class="bi bi-boxes"></i> Kelola Stok Gudang</h2>
            <a href="manage_stock.php" class="btn btn-primary">
                <i class="bi bi-gear-fill"></i> Kelola Stok Lengkap
            </a>
        </div>

        <!-- Notifikasi -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Form Tambah Stok -->
            <div class="col-md-6">
                <div class="card stok-card">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-plus-circle"></i> Tambah Stok
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Produk</label>
                                <select class="form-select selectpicker" name="product_id" data-live-search="true" required>
                                    <?php
                                    $product_query = "SELECT id, nama, stok FROM tbl_produk";
                                    $product_result = $conn->query($product_query);
                                    while ($row = $product_result->fetch_assoc()): ?>
                                        <option value="<?php echo $row['id']; ?>">
                                            <?php echo $row['nama']; ?> (Stok: <?php echo $row['stok']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Jumlah</label>
                                <input type="number" class="form-control" name="quantity" required min="1">
                            </div>
                            <button type="submit" name="add_stock" class="btn btn-success w-100">
                                <i class="bi bi-plus-circle"></i> Tambah Stok
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Form Kurangi Stok -->
            <div class="col-md-6">
                <div class="card stok-card">
                    <div class="card-header bg-warning text-dark">
                        <i class="bi bi-dash-circle"></i> Kurangi Stok
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Produk</label>
                                <select class="form-select selectpicker" name="product_id" data-live-search="true" required>
                                    <?php
                                    $product_query = "SELECT id, nama, stok FROM tbl_produk";
                                    $product_result = $conn->query($product_query);
                                    while ($row = $product_result->fetch_assoc()): ?>
                                        <option value="<?php echo $row['id']; ?>">
                                            <?php echo $row['nama']; ?> (Stok: <?php echo $row['stok']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Jumlah</label>
                                <input type="number" class="form-control" name="quantity" required min="1">
                            </div>
                            <button type="submit" name="reduce_stock" class="btn btn-warning w-100 text-white">
                                <i class="bi bi-dash-circle"></i> Kurangi Stok
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Stok -->
        <div class="card stok-card mt-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-list-check"></i> Daftar Stok Produk
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover stok-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Produk</th>
                                <th>Merek</th>
                                <th>Kategori</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): 
                                $stock_class = '';
                                if ($row['stok'] < 5) {
                                    $stock_class = 'stok-critical';
                                } elseif ($row['stok'] < 10) {
                                    $stock_class = 'stok-low';
                                }
                            ?>
                                <tr class="<?php echo $stock_class; ?>">
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo $row['nama']; ?></td>
                                    <td><?php echo $row['merek']; ?></td>
                                    <td><?php echo $row['kategori']; ?></td>
                                    <td>Rp<?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="badge rounded-pill bg-<?php echo ($row['stok'] < 5) ? 'danger' : (($row['stok'] < 10) ? 'warning text-dark' : 'success'); ?> badge-stok">
                                            <?php echo $row['stok']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_stock.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil-fill"></i> Edit
                                        </a>
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
            sessionStorage.clear();
            localStorage.clear();
            window.location.href = "../index.html";
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>

</body>
</html>