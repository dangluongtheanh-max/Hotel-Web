<?php

declare(strict_types=1);

namespace App\DatPhong\Repositories;

use Database\Database;
use PDO;

/**
 * Repository chuyên trách việc truy vấn và cập nhật thông tin người lưu trú (NguoiLuuTru).
 */
class NguoiLuuTruRepository
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
     * Tìm thông tin người lưu trú kèm thông tin phiếu để xác thực quyền sở hữu và trạng thái.
     */
    public function findNguoiLuuTruById(string $maNLT): ?array
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            SELECT nlt.*, pnp.MaKH, pnp.TrangThai AS TrangThaiPhieu
            FROM NguoiLuuTru nlt
            JOIN PhieuNhanPhong pnp ON nlt.MaPhieu = pnp.MaPhieu
            WHERE nlt.MaNguoiLuuTru = ?
        ");
        $stmt->execute([$maNLT]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Cập nhật thông tin chi tiết của người lưu trú.
     */
    public function capNhatNguoiLuuTru(
        string $maNLT,
        string $hoTen,
        string $ngaySinh,
        string $quocTich,
        string $loaiGiayTo,
        string $soGiayTo,
        ?string $ghiChu = null
    ): bool {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            UPDATE NguoiLuuTru
            SET HoTen = :hoTen,
                NgaySinh = :ngaySinh,
                QuocTich = :quocTich,
                LoaiGiayTo = :loaiGiayTo,
                SoGiayTo = :soGiayTo,
                GhiChu = :ghiChu
            WHERE MaNguoiLuuTru = :maNLT
        ");
        return $stmt->execute([
            ':hoTen' => $hoTen,
            ':ngaySinh' => $ngaySinh,
            ':quocTich' => $quocTich,
            ':loaiGiayTo' => $loaiGiayTo,
            ':soGiayTo' => $soGiayTo,
            ':ghiChu' => $ghiChu,
            ':maNLT' => $maNLT,
        ]);
    }
}
