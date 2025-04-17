<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Pastikan hanya Admin atau Gudang yang bisa mengakses halaman ini
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Gudang')) {
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

// Ambil semua stok produk
$query = "SELECT p.id, p.nama, p.merek, k.nama AS kategori, p.harga, p.stok 
          FROM tbl_produk p 
          LEFT JOIN tbl_kategori k ON p.kategori_id = k.id";
$result = $conn->query($query);

// Tambah Stok
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_stock'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];

    $update_query = "UPDATE tbl_produk SET stok = stok + ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $quantity, $product_id);

    if ($stmt->execute()) {
        $insert_riwayat = "INSERT INTO tbl_riwayat_masuk (produk_id, jumlah) VALUES (?, ?)";
        $stmt_riwayat = $conn->prepare($insert_riwayat);
        $stmt_riwayat->bind_param("ii", $product_id, $quantity);
        $stmt_riwayat->execute();

        header("Location: manage_stock.php");
        exit();
    } else {
        echo "Gagal menambah stok.";
    }
}

// Kurangi Stok
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reduce_stock'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];

    $update_query = "UPDATE tbl_produk SET stok = stok - ? WHERE id = ? AND stok >= ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("iii", $quantity, $product_id, $quantity);

    if ($stmt->execute()) {
        $insert_riwayat_keluar = "INSERT INTO tbl_riwayat_keluar (produk_id, jumlah) VALUES (?, ?)";
        $stmt_riwayat_keluar = $conn->prepare($insert_riwayat_keluar);
        $stmt_riwayat_keluar->bind_param("ii", $product_id, $quantity);
        $stmt_riwayat_keluar->execute();

        header("Location: manage_stock.php");
        exit();
    } else {
        echo "Gagal mengurangi stok. Pastikan stok cukup.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Stok</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Dashboard</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Manajemen Stok</h2>

        <h4>Tambah Stok</h4>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Produk</label>
                <select class="form-control" name="product_id" required>
                    <?php
                    $product_query = "SELECT id, nama FROM tbl_produk";
                    $product_result = $conn->query($product_query);
                    while ($row = $product_result->fetch_assoc()) {
                        echo "<option value='" . $row['id'] . "'>" . $row['nama'] . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Jumlah</label>
                <input type="number" class="form-control" name="quantity" required>
            </div>
            <button type="submit" name="add_stock" class="btn btn-success">Tambah Stok</button>
        </form>

        <h4 class="mt-4">Kurangi Stok</h4>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Produk</label>
                <select class="form-control" name="product_id" required>
                    <?php
                    $product_result = $conn->query($product_query);
                    while ($row = $product_result->fetch_assoc()) {
                        echo "<option value='" . $row['id'] . "'>" . $row['nama'] . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Jumlah</label>
                <input type="number" class="form-control" name="quantity" required>
            </div>
            <button type="submit" name="reduce_stock" class="btn btn-warning">Kurangi Stok</button>
        </form>

        <h4 class="mt-4">Daftar Stok</h4>
        <table class="table table-bordered">
            <thead class="table-dark">
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
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo $row['nama']; ?></td>
                        <td><?php echo $row['merek']; ?></td>
                        <td><?php echo $row['kategori']; ?></td>
                        <td>Rp<?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
                        <td><?php echo $row['stok']; ?></td>
                        <td>
                            <a href="edit_stock.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Edit Stok</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
