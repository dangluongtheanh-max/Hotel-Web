<?php

require __DIR__ . '/../Backend/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

use App\DichVu\Repositories\ThueXeMayRepository;
use App\DichVu\Services\ThueXeMayService;
use App\KhachHang\Repositories\KhachHangRepository;
use Database\Database;

$db = new Database();
$pdo = Database::getConnection();
$xeRepo = new ThueXeMayRepository($db);
$khRepo = new KhachHangRepository($db);
$service = new ThueXeMayService($xeRepo, $khRepo);

// Đặt lại tồn kho chuẩn 10 xe trước khi chạy test
$pdo->exec("UPDATE ChiTietDichVu SET SoLuongTon = 10 WHERE MaDV = 'DVThueXe'");

echo "=======================================================\n";
echo "   BẮT ĐẦU KIỂM THỬ CHỨC NĂNG THUÊ XE MÁY (PHƯƠNG ÁN B)\n";
echo "=======================================================\n\n";

// Dọn dẹp các bản ghi rác nếu có từ lần chạy trước
$pdo->exec("DELETE FROM CTSuDungDichVu WHERE MaCTSDDV >= 39");
$pdo->exec("UPDATE ChiTietDichVu SET SoLuongTon = 10 WHERE MaCTDV = 'CTDVThueXe01'");

// TEST 1: Lấy danh sách xe máy cho thuê
echo "TEST 1: Lấy danh sách xe máy cho thuê (DVThueXe)...\n";
$res1 = $service->layDanhSachXeMay();
assert($res1['status'] === 'success' && $res1['code'] === 200, 'Lấy danh mục xe thất bại');
assert(count($res1['data']) === 2, 'Phải có đúng 2 dòng xe máy cho thuê');

$expectedBikes = [
    'CTDVThueXe01' => ['name' => 'Xe tay ga Honda Vision / AirBlade', 'price' => 150000],
    'CTDVThueXe02' => ['name' => 'Xe so Honda Wave / Sirius', 'price' => 100000],
];

foreach ($res1['data'] as $bike) {
    $code = $bike['MaCTDV'];
    assert(isset($expectedBikes[$code]), "Mã $code không nằm trong danh sách kỳ vọng");
    assert((float)$bike['DonGia'] === (float)$expectedBikes[$code]['price'], "Đơn giá $code không khớp");
    assert($bike['TenCTDV'] === $expectedBikes[$code]['name'], "Tên xe $code không khớp");
    echo "  ✓ [{$code}] {$bike['TenCTDV']} - " . number_format($bike['DonGia']) . " đ/ngày (Tồn kho: {$bike['SoLuongTon']})\n";
}
echo "=> TEST 1 ĐẠT 100%!\n\n";

// TEST 2: Validation khi chưa đăng nhập
echo "TEST 2: Kiểm tra validation chưa đăng nhập...\n";
$res2 = $service->datThueXeMay([
    'maCTDV' => 'CTDVThueXe01',
    'soLuongXe' => 1,
    'thoiGianNhan' => '2026-10-16 08:00:00',
    'thoiGianTra' => '2026-10-17 08:00:00'
]);
assert($res2['status'] === 'error' && $res2['code'] === 401, 'Phải trả về lỗi 401 khi chưa đăng nhập');
echo "  ✓ Phản hồi: {$res2['message']}\n";
echo "=> TEST 2 ĐẠT!\n\n";

// TEST 3: Validation khách hàng không có phòng lưu trú hoặc đặt trước
echo "TEST 3: Kiểm tra khách không có phòng lưu trú / đặt trước...\n";
$res3 = $service->datThueXeMay([
    'maKH' => 'KH_KHONG_DAT_PHONG',
    'maCTDV' => 'CTDVThueXe01',
    'soLuongXe' => 1,
    'thoiGianNhan' => '2026-10-16 08:00:00',
    'thoiGianTra' => '2026-10-17 08:00:00'
]);
assert($res3['status'] === 'error' && $res3['code'] === 403, 'Phải từ chối khách không lưu trú với mã 403');
echo "  ✓ Phản hồi: {$res3['message']}\n";
echo "=> TEST 3 ĐẠT!\n\n";

// TEST 4: Validation chọn phòng không thuộc danh sách lưu trú của khách
echo "TEST 4: Kiểm tra chọn sai phòng (chọn P101 thay vì P002)...\n";
$res4 = $service->datThueXeMay([
    'maTK' => 'TK005',
    'maPhong' => 'P101',
    'maCTDV' => 'CTDVThueXe01',
    'soLuongXe' => 1,
    'thoiGianNhan' => '2026-10-16 08:00:00',
    'thoiGianTra' => '2026-10-17 08:00:00'
]);
assert($res4['status'] === 'error' && $res4['code'] === 400, 'Phải từ chối phòng không thuộc diện lưu trú');
echo "  ✓ Phản hồi: {$res4['message']}\n";
echo "=> TEST 4 ĐẠT!\n\n";

// TEST 5: Validation số lượng xe không hợp lệ (<= 0)
echo "TEST 5: Kiểm tra số lượng xe <= 0...\n";
$res5 = $service->datThueXeMay([
    'maTK' => 'TK005',
    'maPhong' => 'P002',
    'maCTDV' => 'CTDVThueXe01',
    'soLuongXe' => 0,
    'thoiGianNhan' => '2026-10-15 08:00:00',
    'thoiGianTra' => '2026-10-16 08:00:00'
]);
assert($res5['status'] === 'error' && $res5['code'] === 400, 'Phải từ chối số lượng <= 0');
echo "  ✓ Phản hồi: {$res5['message']}\n";
echo "=> TEST 5 ĐẠT!\n\n";

// TEST 6: Validation số lượng xe vượt quá tồn kho
echo "TEST 6: Kiểm tra số lượng xe vượt quá tồn kho (thuê 999 xe)...\n";
$res6 = $service->datThueXeMay([
    'maTK' => 'TK005',
    'maPhong' => 'P002',
    'maCTDV' => 'CTDVThueXe01',
    'soLuongXe' => 999,
    'thoiGianNhan' => '2026-10-15 08:00:00',
    'thoiGianTra' => '2026-10-16 08:00:00'
]);
assert($res6['status'] === 'error' && $res6['code'] === 400, 'Phải từ chối khi vượt quá tồn kho');
echo "  ✓ Phản hồi: {$res6['message']}\n";
echo "=> TEST 6 ĐẠT!\n\n";

// TEST 7: Validation thời gian trả xe trước hoặc bằng thời gian nhận
echo "TEST 7: Kiểm tra thời gian trả xe <= thời gian nhận xe...\n";
$res7 = $service->datThueXeMay([
    'maTK' => 'TK005',
    'maPhong' => 'P002',
    'maCTDV' => 'CTDVThueXe01',
    'soLuongXe' => 1,
    'thoiGianNhan' => '2026-10-16 12:00:00',
    'thoiGianTra' => '2026-10-15 08:00:00'
]);
assert($res7['status'] === 'error' && $res7['code'] === 400, 'Phải từ chối thời gian trả <= nhận');
echo "  ✓ Phản hồi: {$res7['message']}\n";
echo "=> TEST 7 ĐẠT!\n\n";

// TEST 8: Validation thời gian trả xe vượt quá hạn trả phòng của khách
// Phòng P002 của khách TK005 có ThoiGianTraPhong là 2026-10-20 12:00:00
echo "TEST 8: Kiểm tra thời gian trả xe vượt quá hạn trả phòng (chọn trả vào 2026-10-21 10:00:00)...\n";
$res8 = $service->datThueXeMay([
    'maTK' => 'TK005',
    'maPhong' => 'P002',
    'maCTDV' => 'CTDVThueXe01',
    'soLuongXe' => 1,
    'thoiGianNhan' => '2026-10-15 15:00:00',
    'thoiGianTra' => '2026-10-21 10:00:00'
]);
assert($res8['status'] === 'error' && $res8['code'] === 400, 'Phải từ chối khi vượt quá hạn trả phòng');
echo "  ✓ Phản hồi: {$res8['message']}\n";
echo "=> TEST 8 ĐẠT!\n\n";

// TEST 9: Đặt thuê xe máy thành công hợp lệ (2 xe tay ga, trong 2 ngày trong kỳ lưu trú)
echo "TEST 9: Đặt thuê xe hợp lệ: 2 xe tay ga từ 2026-10-15 15:00 đến 2026-10-17 11:00 (44h = 2 ngày)...\n";
$orderPayload = [
    'maTK' => 'TK005',
    'maPhong' => 'P002',
    'maCTDV' => 'CTDVThueXe01',
    'soLuongXe' => 2,
    'thoiGianNhan' => '2026-10-15 15:00:00',
    'thoiGianTra' => '2026-10-17 11:00:00',
    'ghiChu' => 'Cần thêm 1 mũ bảo hiểm trẻ em'
];

$res9 = $service->datThueXeMay($orderPayload);
assert($res9['status'] === 'success' && $res9['code'] === 200, 'Thuê xe phải thành công: ' . ($res9['message'] ?? ''));

$data = $res9['data'];
echo "  ✓ Thông báo: {$res9['message']}\n";
echo "  ✓ Mã CTSDDV: #{$data['maCTSDDV']}\n";
echo "  ✓ Dòng xe: {$data['tenXe']}\n";
echo "  ✓ Số xe thuê: {$data['soLuongXe']} xe\n";
echo "  ✓ Số ngày tính cước: {$data['soNgay']} ngày\n";
echo "  ✓ Số lượng quy đổi (CTHD): {$data['soLuongLuu']} xe-ngày\n";
echo "  ✓ Đơn giá: " . number_format($data['donGiaNgay']) . " đ/ngày\n";
echo "  ✓ Tổng tiền: " . number_format($data['tongTien']) . " đ\n";
echo "  ✓ Thời gian nhận: {$data['thoiGianNhan']}\n";
echo "  ✓ Thời gian trả: {$data['thoiGianTra']}\n";
echo "  ✓ Ghi chú lưu vết: {$data['ghiChu']}\n";

// Kiểm tra tính toán
assert($data['soNgay'] === 2, '44 giờ phải làm tròn lên 2 ngày');
assert($data['soLuongLuu'] === 4, '2 xe * 2 ngày = 4');
assert((float)$data['tongTien'] === 600000.0, '2 xe * 2 ngày * 150,000 = 600,000 đ');

// TEST 10: Kiểm tra dữ liệu trong cơ sở dữ liệu MySQL
echo "\nTEST 10: Kiểm tra bản ghi trong bảng CTSuDungDichVu và tồn kho trong ChiTietDichVu...\n";
$stmtCheck = $pdo->prepare("SELECT * FROM CTSuDungDichVu WHERE MaCTSDDV = ?");
$stmtCheck->execute([$data['maCTSDDV']]);
$rowDB = $stmtCheck->fetch(PDO::FETCH_ASSOC);

assert($rowDB !== false, 'Bản ghi CTSuDungDichVu phải tồn tại');
assert($rowDB['MaCTDV'] === 'CTDVThueXe01', 'MaCTDV phải là CTDVThueXe01');
assert($rowDB['MaPhieu'] === 'PP002', 'MaPhieu phải là PP002');
assert($rowDB['MaPhong'] === 'P002', 'MaPhong phải là P002');
assert((int)$rowDB['SoLuong'] === 4, 'SoLuong trong DB phải là 4');
assert((float)$rowDB['DonGia'] === 150000.0, 'DonGia trong DB phải là 150000');
assert($rowDB['ThoiGianNhan'] === '2026-10-15 15:00:00', 'ThoiGianNhan trong DB phải khớp');
assert($rowDB['ThoiGianTra'] === '2026-10-17 11:00:00', 'ThoiGianTra trong DB phải khớp');
echo "  ✓ Dữ liệu CTSuDungDichVu trong MySQL chuẩn xác 100%:\n";
echo "    - ThoiGianNhan: {$rowDB['ThoiGianNhan']}\n";
echo "    - ThoiGianTra:  {$rowDB['ThoiGianTra']}\n";
echo "    - SoLuong:      {$rowDB['SoLuong']}\n";
echo "    - DonGia:       " . number_format($rowDB['DonGia']) . " đ\n";

// Kiểm tra tồn kho sau khi trừ 2 xe
$stmtStock = $pdo->prepare("SELECT SoLuongTon FROM ChiTietDichVu WHERE MaCTDV = ?");
$stmtStock->execute(['CTDVThueXe01']);
$stockNow = (int)$stmtStock->fetchColumn();
assert($stockNow === 8, "Tồn kho xe phải giảm từ 10 xuống 8 (Thực tế: $stockNow)");
echo "  ✓ Tồn kho xe tay ga đã giảm đúng: 10 -> {$stockNow} xe\n";

// Dọn dẹp bản ghi test và phục hồi tồn kho
$pdo->prepare("DELETE FROM CTSuDungDichVu WHERE MaCTSDDV = ?")->execute([$data['maCTSDDV']]);
$pdo->prepare("UPDATE ChiTietDichVu SET SoLuongTon = 10 WHERE MaCTDV = ?")->execute(['CTDVThueXe01']);
echo "\n🧹 Đã dọn dẹp bản ghi test (MaCTSDDV: #{$data['maCTSDDV']}) và phục hồi tồn kho về 10.\n";

echo "\n🎉 TẤT CẢ 10/10 BÀI TEST CHỨC NĂNG THUÊ XE MÁY ĐÃ VƯỢT QUA XUẤT SẮC 100%!\n";
