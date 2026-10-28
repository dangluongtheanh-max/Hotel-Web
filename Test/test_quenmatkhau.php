<?php

require __DIR__ . "/../Backend/vendor/autoload.php";

use Dotenv\Dotenv;
use Database\Database;
use App\TaiKhoan\Repositories\TaiKhoanRepository;
use App\TaiKhoan\Repositories\QuenMatKhauRepository;
use App\TaiKhoan\Services\TaiKhoanService;
use App\TaiKhoan\Services\QuenMatKhauService;

$dotenv = Dotenv::createImmutable(__DIR__ . "/../Backend");
$dotenv->load();

date_default_timezone_set('Asia/Ho_Chi_Minh');

$db = Database::getConnection();
$taiKhoanRepo = new TaiKhoanRepository(new Database());
$taiKhoanService = new TaiKhoanService($taiKhoanRepo);

$repo = new QuenMatKhauRepository(new Database());
$service = new QuenMatKhauService($repo);

echo "=========================================================\n";
echo "KIỂM THỬ TỰ ĐỘNG CHỨC NĂNG QUÊN MẬT KHẨU (BACKEND & DB)\n";
echo "=========================================================\n\n";

$testEmail = "test_otp_khach@hotelbooking.com";
$testUser  = "test_otp_user";
$oldPass   = "MatKhauCu123";
$newPass   = "MatKhauMoi456";

// Chuẩn bị dữ liệu kiểm thử
$db->exec("DELETE FROM TaiKhoanOTP WHERE Email = '{$testEmail}'");
$db->exec("DELETE FROM KhachHang WHERE MaKH = 'KH_TEST_OTP'");
$db->exec("DELETE FROM TaiKhoan WHERE TenDangNhap = '{$testUser}'");

$hashedOldPass = password_hash($oldPass, PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT INTO TaiKhoan (MaTK, TenDangNhap, MatKhau, Email, SDT, TrangThaiTaiKhoan, VaiTro) 
                      VALUES ('TK_TEST_OTP', ?, ?, ?, '0999888777', 'HoatDong', 'KhachHang')");
$stmt->execute([$testUser, $hashedOldPass, $testEmail]);

$stmtKH = $db->prepare("INSERT INTO KhachHang (MaKH, HoTen, MaTK) VALUES ('KH_TEST_OTP', 'Khách Test OTP', 'TK_TEST_OTP')");
$stmtKH->execute();

$passedTests = 0;
$totalTests = 0;

function assertTest(string $name, bool $condition, string $detail = '') {
    global $passedTests, $totalTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo " [PASS] $name\n";
    } else {
        echo " [FAIL] $name: $detail\n";
    }
}

// -----------------------------------------------------------------
// Test 1: Email không hợp lệ
// -----------------------------------------------------------------
try {
    $service->quenMatKhau(['action' => 'gui_otp', 'email' => 'invalid-email-format']);
    assertTest("Test 1: Validate email sai định dạng", false, "Không ném ngoại lệ");
} catch (\Exception $e) {
    assertTest("Test 1: Validate email sai định dạng", str_contains($e->getMessage(), "không đúng định dạng"));
}

// -----------------------------------------------------------------
// Test 2: Email không tồn tại -> Thông báo an toàn, không tạo OTP
// -----------------------------------------------------------------
$res2 = $service->quenMatKhau(['action' => 'gui_otp', 'email' => 'nonexistent@gmail.com']);
$stmtCheck = $db->prepare("SELECT COUNT(*) FROM TaiKhoanOTP WHERE Email = 'nonexistent@gmail.com'");
$stmtCheck->execute();
$count2 = (int)$stmtCheck->fetchColumn();
assertTest("Test 2: Email không tồn tại trả về thông báo an toàn", $res2['success'] === true && $count2 === 0);

// -----------------------------------------------------------------
// Test 3: Tài khoản Admin không được nhận OTP quên mật khẩu khách hàng
// -----------------------------------------------------------------
$res3 = $service->quenMatKhau(['action' => 'gui_otp', 'email' => 'admin@hotelbooking.com']);
$stmtCheck3 = $db->prepare("SELECT COUNT(*) FROM TaiKhoanOTP WHERE Email = 'admin@hotelbooking.com'");
$stmtCheck3->execute();
$count3 = (int)$stmtCheck3->fetchColumn();
assertTest("Test 3: Email Admin không được tạo OTP khách hàng", $res3['success'] === true && $count3 === 0);

// -----------------------------------------------------------------
// Test 4: Tạo OTP trực tiếp trong Repository và kiểm tra hash an toàn
// -----------------------------------------------------------------
$otpRaw = "048291"; // Có số 0 ở đầu
$otpHash = hash('sha256', $otpRaw);
$expires = date('Y-m-d H:i:s', time() + 300);

$repo->invalidateOldOTPs($testEmail);
$repo->createOTPRequest($testEmail, 'TK_TEST_OTP', $otpHash, $expires);

$record = $repo->getLatestActiveOTP($testEmail);
assertTest("Test 4: Lưu OTP an toàn dưới dạng Hash SHA-256", $record !== null && $record['OTPHash'] === $otpHash && $record['OTPHash'] !== $otpRaw);

// -----------------------------------------------------------------
// Test 5: Nhập sai OTP -> tăng số lần thử
// -----------------------------------------------------------------
try {
    $service->quenMatKhau(['action' => 'xacminh_otp', 'email' => $testEmail, 'otp' => '999999']);
    assertTest("Test 5: Nhập sai OTP", false, "Không ném ngoại lệ khi OTP sai");
} catch (\Exception $e) {
    $recordAfter = $repo->getLatestActiveOTP($testEmail);
    assertTest("Test 5: Nhập sai OTP tăng số lần thử", (int)$recordAfter['SoLanThu'] === 1 && str_contains($e->getMessage(), "còn 4 lần thử"));
}

// -----------------------------------------------------------------
// Test 6: Nhập sai 5 lần -> Hủy bỏ yêu cầu (Brute-force protection)
// -----------------------------------------------------------------
for ($i = 2; $i <= 5; $i++) {
    try {
        $service->quenMatKhau(['action' => 'xacminh_otp', 'email' => $testEmail, 'otp' => '999999']);
    } catch (\Exception $e) {
        // Bắt lỗi
    }
}
$stmtBlocked = $db->prepare("SELECT TrangThai, SoLanThu FROM TaiKhoanOTP WHERE MaOTP = ?");
$stmtBlocked->execute([$record['MaOTP']]);
$blockedRecord = $stmtBlocked->fetch(PDO::FETCH_ASSOC);
assertTest("Test 6: Nhập sai 5 lần thì OTP bị khóa (DaHuy)", $blockedRecord['TrangThai'] === 'DaHuy' && (int)$blockedRecord['SoLanThu'] >= 5);

// -----------------------------------------------------------------
// Test 7: Tạo OTP mới và xác minh chính xác -> Nhận ResetToken
// -----------------------------------------------------------------
$validOtp = "012345";
$validHash = hash('sha256', $validOtp);
$repo->createOTPRequest($testEmail, 'TK_TEST_OTP', $validHash, date('Y-m-d H:i:s', time() + 300));

$verifyRes = $service->quenMatKhau(['action' => 'xacminh_otp', 'email' => $testEmail, 'otp' => $validOtp]);
$resetToken = $verifyRes['data']['reset_token'] ?? '';
assertTest("Test 7: Xác minh OTP thành công cấp ResetToken 64-char", !empty($resetToken) && strlen($resetToken) === 64);

// -----------------------------------------------------------------
// Test 8: Đặt mật khẩu mới khi chưa có token hoặc token sai -> Bị chặn
// -----------------------------------------------------------------
try {
    $service->quenMatKhau([
        'action' => 'datlai_matkhau',
        'email' => $testEmail,
        'reset_token' => 'fake_invalid_token',
        'matKhauMoi' => $newPass,
        'xacNhanMatKhau' => $newPass
    ]);
    assertTest("Test 8: Chặn bypass đổi mật khẩu với token giả", false);
} catch (\Exception $e) {
    assertTest("Test 8: Chặn bypass đổi mật khẩu với token giả", str_contains($e->getMessage(), "không hợp lệ"));
}

// -----------------------------------------------------------------
// Test 9: Hai mật khẩu không khớp -> Báo lỗi
// -----------------------------------------------------------------
try {
    $service->quenMatKhau([
        'action' => 'datlai_matkhau',
        'email' => $testEmail,
        'reset_token' => $resetToken,
        'matKhauMoi' => $newPass,
        'xacNhanMatKhau' => 'MismatchPass999'
    ]);
    assertTest("Test 9: Chặn khi 2 mật khẩu không khớp", false);
} catch (\Exception $e) {
    assertTest("Test 9: Chặn khi 2 mật khẩu không khớp", str_contains($e->getMessage(), "không khớp"));
}

// -----------------------------------------------------------------
// Test 10: Đổi mật khẩu thành công với ResetToken hợp lệ
// -----------------------------------------------------------------
$resetRes = $service->quenMatKhau([
    'action' => 'datlai_matkhau',
    'email' => $testEmail,
    'reset_token' => $resetToken,
    'matKhauMoi' => $newPass,
    'xacNhanMatKhau' => $newPass
]);
assertTest("Test 10: Đổi mật khẩu thành công", $resetRes['success'] === true);

// -----------------------------------------------------------------
// Test 11: Đăng nhập bằng mật khẩu mới -> Thành công
// -----------------------------------------------------------------
try {
    $loginNew = $taiKhoanService->dangNhap(['tenDangNhap' => $testUser, 'matKhau' => $newPass]);
    assertTest("Test 11: Đăng nhập thành công với mật khẩu mới", !empty($loginNew['TenDangNhap']));
} catch (\Exception $e) {
    assertTest("Test 11: Đăng nhập thành công với mật khẩu mới", false, $e->getMessage());
}

// -----------------------------------------------------------------
// Test 12: Đăng nhập bằng mật khẩu cũ -> Thất bại
// -----------------------------------------------------------------
try {
    $taiKhoanService->dangNhap(['tenDangNhap' => $testUser, 'matKhau' => $oldPass]);
    assertTest("Test 12: Mật khẩu cũ bị từ chối", false);
} catch (\Exception $e) {
    assertTest("Test 12: Mật khẩu cũ bị từ chối", str_contains($e->getMessage(), "Sai mật khẩu"));
}

// -----------------------------------------------------------------
// Test 13: Thử dùng lại ResetToken lần thứ 2 -> Bị từ chối (Single-use)
// -----------------------------------------------------------------
try {
    $service->quenMatKhau([
        'action' => 'datlai_matkhau',
        'email' => $testEmail,
        'reset_token' => $resetToken,
        'matKhauMoi' => 'MatKhauMoiNua789',
        'xacNhanMatKhau' => 'MatKhauMoiNua789'
    ]);
    assertTest("Test 13: ResetToken chỉ được dùng 1 lần (Single-use)", false);
} catch (\Exception $e) {
    assertTest("Test 13: ResetToken chỉ được dùng 1 lần (Single-use)", str_contains($e->getMessage(), "không hợp lệ hoặc đã hết hạn"));
}

// Dọn dẹp dữ liệu kiểm thử
$db->exec("DELETE FROM TaiKhoanOTP WHERE Email = '{$testEmail}'");
$db->exec("DELETE FROM KhachHang WHERE MaKH = 'KH_TEST_OTP'");
$db->exec("DELETE FROM TaiKhoan WHERE TenDangNhap = '{$testUser}'");

echo "\n=========================================================\n";
echo "KẾT QUẢ: ĐÃ ĐẠT {$passedTests}/{$totalTests} KIỂM THỬ TỰ ĐỘNG!\n";
echo "=========================================================\n";
