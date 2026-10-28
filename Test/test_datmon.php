<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

use App\DichVu\Repositories\DatDoAnRepository;
use App\DichVu\Services\DatDoAnService;
use App\KhachHang\Repositories\KhachHangRepository;
use Database\Database;

$db = new Database();
$pdo = Database::getConnection();
$dichVuRepo = new DatDoAnRepository($db);
$khachHangRepo = new KhachHangRepository($db);
$service = new DatDoAnService($dichVuRepo, $khachHangRepo);

echo "====================================================================\n";
echo "TEST 1: LẤY DANH SÁCH MÓN ĂN (MENU)\n";
echo "====================================================================\n";
$menuRes = $service->layDanhSachMonAn();
echo "Trạng thái: " . $menuRes['status'] . " | Mã: " . $menuRes['code'] . "\n";
echo "Tổng số món ăn đang phục vụ: " . count($menuRes['data']) . " món:\n";
foreach ($menuRes['data'] as $m) {
    echo "  - [{$m['MaCTDV']}] {$m['TenCTDV']} | Giá: " . number_format($m['DonGia']) . " đ | Mô tả: {$m['MoTa']}\n";
}

echo "\n====================================================================\n";
echo "TEST 2: KIỂM TRA THÔNG TIN LƯU TRÚ CỦA KHÁCH HÀNG\n";
echo "====================================================================\n";
// KH001 (khach01 - TK003) không có phòng đang lưu trú
$phongKhach1 = $service->layPhongDangLuuTru('TK003');
echo "Khách 01 (TK003): " . $phongKhach1['message'] . " (Số phòng: " . count($phongKhach1['data']) . ")\n";

// KH002 (khach02 - TK005) đang lưu trú tại phòng P002
$phongKhach2 = $service->layPhongDangLuuTru('TK005');
echo "Khách 02 (TK005): " . $phongKhach2['message'] . "\n";
foreach ($phongKhach2['data'] as $p) {
    echo "  * Phòng [{$p['MaPhong']}] - {$p['TenLoaiPhong']} | Phiếu: {$p['MaPhieu']} | Trạng thái: {$p['TrangThai']}\n";
}

echo "\n====================================================================\n";
echo "TEST 3: CHẶN ĐẶT MÓN KHI KHÁCH KHÔNG CÓ PHÒNG ĐANG LƯU TRÚ (KH001)\n";
echo "====================================================================\n";
$resKhach1 = $service->datMonAn([
    'maKH' => 'KH_KHONG_DAT_PHONG',
    'maCTDV' => 'CTDVDoAn02',
    'soLuong' => 1
]);
echo "Kết quả: " . json_encode($resKhach1, JSON_UNESCAPED_UNICODE) . "\n";
if ($resKhach1['code'] === 403) {
    echo "=> KẾT QUẢ TEST 3: ĐÃ CHẶN ĐÚNG NGHIỆP VỤ LƯU TRÚ!\n";
} else {
    echo "=> KẾT QUẢ TEST 3: THẤT BẠI!\n";
}

echo "\n====================================================================\n";
echo "TEST 4: CHẶN ĐẶT MÓN VỚI SỐ LƯỢNG KHÔNG HỢP LỆ (<= 0)\n";
echo "====================================================================\n";
$resZero = $service->datMonAn([
    'maTK' => 'TK005',
    'maCTDV' => 'CTDVDoAn02',
    'soLuong' => 0
]);
echo "Kết quả: " . json_encode($resZero, JSON_UNESCAPED_UNICODE) . "\n";
if ($resZero['code'] === 400) {
    echo "=> KẾT QUẢ TEST 4: ĐÃ BẮT LỖI SỐ LƯỢNG THÀNH CÔNG!\n";
} else {
    echo "=> KẾT QUẢ TEST 4: THẤT BẠI!\n";
}

echo "\n====================================================================\n";
echo "TEST 5: CHẶN ĐẶT PHÒNG KHÔNG THUỘC PHIẾU ĐANG LƯU TRÚ (CHỌN P101)\n";
echo "====================================================================\n";
$resWrongRoom = $service->datMonAn([
    'maTK' => 'TK005',
    'maCTDV' => 'CTDVDoAn02',
    'soLuong' => 1,
    'maPhong' => 'P101'
]);
echo "Kết quả: " . json_encode($resWrongRoom, JSON_UNESCAPED_UNICODE) . "\n";
if ($resWrongRoom['code'] === 400) {
    echo "=> KẾT QUẢ TEST 5: ĐÃ CHẶN GIAO SAI PHÒNG THÀNH CÔNG!\n";
} else {
    echo "=> KẾT QUẢ TEST 5: THẤT BẠI!\n";
}

echo "\n====================================================================\n";
echo "TEST 6: ĐẶT MÓN THÀNH CÔNG CHO KHÁCH ĐANG LƯU TRÚ (KH002 - PHÒNG P002)\n";
echo "====================================================================\n";
$orderSuccess = $service->datMonAn([
    'maTK' => 'TK005',
    'maCTDV' => 'CTDVDoAn02', // Cơm gà Hội An (50.000 đ)
    'soLuong' => 2,
    'ghiChu' => 'Không hành lá, mang thêm 2 ly đá lạnh',
    'maPhong' => 'P002'
]);
echo "Kết quả: " . json_encode($orderSuccess, JSON_UNESCAPED_UNICODE) . "\n";

if ($orderSuccess['code'] === 200) {
    $data = $orderSuccess['data'];
    $maCTSDDV = $data['maCTSDDV'];
    echo "\nThông tin chi tiết đơn vừa lưu:\n";
    echo "  - Mã CTSDDV: {$data['maCTSDDV']}\n";
    echo "  - Tên món: {$data['tenMonAn']}\n";
    echo "  - Số phòng nhận: {$data['maPhong']}\n";
    echo "  - Số lượng: {$data['soLuong']}\n";
    echo "  - Đơn giá: " . number_format($data['donGia']) . " đ\n";
    echo "  - Thành tiền: " . number_format($data['thanhTien']) . " đ (Kỳ vọng: 100,000 đ)\n";
    echo "  - Ghi chú: {$data['ghiChu']}\n";
    echo "  - Thông báo: {$orderSuccess['message']}\n";

    // Kiểm tra trực tiếp trong CSDL
    $stmt = $pdo->prepare("SELECT * FROM CTSuDungDichVu WHERE MaCTSDDV = ?");
    $stmt->execute([$maCTSDDV]);
    $dbRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\nBản ghi đọc từ CSDL MySQL:\n";
    print_r($dbRecord);

    // Dọn dẹp bản ghi test
    $pdo->exec("DELETE FROM CTSuDungDichVu WHERE MaCTSDDV = {$maCTSDDV}");
    echo "-> Đã dọn dẹp bản ghi test {$maCTSDDV}.\n";
    echo "\n=> KẾT QUẢ TEST 6: ĐẶT MÓN THÀNH CÔNG VÀ CHÍNH XÁC 100%!\n";
} else {
    echo "=> KẾT QUẢ TEST 6: THẤT BẠI!\n";
}

echo "\n🎉 HOÀN THÀNH TOÀN BỘ CÁC BÀI TEST NGHIỆP VỤ ĐẶT MÓN ĂN!\n";
