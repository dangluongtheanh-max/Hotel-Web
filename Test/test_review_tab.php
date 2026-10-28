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
use Database\Database;

$html = file_get_contents(__DIR__ . '/../Frontend/lichsudatphong.html');
echo "1. KIỂM TRA FILE GIAO DIỆN lichsudatphong.html:\n";
echo "   - Kích thước HTML: " . strlen($html) . " bytes\n";
echo "   - Tab 'Đánh Giá Của Tôi' (#tabBtnReview): " . (strpos($html, 'id="tabBtnReview"') !== false ? "PASS" : "FAIL") . "\n";
echo "   - Bộ lọc phụ Đánh giá (#reviewFilterBar): " . (strpos($html, 'id="reviewFilterBar"') !== false ? "PASS" : "FAIL") . "\n";
echo "   - Modal Đánh giá (#reviewModal): " . (strpos($html, 'id="reviewModal"') !== false ? "PASS" : "FAIL") . "\n";
echo "   - Dropdown chọn phòng (#reviewRoomSelect): " . (strpos($html, 'id="reviewRoomSelect"') !== false ? "PASS" : "FAIL") . "\n";
echo "   - Hộp chọn sao (#starRatingBox): " . (strpos($html, 'id="starRatingBox"') !== false ? "PASS" : "FAIL") . "\n";
echo "   - Ô nhận xét (#reviewNoiDung): " . (strpos($html, 'id="reviewNoiDung"') !== false ? "PASS" : "FAIL") . "\n";
echo "   - Nút gửi đánh giá (#btnSubmitReview): " . (strpos($html, 'id="btnSubmitReview"') !== false ? "PASS" : "FAIL") . "\n";
echo "   - Hàm openReviewModalForSlip: " . (strpos($html, 'function openReviewModalForSlip') !== false ? "PASS" : "FAIL") . "\n";
echo "   - Hàm submitReview: " . (strpos($html, 'function submitReview') !== false ? "PASS" : "FAIL") . "\n";
echo "   - Hàm updateSlipReviewStateInList: " . (strpos($html, 'function updateSlipReviewStateInList') !== false ? "PASS" : "FAIL") . "\n";

echo "\n2. KIỂM TRA DỮ LIỆU BACKEND CHO KH002:\n";
$datPhongRepo = new DatPhongRepository(new Database());
$khachHangRepo = new KhachHangRepository(new Database());
$lichSuService = new LichSuDatPhongService($datPhongRepo, $khachHangRepo);
$items = $lichSuService->layLichSuDatPhong('TK005', 20, 0);

echo "   - Tổng số phiếu của KH002: " . count($items) . "\n";
foreach ($items as $item) {
    $eligible = (int)($item['SoPhongDuDieuKien'] ?? 0);
    $reviewed = (int)($item['SoPhongDaDanhGia'] ?? 0);
    $total = (int)($item['TongSoPhong'] ?? 1);

    if ($eligible === 0) {
        $statusText = "Chưa đủ điều kiện (Chưa trả phòng)";
    } elseif ($reviewed === 0) {
        $statusText = "Chưa đánh giá";
    } elseif ($reviewed < $eligible) {
        $statusText = "Đã đánh giá {$reviewed}/{$eligible}";
    } else {
        $statusText = "Đã đánh giá {$reviewed}/{$eligible}";
    }

    echo "     * Phiếu {$item['MaPhieu']}: Trạng thái đánh giá = [{$statusText}] | Tổng phòng: {$total} | Đủ ĐK: {$eligible} | Đã ĐG: {$reviewed}\n";
}
