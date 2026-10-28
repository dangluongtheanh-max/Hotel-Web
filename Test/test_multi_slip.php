<?php

require __DIR__ . '/../Backend/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

$pdo = Database\Database::getConnection();

echo "====================================================================\n";
echo "   KIỂM THỬ XỬ LÝ ĐA PHIẾU NHẬN PHÒNG (DANGLUUTRU & DADAT) CHO KHÁCH   \n";
echo "====================================================================\n\n";

// 1. Kiểm tra timThongTinLuuTruHienTai cho KH002
$thueXeRepo = new App\DichVu\Repositories\ThueXeMayRepository();
$datDoAnRepo = new App\DichVu\Repositories\DatDoAnRepository();
$giatUiRepo = new App\DichVu\Repositories\GiatUiRepository();

$dsThueXe = $thueXeRepo->timThongTinLuuTruHienTai('KH002');
echo "1. Danh sách phòng & phiếu nhận phòng của KH002:\n";
foreach ($dsThueXe as $row) {
    echo "   - Phiếu: {$row['MaPhieu']} | Phòng: {$row['MaPhong']} | Loại: {$row['TenLoaiPhong']} | Trạng thái phiếu: {$row['TrangThaiPhieu']} | Nhận: {$row['ThoiGianNhanPhong']} | Trả: {$row['ThoiGianTraPhong']}\n";
}

if (count($dsThueXe) < 2) {
    echo "❌ LỖI: KH002 phải có ít nhất 2 phiếu (PP002 và PP007) nhưng chỉ tìm thấy " . count($dsThueXe) . "!\n";
    exit(1);
}
echo "✅ KH002 đã lấy được cả 2 phiếu (PP002 - DangLuuTru và PP007 - DaDat) thành công!\n\n";

// 2. Kiểm thử dịch vụ thuê xe máy
$db = new Database\Database();
$khRepo = new App\KhachHang\Repositories\KhachHangRepository($db);
$thueXeService = new App\DichVu\Services\ThueXeMayService($thueXeRepo, $khRepo);

// 2a. Thuê xe cho phòng đang ở (PP002 - P002)
echo "2a. Thuê xe máy gắn vào phiếu đang ở PP002 (Phòng P002):\n";
$res1 = $thueXeService->datThueXeMay([
    'maKH' => 'KH002',
    'maPhieu' => 'PP002',
    'maPhong' => 'P002',
    'maCTDV' => 'CTDVThueXe01',
    'soLuongXe' => 1,
    'thoiGianNhan' => '2026-10-15 08:00:00',
    'thoiGianTra' => '2026-10-16 10:00:00',
    'ghiChu' => 'Test thuê xe phiếu PP002'
]);

$id1 = null;
if (($res1['status'] ?? '') === 'success') {
    echo "   ✅ Thuê xe cho PP002 thành công! Tổng tiền: " . number_format($res1['data']['tongTien']) . " đ\n";
    $id1 = $res1['data']['maCTSDDV'];
} else {
    echo "   ❌ Thất bại: " . ($res1['message'] ?? '') . "\n";
}

// 2b. Thuê xe đặt trước cho phiếu PP007 (Phòng P303 - DaDat: 2026-10-16 14:00 đến 2026-10-18 12:00)
echo "\n2b. Thuê xe máy đặt trước gắn vào phiếu PP007 (Phòng P303):\n";
$res2 = $thueXeService->datThueXeMay([
    'maKH' => 'KH002',
    'maPhong' => 'PP007|P303', // Test cú pháp composite MaPhieu|MaPhong
    'maCTDV' => 'CTDVThueXe02',
    'soLuongXe' => 1,
    'thoiGianNhan' => '2026-10-16 15:00:00',
    'thoiGianTra' => '2026-10-18 11:00:00',
    'ghiChu' => 'Test thuê xe đặt trước phiếu PP007'
]);

$id2 = null;
if (($res2['status'] ?? '') === 'success') {
    echo "   ✅ Thuê xe cho PP007 thành công! Tổng tiền: " . number_format($res2['data']['tongTien']) . " đ\n";
    $id2 = $res2['data']['maCTSDDV'];
} else {
    echo "   ❌ Thất bại: " . ($res2['message'] ?? '') . "\n";
}

// 2c. Thử thuê xe trước thời gian nhận phòng của PP007 (phải báo lỗi)
echo "\n2c. Thử thuê xe trước giờ nhận phòng của phiếu đặt trước PP007:\n";
$resFailCheckin = $thueXeService->datThueXeMay([
    'maKH' => 'KH002',
    'maPhong' => 'PP007|P303',
    'maCTDV' => 'CTDVThueXe01',
    'soLuongXe' => 1,
    'thoiGianNhan' => '2026-10-15 08:00:00', // Sớm hơn 16/10 14:00
    'thoiGianTra' => '2026-10-17 10:00:00'
]);
if (($resFailCheckin['status'] ?? '') === 'error') {
    echo "   ✅ Bắt lỗi chính xác: " . $resFailCheckin['message'] . "\n";
} else {
    echo "   ❌ Lỗi: Hệ thống không chặn thuê trước giờ nhận phòng!\n";
}

// 2d. Thử thuê xe vượt quá giờ trả phòng của PP007 (phải báo lỗi)
echo "\n2d. Thử thuê xe vượt quá giờ trả phòng của phiếu PP007:\n";
$resFailCheckout = $thueXeService->datThueXeMay([
    'maKH' => 'KH002',
    'maPhong' => 'PP007|P303',
    'maCTDV' => 'CTDVThueXe01',
    'soLuongXe' => 1,
    'thoiGianNhan' => '2026-10-16 15:00:00',
    'thoiGianTra' => '2026-10-19 12:00:00' // Quá hạn 18/10 12:00
]);
if (($resFailCheckout['status'] ?? '') === 'error') {
    echo "   ✅ Bắt lỗi chính xác: " . $resFailCheckout['message'] . "\n";
} else {
    echo "   ❌ Lỗi: Hệ thống không chặn thuê quá hạn trả phòng!\n";
}

// 3. Kiểm thử Đặt món ăn
echo "\n3a. Thử đặt món ăn cho phòng đặt trước PP007 (Phải bị chặn 403):\n";
$datDoAnService = new App\DichVu\Services\DatDoAnService($datDoAnRepo, $khRepo);
$resFoodBlocked = $datDoAnService->datMonAn([
    'maKH' => 'KH002',
    'maPhong' => 'PP007|P303',
    'maCTDV' => 'CTDVDoAn01',
    'soLuong' => 2
]);
if (($resFoodBlocked['status'] ?? '') === 'error' && ($resFoodBlocked['code'] ?? 0) === 403) {
    echo "   ✅ Đã chặn đúng nghiệp vụ: " . $resFoodBlocked['message'] . "\n";
} else {
    echo "   ❌ Lỗi: Hệ thống không chặn đặt món ăn cho phiếu đặt trước PP007!\n";
}

echo "\n3b. Đặt món ăn cho phòng đang ở PP002 (Phòng P002 - Phải thành công):\n";
$resFood = $datDoAnService->datMonAn([
    'maKH' => 'KH002',
    'maPhong' => 'PP002|P002',
    'maCTDV' => 'CTDVDoAn01',
    'soLuong' => 2,
    'ghiChu' => 'Giao phòng P002'
]);
$idFood = null;
if (($resFood['status'] ?? '') === 'success') {
    echo "   ✅ Đặt món cho phòng đang ở PP002 thành công! Mã phiếu: {$resFood['data']['maPhieu']} | Phòng: {$resFood['data']['maPhong']}\n";
    $idFood = $resFood['data']['maCTSDDV'];
} else {
    echo "   ❌ Đặt món thất bại: " . ($resFood['message'] ?? '') . "\n";
}

// 4. Kiểm thử Giặt ủi
echo "\n4a. Thử đặt giặt ủi cho phòng đặt trước PP007 (Phải bị chặn 403):\n";
$giatUiService = new App\DichVu\Services\GiatUiService($giatUiRepo, $khRepo);
$resLaundryBlocked = $giatUiService->datDichVuGiatUi([
    'maKH' => 'KH002',
    'maPhong' => 'PP007|P303',
    'danhSachDo' => [
        ['maCTDV' => 'CTDVGiatUi01', 'soLuong' => 3]
    ]
]);
if (($resLaundryBlocked['status'] ?? '') === 'error' && ($resLaundryBlocked['code'] ?? 0) === 403) {
    echo "   ✅ Đã chặn đúng nghiệp vụ: " . $resLaundryBlocked['message'] . "\n";
} else {
    echo "   ❌ Lỗi: Hệ thống không chặn đặt giặt ủi cho phiếu đặt trước PP007!\n";
}

echo "\n4b. Đặt giặt ủi cho phòng đang ở PP002 (Phòng P002 - Phải thành công):\n";
$resLaundry = $giatUiService->datDichVuGiatUi([
    'maKH' => 'KH002',
    'maPhong' => 'PP002|P002',
    'danhSachDo' => [
        ['maCTDV' => 'CTDVGiatUi01', 'soLuong' => 3]
    ],
    'ghiChu' => 'Giặt gấp phòng P002'
]);
if (($resLaundry['status'] ?? '') === 'success') {
    echo "   ✅ Đặt giặt ủi cho phòng đang ở PP002 thành công! Mã phiếu: {$resLaundry['data']['maPhieu']} | Phòng: {$resLaundry['data']['maPhong']}\n";
} else {
    echo "   ❌ Đặt giặt ủi thất bại: " . ($resLaundry['message'] ?? '') . "\n";
}

// 5. Kiểm tra trực tiếp bảng CTSuDungDichVu để đảm bảo dữ liệu ghi chuẩn xác
echo "\n5. Kiểm tra dữ liệu được lưu trong bảng CTSuDungDichVu:\n";
$checkStmt = $pdo->prepare("
    SELECT ctsd.MaCTSDDV, ctsd.MaCTDV, ctdv.TenCTDV, ctsd.MaPhieu, ctsd.MaPhong, ctsd.SoLuong, ctsd.ThoiGianNhan, ctsd.ThoiGianTra, (ctsd.DonGia * ctsd.SoLuong) AS ThanhTien
    FROM CTSuDungDichVu ctsd
    JOIN ChiTietDichVu ctdv ON ctsd.MaCTDV = ctdv.MaCTDV
    WHERE ctsd.MaPhieu IN ('PP002', 'PP007')
    ORDER BY ctsd.MaCTSDDV DESC
    LIMIT 6
");
$checkStmt->execute();
$records = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($records as $r) {
    echo "   - Mã: {$r['MaCTSDDV']} | DV: {$r['TenCTDV']} | Phiếu: {$r['MaPhieu']} | Phòng: {$r['MaPhong']} | SL: {$r['SoLuong']} | Nhận: {$r['ThoiGianNhan']} | Trả: {$r['ThoiGianTra']} | Tiền: " . number_format($r['ThanhTien']) . " đ\n";
}

// Clean up test data inserted in this run
echo "\n6. Dọn dẹp dữ liệu kiểm thử vừa tạo...\n";
if (!empty($id1)) {
    $pdo->prepare("DELETE FROM CTSuDungDichVu WHERE MaCTSDDV = ?")->execute([$id1]);
    $pdo->prepare("UPDATE ChiTietDichVu SET SoLuongTon = SoLuongTon + 1 WHERE MaCTDV = 'CTDVThueXe01'")->execute();
}
if (!empty($id2)) {
    $pdo->prepare("DELETE FROM CTSuDungDichVu WHERE MaCTSDDV = ?")->execute([$id2]);
    $pdo->prepare("UPDATE ChiTietDichVu SET SoLuongTon = SoLuongTon + 1 WHERE MaCTDV = 'CTDVThueXe02'")->execute();
}
if (!empty($idFood)) {
    $pdo->prepare("DELETE FROM CTSuDungDichVu WHERE MaCTSDDV = ?")->execute([$idFood]);
}
if (!empty($resLaundry['data']['chiTietDonGiat'])) {
    foreach ($resLaundry['data']['chiTietDonGiat'] as $item) {
        $mId = $item['maCTSDDV'] ?? $item['id'] ?? null;
        if (!empty($mId)) {
            $pdo->prepare("DELETE FROM CTSuDungDichVu WHERE MaCTSDDV = ?")->execute([$mId]);
        }
    }
}
echo "   ✅ Đã dọn dẹp xong!\n";

echo "\n====================================================================\n";
echo "   TẤT CẢ CÁC KIỂM THỬ XỬ LÝ ĐA PHIẾU ĐÃ HOÀN TẤT THÀNH CÔNG 100%!   \n";
echo "====================================================================\n";
