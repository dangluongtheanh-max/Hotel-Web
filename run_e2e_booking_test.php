<?php
/**
 * Test Script Kiểm Thử Chức Năng Đặt Phòng & Ghép Nối Hóa Đơn & Lịch Sử Đặt Phòng
 */

require_once __DIR__ . '/Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/Backend');
$dotenv->load();

$baseUrl = 'http://localhost:8000/Backend/public/index.php';

function postJson($url, $data, $bearerToken = null) {
    $ch = curl_init($url);
    $payload = json_encode($data);
    $headers = ['Content-Type: application/json'];
    if ($bearerToken) {
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $code, 'body' => json_decode($res, true), 'raw' => $res];
}

function getJson($url, $bearerToken = null) {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json'];
    if ($bearerToken) {
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $code, 'body' => json_decode($res, true), 'raw' => $res];
}

$db = Database\Database::getConnection();

echo "=================================================================\n";
echo "BẮT ĐẦU KIỂM THỬ: ĐẶT PHÒNG + HÓA ĐƠN + LỊCH SỬ ĐẶT PHÒNG (E2E)\n";
echo "=================================================================\n\n";

// Lấy phòng trống thử nghiệm
$stmt = $db->query("SELECT p.MaPhong, p.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.SoNguoiToiDa 
                    FROM Phong p 
                    JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong 
                    WHERE p.TrangThaiPhong = 'Trong' 
                    LIMIT 2");
$availableRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($availableRooms)) {
    echo "⚠️ CẢNH BÁO: Không có phòng nào đang có trạng thái 'Trong'. Cập nhật tạm 2 phòng...\n";
    $db->exec("UPDATE Phong SET TrangThaiPhong = 'Trong' LIMIT 2");
    $availableRooms = $db->query("SELECT p.MaPhong, p.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.SoNguoiToiDa 
                                  FROM Phong p 
                                  JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong 
                                  WHERE p.TrangThaiPhong = 'Trong' 
                                  LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
}

$room1 = $availableRooms[0];
echo "✓ Phòng test 1: {$room1['MaPhong']} ({$room1['TenLoaiPhong']} - Giá: " . number_format($room1['Gia']) . "đ)\n";

// Lấy 1 dịch vụ thử nghiệm
$dvSample = $db->query("SELECT ctdv.MaCTDV, ctdv.TenCTDV, ctdv.DonGia, dv.TenDV 
                        FROM ChiTietDichVu ctdv 
                        JOIN DichVu dv ON ctdv.MaDV = dv.MaDV 
                        LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($dvSample) {
    echo "✓ Dịch vụ đi kèm test: {$dvSample['MaCTDV']} - {$dvSample['TenCTDV']} (Giá: " . number_format($dvSample['DonGia']) . "đ)\n";
}

$ngayNhan = date('Y-m-d', strtotime('+10 days'));
$ngayTra = date('Y-m-d', strtotime('+12 days')); // 2 đêm

// -----------------------------------------------------------------------------------
// TEST 1: KHÁCH HÀNG ĐẶT PHÒNG ONLINE (VIETQR / CHUYỂN KHOẢN)
// -----------------------------------------------------------------------------------
echo "\n--- [TEST 1] KHÁCH HÀNG ĐẶT PHÒNG TRỰC TUYẾN ---\n";
$bookingOnlinePayload = [
    'maTK' => 'TK003', // khach01 (KH001)
    'kenhDat' => 'TrucTuyen',
    'ngayNhan' => $ngayNhan,
    'ngayTra' => $ngayTra,
    'soNguoiLon' => 1,
    'soTreEm' => 0,
    'soPhong' => 1,
    'phongDaChon' => [
        [
            'maLoaiPhong' => $room1['MaLoaiPhong'],
            'soLuong' => 1
        ]
    ],
    'nguoiLuuTruTheoPhong' => [
        [
            'maLoaiPhong' => $room1['MaLoaiPhong'],
            'index' => 0,
            'khach' => [
                [
                    'hoTen' => 'Nguyễn Văn Test',
                    'ngaySinh' => '1995-05-15',
                    'quocTich' => 'Việt Nam',
                    'loaiGiayTo' => 'CCCD',
                    'soGiayTo' => '079195000001',
                    'ghiChu' => 'Khách VIP yêu cầu phòng tầng cao'
                ]
            ]
        ]
    ],
    'dichVuDaChon' => $dvSample ? [
        [
            'maCTDV' => $dvSample['MaCTDV'],
            'soLuong' => 2
        ]
    ] : [],
    'hinhThuc' => 'ChuyenKhoan'
];

$resOnline = postJson("$baseUrl/datphong/datphongvathanhtoan", $bookingOnlinePayload, 'TK003');
echo "Status Code: {$resOnline['status']}\n";
if (!$resOnline['body']['success']) {
    echo "❌ LỖI ĐẶT PHÒNG TRỰC TUYẾN: " . json_encode($resOnline['body'], JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

$dataOnline = $resOnline['body']['data'];
$maPhieuOnline = $dataOnline['maPhieu'];
$maHDOnline = $dataOnline['maHD'];
$maTTOnline = $dataOnline['maThanhToan'];

echo "✅ Đặt phòng trực tuyến thành công!\n";
echo "   - Mã phiếu: #{$maPhieuOnline}\n";
echo "   - Mã hóa đơn: #{$maHDOnline}\n";
echo "   - Mã thanh toán: #{$maTTOnline}\n";
echo "   - Tổng tiền sau VAT: " . number_format($dataOnline['chiPhiDuKien']['tongTienSauVAT']) . " đ\n";

// Kiểm tra trong CSDL: Bảng HoaDon & CTHD
$checkHD = $db->query("SELECT * FROM HoaDon WHERE MaHD = '{$maHDOnline}'")->fetch(PDO::FETCH_ASSOC);
if (!$checkHD || $checkHD['MaPhieu'] !== $maPhieuOnline) {
    echo "❌ Lỗi: Bản ghi HoaDon không khớp mã phiếu!\n";
    exit(1);
}
echo "✓ DB HoaDon tồn tại: MaHD={$checkHD['MaHD']}, MaPhieu={$checkHD['MaPhieu']}, TongTien={$checkHD['TongTienSauVAT']}\n";

$checkCTHD = $db->query("SELECT * FROM CTHD WHERE MaHD = '{$maHDOnline}'")->fetchAll(PDO::FETCH_ASSOC);
echo "✓ DB CTHD có " . count($checkCTHD) . " dòng chi tiết:\n";
foreach ($checkCTHD as $c) {
    if ($c['MaPhong']) {
        echo "   + Dòng tiền phòng: Phòng {$c['MaPhong']}, {$c['SoDem']} đêm, đơn giá " . number_format($c['DonGia']) . "đ\n";
    } elseif ($c['MaCTDV']) {
        echo "   + Dòng dịch vụ: Mã DV {$c['MaCTDV']}, SL {$c['SoLuong']}, đơn giá " . number_format($c['DonGia']) . "đ\n";
    }
}

// -----------------------------------------------------------------------------------
// TEST 2: LỄ TÂN ĐẶT PHÒNG TẠI QUẦY (TIỀN MẶT)
// -----------------------------------------------------------------------------------
echo "\n--- [TEST 2] LỄ TÂN ĐẶT PHÒNG TẠI QUẦY (TIỀN MẶT) ---\n";
// Lấy phòng khác
$room2 = isset($availableRooms[1]) ? $availableRooms[1] : $room1;
$bookingCounterPayload = [
    'maTK' => 'TK003', // Đặt cho khách hàng KH001
    'ngayNhan' => $ngayNhan,
    'ngayTra' => $ngayTra,
    'soNguoiLon' => 1,
    'soTreEm' => 0,
    'soPhong' => 1,
    'phongDaChon' => [
        [
            'maLoaiPhong' => $room2['MaLoaiPhong'],
            'soLuong' => 1
        ]
    ],
    'nguoiLuuTruTheoPhong' => [
        [
            'maLoaiPhong' => $room2['MaLoaiPhong'],
            'index' => 0,
            'khach' => [
                [
                    'hoTen' => 'Trần Văn Counter',
                    'ngaySinh' => '1990-01-01',
                    'quocTich' => 'Việt Nam',
                    'loaiGiayTo' => 'CCCD',
                    'soGiayTo' => '079190000002',
                    'ghiChu' => 'Khách làm thủ tục trực tiếp tại quầy'
                ]
            ]
        ]
    ],
    'dichVuDaChon' => [],
    'hinhThuc' => 'TienMat',
    'kenhDat' => 'TrucTiep',
    'maLeTan' => 'LT001'
];

$resCounter = postJson("$baseUrl/datphong/datphongvathanhtoan", $bookingCounterPayload, 'TK002');
echo "Status Code: {$resCounter['status']}\n";
if (!$resCounter['body']['success']) {
    echo "❌ LỖI ĐẶT PHÒNG TẠI QUẦY: " . json_encode($resCounter['body'], JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

$dataCounter = $resCounter['body']['data'];
$maPhieuCounter = $dataCounter['maPhieu'];
$maHDCounter = $dataCounter['maHD'];

echo "✅ Đặt phòng tại quầy thành công!\n";
echo "   - Mã phiếu: #{$maPhieuCounter}\n";
echo "   - Mã hóa đơn: #{$maHDCounter}\n";
echo "   - Kênh đặt: {$dataCounter['kenhDat']} (Lễ tân: {$dataCounter['maLeTan']})\n";

// Kiểm tra ThanhToan hình thức TienMat
$checkTTCounter = $db->query("SELECT * FROM ThanhToan WHERE MaHD = '{$maHDCounter}'")->fetch(PDO::FETCH_ASSOC);
if (!$checkTTCounter || $checkTTCounter['HinhThuc'] !== 'TienMat') {
    echo "❌ Lỗi: Hình thức thanh toán tại quầy không phải TienMat!\n";
    exit(1);
}
echo "✓ DB ThanhToan hình thức TienMat đã lưu thành công.\n";

// -----------------------------------------------------------------------------------
// TEST 3: KIỂM THỬ MODULE HOADON API
// -----------------------------------------------------------------------------------
echo "\n--- [TEST 3] KIỂM THỬ API MODULE HOADON (xemHoaDon / timHoaDon) ---\n";
// Gọi xemHoaDon theo maPhieu
$resViewHDPhieu = getJson("$baseUrl/hoadon/xemhoadon?maPhieu={$maPhieuOnline}");
echo "Raw response: " . $resViewHDPhieu['raw'] . "\n";
if (!$resViewHDPhieu['body']['success']) {
    echo "❌ Lỗi xem hóa đơn theo mã phiếu: " . json_encode($resViewHDPhieu['body'], JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}
$hdData = $resViewHDPhieu['body']['data'];
echo "✅ Gọi /hoadon/xemhoadon?maPhieu={$maPhieuOnline} thành công:\n";
echo "   - Mã HĐ: {$hdData['hoaDon']['MaHD']}\n";
echo "   - Khách hàng: {$hdData['phieu']['TenKhachHang']}\n";
echo "   - Tiền phòng: " . number_format($hdData['tongHop']['tongTienPhong']) . " đ\n";
echo "   - Tiền dịch vụ: " . number_format($hdData['tongHop']['tongTienDichVu']) . " đ\n";
echo "   - Tổng tiền sau VAT: " . number_format($hdData['tongHop']['tongTienSauVAT']) . " đ\n";
echo "   - Số dòng CTHD: " . count($hdData['chiTiet']) . " dòng\n";

// Gọi xemHoaDon theo maHD
$resViewHDId = getJson("$baseUrl/hoadon/xemhoadon?maHD={$maHDOnline}");
if (!$resViewHDId['body']['success']) {
    echo "❌ Lỗi xem hóa đơn theo mã HĐ: " . json_encode($resViewHDId['body'], JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}
echo "✅ Gọi /hoadon/xemhoadon?maHD={$maHDOnline} thành công!\n";

// -----------------------------------------------------------------------------------
// TEST 4: KIỂM THỬ LỊCH SỬ ĐẶT PHÒNG (LICHSUDATPHONG API)
// -----------------------------------------------------------------------------------
echo "\n--- [TEST 4] KIỂM THỬ LỊCH SỬ ĐẶT PHÒNG (datphong/lichsu) ---\n";
$resHistory = getJson("$baseUrl/datphong/lichsu?maTK=TK003&limit=10&page=1", 'TK003');
if (!$resHistory['body']['success']) {
    echo "❌ Lỗi lấy lịch sử đặt phòng: " . json_encode($resHistory['body'], JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

$historyItems = $resHistory['body']['data']['items'];
echo "✅ Lấy lịch sử đặt phòng thành công (" . count($historyItems) . " phiếu trong lịch sử):\n";

$foundOnline = false;
$foundCounter = false;
foreach ($historyItems as $item) {
    if ($item['MaPhieu'] === $maPhieuOnline) {
        $foundOnline = true;
        echo "   + Tìm thấy phiếu Online #{$item['MaPhieu']}: Mã HĐ=#{$item['MaHD']}, Tổng tiền=" . number_format($item['TongTien']) . "đ, TT={$item['TrangThaiThanhToan']}\n";
    }
    if ($item['MaPhieu'] === $maPhieuCounter) {
        $foundCounter = true;
        echo "   + Tìm thấy phiếu Counter #{$item['MaPhieu']}: Mã HĐ=#{$item['MaHD']}, Tổng tiền=" . number_format($item['TongTien']) . "đ, TT={$item['TrangThaiThanhToan']}\n";
    }
}

if (!$foundOnline || !$foundCounter) {
    echo "❌ Lỗi: Không tìm thấy phiếu vừa đặt trong danh sách lịch sử!\n";
    exit(1);
}

// -----------------------------------------------------------------------------------
// TEST 5: KIỂM THỬ CHI TIẾT PHIẾU (datphong/xemctphieu)
// -----------------------------------------------------------------------------------
echo "\n--- [TEST 5] KIỂM THỬ XEM CHI TIẾT PHIẾU (datphong/xemctphieu) ---\n";
$resDetail = postJson("$baseUrl/datphong/xemctphieu", ['maPhieu' => $maPhieuOnline, 'maTK' => 'TK003'], 'TK003');
if (!$resDetail['body']['success']) {
    echo "❌ Lỗi xem chi tiết phiếu: " . json_encode($resDetail['body'], JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}
$detailData = $resDetail['body']['data'];
echo "✅ Xem chi tiết phiếu thành công:\n";
echo "   - Mã HĐ trong phiếu: #" . ($detailData['HoaDon']['MaHD'] ?? 'null') . "\n";
echo "   - Số dòng ChiTietHoaDon trả về: " . count($detailData['ChiTietHoaDon']) . "\n";
echo "   - Số khách lưu trú: " . count($detailData['NguoiLuuTru']) . "\n";

// -----------------------------------------------------------------------------------
// TEST 6: KIỂM THỬ GIAO DIỆN LỄ TÂN (letan/xemdsphieu)
// -----------------------------------------------------------------------------------
echo "\n--- [TEST 6] KIỂM THỬ DANH SÁCH PHIẾU BÊN LỄ TÂN (letan/xemdsphieu) ---\n";
$resRec = getJson("$baseUrl/letan/xemdsphieu");
if (!$resRec['body']['success']) {
    echo "❌ Lỗi xem danh sách lễ tân: " . json_encode($resRec['body'], JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}
$recItems = $resRec['body']['data']['items'];
$foundInRec = false;
foreach ($recItems as $item) {
    if ($item['MaPhieu'] === $maPhieuOnline) {
        $foundInRec = true;
        echo "   + Phiếu Online bên Lễ tân: #{$item['MaPhieu']} (Mã HĐ: #{$item['MaHD']}, Tiền: " . number_format($item['TongTienSauVAT']) . "đ)\n";
        break;
    }
}
if (!$foundInRec) {
    echo "❌ Lỗi: Phiếu mới không xuất hiện trong danh sách Lễ tân!\n";
    exit(1);
}

echo "\n=================================================================\n";
echo "🎉 TẤT CẢ 6 HẠNG MỤC TEST E2E ĐỀU HOÀN THÀNH VÀ VƯỢT QUA 100%!\n";
echo "=================================================================\n";
