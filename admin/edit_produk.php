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

// **Ambil ID produk dari URL**
if (!isset($_GET['id'])) {
    header("Location: manage_produk.php");
    exit();
}

$id = $_GET['id'];

// **Ambil data produk berdasarkan ID**
$query = "SELECT * FROM tbl_produk WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$produk = $result->fetch_assoc();

// Jika produk tidak ditemukan, kembalikan ke halaman utama
if (!$produk) {
    header("Location: manage_produk.php");
    exit();
}

// **Ambil daftar kategori**
$kategori_query = "SELECT * FROM tbl_kategori";
$kategori_result = $conn->query($kategori_query);

// **Proses Update Produk**
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_product'])) {
    $nama = $_POST['nama'];
    $merek = $_POST['merek'];
    $kategori_id = $_POST['kategori_id'];
    $harga = $_POST['harga'];
    $stok = $_POST['stok'];

    $update_query = "UPDATE tbl_produk SET nama=?, merek=?, kategori_id=?, harga=?, stok=? WHERE id=?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssidii", $nama, $merek, $kategori_id, $harga, $stok, $id);

    if ($stmt->execute()) {
        header("Location: manage_produk.php?success=update");
        exit();
    } else {
        echo "Gagal mengupdate produk";
    }
}
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
        <h2 class="bi bi-box-seam"> Edit Produk</h2>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nama Produk</label>
                <input type="text" name="nama" class="form-control" value="<?php echo $produk['nama']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Merek</label>
                <input type="text" name="merek" class="form-control" value="<?php echo $produk['merek']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Kategori</label>
                <select name="kategori_id" class="form-control" required>
                    <?php while ($kat = $kategori_result->fetch_assoc()): ?>
                        <option value="<?php echo $kat['id']; ?>" <?php if ($kat['id'] == $produk['kategori_id']) echo "selected"; ?>>
                            <?php echo $kat['nama']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Harga</label>
                <input type="number" name="harga" class="form-control" value="<?php echo $produk['harga']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Stok</label>
                <input type="number" name="stok" class="form-control" value="<?php echo $produk['stok']; ?>" required>
            </div>
            <button type="submit" name="update_product" class="btn btn-primary">Simpan Perubahan</button>
            <a href="manage_produk.php" class="btn btn-secondary">Kembali</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
