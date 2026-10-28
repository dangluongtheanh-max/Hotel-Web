<?php
require_once __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

require_once __DIR__ . '/../Backend/Database/Database.php';
$db = \Database\Database::getConnection();

$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "TABLES IN DATABASE:\n";
print_r($tables);

foreach ($tables as $t) {
    if (stripos($t, 'phong') !== false || stripos($t, 'phieu') !== false || stripos($t, 'dat') !== false) {
        echo "\n=== TABLE $t ===\n";
        print_r($db->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC));
    }
}
