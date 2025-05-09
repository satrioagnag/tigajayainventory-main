<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Kasir') {
    http_response_code(403); // Send proper code
    echo json_encode(["status" => "unauthorized"]);
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$dbname = "tigajayamotor_inventory";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Koneksi database gagal."]);
    exit();
}

if (isset($_GET['phone'])) {
    $phone = $_GET['phone'];
    $stmt = $conn->prepare("SELECT name, total_spent, membership_level FROM tbl_member WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $member = $result->fetch_assoc();
        $discount = 0;
        if ($member['membership_level'] === 'Silver')
            $discount = 5;
        if ($member['membership_level'] === 'Gold')
            $discount = 10;

        echo json_encode([
            "status" => "found",
            "name" => $member['name'],
            "total_spent" => $member['total_spent'],
            "level" => $member['membership_level'],
            "discount" => $discount
        ]);
    } else {
        echo json_encode(["status" => "not_found"]);
    }
}