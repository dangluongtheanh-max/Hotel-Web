<?php
echo "=== KIỂM THỬ CÁC ENDPOINT HTTP API LỄ TÂN TRÊN LOCALHOST:8000 ===\n\n";

$baseUrl = 'http://localhost:8000/Backend/public/index.php';

function postJson($url, $data) {
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'ignore_errors' => true,
            'timeout' => 5
        ]
    ];
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    return json_decode($result, true);
}

// 1. Test xem danh sách phiếu
$dsRes = postJson($baseUrl . '/letan/xemdsphieu', []);
echo "1. POST /letan/xemdsphieu: ";
if ($dsRes && isset($dsRes['success']) && $dsRes['success']) {
    $count = count($dsRes['data'] ?? []);
    echo "OK (Tìm thấy {$count} phiếu)\n";
} else {
    echo "LỖI hoặc Server không phản hồi\n";
}

// 2. Test xem chi tiết phiếu
$maPhieu = 'PP1025';
$ctRes = postJson($baseUrl . '/letan/xemctphieu', ['maPhieu' => $maPhieu]);
echo "2. POST /letan/xemctphieu (Phiếu #{$maPhieu}): ";
if ($ctRes && isset($ctRes['success']) && $ctRes['success']) {
    echo "OK (Khách: " . ($ctRes['data']['phieu']['TenKH'] ?? 'N/A') . ")\n";
} else {
    echo "LỖI: " . ($ctRes['message'] ?? 'Không phản hồi') . "\n";
}

// 3. Test tính tiền checkout
$billRes = postJson($baseUrl . '/letan/tinhtiencheckout', ['maPhieu' => $maPhieu]);
echo "3. POST /letan/tinhtiencheckout (Phiếu #{$maPhieu}): ";
if ($billRes && isset($billRes['success']) && $billRes['success']) {
    $b = $billRes['data'] ?? [];
    echo "OK (Tổng chi phí: " . number_format($b['tongChiPhiToanBo'] ?? 0) . " đ, Phụ phí trễ: " . number_format($b['tongPhuPhiTreGio'] ?? 0) . " đ)\n";
} else {
    echo "LỖI: " . ($billRes['message'] ?? 'Không phản hồi') . "\n";
}

// 4. Test API đổi phòng người lưu trú với dữ liệu kiểm tra ràng buộc
$doiRes = postJson($baseUrl . '/letan/doiphongnguoiluutru', [
    'maPhieu' => $maPhieu,
    'maNLT' => 'NLT_TEST_999',
    'maPhongMoi' => 'P999'
]);
echo "4. POST /letan/doiphongnguoiluutru (Bắt lỗi phòng không tồn tại): ";
if ($doiRes && isset($doiRes['success']) && $doiRes['success'] === false) {
    echo "OK (Bắt lỗi chính xác: " . $doiRes['message'] . ")\n";
} else {
    echo "LỖI\n";
}

// 5. Test API cập nhật giấy tờ khách lưu trú với dữ liệu kiểm tra
$capNhatRes = postJson($baseUrl . '/letan/capnhatkhach', [
    'maPhieu' => $maPhieu,
    'maNLT' => 'NLT_TEST_999',
    'hoTen' => ''
]);
echo "5. POST /letan/capnhatkhach (Bắt lỗi thiếu họ tên): ";
if ($capNhatRes && isset($capNhatRes['success']) && $capNhatRes['success'] === false) {
    echo "OK (Bắt lỗi chính xác: " . $capNhatRes['message'] . ")\n";
} else {
    echo "LỖI\n";
}

echo "\n=== HOÀN TẤT KIỂM THỬ API HTTP ===\n";
