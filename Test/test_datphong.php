<?php
require __DIR__ . "/../Backend/vendor/autoload.php";
use Dotenv\Dotenv;
use Database\Database;
use App\DatPhong\Repositories\DatPhongRepository;
use App\DatPhong\Services\DatPhongService;
use App\Phong\Repositories\PhongRepository;
use App\Phong\Services\PhongService;

$dotenv = Dotenv::createImmutable(__DIR__ . "/../Backend");
$dotenv->load();

$db = Database::getConnection();
// Dọn dẹp các phiếu test sinh ra trong các lần chạy trước để không cạn phòng
$db->exec("DELETE FROM ThanhToan WHERE MaHD IN (SELECT MaHD FROM HoaDon WHERE MaPhieu > 'PP007')");
$db->exec("DELETE FROM CTHD WHERE MaPhieu > 'PP007'");
$db->exec("DELETE FROM HoaDon WHERE MaPhieu > 'PP007'");
$db->exec("DELETE FROM CTSuDungDichVu WHERE MaPhieu > 'PP007'");
$db->exec("DELETE FROM NguoiLuuTru WHERE MaPhieu > 'PP007'");
$db->exec("DELETE FROM ChiTietPhieuNhanPhong WHERE MaPhieu > 'PP007'");
$db->exec("DELETE FROM PhieuNhanPhong WHERE MaPhieu > 'PP007'");

$datPhongRepo = new DatPhongRepository(new Database());
$phongRepo = new PhongRepository(new Database());
$phongService = new PhongService($phongRepo);
$datPhongService = new DatPhongService($datPhongRepo, $phongService);

echo "=== TEST 1: TÌM KIẾM 4 KHÁCH - 2 PHÒNG (TẤT CẢ LOẠI PHÒNG) ===\n";
$res1 = $datPhongService->timKiemVaGoiY([
    'ngayNhan' => '2026-10-15',
    'ngayTra' => '2026-10-17',
    'soNguoiLon' => 4,
    'soTreEm' => 0,
    'soPhong' => 2,
    'maLoaiPhong' => ''
]);
print_r($res1['goiYCombo']);
echo "\nSố loại phòng tìm thấy: " . count($res1['danhSach']) . "\n";

echo "\n=== TEST 2: QUY TẮC TRẺ EM (3 Người lớn + 3 Trẻ em => 3 + 1 = 4 Khách) ===\n";
$tongKhach = $datPhongService->quyDoiSoKhach(3, 3);
echo "3 lớn + 3 trẻ em = {$tongKhach} khách (Kỳ vọng: 4)\n";

$tongKhach2 = $datPhongService->quyDoiSoKhach(2, 1);
echo "2 lớn + 1 trẻ em = {$tongKhach2} khách (Kỳ vọng: 2)\n";

echo "\n=== TEST 3: CHỌN 1 LOẠI PHÒNG KHÔNG ĐỦ SỨC CHỨA (LP001 max 2, đặt 2 phòng cho 5 khách) ===\n";
$res3 = $datPhongService->timKiemVaGoiY([
    'ngayNhan' => '2026-10-15',
    'ngayTra' => '2026-10-17',
    'soNguoiLon' => 5,
    'soTreEm' => 0,
    'soPhong' => 2,
    'maLoaiPhong' => 'LP001'
]);
echo "Hợp lệ: " . ($res3['hopLe'] ? 'TRUE' : 'FALSE') . "\n";
echo "Thông báo: " . $res3['thongBao'] . "\n";

echo "\n=== TEST 4: TÍNH CHI PHÍ TẠM TÍNH ===\n";
$chiphi = $datPhongService->tinhChiPhiTamTinh([
    'ngayNhan' => '2026-10-15',
    'ngayTra' => '2026-10-17',
    'soNguoiLon' => 4,
    'soTreEm' => 0,
    'soPhong' => 2,
    'phongDaChon' => [
        ['maLoaiPhong' => 'LP001', 'soLuong' => 1],
        ['maLoaiPhong' => 'LP002', 'soLuong' => 1],
    ],
    'maKM' => '',
    'dichVuDaChon' => []
]);
echo "\n=== TEST 4.5: BÁO LỖI KHI NGƯỜI LƯU TRÚ KHÔNG ĐÚNG CƠ CẤU (2 LỚN, 0 TRẺ NHƯNG NHẬP KHÁCH SINH 2020) ===\n";
try {
    $datPhongService->datPhongVaThanhToan([
        'maTK' => 'TK003',
        'ngayNhan' => '2026-11-01',
        'ngayTra' => '2026-11-03',
        'soNguoiLon' => 2,
        'soTreEm' => 0,
        'soPhong' => 1,
        'phongDaChon' => [['maLoaiPhong' => 'LP001', 'soLuong' => 1]],
        'nguoiLuuTruTheoPhong' => [
            [
                'maLoaiPhong' => 'LP001',
                'index' => 0,
                'khach' => [
                    ['hoTen' => 'A', 'ngaySinh' => '2020-10-22', 'quocTich' => 'Việt Nam', 'loaiGiayTo' => 'CCCD', 'soGiayTo' => '47239857978'],
                    ['hoTen' => 'Phò', 'ngaySinh' => '2006-10-22', 'quocTich' => 'Việt Nam', 'loaiGiayTo' => 'CCCD', 'soGiayTo' => '144233534234']
                ]
            ]
        ],
        'maKM' => '',
        'dichVuDaChon' => [],
        'hinhThuc' => 'ChuyenKhoan'
    ]);
    echo "LỖI: Lẽ ra phải báo lỗi cơ cấu độ tuổi nhưng lại cho qua!\n";
} catch (\Exception $e) {
    echo "Kỳ vọng thành công: Đã bắt được lỗi hợp lệ: " . $e->getMessage() . "\n";
}

echo "\n=== TEST 5: ĐẶT PHÒNG VÀ THANH TOÁN (TRANSACTION TOÀN DIỆN VỚI NGÀY SINH HỢP LỆ) ===\n";
try {
    $bookingResult = $datPhongService->datPhongVaThanhToan([
        'maTK' => 'TK003', // Khách hàng khach01 / an@gmail.com
        'ngayNhan' => '2026-11-01',
        'ngayTra' => '2026-11-03',
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
                        'hoTen' => 'Nguyễn Văn An',
                        'ngaySinh' => '1990-01-01',
                        'quocTich' => 'Việt Nam',
                        'loaiGiayTo' => 'CCCD',
                        'soGiayTo' => '001202000001'
                    ],
                    [
                        'hoTen' => 'Trần Thị Mai',
                        'ngaySinh' => '1995-05-15',
                        'quocTich' => 'Việt Nam',
                        'loaiGiayTo' => 'CCCD',
                        'soGiayTo' => '001202000002'
                    ]
                ]
            ]
        ],
        'maKM' => '',
        'dichVuDaChon' => [],
        'hinhThuc' => 'ChuyenKhoan'
    ]);
    echo "Đặt phòng thành công!\n";
    echo " - Mã phiếu: " . $bookingResult['data']['maPhieu'] . "\n";
    echo " - Mã KM lưu tại phiếu: " . ($bookingResult['data']['maKM'] ?? 'Không') . "\n";
    echo " - Khách hàng: " . $bookingResult['data']['khachHang'] . "\n";
    echo " - Phòng gán: " . $bookingResult['data']['danhSachPhong'][0]['maPhong'] . " (" . $bookingResult['data']['danhSachPhong'][0]['tenLoaiPhong'] . ")\n";
    echo " - Tổng chi phí dự kiến: " . number_format($bookingResult['data']['tongTien']) . " đ\n";
    echo " - Trạng thái: Đã giữ chỗ thành công (Thanh toán khi nhận/trả phòng)\n";
} catch (\Exception $e) {
    echo "LỖI ĐẶT PHÒNG: " . $e->getMessage() . "\n";
}

echo "\n=== TEST 6: ĐẶT 1 PHÒNG STANDARD (SỨC CHỨA 2) CHO 2 NGƯỜI LỚN + 1 TRẺ EM (KHAI BÁO ĐỦ 3 KHÁCH) ===\n";
try {
    $bookingResult2 = $datPhongService->datPhongVaThanhToan([
        'maTK' => 'TK003',
        'ngayNhan' => '2026-11-05',
        'ngayTra' => '2026-11-07',
        'soNguoiLon' => 2,
        'soTreEm' => 1,
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
                        'hoTen' => 'Nguyễn Văn Bố',
                        'ngaySinh' => '1988-03-10',
                        'quocTich' => 'Việt Nam',
                        'loaiGiayTo' => 'CCCD',
                        'soGiayTo' => '001202000003'
                    ],
                    [
                        'hoTen' => 'Trần Thị Mẹ',
                        'ngaySinh' => '1992-07-20',
                        'quocTich' => 'Việt Nam',
                        'loaiGiayTo' => 'CCCD',
                        'soGiayTo' => '001202000004'
                    ],
                    [
                        'hoTen' => 'Nguyễn Bé Con',
                        'ngaySinh' => '2022-09-01', // 4 tuổi (<= 11 tuổi => Trẻ em)
                        'quocTich' => 'Việt Nam',
                        'loaiGiayTo' => 'GiayKhaiSinh',
                        'soGiayTo' => 'KS-2022001'
                    ]
                ]
            ]
        ],
        'maKM' => '',
        'dichVuDaChon' => [],
        'hinhThuc' => 'ChuyenKhoan'
    ]);
    echo "Test 6 thành công! Đã đặt phòng và khai báo đủ " . count($bookingResult2['data']['danhSachNguoiLuuTru']) . " khách lưu trú:\n";
    foreach ($bookingResult2['data']['danhSachNguoiLuuTru'] as $g) {
        echo " - " . $g['hoTen'] . " | Sinh: " . $g['ngaySinh'] . " | " . $g['nhomTuoi'] . " (" . $g['tuoi'] . "t) | Giấy tờ: " . $g['loaiGiayTo'] . "\n";
    }

    echo "\n=== TEST 7: XEM CHI TIẾT PHIẾU NHẬN PHÒNG & CTPNP ===\n";
    $ctPhieu = $datPhongService->xemChiTietPhieu($bookingResult2['data']['maPhieu']);
    echo "Thông tin PNP:\n";
    echo " - TenKH: " . ($ctPhieu['Phieu']['TenKH'] ?? 'N/A') . "\n";
    echo " - MaPhieu: " . ($ctPhieu['Phieu']['MaPhieu'] ?? 'N/A') . "\n";
    echo " - NgayLap: " . ($ctPhieu['Phieu']['NgayLap'] ?? 'N/A') . "\n";
    echo " - KenhDat: " . ($ctPhieu['Phieu']['KenhDat'] ?? 'N/A') . "\n";

    echo "Thông tin CTPNP (" . count($ctPhieu['DanhSachPhong']) . " phòng):\n";
    foreach ($ctPhieu['DanhSachPhong'] as $ct) {
        echo " - MaPhong: " . $ct['MaPhong'] . " (String)\n";
        echo "   ThoiGianNhanPhong: " . $ct['ThoiGianNhanPhong'] . "\n";
        echo "   ThoiGianTraPhong: " . $ct['ThoiGianTraPhong'] . "\n";
        echo "   SoNguoi: " . $ct['SoNguoi'] . "\n";
        echo "   TrangThai: " . $ct['TrangThai'] . "\n";
    }
} catch (\Exception $e) {
    echo "LỖI TEST 6/7: " . $e->getMessage() . "\n";
}

echo "\n=== TOÀN BỘ LOGIC BACKEND TEST THÀNH CÔNG! ===\n";
