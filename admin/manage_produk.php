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

// **Tambahkan kategori jika belum ada**
$kategori_default = ["Aki", "Busi", "Kampas Rem", "Lampu"];
foreach ($kategori_default as $kategori) {
    $query = "INSERT IGNORE INTO tbl_kategori (nama) VALUES (?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $kategori);
    $stmt->execute();
}

// **Ambil semua kategori**
$kategori_query = "SELECT * FROM tbl_kategori";
$kategori_result = $conn->query($kategori_query);

// **Tambah Produk**
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $nama = $_POST['nama'];
    $merek = $_POST['merek'];
    $kategori_id = $_POST['kategori_id'];
    $harga = $_POST['harga'];
    $stok = $_POST['stok'];
    $min_stok = $_POST['min_stok'] ?? 5; // Default minimum stock

    $query = "INSERT INTO tbl_produk (nama, merek, kategori_id, harga, stok, min_stok) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssidii", $nama, $merek, $kategori_id, $harga, $stok, $min_stok);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Produk berhasil ditambahkan";
        header("Location: manage_produk.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Gagal menambah produk: " . $conn->error;
    }
}

// **Hapus Produk**
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_product'])) {
    $id = $_POST['id'];
    $query = "DELETE FROM tbl_produk WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Produk berhasil dihapus";
        header("Location: manage_produk.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Gagal menghapus produk: " . $conn->error;
    }
}

// **Ambil semua produk**
$query = "SELECT p.id, p.nama, p.merek, k.nama AS kategori, p.harga, p.stok, p.min_stok 
          FROM tbl_produk p 
          LEFT JOIN tbl_kategori k ON p.kategori_id = k.id
          ORDER BY p.nama ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Produk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card-product {
            transition: all 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-product:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .stock-low {
            background-color: #fff8f8;
        }
        .stock-very-low {
            background-color: #ffebee;
        }
        .nav-pills .nav-link.active {
            background-color: #2c3e50;
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #2c3e50, #34495e);">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-box-seam"></i> Inventory System
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
        <!-- Notifikasi -->
        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-box-seam"></i> Manajemen Produk</h2>
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" id="searchInput" placeholder="Cari produk...">
            </div>
        </div>

        <!-- Tab Navigasi -->
        <ul class="nav nav-pills mb-4" id="productTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-tab" data-bs-toggle="pill" data-bs-target="#all" type="button">Semua Produk</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="low-tab" data-bs-toggle="pill" data-bs-target="#low" type="button">Stok Rendah</button>
            </li>
        </ul>

        <!-- Form Tambah Produk -->
        <div class="card mb-4 card-product">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-plus-circle"></i> Tambah Produk Baru
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Nama Produk</label>
                            <input type="text" name="nama" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Merek</label>
                            <input type="text" name="merek" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Kategori</label>
                            <select name="kategori_id" class="form-select" required>
                                <option value="">Pilih Kategori</option>
                                <?php 
                                $kategori_result->data_seek(0); // Reset pointer hasil query
                                while ($kat = $kategori_result->fetch_assoc()): ?>
                                    <option value="<?php echo $kat['id']; ?>"><?php echo htmlspecialchars($kat['nama']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Harga (Rp)</label>
                            <input type="number" name="harga" class="form-control" min="0" required>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Stok</label>
                            <input type="number" name="stok" class="form-control" min="0" required>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Min. Stok</label>
                            <input type="number" name="min_stok" class="form-control" min="1" value="5">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" name="add_product" class="btn btn-primary w-100">
                                <i class="bi bi-save"></i> Simpan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Daftar Produk -->
        <div class="tab-content" id="productTabsContent">
            <div class="tab-pane fade show active" id="all" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover" id="productsTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="5%">ID</th>
                                <th width="20%">Nama Produk</th>
                                <th width="15%">Merek</th>
                                <th width="15%">Kategori</th>
                                <th width="15%">Harga</th>
                                <th width="10%">Stok</th>
                                <th width="10%">Status</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): 
                                $stock_class = '';
                                $stock_status = 'Aman';
                                if ($row['stok'] <= 0) {
                                    $stock_class = 'stock-very-low';
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
                                    <td>
                                        <?php if($stock_status == 'Aman'): ?>
                                            <span class="badge bg-success"><?php echo $stock_status; ?></span>
                                        <?php elseif($stock_status == 'Rendah'): ?>
                                            <span class="badge bg-warning"><?php echo $stock_status; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><?php echo $stock_status; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            <a href="edit_produk.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning me-1">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="delete_product" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="tab-pane fade" id="low" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nama Produk</th>
                                <th>Merek</th>
                                <th>Kategori</th>
                                <th>Stok</th>
                                <th>Min. Stok</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $result->data_seek(0); // Reset pointer hasil query
                            while ($row = $result->fetch_assoc()): 
                                if ($row['stok'] > $row['min_stok']) continue;
                            ?>
                                <tr class="<?php echo ($row['stok'] <= 0) ? 'stock-very-low' : 'stock-low'; ?>">
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                    <td><?php echo htmlspecialchars($row['merek']); ?></td>
                                    <td><?php echo htmlspecialchars($row['kategori']); ?></td>
                                    <td><?php echo $row['stok']; ?></td>
                                    <td><?php echo $row['min_stok']; ?></td>
                                    <td>
                                        <a href="edit_produk.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i> Restock
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi pencarian
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const input = this.value.toLowerCase();
            const rows = document.querySelectorAll('#productsTable tbody tr');
            
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
    </script>
</body>
</html>