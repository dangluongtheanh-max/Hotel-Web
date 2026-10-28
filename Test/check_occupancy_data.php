<?php
require_once __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

require_once __DIR__ . '/../Backend/Database/Database.php';
$db = \Database\Database::getConnection();

echo "=== SAMPLE chitietphieunhanphong ===\n";
print_r($db->query("SELECT * FROM chitietphieunhanphong LIMIT 15")->fetchAll(PDO::FETCH_ASSOC));

echo "=== DISTINCT TrangThai in chitietphieunhanphong ===\n";
print_r($db->query("SELECT TrangThai, COUNT(*) as cnt FROM chitietphieunhanphong GROUP BY TrangThai")->fetchAll(PDO::FETCH_ASSOC));

echo "=== DISTINCT TrangThai in phieunhanphong ===\n";
print_r($db->query("SELECT TrangThai, COUNT(*) as cnt FROM phieunhanphong GROUP BY TrangThai")->fetchAll(PDO::FETCH_ASSOC));

echo "=== ROOMS AND TYPES ===\n";
print_r($db->query("SELECT p.MaPhong, p.TrangThaiPhong, lp.MaLoaiPhong, lp.TenLoaiPhong, lp.TinhTrang 
                    FROM phong p JOIN loaiphong lp ON p.MaLoaiPhong = lp.MaLoaiPhong")->fetchAll(PDO::FETCH_ASSOC));
