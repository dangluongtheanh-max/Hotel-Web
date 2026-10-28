<?php

declare(strict_types=1);

namespace App\DanhGia\Repositories;

use App\DanhGia\Models\DanhGia;
use Database\Database;
use PDO;

/**
 * Repository xử lý toàn bộ các thao tác CSDL cho module Đánh Giá.
 * Gồm 2 phần nghiệp vụ chính: Tra cứu dữ liệu (Query) và Thao tác dữ liệu (Command).
 */
class DanhGiaXuLyRepository
{
    private ?Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    private function getConnection(): PDO
    {
        return Database::getConnection();
    }

    // =========================================================================
    // PHẦN 1: TRA CỨU & TRUY VẤN ĐÁNH GIÁ (READ / QUERY)
    // =========================================================================

    /**
     * Tìm đánh giá theo mã đánh giá (Primary Key).
     */
    public function findDanhGiaById(string $id): ?DanhGia
    {
        $stmt = $this->getConnection()->prepare("SELECT * FROM DanhGia WHERE MaDanhGia = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return new DanhGia(
            $row['MaDanhGia'],
            (int) $row['SoSao'],
            $row['NoiDung'] ?? '',
            new \DateTime($row['NgayDanhGia']),
            $row['MaKH'],
            $row['MaPhieu'],
            $row['MaPhong']
        );
    }

    /**
     * Tìm đánh giá cụ thể theo mã phiếu nhận phòng và mã phòng.
     * Dùng để kiểm tra chống trùng lặp đánh giá.
     */
    public function findByPhieuVaPhong(string $maPhieu, string $maPhong): ?DanhGia
    {
        $stmt = $this->getConnection()->prepare("SELECT * FROM DanhGia WHERE MaPhieu = ? AND MaPhong = ?");
        $stmt->execute([$maPhieu, $maPhong]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return new DanhGia(
            $row['MaDanhGia'],
            (int) $row['SoSao'],
            $row['NoiDung'] ?? '',
            new \DateTime($row['NgayDanhGia']),
            $row['MaKH'],
            $row['MaPhieu'],
            $row['MaPhong']
        );
    }

    /**
     * Lấy danh sách đánh giá của một phiếu nhận phòng.
     */
    public function getDanhGiaByPhieu(string $maPhieu): array
    {
        $stmt = $this->getConnection()->prepare("SELECT * FROM DanhGia WHERE MaPhieu = ? ORDER BY NgayDanhGia DESC");
        $stmt->execute([$maPhieu]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy danh sách đánh giá theo các bộ lọc tùy chọn (MaKH, MaPhieu, MaPhong).
     */
    public function getAllDanhGia(array $filters = []): array
    {
        $sql = "SELECT * FROM DanhGia WHERE 1=1";
        $params = [];

        if (!empty($filters['MaKH'])) {
            $sql .= " AND MaKH = ?";
            $params[] = $filters['MaKH'];
        }
        if (!empty($filters['MaPhieu'])) {
            $sql .= " AND MaPhieu = ?";
            $params[] = $filters['MaPhieu'];
        }
        if (!empty($filters['MaPhong'])) {
            $sql .= " AND MaPhong = ?";
            $params[] = $filters['MaPhong'];
        }

        $sql .= " ORDER BY NgayDanhGia DESC";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // PHẦN 2: THAO TÁC & KIỂM TRA ĐIỀU KIỆN (WRITE / COMMAND)
    // =========================================================================

    /**
     * Sinh mã đánh giá tự động dạng DGxxx kế tiếp.
     */
    public function generateMaDanhGia(): string
    {
        $stmt = $this->getConnection()->query("SELECT MaDanhGia FROM DanhGia WHERE MaDanhGia LIKE 'DG%' ORDER BY CAST(SUBSTRING(MaDanhGia, 3) AS UNSIGNED) DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next = ($row && !empty($row['MaDanhGia'])) ? ((int) substr($row['MaDanhGia'], 2)) + 1 : 1;
        return sprintf('DG%03d', $next);
    }

    /**
     * Kiểm tra trạng thái phòng và quyền sở hữu phiếu nhận phòng.
     * Phòng phải tồn tại trong phiếu và có trạng thái trả phòng 'DaTraPhong'.
     */
    public function kiemTraDieuKienDanhGia(string $maPhieu, string $maPhong): ?array
    {
        $stmt = $this->getConnection()->prepare("
            SELECT ctpnp.TrangThai AS TrangThaiPhong, ctpnp.ThoiGianTraPhong,
                   pnp.MaKH, pnp.TrangThai AS TrangThaiPhieu
            FROM ChiTietPhieuNhanPhong ctpnp
            JOIN PhieuNhanPhong pnp ON ctpnp.MaPhieu = pnp.MaPhieu
            WHERE ctpnp.MaPhieu = ? AND ctpnp.MaPhong = ?
        ");
        $stmt->execute([$maPhieu, $maPhong]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Lưu thông tin đánh giá (INSERT mới nếu chưa có, UPDATE nếu đã tồn tại).
     */
    public function saveDanhGia(DanhGia $danhGia): bool
    {
        $existing = $this->findDanhGiaById($danhGia->getMaDanhGia());
        if ($existing) {
            $stmt = $this->getConnection()->prepare("UPDATE DanhGia SET SoSao = ?, NoiDung = ?, NgayDanhGia = ?, MaKH = ?, MaPhieu = ?, MaPhong = ? WHERE MaDanhGia = ?");
            return $stmt->execute([
                $danhGia->getSoSao(),
                $danhGia->getNoiDung(),
                $danhGia->getNgayDanhGia()->format('Y-m-d'),
                $danhGia->getMaKH(),
                $danhGia->getMaPhieu(),
                $danhGia->getMaPhong(),
                $danhGia->getMaDanhGia()
            ]);
        }

        $stmt = $this->getConnection()->prepare("INSERT INTO DanhGia (MaDanhGia, SoSao, NoiDung, NgayDanhGia, MaKH, MaPhieu, MaPhong) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $danhGia->getMaDanhGia(),
            $danhGia->getSoSao(),
            $danhGia->getNoiDung(),
            $danhGia->getNgayDanhGia()->format('Y-m-d'),
            $danhGia->getMaKH(),
            $danhGia->getMaPhieu(),
            $danhGia->getMaPhong()
        ]);
    }

    /**
     * Xóa đánh giá theo mã đánh giá.
     */
    public function deleteDanhGia(string $id): bool
    {
        $stmt = $this->getConnection()->prepare("DELETE FROM DanhGia WHERE MaDanhGia = ?");
        return $stmt->execute([$id]);
    }
}
