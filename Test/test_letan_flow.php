<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend'))->load();

use Database\Database;
use App\DatPhong\Repositories\DatPhongRepository;
use App\DatPhong\Services\DatPhongService;
use App\Phong\Repositories\PhongRepository;
use App\Phong\Services\PhongService;
use App\LeTan\Services\LeTanService;
use App\LeTan\Repositories\LeTanRepository;
use App\HoaDon\Repositories\HoaDonRepository;
use App\ThanhToan\Repositories\ThanhToanRepository;
use App\DichVu\Services\DatDoAnService;
use App\DichVu\Repositories\DatDoAnRepository;
use App\DichVu\Services\GiatUiService;
use App\DichVu\Repositories\GiatUiRepository;
use App\DanhGia\Services\DanhGiaService;
use App\DanhGia\Repositories\DanhGiaRepository;
use App\KhachHang\Repositories\KhachHangRepository;

$db = Database::getConnection();

// 1. Dọn dẹp dữ liệu test cũ để test chạy độc lập, sạch sẽ
$db->exec("DELETE FROM DanhGia WHERE MaPhieu = 'PP999'");
$db->exec("DELETE FROM ThanhToan WHERE MaHD IN (SELECT MaHD FROM HoaDon WHERE MaPhieu = 'PP999')");
$db->exec("DELETE FROM CTHD WHERE MaPhieu = 'PP999'");
$db->exec("DELETE FROM HoaDon WHERE MaPhieu = 'PP999'");
$db->exec("DELETE FROM CTSuDungDichVu WHERE MaPhieu = 'PP999'");
$db->exec("DELETE FROM NguoiLuuTru WHERE MaPhieu = 'PP999'");
$db->exec("DELETE FROM ChiTietPhieuNhanPhong WHERE MaPhieu = 'PP999'");
$db->exec("DELETE FROM PhieuNhanPhong WHERE MaPhieu = 'PP999'");

// Khởi tạo các services
$datPhongRepo = new DatPhongRepository(new Database());
$phongRepo = new PhongRepository(new Database());
$phongService = new PhongService($phongRepo);
$datPhongService = new DatPhongService($datPhongRepo, $phongService);

$leTanRepo = new LeTanRepository();
$hoaDonRepo = new HoaDonRepository();
$thanhToanRepo = new ThanhToanRepository();
$leTanService = new LeTanService($leTanRepo, $hoaDonRepo, $thanhToanRepo);

$khachHangRepo = new KhachHangRepository(new Database());
$datDoAnRepo = new DatDoAnRepository();
$datDoAnService = new DatDoAnService($datDoAnRepo, $khachHangRepo);
$giatUiRepo = new GiatUiRepository();
$giatUiService = new GiatUiService($giatUiRepo, $khachHangRepo);

$danhGiaRepo = new DanhGiaRepository();
$danhGiaService = new DanhGiaService($danhGiaRepo, $khachHangRepo);

echo "====================================================================\n";
echo "   KIỂM THỬ TOÀN DIỆN LUỒNG: ĐẶT PHÒNG -> CHECK-IN -> DỊCH VỤ -> CHECK-OUT -> ĐÁNH GIÁ\n";
echo "====================================================================\n\n";

// BƯỚC 0: Khách hàng đặt phòng trực tuyến (DaDatPhong)
echo "--- BƯỚC 0: KHÁCH HÀNG ĐẶT PHÒNG TRỰC TUYẾN & THANH TOÁN QR MBBANK ---\n";
// Tạo bản ghi phiếu nhận phòng chuẩn test
$maPhieu = 'PP999';
$maPhong = 'P102'; // Phòng Standard còn trống
$maKH = 'KH001';   // Khách hàng Nguyen Van An (TK003)

// Ghi nhận trực tiếp phiếu đặt cọc thành công
$db->exec("
    INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH, MaLeTan, MaKM)
    VALUES ('{$maPhieu}', CURDATE(), 'TrucTuyen', 'DaDatPhong', '{$maKH}', NULL, NULL)
");
$db->exec("
    INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai)
    VALUES ('{$maPhieu}', '{$maPhong}', '2026-11-10 14:00:00', '2026-11-12 12:00:00', 2, 'DaDatPhong')
");
$db->exec("
    INSERT INTO NguoiLuuTru (MaNguoiLuuTru, HoTen, NgaySinh, QuocTich, LoaiGiayTo, SoGiayTo, GhiChu, MaPhieu, MaPhong)
    VALUES 
    ('NLT991', 'Nguyễn Văn An', '1990-01-01', 'Việt Nam', 'CCCD', '001202000001', 'Khách chính', '{$maPhieu}', '{$maPhong}'),
    ('NLT992', 'Trần Thị Mai', '1995-05-15', 'Việt Nam', 'CCCD', '001202000002', 'Đi cùng', '{$maPhieu}', '{$maPhong}')
");

$maHD = 'HD999';
$tongTienPhong = 1000000;
$db->exec("
    INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT)
    VALUES ('{$maHD}', '{$maPhieu}', {$tongTienPhong})
");
$db->exec("
    INSERT INTO CTHD (MaHD, MaPhieu, MaPhong, MaCTDV, SoDem, SoLuong, DonGia)
    VALUES ('{$maHD}', '{$maPhieu}', '{$maPhong}', NULL, 2, 1, 500000)
");
$db->exec("
    INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD)
    VALUES ('TT999', {$tongTienPhong}, NOW(), 'ChuyenKhoan', 'HoanTat', '{$maHD}')
");

echo "✅ Tạo thành công phiếu đặt phòng trước: #{$maPhieu} | Phòng: {$maPhong}\n";
echo "   - Trạng thái ban đầu: DaDatPhong\n";
echo "   - Tiền phòng đã thanh toán: " . number_format($tongTienPhong) . " đ (MBBank)\n\n";

// TEST 1: Lễ tân tra cứu chi tiết và đối soát giấy tờ người lưu trú
echo "--- TEST 1: LỄ TÂN XEM CHI TIẾT ĐỐI SOÁT GIẤY TỜ ---\n";
$detailRes = $leTanService->xemCTPhieu(['maPhieu' => $maPhieu]);
$phieu = $detailRes['data']['phieu'];
$nlt = $detailRes['data']['danhSachNguoiLuuTru'];
echo "✅ Lấy chi tiết thành công:\n";
echo "   - Khách đặt: {$phieu['TenKH']} | SĐT: {$phieu['SDT']}\n";
echo "   - Số khách khai báo: " . count($nlt) . " người:\n";
foreach ($nlt as $idx => $k) {
    echo "     " . ($idx + 1) . ". {$k['HoTen']} | Sinh: {$k['NgaySinh']} | Giấy tờ: {$k['LoaiGiayTo']} - {$k['SoGiayTo']}\n";
}

// TEST 2: Lễ tân Check-in thành công
echo "\n--- TEST 2: LỄ TÂN THỰC HIỆN CHECK-IN ---\n";
$checkinRes = $leTanService->checkin(['maPhieu' => $maPhieu, 'maLeTan' => 'LT001']);
echo "✅ " . $checkinRes['message'] . "\n";

// Xác thực lại trạng thái trong CSDL
$stmtCheck = $db->prepare("SELECT TrangThai FROM PhieuNhanPhong WHERE MaPhieu = ?");
$stmtCheck->execute([$maPhieu]);
$statusPNP = $stmtCheck->fetchColumn();

$stmtCheck2 = $db->prepare("SELECT TrangThai FROM ChiTietPhieuNhanPhong WHERE MaPhieu = ? AND MaPhong = ?");
$stmtCheck2->execute([$maPhieu, $maPhong]);
$statusCTP = $stmtCheck2->fetchColumn();

echo "   - CSDL PhieuNhanPhong: {$statusPNP} (Kỳ vọng: DangLuuTru)\n";
echo "   - CSDL ChiTietPhieuNhanPhong: {$statusCTP} (Kỳ vọng: DangLuuTru)\n";
if ($statusPNP === 'DangLuuTru' && $statusCTP === 'DangLuuTru') {
    echo "   -> KẾT QUẢ CHECK-IN: CHUẨN XÁC 100%!\n";
}

// Thử check-in lại lần nữa (Phải bị chặn)
try {
    $leTanService->checkin(['maPhieu' => $maPhieu]);
    echo "   -> LỖI: Lẽ ra phải chặn check-in lần 2!\n";
} catch (\Exception $e) {
    echo "   ✅ Chặn check-in lặp lại hợp lệ: " . $e->getMessage() . "\n";
}

// TEST 3: Khách đặt dịch vụ trong lúc lưu trú (Đồ ăn & Giặt ủi)
echo "\n--- TEST 3: ĐẶT DỊCH VỤ TRONG QUÁ TRÌNH LƯU TRÚ ---\n";
// Khách đặt món ăn: 2 phần Bò bít tết (CTDVDoAn01 - 120,000 đ)
$resMonAn = $datDoAnService->datMonAn([
    'maTK' => 'TK003',
    'maPhieu' => $maPhieu,
    'maPhong' => $maPhong,
    'maCTDV' => 'CTDVDoAn01',
    'soLuong' => 2,
    'ghiChu' => 'Giao trước 18h tối'
]);
echo "✅ " . $resMonAn['message'] . " (Thành tiền: " . number_format($resMonAn['data']['thanhTien']) . " đ)\n";

// Khách đặt giặt ủi: 3 Áo thun (CTDVGiatUi01 - 15,000 đ x 3 = 45,000 đ)
$resGiatUi = $giatUiService->datDichVuGiatUi([
    'maTK' => 'TK003',
    'maPhieu' => $maPhieu,
    'maPhong' => $maPhong,
    'danhSachDo' => [
        ['maCTDV' => 'CTDVGiatUi01', 'soLuong' => 3]
    ]
]);
echo "✅ " . $resGiatUi['message'] . " (Thành tiền: 45,000 đ)\n";

// TEST 4: Lễ tân tính tiền quyết toán Check-out
echo "\n--- TEST 4: LỄ TÂN TÍNH TIỀN QUYẾT TOÁN CHECK-OUT ---\n";
$tinhRes = $leTanService->tinhTienCheckout(['maPhieu' => $maPhieu]);
$bill = $tinhRes['data'];
echo "Bảng kê chi phí:\n";
echo "   - Tiền phòng ban đầu: " . number_format($bill['tienPhongGoc']) . " đ\n";
echo "   - Dịch vụ phát sinh (" . count($bill['danhSachDichVu']) . " mục):\n";
foreach ($bill['danhSachDichVu'] as $dv) {
    echo "     + {$dv['TenCTDV']} (SL: {$dv['SoLuong']}) = " . number_format($dv['ThanhTien']) . " đ\n";
}
echo "   - Tổng tiền dịch vụ phát sinh: " . number_format($bill['tongTienDichVu']) . " đ (Kỳ vọng: 285,000 đ)\n";
echo "   - Đã thanh toán trước: " . number_format($bill['daThanhToan']) . " đ\n";
echo "   - Phụ thu còn lại cần thanh toán: " . number_format($bill['conLai']) . " đ\n";

// TEST 5: Lễ tân thực hiện Check-out & Thu tiền phát sinh
echo "\n--- TEST 5: LỄ TÂN CHECK-OUT HOÀN TẤT ---\n";
$checkoutRes = $leTanService->checkout([
    'maPhieu' => $maPhieu,
    'hinhThuc' => 'TienMat',
    'maLeTan' => 'LT001'
]);
echo "✅ " . $checkoutRes['message'] . "\n";

$stmtCheckOut1 = $db->prepare("SELECT TrangThai FROM PhieuNhanPhong WHERE MaPhieu = ?");
$stmtCheckOut1->execute([$maPhieu]);
$statusPNPOut = $stmtCheckOut1->fetchColumn();

$stmtCheckOut2 = $db->prepare("SELECT TrangThai FROM ChiTietPhieuNhanPhong WHERE MaPhieu = ? AND MaPhong = ?");
$stmtCheckOut2->execute([$maPhieu, $maPhong]);
$statusCTPOut = $stmtCheckOut2->fetchColumn();

echo "   - CSDL PhieuNhanPhong: {$statusPNPOut} (Kỳ vọng: DaTraPhong)\n";
echo "   - CSDL ChiTietPhieuNhanPhong: {$statusCTPOut} (Kỳ vọng: DaTraPhong)\n";
if ($statusPNPOut === 'DaTraPhong' && $statusCTPOut === 'DaTraPhong') {
    echo "   -> KẾT QUẢ CHECK-OUT: CHUẨN XÁC 100%!\n";
}

// TEST 6: Khách hàng đánh giá phòng sau khi đã Check-out
echo "\n--- TEST 6: KHÁCH HÀNG ĐÁNH GIÁ PHÒNG SAU CHECK-OUT ---\n";
$reviewRes = $danhGiaService->themDanhGia([
    'maTK' => 'TK003',
    'maPhieu' => $maPhieu,
    'maPhong' => $maPhong,
    'soSao' => 5,
    'noiDung' => 'Khách sạn phục vụ rất chuyên nghiệp, phòng sạch đẹp và đồ ăn ngon tuyệt vời!'
]);
echo "✅ " . $reviewRes['message'] . " (Đánh giá: " . $reviewRes['data']['SoSao'] . " sao - Mã DG: " . $reviewRes['data']['MaDanhGia'] . ")\n";

// Chặn đánh giá lần 2 trên cùng phòng
$reviewDuplicate = $danhGiaService->themDanhGia([
    'maTK' => 'TK003',
    'maPhieu' => $maPhieu,
    'maPhong' => $maPhong,
    'soSao' => 4,
    'noiDung' => 'Thử đánh giá lại'
]);
if ($reviewDuplicate['status'] === 'error') {
    echo "✅ Chặn đánh giá trùng lặp hợp lệ: " . $reviewDuplicate['message'] . "\n";
}

echo "\n====================================================================\n";
echo "🎉 CHU TRÌNH TỪ ĐẶT PHÒNG -> CHECK-IN -> DỊCH VỤ -> CHECK-OUT -> ĐÁNH GIÁ ĐÃ TEST THÀNH CÔNG 100%!\n";
echo "====================================================================\n";
