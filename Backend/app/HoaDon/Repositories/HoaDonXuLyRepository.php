<?php

declare(strict_types=1);

namespace App\HoaDon\Repositories;

use Database\Database;
use PDO;

/**
 * Repository xử lý toàn bộ truy vấn CSDL cho module HoaDon và CTHD.
 * Gồm 2 phần nghiệp vụ: Tra cứu dữ liệu (Query) và Thao tác lưu trữ (Command).
 */
class HoaDonXuLyRepository
{
    private ?Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    // =========================================================================
    // PHẦN 1: TRA CỨU & TRUY VẤN HÓA ĐƠN (READ / QUERY)
    // =========================================================================

    public function findHoaDonById(string $id, ?PDO $db = null): ?array
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->prepare("SELECT * FROM HoaDon WHERE MaHD = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findHoaDonByMaPhieu(string $maPhieu, ?PDO $db = null): ?array
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->prepare("SELECT * FROM HoaDon WHERE MaPhieu = ?");
        $stmt->execute([$maPhieu]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Lấy toàn bộ chi tiết hóa đơn (CTHD) thô theo mã hóa đơn.
     */
    public function getCTHDByMaHD(string $maHD, ?PDO $db = null): array
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->prepare("SELECT * FROM CTHD WHERE MaHD = ?");
        $stmt->execute([$maHD]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy danh sách CTHD kèm thông tin chi tiết loại phòng và dịch vụ.
     */
    public function getChiTietDayDuHoaDon(string $maHD, ?PDO $db = null): array
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->prepare("
            SELECT 
                ct.*,
                p.MaLoaiPhong,
                lp.TenLoaiPhong,
                lp.Gia AS GiaLoaiPhong,
                ctdv.TenCTDV,
                dv.TenDV,
                CASE 
                    WHEN ct.MaPhong IS NOT NULL AND ct.SoDem > 0 THEN (ct.SoDem * ct.DonGia)
                    ELSE (ct.SoLuong * ct.DonGia)
                END AS ThanhTien
            FROM CTHD ct
            LEFT JOIN Phong p ON ct.MaPhong = p.MaPhong
            LEFT JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
            LEFT JOIN ChiTietDichVu ctdv ON ct.MaCTDV = ctdv.MaCTDV
            LEFT JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
            WHERE ct.MaHD = ?
            ORDER BY ct.MaCTHD ASC
        ");
        $stmt->execute([$maHD]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy thông tin phiếu nhận phòng & khách hàng tương ứng.
     */
    public function getThongTinPhieuVaKhachHang(string $maPhieu, ?PDO $db = null): ?array
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->prepare("
            SELECT 
                pnp.MaPhieu,
                pnp.NgayLap,
                pnp.KenhDat,
                pnp.TrangThai AS TrangThaiPhieu,
                pnp.MaKM,
                pnp.MaLeTan,
                kh.MaKH,
                kh.HoTen AS TenKhachHang,
                kh.SDT,
                tk.Email,
                (SELECT MIN(ctp.ThoiGianNhanPhong) FROM ChiTietPhieuNhanPhong ctp WHERE ctp.MaPhieu = pnp.MaPhieu) AS NgayNhan,
                (SELECT MAX(ctp.ThoiGianTraPhong) FROM ChiTietPhieuNhanPhong ctp WHERE ctp.MaPhieu = pnp.MaPhieu) AS NgayTra
            FROM PhieuNhanPhong pnp
            JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
            LEFT JOIN TaiKhoan tk ON kh.MaTK = tk.MaTK
            WHERE pnp.MaPhieu = ?
        ");
        $stmt->execute([$maPhieu]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Lấy danh sách các giao dịch thanh toán của hóa đơn.
     */
    public function getThanhToanByMaHD(string $maHD, ?PDO $db = null): array
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->prepare("
            SELECT * FROM ThanhToan 
            WHERE MaHD = ?
            ORDER BY ThoiGianThanhToan DESC
        ");
        $stmt->execute([$maHD]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy thông tin mã khuyến mãi để áp dụng chiết khấu.
     */
    public function getKhuyenMaiById(string $maKM, ?PDO $db = null): ?array
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->prepare("SELECT * FROM KhuyenMai WHERE MaKM = ?");
        $stmt->execute([$maKM]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // =========================================================================
    // PHẦN 2: THAO TÁC & LƯU TRỮ HÓA ĐƠN (WRITE / COMMAND)
    // =========================================================================

    /**
     * Sinh mã hóa đơn tự động theo định dạng HD001, HD002,...
     */
    public function generateMaHoaDon(?PDO $db = null): string
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->query("SELECT MaHD FROM HoaDon WHERE MaHD LIKE 'HD%' ORDER BY CAST(SUBSTRING(MaHD, 3) AS UNSIGNED) DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next = ($row && !empty($row['MaHD'])) ? ((int) substr($row['MaHD'], 2)) + 1 : 1;
        return sprintf('HD%03d', $next);
    }

    /**
     * Lưu thông tin hóa đơn tổng vào bảng HoaDon.
     */
    public function luuHoaDon(
        string $maHD,
        string $maPhieu,
        float $tongTienSauVAT,
        PDO $db
    ): bool {
        $sql = "INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE TongTienSauVAT = VALUES(TongTienSauVAT)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            $maHD,
            $maPhieu,
            $tongTienSauVAT
        ]);
    }

    /**
     * Lưu chi tiết hóa đơn (tiền phòng hoặc tiền dịch vụ) vào bảng CTHD.
     */
    public function luuCTHD(
        string $maHD,
        string $maPhieu,
        ?string $maPhong,
        ?string $maCTDV,
        int $soDem,
        int $soLuong,
        float $donGia,
        PDO $db
    ): bool {
        $sql = "INSERT INTO CTHD (MaHD, MaPhieu, MaPhong, MaCTDV, SoDem, SoLuong, DonGia)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            $maHD,
            $maPhieu,
            $maPhong,
            $maCTDV,
            $soDem,
            $soLuong,
            $donGia
        ]);
    }
}
