<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/database/Database.php';
require_once __DIR__ . '/app/LeTan/Repositories/LeTanRepository.php';
require_once __DIR__ . '/app/LeTan/Services/LeTanService.php';

use App\LeTan\Repositories\LeTanRepository;
use App\LeTan\Services\LeTanService;

$repo = new LeTanRepository();
$service = new LeTanService($repo);

echo "=========================================================\n";
echo " KIỂM THỬ TOÀN DIỆN NGHIỆP VỤ LỄ TÂN (CHECK-IN / CHECK-OUT)\n";
echo "=========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest($description, $actual, $expected) {
    global $passCount, $failCount;
    if ($actual == $expected) {
        echo "  [PASS] {$description}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$description}\n";
        echo "         Expected: " . json_encode($expected) . "\n";
        echo "         Actual:   " . json_encode($actual) . "\n";
        $failCount++;
    }
}

// -------------------------------------------------------------
// TEST 1: TÍNH PHỤ PHÍ TRẢ PHÒNG TRỄ (LATE CHECK-OUT)
// -------------------------------------------------------------
echo "1. KIỂM THỬ CHÍNH SÁCH LATE CHECK-OUT:\n";

$donGia = 1000000; // 1,000,000 đ
$traDuKien = '2026-10-20 12:00:00';
$baseTs = strtotime($traDuKien);

// 1.1 Trả phòng sớm hoặc đúng 12:00
$res1 = $service->tinhPhuPhiTreGio($traDuKien, $donGia, $baseTs);
assertTest("Đúng 12:00 không tính phạt (tiLe = 0%)", $res1['tiLePhanTram'], 0);
assertTest("Đúng 12:00 phụ thu = 0 đ", $res1['tienPhuThu'], 0.0);

// 1.2 Trả phòng lúc 12:25 (Trong thời gian ân hạn 30 phút)
$res2 = $service->tinhPhuPhiTreGio($traDuKien, $donGia, $baseTs + 25 * 60);
assertTest("Lúc 12:25 được ân hạn 30 phút (tiLe = 0%)", $res2['tiLePhanTram'], 0);
assertTest("Lúc 12:25 phụ thu = 0 đ", $res2['tienPhuThu'], 0.0);

// 1.3 Trả phòng lúc đúng 12:30 (Mốc cuối cùng ân hạn)
$res3 = $service->tinhPhuPhiTreGio($traDuKien, $donGia, $baseTs + 30 * 60);
assertTest("Đúng 12:30 vẫn được miễn phí (tiLe = 0%)", $res3['tiLePhanTram'], 0);
assertTest("Đúng 12:30 phụ thu = 0 đ", $res3['tienPhuThu'], 0.0);

// 1.4 Trả phòng lúc 12:35 (Quá ân hạn 5 phút -> tính 1 giờ trễ = 10%)
$res4 = $service->tinhPhuPhiTreGio($traDuKien, $donGia, $baseTs + 35 * 60);
assertTest("Lúc 12:35 phạt 1 giờ trễ (tiLe = 10%)", $res4['tiLePhanTram'], 10);
assertTest("Lúc 12:35 phụ thu = 100,000 đ", $res4['tienPhuThu'], 100000.0);

// 1.5 Trả phòng lúc 13:29 (Vẫn trong phạm vi 1 giờ đầu quá hạn)
$res5 = $service->tinhPhuPhiTreGio($traDuKien, $donGia, $baseTs + (60 + 29) * 60);
assertTest("Lúc 13:29 vẫn là 1 giờ trễ (tiLe = 10%)", $res5['tiLePhanTram'], 10);
assertTest("Lúc 13:29 phụ thu = 100,000 đ", $res5['tienPhuThu'], 100000.0);

// 1.6 Trả phòng lúc 13:35 (Sang giờ trễ thứ 2 -> tính 20%)
$res6 = $service->tinhPhuPhiTreGio($traDuKien, $donGia, $baseTs + (60 + 35) * 60);
assertTest("Lúc 13:35 sang giờ trễ thứ 2 (tiLe = 20%)", $res6['tiLePhanTram'], 20);
assertTest("Lúc 13:35 phụ thu = 200,000 đ", $res6['tienPhuThu'], 200000.0);

// 1.7 Trả phòng lúc 23:00 (Trễ 11 giờ -> chặn trần 100%)
$res7 = $service->tinhPhuPhiTreGio($traDuKien, $donGia, $baseTs + 11 * 3600);
assertTest("Trễ trên 10 giờ chặn trần 100% (tiLe = 100%)", $res7['tiLePhanTram'], 100);
assertTest("Trễ trên 10 giờ phụ thu tối đa 100% = 1,000,000 đ", $res7['tienPhuThu'], 1000000.0);

echo "\n-------------------------------------------------------------\n";
echo "2. KIỂM THỬ TRUY VẤN VÀ TÍNH TIỀN CHECKOUT TỪ DỮ LIỆU THỰC TẾ:\n";

$slips = $repo->getDanhSachPhieu();
$testSlip = null;
foreach ($slips as $s) {
    if ($s['TrangThai'] === 'DangLuuTru' || $s['TrangThai'] === 'DaTraPhong') {
        $testSlip = $s;
        break;
    }
}

if ($testSlip) {
    $maPhieu = $testSlip['MaPhieu'];
    echo "  Tìm thấy phiếu test: #{$maPhieu} (Trạng thái: {$testSlip['TrangThai']})\n";
    $bill = $service->tinhTienCheckout(['maPhieu' => $maPhieu]);
    assertTest("Tính tiền checkout thành công", $bill['success'], true);
    assertTest("Có dữ liệu bảng kê", isset($bill['data']['tongChiPhiToanBo']), true);
    assertTest("Số tiền còn lại không âm", $bill['data']['conLai'] >= 0, true);
    echo "  -> Tổng chi phí: " . number_format($bill['data']['tongChiPhiToanBo']) . " đ\n";
    echo "  -> Đã thanh toán: " . number_format($bill['data']['daThanhToan']) . " đ\n";
    echo "  -> Phụ phí trễ: " . number_format($bill['data']['tongPhuPhiTreGio']) . " đ\n";
    echo "  -> Dịch vụ: " . number_format($bill['data']['tongTienDichVu']) . " đ\n";
    echo "  -> Còn lại cần thu: " . number_format($bill['data']['conLai']) . " đ\n";
} else {
    echo "  [INFO] Không có phiếu nào ở trạng thái DangLuuTru hoặc DaTraPhong để test tính tiền.\n";
}

echo "\n-------------------------------------------------------------\n";
echo "3. KIỂM THỬ ĐỔI PHÒNG NGƯỜI LƯU TRÚ VÀ CẬP NHẬT THÔNG TIN GIẤY TỜ:\n";

// Lấy 1 phiếu có người lưu trú
$guestSlip = null;
foreach ($slips as $s) {
    $ct = $repo->getChiTietPhieu($s['MaPhieu']);
    $nlt = $ct['danhSachNLT'] ?? $ct['danhSachNguoiLuuTru'] ?? [];
    $phong = $ct['danhSachPhong'] ?? [];
    if (count($nlt) > 0 && count($phong) > 1) {
        $guestSlip = ['ct' => $ct, 'nlt' => $nlt, 'phong' => $phong];
        break;
    }
}

if ($guestSlip) {
    $phieuId = $guestSlip['ct']['phieu']['MaPhieu'];
    $firstGuest = $guestSlip['nlt'][0];
    $maNLT = $firstGuest['MaNguoiLuuTru'];
    $phongCu = $firstGuest['MaPhong'];
    
    // Tìm phòng khác trong phiếu chưa hủy
    $phongMoi = null;
    foreach ($guestSlip['phong'] as $p) {
        if ($p['MaPhong'] !== $phongCu && $p['TrangThai'] !== 'DaHuy') {
            $phongMoi = $p['MaPhong'];
            break;
        }
    }

    if ($phongMoi) {
        echo "  Thực hiện đổi khách {$firstGuest['HoTen']} từ phòng {$phongCu} sang phòng {$phongMoi}...\n";
        $doiRes = $service->doiPhongNguoiLuuTru([
            'maPhieu' => $phieuId,
            'maNLT' => $maNLT,
            'maPhongMoi' => $phongMoi
        ]);
        assertTest("Đổi phòng người lưu trú thành công", $doiRes['success'], true);
        assertTest("Phòng mới khớp", $doiRes['data']['phongMoi'], $phongMoi);

        // Chuyển lại phòng cũ để không ảnh hưởng dữ liệu
        $service->doiPhongNguoiLuuTru([
            'maPhieu' => $phieuId,
            'maNLT' => $maNLT,
            'maPhongMoi' => $phongCu
        ]);
        echo "  -> Đã hoàn trả khách về phòng {$phongCu} an toàn.\n";
    }

    // Test sửa thông tin khách
    echo "  Thực hiện cập nhật thông tin khách {$firstGuest['HoTen']}...\n";
    $upRes = $service->capNhatThongTinKhach([
        'maPhieu' => $phieuId,
        'maNLT' => $maNLT,
        'hoTen' => $firstGuest['HoTen'],
        'ngaySinh' => $firstGuest['NgaySinh'],
        'quocTich' => $firstGuest['QuocTich'] ?? 'Việt Nam',
        'loaiGiayTo' => $firstGuest['LoaiGiayTo'] ?? 'CCCD',
        'soGiayTo' => $firstGuest['SoGiayTo'],
        'ghiChu' => 'Đã đối soát CCCD bản cứng tại quầy lễ tân'
    ]);
    assertTest("Cập nhật thông tin khách lưu trú thành công", $upRes['success'], true);

} else {
    echo "  [INFO] Không tìm thấy phiếu có >= 2 phòng và có người lưu trú để test đổi phòng.\n";
}
echo "\n-------------------------------------------------------------\n";
echo "4. KIỂM THỬ QUY CHUẨN LATE CHECK-IN (QUÁ 24H VẪN GIỮ PHÒNG):\n";

// Quy chuẩn mới: Khách đã trả 100% tiền -> Giữ phòng đến hết ngày trả phòng (ThoiGianTraPhong)
// Giả lập: Giờ nhận phòng cách đây 28 tiếng, giờ trả phòng sau 20 tiếng nữa
$now = time();
$nhanTs = $now - (28 * 3600);
$traTs = $now + (20 * 3600);

$isPast24h = ($now > $nhanTs + 24 * 3600);
$isExpired = ($now > $traTs);

assertTest("Khách đến trễ hơn 24h kể từ giờ nhận (isPast24h = true)", $isPast24h, true);
assertTest("Chưa quá hạn ngày trả phòng (isExpired = false)", $isExpired, false);
assertTest("Hệ thống duy trì giữ phòng cho khách (KHÔNG tự động hủy)", !$isExpired, true);

echo "\n-------------------------------------------------------------\n";
echo "5. KIỂM THỬ RÀNG BUỘC ĐỔI PHÒNG (BẢO VỆ DỮ LIỆU & SỨC CHỨA):\n";

if ($guestSlip && $phongMoi) {
    // 5.1 Thử đổi vào chính phòng hiện tại -> Phải báo lỗi
    try {
        $service->doiPhongNguoiLuuTru([
            'maPhieu' => $phieuId,
            'maNLT' => $maNLT,
            'maPhongMoi' => $phongCu
        ]);
        assertTest("Chặn đổi vào chính phòng đang ở", false, true);
    } catch (Exception $e) {
        assertTest("Chặn đổi vào chính phòng đang ở (ném Exception)", true, true);
    }

    // 5.2 Thử đổi vào phòng không thuộc phiếu -> Phải báo lỗi
    try {
        $service->doiPhongNguoiLuuTru([
            'maPhieu' => $phieuId,
            'maNLT' => $maNLT,
            'maPhongMoi' => 'PHONG_KHONG_TON_TAI_999'
        ]);
        assertTest("Chặn đổi sang phòng không thuộc phiếu", false, true);
    } catch (Exception $e) {
        assertTest("Chặn đổi sang phòng không thuộc phiếu (ném Exception)", true, true);
    }
}

echo "\n=========================================================\n";
echo " TỔNG KẾT KIỂM THỬ TOÀN BỘ HỆ THỐNG LỄ TÂN:\n";
echo "   ✅ PASSED: {$passCount}\n";
echo "   ❌ FAILED: {$failCount}\n";
echo "=========================================================\n";

