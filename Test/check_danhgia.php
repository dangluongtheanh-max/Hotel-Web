<?php
require_once __DIR__ . '/../Backend/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend')->load();
require_once __DIR__ . '/../Backend/Database/Database.php';

$pdo = Database\Database::getConnection();

echo "=== TABLE danhgia SCHEMA & DATA ===\n";
try {
    $stmt = $pdo->query("DESCRIBE danhgia");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "{$c['Field']} ({$c['Type']}) Null:{$c['Null']} Key:{$c['Key']}\n";
    }

    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM danhgia");
    $cnt = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "Total records in danhgia: $cnt\n";

    if ($cnt > 0) {
        $stmt = $pdo->query("SELECT d.*, k.HoTen, p.MaLoaiPhong, lp.TenLoaiPhong 
                             FROM danhgia d 
                             LEFT JOIN khachhang k ON d.MaKH = k.MaKH 
                             LEFT JOIN phong p ON d.MaPhong = p.MaPhong 
                             LEFT JOIN loaiphong lp ON p.MaLoaiPhong = lp.MaLoaiPhong 
                             LIMIT 10");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        print_r($rows);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
