<?php

declare(strict_types=1);

namespace App\Phong\Repositories;

use App\Phong\Models\LoaiPhong;
use Database\Database;
use PDO;

/**
 * Repository chuyên trách quản lý dữ liệu Loại phòng (LoaiPhong).
 */
class LoaiPhongRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    public function findLoaiPhongById(string $id): ?LoaiPhong
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("SELECT * FROM LoaiPhong WHERE MaLoaiPhong = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return new LoaiPhong(
            $row['MaLoaiPhong'],
            $row['TenLoaiPhong'],
            (float) $row['Gia'],
            (int) $row['SoNguoiToiDa'],
            $row['MoTa'] ?? '',
            $row['TienNghi'] ?? ''
        );
    }

    public function getAllLoaiPhong(array $filters = []): array
    {
        $db = $this->getConnection();
        $sql = "SELECT * FROM LoaiPhong WHERE 1=1";
        $params = [];

        if (!empty($filters['MaLoaiPhong'])) {
            $sql .= " AND MaLoaiPhong = ?";
            $params[] = $filters['MaLoaiPhong'];
        }

        if (!empty($filters['keyword'])) {
            $kw = '%' . trim((string)$filters['keyword']) . '%';
            $sql .= " AND (TenLoaiPhong LIKE ? OR MoTa LIKE ? OR TienNghi LIKE ?)";
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $sql .= " ORDER BY MaLoaiPhong ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[] = new LoaiPhong(
                $row['MaLoaiPhong'],
                $row['TenLoaiPhong'],
                (float) $row['Gia'],
                (int) $row['SoNguoiToiDa'],
                $row['MoTa'] ?? '',
                $row['TienNghi'] ?? ''
            );
        }
        return $result;
    }

    public function existsLoaiPhong(string $id): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM LoaiPhong WHERE MaLoaiPhong = ?");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function createLoaiPhong(LoaiPhong $loaiPhong): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            INSERT INTO LoaiPhong (MaLoaiPhong, TenLoaiPhong, Gia, SoNguoiToiDa, MoTa, TienNghi, TinhTrang)
            VALUES (?, ?, ?, ?, ?, ?, 'ConPhong')
        ");
        return $stmt->execute([
            $loaiPhong->getMaLoaiPhong(),
            $loaiPhong->getTenLoaiPhong(),
            $loaiPhong->getGia(),
            $loaiPhong->getSoNguoiToiDa(),
            $loaiPhong->getMoTa(),
            $loaiPhong->getTienNghi()
        ]);
    }

    public function updateLoaiPhong(LoaiPhong $loaiPhong): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            UPDATE LoaiPhong 
            SET TenLoaiPhong = ?, Gia = ?, SoNguoiToiDa = ?, MoTa = ?, TienNghi = ?
            WHERE MaLoaiPhong = ?
        ");
        return $stmt->execute([
            $loaiPhong->getTenLoaiPhong(),
            $loaiPhong->getGia(),
            $loaiPhong->getSoNguoiToiDa(),
            $loaiPhong->getMoTa(),
            $loaiPhong->getTienNghi(),
            $loaiPhong->getMaLoaiPhong()
        ]);
    }
}
