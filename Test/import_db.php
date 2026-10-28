<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

echo "1. Kết nối tới MySQL...\n";
$pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
]);

echo "2. Drop & Recreate database hotel_booking...\n";
$pdo->exec("DROP DATABASE IF EXISTS hotel_booking;");
$pdo->exec("CREATE DATABASE hotel_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
$pdo->exec("USE hotel_booking;");

echo "3. Thực thi Database/hotel_booking.sql...\n";
$sqlSchema = file_get_contents(__DIR__ . '/../Database/hotel_booking.sql');

function executeMultiQuery(PDO $pdo, string $sql) {
    // Tách câu lệnh theo dấu ; ở cuối dòng
    $lines = explode("\n", $sql);
    $current = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '--') || $trimmed === '') {
            continue;
        }
        $current .= ' ' . $line;
        if (str_ends_with($trimmed, ';')) {
            $stmt = trim($current);
            $current = '';
            if (!empty($stmt) && !str_starts_with(strtoupper($stmt), 'SHOW TABLES')) {
                $pdo->exec($stmt);
            }
        }
    }
}

executeMultiQuery($pdo, $sqlSchema);
echo " -> Đã tạo xong tất cả các bảng schema!\n";

echo "4. Thực thi Database/data.sql...\n";
$sqlData = file_get_contents(__DIR__ . '/../Database/data.sql');
executeMultiQuery($pdo, $sqlData);
echo " -> Đã nạp xong toàn bộ dữ liệu mẫu!\n";

echo "5. Mã hóa mật khẩu tài khoản (hash passwords)...\n";
$stmt = $pdo->query("SELECT MaTK, MatKhau FROM TaiKhoan");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($accounts as $acc) {
    if (strlen($acc["MatKhau"]) < 60) {
        $hashed = password_hash($acc["MatKhau"], PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE TaiKhoan SET MatKhau = ? WHERE MaTK = ?");
        $update->execute([$hashed, $acc["MaTK"]]);
        echo " - Đã hash cho: " . $acc["MaTK"] . "\n";
    }
}

echo "\n🎉 HOÀN TẤT CẬP NHẬT DATABASE THÀNH CÔNG VÀ CHÍNH XÁC 100%!\n";
