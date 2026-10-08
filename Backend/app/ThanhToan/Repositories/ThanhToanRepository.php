<?php

namespace App\ThanhToan\Repositories;

use App\ThanhToan\Models\ThanhToan;
use Database\Database;
use PDO;
use DateTimeImmutable;

/**
 * Repository cho module ThanhToan.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class ThanhToanRepository
{
    private PDO $db;

    public function __construct(?Database $db = null)
    {
        $this->db = Database::getConnection();
    }

    public function findThanhToanById(string $id): ?ThanhToan
    {
        $stmt = $this->db->prepare("SELECT * FROM ThanhToan WHERE MaThanhToan = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapToModel($row);
    }

    public function findByMaHD(string $maHD): array
    {
        $stmt = $this->db->prepare("SELECT * FROM ThanhToan WHERE MaHD = ? ORDER BY ThoiGianThanhToan DESC");
        $stmt->execute([$maHD]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapToModel'], $rows);
    }

    public function getAllThanhToan(array $filters = []): array
    {
        $sql = "SELECT * FROM ThanhToan WHERE 1=1";
        $params = [];

        if (!empty($filters['MaHD'])) {
            $sql .= " AND MaHD = ?";
            $params[] = $filters['MaHD'];
        }
        if (!empty($filters['HinhThuc'])) {
            $sql .= " AND HinhThuc = ?";
            $params[] = $filters['HinhThuc'];
        }
        if (!empty($filters['TrangThaiThanhToan'])) {
            $sql .= " AND TrangThaiThanhToan = ?";
            $params[] = $filters['TrangThaiThanhToan'];
        }
        if (!empty($filters['TuNgay'])) {
            $sql .= " AND ThoiGianThanhToan >= ?";
            $params[] = $filters['TuNgay'];
        }
        if (!empty($filters['DenNgay'])) {
            $sql .= " AND ThoiGianThanhToan <= ?";
            $params[] = $filters['DenNgay'];
        }

        $sql .= " ORDER BY ThoiGianThanhToan DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapToModel'], $rows);
    }

    public function saveThanhToan(ThanhToan $thanhToan): bool
    {
        $existing = $this->findThanhToanById($thanhToan->getMaThanhToan());
        $timeStr = $thanhToan->getThoiGianThanhToan()->format('Y-m-d H:i:s');

        if ($existing) {
            $stmt = $this->db->prepare("UPDATE ThanhToan SET SoTien = ?, ThoiGianThanhToan = ?, HinhThuc = ?, TrangThaiThanhToan = ?, MaHD = ? WHERE MaThanhToan = ?");
            return $stmt->execute([
                $thanhToan->getSoTien(),
                $timeStr,
                $thanhToan->getHinhThuc(),
                $thanhToan->getTrangThaiThanhToan(),
                $thanhToan->getMaHD(),
                $thanhToan->getMaThanhToan()
            ]);
        }

        $stmt = $this->db->prepare("INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $thanhToan->getMaThanhToan(),
            $thanhToan->getSoTien(),
            $timeStr,
            $thanhToan->getHinhThuc(),
            $thanhToan->getTrangThaiThanhToan(),
            $thanhToan->getMaHD()
        ]);
    }

    public function updateTrangThai(string $id, string $trangThai): bool
    {
        $stmt = $this->db->prepare("UPDATE ThanhToan SET TrangThaiThanhToan = ? WHERE MaThanhToan = ?");
        return $stmt->execute([$trangThai, $id]);
    }

    public function deleteThanhToan(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM ThanhToan WHERE MaThanhToan = ?");
        return $stmt->execute([$id]);
    }

    private function mapToModel(array $row): ThanhToan
    {
        return new ThanhToan(
            $row['MaThanhToan'],
            (float) $row['SoTien'],
            new DateTimeImmutable($row['ThoiGianThanhToan']),
            $row['HinhThuc'],
            $row['TrangThaiThanhToan'],
            $row['MaHD']
        );
    }
}
