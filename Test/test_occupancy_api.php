<?php
require_once __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

$baseUrl = 'http://localhost:8000/Backend/public/index.php';

function testEndpoint($name, $url, $expectedCode = 200, $callback = null) {
    echo "--- Testing: $name ---\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);
    $pass = ($httpCode === $expectedCode);
    if ($pass && $callback) {
        $pass = $callback($json);
    }

    if ($pass) {
        echo "✅ PASSED (HTTP $httpCode)\n";
    } else {
        echo "❌ FAILED (Expected $expectedCode, got $httpCode)\n";
        echo "Response: " . substr($response, 0, 500) . "\n";
    }
    return $pass;
}

$allPassed = true;

// 1. Test thống kê công suất bình thường
$allPassed = testEndpoint(
    'GET /admin/thongkecongsuatphong (2026-10-01 -> 2026-11-30)',
    "$baseUrl/admin/thongkecongsuatphong?tu_ngay=2026-10-01&den_ngay=2026-11-30",
    200,
    function($json) {
        $data = $json['data']['data'] ?? $json['data'] ?? [];
        $tq = $data['tong_quan'] ?? [];
        $list = $data['danh_sach'] ?? [];
        $valid = isset($tq['cong_suat_trung_binh']) && count($list) >= 3;
        echo "   -> Tổng phòng: {$tq['tong_so_phong']}, Đêm có thể bán: {$tq['tong_dem_co_the_ban']}, Đêm thực tế: {$tq['tong_dem_thuc_te']}, CS trung bình: {$tq['cong_suat_trung_binh']}%\n";
        return $valid;
    }
) && $allPassed;

// 2. Test ngày bắt đầu > ngày kết thúc (Dòng sự kiện 4.1)
$allPassed = testEndpoint(
    'GET /admin/thongkecongsuatphong (Invalid dates: from > to)',
    "$baseUrl/admin/thongkecongsuatphong?tu_ngay=2026-12-01&den_ngay=2026-11-01",
    400,
    function($json) {
        $st = $json['data']['status'] ?? $json['status'] ?? '';
        return $st === 'error' || $json['success'] === false;
    }
) && $allPassed;

// 3. Test lọc theo loại phòng cụ thể
$allPassed = testEndpoint(
    'GET /admin/thongkecongsuatphong?ma_loai_phong=LP001',
    "$baseUrl/admin/thongkecongsuatphong?tu_ngay=2026-10-01&den_ngay=2026-11-30&ma_loai_phong=LP001",
    200,
    function($json) {
        $data = $json['data']['data'] ?? $json['data'] ?? [];
        $list = $data['danh_sach'] ?? [];
        return count($list) === 1 && $list[0]['ma_loai_phong'] === 'LP001';
    }
) && $allPassed;

// 4. Test loại phòng 0 phòng (LP004) -> công suất phòng phải là null / Không áp dụng (Dòng sự kiện 6.1)
$allPassed = testEndpoint(
    'Check N/A handling for 0 capacity room type (LP004)',
    "$baseUrl/admin/thongkecongsuatphong?tu_ngay=2026-10-01&den_ngay=2026-11-30",
    200,
    function($json) {
        $data = $json['data']['data'] ?? $json['data'] ?? [];
        $list = $data['danh_sach'] ?? [];
        foreach ($list as $item) {
            if ($item['ma_loai_phong'] === 'LP004') {
                return $item['so_dem_co_the_ban'] === 0 && $item['cong_suat_phong'] === null;
            }
        }
        return false;
    }
) && $allPassed;

// 5. Test kỳ không có lượt lưu trú nào (Dòng sự kiện 5.1: hiển thị 0%)
$allPassed = testEndpoint(
    'Check 0% handling for empty stay period (2020-01-01 -> 2020-01-10)',
    "$baseUrl/admin/thongkecongsuatphong?tu_ngay=2020-01-01&den_ngay=2020-01-10",
    200,
    function($json) {
        $data = $json['data']['data'] ?? $json['data'] ?? [];
        $tq = $data['tong_quan'] ?? [];
        echo "   -> CS thời điểm trống: {$tq['cong_suat_trung_binh']}%\n";
        return (int)$tq['tong_dem_thuc_te'] === 0 && (float)$tq['cong_suat_trung_binh'] == 0.0;
    }
) && $allPassed;

echo "\n" . ($allPassed ? "🎉 ALL API TESTS PASSED!\n" : "❌ SOME TESTS FAILED!\n");
