<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Koneksi ke database
$host = "localhost";
$user = "root";
$password = "";
$dbname = "tigajayamotor_inventory";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "message" => "Koneksi database gagal",
        "error" => $conn->connect_error
    ]));
}

// Ambil data dari request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode([
        "success" => false,
        "message" => "Data JSON tidak valid"
    ]));
}

$email = $data["email"] ?? '';
$password = $data["password"] ?? '';

// Validasi input
if (empty($email) || empty($password)) {
    http_response_code(400);
    die(json_encode([
        "success" => false,
        "message" => "Email dan password harus diisi"
    ]));
}

// Cek user di database
$query = "SELECT id, email, password, role FROM tbl_user WHERE email = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "message" => "Persiapan query gagal",
        "error" => $conn->error
    ]));
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    // Verifikasi password (tanpa hashing)
    if ($password === $user["password"]) {
        $_SESSION["isAuthenticated"] = true;
        $_SESSION["role"] = $user["role"];
        $_SESSION["email"] = $user["email"];
        $_SESSION["user_id"] = $user["id"];

        echo json_encode([
            "success" => true,
            "role" => $user["role"],
            "user" => [
                "email" => $user["email"],
                "id" => $user["id"]
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Password salah"
        ]);
    }
} else {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "User tidak ditemukan"
    ]);
}

$stmt->close();
$conn->close();
?>