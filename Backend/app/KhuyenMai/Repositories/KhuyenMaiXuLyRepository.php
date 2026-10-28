<?php

declare(strict_types=1);

namespace App\KhuyenMai\Repositories;

use App\KhuyenMai\Models\KhuyenMai;
use Database\Database;
use PDO;

/**
 * Repository xử lý toàn bộ truy vấn CSDL cho module KhuyenMai.
 * Bao gồm 2 phần nghiệp vụ: Tra cứu hiệu lực mã (Query) và Thao tác quản trị mã (Command).
 */
class KhuyenMaiXuLyRepository
{
    private PDO $db;

    public function __construct($db = null)
    {
        if ($db instanceof PDO) {
            $this->db = $db;
        } elseif ($db instanceof Database) {
            $this->db = Database::getConnection();
        } else {
            $this->db = Database::getConnection();
        }
    }

    public function getConnection(): PDO
    {
        return $this->db;
    }

    // =========================================================================
    // PHẦN 1: TRA CỨU & KIỂM TRA HIỆU LỰC KHUYẾN MÃI (READ / QUERY)
    // =========================================================================

    /**
     * Lấy toàn bộ danh sách khuyến mãi (Dành cho Quản trị viên).
     */
    public function getAll(): array
    {
        $sql = "SELECT MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, SoLuong, SoLuongDaDung, NgayBatDau, NgayKetThuc, TrangThai 
                FROM KhuyenMai 
                ORDER BY NgayBatDau DESC, MaKM ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy danh sách các mã đang có hiệu lực trong ngày và còn lượt sử dụng.
     */
    public function getActivePromotions(?string $today = null): array
    {
        $today = $today ?? date('Y-m-d');
        $sql = "SELECT MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, SoLuong, SoLuongDaDung, NgayBatDau, NgayKetThuc, TrangThai 
                FROM KhuyenMai 
                WHERE TrangThai = 'HoatDong' 
                  AND :todayStart >= NgayBatDau 
                  AND :todayEnd <= NgayKetThuc 
                  AND (SoLuongDaDung < SoLuong)
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
        $sql = "SELECT MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, SoLuong, SoLuongDaDung, NgayBatDau, NgayKetThuc, TrangThai 
                FROM KhuyenMai 
                WHERE TrangThai = 'HoatDong' 
                  AND NgayBatDau > :today 
                ORDER BY NgayBatDau ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':today' => $today]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tìm khuyến mãi theo mã định danh (trả về mảng).
     */
    public function findByMaKM(string $maKM): ?array
    {
        $sql = "SELECT MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, SoLuong, SoLuongDaDung, NgayBatDau, NgayKetThuc, TrangThai 
                FROM KhuyenMai 
                WHERE MaKM = :maKM 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':maKM' => $maKM]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Tìm khuyến mãi theo mã định danh và trả về đối tượng Model KhuyenMai.
     */
    public function findKhuyenMaiById(string $maKM): ?KhuyenMai
    {
        $row = $this->findByMaKM($maKM);
        if (!$row) {
            return null;
        }
        return KhuyenMai::fromArray($row);
    }

    // =========================================================================
    // PHẦN 2: THAO TÁC THÊM, CẬP NHẬT, ĐỔI TRẠNG THÁI & XÓA (WRITE / COMMAND)
    // =========================================================================

    /**
     * Tạo mới mã khuyến mãi (Admin).
     */
    public function create(array $data): bool
    {
        $sql = "INSERT INTO KhuyenMai (MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, SoLuong, SoLuongDaDung, NgayBatDau, NgayKetThuc, TrangThai) 
                VALUES (:maKM, :tenKM, :loaiKM, :giaTriGiam, :soDemToiThieu, :soLuong, :soLuongDaDung, :ngayBatDau, :ngayKetThuc, :trangThai)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':maKM' => $data['MaKM'],
            ':tenKM' => $data['TenKM'],
            ':loaiKM' => $data['LoaiKM'],
            ':giaTriGiam' => $data['GiaTriGiam'],
            ':soDemToiThieu' => $data['SoDemToiThieu'] ?? 0,
            ':soLuong' => (int)($data['SoLuong'] ?? 100),
            ':soLuongDaDung' => (int)($data['SoLuongDaDung'] ?? 0),
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
                    SoLuong = :soLuong,
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
            ':soLuong' => (int)($data['SoLuong'] ?? 100),
            ':ngayBatDau' => $data['NgayBatDau'],
            ':ngayKetThuc' => $data['NgayKetThuc'],
            ':trangThai' => $data['TrangThai'] ?? 'HoatDong',
            ':maKM' => $maKM,
        ]);
    }

    /**
     * Tăng số lượng đã dùng của khuyến mãi khi khách đặt phòng thành công.
     */
    public function tangSoLuongDaDung(string $maKM, ?PDO $db = null): bool
    {
        $targetDb = $db ?? $this->db;
        $sql = "UPDATE KhuyenMai 
                SET SoLuongDaDung = SoLuongDaDung + 1 
                WHERE MaKM = :maKM AND SoLuongDaDung < SoLuong";
        $stmt = $targetDb->prepare($sql);
        return $stmt->execute([':maKM' => $maKM]);
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
