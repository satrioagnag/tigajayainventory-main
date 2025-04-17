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

// Ambil data stok produk
$id = $_GET['id'];
$query = "SELECT * FROM tbl_produk WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

// Proses update stok
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stok = $_POST['stok'];

    $update_query = "UPDATE tbl_produk SET stok = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $stok, $id);

    if ($stmt->execute()) {
        header("Location: manage_stock.php");
        exit();
    } else {
        echo "Gagal mengupdate stok.";
    }
}
?>
