<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

use Database\Database;

$db = Database::getConnection();

echo "Bắt đầu thêm 2 phiếu nhận phòng (PP013, PP014) cho KH002, mỗi phiếu có 2 phòng ở trạng thái DaTraPhong...\n";

try {
    $db->beginTransaction();

    // 1. PhieuNhanPhong
    $phieuSql = "INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH, MaLeTan, MaKM) VALUES
        ('PP013', '2026-08-20', 'TrucTuyen', 'DaTraPhong', 'KH002', NULL, NULL),
        ('PP014', '2026-09-01', 'TrucTuyen', 'DaTraPhong', 'KH002', NULL, NULL)
        ON DUPLICATE KEY UPDATE TrangThai = VALUES(TrangThai);";
    $db->exec($phieuSql);
    echo "✔ Đã tạo 2 PhieuNhanPhong: PP013, PP014 (Trạng thái: DaTraPhong, Khách: KH002)\n";

    // 2. ChiTietPhieuNhanPhong (Mỗi phiếu 2 phòng)
    // PP013: P105 (Standard) & P203 (Deluxe), từ 2026-08-22 đến 2026-08-25 (3 đêm)
    // PP014: P204 (Deluxe) & P304 (Suite), từ 2026-09-03 đến 2026-09-05 (2 đêm)
    $ctpnpSql = "INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai) VALUES
        ('PP013', 'P105', '2026-08-22 14:00:00', '2026-08-25 12:00:00', 2, 'DaTraPhong'),
        ('PP013', 'P203', '2026-08-22 14:00:00', '2026-08-25 12:00:00', 2, 'DaTraPhong'),
        ('PP014', 'P204', '2026-09-03 14:00:00', '2026-09-05 12:00:00', 2, 'DaTraPhong'),
        ('PP014', 'P304', '2026-09-03 14:00:00', '2026-09-05 12:00:00', 3, 'DaTraPhong')
        ON DUPLICATE KEY UPDATE TrangThai = VALUES(TrangThai);";
    $db->exec($ctpnpSql);
    echo "✔ Đã tạo ChiTietPhieuNhanPhong (PP013 -> P105, P203; PP014 -> P204, P304)\n";

    // 3. NguoiLuuTru
    $nltSql = "INSERT INTO NguoiLuuTru (MaNguoiLuuTru, HoTen, NgaySinh, QuocTich, LoaiGiayTo, SoGiayTo, GhiChu, MaPhieu, MaPhong) VALUES
        ('NLT023', 'Tran Thi Binh', '1993-04-12', 'Viet Nam', 'CCCD', '001202000002', 'Khach luu tru chinh', 'PP013', 'P105'),
        ('NLT024', 'Tran Thi Binh', '1993-04-12', 'Viet Nam', 'CCCD', '001202000002', 'Khach luu tru chinh', 'PP013', 'P203'),
        ('NLT025', 'Tran Thi Binh', '1993-04-12', 'Viet Nam', 'CCCD', '001202000002', 'Khach luu tru chinh', 'PP014', 'P204'),
        ('NLT026', 'Tran Thi Binh', '1993-04-12', 'Viet Nam', 'CCCD', '001202000002', 'Khach luu tru chinh', 'PP014', 'P304')
        ON DUPLICATE KEY UPDATE HoTen = VALUES(HoTen);";
    $db->exec($nltSql);
    echo "✔ Đã tạo Người Lưu Trú (NLT023, NLT024, NLT025, NLT026)\n";

    // 4. HoaDon
    // PP013: (3 đêm * 500k + 3 đêm * 800k) * 1.1 = 3.900.000 * 1.1 = 4.290.000 đ
    // PP014: (2 đêm * 800k + 2 đêm * 1200k) * 1.1 = 4.000.000 * 1.1 = 4.400.000 đ
    $hdSql = "INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT) VALUES
        ('HD013', 'PP013', 4290000),
        ('HD014', 'PP014', 4400000)
        ON DUPLICATE KEY UPDATE TongTienSauVAT = VALUES(TongTienSauVAT);";
    $db->exec($hdSql);
    echo "✔ Đã tạo Hóa đơn (HD013, HD014)\n";

    // 5. CTHD
    $cthdSql = "INSERT INTO CTHD (MaHD, MaPhieu, MaPhong, MaCTDV, SoDem, SoLuong, DonGia) VALUES
        ('HD013', 'PP013', 'P105', NULL, 3, 1, 500000),
        ('HD013', 'PP013', 'P203', NULL, 3, 1, 800000),
        ('HD014', 'PP014', 'P204', NULL, 2, 1, 800000),
        ('HD014', 'PP014', 'P304', NULL, 2, 1, 1200000)
        ON DUPLICATE KEY UPDATE DonGia = VALUES(DonGia);";
    $db->exec($cthdSql);
    echo "✔ Đã tạo Chi tiết hóa đơn CTHD cho từng phòng\n";

    // 6. ThanhToan
    $ttSql = "INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD) VALUES
        ('TT013', 4290000, '2026-08-25 11:30:00', 'ChuyenKhoan', 'HoanTat', 'HD013'),
        ('TT014', 4400000, '2026-09-05 11:45:00', 'ChuyenKhoan', 'HoanTat', 'HD014')
        ON DUPLICATE KEY UPDATE TrangThaiThanhToan = VALUES(TrangThaiThanhToan);";
    $db->exec($ttSql);
    echo "✔ Đã tạo ThanhToan hoàn tất (TT013, TT014)\n";

    // 7. Xóa sạch đánh giá cũ nếu có cho PP013, PP014
    $db->exec("DELETE FROM DanhGia WHERE MaPhieu IN ('PP013', 'PP014');");
    echo "✔ Đảm bảo KHÔNG có đánh giá nào cho PP013, PP014\n";

    $db->commit();
    echo "\n🎉 HOÀN TẤT THÊM 2 PHIẾU ĐÃ TRẢ PHÒNG (MỖI PHIẾU 2 PHÒNG) CHO KH002!\n";
} catch (\Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ LỖI: " . $e->getMessage() . "\n";
}
