<?php
require __DIR__ . "/../Backend/vendor/autoload.php";

use Dotenv\Dotenv;
use Database\Database;
use App\DatPhong\Repositories\DatPhongRepository;
use App\DatPhong\Services\DatPhongService;
use App\Phong\Repositories\PhongRepository;
use App\Phong\Services\PhongService;
use App\ThanhToan\Repositories\ThanhToanRepository;
use App\ThanhToan\Services\ThanhToanService;
use App\LeTan\Repositories\LeTanRepository;

$dotenv = Dotenv::createImmutable(__DIR__ . "/../Backend");
$dotenv->load();

$db = Database::getConnection();

echo "====================================================================\n";
echo " KIỂM TRA LUỒNG ĐẶT PHÒNG: CHỜ THANH TOÁN (20 PHÚT) VÀ TỰ ĐỘNG HỦY\n";
echo "====================================================================\n\n";

$datPhongRepo = new DatPhongRepository(new Database());
$phongRepo = new PhongRepository(new Database());
$phongService = new PhongService($phongRepo);
$datPhongService = new DatPhongService($datPhongRepo, $phongService);
$thanhToanRepo = new ThanhToanRepository(new Database());
$thanhToanService = new ThanhToanService($thanhToanRepo);
$leTanRepo = new LeTanRepository();

// Chọn phòng trống khả dụng để test
$stmt = $db->query("SELECT MaPhong, MaLoaiPhong FROM Phong WHERE TrangThaiPhong = 'Trong' ORDER BY MaPhong ASC LIMIT 2");
$testRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($testRooms) < 2) {
    die("LỖI: Cần ít nhất 2 phòng Trống để chạy bộ test này!\n");
}

$room1 = $testRooms[0]['MaPhong'];
$loaiPhong1 = $testRooms[0]['MaLoaiPhong'];
$room2 = $testRooms[1]['MaPhong'];
$loaiPhong2 = $testRooms[1]['MaLoaiPhong'];

$stmtKh = $db->query("SELECT kh.MaKH, kh.MaTK FROM KhachHang kh WHERE kh.MaTK IS NOT NULL LIMIT 1");
$khRow = $stmtKh->fetch(PDO::FETCH_ASSOC);
$testMaTK = $khRow['MaTK'];
$testMaKH = $khRow['MaKH'];

echo "[SETUP] Phòng test: {$room1} ({$loaiPhong1}) và {$room2} ({$loaiPhong2})\n";
echo "[SETUP] Tài khoản test: {$testMaTK} ({$testMaKH})\n\n";

// ====================================================================
// TEST 1: ĐẶT PHÒNG ONLINE (CHUYỂN KHOẢN QR) -> CHỜ THANH TOÁN 20 PHÚT
// ====================================================================
echo "--- TEST 1: Đặt phòng trực tuyến (ChuyenKhoan) đầy đủ người lưu trú ---\n";

$bookingParams1 = [
    'maTK' => $testMaTK,
    'ngayNhan' => date('Y-m-d', strtotime('+3 days')),
    'ngayTra' => date('Y-m-d', strtotime('+5 days')),
    'soNguoiLon' => 2,
    'soTreEm' => 0,
    'soPhong' => 1,
    'phongDaChon' => [
        ['maLoaiPhong' => $loaiPhong1, 'soLuong' => 1]
    ],
    'nguoiLuuTruTheoPhong' => [
        [
            'phongSo' => 1,
            'khach' => [
                [
                    'hoTen' => 'Nguyễn Văn Test 1',
                    'ngaySinh' => '1995-05-15',
                    'quocTich' => 'Việt Nam',
                    'loaiGiayTo' => 'CCCD',
                    'soGiayTo' => '079095001111',
                    'ghiChu' => 'Khách chính'
                ],
                [
                    'hoTen' => 'Trần Thị Test 2',
                    'ngaySinh' => '1997-08-20',
                    'quocTich' => 'Việt Nam',
                    'loaiGiayTo' => 'CCCD',
                    'soGiayTo' => '079097002222',
                    'ghiChu' => 'Người đi cùng'
                ]
            ]
        ]
    ],
    'hinhThuc' => 'ChuyenKhoan',
    'kenhDat' => 'TrucTuyen'
];

$res1 = $datPhongService->datPhongVaThanhToan($bookingParams1);

$maPhieu1 = $res1['maPhieu'];
$maHD1 = $res1['maHD'];

echo "=> Tạo thành công Phiếu: {$maPhieu1}, Hóa đơn: {$maHD1}\n";

// Kiểm tra trạng thái trong DB
$stmtCheck = $db->prepare("SELECT TrangThai, ThoiGianHetHan FROM PhieuNhanPhong WHERE MaPhieu = ?");
$stmtCheck->execute([$maPhieu1]);
$phieuRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

echo " - Trạng thái Phiếu: {$phieuRow['TrangThai']} (Kỳ vọng: ChoThanhToan)\n";
echo " - Thời gian hết hạn: {$phieuRow['ThoiGianHetHan']}\n";
assert($phieuRow['TrangThai'] === 'ChoThanhToan', "LỖI: Trạng thái phiếu không phải ChoThanhToan");
assert(!empty($phieuRow['ThoiGianHetHan']), "LỖI: ThoiGianHetHan bị rỗng");

// Kiểm tra chênh lệch thời gian hết hạn (~20 phút)
$diffSec = strtotime($phieuRow['ThoiGianHetHan']) - time();
echo " - Thời gian còn lại: {$diffSec}s (~20 phút = 1200s)\n";
assert($diffSec >= 1150 && $diffSec <= 1250, "LỖI: ThoiGianHetHan không phải ~1200 giây");

// Kiểm tra trạng thái ChiTietPhieuNhanPhong
$stmtCtp = $db->prepare("SELECT MaPhong, TrangThai FROM ChiTietPhieuNhanPhong WHERE MaPhieu = ?");
$stmtCtp->execute([$maPhieu1]);
$ctpList = $stmtCtp->fetchAll(PDO::FETCH_ASSOC);
$bookedRoom1 = $ctpList[0]['MaPhong'];
echo " - Phòng được gán: {$bookedRoom1}, Trạng thái chi tiết: {$ctpList[0]['TrangThai']} (Kỳ vọng: ChoThanhToan)\n";
assert($ctpList[0]['TrangThai'] === 'ChoThanhToan', "LỖI: Trạng thái chi tiết không phải ChoThanhToan");

// Kiểm tra trạng thái bảng Phong
$stmtP = $db->prepare("SELECT TrangThaiPhong FROM Phong WHERE MaPhong = ?");
$stmtP->execute([$bookedRoom1]);
$roomStatus = $stmtP->fetchColumn();
echo " - Trạng thái phòng {$bookedRoom1} trong bảng Phong: {$roomStatus} (Kỳ vọng: ChoThanhToan)\n";
assert($roomStatus === 'ChoThanhToan', "LỖI: Trạng thái phòng không phải ChoThanhToan");

// Kiểm tra bảng ThanhToan
$stmtTT = $db->prepare("SELECT TrangThaiThanhToan, HinhThuc FROM ThanhToan WHERE MaHD = ?");
$stmtTT->execute([$maHD1]);
$ttRow = $stmtTT->fetch(PDO::FETCH_ASSOC);
echo " - Bản ghi ThanhToan: Phương thức: {$ttRow['HinhThuc']}, Trạng thái: {$ttRow['TrangThaiThanhToan']} (Kỳ vọng: ChoThanhToan)\n";
assert($ttRow['TrangThaiThanhToan'] === 'ChoThanhToan', "LỖI: ThanhToan không phải ChoThanhToan");

// Kiểm tra KPI quầy lễ tân
$soDoData = $leTanRepo->getThongKeLoaiPhongVaPhong();
echo " - KPI Quầy Lễ Tân: Số phòng giữ chỗ (ChoThanhToan): {$soDoData['kpi']['soPhongChoThanhToan']}\n";
assert($soDoData['kpi']['soPhongChoThanhToan'] >= 1, "LỖI: KPI Lễ tân không đếm phòng ChoThanhToan");

echo "=> TEST 1 PASSED!\n\n";

// ====================================================================
// TEST 2: KHÁCH THANH TOÁN THÀNH CÔNG TRONG 20 PHÚT
// ====================================================================
echo "--- TEST 2: Khách hàng thanh toán thành công trong 20 phút ---\n";

// Khách hoàn tất thanh toán (Mô phỏng VietQR thành công qua xuLyWebhook)
$payRes = $thanhToanService->xuLyWebhook([
    'content' => "THANHTOAN " . $maHD1,
    'transferAmount' => 999999999
]);
echo " - Kết quả mô phỏng thanh toán: " . ($payRes['success'] ? 'SUCCESS' : 'FAILED') . "\n";
assert($payRes['success'] === true, "LỖI: Thanh toán không thành công: " . ($payRes['error'] ?? ''));

// Kiểm tra lại trạng thái phiếu
$stmtCheck->execute([$maPhieu1]);
$phieuRowAfter = $stmtCheck->fetch(PDO::FETCH_ASSOC);
echo " - Trạng thái Phiếu sau thanh toán: {$phieuRowAfter['TrangThai']} (Kỳ vọng: DaDatPhong)\n";
assert($phieuRowAfter['TrangThai'] === 'DaDatPhong', "LỖI: Phiếu không chuyển sang DaDatPhong");

// Kiểm tra trạng thái phòng
$stmtP->execute([$bookedRoom1]);
$roomStatusAfter = $stmtP->fetchColumn();
echo " - Trạng thái phòng {$bookedRoom1}: {$roomStatusAfter} (Kỳ vọng: DaDat)\n";
assert($roomStatusAfter === 'DaDat', "LỖI: Phòng không chuyển sang DaDat");

// Kiểm tra trạng thái ChiTietPhieuNhanPhong
$stmtCtp->execute([$maPhieu1]);
$ctpAfter = $stmtCtp->fetchAll(PDO::FETCH_ASSOC);
echo " - Trạng thái chi tiết: {$ctpAfter[0]['TrangThai']} (Kỳ vọng: DaDatPhong)\n";
assert($ctpAfter[0]['TrangThai'] === 'DaDatPhong', "LỖI: Chi tiết phiếu không chuyển sang DaDatPhong");

echo "=> TEST 2 PASSED!\n\n";

// ====================================================================
// TEST 3: QUÁ 20 PHÚT KHÔNG THANH TOÁN -> HỦY PHIẾU, NHẢ LẠI PHÒNG TRỐNG
// ====================================================================
echo "--- TEST 3: Đặt phòng và quá hạn 20 phút không thanh toán ---\n";

$bookingParams2 = [
    'maTK' => $testMaTK,
    'ngayNhan' => date('Y-m-d', strtotime('+10 days')),
    'ngayTra' => date('Y-m-d', strtotime('+12 days')),
    'soNguoiLon' => 1,
    'soTreEm' => 0,
    'soPhong' => 1,
    'phongDaChon' => [
        ['maLoaiPhong' => $loaiPhong2, 'soLuong' => 1]
    ],
    'nguoiLuuTruTheoPhong' => [
        [
            'phongSo' => 1,
            'khach' => [
                [
                    'hoTen' => 'Lê Timeout Test',
                    'ngaySinh' => '1990-01-01',
                    'quocTich' => 'Việt Nam',
                    'loaiGiayTo' => 'CCCD',
                    'soGiayTo' => '079090003333'
                ]
            ]
        ]
    ],
    'hinhThuc' => 'ChuyenKhoan',
    'kenhDat' => 'TrucTuyen'
];

$res2 = $datPhongService->datPhongVaThanhToan($bookingParams2);

$maPhieu2 = $res2['maPhieu'];
$maHD2 = $res2['maHD'];

$stmtCtp->execute([$maPhieu2]);
$ctpList2 = $stmtCtp->fetchAll(PDO::FETCH_ASSOC);
$bookedRoom2 = $ctpList2[0]['MaPhong'];

echo "=> Đã tạo Phiếu: {$maPhieu2}, Phòng tạm giữ: {$bookedRoom2} (Trạng thái: ChoThanhToan)\n";

// Mô phỏng quá hạn 20 phút: chỉnh ThoiGianHetHan về 5 phút trước
$db->prepare("UPDATE PhieuNhanPhong SET ThoiGianHetHan = DATE_SUB(NOW(), INTERVAL 5 MINUTE) WHERE MaPhieu = ?")
   ->execute([$maPhieu2]);

echo " - Đã mô phỏng: Chỉnh ThoiGianHetHan về 5 phút trước (quá hạn > 20p)\n";

// Kích hoạt quét dọn tự động lazy cleanup (như khi khách tìm kiếm hoặc lễ tân mở trang)
$expiredCount = $thanhToanService->quetVaXuLyTatCaPhieuHetHan();
echo " - Số phiếu hết hạn được giải phóng: {$expiredCount} (Kỳ vọng: >= 1)\n";
assert($expiredCount >= 1, "LỖI: quetVaXuLyTatCaPhieuHetHan không phát hiện phiếu quá hạn");

// Kiểm tra trạng thái phiếu sau khi hết hạn
$stmtCheck->execute([$maPhieu2]);
$phieu2Row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
echo " - Trạng thái Phiếu sau khi hết hạn: {$phieu2Row['TrangThai']} (Kỳ vọng: ThanhToanThatBai)\n";
assert($phieu2Row['TrangThai'] === 'ThanhToanThatBai', "LỖI: Phiếu không chuyển sang ThanhToanThatBai");

// Kiểm tra trạng thái phòng: PHẢI NHẢ VỀ 'Trong'
$stmtP->execute([$bookedRoom2]);
$room2Status = $stmtP->fetchColumn();
echo " - Trạng thái phòng {$bookedRoom2}: {$room2Status} (Kỳ vọng: Trong)\n";
assert($room2Status === 'Trong', "LỖI: Phòng chưa được nhả về trạng thái Trong!");

// Kiểm tra trạng thái chi tiết phiếu
$stmtCtp->execute([$maPhieu2]);
$ctp2Row = $stmtCtp->fetchAll(PDO::FETCH_ASSOC);
echo " - Trạng thái ChiTietPhieuNhanPhong: {$ctp2Row[0]['TrangThai']} (Kỳ vọng: ThanhToanThatBai)\n";
assert($ctp2Row[0]['TrangThai'] === 'ThanhToanThatBai', "LỖI: Chi tiết phiếu không phải ThanhToanThatBai");

// Kiểm tra trạng thái thanh toán: DaHetHan
$stmtTT->execute([$maHD2]);
$tt2Row = $stmtTT->fetch(PDO::FETCH_ASSOC);
echo " - Trạng thái bản ghi ThanhToan: {$tt2Row['TrangThaiThanhToan']} (Kỳ vọng: DaHetHan)\n";
assert($tt2Row['TrangThaiThanhToan'] === 'DaHetHan', "LỖI: Bản ghi thanh toán không phải DaHetHan");

echo "=> TEST 3 PASSED!\n\n";

// ====================================================================
// TEST 4: ĐẶT PHÒNG TẠI QUẦY (TIỀN MẶT) -> TRỰC TIẾP DaDatPhong
// ====================================================================
echo "--- TEST 4: Đặt phòng tại quầy (TienMat) không dùng ChoThanhToan ---\n";

$bookingParams4 = [
    'maTK' => 'TK001',
    'kenhDat' => 'TrucTiep',
    'maLeTan' => 'LT001',
    'hinhThuc' => 'TienMat',
    'ngayNhan' => date('Y-m-d', strtotime('+15 days')),
    'ngayTra' => date('Y-m-d', strtotime('+17 days')),
    'soNguoiLon' => 1,
    'soTreEm' => 0,
    'soPhong' => 1,
    'phongDaChon' => [
        ['maLoaiPhong' => $loaiPhong2, 'soLuong' => 1]
    ],
    'nguoiLuuTruTheoPhong' => [
        [
            'phongSo' => 1,
            'khach' => [
                [
                    'hoTen' => 'Khách Quầy Tiền Mặt',
                    'ngaySinh' => '1988-12-12',
                    'quocTich' => 'Việt Nam',
                    'loaiGiayTo' => 'CCCD',
                    'soGiayTo' => '079088004444'
                ]
            ]
        ]
    ]
];

$res4 = $datPhongService->datPhongVaThanhToan($bookingParams4);

$maPhieu4 = $res4['maPhieu'];
$maHD4 = $res4['maHD'];

$stmtCheck->execute([$maPhieu4]);
$phieu4Row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
echo " - Trạng thái Phiếu tại quầy: {$phieu4Row['TrangThai']} (Kỳ vọng: DaDatPhong)\n";
assert($phieu4Row['TrangThai'] === 'DaDatPhong', "LỖI: Phiếu tại quầy không phải DaDatPhong");

$stmtCtp->execute([$maPhieu4]);
$ctp4List = $stmtCtp->fetchAll(PDO::FETCH_ASSOC);
$room4 = $ctp4List[0]['MaPhong'];
echo " - Trạng thái phòng {$room4}: ";
$stmtP->execute([$room4]);
$room4Status = $stmtP->fetchColumn();
echo "{$room4Status} (Kỳ vọng: DaDat)\n";
assert($room4Status === 'DaDat', "LỖI: Phòng tại quầy không phải DaDat");

$stmtTT->execute([$maHD4]);
$tt4Row = $stmtTT->fetch(PDO::FETCH_ASSOC);
echo " - Trạng thái ThanhToan tiền mặt: {$tt4Row['TrangThaiThanhToan']} (Kỳ vọng: HoanTat)\n";
assert($tt4Row['TrangThaiThanhToan'] === 'HoanTat', "LỖI: Thanh toán tiền mặt không phải HoanTat");

echo "=> TEST 4 PASSED!\n\n";

// Dọn dẹp dữ liệu test
$db->exec("DELETE FROM ThanhToan WHERE MaHD IN ('{$maHD1}', '{$maHD2}', '{$maHD4}')");
$db->exec("DELETE FROM CTHD WHERE MaPhieu IN ('{$maPhieu1}', '{$maPhieu2}', '{$maPhieu4}')");
$db->exec("DELETE FROM HoaDon WHERE MaHD IN ('{$maHD1}', '{$maHD2}', '{$maHD4}')");
$db->exec("DELETE FROM NguoiLuuTru WHERE MaPhieu IN ('{$maPhieu1}', '{$maPhieu2}', '{$maPhieu4}')");
$db->exec("DELETE FROM ChiTietPhieuNhanPhong WHERE MaPhieu IN ('{$maPhieu1}', '{$maPhieu2}', '{$maPhieu4}')");
$db->exec("DELETE FROM PhieuNhanPhong WHERE MaPhieu IN ('{$maPhieu1}', '{$maPhieu2}', '{$maPhieu4}')");
$db->exec("UPDATE Phong SET TrangThaiPhong = 'Trong' WHERE MaPhong IN ('{$bookedRoom1}', '{$bookedRoom2}', '{$room4}')");

echo "====================================================================\n";
echo " TẤT CẢ 4 KỊCH BẢN TEST ĐÃ CHẠY THÀNH CÔNG 100%!\n";
echo "====================================================================\n";
