<?php

declare(strict_types=1);

namespace App\Phong\Repositories;

use App\Phong\Models\LoaiPhong;
use App\Phong\Models\Phong;
use Database\Database;
use PDO;

/**
 * Repository cho module Phong.
 * Chịu trách nhiệm toàn bộ truy vấn CSDL của phòng vật lý và trạng thái phòng trống,
 * đồng thời đóng vai trò Facade tích hợp LoaiPhongRepository cho các truy vấn Loại phòng.
 */
class PhongRepository
{
    private Database $db;
    private LoaiPhongRepository $loaiPhongRepo;

    public function __construct(?Database $db = null, ?LoaiPhongRepository $loaiPhongRepo = null)
    {
        $this->db = $db ?? new Database();
        $this->loaiPhongRepo = $loaiPhongRepo ?? new LoaiPhongRepository($this->db);
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    public function getLoaiPhongRepo(): LoaiPhongRepository
    {
        return $this->loaiPhongRepo;
    }

    // =========================================================================
    // PHÂN HỆ PHÒNG VẬT LÝ (PHONG)
    // =========================================================================

    /**
     * Lấy danh sách tất cả các phòng vật lý kèm thông tin loại phòng
     */
    public function getAllPhong(array $filters = []): array
    {
        $db = $this->getConnection();
        $sql = "
            SELECT p.MaPhong, p.TrangThaiPhong, p.MaLoaiPhong, 
                   lp.TenLoaiPhong, lp.Gia, lp.SoNguoiToiDa
            FROM Phong p
            JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['MaLoaiPhong'])) {
            $sql .= " AND p.MaLoaiPhong = ?";
            $params[] = $filters['MaLoaiPhong'];
        }

        if (!empty($filters['TrangThaiPhong'])) {
            $sql .= " AND p.TrangThaiPhong = ?";
            $params[] = $filters['TrangThaiPhong'];
        }

        if (!empty($filters['MaPhong'])) {
            $sql .= " AND p.MaPhong = ?";
            $params[] = $filters['MaPhong'];
        }

        if (!empty($filters['keyword'])) {
            $kw = '%' . trim((string)$filters['keyword']) . '%';
            $sql .= " AND (p.MaPhong LIKE ? OR lp.TenLoaiPhong LIKE ?)";
            $params[] = $kw;
            $params[] = $kw;
        }

        $sql .= " ORDER BY p.MaLoaiPhong ASC, p.MaPhong ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tìm phòng vật lý theo mã phòng
     */
    public function findPhongById(string $id): ?array
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            SELECT p.MaPhong, p.TrangThaiPhong, p.MaLoaiPhong, 
                   lp.TenLoaiPhong, lp.Gia, lp.SoNguoiToiDa
            FROM Phong p
            JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
            WHERE p.MaPhong = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Thêm phòng vật lý mới
     */
    public function createPhong(Phong $room): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            INSERT INTO Phong (MaPhong, TrangThaiPhong, MaLoaiPhong)
            VALUES (?, ?, ?)
        ");
        return $stmt->execute([
            $room->getMaPhong(),
            $room->getTrangThaiPhong(),
            $room->getMaLoaiPhong()
        ]);
    }

    /**
     * Cập nhật thông tin phòng vật lý
     */
    public function updatePhong(Phong $room): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            UPDATE Phong 
            SET TrangThaiPhong = ?, MaLoaiPhong = ?
            WHERE MaPhong = ?
        ");
        return $stmt->execute([
            $room->getTrangThaiPhong(),
            $room->getMaLoaiPhong(),
            $room->getMaPhong()
        ]);
    }

    /**
     * Đếm số phòng trống thực tế của từng loại phòng trong khoảng ngày [checkIn, checkOut]
     * Không bị trùng lịch trong ChiTietPhieuNhanPhong với các phiếu chưa huỷ/từ chối
     */
    public function countAvailableRoomsByType(string $checkIn, string $checkOut): array
    {
        $db = $this->getConnection();
        $checkInTime = $checkIn . ' 14:00:00';
        $checkOutTime = $checkOut . ' 12:00:00';

        $sql = "
            SELECT p.MaLoaiPhong, COUNT(p.MaPhong) AS SoPhongTrong
            FROM Phong p
            WHERE p.TrangThaiPhong NOT IN ('BaoTri', 'ChoThanhToan')
              AND p.MaPhong NOT IN (
                  SELECT ctpnp.MaPhong
                  FROM ChiTietPhieuNhanPhong ctpnp
                  JOIN PhieuNhanPhong pnp ON ctpnp.MaPhieu = pnp.MaPhieu
                  WHERE pnp.TrangThai NOT IN ('DaHuy', 'ThanhToanThatBai')
                    AND ctpnp.TrangThai NOT IN ('DaHuy', 'ThanhToanThatBai')
                    AND ctpnp.ThoiGianNhanPhong < :checkOutTime
                    AND ctpnp.ThoiGianTraPhong > :checkInTime
              )
            GROUP BY p.MaLoaiPhong
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':checkInTime' => $checkInTime,
            ':checkOutTime' => $checkOutTime,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $counts = [];
        foreach ($rows as $r) {
            $counts[$r['MaLoaiPhong']] = (int) $r['SoPhongTrong'];
        }
        return $counts;
    }

    /**
     * Lấy danh sách các mã phòng vật lý còn trống của 1 loại phòng trong khoảng ngày
     */
    public function getAvailableRoomsByLoai(string $maLoaiPhong, string $checkIn, string $checkOut): array
    {
        $db = $this->getConnection();
        $checkInTime = $checkIn . ' 14:00:00';
        $checkOutTime = $checkOut . ' 12:00:00';

        $sql = "
            SELECT p.MaPhong
            FROM Phong p
            WHERE p.MaLoaiPhong = :maLoaiPhong
              AND p.TrangThaiPhong NOT IN ('BaoTri', 'ChoThanhToan')
              AND p.MaPhong NOT IN (
                  SELECT ctpnp.MaPhong
                  FROM ChiTietPhieuNhanPhong ctpnp
                  JOIN PhieuNhanPhong pnp ON ctpnp.MaPhieu = pnp.MaPhieu
                  WHERE pnp.TrangThai NOT IN ('DaHuy', 'ThanhToanThatBai')
                    AND ctpnp.TrangThai NOT IN ('DaHuy', 'ThanhToanThatBai')
                    AND ctpnp.ThoiGianNhanPhong < :checkOutTime
                    AND ctpnp.ThoiGianTraPhong > :checkInTime
              )
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':maLoaiPhong' => $maLoaiPhong,
            ':checkInTime' => $checkInTime,
            ':checkOutTime' => $checkOutTime,
        ]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Cập nhật trạng thái trực tiếp của 1 phòng (Trong, ChoThanhToan, DaDat, DangSuDung, BaoTri)
     */
    public function capNhatTrangThaiPhong(string $maPhong, string $trangThai, ?PDO $db = null): bool
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->prepare("UPDATE Phong SET TrangThaiPhong = ? WHERE MaPhong = ?");
        return $stmt->execute([$trangThai, $maPhong]);
    }

    // =========================================================================
    // FACADE DELEGATE SANG LOAIPHONG REPOSITORY
    // =========================================================================

    public function findLoaiPhongById(string $id): ?LoaiPhong
    {
        return $this->loaiPhongRepo->findLoaiPhongById($id);
    }

    public function getAllLoaiPhong(array $filters = []): array
    {
        return $this->loaiPhongRepo->getAllLoaiPhong($filters);
    }

    public function existsLoaiPhong(string $id): bool
    {
        return $this->loaiPhongRepo->existsLoaiPhong($id);
    }

    public function createLoaiPhong(LoaiPhong $loaiPhong): bool
    {
        return $this->loaiPhongRepo->createLoaiPhong($loaiPhong);
    }

    public function updateLoaiPhong(LoaiPhong $loaiPhong): bool
    {
        return $this->loaiPhongRepo->updateLoaiPhong($loaiPhong);
    }
}
