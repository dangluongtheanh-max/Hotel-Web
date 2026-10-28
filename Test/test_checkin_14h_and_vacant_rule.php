<?php

require_once __DIR__ . '/../Backend/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend'))->load();

use Database\Database;
use App\LeTan\Repositories\LeTanRepository;
use App\LeTan\Services\LeTanService;

echo "========================================================\n";
echo "TEST CHECK-IN RULE: 14:00 & EXCEPTION FOR VACANT ROOMS\n";
echo "========================================================\n\n";

$db = Database::getConnection();
$repo = new LeTanRepository();
$service = new LeTanService($repo);

// Cleanup any old test slips if exists
$db->exec("DELETE FROM ThanhToan WHERE MaHD IN (SELECT MaHD FROM HoaDon WHERE MaPhieu IN ('PNP_TEST_RULE1', 'PNP_TEST_RULE2'))");
$db->exec("DELETE FROM cthd WHERE MaHD IN (SELECT MaHD FROM HoaDon WHERE MaPhieu IN ('PNP_TEST_RULE1', 'PNP_TEST_RULE2'))");
$db->exec("DELETE FROM HoaDon WHERE MaPhieu IN ('PNP_TEST_RULE1', 'PNP_TEST_RULE2')");
$db->exec("DELETE FROM ChiTietPhieuNhanPhong WHERE MaPhieu IN ('PNP_TEST_RULE1', 'PNP_TEST_RULE2')");
$db->exec("DELETE FROM PhieuNhanPhong WHERE MaPhieu IN ('PNP_TEST_RULE1', 'PNP_TEST_RULE2')");

// Ensure room P101 is reset to 'Trong'
$db->exec("UPDATE Phong SET TrangThaiPhong = 'Trong' WHERE MaPhong = 'P101'");

$testMaKH = 'KH001';
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// ---------------------------------------------------------
// TEST 1: Check-in trước ngày nhận phòng (Đặt ngày mai, check-in hôm nay)
// ---------------------------------------------------------
echo "[TEST 1] Đặt phòng cho ngày mai ($tomorrow 14:00). Check-in hôm nay ($today)...\n";
$db->prepare("
    INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH, MaLeTan)
    VALUES ('PNP_TEST_RULE1', :today, 'TrucTiep', 'DaDatPhong', :kh, 'LT001')
")->execute([':today' => $today, ':kh' => $testMaKH]);

$db->prepare("
    INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai)
    VALUES ('PNP_TEST_RULE1', 'P101', :nhan, :tra, 2, 'DaDatPhong')
")->execute([
    ':nhan' => "$tomorrow 14:00:00",
    ':tra'  => date('Y-m-d 12:00:00', strtotime('+2 days'))
]);

try {
    $service->checkin(['maPhieu' => 'PNP_TEST_RULE1', 'maPhong' => 'P101', 'maLeTan' => 'LT002']);
    echo "❌ THẤT BẠI: Đáng lẽ phải chặn check-in trước ngày nhận phòng!\n";
} catch (Exception $e) {
    echo "✅ THÀNH CÔNG: Đã chặn đúng như kỳ vọng!\n";
    echo "   Thông báo lỗi nhận được: " . $e->getMessage() . "\n";
}

// ---------------------------------------------------------
// TEST 2: Đặt phòng hôm nay ($today 23:59 - giả lập giờ nhận trong tương lai)
//         Trường hợp 2A: Phòng KHÔNG TRỐNG (Đang có khách / DangSuDung)
// ---------------------------------------------------------
echo "\n[TEST 2A] Đặt phòng cho hôm nay, giờ nhận là 23:59:00 (chưa đến giờ nhận).\n";
echo "          Phòng P101 đang có khách (TrangThaiPhong = 'DangSuDung')...\n";

$db->prepare("
    INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH, MaLeTan)
    VALUES ('PNP_TEST_RULE2', :today, 'TrucTiep', 'DaDatPhong', :kh, 'LT001')
")->execute([':today' => $today, ':kh' => $testMaKH]);

$db->prepare("
    INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai)
    VALUES ('PNP_TEST_RULE2', 'P101', :nhan, :tra, 2, 'DaDatPhong')
")->execute([
    ':nhan' => "$today 23:59:00",
    ':tra'  => "$tomorrow 12:00:00"
]);

// Set P101 to 'DangSuDung'
$db->exec("UPDATE Phong SET TrangThaiPhong = 'DangSuDung' WHERE MaPhong = 'P101'");

try {
    $service->checkin(['maPhieu' => 'PNP_TEST_RULE2', 'maPhong' => 'P101', 'maLeTan' => 'LT002']);
    echo "❌ THẤT BẠI: Đáng lẽ phải chặn vì phòng chưa trống!\n";
} catch (Exception $e) {
    echo "✅ THÀNH CÔNG: Đã chặn đúng quy định khi phòng chưa trống!\n";
    echo "   Thông báo lỗi nhận được: " . $e->getMessage() . "\n";
}

// ---------------------------------------------------------
// TEST 3: Đặt phòng hôm nay ($today 23:59 - chưa đến giờ nhận).
//         Trường hợp 2B: Phòng ĐANG TRỐNG (Ngoại lệ cho phép check-in sớm)
// ---------------------------------------------------------
echo "\n[TEST 2B] Đặt phòng cho hôm nay, chưa đến giờ nhận phòng (23:59:00).\n";
echo "          Phòng P101 ĐANG TRỐNG (TrangThaiPhong = 'Trong', không có ai đang ở)...\n";

// Set P101 to 'Trong'
$db->exec("UPDATE Phong SET TrangThaiPhong = 'Trong' WHERE MaPhong = 'P101'");

try {
    $res = $service->checkin(['maPhieu' => 'PNP_TEST_RULE2', 'maPhong' => 'P101', 'maLeTan' => 'LT002']);
    echo "✅ THÀNH CÔNG: Ngoại lệ phòng trống đã kích hoạt! Check-in sớm được duyệt thành công!\n";
    echo "   Kết quả: " . $res['message'] . "\n";

    // Kiểm tra trạng thái phòng P101 trong DB
    $stmtP = $db->query("SELECT TrangThaiPhong FROM Phong WHERE MaPhong = 'P101'");
    $trangThaiPhong = $stmtP->fetchColumn();
    echo "   Trạng thái phòng P101 trong bảng Phong: " . $trangThaiPhong . "\n";
    if ($trangThaiPhong === 'DangSuDung') {
        echo "   -> Đồng bộ Phong.TrangThaiPhong = 'DangSuDung' CHÍNH XÁC!\n";
    } else {
        echo "   -> ❌ Sai trạng thái phòng!\n";
    }

    // Kiểm tra ChiTietPhieuNhanPhong
    $stmtCT = $db->query("SELECT TrangThai, MaLeTanCheckin FROM ChiTietPhieuNhanPhong WHERE MaPhieu = 'PNP_TEST_RULE2' AND MaPhong = 'P101'");
    $ct = $stmtCT->fetch(PDO::FETCH_ASSOC);
    echo "   ChiTietPhieuNhanPhong: TrangThai = {$ct['TrangThai']}, MaLeTanCheckin = {$ct['MaLeTanCheckin']}\n";

} catch (Exception $e) {
    echo "❌ THẤT BẠI: Lỗi không mong muốn: " . $e->getMessage() . "\n";
}

// ---------------------------------------------------------
// TEST 4: Check-out và kiểm tra hoàn trả trạng thái phòng về 'Trong'
// ---------------------------------------------------------
echo "\n[TEST 3] Thực hiện Check-out phòng P101...\n";
try {
    $resOut = $service->checkout(['maPhieu' => 'PNP_TEST_RULE2', 'maPhong' => 'P101', 'maLeTan' => 'LT001']);
    echo "✅ THÀNH CÔNG: Check-out thành công!\n";
    
    // Kiểm tra trạng thái phòng P101 trong DB
    $stmtP = $db->query("SELECT TrangThaiPhong FROM Phong WHERE MaPhong = 'P101'");
    $trangThaiPhong = $stmtP->fetchColumn();
    echo "   Trạng thái phòng P101 sau Check-out: " . $trangThaiPhong . "\n";
    if ($trangThaiPhong === 'Trong') {
        echo "   -> Đồng bộ Phong.TrangThaiPhong = 'Trong' HOÀN HẢO!\n";
    } else {
        echo "   -> ❌ Sai trạng thái phòng sau checkout!\n";
    }
} catch (Exception $e) {
    echo "❌ THẤT BẠI: Lỗi khi checkout: " . $e->getMessage() . "\n";
}

// Dọn dẹp dữ liệu test
$db->exec("DELETE FROM ThanhToan WHERE MaHD IN (SELECT MaHD FROM HoaDon WHERE MaPhieu IN ('PNP_TEST_RULE1', 'PNP_TEST_RULE2'))");
$db->exec("DELETE FROM cthd WHERE MaHD IN (SELECT MaHD FROM HoaDon WHERE MaPhieu IN ('PNP_TEST_RULE1', 'PNP_TEST_RULE2'))");
$db->exec("DELETE FROM HoaDon WHERE MaPhieu IN ('PNP_TEST_RULE1', 'PNP_TEST_RULE2')");
$db->exec("DELETE FROM ChiTietPhieuNhanPhong WHERE MaPhieu IN ('PNP_TEST_RULE1', 'PNP_TEST_RULE2')");
$db->exec("DELETE FROM PhieuNhanPhong WHERE MaPhieu IN ('PNP_TEST_RULE1', 'PNP_TEST_RULE2')");
$db->exec("UPDATE Phong SET TrangThaiPhong = 'Trong' WHERE MaPhong = 'P101'");
echo "\n[CLEANUP] Đã dọn dẹp dữ liệu kiểm thử thành công.\n";
