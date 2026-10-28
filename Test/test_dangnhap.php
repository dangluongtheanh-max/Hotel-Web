<?php
require __DIR__ . "/../Backend/vendor/autoload.php";
use Dotenv\Dotenv;
use Database\Database;
use App\TaiKhoan\Services\TaiKhoanService;
use App\TaiKhoan\Repositories\TaiKhoanRepository;

$dotenv = Dotenv::createImmutable(__DIR__ . "/../Backend");
$dotenv->load();

$db = Database::getConnection();

// Xóa user test cũ nếu có
$db->exec("DELETE FROM TaiKhoan WHERE TenDangNhap = 'testuser'");

// Tạo user test mới
$hashedPassword = password_hash("123456", PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT INTO TaiKhoan (MaTK, TenDangNhap, MatKhau, Email, SDT, TrangThaiTaiKhoan, VaiTro) VALUES ('TK_TEST', 'testuser', ?, 'test@gmail.com', '0987654321', 'HoatDong', 'KhachHang')");
$stmt->execute([$hashedPassword]);

$repo = new TaiKhoanRepository(new Database());
$service = new TaiKhoanService($repo);

echo "Test 3: Dang nhap thanh cong\n";
try {
    $res = $service->dangNhap(["TenDangNhap" => "testuser", "MatKhau" => "123456"]);
    print_r($res);
} catch (\Exception $e) {
    echo " -> LỖI: " . $e->getMessage() . "\n";
}

echo "\nTest 4: Sai mat khau\n";
try {
    $service->dangNhap(["TenDangNhap" => "testuser", "MatKhau" => "sai_mat_khau"]);
} catch (\Exception $e) {
    echo " -> " . $e->getMessage() . "\n";
}

// Dọn dẹp
$db->exec("DELETE FROM TaiKhoan WHERE TenDangNhap = 'testuser'");

