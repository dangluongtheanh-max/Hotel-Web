<?php
require __DIR__ . "/../Backend/vendor/autoload.php";
use Dotenv\Dotenv;
use Database\Database;

$dotenv = Dotenv::createImmutable(__DIR__ . "/../Backend");
$dotenv->load();
$db = Database::getConnection();

$stmt = $db->query("SELECT MaTK, MatKhau FROM TaiKhoan");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($accounts as $acc) {
    if (strlen($acc["MatKhau"]) < 60) {
        $hashed = password_hash($acc["MatKhau"], PASSWORD_DEFAULT);
        $update = $db->prepare("UPDATE TaiKhoan SET MatKhau = ? WHERE MaTK = ?");
        $update->execute([$hashed, $acc["MaTK"]]);
        echo "Da ma hoa (hash) mat khau cho: " . $acc["MaTK"] . "\n";
    }
}
echo "Xong!\n";

