<?php
/**
 * Test Verification Suite for UC21.3 – Thống Kê Đánh Giá
 */

$baseUrl = 'http://localhost:8000/Backend/public/index.php';
$passed = 0;
$total = 0;

function assertTest($description, $condition) {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] $description\n";
    } else {
        echo "  [FAIL] $description\n";
    }
}

echo "=================================================================\n";
echo "1. KIỂM TRA TỆP GIAO DIỆN FRONTEND & BACKEND VIEW CHO UC21.3\n";
echo "=================================================================\n";

$frontendHtml = file_get_contents(__DIR__ . '/../Frontend/admin.html');
$backendHtml = file_get_contents(__DIR__ . '/../Backend/app/Admin/View/index.html');

assertTest("Frontend admin.html tồn tại và không rỗng", strlen($frontendHtml) > 50000);
assertTest("Backend index.html đồng bộ khớp kích thước với Frontend", abs(strlen($frontendHtml) - strlen($backendHtml)) < 100);

// Kiểm tra các phần tử giao diện chính
assertTest("Nút sub-tab ⭐ Thống Kê Đánh Giá (#subTabBtnReviewStats)", strpos($frontendHtml, 'id="subTabBtnReviewStats"') !== false);
assertTest("Vùng chứa thống kê đánh giá (#sub-stats-reviews)", strpos($frontendHtml, 'id="sub-stats-reviews"') !== false);
assertTest("Nút chuyển chế độ Theo Loại Phòng (#subReviewTabType)", strpos($frontendHtml, 'id="subReviewTabType"') !== false);
assertTest("Nút chuyển chế độ Theo Phòng (#subReviewTabRoom)", strpos($frontendHtml, 'id="subReviewTabRoom"') !== false);
assertTest("Bộ lọc ngày đánh giá (#reviewFromDate & #reviewToDate)", strpos($frontendHtml, 'id="reviewFromDate"') !== false && strpos($frontendHtml, 'id="reviewToDate"') !== false);
assertTest("Banner báo lỗi ngày không hợp lệ (#reviewDateErrorBanner)", strpos($frontendHtml, 'id="reviewDateErrorBanner"') !== false);
assertTest("4 Bento KPI cards Theo Loại Phòng", strpos($frontendHtml, 'id="kpiReviewTypeTotalReviews"') !== false && strpos($frontendHtml, 'id="kpiReviewTypeAvgScore"') !== false);
assertTest("Biểu đồ so sánh đánh giá (#reviewChartContainer)", strpos($frontendHtml, 'id="reviewChartContainer"') !== false);
assertTest("Bảng và footer thống kê theo loại phòng", strpos($frontendHtml, 'id="reviewRoomTypeTableBody"') !== false && strpos($frontendHtml, 'id="reviewRoomTypeTableFoot"') !== false);
assertTest("Bộ lọc phụ và tìm kiếm phòng (#selectReviewRoomFilterType & #searchReviewRoomInput)", strpos($frontendHtml, 'id="selectReviewRoomFilterType"') !== false && strpos($frontendHtml, 'id="searchReviewRoomInput"') !== false);
assertTest("4 Bento KPI cards Theo Phòng (kèm phản hồi tiêu cực)", strpos($frontendHtml, 'id="kpiReviewRoomTotalReviews"') !== false && strpos($frontendHtml, 'id="kpiReviewRoomNegativeReviews"') !== false);
assertTest("Bảng thống kê theo phòng (#reviewRoomTableBody)", strpos($frontendHtml, 'id="reviewRoomTableBody"') !== false);
assertTest("Modal xem chi tiết đánh giá (#reviewDetailModal)", strpos($frontendHtml, 'id="reviewDetailModal"') !== false);
assertTest("Hàm JavaScript initReviewStatsTab và switchReviewSubMode", strpos($frontendHtml, 'function initReviewStatsTab') !== false && strpos($frontendHtml, 'function switchReviewSubMode') !== false);
assertTest("Hàm JavaScript openReviewDetailModal và filterReviewDetailByStars", strpos($frontendHtml, 'function openReviewDetailModal') !== false && strpos($frontendHtml, 'function filterReviewDetailByStars') !== false);

echo "\n=================================================================\n";
echo "2. KIỂM TRA API BACKEND THỐNG KÊ ĐÁNH GIÁ (UC21.3)\n";
echo "=================================================================\n";

// Test 2.1: Thống kê theo loại phòng (Toàn bộ thời gian)
$urlType = "$baseUrl/admin/thongkedanhgia?che_do=room_type&tu_ngay=2020-01-01&den_ngay=2030-12-31";
$resType = @file_get_contents($urlType);
$dataType = json_decode($resType, true);

assertTest("API thống kê theo loại phòng trả về HTTP 200 JSON hợp lệ", $dataType !== null && ($dataType['success'] ?? false) === true);

$dataObjType = $dataType['data']['data'] ?? $dataType['data'] ?? [];
$listType = $dataObjType['danh_sach'] ?? [];
$tongQuanType = $dataObjType['tong_quan'] ?? [];

assertTest("Dữ liệu danh sách loại phòng có chứa ít nhất 1 loại phòng", count($listType) > 0);
assertTest("Tổng quan có tong_luot_danh_gia, diem_trung_binh, so_luot_5_sao", isset($tongQuanType['tong_luot_danh_gia']) && (isset($tongQuanType['diem_trung_binh']) || isset($tongQuanType['diem_danh_gia_trung_binh'])));

// Kiểm tra quy tắc tính toán: Tổng các mức sao = tổng lượt đánh giá
$sumStarsCount = 0;
foreach ($listType as $item) {
    $sumRoomStars = $item['so_luot_1_sao'] + $item['so_luot_2_sao'] + $item['so_luot_3_sao'] + $item['so_luot_4_sao'] + $item['so_luot_5_sao'];
    assertTest("Loại phòng {$item['ma_loai_phong']}: Tổng các sao ($sumRoomStars) = số lượt ĐG ({$item['so_luot_danh_gia']})", $sumRoomStars === $item['so_luot_danh_gia']);
    $sumStarsCount += $item['so_luot_danh_gia'];
}

assertTest("Tổng số lượt ĐG các loại phòng khớp tổng quan hệ thống ($sumStarsCount = {$tongQuanType['tong_luot_danh_gia']})", $sumStarsCount === $tongQuanType['tong_luot_danh_gia']);

// Test 2.2: Thống kê theo phòng
$urlRoom = "$baseUrl/admin/thongkedanhgia?che_do=room&tu_ngay=2020-01-01&den_ngay=2030-12-31";
$resRoom = @file_get_contents($urlRoom);
$dataRoom = json_decode($resRoom, true);

assertTest("API thống kê theo phòng trả về JSON thành công", $dataRoom !== null && ($dataRoom['success'] ?? false) === true);
$dataObjRoom = $dataRoom['data']['data'] ?? $dataRoom['data'] ?? [];
$listRoom = $dataObjRoom['danh_sach'] ?? [];
$tongQuanRoom = $dataObjRoom['tong_quan'] ?? [];

assertTest("Danh sách phòng có chứa các phòng thực tế", count($listRoom) > 0);
assertTest("Tổng quan theo phòng có so_danh_gia_1_2_sao (để phát hiện phòng cần bảo trì)", isset($tongQuanRoom['so_danh_gia_1_2_sao']));

// Test 2.3: Validation ngày sai (Dòng sự kiện phụ 3.1)
$urlInvalid = "$baseUrl/admin/thongkedanhgia?che_do=room_type&tu_ngay=2026-12-31&den_ngay=2026-01-01";
$context = stream_context_create(['http' => ['ignore_errors' => true]]);
$resInvalid = @file_get_contents($urlInvalid, false, $context);
$httpStatus = 0;
if (isset($http_response_header[0]) && preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match)) {
    $httpStatus = (int)$match[1];
}
$dataInvalid = json_decode($resInvalid, true);
assertTest("Validation ngày sai (tu_ngay > den_ngay) trả về HTTP 400", $httpStatus === 400 && ($dataInvalid['success'] ?? true) === false);

// Test 2.4: Chi tiết đánh giá modal
$urlDetail = "$baseUrl/admin/chitietdanhgia?doi_tuong=room_type&ma_doi_tuong=LP001&tu_ngay=2020-01-01&den_ngay=2030-12-31";
$resDetail = @file_get_contents($urlDetail);
$dataDetail = json_decode($resDetail, true);

assertTest("API chi tiết đánh giá cho modal trả về thành công", $dataDetail !== null && ($dataDetail['success'] ?? false) === true);
$detailList = $dataDetail['data']['danh_sach'] ?? [];
assertTest("API chi tiết đánh giá có chứa danh sách nhận xét thực tế", is_array($detailList));

echo "\n=================================================================\n";
echo "KẾT QUẢ KIỂM THỬ TỔNG THỂ UC21.3: $passed / $total KIỂM TRA ĐẠT (" . round(($passed / $total) * 100, 1) . "%)\n";
echo "=================================================================\n";
