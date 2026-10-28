<?php

require __DIR__ . "/../Backend/vendor/autoload.php";

use Dotenv\Dotenv;
use Shared\Mailer;

$dotenv = Dotenv::createImmutable(__DIR__ . "/../Backend");
$dotenv->load();

date_default_timezone_set('Asia/Ho_Chi_Minh');

echo "=========================================================\n";
echo "KIỂM TRA KẾT NỐI VÀ GỬI EMAIL THẬT QUA GMAIL SMTP\n";
echo "=========================================================\n\n";

$mailConfig = require __DIR__ . "/../Backend/config/Mail.php";

$username = $mailConfig['username'];
$password = $mailConfig['password'];

if (empty($username) || empty($password)) {
    echo "[CHƯA CẤU HÌNH GMAIL SMTP]\n";
    echo "- File: Backend/.env\n";
    echo "- Trạng thái: MAIL_USERNAME hoặc MAIL_PASSWORD đang để trống.\n\n";
    echo "HƯỚNG DẪN BẬT VÀ LẤY MẬT KHẨU ỨNG DỤNG GMAIL:\n";
    echo " 1. Truy cập https://myaccount.google.com/security\n";
    echo " 2. Bật 'Xác minh 2 bước' (2-Step Verification) nếu chưa bật.\n";
    echo " 3. Tìm kiếm 'Mật khẩu ứng dụng' (App passwords).\n";
    echo " 4. Tạo tên mới (vd: 'Hotel Web') và sao chép mã 16 chữ cái được cấp.\n";
    echo " 5. Mở file Backend/.env, điền:\n";
    echo "    MAIL_USERNAME=email_cua_ban@gmail.com\n";
    echo "    MAIL_PASSWORD=16_chu_cai_mat_khau_ung_dung\n";
    echo "    MAIL_FROM_ADDRESS=email_cua_ban@gmail.com\n";
    echo " 6. Chạy lại lệnh: php Test/test_smtp.php [email_nhan_thu]\n";
    exit(0);
}

$recipient = $argv[1] ?? $username;
echo "Đang thử gửi email OTP đến: {$recipient} ...\n";

try {
    $testOtp = sprintf('%06d', random_int(0, 999999));
    $success = Mailer::sendOTP($recipient, $testOtp, 5, "Khách hàng Thử nghiệm");

    if ($success) {
        echo "\n[THÀNH CÔNG] Đã gửi email OTP {$testOtp} đến {$recipient}!\n";
        echo "Vui lòng mở hộp thư Gmail của bạn (hoặc thư rác) để kiểm tra email nhận được.\n";
    } else {
        echo "\n[THẤT BẠI] Mailer trả về false.\n";
    }
} catch (\Exception $e) {
    echo "\n[LỖI GỬI MAIL]: " . $e->getMessage() . "\n";
    echo "Gợi ý kiểm tra:\n";
    echo " 1. Đảm bảo mật khẩu ứng dụng Gmail (App password) gồm đúng 16 chữ cái.\n";
    echo " 2. Đảm bảo máy tính có kết nối mạng Internet.\n";
}
