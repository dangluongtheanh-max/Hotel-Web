<?php
require_once __DIR__ . '/../Backend/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend')->load();

echo "====================================================\n";
echo "TEST KHACHHANG PROFILE & PASSWORD CHANGE APIS\n";
echo "====================================================\n";

$baseUrl = 'http://localhost:8000/Backend/public/index.php';

// Helper function to make HTTP POST requests
function postJson($url, $data) {
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\nAccept: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'ignore_errors' => true
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return json_decode($result, true);
}

// 1. Test Login as KH001 (khach01 / 123456)
echo "\n1. Test dangNhap as khach01:\n";
$loginRes = postJson("$baseUrl/taikhoan/dangnhap", [
    'tenDangNhap' => 'khach01',
    'matKhau' => '123456'
]);
echo "Login status: " . ($loginRes['success'] ? 'SUCCESS' : 'FAILED') . "\n";
echo "UserData: ";
print_r($loginRes['data'] ?? []);
$maTK = $loginRes['data']['MaTK'] ?? 'TK003';

// 2. Test Xem Thong Tin Ca Nhan
echo "\n2. Test xemThongTinCaNhan for MaTK: $maTK:\n";
$viewRes = postJson("$baseUrl/khachhang/xemthongtincanhan", [
    'maTK' => $maTK
]);
echo "View profile status: " . ($viewRes['success'] ? 'SUCCESS' : 'FAILED') . "\n";
echo "Profile details: ";
print_r($viewRes['data'] ?? []);

// 3. Test Cap Nhat Thong Tin Ca Nhan
echo "\n3. Test capNhatThongTinCaNhan:\n";
$updateRes = postJson("$baseUrl/khachhang/capnhatthongtincanhan", [
    'maTK' => $maTK,
    'hoTen' => 'Nguyen Van An Updated',
    'sdt' => '0911000001',
    'email' => 'an.updated@gmail.com'
]);
echo "Update profile status: " . ($updateRes['success'] ? 'SUCCESS' : 'FAILED') . "\n";
echo "Message: " . ($updateRes['data']['message'] ?? $updateRes['message'] ?? '') . "\n";
echo "New HoTen: " . ($updateRes['data']['data']['HoTen'] ?? '') . ", New Email: " . ($updateRes['data']['data']['Email'] ?? '') . "\n";

// Revert back so database stays clean
$revertRes = postJson("$baseUrl/khachhang/capnhatthongtincanhan", [
    'maTK' => $maTK,
    'hoTen' => 'Nguyen Van An',
    'sdt' => '0911000001',
    'email' => 'an@gmail.com'
]);
echo "Reverted profile back to original: " . ($revertRes['success'] ? 'OK' : 'FAILED') . "\n";

// 4. Test Doi Mat Khau (Sai mat khau cu)
echo "\n4. Test doiMatKhau with wrong old password:\n";
$pwdFail = postJson("$baseUrl/khachhang/doimatkhau", [
    'maTK' => $maTK,
    'matKhauCu' => 'wrong_password_123',
    'matKhauMoi' => 'newpassword123',
    'xacNhanMatKhau' => 'newpassword123'
]);
echo "Expected fail status: " . ($pwdFail['success'] === false ? 'PASS (Correctly rejected)' : 'FAIL') . "\n";
echo "Error message: " . ($pwdFail['error'] ?? $pwdFail['message'] ?? '') . "\n";

// 5. Test Doi Mat Khau thanh cong va doi lai
echo "\n5. Test doiMatKhau success flow:\n";
$pwdOk = postJson("$baseUrl/khachhang/doimatkhau", [
    'maTK' => $maTK,
    'matKhauCu' => '123456',
    'matKhauMoi' => 'abcdef123',
    'xacNhanMatKhau' => 'abcdef123'
]);
echo "Change password status: " . ($pwdOk['success'] ? 'SUCCESS' : 'FAILED') . "\n";

// Revert password back to 123456
$pwdRevert = postJson("$baseUrl/khachhang/doimatkhau", [
    'maTK' => $maTK,
    'matKhauCu' => 'abcdef123',
    'matKhauMoi' => '123456',
    'xacNhanMatKhau' => '123456'
]);
echo "Revert password status: " . ($pwdRevert['success'] ? 'SUCCESS' : 'FAILED') . "\n";

echo "\nALL BACKEND API TESTS COMPLETED!\n";
