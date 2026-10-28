<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/database/Database.php';

use Database\Database;
$db = Database::getConnection();

echo "=== TABLES ===\n";
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

echo "\n=== DESCRIBE hoadon ===\n";
try {
    $cols = $db->query('DESCRIBE hoadon')->fetchAll(PDO::FETCH_ASSOC);
    print_r($cols);
} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage() . "\n";
}

echo "\n=== DESCRIBE related tables ===\n";
foreach (['chitiethoadon', 'cthd', 'chitiet_hoadon', 'chitiethd', 'chitiethoadonphong'] as $t) {
    try {
        $cols = $db->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
        echo "Table $t exists:\n";
        print_r($cols);
    } catch (Exception $e) {
        // không có
    }
}
