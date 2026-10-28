<?php

require __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

echo "=======================================================\n";
echo "   BẮT ĐẦU KIỂM THỬ CHỨC NĂNG ĐẶT DỊCH VỤ GIẶT ỦI\n";
echo "=======================================================\n\n";

$db = new \Database\Database();
$giatUiRepo = new \App\DichVu\Repositories\GiatUiRepository($db);
$khachHangRepo = new \App\KhachHang\Repositories\KhachHangRepository($db);
$service = new \App\DichVu\Services\GiatUiService($giatUiRepo, $khachHangRepo);

// Test 1: Kiểm tra danh sách bảng giá giặt ủi (8 loại đồ)
echo "TEST 1: Lấy danh mục bảng giá giặt ủi cố định...\n";
$res1 = $service->layDanhSachGiatUi();
assert($res1['status'] === 'success', 'Status phải là success');
assert(count($res1['data']) === 8, 'Phải có đúng 8 loại đồ giặt ủi');

$expectedPrices = [
    'CTDVGiatUi01' => ['name' => 'Áo thun', 'price' => 15000],
    'CTDVGiatUi02' => ['name' => 'Quần ngắn', 'price' => 15000],
    'CTDVGiatUi03' => ['name' => 'Quần dài', 'price' => 25000],
    'CTDVGiatUi04' => ['name' => 'Áo sơ mi', 'price' => 25000],
    'CTDVGiatUi05' => ['name' => 'Áo khoác', 'price' => 30000],
    'CTDVGiatUi06' => ['name' => 'Khăn', 'price' => 10000],
    'CTDVGiatUi07' => ['name' => 'Vớ', 'price' => 10000],
    'CTDVGiatUi08' => ['name' => 'Đồ lót', 'price' => 10000],
];

foreach ($res1['data'] as $item) {
    $code = $item['MaCTDV'];
    assert(isset($expectedPrices[$code]), "Mã $code không nằm trong danh sách mong đợi");
    assert((float)$item['DonGia'] === (float)$expectedPrices[$code]['price'], "Đơn giá $code không khớp");
    assert($item['TenCTDV'] === $expectedPrices[$code]['name'], "Tên $code không khớp");
    echo "  ✓ [{$code}] {$item['TenCTDV']} - " . number_format($item['DonGia']) . " đ\n";
}
echo "=> TEST 1 ĐẠT 100%!\n\n";

// Test 2: Đặt giặt ủi khi chưa đăng nhập (không có maTK, maKH)
echo "TEST 2: Kiểm tra validation chưa đăng nhập...\n";
$res2 = $service->datDichVuGiatUi([
    'danhSachDo' => [['maCTDV' => 'CTDVGiatUi01', 'soLuong' => 2]]
]);
assert($res2['status'] === 'error' && $res2['code'] === 401, 'Phải trả về lỗi 401 khi chưa đăng nhập');
echo "  ✓ Phản hồi: {$res2['message']}\n";
echo "=> TEST 2 ĐẠT!\n\n";

// Test 3: Đặt giặt ủi với số lượng không hợp lệ (số âm hoặc 0)
echo "TEST 3: Kiểm tra validation số lượng không hợp lệ...\n";
$res3 = $service->datDichVuGiatUi([
    'maTK' => 'TK005',
    'danhSachDo' => [
        ['maCTDV' => 'CTDVGiatUi01', 'soLuong' => 0]
    ]
]);
assert($res3['status'] === 'error' && $res3['code'] === 400, 'Phải từ chối số lượng <= 0');
echo "  ✓ Phản hồi: {$res3['message']}\n";
echo "=> TEST 3 ĐẠT!\n\n";

// Test 4: Đặt giặt ủi với khách KHÔNG có phòng lưu trú hoặc đặt trước
echo "TEST 4: Kiểm tra khách không có phòng lưu trú / đặt trước...\n";
$res4 = $service->datDichVuGiatUi([
    'maKH' => 'KH_KHONG_DAT_PHONG',
    'danhSachDo' => [
        ['maCTDV' => 'CTDVGiatUi01', 'soLuong' => 2]
    ]
]);
assert($res4['status'] === 'error' && $res4['code'] === 403, 'Phải từ chối khách không lưu trú với mã 403');
echo "  ✓ Phản hồi: {$res4['message']}\n";
echo "=> TEST 4 ĐẠT!\n\n";

// Test 5: Đặt giặt ủi hợp lệ nhiều loại đồ cùng lúc với khách đang lưu trú (khach02 - TK005)
echo "TEST 5: Đặt giặt ủi nhiều món với khách đang lưu trú (khach02, phòng P002, phiếu PP002)...\n";
$orderPayload = [
    'maTK' => 'TK005',
    'maPhong' => 'P002',
    'ghiChu' => 'Giặt sấy gấp lấy trong chiều',
    'danhSachDo' => [
        ['maCTDV' => 'CTDVGiatUi01', 'soLuong' => 3], // 3 Áo thun * 15,000 = 45,000
        ['maCTDV' => 'CTDVGiatUi06', 'soLuong' => 2], // 2 Khăn * 10,000 = 20,000
        ['maCTDV' => 'CTDVGiatUi05', 'soLuong' => 1], // 1 Áo khoác * 30,000 = 30,000
    ]
];

$res5 = $service->datDichVuGiatUi($orderPayload);
assert($res5['status'] === 'success' && $res5['code'] === 200, 'Đặt dịch vụ phải thành công');
assert($res5['data']['tongTien'] === 95000.0, 'Tổng tiền phải chính xác là 95,000 đ');
assert($res5['data']['soLoaiDo'] === 3, 'Số loại đồ phải là 3');

echo "  ✓ Thông báo thành công: {$res5['message']}\n";
echo "  ✓ Tổng tiền dự kiến: " . number_format($res5['data']['tongTien']) . " đ\n";
echo "  ✓ Số bản ghi tạo ra: {$res5['data']['soLoaiDo']}\n";

// Kiểm tra chi tiết trong Database CTSuDungDichVu
$pdo = \Database\Database::getConnection();
$insertedIds = [];
foreach ($res5['data']['chiTietDonGiat'] as $row) {
    $insertedIds[] = $row['maCTSDDV'];
    echo "    - Bản ghi MaCTSDDV #{$row['maCTSDDV']}: {$row['tenCTDV']} ({$row['maCTDV']}) x {$row['soLuong']} = " . number_format($row['thanhTien']) . " đ (Đơn giá DB: " . number_format($row['donGia']) . " đ)\n";
    
    // Kiểm tra trực tiếp trong DB
    $stmt = $pdo->prepare("SELECT * FROM CTSuDungDichVu WHERE MaCTSDDV = ?");
    $stmt->execute([$row['maCTSDDV']]);
    $dbRow = $stmt->fetch(\PDO::FETCH_ASSOC);
    assert(!empty($dbRow), 'Bản ghi phải tồn tại trong bảng CTSuDungDichVu');
    assert($dbRow['MaPhieu'] === 'PP002', 'Mã phiếu phải là PP002');
    assert($dbRow['MaPhong'] === 'P002', 'Mã phòng phải là P002');
    assert((int)$dbRow['SoLuong'] === (int)$row['soLuong'], 'Số lượng phải khớp');
    assert((float)$dbRow['DonGia'] === (float)$row['donGia'], 'Đơn giá lưu trữ phải khớp');
}
echo "=> TEST 5 ĐẠT 100% CẢ LOGIC VÀ DATABASE INTEGRITY!\n\n";

// Dọn dẹp bản ghi test
if (!empty($insertedIds)) {
    $idList = implode(',', $insertedIds);
    $pdo->exec("DELETE FROM CTSuDungDichVu WHERE MaCTSDDV IN ($idList)");
    echo "🧹 Đã dọn dẹp các bản ghi test (MaCTSDDV: $idList).\n\n";
}

echo "🎉 TẤT CẢ 5/5 BÀI TEST CHỨC NĂNG GIẶT ỦI ĐÃ VƯỢT QUA XUẤT SẮC!\n";
