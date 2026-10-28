<?php

require __DIR__ . '/../Backend/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend'))->load();

use Database\Database;
use App\DatPhong\Repositories\DatPhongRepository;
use App\DatPhong\Services\DatPhongService;
use App\LeTan\Repositories\LeTanRepository;
use App\LeTan\Services\LeTanService;

echo "========================================================\n";
echo "TEST: MULTI-RECEPTIONIST WORKFLOW (LỰA CHỌN B)\n";
echo "========================================================\n\n";

$db = Database::getConnection();

$datPhongRepo = new DatPhongRepository();
$phongRepo = new \App\Phong\Repositories\PhongRepository(new Database());
$phongService = new \App\Phong\Services\PhongService($phongRepo);
$datPhongService = new DatPhongService($datPhongRepo, $phongService);
$leTanService = new LeTanService();
$leTanRepo = new LeTanRepository();

// ----------------------------------------------------
// BƯỚC 1: LỄ TÂN 1 (LT001 - Pham Thi Hoa) TẠO PHIẾU TẠI QUẦY
// ----------------------------------------------------
echo "[BƯỚC 1] Lễ tân LT001 (Pham Thi Hoa) tạo phiếu đặt phòng tại quầy cho 2 phòng LP001...\n";
$bookingParams = [
    'maTK' => 'TK002', // Tài khoản của LT001 (Pham Thi Hoa)
    'kenhDat' => 'TrucTiep',
    'maLeTan' => 'LT001',
    'ngayNhan' => date('Y-m-d H:i:s'),
    'ngayTra' => date('Y-m-d 12:00:00', strtotime('+2 days')),
    'soNguoiLon' => 3,
    'soTreEm' => 0,
    'soPhong' => 2,
    'phongDaChon' => [
        ['maLoaiPhong' => 'LP001', 'soLuong' => 2]
    ],
    'nguoiLuuTruTheoPhong' => [
        [
            'maLoaiPhong' => 'LP001',
            'index' => 0,
            'khach' => [
                [
                    'hoTen' => 'Nguyễn Văn Test A',
                    'ngaySinh' => '1990-01-01',
                    'quocTich' => 'Việt Nam',
                    'loaiGiayTo' => 'CCCD',
                    'soGiayTo' => '001200000001',
                    'ghiChu' => 'Khách VIP'
                ],
                [
                    'hoTen' => 'Nguyễn Thị Test B',
                    'ngaySinh' => '1992-02-02',
                    'quocTich' => 'Việt Nam',
                    'loaiGiayTo' => 'CCCD',
                    'soGiayTo' => '001200000002',
                    'ghiChu' => ''
                ]
            ]
        ],
        [
            'maLoaiPhong' => 'LP001',
            'index' => 1,
            'khach' => [
                [
                    'hoTen' => 'Trần Văn Test C',
                    'ngaySinh' => '1995-05-05',
                    'quocTich' => 'Việt Nam',
                    'loaiGiayTo' => 'CCCD',
                    'soGiayTo' => '001200000003',
                    'ghiChu' => ''
                ]
            ]
        ]
    ],
    'hinhThuc' => 'TienMat'
];

$resDat = $datPhongService->datPhongVaThanhToan($bookingParams);
if (!$resDat['success']) {
    die("❌ Lỗi tạo phiếu: " . ($resDat['message'] ?? 'Không rõ') . "\n");
}

$maPhieu = $resDat['data']['maPhieu'];
echo "✅ Tạo thành công phiếu đặt phòng tại quầy: #$maPhieu\n";
echo "   - Khách đại diện: {$resDat['data']['khachHang']}\n";
echo "   - Kênh đặt: {$resDat['data']['kenhDat']}\n";
echo "   - Mã Lễ tân lập phiếu: {$resDat['data']['maLeTan']}\n";

// Lấy danh sách phòng được hệ thống phân bổ vào ChiTietPhieuNhanPhong
$stmtRooms = $db->prepare("SELECT MaPhong FROM ChiTietPhieuNhanPhong WHERE MaPhieu = ? ORDER BY MaPhong ASC");
$stmtRooms->execute([$maPhieu]);
$assignedRooms = $stmtRooms->fetchAll(PDO::FETCH_COLUMN);

echo "   - Phòng phân bổ: " . implode(', ', $assignedRooms) . "\n";
assert(count($assignedRooms) === 2, "Phải có đúng 2 phòng được gán");
$room1 = $assignedRooms[0];
$room2 = $assignedRooms[1];

// Kiểm tra trong DB xem PhieuNhanPhong.MaLeTan có là LT001 không
$stmtCheck = $db->prepare("SELECT MaPhieu, KenhDat, TrangThai, MaLeTan FROM PhieuNhanPhong WHERE MaPhieu = ?");
$stmtCheck->execute([$maPhieu]);
$pnp = $stmtCheck->fetch(PDO::FETCH_ASSOC);
echo "   -> PhieuNhanPhong: KenhDat={$pnp['KenhDat']}, TrangThai={$pnp['TrangThai']}, MaLeTan={$pnp['MaLeTan']}\n";
assert($pnp['MaLeTan'] === 'LT001', "MaLeTan của phiếu phải là LT001");
echo "   ✓ Xác nhận: Lễ tân tạo phiếu là LT001!\n\n";

// ----------------------------------------------------
// BƯỚC 2: LỄ TÂN 2 (LT002 - Nguyen Thi Lan) CHECK-IN PHÒNG 1 ($room1)
// ----------------------------------------------------
echo "[BƯỚC 2] Lễ tân LT002 (Nguyen Thi Lan) thực hiện Check-in riêng lẻ cho phòng $room1...\n";
$resCheckin1 = $leTanService->checkin([
    'maPhieu' => $maPhieu,
    'maPhong' => $room1,
    'maLeTan' => 'LT002' // Lễ tân khác trực ca
]);
echo "✅ " . $resCheckin1['message'] . "\n";

// Kiểm tra DB sau checkin phòng 1
$stmtCT1 = $db->prepare("SELECT MaPhong, TrangThai, MaLeTanCheckin, MaLeTanCheckout FROM ChiTietPhieuNhanPhong WHERE MaPhieu = ? AND MaPhong = ?");
$stmtCT1->execute([$maPhieu, $room1]);
$ct1 = $stmtCT1->fetch(PDO::FETCH_ASSOC);

$stmtCT2 = $db->prepare("SELECT MaPhong, TrangThai, MaLeTanCheckin, MaLeTanCheckout FROM ChiTietPhieuNhanPhong WHERE MaPhieu = ? AND MaPhong = ?");
$stmtCT2->execute([$maPhieu, $room2]);
$ct2 = $stmtCT2->fetch(PDO::FETCH_ASSOC);

$stmtCheck->execute([$maPhieu]);
$pnp = $stmtCheck->fetch(PDO::FETCH_ASSOC);

echo "   -> Phòng $room1: TrangThai={$ct1['TrangThai']}, MaLeTanCheckin={$ct1['MaLeTanCheckin']}\n";
echo "   -> Phòng $room2: TrangThai={$ct2['TrangThai']}, MaLeTanCheckin=" . ($ct2['MaLeTanCheckin'] ?: 'NULL') . "\n";
echo "   -> PhieuNhanPhong: TrangThai={$pnp['TrangThai']}, MaLeTan={$pnp['MaLeTan']}\n";
assert($ct1['MaLeTanCheckin'] === 'LT002', "MaLeTanCheckin của phòng $room1 phải là LT002");
assert($ct2['MaLeTanCheckin'] === null, "MaLeTanCheckin của phòng $room2 phải là NULL (chưa checkin)");
assert($pnp['MaLeTan'] === 'LT001', "MaLeTan của PhieuNhanPhong TUYỆT ĐỐI không bị ghi đè, vẫn là LT001");
echo "   ✓ Xác nhận: Phòng $room1 đã checkin bởi LT002, phiếu vẫn giữ nguyên MaLeTan là LT001!\n\n";

// ----------------------------------------------------
// BƯỚC 3: LỄ TÂN 2 (LT002) CHECK-IN TIẾP PHÒNG 2 ($room2)
// ----------------------------------------------------
echo "[BƯỚC 3] Lễ tân LT002 tiếp tục Check-in cho phòng $room2...\n";
$resCheckin2 = $leTanService->checkin([
    'maPhieu' => $maPhieu,
    'maPhong' => $room2,
    'maLeTan' => 'LT002'
]);
echo "✅ " . $resCheckin2['message'] . "\n";

$stmtCT2->execute([$maPhieu, $room2]);
$ct2 = $stmtCT2->fetch(PDO::FETCH_ASSOC);
echo "   -> Phòng $room2: TrangThai={$ct2['TrangThai']}, MaLeTanCheckin={$ct2['MaLeTanCheckin']}\n";
assert($ct2['MaLeTanCheckin'] === 'LT002', "MaLeTanCheckin của phòng $room2 phải là LT002");
echo "   ✓ Xác nhận: Cả 2 phòng đều đã checkin bởi LT002!\n\n";

// ----------------------------------------------------
// BƯỚC 4: LỄ TÂN 1 (LT001 - Pham Thi Hoa) CHECK-OUT RIÊNG LẺ PHÒNG 1 ($room1)
// ----------------------------------------------------
echo "[BƯỚC 4] Đến ca sáng hôm sau, Lễ tân LT001 (Pham Thi Hoa) làm thủ tục Check-out trả phòng cho riêng phòng $room1...\n";
$resCheckout1 = $leTanService->checkout([
    'maPhieu' => $maPhieu,
    'maPhong' => $room1,
    'hinhThuc' => 'TienMat',
    'maLeTan' => 'LT001'
]);
echo "✅ " . $resCheckout1['message'] . "\n";

$stmtCT1->execute([$maPhieu, $room1]);
$ct1 = $stmtCT1->fetch(PDO::FETCH_ASSOC);
$stmtCT2->execute([$maPhieu, $room2]);
$ct2 = $stmtCT2->fetch(PDO::FETCH_ASSOC);
$stmtCheck->execute([$maPhieu]);
$pnp = $stmtCheck->fetch(PDO::FETCH_ASSOC);

echo "   -> Phòng $room1: TrangThai={$ct1['TrangThai']}, MaLeTanCheckout={$ct1['MaLeTanCheckout']}\n";
echo "   -> Phòng $room2: TrangThai={$ct2['TrangThai']}, MaLeTanCheckout=" . ($ct2['MaLeTanCheckout'] ?: 'NULL') . "\n";
echo "   -> PhieuNhanPhong: TrangThai={$pnp['TrangThai']}, MaLeTan={$pnp['MaLeTan']}\n";
assert($ct1['TrangThai'] === 'DaTraPhong', "Phòng $room1 phải là DaTraPhong");
assert($ct1['MaLeTanCheckout'] === 'LT001', "MaLeTanCheckout của phòng $room1 phải là LT001");
assert($ct2['TrangThai'] === 'DangLuuTru', "Phòng $room2 vẫn phải là DangLuuTru");
assert($pnp['TrangThai'] === 'DangLuuTru', "Phiếu vẫn DangLuuTru vì phòng $room2 chưa trả");
assert($pnp['MaLeTan'] === 'LT001', "MaLeTan của PhieuNhanPhong vẫn là LT001");
echo "   ✓ Xác nhận: Phòng $room1 đã checkout bởi LT001, phòng $room2 vẫn đang ở, phiếu tổng vẫn là DangLuuTru!\n\n";

// ----------------------------------------------------
// BƯỚC 5: LỄ TÂN 2 (LT002 - Nguyen Thi Lan) CHECK-OUT PHÒNG CÒN LẠI ($room2)
// ----------------------------------------------------
echo "[BƯỚC 5] Lễ tân LT002 (Nguyen Thi Lan) làm thủ tục Check-out trả phòng cho phòng còn lại ($room2)...\n";
$resCheckout2 = $leTanService->checkout([
    'maPhieu' => $maPhieu,
    'maPhong' => $room2,
    'hinhThuc' => 'TienMat',
    'maLeTan' => 'LT002'
]);
echo "✅ " . $resCheckout2['message'] . "\n";

$stmtCT2->execute([$maPhieu, $room2]);
$ct2 = $stmtCT2->fetch(PDO::FETCH_ASSOC);
$stmtCheck->execute([$maPhieu]);
$pnp = $stmtCheck->fetch(PDO::FETCH_ASSOC);

echo "   -> Phòng $room2: TrangThai={$ct2['TrangThai']}, MaLeTanCheckout={$ct2['MaLeTanCheckout']}\n";
echo "   -> PhieuNhanPhong: TrangThai={$pnp['TrangThai']}, MaLeTan={$pnp['MaLeTan']}\n";
assert($ct2['TrangThai'] === 'DaTraPhong', "Phòng $room2 phải là DaTraPhong");
assert($ct2['MaLeTanCheckout'] === 'LT002', "MaLeTanCheckout của phòng $room2 phải là LT002");
assert($pnp['TrangThai'] === 'DaTraPhong', "Toàn bộ phòng đã trả -> Phiếu tổng chuyển sang DaTraPhong");
assert($pnp['MaLeTan'] === 'LT001', "MaLeTan của PhieuNhanPhong VẪN LÀ LT001 (người tạo phiếu ban đầu)");
echo "   ✓ Xác nhận: Toàn bộ phòng đã checkout, phiếu tổng hoàn tất DaTraPhong, người tạo phiếu vẫn là LT001!\n\n";

// ----------------------------------------------------
// BƯỚC 6: XÁC THỰC API XEM CHI TIẾT PHIẾU (getChiTietPhieu)
// ----------------------------------------------------
echo "[BƯỚC 6] Kiểm tra API getChiTietPhieu của DatPhongRepo & LeTanRepo...\n";
$detailDatPhong = $datPhongRepo->getChiTietPhieu($maPhieu);
$detailLeTan = $leTanRepo->getChiTietPhieu($maPhieu);

echo "   * DatPhongRepo:\n";
echo "     - Lễ tân tạo phiếu: {$detailDatPhong['Phieu']['TenLeTan']} ({$detailDatPhong['Phieu']['MaLeTan']})\n";
foreach ($detailDatPhong['DanhSachPhong'] as $p) {
    echo "     - Phòng {$p['MaPhong']}: Check-in bởi [{$p['TenLeTanCheckin']} - {$p['MaLeTanCheckin']}], Check-out bởi [{$p['TenLeTanCheckout']} - {$p['MaLeTanCheckout']}]\n";
}

echo "   * LeTanRepo:\n";
echo "     - Lễ tân tạo phiếu: {$detailLeTan['phieu']['TenLeTan']} ({$detailLeTan['phieu']['MaLeTan']})\n";
foreach ($detailLeTan['danhSachPhong'] as $p) {
    echo "     - Phòng {$p['MaPhong']}: Check-in bởi [{$p['TenLeTanCheckin']} - {$p['MaLeTanCheckin']}], Check-out bởi [{$p['TenLeTanCheckout']} - {$p['MaLeTanCheckout']}]\n";
}

echo "\n========================================================\n";
echo "🎉 TẤT CẢ CÁC BƯỚC TEST ĐÃ THÀNH CÔNG RỰC RỠ (100% PASS)!\n";
echo "========================================================\n";
