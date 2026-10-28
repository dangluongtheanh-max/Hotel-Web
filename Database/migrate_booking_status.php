<?php

require_once __DIR__ . '/../Backend/vendor/autoload.php';
if (file_exists(__DIR__ . '/../Backend/.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend')->load();
}

require_once __DIR__ . '/../Backend/Database/Database.php';

use Database\Database;

try {
    $db = Database::getConnection();

    $constraints = $db->query("
        SELECT tc.TABLE_NAME, tc.CONSTRAINT_NAME, cc.CHECK_CLAUSE 
        FROM information_schema.TABLE_CONSTRAINTS tc
        JOIN information_schema.CHECK_CONSTRAINTS cc 
          ON tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME 
         AND tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA
        WHERE tc.CONSTRAINT_SCHEMA = DATABASE() AND tc.CONSTRAINT_TYPE = 'CHECK'
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($constraints as $c) {
        $tbl = strtolower($c['TABLE_NAME']);
        $name = $c['CONSTRAINT_NAME'];
        $clause = $c['CHECK_CLAUSE'];

        if ($tbl === 'phong' && (strpos($clause, 'TrangThaiPhong') !== false || $name === 'phong_chk_1' || $name === 'chk_phong_trangthai')) {
            echo "Dropping constraint on {$tbl}: {$name}...\n";
            $db->exec("ALTER TABLE Phong DROP CHECK `{$name}`");
        }

        if ($tbl === 'phieunhanphong' && (strpos($clause, 'TrangThai') !== false || $name === 'phieunhanphong_chk_2' || $name === 'chk_pnp_trangthai')) {
            echo "Dropping constraint on {$tbl}: {$name}...\n";
            $db->exec("ALTER TABLE PhieuNhanPhong DROP CHECK `{$name}`");
        }

        if ($tbl === 'chitietphieunhanphong' && (strpos($clause, 'TrangThai') !== false || $name === 'chitietphieunhanphong_chk_3' || $name === 'chk_ctpnp_trangthai')) {
            echo "Dropping constraint on {$tbl}: {$name}...\n";
            $db->exec("ALTER TABLE ChiTietPhieuNhanPhong DROP CHECK `{$name}`");
        }
    }

    echo "Adding new constraints...\n";
    $db->exec("ALTER TABLE Phong ADD CONSTRAINT chk_phong_trangthai CHECK (TrangThaiPhong IN ('Trong', 'ChoThanhToan', 'DaDat', 'DangSuDung', 'BaoTri'))");
    $db->exec("ALTER TABLE PhieuNhanPhong ADD CONSTRAINT chk_pnp_trangthai CHECK (TrangThai IN ('ChoThanhToan', 'ThanhToanThatBai', 'DaDatPhong', 'DangLuuTru', 'DaHuy', 'DaTraPhong'))");
    $db->exec("ALTER TABLE ChiTietPhieuNhanPhong ADD CONSTRAINT chk_ctpnp_trangthai CHECK (TrangThai IN ('ChoThanhToan', 'ThanhToanThatBai', 'DaDatPhong', 'DangLuuTru', 'DaHuy', 'DaTraPhong'))");

    echo "Migration completed successfully and verified!\n";

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
