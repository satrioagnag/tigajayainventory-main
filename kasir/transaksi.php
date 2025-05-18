<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("POST data: " . print_r($_POST, true));
}
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

// Ambil data kategori
$kategori_query = "SELECT id, nama FROM tbl_kategori";
$kategori_result = $conn->query($kategori_query);

// Ambil data produk berdasarkan kategori atau pencarian
$produk_query = "SELECT p.id, p.nama, p.harga, p.stok, k.nama AS kategori 
                 FROM tbl_produk p
                 LEFT JOIN tbl_kategori k ON p.kategori_id = k.id
                 WHERE p.stok > 0";
if (isset($_GET['kategori_id']) && !empty($_GET['kategori_id'])) {
    $kategori_id = intval($_GET['kategori_id']);
    $produk_query .= " AND p.kategori_id = $kategori_id";
}
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $produk_query .= " AND p.nama LIKE '%$search%'";
}
$produk_result = $conn->query($produk_query);

// Proses transaksi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['checkout'])) {
        error_log("ISI POST: " . print_r($_POST, true));
    // Validasi input
    $produk_id = intval($_POST['produk_id']);
    $jumlah = intval($_POST['jumlah']);
    $diskon = floatval($_POST['diskon']);
    $pembayaran = floatval($_POST['pembayaran']);
    $metode_pembayaran = $conn->real_escape_string($_POST['metode_pembayaran']);
    $phone = isset($_POST['phone']) ? $_POST['phone'] : "";

    // Ambil data produk
    $query = "SELECT nama, harga, stok FROM tbl_produk WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $produk_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $produk = $result->fetch_assoc();

    if ($produk && $jumlah <= $produk['stok']) {
        $harga_total = ($produk['harga'] * $jumlah) - $diskon;
        $kembalian = $pembayaran - $harga_total;

        if ($kembalian >= 0) {
            // Kurangi stok
            $update_stok = "UPDATE tbl_produk SET stok = stok - ? WHERE id = ?";
            $stmt = $conn->prepare($update_stok);
            $stmt->bind_param("ii", $jumlah, $produk_id);
            $stmt->execute();

            // Simpan transaksi - DIUBAH TANPA kasir_id
            $insert_transaksi = "INSERT INTO tbl_transaksi (produk_id, jumlah, harga_total, diskon, pembayaran, kembalian, metode_pembayaran) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_transaksi);
            $stmt->bind_param("iidddds", $produk_id, $jumlah, $harga_total, $diskon, $pembayaran, $kembalian, $metode_pembayaran);
            $stmt->execute();

            // Simpan data untuk e-receipt
            $_SESSION['receipt'] = [
                'produk' => $produk['nama'],
                'jumlah' => $jumlah,
                'harga_satuan' => $produk['harga'],
                'harga_total' => $harga_total,
                'diskon' => $diskon,
                'pembayaran' => $pembayaran,
                'kembalian' => $kembalian,
                'metode_pembayaran' => $metode_pembayaran,
                'kasir' => $_SESSION['nama'],
                'tanggal' => date('d/m/Y H:i:s')
            ];
            // Setelah insert transaksi berhasil
            if (!empty($phone)) {
                updateCustomerMembership($phone, $harga_total, $conn);
            }

            $_SESSION['success'] = "Transaksi berhasil! Kembalian: Rp" . number_format($kembalian, 0, ',', '.');
            echo '<pre>';
print_r($_POST);
echo '</pre>';

            header("Location: transaksi.php");
            exit();
        } else {
            $_SESSION['error'] = "Pembayaran tidak mencukupi. Kurang: Rp" . number_format(abs($kembalian), 0, ',', '.');
        }
    } else {
        $_SESSION['error'] = "Stok tidak mencukupi atau produk tidak ditemukan.";
    }

    header("Location: transaksi.php");
    exit();
}

function updateCustomerMembership($phone, $harga_total, $conn)
{
        error_log("MASUK fungsi updateCustomerMembership, phone: $phone, total: $harga_total");

    if (empty($phone)) {
                error_log("Phone kosong");
        return;
    }
    
    // Prepare phone as string, not integer
    $phone = (string)$phone;

    // 1. Update total_spent
    $sql = "UPDATE tbl_member SET total_spent = total_spent + ? WHERE phone = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ds", $harga_total, $phone);
    $stmt->execute();

        error_log("Baris yang diupdate: " . $stmt->affected_rows);


    // Check if the update affected any rows
    if ($stmt->affected_rows == 0) {
        error_log("Tidak ada member dengan phone: $phone");
        return;
    }

     // 2. Fetch new total to check membership level
    $sql = "SELECT total_spent FROM tbl_member WHERE phone = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $phone); // Changed to string parameter
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        return; // No member found
    }
    
    $row = $result->fetch_assoc();
    $total = $row['total_spent'];

    // 3. Determine new level
    if ($total >= 5000000) {
        $level = 'Gold';
    } elseif ($total >= 1000000) {
        $level = 'Silver';
    } else {
        $level = 'Regular';
    }

    // 4. Update level
    $sql = "UPDATE tbl_member SET membership_level = ? WHERE phone = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $level, $phone);
    $stmt->execute();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir - Transaksi</title>
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

        .product-card {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .product-card:hover {
            background-color: #f0f8ff;
            transform: scale(1.02);
        }

        .product-card.selected {
            background-color: #e3f2fd;
            border-left: 4px solid var(--accent-color);
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

        .receipt-container {
            display: none;
            max-width: 400px;
            margin: 20px auto;
            font-family: 'Courier New', monospace;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 15px;
        }

        .receipt-header h3 {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .receipt-header p {
            font-size: 0.8rem;
            margin-bottom: 0;
        }

        .receipt-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .receipt-divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }

        .btn-print {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-print:hover {
            background-color: var(--secondary-color);
            color: white;
        }

        .badge-stock {
            font-size: 0.7rem;
            padding: 3px 6px;
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
                            <i class="bi bi-person-circle"></i>
                            <?php echo htmlspecialchars($_SESSION['nama'] ?? 'Kasir'); ?>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-cart-plus"></i> Transaksi Penjualan</h2>
            <div class="text-muted" id="realtime-clock">
            </div>
        </div>

        <!-- Notifikasi -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row">
            <!-- Kolom Produk -->
            <div class="col-md-6 mb-4">
                <div class="card card-kasir">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-box-seam"></i> Daftar Produk
                    </div>
                    <div class="card-body">
                        <!-- Form Filter Kategori dan Search Bar -->
                        <form method="GET" class="mb-3">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <select class="form-select" name="kategori_id" onchange="this.form.submit()">
                                        <option value="">Semua Kategori</option>
                                        <?php
                                        $kategori_result->data_seek(0);
                                        while ($kat = $kategori_result->fetch_assoc()): ?>
                                            <option value="<?php echo $kat['id']; ?>" <?php if (isset($_GET['kategori_id']) && $_GET['kategori_id'] == $kat['id'])
                                                   echo "selected"; ?>>
                                                <?php echo htmlspecialchars($kat['nama']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <div class="search-box">
                                        <i class="bi bi-search"></i>
                                        <input type="text" name="search" class="form-control"
                                            placeholder="Cari produk..."
                                            value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Daftar Produk -->
                        <div class="list-group" style="max-height: 400px; overflow-y: auto;">
                            <?php
                            $produk_result->data_seek(0);
                            while ($row = $produk_result->fetch_assoc()):
                                $harga_formatted = number_format($row['harga'], 0, ',', '.');
                                ?>
                                <a href="#" class="list-group-item list-group-item-action product-card"
                                    data-id="<?php echo $row['id']; ?>"
                                    onclick="selectProduct(<?php echo $row['id']; ?>, <?php echo $row['harga']; ?>, '<?php echo htmlspecialchars($row['nama']); ?>')">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($row['nama']); ?></h6>
                                            <small
                                                class="text-muted"><?php echo htmlspecialchars($row['kategori']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="fw-bold">Rp<?php echo $harga_formatted; ?></span>
                                            <span class="badge bg-success badge-stock">Stok:
                                                <?php echo $row['stok']; ?></span>
                                        </div>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom Transaksi -->
            <div class="col-md-6 mb-4">
                <div class="card card-kasir">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-cash-stack"></i> Form Transaksi
                    </div>
                    <div class="card-body">
                        <form method="POST" id="transaksiForm">
                            <div class="mb-3">
                                <label class="form-label">Produk Terpilih</label>
                                <input type="text" id="selected_product" class="form-control" readonly>
                                <input type="hidden" name="produk_id" id="produk_id">
                            </div>

                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Harga Satuan</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" id="harga_satuan" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Jumlah</label>
                                    <input type="number" name="jumlah" id="jumlah" class="form-control" required min="1"
                                        value="1" oninput="hitungTotal()">
                                </div>
                            </div>

                            <div class="row g-2 mt-2">
                                <div class="col-md-6">
                                    <label class="form-label">Diskon</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" name="diskon" id="diskon" class="form-control" value="0"
                                            min="0" oninput="hitungTotal()">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Persentase Diskon</label>
                                    <div class="input-group">
                                        <input type="number" name="diskon_percent" id="diskon_percent"
                                            class="form-control" value="0" min="0" , oninput="">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                <label class="form-label">Nomor HP</label>
                                <input type="text" name="phone" id="phone" class="form-control"
                                    placeholder="Masukkan nomor HP" onblur="checkMember()">
                            </div>
                                <div>
                                    <label class="form-label">Metode Pembayaran</label>
                                    <select class="form-select" name="metode_pembayaran" required>
                                        <option value="Cash">Cash</option>
                                        <option value="QRIS">QRIS</option>
                                        <option value="Transfer Bank">Transfer Bank</option>
                                        <option value="Debit/Credit Card">Debit/Credit Card</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-2 mt-2">
                                <div class="col-md-6">
                                    <label class="form-label">Total Harga</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" id="total_harga" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Pembayaran</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" name="pembayaran" id="pembayaran" class="form-control"
                                            required min="0" oninput="hitungKembalian()">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3 mt-2">
                                <label class="form-label">Kembalian</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" id="kembalian" class="form-control" readonly>
                                </div>
                            </div>

                            <button type="submit" name="checkout" class="btn btn-primary w-100 mt-3">
                                <i class="bi bi-check-circle"></i> Proses Transaksi
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Kolom Membership -->
            <div class="col-md-6 mb-4">
                <div class="card card-kasir">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-person-vcard"></i> Member
                    </div>
                    <div class="card-body">
                        <form method="POST" id="transaksiForm">
                            <div class="mb-3">
                                <label class="form-label">Nama Member</label>
                                <input type="text" id="member_name" class="form-control" readonly>
                            </div>

                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Total Spend</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" id="total_spent" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Membership Level</label>
                                    <input type="text" id="membership_level" class="form-control" readonly>
                                </div>
                            </div>

                            <input type="hidden" id="discount_value" name="discount_value">
                        </form>
                    </div>
                </div>
            </div>
        </div>


        <!-- E-Receipt -->
        <?php if (isset($_SESSION['receipt'])): ?>
            <div class="row">
                <div class="col-md-12 text-center">
                    <button class="btn btn-print mb-3" onclick="showReceipt()">
                        <i class="bi bi-receipt"></i> Tampilkan Struk
                    </button>

                    <div id="receiptContainer" class="receipt-container">
                        <div class="receipt-header">
                            <h3>TIGA JAYA MOTOR</h3>
                            <p>Jl. Erlangga No.6, Katang, Sukorejo, Kec. Ngasem</p>
                            <p>Kabupaten Kediri, Jawa Timur</p>
                            <p>Telp: (0354) 123456</p>
                        </div>

                        <div class="receipt-divider"></div>

                        <div class="receipt-line">
                            <span>Kasir:</span>
                            <span><?php echo htmlspecialchars($_SESSION['receipt']['kasir'] ?? ''); ?></span>
                        </div>
                        <div class="receipt-line">
                            <span>Tanggal:</span>
                            <span><?php echo htmlspecialchars($_SESSION['receipt']['tanggal'] ?? ''); ?></span>
                        </div>

                        <div class="receipt-divider"></div>

                        <div class="receipt-line">
                            <span><?php echo htmlspecialchars($_SESSION['receipt']['produk'] ?? ''); ?></span>
                            <span>Rp<?php echo number_format($_SESSION['receipt']['harga_satuan'] ?? 0, 0, ',', '.'); ?></span>
                        </div>
                        <div class="receipt-line">
                            <span><?php echo $_SESSION['receipt']['jumlah'] ?? 0; ?> x</span>
                            <span>Rp<?php echo number_format(($_SESSION['receipt']['harga_satuan'] ?? 0) * ($_SESSION['receipt']['jumlah'] ?? 0), 0, ',', '.'); ?></span>
                        </div>

                        <div class="receipt-divider"></div>

                        <div class="receipt-line">
                            <span>Subtotal:</span>
                            <span>Rp<?php echo number_format(($_SESSION['receipt']['harga_satuan'] ?? 0) * ($_SESSION['receipt']['jumlah'] ?? 0), 0, ',', '.'); ?></span>
                        </div>
                        <div class="receipt-line">
                            <span>Diskon:</span>
                            <span>Rp<?php echo number_format($_SESSION['receipt']['diskon'] ?? 0, 0, ',', '.'); ?></span>
                        </div>
                        <div class="receipt-line">
                            <span><strong>Total:</strong></span>
                            <span><strong>Rp<?php echo number_format($_SESSION['receipt']['harga_total'] ?? 0, 0, ',', '.'); ?></strong></span>
                        </div>
                        <div class="receipt-divider"></div>
                        <div class="receipt-line">
                            <span>Tunai:</span>
                            <span>Rp<?php echo number_format($_SESSION['receipt']['pembayaran'] ?? 0, 0, ',', '.'); ?></span>
                        </div>
                        <div class="receipt-line">
                            <span>Kembali:</span>
                            <span>Rp<?php echo number_format($_SESSION['receipt']['kembalian'] ?? 0, 0, ',', '.'); ?></span>
                        </div>
                        <div class="receipt-line">
                            <span>Metode:</span>
                            <span><?php echo htmlspecialchars($_SESSION['receipt']['metode_pembayaran'] ?? ''); ?></span>
                        </div>
                        <div class="receipt-divider"></div>
                        <div class="text-center mt-3">
                            <p>Terima kasih telah berbelanja</p>
                            <p>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan</p>
                        </div>
                    </div>

                    <button id="printBtn" class="btn btn-print" onclick="printReceipt()" style="display: none;">
                        <i class="bi bi-printer"></i> Cetak Struk
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['receipt']); ?>
        <?php endif; ?>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Fungsi untuk memilih produk
            function selectProduct(id, harga, nama) {
                document.getElementById('produk_id').value = id;
                document.getElementById('selected_product').value = nama;
                document.getElementById('harga_satuan').value = harga.toLocaleString('id-ID');

                // Update tampilan produk terpilih
                const productCards = document.querySelectorAll('.product-card');
                productCards.forEach(card => {
                    card.classList.remove('selected');
                    if (parseInt(card.getAttribute('data-id')) === id) {
                        card.classList.add('selected');
                    }
                });


                hitungTotal();
            }

            //eventListner untuk sync diskon
            document.getElementById('diskon').addEventListener('input', function () {
                syncDiskonToPercent();
                hitungTotal();
            });
            document.getElementById('diskon_percent').addEventListener('input', function () {
                syncPercentToDiskon();
                hitungTotal();
            });
            document.getElementById('harga_satuan').addEventListener('input', hitungTotal);
            document.getElementById('jumlah').addEventListener('input', hitungTotal);

            function syncDiskonToPercent() {
                const harga = parseFloat(document.getElementById('harga_satuan').value.replace(/\./g, '')) || 0;
                const jumlah = parseInt(document.getElementById('jumlah').value) || 0;
                const diskon = parseFloat(document.getElementById('diskon').value) || 0;
                const subtotal = harga * jumlah;

                const diskon_percent = subtotal > 0 ? (diskon / subtotal) * 100 : 0;
                document.getElementById('diskon_percent').value = Math.round(diskon_percent);
            }

            function syncPercentToDiskon() {
                const harga = parseFloat(document.getElementById('harga_satuan').value.replace(/\./g, '')) || 0;
                const jumlah = parseInt(document.getElementById('jumlah').value) || 0;
                const diskon_percent = parseFloat(document.getElementById('diskon_percent').value) || 0;
                const subtotal = harga * jumlah;

                const diskon = (diskon_percent / 100) * subtotal;
                document.getElementById('diskon').value = Math.round(diskon);
            }

            // Fungsi untuk menghitung total harga
            function hitungTotal() {
                const harga = parseFloat(document.getElementById('harga_satuan').value.replace(/\./g, '')) || 0;
                const jumlah = parseInt(document.getElementById('jumlah').value) || 0;
                const subtotal = harga * jumlah;
                const diskon = parseFloat(document.getElementById('diskon').value) || 0;

                const total = subtotal - diskon;
                document.getElementById('total_harga').value = total.toLocaleString('id-ID');

                hitungKembalian();
            }

            // Fungsi untuk menghitung kembalian
            function hitungKembalian() {
                const total = parseFloat(document.getElementById('total_harga').value.replace(/\./g, '')) || 0;
                const pembayaran = parseFloat(document.getElementById('pembayaran').value) || 0;

                const kembalian = pembayaran - total;
                document.getElementById('kembalian').value = kembalian >= 0 ? kembalian.toLocaleString('id-ID') : '0';
            }

            // Fungsi untuk menampilkan struk
            function showReceipt() {
                const receipt = document.getElementById('receiptContainer');
                const printBtn = document.getElementById('printBtn');

                if (receipt.style.display === 'block') {
                    receipt.style.display = 'none';
                    printBtn.style.display = 'none';
                } else {
                    receipt.style.display = 'block';
                    printBtn.style.display = 'inline-block';
                }
            }

            // Fungsi untuk mencetak struk
            function printReceipt() {
                const receiptContent = document.getElementById('receiptContainer').innerHTML;
                const printWindow = window.open('', '', 'width=400,height=600');

                printWindow.document.open();
                printWindow.document.write(`
                <html>
                    <head>
                        <title>Struk Pembelian</title>
                        <style>
                            body { font-family: 'Courier New', monospace; font-size: 12px; padding: 10px; }
                            .receipt-header { text-align: center; margin-bottom: 10px; }
                            .receipt-header h3 { font-weight: bold; margin: 5px 0; font-size: 14px; }
                            .receipt-line { display: flex; justify-content: space-between; margin-bottom: 3px; }
                            .receipt-divider { border-top: 1px dashed #000; margin: 5px 0; }
                            @media print { 
                                body { padding: 0; margin: 0; }
                                button { display: none !important; }
                            }
                        </style>
                    </head>
                    <body>
                        ${receiptContent}
                        <script>
                            window.onload = function() {
                                setTimeout(function() {
                                    window.print();
                                    window.close();
                                }, 200);
                            };
                        <\/script>
                    </body>
                </html>
            `);
                printWindow.document.close();
            }

            // Fungsi logout
            function logout() {
                if (confirm('Apakah Anda yakin ingin logout?')) {
                    sessionStorage.clear();
                    localStorage.clear();
                    window.location.href = "../index.html";
                }
            }

            // Inisialisasi saat halaman dimuat
            document.addEventListener('DOMContentLoaded', function () {
                // Pilih produk pertama secara default jika ada
                const firstProduct = document.querySelector('.product-card');
                if (firstProduct) {
                    const onclickAttr = firstProduct.getAttribute('onclick');
                    const matches = onclickAttr.match(/selectProduct\((\d+),\s*(\d+),\s*'([^']+)'/);
                    if (matches) {
                        selectProduct(matches[1], matches[2], matches[3]);
                    }
                }
            });

            //clock real time
            function updateClock() {
                const now = new Date();
                const options = {
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit', second: '2-digit',
                    hour12: false
                };
                const formatted = now.toLocaleString('id-ID', options).replace(',', '');
                document.getElementById('realtime-clock').textContent = formatted;
            }

            setInterval(updateClock, 1000); // Update every second
            updateClock(); // Run once immediately


            // Fungsi untuk memeriksa member

            function checkMember() {
                const phone = document.getElementById('phone').value;
                if (!phone) return;

                fetch(`../assets/check_member.php?phone=${phone}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === "found") {
                            document.getElementById('member_name').value = data.name;
                            document.getElementById('total_spent').value = data.total_spent;
                            document.getElementById('membership_level').value = data.level;
                            document.getElementById('discount_value').value = data.discount;

                            // Auto-apply discount percent
                            document.getElementById('diskon_percent').value = data.discount;
                            syncPercentToDiskon();  // Convert to absolute discount
                            hitungTotal(); // Recalculate total

                            alert(`Diskon ${data.discount}% diterapkan untuk ${data.level} member`);
                        } else {
                            document.getElementById('member_name').value = "Tidak ditemukan";
                            document.getElementById('total_spent').value = "-";
                            document.getElementById('membership_level').value = "-";
                            document.getElementById('discount_value').value = 0;

                            // Reset discount fields
                            document.getElementById('diskon').value = 0;
                            document.getElementById('diskon_percent').value = 0;
                            hitungTotal();

                            alert("Nomor tidak terdaftar sebagai member.");
                        }
                    })
                    .catch(error => {
                        console.error("Fetch error:", error);
                        alert("Terjadi kesalahan saat mengecek data member.");
                    });
            }




        </script>
</body>

</html>