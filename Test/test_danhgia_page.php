<?php
/**
 * Test kiểm tra tính toàn vẹn của trang riêng biệt Frontend/danhgia.html
 */

$htmlPath = __DIR__ . '/../Frontend/danhgia.html';
if (!file_exists($htmlPath)) {
    echo "LỖI: Không tìm thấy file Frontend/danhgia.html\n";
    exit(1);
}

$html = file_get_contents($htmlPath);

echo "1. KIỂM TRA FILE GIAO DIỆN danhgia.html:\n";
echo "   - Kích thước HTML: " . strlen($html) . " bytes\n";
echo "   - Menu 'Đánh giá của tôi' active: " . (strpos($html, 'nav-link active') !== false && strpos($html, 'danhgia.html') !== false ? "PASS\n" : "FAIL\n");
echo "   - Bộ lọc phụ (#reviewFilterBar): " . (strpos($html, 'id="reviewFilterBar"') !== false ? "PASS\n" : "FAIL\n");
echo "   - Modal Đánh giá (#reviewModal): " . (strpos($html, 'id="reviewModal"') !== false ? "PASS\n" : "FAIL\n");
echo "   - Dropdown chọn phòng (#reviewRoomSelect): " . (strpos($html, 'id="reviewRoomSelect"') !== false ? "PASS\n" : "FAIL\n");
echo "   - Hộp chọn sao (#starRatingBox): " . (strpos($html, 'id="starRatingBox"') !== false ? "PASS\n" : "FAIL\n");
echo "   - Ô nhận xét (#reviewNoiDung): " . (strpos($html, 'id="reviewNoiDung"') !== false ? "PASS\n" : "FAIL\n");
echo "   - Nút gửi đánh giá (#btnSubmitReview): " . (strpos($html, 'id="btnSubmitReview"') !== false ? "PASS\n" : "FAIL\n");
echo "   - Hàm openReviewModalForSlip: " . (strpos($html, 'function openReviewModalForSlip') !== false ? "PASS\n" : "FAIL\n");
echo "   - Hàm submitReview: " . (strpos($html, 'function submitReview') !== false ? "PASS\n" : "FAIL\n");
echo "   - Hàm updateSlipReviewStateInList: " . (strpos($html, 'function updateSlipReviewStateInList') !== false ? "PASS\n" : "FAIL\n");

echo "\n2. KIỂM TRA TOP-NAV ĐỒNG BỘ TRÊN CÁC TRANG:\n";
$pages = [
    'datphong.html' => __DIR__ . '/../Frontend/datphong.html',
    'dichvu.html' => __DIR__ . '/../Frontend/dichvu.html',
    'lichsudatphong.html' => __DIR__ . '/../Frontend/lichsudatphong.html',
    'danhgia.html' => __DIR__ . '/../Frontend/danhgia.html',
    'chitietphieu.html' => __DIR__ . '/../Frontend/chitietphieu.html',
];

foreach ($pages as $name => $path) {
    if (!file_exists($path)) {
        echo "   - $name: KHÔNG TÌM THẤY FILE\n";
        continue;
    }
    $c = file_get_contents($path);
    $hasLink = (strpos($c, 'danhgia.html') !== false);
    echo "   - $name có link tới danhgia.html: " . ($hasLink ? "PASS\n" : "FAIL\n");
}
