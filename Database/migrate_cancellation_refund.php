<?php

declare(strict_types=1);

require_once __DIR__ . '/../Backend/vendor/autoload.php';
if (file_exists(__DIR__ . '/../Backend/.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend')->load();
}

require_once __DIR__ . '/../Backend/database/Database.php';

use Database\Database;

/**
 * Migration an toàn (idempotent) cho MySQL 8+ & MariaDB:
 * Thêm các cột hoàn tiền & hủy phòng nếu chưa tồn tại.
 */
$columnsToAdd = [
    'PhieuNhanPhong' => [
        'ThoiGianHuy' => 'DATETIME NULL AFTER LyDoHuy',
        'TyLeHoan'    => 'DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER ThoiGianHuy',
        'SoTienHoan'  => 'DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER TyLeHoan',
    ],
    'ChiTietPhieuNhanPhong' => [
        'ThoiGianHuy' => 'DATETIME NULL AFTER LyDoHuy',
        'TyLeHoan'    => 'DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER ThoiGianHuy',
        'PhiHuy'      => 'DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER TyLeHoan',
        'SoTienHoan'  => 'DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER PhiHuy',
    ],
    'ThanhToan' => [
        'SoTienHoan'   => 'DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER TrangThaiThanhToan',
        'NganHangHoan' => 'VARCHAR(100) NULL DEFAULT NULL AFTER SoTienHoan',
        'STKHoan'      => 'VARCHAR(50) NULL DEFAULT NULL AFTER NganHangHoan',
        'TenChuTKHoan' => 'VARCHAR(150) NULL DEFAULT NULL AFTER STKHoan',
        'LyDoHoan'     => 'TEXT NULL AFTER TenChuTKHoan',
        'ThoiGianHoan' => 'DATETIME NULL DEFAULT NULL AFTER LyDoHoan',
    ],
];

try {
    $db = Database::getConnection();
    $stmtCheck = $db->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");

    foreach ($columnsToAdd as $table => $cols) {
        foreach ($cols as $col => $definition) {
            $stmtCheck->execute([$table, $col]);
            if ((int)$stmtCheck->fetchColumn() > 0) {
                echo "[SKIP] {$table}.{$col} đã tồn tại.\n";
                continue;
            }
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$definition}");
            echo "[ADD]  {$table}.{$col}\n";
        }
    }

    echo "Migration hoàn tất!\n";
} catch (Throwable $e) {
    echo "Lỗi migration: " . $e->getMessage() . "\n";
    exit(1);
}
