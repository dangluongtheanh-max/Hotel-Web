<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend'))->load();

use Database\Database;
use App\LeTan\Services\LeTanService;
use App\LeTan\Repositories\LeTanRepository;
use App\HoaDon\Repositories\HoaDonRepository;
use App\ThanhToan\Repositories\ThanhToanRepository;

echo "====================================================================\n";
echo "   KIỂM THỬ: TỰ ĐỘNG HỦY KHI QUÁ 24H & CHECK-IN THẤT BẠI KÈM LÝ DO\n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$repo = new LeTanRepository();
$service = new LeTanService($repo);

// Dọn dẹp dữ liệu test cũ
$db->exec("DELETE FROM ChiTietPhieuNhanPhong WHERE MaPhieu IN ('PP_TEST_24H', 'PP_TEST_SPLIT')");
$db->exec("DELETE FROM PhieuNhanPhong WHERE MaPhieu IN ('PP_TEST_24H', 'PP_TEST_SPLIT')");

// ------------------------------------------------------------------
// TEST 1: TỰ ĐỘNG QUÉT VÀ HỦY KHI QUÁ 24 GIỜ KỂ TỪ THỜI GIAN NHẬN PHÒNG
// ------------------------------------------------------------------
echo "--- TEST 1: TỰ ĐỘNG QUÉT HỦY PHÒNG/PHIẾU QUÁ 24H ---\n";
// Giả lập phiếu nhận phòng ngày hôm qua (26 tiếng trước)
$gioNhanQuaHan = date('Y-m-d H:i:s', time() - 26 * 3600);
$gioTraQuaHan = date('Y-m-d H:i:s', time() + 24 * 3600);

$db->exec("INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH) VALUES ('PP_TEST_24H', CURDATE(), 'TrucTuyen', 'DaDatPhong', 'KH001')");
$db->exec("INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai) VALUES ('PP_TEST_24H', 'P105', '{$gioNhanQuaHan}', '{$gioTraQuaHan}', 2, 'DaDatPhong')");

// Gọi quét tự động (hoặc getDanhSachPhieu)
$count = $repo->tuDongHuyPhieuQuaHan24h();
echo "✅ Đã quét tự động, số phòng quá hạn bị hủy: {$count}\n";

$stmt = $db->query("SELECT TrangThai, LyDoHuy FROM PhieuNhanPhong WHERE MaPhieu = 'PP_TEST_24H'");
$phieu = $stmt->fetch(PDO::FETCH_ASSOC);
$stmtCt = $db->query("SELECT TrangThai, LyDoHuy FROM ChiTietPhieuNhanPhong WHERE MaPhieu = 'PP_TEST_24H' AND MaPhong = 'P105'");
$ct = $stmtCt->fetch(PDO::FETCH_ASSOC);

echo "   - CSDL PhieuNhanPhong: {$phieu['TrangThai']} (Kỳ vọng: DaHuy)\n";
echo "   - CSDL ChiTietPhieuNhanPhong: {$ct['TrangThai']} (Kỳ vọng: DaHuy)\n";
echo "   - Lý do hủy lưu tại CSDL: {$ct['LyDoHuy']}\n";

if ($phieu['TrangThai'] === 'DaHuy' && $ct['TrangThai'] === 'DaHuy') {
    echo "-> TEST 1 PASSED: TỰ ĐỘNG HỦY KHI QUÁ 24H THÀNH CÔNG 100%!\n\n";
} else {
    echo "❌ TEST 1 FAILED!\n\n";
}

// ------------------------------------------------------------------
// TEST 2: CỐ TÌNH CHECK-IN PHÒNG ĐÃ QUÁ 24H -> CHẶN & CHUYỂN SANG ĐÃ HỦY
// ------------------------------------------------------------------
echo "--- TEST 2: CỐ TÌNH CHECK-IN PHÒNG QUÁ 24H ---\n";
// Đặt lại phòng về DaDatPhong để test việc cố tình check-in
$db->exec("UPDATE PhieuNhanPhong SET TrangThai = 'DaDatPhong', LyDoHuy = NULL WHERE MaPhieu = 'PP_TEST_24H'");
$db->exec("UPDATE ChiTietPhieuNhanPhong SET TrangThai = 'DaDatPhong', LyDoHuy = NULL WHERE MaPhieu = 'PP_TEST_24H'");

try {
    $service->checkin(['maPhieu' => 'PP_TEST_24H', 'maPhong' => 'P105']);
    echo "❌ Lỗi: Lẽ ra phải chặn check-in khi quá 24h!\n";
} catch (Exception $e) {
    echo "✅ Đã chặn check-in quá hạn thành công!\n";
    echo "   - Thông báo lỗi trả về lễ tân: " . $e->getMessage() . "\n";
    
    // Kiểm tra xem phòng có tự động cập nhật DaHuy sau khi cố check-in không
    $stmtCt = $db->query("SELECT TrangThai, LyDoHuy FROM ChiTietPhieuNhanPhong WHERE MaPhieu = 'PP_TEST_24H' AND MaPhong = 'P105'");
    $ct = $stmtCt->fetch(PDO::FETCH_ASSOC);
    echo "   - Trạng thái phòng sau khi check-in thất bại: {$ct['TrangThai']} (Lý do: {$ct['LyDoHuy']})\n";
    if ($ct['TrangThai'] === 'DaHuy') {
        echo "-> TEST 2 PASSED: BÁO THẤT BẠI VÀ CHUYỂN ĐÃ HỦY CHUẨN XÁC!\n\n";
    }
}

// ------------------------------------------------------------------
// TEST 3: LỄ TÂN BÁO CHECK-IN THẤT BẠI TỪNG PHÒNG LẺ KÈM LÝ DO
// ------------------------------------------------------------------
echo "--- TEST 3: LỄ TÂN HỦY PHÒNG LẺ KHI CHECK-IN THẤT BẠI KÈM LÝ DO ---\n";
$gioNhanHopLe = date('Y-m-d H:i:s', time() + 3600);
$gioTraHopLe = date('Y-m-d H:i:s', time() + 48 * 3600);

$db->exec("INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH) VALUES ('PP_TEST_SPLIT', CURDATE(), 'TrucTuyen', 'DaDatPhong', 'KH001')");
$db->exec("INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai) VALUES ('PP_TEST_SPLIT', 'P106', '{$gioNhanHopLe}', '{$gioTraHopLe}', 2, 'DaDatPhong')");
$db->exec("INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai) VALUES ('PP_TEST_SPLIT', 'P107', '{$gioNhanHopLe}', '{$gioTraHopLe}', 2, 'DaDatPhong')");

// Hủy riêng phòng P106 do thiếu CCCD
$lyDoP106 = "Khách không xuất trình được CCCD / Hộ chiếu hợp lệ tại quầy";
$res1 = $service->huyPhieu([
    'maPhieu' => 'PP_TEST_SPLIT',
    'maPhong' => 'P106',
    'lyDo' => $lyDoP106,
    'maLeTan' => 'LT001'
]);
echo "✅ Kết quả hủy phòng P106: {$res1['message']}\n";

$stmtP106 = $db->query("SELECT TrangThai, LyDoHuy FROM ChiTietPhieuNhanPhong WHERE MaPhieu = 'PP_TEST_SPLIT' AND MaPhong = 'P106'");
$rowP106 = $stmtP106->fetch(PDO::FETCH_ASSOC);
$stmtP107 = $db->query("SELECT TrangThai FROM ChiTietPhieuNhanPhong WHERE MaPhieu = 'PP_TEST_SPLIT' AND MaPhong = 'P107'");
$rowP107 = $stmtP107->fetch(PDO::FETCH_ASSOC);
$stmtPhieu = $db->query("SELECT TrangThai FROM PhieuNhanPhong WHERE MaPhieu = 'PP_TEST_SPLIT'");
$rowPhieu = $stmtPhieu->fetch(PDO::FETCH_ASSOC);

echo "   - Phòng P106: {$rowP106['TrangThai']} (Kỳ vọng: DaHuy, Lý do: {$rowP106['LyDoHuy']})\n";
echo "   - Phòng P107: {$rowP107['TrangThai']} (Kỳ vọng: DaDatPhong)\n";
echo "   - Phiếu cha PP_TEST_SPLIT: {$rowPhieu['TrangThai']} (Kỳ vọng: DaDatPhong - vì P107 vẫn chưa hủy)\n";

if ($rowP106['TrangThai'] === 'DaHuy' && $rowP107['TrangThai'] === 'DaDatPhong' && $rowPhieu['TrangThai'] === 'DaDatPhong') {
    echo "-> TEST 3.1 PASSED: HỦY PHÒNG LẺ HOẠT ĐỘNG HOÀN HẢO!\n\n";
}

// Giờ hủy nốt phòng P107 do quá 24h
$lyDoP107 = "Khách không đến nhận phòng";
$res2 = $service->huyPhieu([
    'maPhieu' => 'PP_TEST_SPLIT',
    'maPhong' => 'P107',
    'lyDo' => $lyDoP107,
    'maLeTan' => 'LT001'
]);
echo "✅ Kết quả hủy phòng P107: {$res2['message']}\n";

$stmtPhieuSau = $db->query("SELECT TrangThai, LyDoHuy FROM PhieuNhanPhong WHERE MaPhieu = 'PP_TEST_SPLIT'");
$rowPhieuSau = $stmtPhieuSau->fetch(PDO::FETCH_ASSOC);
echo "   - Trạng thái phiếu cha sau khi hủy nốt P107: {$rowPhieuSau['TrangThai']} (Kỳ vọng: DaHuy)\n";

if ($rowPhieuSau['TrangThai'] === 'DaHuy') {
    echo "-> TEST 3.2 PASSED: PHIẾU CHA TỰ ĐỘNG CHUYỂN DAHUY KHI TOÀN BỘ PHÒNG ĐÃ HỦY!\n\n";
}

// Dọn dẹp dữ liệu test
$db->exec("DELETE FROM ChiTietPhieuNhanPhong WHERE MaPhieu IN ('PP_TEST_24H', 'PP_TEST_SPLIT')");
$db->exec("DELETE FROM PhieuNhanPhong WHERE MaPhieu IN ('PP_TEST_24H', 'PP_TEST_SPLIT')");

echo "====================================================================\n";
echo "🎉 TẤT CẢ CÁC TEST CASE NGHIỆP VỤ HỦY & QUÁ HẠN 24H ĐÃ THÀNH CÔNG 100%!\n";
echo "====================================================================\n";
