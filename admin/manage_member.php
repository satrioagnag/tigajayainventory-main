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

// **Tambah Member**
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_member'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $total_spent = $_POST['total_spent'] ?? 0; // Default total_spent
    $membership_level = $_POST['membership_level'] ?? 'Regular'; // Default level

    $query = "INSERT INTO tbl_member (name, phone, total_spent, membership_level) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssds", $name, $phone, $total_spent, $membership_level);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Member berhasil ditambahkan";
        header("Location: manage_member.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Gagal menambah member: " . $conn->error;
    }
}

// **Hapus Member**
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_member'])) {
    $id = $_POST['phone'];
    $query = "DELETE FROM tbl_member WHERE phone = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Produk berhasil dihapus";
        header("Location: manage_member.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Gagal menghapus member: " . $conn->error;
    }
}

// **Ambil semua member**
$query = "SELECT customer_id, name, phone, total_spent, membership_level FROM tbl_member
          ORDER BY name ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Membership</title>
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
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link text-white">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['nama'] ?? 'Admin'); ?>
                        </span>
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
            <h2><i class="bi bi-people"></i> Manajemen Membership</h2>
        </div>

        <!-- Form Tambah Produk -->
        <div class="card mb-4 card-product">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-plus-circle"></i> Tambah Membership Baru
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Nama Member</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">No HP</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Level Member</label>
                            <select name="membership_level" class="form-select">
                                <option value="Regular">Regular</option>
                                <option value="Silver">Silver</option>
                                <option value="Gold">Gold</option>
                                <option value="Platinum">Platinum</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Total Spent</label>
                            <input type="number" name="total_spent" class="form-control" value="0">
                        </div>
                        <div class="col-12 mt-3">
                            <button type="submit" name="add_member" class="btn btn-primary w-100">
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
                                <th width="25%">Nama</th>
                                <th width="15%">No HP</th>
                                <th width="18%">Total Spent</th>
                                <th width="12%">Membership Level</th>
                                <th width="10%">Aksi</th>

                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="<?php echo $stock_class; ?>">
                                    <td><?php echo $row['customer_id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($row['total_spent']); ?></td>
                                    <td><?php echo htmlspecialchars($row['membership_level']); ?></td>
                                    <td>
                                        <div class="d-flex">
                                            <a href="edit_member.php?customer_id=<?php echo $row['customer_id']; ?>" class="btn btn-sm btn-warning me-1">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">
                                                <input type="hidden" name="phone" value="<?php echo $row['phone']; ?>">
                                                <button type="submit" name="delete_member" class="btn btn-sm btn-danger">
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>

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