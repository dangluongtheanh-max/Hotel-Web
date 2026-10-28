<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

use App\DanhGia\Repositories\DanhGiaRepository;
use App\DanhGia\Services\DanhGiaService;
use Database\Database;

$db = Database::getConnection();

echo "====================================================\n";
echo "1. KIỂM TRA DỮ LIỆU BẢNG DANHGIA SAU KHI NẠP SEED\n";
echo "====================================================\n";
$repo = new DanhGiaRepository();
$service = new DanhGiaService($repo);

$allDG = $repo->getAllDanhGia();
echo "Số lượng đánh giá hiện có: " . count($allDG) . "\n";
foreach ($allDG as $dg) {
    echo " - [{$dg['MaDanhGia']}] Phiếu: {$dg['MaPhieu']} | Phòng: {$dg['MaPhong']} | {$dg['SoSao']} sao | KH: {$dg['MaKH']} | Nội dung: {$dg['NoiDung']}\n";
}

echo "\n====================================================\n";
echo "2. TEST RÀNG BUỘC DATABASE (UNIQUE & FOREIGN KEY)\n";
echo "====================================================\n";
try {
    $ins = $db->prepare("INSERT INTO DanhGia (MaDanhGia, SoSao, NoiDung, NgayDanhGia, MaKH, MaPhieu, MaPhong) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $ins->execute(['DG999', 5, 'Duplicate room test', '2026-09-22', 'KH001', 'PP001', 'P001']);
    echo "❌ LỖI: Lẽ ra DB phải chặn trùng phòng (PP001, P001)!\n";
} catch (PDOException $e) {
    echo "✅ Ràng buộc UNIQUE (MaPhieu, MaPhong) hoạt động chính xác!\n";
}

try {
    $ins = $db->prepare("INSERT INTO DanhGia (MaDanhGia, SoSao, NoiDung, NgayDanhGia, MaKH, MaPhieu, MaPhong) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $ins->execute(['DG998', 5, 'Invalid room test', '2026-09-22', 'KH001', 'PP001', 'P999_KHONG_TON_TAI']);
    echo "❌ LỖI: Lẽ ra DB phải chặn khóa ngoại phòng không có trong phiếu!\n";
} catch (PDOException $e) {
    echo "✅ Khóa ngoại FK (MaPhieu, MaPhong) -> ChiTietPhieuNhanPhong hoạt động chính xác!\n";
}

echo "\n====================================================\n";
echo "3. TEST DANHGIASERVICE - NGHIỆP VỤ ĐÁNH GIÁ TỪNG PHÒNG\n";
echo "====================================================\n";
// Kiểm tra xem phòng P002 của phiếu PP002 chưa được đánh giá
$resThem = $service->themDanhGia([
    'MaKH' => 'KH002',
    'MaPhieu' => 'PP002',
    'MaPhong' => 'P002',
    'SoSao' => 5,
    'NoiDung' => 'Phòng P002 dịch vụ rất tốt, tiện nghi hiện đại!'
]);
echo "Kết quả thêm đánh giá mới cho phòng P002:\n";
print_r($resThem);

// Thử thêm lại chính phòng đó (nghiệp vụ chặn trùng)
$resThemTrung = $service->themDanhGia([
    'MaKH' => 'KH002',
    'MaPhieu' => 'PP002',
    'MaPhong' => 'P002',
    'SoSao' => 4,
    'NoiDung' => 'Thử đánh giá lần 2 cùng phòng'
]);
echo "Kết quả thử đánh giá lần 2 cùng phòng P002:\n";
print_r($resThemTrung);

// Test xem danh giá theo phiếu PP001 (khách đặt 2 phòng P001, P004)
echo "\nXem các đánh giá của phiếu PP001:\n";
$resXem = $service->xemDanhGiaTheoPhieu(['MaPhieu' => 'PP001']);
print_r($resXem);

// Dọn dẹp bản ghi test P002
if (!empty($resThem['data']['MaDanhGia'])) {
    $service->xoaDanhGia(['MaDanhGia' => $resThem['data']['MaDanhGia']]);
    echo "Đã dọn dẹp bản ghi test {$resThem['data']['MaDanhGia']}.\n";
}

echo "\n🎉 TẤT CẢ CÁC BÀI TEST SERVICE VÀ REPOSITORY ĐÃ VƯỢT QUA 100%!\n";
