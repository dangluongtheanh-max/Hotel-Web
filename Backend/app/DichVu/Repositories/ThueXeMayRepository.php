<?php

declare(strict_types=1);

namespace App\DichVu\Repositories;

use Database\Database;
use PDO;

/**
 * Repository cho dịch vụ Thuê xe máy (DVThueXe).
 * Chịu trách nhiệm tương tác cơ sở dữ liệu cho việc thuê xe máy.
 */
class ThueXeMayRepository
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
     * Lấy danh sách các dòng xe máy cho thuê thuộc dịch vụ DVThueXe
     */
    public function layDanhSachXeMay(?PDO $db = null): array
    {
        $db = $db ?? $this->getConnection();
        $sql = "
            SELECT ctdv.MaCTDV, ctdv.MaDV, ctdv.TenCTDV, ctdv.MoTa, ctdv.MoTaChiTiet,
                   ctdv.DonGia, ctdv.SoLuongTon, dv.TenDV
            FROM ChiTietDichVu ctdv
            JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
            WHERE ctdv.MaDV = 'DVThueXe' AND dv.TrangThai = 'DangCungCap'
            ORDER BY ctdv.MaCTDV ASC
        ";
        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy thông tin chi tiết của 1 dòng xe máy theo MaCTDV
     */
    public function timXeMayTheoMa(string $maCTDV, ?PDO $db = null): ?array
    {
        $db = $db ?? $this->getConnection();
        $sql = "
            SELECT ctdv.MaCTDV, ctdv.MaDV, ctdv.TenCTDV, ctdv.MoTa, ctdv.MoTaChiTiet,
                   ctdv.DonGia, ctdv.SoLuongTon, dv.TenDV
            FROM ChiTietDichVu ctdv
            JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
            WHERE ctdv.MaCTDV = ? AND ctdv.MaDV = 'DVThueXe' AND dv.TrangThai = 'DangCungCap'
            LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$maCTDV]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Lấy thông tin các phòng đang lưu trú hoặc đã đặt trước của khách hàng
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
     * Lấy chi tiết thông tin lưu trú của phòng cụ thể để kiểm tra thời gian nhận/trả phòng
     */
    public function timChiTietPhongDangLuuTru(string $maPhieu, string $maPhong, string $maKH, ?PDO $db = null): ?array
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
            WHERE pnp.MaPhieu = ? AND ctpnp.MaPhong = ? AND pnp.MaKH = ?
              AND pnp.TrangThai IN ('DangLuuTru', 'DaDatPhong', 'DaDat') 
              AND ctpnp.TrangThai IN ('DangLuuTru', 'DaDatPhong', 'DaDat')
            LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$maPhieu, $maPhong, $maKH]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Lưu thông tin thuê xe vào bảng CTSuDungDichVu (Phương án B: giữ nguyên thiết kế)
     * và giảm số lượng tồn của xe trong ChiTietDichVu.
     */
    public function luuThueXeMay(
        string $maCTDV,
        string $maPhieu,
        string $maPhong,
        float $donGia,
        int $soLuongLuu,
        int $soLuongXe,
        string $thoiGianNhan,
        string $thoiGianTra,
        ?string $ghiChu = null,
        ?PDO $db = null
    ): array {
        $conn = $db ?? $this->getConnection();
        $isExternalTx = $conn->inTransaction();

        if (!$isExternalTx) {
            $conn->beginTransaction();
        }

        try {
            // 1. Thêm bản ghi vào CTSuDungDichVu với ThoiGianNhan và ThoiGianTra
            $sqlInsert = "
                INSERT INTO CTSuDungDichVu (MaCTDV, MaPhieu, MaPhong, DonGia, GhiChu, SoLuong, ThoiGianNhan, ThoiGianTra)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmtInsert = $conn->prepare($sqlInsert);
            $stmtInsert->execute([
                $maCTDV,
                $maPhieu,
                $maPhong,
                $donGia,
                $ghiChu,
                $soLuongLuu,
                $thoiGianNhan,
                $thoiGianTra
            ]);

            $maCTSDDV = (int)$conn->lastInsertId();

            // 2. Cập nhật trừ số lượng xe khả dụng trong kho
            $sqlUpdateStock = "
                UPDATE ChiTietDichVu
                SET SoLuongTon = SoLuongTon - ?
                WHERE MaCTDV = ? AND SoLuongTon >= ?
            ";
            $stmtUpdate = $conn->prepare($sqlUpdateStock);
            $stmtUpdate->execute([$soLuongXe, $maCTDV, $soLuongXe]);

            if ($stmtUpdate->rowCount() === 0) {
                throw new \Exception("Số lượng xe trong kho không đủ để đáp ứng yêu cầu thuê {$soLuongXe} xe.");
            }

            if (!$isExternalTx) {
                $conn->commit();
            }

            return [
                'maCTSDDV' => $maCTSDDV,
                'maCTDV' => $maCTDV,
                'maPhieu' => $maPhieu,
                'maPhong' => $maPhong,
                'donGia' => $donGia,
                'soLuong' => $soLuongLuu,
                'soLuongXe' => $soLuongXe,
                'thoiGianNhan' => $thoiGianNhan,
                'thoiGianTra' => $thoiGianTra,
                'ghiChu' => $ghiChu
            ];
        } catch (\Exception $e) {
            if (!$isExternalTx && $conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }
}
