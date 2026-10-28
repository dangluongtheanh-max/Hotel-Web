<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

use App\DanhGia\Repositories\DanhGiaRepository;
use App\DanhGia\Services\DanhGiaService;
use App\DatPhong\Repositories\DatPhongRepository;
use App\DatPhong\Services\DatPhongService;
use App\KhachHang\Repositories\KhachHangRepository;
use App\Phong\Repositories\PhongRepository;
use App\Phong\Services\PhongService;
use Database\Database;

$danhGiaRepo = new DanhGiaRepository();
$khachHangRepo = new KhachHangRepository(new Database());
$danhGiaService = new DanhGiaService($danhGiaRepo, $khachHangRepo);

$phongRepo = new PhongRepository(new Database());
$phongService = new PhongService($phongRepo);
$datPhongRepo = new DatPhongRepository(new Database());
$datPhongService = new DatPhongService(
    $datPhongRepo,
    $phongService,
    null,
    null,
    null,
    $khachHangRepo,
    null,
    null,
    $danhGiaService
);

echo "====================================================================\n";
echo "KIỂM TRA 3 PHIẾU ĐÃ CHECK-OUT NHƯNG CHƯA ĐÁNH GIÁ (PP004, PP005, PP006)\n";
echo "====================================================================\n";

foreach (['PP004', 'PP005', 'PP006'] as $maPhieu) {
    $detail = $datPhongService->xemChiTietPhieu($maPhieu, 'TK003');
    echo "\n>>> Phiếu [{$maPhieu}]:\n";
    echo "Trạng thái phiếu: " . $detail['Phieu']['TrangThai'] . "\n";
    echo "Khách hàng: " . $detail['Phieu']['TenKH'] . " (Mã KH: " . $detail['Phieu']['MaKH'] . ")\n";
    echo "Hóa đơn tổng tiền: " . number_format($detail['HoaDon']['TongTienSauVAT']) . " đ\n";
    echo "Danh sách phòng (" . count($detail['DanhSachPhong']) . " phòng):\n";
    foreach ($detail['DanhSachPhong'] as $p) {
        $daDG = $p['DaDanhGia'] ? "ĐÃ ĐÁNH GIÁ" : "CHƯA ĐÁNH GIÁ";
        echo "  - Phòng [{$p['MaPhong']}] ({$p['TenLoaiPhong']}): Trạng thái = {$p['TrangThai']} | Đánh giá = {$daDG}\n";
    }
}

echo "\n====================================================================\n";
echo "KIỂM TRA DANH SÁCH LỊCH SỬ ĐẶT PHÒNG CỦA KHÁCH 01 (TK003)\n";
echo "====================================================================\n";
use App\DatPhong\Services\LichSuDatPhongService;

$lichSuService = new LichSuDatPhongService($datPhongRepo, $khachHangRepo);
$history = $lichSuService->layLichSuDatPhong('TK003', 10, 0);
echo "Tổng số phiếu trong lịch sử của KH001 (khach01): " . count($history) . "\n";
foreach ($history as $item) {
    echo "  * Mã phiếu: {$item['MaPhieu']} | Ngày lập: {$item['NgayLap']} | Trạng thái: {$item['TrangThai']} | Phòng: {$item['DanhSachMaPhong']} | Tổng tiền: " . number_format($item['TongTien']) . " đ\n";
}

