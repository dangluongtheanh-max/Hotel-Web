<?php
require_once __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->safeLoad();
require_once __DIR__ . '/../Backend/Database/Database.php';
$db = \Database\Database::getConnection();
$stmt = $db->query("DESCRIBE ChiTietDichVu");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . ' (' . $r['Type'] . ')' . PHP_EOL;
}
