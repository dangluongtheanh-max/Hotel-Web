<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend'))->load();
$db = Database\Database::getConnection();

echo "1. Drop CHECK cũ...\n";
$db->exec("ALTER TABLE `phieunhanphong` DROP CHECK `phieunhanphong_chk_2`");
$db->exec("ALTER TABLE `chitietphieunhanphong` DROP CHECK `chitietphieunhanphong_chk_3`");
echo "   -> Đã drop phieunhanphong_chk_2 và chitietphieunhanphong_chk_3\n";

echo "2. Cập nhật dữ liệu cũ...\n";
$db->exec("UPDATE phieunhanphong SET TrangThai = 'DaDatPhong' WHERE TrangThai IN ('DaDat', 'ChoXacNhan')");
$db->exec("UPDATE phieunhanphong SET TrangThai = 'DaHuy' WHERE TrangThai = 'TuChoi'");
$db->exec("UPDATE chitietphieunhanphong SET TrangThai = 'DaDatPhong' WHERE TrangThai = 'DaDat'");
$db->exec("UPDATE chitietphieunhanphong SET TrangThai = 'DaHuy' WHERE TrangThai = 'TuChoi'");

echo "3. Thêm CHECK constraints mới (4 trạng thái)...\n";
$db->exec("ALTER TABLE `phieunhanphong` ADD CONSTRAINT `phieunhanphong_chk_2` CHECK (TrangThai IN ('DaDatPhong', 'DangLuuTru', 'DaHuy', 'DaTraPhong'))");
$db->exec("ALTER TABLE `chitietphieunhanphong` ADD CONSTRAINT `chitietphieunhanphong_chk_3` CHECK (TrangThai IN ('DaDatPhong', 'DangLuuTru', 'DaHuy', 'DaTraPhong'))");

echo "4. Kiểm tra danh sách trạng thái hiện tại trong CSDL:\n";
$stmt = $db->query("SELECT DISTINCT TrangThai FROM phieunhanphong");
echo " - PhieuNhanPhong: " . implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";

$stmt2 = $db->query("SELECT DISTINCT TrangThai FROM chitietphieunhanphong");
echo " - ChiTietPhieuNhanPhong: " . implode(', ', $stmt2->fetchAll(PDO::FETCH_COLUMN)) . "\n";

echo "Hoàn tất thành công 100%!\n";
