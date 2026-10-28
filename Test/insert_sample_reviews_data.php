<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

use Database\Database;

$db = Database::getConnection();

echo "Bắt đầu thêm 3 phiếu nhận phòng đã check-out nhưng chưa đánh giá...\n";

try {
    $db->beginTransaction();

    // 1. PhieuNhanPhong (KenhDat = 'TrucTuyen' => MaLeTan phải là NULL theo CHECK constraint)
    $phieuSql = "INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH, MaLeTan, MaKM) VALUES
        ('PP004', '2026-08-15', 'TrucTuyen', 'DaTraPhong', 'KH001', NULL, NULL),
        ('PP005', '2026-08-25', 'TrucTuyen', 'DaTraPhong', 'KH001', NULL, 'KM001'),
        ('PP006', '2026-09-05', 'TrucTuyen', 'DaTraPhong', 'KH001', NULL, NULL)
        ON DUPLICATE KEY UPDATE TrangThai = VALUES(TrangThai);";
    $db->exec($phieuSql);
    echo "✔ Đã thêm/cập nhật 3 PhieuNhanPhong: PP004, PP005, PP006\n";

    // 2. ChiTietPhieuNhanPhong (PP004: P101, PP005: P102 & P201, PP006: P301)
    $ctpnpSql = "INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai) VALUES
        ('PP004', 'P101', '2026-08-16 14:00:00', '2026-08-18 12:00:00', 2, 'DaTraPhong'),
        ('PP005', 'P102', '2026-08-26 14:00:00', '2026-08-28 12:00:00', 2, 'DaTraPhong'),
        ('PP005', 'P201', '2026-08-26 14:00:00', '2026-08-28 12:00:00', 2, 'DaTraPhong'),
        ('PP006', 'P301', '2026-09-08 14:00:00', '2026-09-11 12:00:00', 3, 'DaTraPhong')
        ON DUPLICATE KEY UPDATE TrangThai = VALUES(TrangThai);";
    $db->exec($ctpnpSql);
    echo "✔ Đã thêm 4 ChiTietPhieuNhanPhong cho 3 phiếu (PP005 có 2 phòng P102 và P201)\n";

    // 3. NguoiLuuTru
    $nltSql = "INSERT INTO NguoiLuuTru (MaNguoiLuuTru, HoTen, NgaySinh, QuocTich, LoaiGiayTo, SoGiayTo, GhiChu, MaPhieu, MaPhong) VALUES
        ('NLT004', 'Nguyen Van An', '1990-05-15', 'Viet Nam', 'CCCD', '001202000001', 'Khach luu tru chinh', 'PP004', 'P101'),
        ('NLT005', 'Le Thi Hoa', '1992-03-10', 'Viet Nam', 'CCCD', '001202000005', 'Nguoi di cung', 'PP004', 'P101'),
        ('NLT006', 'Nguyen Van An', '1990-05-15', 'Viet Nam', 'CCCD', '001202000001', 'Phong Standard', 'PP005', 'P102'),
        ('NLT007', 'Tran Duc Minh', '1988-11-20', 'Viet Nam', 'CCCD', '001202000007', 'Phong Deluxe', 'PP005', 'P201'),
        ('NLT008', 'Pham Huong Giang', '1991-07-14', 'Viet Nam', 'CCCD', '001202000008', 'Nguoi di cung', 'PP005', 'P201'),
        ('NLT009', 'Nguyen Van An', '1990-05-15', 'Viet Nam', 'CCCD', '001202000001', 'Khach VIP phong Suite', 'PP006', 'P301'),
        ('NLT010', 'Nguyen Thi Mai', '1995-08-20', 'Viet Nam', 'CCCD', '001202000002', 'Vo', 'PP006', 'P301')
        ON DUPLICATE KEY UPDATE HoTen = VALUES(HoTen);";
    $db->exec($nltSql);
    echo "✔ Đã thêm danh sách Người Lưu Trú (NLT004 -> NLT010)\n";

    // 4. HoaDon
    $hdSql = "INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT) VALUES
        ('HD004', 'PP004', 1100000),
        ('HD005', 'PP005', 2574000),
        ('HD006', 'PP006', 3960000)
        ON DUPLICATE KEY UPDATE TongTienSauVAT = VALUES(TongTienSauVAT);";
    $db->exec($hdSql);
    echo "✔ Đã thêm 3 Hóa đơn (HD004, HD005, HD006)\n";

    // 5. CTHD (Phòng: MaCTDV IS NULL, SoDem > 0)
    $cthdSql = "INSERT INTO CTHD (MaHD, MaPhieu, MaPhong, MaCTDV, SoDem, SoLuong, DonGia) VALUES
        ('HD004', 'PP004', 'P101', NULL, 2, 1, 500000),
        ('HD005', 'PP005', 'P102', NULL, 2, 1, 500000),
        ('HD005', 'PP005', 'P201', NULL, 2, 1, 800000),
        ('HD006', 'PP006', 'P301', NULL, 3, 1, 1200000)
        ON DUPLICATE KEY UPDATE DonGia = VALUES(DonGia);";
    $db->exec($cthdSql);
    echo "✔ Đã thêm chi tiết hóa đơn CTHD\n";

    // 6. ThanhToan (HinhThuc: 'ChuyenKhoan' hoặc 'TienMat')
    $ttSql = "INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD) VALUES
        ('TT004', 1100000, '2026-08-18 11:30:00', 'ChuyenKhoan', 'HoanTat', 'HD004'),
        ('TT005', 2574000, '2026-08-28 11:45:00', 'ChuyenKhoan', 'HoanTat', 'HD005'),
        ('TT006', 3960000, '2026-09-11 11:30:00', 'TienMat', 'HoanTat', 'HD006')
        ON DUPLICATE KEY UPDATE TrangThaiThanhToan = VALUES(TrangThaiThanhToan);";
    $db->exec($ttSql);
    echo "✔ Đã thêm thanh toán hoàn tất (TT004, TT005, TT006)\n";

    // Đảm bảo không có đánh giá nào cho PP004, PP005, PP006 để người dùng test chức năng gửi đánh giá
    $db->exec("DELETE FROM DanhGia WHERE MaPhieu IN ('PP004', 'PP005', 'PP006');");
    echo "✔ Đảm bảo KHÔNG có đánh giá nào cho PP004, PP005, PP006\n";

    $db->commit();
    echo "\n🎉 HOÀN TẤT THÊM DỮ LIỆU THÀNH CÔNG!\n";
} catch (\Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ LỖI: " . $e->getMessage() . "\n";
    exit(1);
}
