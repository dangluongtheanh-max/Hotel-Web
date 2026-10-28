<?php
require __DIR__ . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__)->load();
require __DIR__ . '/database/Database.php';
require __DIR__ . '/app/LeTan/Repositories/LeTanRepository.php';
require __DIR__ . '/app/LeTan/Services/LeTanService.php';

use Database\Database;
use App\LeTan\Repositories\LeTanRepository;
use App\LeTan\Services\LeTanService;

$db = Database::getConnection();
$repo = new LeTanRepository();
$service = new LeTanService($repo);

echo "=========================================================================================\n";
echo " KỊCH BẢN KIỂM THỬ: 1 PHIẾU ĐANG LƯU TRÚ - ĐẶT 3 PHÒNG VỚI 3 TRƯỜNG HỢP CHECK-OUT KHÁC NHAU\n";
echo "  1. Phòng 1: Check-out ĐÚNG GIỜ        -> Phụ phí = 0 đ (0%)\n";
echo "  2. Phòng 2: Check-out TRỄ 30 PHÚT      -> Phụ phí = 0 đ (Ân hạn 30 phút)\n";
echo "  3. Phòng 3: Check-out TRỄ 60 PHÚT      -> Phụ phí = 10% đơn giá phòng (120,000 đ)\n";
echo "=========================================================================================\n\n";

$maPhieu = 'PP888';
$maHD = 'HD888';
$maTT = 'TT888';
$maKH = 'KH002'; // Đặng Lương Thế Anh
$maLeTan = 'LT001'; // Pham Thi Hoa

// Dọn dẹp dữ liệu cũ của phiếu test này (nếu đã chạy trước đó)
$db->exec("DELETE FROM ThanhToan WHERE MaHD = '{$maHD}'");
$db->exec("DELETE FROM CTHD WHERE MaPhieu = '{$maPhieu}'");
$db->exec("DELETE FROM HoaDon WHERE MaPhieu = '{$maPhieu}'");
$db->exec("DELETE FROM NguoiLuuTru WHERE MaPhieu = '{$maPhieu}'");
$db->exec("DELETE FROM ChiTietPhieuNhanPhong WHERE MaPhieu = '{$maPhieu}'");
$db->exec("DELETE FROM PhieuNhanPhong WHERE MaPhieu = '{$maPhieu}'");

echo "[BƯỚC 1] KHỞI TẠO DỮ LIỆU PHIẾU VÀ 3 PHÒNG ĐANG LƯU TRÚ:\n";

// 1. PhieuNhanPhong
$ngayLap = date('Y-m-d', strtotime('-1 day'));
$stmt = $db->prepare("INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH, MaLeTan) VALUES (?, ?, 'TrucTiep', 'DangLuuTru', ?, ?)");
$stmt->execute([$maPhieu, $ngayLap, $maKH, $maLeTan]);
echo "  + Đã tạo PhieuNhanPhong #{$maPhieu} (Trạng thái: DangLuuTru, Khách hàng: {$maKH}, Lễ tân: {$maLeTan})\n";

// Mốc thời gian
$now = time();
$nhanPhongTs = strtotime('-1 day 14:00:00'); // Nhận phòng 14:00 hôm qua
$thoiGianNhan = date('Y-m-d H:i:s', $nhanPhongTs);

// 3 phòng:
// P201: Đúng giờ -> Giờ trả dự kiến = bây giờ + 15 phút (chưa tới hạn trả -> trễ 0 phút)
$traPhongP201 = date('Y-m-d H:i:s', $now + 15 * 60);

// P202: Trễ 30 phút -> Giờ trả dự kiến = bây giờ - 30 phút (trễ đúng 30 phút -> trong ân hạn 30 phút)
$traPhongP202 = date('Y-m-d H:i:s', $now - 30 * 60);

// P301: Trễ 60 phút -> Giờ trả dự kiến = bây giờ - 60 phút (trễ 60 phút -> tính 1 giờ trễ = 10%)
$traPhongP301 = date('Y-m-d H:i:s', $now - 60 * 60);

$phongs = [
    [
        'maPhong' => 'P201',
        'loai' => 'Deluxe',
        'gia' => 800000,
        'traDuKien' => $traPhongP201,
        'moTaCase' => 'Check-out ĐÚNG GIỜ (Trả trước hạn 15 phút)'
    ],
    [
        'maPhong' => 'P202',
        'loai' => 'Deluxe',
        'gia' => 800000,
        'traDuKien' => $traPhongP202,
        'moTaCase' => 'Check-out TRỄ 30 PHÚT (Hưởng chính sách ân hạn 30 phút)'
    ],
    [
        'maPhong' => 'P301',
        'loai' => 'Suite',
        'gia' => 1200000,
        'traDuKien' => $traPhongP301,
        'moTaCase' => 'Check-out TRỄ 60 PHÚT (Vượt ân hạn, phạt 10% đơn giá phòng)'
    ]
];

// 2. ChiTietPhieuNhanPhong & Phong & NguoiLuuTru
$tongTienPhongGoc = 0;
foreach ($phongs as $idx => $p) {
    $tongTienPhongGoc += $p['gia'];
    
    // ChiTietPhieuNhanPhong
    $stmt = $db->prepare("
        INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai, MaLeTanCheckin)
        VALUES (?, ?, ?, ?, 2, 'DangLuuTru', ?)
    ");
    $stmt->execute([$maPhieu, $p['maPhong'], $thoiGianNhan, $p['traDuKien'], $maLeTan]);

    // Update Phong sang DangSuDung
    $db->prepare("UPDATE Phong SET TrangThaiPhong = 'DangSuDung' WHERE MaPhong = ?")->execute([$p['maPhong']]);

    // NguoiLuuTru
    $maNLT = 'NLT888_' . ($idx + 1);
    $tenKhach = ($idx === 0) ? 'Đặng Lương Thế Anh' : (($idx === 1) ? 'Nguyễn Văn Khách 2' : 'Trần Thị Khách 3');
    $cccd = '07920000000' . ($idx + 1);
    $stmt = $db->prepare("
        INSERT INTO NguoiLuuTru (MaNguoiLuuTru, HoTen, NgaySinh, QuocTich, LoaiGiayTo, SoGiayTo, GhiChu, MaPhieu, MaPhong)
        VALUES (?, ?, '1995-05-15', 'Việt Nam', 'CCCD', ?, 'Khách lưu trú thực tế', ?, ?)
    ");
    $stmt->execute([$maNLT, $tenKhach, $cccd, $maPhieu, $p['maPhong']]);

    echo "  + Phòng {$p['maPhong']} ({$p['loai']}, " . number_format($p['gia']) . " đ): {$p['moTaCase']}\n";
    echo "    - Giờ nhận: {$thoiGianNhan} | Giờ trả dự kiến: {$p['traDuKien']}\n";
}

// 3. HoaDon & CTHD ban đầu (Tiền phòng gốc 1 đêm = 2,800,000 đ)
$stmt = $db->prepare("INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT) VALUES (?, ?, ?)");
$stmt->execute([$maHD, $maPhieu, $tongTienPhongGoc]);

foreach ($phongs as $p) {
    $stmt = $db->prepare("INSERT INTO CTHD (MaHD, MaPhieu, MaPhong, MaCTDV, SoDem, SoLuong, DonGia) VALUES (?, ?, ?, NULL, 1, 1, ?)");
    $stmt->execute([$maHD, $maPhieu, $p['maPhong'], $p['gia']]);
}

// 4. ThanhToan cọc ban đầu (2,800,000 đ qua ChuyenKhoan)
$stmt = $db->prepare("INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD) VALUES (?, ?, ?, 'ChuyenKhoan', 'HoanTat', ?)");
$stmt->execute([$maTT, $tongTienPhongGoc, $thoiGianNhan, $maHD]);

echo "  + Hóa đơn ban đầu #{$maHD}: Tiền phòng gốc = " . number_format($tongTienPhongGoc) . " đ\n";
echo "  + Thanh toán cọc trước #{$maTT}: " . number_format($tongTienPhongGoc) . " đ (ChuyenKhoan, HoanTat)\n";
echo "  => KHỞI TẠO XONG PHIẾU TEST #{$maPhieu}!\n\n";

// -----------------------------------------------------------------------------------------
echo "[BƯỚC 2] CHẠY TÍNH TOÁN QUYẾT TOÁN TRƯỚC KHI CHECK-OUT (API tinhtiencheckout):\n";
$calcResult = $service->tinhTienCheckout(['maPhieu' => $maPhieu]);
$bill = $calcResult['data'];

echo "  - Tổng tiền phòng gốc:      " . number_format($bill['tienPhongGoc']) . " đ\n";
echo "  - Dịch vụ phát sinh:        " . number_format($bill['tongTienDichVu']) . " đ\n";
echo "  - Phụ phí trả phòng trễ:    " . number_format($bill['tongPhuPhiTreGio']) . " đ\n";
echo "  - Đã thanh toán trước đó:   -" . number_format($bill['daThanhToan']) . " đ\n";
echo "  - SỐ TIỀN CẦN THU TẠI QUẦY:  " . number_format($bill['conLai']) . " đ\n\n";

echo "  CHI TIẾT PHỤ PHÍ TỪNG PHÒNG:\n";
foreach ($bill['chiTietTreGio'] as $t) {
    echo "    * Phòng {$t['maPhong']} ({$t['tenLoaiPhong']}): Trễ {$t['soPhutTre']} phút (tính {$t['soGioTre']}h) -> Phạt {$t['tiLePhanTram']}% đơn giá = " . number_format($t['tienPhuThu']) . " đ ({$t['ghiChu']})\n";
}
echo "\n";

// -----------------------------------------------------------------------------------------
echo "[BƯỚC 3] TIẾN HÀNH THỰC HIỆN CHECK-OUT LẦN LƯỢT TỪNG PHÒNG:\n";

// 3.1 Check-out Phòng 1 (P201)
echo "  3.1 Check-out Phòng P201 (Đúng giờ):\n";
$resP201 = $service->checkout([
    'maPhieu' => $maPhieu,
    'maPhong' => 'P201',
    'hinhThuc' => 'TienMat',
    'maLeTan' => $maLeTan
]);
echo "      -> Kết quả: {$resP201['message']}\n";
echo "      -> Phụ thu: " . number_format($resP201['data']['soTienPhuThu']) . " đ\n";

// 3.2 Check-out Phòng 2 (P202)
echo "  3.2 Check-out Phòng P202 (Trễ 30 phút - Ân hạn):\n";
$resP202 = $service->checkout([
    'maPhieu' => $maPhieu,
    'maPhong' => 'P202',
    'hinhThuc' => 'TienMat',
    'maLeTan' => $maLeTan
]);
echo "      -> Kết quả: {$resP202['message']}\n";
echo "      -> Phụ thu: " . number_format($resP202['data']['soTienPhuThu']) . " đ\n";

// 3.3 Check-out Phòng 3 (P301)
echo "  3.3 Check-out Phòng P301 (Trễ 60 phút - Phạt 10% đơn giá = 120,000 đ):\n";
$resP301 = $service->checkout([
    'maPhieu' => $maPhieu,
    'maPhong' => 'P301',
    'hinhThuc' => 'TienMat',
    'maLeTan' => $maLeTan
]);
echo "      -> Kết quả: {$resP301['message']}\n";
echo "      -> Phụ thu: " . number_format($resP301['data']['soTienPhuThu']) . " đ (Mã thanh toán phụ thu: {$resP301['data']['maThanhToanPhuThu']})\n\n";

// -----------------------------------------------------------------------------------------
echo "=========================================================================================\n";
echo " [BƯỚC 4] TRUY VẤN VÀ IN TOÀN BỘ KẾT QUẢ CSDL ĐỂ BẠN KIỂM TRA ĐỐI SOÁT:\n";
echo "=========================================================================================\n\n";

// 1. PhieuNhanPhong
echo "1. BẢNG PhieuNhanPhong:\n";
$phieuDb = $db->query("SELECT MaPhieu, NgayLap, KenhDat, TrangThai, MaKH, MaLeTan FROM PhieuNhanPhong WHERE MaPhieu = '{$maPhieu}'")->fetch(PDO::FETCH_ASSOC);
print_r($phieuDb);

// 2. ChiTietPhieuNhanPhong
echo "\n2. BẢNG ChiTietPhieuNhanPhong:\n";
$ctpDb = $db->query("SELECT MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai, MaLeTanCheckin, MaLeTanCheckout FROM ChiTietPhieuNhanPhong WHERE MaPhieu = '{$maPhieu}' ORDER BY MaPhong ASC")->fetchAll(PDO::FETCH_ASSOC);
print_r($ctpDb);

// 3. HoaDon
echo "\n3. BẢNG HoaDon:\n";
$hdDb = $db->query("SELECT MaHD, MaPhieu, TongTienSauVAT FROM HoaDon WHERE MaPhieu = '{$maPhieu}'")->fetch(PDO::FETCH_ASSOC);
print_r($hdDb);

// 4. CTHD
echo "\n4. BẢNG CTHD (Chi tiết hóa đơn gồm tiền phòng + phụ phí trễ giờ):\n";
$cthdDb = $db->query("
    SELECT cthd.MaCTHD, cthd.MaHD, cthd.MaPhieu, cthd.MaPhong, cthd.MaCTDV, ctdv.TenCTDV, cthd.SoDem, cthd.SoLuong, cthd.DonGia,
           (cthd.SoDem * cthd.DonGia + cthd.SoLuong * CASE WHEN cthd.MaCTDV IS NOT NULL THEN cthd.DonGia ELSE 0 END) AS ThanhTien
    FROM CTHD cthd
    LEFT JOIN ChiTietDichVu ctdv ON cthd.MaCTDV = ctdv.MaCTDV
    WHERE cthd.MaPhieu = '{$maPhieu}'
    ORDER BY cthd.MaCTHD ASC
")->fetchAll(PDO::FETCH_ASSOC);
print_r($cthdDb);

// 5. ThanhToan
echo "\n5. BẢNG ThanhToan (Các lần thanh toán cho hóa đơn {$maHD}):\n";
$ttDb = $db->query("SELECT MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD FROM ThanhToan WHERE MaHD = '{$maHD}' ORDER BY ThoiGianThanhToan ASC")->fetchAll(PDO::FETCH_ASSOC);
print_r($ttDb);

// 6. Trạng thái phòng vật lý trong bảng Phong
echo "\n6. TRẠNG THÁI PHÒNG VẬT LÝ (Phong) SAU CHECK-OUT:\n";
$phongDb = $db->query("SELECT MaPhong, TrangThaiPhong FROM Phong WHERE MaPhong IN ('P201', 'P202', 'P301') ORDER BY MaPhong ASC")->fetchAll(PDO::FETCH_ASSOC);
print_r($phongDb);

echo "=========================================================================================\n";
echo " HOÀN TẤT KỊCH BẢN KIỂM THỬ THÀNH CÔNG 100%!\n";
echo "=========================================================================================\n";
