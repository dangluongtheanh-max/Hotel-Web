<?php

namespace App\KhuyenMai\Repositories;

use Database\Database;
use PDO;

/**
 * Repository cho module KhuyenMai.
 * Chịu trách nhiệm truy vấn và thao tác trực tiếp với bảng KhuyenMai trong CSDL.
 */
class KhuyenMaiRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Lấy toàn bộ danh sách khuyến mãi (Dành cho Quản trị viên).
     */
    public function getAll(): array
    {
        $sql = "SELECT MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, SoNguoiToiThieu, NgayBatDau, NgayKetThuc, TrangThai 
                FROM KhuyenMai 
                ORDER BY NgayBatDau DESC, MaKM ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy danh sách các mã đang có hiệu lực trong ngày.
     */
    public function getActivePromotions(?string $today = null): array
    {
        $today = $today ?? date('Y-m-d');
        $sql = "SELECT MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, SoNguoiToiThieu, NgayBatDau, NgayKetThuc, TrangThai 
                FROM KhuyenMai 
                WHERE TrangThai = 'HoatDong' 
                  AND :todayStart >= NgayBatDau 
                  AND :todayEnd <= NgayKetThuc 
                ORDER BY GiaTriGiam DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':todayStart' => $today,
            ':todayEnd' => $today
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy các chương trình ưu đãi sắp diễn ra (sự kiện Admin set trong tương lai, VD: Flash Sale 12/12).
     */
    public function getUpcomingPromotions(?string $today = null): array
    {
        $today = $today ?? date('Y-m-d');
        $sql = "SELECT MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, SoNguoiToiThieu, NgayBatDau, NgayKetThuc, TrangThai 
                FROM KhuyenMai 
                WHERE TrangThai = 'HoatDong' 
                  AND NgayBatDau > :today 
                ORDER BY NgayBatDau ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':today' => $today]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tìm khuyến mãi theo mã định danh.
     */
    public function findByMaKM(string $maKM): ?array
    {
        $sql = "SELECT MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, SoNguoiToiThieu, NgayBatDau, NgayKetThuc, TrangThai 
                FROM KhuyenMai 
                WHERE MaKM = :maKM 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':maKM' => $maKM]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Tạo mới mã khuyến mãi (Admin).
     */
    public function create(array $data): bool
    {
        $sql = "INSERT INTO KhuyenMai (MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, SoNguoiToiThieu, NgayBatDau, NgayKetThuc, TrangThai) 
                VALUES (:maKM, :tenKM, :loaiKM, :giaTriGiam, :soDemToiThieu, :soNguoiToiThieu, :ngayBatDau, :ngayKetThuc, :trangThai)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':maKM' => $data['MaKM'],
            ':tenKM' => $data['TenKM'],
            ':loaiKM' => $data['LoaiKM'],
            ':giaTriGiam' => $data['GiaTriGiam'],
            ':soDemToiThieu' => $data['SoDemToiThieu'] ?? 0,
            ':soNguoiToiThieu' => $data['SoNguoiToiThieu'] ?? 0,
            ':ngayBatDau' => $data['NgayBatDau'],
            ':ngayKetThuc' => $data['NgayKetThuc'],
            ':trangThai' => $data['TrangThai'] ?? 'HoatDong',
        ]);
    }

    /**
     * Cập nhật thông tin khuyến mãi (Admin).
     */
    public function update(string $maKM, array $data): bool
    {
        $sql = "UPDATE KhuyenMai 
                SET TenKM = :tenKM, 
                    LoaiKM = :loaiKM, 
                    GiaTriGiam = :giaTriGiam, 
                    SoDemToiThieu = :soDemToiThieu, 
                    SoNguoiToiThieu = :soNguoiToiThieu, 
                    NgayBatDau = :ngayBatDau, 
                    NgayKetThuc = :ngayKetThuc, 
                    TrangThai = :trangThai 
                WHERE MaKM = :maKM";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':tenKM' => $data['TenKM'],
            ':loaiKM' => $data['LoaiKM'],
            ':giaTriGiam' => $data['GiaTriGiam'],
            ':soDemToiThieu' => $data['SoDemToiThieu'] ?? 0,
            ':soNguoiToiThieu' => $data['SoNguoiToiThieu'] ?? 0,
            ':ngayBatDau' => $data['NgayBatDau'],
            ':ngayKetThuc' => $data['NgayKetThuc'],
            ':trangThai' => $data['TrangThai'] ?? 'HoatDong',
            ':maKM' => $maKM,
        ]);
    }

    /**
     * Cập nhật nhanh trạng thái Bật / Tắt (HoatDong / NgungHoatDong).
     */
    public function updateStatus(string $maKM, string $trangThai): bool
    {
        $sql = "UPDATE KhuyenMai SET TrangThai = :trangThai WHERE MaKM = :maKM";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':trangThai' => $trangThai,
            ':maKM' => $maKM
        ]);
    }

    /**
     * Xóa mã khuyến mãi.
     */
    public function delete(string $maKM): bool
    {
        $sql = "DELETE FROM KhuyenMai WHERE MaKM = :maKM";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':maKM' => $maKM]);
    }
}
