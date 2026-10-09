<?php

namespace App\HoaDon\Repositories;

use Database\Database;
use PDO;

/**
 * Repository cho module HoaDon.
 * Chịu trách nhiệm toàn bộ các câu truy vấn và giao dịch CSDL liên quan đến Hóa Đơn và CTHD.
 */
class HoaDonRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Tìm hóa đơn theo mã HD.
     */
    public function findHoaDonById(string $maHD): ?array
    {
        $sql = "SELECT hd.MaHD, hd.MaPhieu, hd.TongTienSauVAT,
                       pnp.NgayLap, pnp.KenhDat, pnp.TrangThai AS TrangThaiPhieu, pnp.MaKM,
                       kh.MaKH, kh.HoTen AS TenKhachHang, kh.SDT, kh.Email,
                       km.TenKM, km.LoaiKM, km.GiaTriGiam
                FROM HoaDon hd
                JOIN PhieuNhanPhong pnp ON hd.MaPhieu = pnp.MaPhieu
                JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                LEFT JOIN KhuyenMai km ON pnp.MaKM = km.MaKM
                WHERE hd.MaHD = :maHD
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':maHD' => $maHD]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Tìm hóa đơn theo mã phiếu nhận phòng.
     */
    public function findHoaDonByMaPhieu(string $maPhieu): ?array
    {
        $sql = "SELECT hd.MaHD, hd.MaPhieu, hd.TongTienSauVAT,
                       pnp.NgayLap, pnp.KenhDat, pnp.TrangThai AS TrangThaiPhieu, pnp.MaKM,
                       kh.MaKH, kh.HoTen AS TenKhachHang, kh.SDT, kh.Email
                FROM HoaDon hd
                JOIN PhieuNhanPhong pnp ON hd.MaPhieu = pnp.MaPhieu
                JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                WHERE hd.MaPhieu = :maPhieu
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':maPhieu' => $maPhieu]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Lấy toàn bộ danh sách hóa đơn (có kèm tên khách và trạng thái thanh toán).
     */
    public function getAllHoaDon(array $filters = []): array
    {
        $sql = "SELECT hd.MaHD, hd.MaPhieu, hd.TongTienSauVAT,
                       pnp.NgayLap, pnp.TrangThai AS TrangThaiPhieu,
                       kh.MaKH, kh.HoTen AS TenKhachHang, kh.SDT,
                       tt.MaThanhToan, tt.HinhThuc, tt.TrangThaiThanhToan, tt.ThoiGianThanhToan
                FROM HoaDon hd
                JOIN PhieuNhanPhong pnp ON hd.MaPhieu = pnp.MaPhieu
                JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                LEFT JOIN ThanhToan tt ON hd.MaHD = tt.MaHD
                ORDER BY hd.MaHD DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy chi tiết các dòng trong hóa đơn (CTHD) kèm tên phòng và tên dịch vụ.
     */
    public function getChiTietHoaDon(string $maHD): array
    {
        $sql = "SELECT ct.MaCTHD, ct.MaHD, ct.MaPhieu, ct.MaPhong, ct.MaCTDV,
                       ct.SoDem, ct.SoLuong, ct.DonGia,
                       lp.TenLoaiPhong,
                       ctdv.TenCTDV,
                       CASE 
                           WHEN ct.MaPhong IS NOT NULL THEN (ct.SoDem * ct.DonGia)
                           ELSE (ct.SoLuong * ct.DonGia)
                       END AS ThanhTien
                FROM CTHD ct
                LEFT JOIN Phong p ON ct.MaPhong = p.MaPhong
                LEFT JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                LEFT JOIN ChiTietDichVu ctdv ON ct.MaCTDV = ctdv.MaCTDV
                WHERE ct.MaHD = :maHD
                ORDER BY ct.MaCTHD ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':maHD' => $maHD]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy toàn bộ thông tin phiếu đặt phòng để chuẩn bị lập hóa đơn Check-out.
     */
    public function getPhieuNhanPhongFull(string $maPhieu): ?array
    {
        // 1. Thông tin phiếu chính + Khách hàng + Khuyến mãi
        $sqlPnp = "SELECT pnp.MaPhieu, pnp.NgayLap, pnp.KenhDat, pnp.TrangThai, pnp.MaKH, pnp.MaKM,
                          kh.HoTen AS TenKhachHang, kh.SDT, kh.Email,
                          km.TenKM, km.LoaiKM, km.GiaTriGiam, km.SoDemToiThieu, km.SoNguoiToiThieu, km.TrangThai AS TrangThaiKM
                   FROM PhieuNhanPhong pnp
                   JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                   LEFT JOIN KhuyenMai km ON pnp.MaKM = km.MaKM
                   WHERE pnp.MaPhieu = :maPhieu
                   LIMIT 1";
        $stmtPnp = $this->db->prepare($sqlPnp);
        $stmtPnp->execute([':maPhieu' => $maPhieu]);
        $phieu = $stmtPnp->fetch(PDO::FETCH_ASSOC);

        if (!$phieu) {
            return null;
        }

        // 2. Danh sách phòng thuê trong phiếu
        $sqlPhong = "SELECT ctpnp.MaPhieu, ctpnp.MaPhong, ctpnp.ThoiGianNhanPhong, ctpnp.ThoiGianTraPhong, ctpnp.SoNguoi,
                            p.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia AS DonGiaPhong
                     FROM ChiTietPhieuNhanPhong ctpnp
                     JOIN Phong p ON ctpnp.MaPhong = p.MaPhong
                     JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                     WHERE ctpnp.MaPhieu = :maPhieu";
        $stmtPhong = $this->db->prepare($sqlPhong);
        $stmtPhong->execute([':maPhieu' => $maPhieu]);
        $phongList = $stmtPhong->fetchAll(PDO::FETCH_ASSOC);

        // 3. Danh sách dịch vụ đã dùng trong phiếu
        $sqlDV = "SELECT ctsd.MaCTSDDV, ctsd.MaCTDV, ctsd.MaPhieu, ctsd.MaPhong, ctsd.DonGia, ctsd.SoLuong, ctsd.MoTa, ctsd.TrangThai,
                         ctdv.TenCTDV
                  FROM CTSuDungDichVu ctsd
                  JOIN ChiTietDichVu ctdv ON ctsd.MaCTDV = ctdv.MaCTDV
                  WHERE ctsd.MaPhieu = :maPhieu";
        $stmtDV = $this->db->prepare($sqlDV);
        $stmtDV->execute([':maPhieu' => $maPhieu]);
        $dvList = $stmtDV->fetchAll(PDO::FETCH_ASSOC);

        $phieu['DanhSachPhong'] = $phongList;
        $phieu['DanhSachDichVu'] = $dvList;

        return $phieu;
    }

    /**
     * Tạo mã hóa đơn tiếp theo tự động (HD001, HD002, HD004...).
     */
    public function generateNextMaHD(): string
    {
        $sql = "SELECT MaHD FROM HoaDon ORDER BY MaHD DESC LIMIT 1";
        $stmt = $this->db->query($sql);
        $last = $stmt->fetchColumn();

        if (!$last) {
            return 'HD001';
        }

        if (preg_match('/^HD(\d+)$/', $last, $matches)) {
            $num = (int)$matches[1] + 1;
            return 'HD' . str_pad((string)$num, 3, '0', STR_PAD_LEFT);
        }

        return 'HD_' . date('YmdHis');
    }

    /**
     * Tạo hóa đơn và lưu chi tiết trong một Database Transaction an toàn.
     */
    public function saveHoaDonWithDetails(array $hoaDonData, array $cthdItems, string $maPhieu): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Insert HoaDon
            $sqlHD = "INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT) 
                      VALUES (:maHD, :maPhieu, :tongTienSauVAT)";
            $stmtHD = $this->db->prepare($sqlHD);
            $stmtHD->execute([
                ':maHD' => $hoaDonData['MaHD'],
                ':maPhieu' => $hoaDonData['MaPhieu'],
                ':tongTienSauVAT' => $hoaDonData['TongTienSauVAT'],
            ]);

            // 2. Insert từng dòng CTHD
            $sqlCT = "INSERT INTO CTHD (MaHD, MaPhieu, MaPhong, MaCTDV, SoDem, SoLuong, DonGia) 
                      VALUES (:maHD, :maPhieu, :maPhong, :maCTDV, :soDem, :soLuong, :donGia)";
            $stmtCT = $this->db->prepare($sqlCT);

            foreach ($cthdItems as $item) {
                $stmtCT->execute([
                    ':maHD' => $hoaDonData['MaHD'],
                    ':maPhieu' => $hoaDonData['MaPhieu'],
                    ':maPhong' => $item['MaPhong'],
                    ':maCTDV' => $item['MaCTDV'],
                    ':soDem' => $item['SoDem'] ?? 0,
                    ':soLuong' => $item['SoLuong'] ?? 1,
                    ':donGia' => $item['DonGia'],
                ]);
            }

            // 3. Cập nhật trạng thái phiếu thành DaTraPhong nếu chưa
            $sqlUp = "UPDATE PhieuNhanPhong SET TrangThai = 'DaTraPhong' WHERE MaPhieu = :maPhieu";
            $stmtUp = $this->db->prepare($sqlUp);
            $stmtUp->execute([':maPhieu' => $maPhieu]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
