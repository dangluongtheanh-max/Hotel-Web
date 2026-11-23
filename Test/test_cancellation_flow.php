<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend'))->load();

use Database\Database;
use Shared\ChinhSachHoanHuy;
use App\LeTan\Services\LeTanService;
use App\LeTan\Repositories\LeTanRepository;

echo "====================================================================\n";
echo "   KIỂM THỬ: CHÍNH SÁCH HOÀN HỦY THỐNG NHẤT & NO-SHOW QUÁ HẠN TRẢ PHÒNG\n";
echo "====================================================================\n\n";

// ------------------------------------------------------------------
// PHẦN A: UNIT TEST QUY TẮC DÙNG CHUNG (Shared\ChinhSachHoanHuy)
// ------------------------------------------------------------------
echo "--- PHẦN A: KIỂM THỬ QUY TẮC CHÍNH SÁCH DÙNG CHUNG ---\n";
$now = strtotime('2026-11-01 14:00:00');
$r100 = ChinhSachHoanHuy::tinhHoanTheoThoiGian('2026-11-08 14:00:00', 2000000, $now, false); // đúng 7 ngày
$r50  = ChinhSachHoanHuy::tinhHoanTheoThoiGian('2026-11-05 14:00:00', 2000000, $now, false); // 4 ngày (3 <= x < 7)
$r0   = ChinhSachHoanHuy::tinhHoanTheoThoiGian('2026-11-03 14:00:00', 2000000, $now, false); // 2 ngày (< 3)
$rNs  = ChinhSachHoanHuy::tinhHoanTheoThoiGian('2026-10-30 14:00:00', 2000000, $now, true);  // No-show

$unitPass = true;
$assertions = [
    'Mốc >= 7 ngày (hoàn 100%, phí 0%)' => ((float)$r100['tyLeHoan'] === 100.0 && (float)$r100['tienHoan'] === 2000000.0 && (float)$r100['phiHuy'] === 0.0),
    'Mốc 3-<7 ngày (hoàn 50%, phí 50%)' => ((float)$r50['tyLeHoan'] === 50.0 && (float)$r50['tienHoan'] === 1000000.0 && (float)$r50['phiHuy'] === 1000000.0),
    'Mốc < 3 ngày (hoàn 0%, phí 100%)'   => ((float)$r0['tyLeHoan'] === 0.0 && (float)$r0['tienHoan'] === 0.0 && (float)$r0['phiHuy'] === 2000000.0),
    'Mốc No-show (hoàn 0%, phí 100%)'    => ((float)$rNs['tyLeHoan'] === 0.0 && (float)$rNs['tienHoan'] === 0.0 && (float)$rNs['phiHuy'] === 2000000.0),
    'Chưa quá ThoiGianTraPhong dù trễ >24h' => (ChinhSachHoanHuy::daQuaHanTraPhong('2026-11-02 12:00:00', $now) === false),
    'Đã quá ThoiGianTraPhong dự kiến'       => (ChinhSachHoanHuy::daQuaHanTraPhong('2026-11-01 13:59:59', $now) === true),
];

foreach ($assertions as $label => $ok) {
    echo ($ok ? "✅ " : "❌ ") . $label . "\n";
    if (!$ok) {
        $unitPass = false;
    }
}
if (!$unitPass) {
    exit(1);
}
echo "-> PHẦN A PASSED!\n\n";

// ------------------------------------------------------------------
// PHẦN B: TÍCH HỢP CSDL (No-show quá giờ trả phòng & hủy phòng lẻ)
// ------------------------------------------------------------------
$db = Database::getConnection();
$repo = new LeTanRepository();
$service = new LeTanService($repo);

// Dọn dẹp dữ liệu test cũ (theo đúng thứ tự FK)
$db->exec("DELETE FROM ThanhToan WHERE MaHD IN ('HD_TEST_SPLIT')");
$db->exec("DELETE FROM HoaDon WHERE MaPhieu IN ('PP_TEST_NOSHOW', 'PP_TEST_KEEP', 'PP_TEST_SPLIT')");
$db->exec("DELETE FROM ChiTietPhieuNhanPhong WHERE MaPhieu IN ('PP_TEST_NOSHOW', 'PP_TEST_KEEP', 'PP_TEST_SPLIT')");
$db->exec("DELETE FROM PhieuNhanPhong WHERE MaPhieu IN ('PP_TEST_NOSHOW', 'PP_TEST_KEEP', 'PP_TEST_SPLIT')");

// Tìm 2 phòng Trong rảnh để test không ảnh hưởng dữ liệu đang lưu trú
$idleRooms = $db->query("SELECT p.MaPhong FROM Phong p WHERE p.TrangThaiPhong = 'Trong' AND NOT EXISTS (SELECT 1 FROM ChiTietPhieuNhanPhong ct WHERE ct.MaPhong = p.MaPhong AND ct.TrangThai IN ('DaDatPhong','DangLuuTru','ChoThanhToan')) ORDER BY p.MaPhong LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
if (count($idleRooms) < 2) {
    throw new RuntimeException('Cần ít nhất 2 phòng Trong rảnh để chạy test tích hợp CSDL.');
}
[$roomA, $roomB] = $idleRooms;

// ------------------------------------------------------------------
// TEST 1: TRỄ CHECK-IN (>24H) NHƯNG CHƯA QUÁ THỜI GIAN TRẢ PHÒNG -> GIỮ PHÒNG
// ------------------------------------------------------------------
echo "--- TEST 1: TRỄ CHECK-IN >24H NHƯNG CHƯA QUÁ GIỜ TRẢ PHÒNG DỰ KIẾN ---\n";
$gioNhanDaQua = date('Y-m-d H:i:s', time() - 26 * 3600);
$gioTraConHan = date('Y-m-d H:i:s', time() + 24 * 3600);

$db->exec("INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH) VALUES ('PP_TEST_KEEP', CURDATE(), 'TrucTuyen', 'DaDatPhong', 'KH001')");
$db->exec("INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai) VALUES ('PP_TEST_KEEP', '{$roomA}', '{$gioNhanDaQua}', '{$gioTraConHan}', 2, 'DaDatPhong')");

$repo->tuDongHuyPhieuQuaHanTraPhong();
$stmtKeep = $db->query("SELECT TrangThai FROM ChiTietPhieuNhanPhong WHERE MaPhieu = 'PP_TEST_KEEP' AND MaPhong = '{$roomA}'");
$ctKeep = $stmtKeep->fetch(PDO::FETCH_ASSOC);
echo "   - Trạng thái PP_TEST_KEEP/{$roomA}: {$ctKeep['TrangThai']} (Kỳ vọng: DaDatPhong)\n";
if ($ctKeep['TrangThai'] === 'DaDatPhong') {
    echo "-> TEST 1 PASSED: GIỮ PHÒNG KHI CHƯA QUÁ GIỜ TRẢ PHÒNG DỰ KIẾN!\n\n";
} else {
    echo "❌ TEST 1 FAILED!\n\n";
    exit(1);
}

// ------------------------------------------------------------------
// TEST 2: QUÁ THỜI GIAN TRẢ PHÒNG DỰ KIẾN -> TỰ ĐỘNG HỦY NO-SHOW (HOÀN 0%)
// ------------------------------------------------------------------
echo "--- TEST 2: QUÁ GIỜ TRẢ PHÒNG DỰ KIẾN -> TỰ HỦY NO-SHOW & CHẶN CHECK-IN ---\n";
$gioNhanQuaHan = date('Y-m-d H:i:s', time() - 48 * 3600);
$gioTraQuaHan = date('Y-m-d H:i:s', time() - 3600);

$db->exec("INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH) VALUES ('PP_TEST_NOSHOW', CURDATE(), 'TrucTuyen', 'DaDatPhong', 'KH001')");
$db->exec("INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai) VALUES ('PP_TEST_NOSHOW', '{$roomA}', '{$gioNhanQuaHan}', '{$gioTraQuaHan}', 2, 'DaDatPhong')");

$count = $repo->tuDongHuyPhieuQuaHanTraPhong();
echo "✅ Đã quét tự động, số phòng No-show quá giờ trả bị hủy: {$count}\n";

$stmt = $db->query("SELECT TrangThai, TyLeHoan, SoTienHoan, LyDoHuy FROM PhieuNhanPhong WHERE MaPhieu = 'PP_TEST_NOSHOW'");
$phieu = $stmt->fetch(PDO::FETCH_ASSOC);
$stmtCt = $db->query("SELECT TrangThai, TyLeHoan, LyDoHuy FROM ChiTietPhieuNhanPhong WHERE MaPhieu = 'PP_TEST_NOSHOW' AND MaPhong = '{$roomA}'");
$ct = $stmtCt->fetch(PDO::FETCH_ASSOC);

echo "   - CSDL PhieuNhanPhong: {$phieu['TrangThai']}, TyLeHoan={$phieu['TyLeHoan']} (Kỳ vọng: DaHuy, 0)\n";
echo "   - CSDL ChiTietPhieuNhanPhong: {$ct['TrangThai']}, TyLeHoan={$ct['TyLeHoan']} (Kỳ vọng: DaHuy, 0)\n";

if ($phieu['TrangThai'] === 'DaHuy' && $ct['TrangThai'] === 'DaHuy' && (int)$ct['TyLeHoan'] === 0) {
    echo "-> TEST 2.1 PASSED!\n\n";
} else {
    echo "❌ TEST 2.1 FAILED!\n\n";
    exit(1);
}

// Cố tình check-in phiếu đã quá giờ trả phòng -> chặn & chuyển DaHuy
$db->exec("UPDATE PhieuNhanPhong SET TrangThai = 'DaDatPhong', LyDoHuy = NULL WHERE MaPhieu = 'PP_TEST_NOSHOW'");
$db->exec("UPDATE ChiTietPhieuNhanPhong SET TrangThai = 'DaDatPhong', LyDoHuy = NULL WHERE MaPhieu = 'PP_TEST_NOSHOW'");

try {
    $service->checkin(['maPhieu' => 'PP_TEST_NOSHOW', 'maPhong' => $roomA]);
    echo "❌ Lỗi: Lẽ ra phải chặn check-in khi đã quá giờ trả phòng dự kiến!\n";
    exit(1);
} catch (Exception $e) {
    echo "✅ Đã chặn check-in quá giờ trả phòng thành công: " . $e->getMessage() . "\n";
    $stmtCt2 = $db->query("SELECT TrangThai FROM ChiTietPhieuNhanPhong WHERE MaPhieu = 'PP_TEST_NOSHOW' AND MaPhong = '{$roomA}'");
    $ct2 = $stmtCt2->fetch(PDO::FETCH_ASSOC);
    if ($ct2['TrangThai'] === 'DaHuy') {
        echo "-> TEST 2.2 PASSED!\n\n";
    } else {
        echo "❌ TEST 2.2 FAILED!\n\n";
        exit(1);
    }
}

// ------------------------------------------------------------------
// TEST 3: KHÁCH TỰ HỦY & HỦY PHÒNG LẺ ĐÃ THANH TOÁN (>= 7 NGÀY -> 100%)
// ------------------------------------------------------------------
echo "--- TEST 3: KHÁCH TỰ HỦY PHÒNG LẺ ĐÃ THANH TOÁN (>= 7 NGÀY -> HOÀN 100%) ---\n";
$gioNhanHopLe = date('Y-m-d H:i:s', time() + 8 * 86400); // >= 7 ngày -> hoàn 100%
$gioTraHopLe = date('Y-m-d H:i:s', time() + 10 * 86400);

$db->exec("INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH) VALUES ('PP_TEST_SPLIT', CURDATE(), 'TrucTuyen', 'DaDatPhong', 'KH001')");
$db->exec("INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai) VALUES ('PP_TEST_SPLIT', '{$roomA}', '{$gioNhanHopLe}', '{$gioTraHopLe}', 2, 'DaDatPhong')");
$db->exec("INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai) VALUES ('PP_TEST_SPLIT', '{$roomB}', '{$gioNhanHopLe}', '{$gioTraHopLe}', 2, 'DaDatPhong')");
$db->exec("INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT) VALUES ('HD_TEST_SPLIT', 'PP_TEST_SPLIT', 4320000)");
$db->exec("INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD) VALUES ('TT_TEST_SPLIT', 4320000, NOW(), 'ChuyenKhoan', 'HoanTat', 'HD_TEST_SPLIT')");

$res1 = $service->huyPhieu([
    'maPhieu' => 'PP_TEST_SPLIT',
    'maPhong' => $roomA,
    'actor' => 'khachHang',
    'maKH' => 'KH001',
    'lyDo' => "Khách tự đổi lịch phòng {$roomA}",
    'nganHangHoan' => 'VCB',
    'stkHoan' => '1012345678',
    'tenChuTKHoan' => 'HO ANH HIGH',
]);
echo "✅ Kết quả hủy phòng {$roomA}: {$res1['message']} (TyLeHoan={$res1['TyLeHoan']}%)\n";

$stmtP106 = $db->query("SELECT TrangThai, TyLeHoan FROM ChiTietPhieuNhanPhong WHERE MaPhieu = 'PP_TEST_SPLIT' AND MaPhong = '{$roomA}'");
$rowP106 = $stmtP106->fetch(PDO::FETCH_ASSOC);
$stmtP107 = $db->query("SELECT TrangThai FROM ChiTietPhieuNhanPhong WHERE MaPhieu = 'PP_TEST_SPLIT' AND MaPhong = '{$roomB}'");
$rowP107 = $stmtP107->fetch(PDO::FETCH_ASSOC);
$stmtPhieu = $db->query("SELECT TrangThai FROM PhieuNhanPhong WHERE MaPhieu = 'PP_TEST_SPLIT'");
$rowPhieu = $stmtPhieu->fetch(PDO::FETCH_ASSOC);
$stmtTT1 = $db->query("SELECT TrangThaiThanhToan, SoTienHoan, NganHangHoan FROM ThanhToan WHERE MaThanhToan = 'TT_TEST_SPLIT'");
$rowTT1 = $stmtTT1->fetch(PDO::FETCH_ASSOC);

if ($rowP106['TrangThai'] === 'DaHuy' && (int)$rowP106['TyLeHoan'] === 100 && $rowP107['TrangThai'] === 'DaDatPhong' && $rowPhieu['TrangThai'] === 'DaDatPhong' && $rowTT1['TrangThaiThanhToan'] === 'HoanTienMotPhan') {
    echo "-> TEST 3.1 PASSED: KHÁCH TỰ HỦY PHÒNG LẺ & ĐỒNG BỘ HoanTienMotPhan CHUẨN!\n\n";
} else {
    echo "❌ TEST 3.1 FAILED!\n\n";
    exit(1);
}

$res2 = $service->huyPhieu([
    'maPhieu' => 'PP_TEST_SPLIT',
    'maPhong' => $roomB,
    'lyDo' => "Khách hủy nốt {$roomB}",
    'maLeTan' => 'LT001'
]);
$stmtPhieuSau = $db->query("SELECT TrangThai FROM PhieuNhanPhong WHERE MaPhieu = 'PP_TEST_SPLIT'");
$rowPhieuSau = $stmtPhieuSau->fetch(PDO::FETCH_ASSOC);
$stmtTT2 = $db->query("SELECT TrangThaiThanhToan, SoTienHoan FROM ThanhToan WHERE MaThanhToan = 'TT_TEST_SPLIT'");
$rowTT2 = $stmtTT2->fetch(PDO::FETCH_ASSOC);
if ($rowPhieuSau['TrangThai'] === 'DaHuy' && $rowTT2['TrangThaiThanhToan'] === 'DaHoanTien' && (float)$rowTT2['SoTienHoan'] === 4320000.0) {
    echo "-> TEST 3.2 PASSED: PHIẾU CHA CHUYỂN DAHUY & THANH TOÁN CHUYỂN DaHoanTien (4.320.000đ)!\n\n";
} else {
    echo "❌ TEST 3.2 FAILED!\n\n";
    exit(1);
}

// Dọn dẹp dữ liệu test & trả trạng thái Trong cho phòng test
$db->exec("DELETE FROM ThanhToan WHERE MaHD IN ('HD_TEST_SPLIT')");
$db->exec("DELETE FROM HoaDon WHERE MaPhieu IN ('PP_TEST_NOSHOW', 'PP_TEST_KEEP', 'PP_TEST_SPLIT')");
$db->exec("DELETE FROM ChiTietPhieuNhanPhong WHERE MaPhieu IN ('PP_TEST_NOSHOW', 'PP_TEST_KEEP', 'PP_TEST_SPLIT')");
$db->exec("DELETE FROM PhieuNhanPhong WHERE MaPhieu IN ('PP_TEST_NOSHOW', 'PP_TEST_KEEP', 'PP_TEST_SPLIT')");
$db->exec("UPDATE Phong SET TrangThaiPhong = 'Trong' WHERE MaPhong IN ('{$roomA}', '{$roomB}')");

echo "====================================================================\n";
echo "🎉 TẤT CẢ TEST CASE ĐÃ HOÀN TẤT!\n";
echo "====================================================================\n";
