<?php

require_once __DIR__ . '/../Backend/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend'))->load();

use Database\Database;

$db = Database::getConnection();

echo "========================================================\n";
echo "MIGRATION: ADD IDENTITY COLUMNS TO KhachHang TABLE\n";
echo "========================================================\n\n";

// 1. Kiểm tra và thêm các cột vào bảng KhachHang
$cols = $db->query("SHOW COLUMNS FROM KhachHang")->fetchAll(PDO::FETCH_COLUMN);

$columnsToAdd = [
    'NgaySinh' => 'DATE NULL AFTER SDT',
    'QuocTich' => "VARCHAR(100) DEFAULT 'Việt Nam' AFTER NgaySinh",
    'LoaiGiayTo' => "VARCHAR(50) DEFAULT 'CCCD' AFTER QuocTich",
    'SoGiayTo' => 'VARCHAR(50) NULL AFTER LoaiGiayTo'
];

foreach ($columnsToAdd as $colName => $colDef) {
    if (!in_array($colName, $cols)) {
        echo "-> Thêm cột `{$colName}` vào bảng KhachHang...\n";
        $db->exec("ALTER TABLE KhachHang ADD COLUMN {$colName} {$colDef}");
        echo "   ✅ Đã thêm cột `{$colName}` thành công!\n";
    } else {
        echo "   ✓ Cột `{$colName}` đã tồn tại trong bảng KhachHang.\n";
    }
}

// 2. Đồng bộ dữ liệu lưu trú gần nhất vào các tài khoản khách hàng hiện có (theo yêu cầu A3)
echo "\n-> Tiến hành đồng bộ thông tin định danh cũ từ bảng NguoiLuuTru vào KhachHang...\n";
$stmtSync = $db->query("
    UPDATE KhachHang kh
    SET 
        NgaySinh = COALESCE(kh.NgaySinh, (
            SELECT nlt.NgaySinh FROM NguoiLuuTru nlt 
            JOIN PhieuNhanPhong pnp ON nlt.MaPhieu = pnp.MaPhieu 
            WHERE pnp.MaKH = kh.MaKH 
            ORDER BY (CASE WHEN nlt.HoTen = kh.HoTen THEN 0 ELSE 1 END), pnp.NgayLap DESC LIMIT 1
        )),
        QuocTich = COALESCE(kh.QuocTich, (
            SELECT nlt.QuocTich FROM NguoiLuuTru nlt 
            JOIN PhieuNhanPhong pnp ON nlt.MaPhieu = pnp.MaPhieu 
            WHERE pnp.MaKH = kh.MaKH 
            ORDER BY (CASE WHEN nlt.HoTen = kh.HoTen THEN 0 ELSE 1 END), pnp.NgayLap DESC LIMIT 1
        ), 'Việt Nam'),
        LoaiGiayTo = COALESCE(kh.LoaiGiayTo, (
            SELECT nlt.LoaiGiayTo FROM NguoiLuuTru nlt 
            JOIN PhieuNhanPhong pnp ON nlt.MaPhieu = pnp.MaPhieu 
            WHERE pnp.MaKH = kh.MaKH 
            ORDER BY (CASE WHEN nlt.HoTen = kh.HoTen THEN 0 ELSE 1 END), pnp.NgayLap DESC LIMIT 1
        ), 'CCCD'),
        SoGiayTo = COALESCE(kh.SoGiayTo, (
            SELECT nlt.SoGiayTo FROM NguoiLuuTru nlt 
            JOIN PhieuNhanPhong pnp ON nlt.MaPhieu = pnp.MaPhieu 
            WHERE pnp.MaKH = kh.MaKH 
            ORDER BY (CASE WHEN nlt.HoTen = kh.HoTen THEN 0 ELSE 1 END), pnp.NgayLap DESC LIMIT 1
        ))
    WHERE kh.SoGiayTo IS NULL OR kh.NgaySinh IS NULL
");

echo "✅ Hoàn tất đồng bộ dữ liệu!\n\n";

// 3. Hiển thị danh sách khách hàng sau khi cập nhật
echo "=== DANH SÁCH KHÁCH HÀNG SAU CẬP NHẬT (MẪU) ===\n";
$stmtCheck = $db->query("SELECT MaKH, HoTen, SDT, NgaySinh, QuocTich, LoaiGiayTo, SoGiayTo, MaTK FROM KhachHang WHERE MaTK IS NOT NULL LIMIT 5");
$customers = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);
foreach ($customers as $c) {
    echo "• [{$c['MaKH']}] {$c['HoTen']} | SDT: {$c['SDT']} | Sinh: " . ($c['NgaySinh'] ?: 'Chưa có') . " | {$c['LoaiGiayTo']}: " . ($c['SoGiayTo'] ?: 'Chưa có') . " | QT: {$c['QuocTich']}\n";
}

echo "\nMigration hoàn thành xuất sắc!\n";
