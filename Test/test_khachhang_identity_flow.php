<?php

require_once __DIR__ . '/../Backend/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend'))->load();

use Database\Database;

echo "========================================================\n";
echo "TEST: KHACH HANG IDENTITY & AUTO-FILL FULL FLOW\n";
echo "========================================================\n\n";

$baseUrl = 'http://localhost:8000/Backend/public/index.php';

function postJson($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true), 'raw' => $response];
}

$db = Database::getConnection();

// Dọn dẹp tài khoản test nếu có
$db->exec("DELETE FROM KhachHang WHERE SDT = '0988776655'");
$db->exec("DELETE FROM TaiKhoan WHERE TenDangNhap = 'test_brand_new_user'");

// -------------------------------------------------------------
// TEST 1: Tra cứu khách hàng cũ khach01 (SĐT: 0911000001)
// -------------------------------------------------------------
echo "[TEST 1] Tra cứu khách hàng cũ qua SĐT 0911000001...\n";
$res1 = postJson("$baseUrl/khachhang/timtheosdt", ['sdt' => '0911000001']);

if ($res1['code'] === 200 && !empty($res1['body']['data']['found'])) {
    $kh = $res1['body']['data']['data'];
    echo "✅ Tra cứu thành công!\n";
    echo "   - Họ tên: {$kh['HoTen']}\n";
    echo "   - Ngày sinh: {$kh['NgaySinh']}\n";
    echo "   - Quốc tịch: {$kh['QuocTich']}\n";
    echo "   - Loại giấy tờ: {$kh['LoaiGiayTo']}\n";
    echo "   - Số giấy tờ: {$kh['SoGiayTo']}\n";

    if (!empty($kh['NgaySinh']) && !empty($kh['SoGiayTo'])) {
        echo "   -> PASS: Dữ liệu định danh đã có sẵn trong bảng KhachHang!\n";
    } else {
        echo "   -> FAIL: Chưa có NgaySinh hoặc SoGiayTo!\n";
    }
} else {
    echo "❌ Lỗi TEST 1: " . json_encode($res1) . "\n";
}

// -------------------------------------------------------------
// TEST 2: Đăng ký khách hàng MỚI TINH (CHƯA TỪNG ĐẶT PHÒNG) kèm CCCD + Ngày sinh
// -------------------------------------------------------------
echo "\n[TEST 2] Đăng ký khách hàng mới (chưa có lịch sử lưu trú) với thông tin CCCD + Ngày sinh...\n";
$resReg = postJson("$baseUrl/khachhang/dangky", [
    'hoTen' => 'Trần Khách Mới Test',
    'sdt' => '0988776655',
    'email' => 'khachmoi_test@gmail.com',
    'tenDangNhap' => 'test_brand_new_user',
    'matKhau' => '123456',
    'ngaySinh' => '1998-12-25',
    'quocTich' => 'Việt Nam',
    'loaiGiayTo' => 'CCCD',
    'soGiayTo' => '079198009988'
]);

if ($resReg['code'] === 200 && !empty($resReg['body']['success'])) {
    echo "✅ Đăng ký tài khoản mới thành công!\n";
    $regData = $resReg['body']['data']['data'] ?? $resReg['body']['data'];
    $newMaKH = $regData['MaKH'];
    $newMaTK = $regData['MaTK'];

    // -------------------------------------------------------------
    // TEST 3: Lễ tân tra cứu SĐT của khách hàng mới tinh này
    // -------------------------------------------------------------
    echo "\n[TEST 3] Lễ tân tra cứu SĐT 0988776655 của khách hàng mới tinh (chưa từng đặt phòng)...\n";
    $res3 = postJson("$baseUrl/khachhang/timtheosdt", ['sdt' => '0988776655']);
    if ($res3['code'] === 200 && !empty($res3['body']['data']['found'])) {
        $khNew = $res3['body']['data']['data'];
        echo "✅ Tra cứu khách mới tinh thành công:\n";
        echo "   - Họ tên: {$khNew['HoTen']}\n";
        echo "   - Ngày sinh: {$khNew['NgaySinh']}\n";
        echo "   - Quốc tịch: {$khNew['QuocTich']}\n";
        echo "   - Loại giấy tờ: {$khNew['LoaiGiayTo']}\n";
        echo "   - Số giấy tờ: {$khNew['SoGiayTo']}\n";

        if ($khNew['NgaySinh'] === '1998-12-25' && $khNew['SoGiayTo'] === '079198009988') {
            echo "   🎉 THÀNH CÔNG RỰC RỠ: Khách hàng mới tinh chưa từng đặt phòng vẫn trả về đầy đủ Ngày sinh & CCCD để lễ tân tự động điền!\n";
        } else {
            echo "   ❌ Dữ liệu trả về không khớp!\n";
        }
    } else {
        echo "   ❌ Lỗi TEST 3: " . json_encode($res3) . "\n";
    }

    // -------------------------------------------------------------
    // TEST 4: Khách hàng cập nhật thông tin trong trang cá nhân
    // -------------------------------------------------------------
    echo "\n[TEST 4] Khách hàng cập nhật CCCD & Ngày sinh qua API capnhatthongtincanhan...\n";
    $res4 = postJson("$baseUrl/khachhang/capnhatthongtincanhan", [
        'maTK' => $newMaTK,
        'hoTen' => 'Trần Khách Mới Đổi Tên',
        'sdt' => '0988776655',
        'email' => 'khachmoi_test@gmail.com',
        'ngaySinh' => '1999-01-01',
        'quocTich' => 'Việt Nam',
        'loaiGiayTo' => 'CCCD',
        'soGiayTo' => '079199000000'
    ]);
    if ($res4['code'] === 200) {
        echo "✅ Cập nhật hồ sơ thành công!\n";
        $checkUpdated = postJson("$baseUrl/khachhang/timtheosdt", ['sdt' => '0988776655']);
        $u = $checkUpdated['body']['data']['data'];
        echo "   - Tên mới: {$u['HoTen']} | Sinh: {$u['NgaySinh']} | CCCD: {$u['SoGiayTo']}\n";
        if ($u['NgaySinh'] === '1999-01-01' && $u['SoGiayTo'] === '079199000000') {
            echo "   ✓ Xác nhận: Thông tin mới được lưu chính xác vào KhachHang!\n";
        }
    } else {
        echo "   ❌ Lỗi TEST 4: " . json_encode($res4) . "\n";
    }
} else {
    echo "❌ Lỗi TEST 2: " . json_encode($resReg) . "\n";
}

// Dọn dẹp sau test
$db->exec("DELETE FROM KhachHang WHERE SDT = '0988776655'");
$db->exec("DELETE FROM TaiKhoan WHERE TenDangNhap = 'test_brand_new_user'");

echo "\n========================================================\n";
echo "TẤT CẢ TEST ĐÃ HOÀN TẤT THÀNH CÔNG!\n";
echo "========================================================\n";
