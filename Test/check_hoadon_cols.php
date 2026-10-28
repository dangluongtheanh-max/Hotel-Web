<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend'))->load();
$stmt = Database\Database::getConnection()->query('DESCRIBE HoaDon');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
$stmt2 = Database\Database::getConnection()->query('DESCRIBE ThanhToan');
print_r($stmt2->fetchAll(PDO::FETCH_COLUMN));
