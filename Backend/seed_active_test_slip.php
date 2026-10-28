<?php
require __DIR__ . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__)->load();
require __DIR__ . '/database/Database.php';

use Database\Database;
$db = Database::getConnection();

$maPhieu = 'PP889';
$maHD = 'HD889';
$maTT = 'TT889';
$maKH = 'KH002'; // Đặng Lương Thế Anh
$maLeTan = 'LT001'; // Pham Thi Hoa

// Dọn dẹp nếu có
$db->exec("DELETE FROM ThanhToan WHERE MaHD = '{$maHD}'");
$db->exec("DELETE FROM CTHD WHERE MaPhieu = '{$maPhieu}'");
$db->exec("DELETE FROM HoaDon WHERE MaPhieu = '{$maPhieu}'");
$db->exec("DELETE FROM NguoiLuuTru WHERE MaPhieu = '{$maPhieu}'");
$db->exec("DELETE FROM ChiTietPhieuNhanPhong WHERE MaPhieu = '{$maPhieu}'");
$db->exec("DELETE FROM PhieuNhanPhong WHERE MaPhieu = '{$maPhieu}'");

// 1. PhieuNhanPhong (DangLuuTru)
$ngayLap = date('Y-m-d', strtotime('-1 day'));
$stmt = $db->prepare("INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH, MaLeTan) VALUES (?, ?, 'TrucTiep', 'DangLuuTru', ?, ?)");
$stmt->execute([$maPhieu, $ngayLap, $maKH, $maLeTan]);

$now = time();
$thoiGianNhan = date('Y-m-d 14:00:00', strtotime('-1 day'));

// P203: Đúng giờ -> hẹn trả sau 2 giờ nữa
$traP203 = date('Y-m-d H:i:s', $now + 2 * 3600);

// P204: Trễ 20 phút -> nằm trong ân hạn 30 phút -> Phụ phí 0 đ
$traP204 = date('Y-m-d H:i:s', $now - 20 * 60);

// P302: Trễ 60 phút -> vượt ân hạn 30 phút -> Phạt 10% đơn giá phòng = 120,000 đ
$traP302 = date('Y-m-d H:i:s', $now - 60 * 60);

$phongs = [
    ['maPhong' => 'P203', 'gia' => 800000, 'tra' => $traP203, 'nlt' => 'NLT889_1', 'ten' => 'Đặng Lương Thế Anh', 'cccd' => '079200000011'],
    ['maPhong' => 'P204', 'gia' => 800000, 'tra' => $traP204, 'nlt' => 'NLT889_2', 'ten' => 'Trần Văn Bình', 'cccd' => '079200000012'],
    ['maPhong' => 'P302', 'gia' => 1200000, 'tra' => $traP302, 'nlt' => 'NLT889_3', 'ten' => 'Lê Thị Cẩm', 'cccd' => '079200000013']
];

$tongTien = 0;
foreach ($phongs as $p) {
    $tongTien += $p['gia'];

    // ChiTietPhieuNhanPhong
    $stmt = $db->prepare("
        INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai, MaLeTanCheckin)
        VALUES (?, ?, ?, ?, 2, 'DangLuuTru', ?)
    ");
    $stmt->execute([$maPhieu, $p['maPhong'], $thoiGianNhan, $p['tra'], $maLeTan]);

    // Phong sang DangSuDung
    $db->prepare("UPDATE Phong SET TrangThaiPhong = 'DangSuDung' WHERE MaPhong = ?")->execute([$p['maPhong']]);

    // NguoiLuuTru
    $stmt = $db->prepare("
        INSERT INTO NguoiLuuTru (MaNguoiLuuTru, HoTen, NgaySinh, QuocTich, LoaiGiayTo, SoGiayTo, GhiChu, MaPhieu, MaPhong)
        VALUES (?, ?, '1996-08-20', 'Việt Nam', 'CCCD', ?, 'Đã xác minh tại quầy', ?, ?)
    ");
    $stmt->execute([$p['nlt'], $p['ten'], $p['cccd'], $maPhieu, $p['maPhong']]);
}

// HoaDon & CTHD
$stmt = $db->prepare("INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT) VALUES (?, ?, ?)");
$stmt->execute([$maHD, $maPhieu, $tongTien]);

foreach ($phongs as $p) {
    $stmt = $db->prepare("INSERT INTO CTHD (MaHD, MaPhieu, MaPhong, MaCTDV, SoDem, SoLuong, DonGia) VALUES (?, ?, ?, NULL, 1, 1, ?)");
    $stmt->execute([$maHD, $maPhieu, $p['maPhong'], $p['gia']]);
}

// ThanhToan cọc ban đầu 100% qua ChuyenKhoan
$stmt = $db->prepare("INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD) VALUES (?, ?, ?, 'ChuyenKhoan', 'HoanTat', ?)");
$stmt->execute([$maTT, $tongTien, $thoiGianNhan, $maHD]);

echo "ĐÃ TẠO PHIẾU ĐANG LƯU TRÚ #{$maPhieu} ĐỂ USER TỰ TEST TRÊN GIAO DIỆN WEB THÀNH CÔNG!\n";
