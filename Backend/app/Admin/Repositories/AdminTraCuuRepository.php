<?php

declare(strict_types=1);

namespace App\Admin\Repositories;

use Database\Database;
use PDO;

/**
 * Repository tra cứu thông tin vận hành (Khách hàng, Phiếu đặt, Hóa đơn).
 */
class AdminTraCuuRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /* =========================================================================
     * QUẢN LÝ KHÁCH HÀNG (Admin)
     * ========================================================================= */

    public function getAllKhachHang(array $filters = []): array
    {
        $sql = "SELECT kh.MaKH, kh.HoTen, kh.SDT, kh.MaTK,
                       tk.TenDangNhap, tk.Email, 
                       COALESCE(tk.TrangThaiTaiKhoan, 'ChuaCoTaiKhoan') AS TrangThaiTaiKhoan
                FROM KhachHang kh
                LEFT JOIN TaiKhoan tk ON kh.MaTK = tk.MaTK";
        $conditions = [];
        $parameters = [];

        if (!empty($filters['keyword'])) {
            $conditions[] = '(kh.MaKH LIKE :kw 
                OR kh.HoTen LIKE :kw 
                OR kh.SDT LIKE :kw 
                OR tk.TenDangNhap LIKE :kw 
                OR tk.Email LIKE :kw)';
            $parameters[':kw'] = '%' . $filters['keyword'] . '%';
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY kh.MaKH ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parameters);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================================
     * QUẢN LÝ PHIẾU NHẬN PHÒNG (Admin)
     * ========================================================================= */

    public function getAllPhieuNhanPhong(array $filters = []): array
    {
        $sql = "SELECT 
                    pnp.MaPhieu,
                    pnp.NgayLap,
                    pnp.KenhDat,
                    pnp.TrangThai,
                    pnp.ThoiGianHetHan,
                    pnp.LyDoHuy,
                    pnp.MaKM,
                    kh.MaKH,
                    kh.HoTen AS TenKhachHang,
                    kh.SDT,
                    hd.MaHD,
                    COALESCE(hd.TongTienSauVAT, 0) AS TongTienSauVAT,
                    (
                        SELECT GROUP_CONCAT(CONCAT(ct.MaPhong, ' (', lp.TenLoaiPhong, ')') SEPARATOR ', ')
                        FROM ChiTietPhieuNhanPhong ct
                        JOIN Phong p ON ct.MaPhong = p.MaPhong
                        JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                        WHERE ct.MaPhieu = pnp.MaPhieu
                    ) AS DanhSachPhong
                FROM PhieuNhanPhong pnp
                JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                LEFT JOIN HoaDon hd ON pnp.MaPhieu = hd.MaPhieu";
        $conditions = [];
        $parameters = [];

        if (!empty($filters['keyword'])) {
            $conditions[] = '(pnp.MaPhieu LIKE :kw 
                OR kh.HoTen LIKE :kw 
                OR kh.SDT LIKE :kw 
                OR hd.MaHD LIKE :kw)';
            $parameters[':kw'] = '%' . $filters['keyword'] . '%';
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY pnp.MaPhieu DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parameters);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================================
     * QUẢN LÝ HÓA ĐƠN (Admin)
     * ========================================================================= */

    public function getAllHoaDon(array $filters = []): array
    {
        $sql = "SELECT hd.MaHD, hd.MaPhieu, hd.TongTienSauVAT,
                       kh.MaKH, kh.HoTen AS TenKhachHang, kh.SDT,
                       pnp.KenhDat, pnp.TrangThai AS TrangThaiPhieu, pnp.NgayLap,
                       tt.MaThanhToan, tt.HinhThuc, tt.TrangThaiThanhToan, tt.ThoiGianThanhToan, tt.SoTien
                FROM HoaDon hd
                JOIN PhieuNhanPhong pnp ON hd.MaPhieu = pnp.MaPhieu
                JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                LEFT JOIN ThanhToan tt ON hd.MaHD = tt.MaHD";
        $conditions = [];
        $parameters = [];

        if (!empty($filters['keyword'])) {
            $conditions[] = '(hd.MaHD LIKE :kw OR hd.MaPhieu LIKE :kw OR kh.HoTen LIKE :kw OR kh.SDT LIKE :kw)';
            $parameters[':kw'] = '%' . $filters['keyword'] . '%';
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY hd.MaHD DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parameters);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
