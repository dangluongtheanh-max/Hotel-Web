<?php

declare(strict_types=1);

namespace App\DichVu\Repositories;

use Database\Database;
use PDO;

/**
 * Repository cho dịch vụ Đặt đồ ăn (DVDoAn).
 * Chịu trách nhiệm tương tác trực tiếp với cơ sở dữ liệu cho thực đơn và đặt món ăn.
 */
class DatDoAnRepository
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

    /**
     * Lấy danh sách toàn bộ dịch vụ kèm chi tiết đang cung cấp trong khách sạn
     */
    public function getAllDichVu(?PDO $db = null): array
    {
        $db = $db ?? $this->getConnection();
        $sql = "
            SELECT dv.MaDV, dv.TenDV, dv.MoTa, dv.MoTaChiTiet, dv.DonGia AS GiaGoc,
                   ctdv.MaCTDV, ctdv.TenCTDV, ctdv.MoTa AS MoTaCT, ctdv.MoTaChiTiet AS MoTaChiTietCT, ctdv.DonGia AS DonGiaCT,
                   ctdv.SoLuongTon
            FROM DichVu dv
            LEFT JOIN ChiTietDichVu ctdv ON dv.MaDV = ctdv.MaDV
            WHERE dv.TrangThai = 'DangCungCap'
            ORDER BY dv.MaDV, ctdv.MaCTDV
        ";
        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $services = [];
        foreach ($rows as $r) {
            $maDV = $r['MaDV'];
            if (!isset($services[$maDV])) {
                $services[$maDV] = [
                    'MaDV' => $maDV,
                    'TenDV' => $r['TenDV'],
                    'MoTa' => $r['MoTa'] ?? '',
                    'MoTaChiTiet' => $r['MoTaChiTiet'] ?? '',
                    'DonGia' => (float) $r['GiaGoc'],
                    'ChiTiet' => [],
                ];
            }
            if (!empty($r['MaCTDV'])) {
                $services[$maDV]['ChiTiet'][] = [
                    'MaCTDV' => $r['MaCTDV'],
                    'TenCTDV' => $r['TenCTDV'],
                    'MoTa' => $r['MoTaCT'] ?? '',
                    'MoTaChiTiet' => $r['MoTaChiTietCT'] ?? '',
                    'DonGia' => (float) $r['DonGiaCT'],
                    'SoLuongTon' => (int) ($r['SoLuongTon'] ?? 0),
                ];
            }
        }
        return array_values($services);
    }

    /**
     * Lấy danh sách các món ăn thuộc dịch vụ Đặt đồ ăn (DVDoAn)
     */
    public function layDanhSachMonAn(?PDO $db = null): array
    {
        $db = $db ?? $this->getConnection();
        $sql = "
            SELECT ctdv.MaCTDV, ctdv.MaDV, ctdv.TenCTDV, ctdv.MoTa, ctdv.MoTaChiTiet,
                   ctdv.DonGia, ctdv.SoLuongTon, dv.TenDV
            FROM ChiTietDichVu ctdv
            JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
            WHERE ctdv.MaDV = 'DVDoAn' AND dv.TrangThai = 'DangCungCap'
            ORDER BY ctdv.MaCTDV ASC
        ";
        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tìm món ăn theo mã MaCTDV
     */
    public function timMonAnTheoMa(string $maCTDV, ?PDO $db = null): ?array
    {
        $db = $db ?? $this->getConnection();
        $sql = "
            SELECT ctdv.MaCTDV, ctdv.MaDV, ctdv.TenCTDV, ctdv.MoTa, ctdv.MoTaChiTiet,
                   ctdv.DonGia, ctdv.SoLuongTon, dv.TenDV
            FROM ChiTietDichVu ctdv
            JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
            WHERE ctdv.MaCTDV = ? AND dv.TrangThai = 'DangCungCap'
            LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$maCTDV]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Lấy thông tin phiếu và các phòng đang lưu trú hoặc đã đặt trước của khách hàng
     */
    public function timThongTinLuuTruHienTai(string $maKH, ?PDO $db = null): array
    {
        $db = $db ?? $this->getConnection();
        $sql = "
            SELECT pnp.MaPhieu, pnp.MaKH, ctpnp.MaPhong, lp.TenLoaiPhong,
                   ctpnp.ThoiGianNhanPhong, ctpnp.ThoiGianTraPhong,
                   ctpnp.TrangThai, pnp.TrangThai AS TrangThaiPhieu
            FROM PhieuNhanPhong pnp
            JOIN ChiTietPhieuNhanPhong ctpnp ON pnp.MaPhieu = ctpnp.MaPhieu
            JOIN Phong p ON ctpnp.MaPhong = p.MaPhong
            JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
            WHERE pnp.MaKH = ? 
              AND pnp.TrangThai IN ('DangLuuTru', 'DaDatPhong', 'DaDat') 
              AND ctpnp.TrangThai IN ('DangLuuTru', 'DaDatPhong', 'DaDat')
            ORDER BY 
              CASE WHEN pnp.TrangThai = 'DangLuuTru' THEN 1 ELSE 2 END,
              ctpnp.ThoiGianNhanPhong ASC,
              ctpnp.MaPhong ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$maKH]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Kiểm tra phòng được chọn có thực sự thuộc phiếu và đang lưu trú thực tế không (DangLuuTru)
     */
    public function kiemTraPhongCoDangLuuTru(string $maPhieu, string $maPhong, string $maKH, ?PDO $db = null): bool
    {
        $db = $db ?? $this->getConnection();
        $sql = "
            SELECT 1
            FROM PhieuNhanPhong pnp
            JOIN ChiTietPhieuNhanPhong ctpnp ON pnp.MaPhieu = ctpnp.MaPhieu
            WHERE pnp.MaPhieu = ? AND ctpnp.MaPhong = ? AND pnp.MaKH = ?
              AND pnp.TrangThai = 'DangLuuTru' 
              AND ctpnp.TrangThai = 'DangLuuTru'
            LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$maPhieu, $maPhong, $maKH]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Lưu thông tin sử dụng dịch vụ đặt món ăn vào CTSuDungDichVu
     */
    public function luuDatMonAn(
        string $maCTDV,
        string $maPhieu,
        string $maPhong,
        float $donGia,
        ?string $ghiChu,
        int $soLuong,
        ?PDO $db = null
    ): int {
        $db = $db ?? $this->getConnection();
        $sql = "
            INSERT INTO CTSuDungDichVu (MaCTDV, MaPhieu, MaPhong, DonGia, GhiChu, SoLuong)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $maCTDV,
            $maPhieu,
            $maPhong,
            $donGia,
            $ghiChu,
            $soLuong
        ]);
        return (int) $db->lastInsertId();
    }
}
