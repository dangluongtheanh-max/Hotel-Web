<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';
$dbName = $_ENV['DB_DATABASE'] ?? 'hotel_booking';

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "========================================\n";
echo "1. DANH SÁCH DỊCH VỤ (DichVu):\n";
echo "========================================\n";
$services = $pdo->query("SELECT MaDV, TenDV, DonGia, TrangThai FROM DichVu")->fetchAll();
foreach ($services as $s) {
    printf("  %-12s %-25s %12s đ  [%s]\n", $s['MaDV'], $s['TenDV'], number_format($s['DonGia']), $s['TrangThai']);
}

echo "\n========================================\n";
echo "2. DANH SÁCH CHI TIẾT DỊCH VỤ (ChiTietDichVu):\n";
echo "========================================\n";
$items = $pdo->query("SELECT MaCTDV, MaDV, TenCTDV, DonGia, SoLuongTon FROM ChiTietDichVu ORDER BY MaDV, MaCTDV")->fetchAll();
foreach ($items as $item) {
    printf("  %-15s (%-10s) %-35s %10s đ  (Tồn: %d)\n", 
        $item['MaCTDV'], $item['MaDV'], $item['TenCTDV'], number_format($item['DonGia']), $item['SoLuongTon']);
}

echo "\n========================================\n";
echo "3. CẤU TRÚC BẢNG CTSuDungDichVu:\n";
echo "========================================\n";
$cols = $pdo->query("DESCRIBE CTSuDungDichVu")->fetchAll();
foreach ($cols as $c) {
    printf("  %-15s %-15s %-8s %-5s\n", $c['Field'], $c['Type'], $c['Null'], $c['Key']);
}
