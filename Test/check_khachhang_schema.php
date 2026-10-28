<?php
require_once __DIR__ . '/../Backend/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend')->load();
require_once __DIR__ . '/../Backend/Database/Database.php';

$db = \Database\Database::getConnection();

echo "=== TABLE KhachHang ===\n";
foreach ($db->query("DESCRIBE KhachHang")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo "{$c['Field']} ({$c['Type']}) Null:{$c['Null']} Key:{$c['Key']}\n";
}

echo "\n=== TABLE TaiKhoan ===\n";
foreach ($db->query("DESCRIBE TaiKhoan")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo "{$c['Field']} ({$c['Type']}) Null:{$c['Null']} Key:{$c['Key']}\n";
}

echo "\n=== SAMPLE KHACHHANG ROWS ===\n";
$rows = $db->query("SELECT kh.*, tk.TenDangNhap, tk.Email, tk.VaiTro FROM KhachHang kh LEFT JOIN TaiKhoan tk ON kh.MaTK = tk.MaTK LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
