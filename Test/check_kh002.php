<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

use App\DanhGia\Repositories\DanhGiaRepository;
use App\DanhGia\Services\DanhGiaService;
use App\DatPhong\Repositories\DatPhongRepository;
use App\DatPhong\Services\DatPhongService;
use App\DatPhong\Services\LichSuDatPhongService;
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
echo "1. KIỂM TRA LỊCH SỬ ĐẶT PHÒNG CỦA KH002 (TK005 - khach02)\n";
echo "====================================================================\n";
$lichSuService = new LichSuDatPhongService($datPhongRepo, $khachHangRepo);
$history = $lichSuService->layLichSuDatPhong('TK005', 20, 0);
echo "Tổng số phiếu trong lịch sử của KH002: " . count($history) . " phiếu:\n";
foreach ($history as $item) {
    echo "  * Mã phiếu: {$item['MaPhieu']} | Ngày lập: {$item['NgayLap']} | Trạng thái: {$item['TrangThai']} | Phòng: {$item['DanhSachPhong']} | Tổng phòng: {$item['TongSoPhong']} | Đủ ĐK: {$item['SoPhongDuDieuKien']} | Đã ĐG: {$item['SoPhongDaDanhGia']}\n";
}

echo "\n====================================================================\n";
echo "2. KIỂM TRA XEM CHI TIẾT 2 PHIẾU ĐÃ TRẢ PHÒNG (PP011, PP012)\n";
echo "====================================================================\n";
foreach (['PP011', 'PP012'] as $maPhieu) {
    $detail = $datPhongService->xemChiTietPhieu($maPhieu, 'TK005');
    echo "\n>>> Phiếu [{$maPhieu}]:\n";
    echo "  - Trạng thái phiếu: " . $detail['Phieu']['TrangThai'] . "\n";
    echo "  - Khách hàng: " . $detail['Phieu']['TenKH'] . " (Mã KH: " . $detail['Phieu']['MaKH'] . ")\n";
    echo "  - Hóa đơn tổng tiền: " . number_format($detail['HoaDon']['TongTienSauVAT']) . " đ\n";
    echo "  - Danh sách phòng (" . count($detail['DanhSachPhong']) . " phòng):\n";
    foreach ($detail['DanhSachPhong'] as $p) {
        $daDG = $p['DaDanhGia'] ? "ĐÃ ĐÁNH GIÁ" : "CHƯA ĐÁNH GIÁ (SẴN SÀNG TEST ĐÁNH GIÁ)";
        echo "    * Phòng [{$p['MaPhong']}] ({$p['TenLoaiPhong']}): Trạng thái = {$p['TrangThai']} | Đánh giá = {$daDG}\n";
    }
}
