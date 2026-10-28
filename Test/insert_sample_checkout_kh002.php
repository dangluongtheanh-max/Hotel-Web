<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

use Database\Database;

$db = Database::getConnection();

echo "Bắt đầu tạo 2 phiếu nhận phòng đã trả phòng (DaTraPhong) cho khách KH002...\n";

try {
    $db->beginTransaction();

    // 1. PhieuNhanPhong
    $phieuSql = "INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH, MaLeTan, MaKM) VALUES
        ('PP011', '2026-09-15', 'TrucTuyen', 'DaTraPhong', 'KH002', NULL, NULL),
        ('PP012', '2026-10-02', 'TrucTuyen', 'DaTraPhong', 'KH002', NULL, NULL)
        ON DUPLICATE KEY UPDATE TrangThai = VALUES(TrangThai);";
    $db->exec($phieuSql);
    echo "✔ Đã tạo 2 PhieuNhanPhong: PP011, PP012 (Trạng thái: DaTraPhong, Khách: KH002)\n";

    // 2. ChiTietPhieuNhanPhong
    // PP011: Phòng P202 (Deluxe, 18/09/2026 - 20/09/2026)
    // PP012: Phòng P104 (Standard, 05/10/2026 - 07/10/2026)
    $ctpnpSql = "INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai) VALUES
        ('PP011', 'P202', '2026-09-18 14:00:00', '2026-09-20 12:00:00', 2, 'DaTraPhong'),
        ('PP012', 'P104', '2026-10-05 14:00:00', '2026-10-07 12:00:00', 1, 'DaTraPhong')
        ON DUPLICATE KEY UPDATE TrangThai = VALUES(TrangThai);";
    $db->exec($ctpnpSql);
    echo "✔ Đã tạo ChiTietPhieuNhanPhong (PP011 -> P202, PP012 -> P104)\n";

    // 3. NguoiLuuTru
    $nltSql = "INSERT INTO NguoiLuuTru (MaNguoiLuuTru, HoTen, NgaySinh, QuocTich, LoaiGiayTo, SoGiayTo, GhiChu, MaPhieu, MaPhong) VALUES
        ('NLT021', 'Tran Thi Binh', '1993-04-12', 'Viet Nam', 'CCCD', '001202000002', 'Khach luu tru chinh', 'PP011', 'P202'),
        ('NLT022', 'Tran Thi Binh', '1993-04-12', 'Viet Nam', 'CCCD', '001202000002', 'Khach luu tru chinh', 'PP012', 'P104')
        ON DUPLICATE KEY UPDATE HoTen = VALUES(HoTen);";
    $db->exec($nltSql);
    echo "✔ Đã tạo Người Lưu Trú (NLT021, NLT022)\n";

    // 4. HoaDon
    // PP011: 2 đêm x 800.000 + 10% VAT = 1.760.000 đ
    // PP012: 2 đêm x 500.000 + 10% VAT = 1.100.000 đ
    $hdSql = "INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT) VALUES
        ('HD011', 'PP011', 1760000),
        ('HD012', 'PP012', 1100000)
        ON DUPLICATE KEY UPDATE TongTienSauVAT = VALUES(TongTienSauVAT);";
    $db->exec($hdSql);
    echo "✔ Đã tạo Hóa đơn (HD011, HD012)\n";

    // 5. CTHD
    $cthdSql = "INSERT INTO CTHD (MaHD, MaPhieu, MaPhong, MaCTDV, SoDem, SoLuong, DonGia) VALUES
        ('HD011', 'PP011', 'P202', NULL, 2, 1, 800000),
        ('HD012', 'PP012', 'P104', NULL, 2, 1, 500000)
        ON DUPLICATE KEY UPDATE DonGia = VALUES(DonGia);";
    $db->exec($cthdSql);
    echo "✔ Đã tạo Chi tiết hóa đơn CTHD\n";

    // 6. ThanhToan
    $ttSql = "INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD) VALUES
        ('TT011', 1760000, '2026-09-20 11:30:00', 'ChuyenKhoan', 'HoanTat', 'HD011'),
        ('TT012', 1100000, '2026-10-07 11:45:00', 'TienMat', 'HoanTat', 'HD012')
        ON DUPLICATE KEY UPDATE TrangThaiThanhToan = VALUES(TrangThaiThanhToan);";
    $db->exec($ttSql);
    echo "✔ Đã tạo ThanhToan hoàn tất (TT011, TT012)\n";

    // 7. Đảm bảo chưa có đánh giá nào cho PP011, PP012
    $db->exec("DELETE FROM DanhGia WHERE MaPhieu IN ('PP011', 'PP012');");
    echo "✔ Đảm bảo KHÔNG có đánh giá nào cho PP011, PP012 (Sẵn sàng test chức năng đánh giá)\n";

    $db->commit();
    echo "\n🎉 TẠO DỮ LIỆU PHIẾU ĐÃ TRẢ PHÒNG CHO KH002 THÀNH CÔNG RỰC RỠ!\n";
} catch (\Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ LỖI: " . $e->getMessage() . "\n";
}
