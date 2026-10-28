<?php

require_once __DIR__ . '/../Backend/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend'))->load();
require_once __DIR__ . '/../Backend/database/Database.php';

use Database\Database;
use App\DatPhong\Repositories\DatPhongRepository;
use App\DatPhong\Services\DatPhongService;
use App\Phong\Repositories\PhongRepository;
use App\Phong\Services\PhongService;
use App\LeTan\Repositories\LeTanRepository;
use App\LeTan\Services\LeTanService;

echo "========================================================\n";
echo "TEST: EARLY CHECK-OUT & COUNTER BOOKING WITH CASH\n";
echo "========================================================\n\n";

$db = Database::getConnection();
$phongRepo = new PhongRepository(new Database());
$phongService = new PhongService($phongRepo);
$datPhongRepo = new DatPhongRepository(new Database());
$datPhongService = new DatPhongService($datPhongRepo, $phongService);
$leTanRepo = new LeTanRepository();
$leTanService = new LeTanService($leTanRepo);

// -----------------------------------------------------------
// TEST 1: ĐẶT PHÒNG TẠI QUẦY THANH TOÁN TIỀN MẶT (LỄ TÂN)
// -----------------------------------------------------------
echo "--- TEST 1: ĐẶT PHÒNG TẠI QUẦY THANH TOÁN TIỀN MẶT ---\n";
try {
    $res1 = $datPhongService->datPhongVaThanhToan([
        'maTK' => 'TK002', // Tài khoản lễ tân letan01 (LT001)
        'kenhDat' => 'TrucTiep',
        'maLeTan' => 'LT001',
        'ngayNhan' => '2026-11-20',
        'ngayTra' => '2026-11-23',
        'soNguoiLon' => 2,
        'soTreEm' => 0,
        'soPhong' => 1,
        'phongDaChon' => [
            ['maLoaiPhong' => 'LP001', 'soLuong' => 1]
        ],
        'nguoiLuuTruTheoPhong' => [
            [
                'maLoaiPhong' => 'LP001',
                'index' => 0,
                'khach' => [
                    [
                        'hoTen' => 'Khách Vãng Lai Test Tiền Mặt',
                        'ngaySinh' => '1995-05-15',
                        'quocTich' => 'Việt Nam',
                        'loaiGiayTo' => 'CCCD',
                        'soGiayTo' => '079095012345',
                        'ghiChu' => 'Đặt tại quầy thu tiền mặt'
                    ],
                    [
                        'hoTen' => 'Bạn Khách Vãng Lai',
                        'ngaySinh' => '1996-08-20',
                        'quocTich' => 'Việt Nam',
                        'loaiGiayTo' => 'CCCD',
                        'soGiayTo' => '079096054321',
                        'ghiChu' => null
                    ]
                ]
            ]
        ],
        'hinhThuc' => 'TienMat'
    ]);

    $maPhieu1 = $res1['data']['maPhieu'];
    echo "  [PASS] Tạo thành công phiếu đặt phòng tại quầy: #{$maPhieu1}\n";
    echo "  - Khách hàng: {$res1['data']['khachHang']}\n";
    echo "  - Kênh đặt: {$res1['data']['kenhDat']}\n";
    echo "  - Mã lễ tân: {$res1['data']['maLeTan']}\n";
    echo "  - Hình thức thanh toán: {$res1['data']['thanhToan']['hinhThuc']}\n";
    echo "  - Tổng tiền: " . number_format($res1['data']['tongTien']) . " đ\n";

    // Kiểm tra CSDL bảng PhieuNhanPhong và ThanhToan
    $stmtP = $db->prepare("SELECT * FROM PhieuNhanPhong WHERE MaPhieu = ?");
    $stmtP->execute([$maPhieu1]);
    $rowP = $stmtP->fetch(PDO::FETCH_ASSOC);
    assert($rowP['KenhDat'] === 'TrucTiep', 'KenhDat phải là TrucTiep');
    assert($rowP['MaLeTan'] === 'LT001', 'MaLeTan phải là LT001');
    assert($rowP['TrangThai'] === 'DaDatPhong', 'TrangThai phải là DaDatPhong');

    $stmtTT = $db->prepare("SELECT tt.* FROM ThanhToan tt JOIN HoaDon hd ON tt.MaHD = hd.MaHD WHERE hd.MaPhieu = ?");
    $stmtTT->execute([$maPhieu1]);
    $rowTT = $stmtTT->fetch(PDO::FETCH_ASSOC);
    assert($rowTT['HinhThuc'] === 'TienMat', 'HinhThuc thanh toán phải là TienMat');
    assert($rowTT['TrangThaiThanhToan'] === 'HoanTat', 'TrangThaiThanhToan phải là HoanTat');
    echo "  [PASS] CSDL xác nhận chính xác ràng buộc CHECK SQL cho KenhDat='TrucTiep' và HinhThuc='TienMat'!\n\n";

} catch (Exception $e) {
    echo "  [FAIL] Test 1: " . $e->getMessage() . "\n";
    exit(1);
}

// -----------------------------------------------------------
// TEST 2: ĐẶT PHÒNG TẠI QUẦY THANH TOÁN CHUYỂN KHOẢN QR
// -----------------------------------------------------------
echo "--- TEST 2: ĐẶT PHÒNG TẠI QUẦY THANH TOÁN CHUYỂN KHOẢN QR ---\n";
try {
    $res2 = $datPhongService->datPhongVaThanhToan([
        'maTK' => 'TK002', // Lễ tân
        'kenhDat' => 'TrucTiep',
        'maLeTan' => 'LT001',
        'ngayNhan' => '2026-11-25',
        'ngayTra' => '2026-11-27',
        'soNguoiLon' => 2,
        'soTreEm' => 0,
        'soPhong' => 1,
        'phongDaChon' => [
            ['maLoaiPhong' => 'LP002', 'soLuong' => 1]
        ],
        'nguoiLuuTruTheoPhong' => [
            [
                'maLoaiPhong' => 'LP002',
                'index' => 0,
                'khach' => [
                    [
                        'hoTen' => 'Khách Quầy Quét QR',
                        'ngaySinh' => '1990-01-01',
                        'quocTich' => 'Việt Nam',
                        'loaiGiayTo' => 'CCCD',
                        'soGiayTo' => '079090099887',
                        'ghiChu' => null
                    ],
                    [
                        'hoTen' => 'Khách Đi Cùng',
                        'ngaySinh' => '1992-02-02',
                        'quocTich' => 'Việt Nam',
                        'loaiGiayTo' => 'CCCD',
                        'soGiayTo' => '079092011223',
                        'ghiChu' => null
                    ]
                ]
            ]
        ],
        'hinhThuc' => 'ChuyenKhoan'
    ]);

    $maPhieu2 = $res2['data']['maPhieu'];
    echo "  [PASS] Tạo thành công phiếu quầy quét QR: #{$maPhieu2}\n";
    echo "  - Kênh đặt: {$res2['data']['kenhDat']}\n";
    echo "  - Hình thức thanh toán: {$res2['data']['thanhToan']['hinhThuc']}\n";
    echo "  - QR URL: " . substr($res2['data']['thanhToan']['qrUrl'], 0, 60) . "...\n";
    echo "  [PASS] Test 2 hoàn tất thành công!\n\n";

} catch (Exception $e) {
    echo "  [FAIL] Test 2: " . $e->getMessage() . "\n";
    exit(1);
}

// -----------------------------------------------------------
// TEST 3: CHECK-OUT SỚM (VÍ DỤ ĐẶT ĐẾN 20/10 NHƯNG CHECK-OUT 19/10)
// -----------------------------------------------------------
echo "--- TEST 3: KIỂM TRA CHECK-OUT SỚM (EARLY CHECK-OUT) ---\n";
try {
    // 3.1 Tạo phiếu đặt từ hôm qua đến ngày mai
    $maPhieu3 = 'TEST_EARLY_' . time();
    $maPhong3 = 'P110'; // Dùng phòng P110 sạch để test

    // Làm sạch toàn bộ dữ liệu của các lần test early trước nếu có
    $db->exec("DELETE FROM NguoiLuuTru WHERE MaPhieu LIKE 'TEST_EARLY_%'");
    $db->exec("DELETE FROM ThanhToan WHERE MaHD IN (SELECT MaHD FROM HoaDon WHERE MaPhieu LIKE 'TEST_EARLY_%')");
    $db->exec("DELETE FROM CTHD WHERE MaPhieu LIKE 'TEST_EARLY_%'");
    $db->exec("DELETE FROM HoaDon WHERE MaPhieu LIKE 'TEST_EARLY_%'");
    $db->exec("DELETE FROM ChiTietPhieuNhanPhong WHERE MaPhieu LIKE 'TEST_EARLY_%'");
    $db->exec("DELETE FROM PhieuNhanPhong WHERE MaPhieu LIKE 'TEST_EARLY_%'");

    $db->exec("
        INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH, MaLeTan)
        VALUES ('{$maPhieu3}', '2026-10-18', 'TrucTiep', 'DaDatPhong', 'KH001', 'LT001')
    ");

    $db->exec("
        INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai)
        VALUES ('{$maPhieu3}', '{$maPhong3}', '2026-10-18 14:00:00', '2026-10-20 12:00:00', 2, 'DaDatPhong')
    ");

    $maHD3 = 'HD_EARLY_' . time();
    $db->exec("
        INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT)
        VALUES ('{$maHD3}', '{$maPhieu3}', 1100000)
    ");

    $db->exec("
        INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD)
        VALUES ('TT_EARLY_" . time() . "', 1100000, '2026-10-18 14:05:00', 'TienMat', 'HoanTat', '{$maHD3}')
    ");

    echo "  - Phiếu #{$maPhieu3}: Khách đặt từ 18/10 đến 20/10 12:00:00 (Phòng {$maPhong3})\n";

    // 3.2 Khách đến nhận phòng (Check-in -> DangLuuTru)
    $maNLT3 = 'NLT_EAR_' . time();
    $db->exec("
        INSERT INTO NguoiLuuTru (MaNguoiLuuTru, HoTen, NgaySinh, QuocTich, LoaiGiayTo, SoGiayTo, MaPhieu, MaPhong)
        VALUES ('{$maNLT3}', 'Khách Checkin', '1990-01-01', 'Việt Nam', 'CCCD', '079090000001', '{$maPhieu3}', '{$maPhong3}')
    ");

    $checkinRes = $leTanService->checkin([
        'maPhieu' => $maPhieu3,
        'maPhong' => $maPhong3,
        'maLeTan' => 'LT001',
        'nguoiLuuTru' => [
            [
                'hoTen' => 'Khách Checkin',
                'ngaySinh' => '1990-01-01',
                'quocTich' => 'Việt Nam',
                'loaiGiayTo' => 'CCCD',
                'soGiayTo' => '079090000001'
            ]
        ]
    ]);
    echo "  - Khách nhận phòng thành công: " . $checkinRes['message'] . "\n";

    // 3.3 Khách trả phòng sớm (Check-out vào 19/10, trước hạn 20/10)
    echo "  - Tiến hành Check-out sớm...\n";
    $checkoutRes = $leTanService->checkout([
        'maPhieu' => $maPhieu3,
        'maPhong' => $maPhong3,
        'maLeTan' => 'LT001',
        'hinhThuc' => 'TienMat'
    ]);
    echo "  [PASS] Check-out sớm thành công: " . $checkoutRes['message'] . "\n";

    // 3.4 Kiểm tra CSDL: ThoiGianTraPhong đã cập nhật về thời gian thực tế
    $stmtCheckCT = $db->prepare("SELECT ThoiGianNhanPhong, ThoiGianTraPhong, TrangThai FROM ChiTietPhieuNhanPhong WHERE MaPhieu = ? AND MaPhong = ?");
    $stmtCheckCT->execute([$maPhieu3, $maPhong3]);
    $ctRow = $stmtCheckCT->fetch(PDO::FETCH_ASSOC);

    echo "  - Trạng thái phòng chi tiết: {$ctRow['TrangThai']}\n";
    echo "  - Thời gian nhận ban đầu: {$ctRow['ThoiGianNhanPhong']}\n";
    echo "  - Thời gian trả sau khi check-out: {$ctRow['ThoiGianTraPhong']}\n";

    assert($ctRow['TrangThai'] === 'DaTraPhong', 'Trạng thái phòng phải là DaTraPhong');
    assert($ctRow['ThoiGianTraPhong'] < '2026-10-20 12:00:00', 'ThoiGianTraPhong phải được cập nhật sớm hơn 20/10!');

    // 3.5 Kiểm tra phòng có sẵn sàng cho khách khác đặt đêm 19/10 - 20/10 không
    $availRooms = $phongRepo->getAvailableRoomsByLoai('LP001', '2026-10-19', '2026-10-20');
    echo "  - Danh sách phòng trống LP001: " . implode(', ', $availRooms) . "\n";
    $pCheck = $db->query("SELECT * FROM Phong WHERE MaPhong = '{$maPhong3}'")->fetch(PDO::FETCH_ASSOC);
    echo "  - Bản ghi trong bảng Phong: " . json_encode($pCheck) . "\n";
    $ctCheck = $db->query("SELECT * FROM ChiTietPhieuNhanPhong WHERE MaPhong = '{$maPhong3}'")->fetchAll(PDO::FETCH_ASSOC);
    echo "  - ChiTietPhieuNhanPhong của {$maPhong3}: " . json_encode($ctCheck) . "\n";
    $isP110Available = in_array($maPhong3, $availRooms, true);
    echo "  - Phòng {$maPhong3} có xuất hiện trong danh sách phòng trống đêm 19/10->20/10 không? " . ($isP110Available ? "CÓ (ĐÃ ĐƯỢC GIẢI PHÓNG NGAY LẬP TỨC)" : "KHÔNG") . "\n";
    assert($isP110Available, "Phòng {$maPhong3} phải được giải phóng và có thể đặt cho đêm 19/10!");

    echo "  [PASS] Test 3 hoàn tất xuất sắc!\n\n";

} catch (Exception $e) {
    echo "  [FAIL] Test 3: " . $e->getMessage() . "\n";
    exit(1);
}

echo "========================================================\n";
echo "TẤT CẢ CÁC BÀI TEST ĐÃ HOÀN TẤT THÀNH CÔNG 100%!\n";
echo "========================================================\n";
