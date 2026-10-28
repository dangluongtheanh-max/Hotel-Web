<?php
require_once __DIR__ . '/../Backend/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend')->load();
require_once __DIR__ . '/../Backend/Database/Database.php';

$db = Database\Database::getConnection();
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Tables in DB:\n";
print_r($tables);
